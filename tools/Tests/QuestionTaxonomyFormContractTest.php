<?php
$path=dirname(__DIR__,2).'/app/Views/developer/question/partials/taxonomy.php';
$s=(string)file_get_contents($path);
foreach(['name="board"','name="subject"','name="domain"','name="topic"','name="concept"','type="hidden"'] as $needle){if(!str_contains($s,$needle)){throw new RuntimeException('taxonomy form contract missing '.$needle);}}
echo "[PASS] Question taxonomy form submits board and scoped context values.\n";
