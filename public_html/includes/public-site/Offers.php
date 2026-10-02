<?php
declare(strict_types=1);
namespace StratEdgePublic;
final class Offers {
 public static function all(): array {
  $single=\stratedge_pack_get('unique');
  return [
   'multi'=>['eyebrow'=>'Football & autres sports','title'=>'Multisports','price'=>number_format($single['prix'],2,',',' '),'period'=>'à partir de · 1 crédit','description'=>'Pour consulter les analyses à votre rythme. Un crédit active 24 h d’accès Multisports à la première consultation. Packs de 1 à 10 crédits.','target'=>'/packs-daily.php'],
   'tennis'=>['eyebrow'=>'ATP / WTA','title'=>'Tennis','price'=>'15','period'=>'pour 7 jours d’accès','description'=>'L’accès aux analyses Tennis publiées pendant 7 jours. Un univers dédié au circuit et à ses conditions de jeu.','target'=>'/offre-tennis.php'],
   'fun'=>['eyebrow'=>'Le format à forte variance','title'=>'Fun','price'=>'10','period'=>'pour 7 jours d’accès','description'=>'L’accès aux sélections Fun pendant 7 jours. Des cotes plus élevées et un risque de perte plus important.','target'=>'/offre-fun.php'],
   'vip'=>['eyebrow'=>'Tous les univers','title'=>'VIP Max','price'=>'50','period'=>'pour 30 jours d’accès','description'=>'Les univers Multisports, Tennis et Fun réunis dans un accès de 30 jours. Pour suivre l’ensemble des publications réservées aux membres.','target'=>'/offre.php?type=vip_max']
  ];
 }
 public static function target($key): ?string { return is_string($key)?(self::all()[$key]['target']??null):null; }
 public static function safeRedirect($url): string {
  if(!is_string($url)||preg_match('/[\x00-\x20\\\\]/',$url)||strpos($url,'://')!==false||strpos($url,'//')===0) return '/dashboard.php';
  if(!preg_match('~^/?[a-zA-Z0-9/_-]+\.php(?:\?[^#]*)?$~D',$url)) return '/dashboard.php';
  return '/'.ltrim($url,'/');
 }
}
