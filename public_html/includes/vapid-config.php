<?php
// ============================================================
// STRATEDGE — Configuration Web Push (VAPID)
// ============================================================
// Pour générer tes clés VAPID :
// 1. Va sur https://vapidkeys.com  (site gratuit)
// 2. Clique "Generate" → copie les 2 clés
// 3. Colle-les ci-dessous
// OU utilise cette commande sur ton PC :
//   npx web-push generate-vapid-keys
// ============================================================

define('VAPID_PUBLIC_KEY',  'BHdwGFNT8DZpkvJ9G-cfUra0ZuO0tCejCqzzpsbPwIMBTSvbvIBQ-tIQzhobnTn5V9kuuDJriNQvpfJTo8oTjzA');
define('VAPID_PRIVATE_KEY', 'xs1-4AZQMqwCVZlDKnRffBrMwYYk7m_7A_gdWvlD8wA');
define('VAPID_SUBJECT',     'mailto:stratedgepronos@gmail.com');

// ── Librairie Web Push PHP ─────────────────────────────────
// Pour envoyer des pushs depuis PHP, on a besoin de minishlink/web-push
// Installation sur Hostinger via Composer :
//   1. Connecte-toi en SSH ou via le terminal Hostinger
//   2. cd ~/public_html
//   3. composer require minishlink/web-push
// Si pas de Composer disponible, on utilise la méthode cURL manuelle ci-dessous

define('USE_COMPOSER_WEBPUSH', false); // Passe à true si Composer installé
