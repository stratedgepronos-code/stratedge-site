<?php
declare(strict_types=1);
namespace StratEdgePublic;
final class Results
{
    public static function calculate(array $bets): array
    {
        $out=['wins'=>0,'losses'=>0,'voids'=>0,'missing'=>0,'complete'=>0,'net'=>0.0,'roi'=>null,'rate'=>null,'from'=>null,'to'=>null];
        foreach ($bets as $b) {
            $r=$b['resultat']??'';if (!in_array($r,['gagne','perdu','annule'],true)) { continue; }
            $out[$r==='gagne'?'wins':($r==='perdu'?'losses':'voids')]++;
            $date=$b['date_resultat']??$b['date_post']??null;if($date){$out['from']=$out['from']===null? $date:min($out['from'],$date);$out['to']=$out['to']===null?$date:max($out['to'],$date);}
            if ($r==='annule') { continue; }
            $odds=filter_var(str_replace(',','.',(string)($b['cote']??'')),FILTER_VALIDATE_FLOAT);
            if ($odds===false || $odds<=1 || !is_finite($odds)) { $out['missing']++; continue; }
            $out['complete']++;$out['net']+=$r==='gagne'?$odds-1:-1;
        }
        $resolved=$out['wins']+$out['losses'];$out['rate']=$resolved?round(100*$out['wins']/$resolved,1):null;
        // A partial, selectively priced sample cannot advertise a full-history return.
        if ($out['complete']>0 && $out['missing']===0) { $out['roi']=round(100*$out['net']/$out['complete'],1); }
        return $out;
    }
    public static function load(\PDO $db): array
    {
        // Older installations may not have the optional categorisation columns yet.
        // Keep the existing SELECT * compatibility, then discard every non-public field.
        $allowed = array_fill_keys(['id','titre','cote','resultat','date_post','date_resultat','categorie','posted_by_role'], null);
        $rows = $db->query("SELECT * FROM bets WHERE resultat IN ('gagne','perdu','annule') ORDER BY COALESCE(date_resultat,date_post) DESC,id DESC")->fetchAll(\PDO::FETCH_ASSOC);
        return array_map(static fn(array $row): array => array_replace($allowed, array_intersect_key($row, $allowed)), $rows);
    }
    public static function category(array $bet): string
    {
        if (($bet['posted_by_role']??'')==='admin_tennis' || ($bet['categorie']??'')==='tennis') { return 'tennis'; }
        return ($bet['posted_by_role']??'')==='admin_fun' ? 'fun' : 'multi';
    }
}
