<?php
require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php'; App\Core\Autoloader::register();
$classes=[App\Services\Blueprint\Creation\BlueprintCreationInput::class,App\Services\Blueprint\Creation\BlueprintIdGenerator::class,App\Services\Blueprint\Creation\BlueprintVersionResolver::class,App\Services\Blueprint\Creation\BlueprintRecordFactory::class,App\Services\Blueprint\Creation\BlueprintCreationService::class];
foreach($classes as $class){if(!class_exists($class)){throw new RuntimeException('autoload failed: '.$class);}}
echo "[PASS] Blueprint creation autoloader contract verified.\n";
