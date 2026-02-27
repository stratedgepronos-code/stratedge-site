<?php
// ============================================================
// STRATEDGE — Polling AJAX pour le chat admin
// Retourne le nombre de messages + badges non-lus
// ============================================================
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
header('Content-Type: application/json');

$db     = getDB();
$chatId = (int)($_GET['chat_id'] ?? 0);

// Compte messages dans la conv ouverte
$count = 0;
if ($chatId) {
    $stmt = $db->prepare("SELECT COUNT(*) FROM chat_messages WHERE chat_id = ?");
    $stmt->execute([$chatId]);
    $count = (int)$stmt->fetchColumn();
}

// Infos non-lus pour toutes les convs
$convs = $db->query("
    SELECT c.id,
        (SELECT COUNT(*) FROM chat_messages cm 
         WHERE cm.chat_id = c.id AND cm.expediteur = 'membre' AND cm.lu = 0) as nb_nonlus
    FROM chats c
")->fetchAll(PDO::FETCH_ASSOC);

$totalNonlus = array_sum(array_column($convs, 'nb_nonlus'));

echo json_encode([
    'count'         => $count,
    'total_nonlus'  => $totalNonlus,
    'conversations' => $convs,
]);
