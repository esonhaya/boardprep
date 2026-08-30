<?php
require_once dirname(__DIR__,2).'/app/Core/Autoloader.php'; App\Core\Autoloader::register();
use App\Services\Question\Quality\QuestionQualityReportPresenter;
use App\Services\RepositoryHealth\DTO\HealthIssue;
use App\Services\RepositoryHealth\DTO\HealthReport;
$r=new HealthReport();$r->healthScore=88;$i=new HealthIssue();$i->code='missing-explanation';$i->severity='warning';$r->issues=[$i];
$p=QuestionQualityReportPresenter::present($r);
if($p['healthScore']!==88.0||count($p['missingExplanation'])!==1||count($p['issueGroups']['missingExplanation'])!==1){throw new RuntimeException('presenter contract failed');}
echo "[PASS] Question quality report presentation verified.\n";
