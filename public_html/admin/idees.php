<?php
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
require_once __DIR__ . '/../includes/push.php';

$pageActive = 'idees';
$db = getDB();
$isSuperAdmin = isSuperAdmin();

// Créer les tables si besoin
require_once __DIR__ . '/install-admin-idees.php';

// Super admin : supprimer les projets/bugs terminés depuis plus d'une semaine
if ($isSuperAdmin) {
    try {
        $db->exec("DELETE FROM admin_inbox WHERE ref_id IN (SELECT id FROM admin_idees WHERE statut = 'termine' AND date_maj < NOW() - INTERVAL 7 DAY)");
        $db->exec("DELETE FROM admin_idees WHERE statut = 'termine' AND date_maj < NOW() - INTERVAL 7 DAY");
    } catch (Exception $e) { /* ignore */ }
}

$success = '';
$error = '';
$adminId = (int)$_SESSION['membre_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf($_POST['csrf_token'] ?? '')) {
    $action = $_POST['action'] ?? '';

    if ($action === 'submit') {
        $type = in_array($_POST['type'] ?? '', ['idee', 'bug']) ? $_POST['type'] : 'idee';
        $titre = trim($_POST['titre'] ?? '');
        $description = trim($_POST['description'] ?? '');
        if (strlen($titre) < 2) {
            $error = 'Titre trop court.';
        } elseif (strlen($description) < 5) {
            $error = 'Description trop courte.';
        } else {
            $stmt = $db->prepare("INSERT INTO admin_idees (admin_id, type, titre, description) VALUES (?, ?, ?, ?)");
            $stmt->execute([$adminId, $type, $titre, $description]);
            $ideeId = (int)$db->lastInsertId();

            $typeLabel = $type === 'bug' ? 'Bug' : 'Idée';
            $contenu = $typeLabel . " : " . $titre . "\n\n" . substr($description, 0, 500);
            $stmtInbox = $db->prepare("INSERT INTO admin_inbox (type, ref_id, titre, contenu) VALUES (?, ?, ?, ?)");
            $stmtInbox->execute([$type, $ideeId, $titre, $contenu]);

            // Push au super admin
            $super = $db->prepare("SELECT id FROM membres WHERE email = ? LIMIT 1");
            $super->execute([ADMIN_EMAIL]);
            $superId = $super->fetchColumn();
            if ($superId && function_exists('envoyerPush')) {
                envoyerPush((int)$superId, 'Nouvelle ' . $typeLabel . ' admin', $titre, '/admin/messagerie-interne.php', 'admin_idees');
            }
            $success = $typeLabel . ' enregistrée. Vous serez notifié si elle est acceptée.';
            header('Location: idees.php?ok=1');
            exit;
        }
    }

    if ($isSuperAdmin && $action === 'update_statut') {
        $ideeId = (int)($_POST['idee_id'] ?? 0);
        $statut = $_POST['statut'] ?? '';
        $allowed = ['en_attente', 'accepte', 'refuse', 'en_cours', 'termine'];
        if ($ideeId > 0 && in_array($statut, $allowed)) {
            $db->prepare("UPDATE admin_idees SET statut = ?, date_maj = NOW() WHERE id = ?")->execute([$statut, $ideeId]);
            $success = 'Statut mis à jour.';
        }
    }

    if ($isSuperAdmin && $action === 'update_progression') {
        $ideeId = (int)($_POST['idee_id'] ?? 0);
        $pct = (int)($_POST['progression_pct'] ?? 0);
        $pct = max(0, min(100, $pct));
        if ($ideeId > 0) {
            $db->prepare("UPDATE admin_idees SET progression_pct = ?, date_maj = NOW() WHERE id = ?")->execute([$pct, $ideeId]);
            $success = 'Progression mise à jour.';
        }
    }

    if ($isSuperAdmin && $action === 'update_notes') {
        $ideeId = (int)($_POST['idee_id'] ?? 0);
        $notes = trim($_POST['notes_super'] ?? '');
        if ($ideeId > 0) {
            $db->prepare("UPDATE admin_idees SET notes_super = ?, date_maj = NOW() WHERE id = ?")->execute([$notes, $ideeId]);
            $success = 'Notes enregistrées.';
        }
    }
}

if (isset($_GET['ok'])) $success = 'Idée/Bug enregistré. Vous serez notifié si elle est acceptée.';

// Mes soumissions (pour tous les admins)
$mesIdees = $db->prepare("SELECT * FROM admin_idees WHERE admin_id = ? ORDER BY date_creation DESC");
$mesIdees->execute([$adminId]);
$mesIdees = $mesIdees->fetchAll(PDO::FETCH_ASSOC);

// Toutes les idées (super admin uniquement)
$toutesIdees = [];
if ($isSuperAdmin) {
    $toutesIdees = $db->query("
        SELECT i.*, m.nom as admin_nom
        FROM admin_idees i
        LEFT JOIN membres m ON m.id = i.admin_id
        ORDER BY i.date_creation DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
}

function statutLabel($s) {
    $l = ['en_attente' => 'En attente', 'accepte' => 'Accepté', 'refuse' => 'Refusé', 'en_cours' => 'En cours', 'termine' => 'Terminé'];
    return $l[$s] ?? $s;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Idées & Bugs — Admin StratEdge</title>
  <link rel="icon" type="image/png" href="../assets/images/mascotte.png">
  <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@400;500;600;700&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
  <style>
    :root{--bg-dark:#050810;--bg-card:#0d1220;--neon-green:#ff2d78;--neon-green-dim:#d6245f;--neon-blue:#00d4ff;--text-primary:#f0f4f8;--text-secondary:#b0bec9;--text-muted:#8a9bb0;--border-subtle:rgba(255,45,120,0.12);}
    *{margin:0;padding:0;box-sizing:border-box;}
    body{font-family:'Rajdhani',sans-serif;background:var(--bg-dark);color:var(--text-primary);min-height:100vh;display:flex;}
    .main{margin-left:240px;flex:1;padding:2rem;min-height:100vh;}
    .page-header{margin-bottom:2rem;}
    .page-header h1{font-family:'Orbitron',sans-serif;font-size:1.5rem;font-weight:700;}
    .page-header p{color:var(--text-muted);margin-top:0.3rem;}
    .alert{padding:0.75rem 1rem;border-radius:10px;margin-bottom:1.5rem;font-size:0.9rem;}
    .alert-success{background:rgba(0,200,100,0.1);border:1px solid rgba(0,200,100,0.25);color:#00c864;}
    .alert-error{background:rgba(255,45,120,0.1);border:1px solid rgba(255,45,120,0.25);color:#ff6b9d;}
    .card{background:var(--bg-card);border:1px solid var(--border-subtle);border-radius:14px;padding:1.5rem;margin-bottom:1.5rem;}
    .card h2{font-family:'Orbitron',sans-serif;font-size:1rem;margin-bottom:1rem;display:flex;align-items:center;gap:0.5rem;}
    .form-group{margin-bottom:1rem;}
    .form-group label{display:block;font-size:0.8rem;color:var(--text-muted);margin-bottom:0.35rem;}
    .form-group input,.form-group select,.form-group textarea{width:100%;background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1);border-radius:8px;padding:0.65rem 1rem;color:var(--text-primary);font-family:'Rajdhani',sans-serif;font-size:0.95rem;}
    .form-group select option{background:var(--bg-card);color:var(--text-primary);}
    .form-group input::placeholder,.form-group textarea::placeholder{color:var(--text-muted);opacity:0.9;}
    .form-group textarea{min-height:120px;resize:vertical;}
    .form-group input:focus,.form-group textarea:focus,.form-group select:focus{outline:none;border-color:var(--neon-green);}
    .btn{display:inline-flex;align-items:center;gap:0.5rem;padding:0.7rem 1.4rem;border-radius:10px;font-family:'Rajdhani',sans-serif;font-weight:700;font-size:0.95rem;cursor:pointer;border:none;text-decoration:none;transition:all 0.2s;}
    .btn-primary{background:linear-gradient(135deg,var(--neon-green),var(--neon-green-dim));color:#fff;}
    .btn-primary:hover{box-shadow:0 0 20px rgba(255,45,120,0.4);}
    .btn-secondary{background:rgba(0,212,255,0.15);border:1px solid rgba(0,212,255,0.35);color:#00d4ff;}
    .btn-secondary:hover{background:rgba(0,212,255,0.25);}
    .idee-item{background:rgba(255,255,255,0.03);border:1px solid var(--border-subtle);border-radius:12px;padding:1.2rem;margin-bottom:1rem;}
    .idee-item.accepte{border-color:rgba(0,212,106,0.3);}
    .idee-item.refuse{border-color:rgba(255,100,100,0.2);}
    .idee-header{display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;flex-wrap:wrap;}
    .idee-titre{font-weight:700;font-size:1.05rem;}
    .badge{display:inline-block;padding:0.2rem 0.6rem;border-radius:20px;font-size:0.7rem;font-weight:700;}
    .badge-idee{background:rgba(0,212,255,0.15);color:#00d4ff;}
    .badge-bug{background:rgba(255,165,0,0.2);color:#ffa500;}
    .badge-accepte{background:rgba(0,212,106,0.2);color:#00d46a;}
    .badge-refuse{background:rgba(255,100,100,0.15);color:#ff6b6b;}
    .badge-en_cours{background:rgba(245,200,66,0.2);color:#f5c842;}
    .badge-termine{background:rgba(0,200,150,0.2);color:#00c896;}
    .progress-wrap{margin-top:0.75rem;}
    .progress-bar{height:8px;background:rgba(255,255,255,0.08);border-radius:4px;overflow:hidden;}
    .progress-fill{height:100%;background:linear-gradient(90deg,var(--neon-green),#00d46a);border-radius:4px;transition:width 0.3s;}
    .progress-label{font-size:0.75rem;color:var(--text-muted);margin-top:0.25rem;}
    .idee-desc{color:var(--text-secondary);font-size:0.9rem;margin-top:0.5rem;white-space:pre-wrap;}
    .idee-meta{font-size:0.78rem;color:var(--text-muted);margin-top:0.5rem;}
    .super-actions{display:flex;flex-wrap:wrap;gap:0.5rem;margin-top:0.75rem;align-items:center;}
    .super-actions select,.super-actions input[type="number"]{padding:0.4rem 0.6rem;border-radius:6px;border:1px solid var(--border-subtle);background:rgba(255,255,255,0.05);color:var(--text-primary);font-size:0.85rem;}
    .super-actions input[type="number"]{width:70px;}
    .two-cols{display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;}
    @media(max-width:900px){.two-cols{grid-template-columns:1fr;} .main{margin-left:0;padding-top:68px;}}
  </style>
</head>
<body>
<?php require_once __DIR__ . '/sidebar.php'; ?>
<div class="main">
  <div class="page-header">
    <h1>💡 Idées & Bugs</h1>
    <p>Soumettez une idée ou signalez un bug. Suivi de vos projets ci-dessous.</p>
  </div>

  <?php if ($success): ?>
    <div class="alert alert-success">✓ <?= htmlspecialchars($success) ?></div>
  <?php endif; ?>
  <?php if ($error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <div class="card">
    <h2>➕ Nouvelle idée ou bug</h2>
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
      <input type="hidden" name="action" value="submit">
      <div class="form-group">
        <label>Type</label>
        <select name="type" required>
          <option value="idee">💡 Idée</option>
          <option value="bug">🐛 Bug</option>
        </select>
      </div>
      <div class="form-group">
        <label>Titre</label>
        <input type="text" name="titre" required maxlength="255" placeholder="Court résumé">
      </div>
      <div class="form-group">
        <label>Description</label>
        <textarea name="description" required placeholder="Détaillez votre idée ou les étapes pour reproduire le bug…"></textarea>
      </div>
      <button type="submit" class="btn btn-primary">Envoyer</button>
    </form>
  </div>

  <div class="card">
    <h2>📋 Mes projets</h2>
    <?php if (empty($mesIdees)): ?>
      <p style="color:var(--text-muted);">Aucune idée ou bug soumis pour l’instant.</p>
    <?php else: ?>
      <?php foreach ($mesIdees as $i): ?>
        <div class="idee-item <?= in_array($i['statut'], ['accepte','en_cours','termine']) ? 'accepte' : ($i['statut']==='refuse' ? 'refuse' : '') ?>">
          <div class="idee-header">
            <div>
              <span class="badge badge-<?= $i['type'] ?>"><?= $i['type'] === 'bug' ? '🐛 Bug' : '💡 Idée' ?></span>
              <?php if (in_array($i['statut'], ['accepte','en_cours','termine'])): ?>
                <span class="badge badge-accepte" style="margin-left:0.5rem;">✓ Accepté</span>
              <?php endif; ?>
              <span class="badge badge-<?= $i['statut'] ?>" style="margin-left:0.35rem;"><?= statutLabel($i['statut']) ?></span>
            </div>
          </div>
          <div class="idee-titre"><?= htmlspecialchars($i['titre']) ?></div>
          <div class="idee-desc"><?= nl2br(htmlspecialchars(substr($i['description'], 0, 400))) ?><?= strlen($i['description'])>400 ? '…' : '' ?></div>
          <div class="progress-wrap">
            <div class="progress-bar"><div class="progress-fill" style="width:<?= (int)$i['progression_pct'] ?>%"></div></div>
            <div class="progress-label">Progression : <?= (int)$i['progression_pct'] ?>%</div>
          </div>
          <div class="idee-meta">Créé le <?= date('d/m/Y à H:i', strtotime($i['date_creation'])) ?></div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <?php if ($isSuperAdmin && !empty($toutesIdees)): ?>
  <div class="card">
    <h2>👑 Toutes les idées / bugs (gestion)</h2>
    <p style="color:var(--text-muted);font-size:0.88rem;margin-bottom:1rem;">
      <a href="messagerie-interne.php" class="btn btn-secondary" style="padding:0.5rem 1rem;font-size:0.85rem;">📥 Messagerie interne</a>
    </p>
    <?php foreach ($toutesIdees as $i): ?>
      <div class="idee-item">
        <div class="idee-header">
          <div>
            <span class="badge badge-<?= $i['type'] ?>"><?= $i['type'] === 'bug' ? '🐛 Bug' : '💡 Idée' ?></span>
            <span class="badge badge-<?= $i['statut'] ?>"><?= statutLabel($i['statut']) ?></span>
          </div>
          <span style="font-size:0.8rem;color:var(--text-muted);"><?= htmlspecialchars($i['admin_nom'] ?? '') ?></span>
        </div>
        <div class="idee-titre"><?= htmlspecialchars($i['titre']) ?></div>
        <div class="idee-desc"><?= nl2br(htmlspecialchars(substr($i['description'], 0, 300))) ?>…</div>
        <div class="progress-wrap">
          <div class="progress-bar"><div class="progress-fill" style="width:<?= (int)$i['progression_pct'] ?>%"></div></div>
        </div>
        <div class="super-actions">
          <form method="POST" style="display:inline;">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <input type="hidden" name="action" value="update_statut">
            <input type="hidden" name="idee_id" value="<?= $i['id'] ?>">
            <select name="statut" onchange="this.form.submit()">
              <?php foreach (['en_attente','accepte','refuse','en_cours','termine'] as $s): ?>
                <option value="<?= $s ?>" <?= $i['statut']===$s ? 'selected' : '' ?>><?= statutLabel($s) ?></option>
              <?php endforeach; ?>
            </select>
          </form>
          <form method="POST" style="display:inline;">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <input type="hidden" name="action" value="update_progression">
            <input type="hidden" name="idee_id" value="<?= $i['id'] ?>">
            <input type="number" name="progression_pct" min="0" max="100" value="<?= (int)$i['progression_pct'] ?>" style="width:60px;">
            <button type="submit" class="btn btn-secondary" style="padding:0.35rem 0.7rem;font-size:0.8rem;">%</button>
          </form>
          <form method="POST" style="display:inline;">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <input type="hidden" name="action" value="update_notes">
            <input type="hidden" name="idee_id" value="<?= $i['id'] ?>">
            <input type="text" name="notes_super" placeholder="Notes" value="<?= htmlspecialchars($i['notes_super'] ?? '') ?>" style="min-width:180px;">
            <button type="submit" class="btn btn-secondary" style="padding:0.35rem 0.7rem;font-size:0.8rem;">OK</button>
          </form>
        </div>
        <?php if (!empty($i['notes_super'])): ?>
          <div style="font-size:0.82rem;color:var(--text-muted);margin-top:0.5rem;">Notes : <?= nl2br(htmlspecialchars($i['notes_super'])) ?></div>
        <?php endif; ?>
        <div class="idee-meta">Créé le <?= date('d/m/Y à H:i', strtotime($i['date_creation'])) ?></div>
      </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
</body>
</html>
