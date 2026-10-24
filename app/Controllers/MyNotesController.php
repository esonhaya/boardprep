<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Services\Learning\LearnerNotesService;
use App\Services\Learning\NotesQuestionMatcherService;
use App\Services\Shared\CsrfTokenService;
use App\Services\Shared\TaxonomyStorageService;
use InvalidArgumentException;
use Throwable;

final class MyNotesController
{
    public static function index(): void
    {
        $notesService = new LearnerNotesService();
        $noteId = Request::queryString('note');
        $selected = $noteId !== '' ? $notesService->find($noteId) : null;
        $notFound = $noteId !== '' && !is_array($selected);
        $match = null;
        $civilServiceLevel = strtoupper(Request::queryString('civil_service_level'));
        if (is_array($selected)) {
            $board = self::board((string) ($selected['board_id'] ?? ''));
            if (self::isCivilService($board)) {
                if (in_array($civilServiceLevel, ['PROFESSIONAL', 'SUBPROFESSIONAL'], true)) {
                    $match = (new NotesQuestionMatcherService())->match((string) $selected['board_id'], (string) $selected['body'], $civilServiceLevel);
                }
            } else {
                $match = (new NotesQuestionMatcherService())->match((string) $selected['board_id'], (string) $selected['body']);
            }
        }
        self::render([
            'notes' => $notesService->all(),
            'selectedNote' => $selected,
            'notFound' => $notFound,
            'match' => $match,
            'civilServiceLevel' => $civilServiceLevel,
            'formInput' => [],
            'formError' => '',
            'returnTo' => self::safeReturnTo(Request::queryString('return_to')),
        ]);
    }

    public static function save(): void
    {
        if (!self::validCsrf()) return;
        try {
            $note = (new LearnerNotesService())->save(is_array(Request::input()) ? Request::input() : []);
            \SessionService::flash('success', 'Your note was saved privately.');
            $returnTo = self::safeReturnTo(Request::input('return_to'));
            Response::redirect('/my-notes?note=' . rawurlencode((string) $note['id'])
                . ($returnTo !== '' ? '&return_to=' . rawurlencode($returnTo) : ''), 303);
        } catch (InvalidArgumentException $exception) {
            self::render([
                'notes' => (new LearnerNotesService())->all(),
                'selectedNote' => null,
                'notFound' => false,
                'match' => null,
                'civilServiceLevel' => '',
                'formInput' => is_array(Request::input()) ? Request::input() : [],
                'formError' => $exception->getMessage(),
                'returnTo' => self::safeReturnTo(Request::input('return_to')),
            ]);
        } catch (Throwable) {
            self::render([
                'notes' => (new LearnerNotesService())->all(),
                'selectedNote' => null,
                'notFound' => false,
                'match' => null,
                'civilServiceLevel' => '',
                'formInput' => is_array(Request::input()) ? Request::input() : [],
                'formError' => 'The note could not be saved right now. Your submitted text was not changed in canonical question data. Please try again.',
                'returnTo' => self::safeReturnTo(Request::input('return_to')),
            ]);
        }
    }

    public static function delete(): void
    {
        if (!self::validCsrf()) return;
        if (Request::input('confirm_delete') !== 'yes') {
            \SessionService::flash('error', 'Confirm that you want to delete this note.');
            Response::redirect('/my-notes', 303);
        }
        $noteId = is_scalar(Request::input('note_id')) ? trim((string) Request::input('note_id')) : '';
        $deleted = (new LearnerNotesService())->delete($noteId);
        \SessionService::flash($deleted ? 'success' : 'error', $deleted
            ? 'The note was deleted.'
            : 'That note is unavailable in this learner session.');
        Response::redirect('/my-notes', 303);
    }

    public static function practice(): void
    {
        if (!self::validCsrf()) return;
        $service = new LearnerNotesService();
        $noteId = is_scalar(Request::input('note_id')) ? trim((string) Request::input('note_id')) : '';
        $note = $service->find($noteId);
        if (!is_array($note)) {
            \SessionService::flash('error', 'That note is unavailable in this learner session. Reopen one of your saved notes and try again.');
            Response::redirect('/my-notes', 303);
        }
        $board = self::board((string) ($note['board_id'] ?? ''));
        $level = strtoupper(is_scalar(Request::input('civil_service_level')) ? trim((string) Request::input('civil_service_level')) : '');
        if (self::isCivilService($board) && !in_array($level, ['PROFESSIONAL', 'SUBPROFESSIONAL'], true)) {
            \SessionService::flash('error', 'Choose a Civil Service level before starting practice from these notes.');
            Response::redirect('/my-notes?note=' . rawurlencode($noteId), 303);
        }

        $matched = (new NotesQuestionMatcherService())->matchForPractice(
            (string) $note['board_id'],
            (string) $note['body'],
            $level !== '' ? $level : null
        );
        $match = $matched['match'];
        $available = count($match['candidates']);
        if ($available === 0) {
            \SessionService::flash('error', 'These notes did not match enough eligible questions for a focused session. Try a clearer topic or use ordinary subject practice.');
            Response::redirect('/my-notes?note=' . rawurlencode($noteId), 303);
        }
        $rawCount = Request::input('count');
        $count = is_numeric($rawCount) ? (int) $rawCount : 0;
        $allowedCounts = [5, 10, 15, 20, min(20, $available)];
        if ($count < 1 || $count > 20 || !in_array($count, $allowedCounts, true)) {
            \SessionService::flash('error', 'Choose one of the available session sizes for these note matches.');
            Response::redirect('/my-notes?note=' . rawurlencode($noteId), 303);
        }

        $selected = array_slice($match['candidates'], 0, min($count, $available));
        $questions = [];
        $questionNotes = [];
        foreach ($selected as $candidate) {
            if (!is_array($candidate['question'] ?? null)) continue;
            $question = $candidate['question'];
            $questionId = (string) ($candidate['question_id'] ?? $question['id'] ?? '');
            if ($questionId === '') continue;
            $questions[] = $question;
            $questionNotes[$questionId] = [
                'topic' => (string) ($candidate['topic'] ?? ''),
                'excerpt' => (string) ($candidate['note_excerpt'] ?? ''),
                'possible_answer_discrepancy' => ($candidate['possible_answer_discrepancy'] ?? false) === true,
            ];
        }
        if ($questions === []) {
            \SessionService::flash('error', 'The matched questions are no longer available. Refresh the note and try again.');
            Response::redirect('/my-notes?note=' . rawurlencode($noteId), 303);
        }

        $input = [
            'exam' => (string) $note['board_id'],
            'subject' => '*',
            'mode' => 'practice',
            'count' => min($count, count($questions)),
        ];
        if ($level !== '') $input['civil_service_level'] = $level;
        \QuizStartService::startFromQuestionPool($input, $questions, [
            'return_url' => '/my-notes?note=' . rawurlencode($noteId),
            'eligibility_index' => $matched['eligibility_index'],
            'notes_practice' => [
                'note_id' => $noteId,
                'note_title' => (string) $note['title'],
                'topics' => $match['matched_topics'],
                'limited_topics' => $match['limited_topics'],
                'uncovered_topics' => $match['uncovered_topics'],
                'question_notes' => $questionNotes,
            ],
        ]);
    }

    private static function render(array $state): void
    {
        $boards = array_values(array_filter(TaxonomyStorageService::boards(), static fn(mixed $board): bool => is_array($board)
            && trim((string) ($board['id'] ?? '')) !== ''
            && !in_array(strtolower((string) ($board['status'] ?? 'active')), ['archived', 'inactive'], true)));
        View::render('learning/my-notes', $state + [
            'pageTitle' => 'My Notes | BoardPrep',
            'boards' => $boards,
            'csrfToken' => CsrfTokenService::token(),
            'maxTitleCharacters' => LearnerNotesService::MAX_TITLE_CHARACTERS,
            'maxBodyCharacters' => LearnerNotesService::MAX_BODY_CHARACTERS,
        ]);
    }

    private static function validCsrf(): bool
    {
        if (CsrfTokenService::valid(Request::input('csrf_token'))) return true;
        \SessionService::flash('error', 'Your notes form expired. Reload the page and try again.');
        Response::redirect('/my-notes', 303);
    }

    private static function board(string $id): ?array
    {
        foreach (TaxonomyStorageService::boards() as $board) {
            if (is_array($board) && (string) ($board['id'] ?? '') === $id) return $board;
        }
        return null;
    }

    private static function isCivilService(?array $board): bool
    {
        return is_array($board) && in_array(strtolower((string) ($board['id'] ?? $board['code'] ?? '')), ['cse', 'civil-service'], true);
    }

    private static function safeReturnTo(mixed $value): string
    {
        if (!is_scalar($value)) return '';
        $path = trim((string) $value);
        return preg_match('#\A/mistake-notebook/review\?attempt_id=attempt-[a-f0-9]{16}&question_id=[A-Za-z0-9_-]{1,80}\z#', $path) === 1
            ? $path
            : '';
    }
}
