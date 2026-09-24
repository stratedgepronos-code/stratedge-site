<?php
/**
 * slate/index.php — Le programme du jour, trié par ce qui se démarque.
 *
 * REFONTE DU 30/08. La première version demandait huit champs de formulaire
 * pour filtrer : championnat, marché, deux horaires, deux cotes, confiance,
 * présence de pick. C'est le défaut que tous les guides d'UX sportive
 * signalent — la surcharge tue l'usage. Ici : des pastilles cliquables, une
 * recherche, un curseur. Trois gestes au lieu de huit champs.
 *
 * Le tri par défaut n'est plus alphabétique mais par CONFIANCE : ce qui se
 * démarque remonte. L'ordre chronologique reste accessible en un clic, parce
 * qu'à 14h on ne cherche pas la même chose qu'à 19h.
 *
 * ⚠️ La confiance n'est PAS une espérance de gain. Elle mesure l'écart du
 * marché à sa moyenne du jour sur ce pari. Un marché très tranché l'est
 * généralement à raison — le bookmaker sait que Cagliari reçoit l'Inter.
 * C'est un outil de tri, pas un système de paris. Le bandeau le rappelle.
 */
declare(strict_types=1);
date_default_timezone_set('Europe/Paris');
require_once __DIR__ . '/../../includes/auth.php';
requireSuperAdmin();
require_once __DIR__ . '/_ui.php';

const DATA_DIR = __DIR__ . '/data';
if (!is_dir(DATA_DIR)) { @mkdir(DATA_DIR, 0775, true); }

$msg = null;
if (($_POST['action'] ?? '') === 'upload' && isset($_FILES['slate'])) {
    $f = $_FILES['slate'];
    if ($f['error'] === UPLOAD_ERR_OK && $f['size'] < 12_000_000) {
        $raw = (string)file_get_contents($f['tmp_name']);
        $d = json_decode($raw, true);
        if (is_array($d) && !empty($d['matchs'])) {
            /* La date d'archivage vient des MATCHS, pas du jour du chargement.
               Un slate du 30/08 déposé le 31 au matin s'archivait sous
               slate_2026-08-31.json — et la page résultats ne le retrouvait
               plus quand on voulait régler la veille. On prend donc la date
               majoritaire des coups d'envoi. */
            $dates = [];
            foreach ($d['matchs'] as $mm) {
                if (!empty($mm['kickoff'])) $dates[] = substr((string)$mm['kickoff'], 0, 10);
            }
            $jour = date('Y-m-d');
            if ($dates) {
                $c = array_count_values($dates);
                arsort($c);
                $jour = (string)array_key_first($c);
            }
            file_put_contents(DATA_DIR . "/slate_$jour.json", $raw);
            file_put_contents(DATA_DIR . '/slate_courant.json', $raw);
            $msg = ['ok', count($d['matchs']) . " matchs chargés · archivé au $jour."];
        } else {
            $msg = ['ko', "Ce fichier n'a pas de clé « matchs ». Vérifie l'export."];
        }
    } else {
        $msg = ['ko', 'Envoi interrompu (code ' . $f['error'] . ').'];
    }
}

$slate = []; $genere = null;
if (is_file(DATA_DIR . '/slate_courant.json')) {
    $d = json_decode((string)file_get_contents(DATA_DIR . '/slate_courant.json'), true);
    $slate = $d['matchs'] ?? [];
    $genere = $d['genere_le'] ?? null;
}

/* Familles de paris — l'utilisateur pense en familles, pas en lignes.
   « Buts » regroupe Over/Under 2.5 et 3.5 ; « Mi-temps » les six marchés
   de période. C'est ce découpage qui permet de filtrer d'un clic. */
function famille(string $pick): string {
    $p = mb_strtolower($pick);
    if (str_contains($p, 'btts'))                     return 'BTTS';
    if (str_contains($p, 'mt'))                       return 'Mi-temps';
    if (str_contains($p, 'dom') || str_contains($p, 'ext')) return 'Équipe';
    return 'Buts';
}
$FAMILLES = ['BTTS', 'Buts', 'Mi-temps', 'Équipe'];

$ligues = [];
foreach ($slate as $m) { if (!empty($m['championnat'])) $ligues[$m['championnat']] = true; }
ksort($ligues);

$conf = array_values(array_filter(array_map(fn($m) => $m['confiance'] ?? null, $slate)));
$confMed = $conf ? (function ($a) { sort($a); return $a[intdiv(count($a), 2)]; })($conf) : null;
$fort = count(array_filter($conf, fn($c) => $c >= 80));

?>
<?php
ui_head('Programme du jour');
$nPicks = count(array_filter($slate, fn($x) => !empty($x['pick'])));
ui_topbar('programme', $slate
    ? '<a class="pill" href="resultats.php"><b>' . $nPicks . '</b> paris retenus sur ' . count($slate) . ' matchs</a>'
    : '');
?>
<style>
 /* ── propre à cette page ── */
 .filters{display:flex;flex-direction:column;gap:10px;margin-top:20px}
 .frow{display:flex;gap:10px;flex-wrap:wrap;align-items:center}
 .frow .lbl{min-width:70px}
 .rows{margin-top:16px;border-radius:14px;overflow:hidden;border:1px solid var(--line);background:var(--card2)}
 .thead,.line{display:grid;grid-template-columns:62px minmax(170px,1fr) 128px 150px 60px 60px 62px 56px 84px;
              gap:10px;align-items:center;padding:0 16px}
 .thead{font-size:9.5px;font-weight:600;letter-spacing:.1em;color:var(--mute2);text-transform:uppercase;
        padding-top:11px;padding-bottom:9px;border-bottom:1px solid var(--line);position:sticky;top:0;
        background:var(--card2);z-index:5}
 .thead .r,.line .r{text-align:right}
 .sortable{cursor:pointer;user-select:none}
 .sortable:hover,.sortable.on{color:var(--teal)}
 .line{padding-top:11px;padding-bottom:11px;border-bottom:1px solid #1a1a22;transition:background .1s}
 .line:hover{background:var(--card3)}
 .line:last-child{border-bottom:0}
 .line.nopick{opacity:.42}
 .hh{font-family:'JetBrains Mono',monospace;font-size:15px;font-weight:500}
 .hh small{display:block;font-size:9px;color:var(--mute2);letter-spacing:.08em;margin-top:1px}
 .teams{font-weight:700;font-size:14.5px}
 .teams i{font-style:normal;color:var(--mute2);margin:0 6px;font-weight:500}
 .lg{font-size:11.5px;color:var(--mute);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
 .bet{font-weight:700;font-size:13.5px;color:var(--teal);display:flex;align-items:center;gap:7px;flex-wrap:wrap}
 .bet.none{font-family:'JetBrains Mono',monospace;font-size:10px;color:var(--mute2);font-weight:400;line-height:1.25}
 .src{display:inline-flex;gap:2.5px}
 .src b{width:5px;height:5px;border-radius:50%;background:#26263f;display:block}
 .src b.on{background:var(--lime)}
 .warn-tag{font-family:'JetBrains Mono',monospace;font-size:8.5px;color:var(--amber);
   border:1px solid rgba(245,197,66,.32);border-radius:4px;padding:1px 5px}
 .odd{font-family:'JetBrains Mono',monospace;font-size:13.5px;text-align:right}
 .odd.mut{color:var(--mute);font-size:12px}
 .odd.ev-pos{color:var(--lime)}
 .cf{display:flex;flex-direction:column;align-items:flex-end;gap:3px}
 .cf b{font-size:20px;font-weight:800;line-height:1;letter-spacing:-.02em}
 .cf u{display:block;width:64px;height:3px;background:#1c1c30;border-radius:2px;text-decoration:none}
 .cf u i{display:block;height:100%;border-radius:2px}
 .c-hi b{color:var(--lime)} .c-hi u i{background:var(--lime)}
 .c-md b{color:var(--teal)} .c-md u i{background:var(--teal)}
 .c-lo b{color:var(--mute)} .c-lo u i{background:#3a3f5c}
 .empty{padding:30px;color:var(--mute);text-align:center}
 @media(max-width:820px){
   .thead{display:none}
   .line{grid-template-columns:52px 1fr 70px;row-gap:4px}
   .line .lg{grid-column:2/4;font-size:10px}
   .line .bet{grid-column:2}
   .line .odd{grid-column:3}
   .line .odd.mut{display:none}
   .cf{grid-column:3;grid-row:1}
 }
</style>

<?php if ($msg): ?><div class="note <?= $msg[0] === 'ok' ? 'ok' : 'ko' ?>"><?= e($msg[1]) ?></div><?php endif ?>

<?php if (!$slate): ?>
<div class="hero plain">
  <p class="lbl" style="margin-bottom:8px">Programme</p>
  <h1>Aucun slate <span>chargé</span></h1>
  <form method="post" enctype="multipart/form-data" class="up">
    <input type="file" name="slate" accept=".json" required>
    <input type="hidden" name="action" value="upload">
    <button type="submit">Charger le slate</button>
    <span class="mut" style="font-size:12.5px">Dépose le JSON produit par l'analyse.</span>
  </form>
</div>
<?php else: ?>

<div class="hero">
  <p class="lbl" style="margin-bottom:8px">Programme</p>
  <h1>Le pari le plus probable <span>de chaque match</span></h1>
  <a class="cta" href="live.php">Reprendre au live →</a>

  <div class="stats">
    <div class="stat">
      <div class="lbl">Paris retenus</div>
      <div class="big pos"><?= $nPicks ?></div>
      <div class="sub">sur <?= count($slate) ?> matchs · généré le <?= e(str_replace('T', ' à ', substr((string)$genere, 0, 16))) ?></div>
    </div>
    <div class="stat">
      <div class="lbl">Probabilité médiane</div>
      <div class="mid"><?= $confMed !== null ? number_format($confMed, 1) . ' %' : '—' ?></div>
      <div class="lbl" style="margin-top:12px">À 70 % et plus</div>
      <div class="mid pos"><?= $fort ?></div>
    </div>
    <div class="stat">
      <div class="lbl">Marchés</div>
<?php $famC = []; foreach ($slate as $m) { if ($m['pick']) $famC[famille((string)$m['pick'])] = ($famC[famille((string)$m['pick'])] ?? 0) + 1; }
      arsort($famC); ?>
      <div style="margin-top:8px;display:flex;gap:6px;flex-wrap:wrap;max-width:300px">
<?php foreach ($famC as $k => $v): ?>
        <span class="chip t" style="margin:0;font-size:11px;padding:4px 10px"><?= e($k) ?> · <?= $v ?></span>
<?php endforeach ?>
      </div>
    </div>
  </div>

  <div class="filters">
    <div class="frow">
      <span class="lbl">Pari</span>
      <div class="seg wrap">
        <button class="chip-f" data-f="fam" data-v="" aria-pressed="true">tous</button>
<?php foreach ($FAMILLES as $f): ?>
        <button class="chip-f" data-f="fam" data-v="<?= e($f) ?>" aria-pressed="false"><?= e($f) ?></button>
<?php endforeach ?>
      </div>
      <span class="lbl" style="margin-left:8px">Cote</span>
      <div class="seg wrap">
        <button class="chip-f" data-f="cote" data-v="" aria-pressed="true">toutes</button>
        <button class="chip-f" data-f="cote" data-v="1.30-1.60" aria-pressed="false">1.30–1.60</button>
        <button class="chip-f" data-f="cote" data-v="1.60-2.00" aria-pressed="false">1.60–2.00</button>
        <button class="chip-f" data-f="cote" data-v="2.00-9" aria-pressed="false">2.00+</button>
      </div>
    </div>
    <div class="frow">
      <span class="lbl">Horaire</span>
      <div class="seg wrap">
        <button class="chip-f" data-f="h" data-v="" aria-pressed="true">tout</button>
        <button class="chip-f" data-f="h" data-v="0-12" aria-pressed="false">matin</button>
        <button class="chip-f" data-f="h" data-v="12-17" aria-pressed="false">après-midi</button>
        <button class="chip-f" data-f="h" data-v="17-21" aria-pressed="false">soirée</button>
        <button class="chip-f" data-f="h" data-v="21-24" aria-pressed="false">nuit</button>
      </div>
      <span class="lbl" style="margin-left:8px">Preuve</span>
      <div class="seg wrap">
        <button class="chip-f" data-f="src" data-v="" aria-pressed="true">toutes</button>
        <button class="chip-f" data-f="src" data-v="1" aria-pressed="false">1+ source</button>
        <button class="chip-f" data-f="src" data-v="2" aria-pressed="false">2 sources</button>
      </div>
    </div>
    <div class="frow">
      <span class="lbl">Probabilité ≥</span>
      <input type="range" id="fConf" min="50" max="80" step="1" value="50" style="width:150px">
      <span class="mono teal" id="confVal" style="min-width:34px">50 %</span>
      <input type="search" id="fQ" placeholder="équipe ou championnat…" style="margin-left:8px">
      <span class="mono mut" style="margin-left:auto;font-size:12px">
        <b id="nVis" style="color:var(--txt)"><?= $nPicks ?></b> / <?= count($slate) ?></span>
    </div>
  </div>
</div>

<div class="rows">
  <div class="thead">
    <span class="sortable" data-k="h">Heure</span><span>Match</span>
    <span class="sortable" data-k="lg">Championnat</span>
    <span class="sortable" data-k="pick">Pari</span>
    <span class="r sortable" data-k="cote">Cote</span>
    <span class="r" title="1 / probabilité">Juste</span>
    <span class="r sortable" data-k="ev">EV</span>
    <span class="r">Mise</span>
    <span class="r sortable on" data-k="conf">Proba ▾</span>
  </div>
  <div id="rows">
<?php
usort($slate, fn($a, $b) => ($b['confiance'] ?? 0) <=> ($a['confiance'] ?? 0));
$nSans = count($slate) - $nPicks;
foreach ($slate as $m):
    $sansPick = empty($m['pick']);
    $c = $m['confiance'] === null ? null : (float)$m['confiance'];
    $cls = $c === null ? 'c-lo' : ($c >= 70 ? 'c-hi' : ($c >= 62 ? 'c-md' : 'c-lo'));
    $fam = $m['pick'] ? famille((string)$m['pick']) : '';
?>
  <div class="line<?= $sansPick ? ' nopick hidden' : '' ?>"
       data-fam="<?= e($fam) ?>" data-cote="<?= e($m['cote_pick'] ?? '') ?>"
       data-conf="<?= e($m['confiance'] ?? '') ?>" data-h="<?= e(substr((string)$m['heure'], 0, 2)) ?>"
       data-q="<?= e(mb_strtolower($m['domicile'] . ' ' . $m['exterieur'] . ' ' . $m['championnat'])) ?>"
       data-lg="<?= e($m['championnat']) ?>" data-pick="<?= e($m['pick'] ?? '') ?>"
       data-ev="<?= e($m['ev'] ?? '') ?>" data-hh="<?= e($m['heure']) ?>"
       data-src="<?= (int)($m['sources'] ?? 0) ?>">
    <div class="hh"><?= e($m['heure']) ?><small><?= e($m['code']) ?></small></div>
    <div class="teams"><?= e($m['domicile']) ?><i>–</i><?= e($m['exterieur']) ?></div>
    <div class="lg"><?= e($m['championnat']) ?></div>
    <div class="<?= $m['pick'] ? 'bet' : 'bet none' ?>" title="<?= e($m['motif'] ?? '') ?>">
      <?= $m['pick'] ? e($m['pick']) : e($m['motif'] ?? 'aucun') ?>
<?php if ($m['pick']): ?>
      <span class="src" title="<?= (int)$m['sources'] ?> source(s) concordante(s)">
<?php for ($i = 1; $i <= 2; $i++): ?><b class="<?= $i <= (int)$m['sources'] ? 'on' : '' ?>"></b><?php endfor ?>
      </span>
<?php   if (($m['famille'] ?? '') === 'Équipe'): ?><span class="warn-tag" title="Marché par équipe : moins liquide, jamais backtesté">2ᵈ</span><?php endif ?>
<?php endif ?>
    </div>
    <div class="odd"><?= $m['cote_pick'] ? e(number_format((float)$m['cote_pick'], 2)) : '—' ?></div>
    <div class="odd mut"><?= !empty($m['cote_juste']) ? e(number_format((float)$m['cote_juste'], 2)) : '—' ?></div>
    <div class="odd <?= isset($m['ev']) && $m['ev'] > 0 ? 'ev-pos' : 'mut' ?>"><?= isset($m['ev']) && $m['ev'] !== null ? sprintf('%+.1f', (float)$m['ev'] * 100) : '—' ?></div>
    <div class="odd mut"><?= !empty($m['mise']) ? e(number_format((float)$m['mise'] * 100, 2)) . '%' : '—' ?></div>
    <div class="cf <?= $cls ?>"><b><?= $c === null ? '—' : number_format($c, 1) ?></b>
      <u><i style="width:<?= $c === null ? 0 : max(0, min(100, ($c - 50) * 3)) ?>%"></i></u></div>
  </div>
<?php endforeach ?>
  </div>
  <div class="empty hidden" id="vide">Aucun match ne correspond. Élargis les filtres.</div>
</div>

<?php if ($nSans): ?>
<div style="margin-top:12px"><button type="button" id="toggleSans" class="ghost"><?= $nSans ?> match<?= $nSans > 1 ? 's' : '' ?> sans pari retenu — afficher</button></div>
<?php endif ?>

<div class="note info" style="margin-top:16px">
  <strong>La probabilité affichée est celle du marché dé-viggée</strong>, corrigée de son biais
  connu et pondérée par la fiabilité mesurée de chaque marché. Ce n'est pas une espérance de gain :
  un pari très probable est souvent mal payé. Les points verts indiquent combien de sources
  secondaires confirment — un départage, pas un gage.
</div>

<form method="post" enctype="multipart/form-data" class="up">
  <input type="file" name="slate" accept=".json" required>
  <input type="hidden" name="action" value="upload">
  <button type="submit" class="ghost">Remplacer le slate</button>
</form>

<script>
const S = {fam:'', cote:'', h:'', src:'', conf:50, q:''};
const rows = [...document.querySelectorAll('.line')];
let voirSans = false;
function appliquer(){
  let n = 0;
  for (const r of rows){
    const d = r.dataset;
    if (r.classList.contains('nopick')) { r.classList.toggle('hidden', !voirSans); continue; }
    let ok = true;
    if (S.fam && d.fam !== S.fam) ok = false;
    if (S.h){ const [a,b] = S.h.split('-').map(Number); const h = +d.h; if (isNaN(h) || h < a || h >= b) ok = false; }
    if (S.cote){ const [a,b] = S.cote.split('-').map(Number); const c = parseFloat(d.cote); if (isNaN(c) || c < a || c > b) ok = false; }
    if (S.conf > 50){ const c = parseFloat(d.conf); if (isNaN(c) || c < S.conf) ok = false; }
    if (S.src && (parseInt(d.src) || 0) < parseInt(S.src)) ok = false;
    if (S.q && !d.q.includes(S.q)) ok = false;
    r.classList.toggle('hidden', !ok);
    if (ok) n++;
  }
  document.getElementById('nVis').textContent = n;
  document.getElementById('vide').classList.toggle('hidden', n > 0);
}
document.querySelectorAll('.chip-f').forEach(b => b.addEventListener('click', () => {
  const f = b.dataset.f;
  document.querySelectorAll(`.chip-f[data-f="${f}"]`).forEach(o => o.setAttribute('aria-pressed', String(o === b)));
  S[f] = b.dataset.v; appliquer();
}));
const rg = document.getElementById('fConf');
rg.addEventListener('input', () => { S.conf = +rg.value; document.getElementById('confVal').textContent = rg.value + ' %'; appliquer(); });
document.getElementById('fQ').addEventListener('input', e => { S.q = e.target.value.trim().toLowerCase(); appliquer(); });
const bSans = document.getElementById('toggleSans');
if (bSans) bSans.addEventListener('click', () => {
  voirSans = !voirSans;
  bSans.textContent = bSans.textContent.replace(voirSans ? '— afficher' : '— masquer', voirSans ? '— masquer' : '— afficher');
  appliquer();
});
/* tri par colonne : un clic trie, un second inverse */
const NUM = new Set(['cote','ev','conf']);
let triK = 'conf', triAsc = false;
const cont = document.getElementById('rows');
function trier(k){
  if (k === triK) triAsc = !triAsc; else { triK = k; triAsc = !NUM.has(k); }
  const val = r => { const d = r.dataset;
    if (k === 'h') return d.hh || ''; if (k === 'lg') return d.lg || ''; if (k === 'pick') return d.pick || 'zzz';
    const n = parseFloat(d[k]); return isNaN(n) ? -1e9 : n; };
  rows.sort((a,b) => { const x = val(a), y = val(b); const c = typeof x === 'string' ? x.localeCompare(y) : x - y; return triAsc ? c : -c; });
  rows.forEach(r => cont.appendChild(r));
  document.querySelectorAll('.sortable').forEach(s => { const on = s.dataset.k === triK; s.classList.toggle('on', on);
    s.textContent = s.textContent.replace(/ [▾▴]$/, '') + (on ? (triAsc ? ' ▴' : ' ▾') : ''); });
}
document.querySelectorAll('.sortable').forEach(s => s.addEventListener('click', () => trier(s.dataset.k)));
</script>
<?php endif ?>
<?php ui_foot(); ?>
