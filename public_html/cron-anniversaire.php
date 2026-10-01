<?php
/**
 * StratEdge — Envoi des codes promo anniversaire (à lancer 1×/jour en cron)
 *
 * À exécuter chaque jour (ex. 9h) :
 *   php /chemin/vers/public_html/cron-anniversaire.php
 * ou en HTTP (à réserver au cron). Pour activer, ajouter dans includes/db.php :
 *   define('CRON_ANNIV_KEY', 'votre-cle-secrete');
 * puis : curl -s "https://stratedgepronos.fr/cron-anniversaire.php?key=votre-cle-secrete"
 *
 * Pour chaque membre dont c’est l’anniversaire (date_naissance = jour J),
 * accepte les emails, et qui n’a pas déjà reçu son code cette année :
 * - crée un code promo ANNIV-{id}-{année} (valable 1 fois, 50% / 25% VIP Max)
 * - envoie un email avec le code.
 */
if (php_sapi_name() !== 'cli') {
    // Appel HTTP : vérifier une clé (définir CRON_ANNIV_KEY dans includes/db.php ou config)
    $key = $_GET['key'] ?? '';
    $expected = defined('CRON_ANNIV_KEY') ? CRON_ANNIV_KEY : '';
    if ($expected === '' || $key === '' || !hash_equals($expected, $key)) {
        http_response_code(403);
        exit('Forbidden');
    }
}

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/promo.php';
require_once __DIR__ . '/includes/mailer.php';

$today = date('m-d');
$db = getDB();

// Membres dont c’est l’anniversaire aujourd’hui, avec date_naissance et qui acceptent les mails
$stmt = $db->prepare("
    SELECT id, nom, email
    FROM membres
    WHERE date_naissance IS NOT NULL AND date_naissance != ''
      AND (accepte_emails IS NULL OR accepte_emails = 1)
      AND email != ?
      AND DATE_FORMAT(date_naissance, '%m-%d') = ?
");
$stmt->execute([defined('ADMIN_EMAIL') ? ADMIN_EMAIL : 'stratedgepronos@gmail.com', $today]);
$membres = $stmt->fetchAll(PDO::FETCH_ASSOC);

$envoyes = 0;
$erreurs = 0;

foreach ($membres as $m) {
    $code = creerCodeAnniversaireMembre((int) $m['id']);
    if ($code === null) {
        continue; // déjà envoyé cette année ou erreur
    }
    if (emailAnniversaireCodePromo($m['email'], $m['nom'], $code)) {
        $envoyes++;
        if (php_sapi_name() === 'cli') {
            echo "OK: code envoyé à {$m['email']} ({$m['nom']})\n";
        }
    } else {
        $erreurs++;
        if (php_sapi_name() === 'cli') {
            echo "ERREUR: envoi mail à {$m['email']}\n";
        }
    }
}

if (php_sapi_name() === 'cli') {
    echo "Anniversaires du jour: " . count($membres) . " membre(s), $envoyes email(s) envoyé(s), $erreurs erreur(s).\n";
}
