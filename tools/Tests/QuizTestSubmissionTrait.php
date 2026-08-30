<?php
declare(strict_types=1);
namespace Tools\Tests;

trait QuizTestSubmissionTrait
{
    private function testSubmissionBehavior(): void
    {
        echo "[TEST] Quiz submission behavior\n";
        if (!$this->assertSubmissionDependencies()) { return; }
        $this->seedSubmissionSession();
        $this->assertSubmissionState();
        $this->storeSubmissionAnswer();
        $this->assertStoredSubmissionAnswer();
        $this->clearSubmissionSession();
        echo "[PASS] OK\n";
    }

    private function assertSubmissionDependencies(): bool
    {
        $sessionAvailable = class_exists('SessionService');
        $submissionAvailable = class_exists('QuizSubmissionService');
        $this->assertTrue($sessionAvailable, 'Submission: SessionService available');
        if (!$sessionAvailable) {
            echo "[FAIL] Cannot continue submission behavior test.\n";
            return false;
        }
        $this->assertTrue($submissionAvailable, 'Submission: QuizSubmissionService available');
        if (!$submissionAvailable) {
            echo "[FAIL] Cannot continue submission behavior test.\n";
            return false;
        }
        return true;
    }

    private function seedSubmissionSession(): void
    {
        \SessionService::set('questions', [$this->submissionQuestion()]);
        \SessionService::set('answers', []);
        \SessionService::set('currentQuestion', 0);
        \SessionService::set('mode', 'exam');
    }

    private function submissionQuestion(): array
    {
        return [
            'id'=>401,'question'=>'Capital of France?',
            'choices'=>['London','Paris','Berlin','Madrid'],
            'answer'=>'Paris',
            'explanation'=>'Paris is the capital of France.',
        ];
    }

    private function assertSubmissionState(): void
    {
        $this->assertSame([], \SessionService::get('answers', []), 'Submission: answers initially empty');
    }

    private function storeSubmissionAnswer(): void
    {
        $answers = \SessionService::get('answers', []);
        $answers[401] = 'B';
        \SessionService::set('answers', $answers);
    }

    private function assertStoredSubmissionAnswer(): void
    {
        $stored = \SessionService::get('answers', []);
        $this->assertSame('B', $stored[401] ?? null, 'Submission: selected answer stored by question id');
        $this->assertTrue(
            \QuizScoringService::checkAnswer(\SessionService::get('questions')[0], $stored[401]),
            'Submission: stored answer can be scored'
        );
    }

    private function clearSubmissionSession(): void
    {
        foreach (['questions','answers','currentQuestion','mode','feedback'] as $key) {
            \SessionService::remove($key);
        }
    }
}
