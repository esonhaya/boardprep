<?php
$path=dirname(__DIR__,2).'/public/assets/js/taxonomy-selector.js';
$s=(string)file_get_contents($path);
foreach(['board_id','subject_id','domain_id','topic_id','taxonomy-board-subjects'] as $needle){if(!str_contains($s,$needle)){throw new RuntimeException('selector does not use canonical taxonomy key '.$needle);}}
foreach(['d.subject ===','t.domain ===','c.topic ==='] as $legacy){if(str_contains($s,$legacy)){throw new RuntimeException('legacy taxonomy selector key remains: '.$legacy);}}
echo "[PASS] Taxonomy selector uses canonical hierarchy keys.\n";
