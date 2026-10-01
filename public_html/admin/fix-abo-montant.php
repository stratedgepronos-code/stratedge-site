<?php
// ============================================================
// STRATEDGE — Correction montants abonnements (UI HTML)
// /panel-x9k3m/fix-abo-montant.php
// ============================================================

require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

$db = getDB();
$msg = '';
$msgType = '';

// ── POST: actions ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_montant') {
        $aboId = (int)($_POST['abo_id'] ?? 0);
        $newMontant = (float)($_POST['montant'] ?? 0);
        if ($aboId > 0 && $newMontant >= 0) {
            $stmt = $db->prepare("UPDATE abonnements SET montant = ? WHERE id = ?");
            $ok = $stmt->execute([$newMontant, $aboId]);
            $msg = $ok ? "✅ Abo #$aboId mis à jour : " . number_format($newMontant, 2) . "€" : "❌ Erreur UPDATE abo #$aboId";
            $msgType = $ok ? 'success' : 'error';
        }
    }
    elseif ($action === 'delete_abo') {
        $aboId = (int)($_POST['abo_id'] ?? 0);
        if ($aboId > 0) {
            $stmt = $db->prepare("DELETE FROM abonnements WHERE id = ?");
            $ok = $stmt->execute([$aboId]);
            $msg = $ok ? "🗑️ Abo #$aboId supprimé" : "❌ Erreur suppression";
            $msgType = $ok ? 'success' : 'error';
        }
    }
    elseif ($action === 'toggle_actif') {
        $aboId = (int)($_POST['abo_id'] ?? 0);
        if ($aboId > 0) {
            $stmt = $db->prepare("UPDATE abonnements SET actif = 1 - actif WHERE id = ?");
            $ok = $stmt->execute([$aboId]);
            $msg = $ok ? "🔄 Statut abo #$aboId basculé" : "❌ Erreur";
            $msgType = $ok ? 'success' : 'error';
        }
    }

    $type = $_POST['filter_type'] ?? 'tennis';
    header("Location: ?type=$type&msg=" . urlencode($msg) . "&msg_type=$msgType");
    exit;
}

$filterType = $_GET['type'] ?? 'tennis';
$msgGet = $_GET['msg'] ?? $msg;
$msgTypeGet = $_GET['msg_type'] ?? $msgType;

$stmt = $db->prepare("
    SELECT a.id, a.membre_id, a.type, a.montant, a.date_achat, a.date_fin, a.actif,
           m.email, m.nom
    FROM abonnements a
    LEFT JOIN membres m ON m.id = a.membre_id
    WHERE a.type = ?
    ORDER BY a.date_achat DESC
    LIMIT 100
");
$stmt->execute([$filterType]);
$abos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$sumStmt = $db->prepare("SELECT COALESCE(SUM(montant),0) FROM abonnements WHERE type = ?");
$sumStmt->execute([$filterType]);
$totalGlobal = (float)$sumStmt->fetchColumn();
$countGlobal = count($abos);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>StratEdge · Fix montants abonnements</title>
<style>
  *{box-sizing:border-box}
  body{margin:0;background:#0a0a12;color:#ede8e0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;padding:24px}
  h1{color:#ff2d78;margin-top:0}
  .msg{padding:12px 16px;border-radius:8px;margin-bottom:20px;font-weight:500}
  .msg.success{background:rgba(0,212,106,.15);border:1px solid rgba(0,212,106,.35);color:#00d46a}
  .msg.error{background:rgba(255,68,68,.15);border:1px solid rgba(255,68,68,.4);color:#ff4444}
  .filters{display:flex;gap:10px;margin-bottom:20px;flex-wrap:wrap}
  .filter-btn{padding:8px 16px;background:#1a1a24;border:1px solid rgba(255,255,255,.1);border-radius:6px;color:#ede8e0;text-decoration:none;font-size:.9rem;font-weight:500}
  .filter-btn:hover{border-color:rgba(255,45,120,.5)}
  .filter-btn.active{background:rgba(255,45,120,.15);border-color:#ff2d78;color:#ff2d78}
  .summary{background:linear-gradient(135deg,rgba(0,212,106,.08),rgba(0,212,255,.05));border:1px solid rgba(0,212,106,.3);border-radius:10px;padding:18px 22px;margin-bottom:20px;display:flex;gap:40px;align-items:center;flex-wrap:wrap}
  .summary-item{display:flex;flex-direction:column;gap:4px}
  .summary-label{font-size:.7rem;text-transform:uppercase;letter-spacing:2px;color:#8a9bb0}
  .summary-value{font-size:1.8rem;font-weight:700;color:#00d46a;font-variant-numeric:tabular-nums}
  .summary-value.small{font-size:1.2rem;color:#ede8e0}
  table{width:100%;border-collapse:collapse;background:#0f0f18;border:1px solid rgba(255,255,255,.08);border-radius:10px;overflow:hidden}
  th,td{padding:10px 14px;text-align:left;border-bottom:1px solid rgba(255,255,255,.05);font-size:.9rem}
  th{background:#1a1a24;font-size:.65rem;font-weight:600;letter-spacing:2px;text-transform:uppercase;color:#8a9bb0}
  tr:hover{background:rgba(255,255,255,.02)}
  .mini-form{display:flex;gap:8px;align-items:center}
  input.montant-input{width:90px;padding:6px 10px;background:#0a0a12;border:1px solid rgba(255,255,255,.15);border-radius:5px;color:#ede8e0;font-family:inherit;font-size:.9rem;font-variant-numeric:tabular-nums;text-align:right}
  input.montant-input:focus{outline:none;border-color:#ff2d78}
  button.btn{padding:6px 12px;border:1px solid;border-radius:5px;background:transparent;cursor:pointer;font-size:.8rem;font-weight:600}
  button.btn-save{color:#00d46a;border-color:rgba(0,212,106,.4)}
  button.btn-save:hover{background:rgba(0,212,106,.1)}
  button.btn-del{color:#ff4444;border-color:rgba(255,68,68,.4)}
  button.btn-del:hover{background:rgba(255,68,68,.1)}
  button.btn-toggle{color:#00d4ff;border-color:rgba(0,212,255,.4);font-size:.75rem;padding:4px 8px}
  .status-actif{color:#00d46a}
  .status-inactif{color:#8a9bb0;opacity:.6}
  .status-expired{color:#ffb432}
  .montant-current{font-family:monospace;color:#ff2d78;font-weight:700}
  .email-cell{max-width:250px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
  .back-btn{color:#8a9bb0;text-decoration:none;font-size:.85rem;margin-bottom:20px;display:inline-block}
  .actions-cell{display:flex;gap:5px}
</style>
</head>
<body>

<a href="/panel-x9k3m/" class="back-btn">← Retour dashboard</a>

<h1>🔧 Fix montants abonnements</h1>

<?php if ($msgGet): ?>
  <div class="msg <?= htmlspecialchars($msgTypeGet) ?>"><?= htmlspecialchars($msgGet) ?></div>
<?php endif; ?>

<div class="filters">
  <?php foreach (['tennis', 'fun', 'vip_max', 'weekly', 'daily', 'weekend', 'rasstoss'] as $t): ?>
    <a href="?type=<?= $t ?>" class="filter-btn <?= $filterType === $t ? 'active' : '' ?>">
      <?= strtoupper($t) ?>
    </a>
  <?php endforeach; ?>
</div>

<div class="summary">
  <div class="summary-item">
    <span class="summary-label">Type affiché</span>
    <span class="summary-value small"><?= htmlspecialchars($filterType) ?></span>
  </div>
  <div class="summary-item">
    <span class="summary-label">Nb abos</span>
    <span class="summary-value small"><?= $countGlobal ?></span>
  </div>
  <div class="summary-item">
    <span class="summary-label">Total revenu calculé</span>
    <span class="summary-value"><?= number_format($totalGlobal, 2, ',', ' ') ?> €</span>
  </div>
</div>

<?php if (empty($abos)): ?>
  <p style="color:#8a9bb0;">Aucun abonnement de type <strong><?= htmlspecialchars($filterType) ?></strong></p>
<?php else: ?>
<table>
  <thead>
    <tr>
      <th>ID</th>
      <th>Membre</th>
      <th>Date achat</th>
      <th>Statut</th>
      <th>Montant BDD</th>
      <th>Modifier</th>
      <th>Actions</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($abos as $a):
      $dateFin = !empty($a['date_fin']) ? new DateTime($a['date_fin']) : null;
      $now = new DateTime();
      $isExpired = $dateFin && $dateFin < $now;
      $statusCls = $a['actif'] ? ($isExpired ? 'status-expired' : 'status-actif') : 'status-inactif';
      $statusTxt = $a['actif'] ? ($isExpired ? '⏱️ Expiré' : '✅ Actif') : '❌ Inactif';
    ?>
    <tr>
      <td><strong>#<?= (int)$a['id'] ?></strong></td>
      <td class="email-cell">
        <?= htmlspecialchars($a['email'] ?? '(sans email)') ?><br>
        <small style="color:#8a9bb0;">membre #<?= (int)$a['membre_id'] ?></small>
      </td>
      <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime($a['date_achat']))) ?></td>
      <td class="<?= $statusCls ?>"><?= $statusTxt ?></td>
      <td><span class="montant-current"><?= number_format((float)$a['montant'], 2, ',', ' ') ?> €</span></td>
      <td>
        <form method="post" class="mini-form">
          <input type="hidden" name="action" value="update_montant">
          <input type="hidden" name="abo_id" value="<?= (int)$a['id'] ?>">
          <input type="hidden" name="filter_type" value="<?= htmlspecialchars($filterType) ?>">
          <input type="number" step="0.01" min="0" name="montant"
                 value="<?= number_format((float)$a['montant'], 2, '.', '') ?>"
                 class="montant-input">
          <button type="submit" class="btn btn-save" title="Sauvegarder">💾</button>
        </form>
      </td>
      <td class="actions-cell">
        <form method="post" class="mini-form" onsubmit="return confirm('Basculer actif/inactif ?');">
          <input type="hidden" name="action" value="toggle_actif">
          <input type="hidden" name="abo_id" value="<?= (int)$a['id'] ?>">
          <input type="hidden" name="filter_type" value="<?= htmlspecialchars($filterType) ?>">
          <button type="submit" class="btn btn-toggle" title="Basculer actif/inactif">⇄</button>
        </form>
        <form method="post" class="mini-form"
              onsubmit="return confirm('Supprimer DÉFINITIVEMENT l\'abo #<?= (int)$a['id'] ?> ?');">
          <input type="hidden" name="action" value="delete_abo">
          <input type="hidden" name="abo_id" value="<?= (int)$a['id'] ?>">
          <input type="hidden" name="filter_type" value="<?= htmlspecialchars($filterType) ?>">
          <button type="submit" class="btn btn-del" title="Supprimer">🗑</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>

<p style="color:#8a9bb0;font-size:.85rem;margin-top:20px;">
  💡 Astuces :<br>
  • Pour qu'un abo payé avec promo -50% affiche 7.50€ au lieu de 15€, tape <strong>7.50</strong> et clique 💾<br>
  • Pour ne pas comptabiliser un abo (test, doublon, offert), mets <strong>0</strong>€<br>
  • Le total en haut se recalcule automatiquement après chaque modification<br>
  • Dashboard "Revenus Tennis" = cette somme totale (tous abos type=tennis)
</p>

</body>
</html>
