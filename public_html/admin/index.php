<?php
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
$pageActive = 'index';
$db = getDB();

// Stats visiteurs (table visites en BDD — ne se remet plus à zéro au déploiement)
$visiteursAujourdhui = $visiteursSemaine = $visiteursMois = $visiteursAll = 0;
try {
    $todayStart = strtotime('today');
    $weekStart = strtotime('-7 days');
    $monthStart = strtotime('-30 days');
    $visiteursAll = (int)$db->query("SELECT COUNT(*) FROM visites")->fetchColumn();
    $visiteursMois = (int)$db->query("SELECT COUNT(*) FROM visites WHERE t >= $monthStart")->fetchColumn();
    $visiteursSemaine = (int)$db->query("SELECT COUNT(*) FROM visites WHERE t >= $weekStart")->fetchColumn();
    $visiteursAujourdhui = (int)$db->query("SELECT COUNT(*) FROM visites WHERE t >= $todayStart")->fetchColumn();
} catch (Throwable $e) {
    // Table visites peut ne pas exister
}

$nbMembres    = $db->query("SELECT COUNT(*) FROM membres WHERE email != 'stratedgepronos@gmail.com'")->fetchColumn();
$nbAboActifs  = $db->query("SELECT COUNT(*) FROM abonnements WHERE date_fin > NOW()")->fetchColumn();
// Nettoyage auto: désactiver les abonnements expirés (actif=1 mais date_fin passée)
try { $db->exec("UPDATE abonnements SET actif=0 WHERE actif=1 AND date_fin <= NOW()"); } catch(Throwable $e) {}
$nbBets       = $db->query("SELECT COUNT(*) FROM bets WHERE actif=1")->fetchColumn();
$nbTickets    = $db->query("SELECT COUNT(*) FROM tickets WHERE statut != 'resolu'")->fetchColumn();
$nbMessages   = $db->query("SELECT COUNT(*) FROM messages WHERE expediteur='membre' AND lu=0")->fetchColumn();

// === NOUVEAU MODELE 2026 ===
// MULTI = packs credits (table credits_paris)
// TENNIS = abo Semaine 15€ (table abonnements type='tennis')
// FUN = abo Semaine 10€ (table abonnements type='fun')
// VIP MAX = abo 30 jours (table abonnements type='vip_max')

// Revenus MULTI (packs credits)
try {
    $revenuMulti = (float)$db->query("SELECT COALESCE(SUM(prix_paye),0) FROM credits_paris")->fetchColumn();
    $nbAchatsMulti = (int)$db->query("SELECT COUNT(*) FROM credits_paris")->fetchColumn();
} catch (Throwable $e) { $revenuMulti = 0; $nbAchatsMulti = 0; }

// Revenus TENNIS (abo semaine 15€ ponctuel)
$revenuTennis = (float)$db->query("SELECT COALESCE(SUM(montant),0) FROM abonnements WHERE type='tennis'")->fetchColumn();
$nbAboTennis  = (int)$db->query("SELECT COUNT(*) FROM abonnements WHERE type='tennis'")->fetchColumn();

// Revenus FUN (abo semaine 10€ ponctuel)
$revenuFun = (float)$db->query("SELECT COALESCE(SUM(montant),0) FROM abonnements WHERE type='fun'")->fetchColumn();
$nbAboFun  = (int)$db->query("SELECT COUNT(*) FROM abonnements WHERE type='fun'")->fetchColumn();

// Revenus VIP MAX (abo 30 jours)
$revenuVip = (float)$db->query("SELECT COALESCE(SUM(montant),0) FROM abonnements WHERE type='vip_max'")->fetchColumn();
$nbAboVip  = (int)$db->query("SELECT COUNT(*) FROM abonnements WHERE type='vip_max'")->fetchColumn();
$nbVipActifs = (int)$db->query("SELECT COUNT(*) FROM abonnements WHERE type='vip_max' AND actif=1 AND date_fin>NOW()")->fetchColumn();

// Fondateurs VIP Max (places limitées à 10)
$fondateurPlaces = 0;
try { $fondateurPlaces = (int)$db->query("SELECT COUNT(*) FROM vip_max_fondateurs")->fetchColumn(); } catch(Throwable $e) {}
$fondateurRestant = max(0, 10 - $fondateurPlaces);

$revenuTotal = $revenuMulti + $revenuTennis + $revenuFun + $revenuVip;

$derniersMembres = $db->query("SELECT * FROM membres WHERE email != 'stratedgepronos@gmail.com' ORDER BY date_inscription DESC LIMIT 5")->fetchAll();
$derniersTickets = $db->query("SELECT t.*, m.nom FROM tickets t JOIN membres m ON t.membre_id=m.id WHERE t.statut!='resolu' ORDER BY t.date_creation DESC LIMIT 5")->fetchAll();
?>
<!doctype html>
<html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#0b0d12"><meta name="robots" content="noindex,nofollow"><title>Vue d’ensemble — Admin StratEdge</title><link rel="icon" type="image/png" href="/assets/images/mascotte.png"><style>body{margin:0;background:#0b0d12;color:#f3f4f7}</style></head><body>
<?php require_once __DIR__.'/sidebar.php'; ?>
<main class="main" id="se-main-content" tabindex="-1"><?php require __DIR__.'/design/dashboard.php'; ?></main>
</body></html>
