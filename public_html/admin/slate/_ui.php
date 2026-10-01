<?php
/**
 * slate/_ui.php — Le socle visuel commun aux trois pages.
 *
 * Programme, Historique et Live avaient chacune leur police, leur palette et
 * leurs boutons. Trois pages qui se ressemblent moins que trois sites
 * différents, c'est trois fois l'effort de lecture pour la même personne.
 *
 * Tout ce qui est partagé vit ici : variables de couleur, typographie,
 * barre de navigation, segments, panneaux, tableaux, notes, boutons. Chaque
 * page n'ajoute que ce qui lui est propre.
 *
 * Direction : sans-serif géométrique (Plus Jakarta Sans), chiffres en mono
 * tabulaire (JetBrains Mono), fond quasi noir, lime pour le positif, teal
 * pour l'actif, rouge pour le négatif. Une seule grande carte englobante par
 * page plutôt que des boîtes éparpillées.
 */
declare(strict_types=1);

function ui_head(string $titre): void {
    echo '<!DOCTYPE html><html lang="fr"><head><meta charset="utf-8">'
       . '<meta name="viewport" content="width=device-width,initial-scale=1">'
       . '<title>' . htmlspecialchars($titre, ENT_QUOTES) . ' — StratEdge</title>'
       . '<link rel="preconnect" href="https://fonts.googleapis.com">'
       . '<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">';
    ?>
<style>
 :root{
   --bg:#0a0a0e; --card:#111116; --card2:#16161d; --card3:#1c1c25;
   --line:#232330; --line2:#2c2c3a;
   --txt:#f2f3f8; --txt2:#c5c8d6; --mute:#7c8096; --mute2:#5a5e72;
   --lime:#c3f542; --teal:#17b8b0; --red:#f06a6a; --amber:#f5c542;
   --r:18px; --r2:12px;
 }
 *{box-sizing:border-box;margin:0;padding:0}
 html{background:var(--bg)}
 body{background:var(--bg);color:var(--txt);font-family:'Plus Jakarta Sans',system-ui,sans-serif;
      font-size:14px;line-height:1.45;padding:18px 22px 60px;-webkit-font-smoothing:antialiased}
 a{color:inherit}
 .mono{font-family:'JetBrains Mono',monospace;font-variant-numeric:tabular-nums}
 .pos{color:var(--lime)} .neg{color:var(--red)} .mut{color:var(--mute)} .amb{color:var(--amber)} .teal{color:var(--teal)}
 .lbl{font-size:10.5px;font-weight:600;letter-spacing:.12em;text-transform:uppercase;color:var(--mute)}
 h1{font-size:40px;font-weight:800;letter-spacing:-.03em;line-height:1.02}
 h1 span{color:var(--txt2);font-weight:600}
 h2{font-size:14px;font-weight:700;margin-bottom:12px;display:flex;align-items:baseline;gap:10px}
 h2 small{font-weight:500;color:var(--mute);font-size:11.5px}

 /* ── barre du haut, identique partout ── */
 .topbar{display:flex;align-items:center;gap:14px;margin-bottom:18px;flex-wrap:wrap}
 .brand{display:flex;align-items:center;gap:10px;font-weight:800;font-size:15px;letter-spacing:-.01em;text-decoration:none}
 .brand i{width:30px;height:30px;border-radius:9px;background:linear-gradient(135deg,var(--teal),var(--lime));display:block}
 .ver{font-family:'JetBrains Mono',monospace;font-size:10.5px;padding:3px 9px;border-radius:999px;
      border:1px solid var(--line2);color:var(--lime);background:#0f1a0f}
 .nav{display:flex;gap:4px;margin-left:8px}
 .nav a{padding:7px 12px;border-radius:9px;text-decoration:none;color:var(--txt2);font-weight:600;font-size:13px}
 .nav a:hover,.nav a.on{background:var(--card2);color:var(--txt)}
 .pill{margin-left:auto;display:inline-flex;align-items:center;gap:8px;padding:8px 14px;border-radius:999px;
       background:var(--card2);border:1px solid var(--line2);font-weight:600;font-size:13px;color:var(--txt2);text-decoration:none}
 .pill b{color:var(--lime);font-family:'JetBrains Mono',monospace;font-weight:500}
 .pill.live b{color:var(--teal)}
 .pill.warn{border-color:rgba(240,106,106,.4)} .pill.warn b{color:var(--red)}

 /* ── la grande carte ── */
 .hero{background:linear-gradient(160deg,#111a17 0%,var(--card) 40%);border:1px solid var(--line);
       border-radius:var(--r);padding:26px 30px 24px;position:relative}
 .hero.plain{background:var(--card)}
 .cta{position:absolute;right:30px;top:30px;padding:11px 18px;border-radius:999px;background:var(--lime);
      color:#0c1200;font-weight:700;font-size:13px;text-decoration:none;white-space:nowrap}
 .cta.ghost{background:var(--card2);color:var(--txt);border:1px solid var(--line2)}
 .stats{display:flex;gap:56px;align-items:flex-start;flex-wrap:wrap;margin-top:24px}
 .stat .lbl{font-size:10px;margin-bottom:6px}
 .stat .big{font-size:68px;font-weight:800;letter-spacing:-.045em;line-height:.9}
 .stat .mid{font-size:26px;font-weight:800;letter-spacing:-.02em;line-height:1.1}
 .stat .sub{color:var(--mute);font-size:12px;margin-top:6px}
 .frag{display:inline-flex;align-items:center;gap:6px;margin-top:9px;font-size:12.5px;font-weight:600;color:var(--red)}

 /* ── segments (filtres) ── */
 .seg{display:flex;background:var(--card2);border:1px solid var(--line);border-radius:999px;padding:4px;gap:3px}
 .seg a,.seg button{flex:1;text-align:center;padding:9px 6px;border-radius:999px;text-decoration:none;
        color:var(--txt2);font-size:13px;font-weight:600;transition:.15s;border:0;background:transparent;cursor:pointer;font-family:inherit}
 .seg a.on,.seg button.on,.seg [aria-pressed="true"]{background:var(--teal);color:#04110f}
 .seg a:hover:not(.on),.seg button:hover:not(.on){color:var(--txt);background:var(--card3)}
 .seg.wrap{flex-wrap:wrap}
 .seg.wrap>*{flex:0 0 auto;padding:8px 13px}

 /* ── panneaux et tableaux ── */
 .panel{background:var(--card2);border:1px solid var(--line);border-radius:14px;padding:18px 20px}
 .grid2{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px;margin-top:16px}
 .grid3{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px;margin-top:16px}
 @media(max-width:1500px){.grid3{grid-template-columns:repeat(2,1fr)}}
 @media(max-width:1000px){.grid2,.grid3{grid-template-columns:1fr}}
 table{width:100%;border-collapse:collapse}
 th{font-size:9.5px;font-weight:600;letter-spacing:.1em;color:var(--mute2);text-transform:uppercase;
    text-align:right;padding:6px 8px;border-bottom:1px solid var(--line)}
 th:first-child{text-align:left}
 td{padding:8px 8px;text-align:right;border-bottom:1px solid #1a1a22;font-size:13px}
 td:first-child{text-align:left;font-weight:600}
 tr:last-child td{border-bottom:0}
 .scroll{max-height:330px;overflow:auto}
 .chip{font-family:'JetBrains Mono',monospace;font-size:9.5px;padding:2px 6px;border-radius:5px;
       background:var(--card3);color:var(--mute);margin-left:6px;font-weight:400}
 .chip.t{background:rgba(23,184,176,.14);color:var(--teal)}
 .chip.r{background:rgba(240,106,106,.14);color:var(--red)}
 .dot{display:inline-block;width:7px;height:7px;border-radius:50%;margin-right:3px}

 /* ── KPI en ligne ── */
 .kpis{display:flex;gap:26px;flex-wrap:wrap;margin-bottom:12px}
 .kpi .lbl{font-size:9.5px}
 .kpi b{display:block;font-size:22px;font-weight:800;letter-spacing:-.02em;margin-top:2px}
 .kpi small{display:block;color:var(--mute);font-size:11px}

 /* ── formulaires et notes ── */
 .up{display:flex;gap:12px;align-items:center;flex-wrap:wrap;margin-top:16px;padding:14px 18px;
     background:var(--card2);border:1px solid var(--line);border-radius:14px}
 select,input[type=file],input[type=text],input[type=number],input[type=search]{
   background:var(--card3);border:1px solid var(--line2);color:var(--txt);
   padding:8px 12px;border-radius:9px;font-family:inherit;font-size:13px}
 input[type=search]{border-radius:999px;min-width:220px}
 input[type=range]{accent-color:var(--teal)}
 button,.btn{background:var(--lime);border:0;color:#0c1200;font-weight:700;padding:9px 18px;border-radius:999px;
        cursor:pointer;font-size:13px;font-family:inherit;text-decoration:none;display:inline-block}
 button.ghost,.btn.ghost{background:var(--card3);color:var(--txt);border:1px solid var(--line2)}
 .note{padding:12px 16px;border-radius:12px;margin:14px 0;font-size:13px;line-height:1.5;
       background:rgba(245,197,66,.07);border:1px solid rgba(245,197,66,.2);color:#e6d9a8}
 .note.ok{background:rgba(195,245,66,.07);border-color:rgba(195,245,66,.22);color:#d3ebb3}
 .note.ko{background:rgba(240,106,106,.07);border-color:rgba(240,106,106,.22);color:#f2c6c6}
 .note.info{background:rgba(23,184,176,.07);border-color:rgba(23,184,176,.22);color:#b8e8e4}
 .hidden{display:none!important}
</style>
</head><body>
<?php
    try { $db = getDB(); $pageActive = 'slate'; @require_once __DIR__ . '/../sidebar.php'; }
    catch (Throwable $e) {}
    echo '<div class="main">';
}

/** La barre du haut. $actif ∈ programme · historique · live. */
function ui_topbar(string $actif, string $pillHtml = ''): void { ?>
<div class="topbar">
  <a class="brand" href="index.php"><i></i> StratEdge</a>
  <span class="ver">v6 · slate</span>
  <nav class="nav">
    <a href="index.php"     class="<?= $actif === 'programme'  ? 'on' : '' ?>">Programme</a>
    <a href="resultats.php" class="<?= $actif === 'historique' ? 'on' : '' ?>">Historique</a>
    <a href="live.php"      class="<?= $actif === 'live'       ? 'on' : '' ?>">Live</a>
  </nav>
  <?= $pillHtml ?>
</div>
<?php }

function ui_foot(): void { echo '</div></body></html>'; }

function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES); }
function pc(?float $v, int $d = 1): string { return $v === null ? '—' : number_format($v * 100, $d) . ' %'; }
function sg(?float $v, int $d = 2): string { return $v === null ? '—' : sprintf('%+.' . $d . 'f %%', $v * 100); }
