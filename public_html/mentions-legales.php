<?php
require_once __DIR__ . '/includes/auth.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Mentions légales — StratEdge Pronos</title>
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
    .ml-section a:hover { text-decoration:underline; }

    .info-box {
      background:rgba(0,212,106,0.05); border:1px solid rgba(0,212,106,0.2);
      border-radius:10px; padding:1rem 1.2rem; margin-bottom:1.5rem;
    }
    .info-box p { font-size:0.82rem; color:rgba(255,255,255,0.5); margin:0; line-height:1.6; }

    .warn-box {
      background:rgba(255,200,0,0.05); border:1px solid rgba(255,200,0,0.2);
      border-radius:10px; padding:1rem 1.2rem; margin-bottom:1rem;
    }
    .warn-box p { font-size:0.82rem; color:#ffd700; margin:0; line-height:1.6; }
  </style>
  <!-- PWA -->
  <link rel="manifest" href="/manifest.json">
  <meta name="theme-color" content="#0a0e17">
  <meta name="mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <meta name="apple-mobile-web-app-title" content="StratEdge">
  <link rel="apple-touch-icon" href="/assets/images/mascotte.png">
</head>
<body>

<nav>
  <a class="nav-logo" href="/"><span>Strat</span>Edge</a>
  <a class="nav-back" href="javascript:history.back()">← Retour</a>
</nav>

<div class="page-wrap">
  <h1>Mentions légales</h1>
  <p class="page-subtitle">Conformément à la loi n°2004-575 du 21 juin 2004 pour la confiance dans l'économie numérique (LCEN)</p>

  <!-- Éditeur -->
  <div class="ml-section">
    <h2>1. Éditeur du site</h2>
    <p>Le site <strong>stratedgepronos.fr</strong> est édité à titre personnel par un particulier.</p>
    <p><strong>Contact :</strong> <a href="mailto:contact@stratedgepronos.fr">contact@stratedgepronos.fr</a></p>
    <p><strong>Directeur de la publication :</strong> l'éditeur du site</p>
  </div>

  <!-- Hébergeur -->
  <div class="ml-section">
    <h2>2. Hébergeur</h2>
    <p><strong>Hostinger International Ltd</strong><br>
    61 Lordou Vironos Street, 6023 Larnaca, Chypre<br>
    <a href="https://www.hostinger.fr" target="_blank" rel="noopener">www.hostinger.fr</a></p>
  </div>

  <!-- Nature du service -->
  <div class="ml-section">
    <h2>3. Nature du service</h2>
    <div class="info-box">
      <p>StratEdge est un <strong>service d'analyse sportive à titre informatif</strong>. Ce site ne constitue pas un opérateur de jeux ou de paris en ligne au sens de la loi n°2010-476 du 12 mai 2010 et n'est pas agréé par l'Autorité Nationale des Jeux (ANJ).</p>
    </div>
    <p>Les contenus publiés sur ce site (analyses, statistiques, pronostics) sont fournis à titre informatif et constituent des <strong>opinions analytiques personnelles</strong>. Ils ne constituent en aucun cas des conseils financiers, des garanties de gain ou une incitation à parier.</p>
    <p>L'utilisateur est seul responsable des décisions de mise qu'il prend sur les plateformes de paris en ligne de son choix.</p>
  </div>

  <!-- Avertissement jeux d'argent -->
  <div class="ml-section">
    <h2>4. Avertissement — jeux d'argent</h2>
    <div class="warn-box">
      <p>⚠️ <strong>Les jeux d'argent comportent des risques : endettement, dépendance, isolement.</strong><br>
      Si vous ou un proche êtes concerné, contactez <strong>Joueurs Info Service</strong> au <strong>09 74 75 13 13</strong> (appel non surtaxé, disponible 7j/7).<br>
      Plus d'informations sur <a href="https://www.joueurs-info-service.fr" target="_blank" rel="noopener">joueurs-info-service.fr</a></p>
    </div>
    <ul>
      <li>Les paris sportifs sont <strong>interdits aux personnes mineures</strong> (moins de 18 ans)</li>
      <li>Aucun pronostic ne peut garantir un gain — le résultat sportif est par nature aléatoire</li>
      <li>Les performances passées ne préjugent pas des résultats futurs</li>
      <li>Ne misez jamais plus que ce que vous pouvez vous permettre de perdre</li>
    </ul>
  </div>

  <!-- Propriété intellectuelle -->
  <div class="ml-section">
    <h2>5. Propriété intellectuelle</h2>
    <p>L'ensemble des contenus du site (textes, analyses, design, code) est protégé par le droit d'auteur. Toute reproduction, diffusion ou partage des analyses sans autorisation préalable est strictement interdit.</p>
    <p>Les accès sont <strong>strictement personnels et non cessibles</strong>. Tout partage de compte entraîne la résiliation immédiate de l'abonnement sans remboursement.</p>
  </div>

  <!-- Données personnelles / RGPD -->
  <div class="ml-section">
    <h2>6. Données personnelles (RGPD)</h2>
    <p>Conformément au <strong>Règlement Général sur la Protection des Données (RGPD)</strong> n°2016/679 du 27 avril 2016 et à la loi n°78-17 du 6 janvier 1978 (loi Informatique et Libertés), vous disposez des droits suivants :</p>
    <ul>
      <li><strong>Droit d'accès</strong> : obtenir une copie de vos données personnelles</li>
      <li><strong>Droit de rectification</strong> : corriger des données inexactes</li>
      <li><strong>Droit à l'effacement</strong> : demander la suppression de votre compte et données</li>
      <li><strong>Droit d'opposition</strong> : vous opposer au traitement de vos données</li>
    </ul>
    <p>Pour exercer ces droits : <a href="mailto:contact@stratedgepronos.fr">contact@stratedgepronos.fr</a></p>
    <p><strong>Données collectées :</strong> adresse email, nom, historique des abonnements. Ces données sont utilisées uniquement pour la gestion des accès et ne sont jamais vendues ni partagées avec des tiers à des fins commerciales.</p>
    <p><strong>Durée de conservation :</strong> les données sont conservées 3 ans après la dernière activité du compte.</p>
    <p><strong>Hébergement des données :</strong> serveurs Hostinger (UE).</p>
  </div>

  <!-- Cookies -->
  <div class="ml-section">
    <h2>7. Cookies</h2>
    <p>Ce site utilise uniquement des <strong>cookies de session</strong> strictement nécessaires au fonctionnement de l'authentification. Aucun cookie publicitaire ni de tracking tiers n'est utilisé.</p>
  </div>

  <!-- Limitation de responsabilité -->
  <div class="ml-section">
    <h2>8. Limitation de responsabilité</h2>
    <p>StratEdge ne saurait être tenu responsable :</p>
    <ul>
      <li>Des pertes financières résultant de l'utilisation des analyses publiées</li>
      <li>Des décisions de mises prises par l'utilisateur sur des plateformes de paris</li>
      <li>Des interruptions temporaires de service pour raisons de maintenance</li>
      <li>Du contenu des sites tiers accessibles par liens hypertextes</li>
    </ul>
  </div>

  <!-- Droit applicable -->
  <div class="ml-section">
    <h2>9. Droit applicable</h2>
    <p>Les présentes mentions légales sont soumises au <strong>droit français</strong>. En cas de litige, les tribunaux français seront seuls compétents.</p>
    <p>Dernière mise à jour : <?= date('d/m/Y') ?></p>
  </div>

</div>

<?php require_once __DIR__ . '/includes/footer-main.php'; ?>
</body>
</html>
