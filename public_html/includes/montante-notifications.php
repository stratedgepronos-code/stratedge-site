<?php
// ============================================================
// STRATEDGE — Notifications Montantes (push + email)
// Supporte les 2 contextes:
//   - 'tennis'      → Montante Tennis (sport unique tennis)
//   - 'multi-sport' → Montante Multi-sport (foot + autres sports)
//
// Le contexte définit le titre push, l'URL de redirection, et le tag.
// ============================================================

if (!function_exists('getDB')) {
    require_once __DIR__ . '/db.php';
}
require_once __DIR__ . '/push.php';
require_once __DIR__ . '/mailer.php';

/**
 * Configuration des contextes de montante
 */
function _montanteContextConfig(string $context): array {
    $configs = [
        'tennis' => [
            'emoji'    => '🎾',
            'label'    => 'Montante Tennis',
            'url'      => '/montante-tennis.php',
            'tag_pfx'  => 'montante-tennis',
        ],
        'multi-sport' => [
            'emoji'    => '⚽',
            'label'    => 'Montante Multi-sport',
            'url'      => '/montante-foot.php', // gardé pour compat — peut-être renommé plus tard
            'tag_pfx'  => 'montante-multi',
        ],
    ];
    return $configs[$context] ?? $configs['tennis'];
}

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
 * Notifications (push + Telegram) pour le démarrage d'une montante.
 * @param array  $config   Config de la montante (nom, bankroll_initial, etc.)
 * @param string $context  'tennis' ou 'multi-sport'
 */
function notifyMontanteDemarrage(array $config, string $context = 'tennis'): void {
    $ctx = _montanteContextConfig($context);
    $nom = $config['nom'] ?? $ctx['label'];
    $montant = number_format((float)($config['bankroll_initial'] ?? 100), 2, ',', ' ');
    $title = $ctx['emoji'] . ' Nouvelle ' . $ctx['label'];
    $body = $nom . ' — Montant visé : ' . $montant . ' €';
    envoyerPush(null, $title, $body, $ctx['url'], $ctx['tag_pfx'] . '-demarrage', true);

    // 📱 Annonce Telegram canal public (1 seul message, pas N emails — mai 2026)
    if (file_exists(__DIR__ . '/telegram.php')) {
        require_once __DIR__ . '/telegram.php';
        if (function_exists('telegramAnnonceMontanteDemarrage')) {
            @telegramAnnonceMontanteDemarrage($config, $context);
        }
    }
}

/**
 * Notifications (push + Telegram) pour une nouvelle étape.
 */
function notifyMontanteNouvelleEtape(array $step, array $config, string $context = 'tennis'): void {
    $ctx = _montanteContextConfig($context);
    $match = $step['match_desc'] ?? 'Nouveau prono';
    $stepNum = (int)($step['step_number'] ?? 1);
    $title = '⚡ Step ' . $stepNum . ' — ' . $ctx['label'];
    $body = $match;
    envoyerPush(null, $title, $body, $ctx['url'], $ctx['tag_pfx'] . '-step', true);

    // 📱 Annonce Telegram canal public
    if (file_exists(__DIR__ . '/telegram.php')) {
        require_once __DIR__ . '/telegram.php';
        if (function_exists('telegramAnnonceMontanteNouvelleEtape')) {
            @telegramAnnonceMontanteNouvelleEtape($step, $config, $context);
        }
    }
}

/**
 * Notifications (push + Telegram) pour un résultat d'étape.
 */
function notifyMontanteResultat(array $step, array $config, string $resultat, string $context = 'tennis'): void {
    $ctx = _montanteContextConfig($context);
    $labels = ['gagne' => '✅ Gagné', 'perdu' => '❌ Perdu', 'annule' => '↺ Annulé'];
    $stepNum = (int)($step['step_number'] ?? 0);
    $label = $labels[$resultat] ?? $resultat;
    $title = 'Résultat Step ' . $stepNum . ' — ' . $ctx['label'];
    $body = ($step['match_desc'] ?? 'Prono') . ' → ' . $label;
    envoyerPush(null, $title, $body, $ctx['url'], $ctx['tag_pfx'] . '-resultat', true);

    // 📱 Annonce Telegram canal public
    if (file_exists(__DIR__ . '/telegram.php')) {
        require_once __DIR__ . '/telegram.php';
        if (function_exists('telegramAnnonceMontanteResultat')) {
            @telegramAnnonceMontanteResultat($step, $config, $resultat, $context);
        }
    }
}
