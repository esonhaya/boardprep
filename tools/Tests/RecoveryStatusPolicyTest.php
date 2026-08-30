<?php
require_once dirname(__DIR__,2).'/app/Core/Autoloader.php'; App\Core\Autoloader::register();
if(!RecoveryStatusPolicy::allows('active')||!RecoveryStatusPolicy::allows('APPROVED')||RecoveryStatusPolicy::allows('draft')){throw new RuntimeException('status policy failed');}
echo "[PASS] Recovery status policy verified.\n";
