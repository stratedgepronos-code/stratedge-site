<?php
// ============================================================
// STRATEDGE — Footer légal — inclure sur toutes les pages
// ============================================================
?>
<style>
/* ── Footer légal ─────────────────────────────────────────── */
.legal-footer {
  background: #020508;
  border-top: 1px solid rgba(255,255,255,0.06);
  padding: 2rem 1.5rem 1.5rem;
}
.legal-footer-inner {
  max-width: 1000px;
  margin: 0 auto;
}

/* Bandeau ANJ — la ligne la plus visible */
.legal-anj-bar {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 1rem;
  background: rgba(255,200,0,0.06);
  border: 1px solid rgba(255,200,0,0.2);
  border-radius: 10px;
  padding: 0.9rem 1.5rem;
  margin-bottom: 1.5rem;
  flex-wrap: wrap;
  text-align: center;
}
.legal-anj-icon { font-size: 1.4rem; flex-shrink: 0; }
.legal-anj-text {
  font-size: 0.82rem;
  color: #ffd700;
  font-weight: 600;
  line-height: 1.5;
}
.legal-anj-phone {
  font-family: 'Orbitron', monospace;
  font-size: 1rem;
  font-weight: 900;
  color: #ffd700;
  letter-spacing: 1px;
  white-space: nowrap;
}

/* Bloc disclaimer principal */
.legal-disclaimer-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1rem;
  margin-bottom: 1.2rem;
}
@media (max-width: 640px) {
  .legal-disclaimer-grid { grid-template-columns: 1fr; }
}

.legal-disclaimer-box {
  background: rgba(255,255,255,0.02);
  border: 1px solid rgba(255,255,255,0.06);
  border-radius: 8px;
  padding: 0.8rem 1rem;
}
.legal-disclaimer-box h5 {
  font-size: 0.72rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 1px;
  color: rgba(255,255,255,0.4);
  margin: 0 0 0.4rem 0;
}
.legal-disclaimer-box p {
  font-size: 0.75rem;
  color: rgba(255,255,255,0.35);
  line-height: 1.55;
  margin: 0;
}

/* Ligne du bas */
.legal-bottom-bar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 0.5rem;
  padding-top: 1rem;
  border-top: 1px solid rgba(255,255,255,0.04);
}
.legal-bottom-bar span {
  font-size: 0.68rem;
  color: rgba(255,255,255,0.2);
}
.legal-bottom-bar a {
  font-size: 0.68rem;
  color: rgba(255,255,255,0.3);
  text-decoration: none;
  transition: color .2s;
}
.legal-bottom-bar a:hover { color: rgba(255,255,255,0.6); }
.legal-links { display: flex; gap: 1.2rem; flex-wrap: wrap; }

/* Badge 18+ */
.legal-18-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 32px; height: 32px;
  border: 2px solid rgba(255,100,100,0.5);
  border-radius: 50%;
  color: rgba(255,100,100,0.7);
  font-size: 0.65rem;
  font-weight: 900;
  flex-shrink: 0;
}
</style>

<footer class="legal-footer">
  <div class="legal-footer-inner">

    <!-- Bandeau Joueurs Info Service (obligatoire ANJ) -->
    <div class="legal-anj-bar">
      <div class="legal-anj-icon">⚠️</div>
      <div class="legal-anj-text">
        Les jeux d'argent comportent des risques : endettement, dépendance, isolement.<br>
        <strong>Joueurs Info Service</strong> — aide gratuite et confidentielle :
      </div>
      <div class="legal-anj-phone">📞 09 74 75 13 13</div>
      <div class="legal-18-badge">18+</div>
    </div>

    <!-- Grille de disclaimers -->
    <div class="legal-disclaimer-grid">

      <div class="legal-disclaimer-box">
        <h5>📊 Nature du service</h5>
        <p>StratEdge est un service d'<strong style="color:rgba(255,255,255,0.5)">analyse sportive à titre informatif</strong>. Nous ne sommes pas un opérateur de paris en ligne et ne proposons aucun pari. Nos contenus constituent des opinions analytiques, pas des conseils financiers.</p>
      </div>

      <div class="legal-disclaimer-box">
        <h5>⚠️ Aucune garantie de gain</h5>
        <p>Les résultats sportifs sont <strong style="color:rgba(255,255,255,0.5)">par nature aléatoires</strong>. Aucun pronostic ne peut garantir un gain. Les performances passées ne préjugent pas des résultats futurs. Nous ne remboursons pas les analyses en cas de bet perdant.</p>
      </div>

      <div class="legal-disclaimer-box">
        <h5>🔞 Interdiction aux mineurs</h5>
        <p>L'accès à ce service est <strong style="color:rgba(255,255,255,0.5)">strictement réservé aux personnes majeures</strong> (18 ans et plus). En vous inscrivant, vous attestez être majeur dans votre pays de résidence.</p>
      </div>

      <div class="legal-disclaimer-box">
        <h5>💰 Jeu responsable</h5>
        <p>Ne misez jamais plus que ce que vous pouvez vous permettre de perdre. Fixez-vous des <strong style="color:rgba(255,255,255,0.5)">limites de budget</strong> et respectez-les. En cas de doute sur votre relation au jeu, contactez le 09 74 75 13 13.</p>
      </div>

    </div>

  </div>
</footer>

<?php
// ── Web Push — inscription automatique sur toutes les pages ──
// Charger la config VAPID si pas encore faite
if (!defined('VAPID_PUBLIC_KEY') && file_exists(__DIR__ . '/vapid-config.php')) {
    require_once __DIR__ . '/vapid-config.php';
}
if (function_exists('isLoggedIn') && isLoggedIn() && defined('VAPID_PUBLIC_KEY') && VAPID_PUBLIC_KEY !== 'VOTRE_CLE_PUBLIQUE_VAPID_ICI'):
?>
<script>
(function(){
  const VAPID_KEY = '<?= VAPID_PUBLIC_KEY ?>';
  if (!('serviceWorker' in navigator) || !('PushManager' in window) || !VAPID_KEY) return;

  function urlBase64ToUint8Array(b64) {
    var p = '='.repeat((4 - b64.length % 4) % 4);
    var raw = atob((b64 + p).replace(/-/g, '+').replace(/_/g, '/'));
    return new Uint8Array([...raw].map(function(c){ return c.charCodeAt(0); }));
  }

  navigator.serviceWorker.register('/sw.js').then(function(reg) {
    return reg.pushManager.getSubscription().then(function(existing) {
      if (existing) return; // Deja abonne
      if (Notification.permission === 'denied') return;
      if (Notification.permission === 'default') {
        return Notification.requestPermission().then(function(perm) {
          if (perm !== 'granted') return;
          return subscribe(reg);
        });
      }
      if (Notification.permission === 'granted') return subscribe(reg);
    });
  }).catch(function(e){ console.log('[Push] Init:', e.message); });

  function subscribe(reg) {
    return reg.pushManager.subscribe({
      userVisibleOnly: true,
      applicationServerKey: urlBase64ToUint8Array(VAPID_KEY)
    }).then(function(sub) {
      return fetch('/push-subscribe.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(sub)
      });
    }).then(function() {
      console.log('[Push] Abonne OK');
    }).catch(function(e) {
      console.log('[Push] Subscribe:', e.message);
    });
  }
})();
</script>
<?php endif; ?>
