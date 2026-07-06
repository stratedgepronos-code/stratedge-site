<?php
/**
 * StratEdge — Nettoyage des inscriptions bots existantes
 * USAGE : php cleanup_bots.php --dry-run   (liste sans supprimer — TOUJOURS commencer par ça)
 *         php cleanup_bots.php --execute   (supprime après vérification)
 *
 * Heuristiques de détection (score cumulatif, seuil = 2 points) :
 *  +2  email jamais vérifié ET aucune connexion depuis l'inscription
 *  +1  email sur domaine jetable
 *  +1  username aléatoire (ratio consonnes/pattern hexadécimal/chiffres massifs)
 *  +1  inscription dans une rafale (>5 comptes dans la même minute)
 *  +1  aucun abonnement/paiement/activité
 *
 * ⚠️ ADAPTER les noms de table/colonnes à ton schéma réel avant exécution.
 */

$config = require __DIR__ . '/../config.php'; // adapter le chemin
$db = new PDO($config['dsn'], $config['db_user'], $config['db_pass'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

$dryRun = !in_array('--execute', $argv ?? [], true);
echo $dryRun ? "=== DRY RUN (aucune suppression) ===\n" : "=== EXÉCUTION RÉELLE ===\n";

$blocklist = array_filter(array_map('trim', @file(__DIR__ . '/disposable_domains.txt') ?: []));

// Rafales : minutes avec >5 inscriptions
$bursts = $db->query(
    "SELECT DATE_FORMAT(created_at, '%Y-%m-%d %H:%i') AS m, COUNT(*) c
     FROM users GROUP BY m HAVING c > 5"
)->fetchAll(PDO::FETCH_KEY_PAIR);

$users = $db->query(
    "SELECT id, username, email, email_verified, last_login, created_at,
            (SELECT COUNT(*) FROM subscriptions s WHERE s.user_id = users.id) AS subs
     FROM users"
)->fetchAll(PDO::FETCH_ASSOC);

$suspects = [];
foreach ($users as $u) {
    $score = 0; $reasons = [];

    if (!$u['email_verified'] && $u['last_login'] === null) { $score += 2; $reasons[] = 'jamais vérifié+jamais connecté'; }

    $domain = substr(strrchr(strtolower($u['email']), '@') ?: '', 1);
    if (in_array($domain, $blocklist, true)) { $score += 1; $reasons[] = 'email jetable'; }

    $name = $u['username'];
    if (preg_match('/^[a-f0-9]{8,}$/i', $name)                       // hex
        || preg_match('/\d{5,}/', $name)                              // gros bloc de chiffres
        || (strlen($name) > 7 && !preg_match('/[aeiouy]/i', $name))) // aucune voyelle
    { $score += 1; $reasons[] = 'username aléatoire'; }

    $minute = substr($u['created_at'], 0, 16);
    if (isset($bursts[str_replace('T', ' ', $minute)])) { $score += 1; $reasons[] = 'rafale'; }

    if ((int)$u['subs'] === 0) { $score += 1; $reasons[] = 'zéro activité'; }

    if ($score >= 2) $suspects[] = ['u' => $u, 'score' => $score, 'why' => implode(', ', $reasons)];
}

usort($suspects, fn($a, $b) => $b['score'] <=> $a['score']);
foreach ($suspects as $s) {
    printf("[score %d] #%d %-24s %-35s → %s\n",
        $s['score'], $s['u']['id'], $s['u']['username'], $s['u']['email'], $s['why']);
}
echo count($suspects) . " comptes suspects détectés.\n";

if (!$dryRun && $suspects) {
    // Soft delete recommandé (colonne deleted_at) plutôt que DELETE définitif
    $ids = array_column(array_column($suspects, 'u'), 'id');
    $in  = implode(',', array_fill(0, count($ids), '?'));
    $db->prepare("UPDATE users SET deleted_at = NOW() WHERE id IN ($in)")->execute($ids);
    echo "→ " . count($ids) . " comptes marqués supprimés (soft delete).\n";
}
