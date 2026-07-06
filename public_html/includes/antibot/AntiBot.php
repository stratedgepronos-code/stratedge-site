<?php
/**
 * StratEdge AntiBot v1.0 — protection inscription multi-couches
 * Couches : 1) Cloudflare Turnstile  2) Honeypot  3) Timing minimal
 *           4) Rate limit IP  5) Blocklist emails jetables
 *
 * Intégration : voir INTEGRATION.md
 * IMPORTANT : définir les clés dans config (jamais en dur) :
 *   define('TURNSTILE_SITE_KEY',   getenv('TURNSTILE_SITE_KEY'));
 *   define('TURNSTILE_SECRET_KEY', getenv('TURNSTILE_SECRET_KEY'));
 */

class AntiBot
{
    private PDO $db;
    private array $errors = [];

    // Réglages
    private const MIN_FORM_SECONDS   = 4;     // un humain met > 4s à remplir
    private const MAX_FORM_SECONDS   = 3600;  // token de plus d'1h = rejoué
    private const RATE_LIMIT_MAX     = 3;     // inscriptions max...
    private const RATE_LIMIT_WINDOW  = 3600;  // ...par IP par heure
    private const HONEYPOT_FIELD     = 'website_url'; // nom "appétissant" pour les bots

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /** À appeler AU DÉBUT du traitement du POST d'inscription.
     *  Retourne true si humain, false sinon ($this->getErrors() pour le détail). */
    public function validate(array $post, string $ip): bool
    {
        $this->errors = [];

        // --- Couche 1 : Honeypot (champ caché, un humain ne le remplit jamais)
        if (!empty($post[self::HONEYPOT_FIELD])) {
            $this->fail('honeypot', $ip);
            return false; // silencieux : on répond "OK" au bot sans créer le compte
        }

        // --- Couche 2 : Timing (timestamp signé injecté au rendu du formulaire)
        if (!$this->checkTiming($post['fts'] ?? '')) {
            $this->fail('timing', $ip);
            return false;
        }

        // --- Couche 3 : Rate limit par IP
        if (!$this->checkRateLimit($ip)) {
            $this->errors[] = "Trop de tentatives. Réessaie dans une heure.";
            $this->fail('ratelimit', $ip);
            return false;
        }

        // --- Couche 4 : Email jetable
        $email = strtolower(trim($post['email'] ?? ''));
        if ($this->isDisposableEmail($email)) {
            $this->errors[] = "Les adresses email jetables ne sont pas acceptées.";
            $this->fail('disposable', $ip);
            return false;
        }

        // --- Couche 5 : Cloudflare Turnstile (challenge invisible)
        if (!$this->verifyTurnstile($post['cf-turnstile-response'] ?? '', $ip)) {
            $this->errors[] = "Vérification de sécurité échouée. Recharge la page et réessaie.";
            $this->fail('turnstile', $ip);
            return false;
        }

        $this->logAttempt($ip, 'pass');
        return true;
    }

    /** Champs à injecter dans le <form> (honeypot + timestamp signé). */
    public static function formFields(): string
    {
        $ts  = time();
        $sig = hash_hmac('sha256', (string)$ts, self::hmacKey());
        $hp  = self::HONEYPOT_FIELD;
        return <<<HTML
        <input type="hidden" name="fts" value="{$ts}.{$sig}">
        <div style="position:absolute;left:-9999px;top:-9999px;" aria-hidden="true">
          <label for="{$hp}">Ne pas remplir</label>
          <input type="text" id="{$hp}" name="{$hp}" tabindex="-1" autocomplete="off">
        </div>
        HTML;
    }

    /** Widget Turnstile à placer avant le bouton submit. */
    public static function turnstileWidget(): string
    {
        $siteKey = TURNSTILE_SITE_KEY;
        return <<<HTML
        <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
        <div class="cf-turnstile" data-sitekey="{$siteKey}" data-theme="dark"></div>
        HTML;
    }

    // ------------------------------------------------------------------ privé

    private function checkTiming(string $fts): bool
    {
        if (!str_contains($fts, '.')) return false;
        [$ts, $sig] = explode('.', $fts, 2);
        if (!hash_equals(hash_hmac('sha256', $ts, self::hmacKey()), $sig)) return false;
        $elapsed = time() - (int)$ts;
        return $elapsed >= self::MIN_FORM_SECONDS && $elapsed <= self::MAX_FORM_SECONDS;
    }

    private function checkRateLimit(string $ip): bool
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM antibot_log
             WHERE ip = ? AND created_at > (NOW() - INTERVAL " . self::RATE_LIMIT_WINDOW . " SECOND)"
        );
        $stmt->execute([$ip]);
        return (int)$stmt->fetchColumn() < self::RATE_LIMIT_MAX;
    }

    private function verifyTurnstile(string $token, string $ip): bool
    {
        if ($token === '') return false;
        $ch = curl_init('https://challenges.cloudflare.com/turnstile/v0/siteverify');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query([
                'secret'   => TURNSTILE_SECRET_KEY,
                'response' => $token,
                'remoteip' => $ip,
            ]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 8,
        ]);
        $res = curl_exec($ch);
        curl_close($ch);
        if ($res === false) return false;
        $json = json_decode($res, true);
        return ($json['success'] ?? false) === true;
    }

    private function isDisposableEmail(string $email): bool
    {
        $at = strrchr($email, '@');
        if ($at === false) return true; // email invalide → rejet
        $domain = substr($at, 1);
        $blocklist = self::loadBlocklist();
        return in_array($domain, $blocklist, true);
    }

    private static function loadBlocklist(): array
    {
        static $list = null;
        if ($list === null) {
            $file = __DIR__ . '/disposable_domains.txt';
            $list = is_file($file)
                ? array_filter(array_map('trim', file($file)))
                : [];
        }
        return $list;
    }

    private function fail(string $layer, string $ip): void
    {
        $this->logAttempt($ip, $layer);
    }

    private function logAttempt(string $ip, string $result): void
    {
        $stmt = $this->db->prepare(
            "INSERT INTO antibot_log (ip, result, user_agent, created_at)
             VALUES (?, ?, ?, NOW())"
        );
        $stmt->execute([$ip, $result, substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255)]);
    }

    private static function hmacKey(): string
    {
        // Réutiliser une clé secrète serveur existante (config), jamais en dur
        return defined('ANTIBOT_HMAC_KEY') ? ANTIBOT_HMAC_KEY : 'CHANGE_ME_IN_CONFIG';
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
