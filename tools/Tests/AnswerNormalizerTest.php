<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . "/app/Core/Autoloader.php";
\App\Core\Autoloader::register();

$question = ["choices" => ["A choice", "B choice", "C choice", "D choice"]];

assert(AnswerNormalizer::normalize($question, "a") === "A choice");
assert(AnswerNormalizer::normalize($question, " B ") === "B choice");
assert(AnswerNormalizer::normalize($question, "free text") === "free text");

echo "[PASS] AnswerNormalizer assertions verified.\n";
