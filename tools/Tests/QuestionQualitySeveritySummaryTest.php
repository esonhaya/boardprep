<?php
require_once dirname(__DIR__,2).'/app/Core/Autoloader.php'; App\Core\Autoloader::register();
use App\Services\Question\Quality\QuestionQualitySeveritySummary;
use App\Services\RepositoryHealth\DTO\HealthIssue;
$issues=[];foreach(['error','warning','warning','info'] as $s){$i=new HealthIssue();$i->severity=$s;$issues[]=$i;}
$r=QuestionQualitySeveritySummary::build($issues);
if($r!==['error'=>1,'warning'=>2,'info'=>1]){throw new RuntimeException('severity summary failed');}
echo "[PASS] Question quality severity summary verified.\n";
