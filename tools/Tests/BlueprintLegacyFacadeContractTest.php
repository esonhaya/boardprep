<?php
require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php'; App\Core\Autoloader::register();
$source=file_get_contents(dirname(__DIR__,2).'/app/Services/BlueprintService.php');
if(!str_contains($source,'\\App\\Services\\Blueprint\\BlueprintService::create($data)')){throw new RuntimeException('legacy BlueprintService is not a compatibility facade');}
if(!method_exists(App\Services\BlueprintService::class,'create')){throw new RuntimeException('legacy blueprint API missing');}
echo "[PASS] Legacy BlueprintService compatibility facade verified.\n";
