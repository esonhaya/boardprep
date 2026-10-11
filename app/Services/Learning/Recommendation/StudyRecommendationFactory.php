<?php

declare(strict_types=1);

namespace App\Services\Learning\Recommendation;

use App\Services\Learning\StudyActionService;
use App\Services\Shared\TaxonomyStorageService;

final class StudyRecommendationFactory
{
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

        return [
            'type' => 'topic',
            'title' => "Review {$displayName}" . ($contextLabels !== [] ? ' · ' . implode(' · ', $contextLabels) : ''),
            'reason' => 'Current average: ' . (int) ($topic['average'] ?? 0) . '%',
            'topic' => $name,
            'subject' => $action['subject'],
            'board' => $board,
            'let_level' => $letLevel,
            'let_specialization' => $letSpecialization,
            'civil_service_level' => $civilServiceLevel,
            'action' => StudyActionService::url($action),
            'label' => "Practice {$displayName}",
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
}
