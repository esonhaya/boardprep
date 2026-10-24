<?php

declare(strict_types=1);

namespace App\Services\Learning;

/** Builds display-only links between an existing quiz result and the note snapshot used at start. */
final class NotesPracticeResultService
{
    public static function create(array $context, array $review): array
    {
        $questionNotes = is_array($context['question_notes'] ?? null) ? $context['question_notes'] : [];
        $correct = 0;
        $incorrect = 0;
        $practiced = [];
        $revisit = [];
        $perQuestion = [];
        foreach ($review as $item) {
            if (!is_array($item) || !is_array($item['question'] ?? null)) continue;
            $id = is_scalar($item['question']['id'] ?? null) ? (string) $item['question']['id'] : '';
            $note = is_array($questionNotes[$id] ?? null) ? $questionNotes[$id] : null;
            if (!is_array($note)) continue;
            $topic = trim((string) ($note['topic'] ?? ''));
            if ($topic !== '') {
                $practiced[$topic] = true;
                if (($item['correct'] ?? false) !== true) $revisit[$topic] = true;
            }
            if (($item['correct'] ?? false) === true) $correct++; else $incorrect++;
            $perQuestion[$id] = $note;
        }
        $noteId = (string) ($context['note_id'] ?? '');
        $savedNoteAvailable = preg_match('/\Anote-[a-f0-9]{32}\z/', $noteId) === 1
            && (new LearnerNotesService())->find($noteId) !== null;

        return [
            'note_id' => $noteId,
            'saved_note_available' => $savedNoteAvailable,
            'note_title' => (string) ($context['note_title'] ?? 'My notes'),
            'topics_practiced' => array_keys($practiced),
            'topics_to_revisit' => array_keys($revisit),
            'topics_not_yet_covered' => array_values(array_filter(
                is_array($context['uncovered_topics'] ?? null) ? $context['uncovered_topics'] : [],
                'is_string'
            )),
            'limited_topics' => array_values(array_filter(
                is_array($context['limited_topics'] ?? null) ? $context['limited_topics'] : [],
                'is_string'
            )),
            'correct' => $correct,
            'incorrect' => $incorrect,
            'question_notes' => $perQuestion,
        ];
    }
}
