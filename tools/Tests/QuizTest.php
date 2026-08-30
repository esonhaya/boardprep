<?php

declare(strict_types=1);

namespace Tools\Tests;

use App\Core\Autoloader;

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';

Autoloader::register();

require_once __DIR__ . '/QuizTestNavigationTrait.php';
require_once __DIR__ . '/QuizTestSubmissionTrait.php';
require_once __DIR__ . '/QuizTestGenerationTrait.php';
require_once __DIR__ . '/QuizTestBlueprintDistributionTrait.php';
require_once __DIR__ . '/QuizTestBlueprintCoverageTrait.php';

final class QuizTest
{
    use QuizTestNavigationTrait;
    use QuizTestSubmissionTrait;
    use QuizTestGenerationTrait;
    use QuizTestBlueprintDistributionTrait;
    use QuizTestBlueprintCoverageTrait;

    private int $passed = 0;
    private int $failed = 0;
    private int $assertions = 0;

    public function run(): void
    {
        $this->header();

        $this->testAutoloader();
        $this->testServicesAvailable();
        $this->testCoreQuizServices();
        $this->testSelectionServices();
        $this->testDifficultySelectionBehavior();
        $this->testBlueprintServices();
        $this->testScoringBehavior();
        $this->testHistoryBehavior();
        $this->testResultBehavior();
        $this->testNavigationBehavior();
        $this->testSubmissionBehavior();
        $this->testBalancingBehavior();
        $this->testSelectionBehavior();
        $this->testGenerationBehavior();
        $this->testBlueprintDistributionBehavior();
        $this->testRuntimeAllocationBehavior();
        $this->testBlueprintCoverageBehavior();
        $this->testContentAuthoringBehavior();

        $this->summary();
    }

    private function header(): void
    {
        echo "======================================\n";
        echo " BoardPrep Quiz Test\n";
        echo "======================================\n";
        echo "Mode: In-process simulation\n";
        echo "Database: NOT USED\n";
        echo "HTTP server: NOT USED\n\n";
    }

    private function testAutoloader(): void
    {
        echo "[TEST] Application autoloader\n";

        $this->assertTrue(
            class_exists(Autoloader::class),
            'Autoloader available'
        );

        echo "[PASS] OK\n";
    }

    private function testServicesAvailable(): void
    {
        echo "[TEST] Quiz services\n";

        $services = [
            'QuizScoringService',
            'QuizStartService',
            'QuizSubmissionService',
            'QuizNavigationService',
            'QuizResultService',
            'QuizGenerationService',
            'QuizHistoryService',
            'QuizBlueprintService',
            'QuestionSelectionService',
            'QuestionBalancingService',
            'AdaptiveQuizService',
            'ExamAssemblyService',
        ];

        $before = $this->failed;

        foreach ($services as $class) {
            $this->assertTrue(
                class_exists($class),
                "{$class} available"
            );
        }

        if ($this->failed === $before) {
            echo "[PASS] OK\n";
        }
    }

    private function testCoreQuizServices(): void
    {
        echo "[TEST] Core quiz service methods\n";

        $methods = [
            'QuizScoringService' => [
                'calculate',
                'checkAnswer',
            ],
            'QuizStartService' => [
                'start',
            ],
            'QuizSubmissionService' => [
                'submit',
            ],
            'QuizNavigationService' => [
                'next',
                'current',
                'reset',
            ],
            'QuizResultService' => [
                'build',
            ],
            'QuizGenerationService' => [
                'generate',
            ],
            'QuizHistoryService' => [
                'all',
            ],
        ];

        $before = $this->failed;

        foreach ($methods as $class => $classMethods) {
            foreach ($classMethods as $method) {
                $this->assertTrue(
                    method_exists($class, $method),
                    "{$class}::{$method} exists"
                );
            }
        }

        if ($this->failed === $before) {
            echo "[PASS] OK\n";
        }
    }

    private function testSelectionServices(): void
    {
        echo "[TEST] Quiz selection services\n";

        $services = [
            'QuestionSelectionService',
            'QuestionBalancingService',
            'AdaptiveQuizService',
            'ExamAssemblyService',
        ];

        $before = $this->failed;

        foreach ($services as $class) {
            $this->assertTrue(
                class_exists($class),
                "{$class} available"
            );
        }

        if ($this->failed === $before) {
            echo "[PASS] OK\n";
        }
    }

    private function testDifficultySelectionBehavior(): void
    {
        echo "[TEST] Difficulty selection behavior\n";

        $this->testDifficultyQuotaBehavior();
        $this->testDifficultyShortageRecovery();
        $this->testDifficultyEdgeCases();
        $this->testDifficultyUniqueness();

        echo "[PASS] OK\n";
    }

    private function testDifficultyQuotaBehavior(): void
    {
        $questions = [
            ['id' => 701, 'difficulty' => 'easy'],
            ['id' => 702, 'difficulty' => 'easy'],
            ['id' => 703, 'difficulty' => 'easy'],
            ['id' => 704, 'difficulty' => 'medium'],
            ['id' => 705, 'difficulty' => 'medium'],
            ['id' => 706, 'difficulty' => 'medium'],
            ['id' => 707, 'difficulty' => 'hard'],
            ['id' => 708, 'difficulty' => 'hard'],
        ];

        $balanced =
            \DifficultySelectionService::select(
                $questions,
                [
                    'easy' => 50,
                    'medium' => 50,
                ],
                6
            );

        $this->assertSame(
            6,
            count($balanced),
            'Difficulty: requested count is respected'
        );

        $counts = [
            'easy' => 0,
            'medium' => 0,
            'hard' => 0,
        ];

        foreach ($balanced as $question) {
            $difficulty =
                strtolower(
                    (string) ($question['difficulty'] ?? '')
                );

            if (isset($counts[$difficulty])) {
                $counts[$difficulty]++;
            }
        }

        $this->assertSame(
            3,
            $counts['easy'],
            'Difficulty: easy quota is respected'
        );

        $this->assertSame(
            3,
            $counts['medium'],
            'Difficulty: medium quota is respected'
        );
    }

    private function testDifficultyShortageRecovery(): void
    {
        $shortPool = [
            ['id' => 711, 'difficulty' => 'easy'],
            ['id' => 712, 'difficulty' => 'easy'],
            ['id' => 713, 'difficulty' => 'medium'],
        ];

        $recovered =
            \DifficultySelectionService::select(
                $shortPool,
                [
                    'easy' => 50,
                    'hard' => 50,
                ],
                3
            );

        $this->assertSame(
            3,
            count($recovered),
            'Difficulty: shortage recovery fills available count'
        );
    }

    private function testDifficultyEdgeCases(): void
    {
        $questions = [
            ['id' => 701, 'difficulty' => 'easy'],
            ['id' => 702, 'difficulty' => 'easy'],
            ['id' => 703, 'difficulty' => 'easy'],
            ['id' => 704, 'difficulty' => 'medium'],
            ['id' => 705, 'difficulty' => 'medium'],
            ['id' => 706, 'difficulty' => 'medium'],
            ['id' => 707, 'difficulty' => 'hard'],
            ['id' => 708, 'difficulty' => 'hard'],
        ];

        $mixed =
            \DifficultySelectionService::select(
                $questions,
                [],
                4
            );

        $this->assertSame(
            4,
            count($mixed),
            'Difficulty: empty distribution selects requested count'
        );

        $none =
            \DifficultySelectionService::select(
                $questions,
                [
                    'easy' => 50,
                    'medium' => 50,
                ],
                0
            );

        $this->assertSame(
            0,
            count($none),
            'Difficulty: zero question count returns empty selection'
        );
    }

    private function testDifficultyUniqueness(): void
    {
        $questions = [
            ['id' => 701, 'difficulty' => 'easy'],
            ['id' => 702, 'difficulty' => 'easy'],
            ['id' => 703, 'difficulty' => 'easy'],
            ['id' => 704, 'difficulty' => 'medium'],
            ['id' => 705, 'difficulty' => 'medium'],
            ['id' => 706, 'difficulty' => 'medium'],
            ['id' => 707, 'difficulty' => 'hard'],
            ['id' => 708, 'difficulty' => 'hard'],
        ];

        $balanced =
            \DifficultySelectionService::select(
                $questions,
                [
                    'easy' => 50,
                    'medium' => 50,
                ],
                6
            );

        $ids =
            array_map(
                static fn(array $question): int =>
                    (int) ($question['id'] ?? 0),
                $balanced
            );

        $this->assertSame(
            count($ids),
            count(array_unique($ids)),
            'Difficulty: selected question IDs are unique'
        );
    }
    private function testBlueprintServices(): void
    {
        echo "[TEST] Quiz blueprint services\n";

        $services = [
            'BlueprintExecutor',
            'BlueprintResolverService',
            'BlueprintCoverageAnalyzer',
            'BlueprintCoverageValidator',
            'BlueprintDifficultyAllocator',
            'BlueprintDistributionService',
            'BlueprintIntegrityValidator',
        ];

        $before = $this->failed;

        foreach ($services as $class) {
            $this->assertTrue(
                class_exists($class),
                "{$class} available"
            );
        }

        if ($this->failed === $before) {
            echo "[PASS] OK\n";
        }
    }

    private function testScoringBehavior(): void
    {
        echo "[TEST] Quiz scoring behavior\n";

        $questions = $this->scoringQuestions();
        $answers = [
            1 => 'B',
            2 => '4',
            3 => 'A',
        ];

        $result = \QuizScoringService::calculate(
            $questions,
            $answers
        );

        $this->assertScoringSummary($result);
        $this->assertScoringAnswers($questions);
        $this->assertScoringResults($result);

        echo "[PASS] OK\n";
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function scoringQuestions(): array
    {
        return [
            [
                'id' => 1,
                'question' => 'Capital of France?',
                'choices' => [
                    'London',
                    'Paris',
                    'Berlin',
                    'Madrid',
                ],
                'answer' => 'Paris',
            ],
            [
                'id' => 2,
                'question' => '2 + 2 = ?',
                'choices' => [
                    '3',
                    '4',
                    '5',
                    '6',
                ],
                'answer' => '4',
            ],
            [
                'id' => 3,
                'question' => 'Primary color?',
                'choices' => [
                    'Green',
                    'Orange',
                    'Blue',
                    'Purple',
                ],
                'answer' => 'Blue',
            ],
            [
                'id' => 4,
                'question' => 'Largest ocean?',
                'choices' => [
                    'Atlantic',
                    'Indian',
                    'Pacific',
                    'Arctic',
                ],
                'answer' => 'Pacific',
            ],
        ];
    }

    /**
     * @param array<string,mixed> $result
     */
    private function assertScoringSummary(array $result): void
    {
        $this->assertSame(
            2,
            $result['score'] ?? null,
            'Scoring: correct score'
        );

        $this->assertSame(
            2,
            $result['correct'] ?? null,
            'Scoring: correct count'
        );

        $this->assertSame(
            1,
            $result['incorrect'] ?? null,
            'Scoring: incorrect count'
        );

        $this->assertSame(
            1,
            $result['unanswered'] ?? null,
            'Scoring: unanswered count'
        );

        $this->assertSame(
            4,
            $result['total'] ?? null,
            'Scoring: total count'
        );

        $this->assertTrue(
            (float) ($result['percentage'] ?? -1) === 50.0,
            'Scoring: percentage'
        );
    }

    /**
     * @param array<int,array<string,mixed>> $questions
     */
    private function assertScoringAnswers(array $questions): void
    {
        $this->assertTrue(
            \QuizScoringService::checkAnswer(
                $questions[0],
                'B'
            ),
            'Scoring: choice letter B resolves correctly'
        );

        $this->assertTrue(
            \QuizScoringService::checkAnswer(
                $questions[0],
                'paris'
            ),
            'Scoring: direct answer is case-insensitive'
        );

        $this->assertFalse(
            \QuizScoringService::checkAnswer(
                $questions[0],
                'A'
            ),
            'Scoring: wrong choice is rejected'
        );

        $this->assertFalse(
            \QuizScoringService::checkAnswer(
                $questions[0],
                null
            ),
            'Scoring: unanswered answer is rejected'
        );
    }

    /**
     * @param array<string,mixed> $result
     */
    private function assertScoringResults(array $result): void
    {
        $this->assertSame(
            4,
            count($result['results'] ?? []),
            'Scoring: one result per question'
        );

        $this->assertTrue(
            ($result['results'][0]['correct'] ?? false) === true,
            'Scoring: first result marked correct'
        );

        $this->assertTrue(
            ($result['results'][2]['correct'] ?? true) === false,
            'Scoring: third result marked incorrect'
        );

        $this->assertTrue(
            ($result['results'][3]['answered'] ?? true) === false,
            'Scoring: fourth result marked unanswered'
        );
    }

    private function testHistoryBehavior(): void
    {
        echo "[TEST] Quiz history behavior\n";

        $this->assertTrue(
            class_exists('SessionService'),
            'SessionService available'
        );

        if (!class_exists('SessionService')) {
            echo "[FAIL] Cannot continue history behavior test.\n";
            return;
        }

        \SessionService::remove('quizHistory');
        \SessionService::remove('usedQuestions');

        $this->assertSame(
            [],
            \QuizHistoryService::all(),
            'History: empty history initially'
        );

        $questions = [
            [
                'id' => 101,
                'question' => 'Question one',
            ],
            [
                'id' => 102,
                'question' => 'Question two',
            ],
            [
                'id' => 103,
                'question' => 'Question three',
            ],
        ];

        \QuizHistoryService::remember([
            $questions[0],
            $questions[1],
        ]);

        $unused = \QuizHistoryService::filterUnused(
            $questions
        );

        $this->assertSame(
            1,
            count($unused),
            'History: used questions are filtered'
        );

        $this->assertSame(
            103,
            $unused[0]['id'] ?? null,
            'History: unused question remains'
        );

        \QuizHistoryService::remember([
            $questions[2],
        ]);

        $cycled = \QuizHistoryService::filterUnused(
            $questions
        );

        $this->assertSame(
            3,
            count($cycled),
            'History: exhausted pool resets and returns all questions'
        );

        $this->assertSame(
            [],
            \SessionService::get(
                'usedQuestions',
                []
            ),
            'History: used-question session resets after exhaustion'
        );

        \SessionService::remove('quizHistory');
        \SessionService::remove('usedQuestions');

        echo "[PASS] OK\n";
    }

    private function testResultBehavior(): void
    {
        echo "[TEST] Quiz result behavior\n";

        if (!$this->prepareResultBehavior()) {
            return;
        }

        $result = \QuizResultService::build();

        $this->assertResultSummary($result);
        $this->assertResultReview($result);

        \SessionService::remove('questions');
        \SessionService::remove('answers');

        echo "[PASS] OK\n";
    }

    private function prepareResultBehavior(): bool
    {
        $this->assertTrue(
            class_exists('SessionService'),
            'Result: SessionService available'
        );

        if (!class_exists('SessionService')) {
            echo "[FAIL] Cannot continue result behavior test.\n";
            return false;
        }

        \SessionService::remove('questions');
        \SessionService::remove('answers');

        \SessionService::set(
            'questions',
            $this->resultQuestions()
        );

        \SessionService::set(
            'answers',
            [
                201 => 'B',
                202 => '3',
            ]
        );

        return true;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function resultQuestions(): array
    {
        return [
            [
                'id' => 201,
                'question' => 'Capital of France?',
                'choices' => [
                    'London',
                    'Paris',
                    'Berlin',
                    'Madrid',
                ],
                'answer' => 'Paris',
            ],
            [
                'id' => 202,
                'question' => '2 + 2 = ?',
                'choices' => [
                    '3',
                    '4',
                    '5',
                    '6',
                ],
                'answer' => '4',
            ],
            [
                'id' => 203,
                'question' => 'Primary color?',
                'choices' => [
                    'Green',
                    'Orange',
                    'Blue',
                    'Purple',
                ],
                'answer' => 'Blue',
            ],
        ];
    }

    /**
     * @param array<string,mixed> $result
     */
    private function assertResultSummary(array $result): void
    {
        $this->assertTrue(
            is_array($result),
            'Result: build returns array'
        );

        $this->assertTrue(
            isset($result['summary']),
            'Result: summary exists'
        );

        $this->assertTrue(
            isset($result['review']),
            'Result: review exists'
        );

        $summary = $result['summary'] ?? [];

        $this->assertSame(
            1,
            $summary['score'] ?? null,
            'Result: score comes from scoring'
        );

        $this->assertSame(
            1,
            $summary['correct'] ?? null,
            'Result: correct count comes from scoring'
        );

        $this->assertSame(
            1,
            $summary['incorrect'] ?? null,
            'Result: incorrect count comes from scoring'
        );

        $this->assertSame(
            1,
            $summary['unanswered'] ?? null,
            'Result: unanswered count comes from scoring'
        );

        $this->assertSame(
            3,
            $summary['total'] ?? null,
            'Result: total comes from scoring'
        );

        $this->assertTrue(
            (float) ($summary['percentage'] ?? -1) === 33.0,
            'Result: percentage comes from scoring'
        );
    }

    /**
     * @param array<string,mixed> $result
     */
    private function assertResultReview(array $result): void
    {
        $review = $result['review'] ?? [];

        $this->assertSame(
            3,
            count($review),
            'Result: review contains one entry per question'
        );

        $this->assertTrue(
            ($review[0]['correct'] ?? false) === true,
            'Result: first review entry is correct'
        );

        $this->assertTrue(
            ($review[1]['correct'] ?? true) === false,
            'Result: second review entry is incorrect'
        );

        $this->assertTrue(
            ($review[2]['answered'] ?? true) === false,
            'Result: third review entry is unanswered'
        );
    }

    private function testBalancingBehavior(): void
    {
        echo "[TEST] Quiz balancing behavior\n";

        $questions = $this->balancingQuestions();

        $this->assertBalancedQuestionPreservation($questions);
        $this->assertBalancedDifficultyFiltering($questions);
        $this->assertBalancedTopicInterleaving();

        echo "[PASS] OK\n";
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function balancingQuestions(): array
    {
        return [
            [
                'id' => 401,
                'topic' => 'Grammar',
                'difficulty' => 'easy',
            ],
            [
                'id' => 402,
                'topic' => 'Grammar',
                'difficulty' => 'hard',
            ],
            [
                'id' => 403,
                'topic' => 'Vocabulary',
                'difficulty' => 'easy',
            ],
            [
                'id' => 404,
                'topic' => 'Vocabulary',
                'difficulty' => 'medium',
            ],
            [
                'id' => 405,
                'difficulty' => 'easy',
            ],
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $questions
     */
    private function assertBalancedQuestionPreservation(
        array $questions
    ): void {
        $balanced =
            \QuestionBalancingService::balance(
                $questions
            );

        $this->assertSame(
            5,
            count($balanced),
            'Balancing: mixed difficulty preserves all questions'
        );

        $ids = array_map(
            static fn(array $question): int =>
                (int) $question['id'],
            $balanced
        );

        sort($ids);

        $this->assertSame(
            [401, 402, 403, 404, 405],
            $ids,
            'Balancing: all questions are preserved'
        );
    }

    /**
     * @param array<int,array<string,mixed>> $questions
     */
    private function assertBalancedDifficultyFiltering(
        array $questions
    ): void {
        $easy =
            \QuestionBalancingService::balance(
                $questions,
                [
                    'difficulty' => 'easy',
                ]
            );

        $this->assertSame(
            3,
            count($easy),
            'Balancing: difficulty filter selects easy questions'
        );

        foreach ($easy as $question) {
            $this->assertTrue(
                strtolower($question['difficulty'] ?? '') === 'easy',
                'Balancing: filtered questions match requested difficulty'
            );
        }
    }

    private function assertBalancedTopicInterleaving(): void
    {
        $singleTopic = [
            [
                'id' => 501,
                'topic' => 'Grammar',
                'difficulty' => 'easy',
            ],
            [
                'id' => 502,
                'topic' => 'Grammar',
                'difficulty' => 'easy',
            ],
            [
                'id' => 503,
                'topic' => 'Vocabulary',
                'difficulty' => 'easy',
            ],
            [
                'id' => 504,
                'topic' => 'Vocabulary',
                'difficulty' => 'easy',
            ],
        ];

        $ordered =
            \QuestionBalancingService::balance(
                $singleTopic
            );

        $this->assertSame(
            4,
            count($ordered),
            'Balancing: topic groups preserve total count'
        );

        $firstTopic =
            strtolower(
                trim(
                    $ordered[0]['topic'] ?? ''
                )
            );

        $secondTopic =
            strtolower(
                trim(
                    $ordered[1]['topic'] ?? ''
                )
            );

        $this->assertTrue(
            $firstTopic !== $secondTopic,
            'Balancing: different topics are interleaved'
        );
    }

    private function testSelectionBehavior(): void
    {
        echo "[TEST] Quiz selection behavior\n";

        $questions = $this->selectionQuestions();

        $this->testSelectionBySubject($questions);
        $this->testSelectionQuestionCount($questions);
        $this->testSelectionSubjectIsolation($questions);

        echo "[PASS] OK\n";
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function selectionQuestions(): array
    {
        $questions = [
            [
                'id' => 601,
                'subject' => 'English',
                'domain' => 'Grammar',
                'status' => 'approved',
                'difficulty' => 'easy',
                'question' => 'Grammar 1',
            ],
            [
                'id' => 602,
                'subject' => 'English',
                'domain' => 'Grammar',
                'status' => 'approved',
                'difficulty' => 'medium',
                'question' => 'Grammar 2',
            ],
            [
                'id' => 603,
                'subject' => 'English',
                'domain' => 'Reading',
                'status' => 'approved',
                'difficulty' => 'easy',
                'question' => 'Reading 1',
            ],
            [
                'id' => 604,
                'subject' => 'Science',
                'domain' => 'Biology',
                'status' => 'approved',
                'difficulty' => 'easy',
                'question' => 'Science 1',
            ],
        ];
        return array_map(static function(array $question): array {
            $question['choices'] = ['A', 'B', 'C', 'D'];
            $question['answer'] = 'A';
            $question['explanation'] = 'A is the correct answer for this selection fixture.';
            return $question;
        }, $questions);
    }

    /**
     * @param array<int,array<string,mixed>> $questions
     */
    private function testSelectionBySubject(
        array $questions
    ): void {
        $specification = $this->selectionSpecification(
            'English',
            'Grammar',
            10
        );

        $selected =
            \QuestionSelectionService::select(
                $questions,
                $specification
            );

        $this->assertSame(
            3,
            count($selected),
            'Selection: subject matching questions selected'
        );

        foreach ($selected as $question) {
            $this->assertTrue(
                ($question['subject'] ?? null) === 'English',
                'Selection: selected question belongs to requested subject'
            );
        }
    }

    /**
     * @param array<int,array<string,mixed>> $questions
     */
    private function testSelectionQuestionCount(
        array $questions
    ): void {
        $specification = $this->selectionSpecification(
            'English',
            'Grammar',
            2
        );

        $selected =
            \QuestionSelectionService::select(
                $questions,
                $specification
            );

        $this->assertSame(
            2,
            count($selected),
            'Selection: question count is respected'
        );
    }

    /**
     * @param array<int,array<string,mixed>> $questions
     */
    private function testSelectionSubjectIsolation(
        array $questions
    ): void {
        $specification = $this->selectionSpecification(
            'Science',
            'Biology',
            10
        );

        $selected =
            \QuestionSelectionService::select(
                $questions,
                $specification
            );

        $this->assertSame(
            1,
            count($selected),
            'Selection: different subject does not leak English questions'
        );
    }

    private function selectionSpecification(
        string $subject,
        string $domain,
        int $questionCount
    ): \QuizSpecification {
        return new \QuizSpecification(
            board: 'LET',
            subject: $subject,
            domain: $domain,
            topics: [],
            concepts: [],
            difficulty: 'mixed',
            questionCount: $questionCount,
            mode: 'practice',
            adaptive: false,
            shuffle: false,
            boardBlueprintVersion: null,
            subjectBlueprintVersion: null
        );
    }

    private function testRuntimeAllocationBehavior(): void
    {
        echo "[TEST] Runtime allocation behavior\n";

        $this->assertTrue(
            class_exists('RuntimeAllocationService'),
            'Allocation: RuntimeAllocationService available'
        );

        if (!class_exists('RuntimeAllocationService')) {
            echo "[FAIL] Cannot continue allocation behavior test.\n";
            return;
        }

        $this->assertSame(
            [],
            \RuntimeAllocationService::allocate(
                0,
                ['easy' => 50, 'hard' => 50]
            ),
            'Allocation: zero total returns empty'
        );

        $this->assertSame(
            [],
            \RuntimeAllocationService::allocate(
                10,
                []
            ),
            'Allocation: empty distribution returns empty'
        );

        $result = \RuntimeAllocationService::allocate(
            10,
            [
                'easy' => 50,
                'medium' => 30,
                'hard' => 20,
            ]
        );

        $this->assertSame(
            10,
            array_sum($result),
            'Allocation: total is preserved'
        );

        $this->assertSame(
            5,
            $result['easy'] ?? null,
            'Allocation: easy receives expected count'
        );

        $this->assertSame(
            3,
            $result['medium'] ?? null,
            'Allocation: medium receives expected count'
        );

        $this->assertSame(
            2,
            $result['hard'] ?? null,
            'Allocation: hard receives expected count'
        );

        echo "[PASS] OK\n";
    }


    private function assertNotNull(
        mixed $value,
        string $message
    ): void {
        $this->assertions++;

        if ($value !== null) {
            $this->passed++;
            return;
        }

        $this->failed++;
        echo "[FAIL] {$message}\\n";
    }

    private function testContentAuthoringBehavior(): void
    {
        echo "[TEST] Content authoring behavior\n";

        $this->assertAuthoringServicesAvailable();
        $question = $this->buildAuthoringQuestion();
        $this->assertAuthoringQuestionData($question);
        $this->assertInvalidBlueprint();
        $this->assertQuestionSaveValidationAvailable();

        echo "[PASS] OK\n";
    }

    private function assertAuthoringServicesAvailable(): void
    {
        $this->assertTrue(
            class_exists(\App\Services\Board\BoardService::class),
            "Authoring: BoardService available"
        );

        $this->assertTrue(
            method_exists(
                \App\Services\Board\BoardService::class,
                "create"
            ),
            "Authoring: BoardService::create available"
        );

        $this->assertTrue(
            class_exists(\App\Services\BlueprintService::class),
            "Authoring: BlueprintService available"
        );

        $this->assertTrue(
            method_exists(
                \App\Services\BlueprintService::class,
                "create"
            ),
            "Authoring: BlueprintService::create available"
        );

        $this->assertTrue(
            class_exists(\App\Services\Question\QuestionService::class),
            "Authoring: QuestionService available"
        );
    }

    private function buildAuthoringQuestion(): array
    {
        return \App\Services\Question\QuestionService::build(
            0,
            [
                "board_id" => "LET",
                "subject_id" => "English",
                "domain_id" => "Grammar",
                "topic_id" => "Parts of Speech",
                "concept_id" => "Nouns",
                "difficulty" => "medium",
                "type" => "multiple_choice",
                "question" => "Which word is a noun?",
                "option_1" => "Quickly",
                "option_2" => "Teacher",
                "option_3" => "Beautiful",
                "option_4" => "Run",
                "correct_option" => "option-2",
                "explanation" => "Teacher is a noun.",
            ]
        );
    }

    private function assertAuthoringQuestionData(array $question): void
    {
        $this->assertSame(
            "let",
            $question["taxonomy"]["board_id"],
            "Authoring: board taxonomy preserved"
        );

        $this->assertSame(
            "english",
            $question["taxonomy"]["subject_id"],
            "Authoring: subject taxonomy preserved"
        );

        $this->assertSame(
            "grammar",
            $question["taxonomy"]["domain_id"],
            "Authoring: domain taxonomy preserved"
        );

        $this->assertSame(
            "medium",
            $question["difficulty"],
            "Authoring: difficulty preserved"
        );

        $correctOptions = array_values(
            array_filter(
                $question["options"],
                static fn(array $option): bool =>
                    $option["correct"] === true
            )
        );

        $this->assertSame(
            "option-2",
            $correctOptions[0]["id"] ?? null,
            "Authoring: correct option mapped"
        );
    }

    private function assertInvalidBlueprint(): void
    {
        $invalidBlueprint =
            \App\Services\BlueprintService::create(
                [
                    "board" => "LET",
                    "subject" => "English",
                    "name" => "",
                    "questionCount" => 0,
                    "easy" => 50,
                    "medium" => 30,
                    "hard" => 10,
                ]
            );

        $this->assertSame(
            false,
            $invalidBlueprint["success"] ?? null,
            "Authoring: invalid blueprint rejected"
        );

        $this->assertTrue(
            !empty($invalidBlueprint["errors"]),
            "Authoring: blueprint validation errors returned"
        );
    }

    private function assertQuestionSaveValidationAvailable(): void
    {
        $this->assertTrue(
            method_exists(
                \App\Services\Question\QuestionService::class,
                "validateForSave"
            ),
            "Authoring: question save-validation pipeline available"
        );
    }

    private function assertFalse(
        bool $condition,
        string $message
    ): void {
        $this->assertions++;

        if ($condition) {
            $this->failed++;
            echo "[FAIL] {$message}\n";
        } else {
            $this->passed++;
        }
    }

    private function assertTrue(
        bool $condition,
        string $message
    ): void {
        $this->assertions++;

        if ($condition) {
            $this->passed++;
            return;
        }

        $this->failed++;
        echo "[FAIL] {$message}\n";
    }

    private function assertSame(
        mixed $expected,
        mixed $actual,
        string $message
    ): void {
        $this->assertions++;

        if ($expected === $actual) {
            $this->passed++;
            return;
        }

        $this->failed++;

        echo "[FAIL] {$message}\n";
        echo "       Expected: " . var_export($expected, true) . "\n";
        echo "       Actual:   " . var_export($actual, true) . "\n";
    }

    private function summary(): void
    {
        echo "\n======================================\n";
        echo " SUMMARY\n";
        echo "======================================\n";
        echo "PASS       : {$this->passed}\n";
        echo "FAIL       : {$this->failed}\n";
        echo "ASSERTIONS : {$this->assertions}\n\n";

        if ($this->failed === 0) {
            echo "[PASS] Quiz service simulation passed.\n";
        } else {
            echo "[FAIL] Quiz service simulation failed.\n";
        }
    }
}

$test = new QuizTest();
$test->run();
