<?php
// ============================================================
// STRATEDGE — Rapport automatique des résultats des bets
// ============================================================
// Envoie un email récapitulatif à l'admin :
//   • Quotidien  : chaque jour à 21h   → résultats du jour
//   • Hebdo      : dimanche à 21h      → résultats de la semaine
//   • Mensuel    : 1er du mois à 21h   → résultats du mois passé
//
// Crontab : 0 21 * * * /usr/bin/php /chemin/vers/public_html/cron/rapport-bets.php
// Ou par URL : https://stratedgepronos.fr/cron/rapport-bets.php?key=VOTRE_SECRET_KEY
// ============================================================

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/mailer.php';

// Sécurité : clé obligatoire si appelé par URL
if (php_sapi_name() !== 'cli') {
    if (($_GET['key'] ?? '') !== SECRET_KEY) {
        http_response_code(403);
        die('Accès refusé.');
    }
}

date_default_timezone_set('Europe/Paris');
$db   = getDB();
$now  = new DateTime();
$logs = [];

// Déterminer quels rapports envoyer
$rapports = ['daily']; // toujours le quotidien

if ($now->format('w') == 0) { // dimanche
    $rapports[] = 'weekly';
}
if ($now->format('j') == 1) { // 1er du mois
    $rapports[] = 'monthly';
}

// Forçage via paramètre (debug) : ?period=daily,weekly,monthly
if (!empty($_GET['period'])) {
    $rapports = array_intersect(explode(',', $_GET['period']), ['daily', 'weekly', 'monthly']);
}

foreach ($rapports as $period) {
    $rapport = genererRapport($db, $period, $now);
    if ($rapport) {
        $ok = envoyerEmail(ADMIN_EMAIL, $rapport['sujet'], $rapport['html']);
        $logs[] = strtoupper($period) . ' → ' . ($ok ? 'OK' : 'ÉCHEC');
    } else {
        $logs[] = strtoupper($period) . ' → aucun bet sur la période';
    }
}

$resume = implode(' | ', $logs);
error_log('[rapport-bets] ' . $resume);
if (php_sapi_name() === 'cli') {
    echo $resume . "\n";
} else {
    echo $resume;
}

// ============================================================
// Génération du rapport
// ============================================================
function genererRapport(PDO $db, string $period, DateTime $now): ?array {

    switch ($period) {
        case 'daily':
            $dateDebut = $now->format('Y-m-d 00:00:00');
            $dateFin   = $now->format('Y-m-d 23:59:59');
            $label     = 'QUOTIDIEN';
            $periode   = $now->format('d/m/Y');
            break;

        case 'weekly':
            $lundi   = (clone $now)->modify('monday this week');
            $dimanche = (clone $now)->modify('sunday this week');
            $dateDebut = $lundi->format('Y-m-d 00:00:00');
            $dateFin   = $dimanche->format('Y-m-d 23:59:59');
            $label     = 'HEBDOMADAIRE';
            $periode   = $lundi->format('d/m') . ' → ' . $dimanche->format('d/m/Y');
            break;

        case 'monthly':
            $moisPrec  = (clone $now)->modify('first day of last month');
            $finMois   = (clone $now)->modify('last day of last month');
            $dateDebut = $moisPrec->format('Y-m-01 00:00:00');
            $dateFin   = $finMois->format('Y-m-t 23:59:59');
            $moisNoms  = ['','Janvier','Février','Mars','Avril','Mai','Juin','Juillet','Août','Septembre','Octobre','Novembre','Décembre'];
            $label     = 'MENSUEL';
            $periode   = ($moisNoms[(int)$moisPrec->format('n')] ?? '') . ' ' . $moisPrec->format('Y');
            break;

        default:
            return null;
    }

    // Récupérer les bets avec résultat sur la période
    $stmt = $db->prepare("
        SELECT * FROM bets 
        WHERE date_resultat BETWEEN ? AND ?
          AND resultat IN ('gagne','perdu','annule')
        ORDER BY date_resultat DESC
    ");
    $stmt->execute([$dateDebut, $dateFin]);
    $bets = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($bets)) return null;

    // Stats globales
    $stats = calcStats($bets);

    // Stats par section
    $tennis = array_filter($bets, fn($b) => ($b['categorie'] ?? '') === 'tennis');
    $multi  = array_filter($bets, fn($b) => ($b['categorie'] ?? '') === 'multi');
    $fun    = array_filter($bets, fn($b) => str_contains(($b['type'] ?? ''), 'fun'));

    $sTennis = calcStats($tennis);
    $sMulti  = calcStats($multi);
    $sFun    = calcStats($fun);

    $sujet = "📊 Rapport {$label} StratEdge — {$periode}";
    $html  = buildHtmlRapport($label, $periode, $stats, $sTennis, $sMulti, $sFun, $bets);

    return ['sujet' => $sujet, 'html' => $html];
}

function calcStats(array $bets): array {
    $total    = count($bets);
    $gagnes   = count(array_filter($bets, fn($b) => $b['resultat'] === 'gagne'));
    $perdus   = count(array_filter($bets, fn($b) => $b['resultat'] === 'perdu'));
    $annules  = count(array_filter($bets, fn($b) => $b['resultat'] === 'annule'));
    $taux     = ($gagnes + $perdus) > 0 ? round($gagnes / ($gagnes + $perdus) * 100, 1) : 0;

    $cotes = array_filter(array_map(fn($b) => (float)($b['cote'] ?? 0), $bets), fn($c) => $c > 0);
    $coteMoy = count($cotes) > 0 ? round(array_sum($cotes) / count($cotes), 2) : 0;

    return compact('total', 'gagnes', 'perdus', 'annules', 'taux', 'coteMoy');
}

function buildHtmlRapport(string $label, string $periode, array $g, array $t, array $m, array $f, array $bets): string {
    $sectionBlock = function(string $icon, string $nom, array $s): string {
        if ($s['total'] === 0) return '';
        return "
        <tr>
          <td style='padding:10px 16px;font-size:15px;font-weight:600;color:#f0f4f8;'>
            {$icon} {$nom}
          </td>
          <td style='padding:10px 16px;text-align:center;color:#b0bec9;'>{$s['total']}</td>
          <td style='padding:10px 16px;text-align:center;color:#00c864;font-weight:700;'>{$s['gagnes']}</td>
          <td style='padding:10px 16px;text-align:center;color:#ff4444;font-weight:700;'>{$s['perdus']}</td>
          <td style='padding:10px 16px;text-align:center;color:#f59e0b;'>{$s['annules']}</td>
          <td style='padding:10px 16px;text-align:center;color:#00d4ff;font-weight:700;'>{$s['taux']}%</td>
          <td style='padding:10px 16px;text-align:center;color:#ff2d78;font-weight:700;'>{$s['coteMoy']}</td>
        </tr>";
    };

    $detailRows = '';
    foreach ($bets as $b) {
        $resColors = ['gagne' => '#00c864', 'perdu' => '#ff4444', 'annule' => '#f59e0b'];
        $resLabels = ['gagne' => '✅ Gagné', 'perdu' => '❌ Perdu', 'annule' => '↺ Annulé'];
        $rc = $resColors[$b['resultat']] ?? '#8a9bb0';
        $rl = $resLabels[$b['resultat']] ?? $b['resultat'];
        $cote = $b['cote'] ? number_format((float)$b['cote'], 2) : '—';
        $cat  = ($b['categorie'] ?? 'multi') === 'tennis' ? '🎾' : '⚽';
        $type = ucfirst($b['type'] ?? 'safe');
        $date = date('d/m H:i', strtotime($b['date_resultat']));
        $titre = htmlspecialchars($b['titre'] ?? 'Sans titre');
        $detailRows .= "
        <tr style='border-bottom:1px solid rgba(255,255,255,0.05);'>
          <td style='padding:8px 12px;font-size:13px;color:#b0bec9;'>{$date}</td>
          <td style='padding:8px 12px;font-size:13px;color:#f0f4f8;'>{$cat} {$titre}</td>
          <td style='padding:8px 12px;text-align:center;font-size:13px;color:#b0bec9;'>{$type}</td>
          <td style='padding:8px 12px;text-align:center;font-size:13px;color:#ff2d78;font-weight:700;'>{$cote}</td>
          <td style='padding:8px 12px;text-align:center;font-size:13px;color:{$rc};font-weight:700;'>{$rl}</td>
        </tr>";
    }

    return <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#050810;font-family:'Segoe UI',Tahoma,sans-serif;">
<div style="max-width:680px;margin:30px auto;background:#0d1220;border-radius:16px;border:1px solid rgba(255,45,120,0.15);overflow:hidden;">

  <!-- Header -->
  <div style="background:linear-gradient(135deg,#ff2d78,#d6245f);padding:28px 32px;text-align:center;">
    <h1 style="margin:0;font-size:22px;color:#fff;letter-spacing:1px;">📊 RAPPORT {$label}</h1>
    <p style="margin:8px 0 0;font-size:14px;color:rgba(255,255,255,0.8);">{$periode}</p>
  </div>

  <!-- Stats globales -->
  <div style="padding:28px 32px;">
    <h2 style="margin:0 0 18px;font-size:16px;color:#ff2d78;text-transform:uppercase;letter-spacing:2px;">Résumé global</h2>
    <table style="width:100%;border-collapse:collapse;">
      <tr>
        <td style="padding:12px 0;color:#b0bec9;font-size:14px;">Total paris</td>
        <td style="padding:12px 0;text-align:right;color:#f0f4f8;font-size:20px;font-weight:700;">{$g['total']}</td>
      </tr>
      <tr>
        <td style="padding:12px 0;color:#b0bec9;font-size:14px;">✅ Gagnés</td>
        <td style="padding:12px 0;text-align:right;color:#00c864;font-size:20px;font-weight:700;">{$g['gagnes']}</td>
      </tr>
      <tr>
        <td style="padding:12px 0;color:#b0bec9;font-size:14px;">❌ Perdus</td>
        <td style="padding:12px 0;text-align:right;color:#ff4444;font-size:20px;font-weight:700;">{$g['perdus']}</td>
      </tr>
      <tr>
        <td style="padding:12px 0;color:#b0bec9;font-size:14px;">↺ Remboursés</td>
        <td style="padding:12px 0;text-align:right;color:#f59e0b;font-size:20px;font-weight:700;">{$g['annules']}</td>
      </tr>
      <tr style="border-top:1px solid rgba(255,255,255,0.08);">
        <td style="padding:12px 0;color:#b0bec9;font-size:14px;">Taux de réussite</td>
        <td style="padding:12px 0;text-align:right;color:#00d4ff;font-size:20px;font-weight:700;">{$g['taux']}%</td>
      </tr>
      <tr>
        <td style="padding:12px 0;color:#b0bec9;font-size:14px;">Cote moyenne globale</td>
        <td style="padding:12px 0;text-align:right;color:#ff2d78;font-size:20px;font-weight:700;">{$g['coteMoy']}</td>
      </tr>
    </table>
  </div>

  <!-- Par section -->
  <div style="padding:0 32px 28px;">
    <h2 style="margin:0 0 14px;font-size:16px;color:#ff2d78;text-transform:uppercase;letter-spacing:2px;">Par section</h2>
    <table style="width:100%;border-collapse:collapse;background:rgba(255,255,255,0.02);border-radius:10px;overflow:hidden;">
      <thead>
        <tr style="background:rgba(255,255,255,0.04);">
          <th style="padding:10px 16px;text-align:left;font-size:11px;color:#8a9bb0;text-transform:uppercase;letter-spacing:1px;">Section</th>
          <th style="padding:10px 16px;text-align:center;font-size:11px;color:#8a9bb0;text-transform:uppercase;">Total</th>
          <th style="padding:10px 16px;text-align:center;font-size:11px;color:#00c864;text-transform:uppercase;">G</th>
          <th style="padding:10px 16px;text-align:center;font-size:11px;color:#ff4444;text-transform:uppercase;">P</th>
          <th style="padding:10px 16px;text-align:center;font-size:11px;color:#f59e0b;text-transform:uppercase;">R</th>
          <th style="padding:10px 16px;text-align:center;font-size:11px;color:#8a9bb0;text-transform:uppercase;">Taux</th>
          <th style="padding:10px 16px;text-align:center;font-size:11px;color:#8a9bb0;text-transform:uppercase;">Cote moy.</th>
        </tr>
      </thead>
      <tbody>
        {$sectionBlock('🎾', 'Tennis', $t)}
        {$sectionBlock('⚽', 'Multisport', $m)}
        {$sectionBlock('🎯', 'Fun', $f)}
      </tbody>
    </table>
  </div>

  <!-- Détail des bets -->
  <div style="padding:0 32px 32px;">
    <h2 style="margin:0 0 14px;font-size:16px;color:#ff2d78;text-transform:uppercase;letter-spacing:2px;">Détail des bets</h2>
    <table style="width:100%;border-collapse:collapse;background:rgba(255,255,255,0.02);border-radius:10px;overflow:hidden;">
      <thead>
        <tr style="background:rgba(255,255,255,0.04);">
          <th style="padding:8px 12px;text-align:left;font-size:11px;color:#8a9bb0;text-transform:uppercase;">Date</th>
          <th style="padding:8px 12px;text-align:left;font-size:11px;color:#8a9bb0;text-transform:uppercase;">Bet</th>
          <th style="padding:8px 12px;text-align:center;font-size:11px;color:#8a9bb0;text-transform:uppercase;">Type</th>
          <th style="padding:8px 12px;text-align:center;font-size:11px;color:#8a9bb0;text-transform:uppercase;">Cote</th>
          <th style="padding:8px 12px;text-align:center;font-size:11px;color:#8a9bb0;text-transform:uppercase;">Résultat</th>
        </tr>
      </thead>
      <tbody>{$detailRows}</tbody>
    </table>
  </div>

  <!-- Footer -->
  <div style="padding:20px 32px;text-align:center;border-top:1px solid rgba(255,255,255,0.06);">
    <p style="margin:0;font-size:12px;color:#8a9bb0;">StratEdge Pronos — Rapport automatique</p>
    <p style="margin:4px 0 0;font-size:11px;color:#5a6a7a;">Généré le <?= date('d/m/Y à H:i') ?></p>
  </div>

</div>
</body>
</html>
HTML;
}
