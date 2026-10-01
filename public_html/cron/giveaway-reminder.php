<?php
// ============================================================
// STRATEDGE — Cron GiveAway Reminder
// cron/giveaway-reminder.php
// Envoie un email rappel au super admin le dernier jour du mois
// à 21h pour lui rappeler de faire tourner la roue.
//
// CRON : 0 21 28-31 * * /usr/bin/php /home/u527192911/public_html/cron/giveaway-reminder.php
// (tourne chaque soir du 28 au 31, le script vérifie si c'est le dernier jour)
// ============================================================

define('ABSPATH', true);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/mailer.php';
require_once __DIR__ . '/../includes/giveaway-functions.php';

$tz = new DateTimeZone('Europe/Paris');
$now = new DateTime('now', $tz);

// Vérifier si c'est le dernier jour du mois
$lastDay = (int)$now->format('t'); // nombre de jours dans le mois
$today = (int)$now->format('d');

if ($today !== $lastDay) {
    echo "Pas le dernier jour du mois ($today/$lastDay). Skip.\n";
    exit;
}

$mois = $now->format('Y-m');
$config = getGiveawayConfig($mois);
$classement = getClassementMois($mois);
$totalPts = array_sum(array_column($classement, 'total_pts'));
$nbParticipants = count($classement);

// Déjà tiré ?
if ($config['statut'] === 'drawn') {
    echo "Tirage déjà effectué pour $mois. Skip.\n";
    exit;
}

// Pas de participants ?
if ($nbParticipants === 0) {
    echo "Aucun participant pour $mois. Skip.\n";
    exit;
}

// Envoyer le rappel
$sujet = '🎁 GiveAway ' . moisFrancais($mois) . ' — Il est temps de tirer au sort !';
$top5 = array_slice($classement, 0, 5);
$top5Html = '';
foreach ($top5 as $i => $p) {
    $pct = $totalPts > 0 ? round($p['total_pts'] / $totalPts * 100, 1) : 0;
    $top5Html .= '<tr><td style="padding:4px 8px;color:#f5c842;font-weight:700;">' . ($i + 1) . '</td>'
        . '<td style="padding:4px 8px;">' . htmlspecialchars($p['nom']) . '</td>'
        . '<td style="padding:4px 8px;color:#00d4ff;font-weight:700;">' . $p['total_pts'] . ' pts</td>'
        . '<td style="padding:4px 8px;color:#8a9bb0;">' . $pct . '%</td></tr>';
}

$cadeau = $config['cadeau'] ?? '<em>Non défini</em>';

$body = emailTemplate('🎁 Rappel GiveAway', '
    <p style="font-size:16px;">C\'est le <strong>dernier jour du mois</strong> — il est temps de faire tourner la roue !</p>
    
    <div style="background:#111827;border:1px solid rgba(255,45,120,0.2);border-radius:12px;padding:1rem;margin:1rem 0;">
        <p style="font-size:13px;color:#8a9bb0;letter-spacing:1px;">RÉSUMÉ · ' . strtoupper(moisFrancais($mois)) . '</p>
        <p style="font-size:24px;font-weight:700;color:#fff;margin:.3rem 0;">' . $nbParticipants . ' participants · ' . $totalPts . ' points</p>
        <p style="font-size:14px;color:#8a9bb0;">Cadeau : <span style="color:#ff2d78;font-weight:700;">' . $cadeau . '</span></p>
    </div>
    
    <p style="font-weight:700;margin:.8rem 0 .4rem;">Top 5 :</p>
    <table style="width:100%;border-collapse:collapse;font-size:14px;color:#fff;">
        ' . $top5Html . '
    </table>
    
    <p style="margin-top:1.2rem;">
        <a href="https://stratedgepronos.fr/panel-x9k3m/giveaway.php" style="display:inline-block;background:linear-gradient(135deg,#ff2d78,#c4185a);color:#fff;padding:12px 24px;border-radius:8px;text-decoration:none;font-weight:700;font-size:15px;">
            🎰 Faire tourner la roue →
        </a>
    </p>
');

envoyerEmail(ADMIN_EMAIL, $sujet, $body);
echo "Rappel envoyé pour $mois ($nbParticipants participants, $totalPts pts).\n";
