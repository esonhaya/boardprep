<?php
declare(strict_types=1);
namespace Tools\Tests;

trait QuizTestGenerationTrait
{
    private function testGenerationBehavior(): void
    {
        echo "[TEST] Quiz generation behavior\n";
        if (!$this->assertGenerationDependencies()) { return; }
        $specification = $this->generationSpecification();
        $this->assertGenerationPipeline(
            $this->generationQuestions(),
            $specification
        );
        echo "[PASS] OK\n";
    }

    private function assertGenerationDependencies(): bool
    {
        $serviceAvailable = class_exists('QuizGenerationService');
        $specificationAvailable = class_exists('QuizSpecification');
        $this->assertTrue($serviceAvailable, 'Generation: QuizGenerationService available');
        $this->assertTrue($specificationAvailable, 'Generation: QuizSpecification available');
        if ($serviceAvailable && $specificationAvailable) { return true; }
        echo "[FAIL] Cannot continue generation behavior test.\n";
        return false;
    }

    private function generationSpecification(): \QuizSpecification
    {
        return new \QuizSpecification(
            board: 'LET', subject: 'English', domain: null,
            topics: [], concepts: [], difficulty: 'mixed',
            questionCount: 2, mode: 'practice', adaptive: false,
            shuffle: true, boardBlueprintVersion: null,
            subjectBlueprintVersion: null
        );
    }

    private function generationQuestions(): array
    {
        return [
            $this->generationQuestion(501, 'A', 'easy'),
            $this->generationQuestion(502, 'B', 'medium'),
            $this->generationQuestion(503, 'C', 'hard'),
        ];
    }

    private function generationQuestion(int $id, string $answer, string $difficulty): array
    {
        return [
            'id' => $id,
            'question' => "Generation test question {$id}",
            'choices' => ['A','B','C','D'],
            'answer' => $answer,
            'subject' => 'English',
            'domain' => 'Grammar',
            'difficulty' => $difficulty,
            'status' => 'approved',
            'taxonomy' => ['subject_id'=>'English','domain_id'=>'Grammar'],
        ];
    }

    private function assertGenerationPipeline(array $questions, \QuizSpecification $specification): void
    {
        try {
            $result = \QuizGenerationService::generate($questions, $specification);
            $this->assertGenerationResult($result, $specification);
        } catch (\Throwable $exception) {
            $this->assertTrue(
                false,
                'Generation: pipeline executes without exception: ' . $exception->getMessage()
            );
        }
    }

    private function assertGenerationResult(mixed $result, \QuizSpecification $specification): void
    {
        $this->assertNotNull($result, 'Generation: result returned');
        $this->assertTrue(is_object($result), 'Generation: result is an object');
        $this->assertTrue(
            is_object($result) && property_exists($result, 'questions'),
            'Generation: result exposes questions'
        );
        $this->assertTrue(
            is_object($result) && is_array($result->questions ?? null),
            'Generation: questions are an array'
        );
        $this->assertTrue(
            is_object($result) && is_array($result->questions ?? null)
            && count($result->questions) <= $specification->questionCount,
            'Generation: does not exceed requested count'
        );
    }
}
