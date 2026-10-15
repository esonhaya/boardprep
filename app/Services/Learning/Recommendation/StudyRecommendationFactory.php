<?php

declare(strict_types=1);

namespace App\Services\Learning\Recommendation;

use App\Core\App;
use App\Services\Study\StudyLibraryService;
use App\Services\Learning\StudyActionService;
use App\Services\Shared\TaxonomyStorageService;

final class StudyRecommendationFactory
{
    /** @var array<string, array<string, array<string, string>>> */
    private static array $studyLinksByContext = [];

    public static function forTopic(array $topic): ?array
    {
        $name = self::text($topic['topic'] ?? '');
        if ($name === '') {
            return null;
        }

        $subject = self::text($topic['subject'] ?? '');
        $board = self::text($topic['board'] ?? '');
        $letLevel = self::text($topic['let_level'] ?? '');
        $letSpecialization = self::text($topic['let_specialization'] ?? '');
        $civilServiceLevel = self::text($topic['civil_service_level'] ?? '');
        $action = StudyActionService::quizForTopic(
            $name,
            $subject,
            'practice',
            5,
            'mixed',
            $board,
            $letLevel,
            $letSpecialization,
            $civilServiceLevel
        );
        $displayName = TaxonomyStorageService::label('taxonomy/topics', $name);
        $boardLabel = $board !== '' ? TaxonomyStorageService::label('boards', $board) : '';
        $variantLabel = '';
        if ($civilServiceLevel !== '') {
            $variantLabel = ucfirst(strtolower(str_replace('_', ' ', $civilServiceLevel)));
        } elseif ($letLevel !== '') {
            $variantLabel = ucfirst(strtolower($letLevel));
            if ($letSpecialization !== '') {
                $specialization = TaxonomyStorageService::find('let-specializations', $letSpecialization);
                $variantLabel .= ' · ' . (is_array($specialization)
                    ? self::text($specialization['official_name'] ?? $letSpecialization)
                    : TaxonomyStorageService::label('let-specializations', $letSpecialization));
            }
        }
        $contextLabels = array_filter([$boardLabel, $variantLabel]);

        $attemptCount = max(0, (int) ($topic['attempts'] ?? 0));
        $repeatMistakes = max(0, (int) ($topic['repeated_mistakes'] ?? 0));
        $trend = self::text($topic['trend'] ?? 'INSUFFICIENT_EVIDENCE');
        $study = self::studyLink(
            $name,
            $board,
            is_array($topic['repeated_mistake_question_ids'] ?? null)
                ? $topic['repeated_mistake_question_ids']
                : []
        );
        if ($repeatMistakes > 0) {
            $reason = 'Repeated misses on ' . $repeatMistakes . ' question'
                . ($repeatMistakes === 1 ? '' : 's')
                . ' across completed quizzes. Review the linked notes, then practice this concept.';
        } elseif ($attemptCount < 2) {
            $reason = 'Based on ' . $attemptCount . ' completed attempt'
                . ($attemptCount === 1 ? '' : 's')
                . '; evidence is limited. Review the linked notes and practice again to check understanding.';
        } else {
            $reason = 'Observed accuracy: ' . (int) ($topic['average'] ?? 0) . '% across '
                . $attemptCount . ' topic attempts.';
            if ($trend === 'IMPROVING' && is_numeric($topic['recent_change_points'] ?? null)) {
                $reason .= ' Recent accuracy is up ' . (int) $topic['recent_change_points'] . ' points.';
            } elseif ($trend === 'DECLINING' && is_numeric($topic['recent_change_points'] ?? null)) {
                $reason .= ' Recent accuracy is down ' . abs((int) $topic['recent_change_points']) . ' points.';
            }
            if ($study !== null) {
                $reason .= ' Review the source-linked notes, then retry.';
            }
        }

        return [
            'type' => 'topic',
            'title' => "Review {$displayName}" . ($contextLabels !== [] ? ' · ' . implode(' · ', $contextLabels) : ''),
            'reason' => $reason,
            'attempts' => $attemptCount,
            'evidence_status' => self::text($topic['evidence_status'] ?? 'INSUFFICIENT_EVIDENCE'),
            'accuracy_basis' => self::text($topic['accuracy_basis'] ?? 'QUIZ_CONTEXT'),
            'repeated_mistakes' => $repeatMistakes,
            'trend' => $trend,
            'recent_change_points' => is_numeric($topic['recent_change_points'] ?? null)
                ? (int) $topic['recent_change_points']
                : null,
            'topic' => $name,
            'subject' => $action['subject'],
            'board' => $board,
            'let_level' => $letLevel,
            'let_specialization' => $letSpecialization,
            'civil_service_level' => $civilServiceLevel,
            'action' => StudyActionService::url($action),
            'label' => "Practice {$displayName}",
            'study_action' => $study['url'] ?? null,
            'study_label' => $study['title'] ?? null,
            'study_material_id' => $study['id'] ?? null,
        ];
    }

    public static function general(string $title, string $reason): array
    {
        $action = StudyActionService::quizForTopic('General', '');

        return [
            'type' => 'practice',
            'title' => $title,
            'reason' => $reason,
            'topic' => 'General',
            'subject' => $action['subject'],
            'action' => StudyActionService::url($action),
            'label' => 'Start a practice quiz',
        ];
    }

    private static function text(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }

    private static function studyLink(string $topic, string $board, array $repeatedQuestionIds = []): ?array
    {
        $contextKey = strtolower(trim($board));
        if (!array_key_exists($contextKey, self::$studyLinksByContext)) {
            $materials = StudyLibraryService::all($board !== '' ? $board : null);
            $questionsById = [];
            foreach (App::storage()->all('questions') as $question) {
                if (!is_array($question)) {
                    continue;
                }
                $id = self::text($question['id'] ?? '');
                $taxonomy = is_array($question['taxonomy'] ?? null) ? $question['taxonomy'] : [];
                $topicId = self::text($question['topic'] ?? $taxonomy['topic_id'] ?? '');
                if ($id !== '' && $topicId !== '') {
                    $questionsById[$id] = $topicId;
                }
            }

            $index = [];
            foreach ($materials as $material) {
                if (!is_array($material) || ($material['sources'] ?? []) === []) {
                    continue;
                }
                foreach ((array) ($material['question_links'] ?? []) as $link) {
                    if (!is_array($link)) {
                        continue;
                    }
                    $questionId = self::text($link['question_id'] ?? '');
                    $linkedTopic = $questionsById[$questionId] ?? '';
                    if ($linkedTopic === '') {
                        continue;
                    }
                    $topicKey = strtolower($linkedTopic);
                    $materialId = self::text($material['id'] ?? '');
                    if ($materialId === '') {
                        continue;
                    }
                    if (!isset($index[$topicKey][$materialId])) {
                        $index[$topicKey][$materialId] = [
                            'id' => $materialId,
                            'title' => self::text($material['title'] ?? 'Study material'),
                            'url' => '/study-library?material=' . rawurlencode($materialId),
                            'question_ids' => [],
                        ];
                    }
                    if (!in_array($questionId, $index[$topicKey][$materialId]['question_ids'], true)) {
                        $index[$topicKey][$materialId]['question_ids'][] = $questionId;
                    }
                }
            }
            self::$studyLinksByContext[$contextKey] = $index;
        }

        $topicLinks = self::$studyLinksByContext[$contextKey][strtolower($topic)] ?? [];
        if ($topicLinks === []) {
            return null;
        }
        $repeatedQuestionIds = array_fill_keys(array_map('strval', $repeatedQuestionIds), true);
        foreach ($topicLinks as $link) {
            foreach ($link['question_ids'] ?? [] as $questionId) {
                if (isset($repeatedQuestionIds[(string) $questionId])) {
                    unset($link['question_ids']);
                    return $link;
                }
            }
        }
        ksort($topicLinks);
        $link = reset($topicLinks) ?: null;
        if (is_array($link)) {
            unset($link['question_ids']);
        }
        return $link;
    }
}
