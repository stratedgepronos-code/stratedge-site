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
        <p>Analyses sportives, données et contexte pour éclairer vos décisions. Le journal et les résultats sont accessibles librement.</p>
        <div class="footer-social">
          <a href="https://x.com/strat_edge_" target="_blank" title="X / Twitter" style="color:#fff;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-4.714-6.231-5.401 6.231H2.748l7.73-8.835L1.254 2.25H8.08l4.253 5.622 5.911-5.622zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
          </a>

        </div>
      </div>
      <div class="footer-col"><h4>Navigation</h4><ul>
        <li><a href="/methode.php">› Pourquoi nous</a></li>
        <li><a href="/methode.php">› Comment ça marche</a></li>
        <li><a href="/offres.php">› Tarifs</a></li>
        <li><a href="/bets.php">› Les Bets</a></li>
        <li><a href="/journal.php">› Le journal</a></li>
      </ul></div>
      <div class="footer-col"><h4>Compte</h4><ul>
        <li><a href="/login.php">› Connexion</a></li>
        <li><a href="/register.php">› Inscription</a></li>
        <li><a href="/dashboard.php">› Mon espace</a></li>
        <li><a href="/sav.php">› SAV / Support</a></li>
      </ul></div>
      <div class="footer-col"><h4>Paiements</h4><ul>
        <li><a href="/offres.php">📱 SMS / Appel</a></li>
        <li><a href="/offres.php">💳 Carte bancaire</a></li>
        <li><a href="/offres.php">₿ Crypto (BTC, ETH…)</a></li>
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
