<?php
// ============================================================
// STRATEDGE — Notifications Montante Tennis (push + email)
// Envoi à tous les membres inscrits (avec push et/ou email selon préférences)
// ============================================================

if (!function_exists('getDB')) {
    require_once __DIR__ . '/db.php';
}
require_once __DIR__ . '/push.php';
require_once __DIR__ . '/mailer.php';

/**
 * Retourne tous les membres actifs pouvant recevoir des notifications (email).
 */
function getTousMembresPourNotifications(): array {
    $db = getDB();
    $stmt = $db->query("
        SELECT id, email, nom FROM membres
        WHERE actif = 1 AND email != '' AND email IS NOT NULL
        AND (accepte_emails IS NULL OR accepte_emails = 1)
    ");
    return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
}

/**
 * Notifications (push + email) pour le démarrage d'une montante.
 */
function notifyMontanteDemarrage(array $config): void {
    $nom = $config['nom'] ?? 'Montante Tennis';
    $montant = number_format((float)($config['bankroll_initial'] ?? 100), 2, ',', ' ');
    $title = '🎾 Nouvelle Montante Tennis';
    $body = $nom . ' — Montant visé : ' . $montant . ' €';
    envoyerPush(null, $title, $body, '/montante-tennis.php', 'montante-demarrage', true);

    foreach (getTousMembresPourNotifications() as $m) {
        emailMontanteDemarrage($m['email'], $m['nom'] ?? 'Membre', $config);
    }
}

/**
 * Notifications (push + email) pour une nouvelle étape.
 */
function notifyMontanteNouvelleEtape(array $step, array $config): void {
    $match = $step['match_desc'] ?? 'Nouveau prono';
    $stepNum = (int)($step['step_number'] ?? 1);
    $title = '⚡ Step ' . $stepNum . ' — Montante Tennis';
    $body = $match;
    envoyerPush(null, $title, $body, '/montante-tennis.php', 'montante-step', true);

    foreach (getTousMembresPourNotifications() as $m) {
        emailMontanteNouvelleEtape($m['email'], $m['nom'] ?? 'Membre', $step, $config);
    }
}

/**
 * Notifications (push + email) pour un résultat d'étape.
 */
function notifyMontanteResultat(array $step, array $config, string $resultat): void {
    $labels = ['gagne' => '✅ Gagné', 'perdu' => '❌ Perdu', 'annule' => '↺ Annulé'];
    $stepNum = (int)($step['step_number'] ?? 0);
    $label = $labels[$resultat] ?? $resultat;
    $title = 'Résultat Step ' . $stepNum . ' — Montante Tennis';
    $body = ($step['match_desc'] ?? 'Prono') . ' → ' . $label;
    envoyerPush(null, $title, $body, '/montante-tennis.php', 'montante-resultat', true);

    foreach (getTousMembresPourNotifications() as $m) {
        emailMontanteResultat($m['email'], $m['nom'] ?? 'Membre', $step, $config, $resultat);
    }
}
