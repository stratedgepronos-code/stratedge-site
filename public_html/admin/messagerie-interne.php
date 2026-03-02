<?php
require_once __DIR__ . '/../includes/auth.php';
requireSuperAdmin();
$pageActive = 'messagerie-interne';
$db = getDB();
require_once __DIR__ . '/install-admin-idees.php';

// Marquer comme lu
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf($_POST['csrf_token'] ?? '')) {
    $action = $_POST['action'] ?? '';
    if ($action === 'marquer_lu') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) $db->prepare("UPDATE admin_inbox SET lu = 1 WHERE id = ?")->execute([$id]);
    }
    if ($action === 'tout_lu') {
        $db->exec("UPDATE admin_inbox SET lu = 1");
    }
    header('Location: messagerie-interne.php');
    exit;
}

$messages = $db->query("
    SELECT i.*, a.titre as idee_titre, a.type as idee_type, a.statut, a.progression_pct
    FROM admin_inbox i
    LEFT JOIN admin_idees a ON a.id = i.ref_id
    ORDER BY i.date_creation DESC
    LIMIT 200
")->fetchAll(PDO::FETCH_ASSOC);

$nbNonLus = (int)$db->query("SELECT COUNT(*) FROM admin_inbox WHERE lu = 0")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Messagerie interne — Super Admin StratEdge</title>
  <link rel="icon" type="image/png" href="../assets/images/mascotte.png">
  <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@400;500;600;700&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
  <style>
    :root{--bg-dark:#050810;--bg-card:#0d1220;--neon-green:#ff2d78;--neon-blue:#00d4ff;--text-primary:#f0f4f8;--text-secondary:#b0bec9;--text-muted:#8a9bb0;--border-subtle:rgba(255,45,120,0.12);}
    *{margin:0;padding:0;box-sizing:border-box;}
    body{font-family:'Rajdhani',sans-serif;background:var(--bg-dark);color:var(--text-primary);min-height:100vh;display:flex;}
    .main{margin-left:240px;flex:1;padding:2rem;}
    .page-header{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;margin-bottom:2rem;}
    .page-header h1{font-family:'Orbitron',sans-serif;font-size:1.4rem;}
    .btn{display:inline-flex;align-items:center;gap:0.5rem;padding:0.6rem 1.2rem;border-radius:10px;font-family:'Rajdhani',sans-serif;font-weight:700;text-decoration:none;border:none;cursor:pointer;font-size:0.9rem;transition:all 0.2s;}
    .btn-secondary{background:rgba(0,212,255,0.15);border:1px solid rgba(0,212,255,0.35);color:#00d4ff;}
    .btn-secondary:hover{background:rgba(0,212,255,0.25);}
    .card{background:var(--bg-card);border:1px solid var(--border-subtle);border-radius:14px;padding:1.5rem;margin-bottom:1rem;}
    .msg-item{border-bottom:1px solid var(--border-subtle);padding:1rem 0;}
    .msg-item:last-child{border-bottom:none;}
    .msg-item.non-lu{background:rgba(255,45,120,0.04);margin:0 -1.5rem;padding:1rem 1.5rem;}
    .msg-header{display:flex;align-items:center;justify-content:space-between;gap:0.75rem;flex-wrap:wrap;}
    .msg-titre{font-weight:700;}
    .msg-date{font-size:0.78rem;color:var(--text-muted);}
    .msg-contenu{color:var(--text-secondary);font-size:0.92rem;margin-top:0.5rem;white-space:pre-wrap;}
    .msg-link{margin-top:0.5rem;}
    .msg-link a{color:var(--neon-green);font-size:0.85rem;}
    .badge{font-size:0.7rem;padding:0.15rem 0.5rem;border-radius:12px;margin-left:0.5rem;}
    .badge-idee{background:rgba(0,212,255,0.2);color:#00d4ff;}
    .badge-bug{background:rgba(255,165,0,0.2);color:#ffa500;}
    .badge-count{background:var(--neon-green);color:#fff;padding:0.2rem 0.5rem;border-radius:10px;font-size:0.75rem;font-weight:700;}
    @media(max-width:768px){.main{margin-left:0;padding-top:68px;}}
  </style>
</head>
<body>
<?php require_once __DIR__ . '/sidebar.php'; ?>
<div class="main">
  <div class="page-header">
    <h1>📥 Messagerie interne</h1>
    <div style="display:flex;gap:0.5rem;align-items:center;">
      <?php if ($nbNonLus > 0): ?><span class="badge-count"><?= $nbNonLus ?> non lu<?= $nbNonLus > 1 ? 's' : '' ?></span><?php endif; ?>
      <a href="idees.php" class="btn btn-secondary">💡 Idées & Bugs</a>
      <form method="POST" style="display:inline;">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <input type="hidden" name="action" value="tout_lu">
        <button type="submit" class="btn btn-secondary">Tout marquer lu</button>
      </form>
    </div>
  </div>

  <div class="card">
    <?php if (empty($messages)): ?>
      <p style="color:var(--text-muted);">Aucun message (idées/bugs arriveront ici + notification push).</p>
    <?php else: ?>
      <?php foreach ($messages as $m): ?>
        <div class="msg-item <?= !$m['lu'] ? 'non-lu' : '' ?>">
          <div class="msg-header">
            <span class="msg-titre">
              <?= htmlspecialchars($m['titre']) ?>
              <span class="badge badge-<?= $m['idee_type'] ?? 'idee' ?>"><?= ($m['idee_type'] ?? 'idee') === 'bug' ? 'Bug' : 'Idée' ?></span>
            </span>
            <span class="msg-date"><?= date('d/m/Y H:i', strtotime($m['date_creation'])) ?></span>
          </div>
          <div class="msg-contenu"><?= nl2br(htmlspecialchars($m['contenu'])) ?></div>
          <div class="msg-link">
            <a href="idees.php#idee-<?= (int)$m['ref_id'] ?>">→ Voir et gérer dans Idées & Bugs</a>
            <?php if (!$m['lu']): ?>
              <form method="POST" style="display:inline;margin-left:0.5rem;">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                <input type="hidden" name="action" value="marquer_lu">
                <input type="hidden" name="id" value="<?= $m['id'] ?>">
                <button type="submit" style="background:none;border:none;color:var(--text-muted);cursor:pointer;font-size:0.8rem;">Marquer lu</button>
              </form>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
