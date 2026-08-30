<?php
$path=dirname(__DIR__,2).'/app/Services/Question/QuestionQualityService.php';$s=file_get_contents($path);
foreach(['RepositoryHealthEngine::analyze()','QuestionQualityReportPresenter::present('] as $needle){if(strpos($s,$needle)===false){throw new RuntimeException('missing production delegation: '.$needle);}}
if(strpos($s,'switch (')!==false||substr_count($s,'case ')>0){throw new RuntimeException('legacy inline issue switch remains');}
echo "[PASS] QuestionQualityService production delegation verified.\n";
