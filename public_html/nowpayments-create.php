<?php
// ============================================================
// STRATEDGE — Création d'un paiement NOWPayments
// Appelé en AJAX depuis offre-template.php
// Retourne un JSON avec l'adresse de paiement unique
// ============================================================

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/nowpayments-config.php';
require_once __DIR__ . '/includes/promo.php';

// ── Sécurité : uniquement POST + connecté ──────────────────
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Méthode non autorisée']);
    exit;
}

requireLogin();
$membre = getMembre();
if (!$membre) {
    http_response_code(401);
    echo json_encode(['error' => 'Non connecté']);
    exit;
}

// ── Lire et valider les paramètres ────────────────────────
$coin  = trim($_POST['crypto']  ?? '');
$type  = trim($_POST['offre']   ?? '');

$validCoins  = array_keys(NP_CRYPTO_MAP);
$validTypes  = ['daily', 'weekend', 'weekly', 'tennis', 'vip_max'];
$montants    = ['daily' => 4.50, 'weekend' => 10.00, 'weekly' => 20.00, 'tennis' => 15.00, 'vip_max' => 50.00];

if (!in_array($coin, $validCoins) || !in_array($type, $validTypes)) {
    http_response_code(400);
    echo json_encode(['error' => 'Paramètres invalides']);
    exit;
}

$prix_base    = $montants[$type];
$optionFun    = (in_array($type, ['weekend', 'weekly']) && !empty($_POST['option_fun']) && $_POST['option_fun'] === '1');
if ($optionFun) {
    $prix_base += 10.00;
}
$code_saisi   = trim($_POST['code_promo'] ?? '');
$promo        = calculerPrixAvecPromo($prix_base, $type, (int)$membre['id'], $code_saisi);
$montant_eur  = $promo['montant'];

if ($montant_eur <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Montant invalide après réduction.']);
    exit;
}

// Consommer le code promo ou l'anniversaire dès la génération de l'adresse (1 utilisation)
if ($promo['code_promo_id']) {
    useCodePromo($promo['code_promo_id'], (int)$membre['id'], $type, $prix_base, $montant_eur);
} elseif ($promo['anniversaire']) {
    useAnniversairePromo((int)$membre['id'], $type);
}
$np_currency   = NP_CRYPTO_MAP[$coin];

// ── order_id unique : permet de retrouver membre + offre dans l'IPN ──
// Format : SE_{membre_id}_{type}_{timestamp} (weekend_fun / weekly_fun = pack +10€ Fun)
$orderType = $optionFun ? $type . '_fun' : $type;
$order_id  = 'SE_' . $membre['id'] . '_' . $orderType . '_' . time();

// ── Appel API NOWPayments ──────────────────────────────────
$payload = [
    'price_amount'    => $montant_eur,
    'price_currency'  => NP_CURRENCY_FROM,
    'pay_currency'    => $np_currency,
    'order_id'        => $order_id,
    'order_description' => 'StratEdge ' . ucfirst($type) . ' — membre #' . $membre['id'],
    'success_url'     => NP_SUCCESS_URL,
    'cancel_url'      => NP_CANCEL_URL . '#crypto',
    'is_fixed_rate'   => false,       // taux flottant — fixed_rate cause "amountTo too small" sur petits montants
    'is_fee_paid_by_user' => false,   // les frais NOWPayments sont pris sur nous (0.5%)
];

$ch = curl_init(NP_API_BASE . '/payment');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($payload),
    CURLOPT_HTTPHEADER     => [
        'x-api-key: ' . NP_API_KEY,
        'Content-Type: application/json',
    ],
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_SSL_VERIFYPEER => true,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

// ── Gestion des erreurs ────────────────────────────────────
if ($curlError) {
    error_log('[NOWPayments] cURL error: ' . $curlError);
    http_response_code(503);
    echo json_encode(['error' => 'Erreur de connexion au service de paiement. Réessaie dans quelques secondes.']);
    exit;
}

$data = json_decode($response, true);

if ($httpCode !== 201 || empty($data['payment_id'])) {
    $msg = $data['message'] ?? 'Erreur inconnue';
    error_log('[NOWPayments] API error ' . $httpCode . ': ' . $response);
    http_response_code(502);
    // Message lisible selon l'erreur
    if (stripos($msg, 'too small') !== false) {
        $friendlyMsg = 'Montant trop faible pour cette crypto. Essaie BTC, ETH ou USDC qui acceptent les petits montants.';
    } elseif (stripos($msg, 'currency') !== false) {
        $friendlyMsg = 'Cette crypto n\'est pas disponible pour le moment. Essaies-en une autre.';
    } else {
        $friendlyMsg = 'Service momentanément indisponible. Réessaie dans quelques secondes.';
    }
    echo json_encode(['error' => $friendlyMsg]);
    exit;
}

// ── Stocker le paiement en base pour suivi ─────────────────
try {
    $db   = getDB();
    $stmt = $db->prepare("
        INSERT INTO crypto_payments
            (membre_id, offre, crypto, payment_id, order_id, montant_eur, statut, date_demande)
        VALUES (?, ?, ?, ?, ?, ?, 'waiting', NOW())
        ON DUPLICATE KEY UPDATE payment_id = VALUES(payment_id)
    ");
    $stmt->execute([
        $membre['id'],
        $orderType,
        $coin,
        $data['payment_id'],
        $order_id,
        $montant_eur,
    ]);
} catch (Exception $e) {
    // Non-bloquant : on log mais on continue (le IPN activera quand même)
    error_log('[NOWPayments] DB insert error: ' . $e->getMessage());
}

// ── Retourner les infos au client ──────────────────────────
$out = [
    'payment_id'   => $data['payment_id'],
    'pay_address'  => $data['pay_address'],
    'pay_amount'   => $data['pay_amount'],
    'pay_currency' => strtoupper($coin),
    'expires_at'   => time() + NP_PAYMENT_TTL,
    'network'      => getNetworkLabel($coin),
];
if ($promo['label']) {
    $out['promo_label'] = $promo['label'];
    $out['montant_eur'] = $montant_eur;
}
echo json_encode($out);

// ── Helper : libellé réseau ────────────────────────────────
function getNetworkLabel(string $coin): string {
    return [
        'btc'  => 'Réseau Bitcoin',
        'eth'  => 'Réseau Ethereum (ERC-20)',
        'usdc' => 'Réseau Polygon (MATIC)',
        'sol'  => 'Réseau Solana',
        'bnb'  => 'Réseau BNB Chain (BEP-20)',
    ][$coin] ?? $coin;
}
