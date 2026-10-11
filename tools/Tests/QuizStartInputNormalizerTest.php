<?php
require_once dirname(__DIR__,2).'/app/Core/Autoloader.php'; App\Core\Autoloader::register();
use App\Services\Quiz\Start\QuizStartInputNormalizer;
$r=QuizStartInputNormalizer::normalize(['exam'=>'LET','subject'=>'English','topic'=>' Grammar ','count'=>'15','adaptive'=>'1']);
if($r['board']!=='LET'||$r['subject']!=='English'||$r['topics']!==['Grammar']||$r['count']!==15||$r['adaptive']!==true){throw new RuntimeException('normalize failed');}
$letDefault = QuizStartInputNormalizer::normalize(['exam' => 'LET']);
if ($letDefault['subject'] !== 'english') { throw new RuntimeException('LET default does not match its effective subject selector.'); }
$otherBoardDefault = QuizStartInputNormalizer::normalize(['exam' => 'CPA']);
if ($otherBoardDefault['subject'] !== '*') { throw new RuntimeException('Non-LET/CSE board default does not include its eligible subject pool.'); }
$cse=QuizStartInputNormalizer::normalize(['exam'=>'CSE','civil_service_level'=>'subprofessional']);
if($cse['civil_service_level']!=='SUBPROFESSIONAL'||$cse['board']!=='CSE'){throw new RuntimeException('Civil Service level did not normalize.');}
if(QuizStartInputNormalizer::normalize(['exam'=>'CSE','civil_service_level'=>'PROFESSIONAL','subject'=>'verbal-ability'])['subject']!=='verbal-ability'){throw new RuntimeException('CSE subject placement did not normalize.');}
try{QuizStartInputNormalizer::normalize(['exam'=>'CSE','civil_service_level'=>'PROFESSIONAL','subject'=>'clerical-ability']);throw new RuntimeException('Professional Clerical Ability was accepted.');}catch(InvalidArgumentException){}
try{QuizStartInputNormalizer::normalize(['exam'=>'CSE','civil_service_level'=>'ASSOCIATE']);throw new RuntimeException('Invalid Civil Service level was accepted.');}catch(InvalidArgumentException){}
echo "[PASS] Quiz start input normalization verified.\n";
