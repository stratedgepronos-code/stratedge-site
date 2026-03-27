<?php
// ============================================================
// STRATEDGE — Test paiements (ADMIN ONLY)
// public_html/admin/test-paiements.php
// Simule un appel StarPass pour tester le flux complet
// ============================================================
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

$membre = getMembre();
$db = getDB();

$packs = [
    'daily'   => ['titre' => '⚡ Daily',        'prix' => '4.50€'],
    'weekend' => ['titre' => '📅 Week-End',      'prix' => '10€'],
    'weekly'  => ['titre' => '🏆 Weekly',         'prix' => '20€'],
    'tennis'  => ['titre' => '🎾 Tennis Weekly',  'prix' => '15€'],
    'vip_max' => ['titre' => '👑 VIP Max',        'prix' => '50€'],
];

$result = '';
$logContent = '';

// Simuler un paiement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_type'])) {
    $testType = $_POST['test_type'];
    $testMembre = (int)($_POST['test_membre'] ?? $membre['id']);
    
    if (isset($packs[$testType]) && $testMembre > 0) {
        // Appeler activerAbonnement directement
        $ok = activerAbonnement($testMembre, $testType);
        if ($ok) {
            $result = '<div style="background:rgba(0,200,100,.1);border:1px solid rgba(0,200,100,.3);color:#00c864;padding:12px;border-radius:8px;margin:12px 0;">✅ Abonnement <strong>' . $testType . '</strong> activé pour membre #' . $testMembre . '</div>';
        } else {
            $result = '<div style="background:rgba(255,50,50,.1);border:1px solid rgba(255,50,50,.3);color:#ff4444;padding:12px;border-radius:8px;margin:12px 0;">❌ Échec activation ' . $testType . ' pour membre #' . $testMembre . '</div>';
        }
    }
}

// Lire le log
$logFile = __DIR__ . '/../logs/activate-log.txt';
if (file_exists($logFile)) {
    $logContent = htmlspecialchars(file_get_contents($logFile));
}

// Derniers abonnements
$stmt = $db->query("SELECT a.*, m.nom FROM abonnements a JOIN membres m ON m.id = a.membre_id ORDER BY a.date_achat DESC LIMIT 15");
$derniers = $stmt->fetchAll();

// Tester l'URL activate.php
$activateUrl = 'https://' . ($_SERVER['HTTP_HOST'] ?? 'stratedgepronos.fr') . '/activate.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>🧪 Test Paiements — StratEdge</title>
<link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@400;500;600;700&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'Rajdhani',sans-serif;background:#060810;color:#f0f4f8;padding:2rem;}
h1{font-family:'Orbitron',sans-serif;font-size:1.3rem;margin-bottom:.3rem;}
h1 span{color:#ff2d78;}
.sub{color:#8a9bb0;margin-bottom:1.5rem;font-size:.9rem;}
.grid{display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;margin-bottom:2rem;}
.card{background:#0d1220;border:1px solid rgba(255,45,120,.12);border-radius:14px;padding:1.2rem;}
.card-title{font-family:'Orbitron',sans-serif;font-size:.68rem;letter-spacing:2px;color:#8a9bb0;text-transform:uppercase;margin-bottom:.8rem;}
.pack-btn{display:block;width:100%;padding:.7rem;margin:.4rem 0;background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.08);border-radius:8px;color:#f0f4f8;font-family:'Rajdhani',sans-serif;font-size:.95rem;font-weight:600;cursor:pointer;transition:all .2s;text-align:left;}
.pack-btn:hover{background:rgba(255,45,120,.06);border-color:rgba(255,45,120,.3);}
.pack-prix{float:right;font-family:'Orbitron',sans-serif;font-size:.75rem;color:#00d4ff;}
input[type=number]{background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.1);border-radius:6px;padding:.4rem .6rem;color:#fff;font-size:.9rem;width:80px;margin-bottom:.5rem;}
label{font-size:.75rem;color:#8a9bb0;display:block;margin-bottom:.2rem;}
.log{background:#0a0e17;border:1px solid rgba(255,255,255,.05);border-radius:8px;padding:1rem;font-family:'Space Mono',monospace;font-size:.7rem;color:#8a9bb0;max-height:300px;overflow-y:auto;white-space:pre-wrap;word-break:break-all;}
table{width:100%;border-collapse:collapse;font-size:.85rem;}
th{font-family:'Space Mono',monospace;font-size:.6rem;letter-spacing:1px;color:#8a9bb0;text-transform:uppercase;padding:.5rem;text-align:left;background:rgba(0,0,0,.3);}
td{padding:.4rem .5rem;border-bottom:1px solid rgba(255,255,255,.03);}
.ok{color:#00c864;}.err{color:#ff4444;}
.url-box{background:rgba(0,212,255,.05);border:1px solid rgba(0,212,255,.15);border-radius:8px;padding:.8rem;margin-bottom:1rem;word-break:break-all;}
.url-box code{font-family:'Space Mono',monospace;font-size:.72rem;color:#00d4ff;}
.url-label{font-size:.65rem;color:#8a9bb0;letter-spacing:1px;text-transform:uppercase;margin-bottom:.3rem;}
.warn{background:rgba(245,158,11,.08);border:1px solid rgba(245,158,11,.2);border-radius:8px;padding:.8rem;margin-bottom:1rem;color:#f59e0b;font-size:.85rem;}
.clear-btn{font-size:.7rem;color:#ff4444;background:none;border:1px solid rgba(255,50,50,.2);border-radius:6px;padding:.3rem .8rem;cursor:pointer;float:right;}
</style>
</head>
<body>
<h1>🧪 Test <span>Paiements</span></h1>
<p class="sub">Simule les paiements StarPass sans dépenser — vérifie que le flux fonctionne</p>

<?= $result ?>

<!-- DIAGNOSTIC -->
<div class="card" style="margin-bottom:1.5rem;">
  <div class="card-title">🔍 Diagnostic StarPass</div>
  
  <div class="url-label">URL callback StarPass (urlc) — à configurer dans le panel StarPass :</div>
  <div class="url-box"><code><?= $activateUrl ?>?datas={DATAS}</code></div>
  
  <div class="warn">
    <strong>⚠️ Vérifie dans ton panel StarPass</strong> que l'URL de callback (urlc) est bien :<br>
    <code><?= $activateUrl ?></code><br>
    StarPass doit appeler cette URL en GET avec le paramètre <code>datas</code> contenant <code>MEMBRE_ID:TYPE</code>
  </div>

  <div class="url-label">Test manuel (clique pour tester) :</div>
  <div class="url-box">
    <a href="/activate.php?datas=<?= $membre['id'] ?>:daily" style="color:#00d4ff;text-decoration:none;">
      <code>/activate.php?datas=<?= $membre['id'] ?>:daily</code> → devrait activer Daily et rediriger vers merci.php
    </a>
  </div>
</div>

<div class="grid">
  <!-- SIMULER -->
  <div class="card">
    <div class="card-title">🎮 Simuler un achat</div>
    <form method="post">
      <label>Membre ID :</label>
      <input type="number" name="test_membre" value="<?= $membre['id'] ?>" min="1">
      <div style="margin-top:.5rem;">
      <?php foreach ($packs as $key => $pk): ?>
        <button class="pack-btn" type="submit" name="test_type" value="<?= $key ?>">
          <?= $pk['titre'] ?> <span class="pack-prix"><?= $pk['prix'] ?></span>
        </button>
      <?php endforeach; ?>
      </div>
    </form>
  </div>

  <!-- DERNIERS ABONNEMENTS -->
  <div class="card">
    <div class="card-title">📋 Derniers abonnements (15)</div>
    <div style="max-height:320px;overflow-y:auto;">
    <table>
      <thead><tr><th>Date</th><th>Membre</th><th>Type</th><th>Montant</th><th>Fin</th><th>Actif</th></tr></thead>
      <tbody>
      <?php foreach ($derniers as $a): ?>
      <tr>
        <td style="font-size:.72rem;color:#8a9bb0;"><?= date('d/m H:i', strtotime($a['date_achat'])) ?></td>
        <td><?= htmlspecialchars($a['nom'] ?? '#' . $a['membre_id']) ?></td>
        <td style="font-weight:700;"><?= $a['type'] ?></td>
        <td style="color:#00d4ff;"><?= $a['montant'] ?>€</td>
        <td style="font-size:.72rem;"><?= $a['date_fin'] ? date('d/m/y', strtotime($a['date_fin'])) : '—' ?></td>
        <td><?= $a['actif'] ? '<span class="ok">✅</span>' : '<span class="err">❌</span>' ?></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    </div>
  </div>
</div>

<!-- LOG -->
<div class="card">
  <div class="card-title">
    📝 Log activate.php
    <?php if ($logContent): ?>
    <form method="post" style="display:inline;">
      <button class="clear-btn" name="clear_log" value="1" onclick="return confirm('Vider le log ?')">🗑️ Vider</button>
    </form>
    <?php endif; ?>
  </div>
  <?php
  if (isset($_POST['clear_log'])) {
      @file_put_contents($logFile, '');
      $logContent = '';
  }
  ?>
  <div class="log"><?= $logContent ?: 'Aucun log. Les appels à activate.php apparaîtront ici.' ?></div>
</div>

</body>
</html>
