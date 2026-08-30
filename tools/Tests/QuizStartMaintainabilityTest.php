<?php
$root=dirname(__DIR__,2); $path=$root.'/app/Services/Quiz/QuizStartService.php'; $lines=file($path, FILE_IGNORE_NEW_LINES); if(count($lines)>70){throw new RuntimeException('QuizStartService remains too large: '.count($lines));}
$source=implode("\n",$lines); if(substr_count($source,'public static function start')!==1){throw new RuntimeException('start API changed');}
echo "[PASS] QuizStartService maintainability boundary verified: ".count($lines)." lines.\n";
