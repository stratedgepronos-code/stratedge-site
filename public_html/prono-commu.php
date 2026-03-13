<?php
// ============================================================
// STRATEDGE — Prono de la commu
// Matchs du lendemain (foot), vote jusqu'à 23h59, timer, analyse à droite
// ============================================================
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/mailer.php';
require_once __DIR__ . '/includes/import_football_matches.php';
$db = getDB();

// Créer les tables si elles n'existent pas (évite HTTP 500 si migration non exécutée)
try {
    $db->query("SELECT 1 FROM commu_matches LIMIT 1");
} catch (Throwable $e) {
    if (strpos($e->getMessage(), 'commu_matches') !== false || strpos($e->getMessage(), "doesn't exist") !== false) {
        $db->exec("CREATE TABLE IF NOT EXISTS `commu_matches` (
          `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
          `match_date` DATE NOT NULL,
          `team_home` VARCHAR(120) NOT NULL,
          `team_away` VARCHAR(120) NOT NULL,
          `competition` VARCHAR(120) DEFAULT NULL,
          `heure` VARCHAR(20) DEFAULT NULL,
          `vote_closed_at` DATETIME NOT NULL,
          `is_winner` TINYINT(1) NOT NULL DEFAULT 0,
          `analysis_html` MEDIUMTEXT DEFAULT NULL,
          `analysis_at` DATETIME DEFAULT NULL,
          `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          KEY `idx_match_date` (`match_date`),
          KEY `idx_vote_closed` (`vote_closed_at`),
          KEY `idx_winner` (`is_winner`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $db->exec("CREATE TABLE IF NOT EXISTS `commu_votes` (
          `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
          `match_id` INT UNSIGNED NOT NULL,
          `membre_id` INT UNSIGNED NOT NULL,
          `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          UNIQUE KEY `one_vote_per_member_per_round` (`membre_id`,`match_id`),
          KEY `idx_match` (`match_id`),
          KEY `idx_membre` (`membre_id`),
          FOREIGN KEY (`match_id`) REFERENCES `commu_matches`(`id`) ON DELETE CASCADE,
          FOREIGN KEY (`membre_id`) REFERENCES `membres`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } else {
        throw $e;
    }
}

$membre = isLoggedIn() ? getMembre() : null;
if (!$membre) {
    header('Location: /login.php?redirect=' . urlencode('/prono-commu.php'));
    exit;
}
$currentPage = 'pronocommu';
$avatarUrl = getAvatarUrl($membre);

// Fuseau Paris pour "aujourd'hui" et "demain" (évite décalage si serveur en UTC)
$tzParis = new DateTimeZone('Europe/Paris');
$nowDt = new DateTime('now', $tzParis);
$today = $nowDt->format('Y-m-d');
$tomorrowDt = (clone $nowDt)->modify('+1 day');
$tomorrow = $tomorrowDt->format('Y-m-d');
$voteCloseToday = $today . ' 23:59:00';
$now = $nowDt->format('Y-m-d H:i:s');

// ── Auto-import des matchs du lendemain si la liste est vide ──
$stmtCheck = $db->prepare("SELECT 1 FROM commu_matches WHERE vote_closed_at > ? AND is_winner = 0 LIMIT 1");
$stmtCheck->execute([$now]);
if (!$stmtCheck->fetch()) {
    $voteClosedAt = $today . ' 23:59:00';
    @importFootballMatches($db, $tomorrow, $voteClosedAt, $tzParis);
}

// ── Fermeture des votes (si 23h59 passée et pas encore traité) ──
$stmtRounds = $db->prepare("SELECT DISTINCT vote_closed_at FROM commu_matches WHERE vote_closed_at < ? AND is_winner = 0");
$stmtRounds->execute([$now]);
$roundsToClose = $stmtRounds->fetchAll(PDO::FETCH_COLUMN);
foreach ($roundsToClose as $closedAt) {
    $stmtMatches = $db->prepare("
      SELECT m.id, m.team_home, m.team_away, m.match_date, m.heure, m.competition,
             (SELECT COUNT(*) FROM commu_votes v WHERE v.match_id = m.id) AS nb_votes
      FROM commu_matches m
      WHERE m.vote_closed_at = ?
      ORDER BY nb_votes DESC, m.id ASC
      LIMIT 1
    ");
    $stmtMatches->execute([$closedAt]);
    $winner = $stmtMatches->fetch(PDO::FETCH_ASSOC);
    if ($winner) {
        $db->prepare("UPDATE commu_matches SET is_winner = 1 WHERE id = ?")->execute([$winner['id']]);
        $stmtCounts = $db->prepare("
          SELECT m.team_home, m.team_away, m.heure, m.competition, m.match_date,
                 (SELECT COUNT(*) FROM commu_votes v WHERE v.match_id = m.id) AS nb
          FROM commu_matches m
          WHERE m.vote_closed_at = ?
          ORDER BY nb DESC
        ");
        $stmtCounts->execute([$closedAt]);
        $allCounts = $stmtCounts->fetchAll(PDO::FETCH_ASSOC);
        $body = "Résultats du vote Prono de la commu (fin " . date('d/m/Y H:i', strtotime($closedAt)) . "):\n\n";
        foreach ($allCounts as $row) {
            $body .= $row['team_home'] . ' - ' . $row['team_away'] . ' : ' . $row['nb'] . " vote(s)\n";
        }
        $body .= "\n→ Match sélectionné : " . $winner['team_home'] . ' - ' . $winner['team_away'];
        $body .= ' (' . ($winner['heure'] ?? '') . ', ' . ($winner['competition'] ?? '') . ")";
        @envoyerEmail(ADMIN_EMAIL, '⚽ Prono commu — Match sélectionné : ' . $winner['team_home'] . ' - ' . $winner['team_away'], nl2br(htmlspecialchars($body)));
    }
}

// ── Vote (POST) ──
$voteMessage = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'vote') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $voteMessage = 'Erreur de sécurité.';
    } else {
        $matchId = (int)($_POST['match_id'] ?? 0);
        $stmtM = $db->prepare("SELECT id, vote_closed_at FROM commu_matches WHERE id = ? AND vote_closed_at > ?");
        $stmtM->execute([$matchId, $now]);
        $match = $stmtM->fetch(PDO::FETCH_ASSOC);
        if (!$match) {
            $voteMessage = 'Ce match n\'est plus ouvert au vote.';
        } else {
            // Règle : un membre ne peut voter que pour un match par session (même vote_closed_at = même session).
            $stmtAlready = $db->prepare("
              SELECT 1 FROM commu_votes v
              JOIN commu_matches m ON m.id = v.match_id
              WHERE v.membre_id = ? AND m.vote_closed_at = ?
            ");
            $stmtAlready->execute([$membre['id'], $match['vote_closed_at']]);
            if ($stmtAlready->fetch()) {
                $voteMessage = 'Tu as déjà voté pour un match cette session. Un seul vote par session autorisé.';
            } else {
                $db->prepare("INSERT INTO commu_votes (match_id, membre_id) VALUES (?, ?)")->execute([$matchId, $membre['id']]);
                $voteMessage = 'ok';
            }
        }
    }
    if ($voteMessage !== 'ok') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $voteMessage]);
        exit;
    }
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Ton vote a bien été pris en compte. Rendez-vous à 23h59 pour les résultats !']);
    exit;
}

// ── Données pour la page ──
// Match du jour (gagnant du vote précédent)
$matchDuJour = null;
$stmtJour = $db->prepare("SELECT * FROM commu_matches WHERE match_date = ? AND is_winner = 1 LIMIT 1");
$stmtJour->execute([$today]);
$matchDuJour = $stmtJour->fetch(PDO::FETCH_ASSOC);
if (!$matchDuJour) {
    $stmtJour->execute([$tomorrow]);
    $matchDuJour = $stmtJour->fetch(PDO::FETCH_ASSOC);
}
// Matchs à voter : tous ceux dont la fin des votes n'est pas passée (match_date demain ou vote encore ouvert)
$stmtList = $db->prepare("
  SELECT m.*, (SELECT COUNT(*) FROM commu_votes v WHERE v.match_id = m.id) AS nb_votes
  FROM commu_matches m
  WHERE m.vote_closed_at > ? AND m.is_winner = 0
  ORDER BY m.match_date ASC, m.heure ASC, m.id ASC
");
$stmtList->execute([$now]);
$matchsLendemain = $stmtList->fetchAll(PDO::FETCH_ASSOC);
// Vote déjà fait pour un des matchs encore ouverts ?
$stmtVoteAt = $db->prepare("SELECT 1 FROM commu_votes v JOIN commu_matches m ON m.id = v.match_id WHERE v.membre_id = ? AND m.vote_closed_at > ? LIMIT 1");
$stmtVoteAt->execute([$membre['id'], $now]);
$dejaVote = (bool)$stmtVoteAt->fetch();
// Timer : fin des votes aujourd'hui 23h59
$timerTarget = $today . 'T23:59:00';
$tz = new DateTimeZone('Europe/Paris');
$dt = new DateTime($timerTarget, $tz);
$timerTargetTs = $dt->getTimestamp() * 1000;

// Pour affichage : on n'affiche pas les nombres de votes aux membres
foreach ($matchsLendemain as &$m) { unset($m['nb_votes']); }

// Analyse : on conserve les scripts pour l'affichage des graphiques (Chart.js, ApexCharts, etc.). Contenu défini par l'admin uniquement.
if ($matchDuJour && !empty($matchDuJour['analysis_html'])) {
    $matchDuJour['analysis_html'] = preg_replace('/<iframe\b[^>]*>.*?<\/iframe>/is', '', $matchDuJour['analysis_html']);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Prono de la commu – StratEdge</title>
<link rel="icon" type="image/png" href="assets/images/mascotte.png">
<link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@400;500;600;700&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
<?php require_once __DIR__ . '/includes/sidebar-css.php'; ?>
<style>
:root{--bg:#050810;--card:#111827;--pink:#ff2d78;--blue:#00d4ff;--txt:#f0f4f8;--txt2:#b0bec9;--txt3:#8a9bb0;--border:rgba(255,45,120,0.15);}
.prono-commu-wrap{display:grid;grid-template-columns:minmax(0,520px) minmax(0,1fr);gap:1.5rem;align-items:start;padding:0 0 2rem;}
@media(max-width:900px){.prono-commu-wrap{grid-template-columns:1fr;}}
@media(max-width:640px){.analysis-inner .chart-box,.analysis-inner .player .chart-box{width:100%!important;height:240px;min-width:0;}}
.prono-hero{text-align:center;padding:1.5rem 1rem;margin:-2.5rem -3rem 2rem -3rem;background:linear-gradient(180deg,rgba(255,45,120,0.06) 0%,transparent 100%);border-bottom:1px solid var(--border);}
.prono-hero-tag{font-family:'Space Mono',monospace;font-size:0.7rem;letter-spacing:3px;text-transform:uppercase;color:var(--pink);margin-bottom:0.5rem;}
.prono-hero-title{font-family:'Orbitron',sans-serif;font-size:1.4rem;font-weight:700;margin-bottom:0.5rem;}
.prono-hero-sub{color:var(--txt2);font-size:0.95rem;}
.countdown{display:flex;align-items:center;justify-content:center;gap:6px;margin:16px 0 0;}
.cd-block{display:flex;flex-direction:column;align-items:center;background:rgba(255,45,120,0.08);border:1px solid rgba(255,45,120,0.25);border-radius:8px;padding:8px 12px;min-width:56px;}
.cd-num{font-family:'Orbitron',sans-serif;font-size:22px;font-weight:700;color:#fff;line-height:1;text-shadow:0 0 16px rgba(255,45,120,0.5);}
.cd-label{font-size:9px;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:var(--txt3);margin-top:4px;}
.cd-sep{font-family:'Orbitron',sans-serif;font-size:20px;font-weight:700;color:var(--pink);margin-bottom:12px;}
.panel{background:var(--card);border-radius:14px;border:1px solid var(--border);overflow:visible;}
.panel.votes-panel{overflow:hidden;margin-right:2.5rem;}
.panel-title{font-family:'Orbitron',sans-serif;font-size:0.85rem;font-weight:700;padding:1rem 1.2rem;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:0.5rem;}
.match-row{display:flex;align-items:center;justify-content:space-between;gap:0.75rem;padding:1rem 1.2rem;border-bottom:1px solid rgba(255,255,255,0.06);}
.match-row:last-child{border-bottom:none;}
.match-info{flex:1;min-width:0;}
.match-teams{font-weight:600;font-size:0.95rem;color:var(--txt);line-height:1.35;}
.match-meta{font-size:0.8rem;color:var(--txt3);margin-top:0.25rem;}
.btn-vote{background:linear-gradient(135deg,var(--pink),#d6245f);color:#fff;border:none;padding:0.5rem 1rem;border-radius:8px;font-weight:700;font-size:0.8rem;cursor:pointer;white-space:nowrap;}
.btn-vote:hover{box-shadow:0 0 20px rgba(255,45,120,0.4);}
.btn-vote:disabled{opacity:0.6;cursor:not-allowed;}
.analysis-inner{padding:1.2rem;color:var(--txt2);font-size:0.95rem;line-height:1.6;overflow:visible;}
.analysis-panel .panel-title{background:rgba(0,212,255,0.05);padding:0.75rem 1rem;}
.analysis-panel .analysis-inner{padding:0.85rem 1rem;}
.analysis-inner img{max-width:100%;height:auto;}
.analysis-inner .chart-box{height:220px;position:relative;min-height:180px;}
.analysis-inner .sec{overflow:visible!important;}
.analysis-inner canvas{display:block;}
.analysis-inner .player .chart-box{width:280px;min-width:240px;height:220px;position:relative;}
.analysis-placeholder{color:var(--txt3);font-style:italic;}
.vote-toast{position:fixed;bottom:2rem;left:50%;transform:translateX(-50%);background:var(--card);border:1px solid rgba(0,212,106,0.4);color:#00c864;padding:1rem 1.5rem;border-radius:12px;font-weight:600;z-index:9999;box-shadow:0 8px 32px rgba(0,0,0,0.5);animation:fadeInUp .3s ease;}
@keyframes fadeInUp{from{opacity:0;transform:translateX(-50%) translateY(12px);}to{opacity:1;transform:translateX(-50%) translateY(0);}}
.no-matchs{text-align:center;padding:2rem;color:var(--txt3);}
</style>
</head>
<body>
<?php require_once __DIR__ . '/includes/sidebar.php'; ?>

<div class="prono-hero">
  <div class="prono-hero-tag">⚽ Prono de la commu</div>
  <?php if ($matchDuJour): ?>
  <h1 class="prono-hero-title">Match de la commu<?= $matchDuJour['match_date'] === $today ? ' aujourd\'hui' : ' demain' ?> : <?= clean($matchDuJour['team_home']) ?> – <?= clean($matchDuJour['team_away']) ?></h1>
  <p class="prono-hero-sub"><?= $matchDuJour['heure'] ? clean($matchDuJour['heure']) . ' — ' : '' ?><?= clean($matchDuJour['competition'] ?? '') ?></p>
  <?php else: ?>
  <h1 class="prono-hero-title">Votez pour le match de demain</h1>
  <p class="prono-hero-sub">Choisis le match que tu veux voir analysé. Fin des votes à 23h59.</p>
  <p class="prono-hero-sub" style="font-size:0.8rem;color:var(--txt3);margin-top:0.25rem;">Un seul vote par membre et par session.</p>
  <?php endif; ?>
  <div class="countdown" id="countdown">
    <div class="cd-block"><span class="cd-num" id="cd-hours">--</span><span class="cd-label">Heures</span></div>
    <div class="cd-sep">:</div>
    <div class="cd-block"><span class="cd-num" id="cd-mins">--</span><span class="cd-label">Min</span></div>
    <div class="cd-sep">:</div>
    <div class="cd-block"><span class="cd-num" id="cd-secs">--</span><span class="cd-label">Sec</span></div>
  </div>
  <p class="prono-hero-sub" id="timer-label" style="margin-top:0.5rem;font-size:0.8rem;">avant la fin des votes (23h59)</p>
  <p class="prono-hero-sub" id="timer-expired" style="display:none;margin-top:0.5rem;font-size:0.9rem;color:var(--pink);">Votes clos. Résultats au-dessus.</p>
</div>

<div class="prono-commu-wrap">
  <aside class="panel votes-panel">
    <div class="panel-title">📋 Matchs du lendemain — Vote <span style="font-size:0.7rem;font-weight:400;color:var(--txt3);">(1 vote par session)</span></div>
    <?php if (empty($matchsLendemain)): ?>
    <div class="no-matchs">Aucun match à voter pour le moment.<br><small>Les matchs sont ajoutés par l'équipe (admin → Prono de la commu) ou importés via l'API Football-Data.</small></div>
    <?php else: ?>
    <?php foreach ($matchsLendemain as $mat): ?>
    <div class="match-row" data-match-id="<?= (int)$mat['id'] ?>">
      <div class="match-info">
        <div class="match-teams"><?= clean($mat['team_home']) ?> – <?= clean($mat['team_away']) ?></div>
        <div class="match-meta"><?= $mat['heure'] ? clean($mat['heure']) . ' · ' : '' ?><?= clean($mat['competition'] ?? '') ?></div>
      </div>
      <button type="button" class="btn-vote" data-match-id="<?= (int)$mat['id'] ?>" <?= $dejaVote ? 'disabled' : '' ?>><?= $dejaVote ? 'Voté' : 'Voter' ?></button>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
  </aside>

  <aside class="panel analysis-panel">
    <div class="panel-title">📊 Analyse — Prono gratuit</div>
    <?php if ($matchDuJour && !empty($matchDuJour['analysis_html'])): ?>
    <script>
    (function(){
      var _real = null;
      Object.defineProperty(window, 'Chart', {
        configurable: true,
        get: function(){ return _real; },
        set: function(C){
          if (typeof C !== 'function') { _real = C; return; }
          _real = new Proxy(C, {
            construct: function(target, args){
              if (args[1] && args[1].options) {
                args[1].options.responsive = true;
                args[1].options.maintainAspectRatio = false;
              } else if (args[1]) {
                args[1].options = { responsive: true, maintainAspectRatio: false };
              }
              return new target(...args);
            }
          });
          Object.assign(_real, C);
          _real.prototype = C.prototype;
          if (C.defaults) _real.defaults = C.defaults;
          if (C.register) _real.register = C.register.bind(C);
          if (C.instances) _real.instances = C.instances;
        }
      });
    })();
    </script>
    <?php endif; ?>
    <div class="analysis-inner">
      <?php if ($matchDuJour && !empty($matchDuJour['analysis_html'])): ?>
      <?= $matchDuJour['analysis_html'] ?>
      <script>
      (function(){
        document.querySelectorAll('.analysis-inner canvas').forEach(function(c){
          if (!c.getAttribute('height')) c.setAttribute('height','200');
          var box = c.closest('.chart-box');
          if (box) { box.style.height = box.style.height || '220px'; box.style.position = 'relative'; }
          var sec = c.closest('.sec');
          if (sec) sec.style.overflow = 'visible';
        });
        window.addEventListener('load', function(){
          if (!window.Chart || !window.Chart.instances) return;
          Object.values(window.Chart.instances).forEach(function(ch){
            if (ch && ch.options) { ch.options.maintainAspectRatio = false; ch.resize(); }
          });
        });
      })();
      </script>
      <?php else: ?>
      <p class="analysis-placeholder">RDV 40 min avant le match pour le prono gratuit.</p>
      <?php endif; ?>
    </div>
  </aside>
</div>

<input type="hidden" id="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
<input type="hidden" id="timer_target" value="<?= (int)$timerTargetTs ?>">

<script>
(function(){
  var target = parseInt(document.getElementById('timer_target').value, 10) || 0;
  var els = { h: document.getElementById('cd-hours'), m: document.getElementById('cd-mins'), s: document.getElementById('cd-secs') };
  function pad(n){ return n < 10 ? '0'+n : ''+n; }
  function tick(){
    var now = Date.now();
    var diff = target - now;
    if (diff <= 0) {
      els.h.textContent = els.m.textContent = els.s.textContent = '00';
      var lab = document.getElementById('timer-label');
      var exp = document.getElementById('timer-expired');
      var cd = document.getElementById('countdown');
      if (lab) lab.style.display = 'none';
      if (exp) exp.style.display = 'block';
      if (cd) cd.style.opacity = '0.6';
      return;
    }
    var h = Math.floor(diff / 3600000);
    var m = Math.floor((diff % 3600000) / 60000);
    var s = Math.floor((diff % 60000) / 1000);
    els.h.textContent = pad(h);
    els.m.textContent = pad(m);
    els.s.textContent = pad(s);
  }
  tick();
  setInterval(tick, 1000);
})();

document.querySelectorAll('.btn-vote[data-match-id]').forEach(function(btn){
  if (btn.disabled) return;
  btn.addEventListener('click', function(){
    var matchId = btn.getAttribute('data-match-id');
    var csrf = document.getElementById('csrf_token').value;
    var form = new FormData();
    form.append('action', 'vote');
    form.append('csrf_token', csrf);
    form.append('match_id', matchId);
    btn.disabled = true;
    btn.textContent = '…';
    fetch('prono-commu.php', { method: 'POST', body: form })
      .then(function(r){ return r.json(); })
      .then(function(data){
        if (data.success) {
          btn.textContent = 'Voté';
          var toast = document.createElement('div');
          toast.className = 'vote-toast';
          toast.textContent = data.message || 'Ton vote a bien été pris en compte. Rendez-vous à 23h59 pour les résultats !';
          document.body.appendChild(toast);
          setTimeout(function(){ toast.remove(); }, 4000);
        } else {
          btn.disabled = false;
          btn.textContent = 'Voter';
          alert(data.message || 'Erreur');
        }
      })
      .catch(function(){
        btn.disabled = false;
        btn.textContent = 'Voter';
        alert('Erreur réseau.');
      });
  });
});
</script>
<?php require_once __DIR__ . '/includes/footer-main.php'; ?>
</body>
</html>
