# 🛡️ StratEdge AntiBot — Guide d'intégration (15 min)

## Étape 1 — Cloudflare Turnstile (5 min)
1. dash.cloudflare.com → Turnstile → Add site → domaine `stratedgepronos.fr`, mode **Managed** (invisible pour 95% des humains).
2. Récupérer Site Key + Secret Key → les mettre dans ta config serveur (JAMAIS dans le code) :
```php
// config.php (hors webroot idéalement)
define('TURNSTILE_SITE_KEY',   '<TA_SITE_KEY>');
define('TURNSTILE_SECRET_KEY', '<TA_SECRET_KEY>');
define('ANTIBOT_HMAC_KEY',     '<CHAINE_ALEATOIRE_64_CARS>'); // openssl rand -hex 32
```

## Étape 2 — Base de données (1 min)
```bash
mysql -u <user> -p <db> < schema.sql
```

## Étape 3 — Formulaire d'inscription (3 min)
Dans le template du formulaire :
```php
<form method="post" action="/inscription">
  ...tes champs...
  <?= AntiBot::formFields() ?>       <!-- honeypot + timestamp signé -->
  <?= AntiBot::turnstileWidget() ?>  <!-- captcha invisible -->
  <button type="submit">Créer mon compte</button>
</form>
```

## Étape 4 — Traitement du POST (3 min)
Tout en haut du handler d'inscription :
```php
require_once __DIR__.'/antibot/AntiBot.php';
$antibot = new AntiBot($pdo);
$ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR']; // CF-aware

if (!$antibot->validate($_POST, $ip)) {
    $errs = $antibot->getErrors();
    if (empty($errs)) {
        // honeypot/timing : on fait CROIRE au bot que ça a marché
        header('Location: /inscription/merci'); exit;
    }
    // sinon afficher $errs à l'utilisateur et re-render le formulaire
}
// ... création du compte seulement si validate() === true
```

## Étape 5 — Vérification email obligatoire (si pas déjà fait)
Compte créé avec `email_verified = 0` + lien de confirmation (token 32+ chars, expiration 24h).
Un compte non vérifié sous 72h → purge automatique (cron quotidien) :
```sql
DELETE FROM users WHERE email_verified = 0 AND deleted_at IS NULL
  AND created_at < NOW() - INTERVAL 72 HOUR AND last_login IS NULL;
```

## Étape 6 — Nettoyage des 460 bots existants
```bash
php cleanup_bots.php --dry-run    # TOUJOURS d'abord : vérifier la liste
php cleanup_bots.php --execute    # soft delete (colonne deleted_at)
```
⚠️ Adapter les noms de tables/colonnes du script à ton schéma avant de lancer.

## Bonus — monitoring
```sql
-- Qui attaque, par couche, dernières 24h
SELECT result, COUNT(*) FROM antibot_log
WHERE created_at > NOW() - INTERVAL 24 HOUR GROUP BY result;
```
Si une IP insiste : bannir au niveau Cloudflare (Security → WAF → IP Access Rules),
c'est gratuit et ça n'atteint même plus ton serveur.
