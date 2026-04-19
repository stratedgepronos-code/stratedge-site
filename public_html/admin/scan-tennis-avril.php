<?php
// ============================================================
// STRATEDGE — Scan Tennis Avril 2026 (temporaire)
// /admin/scan-tennis-avril.php
// À supprimer après utilisation
// ============================================================
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
$db = getDB();

// Tous les bets tennis d'avril 2026
$stmt = $db->query("
    SELECT id, titre, description, type, cote, resultat, date_post, date_resultat, posted_by_role, sport, categorie
    FROM bets
    WHERE (categorie = 'tennis' OR sport = 'tennis' OR posted_by_role = 'admin_tennis')
      AND date_post >= '2026-04-01 00:00:00'
      AND date_post < '2026-05-01 00:00:00'
    ORDER BY date_post ASC
");
$bets = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Détection compétition depuis titre + description
function detectCompetition($titre, $desc) {
    $text = strtolower($titre . ' ' . $desc);
    // ATP
    if (preg_match('/atp\s*(master|1000|500|250)/i', $text)) return 'ATP ' . strtoupper(preg_replace('/.*atp\s*/i','',preg_replace('/[\s,].*/','',substr($text, strpos(strtolower($text),'atp')))));
    if (strpos($text, 'masters 1000') !== false || strpos($text, 'master 1000') !== false) return 'ATP Masters 1000';
    if (strpos($text, 'atp 500') !== false) return 'ATP 500';
    if (strpos($text, 'atp 250') !== false) return 'ATP 250';
    if (preg_match('/monte[\s-]?carlo|madrid|roma|rome|barcelona|monte carlo/i', $text)) return 'ATP Masters 1000';
    if (preg_match('/munich|lyon|estoril|geneva|geneve|bucharest|bucarest/i', $text)) return 'ATP 250';
    // WTA
    if (preg_match('/wta\s*(1000|500|250|125)/i', $text)) return 'WTA ' . preg_replace('/.*wta\s*/i','',preg_replace('/[\s,].*/','',substr($text, strpos(strtolower($text),'wta'))));
    if (strpos($text, 'wta 1000') !== false) return 'WTA 1000';
    if (strpos($text, 'wta 500') !== false) return 'WTA 500';
    if (strpos($text, 'wta 250') !== false) return 'WTA 250';
    if (preg_match('/stuttgart|charleston|rouen/i', $text)) return 'WTA';
    // Challenger
    if (strpos($text, 'challenger') !== false) return 'Challenger';
    if (strpos($text, 'ch ') !== false || preg_match('/\bch\d/i', $text)) return 'Challenger';
    // ITF
    if (strpos($text, 'itf') !== false) return 'ITF';
    if (preg_match('/w\d{2,3}/i', $text)) return 'ITF Femmes';
    if (preg_match('/m\d{2,3}/i', $text) && strpos($text, 'match') === false && strpos($text, 'min') === false) return 'ITF Hommes';
    // Grand Slam
    if (preg_match('/roland[\s-]?garros|french open/i', $text)) return 'Grand Slam';
    if (preg_match('/wimbledon|us open|australian open/i', $text)) return 'Grand Slam';
    // ATP generic
    if (strpos($text, 'atp') !== false) return 'ATP';
    if (strpos($text, 'wta') !== false) return 'WTA';
    return 'Non identifié';
}

// Classifier
$stats = [];
$allBets = [];
foreach ($bets as $b) {
    $comp = detectCompetition($b['titre'] ?? '', $b['description'] ?? '');
    $res = $b['resultat'] ?? '';
    if (!isset($stats[$comp])) $stats[$comp] = ['W' => 0, 'L' => 0, 'A' => 0, 'P' => 0, 'total' => 0];
    $stats[$comp]['total']++;
    if ($res === 'gagne') $stats[$comp]['W']++;
    elseif ($res === 'perdu') $stats[$comp]['L']++;
    elseif ($res === 'annule') $stats[$comp]['A']++;
    else $stats[$comp]['P']++;

    $allBets[] = [
        'id' => $b['id'],
        'date' => date('d/m', strtotime($b['date_post'])),
        'titre' => $b['titre'],
        'comp' => $comp,
        'cote' => $b['cote'],
        'resultat' => $res,
        'type' => $b['type'],
        'role' => $b['posted_by_role'],
    ];
}

// Totaux
$totalW = array_sum(array_column($stats, 'W'));
$totalL = array_sum(array_column($stats, 'L'));
$totalA = array_sum(array_column($stats, 'A'));
$totalP = array_sum(array_column($stats, 'P'));
$totalAll = $totalW + $totalL + $totalA + $totalP;
$winrate = ($totalW + $totalL) > 0 ? round($totalW / ($totalW + $totalL) * 100, 1) : 0;

// Tri par nombre de bets
arsort($stats);
?>
<!DOCTYPE html>
<html lang="fr"><head><meta charset="UTF-8"><title>Scan Tennis Avril 2026</title>
<style>
*{margin:0;padding:0;box-sizing:border-box;}
body{background:#0a0a12;color:#f0f4f8;font-family:'Segoe UI',sans-serif;padding:2rem;}
h1{font-size:1.5rem;margin-bottom:0.5rem;color:#ff2d78;}
.sub{color:rgba(255,255,255,0.5);margin-bottom:2rem;}
table{width:100%;border-collapse:collapse;margin-bottom:2rem;}
th{text-align:left;padding:0.6rem;color:rgba(255,255,255,0.4);font-size:0.8rem;letter-spacing:1px;text-transform:uppercase;border-bottom:1px solid rgba(255,255,255,0.1);}
td{padding:0.5rem 0.6rem;border-bottom:1px solid rgba(255,255,255,0.04);font-size:0.9rem;}
.w{color:#00d46a;font-weight:700;}.l{color:#ff4444;font-weight:700;}.a{color:#ffc107;}.p{color:rgba(255,255,255,0.3);}
.comp-name{font-weight:600;color:#00d4ff;}
.total-row td{border-top:2px solid rgba(255,255,255,0.15);font-weight:700;font-size:1rem;}
.winrate{font-size:2rem;font-weight:900;margin:0.5rem 0;}
.wr-good{color:#00d46a;}.wr-ok{color:#ffc107;}.wr-bad{color:#ff4444;}
.detail{font-size:0.82rem;}
.badge{display:inline-block;padding:2px 8px;border-radius:4px;font-size:0.75rem;font-weight:700;}
.badge-w{background:rgba(0,212,106,0.15);color:#00d46a;}
.badge-l{background:rgba(255,68,68,0.15);color:#ff4444;}
.badge-a{background:rgba(255,193,7,0.15);color:#ffc107;}
.badge-p{background:rgba(255,255,255,0.08);color:rgba(255,255,255,0.4);}
h2{font-size:1.1rem;margin:1.5rem 0 0.5rem;color:#00d4ff;}
</style></head><body>
<h1>🎾 Scan Tennis — Avril 2026</h1>
<div class="sub"><?= count($bets) ?> bets trouvés (admin_tennis + categorie tennis) du 01/04 au 30/04</div>

<div style="display:flex;gap:2rem;margin-bottom:2rem;flex-wrap:wrap;">
  <div style="background:rgba(0,212,106,0.08);border:1px solid rgba(0,212,106,0.2);border-radius:12px;padding:1.2rem 2rem;text-align:center;">
    <div style="font-size:0.8rem;color:rgba(255,255,255,0.4);text-transform:uppercase;letter-spacing:1px;">Winrate</div>
    <div class="winrate <?= $winrate >= 60 ? 'wr-good' : ($winrate >= 45 ? 'wr-ok' : 'wr-bad') ?>"><?= $winrate ?>%</div>
    <div style="font-size:0.85rem;color:rgba(255,255,255,0.5);"><?= $totalW ?>W / <?= $totalL ?>L (<?= $totalA ?> annulé<?= $totalA>1?'s':'' ?>, <?= $totalP ?> en cours)</div>
  </div>
  <div style="background:rgba(255,45,120,0.08);border:1px solid rgba(255,45,120,0.2);border-radius:12px;padding:1.2rem 2rem;text-align:center;">
    <div style="font-size:0.8rem;color:rgba(255,255,255,0.4);text-transform:uppercase;letter-spacing:1px;">Total bets</div>
    <div class="winrate" style="color:#ff2d78;"><?= $totalAll ?></div>
    <div style="font-size:0.85rem;color:rgba(255,255,255,0.5);">Avril 2026</div>
  </div>
</div>

<h2>📊 Stats par compétition</h2>
<table>
<thead><tr><th>Compétition</th><th>Total</th><th>W</th><th>L</th><th>Annulé</th><th>En cours</th><th>Winrate</th></tr></thead>
<tbody>
<?php foreach ($stats as $comp => $s):
    $wr = ($s['W'] + $s['L']) > 0 ? round($s['W'] / ($s['W'] + $s['L']) * 100, 1) : '-';
?>
<tr>
  <td class="comp-name"><?= htmlspecialchars($comp) ?></td>
  <td><?= $s['total'] ?></td>
  <td class="w"><?= $s['W'] ?></td>
  <td class="l"><?= $s['L'] ?></td>
  <td class="a"><?= $s['A'] ?></td>
  <td class="p"><?= $s['P'] ?></td>
  <td><span class="<?= is_numeric($wr) ? ($wr >= 60 ? 'w' : ($wr >= 45 ? 'a' : 'l')) : 'p' ?>"><?= $wr ?>%</span></td>
</tr>
<?php endforeach; ?>
<tr class="total-row">
  <td>TOTAL</td>
  <td><?= $totalAll ?></td>
  <td class="w"><?= $totalW ?></td>
  <td class="l"><?= $totalL ?></td>
  <td class="a"><?= $totalA ?></td>
  <td class="p"><?= $totalP ?></td>
  <td><span class="<?= $winrate >= 60 ? 'w' : ($winrate >= 45 ? 'a' : 'l') ?>"><?= $winrate ?>%</span></td>
</tr>
</tbody>
</table>

<h2>📋 Détail de tous les bets</h2>
<table class="detail">
<thead><tr><th>Date</th><th>Titre</th><th>Compétition</th><th>Type</th><th>Cote</th><th>Résultat</th></tr></thead>
<tbody>
<?php foreach ($allBets as $b): ?>
<tr>
  <td><?= $b['date'] ?></td>
  <td><?= htmlspecialchars(mb_substr($b['titre'], 0, 60)) ?></td>
  <td class="comp-name"><?= htmlspecialchars($b['comp']) ?></td>
  <td><?= htmlspecialchars($b['type']) ?></td>
  <td><?= htmlspecialchars($b['cote'] ?: '-') ?></td>
  <td><?php
    if ($b['resultat'] === 'gagne') echo '<span class="badge badge-w">✅ W</span>';
    elseif ($b['resultat'] === 'perdu') echo '<span class="badge badge-l">❌ L</span>';
    elseif ($b['resultat'] === 'annule') echo '<span class="badge badge-a">↺ Annulé</span>';
    else echo '<span class="badge badge-p">⏳ En cours</span>';
  ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>

<p style="margin-top:2rem;color:rgba(255,255,255,0.3);font-size:0.8rem;">⚠️ La détection de compétition est basée sur le titre du bet. Certains matchs peuvent être mal classés si le titre ne mentionne pas la compétition explicitement.</p>
</body></html>
