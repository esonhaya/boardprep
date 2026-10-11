<?php

declare(strict_types=1);

namespace App\Services\Learning;

final class StudyActionService
{
    public static function quizForTopic(
        string $topic,
        string $subject = "",
        string $mode = "practice",
        int $count = 5,
        string $difficulty = "mixed",
        string $board = "",
        string $letLevel = "",
        string $letSpecialization = "",
        string $civilServiceLevel = ""
    ): array {
        $session = StudySessionService::normalize([
            "topic" => $topic,
            "subject" => $subject,
            "mode" => $mode,
            "count" => $count,
            "difficulty" => $difficulty,
        ]);

        $action = [
            "action" => "start",
            "topic" => $session["topic"],
            "subject" => $session["subject"],
            "mode" => $session["mode"],
            "count" => $session["count"],
            "difficulty" => $session["difficulty"],
        ];
        self::appendBoardContext($action, [
            'board' => $board,
            'let_level' => $letLevel,
            'let_specialization' => $letSpecialization,
            'civil_service_level' => $civilServiceLevel,
        ]);

        return $action;
    }

    public static function query(array $spec): string
    {
        $session = StudySessionService::normalize($spec);
        $params = [
            "action" => "start",
            "subject" => $session["subject"],
            "topic" => $session["topic"],
            "mode" => $session["mode"],
            "count" => $session["count"],
            "difficulty" => $session["difficulty"],
        ];
        self::appendBoardContext($params, $spec);

        return http_build_query($params);
    }

    public static function url(array|string $input = []): string
    {
        if (is_string($input)) {
            return $input;
        }

        return "/quiz?" . self::query($input);
    }

    public static function create(array $input = []): array
    {
        $session = StudySessionService::normalize($input);

        $action = self::quizForTopic(
            $session["topic"],
            $session["subject"],
            $session["mode"],
            $session["count"],
            $session["difficulty"],
            self::text($input['board'] ?? $input['exam'] ?? ''),
            self::text($input['let_level'] ?? $input['let_variant']['level'] ?? ''),
            self::text($input['let_specialization_id']
                ?? $input['let_specialization']
                ?? $input['let_variant']['specialization_id']
                ?? ''),
            self::text($input['civil_service_level'] ?? '')
        );

        $action["url"] = self::url($action);

        return $action;
    }

    public static function isValid(array $action): bool
    {
        return
            ($action["action"] ?? "") === "start"
            && is_string($action["url"] ?? null)
            && str_starts_with($action["url"], "/quiz?");
    }

    private static function appendBoardContext(array &$params, array $context): void
    {
        $board = self::text($context['board'] ?? $context['exam'] ?? '');
        if ($board === '') {
            return;
        }

        $params['exam'] = $board;
        $normalizedBoard = strtolower($board);
        if (in_array($normalizedBoard, ['cse', 'civil-service'], true)) {
            $level = self::text($context['civil_service_level'] ?? '');
            if ($level !== '') {
                $params['civil_service_level'] = $level;
            }
        }
        if ($normalizedBoard === 'let') {
            $level = self::text($context['let_level'] ?? '');
            $specialization = self::text($context['let_specialization']
                ?? $context['let_specialization_id']
                ?? '');
            if ($level !== '') {
                $params['let_level'] = $level;
            }
            if ($specialization !== '') {
                $params['let_specialization'] = $specialization;
            }
        }
    }

    private static function text(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }
}
