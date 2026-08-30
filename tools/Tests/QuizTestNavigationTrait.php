<?php
declare(strict_types=1);
namespace Tools\Tests;

trait QuizTestNavigationTrait
{
    private function testNavigationBehavior(): void
    {
        echo "[TEST] Quiz navigation behavior\n";
        if (!$this->assertNavigationDependency()) { return; }
        $this->resetNavigationSession();
        $this->assertEmptyNavigationState();
        $this->seedNavigationQuestions();
        $this->assertNavigationProgression();
        $this->resetNavigationSession();
        echo "[PASS] OK\n";
    }

    private function assertNavigationDependency(): bool
    {
        $available = class_exists('SessionService');
        $this->assertTrue($available, 'Navigation: SessionService available');
        if ($available) { return true; }
        echo "[FAIL] Cannot continue navigation behavior test.\n";
        return false;
    }

    private function resetNavigationSession(): void
    {
        \SessionService::remove('questions');
        \SessionService::remove('currentQuestion');
    }

    private function assertEmptyNavigationState(): void
    {
        $this->assertSame(0, \QuizNavigationService::current(), 'Navigation: current defaults to zero');
        $this->assertFalse(\QuizNavigationService::isLastQuestion(), 'Navigation: empty quiz is not last question');
    }

    private function seedNavigationQuestions(): void
    {
        \SessionService::set('questions', [
            ['id'=>301,'question'=>'Question one'],
            ['id'=>302,'question'=>'Question two'],
            ['id'=>303,'question'=>'Question three'],
        ]);
        \QuizNavigationService::reset();
    }

    private function assertNavigationProgression(): void
    {
        $this->assertNavigationPosition(0, 'reset returns to first question', 'first question is not last');
        \SessionService::set('currentQuestion', 1);
        $this->assertNavigationPosition(1, 'current question is persisted', 'middle question is not last');
        \SessionService::set('currentQuestion', 2);
        $this->assertTrue(\QuizNavigationService::isLastQuestion(), 'Navigation: final question is detected');
    }

    private function assertNavigationPosition(int $expectedIndex, string $indexMessage, string $lastMessage): void
    {
        $this->assertSame($expectedIndex, \QuizNavigationService::current(), "Navigation: {$indexMessage}");
        $this->assertFalse(\QuizNavigationService::isLastQuestion(), "Navigation: {$lastMessage}");
    }
}
