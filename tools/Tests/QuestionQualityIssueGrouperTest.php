<?php
require_once dirname(__DIR__,2).'/app/Core/Autoloader.php'; App\Core\Autoloader::register();
use App\Services\Question\Quality\QuestionQualityIssueGrouper;
use App\Services\RepositoryHealth\DTO\HealthIssue;
function qIssue(string $code,string $severity='warning'): HealthIssue {$i=new HealthIssue();$i->code=$code;$i->severity=$severity;return $i;}
$g=QuestionQualityIssueGrouper::group([qIssue('missing-choices','error'),qIssue('duplicate-choices'),qIssue('future-code')]);
if(count($g['missingChoices'])!==1||count($g['duplicateChoices'])!==1){throw new RuntimeException('canonical grouping failed');}
if(count($g['unclassifiedIssues'])!==1||!isset($g['byCode']['future-code'])){throw new RuntimeException('unknown issue preservation failed');}
echo "[PASS] Question quality issue grouping verified.\n";
