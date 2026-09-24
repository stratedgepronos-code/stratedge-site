<?php
/**
 * slate/resultats.php — L'historique en tableau de bord de stratégie.
 *
 * CE QUE LA PAGE MONTRE, ET DANS QUEL ORDRE
 * Le ROI d'abord, en grand, parce que c'est la seule question qui compte.
 * Puis ce qui permet de savoir si ce ROI veut dire quelque chose : l'effectif,
 * la courbe de bankroll, le drawdown maximal, le ratio de Sharpe.
 *
 * ⚠️ UN ROI SANS EFFECTIF NE VEUT RIEN DIRE
 * Un +30 % sur 20 paris est du bruit ; un +3 % sur 5 000 est un métier. La
 * page affiche donc un bandeau « échantillon fragile » sous 100 paris réglés,
 * et il ne disparaît pas tant que le seuil n'est pas franchi. C'est ce
 * garde-fou qui a manqué au module corners : il a paru rentable sept jours
 * avant qu'un backtest révèle -5,8 %.
 *
 * LE TYPE DE MISE CHANGE LE RÉSULTAT
 * À plat, chaque pari pèse pareil. En Kelly, une cote de 1,30 avec 70 % de
 * probabilité pèse plus qu'une cote de 4,00 avec 30 %. Les deux vues sont
 * proposées : elles ne racontent pas la même histoire, et c'est utile.
 */
declare(strict_types=1);
date_default_timezone_set('Europe/Paris');
require_once __DIR__ . '/../../includes/auth.php';
requireSuperAdmin();
require_once __DIR__ . '/_ui.php';

const DATA_DIR = __DIR__ . '/data';
const HISTO    = DATA_DIR . '/historique.json';
const N_FIABLE = 100;      // sous ce seuil, la variance domine tout

if (!is_dir(DATA_DIR)) { @mkdir(DATA_DIR, 0775, true); }

$msg = null;

/** Le pari est-il gagnant ? null quand on ne peut pas trancher. */
function verdict(string $pick, int $fh, int $fa, ?int $hh = null, ?int $ha = null): ?bool {
    $t = $fh + $fa;
    // Les marchés de mi-temps se règlent avec les colonnes 9 et 10 de
    // l'export PackBall — "Result Home HT" et "Result Visitor HT". Elles
    // étaient là depuis le début ; la page ne les lisait pas, et 14 picks
    // sur 32 restaient invisibles dans l'historique.
    if (str_contains($pick, 'MT')) {
        if ($hh === null || $ha === null) return null;
        $h1 = $hh + $ha;                 // buts de la 1re période
        $h2 = ($fh - $hh) + ($fa - $ha); // buts de la 2e période
        return match (true) {
            $pick === '1re MT Over 0.5'  => $h1 > 0,
            $pick === '1re MT Under 0.5' => $h1 === 0,
            $pick === '1re MT Over 1.5'  => $h1 > 1,
            $pick === '1re MT Under 1.5' => $h1 < 2,
            $pick === '2e MT Over 1.5'   => $h2 > 1,
            $pick === '2e MT Under 1.5'  => $h2 < 2,
            $pick === '2e MT Over 0.5'   => $h2 > 0,
            $pick === '2e MT Under 0.5'  => $h2 === 0,
            default => null,
        };
    }
    return match (true) {
        $pick === 'BTTS Oui'      => $fh > 0 && $fa > 0,
        $pick === 'BTTS Non'      => !($fh > 0 && $fa > 0),
        $pick === 'Over 2.5'      => $t > 2,
        $pick === 'Under 2.5'     => $t < 3,
        $pick === 'Over 3.5'      => $t > 3,
        $pick === 'Under 3.5'     => $t < 4,
        $pick === 'Dom Over 0.5'  => $fh > 0,
        $pick === 'Dom Under 0.5' => $fh === 0,
        $pick === 'Dom Over 1.5'  => $fh > 1,
        $pick === 'Dom Under 1.5' => $fh < 2,
        $pick === 'Ext Over 0.5'  => $fa > 0,
        $pick === 'Ext Under 0.5' => $fa === 0,
        $pick === 'Ext Over 1.5'  => $fa > 1,
        $pick === 'Ext Under 1.5' => $fa < 2,
        // Les marchés de mi-temps demandent le score à la pause, que le
        // fichier de résultats ne donne pas. Mieux vaut ND qu'un faux verdict.
        default => null,
    };
}

function famille(string $p): string {
    $q = mb_strtolower($p);
    if (str_contains($q, 'btts')) return 'BTTS';
    if (str_contains($q, 'mt')) return 'Mi-temps';
    if (str_contains($q, 'dom') || str_contains($q, 'ext')) return 'Équipe';
    return 'Buts';
}

/* ── règlement d'une journée ────────────────────────────────────────────── */
if (($_POST['action'] ?? '') === 'regler' && isset($_FILES['csv'])) {
    $f = $_FILES['csv'];
    $jour = trim((string)($_POST['jour'] ?? date('Y-m-d')));
    $slateFile = DATA_DIR . "/slate_$jour.json";

    if (!is_file($slateFile)) {
        $msg = ['ko', "Aucun slate archivé pour le $jour."];
    } elseif ($f['error'] !== UPLOAD_ERR_OK) {
        $msg = ['ko', 'Envoi interrompu (code ' . $f['error'] . ').'];
    } else {
        $slate = json_decode((string)file_get_contents($slateFile), true)['matchs'] ?? [];
        $res = [];
        if (($h = fopen($f['tmp_name'], 'r')) !== false) {
            fgetcsv($h, 0, ';');
            while (($r = fgetcsv($h, 0, ';')) !== false) {
                if (count($r) < 11) continue;
                $res[mb_strtolower(trim($r[5]) . '|' . trim($r[8]))] =
                    ['statut' => trim($r[4]),
                     'fh' => is_numeric($r[6]) ? (int)$r[6] : null,
                     'fa' => is_numeric($r[7]) ? (int)$r[7] : null,
                     // colonnes 9-10 : le score à la pause
                     'hh' => is_numeric($r[9] ?? '') ? (int)$r[9] : null,
                     'ha' => is_numeric($r[10] ?? '') ? (int)$r[10] : null];
            }
            fclose($h);
        }
        $lignes = []; $nr = 0; $nnd = 0;
        foreach ($slate as $m) {
            if (empty($m['pick'])) continue;
            $k = mb_strtolower(trim((string)$m['domicile']) . '|' . trim((string)$m['exterieur']));
            $r = $res[$k] ?? null;
            if (!$r || $r['fh'] === null || !in_array($r['statut'], ['FT', 'FT_PEN'], true)) { $nnd++; continue; }
            $v = verdict((string)$m['pick'], $r['fh'], $r['fa'], $r['hh'], $r['ha']);
            if ($v === null) { $nnd++; continue; }
            $cote = (float)($m['cote_pick'] ?? 0);
            $conf = $m['confiance'] === null ? null : (float)$m['confiance'];
            // Kelly calculé depuis la probabilité annoncée : c'est elle que le
            // moteur revendique, donc c'est elle qu'on met à l'épreuve.
            $p = $conf !== null ? $conf / 100 : null;
            $kelly = ($p !== null && $cote > 1) ? max(0.0, min(0.05, (($p * $cote - 1) / ($cote - 1)) / 4)) : 0.02;
            $lignes[] = [
                'jour' => $jour, 'heure' => $m['heure'], 'championnat' => $m['championnat'],
                'match' => $m['domicile'] . ' – ' . $m['exterieur'],
                'score' => $r['fh'] . '-' . $r['fa'],
                'pick' => $m['pick'], 'famille' => famille((string)$m['pick']),
                'cote' => $cote, 'confiance' => $conf, 'kelly' => round($kelly, 4),
                'gagne' => $v, 'gain' => $v ? round($cote - 1, 4) : -1.0,
            ];
            $nr++;
        }
        $histo = is_file(HISTO) ? (json_decode((string)file_get_contents(HISTO), true) ?: []) : [];
        // Un double dépôt ne doit jamais compter deux fois les mêmes paris.
        $histo = array_values(array_filter($histo, fn($x) => ($x['jour'] ?? '') !== $jour));
        $histo = array_merge($histo, $lignes);
        file_put_contents(HISTO, json_encode($histo, JSON_UNESCAPED_UNICODE));
        $msg = ['ok', "$nr pari(s) réglé(s) pour le $jour." . ($nnd ? " $nnd non tranchable(s)." : '')];
    }
}

$histo = is_file(HISTO) ? (json_decode((string)file_get_contents(HISTO), true) ?: []) : [];

/* ── filtres ───────────────────────────────────────────────────────────── */
$periode = $_GET['p'] ?? 'all';                 // 7 · 30 · all
$mise    = $_GET['m'] ?? 'flat';                // flat · kelly · fixe
$famF    = $_GET['f'] ?? '';

if ($periode !== 'all') {
    $limite = date('Y-m-d', strtotime("-{$periode} days"));
    $histo = array_values(array_filter($histo, fn($x) => ($x['jour'] ?? '') >= $limite));
}
if ($famF) {
    $histo = array_values(array_filter($histo, fn($x) => ($x['famille'] ?? '') === $famF));
}
usort($histo, fn($a, $b) => ($a['jour'] . $a['heure']) <=> ($b['jour'] . $b['heure']));

/** Mise engagée sur un pari, selon le mode choisi. */
function stake(array $x, string $mode): float {
    return match ($mode) {
        'kelly' => (float)($x['kelly'] ?? 0.02),
        'fixe'  => 0.02,
        default => 0.02,
    };
}

/* ── agrégats ──────────────────────────────────────────────────────────── */
$n = count($histo);
$w = 0; $pnl = 0.0; $engage = 0.0;
$courbe = []; $gains = [];
$pic = 0.0; $dd = 0.0;
foreach ($histo as $x) {
    $s = stake($x, $mise);
    // En flat chaque pari pèse pareil ; en Kelly la mise varie, donc le gain
    // en unités aussi. C'est pour ça que les deux vues divergent.
    $g = $mise === 'flat' ? (float)$x['gain'] : (float)$x['gain'] * ($s / 0.02);
    $pnl += $g; $engage += ($mise === 'flat' ? 1.0 : $s / 0.02);
    $gains[] = $g;
    if ($x['gagne']) $w++;
    $courbe[] = $pnl;
    if ($pnl > $pic) $pic = $pnl;
    if ($pic - $pnl > $dd) $dd = $pic - $pnl;
}
$roi  = $engage > 0 ? $pnl / $engage : 0.0;
$taux = $n ? $w / $n : 0.0;

/* Sharpe : rendement moyen rapporté à sa dispersion. Sous 30 paris il n'a
   aucun sens — la dispersion elle-même est mal estimée. */
$sharpe = null;
if ($n >= 30) {
    $moy = array_sum($gains) / $n;
    $var = 0.0;
    foreach ($gains as $g) { $var += ($g - $moy) ** 2; }
    $sd = sqrt($var / $n);
    $sharpe = $sd > 0 ? ($moy / $sd) * sqrt($n) : null;
}

/** n, réussite, ROI, unités pour un sous-ensemble. */
function perf(array $l, string $mode): array {
    $n = count($l);
    if (!$n) return ['n' => 0, 'w' => null, 'roi' => null, 'u' => 0.0, 'cote' => null];
    $w = 0; $u = 0.0; $e = 0.0; $c = 0.0;
    foreach ($l as $x) {
        $s = stake($x, $mode);
        $g = $mode === 'flat' ? (float)$x['gain'] : (float)$x['gain'] * ($s / 0.02);
        $u += $g; $e += ($mode === 'flat' ? 1.0 : $s / 0.02);
        $c += (float)$x['cote'];
        if ($x['gagne']) $w++;
    }
    return ['n' => $n, 'w' => $w / $n, 'roi' => $e > 0 ? $u / $e : 0.0,
            'u' => $u, 'cote' => $c / $n];
}

function grp(array $h, string $k): array {
    $o = [];
    foreach ($h as $x) { $o[(string)($x[$k] ?? '—')][] = $x; }
    return $o;
}

/* ── rendement par tranche de cote ─────────────────────────────────────── */
$TR = [[1.00, 1.35], [1.35, 1.45], [1.45, 1.55], [1.55, 1.70],
       [1.70, 1.90], [1.90, 2.20], [2.20, 2.70], [2.70, 99]];
$tranches = [];
foreach ($TR as [$a, $b]) {
    $sel = array_values(array_filter($histo, fn($x) => $a <= (float)$x['cote'] && (float)$x['cote'] < $b));
    $tranches[] = ['a' => $a, 'b' => $b] + perf($sel, $mise);
}
$meilleure = null;
foreach ($tranches as $t) {
    if ($t['n'] >= 10 && ($meilleure === null || $t['roi'] > $meilleure['roi'])) $meilleure = $t;
}

$parLigue   = grp($histo, 'championnat');
$parFamille = grp($histo, 'famille');
$parJour    = grp($histo, 'jour');
$parPick    = grp($histo, 'pick');

$ligues = [];
foreach ($parLigue as $k => $l) { $ligues[] = ['nom' => $k] + perf($l, $mise); }
usort($ligues, fn($a, $b) => $b['roi'] <=> $a['roi']);

/* PnL par journée : les barres sous la courbe, comme dans l'outil de
   référence. Une barre verte = journée gagnante, rouge = perdante. */
$pnlJour = [];
foreach ($parJour as $j => $l) {
    $s = 0.0;
    foreach ($l as $x) {
        $st = stake($x, $mise);
        $s += $mise === 'flat' ? (float)$x['gain'] : (float)$x['gain'] * ($st / 0.02);
    }
    $pnlJour[$j] = $s;
}
ksort($pnlJour);
$maxAbsJour = $pnlJour ? max(array_map('abs', $pnlJour)) ?: 1 : 1;

$slatesDispo = [];
foreach (glob(DATA_DIR . '/slate_20*.json') ?: [] as $p) {
    if (preg_match('/slate_(\d{4}-\d{2}-\d{2})\.json$/', $p, $mm)) $slatesDispo[] = $mm[1];
}
rsort($slatesDispo);

function qs(array $o): string {
    return '?' . http_build_query(array_merge(['p' => $_GET['p'] ?? 'all',
        'm' => $_GET['m'] ?? 'flat', 'f' => $_GET['f'] ?? ''], $o));
}

/* Courbe SVG : on échantillonne à 220 points maximum, sinon le tracé devient
   illisible et le HTML inutilement lourd. */
$pts = '';
if (count($courbe) > 1) {
    $max = max($courbe); $min = min($courbe);
    $max = max($max, 0.0); $min = min($min, 0.0);
    $amp = ($max - $min) ?: 1;
    $N = count($courbe);
    $pas = max(1, (int)ceil($N / 220));
    $co = [];
    for ($i = 0; $i < $N; $i += $pas) {
        $x = ($i / max(1, $N - 1)) * 1000;
        $y = 260 - (($courbe[$i] - $min) / $amp) * 240;
        $co[] = round($x, 1) . ',' . round($y, 1);
    }
    $x = 1000; $y = 260 - (($courbe[$N-1] - $min) / $amp) * 240;
    $co[] = round($x, 1) . ',' . round($y, 1);
    $pts = implode(' ', $co);
    $zeroY = 260 - ((0 - $min) / $amp) * 240;
    // graduations Y : cinq niveaux lisibles
    $grad = [];
    for ($k = 0; $k <= 4; $k++) {
        $v = $min + $amp * $k / 4;
        $grad[] = ['y' => 260 - ($k / 4) * 240, 'v' => $v];
    }
    // dates en X : première, milieu, dernière journée
    $jrs = array_keys($parJour); sort($jrs);
    $datesX = [];
    if ($jrs) {
        foreach ([0, intdiv(count($jrs), 2), count($jrs) - 1] as $ix) {
            $datesX[] = ['x' => ($ix / max(1, count($jrs) - 1)) * 1000,
                         'd' => date('d M', strtotime($jrs[$ix]))];
        }
    }
}
?>
<?php
ui_head('Historique');
ui_topbar('historique', '<a class="pill" href="index.php"><b>' . number_format(count($slatesDispo)) . '</b> journée' . (count($slatesDispo) > 1 ? 's' : '') . ' archivée' . (count($slatesDispo) > 1 ? 's' : '') . '</a>');
?>
<style>
 .wrap{display:grid;grid-template-columns:300px minmax(0,1fr);gap:18px;max-width:1900px}
 @media(max-width:1100px){.wrap{grid-template-columns:1fr}}
 .side{display:flex;flex-direction:column;gap:14px}
 .side .lbl{margin:16px 0 8px}
 .side .lbl:first-child{margin-top:2px}
 .cible{background:var(--card);border:1px solid var(--line);border-radius:var(--r);padding:16px 18px;
 .cible.on{border-color:var(--teal);box-shadow:0 0 0 1px rgba(23,184,176,.25),0 0 40px -12px rgba(23,184,176,.35)}
 .cible .tag{display:inline-flex;align-items:center;gap:5px;font-size:10px;font-weight:700;letter-spacing:.08em;
 .cible > *{position:relative;z-index:1}
 .cible h3{font-size:15px;font-weight:700;margin:7px 0 2px;padding-right:86px;line-height:1.25}
 .cible .roi{position:absolute;right:18px;top:16px;text-align:right;z-index:2}
 .cible .roi .lbl{font-size:9.5px}
 .cible .roi b{display:block;font-size:22px;font-weight:800;letter-spacing:-.02em}
 .cible .sub{color:var(--mute);font-size:12.5px}
 .pastilles{display:grid;grid-template-columns:repeat(4,1fr);gap:4px;margin-top:9px}
 .pastilles span{font-family:'JetBrains Mono',monospace;font-size:10px;font-weight:500;
 .pastilles .p{background:rgba(195,245,66,.16);color:var(--lime)}
 .pastilles .n{background:rgba(240,106,106,.16);color:var(--red)}
 .pastilles .z{background:var(--card3);color:var(--mute2)}
 .meilleure{margin-top:11px;font-size:11.5px;color:var(--txt2);padding-right:52px;line-height:1.4}
 .meilleure b{color:var(--lime);font-weight:700}
 .meilleure .mono{font-size:11px;color:var(--txt)}
 .wm{position:absolute;right:12px;bottom:4px;font-size:40px;font-weight:800;
 .repart{display:flex;gap:5px;align-items:flex-end;height:44px;width:270px}
 .repart div{flex:1;display:flex;flex-direction:column;align-items:center;gap:3px;height:100%;justify-content:flex-end}
 .repart i{display:block;width:100%;border-radius:3px 3px 1px 1px}
 .repart small{font-family:'JetBrains Mono',monospace;font-size:8px;color:var(--mute2);transform:rotate(-45deg);transform-origin:top;white-space:nowrap;margin-top:6px}
 .inner{display:grid;grid-template-columns:minmax(0,1fr) 320px;gap:16px;margin-top:22px}
 @media(max-width:1300px){.inner{grid-template-columns:1fr}}
 .legend{display:flex;gap:16px;font-size:12px;color:var(--txt2);margin:6px 0 4px}
 .legend i{display:inline-block;width:14px;height:3px;border-radius:2px;vertical-align:middle;margin-right:6px}
 .chart{position:relative}
 .chart svg{width:100%;height:280px;display:block}
 .chart .yl{position:absolute;left:0;font-family:'JetBrains Mono',monospace;font-size:10px;color:var(--mute);transform:translateY(-50%)}
 .lg{display:flex;justify-content:space-between;align-items:center;gap:10px;padding:12px 14px;
 .lg .n{font-size:13.5px;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
 .lg .s{font-size:11.5px;color:var(--mute);margin-top:2px}
 .lg .s b{color:var(--txt2);font-weight:600}
 .lg .v{font-family:'JetBrains Mono',monospace;font-size:14px;font-weight:500;white-space:nowrap}
 .lg-head{display:flex;justify-content:space-between;font-size:11px;color:var(--mute);margin-bottom:10px}
 .lg-head b{color:var(--txt2);font-weight:600}
</style>

<?php if ($msg): ?><div class="note <?= $msg[0] === 'ok' ? 'ok' : 'ko' ?>"><?= e($msg[1]) ?></div><?php endif ?>

<div class="wrap">

  <!-- ══════ colonne gauche ══════ -->
  <aside class="side">
    <div>
      <p class="lbl">Période analysée</p>
      <div class="seg">
        <a href="<?= qs(['p' => '7']) ?>"   class="<?= $periode === '7' ? 'on' : '' ?>">7 J</a>
        <a href="<?= qs(['p' => '30']) ?>"  class="<?= $periode === '30' ? 'on' : '' ?>">30 J</a>
        <a href="<?= qs(['p' => 'all']) ?>" class="<?= $periode === 'all' ? 'on' : '' ?>">All</a>
      </div>
      <p class="lbl">Type de mise</p>
      <div class="seg">
        <a href="<?= qs(['m' => 'flat']) ?>"  class="<?= $mise === 'flat' ? 'on' : '' ?>">Flat 2%</a>
        <a href="<?= qs(['m' => 'kelly']) ?>" class="<?= $mise === 'kelly' ? 'on' : '' ?>">Kelly ¼</a>
      </div>
    </div>

<?php
    $famAll = ['Buts', 'BTTS', 'Mi-temps', 'Équipe'];
    $ordre  = [];
    foreach ($famAll as $ff) { if (isset($parFamille[$ff])) $ordre[] = $ff; }
    foreach ($ordre as $ff):
      $pf = perf($parFamille[$ff], $mise);
      $trF = [];
      foreach ($TR as [$a, $b]) {
          $sel = array_filter($parFamille[$ff], fn($x) => $a <= (float)$x['cote'] && (float)$x['cote'] < $b);
          $trF[] = ['a' => $a, 'b' => $b] + perf(array_values($sel), $mise);
      }
      $bestF = null;
      foreach ($trF as $tt) { if ($tt['n'] >= 5 && ($bestF === null || $tt['roi'] > $bestF['roi'])) $bestF = $tt; }
      $on = $famF === $ff;
?>
    <a href="<?= qs(['f' => $on ? '' : $ff]) ?>" class="cible <?= $on ? 'on' : '' ?>" style="text-decoration:none;color:inherit;display:block">
      <span class="tag"><?= e(mb_strtoupper($ff)) ?><?= $on ? ' · ↑ CIBLE' : '' ?></span>
      <div class="roi"><span class="lbl">ROI</span><b class="<?= $pf['roi'] > 0 ? 'pos' : 'neg' ?>"><?= sg($pf['roi'], 1) ?></b></div>
      <h3><?= match($ff) { 'BTTS' => 'Les deux équipes marquent', 'Buts' => 'Total de buts du match',
                            'Mi-temps' => 'Buts par période', default => 'Buts par équipe' } ?></h3>
      <div class="sub"><?= pc($pf['w']) ?> de réussite<br><?= $pf['n'] ?> pari<?= $pf['n'] > 1 ? 's' : '' ?> réglé<?= $pf['n'] > 1 ? 's' : '' ?></div>
      <p class="lbl" style="margin-top:12px;font-size:9.5px">Rendement par tranche</p>
      <div class="pastilles">
<?php foreach ($trF as $tt): ?>
        <span class="<?= $tt['n'] ? ($tt['roi'] > 0 ? 'p' : 'n') : 'z' ?>" title="<?= number_format($tt['a'],2) ?>–<?= $tt['b'] > 90 ? '∞' : number_format($tt['b'],2) ?> · <?= $tt['n'] ?> paris"><?= $tt['n'] ? sprintf('%+.1f', $tt['roi'] * 100) : '·' ?></span>
<?php endforeach ?>
      </div>
<?php if ($bestF): ?>
      <div class="meilleure">◉ Meilleure tranche : <span class="mono"><?= number_format($bestF['a'],2) ?> – <?= $bestF['b'] > 90 ? '∞' : number_format($bestF['b'],2) ?></span> <b><?= sg($bestF['roi'], 1) ?></b></div>
<?php endif ?>
      <div class="wm"><?= e(mb_strtoupper(mb_substr($ff, 0, 4))) ?></div>
    </a>
<?php endforeach ?>
  </aside>

  <!-- ══════ la grande carte ══════ -->
  <main class="hero">
    <p class="lbl" style="margin-bottom:8px">Stratégie</p>
    <h1>Le pari le plus probable <span>de chaque match</span></h1>
    <a class="save" href="index.php">Voir le programme →</a>

    <div class="stats">
      <div class="stat">
        <div class="lbl">ROI</div>
        <div class="big <?= $roi > 0 ? 'pos' : 'neg' ?>"><?= $n ? sg($roi) : '—' ?></div>
        <div class="sub">mise <?= $mise === 'kelly' ? 'Kelly ¼' : 'plate 2 %' ?> · <?= $famF ?: 'toutes familles' ?></div>
<?php if ($n && $n < N_FIABLE): ?>
        <div class="frag">🛡️ Échantillon fragile</div>
<?php endif ?>
      </div>
      <div class="stat">
        <div class="lbl">Réussite :</div>
        <div class="mid"><?= $n ? pc($taux) : '—' ?></div>
        <div class="lbl" style="margin-top:12px">Effectif :</div>
        <div class="mid"><?= number_format($n, 0, ',', ' ') ?></div>
        <div class="sub">≈ <?= count($parJour) ? number_format($n / count($parJour), 1) : '—' ?> / journée</div>
      </div>
      <div class="stat">
        <div class="lbl">Répartition des cotes (<?= count($tranches) ?> tranches)</div>
        <div class="repart" style="margin-top:8px">
<?php foreach ($tranches as $t):
        $h = $t['n'] ? max(3, (int)round($t['n'] / max(1, $n) * 100)) : 2; ?>
          <div>
            <i style="height:<?= min(38, $h) ?>px;background:<?= $t['n'] ? ($t['roi'] > 0 ? 'var(--lime)' : 'var(--red)') : 'var(--card3)' ?>"></i>
            <small><?= number_format($t['a'], 2) ?></small>
          </div>
<?php endforeach ?>
        </div>
      </div>
    </div>

<?php if ($n && $n < N_FIABLE): ?>
    <div class="note" style="margin-top:20px">
      <strong><?= $n ?> paris réglés.</strong> Sous <?= N_FIABLE ?>, la variance domine tout — un +30 %
      sur vingt paris est du bruit. Le 29/08, Over faisait +60,7 % et Under −51,4 %
      <em>le même jour</em>. Le Sharpe reste masqué sous 30 paris pour la même raison.
    </div>
<?php endif ?>

    <div class="inner">
      <div class="panel">
        <h2>Backtest <small><?= $mise === 'kelly' ? 'Kelly ¼' : 'mise plate' ?></small></h2>
        <div class="kpis">
          <div class="kpi"><span class="lbl">PNL <i>?</i></span><b class="<?= $pnl > 0 ? 'pos' : 'neg' ?>"><?= sprintf('%+.2f U', $pnl) ?></b></div>
          <div class="kpi"><span class="lbl">ROI <i>?</i></span><b class="<?= $roi > 0 ? 'pos' : 'neg' ?>"><?= $n ? sg($roi) : '—' ?></b></div>
          <div class="kpi"><span class="lbl">BETS <i>?</i></span><b><?= $n ?></b></div>
          <div class="kpi"><span class="lbl">MAX DD <i>?</i></span><b class="neg">−<?= number_format($dd, 2) ?> U</b></div>
          <div class="kpi"><span class="lbl">SHARPE <i>?</i></span><b class="<?= $sharpe === null ? 'mut' : ($sharpe > 0 ? 'pos' : 'neg') ?>"><?= $sharpe === null ? '—' : sprintf('%+.2f', $sharpe) ?></b></div>
        </div>
        <div class="legend"><span><i style="background:var(--lime)"></i>Bankroll</span><span><i style="background:var(--red)"></i>PnL / jour</span></div>

<?php if ($pts): ?>
        <div class="chart">
          <svg viewBox="0 0 1000 300" preserveAspectRatio="none">
            <defs>
              <linearGradient id="g" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0" stop-color="#c3f542" stop-opacity=".18"/>
                <stop offset="1" stop-color="#c3f542" stop-opacity="0"/>
              </linearGradient>
            </defs>
<?php foreach ($grad as $gl): ?>
            <line x1="0" y1="<?= round($gl['y'],1) ?>" x2="1000" y2="<?= round($gl['y'],1) ?>" stroke="#1f1f2a" stroke-width="1"/>
<?php endforeach ?>
            <line x1="0" y1="<?= round($zeroY,1) ?>" x2="1000" y2="<?= round($zeroY,1) ?>" stroke="#3a3a4a" stroke-width="1" stroke-dasharray="5 5"/>
<?php
      // barres PnL par jour, centrées sur l'équilibre
      $nj = count($pnlJour); $ix = 0;
      foreach ($pnlJour as $j => $v):
        $x = $nj > 1 ? ($ix / ($nj - 1)) * 1000 : 500;
        $hb = ($v / $maxAbsJour) * 60;
        $y1 = $zeroY; $y2 = $zeroY - $hb; $ix++; ?>
            <line x1="<?= round($x,1) ?>" y1="<?= round($y1,1) ?>" x2="<?= round($x,1) ?>" y2="<?= round($y2,1) ?>"
                  stroke="<?= $v >= 0 ? '#3f7a2f' : '#8a3a3a' ?>" stroke-width="<?= $nj > 60 ? 2 : 5 ?>" stroke-opacity=".6"/>
<?php endforeach ?>
            <polygon points="0,<?= round($zeroY,1) ?> <?= $pts ?> 1000,<?= round($zeroY,1) ?>" fill="url(#g)"/>
            <polyline points="<?= $pts ?>" fill="none" stroke="#c3f542" stroke-width="2.2" stroke-linejoin="round" stroke-linecap="round"/>
          </svg>
<?php foreach ($grad as $gl): ?>
          <span class="yl" style="top:<?= round($gl['y'] / 300 * 100, 2) ?>%"><?= sprintf('%+.2f U', $gl['v']) ?></span>
<?php endforeach ?>
          <div style="display:flex;justify-content:space-between;font-family:'JetBrains Mono',monospace;font-size:10px;color:var(--mute);margin-top:6px;padding:0 4px">
<?php foreach ($datesX as $dx): ?><span><?= e($dx['d']) ?></span><?php endforeach ?>
          </div>
        </div>
<?php else: ?>
        <p class="mut" style="padding:40px 0;text-align:center">La courbe apparaît dès deux paris réglés.</p>
<?php endif ?>
      </div>

      <div class="panel">
        <div class="lg-head"><b>Championnats</b><span>ROI ↓</span></div>
        <div class="scroll">
<?php foreach (array_slice($ligues, 0, 12) as $lg): ?>
          <div class="lg">
            <div style="min-width:0">
              <div class="n"><?= e($lg['nom']) ?></div>
              <div class="s">Réussite : <b><?= pc($lg['w']) ?></b> &nbsp;<?= $lg['n'] ?> bet<?= $lg['n'] > 1 ? 's' : '' ?></div>
            </div>
            <div class="v <?= $lg['roi'] > 0 ? 'pos' : 'neg' ?>"><?= sg($lg['roi']) ?></div>
          </div>
<?php endforeach ?>
<?php if (!$ligues): ?><p class="mut" style="font-size:13px">Aucun pari réglé.</p><?php endif ?>
        </div>
      </div>
    </div>

    <div class="grid3">
      <div class="panel">
        <h2>Rendement par tranche de cote</h2>
        <table>
          <tr><th>Tranche</th><th>N</th><th>Réussite</th><th>ROI</th></tr>
<?php foreach ($tranches as $t): if (!$t['n']) continue; ?>
          <tr><td class="mono" style="font-weight:500"><?= number_format($t['a'],2) ?> – <?= $t['b'] > 90 ? '∞' : number_format($t['b'],2) ?><?= $t['n'] < 10 ? '<span class="chip">n faible</span>' : '' ?></td>
            <td class="mono"><?= $t['n'] ?></td><td class="mono"><?= pc($t['w'],1) ?></td>
            <td class="mono <?= $t['roi'] > 0 ? 'pos' : 'neg' ?>"><?= sg($t['roi'],1) ?></td></tr>
<?php endforeach ?>
        </table>
      </div>

      <div class="panel">
        <h2>Par pari précis</h2>
        <div class="scroll">
        <table>
          <tr><th>Pari</th><th>N</th><th>Réussite</th><th>ROI</th></tr>
<?php uasort($parPick, fn($a, $b) => count($b) <=> count($a));
      foreach ($parPick as $k => $l): $p = perf($l, $mise); ?>
          <tr><td><?= e($k) ?><?= $p['n'] < 30 ? '<span class="chip">n=' . $p['n'] . '</span>' : '' ?></td>
            <td class="mono"><?= $p['n'] ?></td><td class="mono"><?= pc($p['w'],1) ?></td>
            <td class="mono <?= $p['roi'] > 0 ? 'pos' : 'neg' ?>"><?= sg($p['roi'],1) ?></td></tr>
<?php endforeach ?>
        </table>
        </div>
      </div>

      <div class="panel">
        <h2>Journée par journée</h2>
        <div class="scroll">
        <table>
          <tr><th>Date</th><th>N</th><th>ROI</th><th style="text-align:left">Détail</th></tr>
<?php $jours = $parJour; krsort($jours);
      foreach ($jours as $j => $l): $p = perf($l, $mise); ?>
          <tr><td class="mono" style="font-weight:500"><?= e(date('d M', strtotime($j))) ?></td>
            <td class="mono"><?= $p['n'] ?></td>
            <td class="mono <?= $p['roi'] > 0 ? 'pos' : 'neg' ?>"><?= sg($p['roi'],1) ?></td>
            <td style="text-align:left;white-space:nowrap"><?php foreach (array_slice($l, 0, 22) as $x): ?><span class="dot" style="background:<?= $x['gagne'] ? 'var(--lime)' : 'var(--red)' ?>" title="<?= e($x['match'] . ' · ' . $x['pick']) ?>"></span><?php endforeach ?></td></tr>
<?php endforeach ?>
        </table>
        </div>
      </div>
    </div>

    <form method="post" enctype="multipart/form-data" class="up">
      <input type="hidden" name="action" value="regler">
      <span class="lbl">Régler une journée</span>
      <select name="jour">
<?php foreach ($slatesDispo as $j): ?>
        <option value="<?= e($j) ?>"<?= $j === date('Y-m-d', strtotime('-1 day')) ? ' selected' : '' ?>><?= e($j) ?></option>
<?php endforeach ?>
<?php if (!$slatesDispo): ?><option value="<?= date('Y-m-d') ?>"><?= date('Y-m-d') ?></option><?php endif ?>
      </select>
      <input type="file" name="csv" accept=".csv" required>
      <button type="submit">Régler</button>
      <span class="mut" style="font-size:12px">Export PackBall du même jour, en FT — mi-temps comprises.</span>
    </form>
  </main>
</div>
<?php ui_foot(); ?>
