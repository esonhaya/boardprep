<?php

declare(strict_types=1);

namespace App\Services\Learning\Recommendation;

use App\Core\App;
use App\Services\Question\QuestionEligibilityService;

/** Request-local canonical question evidence shared by readiness and study links. */
final class LearningQuestionCatalog
{
    /** @var array<string,string>|null */
    private static ?array $topicByQuestionId = null;
    /** @var array<string,array<string,int>> */
    private static array $eligibleCountsByScope = [];

    /** @return array<string,string> */
    public static function topicByQuestionId(): array
    {
        if (self::$topicByQuestionId !== null) {
            return self::$topicByQuestionId;
        }
        self::indexTopics(App::storage()->all('questions'));
        return self::$topicByQuestionId ?? [];
    }

    /**
     * Count usable subject pools through the same final eligibility and
     * variant filters as quiz selection. The canonical snapshot is released
     * after this pass; only small request-local indexes remain.
     *
     * @param array<string,mixed> $scope
     * @param array<string,string> $subjects subject ID => display label
     * @return array<string,int>
     */
    public static function eligibleSubjectCounts(array $scope, array $subjects): array
    {
        $key = implode("\0", array_map(
            static fn(string $field): string => strtolower(trim((string) ($scope[$field] ?? ''))),
            ['board', 'let_level', 'let_specialization', 'civil_service_level']
        ));
        if (isset(self::$eligibleCountsByScope[$key])) {
            return self::$eligibleCountsByScope[$key];
        }
        if ($subjects === []) {
            return self::$eligibleCountsByScope[$key] = [];
        }

        $questions = App::storage()->all('questions');
        self::indexTopics($questions);

        $letVariant = null;
        if (($scope['board'] ?? '') === 'let' && trim((string) ($scope['let_level'] ?? '')) !== '') {
            $validation = \App\Services\Quiz\LET\LETVariantRegistry::validateConfiguration([
                'level' => $scope['let_level'],
                'specialization_id' => $scope['let_specialization'] ?? '',
            ]);
            if (($validation['valid'] ?? false) !== true) {
                return self::$eligibleCountsByScope[$key] = array_fill_keys(array_keys($subjects), 0);
            }
            $letVariant = $validation['configuration'] ?? null;
        }

        $eligibility = QuestionEligibilityService::index($questions);
        $request = new \SelectionRequest(
            subject: '*',
            domain: null,
            difficultyDistribution: [],
            questionCount: 1,
            exam: (string) ($scope['board'] ?? ''),
            letVariant: $letVariant,
            civilServiceLevel: trim((string) ($scope['civil_service_level'] ?? '')) !== ''
                ? (string) $scope['civil_service_level']
                : null
        );
        $counts = \QuestionPoolFilter::countBySubject($questions, $subjects, $request, $eligibility);
        return self::$eligibleCountsByScope[$key] = $counts;
    }

    private static function indexTopics(array $questions): void
    {
        if (self::$topicByQuestionId !== null) {
            return;
        }
        self::$topicByQuestionId = [];
        foreach ($questions as $question) {
            if (!is_array($question)) {
                continue;
            }
            $id = is_scalar($question['id'] ?? null) ? trim((string) $question['id']) : '';
            $taxonomy = is_array($question['taxonomy'] ?? null) ? $question['taxonomy'] : [];
            $topic = is_scalar($taxonomy['topic_id'] ?? $question['topic'] ?? null)
                ? trim((string) ($taxonomy['topic_id'] ?? $question['topic'] ?? ''))
                : '';
            if ($id !== '' && $topic !== '') {
                self::$topicByQuestionId[$id] = $topic;
            }
        }
    }
}
