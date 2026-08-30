<?php
require_once dirname(__DIR__,2).'/app/Core/Autoloader.php'; App\Core\Autoloader::register();
use App\Services\Question\Statistics\QuestionStatisticsUpdater;
$r=QuestionStatisticsUpdater::apply(['timesUsed'=>'2','timesCorrect'=>'1','timesIncorrect'=>'1'],true,'now');
if($r['timesUsed']!==3||$r['timesCorrect']!==2||$r['timesIncorrect']!==1) throw new RuntimeException('correct update failed');
echo "[PASS] Correct answers update usage and correct counters once.\n";
