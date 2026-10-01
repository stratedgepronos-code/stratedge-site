<?php
/**
 * NE PAS METTRE DE VRAI TOKEN ICI — ce fichier est versionné (Git).
 *
 * 1. Copie ce fichier en :  telegram-config.local.php  (même dossier includes/)
 * 2. Dans telegram-config.local.php uniquement : remplace les valeurs ci-dessous
 *
 * telegram-config.local.php est dans .gitignore → Git ne le versionne pas.
 *
 * COMMENT OBTENIR LES VALEURS :
 *   1. Crée un bot via @BotFather sur Telegram → tu obtiens le BOT_TOKEN
 *      (format: "1234567890:ABCdefGHIjklMNOpqrSTUvwxyz")
 *   2. Crée un canal public (ex: @stratedgepronos) ou privé
 *   3. Ajoute le bot comme administrateur du canal (permission "Post Messages")
 *   4. CHANNEL_ID :
 *      - Canal public : '@stratedgepronos' (avec le @)
 *      - Canal privé : '-100XXXXXXXXXX' (récupérable via @userinfobot ou les Bot API)
 */
if (!defined('ABSPATH')) {
    define('ABSPATH', true);
}

// Bot token (format: 'XXXXXXXXXX:ABC...')
define('TELEGRAM_BOT_TOKEN', 'TON_TOKEN_ICI');

// Canal de destination (public: '@stratedgepronos' ou privé: '-100XXX...')
define('TELEGRAM_CHANNEL_ID', '@TON_CANAL_ICI');

// (Optionnel) URL publique du canal — utilisée dans les emails/site pour inviter les gens
define('TELEGRAM_CHANNEL_URL', 'https://t.me/stratedgepronos');
