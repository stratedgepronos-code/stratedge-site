<?php
// ============================================================
// STRATEDGE — Diagnostic accès d'un membre spécifique
// /panel-x9k3m/diagnose-membre.php?email=niare1999@hotmail.fr
// ============================================================

require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
header('Content-Type: text/plain; charset=utf-8');

$db = getDB();
$email = trim($_GET['email'] ?? '');

if ($email === '') {
    echo "Usage: ?email=adresse@example.com\n";
    echo "Exemple: /panel-x9k3m/diagnose-membre.php?email=niare1999@hotmail.fr\n";
    exit;
}

echo "═══════════════════════════════════════════════\n";
echo "  DIAGNOSTIC MEMBRE: $email\n";
echo "═══════════════════════════════════════════════\n\n";

// 1) Membre
$stmt = $db->prepare("SELECT * FROM membres WHERE email = ? LIMIT 1");
$stmt->execute([$email]);
$membre = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$membre) {
    echo "❌ AUCUN MEMBRE TROUVÉ avec cet email.\n\n";
    echo "Vérifions les emails proches:\n";
    $part = explode('@', $email)[0];
    $stmt2 = $db->prepare("SELECT id, email, nom, date_inscription FROM membres WHERE email LIKE ? LIMIT 10");
    $stmt2->execute(['%' . $part . '%']);
    foreach ($stmt2->fetchAll(PDO::FETCH_ASSOC) as $m) {
        echo "  #{$m['id']} · {$m['email']} · {$m['nom']} · inscrit le {$m['date_inscription']}\n";
    }
    exit;
}

echo "━━━ MEMBRE ━━━\n";
echo "  ID: #{$membre['id']}\n";
echo "  Email: {$membre['email']}\n";
echo "  Nom: " . ($membre['nom'] ?? '?') . "\n";
echo "  Actif: " . ($membre['actif'] ? '✅ OUI' : '❌ NON') . "\n";
echo "  Date inscription: " . ($membre['date_inscription'] ?? '?') . "\n";
echo "  Email vérifié: " . (!empty($membre['email_verifie']) ? '✅' : '⚠️ NON') . "\n";
echo "\n";

// 2) Tous les abonnements de ce membre
echo "━━━ ABONNEMENTS (tous) ━━━\n";
$stmt = $db->prepare("SELECT * FROM abonnements WHERE membre_id = ? ORDER BY id DESC LIMIT 20");
$stmt->execute([$membre['id']]);
$abos = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($abos)) {
    echo "  ⚠️ AUCUN ABONNEMENT en base pour ce membre.\n";
    echo "  → Le webhook Stripe n'a pas créé l'abonnement.\n\n";
} else {
    foreach ($abos as $a) {
        $isExpired = !empty($a['date_fin']) && strtotime($a['date_fin']) < time();
        $mark = ($a['actif'] && !$isExpired) ? '✅ ACTIF' : '❌ INACTIF';
        echo sprintf(
            "  #%-4d %s type=%-12s actif=%d montant=%s€ created=%s\n",
            $a['id'], $mark, $a['type'], $a['actif'],
            $a['montant'] ?? '?',
            $a['date_creation'] ?? $a['created_at'] ?? '?'
        );
        echo sprintf("       date_fin=%s%s\n",
            $a['date_fin'] ?? '?',
            $isExpired ? ' (EXPIRÉ)' : ''
        );
    }
    echo "\n";
}

// 3) Abonnement actif (ce que getAbonnementActif utilisé par bets.php verrait)
echo "━━━ ABONNEMENT ACTIF (vue par bets.php) ━━━\n";
try {
    $aboActif = getAbonnementActif($membre['id']);
    if ($aboActif) {
        echo "  ✅ Abo actif détecté:\n";
        echo "     type: {$aboActif['type']}\n";
        echo "     date_fin: {$aboActif['date_fin']}\n";
        echo "     montant: " . ($aboActif['montant'] ?? '?') . "€\n";
    } else {
        echo "  ❌ AUCUN abonnement actif détecté par getAbonnementActif().\n";
        echo "     → bets.php va fallback sur la query 'multi' (catégorie multi par défaut)\n";
        echo "     → Membre tennis sans accès car aucun abo trouvé\n";
    }
} catch (Throwable $e) {
    echo "  ⚠️ Erreur: " . $e->getMessage() . "\n";
}
echo "\n";

// 4) Paiements Stripe pour ce membre
echo "━━━ PAIEMENTS STRIPE (table 'paiements_stripe' si existe) ━━━\n";
try {
    $stmt = $db->prepare("SELECT * FROM paiements_stripe WHERE membre_id = ? ORDER BY id DESC LIMIT 10");
    $stmt->execute([$membre['id']]);
    $pays = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($pays)) {
        echo "  (aucun paiement enregistré)\n";
    } else {
        foreach ($pays as $p) {
            echo sprintf("  #%-4d type=%-10s montant=%s€ stripe_session=%s\n",
                $p['id'], $p['type'] ?? '?', $p['montant'] ?? '?',
                substr($p['stripe_session_id'] ?? '?', 0, 35)
            );
            echo sprintf("       compte=%s · date=%s\n",
                $p['stripe_account'] ?? '?',
                $p['date_paiement'] ?? $p['created_at'] ?? '?'
            );
        }
    }
} catch (Throwable $e) {
    echo "  (table paiements_stripe n'existe pas ou erreur: " . substr($e->getMessage(), 0, 80) . ")\n";
}
echo "\n";

// 5) Log Stripe (les 30 dernières lignes contenant ce membre_id ou cet email)
echo "━━━ LOG STRIPE (lignes pertinentes) ━━━\n";
$logFile = __DIR__ . '/../logs/stripe-log.txt';
if (is_file($logFile)) {
    $lines = @file($logFile, FILE_IGNORE_NEW_LINES);
    $relevant = [];
    foreach ($lines ?: [] as $l) {
        if (stripos($l, '#' . $membre['id']) !== false ||
            stripos($l, $email) !== false ||
            stripos($l, 'tennis') !== false) {
            $relevant[] = $l;
        }
    }
    $last = array_slice($relevant, -30);
    if (empty($last)) {
        echo "  (aucune ligne pertinente trouvée)\n";
    } else {
        foreach ($last as $l) echo "  $l\n";
    }
} else {
    echo "  (pas de fichier log stripe)\n";
}
echo "\n";

// 6) Action de réparation: si on trouve un paiement tennis sans abo, proposer la création
echo "━━━ DIAGNOSTIC AUTOMATIQUE ━━━\n";
$hasPaiementTennis = false;
$hasAboTennis = false;
try {
    $r = $db->prepare("SELECT COUNT(*) FROM paiements_stripe WHERE membre_id = ? AND type = 'tennis'");
    $r->execute([$membre['id']]);
    $hasPaiementTennis = (int)$r->fetchColumn() > 0;
} catch (Throwable $e) {}

foreach ($abos as $a) {
    if ($a['type'] === 'tennis' && $a['actif'] && strtotime($a['date_fin'] ?? '0') > time()) {
        $hasAboTennis = true;
        break;
    }
}

if ($hasPaiementTennis && !$hasAboTennis) {
    echo "  🚨 PROBLÈME DÉTECTÉ: paiement tennis présent mais aucun abonnement tennis actif.\n";
    echo "     Le webhook a probablement échoué pour ce membre.\n\n";
    echo "  ⚙️ ACTION RÉPARATION:\n";
    echo "     /panel-x9k3m/diagnose-membre.php?email=" . urlencode($email) . "&fix=tennis\n";
    echo "     → Crée manuellement un abo tennis 7 jours actif pour ce membre.\n";
} elseif ($hasAboTennis) {
    echo "  ✅ Abonnement tennis actif présent. Si l'utilisateur n'a pas accès, c'est ailleurs:\n";
    echo "     - Vérifie qu'il est BIEN connecté (cookie de session)\n";
    echo "     - Vérifie que /bets.php charge bien (pas de cache navigateur)\n";
    echo "     - Demande-lui de se déconnecter/reconnecter\n";
} else {
    echo "  ⚠️ Pas de paiement tennis ET pas d'abo tennis. Le paiement Stripe n'a pas été reçu.\n";
    echo "     Vérifie sur Stripe Dashboard si la transaction est passée.\n";
    echo "     Si oui mais webhook KO → utilise ?fix=tennis pour créer l'abo manuellement.\n";
}
echo "\n";

// 7) Action manuelle: créer un abo tennis 7j
if (($_GET['fix'] ?? '') === 'tennis') {
    echo "━━━ EXÉCUTION FIX: création abo tennis 7j ━━━\n";
    // Permet de passer le montant réel (après code promo) via ?montant=7.50
    $montantFix = isset($_GET['montant']) ? (float)$_GET['montant'] : 15.00;
    try {
        $ok = activerAbonnement((int)$membre['id'], 'tennis', $montantFix);
        if ($ok) {
            echo "  ✅ Abonnement tennis créé pour membre #{$membre['id']}\n";
            echo "  Montant enregistré: " . number_format($montantFix, 2) . "€\n";
            echo "  Date fin: " . date('Y-m-d H:i:s', strtotime('+7 days')) . "\n";
            echo "  Recharge la page sans ?fix= pour vérifier.\n";
        } else {
            echo "  ❌ Échec activerAbonnement().\n";
        }
    } catch (Throwable $e) {
        echo "  ❌ Exception: " . $e->getMessage() . "\n";
    }
    echo "\n";
}

echo "═══════════════════════════════════════════════\n";
