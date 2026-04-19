<?php
require_once __DIR__ . '/includes/auth.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Conditions Générales de Vente — StratEdge Pronos</title>
  <link rel="icon" type="image/png" href="assets/images/mascotte.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
  <style>
    :root { --bg:#0a0e17; --card:#111827; --border:rgba(255,255,255,0.07); --txt:#e2e8f0; --txt2:rgba(255,255,255,0.55); --neon:#00d46a; }
    * { margin:0; padding:0; box-sizing:border-box; }
    body { background:var(--bg); color:var(--txt); font-family:'Inter',sans-serif; min-height:100vh; }
    nav { background:rgba(10,14,23,0.95); border-bottom:1px solid var(--border); padding:1rem 2rem; display:flex; justify-content:space-between; align-items:center; position:sticky; top:0; z-index:100; }
    .nav-logo { font-family:'Orbitron',sans-serif; font-size:1.1rem; font-weight:700; color:#fff; text-decoration:none; }
    .nav-logo span { color:var(--neon); }
    .nav-back { color:var(--txt2); text-decoration:none; font-size:0.85rem; transition:color .2s; }
    .nav-back:hover { color:#fff; }

    .page-wrap { max-width:800px; margin:0 auto; padding:3rem 1.5rem; }
    h1 { font-family:'Orbitron',sans-serif; font-size:1.6rem; font-weight:800; color:#fff; margin-bottom:0.5rem; }
    .page-subtitle { color:var(--txt2); font-size:0.85rem; margin-bottom:3rem; }

    .ml-section { margin-bottom:2.5rem; }
    .ml-section h2 {
      font-size:0.72rem; font-weight:700; text-transform:uppercase; letter-spacing:2px;
      color:var(--neon); margin-bottom:1rem; padding-bottom:0.5rem;
      border-bottom:1px solid rgba(0,212,106,0.2);
    }
    .ml-section p, .ml-section li {
      font-size:0.875rem; color:var(--txt2); line-height:1.75; margin-bottom:0.5rem;
    }
    .ml-section ul { padding-left:1.2rem; }
    .ml-section strong { color:rgba(255,255,255,0.75); }
    .ml-section a { color:var(--neon); text-decoration:none; }

    .info-box {
      background:rgba(0,212,106,0.05); border:1px solid rgba(0,212,106,0.2);
      border-radius:10px; padding:1rem 1.2rem; margin-bottom:1.2rem;
    }
    .info-box p { font-size:0.82rem; color:rgba(255,255,255,0.5); margin:0; line-height:1.6; }

    .warn-box {
      background:rgba(255,200,0,0.05); border:1px solid rgba(255,200,0,0.2);
      border-radius:10px; padding:1rem 1.2rem; margin-bottom:1rem;
    }
    .warn-box p { font-size:0.82rem; color:#ffd700; margin:0; line-height:1.6; }

    .price-table { width:100%; border-collapse:collapse; margin:1rem 0; font-size:0.82rem; }
    .price-table th { background:rgba(255,255,255,0.05); color:rgba(255,255,255,0.6); padding:0.6rem 1rem; text-align:left; font-weight:600; }
    .price-table td { padding:0.6rem 1rem; color:var(--txt2); border-bottom:1px solid rgba(255,255,255,0.04); }
    .price-table tr:last-child td { border-bottom:none; }
  </style>
  <!-- PWA -->
  <link rel="manifest" href="/manifest.json">
  <meta name="theme-color" content="#0a0e17">
  <meta name="mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <meta name="apple-mobile-web-app-title" content="StratEdge">
  <link rel="apple-touch-icon" href="/assets/images/apple-touch-icon.png">
</head>
<body>

<nav>
  <a class="nav-logo" href="/"><span>Strat</span>Edge</a>
  <a class="nav-back" href="javascript:history.back()">← Retour</a>
</nav>

<div class="page-wrap">
  <h1>Conditions Générales de Vente</h1>
  <p class="page-subtitle">En vigueur à compter du <?= date('d/m/Y') ?></p>

  <!-- Article 1 : Objet -->
  <div class="ml-section">
    <h2>Article 1 — Objet et nature du service</h2>
    <div class="info-box">
      <p><strong>StratEdge n'est pas un site de paris en ligne.</strong> Nous vendons un accès à des <strong>analyses sportives à titre informatif</strong>. Nous ne prenons pas de paris, ne gérons pas de mises et ne garantissons aucun gain.</p>
    </div>
    <p>StratEdge (ci-après "le Service") propose des abonnements donnant accès à des analyses, statistiques et opinions sur des événements sportifs. Ces contenus sont fournis <strong>exclusivement à titre informatif</strong> et ne constituent en aucun cas des conseils financiers ou une garantie de résultat.</p>
    <p>L'utilisateur (ci-après "le Client") reste <strong>seul responsable</strong> de ses décisions de mise sur les plateformes de paris de son choix.</p>
  </div>

  <!-- Article 2 : Conditions d'accès -->
  <div class="ml-section">
    <h2>Article 2 — Conditions d'accès</h2>
    <p>L'accès au Service est strictement réservé aux personnes :</p>
    <ul>
      <li>Âgées de <strong>18 ans minimum</strong> (ou de l'âge légal de la majorité dans leur pays de résidence)</li>
      <li>Capables juridiquement de contracter</li>
      <li>Résidant dans un pays où l'accès à ce type de contenu est autorisé</li>
    </ul>
    <p>En souscrivant un abonnement, le Client atteste sur l'honneur remplir ces conditions. StratEdge se réserve le droit de résilier tout compte dont le titulaire ne satisferait pas à ces exigences.</p>
  </div>

  <!-- Article 3 : Offres et tarifs -->
  <div class="ml-section">
    <h2>Article 3 — Offres et tarifs</h2>
    <h3 style="margin:1rem 0 .5rem;color:var(--neon-green,#ff2d78);font-size:1rem;">Packs Crédits Multisports</h3>
    <table class="price-table">
      <thead>
        <tr><th>Formule</th><th>Crédits</th><th>Prix TTC</th><th>Contenu</th></tr>
      </thead>
      <tbody>
        <tr><td><strong>Unique 🎯</strong></td><td>1 analyse</td><td>4,50 €</td><td>1 crédit = pass 24h tous les bets Multi</td></tr>
        <tr><td><strong>Duo 2️⃣</strong></td><td>2 analyses</td><td>8,00 €</td><td>2 crédits à vie</td></tr>
        <tr><td><strong>Trio 🔥</strong></td><td>3 analyses</td><td>12,00 €</td><td>3 crédits à vie · 4€/pari</td></tr>
        <tr><td><strong>Quinté 💎</strong></td><td>5 analyses</td><td>18,00 €</td><td>5 crédits à vie · 3,60€/pari</td></tr>
        <tr><td><strong>Semaine 📅</strong></td><td>7 analyses</td><td>20,00 €</td><td>7 crédits à vie · 2,86€/pari</td></tr>
        <tr><td><strong>Pack 10 🏆</strong></td><td>10 analyses</td><td>30,00 €</td><td>10 crédits à vie · 3€/pari</td></tr>
      </tbody>
    </table>
    <h3 style="margin:1.5rem 0 .5rem;color:var(--neon-green,#ff2d78);font-size:1rem;">Abonnements</h3>
    <table class="price-table">
      <thead>
        <tr><th>Formule</th><th>Durée</th><th>Prix TTC</th><th>Contenu</th></tr>
      </thead>
      <tbody>
        <tr><td><strong>Tennis 🎾</strong></td><td>7 jours</td><td>15,00 €</td><td>Tous les bets Tennis (ATP, WTA, Challenger)</td></tr>
        <tr><td><strong>Fun 🎲</strong></td><td>7 jours</td><td>10,00 €</td><td>Tous les bets Fun (combinés grosses cotes)</td></tr>
        <tr><td><strong>VIP Max 👑</strong></td><td>30 jours</td><td>49,90 €</td><td>Accès à tous les bets (Multi + Tennis + Fun)</td></tr>
      </tbody>
    </table>
    <p>Les prix sont indiqués en euros toutes taxes comprises. StratEdge est un service proposé par un auto-entrepreneur — non assujetti à la TVA (article 293B du CGI). Les crédits Multisports n'expirent pas. Les abonnements Tennis, Fun et VIP Max expirent automatiquement à la fin de la période souscrite, sans reconduction automatique.</p>
  </div>

  <!-- Article 4 : Paiement -->
  <div class="ml-section">
    <h2>Article 4 — Modalités de paiement</h2>
    <p>Le paiement est dû intégralement à la souscription. Les moyens de paiement acceptés sont :</p>
    <ul>
      <li><strong>Carte bancaire</strong> (Visa, Mastercard, Apple Pay, Google Pay) via Stripe</li>
      <li><strong>Crypto-monnaies</strong> (BTC, ETH, USDC, SOL, BNB) via NOWPayments</li>
      <li><strong>Paysafecard</strong> — paiement par code prépayé 16 chiffres</li>
    </ul>
    <p>L'accès au contenu est activé automatiquement après confirmation du paiement. En cas de problème technique, le Client peut contacter le support via la page <a href="/sav.php">SAV</a>.</p>
  </div>

  <!-- Article 5 : Droit de rétractation -->
  <div class="ml-section">
    <h2>Article 5 — Droit de rétractation</h2>
    <div class="warn-box">
      <p>⚠️ Conformément à l'<strong>article L221-28 du Code de la consommation</strong>, le droit de rétractation de 14 jours <strong>ne s'applique pas</strong> aux contenus numériques dont l'exécution a commencé avec l'accord exprès du consommateur et pour lesquels il a renoncé à son droit de rétractation.</p>
    </div>
    <p>En souscrivant un abonnement et en accédant aux analyses, le Client reconnaît expressément que :</p>
    <ul>
      <li>L'exécution du contrat commence immédiatement après le paiement</li>
      <li>Il renonce expressément à son droit de rétractation de 14 jours</li>
    </ul>
    <p>En conséquence, <strong>aucun remboursement ne sera accordé</strong> une fois l'accès activé, y compris en cas de bet perdant.</p>
  </div>

  <!-- Article 6 : Absence de garantie -->
  <div class="ml-section">
    <h2>Article 6 — Absence de garantie de résultat</h2>
    <div class="warn-box">
      <p>⚠️ <strong>Aucun pronostic sportif ne peut garantir un gain.</strong> Le résultat d'un événement sportif est par nature aléatoire et imprévisible. Les analyses fournies par StratEdge sont des opinions basées sur des données statistiques et ne constituent pas une certitude.</p>
    </div>
    <p>StratEdge ne pourra en aucun cas être tenu responsable :</p>
    <ul>
      <li>Des pertes financières résultant de l'utilisation des analyses</li>
      <li>D'un bet perdant, quelle qu'en soit la raison</li>
      <li>Des décisions de mise prises par le Client</li>
      <li>Des résultats sportifs qui différeraient des analyses publiées</li>
    </ul>
    <p>Le Client accepte que l'abonnement rémunère l'accès à une <strong>analyse</strong>, et non un résultat garanti.</p>
  </div>

  <!-- Article 7 : Utilisation du compte -->
  <div class="ml-section">
    <h2>Article 7 — Utilisation du compte</h2>
    <p>L'accès est <strong>strictement personnel et non cessible</strong>. Est formellement interdit :</p>
    <ul>
      <li>Le partage des identifiants de connexion</li>
      <li>La revente ou redistribution des analyses à des tiers</li>
      <li>La publication des contenus sur les réseaux sociaux ou tout autre support</li>
      <li>L'utilisation de plusieurs comptes par la même personne</li>
    </ul>
    <p>Tout abus constaté entraîne la <strong>résiliation immédiate et définitive</strong> du compte sans remboursement ni préavis.</p>
  </div>

  <!-- Article 8 : Protection des données -->
  <div class="ml-section">
    <h2>Article 8 — Protection des données personnelles</h2>
    <p>Les données collectées (email, nom) sont utilisées uniquement pour la gestion des accès. Elles ne sont jamais vendues ni transmises à des tiers à des fins commerciales. Pour plus d'informations, consultez nos <a href="/mentions-legales.php">Mentions légales</a> (article 6 — RGPD).</p>
  </div>

  <!-- Article 9 : Modification des CGV -->
  <div class="ml-section">
    <h2>Article 9 — Modification des CGV</h2>
    <p>StratEdge se réserve le droit de modifier les présentes CGV à tout moment. Les modifications prennent effet à leur date de publication. Le Client sera informé par email de toute modification substantielle.</p>
  </div>

  <!-- Article 10 : Jeu responsable -->
  <div class="ml-section">
    <h2>Article 10 — Jeu responsable</h2>
    <p>StratEdge encourage ses utilisateurs à pratiquer les paris sportifs de manière responsable :</p>
    <ul>
      <li>Ne misez jamais plus que ce que vous pouvez vous permettre de perdre</li>
      <li>Les paris ne doivent pas être une source de revenus principale</li>
      <li>En cas de difficulté avec le jeu : <strong>Joueurs Info Service — 09 74 75 13 13</strong> (gratuit, 7j/7)</li>
      <li>Possibilité d'auto-exclusion sur <a href="https://www.joueurs-info-service.fr" target="_blank" rel="noopener">joueurs-info-service.fr</a></li>
    </ul>
  </div>

  <!-- Article 11 : Droit applicable -->
  <div class="ml-section">
    <h2>Article 11 — Droit applicable et litiges</h2>
    <p>Les présentes CGV sont soumises au <strong>droit français</strong>. En cas de litige, le Client peut contacter le support à l'adresse <a href="mailto:contact@stratedgepronos.fr">contact@stratedgepronos.fr</a> pour une résolution amiable.</p>
    <p>À défaut d'accord amiable, les juridictions françaises seront seules compétentes.</p>
    <p><em>Dernière mise à jour : <?= date('d/m/Y') ?></em></p>
  </div>

</div>

<?php require_once __DIR__ . '/includes/footer-main.php'; ?>
</body>
</html>
