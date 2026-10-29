<?php

declare(strict_types=1);

namespace App\Services\Learning;

/**
 * Selects a small set of dashboard actions from existing learner evidence.
 * It does not query history, the question bank, or create a second ranking model.
 */
final class SmartStudyHubService
{
    private const ACTION_LIMIT = 3;

    /** @return array{learner_state:string,readiness_state:string,actions:array<int,array<string,mixed>>} */
    public static function build(array $readiness, array $continuity): array
    {
        $learnerState = ($continuity['state'] ?? '') === 'RETURNING_LEARNER'
            ? 'RETURNING_LEARNER'
            : 'NO_HISTORY';
        $readinessState = is_scalar($readiness['status'] ?? null)
            ? (string) $readiness['status']
            : 'INSUFFICIENT_PRACTICE_DATA';
        $context = [
            'board' => self::text($readiness['board'] ?? ''),
            'variant' => self::text($readiness['variant_label'] ?? ''),
        ];
        $actions = [];
        $seen = [];

        if (($continuity['has_mistake_or_skipped_review'] ?? false) === true) {
            self::append($actions, $seen, [
                'type' => 'mistake_review',
                'title' => 'Review recorded mistakes',
                'reason' => 'Incorrect and skipped answers from completed attempts are available in your Mistake Notebook.',
                'label' => 'Open Mistake Notebook',
                'href' => '/mistake-notebook',
                'availability_state' => 'SAVED_HISTORY',
            ] + $context);
        }

        foreach ((array) ($readiness['recommendations'] ?? []) as $recommendation) {
            if (count($actions) >= self::ACTION_LIMIT || !is_array($recommendation)) {
                break;
            }
            $candidate = self::readinessAction($recommendation, $context);
            if ($candidate !== null) {
                self::append($actions, $seen, $candidate);
            }
        }

        foreach ([
            [
                'type' => 'study_library',
                'title' => 'Browse published study materials',
                'reason' => 'Explore lessons that are already published for BoardPrep learners.',
                'label' => 'Open Study Library',
                'href' => '/study-library',
                'availability_state' => 'PUBLISHED_LIBRARY',
            ],
            [
                'type' => 'flashcards',
                'title' => 'Explore Flashcards',
                'reason' => 'Choose an available board and subject to review eligible cards.',
                'label' => 'Browse Flashcards',
                'href' => '/flashcards',
                'availability_state' => 'BROWSE',
            ],
        ] as $fallback) {
            if (count($actions) >= self::ACTION_LIMIT) {
                break;
            }
            self::append($actions, $seen, $fallback + $context);
        }

        return [
            'learner_state' => $learnerState,
            'readiness_state' => $readinessState,
            'actions' => array_slice($actions, 0, self::ACTION_LIMIT),
        ];
    }

    private static function readinessAction(array $recommendation, array $context): ?array
    {
        $href = self::text($recommendation['action'] ?? '');
        $route = self::classifyReadinessRoute($href);
        if ($route === null) {
            return null;
        }
        $title = self::text($recommendation['title'] ?? '');
        $reason = self::text($recommendation['reason'] ?? '');
        $label = self::text($recommendation['label'] ?? '');
        if ($title === '' || $reason === '' || $label === '') {
            return null;
        }

        return [
            'type' => $route['type'],
            'title' => $title,
            'reason' => $reason,
            'label' => $label,
            'href' => $href,
            'availability_state' => $route['state'],
        ] + $context;
    }

    /** Only routes produced by the readiness service and understood by learners are accepted. */
    private static function classifyReadinessRoute(string $href): ?array
    {
        if ($href === '' || $href[0] !== '/' || str_starts_with($href, '//')) {
            return null;
        }
        $parts = parse_url($href);
        if (!is_array($parts)) {
            return null;
        }
        foreach (['scheme', 'host', 'user', 'pass', 'fragment'] as $forbiddenComponent) {
            if (array_key_exists($forbiddenComponent, $parts)) {
                return null;
            }
        }
        $path = $parts['path'] ?? '';
        $query = [];
        if (isset($parts['query'])) {
            parse_str((string) $parts['query'], $query);
        }

        if ($path === '/quiz') {
            if ($query === []) {
                return ['type' => 'exam_setup', 'state' => 'SETUP_REQUIRED'];
            }
            if (($query['action'] ?? '') !== 'start'
                || ($query['mode'] ?? '') !== 'practice'
                || self::text($query['subject'] ?? '') === ''
                || self::text($query['topic'] ?? '') === ''
                || !is_numeric($query['count'] ?? null)
                || (int) $query['count'] < 1
                || (int) $query['count'] > 100) {
                return null;
            }
            return ['type' => 'practice', 'state' => 'READINESS_ELIGIBLE_ROUTE'];
        }

        return match ($path) {
            '/exams' => $query === [] ? ['type' => 'exam_setup', 'state' => 'SETUP_REQUIRED'] : null,
            '/mistake-notebook' => $query === [] ? ['type' => 'mistake_review', 'state' => 'SAVED_HISTORY'] : null,
            '/study-library' => $query === [] ? ['type' => 'study_library', 'state' => 'PUBLISHED_LIBRARY'] : null,
            '/flashcards' => $query === [] ? ['type' => 'flashcards', 'state' => 'BROWSE'] : null,
            default => null,
        };
    }

    private static function append(array &$actions, array &$seen, array $candidate): void
    {
        $href = self::text($candidate['href'] ?? '');
        if ($href === '' || isset($seen[$href]) || count($actions) >= self::ACTION_LIMIT) {
            return;
        }
        $seen[$href] = true;
        $actions[] = $candidate;
    }

    private static function text(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }
}
