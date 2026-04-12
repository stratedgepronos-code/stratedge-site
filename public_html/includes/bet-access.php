<?php
// STRATEDGE — Helper unifié accès bets (abonnement OU crédits)
require_once __DIR__ . '/credits-manager.php';

/**
 * Retourne true si le membre a accès au bet :
 * - soit via un abonnement actif (VIP MAX, Tennis...)
 * - soit en consommant un crédit (1ère consult) ou déjà consulté (gratuit)
 */
function stratedge_bet_acces(array $bet, ?array $membre): bool {
    if (!$membre) return false;
    $membreId = (int)$membre['id'];
    if ($membreId <= 0) return false;

    // Admin = accès total
    if (function_exists('isAdmin') && isAdmin()) return true;

    $categorie = $bet['categorie'] ?? 'multi';
    $betId = (int)($bet['id'] ?? 0);

    // 1) VIP MAX → accès à tout
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT type FROM abonnements WHERE membre_id=? AND actif=1 AND (date_fin>NOW() OR type='vip_max')");
        $stmt->execute([$membreId]);
        $typesActifs = $stmt->fetchAll(PDO::FETCH_COLUMN);
        if (in_array('vip_max', $typesActifs, true)) return true;
        // Tennis dédié pour les bets tennis
        if ($categorie === 'tennis' && in_array('tennis', $typesActifs, true)) return true;
    } catch (Throwable $e) {}

    // 2) Pour les bets Multisports : crédits
    if ($categorie === 'multi' && $betId > 0) {
        // Déjà consulté = gratuit
        if (stratedge_credits_deja_consulte($membreId, $betId)) return true;
        // Sinon tenter consommation
        return stratedge_credits_consommer($membreId, $betId);
    }
    return false;
}
