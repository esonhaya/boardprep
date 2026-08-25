<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

assert(\AdaptiveTopicNormalizer::normalize(" Grammar ") === "grammar");
assert(\AdaptiveTopicNormalizer::normalize(null) === "");
echo "[PASS] Adaptive topic normalizer assertions verified.\n";
