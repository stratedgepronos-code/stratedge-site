<?php
// ============================================================
// STRATEDGE — GiveAway Functions
// includes/giveaway-functions.php
// ============================================================

if (!defined('ABSPATH')) define('ABSPATH', true);

// Points par type d'abonnement (Tennis et Fun exclus)
define('GIVEAWAY_POINTS', [
    'daily'         => 1,
    'weekend'       => 3,
    'weekend_fun'   => 3,
    'weekly'        => 6,
    'vip_max'       => 10,
]);

// ── Auto-création des tables ────────────────────────────────
function giveawayInitTables() {
    $db = getDB();
    try {
        $db->query("SELECT 1 FROM giveaway_points LIMIT 1");
    } catch (Throwable $e) {
        $db->exec("CREATE TABLE IF NOT EXISTS `giveaway_points` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `membre_id` INT UNSIGNED NOT NULL,
            `type_abo` VARCHAR(30) NOT NULL,
            `points` INT UNSIGNED NOT NULL DEFAULT 0,
            `mois` VARCHAR(7) NOT NULL COMMENT 'Format YYYY-MM',
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY `idx_mois` (`mois`),
            KEY `idx_membre_mois` (`membre_id`, `mois`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $db->exec("CREATE TABLE IF NOT EXISTS `giveaway_config` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `mois` VARCHAR(7) NOT NULL UNIQUE COMMENT 'Format YYYY-MM',
            `cadeau` TEXT DEFAULT NULL,
            `statut` ENUM('open','closed','drawn') DEFAULT 'open',
            `gagnant_id` INT UNSIGNED DEFAULT NULL,
            `gagnant_nom` VARCHAR(120) DEFAULT NULL,
            `drawn_at` DATETIME DEFAULT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY `idx_mois` (`mois`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }
}

// ── Ajouter des points après achat d'un abonnement ──────────
function ajouterPointsGiveaway(int $membreId, string $typeAbo): bool {
    $pts = GIVEAWAY_POINTS[$typeAbo] ?? 0;
    if ($pts <= 0) return false; // Tennis, fun, rasstoss → pas de points

    $db = getDB();
    giveawayInitTables();

    $tz = new DateTimeZone('Europe/Paris');
    $mois = (new DateTime('now', $tz))->format('Y-m');

    $stmt = $db->prepare("INSERT INTO giveaway_points (membre_id, type_abo, points, mois) VALUES (?, ?, ?, ?)");
    return $stmt->execute([$membreId, $typeAbo, $pts, $mois]);
}

// ── Points d'un membre pour un mois ─────────────────────────
function getPointsMembre(int $membreId, ?string $mois = null): int {
    $db = getDB();
    giveawayInitTables();
    if (!$mois) {
        $tz = new DateTimeZone('Europe/Paris');
        $mois = (new DateTime('now', $tz))->format('Y-m');
    }
    $stmt = $db->prepare("SELECT COALESCE(SUM(points), 0) FROM giveaway_points WHERE membre_id = ? AND mois = ?");
    $stmt->execute([$membreId, $mois]);
    return (int)$stmt->fetchColumn();
}

// ── Détail des points d'un membre ───────────────────────────
function getPointsDetailMembre(int $membreId, ?string $mois = null): array {
    $db = getDB();
    giveawayInitTables();
    if (!$mois) {
        $tz = new DateTimeZone('Europe/Paris');
        $mois = (new DateTime('now', $tz))->format('Y-m');
    }
    $stmt = $db->prepare("SELECT type_abo, points, created_at FROM giveaway_points WHERE membre_id = ? AND mois = ? ORDER BY created_at DESC");
    $stmt->execute([$membreId, $mois]);
    return $stmt->fetchAll();
}

// ── Classement du mois (tous les participants) ──────────────
function getClassementMois(?string $mois = null): array {
    $db = getDB();
    giveawayInitTables();
    if (!$mois) {
        $tz = new DateTimeZone('Europe/Paris');
        $mois = (new DateTime('now', $tz))->format('Y-m');
    }
    $stmt = $db->prepare("
        SELECT gp.membre_id, m.nom, SUM(gp.points) as total_pts
        FROM giveaway_points gp
        JOIN membres m ON m.id = gp.membre_id
        WHERE gp.mois = ?
        GROUP BY gp.membre_id, m.nom
        ORDER BY total_pts DESC
    ");
    $stmt->execute([$mois]);
    return $stmt->fetchAll();
}

// ── Config du GiveAway pour un mois ─────────────────────────
function getGiveawayConfig(?string $mois = null): ?array {
    $db = getDB();
    giveawayInitTables();
    if (!$mois) {
        $tz = new DateTimeZone('Europe/Paris');
        $mois = (new DateTime('now', $tz))->format('Y-m');
    }
    $stmt = $db->prepare("SELECT * FROM giveaway_config WHERE mois = ?");
    $stmt->execute([$mois]);
    $row = $stmt->fetch();

    // Auto-créer si inexistant
    if (!$row) {
        $db->prepare("INSERT IGNORE INTO giveaway_config (mois) VALUES (?)")->execute([$mois]);
        $stmt->execute([$mois]);
        $row = $stmt->fetch();
    }
    return $row ?: null;
}

// ── Sauvegarder la config (cadeau, etc.) ────────────────────
function setGiveawayCadeau(string $mois, string $cadeau): bool {
    $db = getDB();
    giveawayInitTables();
    getGiveawayConfig($mois); // auto-create
    $stmt = $db->prepare("UPDATE giveaway_config SET cadeau = ? WHERE mois = ?");
    return $stmt->execute([$cadeau, $mois]);
}

// ── Tirage au sort pondéré ──────────────────────────────────
function tirerAuSort(string $mois): ?array {
    $db = getDB();
    $classement = getClassementMois($mois);
    if (empty($classement)) return null;

    $totalPts = array_sum(array_column($classement, 'total_pts'));
    if ($totalPts <= 0) return null;

    // Weighted random
    $rand = mt_rand(1, $totalPts);
    $cumul = 0;
    $gagnant = null;
    foreach ($classement as $p) {
        $cumul += (int)$p['total_pts'];
        if ($rand <= $cumul) {
            $gagnant = $p;
            break;
        }
    }
    if (!$gagnant) $gagnant = $classement[0];

    // Sauvegarder le résultat
    $stmt = $db->prepare("UPDATE giveaway_config SET statut = 'drawn', gagnant_id = ?, gagnant_nom = ?, drawn_at = NOW() WHERE mois = ?");
    $stmt->execute([$gagnant['membre_id'], $gagnant['nom'], $mois]);

    return $gagnant;
}

// ── Mois en français ────────────────────────────────────────
function moisFrancais(string $mois): string {
    $parts = explode('-', $mois);
    $noms = ['01'=>'Janvier','02'=>'Février','03'=>'Mars','04'=>'Avril','05'=>'Mai','06'=>'Juin',
             '07'=>'Juillet','08'=>'Août','09'=>'Septembre','10'=>'Octobre','11'=>'Novembre','12'=>'Décembre'];
    return ($noms[$parts[1]] ?? $parts[1]) . ' ' . $parts[0];
}
