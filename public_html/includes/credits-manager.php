<?php
/**
 * STRATEDGE — Gestionnaire de crédits Multi (packs)
 * 
 * RÈGLE CLÉ (depuis 16/04/2026):
 * 1 crédit = 24h d'accès à TOUS les bets du superadmin (Alex)
 * Quand un membre utilise 1 crédit pour débloquer un bet:
 *   → Il obtient un "pass 24h" qui couvre tous les bets superadmin
 *   → Les bets postés dans les 24h suivantes sont accessibles gratuitement
 *   → Après 24h, un nouveau crédit est nécessaire
 * 
 * Ne concerne PAS les bets Tennis (admin_tennis) ni Fun (admin_fun)
 * qui fonctionnent par abonnement séparé.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/packs-config.php';

if (!function_exists('stratedge_credits_ajouter')) {

// ── Auto-create pass_24h table if missing ──
function _stratedge_ensure_pass_table(): void {
    static $done = false;
    if ($done) return;
    try {
        $db = getDB();
        $db->exec("CREATE TABLE IF NOT EXISTS credits_pass_24h (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            membre_id INT UNSIGNED NOT NULL,
            pass_start DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            pass_expire DATETIME NOT NULL,
            credit_pack_id INT UNSIGNED DEFAULT NULL,
            KEY idx_membre_expire (membre_id, pass_expire)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Throwable $e) { /* silencieux si déjà créée */ }
    $done = true;
}

/**
 * Ajouter des crédits (après achat pack)
 */
function stratedge_credits_ajouter(int $membreId, string $packKey, string $methode, ?string $txRef = null): int {
    $pack = stratedge_pack_get($packKey);
    if (!$pack || $membreId <= 0 || !in_array($methode, ['sms','stripe','crypto'], true)) return 0;
    try {
        $db = getDB();
        $stmt = $db->prepare("INSERT INTO credits_paris (membre_id, nb_initial, nb_restants, pack_type, prix_paye, methode, transaction_ref) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$membreId, $pack['nb'], $pack['nb'], $pack['key'], $pack['prix'], $methode, $txRef]);
        return (int)$db->lastInsertId();
    } catch (Throwable $e) { error_log('[credits] ajouter: '.$e->getMessage()); return 0; }
}

/**
 * Solde de crédits restants
 */
function stratedge_credits_solde(int $membreId): int {
    if ($membreId <= 0) return 0;
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT COALESCE(SUM(nb_restants),0) FROM credits_paris WHERE membre_id=?");
        $stmt->execute([$membreId]);
        return (int)$stmt->fetchColumn();
    } catch (Throwable $e) { return 0; }
}

/**
 * Vérifier si le membre a un pass 24h actif
 */
function stratedge_credits_has_pass_24h(int $membreId): bool {
    if ($membreId <= 0) return false;
    _stratedge_ensure_pass_table();
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT 1 FROM credits_pass_24h WHERE membre_id=? AND pass_expire > NOW() LIMIT 1");
        $stmt->execute([$membreId]);
        return (bool)$stmt->fetchColumn();
    } catch (Throwable $e) { return false; }
}

/**
 * Récupérer la date d'expiration du pass 24h actif (ou null)
 */
function stratedge_credits_pass_expire(int $membreId): ?string {
    if ($membreId <= 0) return null;
    _stratedge_ensure_pass_table();
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT pass_expire FROM credits_pass_24h WHERE membre_id=? AND pass_expire > NOW() ORDER BY pass_expire DESC LIMIT 1");
        $stmt->execute([$membreId]);
        $r = $stmt->fetchColumn();
        return $r ?: null;
    } catch (Throwable $e) { return null; }
}

/**
 * Vérifier si un bet spécifique a déjà été débloqué par ce membre
 * (soit via unlock individuel, soit via pass 24h actif)
 */
function stratedge_credits_deja_consulte(int $membreId, int $betId): bool {
    if ($membreId <= 0 || $betId <= 0) return false;
    try {
        $db = getDB();
        // 1. Unlock individuel (ancien système, toujours valable pour les vieux unlocks)
        $stmt = $db->prepare("SELECT 1 FROM credits_consommation WHERE membre_id=? AND bet_id=? LIMIT 1");
        $stmt->execute([$membreId, $betId]);
        if ($stmt->fetchColumn()) return true;
        
        // 2. Pass 24h actif → accès à TOUS les bets superadmin
        // On vérifie que le bet est bien du superadmin
        $stmtBet = $db->prepare("SELECT posted_by_role FROM bets WHERE id=? LIMIT 1");
        $stmtBet->execute([$betId]);
        $betRole = $stmtBet->fetchColumn();
        
        if ($betRole === 'superadmin' && stratedge_credits_has_pass_24h($membreId)) {
            return true;
        }
        
        return false;
    } catch (Throwable $e) { return false; }
}

/**
 * Consommer un crédit pour débloquer un bet
 * 
 * Nouvelle logique 24h pass:
 * - Si pass 24h actif → accès gratuit (pas de consommation)
 * - Si pas de pass → consomme 1 crédit + crée un pass 24h
 * - Le pass 24h couvre TOUS les bets du superadmin pendant 24h
 */
function stratedge_credits_consommer(int $membreId, int $betId): bool {
    if ($membreId <= 0 || $betId <= 0) return false;
    
    // Déjà débloqué (individuel ou pass) = gratuit
    if (stratedge_credits_deja_consulte($membreId, $betId)) return true;
    
    _stratedge_ensure_pass_table();
    
    try {
        $db = getDB();
        
        // Vérifier si pass 24h actif (ne devrait pas arriver vu deja_consulte, mais sécurité)
        if (stratedge_credits_has_pass_24h($membreId)) {
            // Pass actif → enregistrer l'accès sans consommer de crédit
            $db->prepare("INSERT IGNORE INTO credits_consommation (membre_id, bet_id, credit_pack_id) VALUES (?, ?, 0)")
               ->execute([$membreId, $betId]);
            return true;
        }
        
        // Pas de pass actif → consommer 1 crédit + créer le pass 24h
        $db->beginTransaction();
        
        // Trouver le pack avec des crédits restants (FIFO)
        $stmt = $db->prepare("SELECT id FROM credits_paris WHERE membre_id=? AND nb_restants>0 ORDER BY date_achat ASC LIMIT 1 FOR UPDATE");
        $stmt->execute([$membreId]);
        $packId = (int)$stmt->fetchColumn();
        if ($packId === 0) { $db->rollBack(); return false; }
        
        // Débiter 1 crédit
        $db->prepare("UPDATE credits_paris SET nb_restants=nb_restants-1 WHERE id=?")->execute([$packId]);
        
        // Enregistrer l'accès individuel (compatibilité)
        $db->prepare("INSERT INTO credits_consommation (membre_id, bet_id, credit_pack_id) VALUES (?, ?, ?)")
           ->execute([$membreId, $betId, $packId]);
        
        // Créer le pass 24h
        $db->prepare("INSERT INTO credits_pass_24h (membre_id, pass_expire, credit_pack_id) VALUES (?, DATE_ADD(NOW(), INTERVAL 24 HOUR), ?)")
           ->execute([$membreId, $packId]);
        
        $db->commit();
        
        error_log("[credits] Pass 24h créé pour membre #$membreId (pack #$packId, bet déclencheur #$betId)");
        return true;
    } catch (Throwable $e) {
        if (isset($db) && $db->inTransaction()) $db->rollBack();
        error_log('[credits] consommer: '.$e->getMessage());
        return false;
    }
}

/**
 * Historique des achats de packs
 */
function stratedge_credits_historique(int $membreId, int $limit = 20): array {
    if ($membreId <= 0) return [];
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM credits_paris WHERE membre_id=? ORDER BY date_achat DESC LIMIT ?");
        $stmt->bindValue(1, $membreId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) { return []; }
}

/**
 * Stats admin (revenus packs crédits)
 */
function stratedge_credits_stats_admin(?int $days = 30): array {
    try {
        $db = getDB();
        $where = $days ? "WHERE date_achat >= DATE_SUB(NOW(), INTERVAL ? DAY)" : "";
        $sql = "SELECT pack_type, methode, COUNT(*) AS nb_ventes, SUM(prix_paye) AS ca_total, SUM(nb_initial) AS credits_vendus FROM credits_paris $where GROUP BY pack_type, methode ORDER BY ca_total DESC";
        $stmt = $db->prepare($sql);
        if ($days) $stmt->execute([$days]); else $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) { return []; }
}

}
