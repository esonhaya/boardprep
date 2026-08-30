<?php
require_once dirname(__DIR__,2).'/app/Core/Autoloader.php'; App\Core\Autoloader::register();
use App\Services\Quiz\Start\QuizStartSpecificationFactory; use App\Services\Quiz\Start\QuizStartSessionWriter;
$_SESSION=[]; $s=QuizStartSpecificationFactory::create(['subject'=>'English','mode'=>'practice']); $q=[['id'=>1]]; QuizStartSessionWriter::write($s,$q);
if(($_SESSION['questions']??null)!==$q||($_SESSION['answers']??null)!==[]||(!array_key_exists('feedback', $_SESSION) || $_SESSION['feedback'] !== null)||($_SESSION['mode']??null)!=='practice'||empty($_SESSION['quiz_session'])){throw new RuntimeException('writer failed');}
echo "[PASS] Quiz start session writing verified.\n";
