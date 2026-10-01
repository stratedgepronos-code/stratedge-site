<?php
/**
 * STRATEDGE — Configuration centrale des packs de crédits paris
 * 3 univers: multi (multisports), tennis, fun
 * Chaque pack a un 'sport' qui détermine le compte Stripe à utiliser
 */

if (!function_exists('stratedge_packs_all')) {

function stratedge_packs_all(): array {
    return [
        // ═══ MULTISPORTS (foot, NBA, NHL, MLB) ═══
        'unique'  => ['key'=>'unique','sport'=>'multi','label'=>'Unique','nb'=>1,'prix'=>4.50,'prix_unit'=>4.50,'economie'=>0,'badge'=>'','methodes'=>['sms','stripe','crypto'],'sub'=>'Essai'],
        'duo'     => ['key'=>'duo','sport'=>'multi','label'=>'Duo','nb'=>2,'prix'=>8.50,'prix_unit'=>4.25,'economie'=>5,'badge'=>'','methodes'=>['stripe','crypto'],'sub'=>''],
        'trio'    => ['key'=>'trio','sport'=>'multi','label'=>'Trio','nb'=>3,'prix'=>12.00,'prix_unit'=>4.00,'economie'=>11,'badge'=>'🔥 Populaire','methodes'=>['stripe','crypto'],'sub'=>'Le plus choisi'],
        'quinte'  => ['key'=>'quinte','sport'=>'multi','label'=>'Quinté','nb'=>5,'prix'=>18.00,'prix_unit'=>3.60,'economie'=>20,'badge'=>'💎 Meilleur rapport','methodes'=>['stripe','crypto'],'sub'=>'Recommandé'],
        'semaine' => ['key'=>'semaine','sport'=>'multi','label'=>'Semaine','nb'=>7,'prix'=>23.00,'prix_unit'=>3.29,'economie'=>27,'badge'=>'','methodes'=>['stripe','crypto'],'sub'=>'1 pari / jour'],
        'pack10'  => ['key'=>'pack10','sport'=>'multi','label'=>'Pack 10','nb'=>10,'prix'=>30.00,'prix_unit'=>3.00,'economie'=>33,'badge'=>'🏆 Économie max','methodes'=>['stripe','crypto'],'sub'=>'Pour les pros'],

        // Tennis et Fun ne sont plus des packs crédits, mais des abos:
        // -> tennis = abo Semaine 15€ (offre-tennis.php)
        // -> fun = abo Week-End 10€ (offre-fun.php)
    ];
}

// Packs filtrés par sport (pour les pages dédiées)
function stratedge_packs_by_sport(string $sport): array {
    return array_filter(stratedge_packs_all(), fn($p) => $p['sport'] === $sport);
}

// Backward-compat: ancien nom de fonction qui retournait juste les packs multi
function stratedge_packs_config(): array {
    return stratedge_packs_by_sport('multi');
}

function stratedge_pack_get(string $key): ?array {
    return stratedge_packs_all()[$key] ?? null;
}

function stratedge_pack_valid(string $key): bool {
    return isset(stratedge_packs_all()[$key]);
}

// Détermine quel compte Stripe utiliser selon le sport du pack
function stratedge_pack_stripe_account(string $packKey): string {
    $pack = stratedge_pack_get($packKey);
    return $pack['sport'] ?? 'multi';
}

}
