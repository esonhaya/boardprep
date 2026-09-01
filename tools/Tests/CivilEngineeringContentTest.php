<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();
require dirname(__DIR__, 2) . '/bootstrap/app.php';
$questions=\App\Core\App::storage()->all('questions');
$ce=array_values(array_filter($questions,static fn(array $q):bool => (($q['board']??'')==='civil-engineering')));
$counts=[];$blocks=0;$bad=0;$invalid=[];
foreach($ce as $q){$d=$q['taxonomy']['domain_id']??'';$counts[$d]=($counts[$d]??0)+1;$blocks+=count($q['content_blocks']??[]);if(!in_array($q['answer']??'',array_column($q['options']??[],'text'),true)&&!in_array($q['answer']??'',$q['choices']??[],true))$bad++;foreach(($q['content_blocks']??[]) as $b){$invalid=array_merge($invalid,\App\Services\Question\StructuredContentService::validate(['content_blocks'=>[$b]]));}}
$blueprints=\App\Core\App::storage()->all('blueprints');$bp=[];foreach($blueprints as $b)if(($b['id']??'')==='portfolio-civil-engineering-v1'){$bp=$b;break;}
if(count($ce)!==122||$counts!==['ce-structural'=>40,'ce-applied'=>41,'ce-hydraulics-geotech'=>41]||$bad!==0||$invalid!==[]||count($bp['sections']??[])!==3||($bp['official_weights']['ce-structural']??0)!==35)$invalid[]='CE content gate';
echo $invalid===[]?"[PASS] CE content, three-area blueprint, structured blocks, and answer integrity verified.\n":"[FAIL] ".implode('; ',$invalid)."\n";
if($invalid!==[])exit(1);
