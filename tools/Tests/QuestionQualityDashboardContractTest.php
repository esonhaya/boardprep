<?php
$path=dirname(__DIR__,2).'/app/Views/developer/question-quality.php';$s=file_get_contents($path);
foreach(['Priority Issue Summary','$issueGroupLabels','$issueGroups','$severitySummary','unclassifiedIssues'] as $needle){if(strpos($s,$needle)===false){throw new RuntimeException('dashboard contract missing '.$needle);}}
echo "[PASS] Question quality dashboard summary contract verified.\n";
