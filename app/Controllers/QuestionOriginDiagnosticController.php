<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\App;
use App\Core\Request;
use App\Services\Question\QuestionEligibilityService;
use App\Services\Study\StudyLibraryService;

/** Loopback-only diagnostics for questions in the current nonproduction quiz. */
final class QuestionOriginDiagnosticController
{
    public static function index(): never
    {
        $remote = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        if (App::config('environment') === 'production' || !in_array($remote, ['127.0.0.1', '::1'], true)) {
            http_response_code(404);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Not found.';
            exit;
        }
        $questionId = Request::queryString('id');
        $session = \SessionService::get('quiz_session', []);
        $questions = \SessionService::get('questions', []);
        $question = null;
        foreach (is_array($questions) ? $questions : [] as $candidate) {
            if (is_array($candidate) && (string) ($candidate['id'] ?? '') === $questionId) {
                $question = $candidate;
                break;
            }
        }
        if ($question === null || !is_array($session)) {
            http_response_code(404);
            self::json(['error' => 'Question is not part of the current quiz session.']);
        }
        $board = (string) ($session['board'] ?? $session['exam'] ?? '');
        $requestedSubject = (string) ($session['subject'] ?? '*');
        $eligibility = QuestionEligibilityService::forExam($question, $board);
        $taxonomy = is_array($question['taxonomy'] ?? null) ? $question['taxonomy'] : [];
        self::json([
            'question_id' => $questionId,
            'selected_board' => $board,
            'requested_subject' => $requestedSubject,
            'effective_eligible_subject' => (string) ($eligibility['subject_id'] ?? ''),
            'domain' => (string) ($taxonomy['domain_id'] ?? ''),
            'topic' => (string) ($taxonomy['topic_id'] ?? ''),
            'selection_path' => ($session['mode'] ?? 'practice') === 'exam'
                ? 'exam_blueprint > subject_allocation > eligibility-filtered selector'
                : 'practice_subject_request > eligibility-filtered selector',
            'fallback_path' => !empty($question['_selection_duplicate_content_fallback'])
                ? 'duplicate-content recovery after eligible distinct pool exhaustion'
                : 'none',
            'source_references' => StudyLibraryService::forQuestion($questionId),
            'diagnostic_scope' => 'current nonproduction quiz session only',
        ]);
    }

    private static function json(array $value): never
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }
}
