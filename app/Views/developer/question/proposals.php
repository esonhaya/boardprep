<?php
$escape = static fn(mixed $value): string => htmlspecialchars((string) (is_scalar($value) ? $value : ''), ENT_QUOTES, 'UTF-8');
$currentPage = (int) ($pageNumber ?? 1);
$pageCount = (int) ($pageCount ?? 1);
$filters = array_filter([
    'page' => 'question-editor',
    'proposal_batch' => '695',
    'search' => (string) ($search ?? ''),
    'board' => (string) ($board ?? ''),
    'subject' => (string) ($subject ?? ''),
], static fn(string $value): bool => $value !== '');
$previousUrl = '?' . http_build_query($filters + ['page_num' => max(1, $currentPage - 1)]);
$nextUrl = '?' . http_build_query($filters + ['page_num' => min($pageCount, $currentPage + 1)]);
?>
<section class="page-header">
    <div>
        <p class="eyebrow">Developer Question Repository</p>
        <h1>Batch 695 academic review proposals</h1>
        <p>276 proposed questions from the recovered B695 cohort. These records are pending independent review and are not canonical or available in learner question pools.</p>
    </div>
    <div class="page-actions"><a class="button" href="/developer/academic-review">Open restricted reviewer workbench</a><a class="button secondary" href="/question-editor">Canonical question inventory</a></div>
</section>

<section class="card" aria-label="Proposal review status">
    <p><strong>Review state:</strong> Live human-workflow states are shown per proposal below. This inventory view does not approve a proposal, write a canonical answer key, or change board eligibility.</p>
    <p><strong>Matching current filters:</strong> <?= (int) ($totalProposals ?? 0) ?> proposals · Page <?= $currentPage ?> of <?= $pageCount ?></p>
</section>

<form class="filter-bar" method="GET" action="/question-editor">
    <input type="hidden" name="proposal_batch" value="695">
    <label for="proposal-search">Search proposal ID, question text, topic, or concept</label>
    <input class="field-control" type="search" id="proposal-search" name="search" value="<?= $escape($search ?? '') ?>">

    <label for="proposal-board">Affected examination board</label>
    <select class="field-control" id="proposal-board" name="board">
        <option value="">All boards</option>
        <?php foreach (($boards ?? []) as $item): ?>
            <?php $id = (string) ($item['id'] ?? ''); if ($id === '') { continue; } ?>
            <option value="<?= $escape($id) ?>" <?= (($board ?? '') === $id) ? 'selected' : '' ?>><?= $escape($item['name'] ?? $id) ?></option>
        <?php endforeach; ?>
    </select>

    <label for="proposal-subject">Affected subject</label>
    <select class="field-control" id="proposal-subject" name="subject">
        <option value="">All subjects</option>
        <?php foreach (($subjects ?? []) as $item): ?>
            <?php $id = (string) ($item['id'] ?? ''); if ($id === '') { continue; } ?>
            <option value="<?= $escape($id) ?>" <?= (($subject ?? '') === $id) ? 'selected' : '' ?>><?= $escape($item['name'] ?? $id) ?></option>
        <?php endforeach; ?>
    </select>
    <button type="submit">Filter proposals</button>
    <a class="button button-outline" href="/question-editor?proposal_batch=695">Reset</a>
</form>

<?php if (empty($proposals)): ?>
    <p class="empty-state">No proposals match these filters.</p>
<?php else: ?>
    <div class="proposal-list">
        <?php foreach (($proposals ?? []) as $proposal): ?>
            <?php
            $question = is_array($proposal['proposed_question'] ?? null) ? $proposal['proposed_question'] : [];
            $reason = is_array($proposal['proposal_reason'] ?? null) ? $proposal['proposal_reason'] : [];
            $study = is_array($proposal['study_relationship'] ?? null) ? $proposal['study_relationship'] : [];
            ?>
            <article class="card proposal-item" data-proposal-id="<?= $escape($proposal['id'] ?? '') ?>">
                <h2>Proposal #<?= $escape($proposal['question_id'] ?? '') ?></h2>
                <p><strong><?= $escape(str_replace('_', ' ', (string) (($proposal['academic_workflow']['state'] ?? '') ?: 'PENDING_REVIEW'))) ?></strong> · Not canonical unless separately imported · Not selectable in learner pools before import</p>
                <p><strong>Board:</strong> <?= $escape($proposal['board_name'] ?? $proposal['board_id'] ?? '') ?> · <strong>Subject:</strong> <?= $escape($proposal['subject_name'] ?? $proposal['subject_id'] ?? '') ?></p>
                <p><strong>Topic:</strong> <?= $escape($proposal['topic_name'] ?? $proposal['topic_id'] ?? '') ?> · <strong>Concept:</strong> <?= $escape($proposal['concept_name'] ?? $proposal['concept_id'] ?? '') ?></p>
                <p><?= $escape($question['stem'] ?? '') ?></p>
                <p><strong>Claim to review:</strong> <?= $escape($reason['specific_claim_to_review'] ?? 'Not recorded') ?></p>
                <p><strong>Proposed study section:</strong> <?= $escape($study['material_title'] ?? $study['material_id'] ?? '') ?> — <?= $escape($study['section_heading'] ?? $study['section_id'] ?? '') ?> · <?= !empty($study['current_section_exists']) ? 'section currently exists' : 'section is proposal-only' ?></p>
                <p><strong>Sources:</strong> <?= $escape(implode(', ', array_map('strval', (array) ($proposal['source_ids'] ?? [])))) ?></p>
                <a class="button" href="/question-inspector?proposal_id=<?= rawurlencode((string) ($proposal['id'] ?? '')) ?>&amp;return=<?= rawurlencode('/question-editor?proposal_batch=695') ?>">Review proposal details</a>
                <a class="button button-outline" href="/developer/academic-review/proposal?id=<?= rawurlencode((string) ($proposal['id'] ?? '')) ?>">Open academic workflow</a>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<nav class="pagination academic-proposal-pagination" aria-label="Academic review proposal pages">
    <span>Showing <?= count($proposals ?? []) ?> of <?= (int) ($totalProposals ?? 0) ?> proposals · Page <?= $currentPage ?> of <?= $pageCount ?></span>
    <div class="page-actions">
        <?php if ($currentPage > 1): ?><a class="button button-outline" rel="prev" href="<?= $escape($previousUrl) ?>">Previous page</a><?php endif; ?>
        <?php if ($currentPage < $pageCount): ?><a class="button button-outline" rel="next" href="<?= $escape($nextUrl) ?>">Next page</a><?php endif; ?>
    </div>
</nav>
