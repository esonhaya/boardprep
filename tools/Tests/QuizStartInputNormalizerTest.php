<?php
require_once dirname(__DIR__,2).'/app/Core/Autoloader.php'; App\Core\Autoloader::register();
use App\Services\Quiz\Start\QuizStartInputNormalizer;
$r=QuizStartInputNormalizer::normalize(['exam'=>'LET','subject'=>'English','topic'=>' Grammar ','count'=>'15','adaptive'=>'1']);
if($r['board']!=='LET'||$r['subject']!=='English'||$r['topics']!==['Grammar']||$r['count']!==15||$r['adaptive']!==true){throw new RuntimeException('normalize failed');}
echo "[PASS] Quiz start input normalization verified.\n";
