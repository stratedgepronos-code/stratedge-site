<?php
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
$pageActive = 'code-promo';
$db = getDB();

$success = '';
$error = '';
$codes = [];

try {
    $codes = $db->query("SELECT * FROM codes_promo ORDER BY date_creation DESC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $error = 'Table codes_promo absente. Exécutez la migration SQL.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf($_POST['csrf_token'] ?? '')) {
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $code   = trim(strtoupper($_POST['code'] ?? ''));
        $type   = in_array($_POST['type'] ?? '', ['percent', 'fixed']) ? $_POST['type'] : 'percent';
        $value  = (float) ($_POST['value'] ?? 0);
        $offres = [];
        foreach (['unique', 'duo', 'trio', 'quinte', 'semaine', 'pack10', 'tennis', 'fun', 'vip_max'] as $o) {
            if (!empty($_POST['offres'][$o])) $offres[] = $o;
        }
        $offresStr = implode(',', $offres);
        $max_use   = max(0, (int)($_POST['max_utilisations'] ?? 0));
        $date_expir = trim($_POST['date_expir'] ?? '');
        if ($date_expir === '') $date_expir = null;
        $actif = isset($_POST['actif']) ? 1 : 0;
        if ($code === '') {
            $error = 'Le code est obligatoire.';
        } elseif ($type === 'percent' && ($value <= 0 || $value > 100)) {
            $error = 'Pour un pourcentage, saisir une valeur entre 1 et 100.';
        } elseif ($type === 'fixed' && $value <= 0) {
            $error = 'Pour un montant fixe, saisir un nombre positif.';
        } elseif ($offresStr === '') {
            $error = 'Sélectionnez au moins une formule.';
        } else {
            try {
                $stmt = $db->prepare("INSERT INTO codes_promo (code, type, value, offres, max_utilisations, date_expir, actif) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$code, $type, $value, $offresStr, $max_use, $date_expir, $actif]);
                $success = 'Code promo « ' . htmlspecialchars($code) . ' » créé.';
                header('Location: code-promo.php?ok=1');
                exit;
            } catch (PDOException $e) {
                if (strpos($e->getMessage(), 'Duplicate') !== false) $error = 'Ce code existe déjà.';
                else $error = 'Erreur base de données.';
            }
        }
    }
    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            try {
                $db->prepare("DELETE FROM code_promo_utilisations WHERE code_promo_id = ?")->execute([$id]);
                $db->prepare("DELETE FROM codes_promo WHERE id = ?")->execute([$id]);
                $success = 'Code supprimé.';
                header('Location: code-promo.php?deleted=1');
                exit;
            } catch (Throwable $e) {
                $error = 'Erreur lors de la suppression.';
            }
        }
    }
    if ($action === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            try {
                $db->exec("UPDATE codes_promo SET actif = NOT actif WHERE id = $id");
                $success = 'Statut modifié.';
                header('Location: code-promo.php?toggled=1');
                exit;
            } catch (Throwable $e) {
                $error = 'Erreur.';
            }
        }
    }
}

if (isset($_GET['ok'])) $success = 'Code promo créé.';
if (isset($_GET['deleted'])) $success = 'Code supprimé.';
if (isset($_GET['toggled'])) $success = 'Statut modifié.';

$showMigrationBlock = ($error && strpos($error, 'Table codes_promo absente') !== false);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <link rel="icon" type="image/png" href="../assets/images/mascotte.png">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Codes promo — Admin StratEdge</title>
  <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@400;500;600;700&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
  <style>
    :root { --bg-dark:#050810; --bg-card:#0d1220; --neon-green:#ff2d78; --neon-green-dim:#d6245f; --neon-blue:#00d4ff; --text-primary:#f0f4f8; --text-secondary:#b0bec9; --text-muted:#8a9bb0; --border-subtle:rgba(255,45,120,0.12); }
    *, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }
    body { font-family:'Rajdhani',sans-serif; background:var(--bg-dark); color:var(--text-primary); min-height:100vh; display:flex; }
    .main { padding:2rem; }
    .card { background:var(--bg-card); border:1px solid var(--border-subtle); border-radius:14px; padding:1.5rem; }
    .card h1 { font-family:'Orbitron',sans-serif; font-size:1.4rem; font-weight:700; color:var(--text-primary); }
    .card h2 { font-family:'Orbitron',sans-serif; font-size:1rem; font-weight:600; color:var(--text-secondary); }
    .card input[type="text"], .card input[type="number"], .card input[type="date"], .card select {
      background:var(--bg-card); border:1px solid var(--border-subtle); border-radius:8px;
      padding:0.6rem 0.75rem; color:var(--text-primary); font-family:inherit; font-size:0.95rem;
    }
    .card input:focus, .card select:focus { outline:none; border-color:rgba(255,45,120,0.4); }
    /* Menu déroulant : fond sombre + options lisibles (évite blanc sur blanc) */
    .card select { color-scheme: dark; }
    .card select option { background:#0d1220; color:#f0f4f8; }
    .card label { color:var(--text-secondary); }
    .card .btn-sm { background:var(--neon-green); color:#fff; border:none; padding:0.5rem 1.2rem; border-radius:8px; font-weight:700; cursor:pointer; font-family:inherit; font-size:0.9rem; }
    .card .btn-sm:hover { background:var(--neon-green-dim); }
    .card .btn-danger { background:rgba(255,45,120,0.2); color:#ff6b9d; border:1px solid rgba(255,45,120,0.3); }
    .card table th { font-family:'Space Mono',monospace; font-size:0.7rem; letter-spacing:1px; text-transform:uppercase; color:var(--text-muted); }
    .card table td { color:var(--text-secondary); }
    .table-scroll { overflow-x:auto; }
    /* Champ date : wrapper avec icône calendrier visible */
    .date-picker-wrap { position:relative; width:100%; min-height:42px; }
    .date-picker-wrap input { width:100%; padding-right:2.5rem; }
    .date-picker-wrap .cal-icon { position:absolute; right:0.75rem; top:50%; transform:translateY(-50%); pointer-events:none; font-size:1.15rem; opacity:0.9; color:var(--neon-green); }
    .date-picker-wrap .cal-icon svg { width:20px; height:20px; fill:currentColor; }
    /* ═══ Calendrier StratEdge ═══ */
    .cal-popover {
      position: absolute; z-index: 300; left: 0; top: 100%; margin-top: 8px;
      width: 320px;
      background: var(--bg-card);
      border: 1px solid var(--border-subtle);
      border-radius: 14px;
      box-shadow: 0 12px 36px rgba(0,0,0,0.45);
      overflow: hidden;
      opacity: 0; visibility: hidden; transition: opacity 0.2s ease, visibility 0.2s;
    }
    .cal-popover.is-open { opacity: 1; visibility: visible; }

    /* barre accent en haut comme les stat-cards */
    .cal-popover::before {
      content: ''; position: absolute; top: 0; left: 0; right: 0; height: 2px;
      background: linear-gradient(90deg, var(--neon-green), var(--neon-green-dim), #a855f7);
      opacity: 0.8;
    }

    .cal-header {
      display: flex; align-items: center; justify-content: space-between;
      padding: 16px 18px 12px; position: relative;
    }
    .cal-title {
      font-family: 'Orbitron', sans-serif; font-size: 0.85rem; font-weight: 700;
      color: var(--text-primary); letter-spacing: 0.5px;
    }
    .cal-nav { display: flex; gap: 4px; }
    .cal-nav-btn {
      width: 30px; height: 30px; border-radius: 8px;
      border: 1px solid rgba(255,255,255,0.06); background: rgba(255,255,255,0.03);
      color: var(--text-muted); cursor: pointer;
      display: flex; align-items: center; justify-content: center;
      transition: all 0.2s;
    }
    .cal-nav-btn:hover {
      border-color: rgba(255,45,120,0.25); background: rgba(255,45,120,0.08); color: var(--neon-green);
    }
    .cal-nav-btn svg { width: 14px; height: 14px; }

    .cal-weekdays {
      display: grid; grid-template-columns: repeat(7, 1fr);
      padding: 0 14px; margin-bottom: 4px;
      font-family: 'Space Mono', monospace; font-size: 0.6rem;
      letter-spacing: 2px; text-transform: uppercase; color: var(--text-muted);
    }
    .cal-weekdays span { text-align: center; padding: 6px 0; }
    .cal-weekdays-sep {
      margin: 0 14px; height: 1px;
      background: linear-gradient(90deg, transparent, var(--border-subtle), transparent);
    }

    .cal-grid {
      display: grid; grid-template-columns: repeat(7, 1fr);
      gap: 4px; padding: 10px 14px 18px;
    }
    .cal-day {
      width: 38px; height: 38px; margin: 0 auto;
      display: flex; align-items: center; justify-content: center;
      border-radius: 10px; border: 1px solid transparent;
      font-family: 'Rajdhani', sans-serif; font-size: 0.95rem; font-weight: 600;
      color: var(--text-muted); cursor: pointer;
      position: relative; transition: all 0.2s;
    }
    .cal-day.other-month { color: rgba(138,155,176,0.25); pointer-events: none; }
    .cal-day:hover:not(.other-month):not(.selected) {
      background: rgba(255,45,120,0.06); border-color: rgba(255,45,120,0.15); color: var(--text-primary);
    }
    .cal-day.today {
      color: var(--neon-blue); font-weight: 700;
      border-color: rgba(0,212,255,0.3);
      background: rgba(0,212,255,0.06);
    }
    .cal-day.today::after {
      content: ''; position: absolute; bottom: 4px; left: 50%; transform: translateX(-50%);
      width: 4px; height: 4px; border-radius: 50%; background: var(--neon-blue);
    }
    .cal-day.selected {
      background: var(--neon-green); color: #fff; font-weight: 700;
      border-color: var(--neon-green);
      box-shadow: 0 0 12px rgba(255,45,120,0.3);
    }
    .cal-day.selected::after { display: none; }
    .cal-day.selected:hover { background: var(--neon-green-dim); border-color: var(--neon-green-dim); }

    /* Bouton effacer */
    .cal-footer {
      display: flex; justify-content: flex-end;
      padding: 0 14px 14px;
    }
    .cal-clear-btn {
      font-family: 'Rajdhani', sans-serif; font-size: 0.8rem; font-weight: 600;
      color: var(--text-muted); background: none; border: none; cursor: pointer;
      padding: 4px 10px; border-radius: 6px; transition: all 0.2s;
    }
    .cal-clear-btn:hover { color: var(--neon-green); background: rgba(255,45,120,0.06); }
  </style>
</head>
<body>
<?php require_once __DIR__ . '/sidebar.php'; ?>
<div class="main">
  <div class="card" style="max-width:1200px;">
    <h1 style="margin-bottom:0.5rem;">🎟️ Codes promo</h1>
    <p style="color:var(--text-muted);font-size:0.9rem;margin-bottom:1.5rem;">Configurez les codes promo (pourcentage ou montant en €). Les membres peuvent les saisir sur les pages de paiement. L’anniversaire d’un membre lui donne automatiquement -50% sur Tennis/Daily/Weekly/Week-end et -25% sur VIP Max, une fois par an.</p>

    <?php if ($showMigrationBlock): ?>
    <details open style="margin-bottom:1.5rem;background:rgba(255,255,255,0.03);border:1px solid var(--border-subtle);border-radius:10px;">
      <summary style="padding:0.75rem 1rem;cursor:pointer;font-weight:600;color:var(--text-secondary);">📋 Migration SQL — à exécuter dans phpMyAdmin si les tables n’existent pas</summary>
      <p style="padding:0.75rem 1rem;margin:0;font-size:0.85rem;color:var(--text-muted);border-bottom:1px solid var(--border-subtle);">→ Utilisez le fichier <strong>code-promo-migration.sql</strong> dans le dossier admin/ : ouvrez-le, copiez <em>tout</em> son contenu (uniquement du SQL, pas de PHP), puis collez dans l’onglet SQL de phpMyAdmin et exécutez.</p>
      <pre style="margin:0;padding:1rem;overflow:auto;font-size:0.78rem;line-height:1.4;color:var(--text-primary);white-space:pre-wrap;word-break:break-all;">-- Tables codes promo + anniversaire (copier à partir d’ici)
CREATE TABLE IF NOT EXISTS `codes_promo` (
  `id`                INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `code`              VARCHAR(50) NOT NULL,
  `type`              ENUM('percent','fixed') NOT NULL DEFAULT 'percent',
  `value`             DECIMAL(10,2) NOT NULL,
  `offres`            VARCHAR(200) NOT NULL DEFAULT '',
  `max_utilisations`  INT(11) UNSIGNED NOT NULL DEFAULT 0,
  `utilisations`      INT(11) UNSIGNED NOT NULL DEFAULT 0,
  `date_expir`        DATE DEFAULT NULL,
  `actif`             TINYINT(1) NOT NULL DEFAULT 1,
  `date_creation`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `idx_actif` (`actif`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `code_promo_utilisations` (
  `id`            INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `code_promo_id` INT(11) UNSIGNED NOT NULL,
  `membre_id`     INT(11) UNSIGNED NOT NULL,
  `offre`         VARCHAR(30) NOT NULL,
  `montant_avant` DECIMAL(10,2) NOT NULL,
  `montant_apres` DECIMAL(10,2) NOT NULL,
  `date_utilisation` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_code` (`code_promo_id`),
  KEY `idx_membre` (`membre_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `promo_anniversaire_use` (
  `id`         INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `membre_id`  INT(11) UNSIGNED NOT NULL,
  `annee`      SMALLINT UNSIGNED NOT NULL,
  `offre`      VARCHAR(30) NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `membre_annee` (`membre_id`,`annee`),
  KEY `idx_membre` (`membre_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;</pre>
    </details>
    <?php endif; ?>

    <?php if ($error): ?><div style="background:rgba(255,68,68,0.1);border:1px solid rgba(255,68,68,0.3);color:#ff6b6b;padding:0.75rem 1rem;border-radius:8px;margin-bottom:1rem;"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div style="background:rgba(0,200,100,0.1);border:1px solid rgba(0,200,100,0.3);color:#00c864;padding:0.75rem 1rem;border-radius:8px;margin-bottom:1rem;"><?= htmlspecialchars($success) ?></div><?php endif; ?>

    <!-- Formulaire ajout -->
    <div class="card" style="background:var(--bg-dark);border:1px solid var(--border-subtle);margin-bottom:1.5rem;">
      <h2 style="font-size:1rem;margin-bottom:1rem;">Ajouter un code</h2>
      <form method="post">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <input type="hidden" name="action" value="add">
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1rem;margin-bottom:1rem;">
          <div>
            <label style="display:block;font-size:0.8rem;color:var(--text-muted);margin-bottom:0.3rem;">Code</label>
            <input type="text" name="code" required maxlength="50" placeholder="EX. BIENVENUE" style="width:100%;padding:0.5rem;border-radius:6px;border:1px solid var(--border-subtle);background:rgba(255,255,255,0.04);color:var(--text-primary);">
          </div>
          <div>
            <label style="display:block;font-size:0.8rem;color:var(--text-muted);margin-bottom:0.3rem;">Type</label>
            <select name="type" style="width:100%;padding:0.5rem;border-radius:6px;border:1px solid var(--border-subtle);background:rgba(255,255,255,0.04);color:var(--text-primary);">
              <option value="percent">Pourcentage (%)</option>
              <option value="fixed">Montant (€)</option>
            </select>
          </div>
          <div>
            <label style="display:block;font-size:0.8rem;color:var(--text-muted);margin-bottom:0.3rem;">Valeur</label>
            <input type="number" name="value" required step="0.01" min="0" placeholder="10 ou 5.00" style="width:100%;padding:0.5rem;border-radius:6px;border:1px solid var(--border-subtle);background:rgba(255,255,255,0.04);color:var(--text-primary);">
          </div>
          <div>
            <label style="display:block;font-size:0.8rem;color:var(--text-muted);margin-bottom:0.3rem;">Max utilisations (0 = illimité)</label>
            <input type="number" name="max_utilisations" value="0" min="0" style="width:100%;padding:0.5rem;border-radius:6px;border:1px solid var(--border-subtle);background:rgba(255,255,255,0.04);color:var(--text-primary);">
          </div>
          <div>
            <label style="display:block;font-size:0.8rem;color:var(--text-muted);margin-bottom:0.3rem;">Date d’expiration (optionnel)</label>
            <div class="date-picker-wrap" id="date_expir_wrap">
              <input type="text" id="date_expir_display" placeholder="jj/mm/aaaa" readonly style="width:100%;padding:0.6rem 2.5rem 0.6rem 0.75rem;border-radius:8px;border:1px solid var(--border-subtle);background:var(--bg-card);color:var(--text-primary);cursor:pointer;">
              <input type="hidden" id="date_expir" name="date_expir" value="">
              <span class="cal-icon" aria-hidden="true"><svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M19 4h-1V2h-2v2H8V2H6v2H5c-1.11 0-1.99.9-1.99 2L3 20c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 16H5V9h14v11zM9 11H7v2h2v-2zm4 0h-2v2h2v-2zm4 0h-2v2h2v-2z"/></svg></span>
              <div class="cal-popover" id="cal_popover" role="dialog" aria-label="Choisir une date"></div>
            </div>
          </div>
        </div>
        <div style="margin-bottom:1rem;">
          <span style="font-size:0.8rem;color:var(--text-muted);margin-right:0.75rem;">Formules concernées :</span>
          <?php foreach (['unique' => 'Unique (4,50€)', 'duo' => 'Duo (8€)', 'trio' => 'Trio (12€)', 'quinte' => 'Quinté (18€)', 'semaine' => 'Semaine (20€)', 'pack10' => 'Pack 10 (30€)', 'tennis' => 'Tennis (15€)', 'fun' => 'Fun (10€)', 'vip_max' => 'VIP Max'] as $k => $l): ?>
          <label style="margin-right:1rem;font-size:0.9rem;"><input type="checkbox" name="offres[<?= $k ?>]" value="1"> <?= $l ?></label>
          <?php endforeach; ?>
        </div>
        <label style="font-size:0.9rem;"><input type="checkbox" name="actif" value="1" checked> Actif</label>
        <div style="margin-top:1rem;">
          <button type="submit" class="btn-sm" style="background:var(--neon-green);color:#fff;border:none;padding:0.5rem 1.2rem;border-radius:8px;font-weight:700;cursor:pointer;">Créer le code</button>
        </div>
      </form>
    </div>

    <!-- Liste -->
    <h2 style="font-size:1rem;margin-bottom:0.75rem;">Codes existants</h2>
    <?php if (empty($codes)): ?>
    <p style="color:var(--text-muted);">Aucun code pour le moment.</p>
    <?php else: ?>
    <div class="table-scroll">
    <table style="width:100%;border-collapse:collapse;">
      <thead>
        <tr>
          <th style="text-align:left;padding:0.5rem;border-bottom:1px solid var(--border-subtle);">Code</th>
          <th style="text-align:left;padding:0.5rem;border-bottom:1px solid var(--border-subtle);">Type</th>
          <th style="text-align:left;padding:0.5rem;border-bottom:1px solid var(--border-subtle);">Valeur</th>
          <th style="text-align:left;padding:0.5rem;border-bottom:1px solid var(--border-subtle);">Formules</th>
          <th style="text-align:center;padding:0.5rem;border-bottom:1px solid var(--border-subtle);">Utilisations</th>
          <th style="text-align:left;padding:0.5rem;border-bottom:1px solid var(--border-subtle);">Expir.</th>
          <th style="text-align:center;padding:0.5rem;border-bottom:1px solid var(--border-subtle);">Actif</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($codes as $c): ?>
        <tr>
          <td style="padding:0.5rem;border-bottom:1px solid rgba(255,255,255,0.04);"><strong><?= htmlspecialchars($c['code']) ?></strong></td>
          <td style="padding:0.5rem;border-bottom:1px solid rgba(255,255,255,0.04);"><?= $c['type'] === 'percent' ? '%' : '€' ?></td>
          <td style="padding:0.5rem;border-bottom:1px solid rgba(255,255,255,0.04);"><?= $c['type'] === 'percent' ? (int)$c['value'] . '%' : number_format((float)$c['value'], 2, ',', '') . ' €' ?></td>
          <td style="padding:0.5rem;border-bottom:1px solid rgba(255,255,255,0.04);font-size:0.85rem;"><?= htmlspecialchars(str_replace(',', ', ', $c['offres'])) ?></td>
          <td style="padding:0.5rem;border-bottom:1px solid rgba(255,255,255,0.04);text-align:center;"><?= (int)$c['utilisations'] ?><?= (int)$c['max_utilisations'] > 0 ? ' / ' . (int)$c['max_utilisations'] : '' ?></td>
          <td style="padding:0.5rem;border-bottom:1px solid rgba(255,255,255,0.04);"><?= $c['date_expir'] ? date('d/m/Y', strtotime($c['date_expir'])) : '—' ?></td>
          <td style="padding:0.5rem;border-bottom:1px solid rgba(255,255,255,0.04);text-align:center;">
            <form method="post" style="display:inline;">
              <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
              <input type="hidden" name="action" value="toggle">
              <input type="hidden" name="id" value="<?= $c['id'] ?>">
              <button type="submit" class="btn-sm"><?= $c['actif'] ? '✅' : '❌' ?></button>
            </form>
          </td>
          <td style="padding:0.5rem;border-bottom:1px solid rgba(255,255,255,0.04);">
            <form method="post" style="display:inline;" onsubmit="return confirm('Supprimer ce code ?');">
              <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= $c['id'] ?>">
              <button type="submit" class="btn-sm btn-danger">🗑</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    </div>
    <?php endif; ?>
  </div>
</div>
<script>
(function() {
  var MOIS = ['Janvier','Février','Mars','Avril','Mai','Juin','Juillet','Août','Septembre','Octobre','Novembre','Décembre'];
  var JOURS = ['Lun','Mar','Mer','Jeu','Ven','Sam','Dim'];

  function buildPopover() {
    var wrap = document.getElementById('date_expir_wrap');
    var pop = document.getElementById('cal_popover');
    var inputVal = document.getElementById('date_expir');
    var inputDisplay = document.getElementById('date_expir_display');
    if (!wrap || !pop || !inputVal || !inputDisplay) return;

    var view = { year: new Date().getFullYear(), month: new Date().getMonth() };
    var selected = null;

    function parseYmd(str) {
      if (!str || !/^\d{4}-\d{2}-\d{2}$/.test(str)) return null;
      var p = str.split('-');
      return new Date(parseInt(p[0],10), parseInt(p[1],10)-1, parseInt(p[2],10));
    }
    function ymd(d) {
      var y = d.getFullYear(), m = d.getMonth()+1, day = d.getDate();
      return y + '-' + (m<10?'0':'') + m + '-' + (day<10?'0':'') + day;
    }
    function formatDisplay(d) {
      var day = d.getDate(), m = d.getMonth()+1, y = d.getFullYear();
      return (day<10?'0':'') + day + '/' + (m<10?'0':'') + m + '/' + y;
    }

    function render() {
      var first = new Date(view.year, view.month, 1);
      var last = new Date(view.year, view.month + 1, 0);
      var offset = (first.getDay() + 6) % 7;
      var start = new Date(first); start.setDate(start.getDate() - offset);
      var today = new Date(); today.setHours(0,0,0,0);

      var html = '<div class="cal-header">';
      html += '<span class="cal-title">' + MOIS[view.month] + ' ' + view.year + '</span>';
      html += '<div class="cal-nav"><button type="button" class="cal-nav-btn" data-dir="-1" aria-label="Mois précédent"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg></button>';
      html += '<button type="button" class="cal-nav-btn" data-dir="1" aria-label="Mois suivant"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg></button></div></div>';
      html += '<div class="cal-weekdays">';
      JOURS.forEach(function(j){ html += '<span>' + j + '</span>'; });
      html += '</div><div class="cal-weekdays-sep"></div><div class="cal-grid">';

      var d = new Date(start);
      var rows = last.getDate() + offset > 35 ? 42 : 35;
      for (var i = 0; i < rows; i++) {
        var ymdStr = d.getFullYear() + '-' + (d.getMonth()+1<10?'0':'') + (d.getMonth()+1) + '-' + (d.getDate()<10?'0':'') + d.getDate();
        var other = d.getMonth() !== view.month;
        var isToday = d.getTime() === today.getTime();
        var isSelected = selected && ymd(selected) === ymdStr;
        var cls = 'cal-day' + (other ? ' other-month' : '') + (isToday ? ' today' : '') + (isSelected ? ' selected' : '');
        html += '<button type="button" class="' + cls + '" data-ymd="' + ymdStr + '">' + d.getDate() + '</button>';
        d.setDate(d.getDate() + 1);
      }
      html += '</div>';
      html += '<div class="cal-footer"><button type="button" class="cal-clear-btn" data-action="clear">Effacer</button></div>';
      pop.innerHTML = html;

      pop.querySelectorAll('.cal-nav-btn').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
          e.stopPropagation();
          var dir = parseInt(btn.getAttribute('data-dir'), 10);
          view.month += dir;
          if (view.month > 11) { view.month = 0; view.year++; }
          if (view.month < 0) { view.month = 11; view.year--; }
          render();
        });
      });
      pop.querySelectorAll('.cal-day').forEach(function(btn) {
        btn.addEventListener('click', function() {
          var y = btn.getAttribute('data-ymd');
          if (!y) return;
          selected = parseYmd(y) || new Date(y);
          inputVal.value = y;
          inputDisplay.value = formatDisplay(selected);
          inputDisplay.placeholder = '';
          pop.classList.remove('is-open');
        });
      });
      var clearBtn = pop.querySelector('.cal-clear-btn');
      if (clearBtn) clearBtn.addEventListener('click', function() {
        selected = null; inputVal.value = ''; inputDisplay.value = ''; inputDisplay.placeholder = 'jj/mm/aaaa';
        pop.classList.remove('is-open');
      });
    }

    wrap.addEventListener('click', function(e) {
      if (e.target.closest('.cal-popover')) return;
      pop.classList.toggle('is-open');
      if (pop.classList.contains('is-open')) {
        var v = parseYmd(inputVal.value);
        if (v) { view.year = v.getFullYear(); view.month = v.getMonth(); selected = v; } else { selected = null; }
        render();
      }
    });
    document.addEventListener('click', function(e) {
      if (!wrap.contains(e.target)) pop.classList.remove('is-open');
    });
    var cur = parseYmd(inputVal.value);
    if (cur) inputDisplay.value = formatDisplay(cur);
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', buildPopover);
  else buildPopover();
})();
</script>
</body>
</html>
