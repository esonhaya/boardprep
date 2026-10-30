<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Repositories\AttemptRepository;
use App\Services\Learning\LearnerIdentityService;
use App\Services\Learning\LearnerNotesService;
use App\Services\Learning\MistakeNotebookService;
use App\Services\Learning\MistakePracticePoolService;
use App\Services\Question\QuestionEligibilityService;
use App\Services\Shared\CsrfTokenService;
use App\Services\Shared\TaxonomyStorageService;
use App\Services\Study\StudyLibraryService;
use InvalidArgumentException;

final class MistakeNotebookController
{
    public static function index(): void
    {
        $attempts = self::attempts();
        $all = MistakeNotebookService::summaries($attempts);
        $allSkipped = MistakeNotebookService::skipped($attempts, $all);
        $board = self::validFilter(MistakeNotebookService::boardId(Request::queryString('board')), $all, 'board');
        $subject = self::validFilter(MistakeNotebookService::taxonomyId('subjects', Request::queryString('subject')), $all, 'subject', $board);
        $topic = self::validFilter(MistakeNotebookService::taxonomyId('topics', Request::queryString('topic')), $all, 'topic', $board, $subject);
        $filters = [
            'board' => $board,
            'subject' => $subject,
            'topic' => $topic,
            'recent' => Request::queryString('recent') === '1' ? '1' : '',
            'repeated' => Request::queryString('repeated') === '1' ? '1' : '',
            'reviewed' => Request::queryString('reviewed') === '1' ? '1' : '',
            'corrected' => Request::queryString('corrected') === '1' ? '1' : '',
        ];
        $items = MistakeNotebookService::filtered($all, $filters);
        $topicCounts = [];
        foreach ($items as $item) {
            $topicId = (string) ($item['topic_id'] ?? '');
            $boardId = (string) ($item['board'] ?? '');
            $subjectId = (string) ($item['subject'] ?? '');
            $topicKey = strtolower($boardId) . "\0" . strtolower($subjectId) . "\0" . ($topicId !== '' ? $topicId : '__unclassified__');
            $topicCounts[$topicKey] ??= [
                'id' => $topicId,
                'board' => $boardId,
                'subject' => $subjectId,
                'topic' => $topicId !== '' ? TaxonomyStorageService::label('topics', $topicId) : 'Topic not recorded',
                'name' => TaxonomyStorageService::label('boards', $boardId) . ' · '
                    . TaxonomyStorageService::label('subjects', $subjectId) . ' · '
                    . ($topicId !== '' ? TaxonomyStorageService::label('topics', $topicId) : 'Topic not recorded'),
                'questions' => 0,
                'incorrect_attempts' => 0,
            ];
            $topicCounts[$topicKey]['questions']++;
            $topicCounts[$topicKey]['incorrect_attempts'] += (int) ($item['wrong_count'] ?? 0);
        }
        $topicSummary = array_values($topicCounts);
        usort($topicSummary, static fn(array $a, array $b): int => ($b['incorrect_attempts'] <=> $a['incorrect_attempts'])
            ?: ($b['questions'] <=> $a['questions']) ?: strcmp($a['name'], $b['name']));
        $allSkipped = array_values(array_filter($allSkipped, static function (array $item) use ($board, $subject, $topic): bool {
            return ($board === '' || (string) ($item['board'] ?? '') === $board)
                && ($subject === '' || (string) ($item['subject'] ?? '') === $subject)
                && ($topic === '' || (string) ($item['topic_id'] ?? '') === $topic);
        }));
        $skippedTotal = count($allSkipped);
        $skippedPageSize = 20;
        $skippedPages = max(1, (int) ceil($skippedTotal / $skippedPageSize));
        $rawSkippedPage = Request::queryString('skipped_page', '1');
        $requestedSkippedPage = preg_match('/\A[1-9]\d{0,5}\z/', $rawSkippedPage) === 1 ? (int) $rawSkippedPage : 1;
        $skippedPage = min($requestedSkippedPage, $skippedPages);
        $skipped = array_slice($allSkipped, ($skippedPage - 1) * $skippedPageSize, $skippedPageSize);
        $pageSize = 25;
        $page = max(1, min(10000, (int) Request::queryString('page', '1')));
        $pages = max(1, (int) ceil(count($items) / $pageSize));
        $page = min($page, $pages);
        $pagedItems = array_slice($items, ($page - 1) * $pageSize, $pageSize);
        $boards = self::boardOptions($all);
        $subjects = self::subjectOptions($all, $board);
        View::render('learning/mistake-notebook', [
            'pageTitle' => 'Mistake Notebook | BoardPrep',
            'items' => $pagedItems,
            'topicSummary' => array_slice($topicSummary, 0, 8),
            'totalMistakes' => count($all),
            'filteredCount' => count($items),
            'skipped' => array_slice($skipped, 0, 20),
            'filters' => $filters,
            'boards' => $boards,
            'subjects' => $subjects,
            'topics' => self::topicOptions($all, $board, $subject),
            'page' => $page,
            'pages' => $pages,
            'skippedTotal' => $skippedTotal,
            'skippedPage' => $skippedPage,
            'skippedPages' => $skippedPages,
            'skippedPageSize' => $skippedPageSize,
            'csrfToken' => CsrfTokenService::token(),
            'civilServiceLevels' => self::civilServiceLevels($attempts, $board),
            'letVariantOptions' => self::letVariantOptions($all, $allSkipped, $board, $subject, $topic),
        ]);
    }

    public static function review(): void
    {
        $attempts = self::attempts();
        $attemptId = Request::queryString('attempt_id');
        $questionId = Request::queryString('question_id');
        $detail = MistakeNotebookService::detail($attempts, $attemptId, $questionId);
        if ($detail === null) {
            \SessionService::flash('error', 'That mistake review is unavailable in this learner history.');
            Response::redirect('/mistake-notebook', 303);
            return;
        }
        $item = $detail['item'];
        $question = is_array($item['question'] ?? null) ? $item['question'] : [];
        $noteContext = is_array($detail['attempt']['notes_practice'] ?? null) ? $detail['attempt']['notes_practice'] : [];
        $noteLink = is_array($noteContext['question_notes'][$questionId] ?? null)
            ? $noteContext['question_notes'][$questionId]
            : null;
        $noteId = is_scalar($noteContext['note_id'] ?? null) ? (string) $noteContext['note_id'] : '';
        $savedNote = $noteId !== '' ? (new LearnerNotesService())->find($noteId) : null;
        $returnTo = '/mistake-notebook/review?' . http_build_query([
            'attempt_id' => $attemptId,
            'question_id' => $questionId,
        ]);
        $references = StudyLibraryService::forQuestion($questionId, $detail['board'], $detail['subject']);
        $allMistakes = MistakeNotebookService::summaries($attempts);
        $allSkipped = MistakeNotebookService::skipped($attempts, $allMistakes);
        View::render('learning/mistake-review', [
            'pageTitle' => 'Review a Mistake | BoardPrep',
            'detail' => $detail,
            'question' => $question,
            'noteContext' => $noteContext,
            'noteLink' => $noteLink,
            'savedNote' => $savedNote,
            'returnTo' => $returnTo,
            'references' => $references,
            'csrfToken' => CsrfTokenService::token(),
            'civilServiceLevels' => self::civilServiceLevels($attempts, (string) $detail['board']),
            'letVariantOptions' => self::letVariantOptions(
                $allMistakes,
                $allSkipped,
                (string) $detail['board'],
                (string) $detail['subject'],
                (string) ($question['taxonomy']['topic_id'] ?? $question['topic'] ?? ''),
                $questionId
            ),
            'selectedLetVariantKey' => (string) ($detail['variant']['let_variant_key'] ?? ''),
        ]);
    }

    public static function practice(): void
    {
        if (!CsrfTokenService::valid(Request::input('csrf_token'))) {
            \SessionService::flash('error', 'Your practice form expired. Reload the Mistake Notebook and try again.');
            Response::redirect('/mistake-notebook', 303);
            return;
        }

        $attempts = self::attempts();
        $mistakes = MistakeNotebookService::summaries($attempts);
        $board = MistakeNotebookService::boardId(Request::input('board'));
        $subjectInput = is_scalar(Request::input('subject')) ? trim((string) Request::input('subject')) : '';
        $subject = $subjectInput === '*' ? '' : MistakeNotebookService::taxonomyId('subjects', $subjectInput);
        $topic = MistakeNotebookService::taxonomyId('topics', Request::input('topic'));
        $mode = is_scalar(Request::input('practice_type')) ? trim((string) Request::input('practice_type')) : '';
        $focusQuestionId = is_scalar(Request::input('focus_question_id')) ? trim((string) Request::input('focus_question_id')) : '';
        $level = strtoupper(is_scalar(Request::input('civil_service_level')) ? trim((string) Request::input('civil_service_level')) : '');

        $letVariantKey = null;
        $letVariant = null;
        $skipped = MistakeNotebookService::skipped($attempts, $mistakes);
        if (strtolower($board) === 'let') {
            $options = self::letVariantOptions($mistakes, $skipped, $board, $subject, $topic, $focusQuestionId);
            $requestedVariantKey = is_scalar(Request::input('let_variant_key'))
                ? trim((string) Request::input('let_variant_key'))
                : '';
            if ($requestedVariantKey === '' && count($options) === 1) {
                $requestedVariantKey = (string) $options[0]['key'];
            }
            foreach ($options as $option) {
                if ((string) ($option['key'] ?? '') !== $requestedVariantKey) continue;
                $letVariantKey = (string) $option['key'];
                $letVariant = is_array($option['configuration'] ?? null) ? $option['configuration'] : null;
                break;
            }
            if ($letVariantKey === null) {
                self::redirectWithError('Choose a recorded LET practice context before starting recovery. Older attempts without a saved variant remain labeled unscoped.');
                return;
            }
        }

        $scopeHasMistakes = false;
        $focusValid = $focusQuestionId === '';
        foreach ($mistakes as $mistake) {
            if ((string) $mistake['board'] !== $board
                || ($subject !== '' && (string) $mistake['subject'] !== $subject)
                || ($topic !== '' && (string) $mistake['topic_id'] !== $topic)) continue;
            foreach (is_array($mistake['events'] ?? null) ? $mistake['events'] : [] as $event) {
                if (!is_array($event) || ($event['answered'] ?? false) !== true || ($event['correct'] ?? true) !== false
                    || !self::eventMatchesPracticeVariant($event, $board, $level, $letVariantKey)) continue;
                $scopeHasMistakes = true;
                if ($focusQuestionId !== '' && (string) $mistake['question_id'] === $focusQuestionId) $focusValid = true;
            }
        }
        foreach ($skipped as $item) {
            if ((string) ($item['board'] ?? '') !== $board
                || ($subject !== '' && (string) ($item['subject'] ?? '') !== $subject)
                || ($topic !== '' && (string) ($item['topic_id'] ?? '') !== $topic)
                || !self::eventMatchesPracticeVariant($item, $board, $level, $letVariantKey)) continue;
            $scopeHasMistakes = true;
            if ($focusQuestionId !== '' && (string) ($item['question_id'] ?? '') === $focusQuestionId) $focusValid = true;
        }
        if ($board === '' || !$scopeHasMistakes || !$focusValid || !in_array($mode, ['retry', 'related'], true)) {
            self::redirectWithError('Choose a board and focus that contains your own recorded mistakes.');
            return;
        }
        $isCse = in_array(strtolower($board), ['cse', 'civil-service'], true);
        if ($isCse && !in_array($level, ['PROFESSIONAL', 'SUBPROFESSIONAL'], true)) {
            self::redirectWithError('Choose a Civil Service level before starting recovery practice.');
            return;
        }

        $poolService = new MistakePracticePoolService();
        $pool = $mode === 'retry'
            ? $poolService->retry($attempts, $board, $subject, $isCse ? $level : null, $focusQuestionId, $topic, $mistakes, $letVariantKey, $letVariant)
            : $poolService->related($attempts, $board, $subject, $isCse ? $level : null, $focusQuestionId, $topic, 20, $mistakes, $letVariantKey, $letVariant);
        $questions = $pool['questions'];
        if ($questions === []) {
            self::redirectWithError($mode === 'retry'
                ? 'There are no currently eligible incorrect or skipped questions to retry in this board and subject. The original history remains available for review.'
                : 'No other eligible questions share a recorded topic or concept with these mistakes. No unrelated questions were added.');
            return;
        }

        $rawCount = Request::input('count');
        $requested = is_scalar($rawCount) && ctype_digit((string) $rawCount) ? (int) $rawCount : 0;
        if (!in_array($requested, [5, 10, 15, 20], true)) {
            self::redirectWithError('Choose a session size of 5, 10, 15, or 20 questions.');
            return;
        }
        $count = min($requested, count($questions));
        $sourceIds = array_values(array_map('strval', $pool['source_question_ids']));
        $input = [
            'exam' => $board,
            'subject' => $subject !== '' ? $subject : '*',
            'mode' => 'practice',
            'count' => $count,
        ];
        if ($isCse) $input['civil_service_level'] = $level;
        if ($letVariant !== null) {
            $input['let_level'] = (string) ($letVariant['level'] ?? '');
            $input['let_specialization'] = (string) ($letVariant['specialization_id'] ?? '');
        }
        $focus = $focusQuestionId !== '' ? $focusQuestionId : '';
        \QuizStartService::startFromQuestionPool($input, $questions, [
            'return_url' => '/mistake-notebook',
            'eligibility_index' => $pool['eligibility_index'],
            'mistake_recovery' => [
                'mode' => $mode === 'retry' ? 'retry_missed' : 'related_topics',
                'board' => $board,
                'subject' => $subject,
                'topic_id' => $topic,
                'civil_service_level' => $isCse ? $level : '',
                'let_variant_key' => $letVariantKey ?? '',
                'let_level' => $letVariant['level'] ?? '',
                'let_specialization' => $letVariant['specialization_id'] ?? '',
                'focus_question_id' => $focus,
                'source_question_ids' => $sourceIds,
            ],
        ]);
    }

    private static function attempts(): array
    {
        return \App\Core\App::container()->get(AttemptRepository::class)
            ->byUser(LearnerIdentityService::current());
    }

    private static function boardOptions(array $items): array
    {
        $ids = [];
        foreach ($items as $item) if (($item['board'] ?? '') !== '') $ids[(string) $item['board']] = true;
        return array_values(array_filter(TaxonomyStorageService::boards(), static fn(mixed $board): bool => is_array($board)
            && isset($ids[(string) ($board['id'] ?? '')])));
    }

    private static function subjectOptions(array $items, string $board): array
    {
        $ids = [];
        foreach ($items as $item) {
            if (($board === '' || (string) ($item['board'] ?? '') === $board) && ($item['subject'] ?? '') !== '') {
                $ids[(string) $item['subject']] = true;
            }
        }
        return array_values(array_filter(TaxonomyStorageService::subjects(), static fn(mixed $subject): bool => is_array($subject)
            && isset($ids[(string) ($subject['id'] ?? '')])));
    }

    private static function topicOptions(array $items, string $board, string $subject): array
    {
        $ids = [];
        foreach ($items as $item) {
            if (($board === '' || (string) ($item['board'] ?? '') === $board)
                && ($subject === '' || (string) ($item['subject'] ?? '') === $subject)
                && ($item['topic_id'] ?? '') !== '') {
                $ids[(string) $item['topic_id']] = true;
            }
        }
        return array_values(array_filter(TaxonomyStorageService::topics(), static fn(mixed $topic): bool => is_array($topic)
            && isset($ids[(string) ($topic['id'] ?? '')])));
    }

    private static function validFilter(string $value, array $items, string $field, string $board = '', string $subject = ''): string
    {
        if ($value === '') return '';
        foreach ($items as $item) {
            $actual = $field === 'topic' ? ($item['topic_id'] ?? '') : ($item[$field] ?? '');
            if ($actual === $value
                && ($board === '' || (string) ($item['board'] ?? '') === $board)
                && ($subject === '' || (string) ($item['subject'] ?? '') === $subject)) return $value;
        }
        return '';
    }

    private static function civilServiceLevels(array $attempts, string $board): array
    {
        if (!in_array(strtolower($board), ['cse', 'civil-service'], true)) return [];
        $levels = [];
        foreach ($attempts as $attempt) {
            if (MistakeNotebookService::boardId($attempt['board'] ?? '') !== $board) continue;
            $level = strtoupper(is_scalar($attempt['civil_service_level'] ?? null) ? trim((string) $attempt['civil_service_level']) : '');
            if (in_array($level, ['PROFESSIONAL', 'SUBPROFESSIONAL'], true)) $levels[$level] = true;
        }
        return array_keys($levels);
    }

    private static function letVariantOptions(
        array $mistakes,
        array $skipped,
        string $board,
        string $subject = '',
        string $topic = '',
        string $focusQuestionId = ''
    ): array {
        if (strtolower($board) !== 'let') return [];
        $options = [];
        foreach ($mistakes as $mistake) {
            if ((string) ($mistake['board'] ?? '') !== $board
                || ($subject !== '' && (string) ($mistake['subject'] ?? '') !== $subject)
                || ($topic !== '' && (string) ($mistake['topic_id'] ?? '') !== $topic)
                || ($focusQuestionId !== '' && (string) ($mistake['question_id'] ?? '') !== $focusQuestionId)) continue;
            foreach (is_array($mistake['events'] ?? null) ? $mistake['events'] : [] as $event) {
                if (!is_array($event) || ($event['answered'] ?? false) !== true || ($event['correct'] ?? true) !== false) continue;
                self::addLetVariantOption($options, $event);
            }
        }
        foreach ($skipped as $item) {
            if ((string) ($item['board'] ?? '') !== $board
                || ($subject !== '' && (string) ($item['subject'] ?? '') !== $subject)
                || ($topic !== '' && (string) ($item['topic_id'] ?? '') !== $topic)
                || ($focusQuestionId !== '' && (string) ($item['question_id'] ?? '') !== $focusQuestionId)) continue;
            self::addLetVariantOption($options, $item);
        }
        uasort($options, static fn(array $left, array $right): int => strcasecmp((string) $left['label'], (string) $right['label']));
        return array_values($options);
    }

    private static function addLetVariantOption(array &$options, array $event): void
    {
        $key = is_scalar($event['let_variant_key'] ?? null) ? (string) $event['let_variant_key'] : 'UNAVAILABLE';
        if ($key === 'UNAVAILABLE' || isset($options[$key])) return;
        $level = is_scalar($event['let_level'] ?? null) ? (string) $event['let_level'] : '';
        $specialization = is_scalar($event['let_specialization'] ?? null) ? (string) $event['let_specialization'] : '';
        $options[$key] = [
            'key' => $key,
            'label' => is_scalar($event['let_variant_label'] ?? null) ? (string) $event['let_variant_label'] : 'LET variant not recorded',
            'configuration' => $key === 'UNSCOPED' ? null : ['level' => $level, 'specialization_id' => $specialization],
        ];
    }

    private static function eventMatchesPracticeVariant(array $event, string $board, string $civilServiceLevel, ?string $letVariantKey): bool
    {
        if (strtolower($board) === 'let' && $letVariantKey !== null
            && (string) ($event['let_variant_key'] ?? 'UNSCOPED') !== $letVariantKey) return false;
        if (in_array(strtolower($board), ['cse', 'civil-service'], true) && $civilServiceLevel !== '') {
            $recordedLevel = strtoupper(is_scalar($event['civil_service_level'] ?? null) ? (string) $event['civil_service_level'] : '');
            if ($recordedLevel !== '' && $recordedLevel !== $civilServiceLevel) return false;
        }
        return true;
    }

    private static function redirectWithError(string $message): void
    {
        \SessionService::flash('error', $message);
        Response::redirect('/mistake-notebook', 303);
    }
}
