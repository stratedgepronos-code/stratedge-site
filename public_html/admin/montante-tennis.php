<?php
// ============================================================
// STRATEDGE — Admin Montante Tennis
// Créer/gérer une montante, ajouter des étapes, définir les résultats
// ============================================================
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/montante-notifications.php';
requireAdmin();
$pageActive = 'montante-tennis';
$db = getDB();

$adminRole = getAdminRole();
if (in_array($adminRole, ['admin_fun', 'admin_fun_sport'])) {
    header('Location: /panel-x9k3m/index.php');
    exit;
}

$success = '';
$error = '';

// Auto-création tables
try {
    $db->query("SELECT 1 FROM montante_config LIMIT 1");
} catch (Throwable $e) {
    if (strpos($e->getMessage(), "doesn't exist") !== false || strpos($e->getMessage(), 'montante_config') !== false) {
        $db->exec("CREATE TABLE IF NOT EXISTS `montante_config` (
          `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          `nom` VARCHAR(120) NOT NULL DEFAULT 'Montante Tennis',
          `bankroll_initial` DECIMAL(10,2) NOT NULL DEFAULT 100.00,
          `mise_depart` DECIMAL(10,2) NOT NULL DEFAULT 10.00,
          `statut` ENUM('active','pause','terminee') DEFAULT 'active',
          `date_debut` DATE DEFAULT NULL,
          `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $db->exec("CREATE TABLE IF NOT EXISTS `montante_steps` (
          `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          `montante_id` INT UNSIGNED NOT NULL DEFAULT 1,
          `step_number` INT UNSIGNED NOT NULL,
          `match_desc` VARCHAR(255) NOT NULL,
          `competition` VARCHAR(120) DEFAULT NULL,
          `cote` DECIMAL(10,2) NOT NULL,
          `mise` DECIMAL(10,2) NOT NULL,
          `resultat` ENUM('en_cours','gagne','perdu','annule') DEFAULT 'en_cours',
          `gain_perte` DECIMAL(10,2) DEFAULT NULL,
          `bankroll_apres` DECIMAL(10,2) DEFAULT NULL,
          `date_match` DATE DEFAULT NULL,
          `heure` VARCHAR(20) DEFAULT NULL,
          `analyse` TEXT DEFAULT NULL,
          `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } else {
        throw $e;
    }
}

// POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $error = 'Erreur de sécurité.';

    } elseif ($_POST['action'] === 'create_montante') {
        $nom = trim($_POST['nom'] ?? 'Montante Tennis');
        $bankroll = max(1, (float)($_POST['bankroll_initial'] ?? 100));
        $miseDepart = max(1, (float)($_POST['mise_depart'] ?? 10));
        $dateDebut = $_POST['date_debut'] ?? date('Y-m-d');
        $db->exec("UPDATE montante_config SET statut = 'terminee' WHERE statut IN ('active','pause')");
        $stmt = $db->prepare("INSERT INTO montante_config (nom, bankroll_initial, mise_depart, statut, date_debut) VALUES (?, ?, ?, 'active', ?)");
        $stmt->execute([$nom, $bankroll, $miseDepart, $dateDebut]);
        $newId = (int)$db->lastInsertId();
        $success = 'Nouvelle montante créée : ' . clean($nom);
        if ($newId) {
            $newConfig = $db->prepare("SELECT * FROM montante_config WHERE id = ?");
            $newConfig->execute([$newId]);
            $newConfigRow = $newConfig->fetch(PDO::FETCH_ASSOC);
            if ($newConfigRow) {
                notifyMontanteDemarrage($newConfigRow);
            }
        }

    } elseif ($_POST['action'] === 'toggle_statut') {
        $montanteId = (int)($_POST['montante_id'] ?? 0);
        $newStatut = $_POST['new_statut'] ?? '';
        if ($montanteId && in_array($newStatut, ['active','pause','terminee'])) {
            $db->prepare("UPDATE montante_config SET statut = ? WHERE id = ?")->execute([$newStatut, $montanteId]);
            $success = 'Statut mis à jour.';
        }

    } elseif ($_POST['action'] === 'add_step') {
        $montanteId = (int)($_POST['montante_id'] ?? 0);
        $matchDesc = trim($_POST['match_desc'] ?? '');
        $competition = trim($_POST['competition'] ?? '');
        $cote = max(1.01, (float)($_POST['cote'] ?? 1.5));
        $mise = max(0.5, (float)($_POST['mise'] ?? 10));
        $dateMatch = $_POST['date_match'] ?? null;
        $heure = trim($_POST['heure'] ?? '');
        $analyse = trim($_POST['analyse'] ?? '');

        if ($montanteId && $matchDesc !== '') {
            $maxStep = (int)$db->prepare("SELECT COALESCE(MAX(step_number),0) FROM montante_steps WHERE montante_id = ?");
            $stmtMax = $db->prepare("SELECT COALESCE(MAX(step_number),0) FROM montante_steps WHERE montante_id = ?");
            $stmtMax->execute([$montanteId]);
            $nextStep = (int)$stmtMax->fetchColumn() + 1;

            $stmt = $db->prepare("INSERT INTO montante_steps (montante_id, step_number, match_desc, competition, cote, mise, date_match, heure, analyse) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$montanteId, $nextStep, $matchDesc, $competition ?: null, $cote, $mise, $dateMatch ?: null, $heure ?: null, $analyse ?: null]);
            $stepId = (int)$db->lastInsertId();
            $success = 'Étape ' . $nextStep . ' ajoutée : ' . clean($matchDesc);
            if ($stepId && $config = $db->query("SELECT * FROM montante_config WHERE id = " . (int)$montanteId)->fetch(PDO::FETCH_ASSOC)) {
                $newStep = $db->prepare("SELECT * FROM montante_steps WHERE id = ?");
                $newStep->execute([$stepId]);
                $newStepRow = $newStep->fetch(PDO::FETCH_ASSOC);
                if ($newStepRow) {
                    notifyMontanteNouvelleEtape($newStepRow, $config);
                }
            }
        } else {
            $error = 'Match requis.';
        }

    } elseif ($_POST['action'] === 'set_resultat') {
        $stepId = (int)($_POST['step_id'] ?? 0);
        $resultat = $_POST['resultat'] ?? '';

        if ($stepId && in_array($resultat, ['gagne','perdu','annule'])) {
            $step = $db->prepare("SELECT * FROM montante_steps WHERE id = ?");
            $step->execute([$stepId]);
            $s = $step->fetch();

            if ($s) {
                $config = $db->prepare("SELECT * FROM montante_config WHERE id = ?");
                $config->execute([$s['montante_id']]);
                $mc = $config->fetch();

                $previousSteps = $db->prepare("SELECT * FROM montante_steps WHERE montante_id = ? AND step_number < ? ORDER BY step_number DESC LIMIT 1");
                $previousSteps->execute([$s['montante_id'], $s['step_number']]);
                $prev = $previousSteps->fetch();
                $bankrollAvant = $prev ? (float)$prev['bankroll_apres'] : (float)$mc['bankroll_initial'];

                $gainPerte = 0;
                if ($resultat === 'gagne') {
                    $gainPerte = round((float)$s['mise'] * ((float)$s['cote'] - 1), 2);
                } elseif ($resultat === 'perdu') {
                    $gainPerte = -1 * (float)$s['mise'];
                }
                $bankrollApres = round($bankrollAvant + $gainPerte, 2);

                $db->prepare("UPDATE montante_steps SET resultat = ?, gain_perte = ?, bankroll_apres = ? WHERE id = ?")
                   ->execute([$resultat, $gainPerte, $bankrollApres, $stepId]);
                $success = 'Résultat enregistré. ' . ($gainPerte >= 0 ? '+' : '') . number_format($gainPerte, 2) . '€';
                $s['resultat'] = $resultat;
                $s['gain_perte'] = $gainPerte;
                $s['bankroll_apres'] = $bankrollApres;
                notifyMontanteResultat($s, $mc, $resultat);
            }
        } else {
            $error = 'Résultat invalide.';
        }
    } elseif ($_POST['action'] === 'delete_montante') {
        $montanteId = (int)($_POST['montante_id'] ?? 0);
        if ($montanteId) {
            $db->prepare("DELETE FROM montante_steps WHERE montante_id = ?")->execute([$montanteId]);
            $db->prepare("DELETE FROM montante_config WHERE id = ?")->execute([$montanteId]);
            $success = 'Montante supprimée.';
        }
    } elseif ($_POST['action'] === 'edit_step') {
        $stepId = (int)($_POST['step_id'] ?? 0);
        if ($stepId) {
            $matchDesc = trim($_POST['match_desc'] ?? '');
            $competition = trim($_POST['competition'] ?? '');
            $cote = max(1.01, (float)($_POST['cote'] ?? 1.5));
            $mise = max(0.5, (float)($_POST['mise'] ?? 10));
            $dateMatch = !empty($_POST['date_match']) ? $_POST['date_match'] : null;
            $heure = trim($_POST['heure'] ?? '');
            $analyse = trim($_POST['analyse'] ?? '');

            if ($matchDesc !== '') {
                $db->prepare("UPDATE montante_steps SET match_desc = ?, competition = ?, cote = ?, mise = ?, date_match = ?, heure = ?, analyse = ? WHERE id = ?")
                   ->execute([$matchDesc, $competition ?: null, $cote, $mise, $dateMatch ?: null, $heure ?: null, $analyse ?: null, $stepId]);
                header('Location: ' . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) . '?success_edit=1');
                exit;
            } else {
                $error = 'Match requis.';
            }
        }
    }
}

if (isset($_GET['success_edit'])) {
    $success = 'Étape mise à jour.';
}
$config = $db->query("SELECT * FROM montante_config ORDER BY id DESC LIMIT 1")->fetch();
$steps = [];
if ($config) {
    $stmtSteps = $db->prepare("SELECT * FROM montante_steps WHERE montante_id = ? ORDER BY step_number DESC");
    $stmtSteps->execute([$config['id']]);
    $steps = $stmtSteps->fetchAll();
}
$toutesMontantes = $db->query("SELECT * FROM montante_config ORDER BY id DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Montante Tennis – Admin</title>
<link rel="icon" type="image/png" href="../assets/images/mascotte.png">
<link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@400;500;600;700&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
<?php require_once __DIR__ . '/sidebar.php'; ?>
<style>
:root{--bg-dark:#050810;--bg-card:#0d1220;--bg-card2:#111827;--neon-green:#ff2d78;--neon-green-dim:#d6245f;--neon-blue:#00d4ff;--text-primary:#f0f4f8;--text-secondary:#b0bec9;--text-muted:#8a9bb0;--border-subtle:rgba(255,45,120,0.12);}
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'Rajdhani',sans-serif;background:var(--bg-dark);color:var(--text-primary);min-height:100vh;}
.main{padding:1.5rem 2rem;}
.page-header{margin-bottom:1.5rem;}
.page-header h1{font-family:'Orbitron',sans-serif;font-size:1.4rem;}
.card{background:var(--bg-card);border:1px solid var(--border-subtle);border-radius:14px;padding:1.5rem;margin-bottom:1.5rem;}
.card h2{font-size:1rem;margin-bottom:1rem;display:flex;align-items:center;gap:0.5rem;}
.form-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:1rem;align-items:end;}
.form-group label{display:block;font-size:0.75rem;color:var(--text-muted);margin-bottom:0.35rem;}
.form-group input,.form-group select,.form-group textarea{width:100%;padding:0.6rem;background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.1);border-radius:8px;color:var(--text-primary);font-family:inherit;font-size:0.9rem;}
.form-group input:focus,.form-group select:focus,.form-group textarea:focus{outline:none;border-color:var(--neon-green);}
.form-group select option{background:#0d1220;}
.btn{padding:0.6rem 1.2rem;border-radius:8px;font-weight:700;cursor:pointer;border:none;font-size:0.9rem;}
.btn-pink{background:linear-gradient(135deg,var(--neon-green),var(--neon-green-dim));color:#fff;}
.btn-pink:hover{box-shadow:0 0 20px rgba(255,45,120,0.4);}
.btn-green{background:linear-gradient(135deg,#00d46a,#00a050);color:#fff;}
.btn-green:hover{box-shadow:0 0 20px rgba(0,212,106,0.4);}
.btn-sm{padding:0.3rem 0.7rem;border-radius:6px;font-size:0.8rem;font-weight:700;cursor:pointer;border:none;}
.table-wrap{overflow-x:auto;}
table{width:100%;border-collapse:collapse;}
th,td{padding:0.6rem 0.8rem;text-align:left;border-bottom:1px solid rgba(255,255,255,0.06);font-size:0.9rem;}
th{color:var(--text-muted);font-weight:600;font-size:0.7rem;letter-spacing:1px;text-transform:uppercase;}
.alert-success{background:rgba(0,212,106,0.1);border:1px solid rgba(0,212,106,0.3);color:#00c864;padding:0.8rem;border-radius:8px;margin-bottom:1rem;}
.alert-error{background:rgba(255,45,120,0.1);border:1px solid rgba(255,45,120,0.3);color:#ff6b9d;padding:0.8rem;border-radius:8px;margin-bottom:1rem;}
.status-badge{padding:0.25rem 0.7rem;border-radius:6px;font-size:0.75rem;font-weight:700;}
.status-active{background:rgba(0,212,106,0.12);color:#00c864;border:1px solid rgba(0,212,106,0.3);}
.status-pause{background:rgba(245,158,11,0.12);color:#f59e0b;border:1px solid rgba(245,158,11,0.3);}
.status-terminee{background:rgba(255,255,255,0.06);color:var(--text-muted);border:1px solid rgba(255,255,255,0.1);}
.profit-pos{color:#00c864;font-weight:700;}
.profit-neg{color:#ff4444;font-weight:700;}
.btn-delete{background:rgba(255,68,68,0.12);color:#ff4444;border:1px solid rgba(255,68,68,0.35);padding:0.35rem 0.75rem;border-radius:6px;font-size:0.8rem;font-weight:700;cursor:pointer;}
.btn-delete:hover{background:rgba(255,68,68,0.25);}
.montante-row{display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap;padding:0.75rem 0;border-bottom:1px solid rgba(255,255,255,0.06);}
.montante-row:last-child{border-bottom:none;}
/* Calendrier : styles de secours si le fichier /assets/ ne charge pas (FTP) */
.strateedge-date-wrap{position:relative;width:100%;min-height:42px;}
.strateedge-date-wrap .cal-icon{position:absolute;right:0.75rem;top:50%;transform:translateY(-50%);line-height:0;color:#ff2d78;}
.strateedge-date-wrap .cal-icon svg{display:block;width:20px!important;height:20px!important;max-width:20px;max-height:20px;}
.strateedge-date-wrap .cal-popover:not(.is-open):empty{display:none;}
</style>
<link href="/assets/css/calendar-strateedge.css" rel="stylesheet">
</head>
<body>

<div class="main">
  <div class="page-header">
    <h1>🎾 Montante Tennis — Admin</h1>
    <p style="color:var(--text-muted);font-size:0.9rem;">Crée une montante, ajoute les étapes et définis les résultats.</p>
  </div>

  <?php if ($success): ?><div class="alert-success">✅ <?= clean($success) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="alert-error">⚠️ <?= clean($error) ?></div><?php endif; ?>

  <!-- Créer ou gérer la montante -->
  <div class="card">
    <h2>🎾 <?= $config ? 'Montante en cours' : 'Créer une montante' ?></h2>
    <?php if ($config): ?>
    <div style="display:flex;align-items:center;gap:1rem;flex-wrap:wrap;margin-bottom:1rem;">
      <strong style="font-size:1.1rem;"><?= clean($config['nom']) ?></strong>
      <span class="status-badge status-<?= $config['statut'] ?>"><?= $config['statut'] === 'active' ? '🟢 Active' : ($config['statut'] === 'pause' ? '⏸ Pause' : '⬛ Terminée') ?></span>
      <span style="color:var(--text-muted);font-size:0.85rem;">Montant visé : <?= number_format((float)$config['bankroll_initial'], 2) ?>€ · Mise départ : <?= number_format((float)$config['mise_depart'], 2) ?>€</span>
    </div>
    <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
      <?php foreach (['active' => '▶ Activer', 'pause' => '⏸ Pause', 'terminee' => '⏹ Terminer'] as $st => $label): ?>
      <?php if ($config['statut'] !== $st): ?>
      <form method="post" style="display:inline;">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <input type="hidden" name="action" value="toggle_statut">
        <input type="hidden" name="montante_id" value="<?= (int)$config['id'] ?>">
        <input type="hidden" name="new_statut" value="<?= $st ?>">
        <button type="submit" class="btn-sm" style="background:rgba(255,255,255,0.06);color:var(--text-secondary);border:1px solid rgba(255,255,255,0.1);"><?= $label ?></button>
      </form>
      <?php endif; endforeach; ?>
    </div>
    <?php endif; ?>

    <details style="margin-top:1rem;" <?= $config ? '' : 'open' ?>>
      <summary style="cursor:pointer;color:var(--neon-blue);font-weight:600;font-size:0.9rem;">➕ Créer une nouvelle montante</summary>
      <form method="post" style="margin-top:1rem;">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <input type="hidden" name="action" value="create_montante">
        <div class="form-grid">
          <div class="form-group"><label>Nom</label><input type="text" name="nom" value="Montante Tennis" required></div>
          <div class="form-group"><label>Montant visé (€)</label><input type="number" name="bankroll_initial" value="100" step="0.01" min="1" required></div>
          <div class="form-group"><label>Mise de départ (€)</label><input type="number" name="mise_depart" value="10" step="0.01" min="0.5" required></div>
          <div class="form-group"><label>Date début</label>
            <div class="strateedge-date-wrap">
              <input type="text" class="strateedge-date-display" placeholder="jj/mm/aaaa" readonly>
              <input type="hidden" name="date_debut" value="<?= date('Y-m-d') ?>">
              <span class="cal-icon" aria-hidden="true"><svg width="20" height="20" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" focusable="false"><path fill="#ff2d78" d="M19 4h-1V2h-2v2H8V2H6v2H5c-1.11 0-1.99.9-1.99 2L3 20c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 16H5V9h14v11zM9 11H7v2h2v-2zm4 0h-2v2h2v-2zm4 0h-2v2h2v-2z"/></svg></span>
              <div class="cal-popover" role="dialog" aria-label="Choisir une date"></div>
            </div>
          </div>
          <div class="form-group"><button type="submit" class="btn btn-green">Créer</button></div>
        </div>
      </form>
    </details>
  </div>

  <?php if ($config && in_array($config['statut'], ['active','pause'])): ?>
  <!-- Ajouter une étape -->
  <div class="card">
    <h2>➕ Ajouter une étape</h2>
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
      <input type="hidden" name="action" value="add_step">
      <input type="hidden" name="montante_id" value="<?= (int)$config['id'] ?>">
      <div class="form-grid">
        <div class="form-group"><label>Match</label><input type="text" name="match_desc" placeholder="Ex: Djokovic vs Nadal" required></div>
        <div class="form-group"><label>Compétition</label><input type="text" name="competition" placeholder="Ex: Roland-Garros"></div>
        <div class="form-group"><label>Cote</label><input type="number" name="cote" step="0.01" min="1.01" value="1.50" required></div>
        <div class="form-group"><label>Mise (€)</label><input type="number" name="mise" step="0.01" min="0.5" value="<?= number_format((float)$config['mise_depart'], 2, '.', '') ?>" required></div>
        <div class="form-group"><label>Date du match</label>
          <div class="strateedge-date-wrap">
            <input type="text" class="strateedge-date-display" placeholder="jj/mm/aaaa" readonly>
            <input type="hidden" name="date_match" value="">
            <span class="cal-icon" aria-hidden="true"><svg width="20" height="20" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" focusable="false"><path fill="#ff2d78" d="M19 4h-1V2h-2v2H8V2H6v2H5c-1.11 0-1.99.9-1.99 2L3 20c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 16H5V9h14v11zM9 11H7v2h2v-2zm4 0h-2v2h2v-2zm4 0h-2v2h2v-2z"/></svg></span>
            <div class="cal-popover" role="dialog" aria-label="Choisir une date"></div>
          </div>
        </div>
        <div class="form-group"><label>Heure</label><input type="text" name="heure" placeholder="15:00"></div>
      </div>
      <div class="form-group" style="margin-top:1rem;">
        <label>Analyse / Commentaire (optionnel)</label>
        <textarea name="analyse" rows="3" placeholder="Ton analyse du match..." style="width:100%;background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.1);border-radius:8px;padding:0.6rem;color:var(--text-primary);font-family:inherit;resize:vertical;"></textarea>
      </div>
      <button type="submit" class="btn btn-pink" style="margin-top:1rem;">Ajouter l'étape</button>
    </form>
  </div>
  <?php endif; ?>

  <!-- Liste des étapes -->
  <?php if (!empty($steps)): ?>
  <?php
  $editStepId = isset($_GET['edit_step']) ? (int)$_GET['edit_step'] : 0;
  $editStepRow = null;
  if ($editStepId) {
      $stEdit = $db->prepare("SELECT * FROM montante_steps WHERE id = ?");
      $stEdit->execute([$editStepId]);
      $editStepRow = $stEdit->fetch(PDO::FETCH_ASSOC);
  }
  ?>
  <div class="card">
    <h2>📋 Étapes (<?= count($steps) ?>)</h2>
    <?php if ($editStepRow): ?>
    <div style="background:rgba(0,212,106,0.06);border:1px solid rgba(0,212,106,0.25);border-radius:10px;padding:1rem;margin-bottom:1rem;">
      <strong style="color:#00d46a;">✏️ Modifier l'étape <?= (int)$editStepRow['step_number'] ?></strong>
      <form method="post" style="margin-top:1rem;">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <input type="hidden" name="action" value="edit_step">
        <input type="hidden" name="step_id" value="<?= (int)$editStepRow['id'] ?>">
        <div class="form-grid">
          <div class="form-group"><label>Match</label><input type="text" name="match_desc" value="<?= htmlspecialchars($editStepRow['match_desc'] ?? '') ?>" required></div>
          <div class="form-group"><label>Compétition</label><input type="text" name="competition" value="<?= htmlspecialchars($editStepRow['competition'] ?? '') ?>"></div>
          <div class="form-group"><label>Cote</label><input type="number" name="cote" step="0.01" min="1.01" value="<?= number_format((float)$editStepRow['cote'], 2, '.', '') ?>" required></div>
          <div class="form-group"><label>Mise (€)</label><input type="number" name="mise" step="0.01" min="0.5" value="<?= number_format((float)$editStepRow['mise'], 2, '.', '') ?>" required></div>
          <div class="form-group"><label>Date du match</label>
            <div class="strateedge-date-wrap">
              <input type="text" class="strateedge-date-display" placeholder="jj/mm/aaaa" readonly>
              <input type="hidden" name="date_match" value="<?= $editStepRow['date_match'] ? htmlspecialchars($editStepRow['date_match']) : '' ?>">
              <span class="cal-icon" aria-hidden="true"><svg width="20" height="20" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" focusable="false"><path fill="#ff2d78" d="M19 4h-1V2h-2v2H8V2H6v2H5c-1.11 0-1.99.9-1.99 2L3 20c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 16H5V9h14v11zM9 11H7v2h2v-2zm4 0h-2v2h2v-2zm4 0h-2v2h2v-2z"/></svg></span>
              <div class="cal-popover" role="dialog" aria-label="Choisir une date"></div>
            </div>
          </div>
          <div class="form-group"><label>Heure</label><input type="text" name="heure" placeholder="15:00" value="<?= htmlspecialchars($editStepRow['heure'] ?? '') ?>"></div>
        </div>
        <div class="form-group" style="margin-top:0.8rem;">
          <label>Analyse / Commentaire</label>
          <textarea name="analyse" rows="3" style="width:100%;background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.1);border-radius:8px;padding:0.6rem;color:var(--text-primary);font-family:inherit;resize:vertical;"><?= htmlspecialchars($editStepRow['analyse'] ?? '') ?></textarea>
        </div>
        <div style="margin-top:0.8rem;display:flex;gap:0.5rem;">
          <button type="submit" class="btn btn-green">Enregistrer</button>
          <a href="?">Annuler</a>
        </div>
      </form>
    </div>
    <?php endif; ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr><th>#</th><th>Match</th><th>Compétition</th><th>Date</th><th>Cote</th><th>Mise</th><th>Résultat</th><th>+/-</th><th>Bankroll</th><th>Actions</th></tr>
        </thead>
        <tbody>
        <?php foreach ($steps as $s): ?>
          <tr>
            <td style="font-family:'Orbitron',sans-serif;font-weight:700;"><?= (int)$s['step_number'] ?></td>
            <td><?= clean($s['match_desc']) ?></td>
            <td><?= clean($s['competition'] ?? '—') ?></td>
            <td style="white-space:nowrap;"><?= $s['date_match'] ? date('d/m', strtotime($s['date_match'])) : '—' ?><?= $s['heure'] ? ' ' . clean($s['heure']) : '' ?></td>
            <td style="color:var(--neon-blue);font-weight:600;"><?= number_format((float)$s['cote'], 2) ?></td>
            <td><?= number_format((float)$s['mise'], 2) ?>€</td>
            <td>
              <?php
              $rc = ['gagne'=>['✅ Gagné','#00c864','rgba(0,200,100,0.12)'],'perdu'=>['❌ Perdu','#ff4444','rgba(255,68,68,0.12)'],'annule'=>['↺ Annulé','#f59e0b','rgba(245,158,11,0.12)'],'en_cours'=>['⏳ En cours','#00d4ff','rgba(0,212,255,0.1)']];
              $r = $s['resultat'] ?? 'en_cours';
              $ri = $rc[$r] ?? $rc['en_cours'];
              ?>
              <span style="background:<?= $ri[2] ?>;color:<?= $ri[1] ?>;padding:0.2rem 0.6rem;border-radius:6px;font-size:0.75rem;font-weight:700;"><?= $ri[0] ?></span>
            </td>
            <td class="<?= ($s['gain_perte'] ?? 0) >= 0 ? 'profit-pos' : 'profit-neg' ?>" style="font-family:'Space Mono',monospace;font-size:0.82rem;">
              <?= $s['gain_perte'] !== null ? (($s['gain_perte'] >= 0 ? '+' : '') . number_format((float)$s['gain_perte'], 2) . '€') : '—' ?>
            </td>
            <td style="font-weight:600;"><?= $s['bankroll_apres'] !== null ? number_format((float)$s['bankroll_apres'], 2) . '€' : '—' ?></td>
            <td>
              <a href="?edit_step=<?= (int)$s['id'] ?>" class="btn-sm" style="display:inline-block;margin-bottom:0.25rem;background:rgba(0,212,255,0.12);color:#00d4ff;border:1px solid rgba(0,212,255,0.3);text-decoration:none;">✏️ Modifier</a>
              <?php if ($s['resultat'] === 'en_cours'): ?>
              <div style="display:flex;gap:0.25rem;flex-wrap:wrap;">
                <?php foreach (['gagne' => '✅', 'perdu' => '❌', 'annule' => '↺'] as $res => $icon): ?>
                <form method="post" style="display:inline;" onsubmit="return confirm('Marquer comme <?= $res ?> ?')">
                  <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                  <input type="hidden" name="action" value="set_resultat">
                  <input type="hidden" name="step_id" value="<?= (int)$s['id'] ?>">
                  <input type="hidden" name="resultat" value="<?= $res ?>">
                  <button type="submit" class="btn-sm" style="background:<?= $rc[$res][2] ?>;color:<?= $rc[$res][1] ?>;border:1px solid <?= $rc[$res][1] ?>30;"><?= $icon ?></button>
                </form>
                <?php endforeach; ?>
              </div>
              <?php else: ?>
              <span style="font-size:0.75rem;color:var(--text-muted);">Défini</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>

  <!-- Montantes précédentes : supprimer les tests -->
  <?php if (count($toutesMontantes) > 0): ?>
  <div class="card">
    <h2>🗑️ Montantes (supprimer les tests)</h2>
    <p style="color:var(--text-muted);font-size:0.85rem;margin-bottom:1rem;">Tu peux supprimer une montante et toutes ses étapes. Irréversible.</p>
    <?php foreach ($toutesMontantes as $m): ?>
    <div class="montante-row">
      <div>
        <strong><?= clean($m['nom']) ?></strong>
        <span class="status-badge status-<?= $m['statut'] ?>" style="margin-left:0.5rem;"><?= $m['statut'] === 'active' ? '🟢' : ($m['statut'] === 'pause' ? '⏸' : '⬛') ?></span>
        <span style="color:var(--text-muted);font-size:0.82rem;margin-left:0.5rem;">ID <?= (int)$m['id'] ?> · <?= number_format((float)$m['bankroll_initial'], 0) ?>€ visé · <?= date('d/m/Y', strtotime($m['date_debut'] ?? $m['created_at'])) ?></span>
      </div>
      <form method="post" style="display:inline;" onsubmit="return confirm('Supprimer définitivement cette montante et toutes ses étapes ?');">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <input type="hidden" name="action" value="delete_montante">
        <input type="hidden" name="montante_id" value="<?= (int)$m['id'] ?>">
        <button type="submit" class="btn-delete">🗑️ Supprimer</button>
      </form>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<script src="/assets/js/calendar-strateedge.js" defer></script>
</body>
</html>
