<?php
declare(strict_types=1);
namespace Tools\Tests;

trait QuizTestBlueprintDistributionTrait
{
    private function testBlueprintDistributionBehavior(): void
    {
        echo "[TEST] Blueprint distribution behavior\n";
        if (!$this->assertDistributionDependency()) { return; }
        $this->assertDistributionPipeline(
            $this->distributionBoardBlueprint(),
            $this->distributionSubjectBlueprints()
        );
        echo "[PASS] OK\n";
    }

    private function assertDistributionDependency(): bool
    {
        $available = class_exists('BlueprintDistributionService');
        $this->assertTrue($available, 'Distribution: BlueprintDistributionService available');
        if ($available) { return true; }
        echo "[FAIL] Cannot continue distribution behavior test.\n";
        return false;
    }

    private function distributionBoardBlueprint(): array
    {
        return ['subjects'=>[
            ['subject'=>'English','percentage'=>50],
            ['subject'=>'Math','percentage'=>50],
        ]];
    }

    private function distributionSubjectBlueprints(): array
    {
        return [
            'English'=>[
                'domains'=>[
                    ['domain'=>'Grammar','percentage'=>60],
                    ['domain'=>'Reading','percentage'=>40],
                ],
                'difficulty'=>['easy'=>50,'medium'=>50],
            ],
            'Math'=>[
                'domains'=>[['domain'=>'Algebra','percentage'=>100]],
                'difficulty'=>['easy'=>50,'medium'=>50],
            ],
        ];
    }

    private function assertDistributionPipeline(array $boardBlueprint, array $subjectBlueprints): void
    {
        try {
            $requests = \BlueprintDistributionService::distribution($boardBlueprint, $subjectBlueprints, 10);
            $this->assertDistributionRequests($requests);
        } catch (\Throwable $exception) {
            $this->assertTrue(false, 'Distribution: executes without exception: ' . $exception->getMessage());
        }
    }

    private function assertDistributionRequests(array $requests): void
    {
        $this->assertTrue(is_array($requests), 'Distribution: returns an array');
        $this->assertTrue(count($requests) > 0, 'Distribution: creates selection requests');
        $total = 0;
        foreach ($requests as $request) {
            $this->assertTrue(is_object($request), 'Distribution: request is an object');
            $total += (int) ($request->questionCount ?? 0);
        }
        $this->assertSame(10, $total, 'Distribution: requested question count is preserved');
    }
}
