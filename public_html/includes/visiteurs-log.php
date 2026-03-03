<?php
/**
 * StratEdge — Enregistrement des visites pour le compteur (dashboard admin).
 * Stockage en base de données (table visites) pour ne pas être remis à zéro au déploiement.
 */

function log_visite(): void {
    try {
        $db = getDB();
        $stmt = $db->prepare("INSERT INTO visites (t) VALUES (?)");
        $stmt->execute([time()]);
    } catch (Throwable $e) {
        // Table peut ne pas exister encore : silence pour ne pas casser la page
    }
}
