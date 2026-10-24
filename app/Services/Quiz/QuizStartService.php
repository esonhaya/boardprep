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
use App\Services\Quiz\Start\QuizStartSpecificationFactory;
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

        self::startCandidates(
            $input,
            App::container()->get(QuestionRepository::class)->all(),
            QuestionEligibilityService::index(),
            [],
            self::settingsPath($input)
        );
    }

    /** Start through the existing quiz selector while keeping recovery inside a verified study pool. */
    public static function startFromQuestionPool(array $input, array $questions, array $studyContext): void
    {
        $returnUrl = is_scalar($studyContext['return_url'] ?? null)
            && (str_starts_with((string) $studyContext['return_url'], '/study-library?material=')
                || preg_match('#\A/my-notes\?note=note-[a-f0-9]{32}\z#', (string) $studyContext['return_url']) === 1
                || ((string) ($studyContext['return_url'] ?? '') === '/mistake-notebook'
                    && is_array($studyContext['mistake_recovery'] ?? null)))
            ? (string) $studyContext['return_url']
            : '/study-library';

        if (is_array($studyContext['notes_practice'] ?? null)) {
            self::startNotesQuestionPool($input, $questions, $studyContext, $returnUrl);
            return;
        }

        $exam = strtolower(trim((string) ($input['exam'] ?? '')));
        $level = strtoupper(trim((string) ($input['civil_service_level'] ?? '')));
        if (in_array($exam, ['cse', 'civil-service'], true)
            && !in_array($level, ['PROFESSIONAL', 'SUBPROFESSIONAL'], true)) {
            SessionService::flash('error', 'Choose Professional or Subprofessional for this Civil Service lesson practice set.');
            Response::redirect($returnUrl, 303);
            return;
        }
        if (in_array($exam, ['cse', 'civil-service'], true) && $level === 'PROFESSIONAL'
            && in_array(strtolower(trim((string) ($input['subject'] ?? ''))), ['clerical-ability', 'clerical ability'], true)) {
            SessionService::flash('error', 'Clerical Ability is available only for the Subprofessional examination.');
            Response::redirect($returnUrl, 303);
            return;
        }

        try {
            $eligibilityIndex = $studyContext['eligibility_index'] ?? null;
            if (!$eligibilityIndex instanceof \App\Services\Question\QuestionEligibilityIndex) {
                $eligibilityIndex = QuestionEligibilityService::index();
            }
            $specification = QuizStartSpecificationFactory::create($input);
            $selectionRequest = new \SelectionRequest(
                subject: $specification->subject,
                domain: $specification->domain,
                difficultyDistribution: [],
                questionCount: max(1, count($questions)),
                exam: $specification->board,
                letVariant: $specification->letVariant,
                civilServiceLevel: $specification->civilServiceLevel
            );
            $eligiblePool = \QuestionPoolFilter::filter($questions, $selectionRequest, $eligibilityIndex);
        } catch (\InvalidArgumentException $exception) {
            SessionService::flash('error', $exception->getMessage());
            Response::redirect($returnUrl, 303);
            return;
        }

        if ($eligiblePool === []) {
            SessionService::flash('error', 'No eligible questions are currently available in this lesson’s mapped practice pool. Continue reading or return to the subject materials.');
            Response::redirect($returnUrl, 303);
            return;
        }

        $input['exam'] = $specification->board;
        $input['subject'] = $specification->subject;
        $input['mode'] = 'practice';
        $requestedCount = is_numeric($input['count'] ?? null) ? (int) $input['count'] : 10;
        $input['count'] = min(max(1, $requestedCount), count($eligiblePool));
        self::startCandidates($input, $eligiblePool, $eligibilityIndex, $studyContext, $returnUrl);
    }

    /** Use a conservatively ranked note pool while retaining the production quiz session and scoring path. */
    private static function startNotesQuestionPool(array $input, array $questions, array $studyContext, string $returnUrl): void
    {
        $exam = strtolower(trim((string) ($input['exam'] ?? '')));
        $level = strtoupper(trim((string) ($input['civil_service_level'] ?? '')));
        if (in_array($exam, ['cse', 'civil-service'], true)
            && !in_array($level, ['PROFESSIONAL', 'SUBPROFESSIONAL'], true)) {
            SessionService::flash('error', 'Choose Professional or Subprofessional before practicing from these Civil Service notes.');
            Response::redirect($returnUrl . '&civil_service_level_required=1', 303);
            return;
        }

        try {
            $input['mode'] = 'practice';
            $input['subject'] = '*';
            $eligibilityIndex = QuestionEligibilityService::index();
            $specification = QuizStartSpecificationFactory::create($input);
            $request = new \SelectionRequest(
                subject: '*',
                domain: null,
                difficultyDistribution: [],
                questionCount: max(1, count($questions)),
                exam: $specification->board,
                letVariant: $specification->letVariant,
                civilServiceLevel: $specification->civilServiceLevel
            );
            $eligiblePool = \QuestionPoolFilter::filter($questions, $request, $eligibilityIndex);
        } catch (\InvalidArgumentException $exception) {
            SessionService::flash('error', $exception->getMessage());
            Response::redirect($returnUrl, 303);
            return;
        }

        if ($eligiblePool === []) {
            SessionService::flash('error', 'No sufficiently matched, eligible questions remain for this note. Try another note or use ordinary subject practice.');
            Response::redirect($returnUrl, 303);
            return;
        }

        $selected = array_slice($eligiblePool, 0, $specification->questionCount);
        foreach ($selected as $question) {
            $placement = QuestionEligibilityService::forExam($question, $specification->board, $eligibilityIndex);
            if (!is_array($placement) || trim((string) ($placement['subject_id'] ?? '')) === '') {
                SessionService::flash('error', 'A matched question no longer has a valid placement on this board. Please refresh the note match.');
                Response::redirect($returnUrl, 303);
                return;
            }
        }

        $preparation = new \App\Services\Quiz\Start\QuizStartPreparation(
            $specification,
            $selected,
            [],
            []
        );
        QuizStartSessionWriter::write(
            $preparation->specification,
            $preparation->questions,
            $preparation->coverage,
            $preparation->issues,
            $studyContext
        );
        \QuizNavigationService::reset();
        View::render('quiz/index', QuizStartViewModelFactory::create(
            $preparation->specification,
            $preparation->questions
        ));
    }

    private static function startCandidates(
        array $input,
        array $questions,
        ?\App\Services\Question\QuestionEligibilityIndex $eligibilityIndex,
        array $studyContext,
        string $redirectPath
    ): void {
        try {
            $preparation = QuizStartPreparationService::prepare($input, $questions, $eligibilityIndex);
        } catch (\InvalidArgumentException $exception) {
            SessionService::flash('error', $exception->getMessage());
            Response::redirect($redirectPath, 302);
            return;
        }

        if ($preparation->isEmpty()) {
            SessionService::flash(
                'error',
                'No questions matched those quiz settings. Your selected board and subject were kept. Try fewer questions or a broader topic within that subject.'
            );
            Response::redirect($redirectPath, 302);
            return;
        }

        QuizStartSessionWriter::write(
            $preparation->specification,
            $preparation->questions,
            $preparation->coverage,
            $preparation->issues,
            $studyContext
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
