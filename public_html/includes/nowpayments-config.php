<?php
// ============================================================
// STRATEDGE — Configuration NOWPayments
// ⚠️ NE JAMAIS COMMITTER CE FICHIER SUR GIT
// ============================================================
// 1. Va sur https://nowpayments.io → Paramètres → Clés API
// 2. Crée une clé API et colle-la dans NP_API_KEY
// 3. Génère une clé IPN secrète (Paramètres → Clé secrète IPN)
//    ⚠️ Elle n'est affichée QU'UNE SEULE FOIS → la noter immédiatement
//    et coller dans NP_IPN_SECRET
// ============================================================

define('NP_API_KEY',    '7YWP87S-Z8Y4DBD-MWXQDCV-QZ61Y5R');        // ex: "abc123..."
define('NP_IPN_SECRET', 'Ppo/vZbjy9RtnXo4G7F3PLsWADp9rNpf');     // ex: "xyz789..."
define('NP_API_BASE',   'https://api.nowpayments.io/v1');

// Taux de change EUR → crypto
// NOWPayments gère la conversion automatiquement, on lui envoie le prix en EUR
// et il calcule le montant exact en crypto au moment du paiement
define('NP_CURRENCY_FROM', 'eur');                   // devise de base (euros)

// Mapping crypto StratEdge → code NOWPayments
// Pour voir tous les codes disponibles : GET /currencies
define('NP_CRYPTO_MAP', [
    'btc'  => 'btc',
    'eth'  => 'eth',
    'usdc' => 'usdcmatic',   // USDC sur réseau Polygon
    'sol'  => 'sol',
    'bnb'  => 'bnbbsc',      // BNB sur BNB Chain (BEP-20)
]);

// URL de callback — page de succès visible par le client
define('NP_SUCCESS_URL',  'https://stratedgepronos.fr/dashboard.php');
define('NP_CANCEL_URL',   'https://stratedgepronos.fr/offre-daily.php');

// Durée de validité d'un paiement (en secondes) — 60 min par défaut
define('NP_PAYMENT_TTL', 3600);
