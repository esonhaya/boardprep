<?php
$files=[
 dirname(__DIR__,2).'/app/Services/Quiz/Recovery/RecoveryCandidateService.php',
 dirname(__DIR__,2).'/app/Services/Quiz/Recovery/RecoveryCandidateFilter.php',
 dirname(__DIR__,2).'/app/Services/Quiz/Recovery/RecoveryScopeMatcher.php',
 dirname(__DIR__,2).'/app/Services/Quiz/ShortageRecoveryService.php',
];
foreach($files as $file){$lines=count(file($file));if($lines>80){throw new RuntimeException(basename($file).' exceeds 80 lines: '.$lines);}}
echo "[PASS] Recovery maintainability boundary verified.\n";
