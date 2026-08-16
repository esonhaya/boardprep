<?php

declare(strict_types=1);

require_once __DIR__ . '/../Project/Shared/Support/StaticContractScanner.php';

use Tools\Doctor\Project\Shared\Support\StaticContractScanner;

class ScannerParentFixture
{
    protected static function inherited(
        string $value
    ): void {
    }
}

class ScannerChildFixture extends ScannerParentFixture
{
    public static function test(
        string $value,
        array $data = []
    ): void {
        self::inherited(
            $value
        );

        static::inherited(
            $value
        );

        ScannerChildFixture::render(
            'quiz/result',
            [
                'summary' => [
                    'score' => 5,
                    'total' => 10
                ]
            ]
        );
    }

    public static function render(
        string $view,
        array $data = []
    ): void {
    }
}

$source = <<<'CODE'
<?php

class Fixture
{
    public static function test(
        string $value,
        array $data = []
    ): void {
        self::hidden(
            $value,
            [
                'a' => [1, 2, 3],
                'b' => ['x', 'y']
            ]
        );

        Fixture::render(
            'quiz/result',
            [
                'summary' => [
                    'score' => 5,
                    'total' => 10
                ]
            ]
        );
    }

    private static function hidden(
        string $value,
        array $data = []
    ): void {
    }

    private static function render(
        string $view,
        array $data = []
    ): void {
    }
}
CODE;

$calls = StaticContractScanner::staticCalls(
    'fixture.php',
    $source
);

if (count($calls) !== 2) {
    throw new RuntimeException(
        'Expected 2 static calls; got ' . count($calls)
    );
}

if (
    $calls[0]['class'] !== 'self'
    || $calls[0]['method'] !== 'hidden'
    || $calls[0]['arguments'] !== 2
) {
    throw new RuntimeException(
        'Nested self:: parsing failed: '
        . json_encode($calls[0])
    );
}

if (
    $calls[1]['class'] !== 'Fixture'
    || $calls[1]['method'] !== 'render'
    || $calls[1]['arguments'] !== 2
) {
    throw new RuntimeException(
        'Nested array parsing failed: '
        . json_encode($calls[1])
    );
}


$trailingSource = <<<'CODE'
<?php

class TrailingFixture
{
    public static function test(): void
    {
        self::make(
            severity: 'WARNING',
            evidence: [
                'lines' => 10,
            ],
        );

        self::make(
            first: 1,
            second: [
                'nested' => true,
            ],
        );
    }

    private static function make(
        mixed $first = null,
        mixed $second = null,
        mixed $severity = null,
        mixed $evidence = null,
    ): void {
    }
}
CODE;

$trailingCalls =
    StaticContractScanner::staticCalls(
        'trailing.php',
        $trailingSource
    );

if (count($trailingCalls) !== 2) {
    throw new RuntimeException(
        'Expected 2 trailing-comma calls; got '
        . count($trailingCalls)
    );
}

if (
    $trailingCalls[0]['arguments'] !== 2
    || $trailingCalls[1]['arguments'] !== 2
) {
    throw new RuntimeException(
        'Trailing-comma argument counting failed: '
        . json_encode($trailingCalls)
    );
}

if (!method_exists(
    ScannerChildFixture::class,
    'inherited'
)) {
    throw new RuntimeException(
        'PHP inheritance baseline failed.'
    );
}

echo "[PASS] StaticContractScanner regression suite.\n";
