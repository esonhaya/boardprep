<?php
require_once dirname(__DIR__,2).'/app/Core/Autoloader.php'; App\Core\Autoloader::register();
use App\Services\Question\QuestionMetadataBuilderService;
$m=QuestionMetadataBuilderService::build(0,null,'2026-08-30T00:00:00+00:00');
if(trim((string)($m['id']??''))===''){throw new RuntimeException('new question metadata missing primary key');}
if(($m['createdAt']??null)!=='2026-08-30T00:00:00+00:00'||($m['updatedAt']??null)!=='2026-08-30T00:00:00+00:00'){throw new RuntimeException('new question timestamps inconsistent');}
echo "[PASS] New question metadata is valid before persistence.\n";
