<?php

declare(strict_types=1);

namespace Tools\Tests;

use App\Core\Autoloader;
use App\Contracts\StorageInterface;

final class FunctionTest
{
    private int $passed = 0;
    private int $failed = 0;
    private int $assertions = 0;

    /**
     * @var array<int, array{name: string, class: class-string<TestCase>}>
     */
    private array $tests = [
        [
            'name' => 'MemoryStorage',
            'class' => MemoryStorageTest::class,
        ],
        [
            'name' => 'Repository',
            'class' => RepositoryTest::class,
        ],
    ];

    public function run(): int
    {
        Autoloader::register();

        $this->header();

        $this->test(
            'Application autoloader',
            function (): void {
                $this->assertTrue(
                    class_exists(Autoloader::class),
                    'Autoloader could not be loaded.'
                );
            }
        );

        $this->test(
            'StorageInterface',
            function (): void {
                $this->assertTrue(
                    interface_exists(StorageInterface::class),
                    'StorageInterface could not be loaded.'
                );
            }
        );

        foreach ($this->tests as $definition) {
            $this->runTestCase(
                $definition['name'],
                $definition['class']
            );
        }

        $this->summary();

        return $this->failed === 0 ? 0 : 1;
    }

    private function runTestCase(
        string $name,
        string $class
    ): void {
        $this->test(
            $name,
            function () use ($class): void {
                /** @var TestCase $test */
                $test = new $class();

                $test->run();

                $this->assertions +=
                    $test->assertions();
            }
        );
    }

    private function test(
        string $name,
        callable $callback
    ): void {
        echo "\n[TEST] {$name}\n";

        try {
            $callback();

            echo "[PASS] OK\n";

            $this->passed++;
        } catch (\Throwable $exception) {
            echo "[FAIL] "
                . get_class($exception)
                . ': '
                . $exception->getMessage()
                . "\n";

            echo "       "
                . $exception->getFile()
                . ':'
                . $exception->getLine()
                . "\n";

            $this->failed++;
        }
    }

    private function assertTrue(
        bool $condition,
        string $message
    ): void {
        if (!$condition) {
            throw new \RuntimeException($message);
        }
    }

    private function header(): void
    {
        echo "======================================\n";
        echo " BoardPrep Function Test\n";
        echo "======================================\n";
        echo "Mode: In-process simulation\n";
        echo "Database: NOT USED\n";
        echo "HTTP server: NOT USED\n";
    }

    private function summary(): void
    {
        echo "\n======================================\n";
        echo " SUMMARY\n";
        echo "======================================\n";
        echo "PASS       : {$this->passed}\n";
        echo "FAIL       : {$this->failed}\n";
        echo "ASSERTIONS : {$this->assertions}\n";

        if ($this->failed === 0) {
            echo "\n[PASS] Function simulation passed.\n";
            return;
        }

        echo "\n[FAIL] Function simulation failed.\n";
    }
}

final class MemoryStorageTest extends TestCase
{
    public function run(): void
    {
        $storage = new MemoryStorage();

        $board = $storage->create(
            'boards',
            [
                'id' => 'let',
                'name' => 'LET',
                'status' => 'active',
            ]
        );

        $this->assertSame(
            'let',
            $board['id']
        );

        $this->assertTrue(
            $storage->exists('boards', 'let')
        );

        $this->assertSame(
            'LET',
            $storage->find('boards', 'let')['name'] ?? null
        );

        $active = $storage->where(
            'boards',
            [
                'status' => 'active',
            ]
        );

        $this->assertSame(
            1,
            count($active)
        );

        $updated = $storage->update(
            'boards',
            'let',
            [
                'status' => 'archived',
            ]
        );

        $this->assertSame(
            'archived',
            $updated['status'] ?? null
        );

        $this->assertSame(
            0,
            count(
                $storage->where(
                    'boards',
                    [
                        'status' => 'active',
                    ]
                )
            )
        );

        $this->assertSame(
            1,
            count(
                $storage->where(
                    'boards',
                    [
                        'status' => 'archived',
                    ]
                )
            )
        );

        $this->assertTrue(
            $storage->delete('boards', 'let')
        );

        $this->assertFalse(
            $storage->exists('boards', 'let')
        );

        $this->assertThrows(
            \RuntimeException::class,
            function () use ($storage): void {
                $storage->create(
                    'boards',
                    [
                        'id' => '',
                        'name' => 'Invalid',
                    ]
                );
            }
        );
    }
}
