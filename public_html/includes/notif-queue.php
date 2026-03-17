<?php
/**
 * File d'attente notifications (email + push) — évite timeout PHP sur gros volumes.
 * 1) Enfile les destinataires au post d'un bet
 * 2) Traite par paquets (poster-bet + cron)
 */
declare(strict_types=1);

if (!function_exists('getDB')) {
    require_once __DIR__ . '/db.php';
}
require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/push.php';

function notifQueueEnsureTable(PDO $db): void {
    $db->exec("CREATE TABLE IF NOT EXISTS notif_queue (
      id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      batch_id VARCHAR(32) NOT NULL,
      membre_id INT UNSIGNED NOT NULL,
      email VARCHAR(255) NOT NULL,
      nom VARCHAR(120) NOT NULL DEFAULT '',
      bet_type VARCHAR(64) NOT NULL DEFAULT '',
      bet_titre VARCHAR(500) NOT NULL DEFAULT '',
      created_at DATETIME NOT NULL,
      sent_at DATETIME NULL DEFAULT NULL,
      last_error VARCHAR(500) NULL,
      KEY idx_pending (sent_at, id),
      KEY idx_batch (batch_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

/**
 * Ajoute le super admin (ADMIN_EMAIL) au batch s'il existe en membre et n'y est pas déjà — reçoit tout.
 */
function notifQueueEnqueueSuperAdmin(PDO $db, string $batch, string $betType, string $betTitre): void {
    if (!defined('ADMIN_EMAIL') || ADMIN_EMAIL === '') {
        return;
    }
    $stmt = $db->prepare("SELECT id, email, nom FROM membres WHERE email = ? AND actif = 1 LIMIT 1");
    $stmt->execute([ADMIN_EMAIL]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$admin) {
        return;
    }
    $check = $db->prepare("SELECT 1 FROM notif_queue WHERE batch_id = ? AND membre_id = ? LIMIT 1");
    $check->execute([$batch, $admin['id']]);
    if ($check->fetch()) {
        return;
    }
    $ins = $db->prepare("INSERT INTO notif_queue (batch_id, membre_id, email, nom, bet_type, bet_titre, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
    $ins->execute([$batch, $admin['id'], $admin['email'], $admin['nom'] ?? '', $betType, $betTitre]);
}

/**
 * Enfile tous les abonnés concernés par un nouveau bet (tennis vs multi).
 * @return string batch_id
 */
function notifQueueEnqueueNouveauBet(PDO $db, string $categorie, string $betType, string $betTitre): string {
    notifQueueEnsureTable($db);
    $batch = bin2hex(random_bytes(8));

    if ($categorie === 'tennis') {
        $sql = "INSERT INTO notif_queue (batch_id, membre_id, email, nom, bet_type, bet_titre, created_at)
        SELECT DISTINCT ?, m.id, m.email, m.nom, ?, ?, NOW()
        FROM membres m
        INNER JOIN abonnements a ON a.membre_id = m.id AND a.actif = 1
        WHERE a.type = 'tennis' AND a.date_fin > NOW()
        AND (m.accepte_emails IS NULL OR m.accepte_emails = 1)";
        $db->prepare($sql)->execute([$batch, $betType, $betTitre]);
    } else {
        $sql = "INSERT INTO notif_queue (batch_id, membre_id, email, nom, bet_type, bet_titre, created_at)
        SELECT DISTINCT ?, m.id, m.email, m.nom, ?, ?, NOW()
        FROM membres m
        INNER JOIN abonnements a ON a.membre_id = m.id AND a.actif = 1
        WHERE a.type != 'tennis'
        AND (a.type = 'daily' OR a.date_fin > NOW())
        AND (m.accepte_emails IS NULL OR m.accepte_emails = 1)";
        $db->prepare($sql)->execute([$batch, $betType, $betTitre]);
    }
    notifQueueEnqueueSuperAdmin($db, $batch, $betType, $betTitre);
    return $batch;
}

/**
 * Enfile comme l’admin poster-bet « tous abonnés actifs » (toutes formules).
 */
function notifQueueEnqueueNouveauBetTousAbonnes(PDO $db, string $betType, string $betTitre): string {
    notifQueueEnsureTable($db);
    $batch = bin2hex(random_bytes(8));
    $sql = "INSERT INTO notif_queue (batch_id, membre_id, email, nom, bet_type, bet_titre, created_at)
    SELECT DISTINCT ?, m.id, m.email, m.nom, ?, ?, NOW()
    FROM membres m
    INNER JOIN abonnements a ON a.membre_id = m.id AND a.actif = 1
    WHERE (m.accepte_emails IS NULL OR m.accepte_emails = 1)";
    $db->prepare($sql)->execute([$batch, $betType, $betTitre]);
    notifQueueEnqueueSuperAdmin($db, $batch, $betType, $betTitre);
    return $batch;
}

/**
 * Traite jusqu’à $limit lignes en attente.
 * @return array{processed:int,emails_ok:int,emails_fail:int,push_ok:int,push_none:int}
 */
function notifQueueProcessBatch(PDO $db, int $limit = 100): array {
    notifQueueEnsureTable($db);
    set_time_limit(300);
    ignore_user_abort(true);

    $stats = ['processed' => 0, 'emails_ok' => 0, 'emails_fail' => 0, 'push_ok' => 0, 'push_none' => 0];

    $typeLabels = [
        'safe' => '🛡️ Safe', 'fun' => '🎯 Fun', 'live' => '⚡ Live',
        'safe,fun' => '🛡️+🎯 Safe+Fun', 'safe,live' => '🛡️+⚡ Safe+Live',
    ];

    $stmt = $db->prepare('SELECT id, membre_id, email, nom, bet_type, bet_titre FROM notif_queue WHERE sent_at IS NULL ORDER BY id ASC LIMIT ?');
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($rows)) {
        return $stats;
    }

    $upd = $db->prepare('UPDATE notif_queue SET sent_at = NOW(), last_error = ? WHERE id = ?');

    foreach ($rows as $r) {
        $errs = [];
        $okMail = @emailNouveauBet($r['email'], $r['nom'], $r['bet_type'], $r['bet_titre']);
        if ($okMail) {
            $stats['emails_ok']++;
        } else {
            $stats['emails_fail']++;
            $errs[] = 'email';
        }

        $label = $typeLabels[$r['bet_type']] ?? $r['bet_type'];
        $title = '🔥 Nouveau bet disponible !';
        $body = $label . ($r['bet_titre'] ? ' — ' . $r['bet_titre'] : '') . ' vient d\'être posté';
        try {
            $n = envoyerPush((int)$r['membre_id'], $title, $body, '/bets.php', 'nouveau-bet');
            if ($n > 0) {
                $stats['push_ok']++;
            } else {
                $stats['push_none']++;
                $errs[] = 'push0';
            }
        } catch (Throwable $e) {
            $errs[] = 'push_err';
            error_log('[notif-queue] push id=' . $r['membre_id'] . ' ' . $e->getMessage());
        }

        $upd->execute([$errs ? implode(',', $errs) : null, $r['id']]);
        $stats['processed']++;
    }

    return $stats;
}

function notifQueuePendingCount(PDO $db): int {
    try {
        return (int)$db->query('SELECT COUNT(*) FROM notif_queue WHERE sent_at IS NULL')->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

// ── File résultat bet (gagné / perdu / annulé) — même principe que nouveaux bets ──

function resultatQueueEnsureTable(PDO $db): void {
    $db->exec("CREATE TABLE IF NOT EXISTS notif_queue_resultat (
      id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      batch_id VARCHAR(32) NOT NULL,
      membre_id INT UNSIGNED NOT NULL,
      email VARCHAR(255) NOT NULL,
      nom VARCHAR(120) NOT NULL DEFAULT '',
      bet_titre VARCHAR(500) NOT NULL DEFAULT '',
      res_code VARCHAR(16) NOT NULL DEFAULT '',
      type_abo VARCHAR(64) NOT NULL DEFAULT '',
      created_at DATETIME NOT NULL,
      sent_at DATETIME NULL DEFAULT NULL,
      last_error VARCHAR(500) NULL,
      KEY idx_pending_r (sent_at, id),
      KEY idx_batch_r (batch_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function resultatQueueEnqueueSuperAdmin(PDO $db, string $batch, string $titre, string $resCode): void {
    if (!defined('ADMIN_EMAIL') || ADMIN_EMAIL === '') {
        return;
    }
    $stmt = $db->prepare('SELECT id, email, nom FROM membres WHERE email = ? AND actif = 1 LIMIT 1');
    $stmt->execute([ADMIN_EMAIL]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$admin) {
        return;
    }
    $check = $db->prepare('SELECT 1 FROM notif_queue_resultat WHERE batch_id = ? AND membre_id = ? LIMIT 1');
    $check->execute([$batch, $admin['id']]);
    if ($check->fetch()) {
        return;
    }
    $ins = $db->prepare('INSERT INTO notif_queue_resultat (batch_id, membre_id, email, nom, bet_titre, res_code, type_abo, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())');
    $ins->execute([$batch, $admin['id'], $admin['email'], $admin['nom'] ?? '', $titre, $resCode, '']);
}

/**
 * Enfile tous les abonnés actifs + super admin (même sans abo) pour notif résultat.
 */
function resultatQueueEnqueue(PDO $db, string $titre, string $resCode): string {
    resultatQueueEnsureTable($db);
    $batch = bin2hex(random_bytes(8));
    $sql = "INSERT INTO notif_queue_resultat (batch_id, membre_id, email, nom, bet_titre, res_code, type_abo, created_at)
    SELECT DISTINCT ?, m.id, m.email, m.nom, ?, ?, COALESCE(a.type, ''), NOW()
    FROM membres m
    INNER JOIN abonnements a ON a.membre_id = m.id AND a.actif = 1";
    $db->prepare($sql)->execute([$batch, $titre, $resCode]);
    resultatQueueEnqueueSuperAdmin($db, $batch, $titre, $resCode);
    return $batch;
}

/**
 * @return array{processed:int,emails_ok:int,emails_fail:int,push_ok:int,push_none:int}
 */
function resultatQueueProcessBatch(PDO $db, int $limit = 100): array {
    resultatQueueEnsureTable($db);
    set_time_limit(300);
    ignore_user_abort(true);

    $stats = ['processed' => 0, 'emails_ok' => 0, 'emails_fail' => 0, 'push_ok' => 0, 'push_none' => 0];
    $stmt = $db->prepare('SELECT id, membre_id, email, nom, bet_titre, res_code, type_abo FROM notif_queue_resultat WHERE sent_at IS NULL ORDER BY id ASC LIMIT ?');
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($rows)) {
        return $stats;
    }
    $upd = $db->prepare('UPDATE notif_queue_resultat SET sent_at = NOW(), last_error = ? WHERE id = ?');
    foreach ($rows as $r) {
        $errs = [];
        $okMail = @emailResultatBet($r['email'], $r['nom'], $r['bet_titre'], $r['res_code'], $r['type_abo'] ?? '');
        if ($okMail) {
            $stats['emails_ok']++;
        } else {
            $stats['emails_fail']++;
            $errs[] = 'email';
        }
        try {
            $n = pushResultatBet((int)$r['membre_id'], $r['type_abo'] ?? '', $r['bet_titre'], $r['res_code']);
            if ($n > 0) {
                $stats['push_ok']++;
            } else {
                $stats['push_none']++;
                $errs[] = 'push0';
            }
        } catch (Throwable $e) {
            $errs[] = 'push_err';
            $stats['push_none']++;
            error_log('[resultat-queue] push id=' . $r['membre_id'] . ' ' . $e->getMessage());
        }
        $upd->execute([$errs ? implode(',', $errs) : null, $r['id']]);
        $stats['processed']++;
    }
    return $stats;
}

function resultatQueuePendingCount(PDO $db): int {
    try {
        return (int)$db->query('SELECT COUNT(*) FROM notif_queue_resultat WHERE sent_at IS NULL')->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}
