<?php
declare(strict_types=1);
namespace StratEdgePublic;
final class LabDraft {
 public static function make(array $match,array $analysis): array {
  $name=($match['home']??'').' — '.($match['away']??'');$pick=$match['pick']??null;
  if(!$pick)throw new \InvalidArgumentException('Ce match ne contient pas de présélection statistique.');
  $kickoff=(new \DateTimeImmutable($match['kickoff']))->setTimezone(new \DateTimeZone('Europe/Paris'));
  $fs=$match['footystats']??[];$source=$fs['source']??[];
  $body="## La piste statistique\n\n".$name.' · '.($match['league']??'Compétition non renseignée').".\nMarché envisagé : ".($pick['label']??'À compléter').'. Cote exportée : '.($pick['odds']??'non fournie').'.';
  if(isset($pick['probability']))$body.=' Estimation du modèle : '.number_format((float)$pick['probability']*100,1,',',' ').' % (ce n’est pas une certitude).';
  $body.="\n\n## Ce que décrivent les données\n\n".'Analyse calculée le '.($analysis['generated_at']??'date non archivée').'. Version du moteur : '.($analysis['version']??'non archivée').'.';
  $body.="\nFootyStats : ".($fs['home']['n']??$fs['sample']['home_n']??'inconnu').' matchs à domicile pour le recevant ; '.($fs['away']['n']??$fs['sample']['away_n']??'inconnu').' à l’extérieur pour le visiteur.';
  $competition=is_scalar($source['competition']??null)?$source['competition']:($match['league']??'à vérifier');
  $period=is_scalar($source['period']??null)?$source['period']:'Saison de la compétition ; dates détaillées à vérifier';
  $body.="\nCompétition source : ".$competition.'. Période : '.$period.'. Les moyennes globales PackBall ne sont pas des bilans par lieu.';
  $body.="\n\n## Le contexte à vérifier\n\nBrouillon : compléter les compositions, les absences confirmées, le calendrier et les conditions de jeu. Citer les sources consultées et leur date. Si une information manque, le préciser.\n\n## Notre décision\n\nPrésélection descriptive uniquement. Aucun pari n’est validé dans ce brouillon. Ajouter les arguments, les limites et une conclusion après vérification.";
  return ['title'=>$name.' : la piste et ses limites','slug'=>'analyse-'.bin2hex(random_bytes(5)),'summary'=>'Une présélection statistique à confronter au contexte du match, aux informations disponibles et au prix réellement proposé.','author'=>'La rédaction StratEdge','body'=>$body,'sources'=>'','kind'=>'analyse','sport'=>'football','decision'=>'observation','match_label'=>$name,'market'=>(string)($pick['label']??''),'odds'=>isset($pick['odds'])?(string)$pick['odds']:'','odds_at'=>'','kickoff_at'=>$kickoff->format('Y-m-d\TH:i'),'result'=>'en_attente','score'=>''];
 }
}
