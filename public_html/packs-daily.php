<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/packs-config.php';
require_once __DIR__ . '/includes/credits-manager.php';

$membre = isLoggedIn() ? getMembre() : null;
$currentPage = 'packs-daily';
$avatarUrl = $membre ? getAvatarUrl($membre) : null;
$solde = $membre ? stratedge_credits_solde((int)$membre['id']) : 0;
$packs = stratedge_packs_config();

include __DIR__ . '/includes/header.php';
?>
<style>
.packs-wrap{max-width:1400px;margin:2rem auto;padding:0 1.5rem;color:#fff;font-family:'Rajdhani',sans-serif}
.packs-hero{text-align:center;margin-bottom:3rem}
.packs-hero h1{font-family:'Orbitron',sans-serif;font-size:clamp(2rem,5vw,3.5rem);font-weight:900;background:linear-gradient(135deg,#ff2d7a,#c850c0,#00d4ff);-webkit-background-clip:text;-webkit-text-fill-color:transparent;margin:0 0 1rem}
.packs-hero p{font-size:1.15rem;color:rgba(255,255,255,0.7);max-width:700px;margin:0 auto 1rem}
.packs-bar{display:inline-flex;align-items:center;gap:.8rem;background:rgba(0,212,255,0.08);border:1px solid rgba(0,212,255,0.3);padding:.8rem 1.5rem;border-radius:50px;margin-top:1rem}
.packs-bar strong{color:#00d4ff;font-family:'Orbitron',sans-serif;font-size:1.3rem}
.packs-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:1.5rem;margin-bottom:3rem}
@media(max-width:1024px){.packs-grid{grid-template-columns:repeat(2,1fr)}}
@media(max-width:640px){.packs-grid{grid-template-columns:1fr}}
.pack-card{position:relative;background:linear-gradient(180deg,rgba(20,15,30,0.95),rgba(10,10,25,0.95));border:1px solid rgba(255,45,122,0.25);border-radius:20px;padding:2rem 1.5rem;transition:all .3s ease;overflow:hidden}
.pack-card::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;background:linear-gradient(90deg,#ff2d7a,#c850c0,#00d4ff)}
.pack-card:hover{transform:translateY(-6px);border-color:rgba(0,212,255,0.5);box-shadow:0 20px 60px rgba(200,80,192,0.2)}
.pack-card.featured{border-color:rgba(0,212,255,0.6);box-shadow:0 0 40px rgba(0,212,255,0.15)}
.pack-badge{position:absolute;top:1rem;right:1rem;background:linear-gradient(135deg,#ff2d7a,#c850c0);color:#fff;padding:.35rem .8rem;border-radius:20px;font-family:'Orbitron',sans-serif;font-size:.65rem;font-weight:700;letter-spacing:1px;text-transform:uppercase}
.pack-label{font-family:'Orbitron',sans-serif;font-size:1.4rem;font-weight:900;color:#fff;margin:.5rem 0}
.pack-sub{font-size:.85rem;color:rgba(255,255,255,0.5);margin-bottom:1.5rem;min-height:1.2em}
.pack-nb{font-family:'Bebas Neue',sans-serif;font-size:4.5rem;line-height:1;background:linear-gradient(135deg,#00d4ff,#c850c0);-webkit-background-clip:text;-webkit-text-fill-color:transparent;margin-bottom:.2rem}
.pack-nb-label{font-size:.9rem;color:rgba(255,255,255,0.6);text-transform:uppercase;letter-spacing:1.5px;margin-bottom:1.5rem}
.pack-price{font-family:'Orbitron',sans-serif;font-size:2.2rem;font-weight:900;color:#fff;margin-bottom:.3rem}
.pack-price .cur{font-size:1.3rem;color:#00d4ff;vertical-align:super}
.pack-unit{font-size:.8rem;color:rgba(255,255,255,0.5);margin-bottom:.4rem}
.pack-eco{display:inline-block;background:rgba(57,255,20,0.1);border:1px solid rgba(57,255,20,0.3);color:#39ff14;padding:.2rem .7rem;border-radius:12px;font-size:.75rem;font-weight:700;margin-bottom:1.5rem;font-family:'Orbitron',sans-serif}
.pack-methodes{display:flex;flex-direction:column;gap:.5rem}
.pack-btn{display:flex;align-items:center;justify-content:center;gap:.5rem;padding:.75rem;border-radius:10px;font-family:'Orbitron',sans-serif;font-size:.75rem;font-weight:700;letter-spacing:1px;text-transform:uppercase;text-decoration:none;transition:all .2s;cursor:pointer;border:none}
.pack-btn-cb{background:linear-gradient(135deg,#ff2d7a,#c850c0);color:#fff}
.pack-btn-cb:hover{transform:translateY(-2px);box-shadow:0 8px 20px rgba(200,80,192,0.4)}
.pack-btn-crypto{background:rgba(255,255,255,0.05);color:#ffc107;border:1px solid rgba(255,193,7,0.3)}
.pack-btn-crypto:hover{background:rgba(255,193,7,0.1);border-color:rgba(255,193,7,0.5)}
.pack-btn-sms{background:rgba(255,255,255,0.05);color:#00d4ff;border:1px solid rgba(0,212,255,0.3)}
.pack-btn-sms:hover{background:rgba(0,212,255,0.1)}
.packs-faq{max-width:800px;margin:3rem auto;padding:0 1rem}
.packs-faq h2{font-family:'Orbitron',sans-serif;text-align:center;font-size:1.8rem;margin-bottom:2rem;color:#fff}
.faq-item{background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.08);border-radius:12px;padding:1.2rem 1.5rem;margin-bottom:.8rem}
.faq-item strong{color:#00d4ff;display:block;margin-bottom:.4rem;font-family:'Orbitron',sans-serif;font-size:.95rem}
.faq-item p{color:rgba(255,255,255,0.7);margin:0;font-size:.95rem}
</style>

<div class="packs-wrap">
  <div class="packs-hero">
    <h1>Packs Paris</h1>
    <p>Achète tes paris à l'unité ou en pack. <strong style="color:#00d4ff">Crédits à vie</strong> — utilise-les quand tu veux, sans limite de temps.</p>
    <?php if($membre):?><div class="packs-bar">💎 Tes crédits disponibles : <strong><?= $solde ?></strong></div><?php endif;?>
  </div>

  <div class="packs-grid">
    <?php foreach($packs as $k=>$p): $featured = in_array($k,['trio','quinte']); ?>
    <div class="pack-card <?= $featured?'featured':'' ?>">
      <?php if($p['badge']):?><div class="pack-badge"><?= htmlspecialchars($p['badge']) ?></div><?php endif;?>
      <div class="pack-label"><?= htmlspecialchars($p['label']) ?></div>
      <div class="pack-sub"><?= htmlspecialchars($p['sub']) ?></div>
      <div class="pack-nb"><?= $p['nb'] ?></div>
      <div class="pack-nb-label"><?= $p['nb']>1?'paris':'pari' ?></div>
      <div class="pack-price"><span class="cur">€</span><?= number_format($p['prix'],2,',','') ?></div>
      <div class="pack-unit"><?= number_format($p['prix_unit'],2,',','') ?>€ / pari</div>
      <?php if($p['economie']>0):?><div class="pack-eco">-<?= $p['economie'] ?>% d'économie</div><?php else:?><div style="height:1.5rem"></div><?php endif;?>
      <div class="pack-methodes">
        <?php if(in_array('stripe',$p['methodes'])):?>
        <a href="/api/stripe-create-pack.php?pack=<?= $k ?>" class="pack-btn pack-btn-cb">💳 Carte bancaire</a>
        <?php endif;?>
        <?php if(in_array('crypto',$p['methodes'])):?>
        <a href="/api/nowpayments-create-pack.php?pack=<?= $k ?>" class="pack-btn pack-btn-crypto">₿ Crypto</a>
        <?php endif;?>
        <?php if(in_array('sms',$p['methodes'])):?>
        <a href="/api/starpass-pack-unique.php" class="pack-btn pack-btn-sms">📱 SMS</a>
        <?php endif;?>
      </div>
    </div>
    <?php endforeach;?>
  </div>

  <div class="packs-faq">
    <h2>Questions fréquentes</h2>
    <div class="faq-item"><strong>Les crédits expirent-ils ?</strong><p>Non. Tes crédits sont <strong>à vie</strong>. Tu peux les utiliser sur une journée, un mois ou étalés sur toute la saison.</p></div>
    <div class="faq-item"><strong>Comment consommer un crédit ?</strong><p>Chaque fois que tu débloques un bet Multisports pour la première fois, 1 crédit est consommé. Tu peux ensuite revenir le consulter gratuitement autant de fois que tu veux.</p></div>
    <div class="faq-item"><strong>Quels sports sont inclus ?</strong><p>Tous les bets Multisports : ⚽ Football, 🏀 NBA, 🏒 NHL, ⚾ MLB (Safe, Fun, Live). Le Tennis a son propre abonnement dédié.</p></div>
    <div class="faq-item"><strong>Puis-je cumuler plusieurs packs ?</strong><p>Oui ! Tes crédits s'additionnent. Si tu as 3 crédits d'un Trio et que tu achètes un Quinté (5), tu auras 8 crédits total.</p></div>
    <div class="faq-item"><strong>Paiement sécurisé ?</strong><p>Stripe pour CB (crypté PCI-DSS), NowPayments pour crypto, StarPass pour SMS. Aucune donnée bancaire stockée sur StratEdge.</p></div>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
