<?php
// ============================================================
// STRATEDGE — vault.php — Coffre-Fort Super Admin
// Chiffrement AES-256-GCM · Double auth · Auto-lock 15min
// ============================================================
require_once __DIR__ . '/../includes/auth.php';
requireSuperAdmin();

require_once __DIR__ . '/../includes/vault-config.php';

$pageActive = 'vault';
$db = getDB();

// ── Helpers chiffrement ────────────────────────────────────
function vaultDeriveKey(string $masterPassword): string {
    return hash_pbkdf2('sha256', $masterPassword, VAULT_KEY_SALT, 100000, 32, true);
}
function vaultEncrypt(string $plain, string $key): array {
    $iv  = random_bytes(12);
    $tag = '';
    $enc = openssl_encrypt($plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
    return [
        'enc' => base64_encode($enc),
        'iv'  => base64_encode($iv),
        'tag' => base64_encode($tag),
    ];
}
function vaultDecrypt(string $enc, string $iv, string $tag, string $key): string|false {
    return openssl_decrypt(
        base64_decode($enc), 'aes-256-gcm', $key,
        OPENSSL_RAW_DATA, base64_decode($iv), base64_decode($tag)
    );
}

// ── État session coffre ────────────────────────────────────
$vaultOpen    = false;
$vaultKey     = null;
$vaultError   = '';
$vaultSuccess = '';

// Vérifier si le coffre est déjà ouvert et pas expiré
if (isset($_SESSION['vault_open']) && $_SESSION['vault_open'] === true) {
    $elapsed = time() - ($_SESSION['vault_last_activity'] ?? 0);
    if ($elapsed < VAULT_SESSION_TTL) {
        $vaultOpen  = true;
        $vaultKey   = base64_decode($_SESSION['vault_key_b64']);
        $_SESSION['vault_last_activity'] = time(); // refresh
    } else {
        // Expiré → verrouiller
        unset($_SESSION['vault_open'], $_SESSION['vault_key_b64'], $_SESSION['vault_last_activity']);
        $vaultError = 'Session expirée — coffre verrouillé automatiquement.';
    }
}

// ── CSRF ──────────────────────────────────────────────────
if (empty($_SESSION['vault_csrf'])) {
    $_SESSION['vault_csrf'] = bin2hex(random_bytes(32));
}
function verifVaultCsrf(string $token): bool {
    return hash_equals($_SESSION['vault_csrf'] ?? '', $token);
}

// ── Actions POST ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── Déverrouiller ──────────────────────────────────────
    if ($action === 'unlock') {
        if (!verifVaultCsrf($_POST['csrf'] ?? '')) {
            $vaultError = 'Erreur de sécurité.';
        } else {
            $mdp = $_POST['master_password'] ?? '';
            if (password_verify($mdp, VAULT_MASTER_HASH)) {
                $key = vaultDeriveKey($mdp);
                $_SESSION['vault_open']          = true;
                $_SESSION['vault_key_b64']        = base64_encode($key);
                $_SESSION['vault_last_activity']  = time();
                $vaultOpen = true;
                $vaultKey  = $key;
                $vaultSuccess = 'Coffre ouvert.';
            } else {
                sleep(2); // anti brute-force
                $vaultError = '🔒 Mot de passe incorrect.';
            }
        }
    }

    // ── Verrouiller ────────────────────────────────────────
    elseif ($action === 'lock') {
        unset($_SESSION['vault_open'], $_SESSION['vault_key_b64'], $_SESSION['vault_last_activity']);
        $vaultOpen = false;
        $vaultSuccess = 'Coffre verrouillé.';
    }

    // ── Ajouter un prompt ──────────────────────────────────
    elseif ($action === 'add' && $vaultOpen) {
        if (!verifVaultCsrf($_POST['csrf'] ?? '')) {
            $vaultError = 'Erreur de sécurité.';
        } else {
            $titre   = trim($_POST['titre'] ?? '');
            $cat     = trim($_POST['categorie'] ?? 'Général');
            $contenu = trim($_POST['contenu'] ?? '');
            if (!$titre || !$contenu) {
                $vaultError = 'Titre et contenu obligatoires.';
            } else {
                $encrypted = vaultEncrypt($contenu, $vaultKey);
                $db->prepare("INSERT INTO vault_prompts (titre, categorie, contenu_enc, iv, tag) VALUES (?,?,?,?,?)")
                   ->execute([$titre, $cat, $encrypted['enc'], $encrypted['iv'], $encrypted['tag']]);
                $vaultSuccess = '✅ Prompt enregistré dans le coffre.';
            }
        }
    }

    // ── Modifier un prompt ─────────────────────────────────
    elseif ($action === 'edit' && $vaultOpen) {
        if (!verifVaultCsrf($_POST['csrf'] ?? '')) {
            $vaultError = 'Erreur de sécurité.';
        } else {
            $id      = (int)($_POST['id'] ?? 0);
            $titre   = trim($_POST['titre'] ?? '');
            $cat     = trim($_POST['categorie'] ?? 'Général');
            $contenu = trim($_POST['contenu'] ?? '');
            if (!$id || !$titre || !$contenu) {
                $vaultError = 'Données manquantes.';
            } else {
                $encrypted = vaultEncrypt($contenu, $vaultKey);
                $db->prepare("UPDATE vault_prompts SET titre=?, categorie=?, contenu_enc=?, iv=?, tag=?, updated_at=NOW() WHERE id=?")
                   ->execute([$titre, $cat, $encrypted['enc'], $encrypted['iv'], $encrypted['tag'], $id]);
                $vaultSuccess = '✅ Prompt mis à jour.';
            }
        }
    }

    // ── Supprimer un prompt ────────────────────────────────
    elseif ($action === 'delete' && $vaultOpen) {
        if (!verifVaultCsrf($_POST['csrf'] ?? '')) {
            $vaultError = 'Erreur de sécurité.';
        } else {
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                $db->prepare("DELETE FROM vault_prompts WHERE id=?")->execute([$id]);
                $vaultSuccess = '🗑️ Prompt supprimé.';
            }
        }
    }
}

// ── Charger les prompts si coffre ouvert ───────────────────
$prompts   = [];
$categories = [];
if ($vaultOpen && $vaultKey) {
    $rows = $db->query("SELECT * FROM vault_prompts ORDER BY categorie, titre")->fetchAll();
    foreach ($rows as $row) {
        $plain = vaultDecrypt($row['contenu_enc'], $row['iv'], $row['tag'], $vaultKey);
        if ($plain !== false) {
            $row['contenu_clair'] = $plain;
            $prompts[] = $row;
            $categories[$row['categorie']] = true;
        }
    }
    $categories = array_keys($categories);
    sort($categories);
}
$nbPrompts = count($prompts);
$csrf = $_SESSION['vault_csrf'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <link rel="icon" type="image/png" href="../assets/images/mascotte.png">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>🔐 Coffre-Fort — Admin StratEdge</title>
  <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@400;500;600;700&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
  <style>
    :root {
      --bg:#050810; --bg2:#0d1220; --gold:#f5c842; --gold-dim:#c8960c;
      --gold-faint:rgba(245,200,66,0.12); --gold-border:rgba(245,200,66,0.22);
      --txt:#f0f4f8; --txt2:#b0bec9; --txt3:#8a9bb0;
      --border:rgba(245,200,66,0.12);
    }
    *,*::before,*::after{margin:0;padding:0;box-sizing:border-box;}
    body{font-family:'Rajdhani',sans-serif;background:var(--bg);color:var(--txt);min-height:100vh;display:flex;}
    .main{margin-left:240px;flex:1;padding:2rem;min-height:100vh;}

    /* ── Header ── */
    .page-header{margin-bottom:2rem;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;}
    .page-header h1{font-family:'Orbitron',sans-serif;font-size:1.4rem;font-weight:700;display:flex;align-items:center;gap:0.7rem;}
    .page-header p{color:var(--txt3);margin-top:0.3rem;font-size:0.85rem;}

    /* ── Lock screen ── */
    .lock-screen{
      max-width:460px;margin:4rem auto;
      background:linear-gradient(160deg,#111208,#0d1000);
      border:1px solid var(--gold-border);border-radius:20px;padding:3rem 2.5rem;text-align:center;
      box-shadow:0 40px 80px rgba(0,0,0,0.4),0 0 0 1px rgba(245,200,66,0.08);
    }
    .lock-icon{font-size:3.5rem;margin-bottom:1rem;display:block;}
    .lock-title{font-family:'Orbitron',sans-serif;font-size:1.1rem;font-weight:900;
      background:linear-gradient(135deg,#c8960c,#f5c842,#fffbe6);-webkit-background-clip:text;-webkit-text-fill-color:transparent;
      margin-bottom:0.4rem;}
    .lock-sub{color:var(--txt3);font-size:0.82rem;margin-bottom:2rem;}
    .lock-input{
      width:100%;background:rgba(255,255,255,0.04);border:1px solid var(--gold-border);
      border-radius:10px;padding:0.9rem 1.2rem;color:var(--txt);font-family:'Rajdhani',sans-serif;
      font-size:1rem;outline:none;text-align:center;letter-spacing:4px;transition:border 0.2s;
      margin-bottom:1rem;
    }
    .lock-input:focus{border-color:var(--gold);}
    .lock-input::placeholder{letter-spacing:1px;color:var(--txt3);}
    .btn-unlock{
      width:100%;padding:1rem;border:none;border-radius:10px;cursor:pointer;
      background:linear-gradient(135deg,var(--gold-dim),var(--gold));
      color:#050810;font-family:'Orbitron',sans-serif;font-size:0.78rem;
      font-weight:900;letter-spacing:2px;text-transform:uppercase;
      transition:all 0.3s;box-shadow:0 4px 25px rgba(245,200,66,0.25);
    }
    .btn-unlock:hover{box-shadow:0 8px 40px rgba(245,200,66,0.4);transform:translateY(-2px);}
    .lock-warning{
      margin-top:1.5rem;padding:0.75rem 1rem;border-radius:8px;
      background:rgba(245,200,66,0.06);border:1px solid rgba(245,200,66,0.15);
      font-size:0.75rem;color:rgba(245,200,66,0.45);font-family:'Space Mono',monospace;
      letter-spacing:0.5px;line-height:1.6;
    }

    /* ── Open vault ── */
    .vault-header{
      display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;
      background:linear-gradient(135deg,var(--gold-faint),rgba(200,150,12,0.05));
      border:1px solid var(--gold-border);border-radius:14px;padding:1.2rem 1.5rem;
      margin-bottom:1.5rem;
    }
    .vault-status{display:flex;align-items:center;gap:0.7rem;}
    .vault-dot{width:8px;height:8px;border-radius:50%;background:var(--gold);animation:gDot 1.5s ease-in-out infinite;}
    @keyframes gDot{0%,100%{box-shadow:0 0 6px var(--gold);}50%{box-shadow:0 0 14px var(--gold),0 0 25px rgba(245,200,66,0.4);}}
    .vault-status-text{font-family:'Space Mono',monospace;font-size:0.65rem;letter-spacing:2px;color:var(--gold);text-transform:uppercase;}
    .vault-ttl{font-family:'Space Mono',monospace;font-size:0.6rem;color:var(--txt3);letter-spacing:1px;}
    .btn-lock{
      background:rgba(245,200,66,0.08);border:1px solid var(--gold-border);
      color:var(--gold);padding:0.5rem 1.2rem;border-radius:8px;cursor:pointer;
      font-family:'Rajdhani',sans-serif;font-size:0.85rem;font-weight:700;
      transition:all 0.2s;
    }
    .btn-lock:hover{background:rgba(245,200,66,0.15);}

    /* ── Layout vault ── */
    .vault-layout{display:grid;grid-template-columns:320px 1fr;gap:1.5rem;align-items:start;}

    /* ── Formulaire ajout ── */
    .vault-form-card{
      background:var(--bg2);border:1px solid var(--border);border-radius:16px;
      padding:1.5rem;position:sticky;top:2rem;
    }
    .vault-form-card h3{
      font-family:'Orbitron',sans-serif;font-size:0.78rem;font-weight:700;
      letter-spacing:1.5px;color:var(--gold);margin-bottom:1.2rem;
      display:flex;align-items:center;gap:0.5rem;
    }
    .vf-label{font-family:'Space Mono',monospace;font-size:0.58rem;letter-spacing:2px;
      text-transform:uppercase;color:var(--txt3);display:block;margin-bottom:0.35rem;}
    .vf-input,.vf-select,.vf-textarea{
      width:100%;background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.08);
      border-radius:8px;padding:0.65rem 0.9rem;color:var(--txt);
      font-family:'Rajdhani',sans-serif;font-size:0.95rem;outline:none;
      transition:border 0.2s;margin-bottom:1rem;
    }
    .vf-input:focus,.vf-select:focus,.vf-textarea:focus{border-color:var(--gold);}
    .vf-textarea{resize:vertical;min-height:200px;font-family:'Space Mono',monospace;font-size:0.8rem;line-height:1.6;}
    .vf-select option{background:#0d1220;}
    .btn-save-vault{
      width:100%;padding:0.85rem;border:none;border-radius:10px;cursor:pointer;
      background:linear-gradient(135deg,var(--gold-dim),var(--gold));
      color:#050810;font-family:'Orbitron',sans-serif;font-size:0.7rem;
      font-weight:900;letter-spacing:2px;text-transform:uppercase;
      transition:all 0.3s;box-shadow:0 4px 20px rgba(245,200,66,0.2);
    }
    .btn-save-vault:hover{box-shadow:0 8px 30px rgba(245,200,66,0.4);transform:translateY(-2px);}

    /* ── Liste des prompts ── */
    .vault-list-header{
      display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;
    }
    .vault-list-header h3{
      font-family:'Orbitron',sans-serif;font-size:0.82rem;font-weight:700;color:var(--txt);
    }
    .vault-count{
      background:var(--gold-faint);border:1px solid var(--gold-border);
      color:var(--gold);padding:0.2rem 0.7rem;border-radius:20px;
      font-family:'Space Mono',monospace;font-size:0.6rem;font-weight:700;
    }
    .vault-search{
      width:100%;background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.08);
      border-radius:10px;padding:0.65rem 1rem;color:var(--txt);
      font-family:'Rajdhani',sans-serif;font-size:0.9rem;outline:none;
      margin-bottom:1rem;transition:border 0.2s;
    }
    .vault-search:focus{border-color:var(--gold);}
    .cat-label{
      font-family:'Space Mono',monospace;font-size:0.58rem;letter-spacing:3px;
      text-transform:uppercase;color:var(--gold-dim);margin:1.2rem 0 0.5rem;
      padding-bottom:0.3rem;border-bottom:1px solid var(--gold-faint);
    }
    .prompt-card{
      background:var(--bg2);border:1px solid var(--border);border-radius:12px;
      margin-bottom:0.75rem;overflow:hidden;transition:border-color 0.2s;
    }
    .prompt-card:hover{border-color:var(--gold-border);}
    .prompt-head{
      display:flex;align-items:center;justify-content:space-between;
      padding:0.9rem 1.2rem;cursor:pointer;
    }
    .prompt-title{font-family:'Orbitron',sans-serif;font-size:0.78rem;font-weight:700;color:var(--txt);}
    .prompt-meta{font-size:0.72rem;color:var(--txt3);margin-top:0.2rem;}
    .prompt-actions{display:flex;gap:0.5rem;flex-shrink:0;}
    .btn-edit-p,.btn-del-p{
      padding:0.3rem 0.7rem;border-radius:6px;border:none;cursor:pointer;
      font-family:'Rajdhani',sans-serif;font-size:0.78rem;font-weight:700;transition:all 0.2s;
    }
    .btn-edit-p{background:rgba(245,200,66,0.08);color:var(--gold);border:1px solid var(--gold-border);}
    .btn-edit-p:hover{background:rgba(245,200,66,0.2);}
    .btn-del-p{background:rgba(255,45,120,0.06);color:#ff2d78;border:1px solid rgba(255,45,120,0.15);}
    .btn-del-p:hover{background:rgba(255,45,120,0.15);}
    .prompt-body{
      display:none;padding:0 1.2rem 1.2rem;
    }
    .prompt-body.open{display:block;}
    .prompt-content{
      background:rgba(0,0,0,0.3);border:1px solid rgba(255,255,255,0.06);
      border-radius:8px;padding:1rem;font-family:'Space Mono',monospace;
      font-size:0.78rem;line-height:1.7;color:var(--txt2);
      white-space:pre-wrap;word-break:break-word;max-height:400px;overflow-y:auto;
      position:relative;
    }
    .btn-copy{
      position:absolute;top:0.5rem;right:0.5rem;
      background:rgba(245,200,66,0.1);border:1px solid var(--gold-border);
      color:var(--gold);padding:0.25rem 0.6rem;border-radius:5px;cursor:pointer;
      font-family:'Rajdhani',sans-serif;font-size:0.72rem;font-weight:700;
      transition:all 0.2s;
    }
    .btn-copy:hover{background:rgba(245,200,66,0.2);}
    .prompt-content-wrap{position:relative;}

    /* ── Modal édition ── */
    .modal-overlay{
      position:fixed;inset:0;background:rgba(0,0,0,0.75);backdrop-filter:blur(4px);
      z-index:500;display:none;align-items:center;justify-content:center;padding:2rem;
    }
    .modal-overlay.open{display:flex;}
    .modal-box{
      background:var(--bg2);border:1px solid var(--gold-border);border-radius:18px;
      padding:2rem;width:100%;max-width:700px;max-height:90vh;overflow-y:auto;
      box-shadow:0 40px 80px rgba(0,0,0,0.5);
    }
    .modal-title{font-family:'Orbitron',sans-serif;font-size:0.9rem;color:var(--gold);margin-bottom:1.5rem;}
    .modal-close{
      float:right;background:none;border:none;color:var(--txt3);font-size:1.3rem;
      cursor:pointer;line-height:1;transition:color 0.2s;
    }
    .modal-close:hover{color:var(--txt);}

    /* ── Alert boxes ── */
    .alert-ok{background:rgba(0,200,100,0.08);border:1px solid rgba(0,200,100,0.25);border-radius:10px;padding:0.75rem 1rem;color:#00c864;font-size:0.88rem;margin-bottom:1rem;}
    .alert-err{background:rgba(255,45,120,0.08);border:1px solid rgba(255,45,120,0.2);border-radius:10px;padding:0.75rem 1rem;color:#ff6b9d;font-size:0.88rem;margin-bottom:1rem;}

    /* ── Timer bar ── */
    .timer-bar{height:3px;background:rgba(245,200,66,0.1);border-radius:2px;margin-top:0.4rem;overflow:hidden;}
    .timer-fill{height:100%;background:linear-gradient(90deg,var(--gold-dim),var(--gold));border-radius:2px;width:100%;transition:width 1s linear;}

    /* ── Vide ── */
    .vault-empty{text-align:center;padding:3rem;color:var(--txt3);}
    .vault-empty .big{font-size:3rem;opacity:0.2;display:block;margin-bottom:0.5rem;}

    @media(max-width:1100px){.vault-layout{grid-template-columns:1fr;} .vault-form-card{position:static;}}
    @media(max-width:768px){.main{margin-left:0;padding-top:68px;}}
  </style>
</head>
<body>

<?php require_once __DIR__ . '/sidebar.php'; ?>

<div class="main">
  <div class="page-header">
    <div>
      <h1>🔐 Coffre-Fort</h1>
      <p>Accès réservé au super administrateur · Contenu chiffré AES-256-GCM</p>
    </div>
    <?php if ($vaultOpen): ?>
    <form method="POST" style="display:inline;">
      <input type="hidden" name="action" value="lock">
      <input type="hidden" name="csrf" value="<?= $csrf ?>">
      <button class="btn-lock" type="submit">🔒 Verrouiller</button>
    </form>
    <?php endif; ?>
  </div>

  <?php if ($vaultSuccess): ?>
    <div class="alert-ok"><?= htmlspecialchars($vaultSuccess) ?></div>
  <?php endif; ?>
  <?php if ($vaultError): ?>
    <div class="alert-err"><?= htmlspecialchars($vaultError) ?></div>
  <?php endif; ?>

  <?php if (!$vaultOpen): ?>
  <!-- ══ ÉCRAN DE VERROUILLAGE ══ -->
  <div class="lock-screen">
    <span class="lock-icon">🔐</span>
    <div class="lock-title">COFFRE VERROUILLÉ</div>
    <div class="lock-sub">Entrez votre mot de passe maître pour accéder à vos prompts</div>
    <form method="POST" autocomplete="off">
      <input type="hidden" name="action" value="unlock">
      <input type="hidden" name="csrf" value="<?= $csrf ?>">
      <input type="password" name="master_password" class="lock-input"
             placeholder="Mot de passe maître" autofocus autocomplete="new-password">
      <button type="submit" class="btn-unlock">🔓 Ouvrir le Coffre</button>
    </form>
    <div class="lock-warning">
      🛡️ Accès super admin uniquement<br>
      🔒 Verrouillage automatique après 15 min<br>
      🔑 Chiffrement AES-256-GCM · Clé dérivée PBKDF2
    </div>
  </div>

  <?php else: ?>
  <!-- ══ COFFRE OUVERT ══ -->

  <!-- Barre statut + timer -->
  <div class="vault-header">
    <div>
      <div class="vault-status">
        <div class="vault-dot"></div>
        <span class="vault-status-text">Coffre ouvert</span>
        <span class="vault-ttl">· <span id="timerDisplay">15:00</span> avant verrouillage</span>
      </div>
      <div class="timer-bar" style="margin-top:0.5rem;width:200px;">
        <div class="timer-fill" id="timerFill"></div>
      </div>
    </div>
    <div style="font-family:'Space Mono',monospace;font-size:0.6rem;color:var(--txt3);">
      <?= $nbPrompts ?> prompt<?= $nbPrompts>1?'s':'' ?> stocké<?= $nbPrompts>1?'s':'' ?>
    </div>
  </div>

  <div class="vault-layout">

    <!-- Formulaire ajout -->
    <div class="vault-form-card">
      <h3>➕ Nouveau Prompt</h3>
      <form method="POST" id="addForm">
        <input type="hidden" name="action" value="add">
        <input type="hidden" name="csrf" value="<?= $csrf ?>">
        <label class="vf-label">Titre</label>
        <input type="text" name="titre" class="vf-input" placeholder="ex: Prompt Génération Card Football" required>
        <label class="vf-label">Catégorie</label>
        <input type="text" name="categorie" class="vf-input" placeholder="ex: Cards, Analyse, SEO…" list="cats">
        <datalist id="cats">
          <?php foreach($categories as $c): ?>
          <option value="<?= htmlspecialchars($c) ?>">
          <?php endforeach; ?>
        </datalist>
        <label class="vf-label">Contenu du prompt</label>
        <textarea name="contenu" class="vf-textarea" placeholder="Collez votre prompt ici…" required></textarea>
        <button type="submit" class="btn-save-vault">🔐 Enregistrer</button>
      </form>
    </div>

    <!-- Liste prompts -->
    <div>
      <div class="vault-list-header">
        <h3>Mes Prompts</h3>
        <span class="vault-count"><?= $nbPrompts ?></span>
      </div>
      <input type="text" class="vault-search" id="vaultSearch" placeholder="🔍 Rechercher un prompt…" oninput="filterPrompts()">

      <?php if (empty($prompts)): ?>
        <div class="vault-empty">
          <span class="big">🗝️</span>
          Aucun prompt encore. Ajoutez votre premier prompt dans le formulaire.
        </div>
      <?php else: ?>
        <?php
        $currentCat = null;
        foreach ($prompts as $p):
          if ($p['categorie'] !== $currentCat):
            $currentCat = $p['categorie'];
        ?>
          <div class="cat-label prompt-item"><?= htmlspecialchars($currentCat) ?></div>
        <?php endif; ?>
        <div class="prompt-card prompt-item" data-search="<?= htmlspecialchars(strtolower($p['titre'] . ' ' . $p['categorie'] . ' ' . $p['contenu_clair'])) ?>">
          <div class="prompt-head" onclick="togglePrompt(<?= $p['id'] ?>)">
            <div>
              <div class="prompt-title"><?= htmlspecialchars($p['titre']) ?></div>
              <div class="prompt-meta">
                Modifié le <?= date('d/m/Y à H:i', strtotime($p['updated_at'])) ?>
                &nbsp;·&nbsp; <?= mb_strlen($p['contenu_clair']) ?> caractères
              </div>
            </div>
            <div class="prompt-actions">
              <button class="btn-edit-p" onclick="event.stopPropagation();openEdit(<?= $p['id'] ?>,<?= htmlspecialchars(json_encode($p['titre'])) ?>,<?= htmlspecialchars(json_encode($p['categorie'])) ?>,<?= htmlspecialchars(json_encode($p['contenu_clair'])) ?>)">✏️ Éditer</button>
              <button class="btn-del-p" onclick="event.stopPropagation();confirmDelete(<?= $p['id'] ?>,<?= htmlspecialchars(json_encode($p['titre'])) ?>)">🗑️</button>
            </div>
          </div>
          <div class="prompt-body" id="pb-<?= $p['id'] ?>">
            <div class="prompt-content-wrap">
              <div class="prompt-content" id="pc-<?= $p['id'] ?>"><?= htmlspecialchars($p['contenu_clair']) ?></div>
              <button class="btn-copy" onclick="copyPrompt(<?= $p['id'] ?>)">📋 Copier</button>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

  </div><!-- /.vault-layout -->

  <!-- Modal édition -->
  <div class="modal-overlay" id="editModal">
    <div class="modal-box">
      <button class="modal-close" onclick="closeEdit()">✕</button>
      <div class="modal-title">✏️ Modifier le Prompt</div>
      <form method="POST">
        <input type="hidden" name="action" value="edit">
        <input type="hidden" name="csrf" value="<?= $csrf ?>">
        <input type="hidden" name="id" id="edit-id">
        <label class="vf-label">Titre</label>
        <input type="text" name="titre" id="edit-titre" class="vf-input" required>
        <label class="vf-label">Catégorie</label>
        <input type="text" name="categorie" id="edit-cat" class="vf-input" list="cats">
        <label class="vf-label">Contenu</label>
        <textarea name="contenu" id="edit-contenu" class="vf-textarea" required style="min-height:300px;"></textarea>
        <button type="submit" class="btn-save-vault">💾 Sauvegarder</button>
      </form>
    </div>
  </div>

  <!-- Formulaire suppression (hidden) -->
  <form method="POST" id="deleteForm" style="display:none;">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="csrf" value="<?= $csrf ?>">
    <input type="hidden" name="id" id="delete-id">
  </form>

  <?php endif; // coffre ouvert ?>

</div><!-- /.main -->

<script>
// ── Timer auto-lock ───────────────────────────────────────
<?php if ($vaultOpen): ?>
const TTL = <?= VAULT_SESSION_TTL ?>;
let remaining = TTL;
const fill  = document.getElementById('timerFill');
const disp  = document.getElementById('timerDisplay');
function updateTimer() {
  remaining--;
  if (remaining <= 0) {
    window.location.href = 'vault.php';
    return;
  }
  const m = String(Math.floor(remaining/60)).padStart(2,'0');
  const s = String(remaining%60).padStart(2,'0');
  disp.textContent = m + ':' + s;
  fill.style.width = (remaining/TTL*100) + '%';
  // Couleur urgence < 2min
  if (remaining < 120) fill.style.background = 'linear-gradient(90deg,#ff6b00,#ff2d78)';
}
setInterval(updateTimer, 1000);

// Reset timer sur activité
['mousemove','keydown','click','scroll'].forEach(ev => {
  document.addEventListener(ev, () => { remaining = TTL; fill.style.background = ''; }, {passive:true});
});

// ── Toggle prompt ─────────────────────────────────────────
function togglePrompt(id) {
  const body = document.getElementById('pb-' + id);
  body.classList.toggle('open');
}

// ── Copier ────────────────────────────────────────────────
function copyPrompt(id) {
  const text = document.getElementById('pc-' + id).textContent;
  navigator.clipboard.writeText(text).then(() => {
    const btn = event.target;
    btn.textContent = '✅ Copié !';
    setTimeout(() => { btn.textContent = '📋 Copier'; }, 2000);
  });
}

// ── Modal édition ─────────────────────────────────────────
function openEdit(id, titre, cat, contenu) {
  document.getElementById('edit-id').value      = id;
  document.getElementById('edit-titre').value   = titre;
  document.getElementById('edit-cat').value     = cat;
  document.getElementById('edit-contenu').value = contenu;
  document.getElementById('editModal').classList.add('open');
}
function closeEdit() {
  document.getElementById('editModal').classList.remove('open');
}
document.getElementById('editModal').addEventListener('click', e => {
  if (e.target === e.currentTarget) closeEdit();
});

// ── Suppression ───────────────────────────────────────────
function confirmDelete(id, titre) {
  if (confirm('Supprimer définitivement "' + titre + '" ?\nCette action est irréversible.')) {
    document.getElementById('delete-id').value = id;
    document.getElementById('deleteForm').submit();
  }
}

// ── Recherche ─────────────────────────────────────────────
function filterPrompts() {
  const q = document.getElementById('vaultSearch').value.toLowerCase().trim();
  document.querySelectorAll('.prompt-item').forEach(el => {
    const text = el.dataset.search || el.textContent.toLowerCase();
    el.style.display = (!q || text.includes(q)) ? '' : 'none';
  });
}
<?php endif; ?>
</script>

</body>
</html>
