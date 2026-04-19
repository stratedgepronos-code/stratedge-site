<?php
// ============================================================
// STRATEDGE — Diagnostic Push Notifications
// /notifications-diag.php
// Aide les utilisateurs à comprendre pourquoi les push ne marchent pas
// ============================================================
require_once __DIR__ . '/includes/auth.php';
if (file_exists(__DIR__ . '/includes/vapid-config.php')) {
    require_once __DIR__ . '/includes/vapid-config.php';
}

$membre = isLoggedIn() ? getMembre() : null;
$currentPage = 'dashboard';
$vapidOk = defined('VAPID_PUBLIC_KEY') && VAPID_PUBLIC_KEY !== '' && VAPID_PUBLIC_KEY !== 'VOTRE_CLE_PUBLIQUE_VAPID_ICI';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Diagnostic Notifications — StratEdge</title>
<link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Rajdhani:wght@400;600;700&display=swap" rel="stylesheet">
<?php require_once __DIR__ . '/includes/sidebar-css.php'; ?>
<style>
body{background:#050810;color:#f0f4f8;font-family:'Rajdhani',sans-serif;margin:0;}
.diag-wrap{max-width:700px;margin:2rem auto;padding:1.5rem;}
h1{font-family:'Orbitron',sans-serif;font-size:1.4rem;color:#ff2d78;margin-bottom:0.5rem;}
.sub{color:rgba(255,255,255,0.5);margin-bottom:2rem;font-size:0.95rem;}
.check{background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.08);border-radius:12px;padding:1rem 1.2rem;margin-bottom:0.8rem;display:flex;align-items:center;gap:1rem;}
.check-icon{font-size:1.5rem;flex-shrink:0;}
.check-main{flex:1;}
.check-label{font-weight:600;color:#f0f4f8;margin-bottom:0.2rem;}
.check-value{font-size:0.85rem;color:rgba(255,255,255,0.55);font-family:'Share Tech Mono',monospace;}
.ok{border-color:rgba(0,212,106,0.3);}
.ok .check-icon{color:#00d46a;}
.ko{border-color:rgba(255,68,68,0.3);background:rgba(255,68,68,0.04);}
.ko .check-icon{color:#ff4444;}
.warn{border-color:rgba(255,193,7,0.3);background:rgba(255,193,7,0.04);}
.warn .check-icon{color:#ffc107;}
.advice{background:linear-gradient(135deg,rgba(255,45,120,0.08),rgba(0,212,255,0.05));border:1px solid rgba(255,45,120,0.2);border-radius:14px;padding:1.2rem;margin-top:1.5rem;}
.advice h3{font-family:'Orbitron',sans-serif;font-size:1rem;color:#ff2d78;margin-bottom:0.6rem;}
.advice p, .advice li{color:rgba(255,255,255,0.75);font-size:0.92rem;line-height:1.6;}
.advice ol{padding-left:1.5rem;}
.btn-test{display:inline-block;margin-top:1rem;padding:0.8rem 1.8rem;background:linear-gradient(135deg,#ff2d78,#c850c0);border:none;border-radius:10px;color:#fff;font-family:'Orbitron',sans-serif;font-weight:700;letter-spacing:1px;cursor:pointer;font-size:0.85rem;text-decoration:none;}
.btn-back{display:inline-block;margin-top:1rem;color:#00d4ff;text-decoration:none;font-size:0.9rem;}
</style>
</head>
<body>
<?php if ($membre) require_once __DIR__ . '/includes/sidebar.php'; ?>

<div class="diag-wrap">
  <h1>🔧 Diagnostic Notifications Push</h1>
  <p class="sub">Cette page analyse automatiquement ta configuration pour comprendre pourquoi les notifications push ne fonctionnent pas.</p>

  <div id="results"></div>

  <div id="advice-box"></div>

  <a href="/dashboard.php" class="btn-back">← Retour au Dashboard</a>
</div>

<script>
var VK = '<?= $vapidOk ? VAPID_PUBLIC_KEY : "" ?>';
var vapidOk = <?= $vapidOk ? 'true' : 'false' ?>;
var results = document.getElementById('results');
var adviceBox = document.getElementById('advice-box');
var ua = navigator.userAgent;

// Détection
var isIOS = /iPhone|iPad|iPod/.test(ua);
var isAndroid = /Android/.test(ua);
var isSafari = /Safari/.test(ua) && !/Chrome|CriOS|FxiOS|EdgiOS|Edg/.test(ua);
var isChrome = /Chrome|CriOS/.test(ua) && !/Edg/.test(ua);
var isFirefox = /Firefox|FxiOS/.test(ua);
var isStandalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
var iosVersion = 0;
if (isIOS) {
  var m = ua.match(/OS (\d+)_(\d+)/);
  if (m) iosVersion = parseFloat(m[1] + '.' + m[2]);
}

var device = isIOS ? 'iPhone/iPad' : (isAndroid ? 'Android' : 'PC');
var browser = isFirefox ? 'Firefox' : (isSafari ? 'Safari' : (isChrome ? 'Chrome' : 'Autre navigateur'));

function addCheck(status, icon, label, value) {
  var div = document.createElement('div');
  div.className = 'check ' + status;
  div.innerHTML = '<div class="check-icon">' + icon + '</div>' +
                  '<div class="check-main"><div class="check-label">' + label + '</div>' +
                  '<div class="check-value">' + value + '</div></div>';
  results.appendChild(div);
}

function showAdvice(html) {
  adviceBox.innerHTML = '<div class="advice">' + html + '</div>';
}

// === Checks ===

// 1. Device
addCheck('ok', '📱', 'Appareil détecté', device + ' · ' + browser + (iosVersion ? ' · iOS ' + iosVersion : ''));

// 2. VAPID key
if (vapidOk) {
  addCheck('ok', '🔑', 'Clé VAPID serveur', 'Configurée (' + VK.substring(0, 20) + '...)');
} else {
  addCheck('ko', '🔑', 'Clé VAPID serveur', 'MANQUANTE — problème côté serveur');
}

// 3. Service Worker support
if ('serviceWorker' in navigator) {
  addCheck('ok', '⚙️', 'Service Worker', 'Supporté par ce navigateur');
} else {
  addCheck('ko', '⚙️', 'Service Worker', 'NON supporté par ce navigateur');
}

// 4. Push API support
if ('PushManager' in window) {
  addCheck('ok', '🔔', 'Push API', 'Supportée');
} else {
  addCheck('ko', '🔔', 'Push API', 'NON supportée par ce navigateur');
}

// 5. Notification API
if ('Notification' in window) {
  var perm = Notification.permission;
  if (perm === 'granted') addCheck('ok', '✅', 'Permission notifications', 'Accordée');
  else if (perm === 'denied') addCheck('ko', '🚫', 'Permission notifications', 'Bloquée par l\'utilisateur');
  else addCheck('warn', '⏳', 'Permission notifications', 'Pas encore demandée');
} else {
  addCheck('ko', '🔔', 'API Notification', 'Non disponible');
}

// 6. iOS specific
if (isIOS) {
  if (iosVersion < 16.4) {
    addCheck('ko', '❌', 'Version iOS', 'iOS ' + iosVersion + ' — les notifications push nécessitent iOS 16.4 ou supérieur');
  } else {
    addCheck('ok', '📱', 'Version iOS', 'iOS ' + iosVersion + ' (16.4+ OK)');
  }
  if (!isStandalone) {
    addCheck('ko', '📲', 'Mode PWA (écran d\'accueil)', 'Site PAS installé sur l\'écran d\'accueil — OBLIGATOIRE sur iPhone');
  } else {
    addCheck('ok', '📲', 'Mode PWA (écran d\'accueil)', 'Installé ✓');
  }
}

// 7. Check existing subscription
(async function() {
  if (!('serviceWorker' in navigator) || !('PushManager' in window)) return;
  try {
    var reg = await navigator.serviceWorker.getRegistration('/');
    if (reg) {
      addCheck('ok', '🛠️', 'Service Worker enregistré', 'Actif depuis ' + (reg.active ? 'oui' : 'non'));
      var sub = await reg.pushManager.getSubscription();
      if (sub) {
        addCheck('ok', '📬', 'Souscription push active', 'Endpoint: ' + sub.endpoint.substring(0, 60) + '...');
      } else {
        addCheck('warn', '📭', 'Souscription push', 'Aucune souscription — clique sur "Activer" dans le Dashboard');
      }
    } else {
      addCheck('warn', '🛠️', 'Service Worker enregistré', 'Pas encore enregistré (visite le Dashboard d\'abord)');
    }
  } catch (e) {
    addCheck('ko', '❌', 'Erreur SW', e.message);
  }

  // === Advice ===
  setTimeout(function() {
    var advice = '';
    if (isIOS && iosVersion < 16.4) {
      advice = '<h3>❌ iOS trop ancien</h3><p>Les notifications push web ne fonctionnent que sur <strong>iOS 16.4 ou supérieur</strong>. Ta version actuelle est iOS ' + iosVersion + '.</p><p>Va dans <strong>Réglages → Général → Mise à jour logicielle</strong> et installe la dernière version d\'iOS.</p>';
    } else if (isIOS && !isStandalone) {
      advice = '<h3>📱 Installation PWA requise</h3><p>Sur iPhone, les notifications ne fonctionnent <strong>QUE</strong> si tu ouvres le site depuis l\'icône sur ton écran d\'accueil. C\'est une contrainte Apple.</p><ol><li>Ouvre <a href="https://stratedgepronos.fr" style="color:#ff2d78;">stratedgepronos.fr</a> dans <strong>Safari</strong> (pas Chrome)</li><li>Appuie sur <strong>Partager</strong> 􀈂 (bouton en bas)</li><li>Choisis <strong>"Sur l\'écran d\'accueil"</strong> → Ajouter</li><li>Ferme Safari, ouvre StratEdge depuis ton <strong>écran d\'accueil</strong></li><li>Reviens sur cette page pour vérifier le diagnostic</li></ol>';
    } else if (Notification.permission === 'denied') {
      advice = '<h3>🚫 Permission bloquée</h3>';
      if (isIOS) {
        advice += '<p>Tu as refusé la permission précédemment. Pour la réactiver :</p><ol><li>Réglages (iOS) → StratEdge → Notifications → Autoriser</li><li>Ou : désinstalle l\'icône et réinstalle via Safari → Partager → Sur l\'écran d\'accueil</li></ol>';
      } else if (isAndroid) {
        advice += '<p>Pour réactiver :</p><ol><li>Dans Chrome : ⋮ (menu) → Paramètres → Paramètres des sites → Notifications</li><li>Cherche <strong>stratedgepronos.fr</strong> et change en <strong>Autorisé</strong></li><li>Reviens sur le Dashboard</li></ol>';
      } else {
        advice += '<p>Clique sur 🔒 à gauche de l\'URL → Notifications → Autoriser, puis recharge la page.</p>';
      }
    } else if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
      advice = '<h3>❌ Navigateur incompatible</h3><p>Ton navigateur ne supporte pas les notifications push. Utilise plutôt :</p><ul><li>PC : <strong>Chrome, Firefox ou Edge</strong></li><li>Android : <strong>Chrome ou Firefox</strong></li><li>iPhone : <strong>Safari</strong> (iOS 16.4+) avec le site installé sur l\'écran d\'accueil</li></ul>';
    } else {
      advice = '<h3>✅ Configuration OK</h3><p>Tout semble bon de ton côté ! Va sur le <a href="/dashboard.php" style="color:#ff2d78;">Dashboard</a> et clique sur <strong>"Activer les notifications"</strong>.</p><p>Si ça ne fonctionne toujours pas, <a href="/sav.php" style="color:#ff2d78;">contacte le support</a> avec une capture de cette page.</p>';
    }
    showAdvice(advice);
  }, 500);
})();
</script>
</body>
</html>
