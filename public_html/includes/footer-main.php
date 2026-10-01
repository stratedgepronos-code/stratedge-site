<?php
// ============================================================
// STRATEDGE — Footer principal — inclure sur toutes les pages
// ============================================================
?>
<style>
footer { background: #050810; padding: 0; }
.footer-glow { position: relative; }
.footer-main { max-width: 1200px; margin: 0 auto; padding: 4rem 2rem 3rem; display: grid; grid-template-columns: 2fr 1fr 1fr 1fr; gap: 3rem; }
.footer-brand p { color: rgba(255,255,255,0.45); font-size: 0.85rem; line-height: 1.7; margin-top: 1rem; max-width: 300px; }
.footer-brand-logo { height: 40px; width: auto; margin-bottom: 0.5rem; }
.footer-social { display: flex; gap: 0.8rem; margin-top: 1.5rem; }
.footer-social a { width: 40px; height: 40px; border-radius: 10px; background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.08); display: flex; align-items: center; justify-content: center; font-size: 1.1rem; text-decoration: none; transition: all 0.3s ease; }
.footer-social a:hover { background: rgba(255,45,120,0.15); border-color: #ff2d78; transform: translateY(-3px); color: #ff2d78 !important; }
.footer-col h4 { font-family: 'Orbitron', sans-serif; font-size: 0.8rem; font-weight: 700; letter-spacing: 2px; text-transform: uppercase; color: #f0f4f8; margin-bottom: 1.5rem; position: relative; }
.footer-col h4::after { content: ''; position: absolute; bottom: -8px; left: 0; width: 25px; height: 2px; background: #ff2d78; border-radius: 2px; }
.footer-col ul { list-style: none; padding: 0; }
.footer-col ul li { margin-bottom: 0.8rem; }
.footer-col ul li a { color: rgba(255,255,255,0.45); text-decoration: none; font-size: 0.85rem; transition: all 0.3s ease; display: flex; align-items: center; gap: 0.5rem; }
.footer-col ul li a:hover { color: #ff2d78; transform: translateX(5px); }
.footer-bottom { max-width: 1200px; margin: 0 auto; padding: 2rem; border-top: 1px solid rgba(255,255,255,0.05); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; }
.footer-copy { color: rgba(255,255,255,0.45); font-size: 0.78rem; }
.footer-copy a { color: #ff2d78; text-decoration: none; }
.footer-links-legal { display: flex; gap: 1.5rem; }
.footer-links-legal a { color: rgba(255,255,255,0.45); text-decoration: none; font-size: 0.75rem; transition: color 0.3s; }
.footer-links-legal a:hover { color: #f0f4f8; }
@media (max-width: 900px) { .footer-main { grid-template-columns: 1fr 1fr; gap: 2rem; } }
@media (max-width: 600px) { .footer-main { grid-template-columns: 1fr; gap: 2rem; } .footer-bottom { flex-direction: column; text-align: center; } .footer-links-legal { justify-content: center; flex-wrap: wrap; } }
</style>

<footer>
  <div class="footer-glow">
    <?php require_once __DIR__ . '/footer-legal.php'; ?>
    <div class="footer-main">
      <div class="footer-brand">
        <img src="/assets/images/logo site.png" alt="StratEdge Pronos" class="footer-brand-logo">
        <p>Analyse statistique, croisement de data et bets LIVE pour maximiser tes gains. 11+ ans d'expérience au service de tes paris.</p>
        <div class="footer-social">
          <a href="https://twitter.com/StratedgePronos" target="_blank" title="X / Twitter" style="color:#fff;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-4.714-6.231-5.401 6.231H2.748l7.73-8.835L1.254 2.25H8.08l4.253 5.622 5.911-5.622zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
          </a>
          <a href="https://discord.gg/" target="_blank" title="Discord" style="color:#fff;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M20.317 4.37a19.791 19.791 0 0 0-4.885-1.515.074.074 0 0 0-.079.037c-.21.375-.444.864-.608 1.25a18.27 18.27 0 0 0-5.487 0 12.64 12.64 0 0 0-.617-1.25.077.077 0 0 0-.079-.037A19.736 19.736 0 0 0 3.677 4.37a.07.07 0 0 0-.032.027C.533 9.046-.32 13.58.099 18.057a.082.082 0 0 0 .031.057 19.9 19.9 0 0 0 5.993 3.03.078.078 0 0 0 .084-.028c.462-.63.874-1.295 1.226-1.994a.076.076 0 0 0-.041-.106 13.107 13.107 0 0 1-1.872-.892.077.077 0 0 1-.008-.128 10.2 10.2 0 0 0 .372-.292.074.074 0 0 1 .077-.01c3.928 1.793 8.18 1.793 12.062 0a.074.074 0 0 1 .078.01c.12.098.246.198.373.292a.077.077 0 0 1-.006.127 12.299 12.299 0 0 1-1.873.892.077.077 0 0 0-.041.107c.36.698.772 1.362 1.225 1.993a.076.076 0 0 0 .084.028 19.839 19.839 0 0 0 6.002-3.03.077.077 0 0 0 .032-.054c.5-5.177-.838-9.674-3.549-13.66a.061.061 0 0 0-.031-.03zM8.02 15.33c-1.183 0-2.157-1.085-2.157-2.419 0-1.333.956-2.419 2.157-2.419 1.21 0 2.176 1.096 2.157 2.42 0 1.333-.956 2.418-2.157 2.418zm7.975 0c-1.183 0-2.157-1.085-2.157-2.419 0-1.333.955-2.419 2.157-2.419 1.21 0 2.176 1.096 2.157 2.42 0 1.333-.946 2.418-2.157 2.418z"/></svg>
          </a>
        </div>
      </div>
      <div class="footer-col"><h4>Navigation</h4><ul>
        <li><a href="/#why">› Pourquoi nous</a></li>
        <li><a href="/#how">› Comment ça marche</a></li>
        <li><a href="/#pricing">› Tarifs</a></li>
        <li><a href="/bets.php">› Les Bets</a></li>
        <li><a href="/#reviews">› Avis clients</a></li>
      </ul></div>
      <div class="footer-col"><h4>Compte</h4><ul>
        <li><a href="/login.php">› Connexion</a></li>
        <li><a href="/register.php">› Inscription</a></li>
        <li><a href="/dashboard.php">› Mon espace</a></li>
        <li><a href="/sav.php">› SAV / Support</a></li>
      </ul></div>
      <div class="footer-col"><h4>Paiements</h4><ul>
        <li><a href="/#pricing">📱 SMS / Appel</a></li>
        <li><a href="/#pricing">💳 Carte bancaire</a></li>
        <li><a href="/#pricing">₿ Crypto (BTC, ETH…)</a></li>
      </ul></div>
    </div>
    <div class="footer-bottom">
      <div class="footer-copy">© <?= date('Y') ?> <a href="/">StratEdge Pronos</a> — Tous droits réservés</div>
      <div class="footer-links-legal">
        <a href="/mentions-legales.php">Mentions légales</a>
        <a href="/cgv.php">CGV</a>
        <a href="/sav.php">Support</a>
        <a href="https://www.joueurs-info-service.fr" target="_blank" rel="noopener">Joueurs Info Service</a>
      </div>
    </div>
  </div>
</footer>
