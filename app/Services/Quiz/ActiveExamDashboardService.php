<?php

declare(strict_types=1);

namespace App\Services\Quiz;

use App\Services\Shared\TaxonomyStorageService;

/** Minimal continuation summary for the active exam in this browser session. */
final class ActiveExamDashboardService
{
    public static function current(): ?array
    {
        if (\SessionService::get('attempt_persisted') === true
            || \SessionService::get('quiz_completed', false) === true) {
            return null;
        }

        $session = \SessionService::get('quiz_session', []);
        $questions = \SessionService::get('questions', []);
        if (!is_array($session) || ($session['mode'] ?? \SessionService::get('mode')) !== 'exam'
            || !is_scalar($session['id'] ?? null)
            || preg_match('/\Aquiz-[a-f0-9]{16}\z/', (string) $session['id']) !== 1
            || !is_array($questions) || $questions === []) {
            return null;
        }

        foreach ($questions as $question) {
            if (!is_array($question) || !is_scalar($question['id'] ?? null)
                || trim((string) $question['id']) === '') {
                return null;
            }
        }

        $answers = \SessionService::get('answers', []);
        $answers = is_array($answers) ? $answers : [];
        $answered = 0;
        foreach ($questions as $question) {
            $id = trim((string) $question['id']);
            if (is_scalar($answers[$id] ?? null)) $answered++;
        }

        $current = \SessionService::get('currentQuestion', 0);
        $current = is_int($current) || (is_string($current) && preg_match('/\A\d{1,9}\z/', $current) === 1)
            ? (int) $current
            : 0;
        $current = min(max(0, $current), count($questions) - 1);
        $timed = ($session['timer_mode'] ?? '') === 'timed';
        $deadline = is_int($session['deadline_epoch'] ?? null) || is_numeric($session['deadline_epoch'] ?? null)
            ? (int) $session['deadline_epoch']
            : 0;
        $expired = $timed && ($deadline <= 0 || $deadline <= time());
        $board = is_scalar($session['board'] ?? $session['exam'] ?? null)
            ? trim((string) ($session['board'] ?? $session['exam']))
            : '';
        $subject = is_scalar($session['subject'] ?? null) ? trim((string) $session['subject']) : '';
        $boardLabel = TaxonomyStorageService::label('boards', $board);
        $subjectLabel = $subject === '*'
            ? 'All available subjects'
            : TaxonomyStorageService::label('subjects', $subject);
        $variantParts = [];
        if (strtolower($board) === 'let') {
            $level = is_scalar($session['let_level'] ?? null) ? trim((string) $session['let_level']) : '';
            $specialization = is_scalar($session['let_specialization_id'] ?? null) ? trim((string) $session['let_specialization_id']) : '';
            if ($level !== '') $variantParts[] = ucwords(strtolower(str_replace(['_', '-'], ' ', $level)));
            if ($specialization !== '') {
                $label = TaxonomyStorageService::label('let-specializations', $specialization);
                if ($label !== '') $variantParts[] = $label;
            }
        } elseif (in_array(strtolower($board), ['cse', 'civil-service'], true)) {
            $level = is_scalar($session['civil_service_level'] ?? null) ? trim((string) $session['civil_service_level']) : '';
            if ($level !== '') $variantParts[] = ucwords(strtolower(str_replace(['_', '-'], ' ', $level)));
        }

        return [
            'state' => $expired ? 'EXPIRED_SESSION' : 'ACTIVE_SESSION',
            'board' => $boardLabel !== '' ? $boardLabel : $board,
            'subject' => $subjectLabel !== '' ? $subjectLabel : $subject,
            'variant' => implode(' · ', $variantParts),
            'current_question' => $current + 1,
            'question_count' => count($questions),
            'answered_count' => $answered,
            'continue_url' => '/quiz',
        ];
    }
}
