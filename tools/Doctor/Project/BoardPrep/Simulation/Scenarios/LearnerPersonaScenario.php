<?php

declare(strict_types=1);

namespace Tools\Doctor\Project\BoardPrep\Simulation\Scenarios;

use App\Core\App;
use App\Repositories\AttemptRepository;
use App\Repositories\QuestionRepository;
use App\Services\Learning\LearningProgressService;
use App\Services\Learning\StudyDashboardService;
use Tools\Doctor\Project\BoardPrep\Simulation\ApplicationSimulator;
use Tools\Doctor\Project\BoardPrep\Simulation\SimulationScenario;

final class LearnerPersonaScenario extends SimulationScenario
{
    private const PERSONAS = [
        'NEW_LEARNER' => [],
        'STRUGGLING_LEARNER' => [30, 40, 20],
        'IMPROVING_LEARNER' => [30, 60, 90],
        'STRONG_LEARNER' => [90, 100, 90],
        'MIXED_LEARNER' => [100, 30, 90, 40],
        'EXAM_READY_LEARNER' => [80, 90, 90, 80],
    ];

    public function name(): string
    {
        return 'Deterministic learner personas and persisted journeys';
    }

    public function run(ApplicationSimulator $simulation): void
    {
        $attempts = App::container()->get(AttemptRepository::class);
        $created = [];

        try {
            foreach (self::PERSONAS as $persona => $scores) {
                $userId = 'simulation-' . strtolower(str_replace('_', '-', $persona));
                foreach ($scores as $index => $score) {
                    $record = $this->attempt($persona, $userId, $index, $score);
                    $attempts->create($record);
                    $created[] = $record['id'];
                }

                // A new repository read represents the next application session.
                $persisted = App::container()->get(AttemptRepository::class)->byUser($userId);
                $this->verifyPersona($persona, $scores, $persisted);
                $simulation->result()->record($persona, true);
            }

            $this->verifyPersistence($attempts);
            $simulation->result()->record('Persistence', true);
            $simulation->result()->record('Weakness analytics', true);
            $simulation->result()->record('Progress analytics', true);
            $simulation->result()->record('Recommendations', true);
            $this->verifyGeneration();
            $simulation->result()->record('Quiz generation', true);
            $simulation->result()->record('Exam simulation', true);
            $this->verifyRecovery();
            $simulation->result()->record('Failure recovery', true);
        } catch (\Throwable $exception) {
            $simulation->result()->record(
                'Learner persona simulation',
                false,
                get_class($exception) . ': ' . $exception->getMessage()
            );
        } finally {
            foreach ($created as $id) {
                $attempts->delete($id);
            }
        }
    }

    private function attempt(string $persona, string $userId, int $index, int $percentage): array
    {
        $mixed = $persona === 'MIXED_LEARNER';
        $topic = $mixed && $index % 2 === 0 ? 'Reading Comprehension' : 'Subject-Verb Agreement';
        $exam = $persona === 'EXAM_READY_LEARNER' && $index === 3;
        $total = 10;

        return [
            'id' => "batch451-{$userId}-{$index}",
            'session_id' => "batch451-session-{$userId}-{$index}",
            'user_id' => $userId,
            'completed' => true,
            'completed_at' => sprintf('2026-08-%02dT10:00:00+08:00', 10 + $index),
            'mode' => $exam ? 'exam' : 'practice',
            'session_type' => $exam ? 'exam_simulation' : 'quiz',
            'subject' => 'English',
            'topic' => $topic,
            'score' => (int) round($percentage / 10),
            'total' => $total,
            'percentage' => $percentage,
            'learning_context' => [
                'subject' => 'English',
                'topic' => $topic,
                'topics' => [$topic],
            ],
        ];
    }

    private function verifyPersona(string $persona, array $scores, array $persisted): void
    {
        if (count($persisted) !== count($scores)) {
            throw new \RuntimeException("{$persona} did not reload exactly once");
        }

        $progress = LearningProgressService::build($persisted);
        $dashboard = StudyDashboardService::build($persisted);
        if ($progress['totalAttempts'] !== count($scores)) {
            throw new \RuntimeException("{$persona} progress disagrees with persisted history");
        }

        if ($persona === 'NEW_LEARNER' && ($dashboard['weakestTopics'] !== []
            || ($dashboard['studyPlan'][0]['topic'] ?? null) !== 'General')) {
            throw new \RuntimeException('new learner was assigned a fabricated weakness');
        }
        if ($persona === 'STRUGGLING_LEARNER'
            && ($dashboard['studyPlan'][0]['topic'] ?? null) !== 'Subject-Verb Agreement') {
            throw new \RuntimeException('struggling learner did not receive targeted practice');
        }
        if ($persona === 'IMPROVING_LEARNER' && ($progress['trend']['direction'] ?? '') !== 'improving') {
            throw new \RuntimeException('improving learner trend was not detected');
        }
        if ($persona === 'STRONG_LEARNER' && $progress['averageScore'] < 90) {
            throw new \RuntimeException('strong learner analytics falsely degraded performance');
        }
        if ($persona === 'MIXED_LEARNER'
            && ($dashboard['weakestTopics'][0]['topic'] ?? null) !== 'Subject-Verb Agreement') {
            throw new \RuntimeException('mixed learner recommendation was not targeted');
        }
        if ($persona === 'EXAM_READY_LEARNER' && $progress['examAttempts'] !== 1) {
            throw new \RuntimeException('exam completion did not survive reload');
        }
    }

    private function verifyPersistence(AttemptRepository $repository): void
    {
        $learner = $repository->byUser('simulation-exam-ready-learner');
        $sessions = array_column($learner, 'session_id');
        if (count($sessions) !== count(array_unique($sessions))) {
            throw new \RuntimeException('duplicate sessions were persisted');
        }
    }

    private function verifyGeneration(): void
    {
        $questions = array_values(array_filter(
            App::container()->get(QuestionRepository::class)->all(),
            static fn(array $question): bool => ($question['status'] ?? 'active') === 'active'
        ));
        if ($questions === []) {
            throw new \RuntimeException('production question repository has no approved content');
        }

        $result = \QuizGenerationService::generate($questions, new \QuizSpecification(
            'LET', 'English', null, ['Subject-Verb Agreement'], [], 'mixed', 2,
            'practice', false, false, null, null
        ));
        if ($result->questions === []) {
            throw new \RuntimeException('targeted production generation returned no eligible questions');
        }
    }

    private function verifyRecovery(): void
    {
        $valid = $this->attempt('STRUGGLING_LEARNER', 'recovery', 0, 40);
        $progress = LearningProgressService::build([
            $valid,
            'malformed optional row',
            ['id' => 'legacy-incomplete', 'topic' => 'Legacy'],
        ]);
        if ($progress['totalAttempts'] !== 1 || $progress['averageScore'] !== 40) {
            throw new \RuntimeException('malformed optional history corrupted valid analytics');
        }

        $empty = \QuizGenerationService::generate([], new \QuizSpecification(
            'LET', 'English', null, ['Not A Taxonomy Topic'], [], 'hard', 5,
            'practice', false, false, null, null
        ));
        if ($empty->questions !== []) {
            throw new \RuntimeException('invalid empty selection fabricated questions');
        }
    }
}
