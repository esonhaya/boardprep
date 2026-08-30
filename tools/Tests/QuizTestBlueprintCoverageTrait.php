<?php
declare(strict_types=1);
namespace Tools\Tests;

trait QuizTestBlueprintCoverageTrait
{
    private function testBlueprintCoverageBehavior(): void
    {
        echo "[TEST] Blueprint coverage behavior\n";
        if (!$this->assertCoverageDependencies()) { return; }
        $this->assertCoveragePipeline($this->coverageQuestions(), $this->coverageRequest());
        echo "[PASS] OK\n";
    }

    private function assertCoverageDependencies(): bool
    {
        $analyzerAvailable = class_exists('BlueprintCoverageAnalyzer');
        $validatorAvailable = class_exists('BlueprintCoverageValidator');
        $this->assertTrue($analyzerAvailable, 'Coverage: BlueprintCoverageAnalyzer available');
        $this->assertTrue($validatorAvailable, 'Coverage: BlueprintCoverageValidator available');
        if ($analyzerAvailable && $validatorAvailable) { return true; }
        echo "[FAIL] Cannot continue coverage behavior test.\n";
        return false;
    }

    private function coverageQuestions(): array
    {
        return [
            ['id'=>601,'subject'=>'English','domain'=>'Grammar'],
            ['id'=>602,'subject'=>'English','domain'=>'Grammar'],
        ];
    }

    private function coverageRequest(): \SelectionRequest
    {
        return new \SelectionRequest(
            subject:'English',
            domain:'Grammar',
            difficultyDistribution:['easy'=>50,'medium'=>50],
            questionCount:2
        );
    }

    private function assertCoveragePipeline(array $questions, \SelectionRequest $request): void
    {
        try {
            $coverage = \BlueprintCoverageAnalyzer::analyze($questions, [], [], [$request]);
            $this->assertCoverageSummary($coverage);
            $this->assertCoverageValidation($coverage);
        } catch (\Throwable $exception) {
            $this->assertTrue(false, 'Coverage: executes without exception: ' . $exception->getMessage());
        }
    }

    private function assertCoverageSummary(array $coverage): void
    {
        $this->assertSame(1, count($coverage), 'Coverage: produces one coverage row');
        $this->assertSame(2, $coverage[0]['required'] ?? null, 'Coverage: required count preserved');
        $this->assertSame(2, $coverage[0]['generated'] ?? null, 'Coverage: generated count detected');
    }

    private function assertCoverageValidation(array $coverage): void
    {
        $issues = \BlueprintCoverageValidator::validate($coverage);
        $this->assertSame(0, count($issues), 'Coverage: complete coverage has no issues');
        $shortage = $coverage;
        $shortage[0]['generated'] = 1;
        $issues = \BlueprintCoverageValidator::validate($shortage);
        $this->assertSame(1, count($issues), 'Coverage: shortage is detected');
    }
}
