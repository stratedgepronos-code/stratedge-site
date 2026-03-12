<?php
// ============================================================
// STRATEDGE — Admin Prono de la commu : ajouter matchs, poster l'analyse
// ============================================================
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
$pageActive = 'prono-commu-admin';
$db = getDB();

$success = '';
$error = '';

$footballConfig = file_exists(__DIR__ . '/../includes/football_data_config.php')
    ? (require __DIR__ . '/../includes/football_data_config.php') : ['api_key' => ''];
$apiKey = is_array($footballConfig) ? ($footballConfig['api_key'] ?? '') : '';

// Importer les matchs du lendemain via API Football-Data.org
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'import_api') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $error = 'Erreur de sécurité.';
    } elseif ($apiKey === '') {
        $error = 'Configure ta clé API dans includes/football_data_config.php (clé gratuite sur football-data.org).';
    } else {
        $tomorrowApi = date('Y-m-d', strtotime('+1 day'));
        $dayAfter = date('Y-m-d', strtotime('+2 days'));
        $url = 'https://api.football-data.org/v4/matches?dateFrom=' . $tomorrowApi . '&dateTo=' . $dayAfter;
        $ctx = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => "X-Auth-Token: " . $apiKey . "\r\n",
                'timeout' => 15,
            ]
        ]);
        $json = @file_get_contents($url, false, $ctx);
        if ($json === false) {
            $error = 'Impossible de contacter l\'API (timeout ou réseau).';
        } else {
            $data = @json_decode($json, true);
            if (!isset($data['matches']) || !is_array($data['matches'])) {
                $msg = isset($data['message']) ? $data['message'] : 'Réponse API invalide.';
                $error = 'API : ' . $msg;
            } else {
                $voteClosedAt = date('Y-m-d 23:59:00', strtotime('today'));
                $inserted = 0;
                $stmtExists = $db->prepare("SELECT 1 FROM commu_matches WHERE match_date = ? AND team_home = ? AND team_away = ?");
                $stmtIns = $db->prepare("INSERT INTO commu_matches (match_date, team_home, team_away, competition, heure, vote_closed_at) VALUES (?, ?, ?, ?, ?, ?)");
                $tz = new DateTimeZone('Europe/Paris');
                foreach ($data['matches'] as $m) {
                    $home = isset($m['homeTeam']['name']) ? trim($m['homeTeam']['name']) : (isset($m['homeTeam']['shortName']) ? trim($m['homeTeam']['shortName']) : '');
                    $away = isset($m['awayTeam']['name']) ? trim($m['awayTeam']['name']) : (isset($m['awayTeam']['shortName']) ? trim($m['awayTeam']['shortName']) : '');
                    if ($home === '' || $away === '') continue;
                    $competition = isset($m['competition']['name']) ? trim($m['competition']['name']) : '';
                    $heure = '';
                    if (!empty($m['utcDate'])) {
                        try {
                            $dt = new DateTime($m['utcDate'], new DateTimeZone('UTC'));
                            $dt->setTimezone($tz);
                            $heure = $dt->format('H:i');
                        } catch (Exception $e) { }
                    }
                    $stmtExists->execute([$tomorrowApi, $home, $away]);
                    if ($stmtExists->fetch()) continue;
                    $stmtIns->execute([$tomorrowApi, $home, $away, $competition ?: null, $heure ?: null, $voteClosedAt]);
                    $inserted++;
                }
                $success = $inserted > 0 ? $inserted . ' match(s) importé(s) pour demain.' : 'Aucun nouveau match (déjà en base ou pas de match ce jour-là).';
            }
        }
    }
}

// Ajouter un match (foot, lendemain)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $error = 'Erreur de sécurité.';
    } elseif ($_POST['action'] === 'add_match') {
        $teamHome = trim($_POST['team_home'] ?? '');
        $teamAway = trim($_POST['team_away'] ?? '');
        $competition = trim($_POST['competition'] ?? '');
        $heure = trim($_POST['heure'] ?? '');
        $matchDate = $_POST['match_date'] ?? '';
        if ($teamHome === '' || $teamAway === '' || $matchDate === '') {
            $error = 'Équipes et date du match requis.';
        } else {
            $voteClosedAt = date('Y-m-d 23:59:00', strtotime($matchDate . ' -1 day'));
            $stmt = $db->prepare("INSERT INTO commu_matches (match_date, team_home, team_away, competition, heure, vote_closed_at) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$matchDate, $teamHome, $teamAway, $competition ?: null, $heure ?: null, $voteClosedAt]);
            $success = 'Match ajouté. Fin des votes : ' . date('d/m/Y H:i', strtotime($voteClosedAt));
        }
    } elseif ($_POST['action'] === 'save_analysis') {
        $matchId = (int)($_POST['match_id'] ?? 0);
        $analysis = trim($_POST['analysis_html'] ?? '');
        if ($matchId && $analysis !== '') {
            $analysis = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $analysis);
            $analysis = preg_replace('/<iframe\b[^>]*>.*?<\/iframe>/is', '', $analysis);
            $stmt = $db->prepare("UPDATE commu_matches SET analysis_html = ?, analysis_at = NOW() WHERE id = ? AND is_winner = 1");
            $stmt->execute([$analysis, $matchId]);
            $success = 'Analyse enregistrée.';
        } else {
            $error = 'Sélectionne un match et saisis l\'analyse.';
        }
    }
}

$tomorrow = date('Y-m-d', strtotime('+1 day'));
$stmtList = $db->query("
  SELECT m.*, (SELECT COUNT(*) FROM commu_votes v WHERE v.match_id = m.id) AS nb_votes
  FROM commu_matches m
  ORDER BY m.match_date DESC, m.heure ASC
  LIMIT 80
");
$allMatches = $stmtList->fetchAll(PDO::FETCH_ASSOC);
$winners = array_filter($allMatches, function($m) { return !empty($m['is_winner']); });
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Prono de la commu – Admin</title>
<link rel="icon" type="image/png" href="../assets/images/mascotte.png">
<link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@400;500;600;700&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
<?php require_once __DIR__ . '/sidebar.php'; ?>
<style>
.main { padding:1.5rem 2rem; }
.page-header { margin-bottom:1.5rem; }
.page-header h1 { font-family:'Orbitron',sans-serif; font-size:1.4rem; }
.card { background:var(--bg-card); border:1px solid var(--border-subtle); border-radius:14px; padding:1.5rem; margin-bottom:1.5rem; }
.card h2 { font-size:1rem; margin-bottom:1rem; display:flex; align-items:center; gap:0.5rem; }
.form-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(200px,1fr)); gap:1rem; align-items:end; }
.form-group label { display:block; font-size:0.75rem; color:var(--text-muted); margin-bottom:0.35rem; }
.form-group input, .form-group textarea { width:100%; padding:0.6rem; background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.1); border-radius:8px; color:var(--text-primary); }
.btn { padding:0.6rem 1.2rem; border-radius:8px; font-weight:700; cursor:pointer; border:none; }
.btn-pink { background:linear-gradient(135deg,var(--neon-green),var(--neon-green-dim)); color:#fff; }
.table-wrap { overflow-x:auto; }
table { width:100%; border-collapse:collapse; }
th, td { padding:0.6rem 0.8rem; text-align:left; border-bottom:1px solid rgba(255,255,255,0.06); font-size:0.9rem; }
th { color:var(--text-muted); font-weight:600; }
.alert-success { background:rgba(0,212,106,0.1); border:1px solid rgba(0,212,106,0.3); color:#00c864; padding:0.8rem; border-radius:8px; margin-bottom:1rem; }
.alert-error { background:rgba(255,45,120,0.1); border:1px solid rgba(255,45,120,0.3); color:#ff6b9d; padding:0.8rem; border-radius:8px; margin-bottom:1rem; }
.analysis-textarea { min-height:220px; resize:vertical; font-family:inherit; }
</style>
</head>
<body>

<div class="main">
  <div class="page-header">
    <h1>⚽ Prono de la commu — Admin</h1>
    <p style="color:var(--text-muted);font-size:0.9rem;">Ajouter les matchs du lendemain (foot), poster l'analyse du match gagnant.</p>
  </div>

  <?php if ($success): ?><div class="alert-success">✅ <?= htmlspecialchars($success) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="alert-error">⚠️ <?= htmlspecialchars($error) ?></div><?php endif; ?>

  <div class="card">
    <h2>🌐 Importer les matchs du lendemain (API Football-Data.org)</h2>
    <p style="color:var(--text-muted);font-size:0.85rem;margin-bottom:1rem;">Récupère automatiquement les matchs de foot prévus demain. Clé gratuite : <a href="https://www.football-data.org/client/register" target="_blank" rel="noopener" style="color:var(--neon-green);">s'inscrire sur football-data.org</a>, puis définir <code>api_key</code> dans <code>includes/football_data_config.php</code>.</p>
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
      <input type="hidden" name="action" value="import_api">
      <button type="submit" class="btn btn-pink" <?= $apiKey === '' ? 'disabled title="Configure la clé API d\'abord"' : '' ?>>Importer les matchs du lendemain</button>
    </form>
  </div>

  <div class="card">
    <h2>➕ Ajouter un match à la main (lendemain)</h2>
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
      <input type="hidden" name="action" value="add_match">
      <div class="form-grid">
        <div class="form-group">
          <label>Équipe domicile</label>
          <input type="text" name="team_home" placeholder="Ex: PSG" required>
        </div>
        <div class="form-group">
          <label>Équipe extérieur</label>
          <input type="text" name="team_away" placeholder="Ex: Marseille" required>
        </div>
        <div class="form-group">
          <label>Compétition</label>
          <input type="text" name="competition" placeholder="Ex: Ligue 1">
        </div>
        <div class="form-group">
          <label>Heure</label>
          <input type="text" name="heure" placeholder="21:00">
        </div>
        <div class="form-group">
          <label>Date du match</label>
          <input type="date" name="match_date" value="<?= $tomorrow ?>" required>
        </div>
        <div class="form-group">
          <button type="submit" class="btn btn-pink">Ajouter</button>
        </div>
      </div>
    </form>
  </div>

  <div class="card">
    <h2>📊 Analyse du match gagnant</h2>
    <p style="color:var(--text-muted);font-size:0.85rem;margin-bottom:1rem;">Quand un match est choisi à 23h59, tu reçois un mail. Tu peux poster l'analyse ici (affichée à droite sur la page Prono commu).</p>
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
      <input type="hidden" name="action" value="save_analysis">
      <div class="form-group" style="margin-bottom:1rem;">
        <label>Match gagnant</label>
        <select name="match_id" style="width:100%;max-width:400px;padding:0.6rem;background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.1);border-radius:8px;color:var(--text-primary);">
          <option value="">— Choisir —</option>
          <?php foreach ($winners as $w): ?>
          <option value="<?= (int)$w['id'] ?>"><?= htmlspecialchars($w['team_home'] . ' – ' . $w['team_away']) ?> (<?= $w['match_date'] ?>)</option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group" style="margin-bottom:1rem;">
        <label>Analyse HTML (prono gratuit, RDV 40 min avant le match)</label>
        <textarea name="analysis_html" class="analysis-textarea" placeholder="Contenu HTML de l'analyse..."></textarea>
      </div>
      <button type="submit" class="btn btn-pink">Enregistrer l'analyse</button>
    </form>
  </div>

  <div class="card">
    <h2>📋 Matchs (avec nombre de votes)</h2>
    <div class="table-wrap">
      <table>
        <thead>
          <tr><th>Date match</th><th>Match</th><th>Compétition</th><th>Fin votes</th><th>Votes</th><th>Gagnant</th></tr>
        </thead>
        <tbody>
          <?php foreach ($allMatches as $m): ?>
          <tr>
            <td><?= htmlspecialchars($m['match_date']) ?></td>
            <td><?= htmlspecialchars($m['team_home'] . ' – ' . $m['team_away']) ?></td>
            <td><?= htmlspecialchars($m['competition'] ?? '') ?></td>
            <td><?= date('d/m H:i', strtotime($m['vote_closed_at'])) ?></td>
            <td><?= (int)$m['nb_votes'] ?></td>
            <td><?= !empty($m['is_winner']) ? '✅' : '—' ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php if (empty($allMatches)): ?>
    <p style="color:var(--text-muted);padding:1rem;">Aucun match. Ajoute des matchs ci-dessus.</p>
    <?php endif; ?>
  </div>
</div>

</body>
</html>
