<?php

declare(strict_types=1);

namespace App\Services\Quiz;

use App\Services\Quiz\ResultAction\QuizResultActionContext;

final class QuizResultActionService
{
    public static function build(array $session = [], array $summary = []): array
    {
        $context = QuizResultActionContext::fromSession($session);
        $params = [
            'action' => 'start',
            'subject' => $context['subject'],
            'mode' => $context['mode'],
            'count' => $context['count'],
            'difficulty' => $context['difficulty'],
        ];

        if ($context['topic'] !== '') {
            $params['topic'] = $context['topic'];
        }

        [$primaryLabel, $primaryReason] = self::primaryCopy((float) ($summary['percentage'] ?? 0));

        return [
            ['label' => $primaryLabel, 'reason' => $primaryReason, 'url' => '/quiz?' . http_build_query($params)],
            ['label' => 'Back to Study Dashboard', 'reason' => 'Use your study plan and recommendations for the next step.', 'url' => '/study'],
            ['label' => 'View Progress', 'reason' => 'See how this result changed your learning history.', 'url' => '/progress'],
        ];
    }

    private static function primaryCopy(float $percentage): array
    {
        if ($percentage < 60) {
            return ['Practice this again', 'Your score shows this area needs more practice.'];
        }
        if ($percentage < 80) {
            return ['Practice again', 'A short repeat session can help reinforce this material.'];
        }
        return ['Keep practicing', 'Reviewing this topic will help keep the skill strong.'];
    }
}
