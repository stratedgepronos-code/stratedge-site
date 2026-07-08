<?php
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
$pageActive = 'engine';

$db = getDB();
$SHOW_SIDEBAR = true;

// ── Lecture du heartbeat du bot ───────────────────────────────────
$statusFile = __DIR__ . '/data/engine-status.json';
$engine = null;
$engineAgeSec = null;
if (is_readable($statusFile)) {
    $engine = json_decode((string)file_get_contents($statusFile), true);
    if (!empty($engine['last_beat'])) {
        $engineAgeSec = time() - strtotime($engine['last_beat']);
    }
}
// État de santé : online si battement < 4 min
$isOnline = ($engineAgeSec !== null && $engineAgeSec < 240);

// ── Stats des bets réels (table bets) ─────────────────────────────
$betStats = ['total' => 0, 'gagnes' => 0, 'perdus' => 0, 'en_cours' => 0, 'roi' => null, 'avg_cote' => null];
try {
    $row = $db->query("
        SELECT
            COUNT(*) AS total,
            SUM(resultat = 'gagne') AS gagnes,
            SUM(resultat = 'perdu') AS perdus,
            SUM(resultat IN ('en_cours','pending') OR resultat IS NULL) AS en_cours,
            AVG(CAST(REPLACE(cote, ',', '.') AS DECIMAL(10,4))) AS avg_cote
        FROM bets
        WHERE CAST(REPLACE(cote, ',', '.') AS DECIMAL(10,4)) > 0
    ")->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $betStats['total']   = (int)$row['total'];
        $betStats['gagnes']  = (int)$row['gagnes'];
        $betStats['perdus']  = (int)$row['perdus'];
        $betStats['en_cours']= (int)$row['en_cours'];
        $betStats['avg_cote']= $row['avg_cote'] !== null ? round((float)$row['avg_cote'], 2) : null;
    }
    // ROI simplifié sur bets réglés à cote connue (mise unitaire = 1)
    $settled = $db->query("
        SELECT resultat, CAST(REPLACE(cote, ',', '.') AS DECIMAL(10,4)) AS c
        FROM bets
        WHERE resultat IN ('gagne','perdu')
          AND CAST(REPLACE(cote, ',', '.') AS DECIMAL(10,4)) > 0
    ")->fetchAll(PDO::FETCH_ASSOC);
    if ($settled) {
        $pl = 0.0; $n = 0;
        foreach ($settled as $s) {
            $n++;
            $pl += ($s['resultat'] === 'gagne') ? ((float)$s['c'] - 1) : -1;
        }
        $betStats['roi'] = $n ? round($pl / $n * 100, 1) : null;
        $betStats['pl'] = round($pl, 2);
    }
} catch (Throwable $e) { /* table indispo : placeholders */ }

$winrate = ($betStats['gagnes'] + $betStats['perdus']) > 0
    ? round($betStats['gagnes'] / ($betStats['gagnes'] + $betStats['perdus']) * 100)
    : null;

// ── 10 derniers bets pour le flux ─────────────────────────────────
$recentBets = [];
try {
    $recentBets = $db->query("
        SELECT titre, type, sport, cote, resultat,
               COALESCE(date_resultat, date_post) AS d
        FROM bets
        ORDER BY COALESCE(date_resultat, date_post) DESC
        LIMIT 12
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {}

function fmtAge(?int $s): string {
    if ($s === null) return '—';
    if ($s < 60) return $s . 's';
    if ($s < 3600) return floor($s/60) . 'min';
    return floor($s/3600) . 'h';
}
$LEAGUE_NAMES = [1=>'Coupe du Monde',2=>'Ligue des Champions',3=>'Europa League',848=>'Conference League'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta http-equiv="refresh" content="60">
<title>Engine Control · StratEdge Admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@500;700;900&family=Rajdhani:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">
<style>
:root{
  --void:#07070e;--panel:#0d0d18;--raised:#12121f;--inset:#0a0a14;
  --pink:#ff2d78;--cyan:#00d4ff;--green:#2de5a7;--amber:#ffb547;--red:#ff4757;
  --text:#e8e9f0;--dim:#6b6e85;--faint:#3d3f52;
  --line:rgba(139,148,200,.12);--line2:rgba(139,148,200,.22);
}
*{margin:0;padding:0;box-sizing:border-box}
body{background:var(--void);color:var(--text);font-family:'Rajdhani',sans-serif;min-height:100vh;overflow-x:hidden;font-size:16.5px}
body::before{content:"";position:fixed;inset:0;pointer-events:none;z-index:0;
  background:radial-gradient(1100px 500px at 85% -10%,rgba(255,45,120,.06),transparent 60%),
  radial-gradient(900px 500px at 0% 110%,rgba(0,212,255,.05),transparent 60%)}
.mono{font-family:'JetBrains Mono';font-variant-numeric:tabular-nums}
a{color:inherit;text-decoration:none}

/* topbar */
.topbar{position:sticky;top:0;z-index:50;display:flex;align-items:center;gap:1.2rem;
  padding:.7rem 1.6rem;background:rgba(7,7,14,.92);backdrop-filter:blur(12px);border-bottom:1px solid var(--line)}
.brand{display:flex;align-items:baseline;gap:.55rem}
.brand .logo{font-family:'Orbitron';font-weight:900;font-size:1.05rem;letter-spacing:.14em;
  background:linear-gradient(90deg,var(--pink),var(--cyan));-webkit-background-clip:text;background-clip:text;color:transparent}
.brand .sub{font-family:'Orbitron';font-weight:500;font-size:.62rem;letter-spacing:.3em;color:var(--dim)}
.topbar .sp{flex:1}
.back{font-size:.8rem;font-weight:600;color:var(--dim);padding:.4rem .8rem;border:1px solid var(--line);border-radius:4px;transition:.15s}
.back:hover{border-color:var(--line2);color:var(--text)}
.livedot{display:flex;align-items:center;gap:.5rem;font-family:'JetBrains Mono';font-size:.62rem;letter-spacing:.1em;color:var(--dim)}
.livedot i{width:7px;height:7px;border-radius:50%;background:var(--green);box-shadow:0 0 10px var(--green);animation:pulse 2s infinite}
@keyframes pulse{0%,100%{opacity:1}50%{opacity:.3}}

.wrap{position:relative;z-index:1;max-width:100%;margin:0 auto;padding:1.8rem 2.4rem}
.eyebrow{font-family:'Orbitron';font-size:.68rem;letter-spacing:.3em;color:var(--dim);text-transform:uppercase;margin-bottom:.4rem}
h1{font-family:'Orbitron';font-weight:700;font-size:1.85rem;letter-spacing:.06em;margin-bottom:.3rem}
.sub1{color:var(--dim);font-size:1.08rem;font-weight:500;margin-bottom:1.6rem}

/* HERO status */
.hero{background:var(--raised);border:1px solid var(--line2);border-radius:12px;padding:1.6rem 1.8rem;position:relative;overflow:hidden;margin-bottom:1.3rem}
.hero::after{content:"";position:absolute;left:0;right:0;bottom:0;height:3px;background:linear-gradient(90deg,var(--pink),var(--cyan))}
.hero-grid{display:grid;grid-template-columns:auto 1fr auto;gap:2rem;align-items:center}
@media(max-width:820px){.hero-grid{grid-template-columns:1fr;gap:1.2rem}}
.status-orb{display:flex;flex-direction:column;align-items:center;gap:.7rem}
.orb{width:96px;height:96px;border-radius:50%;display:flex;align-items:center;justify-content:center;position:relative}
.orb.on{background:radial-gradient(circle,rgba(45,229,167,.2),transparent 70%)}
.orb.off{background:radial-gradient(circle,rgba(255,71,87,.18),transparent 70%)}
.orb .core{width:44px;height:44px;border-radius:50%}
.orb.on .core{background:var(--green);box-shadow:0 0 40px var(--green),0 0 12px #fff inset;animation:breathe 2.6s infinite}
.orb.off .core{background:var(--red);box-shadow:0 0 30px var(--red)}
@keyframes breathe{0%,100%{transform:scale(1);opacity:1}50%{transform:scale(.82);opacity:.7}}
.orb .ring{position:absolute;inset:0;border-radius:50%;border:1px solid}
.orb.on .ring{border-color:rgba(45,229,167,.35);animation:ring 2.6s infinite}
.orb.off .ring{border-color:rgba(255,71,87,.3)}
@keyframes ring{0%{transform:scale(.9);opacity:.8}100%{transform:scale(1.5);opacity:0}}
.status-orb .lbl{font-family:'Orbitron';font-weight:700;font-size:1.05rem;letter-spacing:.16em}
.status-orb .lbl.on{color:var(--green)}.status-orb .lbl.off{color:var(--red)}
.hero-info h2{font-family:'Orbitron';font-size:1.28rem;letter-spacing:.05em;margin-bottom:.5rem}
.hero-meta{display:flex;flex-wrap:wrap;gap:.5rem .7rem;margin-top:.7rem}
.chip{font-family:'JetBrains Mono';font-size:.78rem;letter-spacing:.04em;color:var(--dim);
  border:1px solid var(--line);background:var(--inset);padding:.28rem .6rem;border-radius:4px}
.chip b{color:var(--cyan);font-weight:700}
.chip.warn{border-color:rgba(255,181,71,.3);color:var(--amber)}
.uptime{text-align:right}
.uptime .big{font-family:'JetBrains Mono';font-weight:700;font-size:2rem;color:var(--green)}
.uptime .big.off{color:var(--red)}
.uptime .cap{font-size:.6rem;letter-spacing:.18em;color:var(--dim);text-transform:uppercase;font-weight:600}

/* KPI cards */
.kgrid{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:1.1rem;margin-bottom:1.3rem}
.kcard{background:var(--panel);border:1px solid var(--line);border-radius:9px;padding:1.3rem 1.4rem;position:relative;overflow:hidden}
.kcard::after{content:"";position:absolute;left:0;top:0;bottom:0;width:2px;background:linear-gradient(180deg,var(--pink),var(--cyan))}
.kcard .kv{font-family:'JetBrains Mono';font-weight:700;font-size:2.1rem;letter-spacing:-.01em;line-height:1}
.kcard .kv.pos{color:var(--green)}.kcard .kv.neg{color:var(--red)}.kcard .kv.cy{color:var(--cyan)}.kcard .kv.am{color:var(--amber)}
.kcard .kl{font-size:.72rem;letter-spacing:.16em;color:var(--dim);text-transform:uppercase;font-weight:600;margin-top:.4rem}
.kcard .kd{font-size:.82rem;color:var(--faint);margin-top:.2rem;font-weight:500}

/* layout 2 cols */
.cols{display:grid;grid-template-columns:1.3fr 1fr;gap:1.1rem}
@media(max-width:960px){.cols{grid-template-columns:1fr}}
.panel{background:var(--panel);border:1px solid var(--line);border-radius:10px;padding:1.2rem 1.3rem}
.panel + .panel{margin-top:1.1rem}
.ptitle{font-family:'Orbitron';font-size:.78rem;font-weight:700;letter-spacing:.2em;text-transform:uppercase;
  display:flex;align-items:center;gap:.6rem;margin-bottom:1.1rem}
.ptitle .tk{width:8px;height:8px;background:linear-gradient(135deg,var(--pink),var(--cyan));border-radius:1px}
.ptitle .cnt{margin-left:auto;font-family:'JetBrains Mono';font-size:.82rem;color:var(--dim);font-weight:400;letter-spacing:0}

/* alerts feed */
.alert{border-left:2px solid var(--pink);background:var(--inset);border-radius:0 6px 6px 0;padding:.7rem .9rem;margin-bottom:.7rem}
.alert:last-child{margin-bottom:0}
.alert .ah{display:flex;align-items:center;gap:.6rem;margin-bottom:.4rem}
.alert .amatch{font-weight:700;font-size:1.1rem}
.alert .ascn{font-family:'Orbitron';font-size:.52rem;letter-spacing:.12em;padding:.14rem .45rem;border-radius:3px;margin-left:auto}
.ascn.SIEGE{color:var(--pink);border:1px solid rgba(255,45,120,.4)}
.ascn.CADENAS{color:var(--cyan);border:1px solid rgba(0,212,255,.4)}
.ascn.CHAOS{color:var(--amber);border:1px solid rgba(255,181,71,.4)}
.ascn.CONTRE{color:var(--green);border:1px solid rgba(45,229,167,.4)}
.ascn.EQUILIBRE{color:var(--dim);border:1px solid var(--line2)}
.alert .atime{font-family:'JetBrains Mono';font-size:.62rem;color:var(--faint)}
.alert .apick{font-size:.95rem;color:var(--text);padding:.15rem 0;padding-left:.9rem;position:relative}
.alert .apick::before{content:"→";position:absolute;left:0;color:var(--cyan)}
.empty{text-align:center;color:var(--faint);padding:2rem 1rem;font-weight:500}
.empty .big{font-size:2rem;margin-bottom:.5rem;opacity:.5}

/* fixtures */
.fix{display:flex;align-items:center;gap:.7rem;padding:.5rem 0;border-bottom:1px solid var(--line)}
.fix:last-child{border-bottom:none}
.fix .ftime{font-family:'JetBrains Mono';font-size:.92rem;color:var(--cyan);font-weight:700;min-width:48px}
.fix .fmatch{font-weight:600;font-size:1.02rem;flex:1}
.fix .fleague{font-size:.6rem;letter-spacing:.1em;color:var(--faint);text-transform:uppercase;font-weight:600}
.fix .fwatch{font-family:'Orbitron';font-size:.5rem;letter-spacing:.1em;color:var(--amber);
  border:1px solid rgba(255,181,71,.35);padding:.15rem .4rem;border-radius:3px;animation:pulse 2s infinite}

/* recent bets */
.bet{display:flex;align-items:center;gap:.7rem;padding:.5rem 0;border-bottom:1px solid var(--line)}
.bet:last-child{border-bottom:none}
.bet .bres{width:8px;height:8px;border-radius:50%;flex-shrink:0}
.bres.gagne{background:var(--green);box-shadow:0 0 8px var(--green)}
.bres.perdu{background:var(--red)}
.bres.en_cours,.bres.pending{background:var(--amber)}
.bet .btitre{flex:1;font-weight:600;font-size:.98rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.bet .bcote{font-family:'JetBrains Mono';font-size:.95rem;color:var(--cyan);font-weight:700}
.bet .btype{font-size:.58rem;letter-spacing:.08em;color:var(--faint);text-transform:uppercase}

.foot{margin-top:1.6rem;padding-top:1rem;border-top:1px solid var(--line);
  display:flex;justify-content:space-between;align-items:center;
  font-family:'JetBrains Mono';font-size:.62rem;color:var(--faint);letter-spacing:.06em}
.foot .grad{width:120px;height:2px;background:linear-gradient(90deg,var(--pink),var(--cyan))}
.refresh-note{font-family:'JetBrains Mono';font-size:.6rem;color:var(--faint)}
/* cohabitation avec la sidebar admin (240px) */
@media(min-width:769px){
  .topbar{margin-left:240px}
  .wrap{margin-left:240px;max-width:calc(100% - 240px)}
}
</style>
</head>
<body>
<?php require_once __DIR__ . '/sidebar.php'; ?>
<header class="topbar">
  <div class="brand"><span class="logo">STRATEDGE</span><span class="sub">ENGINE&nbsp;CONTROL</span></div>
  <div class="sp"></div>
  <div class="livedot"><i></i>LIVE · refresh 60s</div>
</header>

<div class="wrap">
  <div class="eyebrow">Supervision · Halftime Engine Autonome</div>
  <h1>CENTRE DE CONTRÔLE DU BOT</h1>
  <p class="sub1">Surveillance temps réel du système d'analyse mi-temps déployé sur le VPS — état, alertes émises, programme surveillé et performance des picks.</p>

  <!-- HERO -->
  <div class="hero">
    <div class="hero-grid">
      <div class="status-orb">
        <div class="orb <?= $isOnline ? 'on' : 'off' ?>">
          <span class="ring"></span><span class="core"></span>
        </div>
        <span class="lbl <?= $isOnline ? 'on' : 'off' ?>"><?= $isOnline ? 'EN LIGNE' : 'HORS LIGNE' ?></span>
      </div>
      <div class="hero-info">
        <h2><?= $isOnline ? '⚡ Le moteur analyse en autonomie' : '⚠️ Aucun battement récent du moteur' ?></h2>
        <?php if ($engine): ?>
        <div class="hero-meta">
          <span class="chip">Version <b><?= htmlspecialchars($engine['version'] ?? 'v1.0') ?></b></span>
          <span class="chip">Ligues suivies <b><?php
            $lgs = array_map(fn($id) => $LEAGUE_NAMES[$id] ?? "#$id", $engine['leagues'] ?? []);
            echo htmlspecialchars(implode(' · ', $lgs) ?: '—'); ?></b></span>
          <span class="chip">Dernier battement <b><?= fmtAge($engineAgeSec) ?></b></span>
          <?php if (!empty($engine['watching_now'])): ?>
            <span class="chip warn">🔴 Surveille <b><?= count($engine['watching_now']) ?></b> match(s) en fenêtre MT</span>
          <?php endif; ?>
        </div>
        <?php else: ?>
        <div class="hero-meta"><span class="chip warn">Fichier de statut introuvable — le bot n'a pas encore écrit son heartbeat</span></div>
        <?php endif; ?>
      </div>
      <div class="uptime">
        <div class="big <?= $isOnline ? '' : 'off' ?>"><?= isset($engine['stats']['alerts_sent']) ? (int)$engine['stats']['alerts_sent'] : 0 ?></div>
        <div class="cap">Alertes émises (session)</div>
      </div>
    </div>
  </div>

  <!-- KPIs bets réels -->
  <div class="kgrid">
    <div class="kcard">
      <div class="kv cy"><?= $betStats['total'] ?></div>
      <div class="kl">Bets au total</div>
      <div class="kd"><?= $betStats['en_cours'] ?> en cours</div>
    </div>
    <div class="kcard">
      <div class="kv"><?= $winrate !== null ? $winrate.'%' : '—' ?></div>
      <div class="kl">Win rate</div>
      <div class="kd"><?= $betStats['gagnes'] ?>G · <?= $betStats['perdus'] ?>P</div>
    </div>
    <div class="kcard">
      <div class="kv <?= isset($betStats['roi']) && $betStats['roi']>=0 ? 'pos' : ($betStats['roi']!==null?'neg':'') ?>">
        <?= $betStats['roi'] !== null ? ($betStats['roi']>=0?'+':'').$betStats['roi'].'%' : '—' ?>
      </div>
      <div class="kl">ROI (mise unitaire)</div>
      <div class="kd"><?= isset($betStats['pl']) ? (($betStats['pl']>=0?'+':'').$betStats['pl'].'u P&L') : 'réglés à cote connue' ?></div>
    </div>
    <div class="kcard">
      <div class="kv am"><?= $betStats['avg_cote'] !== null ? number_format($betStats['avg_cote'],2,',','') : '—' ?></div>
      <div class="kl">Cote moyenne</div>
      <div class="kd">tous tipsters</div>
    </div>
    <div class="kcard">
      <div class="kv cy"><?= isset($engine['stats']['matches_analyzed']) ? (int)$engine['stats']['matches_analyzed'] : 0 ?></div>
      <div class="kl">Matchs analysés</div>
      <div class="kd">par le bot (session)</div>
    </div>
    <div class="kcard">
      <div class="kv <?= (!empty($engine['stats']['errors'])) ? 'neg' : 'pos' ?>">
        <?= isset($engine['stats']['errors']) ? (int)$engine['stats']['errors'] : 0 ?>
      </div>
      <div class="kl">Erreurs</div>
      <div class="kd">boucle moteur</div>
    </div>
  </div>

  <!-- 2 COLONNES -->
  <div class="cols">
    <!-- Gauche : alertes -->
    <div>
      <div class="panel">
        <div class="ptitle"><span class="tk"></span>Alertes du bot
          <span class="cnt"><?= !empty($engine['alerts_today']) ? count($engine['alerts_today']).' aujourd\'hui' : '' ?></span></div>
        <?php if (!empty($engine['alerts_today'])): ?>
          <?php foreach ($engine['alerts_today'] as $al): ?>
          <div class="alert">
            <div class="ah">
              <span class="amatch"><?= htmlspecialchars($al['match']) ?></span>
              <span class="ascn <?= htmlspecialchars($al['scenario']) ?>"><?= htmlspecialchars($al['scenario']) ?></span>
            </div>
            <div class="atime"><?= date('d/m H:i', strtotime($al['time'])) ?></div>
            <?php foreach (($al['picks'] ?? []) as $pk): ?>
              <div class="apick"><?= htmlspecialchars($pk) ?></div>
            <?php endforeach; ?>
            <?php if (empty($al['picks'])): ?><div class="apick" style="color:var(--dim)">Aucun bet net — triggers actifs</div><?php endif; ?>
          </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="empty"><div class="big">🎯</div>Aucune alerte émise pour l'instant.<br>Le bot enverra une alerte dès qu'un match suivi atteint la mi-temps.</div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Droite : programme + bets récents -->
    <div>
      <div class="panel">
        <div class="ptitle"><span class="tk"></span>Programme surveillé
          <span class="cnt"><?= !empty($engine['today_fixtures']) ? count($engine['today_fixtures']).' matchs' : '' ?></span></div>
        <?php if (!empty($engine['today_fixtures'])): ?>
          <?php
          $watchingSet = array_flip($engine['watching_now'] ?? []);
          foreach (array_slice($engine['today_fixtures'], 0, 10) as $fx): ?>
          <div class="fix">
            <span class="ftime"><?= date('H:i', $fx['kickoff']) ?></span>
            <span class="fmatch"><?= htmlspecialchars($fx['match']) ?>
              <span class="fleague"><?= htmlspecialchars($fx['league'] ?? '') ?></span>
            </span>
            <?php if (isset($watchingSet[$fx['match']])): ?><span class="fwatch">● LIVE</span><?php endif; ?>
          </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="empty" style="padding:1.4rem"><div class="big" style="font-size:1.4rem">📋</div>Programme du jour en attente<br><span style="font-size:.75rem">(chargé au premier cycle du bot)</span></div>
        <?php endif; ?>
      </div>

      <div class="panel">
        <div class="ptitle"><span class="tk"></span>Derniers picks publiés</div>
        <?php if ($recentBets): ?>
          <?php foreach ($recentBets as $b): ?>
          <div class="bet">
            <span class="bres <?= htmlspecialchars($b['resultat'] ?? 'en_cours') ?>"></span>
            <span class="btitre"><?= htmlspecialchars($b['titre'] ?? 'Sans titre') ?></span>
            <span class="btype"><?= htmlspecialchars($b['type'] ?? '') ?></span>
            <span class="bcote"><?= $b['cote'] ? htmlspecialchars($b['cote']) : '—' ?></span>
          </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="empty" style="padding:1.4rem">Aucun bet récent</div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="foot">
    <span>STRATEDGE · ENGINE CONTROL · heartbeat <?= $engine ? 'actif' : 'en attente' ?> · <?= date('d/m/Y H:i') ?></span>
    <span class="grad"></span>
  </div>
</div>
</body>
</html>
