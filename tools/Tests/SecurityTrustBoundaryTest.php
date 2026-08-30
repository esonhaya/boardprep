<?php

declare(strict_types=1);

use App\Core\Autoloader;
use App\Core\Router;
use App\Services\Quiz\Session\QuizSessionQuestion;
use App\Services\Quiz\Start\QuizStartInputNormalizer;

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
Autoloader::register();

$failures = [];
$check = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};

$normalized = QuizStartInputNormalizer::normalize([
    'count' => ['1000000'],
    'difficulty' => ['hard'],
    'mode' => ['exam'],
    'adaptive' => ['unexpected'],
]);
$check($normalized['count'] === 10, 'Array count must fall back to the bounded default.');
$check($normalized['difficulty'] === 'mixed', 'Array difficulty must fall back safely.');
$check($normalized['mode'] === 'practice', 'Array mode must fall back safely.');
$check($normalized['adaptive'] === true, 'Checkbox presence should normalize to boolean.');

$baseQuestion = [
    'id' => 'q-1',
    'question' => 'Safe question?',
    'choices' => ['Yes', 'No'],
    'answer' => 'A',
    'explanation' => 'Safe explanation.',
];
$check(QuizSessionQuestion::isRenderable($baseQuestion), 'Valid session question rejected.');
$check(!QuizSessionQuestion::isRenderable(array_replace($baseQuestion, ['choices' => ['a' => 'Yes', 'b' => 'No']])), 'Associative choices accepted.');
$check(!QuizSessionQuestion::isRenderable(array_replace($baseQuestion, ['explanation' => ['unsafe']])), 'Array explanation accepted.');
$check(!QuizSessionQuestion::isRenderable(array_replace($baseQuestion, ['answer' => 'Z'])), 'Out-of-range answer accepted.');

$router = new Router();
$router->post('/mutate', static fn (): null => null);
try {
    $router->dispatch('GET', '/mutate');
    $failures[] = 'Unsupported route method was dispatched.';
} catch (RuntimeException $exception) {
    $check($exception->getCode() === 405, 'Unsupported route method did not return 405.');
}

if ($failures !== []) {
    fwrite(STDERR, "[FAIL] " . implode("\n[FAIL] ", $failures) . "\n");
    exit(1);
}

echo "[PASS] Security trust-boundary regressions verified.\n";
