<?php
require_once __DIR__ . '/../../public_html/admin/football-lab/lib/AutoContext.php';
$verdictResponse = static function (array $data): array {
    return ['status'=>'completed','output'=>[['type'=>'message','content'=>[['type'=>'output_text','text'=>json_encode($data),'annotations'=>[['type'=>'url_citation','url'=>'https://example.com/club','title'=>'Club']]]]]]];
};
$verdict = ['decision'=>'retained','reason'=>'Effectifs confirmés, contexte compatible.','report'=>'Rapport sourcé.','official_lineups'=>true,'critical_unknowns'=>[]];
check(Context::parse($verdictResponse($verdict),'fixture')['decision'] === 'retained', 'Structured GPT verdict accepted with confirmed lineups');
$verdict['official_lineups'] = false;
check(Context::parse($verdictResponse($verdict),'fixture')['decision'] === 'pending', 'Unconfirmed lineups cannot produce retained verdict');
$verdict['official_lineups'] = true; $verdict['critical_unknowns'] = ['Gardien incertain'];
check(Context::parse($verdictResponse($verdict),'fixture')['decision'] === 'pending', 'Critical unknown prevents retained verdict');
$verdict['decision'] = 'excluded';
check(Context::parse($verdictResponse($verdict),'fixture')['decision'] === 'excluded', 'Sourced exclusion preserved');
$future = $scoreMatch; $future['kickoff'] = gmdate('c', time()+3600);
check(\StratEdgeLab\AutoContext::due($future,null,time()), 'Selected upcoming match queued automatically');
$previous = ['checked_at'=>gmdate('c'),'prompt_version'=>2,'status'=>'review_required'];
check(!\StratEdgeLab\AutoContext::due($future,$previous,time()), 'Recent report is not repeatedly billed');
$previous['checked_at'] = gmdate('c',time()-1900);
check(\StratEdgeLab\AutoContext::due($future,$previous,time()), 'Report refreshed near official lineups');
$future['pick'] = null;
check(!\StratEdgeLab\AutoContext::due($future,null,time()), 'Rejected statistical matches never queued');
$future = $scoreMatch;
check(!\StratEdgeLab\AutoContext::due($future,null,time()), 'Past matches never researched retrospectively');
