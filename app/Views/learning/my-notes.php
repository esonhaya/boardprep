<?php
$notes = is_array($notes ?? null) ? $notes : [];
$boards = is_array($boards ?? null) ? $boards : [];
$selectedNote = is_array($selectedNote ?? null) ? $selectedNote : null;
$match = is_array($match ?? null) ? $match : null;
$formInput = is_array($formInput ?? null) ? $formInput : [];
$formError = is_scalar($formError ?? null) ? (string) $formError : '';
$csrfToken = is_scalar($csrfToken ?? null) ? (string) $csrfToken : '';
$civilServiceLevel = is_scalar($civilServiceLevel ?? null) ? (string) $civilServiceLevel : '';
$escape = static fn(mixed $value): string => htmlspecialchars(is_scalar($value) ? (string) $value : '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$isEditing = $selectedNote !== null || trim((string) ($formInput['note_id'] ?? '')) !== '';
$noteId = $selectedNote['id'] ?? $formInput['note_id'] ?? '';
$titleValue = $formInput['title'] ?? $selectedNote['title'] ?? '';
$bodyValue = $formInput['body'] ?? $selectedNote['body'] ?? '';
$boardValue = $formInput['board_id'] ?? $selectedNote['board_id'] ?? ($boards[0]['id'] ?? '');
$selectedBoard = null;
foreach ($boards as $board) {
    if (is_array($board) && (string) ($board['id'] ?? '') === (string) $boardValue) { $selectedBoard = $board; break; }
}
$isCse = is_array($selectedBoard) && in_array(strtolower((string) ($selectedBoard['id'] ?? $selectedBoard['code'] ?? '')), ['cse', 'civil-service'], true);
?>
<div class="ui-page-index my-notes-page">
    <nav class="breadcrumbs" aria-label="Breadcrumb"><ol><li><a href="/study-library">Study</a></li><li aria-current="page">My Notes</li></ol></nav>
    <section class="page-header">
        <div><p class="eyebrow">Private study workspace</p><h1>Practice Based on My Notes</h1><p>Save your own notes and use their topics to find relevant, existing BoardPrep questions for a focused practice session.</p></div>
        <div class="page-actions"><a class="button button-outline" href="/study-library">Browse study materials</a><a class="button button-outline" href="/quiz">Ordinary practice</a></div>
    </section>

    <p class="notes-privacy-note" role="note">Your notes are private to your learner identity and stay within BoardPrep. They are stored as plain text, are not sent to external services, and are not treated as evidence that an answer is correct.</p>

    <?php if ($formError !== ''): ?><p class="flash-message flash-error" role="alert"><?= $escape($formError) ?></p><?php endif; ?>
    <?php if (!empty($notFound)): ?><div class="empty-state" role="status"><h2>That note is unavailable</h2><p>It may have been deleted or belong to another learner session. Your note list is still private.</p><p><a class="button button-outline" href="/my-notes">Open My Notes</a></p></div><?php endif; ?>

    <div class="my-notes-layout">
        <section class="card notes-list" aria-labelledby="saved-notes-title">
            <div class="section-heading"><div><p class="eyebrow">Saved privately</p><h2 id="saved-notes-title">My notes</h2></div><span class="badge badge-info"><?= count($notes) ?> / <?= (int) \App\Services\Learning\LearnerNotesService::MAX_NOTES ?></span></div>
            <?php if ($notes === []): ?><p class="muted">No notes saved yet. Add a title, select an exam, and paste your notes to begin.</p>
            <?php else: ?><ul class="notes-list-items"><?php foreach ($notes as $note): if (!is_array($note)) continue; $id = (string) ($note['id'] ?? ''); if ($id === '') continue; ?><li><a href="/my-notes?note=<?= rawurlencode($id) ?>"<?= $selectedNote !== null && $id === (string) ($selectedNote['id'] ?? '') ? ' aria-current="page"' : '' ?>><strong><?= $escape($note['title'] ?? 'Untitled note') ?></strong><span><?= $escape($note['board_id'] ?? '') ?> · Updated <?= $escape($note['updated_at'] ?? '') ?></span></a></li><?php endforeach; ?></ul><?php endif; ?>
            <p><a class="button button-outline" href="/my-notes">Create a new note</a></p>
        </section>

        <section class="card note-editor" aria-labelledby="note-editor-title">
            <p class="eyebrow"><?= $isEditing ? 'Edit saved note' : 'New note' ?></p>
            <h2 id="note-editor-title"><?= $isEditing ? 'Your study notes' : 'Add study notes' ?></h2>
            <form method="post" action="/my-notes/save" accept-charset="UTF-8">
                <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                <?php if (is_scalar($noteId) && trim((string) $noteId) !== ''): ?><input type="hidden" name="note_id" value="<?= $escape($noteId) ?>"><?php endif; ?>
                <label for="my-note-title">Note title</label>
                <input id="my-note-title" name="title" type="text" maxlength="120" required value="<?= $escape($titleValue) ?>" autocomplete="off">
                <label for="my-note-board">Examination board</label>
                <select id="my-note-board" name="board_id" required>
                    <?php foreach ($boards as $board): if (!is_array($board)) continue; $id = (string) ($board['id'] ?? ''); if ($id === '') continue; ?><option value="<?= $escape($id) ?>"<?= $id === (string) $boardValue ? ' selected' : '' ?>><?= $escape($board['name'] ?? $board['code'] ?? $id) ?></option><?php endforeach; ?>
                </select>
                <label for="my-note-body">Study notes</label>
                <textarea id="my-note-body" name="body" rows="14" maxlength="<?= (int) \App\Services\Learning\LearnerNotesService::MAX_BODY_CHARACTERS ?>" required aria-describedby="my-note-limit"><?= $escape($bodyValue) ?></textarea>
                <p id="my-note-limit" class="muted">Up to <?= number_format((int) \App\Services\Learning\LearnerNotesService::MAX_BODY_CHARACTERS) ?> characters. Headings and paragraphs are preserved. Text is never executed as HTML or code.</p>
                <div class="page-actions"><button class="button button-green" type="submit"><?= $isEditing ? 'Save changes' : 'Save note' ?></button><?php if ($isEditing && is_scalar($noteId) && preg_match('/\Anote-[a-f0-9]{32}\z/', (string) $noteId) === 1): ?><a class="button button-outline" href="/my-notes?note=<?= rawurlencode((string) $noteId) ?>">Cancel</a><?php endif; ?></div>
            </form>
            <?php if ($selectedNote !== null && preg_match('/\Anote-[a-f0-9]{32}\z/', (string) ($selectedNote['id'] ?? '')) === 1): ?>
                <form class="note-delete-form" method="post" action="/my-notes/delete">
                    <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>"><input type="hidden" name="note_id" value="<?= $escape($selectedNote['id']) ?>"><input id="confirm-note-delete" name="confirm_delete" type="checkbox" value="yes" required><label for="confirm-note-delete">I understand this permanently deletes my saved note.</label><button class="button button-outline" type="submit">Delete note</button>
                </form>
            <?php endif; ?>
        </section>
    </div>

    <?php if ($selectedNote !== null): ?>
        <section class="card notes-match-panel" aria-labelledby="note-match-heading">
            <p class="eyebrow">Existing canonical questions only</p><h2 id="note-match-heading">Practice from these notes</h2>
            <p>The local matcher uses multiple topic terms, board eligibility, question text, taxonomy, and exact lesson-section links where available. A match is a study suggestion, not academic endorsement.</p>
            <?php if ($isCse && !in_array($civilServiceLevel, ['PROFESSIONAL', 'SUBPROFESSIONAL'], true)): ?>
                <form method="get" action="/my-notes" class="notes-level-form">
                    <input type="hidden" name="note" value="<?= $escape($selectedNote['id']) ?>"><label for="notes-cse-level">Civil Service level</label><select id="notes-cse-level" name="civil_service_level" required><option value="">Choose a level</option><option value="PROFESSIONAL">Professional</option><option value="SUBPROFESSIONAL">Subprofessional</option></select><button class="button button-outline" type="submit">Check note matches</button>
                </form>
            <?php elseif ($match === null): ?>
                <p class="muted">Choose the examination level to check eligible questions for this note.</p>
            <?php else: ?>
                <p><strong>Selected board:</strong> <?= $escape($selectedBoard['name'] ?? $selectedNote['board_id']) ?><?= $isCse ? ' · ' . $escape($civilServiceLevel) : '' ?></p>
                <p><strong>Questions available:</strong> <?= (int) ($match['question_count'] ?? 0) ?></p>
                <?php if (!empty($match['matched_topics'])): ?><p><strong>Matched topics:</strong> <?= $escape(implode(', ', $match['matched_topics'])) ?></p><?php endif; ?>
                <?php if (!empty($match['limited_topics'])): ?><p><strong>Topics with limited question coverage:</strong> <?= $escape(implode(', ', $match['limited_topics'])) ?></p><?php endif; ?>
                <?php if (!empty($match['uncovered_topics'])): ?><p><strong>Topics not yet covered:</strong> <?= $escape(implode(', ', $match['uncovered_topics'])) ?></p><?php endif; ?>
                <?php if ((int) ($match['question_count'] ?? 0) > 0): ?>
                    <h3>Why these questions matched</h3>
                    <ul class="notes-match-reasons"><?php foreach (array_slice($match['candidates'], 0, 8) as $candidate): if (!is_array($candidate)) continue; ?><li><strong><?= $escape($candidate['question_id'] ?? '') ?> · <?= $escape($candidate['topic'] ?? '') ?></strong><span><?= $escape($candidate['reason'] ?? '') ?></span></li><?php endforeach; ?></ul>
                    <?php $available = min(20, (int) $match['question_count']); $sizes = array_values(array_unique(array_filter([5, 10, 15, 20], static fn(int $size): bool => $size <= $available))); if ($available > 0) $sizes[] = $available; $sizes = array_values(array_unique($sizes)); sort($sizes); ?>
                    <form method="post" action="/my-notes/practice" class="notes-practice-form">
                        <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>"><input type="hidden" name="note_id" value="<?= $escape($selectedNote['id']) ?>"><?php if ($isCse): ?><input type="hidden" name="civil_service_level" value="<?= $escape($civilServiceLevel) ?>"><?php endif; ?>
                        <label for="notes-practice-count">Session size</label><select id="notes-practice-count" name="count" required><?php foreach ($sizes as $size): ?><option value="<?= (int) $size ?>"><?= (int) $size ?> question<?= $size === 1 ? '' : 's' ?><?= $size === $available && $available < 5 ? ' · all available' : '' ?></option><?php endforeach; ?></select>
                        <button class="button button-green" type="submit">Practice From These Notes</button>
                    </form>
                <?php else: ?>
                    <div class="empty-state" role="status"><h3>No sufficiently relevant questions found</h3><p>BoardPrep did not find a defensible match on this board. It will not fill a quiz with unrelated questions. Try adding clearer topic terms or use ordinary board practice.</p><p><a class="button button-outline" href="/quiz?action=start&amp;exam=<?= rawurlencode((string) ($selectedBoard['code'] ?? $selectedBoard['id'] ?? '')) ?>">Open ordinary practice setup</a></p></div>
                <?php endif; ?>
            <?php endif; ?>
        </section>
    <?php endif; ?>
</div>
