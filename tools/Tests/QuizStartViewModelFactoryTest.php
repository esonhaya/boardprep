<?php
require_once dirname(__DIR__,2).'/app/Core/Autoloader.php'; App\Core\Autoloader::register();
use App\Services\Quiz\Start\QuizStartSpecificationFactory; use App\Services\Quiz\Start\QuizStartViewModelFactory;
$s=QuizStartSpecificationFactory::create(['mode'=>'exam']); $q=[['id'=>1],['id'=>2]]; $v=QuizStartViewModelFactory::create($s,$q);
if($v['question']!==$q[0]||$v['current']!==0||$v['total']!==2||$v['mode']!=='exam'||$v['feedback']!==null){throw new RuntimeException('view model failed');}
echo "[PASS] Quiz start view model verified.\n";
