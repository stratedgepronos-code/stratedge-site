<?php
// ============================================================
// STRATEDGE — Diagnostic insertion des bets
// /panel-x9k3m/diagnose-bets.php
// ============================================================

require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
header('Content-Type: text/plain; charset=utf-8');

$db = getDB();

echo "═══════════════════════════════════════════════\n";
echo "  DIAGNOSTIC INSERTION DES BETS\n";
echo "═══════════════════════════════════════════════\n\n";

// 1) Structure de la colonne actif (DEFAULT value)
echo "━━━ STRUCTURE TABLE BETS ━━━\n";
try {
    $cols = $db->query("SHOW COLUMNS FROM bets WHERE Field IN ('actif', 'resultat', 'posted_by_role', 'categorie', 'type')")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($cols as $c) {
        echo sprintf("  %-20s type=%-30s default=%s null=%s\n",
            $c['Field'], $c['Type'], var_export($c['Default'], true), $c['Null']);
    }
} catch (Throwable $e) {
    echo "  Erreur: " . $e->getMessage() . "\n";
}
echo "\n";

// 2) Les 30 derniers bets
echo "━━━ 30 DERNIERS BETS (tous types / toutes catégories) ━━━\n";
$stmt = $db->query("SELECT id, titre, type, categorie, sport, actif, resultat, posted_by_role, date_post, LENGTH(image_path) AS ip_len FROM bets ORDER BY id DESC LIMIT 30");
$bets = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($bets)) {
    echo "  (aucun bet trouvé dans la table)\n\n";
} else {
    foreach ($bets as $b) {
        $mark = $b['actif'] ? '✅' : '❌';
        $res = $b['resultat'] ?: 'en_cours';
        echo sprintf(
            "  #%-4d %s actif=%d cat=%-7s type=%-12s sport=%-10s role=%-12s res=%-9s · %s · %s\n",
            $b['id'], $mark, $b['actif'],
            $b['categorie'] ?? '?',
            $b['type'] ?? '?',
            $b['sport'] ?? '?',
            $b['posted_by_role'] ?? '?',
            $res,
            $b['date_post'] ?? '?',
            mb_substr($b['titre'] ?? '(sans titre)', 0, 35)
        );
    }
    echo "\n";
}

// 3) Compteurs par combinaison pour détecter des anomalies
echo "━━━ RÉPARTITION DES BETS (tous) ━━━\n";
$rows = $db->query("SELECT categorie, type, actif, COUNT(*) AS n FROM bets GROUP BY categorie, type, actif ORDER BY categorie, type, actif DESC")->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
    $mark = $r['actif'] ? '✅' : '❌';
    echo sprintf("  %s cat=%-8s type=%-12s → %d bets\n",
        $mark, $r['categorie'] ?? '?', $r['type'] ?? '?', $r['n']);
}
echo "\n";

// 4) Log file poster-bet-from-card
echo "━━━ LOG poster-bet-from-card.log (30 dernières lignes) ━━━\n";
$logFile = __DIR__ . '/../logs/poster-bet-from-card.log';
if (is_file($logFile)) {
    $lines = @file($logFile, FILE_IGNORE_NEW_LINES);
    $last = array_slice($lines ?: [], -30);
    if (empty($last)) {
        echo "  (log file vide)\n";
    } else {
        foreach ($last as $l) echo "  $l\n";
    }
    echo "\n  Fichier : $logFile (taille: " . @filesize($logFile) . " bytes)\n";
} else {
    echo "  (aucun log — poster-bet-from-card.php n'a pas encore été appelé après le fix)\n";
}
echo "\n";

// 5) Bets invisibles (actif=0) récents
echo "━━━ BETS INACTIFS RÉCENTS (< 7 jours) ━━━\n";
$stmt = $db->query("SELECT id, titre, categorie, type, posted_by_role, date_post FROM bets WHERE actif = 0 AND date_post > DATE_SUB(NOW(), INTERVAL 7 DAY) ORDER BY id DESC LIMIT 20");
$inactive = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (empty($inactive)) {
    echo "  (aucun bet inactif récent — tout est bon)\n";
} else {
    echo "  ⚠️ " . count($inactive) . " bet(s) récents inactifs (invisibles côté page bets):\n";
    foreach ($inactive as $b) {
        echo sprintf("  #%d · cat=%s type=%s · %s · %s\n",
            $b['id'], $b['categorie'], $b['type'], $b['date_post'],
            mb_substr($b['titre'] ?? '(sans titre)', 0, 40));
    }
    echo "\n  Pour les réactiver en masse (⚠️ à utiliser avec précaution) :\n";
    echo "  https://stratedgepronos.fr/panel-x9k3m/diagnose-bets.php?reactivate=all\n";
}
echo "\n";

// 6) Action "reactivate" : remettre actif=1 sur les bets récents inactifs
if (($_GET['reactivate'] ?? '') === 'all') {
    $stmt = $db->prepare("UPDATE bets SET actif = 1 WHERE actif = 0 AND date_post > DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $stmt->execute();
    echo "━━━ RÉACTIVATION ━━━\n";
    echo "  ✅ " . $stmt->rowCount() . " bet(s) réactivé(s)\n\n";
}

echo "═══════════════════════════════════════════════\n";
echo "  Pour réactiver les bets inactifs récents:\n";
echo "  ?reactivate=all\n";
echo "═══════════════════════════════════════════════\n";
