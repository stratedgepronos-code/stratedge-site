<?php
declare(strict_types=1);
require_once dirname(__DIR__,3).'/includes/auth.php';requireSuperAdmin();
$root=dirname(__DIR__,4).'/live90/';
$files=['prompt'=>['PROMPT_ANALYSTE_V4.md','text/markdown; charset=utf-8'],'collector'=>['live90-packball.user.js','text/javascript; charset=utf-8'],'filters'=>['FILTRES_LIVE_V4.md','text/markdown; charset=utf-8']];
$key=$_GET['file']??'';
if(!is_string($key)||!isset($files[$key])){http_response_code(404);exit;}
[$name,$type]=$files[$key];
if(!is_readable($root.$name)){http_response_code(503);exit('Fichier indisponible');}
header('Content-Type: '.$type);header('Content-Disposition: attachment; filename="'.$name.'"');header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');readfile($root.$name);
