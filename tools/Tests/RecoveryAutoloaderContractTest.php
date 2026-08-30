<?php
require_once dirname(__DIR__,2).'/app/Core/Autoloader.php'; App\Core\Autoloader::register();
$classes=['RecoveryQuestionContext','RecoveryQuestionContextFactory','RecoveryStatusPolicy','RecoveryScopeMatcher','RecoveryScopePlan','RecoveryCandidateFilter','RecoveryCandidateService'];
foreach($classes as $class){if(!class_exists($class)){throw new RuntimeException('autoload failed: '.$class);}}
echo "[PASS] Recovery autoloader contract verified.\n";
