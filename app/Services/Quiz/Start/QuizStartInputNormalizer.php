<?php

declare(strict_types=1);

namespace App\Services\Quiz\Start;

use App\Services\Quiz\LET\LETVariantRegistry;

final class QuizStartInputNormalizer
{
    public static function normalize(array $input): array
    {
        $topic = self::text($input['topic'] ?? '');
        $difficulty = strtolower(self::text($input['difficulty'] ?? 'mixed'));
        if (!in_array($difficulty, ['easy', 'medium', 'hard', 'mixed'], true)) {
            $difficulty = 'mixed';
        }
        $mode = self::text($input['mode'] ?? 'practice');
        if (!in_array($mode, ['practice', 'exam', 'review'], true)) {
            $mode = 'practice';
        }
        $timerMode = strtolower(self::text($input['timer_mode'] ?? 'untimed'));
        if (!in_array($timerMode, ['timed', 'untimed'], true)) {
            $timerMode = 'untimed';
        }
        $timeLimitMinutes = 0;
        if ($mode === 'exam' && $timerMode === 'timed') {
            $rawTimeLimit = $input['time_limit_minutes'] ?? 60;
            $numericTimeLimit = is_numeric($rawTimeLimit) ? (float) $rawTimeLimit : NAN;
            if (!is_finite($numericTimeLimit) || floor($numericTimeLimit) !== $numericTimeLimit
                || $numericTimeLimit < 1 || $numericTimeLimit > 600) {
                throw new \InvalidArgumentException('Choose a whole-number timer from 1 to 600 minutes.');
            }
            $timeLimitMinutes = (int) $numericTimeLimit;
        } else {
            $timerMode = 'untimed';
        }
        $defaultCount = $mode === 'exam' ? 150 : 10;
        $rawCount = $input['count'] ?? null;
        $numericCount = is_numeric($rawCount) ? (float) $rawCount : NAN;
        $count = is_finite($numericCount)
            ? (int) round($numericCount)
            : $defaultCount;

        $board = self::text($input['exam'] ?? 'LET') ?: 'LET';
        $letVariant = null;
        $civilServiceLevel = '';
        $level = '';
        $specialization = '';
        if (in_array(strtolower($board), ['cse', 'civil-service'], true)) {
            $civilServiceLevel = strtoupper(self::text($input['civil_service_level'] ?? $input['civilServiceLevel'] ?? ''));
            if ($civilServiceLevel !== '' && !in_array($civilServiceLevel, ['PROFESSIONAL', 'SUBPROFESSIONAL'], true)) {
                throw new \InvalidArgumentException('Civil Service level must be Professional or Subprofessional.');
            }
        }
        if (strtolower($board) === 'let') {
            $level = strtoupper(self::text($input['let_level'] ?? ''));
            $specialization = self::text($input['let_specialization'] ?? '');
            if ($level !== '' || $specialization !== '') {
                $validation = LETVariantRegistry::validateConfiguration([
                    'level' => $level,
                    'specialization_id' => $specialization,
                ]);
                if (($validation['valid'] ?? false) !== true) {
                    throw new \InvalidArgumentException(implode(' ', array_values($validation['errors'] ?? [])));
                }
                $letVariant = $validation['configuration'];
            }
        }
        $subject = self::text($input['subject'] ?? '');
        $subjectExplicit = array_key_exists('subject', $input) && $subject !== '';
        if ($subject === '') {
            $subject = '*';
        }
        if ($civilServiceLevel === 'PROFESSIONAL' && in_array(strtolower($subject), ['clerical-ability', 'clerical ability'], true)) {
            throw new \InvalidArgumentException('Clerical Ability is available only for the Subprofessional examination.');
        }

        return [
            'board' => $board,
            'subject' => $subject,
            'subject_explicit' => $subjectExplicit,
            'domain' => self::nullableText($input['domain'] ?? null),
            'topics' => $topic === '' ? [] : [$topic],
            'difficulty' => $difficulty,
            'count' => max(1, min($mode === 'exam' ? 150 : 20, $count)),
            'mode' => $mode,
            'timer_mode' => $timerMode,
            'time_limit_minutes' => $timeLimitMinutes,
            'adaptive' => isset($input['adaptive']),
            'shuffle' => true,
            'let_variant' => $letVariant,
            'let_level' => $letVariant['level'] ?? ($level !== '' ? $level : ''),
            'let_specialization' => $letVariant['specialization_id'] ?? $specialization,
            'civil_service_level' => $civilServiceLevel,
        ];
    }

    private static function text(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }

    private static function nullableText(mixed $value): ?string
    {
        $text = self::text($value);
        return $text === '' ? null : $text;
    }
}
