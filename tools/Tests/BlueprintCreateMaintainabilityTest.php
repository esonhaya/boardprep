<?php
$path=dirname(__DIR__,2).'/app/Services/Blueprint/BlueprintService.php'; $source=file_get_contents($path);
if(substr_count($source,"\n")+1>45){throw new RuntimeException('canonical BlueprintService exceeded thin facade boundary');}
$creation=file_get_contents(dirname(__DIR__,2).'/app/Services/Blueprint/Creation/BlueprintCreationService.php');
if(substr_count($creation,"\n")+1>65){throw new RuntimeException('BlueprintCreationService exceeded orchestration boundary');}
echo "[PASS] Blueprint creation maintainability boundaries verified.\n";
