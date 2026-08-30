<?php
require_once dirname(__DIR__,2).'/app/Core/Autoloader.php'; App\Core\Autoloader::register();
use App\Services\Question\Quality\QuestionQualityIssueCatalog;
$m=QuestionQualityIssueCatalog::legacyBuckets();
if(($m['missing-choices']??null)!=='missingChoices'||($m['duplicate-choices']??null)!=='duplicateChoices'){throw new RuntimeException('canonical choice issue codes missing');}
if(isset($m['missing-choice'])||isset($m['duplicate-choice'])){throw new RuntimeException('stale singular issue codes retained');}
echo "[PASS] Question quality issue catalog verified.\n";
