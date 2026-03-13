<?php
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
$pageActive = 'admins';
$db = getDB();

// ── Seul le super-admin (email principal) peut gérer les rôles ──
$isSuperAdmin = ($_SESSION['membre_email'] === ADMIN_EMAIL);

$success = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf($_POST['csrf_token'] ?? '')) {
    if (!$isSuperAdmin) {
        $error = 'Seul le super-admin peut modifier les rôles.';
    } else {
        $action    = $_POST['action'] ?? '';
        $membreId  = (int)($_POST['membre_id'] ?? 0);

        // Ne pas modifier son propre compte
        if ($membreId === (int)$_SESSION['membre_id']) {
            $error = 'Vous ne pouvez pas modifier votre propre rôle.';
        } elseif ($action === 'promote' && $membreId > 0) {
            $newRole = $_POST['new_role'] ?? 'admin';
            $allowed = ['admin', 'admin_tennis', 'admin_fun', 'admin_fun_sport'];
            if (!in_array($newRole, $allowed)) $newRole = 'admin';
            $stmt = $db->prepare("UPDATE membres SET role = ? WHERE id = ?");
            $stmt->execute([$newRole, $membreId]);
            if ($stmt->rowCount() > 0) {
                $success = 'Membre promu avec le rôle : ' . $newRole;
            } else {
                $error = 'Aucune ligne modifiée. Vérifie que la colonne "role" existe (VARCHAR 30) et que l\'id est valide.';
            }
        } elseif ($action === 'demote' && $membreId > 0) {
            $db->prepare("UPDATE membres SET role = 'user' WHERE id = ?")->execute([$membreId]);
            $success = 'Droits administrateur retirés.';
        }
    }
}

// Récupérer les admins et les membres normaux (inclure role NULL dans les membres)
$adminRoles = ['admin', 'admin_tennis', 'admin_fun', 'admin_fun_sport'];
$admins  = $db->query("SELECT id, nom, email, date_inscription, role FROM membres WHERE role IN ('admin','admin_tennis','admin_fun','admin_fun_sport') ORDER BY nom")->fetchAll();
$membres = $db->query("SELECT id, nom, email, date_inscription FROM membres WHERE (role IS NULL OR role NOT IN ('admin','admin_tennis','admin_fun','admin_fun_sport')) AND actif = 1 AND email != '" . $db->quote(ADMIN_EMAIL) . "' ORDER BY nom")->fetchAll();
$roleLabels = ['admin' => 'Admin', 'admin_tennis' => 'Admin Tennis', 'admin_fun' => 'Admin Fun', 'admin_fun_sport' => 'Admin Fun Sport'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>👑 Gestion Admins — Panel StratEdge</title>
  <link rel="icon" type="image/png" href="../assets/images/mascotte.png">
  <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@400;500;600;700&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
  <style>
    :root{--bg-dark:#050810;--bg-card:#0d1220;--neon-green:#ff2d78;--neon-green-dim:#d6245f;--neon-blue:#00d4ff;--gold:#f59e0b;--text-primary:#f0f4f8;--text-secondary:#b0bec9;--text-muted:#8a9bb0;--border-subtle:rgba(255,45,120,0.12);--glow-green:0 0 20px rgba(255,45,120,0.3);}
    *{margin:0;padding:0;box-sizing:border-box;}
    body{font-family:'Rajdhani',sans-serif;background:var(--bg-dark);color:var(--text-primary);min-height:100vh;display:flex;}
    .main{margin-left:240px;flex:1;padding:2rem;min-height:100vh;}
    .topbar{margin-bottom:2rem;}
    .topbar h1{font-family:'Orbitron',sans-serif;font-size:1.3rem;font-weight:700;}
    .topbar p{color:var(--text-muted);font-size:0.88rem;margin-top:0.3rem;}

    .alert{padding:0.75rem 1.1rem;border-radius:10px;font-size:0.88rem;margin-bottom:1.5rem;}
    .alert-success{background:rgba(0,200,100,0.08);border:1px solid rgba(0,200,100,0.2);color:#00c864;}
    .alert-error{background:rgba(255,45,120,0.08);border:1px solid rgba(255,45,120,0.25);color:#ff6b9d;}
    .alert-warn{background:rgba(245,158,11,0.08);border:1px solid rgba(245,158,11,0.25);color:#f59e0b;margin-bottom:2rem;}

    /* Cards */
    .section-title{font-family:'Orbitron',sans-serif;font-size:0.75rem;letter-spacing:3px;text-transform:uppercase;color:var(--text-muted);margin-bottom:1rem;display:flex;align-items:center;gap:0.6rem;}
    .section-title::after{content:'';flex:1;height:1px;background:var(--border-subtle);}

    .admins-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:1rem;margin-bottom:2.5rem;}
    .admin-card{background:var(--bg-card);border:1px solid rgba(245,158,11,0.25);border-radius:14px;padding:1.4rem;display:flex;flex-direction:column;gap:1rem;position:relative;overflow:hidden;}
    .admin-card::before{content:'';position:absolute;top:0;left:0;right:0;height:2px;background:linear-gradient(90deg,var(--gold),transparent);}
    .admin-card .badge-admin{display:inline-flex;align-items:center;gap:0.4rem;background:rgba(245,158,11,0.1);border:1px solid rgba(245,158,11,0.3);color:var(--gold);font-family:'Orbitron',sans-serif;font-size:0.6rem;letter-spacing:2px;text-transform:uppercase;padding:0.25rem 0.7rem;border-radius:20px;width:fit-content;}
    .admin-info h3{font-size:1rem;font-weight:700;color:var(--text-primary);margin-bottom:0.25rem;}
    .admin-info p{font-size:0.82rem;color:var(--text-muted);}
    .admin-avatar{width:44px;height:44px;border-radius:50%;background:linear-gradient(135deg,var(--gold),#d97706);display:flex;align-items:center;justify-content:center;font-weight:900;font-size:1rem;color:#050810;font-family:'Orbitron',sans-serif;flex-shrink:0;}
    .admin-card-top{display:flex;align-items:center;gap:1rem;}
    .super-badge{background:rgba(255,45,120,0.1);border:1px solid rgba(255,45,120,0.3);color:#ff6b9d;font-family:'Orbitron',sans-serif;font-size:0.58rem;letter-spacing:2px;text-transform:uppercase;padding:0.2rem 0.6rem;border-radius:20px;width:fit-content;margin-top:0.3rem;}
    .btn-demote{width:100%;padding:0.6rem;background:rgba(255,45,120,0.08);border:1px solid rgba(255,45,120,0.25);color:#ff6b9d;border-radius:8px;font-family:'Rajdhani',sans-serif;font-weight:700;font-size:0.85rem;cursor:pointer;transition:all 0.2s;display:flex;align-items:center;justify-content:center;gap:0.5rem;}
    .btn-demote:hover{background:rgba(255,45,120,0.18);border-color:rgba(255,45,120,0.5);}

    /* Table membres */
    .members-table{background:var(--bg-card);border:1px solid var(--border-subtle);border-radius:14px;overflow:hidden;margin-bottom:2.5rem;}
    .table-search{padding:1rem 1.2rem;border-bottom:1px solid var(--border-subtle);}
    .table-search input{width:100%;background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.1);border-radius:8px;padding:0.6rem 1rem;color:var(--text-primary);font-family:'Rajdhani',sans-serif;font-size:0.9rem;outline:none;transition:border 0.2s;}
    .table-search input:focus{border-color:var(--neon-green);}
    table{width:100%;border-collapse:collapse;}
    thead th{padding:0.75rem 1.2rem;text-align:left;font-family:'Space Mono',monospace;font-size:0.6rem;letter-spacing:2px;text-transform:uppercase;color:var(--text-muted);border-bottom:1px solid var(--border-subtle);background:rgba(255,255,255,0.02);}
    tbody tr{border-bottom:1px solid rgba(255,255,255,0.03);transition:background 0.2s;}
    tbody tr:last-child{border-bottom:none;}
    tbody tr:hover{background:rgba(255,45,120,0.03);}
    td{padding:0.85rem 1.2rem;font-size:0.88rem;color:var(--text-secondary);}
    td.nom{color:var(--text-primary);font-weight:600;}
    .avatar-small{width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg,#ff2d78,#00d4ff);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:0.75rem;color:#050810;font-family:'Orbitron',sans-serif;flex-shrink:0;}
    td.avatar-cell{display:flex;align-items:center;gap:0.75rem;}
    .btn-promote{background:linear-gradient(135deg,rgba(245,158,11,0.15),rgba(245,158,11,0.08));border:1px solid rgba(245,158,11,0.35);color:var(--gold);padding:0.4rem 1rem;border-radius:7px;font-family:'Rajdhani',sans-serif;font-weight:700;font-size:0.82rem;cursor:pointer;transition:all 0.2s;display:inline-flex;align-items:center;gap:0.4rem;}
    .btn-promote:hover{background:rgba(245,158,11,0.25);border-color:rgba(245,158,11,0.6);}
    .disabled-btn{opacity:0.35;cursor:not-allowed;pointer-events:none;}
    .no-access{opacity:0.5;font-style:italic;font-size:0.8rem;}
  </style>
</head>
<body>
<?php require_once __DIR__ . '/sidebar.php'; ?>
<div class="main">

  <div class="topbar">
    <h1>👑 Gestion des Administrateurs</h1>
    <p>Promouvoir ou rétrograder des membres au rang admin</p>
  </div>

  <?php if ($success): ?>
    <div class="alert alert-success">✓ <?= htmlspecialchars($success) ?></div>
  <?php endif; ?>
  <?php if ($error): ?>
    <div class="alert alert-error">✗ <?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <?php if (!$isSuperAdmin): ?>
    <div class="alert alert-warn">
      👁️ <strong>Mode lecture seule</strong> — Vous pouvez voir la liste des admins mais seul le super-admin (propriétaire du site) peut modifier les rôles.
    </div>
  <?php endif; ?>

  <!-- ── Admins actuels ── -->
  <div class="section-title">👑 Administrateurs actuels (<?= count($admins) + 1 ?>)</div>
  <div class="admins-grid">

    <!-- Toi — Super Admin fixe -->
    <div class="admin-card">
      <div class="admin-card-top">
        <div class="admin-avatar">S</div>
        <div class="admin-info">
          <h3>Super Admin</h3>
          <p><?= htmlspecialchars(ADMIN_EMAIL) ?></p>
          <div class="super-badge">⭐ PROPRIÉTAIRE</div>
        </div>
      </div>
      <div class="badge-admin">👑 Super Admin</div>
    </div>

    <!-- Admins promus -->
    <?php foreach ($admins as $a): ?>
    <div class="admin-card">
      <div class="admin-card-top">
        <div class="admin-avatar"><?= strtoupper(substr($a['nom'], 0, 1)) ?></div>
        <div class="admin-info">
          <h3><?= htmlspecialchars($a['nom']) ?></h3>
          <p><?= htmlspecialchars($a['email']) ?></p>
          <p style="font-size:0.75rem;color:var(--text-muted);">Depuis le <?= date('d/m/Y', strtotime($a['date_inscription'])) ?></p>
        </div>
      </div>
      <div class="badge-admin">👑 <?= htmlspecialchars($roleLabels[$a['role']] ?? 'Admin') ?></div>
      <?php if ($isSuperAdmin): ?>
      <form method="POST" onsubmit="return confirm('Retirer les droits admin à <?= htmlspecialchars($a['nom']) ?> ?')">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <input type="hidden" name="action" value="demote">
        <input type="hidden" name="membre_id" value="<?= $a['id'] ?>">
        <button type="submit" class="btn-demote">✕ Retirer les droits admin</button>
      </form>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>

    <?php if (empty($admins)): ?>
    <div style="color:var(--text-muted);font-size:0.88rem;padding:1rem;grid-column:1/-1;">Aucun admin promu pour l'instant.</div>
    <?php endif; ?>

  </div>

  <!-- ── Liste membres à promouvoir ── -->
  <div class="section-title">👥 Membres (<?= count($membres) ?>)</div>
  <div class="members-table">
    <div class="table-search">
      <input type="text" id="searchInput" placeholder="🔍 Rechercher un membre…" oninput="filterTable(this.value)">
    </div>
    <table>
      <thead>
        <tr>
          <th>Membre</th>
          <th>Email</th>
          <th>Inscription</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody id="membresTable">
        <?php foreach ($membres as $m): ?>
        <tr data-nom="<?= strtolower(htmlspecialchars($m['nom'])) ?>" data-email="<?= strtolower(htmlspecialchars($m['email'])) ?>">
          <td class="avatar-cell nom">
            <div class="avatar-small"><?= strtoupper(substr($m['nom'], 0, 1)) ?></div>
            <?= htmlspecialchars($m['nom']) ?>
          </td>
          <td><?= htmlspecialchars($m['email']) ?></td>
          <td><?= date('d/m/Y', strtotime($m['date_inscription'])) ?></td>
          <td>
            <?php if ($isSuperAdmin): ?>
            <form method="POST" style="display:inline-flex;align-items:center;gap:0.5rem;" onsubmit="return confirm('Promouvoir <?= htmlspecialchars($m['nom']) ?> ?')">
              <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
              <input type="hidden" name="action" value="promote">
              <input type="hidden" name="membre_id" value="<?= $m['id'] ?>">
              <select name="new_role" style="background:rgba(255,255,255,0.06);border:1px solid var(--border-subtle);color:var(--text-primary);padding:0.35rem 0.5rem;border-radius:6px;font-family:'Rajdhani',sans-serif;font-size:0.82rem;">
                <option value="admin">Admin</option>
                <option value="admin_tennis">Admin Tennis</option>
                <option value="admin_fun">Admin Fun</option>
                <option value="admin_fun_sport">Admin Fun Sport</option>
              </select>
              <button type="submit" class="btn-promote">👑 Promouvoir</button>
            </form>
            <?php else: ?>
            <span class="no-access">Non autorisé</span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($membres)): ?>
        <tr><td colspan="4" style="text-align:center;color:var(--text-muted);padding:2rem;">Aucun membre</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

</div>

<script>
function filterTable(q) {
  q = q.toLowerCase();
  document.querySelectorAll('#membresTable tr[data-nom]').forEach(function(tr) {
    const match = tr.dataset.nom.includes(q) || tr.dataset.email.includes(q);
    tr.style.display = match ? '' : 'none';
  });
}
</script>
</body>
</html>
