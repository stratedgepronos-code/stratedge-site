<?php
/**
 * StratEdge — Enregistrement des visites pour le compteur (dashboard admin).
 * Compte les visiteurs UNIQUES : 1 ligne par visiteur par jour (identifié par IP + User-Agent).
 */

function log_visite(): void {
    try {
        $db = getDB();
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $visitor_id = substr(hash('sha256', $ip . "\0" . $ua), 0, 64);
        $todayStart = strtotime('today');

        // Ne rien insérer si ce visiteur a déjà été logué aujourd'hui
        $check = $db->prepare("SELECT 1 FROM visites WHERE visitor_id = ? AND t >= ? LIMIT 1");
        $check->execute([$visitor_id, $todayStart]);
        if ($check->fetch()) return;

        $stmt = $db->prepare("INSERT INTO visites (visitor_id, t) VALUES (?, ?)");
        $stmt->execute([$visitor_id, time()]);
    } catch (Throwable $e) {
        try {
            $db = getDB();
            $stmt = $db->prepare("INSERT INTO visites (t) VALUES (?)");
            $stmt->execute([time()]);
        } catch (Throwable $e2) {
            // Ne pas casser la page
        }
    }
}
