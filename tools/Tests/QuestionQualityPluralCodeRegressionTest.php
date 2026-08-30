<?php
require_once dirname(__DIR__,2).'/app/Core/Autoloader.php'; App\Core\Autoloader::register();
use App\Services\Quality\Validators\ChoiceValidator;
use App\Services\Question\Quality\QuestionQualityIssueGrouper;
use App\Services\RepositoryHealth\Engine\QuestionIssueMapper;
$q=['id'=>408,'question'=>'Which answer is correct?','answer'=>'A','choices'=>['A','A']];
$r=QuestionIssueMapper::map($q,ChoiceValidator::validate($q));
$g=QuestionQualityIssueGrouper::group($r->issues);
if(count($g['missingChoices'])!==1||count($g['duplicateChoices'])!==1){throw new RuntimeException('production validator codes are not grouped');}
echo "[PASS] Production plural choice-code regression verified.\n";
