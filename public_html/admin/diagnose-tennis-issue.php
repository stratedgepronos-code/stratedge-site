<?php
// ============================================================
// STRATEDGE — Diagnostic rapide abonnement tennis mal routé
// URL: /panel-x9k3m/diagnose-tennis-issue.php?email=xxx@xxx.com
//      /panel-x9k3m/diagnose-tennis-issue.php?membre_id=123
// ============================================================

require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
header('Content-Type: text/plain; charset=utf-8');

$db = getDB();

// ── Récup membre ─────────────────────────────────────────────
$email = trim($_GET['email'] ?? '');
$membreId = (int)($_GET['membre_id'] ?? 0);

if (!$email && !$membreId) {
    die("Usage:\n  ?email=xxx@xxx.com\n  OU\n  ?membre_id=123\n\nFix manuel:\n  ?membre_id=123&fix=tennis\n  → Désactive les autres abos + crée un 'tennis' propre\n");
}

if ($email) {
    $stmt = $db->prepare("SELECT id, email, nom FROM membres WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $membre = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$membre) die("❌ Aucun membre trouvé avec email: $email\n");
    $membreId = (int)$membre['id'];
} else {
    $stmt = $db->prepare("SELECT id, email, nom FROM membres WHERE id = ? LIMIT 1");
    $stmt->execute([$membreId]);
    $membre = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$membre) die("❌ Aucun membre avec id=$membreId\n");
}

echo "═══════════════════════════════════════════════\n";
echo "  DIAGNOSTIC MEMBRE #{$membre['id']}\n";
echo "═══════════════════════════════════════════════\n";
echo "Email : {$membre['email']}\n";
echo "Nom   : " . ($membre['nom'] ?: '(vide)') . "\n\n";

// ── Tous les abonnements ─────────────────────────────────────
echo "━━━ ABONNEMENTS (table abonnements) ━━━\n";
$stmt = $db->prepare("SELECT * FROM abonnements WHERE membre_id = ? ORDER BY date_achat DESC");
$stmt->execute([$membreId]);
$abos = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($abos)) {
    echo "❌ Aucun abonnement trouvé !\n\n";
} else {
    foreach ($abos as $a) {
        $expire = $a['date_fin'] ? new DateTime($a['date_fin']) : null;
        $expired = $expire && $expire < new DateTime();
        $mark = $a['actif'] ? ($expired ? '⏱️ EXPIRÉ' : '✅ ACTIF') : '❌ INACTIF';
        echo sprintf(
            "  #%d | type=%s | actif=%d | montant=%s€ | date_achat=%s | date_fin=%s | %s\n",
            $a['id'], $a['type'], $a['actif'],
            $a['montant'] ?? '?',
            $a['date_achat'] ?? '?',
            $a['date_fin'] ?? '(illimité)',
            $mark
        );
    }
    echo "\n";
}

// ── Paiements Stripe ─────────────────────────────────────────
echo "━━━ PAIEMENTS STRIPE (table stripe_payments) ━━━\n";
try {
    $stmt = $db->prepare("SELECT * FROM stripe_payments WHERE membre_id = ? ORDER BY date_paiement DESC");
    $stmt->execute([$membreId]);
    $pays = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($pays)) {
        echo "(aucun paiement Stripe enregistré)\n\n";
    } else {
        foreach ($pays as $p) {
            echo sprintf(
                "  #%d | offre=%s | compte=%s | montant=%s€ | session=%s | order=%s | %s | %s\n",
                $p['id'], $p['offre'], $p['stripe_account'] ?? '?',
                $p['montant_eur'], substr($p['stripe_session_id'] ?? '', 0, 20) . '…',
                $p['order_id'] ?? '?', $p['statut'] ?? '?',
                $p['date_paiement'] ?? '?'
            );
        }
        echo "\n";
    }
} catch (Throwable $e) {
    echo "(Table stripe_payments inexistante ou erreur: " . $e->getMessage() . ")\n\n";
}

// ── Droits d'accès calculés ──────────────────────────────────
echo "━━━ DROITS D'ACCÈS CALCULÉS (getMembreAcces) ━━━\n";
$acces = getMembreAcces($membreId);
foreach ($acces as $k => $v) {
    echo "  $k : " . ($v ? '✅ OUI' : '❌ NON') . "\n";
}
echo "\n";

$whereClause = buildBetsWhereClause($acces);
echo "Clause SQL appliquée sur les bets :\n  $whereClause\n\n";

// ── Logs stripe liés ─────────────────────────────────────────
echo "━━━ LOGS STRIPE RÉCENTS (dernières 20 lignes contenant l'email) ━━━\n";
$logFile = __DIR__ . '/../logs/stripe-log.txt';
if (is_file($logFile)) {
    $lines = @file($logFile, FILE_IGNORE_NEW_LINES);
    $filtered = array_filter($lines, function($l) use ($membreId, $membre) {
        return strpos($l, "#$membreId ") !== false
            || strpos($l, $membre['email']) !== false
            || strpos($l, "membre $membreId") !== false
            || strpos($l, "membre=$membreId") !== false;
    });
    $last = array_slice($filtered, -20);
    if (empty($last)) {
        echo "(rien trouvé avec membreId=$membreId)\n\n";
    } else {
        foreach ($last as $l) echo "  $l\n";
        echo "\n";
    }
} else {
    echo "(logs/stripe-log.txt introuvable)\n\n";
}

// ── FIX manuel si demandé ────────────────────────────────────
if (($_GET['fix'] ?? '') === 'tennis') {
    echo "━━━ 🔧 FIX MANUEL : ACTIVATION TENNIS ━━━\n";

    // 1. Désactiver tous les abos actifs qui ne sont PAS tennis
    $stmtDeact = $db->prepare("
        UPDATE abonnements
        SET actif = 0
        WHERE membre_id = ? AND actif = 1 AND type != 'tennis'
    ");
    $stmtDeact->execute([$membreId]);
    $nbDeact = $stmtDeact->rowCount();
    echo "  → $nbDeact abonnement(s) non-tennis désactivé(s)\n";

    // 2. Vérifier s'il a déjà un tennis actif
    $stmtCheck = $db->prepare("
        SELECT id, date_fin FROM abonnements
        WHERE membre_id = ? AND type = 'tennis' AND actif = 1 AND date_fin > NOW()
        LIMIT 1
    ");
    $stmtCheck->execute([$membreId]);
    $existing = $stmtCheck->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        echo "  → Abo tennis déjà actif (#{$existing['id']}, expire {$existing['date_fin']}), aucune création\n";
    } else {
        // 3. Créer un abo tennis (7 jours à partir de maintenant)
        $ok = activerAbonnement($membreId, 'tennis');
        if ($ok) {
            $newId = $db->lastInsertId();
            echo "  → ✅ Abo tennis créé (#$newId), expire dans 7 jours\n";
        } else {
            echo "  → ❌ Échec création abo tennis\n";
        }
    }

    echo "\nDroits recalculés :\n";
    $acces2 = getMembreAcces($membreId);
    foreach ($acces2 as $k => $v) echo "  $k : " . ($v ? '✅' : '❌') . "\n";
}

echo "\n═══════════════════════════════════════════════\n";
echo "  Pour fix manuel:\n";
echo "  ?membre_id={$membre['id']}&fix=tennis\n";
echo "═══════════════════════════════════════════════\n";
