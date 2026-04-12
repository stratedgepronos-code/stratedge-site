<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/packs-config.php';

if (!function_exists('stratedge_credits_ajouter')) {

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

function stratedge_credits_solde(int $membreId): int {
    if ($membreId <= 0) return 0;
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT COALESCE(SUM(nb_restants),0) FROM credits_paris WHERE membre_id=?");
        $stmt->execute([$membreId]);
        return (int)$stmt->fetchColumn();
    } catch (Throwable $e) { return 0; }
}

function stratedge_credits_deja_consulte(int $membreId, int $betId): bool {
    if ($membreId <= 0 || $betId <= 0) return false;
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT 1 FROM credits_consommation WHERE membre_id=? AND bet_id=? LIMIT 1");
        $stmt->execute([$membreId, $betId]);
        return (bool)$stmt->fetchColumn();
    } catch (Throwable $e) { return false; }
}

function stratedge_credits_consommer(int $membreId, int $betId): bool {
    if ($membreId <= 0 || $betId <= 0) return false;
    if (stratedge_credits_deja_consulte($membreId, $betId)) return true;
    try {
        $db = getDB();
        $db->beginTransaction();
        $stmt = $db->prepare("SELECT id FROM credits_paris WHERE membre_id=? AND nb_restants>0 ORDER BY date_achat ASC LIMIT 1 FOR UPDATE");
        $stmt->execute([$membreId]);
        $packId = (int)$stmt->fetchColumn();
        if ($packId === 0) { $db->rollBack(); return false; }
        $db->prepare("UPDATE credits_paris SET nb_restants=nb_restants-1 WHERE id=?")->execute([$packId]);
        $db->prepare("INSERT INTO credits_consommation (membre_id, bet_id, credit_pack_id) VALUES (?, ?, ?)")->execute([$membreId, $betId, $packId]);
        $db->commit();
        return true;
    } catch (Throwable $e) {
        if (isset($db) && $db->inTransaction()) $db->rollBack();
        error_log('[credits] consommer: '.$e->getMessage());
        return false;
    }
}

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
