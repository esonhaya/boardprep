<?php

declare(strict_types=1);

use App\Core\App;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Repositories\QuestionRepository;
use App\Services\Question\QuestionEligibilityService;
use App\Services\Quiz\Start\QuizStartPreparationService;
use App\Services\Quiz\Start\QuizStartSessionWriter;
use App\Services\Quiz\Start\QuizStartViewModelFactory;
use App\Services\Profile\LearnerPreferenceService;

class QuizStartService
{
    public static function start(): void
    {
        $input = LearnerPreferenceService::applyToQuizInput(Request::all());
        $exam = strtolower(trim((string) ($input['exam'] ?? '')));
        if (in_array($exam, ['cse', 'civil-service'], true)) {
            $level = strtoupper(trim((string) ($input['civil_service_level'] ?? $input['civilServiceLevel'] ?? '')));
            if (!in_array($level, ['PROFESSIONAL', 'SUBPROFESSIONAL'], true)) {
                SessionService::flash('error', 'Select Professional or Subprofessional for a Civil Service quiz.');
                Response::redirect(self::settingsPath($input), 302);
                return;
            }
            if ($level === 'PROFESSIONAL' && in_array(strtolower(trim((string) ($input['subject'] ?? ''))), ['clerical-ability', 'clerical ability'], true)) {
                SessionService::flash('error', 'Clerical Ability is available only for the Subprofessional examination.');
                Response::redirect(self::settingsPath($input), 302);
                return;
            }
        }
        $questions = App::container()->get(QuestionRepository::class)->all();
        // Build the immutable eligibility index once per start request. The
        // selector may make several passes over the same bank; sharing this
        // map avoids a storage-backed eligibility lookup for every question
        // and keeps learner start latency bounded as the bank grows.
        $eligibilityIndex = QuestionEligibilityService::index();
        try {
            $preparation = QuizStartPreparationService::prepare($input, $questions, $eligibilityIndex);
        } catch (\InvalidArgumentException $exception) {
            SessionService::flash('error', $exception->getMessage());
            Response::redirect(self::settingsPath($input), 302);
            return;
        }

        if ($preparation->isEmpty()) {
            SessionService::flash(
                'error',
                'No questions matched those quiz settings. Your selected board and subject were kept. Try fewer questions or a broader topic within that subject.'
            );
            Response::redirect(self::settingsPath($input), 302);
            return;
        }

        QuizStartSessionWriter::write(
            $preparation->specification,
            $preparation->questions,
            $preparation->coverage,
            $preparation->issues
        );
        QuizNavigationService::reset();

        View::render(
            'quiz/index',
            QuizStartViewModelFactory::create(
                $preparation->specification,
                $preparation->questions
            )
        );
    }

    /** Preserve safe learner choices when a start request needs correction. */
    private static function settingsPath(array $input): string
    {
        $allowed = [
            'exam', 'subject', 'topic', 'difficulty', 'count', 'mode',
            'timer_mode', 'time_limit_minutes', 'let_level',
            'let_specialization', 'civil_service_level',
        ];
        $query = [];
        foreach ($allowed as $key) {
            $value = $input[$key] ?? null;
            if (!is_scalar($value)) {
                continue;
            }
            $value = trim((string) $value);
            if ($value !== '' && strlen($value) <= 160) {
                $query[$key] = $value;
            }
        }
        if (isset($input['adaptive'])) {
            $query['adaptive'] = '1';
        }

        return '/quiz' . ($query === [] ? '' : '?' . http_build_query($query));
    }
}
