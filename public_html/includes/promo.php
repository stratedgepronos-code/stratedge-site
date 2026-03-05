<?php
/**
 * StratEdge — Codes promo et réduction anniversaire
 * - Codes promo : configurés en admin (%, €), applicables aux offres choisies
 * - Anniversaire : 50% sur tennis/daily/weekly/weekend, 25% sur vip_max, 1×/an/membre
 */
if (!defined('DB_HOST')) require_once __DIR__ . '/db.php';

/** Offres éligibles anniversaire 50% */
const PROMO_ANNIV_50 = ['tennis', 'daily', 'weekly', 'weekend'];
/** Offres éligibles anniversaire 25% */
const PROMO_ANNIV_25 = ['vip_max'];

/**
 * Vérifie si c'est l'anniversaire du membre (mois-jour) et qu'il n'a pas déjà utilisé la réduction cette année.
 */
function isAnniversaireEligible(int $membre_id): bool {
    $db = getDB();
    $stmt = $db->prepare("SELECT date_naissance FROM membres WHERE id = ? LIMIT 1");
    $stmt->execute([$membre_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row || empty($row['date_naissance'])) return false;
    $dn = $row['date_naissance'];
    $today = date('m-d');
    $birth = date('m-d', strtotime($dn));
    if ($today !== $birth) return false;
    $annee = (int) date('Y');
    try {
        $stmt = $db->prepare("SELECT 1 FROM promo_anniversaire_use WHERE membre_id = ? AND annee = ? LIMIT 1");
        $stmt->execute([$membre_id, $annee]);
        if ($stmt->fetch()) return false;
        // Pas éligible non plus si le membre a déjà utilisé son code ANNIV cette année
        $stmt = $db->prepare("SELECT 1 FROM code_promo_utilisations u JOIN codes_promo c ON c.id = u.code_promo_id WHERE u.membre_id = ? AND c.code LIKE 'ANNIV-%' AND YEAR(u.date_utilisation) = ? LIMIT 1");
        $stmt->execute([$membre_id, $annee]);
        return !$stmt->fetch();
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * Retourne le pourcentage de réduction anniversaire pour une offre (50 ou 25).
 */
function getAnniversairePercent(string $offre): int {
    if (in_array($offre, PROMO_ANNIV_50, true)) return 50;
    if (in_array($offre, PROMO_ANNIV_25, true)) return 25;
    return 0;
}

/**
 * Crée le code promo anniversaire pour un membre (1× par an).
 * Code = ANNIV-{membre_id}-{année}, 50% tennis/daily/weekly/weekend, 25% vip_max (géré dans appliquerCodePromo).
 * @return string|null Le code créé ou null si déjà existant / erreur
 */
function creerCodeAnniversaireMembre(int $membre_id): ?string {
    $annee = (int) date('Y');
    $code = 'ANNIV-' . $membre_id . '-' . $annee;
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT id FROM codes_promo WHERE code = ? LIMIT 1");
        $stmt->execute([$code]);
        if ($stmt->fetch()) return null; // déjà créé cette année
        $offres = 'tennis,daily,weekly,weekend,vip_max';
        $db->prepare("INSERT INTO codes_promo (code, type, value, offres, max_utilisations, date_expir, actif) VALUES (?, 'percent', 50, ?, 1, NULL, 1)")
           ->execute([$code, $offres]);
        return $code;
    } catch (Throwable $e) {
        error_log('[promo] creerCodeAnniversaireMembre: ' . $e->getMessage());
        return null;
    }
}

/**
 * Enregistre l'utilisation de la réduction anniversaire (1× par membre par an).
 */
function useAnniversairePromo(int $membre_id, string $offre): bool {
    $annee = (int) date('Y');
    try {
        $db = getDB();
        $db->prepare("INSERT INTO promo_anniversaire_use (membre_id, annee, offre) VALUES (?, ?, ?)")
           ->execute([$membre_id, $annee, $offre]);
        return true;
    } catch (Throwable $e) {
        error_log('[promo] useAnniversairePromo: ' . $e->getMessage());
        return false;
    }
}

/**
 * Valide un code promo et retourne le montant final (€) ou false.
 * Vérifie : actif, date_expir, max_utilisations, offres, et si le membre ne l'a pas déjà utilisé (pour codes 1 utilisation).
 * @return array{success:bool, montant_final:float, label:string}|array{success:false, error:string}
 */
function appliquerCodePromo(string $code, string $offre, float $prix_initial, int $membre_id): array {
    $code = trim(strtoupper($code));
    if ($code === '') return ['success' => false, 'error' => 'Code vide'];
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT id, code, type, value, offres, max_utilisations, utilisations, date_expir, actif FROM codes_promo WHERE code = ? LIMIT 1");
        $stmt->execute([$code]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return ['success' => false, 'error' => 'Code invalide'];
        if (empty($row['actif'])) return ['success' => false, 'error' => 'Code expiré'];
        if (!empty($row['date_expir']) && $row['date_expir'] < date('Y-m-d')) return ['success' => false, 'error' => 'Code expiré'];
        if ($row['max_utilisations'] > 0 && (int)$row['utilisations'] >= (int)$row['max_utilisations']) return ['success' => false, 'error' => 'Code épuisé'];
        $stmt2 = $db->prepare("SELECT 1 FROM code_promo_utilisations WHERE code_promo_id = ? AND membre_id = ? LIMIT 1");
        $stmt2->execute([$row['id'], $membre_id]);
        if ($stmt2->fetch()) return ['success' => false, 'error' => 'Ce code a déjà été utilisé avec votre compte'];
        $offres = array_map('trim', explode(',', $row['offres']));
        if (!in_array($offre, $offres, true)) return ['success' => false, 'error' => 'Code non valable pour cette formule'];
        // Vérifier si ce membre a déjà utilisé ce code (pour usage unique par membre : on peut ajouter une table ou considérer max_utilisations=1 = 1 fois global)
        $reduction = 0.0;
        $pct = (float) $row['value'];
        // Code anniversaire : 50% partout sauf VIP Max = 25%
        if ($row['type'] === 'percent' && preg_match('/^ANNIV-\d+-\d{4}$/', $row['code'])) {
            $pct = ($offre === 'vip_max') ? 25.0 : 50.0;
        }
        if ($row['type'] === 'percent') {
            $reduction = $prix_initial * ($pct / 100);
        } else {
            $reduction = (float) $row['value'];
        }
        $montant_final = max(0, $prix_initial - $reduction);
        $label = $row['type'] === 'percent'
            ? '-' . (int)$row['value'] . '%'
            : '-' . number_format((float)$row['value'], 2, ',', '') . ' €';
        return [
            'success'       => true,
            'montant_final' => round($montant_final, 2),
            'label'         => $label,
            'code_promo_id' => (int) $row['id'],
        ];
    } catch (Throwable $e) {
        error_log('[promo] appliquerCodePromo: ' . $e->getMessage());
        return ['success' => false, 'error' => 'Erreur code promo'];
    }
}

/**
 * Enregistre l'utilisation d'un code promo et incrémente le compteur.
 */
function useCodePromo(int $code_promo_id, int $membre_id, string $offre, float $montant_avant, float $montant_apres): void {
    try {
        $db = getDB();
        $db->prepare("INSERT INTO code_promo_utilisations (code_promo_id, membre_id, offre, montant_avant, montant_apres) VALUES (?, ?, ?, ?, ?)")
           ->execute([$code_promo_id, $membre_id, $offre, $montant_avant, $montant_apres]);
        $db->prepare("UPDATE codes_promo SET utilisations = utilisations + 1 WHERE id = ?")->execute([$code_promo_id]);
    } catch (Throwable $e) {
        error_log('[promo] useCodePromo: ' . $e->getMessage());
    }
}

/**
 * Calcule le prix final : priorité code promo saisi, sinon anniversaire si éligible.
 * Retourne ['montant' => float, 'label' => string|null, 'anniversaire' => bool, 'code_promo_id' => int|null]
 */
function calculerPrixAvecPromo(float $prix_initial, string $offre, int $membre_id, string $code_saisi = ''): array {
    $result = [
        'montant'        => $prix_initial,
        'label'          => null,
        'anniversaire'   => false,
        'code_promo_id'  => null,
    ];
    if ($code_saisi !== '') {
        $app = appliquerCodePromo($code_saisi, $offre, $prix_initial, $membre_id);
        if ($app['success']) {
            $result['montant'] = $app['montant_final'];
            $result['label'] = 'Code ' . $app['label'];
            $result['code_promo_id'] = $app['code_promo_id'];
            return $result;
        }
    }
    if (isAnniversaireEligible($membre_id)) {
        $pct = getAnniversairePercent($offre);
        if ($pct > 0) {
            $result['montant'] = round(max(0, $prix_initial * (1 - $pct / 100)), 2);
            $result['label'] = 'Anniversaire -' . $pct . '%';
            $result['anniversaire'] = true;
        }
    }
    return $result;
}
