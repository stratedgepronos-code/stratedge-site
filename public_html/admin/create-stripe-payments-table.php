<?php
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
header('Content-Type: text/plain; charset=utf-8');

$sql = "CREATE TABLE IF NOT EXISTS stripe_payments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    membre_id INT UNSIGNED NOT NULL,
    offre VARCHAR(30) NOT NULL,
    stripe_session_id VARCHAR(120) NOT NULL,
    order_id VARCHAR(80) NOT NULL,
    montant_eur DECIMAL(8,2) NOT NULL,
    stripe_account VARCHAR(20) DEFAULT 'multi',
    statut VARCHAR(20) DEFAULT 'validé',
    date_paiement DATETIME DEFAULT CURRENT_TIMESTAMP,
    KEY idx_session (stripe_session_id),
    KEY idx_membre (membre_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

try {
    $db = getDB();
    $db->exec($sql);
    echo "✅ Table stripe_payments créée (ou déjà existante)\n";
    $count = $db->query("SELECT COUNT(*) FROM stripe_payments")->fetchColumn();
    echo "   Lignes : $count\n";
} catch (Throwable $e) {
    echo "❌ Erreur : " . $e->getMessage() . "\n";
}
