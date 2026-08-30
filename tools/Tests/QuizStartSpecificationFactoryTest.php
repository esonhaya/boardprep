<?php
require_once dirname(__DIR__,2).'/app/Core/Autoloader.php'; App\Core\Autoloader::register();
use App\Services\Quiz\Start\QuizStartSpecificationFactory;
$s=QuizStartSpecificationFactory::create(['exam'=>'LET','subject'=>'English','topic'=>'Grammar','difficulty'=>'easy','count'=>5,'mode'=>'practice']);
if($s->board!=='LET'||$s->subject!=='English'||$s->topics!==['Grammar']||$s->questionCount!==5||$s->difficulty!=='easy'){throw new RuntimeException('specification failed');}
echo "[PASS] Quiz start specification factory verified.\n";
