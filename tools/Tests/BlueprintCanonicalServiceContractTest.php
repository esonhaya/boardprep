<?php
require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php'; App\Core\Autoloader::register();
if(!method_exists(App\Services\Blueprint\BlueprintService::class,'create')){throw new RuntimeException('canonical blueprint create missing');}
$source=file_get_contents(dirname(__DIR__,2).'/app/Services/Blueprint/BlueprintService.php');
if(!str_contains($source,'BlueprintCreationService::create')){throw new RuntimeException('canonical service does not delegate creation pipeline');}
echo "[PASS] Canonical BlueprintService production contract verified.\n";
