<?php
require_once dirname(__DIR__,2).'/app/Core/Autoloader.php'; App\Core\Autoloader::register();
use App\Services\Question\Statistics\QuestionStatisticsUpdater;
$r=QuestionStatisticsUpdater::apply(['timesUsed'=>2,'timesCorrect'=>1,'timesIncorrect'=>0],false,'now');
if($r['timesUsed']!==3||$r['timesCorrect']!==1||$r['timesIncorrect']!==1) throw new RuntimeException('incorrect update failed');
echo "[PASS] Incorrect answers update usage and incorrect counters once.\n";
