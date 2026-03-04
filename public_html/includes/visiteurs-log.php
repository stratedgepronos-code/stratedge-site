<?php
/**
 * StratEdge — Enregistrement des visites pour le compteur (dashboard admin).
 * Compte les visiteurs UNIQUES (un même visiteur = une entrée par période, identifié par IP + User-Agent).
 * Stockage en base : table visites (visitor_id, t) pour stats "visiteurs uniques".
 */

function log_visite(): void {
    try {
        $db = getDB();
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $visitor_id = substr(hash('sha256', $ip . "\0" . $ua), 0, 64);

        $stmt = $db->prepare("INSERT INTO visites (visitor_id, t) VALUES (?, ?)");
        $stmt->execute([$visitor_id, time()]);
    } catch (Throwable $e) {
        // Table ou colonne visitor_id peut ne pas exister : fallback sans visitor_id
        try {
            $db = getDB();
            $stmt = $db->prepare("INSERT INTO visites (t) VALUES (?)");
            $stmt->execute([time()]);
        } catch (Throwable $e2) {
            // Ne pas casser la page
        }
    }
}
