<?php

declare(strict_types=1);

namespace Tools\Tests;

use App\Core\Autoloader;

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';

Autoloader::register();

final class QuizSelectionHardeningTest
{
    public static function run(): void
    {
        $request = new \SelectionRequest(
            subject: 'english',
            domain: 'grammar',
            difficultyDistribution: [],
            questionCount: 5
        );

        $result = new \SelectionResult(
            questions: [['id' => 1]],
            fulfilled: false,
            request: $request
        );

        assert($result->count() === 1);
        assert($result->shortage() === 4);
        assert($result->hasShortage() === true);
    }
}

QuizSelectionHardeningTest::run();
