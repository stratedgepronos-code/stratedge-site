<?php
// ============================================================
// STRATEDGE — Correction montants abonnements
// /panel-x9k3m/fix-abo-montant.php
//
// Usage:
//   ?list                         → Liste tous les abos tennis avec leur montant
//   ?abo_id=123&montant=7.50      → Corrige le montant d'un abo précis
//   ?membre_id=XX&type=tennis&montant=7.50&action=add
//                                 → Ajoute un abo manuel avec montant custom
// ============================================================

require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
header('Content-Type: text/plain; charset=utf-8');

$db = getDB();

$action   = $_GET['action'] ?? '';
$aboId    = (int)($_GET['abo_id'] ?? 0);
$membreId = (int)($_GET['membre_id'] ?? 0);
$type     = $_GET['type'] ?? '';
$montant  = isset($_GET['montant']) ? (float)$_GET['montant'] : null;

echo "═══════════════════════════════════════════════\n";
echo "  STRATEDGE — Correction montants abonnements\n";
echo "═══════════════════════════════════════════════\n\n";

// ── Mode LIST ────────────────────────────────────────────────
if (isset($_GET['list'])) {
    $filterType = $_GET['list'] ?: 'tennis';
    echo "Liste des abos type='$filterType' (30 derniers) :\n\n";

    $stmt = $db->prepare("
        SELECT a.id, a.membre_id, a.type, a.montant, a.date_achat, a.date_fin, a.actif,
               m.email, m.nom
        FROM abonnements a
        LEFT JOIN membres m ON m.id = a.membre_id
        WHERE a.type = ?
        ORDER BY a.date_achat DESC
        LIMIT 30
    ");
    $stmt->execute([$filterType]);
    $abos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($abos)) {
        echo "(aucun abo $filterType trouvé)\n";
    } else {
        $sum = 0;
        foreach ($abos as $a) {
            $sum += (float)$a['montant'];
            $mark = $a['actif'] ? '✅' : '❌';
            echo sprintf(
                "  #%-4d | %s | %-30s | %5.2f€ | achat=%s | fin=%s | %s\n",
                $a['id'], $mark,
                substr(($a['email'] ?? '(sans email)'), 0, 30),
                (float)$a['montant'],
                $a['date_achat'],
                $a['date_fin'] ?? '-',
                $a['type']
            );
        }
        echo "\nTotal (30 derniers) : " . number_format($sum, 2) . "€\n";
    }

    // Total global
    $totalGlobal = (float)$db->query("SELECT COALESCE(SUM(montant),0) FROM abonnements WHERE type='$filterType'")->fetchColumn();
    $countGlobal = (int)$db->query("SELECT COUNT(*) FROM abonnements WHERE type='$filterType'")->fetchColumn();
    echo "Total GLOBAL (tous abos $filterType) : " . number_format($totalGlobal, 2) . "€ sur $countGlobal abos\n\n";
    echo "Pour corriger un montant :\n";
    echo "  ?abo_id=XXX&montant=7.50\n\n";
    echo "Pour ajouter un abo manuel :\n";
    echo "  ?action=add&membre_id=XX&type=tennis&montant=15.00\n";
    exit;
}

// ── Mode CORRIGER un abo existant ────────────────────────────
if ($aboId > 0 && $montant !== null) {
    // Vérifier qu'il existe
    $stmt = $db->prepare("
        SELECT a.*, m.email FROM abonnements a
        LEFT JOIN membres m ON m.id = a.membre_id
        WHERE a.id = ? LIMIT 1
    ");
    $stmt->execute([$aboId]);
    $abo = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$abo) die("❌ Abo #$aboId introuvable\n");

    echo "AVANT :\n";
    echo "  Abo #{$abo['id']} | membre={$abo['membre_id']} ({$abo['email']}) | type={$abo['type']} | montant={$abo['montant']}€ | date_achat={$abo['date_achat']}\n\n";

    $stmt = $db->prepare("UPDATE abonnements SET montant = ? WHERE id = ?");
    $ok = $stmt->execute([$montant, $aboId]);

    if ($ok) {
        echo "✅ Montant mis à jour : " . number_format($montant, 2) . "€\n\n";
        // Re-afficher l'abo après
        $stmt = $db->prepare("SELECT * FROM abonnements WHERE id = ?");
        $stmt->execute([$aboId]);
        $after = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "APRÈS :\n";
        echo "  Abo #{$after['id']} | montant=" . number_format((float)$after['montant'], 2) . "€\n\n";

        // Recalculer le total
        $total = (float)$db->query("SELECT COALESCE(SUM(montant),0) FROM abonnements WHERE type='{$abo['type']}'")->fetchColumn();
        echo "Nouveau revenu total '{$abo['type']}' : " . number_format($total, 2) . "€\n";
    } else {
        echo "❌ Échec UPDATE\n";
    }
    exit;
}

// ── Mode AJOUTER un abo manuel avec montant custom ───────────
if ($action === 'add' && $membreId > 0 && $type !== '' && $montant !== null) {
    // Vérifier le membre
    $stmt = $db->prepare("SELECT id, email, nom FROM membres WHERE id = ?");
    $stmt->execute([$membreId]);
    $membre = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$membre) die("❌ Membre #$membreId introuvable\n");

    $typesOk = ['daily','weekend','weekly','tennis','vip_max','fun','rasstoss'];
    if (!in_array($type, $typesOk)) die("❌ Type invalide. Valide: " . implode(', ', $typesOk) . "\n");

    echo "Ajout manuel pour membre #{$membre['id']} ({$membre['email']}) :\n";
    echo "  type    = $type\n";
    echo "  montant = " . number_format($montant, 2) . "€\n\n";

    // Utiliser la fonction activerAbonnement avec le montant custom
    $ok = activerAbonnement($membreId, $type, $montant);

    if ($ok) {
        $newId = $db->lastInsertId();
        echo "✅ Abo #$newId créé\n\n";

        // Vérifier
        $stmt = $db->prepare("SELECT * FROM abonnements WHERE id = ?");
        $stmt->execute([$newId]);
        $new = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "Détails :\n";
        echo "  type     = {$new['type']}\n";
        echo "  montant  = {$new['montant']}€\n";
        echo "  actif    = {$new['actif']}\n";
        echo "  date_fin = " . ($new['date_fin'] ?? '(illimité)') . "\n";

        $total = (float)$db->query("SELECT COALESCE(SUM(montant),0) FROM abonnements WHERE type='$type'")->fetchColumn();
        echo "\nNouveau revenu total '$type' : " . number_format($total, 2) . "€\n";
    } else {
        echo "❌ Échec activerAbonnement\n";
    }
    exit;
}

// ── AIDE ─────────────────────────────────────────────────────
echo "Usage :\n\n";
echo "  1) Lister les abos d'un type (voir montants + IDs) :\n";
echo "     ?list=tennis\n";
echo "     ?list=fun\n";
echo "     ?list=vip_max\n\n";
echo "  2) Corriger le montant d'un abo existant :\n";
echo "     ?abo_id=123&montant=7.50\n";
echo "     (ex: abo payé 7.50€ avec promo -50% mais stocké à 15€)\n\n";
echo "  3) Ajouter un abo manuel avec montant custom :\n";
echo "     ?action=add&membre_id=XX&type=tennis&montant=7.50\n";
echo "     (le type peut être : daily, weekend, weekly, tennis, vip_max, fun, rasstoss)\n";
