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
        foreach (['daily', 'weekly', 'weekend', 'tennis', 'vip_max'] as $o) {
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

require_once __DIR__ . '/sidebar.php';
?>
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
            <input type="date" name="date_expir" style="width:100%;padding:0.5rem;border-radius:6px;border:1px solid var(--border-subtle);background:rgba(255,255,255,0.04);color:var(--text-primary);">
          </div>
        </div>
        <div style="margin-bottom:1rem;">
          <span style="font-size:0.8rem;color:var(--text-muted);margin-right:0.75rem;">Formules concernées :</span>
          <?php foreach (['daily' => 'Daily', 'weekend' => 'Week-end', 'weekly' => 'Weekly', 'tennis' => 'Tennis', 'vip_max' => 'VIP Max'] as $k => $l): ?>
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
