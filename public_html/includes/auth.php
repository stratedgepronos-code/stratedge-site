<?php
// ============================================================
// STRATEDGE — Authentification & Sessions
// ============================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_secure'   => true,
        'cookie_samesite' => 'Strict',
    ]);
}

require_once __DIR__ . '/db.php';

// ── Vérifier si l'utilisateur est connecté ─────────────────
function isLoggedIn(): bool {
    return isset($_SESSION['membre_id']) && !empty($_SESSION['membre_id']);
}

// ── Vérifier si l'admin est connecté ──────────────────────
function isAdmin(): bool {
    return isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
}

// ── Vérifier si c'est le SUPER admin (email principal) ────
function isSuperAdmin(): bool {
    return isAdmin() && isset($_SESSION['membre_email']) && $_SESSION['membre_email'] === ADMIN_EMAIL;
}

// ── Obliger le super admin ─────────────────────────────────
function requireSuperAdmin(): void {
    if (!isSuperAdmin()) {
        header('Location: /admin/index.php');
        exit;
    }
}

// ── Obliger la connexion (redirige sinon) ──────────────────
function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: /login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
        exit;
    }
}

// ── Obliger l'admin ────────────────────────────────────────
function requireAdmin(): void {
    if (!isAdmin()) {
        header('Location: /login.php');
        exit;
    }
}

// ── Connexion d'un membre ──────────────────────────────────
function loginMembre(string $email, string $password): array {
    // ── Rate limiting anti brute-force ───────────────────────
    $ip       = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $cacheKey = sys_get_temp_dir() . '/stratedge_login_' . md5($ip) . '.json';
    $attempts = 0;
    $firstAt  = 0;

    if (file_exists($cacheKey)) {
        $data     = json_decode(file_get_contents($cacheKey), true) ?: [];
        $attempts = $data['attempts'] ?? 0;
        $firstAt  = $data['first_at'] ?? 0;
        // Reset après 15 minutes
        if (time() - $firstAt > 900) { $attempts = 0; $firstAt = 0; }
    }

    // Bloquer après 10 tentatives en 15 min
    if ($attempts >= 10) {
        $wait = 900 - (time() - $firstAt);
        return ['success' => false, 'error' => "Trop de tentatives. Réessayez dans " . ceil($wait/60) . " minute(s)."];
    }

    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM membres WHERE email = ? AND actif = 1 AND banni = 0 LIMIT 1");
    $stmt->execute([$email]);
    $membre = $stmt->fetch();

    if (!$membre || !password_verify($password, $membre['password'])) {
        // Incrémenter le compteur
        $attempts++;
        file_put_contents($cacheKey, json_encode([
            'attempts' => $attempts,
            'first_at' => $firstAt ?: time(),
        ]));
        // Délai progressif anti-timing attack
        sleep(min($attempts, 3));
        return ['success' => false, 'error' => 'Email ou mot de passe incorrect.'];
    }

    // Login réussi → reset compteur
    @unlink($cacheKey);

    // Régénérer l'ID de session après login (protection contre session fixation)
    session_regenerate_id(true);

    $_SESSION['membre_id']    = $membre['id'];
    $_SESSION['membre_nom']   = $membre['nom'];
    $_SESSION['membre_email'] = $membre['email'];
    $_SESSION['is_admin']     = ($membre['email'] === ADMIN_EMAIL || ($membre['role'] ?? '') === 'admin');
    $_SESSION['login_time']   = time();
    $_SESSION['user_agent']   = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 200);

    return ['success' => true];
}

// ── Inscription d'un membre ────────────────────────────────
function registerMembre(string $nom, string $email, string $password): array {
    $db = getDB();

    // Vérifier si l'email existe déjà
    $stmt = $db->prepare("SELECT id FROM membres WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        return ['success' => false, 'error' => 'Cet email est déjà utilisé.'];
    }

    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    $stmt = $db->prepare("INSERT INTO membres (nom, email, password) VALUES (?, ?, ?)");
    $stmt->execute([$nom, $email, $hash]);

    return ['success' => true, 'id' => $db->lastInsertId()];
}

// ── Déconnexion ────────────────────────────────────────────
function logoutMembre(): void {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
}

// ── Récupérer le membre connecté ──────────────────────────
function getMembre(): ?array {
    if (!isLoggedIn()) return null;
    $db = getDB();
    // Essayer avec photo_profil (migration effectuée), sinon fallback sans
    try {
        $stmt = $db->prepare("SELECT id, nom, email, date_inscription, photo_profil FROM membres WHERE id = ? LIMIT 1");
        $stmt->execute([$_SESSION['membre_id']]);
    } catch (Exception $e) {
        $stmt = $db->prepare("SELECT id, nom, email, date_inscription FROM membres WHERE id = ? LIMIT 1");
        $stmt->execute([$_SESSION['membre_id']]);
    }
    $row = $stmt->fetch();
    if ($row && !isset($row['photo_profil'])) $row['photo_profil'] = null;
    return $row ?: null;
}

/**
 * URL de la photo de profil d'un membre (fallback initiale)
 */
function getAvatarUrl(?array $membre): string {
    if (!empty($membre['photo_profil'])) {
        return '/uploads/avatars/' . htmlspecialchars($membre['photo_profil']);
    }
    return '';
}

// ── Vérifier si un membre a un abonnement actif ────────────
function getAbonnementActif(int $membreId): ?array {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT * FROM abonnements
        WHERE membre_id = ?
          AND actif = 1
          AND (
            type = 'daily'
            OR type = 'rasstoss'
            OR (type IN ('weekend','weekly','tennis') AND date_fin > NOW())
          )
        ORDER BY date_achat DESC
        LIMIT 1
    ");
    $stmt->execute([$membreId]);
    return $stmt->fetch() ?: null;
}

// ── Vérifier type d'accès ──────────────────────────────────
function hasAcces(int $membreId): bool {
    return getAbonnementActif($membreId) !== null;
}

// ── Activer un abonnement après paiement ──────────────────
function activerAbonnement(int $membreId, string $type): bool {
    $db = getDB();

    // Calcul date de fin
    $dateFin = null;
    if ($type === 'weekend') {
        // Prochain dimanche 23:59
        $now = new DateTime('now', new DateTimeZone('Europe/Paris'));
        $sunday = clone $now;
        $sunday->modify('Sunday this week');
        $sunday->setTime(23, 59, 59);
        if ($sunday < $now) $sunday->modify('+7 days');
        $dateFin = $sunday->format('Y-m-d H:i:s');
    } elseif ($type === 'weekly') {
        $dateFin = date('Y-m-d H:i:s', strtotime('+7 days'));
    }
    // daily = date_fin NULL
    // rasstoss = date_fin 2090 (à vie)
    if ($type === 'rasstoss') { $dateFin = '2090-01-01 00:00:00'; }

    $montants = ['daily' => 4.50, 'weekend' => 10.00, 'weekly' => 20.00, 'tennis' => 15.00, 'rasstoss' => 0.00];
    $montant = $montants[$type] ?? 0;

    $stmt = $db->prepare("
        INSERT INTO abonnements (membre_id, type, date_fin, actif, montant)
        VALUES (?, ?, ?, 1, ?)
    ");
    return $stmt->execute([$membreId, $type, $dateFin, $montant]);
}

// ── Historique des abonnements ─────────────────────────────
function getHistoriqueAbonnements(int $membreId): array {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT * FROM abonnements
        WHERE membre_id = ?
        ORDER BY date_achat DESC
    ");
    $stmt->execute([$membreId]);
    return $stmt->fetchAll();
}

// ── Sécurité : nettoyer les inputs ────────────────────────
function clean(string $str): string {
    return htmlspecialchars(trim($str), ENT_QUOTES, 'UTF-8');
}

// ── Générer un token CSRF ─────────────────────────────────
function csrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrf(string $token): bool {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
