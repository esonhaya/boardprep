<?php
require_once dirname(__DIR__,2).'/app/Core/Autoloader.php'; App\Core\Autoloader::register();
use App\Services\Question\Quality\QuestionQualityIssueCatalog;
$m=QuestionQualityIssueCatalog::legacyBuckets();
if(($m['missing-choices']??null)!=='missingChoices'||($m['duplicate-choices']??null)!=='duplicateChoices'){throw new RuntimeException('canonical choice issue codes missing');}
if(isset($m['missing-choice'])||isset($m['duplicate-choice'])){throw new RuntimeException('stale singular issue codes retained');}
if(($m['QUESTION_REUSE_REVIEW_HOLD']??null)!=='eligibilityReview'){throw new RuntimeException('review-required question eligibility holds are not grouped on the Developer quality dashboard');}
echo "[PASS] Question quality issue catalog verified.\n";
