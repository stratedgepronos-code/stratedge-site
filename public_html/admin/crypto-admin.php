<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/mailer.php';
requireAdmin();
$db = getDB();
$pageActive = 'crypto';

$success = ''; $error = '';

// Valider ou rejeter une demande
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf($_POST['csrf_token'] ?? '')) {
    $payId  = (int)($_POST['payment_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    $note   = trim($_POST['note'] ?? '');

    if ($payId && in_array($action, ['valider','rejeter'])) {
        $statut = $action === 'valider' ? 'validé' : 'rejeté';

        // Récupérer le paiement
        $p = $db->prepare("SELECT cp.*, m.email, m.nom FROM crypto_payments cp JOIN membres m ON m.id=cp.membre_id WHERE cp.id=?");
        $p->execute([$payId]);
        $pay = $p->fetch();

        if ($pay) {
            $db->prepare("UPDATE crypto_payments SET statut=?, date_validation=NOW(), note_admin=? WHERE id=?")
               ->execute([$statut, $note, $payId]);

            if ($action === 'valider') {
                $durees = ['daily' => 'P1D', 'weekend' => 'P3D', 'weekly' => 'P7D'];
                $expire = (new DateTime())->add(new DateInterval($durees[$pay['offre']] ?? 'P1D'))->format('Y-m-d H:i:s');
                $db->prepare("INSERT INTO abonnements (membre_id, type, actif, date_debut, date_fin) VALUES (?,?,1,NOW(),?)")
                   ->execute([$pay['membre_id'], $pay['offre'], $expire]);

                envoyerEmailTexte($pay['email'], "✅ Accès activé — StratEdge Pronos",
                    "Bonjour {$pay['nom']},\n\nVotre paiement crypto a été validé !\n"
                    . "Votre accès {$pay['offre']} est maintenant actif.\n\n"
                    . "👉 Connectez-vous sur https://stratedgepronos.fr/dashboard.php\n\n"
                    . "StratEdge Pronos"
                );
                $success = "✅ Abonnement activé pour {$pay['nom']} — email envoyé.";
            } else {
                envoyerEmailTexte($pay['email'], "❌ Transaction rejetée — StratEdge Pronos",
                    "Bonjour {$pay['nom']},\n\nVotre transaction n'a pas pu être validée.\n"
                    . "Raison : " . ($note ?: 'Transaction introuvable sur la blockchain') . "\n\n"
                    . "Contactez le support : stratedgepronos@gmail.com\n\nStratEdge Pronos"
                );
                $success = "Transaction rejetée — {$pay['nom']} notifié.";
            }
            }
        }
    }
}

$payments = $db->query("
    SELECT cp.*, m.nom, m.email
    FROM crypto_payments cp
    JOIN membres m ON m.id = cp.membre_id
    ORDER BY cp.statut='en_attente' DESC, cp.date_demande DESC
")->fetchAll();

$pending = array_filter($payments, fn($p) => $p['statut'] === 'en_attente');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Paiements Crypto — Admin StratEdge</title>
  <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@400;600;700&family=Space+Mono&display=swap" rel="stylesheet">
  <style>
    :root{--bg-dark:#050810;--bg-card:#0d1220;--neon-green:#ff2d78;--text-primary:#f0f4f8;--text-secondary:#b0bec9;--text-muted:#8a9bb0;--border-subtle:rgba(255,45,120,0.12);}
    *{margin:0;padding:0;box-sizing:border-box}
    body{font-family:'Rajdhani',sans-serif;background:var(--bg-dark);color:var(--text-primary);min-height:100vh;display:flex;}
    .main{flex:1;padding:2rem;max-width:1100px;}
    .page-title{font-family:'Orbitron',sans-serif;font-size:1.4rem;font-weight:700;margin-bottom:0.4rem;}
    .page-sub{color:var(--text-muted);font-size:0.88rem;margin-bottom:2rem;}
    .alert-ok{background:rgba(0,200,100,0.1);border:1px solid rgba(0,200,100,0.2);border-radius:10px;padding:1rem;color:#00c864;margin-bottom:1.5rem;}
    .card{background:var(--bg-card);border:1px solid var(--border-subtle);border-radius:14px;padding:1.5rem;margin-bottom:1.5rem;}
    .card-title{font-family:'Orbitron',sans-serif;font-size:0.85rem;font-weight:700;margin-bottom:1.2rem;color:var(--text-secondary);}
    .pay-row{background:rgba(255,255,255,0.02);border:1px solid rgba(255,255,255,0.06);border-radius:12px;padding:1.2rem;margin-bottom:1rem;}
    .pay-header{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:0.5rem;margin-bottom:0.8rem;}
    .pay-name{font-weight:700;font-size:1rem;}
    .pay-offre{font-family:'Orbitron',sans-serif;font-size:0.7rem;padding:0.25rem 0.7rem;border-radius:20px;background:rgba(255,45,120,0.1);color:#ff2d78;border:1px solid rgba(255,45,120,0.25);}
    .pay-hash{font-family:'Space Mono',monospace;font-size:0.75rem;color:var(--text-muted);background:rgba(0,0,0,0.3);border-radius:6px;padding:0.4rem 0.8rem;word-break:break-all;margin-bottom:0.8rem;}
    .pay-meta{font-size:0.8rem;color:var(--text-muted);margin-bottom:0.8rem;}
    .pay-actions{display:flex;gap:0.5rem;flex-wrap:wrap;align-items:flex-end;}
    .pay-note{flex:1;min-width:200px;}
    .pay-note input{width:100%;background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.1);border-radius:8px;padding:0.5rem 0.75rem;color:var(--text-primary);font-family:'Rajdhani',sans-serif;font-size:0.85rem;outline:none;}
    .btn-valider{background:rgba(0,200,100,0.15);border:1px solid rgba(0,200,100,0.3);color:#00c864;padding:0.5rem 1.2rem;border-radius:8px;font-family:'Orbitron',sans-serif;font-size:0.7rem;font-weight:700;cursor:pointer;transition:all 0.2s;}
    .btn-rejeter{background:rgba(255,45,120,0.1);border:1px solid rgba(255,45,120,0.25);color:#ff6b9d;padding:0.5rem 1.2rem;border-radius:8px;font-family:'Orbitron',sans-serif;font-size:0.7rem;font-weight:700;cursor:pointer;transition:all 0.2s;}
    .btn-verify{display:inline-flex;align-items:center;gap:0.4rem;background:rgba(0,212,255,0.08);border:1px solid rgba(0,212,255,0.2);color:#00d4ff;padding:0.4rem 0.9rem;border-radius:7px;font-size:0.78rem;font-weight:700;text-decoration:none;cursor:pointer;transition:all 0.2s;}
    .status-badge{display:inline-flex;align-items:center;gap:0.3rem;padding:0.2rem 0.7rem;border-radius:20px;font-size:0.72rem;font-weight:700;}
    .s-attente{background:rgba(255,193,7,0.1);color:#ffc107;border:1px solid rgba(255,193,7,0.25);}
    .s-valide{background:rgba(0,200,100,0.1);color:#00c864;border:1px solid rgba(0,200,100,0.25);}
    .s-rejete{background:rgba(255,45,120,0.1);color:#ff6b9d;border:1px solid rgba(255,45,120,0.2);}
    .empty{text-align:center;color:var(--text-muted);padding:2rem;}
    .stats{display:grid;grid-template-columns:repeat(3,1fr);gap:1rem;margin-bottom:2rem;}
    .stat{background:var(--bg-card);border:1px solid var(--border-subtle);border-radius:12px;padding:1.2rem;text-align:center;}
    .stat-num{font-family:'Orbitron',sans-serif;font-size:2rem;font-weight:900;color:#ff2d78;}
    .stat-label{font-size:0.78rem;color:var(--text-muted);margin-top:0.3rem;}
  </style>
</head>
<body>
<?php require_once __DIR__ . '/sidebar.php'; ?>
<div class="main">
  <div class="page-title">💰 Paiements Crypto</div>
  <div class="page-sub">Validez ou rejetez les transactions soumises par les membres</div>

  <?php if ($success): ?><div class="alert-ok"><?= clean($success) ?></div><?php endif; ?>

  <!-- Stats -->
  <div class="stats">
    <div class="stat">
      <div class="stat-num"><?= count(array_filter($payments, fn($p)=>$p['statut']==='en_attente')) ?></div>
      <div class="stat-label">En attente</div>
    </div>
    <div class="stat">
      <div class="stat-num" style="color:#00c864"><?= count(array_filter($payments, fn($p)=>$p['statut']==='validé')) ?></div>
      <div class="stat-label">Validés</div>
    </div>
    <div class="stat">
      <div class="stat-num" style="color:#6b7a90"><?= count(array_filter($payments, fn($p)=>$p['statut']==='rejeté')) ?></div>
      <div class="stat-label">Rejetés</div>
    </div>
  </div>

  <!-- Paiements en attente -->
  <div class="card">
    <div class="card-title">⏳ EN ATTENTE DE VALIDATION</div>
    <?php $pending = array_filter($payments, fn($p)=>$p['statut']==='en_attente'); ?>
    <?php if (empty($pending)): ?>
      <div class="empty">✅ Aucune transaction en attente</div>
    <?php else: foreach ($pending as $pay): ?>
      <div class="pay-row">
        <div class="pay-header">
          <div>
            <span class="pay-name"><?= clean($pay['nom']) ?></span>
            <span style="color:var(--text-muted);font-size:0.82rem;margin-left:0.5rem;"><?= clean($pay['email']) ?></span>
          </div>
          <div style="display:flex;gap:0.5rem;align-items:center;">
            <span class="pay-offre"><?= ucfirst($pay['offre']) ?></span>
            <span style="font-family:'Orbitron',sans-serif;font-size:0.7rem;color:var(--text-muted);"><?= strtoupper($pay['crypto']) ?></span>
          </div>
        </div>
        <div class="pay-hash"><?= clean($pay['tx_hash']) ?></div>
        <div class="pay-meta">
          Soumis le <?= date('d/m/Y à H:i', strtotime($pay['date_demande'])) ?>
          &nbsp;—&nbsp;
          <?php
            $explorers = ['btc'=>'https://www.blockchain.com/explorer/transactions/btc/','eth'=>'https://etherscan.io/tx/','usdc'=>'https://polygonscan.com/tx/','sol'=>'https://solscan.io/tx/','bnb'=>'https://bscscan.com/tx/'];
            $url = ($explorers[$pay['crypto']] ?? '') . urlencode($pay['tx_hash']);
          ?>
          <a href="<?= $url ?>" target="_blank" class="btn-verify">🔍 Vérifier sur la blockchain</a>
        </div>
        <form method="POST" style="width:100%;" onsubmit="return confirm('Confirmer cette action ?')">
          <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
          <input type="hidden" name="payment_id" value="<?= $pay['id'] ?>">
          <div class="pay-actions">
            <div class="pay-note">
              <input type="text" name="note" placeholder="Note (optionnel pour rejet)">
            </div>
            <button type="submit" name="action" value="valider" class="btn-valider">✅ Valider</button>
            <button type="submit" name="action" value="rejeter" class="btn-rejeter">❌ Rejeter</button>
          </div>
        </form>
      </div>
    <?php endforeach; endif; ?>
  </div>

  <!-- Historique -->
  <div class="card">
    <div class="card-title">📋 HISTORIQUE</div>
    <?php $historique = array_filter($payments, fn($p)=>$p['statut']!=='en_attente');
    if (empty($historique)): ?>
      <div class="empty">Aucun historique</div>
    <?php else: foreach ($historique as $pay): ?>
      <div style="display:flex;align-items:center;gap:1rem;padding:0.75rem 0;border-bottom:1px solid rgba(255,255,255,0.04);flex-wrap:wrap;">
        <span class="status-badge <?= $pay['statut']==='validé' ? 's-valide' : 's-rejete' ?>"><?= $pay['statut']==='validé'?'✅':'❌' ?> <?= $pay['statut'] ?></span>
        <span style="font-weight:700;"><?= clean($pay['nom']) ?></span>
        <span style="font-family:'Orbitron',sans-serif;font-size:0.7rem;color:#ff2d78;"><?= ucfirst($pay['offre']) ?></span>
        <span style="font-size:0.75rem;color:var(--text-muted);"><?= strtoupper($pay['crypto']) ?></span>
        <span style="font-family:'Space Mono',monospace;font-size:0.68rem;color:var(--text-muted);flex:1;"><?= clean(substr($pay['tx_hash'],0,32)) ?>...</span>
        <span style="font-size:0.78rem;color:var(--text-muted);"><?= date('d/m/Y', strtotime($pay['date_validation']??$pay['date_demande'])) ?></span>
      </div>
    <?php endforeach; endif; ?>
  </div>
</div>
</body>
</html>
