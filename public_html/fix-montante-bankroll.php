<?php
// ============================================================
// STRATEDGE — Migration : recalcul bankroll_apres
// Corrige les étapes dont bankroll_apres a été calculé avec
// bankroll_initial (objectif) au lieu de mise_depart (capital réel)
//
// USAGE : accéder à /fix-montante-bankroll.php une seule fois
// puis SUPPRIMER CE FICHIER.
// ============================================================
require_once __DIR__ . '/includes/auth.php';
if (!isAdmin()) { http_response_code(403); die('Admin only'); }
$db = getDB();

$results = [];

foreach (['montante_config' => 'montante_steps', 'montante_foot_config' => 'montante_foot_steps'] as $cfgTable => $stepsTable) {
    try {
        // Check table exists
        $db->query("SELECT 1 FROM {$cfgTable} LIMIT 1");
    } catch (Throwable $e) {
        $results[] = "[SKIP] {$cfgTable} : table inexistante";
        continue;
    }

    $montantes = $db->query("SELECT * FROM {$cfgTable}")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($montantes as $m) {
        $mid = (int)$m['id'];
        $miseDepart = (float)$m['mise_depart'];
        $currentBr = $miseDepart;

        $stRows = $db->prepare("SELECT * FROM {$stepsTable} WHERE montante_id = ? ORDER BY step_number ASC");
        $stRows->execute([$mid]);
        $steps = $stRows->fetchAll(PDO::FETCH_ASSOC);

        $count = 0;
        foreach ($steps as $st) {
            $gp = null;
            $brAfter = null;
            if ($st['resultat'] === 'gagne') {
                $gp = round((float)$st['mise'] * ((float)$st['cote'] - 1), 2);
                $currentBr = round($currentBr + $gp, 2);
                $brAfter = $currentBr;
            } elseif ($st['resultat'] === 'perdu') {
                $gp = -1 * (float)$st['mise'];
                $currentBr = round($currentBr + $gp, 2);
                $brAfter = $currentBr;
            } elseif ($st['resultat'] === 'annule') {
                $gp = 0;
                $brAfter = $currentBr;
            }
            // Pour "en_cours" : gain_perte et bankroll_apres restent null
            $db->prepare("UPDATE {$stepsTable} SET gain_perte = ?, bankroll_apres = ? WHERE id = ?")
               ->execute([$gp, $brAfter, $st['id']]);
            $count++;
        }

        $results[] = "[OK] {$cfgTable} ID #{$mid} ({$m['nom']}) : {$count} étape(s) recalculée(s) · départ {$miseDepart}€ → actuel {$currentBr}€";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Migration bankroll montantes</title>
<style>
body{background:#0a0e17;color:#e8ecf4;font-family:monospace;padding:2rem;line-height:1.6;}
h1{color:#39ff14;}
.r{padding:0.5rem 0;border-bottom:1px solid rgba(255,255,255,0.06);}
.warn{color:#ffc107;margin-top:2rem;padding:1rem;background:rgba(255,193,7,0.08);border-left:3px solid #ffc107;}
</style>
</head>
<body>
<h1>✓ Migration terminée</h1>
<div><?php foreach ($results as $r) echo '<div class="r">' . htmlspecialchars($r) . '</div>'; ?></div>
<div class="warn">⚠️ IMPORTANT : supprime ce fichier <code>fix-montante-bankroll.php</code> maintenant que la migration est faite.</div>
</body>
</html>
