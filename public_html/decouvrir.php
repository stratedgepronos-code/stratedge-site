<?php
require __DIR__.'/includes/public-site/bootstrap.php';require __DIR__.'/includes/public-site/Offers.php';
$key=is_string($_GET['offre']??null)?$_GET['offre']:'';$target=\StratEdgePublic\Offers::target($key);if(!$target){header('Location: /offres.php');exit;}
front_count('choix_offre');$_SESSION['public_offer']=$key;$_SESSION['public_source']=$frontSource;$_SESSION['public_offer_at']=time();
if($frontMember){header('Location: '.$target);exit;}
$offer=\StratEdgePublic\Offers::all()[$key];$pageActive='offres';$pagePath='/decouvrir.php?offre='.$key;$frontNoIndex=true;$pageTitle='Découvrir '.$offer['title'].' | StratEdge';
require __DIR__.'/includes/public-site/header.php'; ?>
<div class="wrap"><section class="join-box"><span class="eyebrow pink">Votre choix / <?= front_h($offer['title']) ?></span><h1>BIENVENUE<br>SUR VOTRE TERRAIN.</h1><p><?= front_h($offer['description']) ?></p><p><strong style="color:var(--paper)"><?= front_h($offer['price']) ?> €</strong> · <?= front_h($offer['period']) ?></p><p>Votre compte vous permet de retrouver cet accès et de choisir votre moyen de paiement. L’inscription est gratuite et ne déclenche aucun achat.</p><div class="actions"><a class="button" href="<?= front_link('/register.php') ?>">Créer mon compte <?= front_arrow() ?></a><a class="text-link" href="<?= front_link('/login.php?redirect='.rawurlencode($target)) ?>">J’ai déjà un compte ↗</a></div><p class="proof-note">Service réservé aux adultes. <a href="/cgv.php" style="text-decoration:underline">Conditions de vente</a>.</p><a class="text-link" href="/offres.php">← Revenir aux offres</a></section></div>
<?php require __DIR__.'/includes/public-site/footer.php'; ?>
