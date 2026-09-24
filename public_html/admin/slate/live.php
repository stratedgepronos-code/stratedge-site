<?php
/**
 * slate/live.php — Piloter le Live Watcher sans SSH.
 *
 * DEUX FONCTIONS
 *   1. Déposer les deux CSV PackBall du jour → calcule les λ pré-match,
 *      écrit /var/lib/stratedge/baseline.json, et mesure immédiatement le
 *      taux d'appariement avec les matchs réellement présents en base live.
 *   2. Voir l'état : relevés, cycles, dernier contact, alertes par règle.
 *
 * POURQUOI LE TAUX D'APPARIEMENT EST AFFICHÉ SI GROS
 * Les règles B et D du moteur exigent les λ pré-match. Si la jointure échoue,
 * elles restent muettes SANS AUCUNE ERREUR — le moteur considère simplement
 * qu'il n'y a pas de baseline pour ce match. C'est une panne silencieuse.
 * Mesuré le 01/09 : 28 matchs exportés contre 32 en live, seuls 12 appariés.
 * Les 20 autres étaient absents du CSV, pas mal orthographiés — le filtre de
 * ligues PackBall était trop étroit à l'export.
 *
 * AUCUN REDÉMARRAGE N'EST NÉCESSAIRE : le moteur surveille le mtime du
 * fichier (reload_if_changed) et le relit dès qu'il change.
 */
declare(strict_types=1);
date_default_timezone_set('Europe/Paris');
require_once __DIR__ . '/../../includes/auth.php';
requireSuperAdmin();
require_once __DIR__ . '/_ui.php';

const DB_LIVE  = '/var/lib/stratedge/live.sqlite';
const BASELINE = '/var/lib/stratedge/baseline.json';

/* Colonnes de l'export stats PackBall — mêmes que le slate quotidien. */
const C_BM_DOM = 17, C_BM_EXT = 18, C_BE_DOM = 19, C_BE_EXT = 20;

/** Reproduit EXACTEMENT norm() du moteur Python. Toute divergence ici
 *  casserait la jointure sans le dire. */
function lwNorm(?string $s): string {
    if (!$s) return '';
    $s = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s) ?: $s;
    $s = str_replace('-', ' ', mb_strtolower($s));
    return trim(preg_replace('/\s+/', ' ', $s));
}

function lwNum($v): ?float {
    $f = str_replace(',', '.', trim((string)$v));
    return is_numeric($f) ? (float)$f : null;
}

/** Lit un CSV PackBall (séparateur ";", BOM UTF-8). */
function lwCsv(string $path): array {
    $out = [];
    if (($h = fopen($path, 'r')) === false) return $out;
    $first = true;
    while (($r = fgetcsv($h, 0, ';')) !== false) {
        if ($first) { $first = false; continue; }       // en-tête
        if (count($r) > 8) $out[] = $r;
    }
    fclose($h);
    return $out;
}

function lwDb(): ?PDO {
    if (!is_readable(DB_LIVE)) return null;
    try {
        $p = new PDO('sqlite:' . DB_LIVE, null, null,
                     [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        return $p;
    } catch (Throwable $e) { return null; }
}

$msg = null; $rapport = null;

/* ── génération de la baseline ─────────────────────────────────────────── */
if (($_POST['action'] ?? '') === 'baseline'
    && isset($_FILES['stats'], $_FILES['odds'])) {

    $fs = $_FILES['stats']; $fo = $_FILES['odds'];
    if ($fs['error'] !== UPLOAD_ERR_OK || $fo['error'] !== UPLOAD_ERR_OK) {
        $msg = ['ko', 'Envoi interrompu — il faut les DEUX fichiers.'];
    } else {
        $stats = [];
        foreach (lwCsv($fs['tmp_name']) as $r) {
            if (count($r) > 70) {
                $stats[trim($r[3]) . '|' . trim($r[5]) . '|' . trim($r[8])] = $r;
            }
        }

        $base = []; $sans = 0;
        foreach (lwCsv($fo['tmp_name']) as $r) {
            $k = trim($r[3]) . '|' . trim($r[5]) . '|' . trim($r[8]);
            $s = $stats[$k] ?? null;
            if (!$s || count($s) <= C_BE_EXT) { $sans++; continue; }

            $bmd = lwNum($s[C_BM_DOM]); $bme = lwNum($s[C_BM_EXT]);
            $bed = lwNum($s[C_BE_DOM]); $bee = lwNum($s[C_BE_EXT]);
            if ($bmd === null || $bme === null || $bed === null || $bee === null) {
                $sans++; continue;
            }
            // Méthode de convergence, identique au slate : le λ d'une équipe
            // combine ce qu'elle marque et ce que l'adversaire encaisse.
            $lh = ($bmd + $bee) / 2;
            $la = ($bme + $bed) / 2;

            $ts = DateTime::createFromFormat('d-m-Y H:i', trim($r[3]));
            $jour = $ts ? $ts->format('Y-m-d') : date('Y-m-d');

            $base[] = [
                'date' => $jour, 'home' => trim($r[5]), 'away' => trim($r[8]),
                'lambda_home'  => round($lh, 3),
                'lambda_away'  => round($la, 3),
                'lambda_total' => round($lh + $la, 3),
            ];
        }

        if (!$base) {
            $msg = ['ko', "Aucun match exploitable — vérifie que ce sont bien "
                        . "les deux exports du même jour."];
        } else {
            $ok = @file_put_contents(BASELINE,
                    json_encode($base, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            if ($ok === false) {
                $msg = ['ko', 'Écriture impossible dans ' . BASELINE . '.'];
            } else {
                // ── contrôle d'appariement, tout de suite ──────────────
                $idx = [];
                foreach ($base as $b) $idx[lwNorm($b['home']) . '|' . lwNorm($b['away'])] = true;

                $live = []; $app = 0; $manques = [];
                if ($p = lwDb()) {
                    $q = $p->query("SELECT DISTINCT home, away FROM snapshots
                                    WHERE collected_at > datetime('now','-3 hours')");
                    foreach ($q as $r) {
                        $live[] = $r;
                        if (isset($idx[lwNorm($r['home']) . '|' . lwNorm($r['away'])])) $app++;
                        elseif (count($manques) < 14) $manques[] = $r['home'] . ' – ' . $r['away'];
                    }
                }
                $rapport = ['n' => count($base), 'sans' => $sans,
                            'live' => count($live), 'app' => $app, 'manques' => $manques];
                $msg = ['ok', count($base) . " matchs écrits. Le moteur relit le "
                            . "fichier tout seul — aucun redémarrage nécessaire."];
            }
        }
    }
}

/* ── reprise depuis le slate du jour ───────────────────────────────────
   Le slate contient déjà les λ, calculés par la même méthode de convergence.
   Les redéposer en CSV serait faire deux fois le même travail — et prendre
   le risque que les deux systèmes divergent sur des chiffres différents. */
if (($_POST['action'] ?? '') === 'depuis_slate') {
    $f = __DIR__ . '/data/slate_courant.json';
    if (!is_file($f)) {
        $msg = ['ko', "Aucun slate chargé. Passe d'abord par le programme du jour."];
    } else {
        $d = json_decode((string)file_get_contents($f), true);
        $base = []; $sans = 0;
        foreach (($d['matchs'] ?? []) as $m) {
            if (!isset($m['lambda_dom'], $m['lambda_ext'])
                || $m['lambda_dom'] === null || $m['lambda_ext'] === null) {
                $sans++; continue;
            }
            // Le moteur joint sur la date LOCALE ; kickoff est déjà en heure
            // locale dans le slate.
            $jour = substr((string)($m['kickoff'] ?? ''), 0, 10) ?: date('Y-m-d');
            $base[] = [
                'date' => $jour, 'home' => $m['domicile'], 'away' => $m['exterieur'],
                'lambda_home'  => (float)$m['lambda_dom'],
                'lambda_away'  => (float)$m['lambda_ext'],
                'lambda_total' => round((float)$m['lambda_dom'] + (float)$m['lambda_ext'], 3),
            ];
        }
        if (!$base) {
            $msg = ['ko', "Le slate ne contient pas de λ par équipe — il date "
                        . "d'avant la mise à jour. Regénère-le."];
        } else {
            $ok = @file_put_contents(BASELINE,
                    json_encode($base, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            if ($ok === false) {
                $msg = ['ko', 'Écriture impossible dans ' . BASELINE . '.'];
            } else {
                $idx = [];
                foreach ($base as $b) $idx[lwNorm($b['home']) . '|' . lwNorm($b['away'])] = true;
                $live = []; $app = 0; $manques = [];
                if ($p = lwDb()) {
                    foreach ($p->query("SELECT DISTINCT home, away FROM snapshots
                                        WHERE collected_at > datetime('now','-3 hours')") as $r) {
                        $live[] = $r;
                        if (isset($idx[lwNorm($r['home']) . '|' . lwNorm($r['away'])])) $app++;
                        elseif (count($manques) < 14) $manques[] = $r['home'] . ' – ' . $r['away'];
                    }
                }
                $rapport = ['n' => count($base), 'sans' => $sans,
                            'live' => count($live), 'app' => $app, 'manques' => $manques];
                $msg = ['ok', count($base) . ' matchs repris du slate du jour.'];
            }
        }
    }
}

/* ── état du système ───────────────────────────────────────────────────── */
$etat = ['releves' => 0, 'cycles' => 0, 'matchs' => 0, 'dernier' => null,
         'alertes' => [], 'recentes' => [], 'live_now' => 0];
if ($p = lwDb()) {
    try {
        $r = $p->query("SELECT count(*) n, count(DISTINCT collected_at) c,
                               count(DISTINCT packball_id) m, max(collected_at) d
                        FROM snapshots")->fetch(PDO::FETCH_ASSOC);
        $etat['releves'] = (int)$r['n']; $etat['cycles'] = (int)$r['c'];
        $etat['matchs'] = (int)$r['m'];  $etat['dernier'] = $r['d'];

        $etat['live_now'] = (int)$p->query("SELECT count(DISTINCT packball_id) FROM snapshots
            WHERE state='LIVE' AND collected_at > datetime('now','-3 minutes')")->fetchColumn();

        foreach ($p->query("SELECT rule, count(*) n, sum(sent) s, round(avg(ev),4) e
                            FROM alerts GROUP BY rule ORDER BY rule") as $a) {
            $etat['alertes'][] = $a;
        }
        foreach ($p->query("SELECT created_at, rule, market, minute, odds, p_est, ev, sent,
                                   json_extract(payload,'\$.home') h,
                                   json_extract(payload,'\$.away') a
                            FROM alerts ORDER BY id DESC LIMIT 12") as $a) {
            $etat['recentes'][] = $a;
        }
    } catch (Throwable $e) { /* table alerts absente tant qu'aucune alerte */ }
}

$blInfo = is_file(BASELINE)
    ? ['n' => count(json_decode((string)file_get_contents(BASELINE), true) ?: []),
       'date' => date('d/m à H:i', (int)filemtime(BASELINE))]
    : null;

/* Un relevé plus vieux que 3 min pendant les heures de match = collecte arrêtée.
   C'est presque toujours l'onglet PackBall passé en arrière-plan : Chrome y
   ralentit les timers. */
$ageMin = $etat['dernier'] ? (time() - strtotime($etat['dernier'] . ' UTC')) / 60 : null;

?>
<?php
ui_head('Live Watcher');
$pill = ($ageMin !== null && $ageMin <= 3)
    ? '<span class="pill live"><b>●</b> collecte active · ' . $etat['live_now'] . ' match' . ($etat['live_now'] > 1 ? 's' : '') . '</span>'
    : '<span class="pill warn"><b>●</b> collecte arrêtée' . ($ageMin !== null ? ' depuis ' . (int)$ageMin . ' min' : '') . '</span>';
ui_topbar('live', $pill);
$LBL = ['A' => 'Pression convertible', 'B' => 'Siège à domicile', 'C' => 'Carton rouge', 'D' => 'Match verrouillé'];
$nAl = array_sum(array_column($etat['alertes'], 'n'));
?>
<style>
 .jauge{height:6px;background:#1c1c30;border-radius:3px;overflow:hidden;margin-top:8px}
 .jauge i{display:block;height:100%}
 .regle{display:flex;justify-content:space-between;align-items:center;gap:10px;padding:12px 14px;
        border-radius:12px;background:var(--card3);margin-bottom:8px}
 .regle .n{font-size:13.5px;font-weight:700}
 .regle .s{font-size:11.5px;color:var(--mute);margin-top:2px}
 .regle .v{font-family:'JetBrains Mono',monospace;font-size:14px;font-weight:500;white-space:nowrap}
 .regle.off{opacity:.5}
 .up.two{gap:22px}
 .fld{display:flex;flex-direction:column;gap:5px}
 .ou{font-size:11px;font-weight:600;letter-spacing:.14em;color:var(--mute2);text-transform:uppercase;margin:14px 0 6px}
</style>

<?php if ($msg): ?><div class="note <?= $msg[0] === 'ok' ? 'ok' : 'ko' ?>"><?= e($msg[1]) ?></div><?php endif ?>

<?php if ($ageMin !== null && $ageMin > 3): ?>
<div class="note ko">
  <strong>Collecte interrompue depuis <?= (int)$ageMin ?> minutes.</strong>
  Presque toujours l'onglet PackBall passé en arrière-plan — Chrome y ralentit les timers.
  Rouvre <span class="mono">packball.com/fr/matches</span> avec le bon filtre et garde l'onglet visible.
</div>
<?php endif ?>

<div class="hero">
  <p class="lbl" style="margin-bottom:8px">Temps réel</p>
  <h1>Live Watcher <span>· mode shadow</span></h1>
  <a class="cta ghost" href="resultats.php">Voir l'historique →</a>

  <div class="stats">
    <div class="stat">
      <div class="lbl">Matchs suivis</div>
      <div class="big <?= $etat['live_now'] ? 'pos' : 'mut' ?>"><?= $etat['live_now'] ?></div>
      <div class="sub">en direct dans les 3 dernières minutes</div>
    </div>
    <div class="stat">
      <div class="lbl">Relevés</div>
      <div class="mid"><?= number_format($etat['releves'], 0, ',', ' ') ?></div>
      <div class="lbl" style="margin-top:12px">Cycles</div>
      <div class="mid"><?= number_format($etat['cycles'], 0, ',', ' ') ?></div>
      <div class="sub">un toutes les 45 s</div>
    </div>
    <div class="stat">
      <div class="lbl">Alertes</div>
      <div class="mid"><?= $nAl ?></div>
      <div class="lbl" style="margin-top:12px">Dernier relevé</div>
      <div class="mid <?= ($ageMin !== null && $ageMin <= 3) ? 'pos' : 'neg' ?>" style="font-size:20px">
        <?= $etat['dernier'] ? e(substr($etat['dernier'], 11, 5)) . ' UTC' : '—' ?></div>
      <div class="sub"><?= $ageMin === null ? 'aucun' : 'il y a ' . (int)$ageMin . ' min' ?></div>
    </div>
    <div class="stat">
      <div class="lbl">Baseline pré-match</div>
      <div class="mid <?= $blInfo ? '' : 'neg' ?>"><?= $blInfo ? $blInfo['n'] : '—' ?></div>
      <div class="sub"><?= $blInfo ? 'matchs · ' . e($blInfo['date']) : 'absente · B et D inactives' ?></div>
    </div>
  </div>

  <div class="grid2">
    <div class="panel">
      <h2>Alimenter la baseline <small>règles B et D</small></h2>
      <p class="mut" style="font-size:12.5px;line-height:1.5;margin-bottom:12px">
        Le siège à domicile et le match verrouillé ont besoin des λ pré-match. Sans eux ils
        restent muets, sans message d'erreur. Le moteur relit le fichier tout seul.</p>

      <form method="post" style="display:flex;gap:12px;align-items:center;flex-wrap:wrap">
        <input type="hidden" name="action" value="depuis_slate">
        <button type="submit">Reprendre du slate</button>
        <span class="mut" style="font-size:12px">Même calcul que le programme, aucun fichier à redéposer.</span>
      </form>

      <p class="ou">— ou déposer les CSV —</p>
      <form method="post" enctype="multipart/form-data" class="up two" style="margin-top:0;padding:0;background:transparent;border:0">
        <input type="hidden" name="action" value="baseline">
        <div class="fld"><span class="lbl" style="font-size:9.5px">Export stats</span><input type="file" name="stats" accept=".csv" required></div>
        <div class="fld"><span class="lbl" style="font-size:9.5px">Export cotes</span><input type="file" name="odds" accept=".csv" required></div>
        <button type="submit" class="ghost">Générer</button>
      </form>

<?php if ($rapport):
        $taux = $rapport['live'] ? $rapport['app'] / $rapport['live'] * 100 : 0;
        $col = $taux >= 80 ? 'var(--lime)' : ($taux >= 50 ? 'var(--amber)' : 'var(--red)'); ?>
      <div class="kpis" style="margin-top:16px">
        <div class="kpi"><span class="lbl">Écrits</span><b><?= $rapport['n'] ?></b><small><?= $rapport['sans'] ?> sans stats</small></div>
        <div class="kpi" style="flex:1"><span class="lbl">Appariement avec le live</span>
          <b style="color:<?= $col ?>"><?= number_format($taux, 0) ?> %</b>
          <small><?= $rapport['app'] ?> / <?= $rapport['live'] ?> matchs</small>
          <div class="jauge"><i style="width:<?= (int)$taux ?>%;background:<?= $col ?>"></i></div></div>
      </div>
<?php if ($rapport['manques']): ?>
      <div class="note" style="margin-bottom:0">
        <strong>B et D resteront muettes sur ces matchs</strong> — en live mais absents de l'export :
        <div class="mono" style="margin-top:6px;font-size:11px;line-height:1.6"><?= e(implode(' · ', array_slice($rapport['manques'], 0, 8))) ?></div>
        <p style="margin-top:7px">Ce n'est pas un problème d'orthographe. <strong>Exporte au périmètre le plus large.</strong></p>
      </div>
<?php endif ?>
<?php endif ?>
    </div>

    <div class="panel">
      <h2>Règles <small>4 actives en mode shadow</small></h2>
<?php $par = []; foreach ($etat['alertes'] as $a) $par[$a['rule']] = $a;
      foreach (['A', 'B', 'C', 'D'] as $r):
        $a = $par[$r] ?? null;
        $off = in_array($r, ['B', 'D'], true) && !$blInfo; ?>
      <div class="regle <?= $off ? 'off' : '' ?>">
        <div style="min-width:0">
          <div class="n"><span class="chip t" style="margin:0 8px 0 0"><?= $r ?></span><?= e($LBL[$r]) ?></div>
          <div class="s"><?= $off ? 'inactive — baseline absente' : ($a ? (int)$a['n'] . ' alerte' . ((int)$a['n'] > 1 ? 's' : '') . ' · ' . (int)$a['s'] . ' envoyée' . ((int)$a['s'] > 1 ? 's' : '') : 'aucune alerte pour l\'instant') ?></div>
        </div>
        <div class="v <?= ($a && $a['e'] !== null && (float)$a['e'] > 0) ? 'pos' : 'mut' ?>">
          <?= ($a && $a['e'] !== null) ? sprintf('EV %+.1f %%', (float)$a['e'] * 100) : '—' ?></div>
      </div>
<?php endforeach ?>
      <p class="mut" style="font-size:11.5px;margin-top:10px;line-height:1.5">
        Zéro alerte est le comportement attendu tant qu'aucun match ne remplit les conditions —
        <strong>A</strong> exige ExG ≥ 0,45, pression ≥ 60 et deux tirs cadrés entre la 25ᵉ et la 70ᵉ.</p>
    </div>
  </div>

<?php if ($etat['recentes']): ?>
  <div class="panel" style="margin-top:16px">
    <h2>Dernières détections</h2>
    <table>
      <tr><th>Heure</th><th>Match</th><th>Règle</th><th>Marché</th><th>Min</th><th>Cote</th><th>P est.</th><th>EV</th><th>Envoi</th></tr>
<?php foreach ($etat['recentes'] as $a): ?>
      <tr>
        <td class="mono" style="font-weight:500"><?= e(substr((string)$a['created_at'], 11, 5)) ?></td>
        <td style="font-weight:600"><?= e(($a['h'] ?? '?') . ' – ' . ($a['a'] ?? '?')) ?></td>
        <td><span class="chip t" style="margin:0"><?= e($a['rule']) ?></span></td>
        <td class="mono"><?= e($a['market']) ?></td>
        <td class="mono"><?= (int)$a['minute'] ?>′</td>
        <td class="mono"><?= $a['odds'] === null ? '—' : number_format((float)$a['odds'], 2) ?></td>
        <td class="mono mut"><?= $a['p_est'] === null ? '—' : number_format((float)$a['p_est'] * 100, 1) . ' %' ?></td>
        <td class="mono <?= ($a['ev'] !== null && (float)$a['ev'] > 0) ? 'pos' : 'mut' ?>"><?= $a['ev'] === null ? '—' : sprintf('%+.1f %%', (float)$a['ev'] * 100) ?></td>
        <td class="mono <?= (int)$a['sent'] ? 'pos' : 'amb' ?>"><?= (int)$a['sent'] ? '✓' : '—' ?></td>
      </tr>
<?php endforeach ?>
    </table>
  </div>
<?php endif ?>

  <div class="note info" style="margin:16px 0 0">
    <strong>Mode shadow.</strong> Les alertes partent sur Telegram préfixées
    <span class="mono">🧪 SHADOW — ne pas miser</span>, sans plafond quotidien. Le live n'a jamais
    été mesuré : on compte d'abord. Le passage en réel se fait par
    <span class="mono">SE_SHADOW_MODE=0</span> et un redémarrage du service.
  </div>
</div>
<?php ui_foot(); ?>
