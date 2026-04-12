<?php
/**
 * STRATEDGE — Configuration centrale des packs de crédits paris
 */

if (!function_exists('stratedge_packs_config')) {

function stratedge_packs_config(): array {
    return [
        'unique'  => ['key'=>'unique','label'=>'Unique','nb'=>1,'prix'=>4.50,'prix_unit'=>4.50,'economie'=>0,'badge'=>'','methodes'=>['sms','stripe','crypto'],'sub'=>'Essai'],
        'duo'     => ['key'=>'duo','label'=>'Duo','nb'=>2,'prix'=>8.50,'prix_unit'=>4.25,'economie'=>5,'badge'=>'','methodes'=>['stripe','crypto'],'sub'=>''],
        'trio'    => ['key'=>'trio','label'=>'Trio','nb'=>3,'prix'=>12.00,'prix_unit'=>4.00,'economie'=>11,'badge'=>'🔥 Populaire','methodes'=>['stripe','crypto'],'sub'=>'Le plus choisi'],
        'quinte'  => ['key'=>'quinte','label'=>'Quinté','nb'=>5,'prix'=>18.00,'prix_unit'=>3.60,'economie'=>20,'badge'=>'💎 Meilleur rapport','methodes'=>['stripe','crypto'],'sub'=>'Recommandé'],
        'semaine' => ['key'=>'semaine','label'=>'Semaine','nb'=>7,'prix'=>23.00,'prix_unit'=>3.29,'economie'=>27,'badge'=>'','methodes'=>['stripe','crypto'],'sub'=>'1 pari / jour'],
        'pack10'  => ['key'=>'pack10','label'=>'Pack 10','nb'=>10,'prix'=>30.00,'prix_unit'=>3.00,'economie'=>33,'badge'=>'🏆 Économie max','methodes'=>['stripe','crypto'],'sub'=>'Pour les pros'],
    ];
}

function stratedge_pack_get(string $key): ?array {
    return stratedge_packs_config()[$key] ?? null;
}

function stratedge_pack_valid(string $key): bool {
    return isset(stratedge_packs_config()[$key]);
}

}
