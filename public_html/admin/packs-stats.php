<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/credits-manager.php';
requireAdmin();

$stats30 = stratedge_credits_stats_admin(30);
$statsAll = stratedge_credits_stats_admin(null);

function agg($rows) {
    $total = ['ventes'=>0,'ca'=>0,'credits'=>0];
    foreach ($rows as $r) {
        $total['ventes'] += (int)$r['nb_ventes'];
        $total['ca']     += (float)$r['ca_total'];
        $total['credits']+= (int)$r['credits_vendus'];
    }
    return $total;
}
$t30 = agg($stats30); $tAll = agg($statsAll);
?><!DOCTYPE html><html><head><meta charset="utf-8"><title>Stats Packs · Admin</title>
<style>body{background:#0a0a12;color:#fff;font-family:'Rajdhani',sans-serif;padding:2rem;max-width:1200px;margin:auto}h1{font-family:'Orbitron',sans-serif;color:#00d4ff}.kpis{display:grid;grid-template-columns:repeat(3,1fr);gap:1rem;margin:2rem 0}.kpi{background:rgba(255,255,255,.04);border:1px solid rgba(0,212,255,.2);border-radius:12px;padding:1.5rem}.kpi-v{font-family:'Bebas Neue',sans-serif;font-size:2.5rem;color:#00d4ff}.kpi-l{color:rgba(255,255,255,.6);text-transform:uppercase;font-size:.75rem;letter-spacing:1px}table{width:100%;border-collapse:collapse;margin-top:1rem}th,td{padding:.8rem;text-align:left;border-bottom:1px solid rgba(255,255,255,.08)}th{font-family:'Orbitron',sans-serif;font-size:.75rem;color:#ff2d7a;text-transform:uppercase}h2{color:#ff2d7a;margin-top:2rem}</style></head><body>
<h1>📊 Stats Packs Crédits</h1>
<h2>30 derniers jours</h2>
<div class="kpis">
<div class="kpi"><div class="kpi-l">Ventes</div><div class="kpi-v"><?= $t30['ventes'] ?></div></div>
<div class="kpi"><div class="kpi-l">CA</div><div class="kpi-v"><?= number_format($t30['ca'],2,',',' ') ?>€</div></div>
<div class="kpi"><div class="kpi-l">Crédits vendus</div><div class="kpi-v"><?= $t30['credits'] ?></div></div>
</div>
<table><tr><th>Pack</th><th>Méthode</th><th>Ventes</th><th>CA</th><th>Crédits</th></tr>
<?php foreach($stats30 as $r):?><tr><td><?= ucfirst($r['pack_type']) ?></td><td><?= $r['methode'] ?></td><td><?= $r['nb_ventes'] ?></td><td><?= number_format($r['ca_total'],2,',',' ') ?>€</td><td><?= $r['credits_vendus'] ?></td></tr><?php endforeach;?>
</table>
<h2>Total depuis le début</h2>
<div class="kpis">
<div class="kpi"><div class="kpi-l">Ventes totales</div><div class="kpi-v"><?= $tAll['ventes'] ?></div></div>
<div class="kpi"><div class="kpi-l">CA total</div><div class="kpi-v"><?= number_format($tAll['ca'],2,',',' ') ?>€</div></div>
<div class="kpi"><div class="kpi-l">Crédits vendus</div><div class="kpi-v"><?= $tAll['credits'] ?></div></div>
</div>
</body></html>
