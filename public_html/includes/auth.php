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

// ── Rôle admin spécialisé ─────────────────────────────────
function getAdminRole(): string {
    if (!isAdmin()) return '';
    if (isSuperAdmin()) return 'super';
    return $_SESSION['admin_role'] ?? 'admin';
}

function isAdminTennis(): bool {
    return getAdminRole() === 'admin_tennis';
}

function isAdminFun(): bool {
    return getAdminRole() === 'admin_fun';
}

/** Admin Fun Sport : uniquement Fun Foot / NBA / NHL (cards) */
function isAdminFunSport(): bool {
    return getAdminRole() === 'admin_fun_sport';
}

// ── Obliger le super admin ─────────────────────────────────
function requireSuperAdmin(): void {
    if (!isSuperAdmin()) {
        header('Location: /panel-x9k3m/index.php');
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

    $role = $membre['role'] ?? '';
    $isAdm = ($membre['email'] === ADMIN_EMAIL || in_array($role, ['admin', 'admin_tennis', 'admin_fun', 'admin_fun_sport'], true));

    $_SESSION['membre_id']    = $membre['id'];
    $_SESSION['membre_nom']   = $membre['nom'];
    $_SESSION['membre_email'] = $membre['email'];
    $_SESSION['is_admin']     = $isAdm;
    $_SESSION['admin_role']   = $isAdm ? $role : '';
    $_SESSION['login_time']   = time();
    $_SESSION['user_agent']   = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 200);

    return ['success' => true];
}

// ── Inscription d'un membre ────────────────────────────────
// $accepte_emails : 1 = accepte les notifications par email (RGPD/LCEN), 0 = non
// $date_naissance : date au format Y-m-d ou null
function registerMembre(string $nom, string $email, string $password, int $accepte_emails = 1, ?string $date_naissance = null): array {
    $db = getDB();

    // Vérifier si l'email existe déjà
    $stmt = $db->prepare("SELECT id FROM membres WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        return ['success' => false, 'error' => 'Cet email est déjà utilisé.'];
    }

    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    $dateNaissance = null;
    if ($date_naissance !== null && $date_naissance !== '') {
        $t = strtotime($date_naissance);
        if ($t !== false) $dateNaissance = date('Y-m-d', $t);
    }
    try {
        $stmt = $db->prepare("INSERT INTO membres (nom, email, password, accepte_emails, date_naissance) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$nom, $email, $hash, $accepte_emails, $dateNaissance]);
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'date_naissance') !== false || strpos($e->getMessage(), 'accepte_emails') !== false) {
            try {
                $stmt = $db->prepare("INSERT INTO membres (nom, email, password, accepte_emails) VALUES (?, ?, ?, ?)");
                $stmt->execute([$nom, $email, $hash, $accepte_emails]);
            } catch (PDOException $e2) {
                if (strpos($e2->getMessage(), 'accepte_emails') !== false) {
                    $stmt = $db->prepare("INSERT INTO membres (nom, email, password) VALUES (?, ?, ?)");
                    $stmt->execute([$nom, $email, $hash]);
                } else {
                    throw $e2;
                }
            }
        } else {
            throw $e;
        }
    }
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
    // Essayer avec photo_profil + accepte_emails, sinon fallback
    try {
        $stmt = $db->prepare("SELECT id, nom, email, date_inscription, photo_profil, accepte_emails FROM membres WHERE id = ? LIMIT 1");
        $stmt->execute([$_SESSION['membre_id']]);
    } catch (Exception $e) {
        try {
            $stmt = $db->prepare("SELECT id, nom, email, date_inscription, photo_profil FROM membres WHERE id = ? LIMIT 1");
            $stmt->execute([$_SESSION['membre_id']]);
        } catch (Exception $e2) {
            $stmt = $db->prepare("SELECT id, nom, email, date_inscription FROM membres WHERE id = ? LIMIT 1");
            $stmt->execute([$_SESSION['membre_id']]);
        }
    }
    $row = $stmt->fetch();
    if ($row) {
        if (!isset($row['photo_profil'])) $row['photo_profil'] = null;
        if (!isset($row['accepte_emails'])) $row['accepte_emails'] = 1;
    }
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
            OR (type IN ('weekend','weekend_fun','weekly','tennis','vip_max','fun') AND date_fin > NOW())
          )
        ORDER BY date_achat DESC
        LIMIT 1
    ");
    $stmt->execute([$membreId]);
    return $stmt->fetch() ?: null;
}

// ── Tous les abonnements actifs (un membre peut avoir tennis + fun) ──
function getAllAbonnementsActifs(int $membreId): array {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT type FROM abonnements
        WHERE membre_id = ?
          AND actif = 1
          AND (
            type IN ('daily','rasstoss')
            OR (type IN ('weekend','weekend_fun','weekly','tennis','vip_max','fun') AND date_fin > NOW())
          )
    ");
    $stmt->execute([$membreId]);
    return array_column($stmt->fetchAll(), 'type');
}

/**
 * Retourne les droits d'accès d'un membre aux bets.
 * @return array ['all' => bool, 'multi' => bool, 'tennis' => bool, 'fun' => bool]
 */
function getMembreAcces(int $membreId): array {
    $acces = ['all' => false, 'multi' => false, 'tennis' => false, 'fun' => false];

    if (isAdmin()) {
        $acces['all'] = true;
        return $acces;
    }

    $types = getAllAbonnementsActifs($membreId);
    if (empty($types)) return $acces;

    foreach ($types as $t) {
        switch ($t) {
            case 'rasstoss':
                $acces['all'] = true;
                return $acces;
            case 'tennis':
                $acces['tennis'] = true;
                break;
            case 'fun':
                $acces['fun'] = true;
                break;
            case 'daily':
            case 'weekend':
            case 'weekly':
                $acces['multi'] = true;
                break;
            case 'weekend_fun':
                $acces['multi'] = true;
                $acces['fun']   = true;
                break;
            case 'vip_max':
                $acces['multi']  = true;
                $acces['fun']    = true;
                $acces['tennis'] = true;
                break;
        }
    }
    return $acces;
}

/**
 * Construit la clause WHERE SQL pour filtrer les bets selon les droits d'accès.
 */
function buildBetsWhereClause(array $acces): string {
    if ($acces['all']) return '1=1';

    $clauses = [];
    if ($acces['multi'] && $acces['fun']) {
        $clauses[] = "categorie = 'multi'";
    } elseif ($acces['multi']) {
        $clauses[] = "(categorie = 'multi' AND type NOT LIKE '%fun%')";
    }
    if ($acces['fun'] && !$acces['multi']) {
        $clauses[] = "type LIKE '%fun%'";
    }
    if ($acces['tennis']) {
        $clauses[] = "categorie = 'tennis'";
    }

    return empty($clauses) ? '0=1' : '(' . implode(' OR ', $clauses) . ')';
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
    if ($type === 'weekend' || $type === 'weekend_fun') {
        // Prochain dimanche 23:59
        $now = new DateTime('now', new DateTimeZone('Europe/Paris'));
        $sunday = clone $now;
        $sunday->modify('Sunday this week');
        $sunday->setTime(23, 59, 59);
        if ($sunday < $now) $sunday->modify('+7 days');
        $dateFin = $sunday->format('Y-m-d H:i:s');
    } elseif ($type === 'weekly' || $type === 'tennis') {
        $dateFin = date('Y-m-d H:i:s', strtotime('+7 days'));
    } elseif ($type === 'vip_max') {
        $dateFin = date('Y-m-d H:i:s', strtotime('+30 days'));
    } elseif ($type === 'fun') {
        // Fun = abonnement 7 jours glissants (modifie 16/04/2026 - avant: jusqu'au dimanche)
        $dateFin = date('Y-m-d H:i:s', strtotime('+7 days'));
    }
    if ($type === 'rasstoss') { $dateFin = '2090-01-01 00:00:00'; }

    $montants = ['daily' => 4.50, 'weekend' => 10.00, 'weekend_fun' => 20.00, 'weekly' => 20.00, 'tennis' => 15.00, 'vip_max' => 50.00, 'fun' => 10.00, 'rasstoss' => 0.00];
    $montant = $montants[$type] ?? 0;

    $stmt = $db->prepare("
        INSERT INTO abonnements (membre_id, type, date_fin, actif, montant)
        VALUES (?, ?, ?, 1, ?)
    ");
    $ok = $stmt->execute([$membreId, $type, $dateFin, $montant]);

    // GiveAway : points mensuels (Daily, Week-End, Weekly, VIP Max — pas tennis/fun)
    try {
        require_once __DIR__ . '/giveaway-functions.php';
        ajouterPointsGiveaway($membreId, $type);
    } catch (Throwable $e) { /* silencieux si tables absentes */ }

    return $ok;
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

// ── URL image bet : toujours passer par restore-image.php pour servir depuis disque ou BDD ──
function betImageUrl(string $path, string $subdir = 'bets'): string {
    $path = str_replace('\\', '/', trim($path));
    if ($path === '') return '';
    if (strpos($path, 'http') === 0) return $path;
    $path = ltrim($path, '/');
    if (strpos($path, 'uploads/') !== 0) {
        $path = 'uploads/' . $subdir . '/' . $path;
    }
    $file = basename($path);
    if (!in_array($subdir, ['bets', 'locked'], true)) $subdir = 'bets';
    $base = defined('SITE_URL') ? rtrim(SITE_URL, '/') : '';
    $script = $base ? ($base . '/restore-image.php') : '/restore-image.php';
    return $script . '?dir=' . rawurlencode($subdir) . '&file=' . rawurlencode($file);
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
