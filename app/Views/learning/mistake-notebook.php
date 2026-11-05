<?php
$items = is_array($items ?? null) ? $items : [];
$skipped = is_array($skipped ?? null) ? $skipped : [];
$boards = is_array($boards ?? null) ? $boards : [];
$subjects = is_array($subjects ?? null) ? $subjects : [];
$topics = is_array($topics ?? null) ? $topics : [];
$topicSummary = is_array($topicSummary ?? null) ? $topicSummary : [];
$filters = is_array($filters ?? null) ? $filters : [];
$csrfToken = is_scalar($csrfToken ?? null) ? (string) $csrfToken : '';
$escape = static fn(mixed $value): string => htmlspecialchars(is_scalar($value) ? (string) $value : '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$boardLabel = static fn(mixed $id): string => \App\Services\Shared\TaxonomyStorageService::label('boards', $id);
$subjectLabel = static fn(mixed $id): string => \App\Services\Shared\TaxonomyStorageService::label('subjects', $id);
$selectedBoard = is_scalar($filters['board'] ?? null) ? (string) $filters['board'] : '';
$selectedSubject = is_scalar($filters['subject'] ?? null) ? (string) $filters['subject'] : '';
$selectedTopic = is_scalar($filters['topic'] ?? null) ? (string) $filters['topic'] : '';
$cse = in_array(strtolower($selectedBoard), ['cse', 'civil-service'], true);
$levels = is_array($civilServiceLevels ?? null) ? $civilServiceLevels : [];
$letVariantOptions = is_array($letVariantOptions ?? null) ? $letVariantOptions : [];
$skippedTotal = max(0, (int) ($skippedTotal ?? count($skipped)));
$skippedPage = max(1, (int) ($skippedPage ?? 1));
$skippedPages = max(1, (int) ($skippedPages ?? 1));
$skippedPageSize = max(1, (int) ($skippedPageSize ?? 20));
$currentQuery = $filters;
?>
<div class="mistake-notebook-page">
    <nav class="breadcrumbs" aria-label="Breadcrumb"><ol><li><a href="/progress">Progress</a></li><li aria-current="page">Mistake Notebook</li></ol></nav>
    <section class="page-header">
        <div><p class="eyebrow">Learn from your attempts</p><h1>Mistake Notebook</h1><p>Review submitted answers you got wrong, see what happened on later attempts, and choose a focused retry.</p></div>
        <div class="page-actions"><a class="button button-outline" href="/history">Quiz history</a><a class="button button-green" href="/quiz">Regular practice</a></div>
    </section>

    <section class="mistake-filter-panel card" aria-labelledby="mistake-filter-title">
        <h2 id="mistake-filter-title">Find a review</h2>
        <form method="get" action="/mistake-notebook" class="mistake-filter-form">
            <label for="mistake-board">Examination board</label>
            <select id="mistake-board" name="board"><option value="">All boards</option><?php foreach ($boards as $board): if (!is_array($board)) continue; $id = (string) ($board['id'] ?? ''); if ($id === '') continue; ?><option value="<?= $escape($id) ?>"<?= $id === $selectedBoard ? ' selected' : '' ?>><?= $escape($board['name'] ?? $id) ?></option><?php endforeach; ?></select>
            <label for="mistake-subject">Subject</label>
            <select id="mistake-subject" name="subject"><option value="">All subjects</option><?php foreach ($subjects as $subject): if (!is_array($subject)) continue; $id = (string) ($subject['id'] ?? ''); if ($id === '') continue; ?><option value="<?= $escape($id) ?>"<?= $id === $selectedSubject ? ' selected' : '' ?>><?= $escape($subject['name'] ?? $id) ?></option><?php endforeach; ?></select>
            <label for="mistake-topic">Topic</label>
            <select id="mistake-topic" name="topic"><option value="">All recorded topics</option><?php foreach ($topics as $topic): if (!is_array($topic)) continue; $id = (string) ($topic['id'] ?? ''); if ($id === '') continue; ?><option value="<?= $escape($id) ?>"<?= $id === $selectedTopic ? ' selected' : '' ?>><?= $escape($topic['name'] ?? $id) ?></option><?php endforeach; ?></select>
            <label class="mistake-check"><input type="checkbox" name="recent" value="1"<?= ($filters['recent'] ?? '') === '1' ? ' checked' : '' ?>> Recent mistakes (last 30 days)</label>
            <label class="mistake-check"><input type="checkbox" name="repeated" value="1"<?= ($filters['repeated'] ?? '') === '1' ? ' checked' : '' ?>> Missed more than once</label>
            <label class="mistake-check"><input type="checkbox" name="reviewed" value="1"<?= ($filters['reviewed'] ?? '') === '1' ? ' checked' : '' ?>> Attempted again</label>
            <label class="mistake-check"><input type="checkbox" name="corrected" value="1"<?= ($filters['corrected'] ?? '') === '1' ? ' checked' : '' ?>> Correct on latest attempt</label>
            <div class="mistake-filter-actions"><button class="button button-outline" type="submit">Apply filters</button><a href="/mistake-notebook">Clear</a></div>
        </form>
    </section>

    <?php if ($selectedBoard !== ''): ?>
    <section class="mistake-practice-panel card" aria-labelledby="mistake-practice-title">
        <div><p class="eyebrow">Targeted practice</p><h2 id="mistake-practice-title">Practice this mistake set</h2><p>Retry uses only your recorded incorrect or skipped questions that remain eligible in the selected board and variant. Related practice uses other eligible questions with the same recorded topic or concept. If fewer questions are available, the session uses only that eligible set.</p><p><strong><?= $escape($boardLabel($selectedBoard)) ?></strong><?= $selectedSubject !== '' ? ' · ' . $escape($subjectLabel($selectedSubject)) : ' · all subjects' ?></p></div>
        <form method="post" action="/mistake-notebook/practice" class="mistake-practice-form">
            <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>"><input type="hidden" name="board" value="<?= $escape($selectedBoard) ?>"><input type="hidden" name="subject" value="<?= $escape($selectedSubject !== '' ? $selectedSubject : '*') ?>"><input type="hidden" name="topic" value="<?= $escape($selectedTopic) ?>">
            <label for="mistake-level">Civil Service level</label>
            <?php if ($cse): ?><select id="mistake-level" name="civil_service_level" required><option value="">Choose level</option><?php foreach (['PROFESSIONAL', 'SUBPROFESSIONAL'] as $level): ?><option value="<?= $level ?>"><?= $level === 'PROFESSIONAL' ? 'Professional' : 'Subprofessional' ?></option><?php endforeach; ?></select><?php else: ?><input type="hidden" name="civil_service_level" value=""><p class="muted-note">Not required for this board.</p><?php endif; ?>
            <?php if (strtolower($selectedBoard) === 'let'): ?><label for="mistake-let-variant">LET level and specialization</label><select id="mistake-let-variant" name="let_variant_key" required><option value=""<?= count($letVariantOptions) === 1 ? '' : ' selected' ?>>Choose the saved practice context</option><?php foreach ($letVariantOptions as $option): if (!is_array($option) || !is_scalar($option['key'] ?? null)) continue; ?><option value="<?= $escape($option['key']) ?>"<?= count($letVariantOptions) === 1 ? ' selected' : '' ?>><?= $escape($option['label'] ?? 'LET practice context') ?></option><?php endforeach; ?></select><p class="muted-note">Saved variants are kept separate. Older attempts without variant details are labeled as unscoped.</p><?php endif; ?>
            <label for="mistake-count">Session size</label><select id="mistake-count" name="count" required><?php foreach ([5, 10, 15, 20] as $size): ?><option value="<?= $size ?>"><?= $size ?> questions (maximum; smaller eligible pools stay smaller)</option><?php endforeach; ?></select>
            <div class="page-actions"><button class="button button-green" type="submit" name="practice_type" value="retry">Retry Missed Questions</button><button class="button button-outline" type="submit" name="practice_type" value="related">Practice Related Topics</button></div>
        </form>
    </section>
    <?php else: ?><p class="muted-note">Choose a board in the filters to start targeted practice. A question’s history remains grouped under the board and subject where you answered it.</p><?php endif; ?>

    <?php if ($topicSummary !== []): ?><section class="mistake-topic-summary card" aria-labelledby="mistake-topic-summary-title"><h2 id="mistake-topic-summary-title">Weak areas in this view</h2><p>Counts come from your recorded submitted attempts and distinct questions, grouped by board and subject. They do not imply mastery or academic validation.</p><ul><?php foreach ($topicSummary as $topic): ?><li><?php if (($topic['id'] ?? '') !== ''): $topicQuery = $filters; $topicQuery['board'] = (string) ($topic['board'] ?? ''); $topicQuery['subject'] = (string) ($topic['subject'] ?? ''); $topicQuery['topic'] = (string) $topic['id']; $topicQuery['page'] = 1; ?><a href="/mistake-notebook?<?= $escape(http_build_query($topicQuery)) ?>"><?= $escape($topic['name']) ?></a><?php else: ?><strong><?= $escape($topic['name']) ?></strong><?php endif; ?> · <?= (int) $topic['questions'] ?> question<?= (int) $topic['questions'] === 1 ? '' : 's' ?> · <?= (int) $topic['incorrect_attempts'] ?> incorrect attempt<?= (int) $topic['incorrect_attempts'] === 1 ? '' : 's' ?></li><?php endforeach; ?></ul></section><?php endif; ?>

    <section aria-labelledby="mistake-results-title">
        <div class="section-heading"><div><p class="eyebrow"><?= (int) ($filteredCount ?? 0) ?> question-level records</p><h2 id="mistake-results-title">Submitted answers to revisit</h2></div></div>
        <?php if ($items === []): ?>
            <div class="empty-state" role="status"><h3>No incorrect answers in this view</h3><p><?php if ((int) ($totalMistakes ?? 0) === 0): ?>There are no recorded mistakes yet. Your skipped questions are shown separately when available.<?php else: ?>Try clearing or changing the filters to see other recorded mistakes.<?php endif; ?> Your history is not used to invent a weakness when there is no submitted incorrect answer.</p><p><a class="button button-green" href="/quiz">Start regular practice</a></p></div>
        <?php else: ?>
            <div class="mistake-card-list">
                <?php foreach ($items as $item): if (!is_array($item)) continue; $questionId = (string) ($item['question_id'] ?? ''); $attemptId = (string) ($item['latest_mistake_attempt_id'] ?? ''); $status = (string) ($item['latest_status'] ?? 'NOT_YET_RETRIED'); ?>
                <article class="mistake-card card">
                    <div class="mistake-card-heading"><div><p class="eyebrow"><?= $escape($boardLabel($item['board'] ?? '')) ?> · <?= $escape($subjectLabel($item['subject'] ?? '')) ?></p><h3>Question <?= $escape($questionId) ?></h3></div><span class="badge <?= $status === 'CORRECT_ON_MOST_RECENT_RETRY' ? 'badge-success' : 'badge-warning' ?>"><?= $status === 'CORRECT_ON_MOST_RECENT_RETRY' ? 'Correct on latest retry' : ($status === 'STILL_NEEDS_PRACTICE' ? 'Still needs practice' : 'Not yet retried') ?></span></div>
                    <p class="mistake-question-preview"><?= $escape($item['question_text'] ?? '') ?></p>
                    <p><strong>Topic:</strong> <?= $escape($item['topic'] ?? 'Topic not recorded') ?> · <strong>Incorrect attempts:</strong> <?= (int) ($item['wrong_count'] ?? 0) ?> · <strong>Answered attempts:</strong> <?= (int) ($item['attempt_count'] ?? 0) ?></p>
                    <?php if (in_array(strtolower((string) ($item['board'] ?? '')), ['let', 'cse', 'civil-service'], true) && !empty($item['latest_mistake_variant_label'])): ?><p class="muted-note">Most recent miss · <?= $escape($item['latest_mistake_variant_label']) ?></p><?php endif; ?>
                    <?php if (!empty($item['last_mistake_at'])): ?><p class="muted-note">Most recent mistake: <?= $escape(\App\Support\Presentation\UiFormatter::formatDateTime((string) $item['last_mistake_at'])) ?><?php if (!empty($item['subsequently_correct'])): ?> · answered correctly on a later attempt<?php endif; ?></p><?php endif; ?>
                    <p><a class="button button-outline" href="/mistake-notebook/review?attempt_id=<?= rawurlencode($attemptId) ?>&amp;question_id=<?= rawurlencode($questionId) ?>">Understand this answer</a></p>
                </article>
                <?php endforeach; ?>
            </div>
            <?php if ((int) ($pages ?? 1) > 1): ?><nav class="mistake-pagination" aria-label="Mistake notebook pages"><?php if ((int) $page > 1): $currentQuery['page'] = (int) $page - 1; ?><a class="button button-outline" href="/mistake-notebook?<?= $escape(http_build_query($currentQuery)) ?>">Previous</a><?php endif; ?><span>Page <?= (int) $page ?> of <?= (int) $pages ?></span><?php if ((int) $page < (int) $pages): $currentQuery['page'] = (int) $page + 1; ?><a class="button button-outline" href="/mistake-notebook?<?= $escape(http_build_query($currentQuery)) ?>">Next</a><?php endif; ?></nav><?php endif; ?>
        <?php endif; ?>
    </section>

    <?php if ($skippedTotal > 0): $skippedFirst = (($skippedPage - 1) * $skippedPageSize) + 1; $skippedLast = min($skippedTotal, ($skippedPage - 1) * $skippedPageSize + count($skipped)); ?>
        <section class="mistake-skipped" aria-labelledby="skipped-title"><h2 id="skipped-title">Skipped questions</h2><p>These were left unanswered. They are not counted as submitted mistakes.</p>
            <p class="mistake-skipped-range" role="status" aria-live="polite">Showing <?= $skippedFirst ?>–<?= $skippedLast ?> of <?= $skippedTotal ?> skipped questions · Page <?= $skippedPage ?> of <?= $skippedPages ?></p>
            <ul><?php foreach ($skipped as $item): if (!is_array($item)) continue; ?><li><a href="/mistake-notebook/review?attempt_id=<?= rawurlencode((string) ($item['attempt_id'] ?? '')) ?>&amp;question_id=<?= rawurlencode((string) ($item['question_id'] ?? '')) ?>">Question <?= $escape($item['question_id'] ?? '') ?> · <?= $escape($boardLabel($item['board'] ?? '')) ?> · <?= $escape($subjectLabel($item['subject'] ?? '')) ?></a> · skipped <?= (int) ($item['count'] ?? 0) ?> time<?= (int) ($item['count'] ?? 0) === 1 ? '' : 's' ?><?= !empty($item['variant_label']) ? ' · ' . $escape($item['variant_label']) : '' ?></li><?php endforeach; ?></ul>
            <?php if ($skippedPages > 1): $skippedQuery = $filters; $skippedQuery['page'] = (int) ($page ?? 1); $skippedQuery['skipped_page'] = $skippedPage; ?><nav class="mistake-pagination" aria-label="Skipped question pages"><div><?php if ($skippedPage > 1): $skippedQuery['skipped_page'] = $skippedPage - 1; ?><a class="button button-outline" rel="prev" href="/mistake-notebook?<?= $escape(http_build_query($skippedQuery)) ?>">Previous</a><?php endif; ?><?php if ($skippedPage < $skippedPages): $skippedQuery['skipped_page'] = $skippedPage + 1; ?><a class="button button-outline" rel="next" href="/mistake-notebook?<?= $escape(http_build_query($skippedQuery)) ?>">Next</a><?php endif; ?></div></nav><?php endif; ?>
        </section>
    <?php endif; ?>
</div>
