<?php
$materials = is_array($materials ?? null) ? $materials : [];
$exam = (string) ($exam ?? '');
$search = (string) ($search ?? '');
$materialRequested = ($materialRequested ?? false) === true;
$escape = static fn(mixed $value): string => htmlspecialchars(is_scalar($value) ? (string) $value : '', ENT_QUOTES, 'UTF-8');
?>
<div class="ui-page-index study-library-page">
    <nav class="breadcrumbs" aria-label="Breadcrumb"><ol><li><a href="/exams">Examinations</a></li><li aria-current="page">Study library</li></ol></nav>
    <section class="page-header">
        <div><p class="eyebrow">Study Library<?= $exam !== '' ? ' · ' . $escape(strtoupper($exam)) : '' ?></p><h1>Study resources</h1><p>BoardPrep lessons teach the concepts; linked authorities show examination scope and source material.</p></div>
        <div class="page-actions"><a class="button button-green" href="/quiz">Practice now</a><a class="button button-outline" href="/flashcards">Review flashcards</a></div>
    </section>
    <?php if ($materials === []): ?>
        <div class="empty-state">
            <h2><?= $materialRequested ? 'Study material unavailable' : ($search !== '' ? 'No study materials match that search' : 'No study materials are available yet') ?></h2>
            <p><?= $materialRequested ? 'This linked study material is no longer available.' : ($search !== '' ? 'Try a broader title, topic, or phrase, or clear the search to browse all published materials.' : 'There are no published materials for this examination. You can choose another exam or start practice while lessons are prepared.') ?></p>
            <p><?php if ($search !== ''): ?><a class="button button-outline" href="/study-library<?= $exam !== '' ? '?exam=' . rawurlencode($exam) : '' ?>">Clear search</a><?php endif; ?> <a class="button button-outline" href="/study-library">Browse all study materials</a> <a class="button button-outline" href="/exams">Choose an exam</a> <a class="button button-outline" href="/dashboard">Dashboard</a></p>
        </div>
    <?php else: ?>
        <?php if (!$materialRequested): ?>
            <form class="study-library-search card" method="GET" action="/study-library" role="search">
                <?php if ($exam !== ''): ?><input type="hidden" name="exam" value="<?= $escape($exam) ?>"><?php endif; ?>
                <label for="study-library-search">Search lesson titles, notes, and concepts</label>
                <div><input id="study-library-search" name="q" type="search" maxlength="120" value="<?= $escape($search) ?>" placeholder="Try a subject, topic, or phrase"><button type="submit">Search</button></div>
                <p class="muted" role="status" aria-live="polite">Showing <?= count($materials) ?> study <?= count($materials) === 1 ? 'material' : 'materials' ?><?= $search !== '' ? ' matching “' . $escape($search) . '”' : '' ?><?= $exam !== '' ? ' for ' . $escape(strtoupper($exam)) : '' ?>.</p>
            </form>
        <?php endif; ?>
        <div class="ui-feature-grid study-material-grid">
            <?php foreach ($materials as $material): ?>
                <?php
                $materialId = (string) ($material['id'] ?? '');
                $lesson = is_array($material['lesson'] ?? null) ? $material['lesson'] : null;
                $detailUrl = '/study-library?material=' . rawurlencode($materialId);
                $sourceById = [];
                foreach ((array) ($material['sources'] ?? []) as $source) if (is_array($source) && is_scalar($source['id'] ?? null)) $sourceById[(string) $source['id']] = $source;
                $notes = array_values(array_filter((array) ($material['notes'] ?? []), 'is_array'));
                $displayNotes = $materialRequested ? $notes : array_slice($notes, 0, 4);
                ?>
                <article id="study-material-<?= $escape($materialId) ?>" class="card study-material-card">
                    <p class="eyebrow"><?= $lesson !== null ? 'BoardPrep study lesson' : 'Reference/source record' ?> · v<?= (int) ($material['version'] ?? 1) ?></p>
                    <h2><?= $escape($material['title'] ?? '') ?></h2>
                    <p><?= $escape($material['summary'] ?? '') ?></p>
                    <?php if (is_array($material['priority'] ?? null)): ?>
                        <p><strong>Review focus: <?= $escape($material['priority']['level'] ?? 'LOW') ?></strong></p>
                        <ul><?php foreach (array_slice($material['priority']['reasons'] ?? [], 0, 2) as $reason): ?><li><?= $escape($reason) ?></li><?php endforeach; ?></ul>
                    <?php endif; ?>
                    <p><span class="badge <?= $lesson !== null ? 'badge-success' : 'badge-info' ?>"><?= $lesson !== null ? 'Study lesson' : (!empty($material['pdf_url']) ? 'Downloadable learning material' : 'Primary reference/source') ?></span> <span class="muted"><?= count($material['source_ids'] ?? []) ?> registered sources · <?= count($material['question_links'] ?? []) ?> linked questions</span></p>
                    <?php if ($lesson !== null): ?><p><a class="button button-green" href="<?= $escape($detailUrl) ?>#boardprep-lesson-<?= $escape($lesson['id'] ?? '') ?>">Open complete lesson</a><?php if (!empty($material['pdf_url'])): ?> <a class="button button-outline" href="<?= $escape($material['pdf_url']) ?>" download>Download lesson material</a><?php endif; ?></p><?php else: ?><p><a class="button button-outline" href="<?= $escape($detailUrl) ?>">Open source notes and references</a></p><p class="muted">This source record provides references and notes; it is not a complete BoardPrep lesson.</p><?php endif; ?>
                    <details class="study-material-disclosure"<?= $materialRequested ? ' open' : '' ?>>
                        <summary>Read study notes and sources</summary>
                        <div class="study-material-notes">
                            <?php foreach ($displayNotes as $note): ?>
                                <?php
                                $noteHeading = $note['heading'] ?? $note[0] ?? '';
                                $noteBody = $note['body'] ?? $note[1] ?? '';
                                if (!is_scalar($noteHeading) || !is_scalar($noteBody)) continue;
                                $noteHeading = trim((string) $noteHeading);
                                $noteBody = trim((string) $noteBody);
                                if ($noteHeading === '' && $noteBody === '') continue;
                                ?>
                                <section><?php if ($noteHeading !== ''): ?><h3><?= $escape($noteHeading) ?></h3><?php endif; ?><?php if ($noteBody !== ''): ?><p><?= $escape($noteBody) ?></p><?php endif; ?></section>
                            <?php endforeach; ?>
                        </div>
                        <?php if (!$materialRequested && count($notes) > count($displayNotes)): ?><p><a href="<?= $escape($detailUrl) ?>">Open all <?= count($notes) ?> notes</a></p><?php endif; ?>
                    </details>
                    <?php if ($lesson !== null && $materialRequested): ?>
                        <section id="boardprep-lesson-<?= $escape($lesson['id'] ?? '') ?>" class="boardprep-study-lesson" aria-labelledby="lesson-title-<?= $escape($lesson['id'] ?? '') ?>">
                            <p class="eyebrow">Original BoardPrep instruction · v<?= (int) ($lesson['version'] ?? 1) ?></p>
                            <h3 id="lesson-title-<?= $escape($lesson['id'] ?? '') ?>"><?= $escape($lesson['title'] ?? 'Study lesson') ?></h3>
                            <div class="study-scope-note"><strong>Official examination scope</strong><p><?= $escape($lesson['scope_note'] ?? '') ?></p></div>
                            <?php if (!empty($lesson['learning_objectives'])): ?><h4>What you will learn</h4><ul><?php foreach ($lesson['learning_objectives'] as $objective): ?><li><?= $escape($objective) ?></li><?php endforeach; ?></ul><?php endif; ?>
                            <?php foreach ((array) ($lesson['sections'] ?? []) as $sectionIndex => $section): if (!is_array($section)) continue; ?><?php $sectionId = is_scalar($section['id'] ?? null) && trim((string) $section['id']) !== '' ? (string) $section['id'] : 'section-' . $sectionIndex; ?><section id="<?= $escape(\App\Services\Study\StudyLibraryService::lessonSectionAnchorId((string) ($lesson['id'] ?? ''), $sectionId)) ?>"><h4><?= $escape($section['heading'] ?? '') ?></h4><?php if (is_scalar($section['body'] ?? null)): ?><p><?= $escape($section['body']) ?></p><?php endif; ?><?php if (!empty($section['items'])): ?><ul><?php foreach ($section['items'] as $item): ?><li><?= $escape($item) ?></li><?php endforeach; ?></ul><?php endif; ?></section><?php endforeach; ?>
                            <?php if (!empty($lesson['worked_examples'])): ?><h4>Worked examples</h4><?php foreach ($lesson['worked_examples'] as $example): if (!is_array($example)) continue; ?><article class="card study-worked-example"><h5><?= $escape($example['heading'] ?? 'Example') ?></h5><p><strong>Question:</strong> <?= $escape($example['prompt'] ?? '') ?></p><ol><?php foreach ((array) ($example['steps'] ?? []) as $step): ?><li><?= $escape($step) ?></li><?php endforeach; ?></ol><p><strong>Answer:</strong> <?= $escape($example['answer'] ?? '') ?></p><p><strong>Check:</strong> <?= $escape($example['check'] ?? '') ?></p></article><?php endforeach; ?><?php endif; ?>
                            <?php foreach ([['common_misconceptions', 'Common misconceptions'], ['question_patterns', 'Practice patterns']] as [$key, $heading]): if (empty($lesson[$key])) continue; ?><h4><?= $escape($heading) ?></h4><ul><?php foreach ((array) $lesson[$key] as $item): ?><li><?= $escape($item) ?></li><?php endforeach; ?></ul><?php endforeach; ?>
                            <h3>Sources</h3>
                            <?php foreach ([['official_scope_source_ids', 'Official scope references'], ['instructional_source_ids', 'Instructional references']] as [$key, $heading]): ?>
                                <?php $referenceIds = (array) ($lesson[$key] ?? []); if ($referenceIds === []) continue; ?>
                                <section class="study-material-sources"><h4><?= $escape($heading) ?></h4><ul><?php foreach ($referenceIds as $sourceId): $source = $sourceById[(string) $sourceId] ?? []; if ($source === []) continue; ?><li><a href="<?= $escape($source['url'] ?? '#') ?>" target="_blank" rel="noopener"><?= $escape($source['title'] ?? 'Registered reference') ?></a><?php if (!empty($source['authority'])): ?> · <?= $escape($source['authority']) ?><?php endif; ?><?php if (!empty($source['version'])): ?> · <?= $escape($source['version']) ?><?php endif; ?></li><?php endforeach; ?></ul></section>
                            <?php endforeach; ?>
                            <?php if (!empty($lesson['source_review']['checked_at'])): ?><p class="muted">Source and lesson checks recorded <?= $escape($lesson['source_review']['checked_at']) ?>. This does not represent human editorial sign-off.</p><?php endif; ?>
                            <p class="page-actions"><a class="button button-green" href="/quiz?exam=<?= rawurlencode((string) ($lesson['board_id'] ?? '')) ?>&amp;subject=<?= rawurlencode((string) ($lesson['subject_id'] ?? '')) ?>">Practice this subject</a><?php if (!empty($material['pdf_url'])): ?> <a class="button button-outline" href="<?= $escape($material['pdf_url']) ?>" download>Download searchable study PDF</a><?php endif; ?></p>
                        </section>
                    <?php endif; ?>
                    <?php if (!empty($material['sources']) && $lesson === null): ?>
                        <section class="study-material-sources" aria-label="Sources"><h3>Sources</h3><ul><?php foreach ($material['sources'] as $source): ?><li><a href="<?= $escape($source['url'] ?? '#') ?>" target="_blank" rel="noopener"><?= $escape($source['title'] ?? 'Registered reference') ?></a><?php if (!empty($source['authority'])): ?> · <?= $escape($source['authority']) ?><?php endif; ?></li><?php endforeach; ?></ul></section>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
