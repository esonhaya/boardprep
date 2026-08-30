<?php
require_once dirname(__DIR__,2).'/app/Core/Autoloader.php'; App\Core\Autoloader::register();
use App\Services\Question\Statistics\QuestionStatisticsUpdater;
$r=QuestionStatisticsUpdater::apply(['timesUsed'=>2,'timesCorrect'=>1,'timesIncorrect'=>1],null,'now');
if($r['timesUsed']!==3||$r['timesCorrect']!==1||$r['timesIncorrect']!==1) throw new RuntimeException('unanswered update failed');
echo "[PASS] Unanswered questions increment usage without false incorrect counts.\n";
