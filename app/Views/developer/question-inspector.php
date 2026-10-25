<?php
$taxonomy = is_array($question['taxonomy'] ?? null) ? $question['taxonomy'] : [];
$choices = is_array($question['choices'] ?? null) ? $question['choices'] : [];
$answerKey = $question['answer'] ?? $question['correctAnswer'] ?? $question['correct_answer'] ?? null;
$explanation = $question['explanation'] ?? $question['rationale'] ?? '';
$isProposal = ($isProposal ?? false) === true;
$proposal = is_array($proposal ?? null) ? $proposal : [];
$proposalReason = is_array($proposal['proposal_reason'] ?? null) ? $proposal['proposal_reason'] : [];
$proposalComparison = is_array($proposal['current_comparison'] ?? null) ? $proposal['current_comparison'] : [];
$proposalProvenance = is_array($proposal['provenance'] ?? null) ? $proposal['provenance'] : [];
$proposalStudy = is_array($proposal['study_relationship'] ?? null) ? $proposal['study_relationship'] : [];
$proposalTaxonomyState = implode(' · ', [
    'board ' . (!empty($proposalComparison['current_board_exists']) ? 'registered' : 'not registered'),
    'subject ' . (!empty($proposalComparison['current_subject_exists']) ? 'registered' : 'not registered'),
    'board and subject mapping ' . (!empty($proposalComparison['current_board_subject_mapping_valid']) ? 'valid' : 'not registered'),
    'domain to subject mapping ' . (!empty($proposalComparison['current_domain_matches_subject']) ? 'matches' : 'unresolved'),
    'topic ' . (empty($proposalComparison['current_topic_exists']) ? 'not registered' : (!empty($proposalComparison['current_topic_matches_domain']) ? 'registered; domain mapping matches' : 'registered; domain mapping unresolved')),
    'concept ' . (empty($proposalComparison['current_concept_exists']) ? 'not registered' : (!empty($proposalComparison['current_concept_matches_topic']) ? 'registered; topic mapping matches' : 'registered; topic mapping unresolved')),
]);
$escape = static fn(mixed $value): string => htmlspecialchars((string) (is_scalar($value) ? $value : ''), ENT_QUOTES, 'UTF-8');
$explicitReview = $question['academic_review_status'] ?? $question['review_status'] ?? $question['academic_status'] ?? null;
$exactLinks = array_values(array_filter($studyReferences ?? [], static fn(array $reference): bool => trim((string) ($reference['section_id'] ?? '')) !== ''));
$sourceOnlyLinks = array_values(array_filter($studyReferences ?? [], static fn(array $reference): bool => trim((string) ($reference['section_id'] ?? '')) === ''));
?>
<div class="ui-page-detail" data-ui-archetype="detail">
    <section class="page-header">
        <div>
            <p class="eyebrow"><?= $isProposal ? 'Academic review proposal' : 'Question inspector' ?></p>
            <h1><?= $isProposal ? 'Proposal' : 'Question' ?> #<?= $escape($question['id'] ?? '') ?></h1>
            <p><?= $isProposal ? 'Review the submitted item and evidence. This record is not canonical and cannot enter learner question pools from this page.' : 'Inspect canonical content, board scope, references, and review state.' ?></p>
        </div>
        <div class="page-actions">
            <a class="button secondary" href="<?= $escape($returnUrl ?? '/question-editor') ?>">Back to repository</a>
            <?php if (!$isProposal): ?><a class="button" href="/question-editor/edit?id=<?= rawurlencode((string) ($question['id'] ?? '')) ?>&amp;return=<?= rawurlencode((string) ($returnUrl ?? '/question-editor')) ?>">Edit question</a><?php endif; ?>
        </div>
    </section>

    <?php if ($isProposal): ?>
        <section class="card" aria-labelledby="proposal-status-heading">
            <h2 id="proposal-status-heading">Pending proposal — not an approved question</h2>
            <p><strong>Current review status:</strong> <?= $escape($proposal['current_status'] ?? 'PENDING') ?></p>
            <p><strong>Primary disposition:</strong> <?= $escape($proposal['primary_disposition'] ?? 'REVIEW_REQUIRED') ?></p>
            <p><strong>Source branch status:</strong> <?= $escape($proposal['proposed_question']['source_branch_record_status'] ?? 'Not recorded') ?> · This historical value does not make the proposal active in the current repository.</p>
            <p><strong>Affected board:</strong> <?= $escape($proposal['board_name'] ?? $proposal['board_id'] ?? '') ?> · <strong>Subject:</strong> <?= $escape($proposal['subject_name'] ?? $proposal['subject_id'] ?? '') ?></p>
            <p><strong>Specific claim to review:</strong> <?= $escape($proposalReason['specific_claim_to_review'] ?? 'No proposition was recorded.') ?></p>
            <p><strong>Item-level source locator:</strong> <?= $escape($proposal['source_support_locator'] ?? 'Not recorded') ?></p>
            <p><strong>Requested action:</strong> <?= $escape($proposalReason['change_requested'] ?? 'Independent review required.') ?></p>
            <p><strong>Canonical ID match:</strong> <?= !empty($proposalComparison['canonical_id_present']) ? 'Present — reconcile before any action' : 'None' ?> · <strong>Exact stem match:</strong> <?= $escape($proposalComparison['exact_stem_match_question_id'] ?? 'None') ?> · <strong>Current eligibility:</strong> <?= $escape($proposalComparison['current_production_eligibility'] ?? 'Not available') ?></p>
            <p><strong>Current taxonomy check:</strong> <?= $escape($proposalTaxonomyState) ?> · Proposed taxonomy remains inactive pending review.</p>
            <p><strong>Conflicting information:</strong> No canonical ID or exact-stem conflict was found at the B713 baseline. No independent source-conflict adjudication is claimed.</p>
            <?php if (!empty($proposal['secondary_flags'])): ?><p><strong>Review flags:</strong> <?= $escape(implode(', ', array_map('strval', (array) $proposal['secondary_flags']))) ?></p><?php endif; ?>
            <?php if (is_array($proposal['currentness_evidence'] ?? null)): ?>
                <?php $currentness = $proposal['currentness_evidence']; $currentnessUrl = (string) ($currentness['url'] ?? ''); ?>
                <p><strong>Source currentness check:</strong> <?= $escape($currentness['finding'] ?? '') ?><?php if (in_array(strtolower((string) parse_url($currentnessUrl, PHP_URL_SCHEME)), ['http', 'https'], true)): ?> <a href="<?= $escape($currentnessUrl) ?>" target="_blank" rel="noopener noreferrer">Open official PRC calendar</a><?php endif; ?></p>
            <?php endif; ?>
            <h3>Academic review fields</h3>
            <ul><?php foreach ((array) ($proposal['academic_review']['fields'] ?? []) as $field => $value): ?><li><strong><?= $escape($field) ?>:</strong> <?= $escape($value) ?></li><?php endforeach; ?></ul>
            <p>Reviewer identity is not recorded. An authenticated subject expert must make any future decision through the established review process.</p>
            <h3>Recovered source provenance</h3>
            <p>Source commit <code><?= $escape($proposalProvenance['original_source_commit_sha'] ?? '') ?></code> · <code><?= $escape($proposalProvenance['source_question_path'] ?? '') ?></code> · source worktree was dirty; this item itself came from the committed tree and did not overlap the dirty counter delta.</p>
        </section>
        <section class="card" aria-labelledby="proposal-study-heading">
            <h2 id="proposal-study-heading">Proposed study relationship</h2>
            <p><strong>Material:</strong> <?= $escape($proposalStudy['material_title'] ?? $proposalStudy['material_id'] ?? '') ?> (<code><?= $escape($proposalStudy['material_id'] ?? '') ?></code>)</p>
            <p><strong>Section:</strong> <?= $escape($proposalStudy['section_heading'] ?? $proposalStudy['section_id'] ?? '') ?> (<code><?= $escape($proposalStudy['section_id'] ?? '') ?></code>)</p>
            <p><strong>Current library state:</strong> <?= !empty($proposalStudy['current_section_exists']) ? 'The section exists; the question link remains proposal-only.' : 'The material section is absent from the current library and must be reconciled before a live link is added.' ?></p>
            <?php if (trim((string) ($proposalStudyUrl ?? '')) !== ''): ?><p><a class="button button-outline" href="<?= $escape($proposalStudyUrl) ?>">Open exact current study section</a> · This existing study link does not make the proposal eligible for practice.</p><?php endif; ?>
            <p><?= nl2br($escape($proposalStudy['section_body'] ?? 'No source-branch section text was recorded.')) ?></p>
            <?php if (trim((string) ($proposalStudy['lesson_passage_in_review_packet'] ?? '')) !== ''): ?><h3>Lesson passage in the review packet</h3><p><?= nl2br($escape($proposalStudy['lesson_passage_in_review_packet'])) ?></p><?php endif; ?>
        </section>
    <?php endif; ?>

    <?php if (!empty($selectionHolds)): ?>
        <section class="card" aria-labelledby="selection-hold-heading">
            <h2 id="selection-hold-heading">Question selection hold</h2>
            <p>This is an exam-pool eligibility hold. It does not change the canonical answer key or historical attempts.</p>
            <?php foreach ($selectionHolds as $hold): ?>
                <p><strong><?= $escape($hold['board_id'] ?? 'Board not recorded') ?></strong>: <?= $escape($hold['reason'] ?? 'Reason not recorded') ?></p>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>

    <section class="card">
        <h2>Question</h2>
        <p class="question-stem"><?= nl2br($escape($question['question'] ?? 'Question text is missing.')) ?></p>
        <?php if ($choices === []): ?>
            <p class="empty-state">No answer choices are recorded.</p>
        <?php else: ?>
            <ol class="question-choice-list">
                <?php foreach ($choices as $choice): ?>
                    <?php $choiceText = is_scalar($choice) ? (string) $choice : ''; ?>
                    <li>
                        <?= nl2br($escape($choiceText)) ?>
                        <?php if (is_scalar($answerKey) && (string) $answerKey === $choiceText): ?>
                            <strong class="badge badge-info"><?= $isProposal ? 'Proposed answer; not a key' : 'Stored answer key' ?></strong>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>
        <p><strong><?= $isProposal ? 'Proposed answer (not a canonical answer key)' : 'Stored answer metadata' ?>:</strong> <code><?= $escape(is_scalar($answerKey) ? $answerKey : json_encode($answerKey, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></code></p>
        <h3>Rationale</h3>
        <p><?= trim((string) $explanation) !== '' ? nl2br($escape($explanation)) : 'No explanation is recorded.' ?></p>
    </section>

    <section class="card">
        <h2>Record and academic review state</h2>
        <div class="ui-metadata-grid">
            <?php foreach (['status' => 'Repository status', 'difficulty' => 'Difficulty'] as $key => $label): ?>
                <div class="ui-metadata-item"><small><?= $escape($label) ?></small><strong><?= $escape($question[$key] ?? 'Not recorded') ?></strong></div>
            <?php endforeach; ?>
            <div class="ui-metadata-item"><small>Question academic review</small><strong><?= $escape($isProposal ? ($proposal['current_status'] ?? 'PENDING') : (is_scalar($explicitReview) && trim((string) $explicitReview) !== '' ? $explicitReview : 'UNKNOWN / NOT RECORDED')) ?></strong></div>
            <div class="ui-metadata-item"><small>Independent approval</small><strong>Not inferred from repository status</strong></div>
        </div>
        <h3>Canonical taxonomy</h3>
        <div class="chip-list">
            <?php foreach (['board_id' => 'Board', 'subject_id' => 'Subject', 'domain_id' => 'Domain', 'topic_id' => 'Topic', 'concept_id' => 'Concept'] as $key => $label): ?>
                <?php if (trim((string) ($taxonomy[$key] ?? '')) !== ''): ?>
                    <span class="chip"><small><?= $escape($label) ?></small> <?= $escape($taxonomy[$key]) ?></span>
                <?php endif; ?>
            <?php endforeach; ?>
            <?php if ($taxonomy === []): ?><p class="muted-note">Not classified</p><?php endif; ?>
        </div>
        <?php if (!$isProposal): ?><h3>Usage statistics</h3>
        <div class="ui-metadata-grid">
            <div class="ui-metadata-item"><small>Times used</small><strong><?= (int) ($question['timesUsed'] ?? 0) ?></strong></div>
            <div class="ui-metadata-item"><small>Correct</small><strong><?= (int) ($question['timesCorrect'] ?? 0) ?></strong></div>
            <div class="ui-metadata-item"><small>Incorrect</small><strong><?= (int) ($question['timesIncorrect'] ?? 0) ?></strong></div>
        </div>
        <?php else: ?><p>This proposal has no current learner usage statistics because it is not in the canonical repository.</p><?php endif; ?>
    </section>

    <section class="card" aria-labelledby="references-heading">
        <h2 id="references-heading">Sources and study relationships</h2>
        <p>Reachability comes from persisted checks only; it does not establish that a source supports the answer or that the item has academic approval.</p>
        <p><strong>Source URL audit:</strong> <?= $escape($sourceAuditStatus ?? 'UNAVAILABLE') ?><?php if (!empty($sourceAuditGeneratedAt)): ?> · checked <?= $escape($sourceAuditGeneratedAt) ?><?php endif; ?></p>
        <?php if (!empty($sources)): ?>
            <ul>
                <?php foreach ($sources as $sourceItem): ?>
                    <?php $record = is_array($sourceItem['record'] ?? null) ? $sourceItem['record'] : null; $url = (string) ($record['url'] ?? ''); $safeUrl = in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true); $check = is_array($sourceItem['url_status'] ?? null) ? $sourceItem['url_status'] : null; ?>
                    <li>
                        <strong><?= $escape($record['title'] ?? 'Unresolved source ID') ?></strong>
                        (<code><?= $escape($sourceItem['id'] ?? '') ?></code>)
                        — <?= $escape($record['authority'] ?? 'Authority not recorded') ?>.
                        <?php if ($safeUrl): ?> <a href="<?= $escape($url) ?>" target="_blank" rel="noopener noreferrer">Open source</a><?php endif; ?>
                        <br><small><?= $isProposal ? 'B695 evidence status: ' . $escape($sourceItem['evidence_status'] ?? 'REVIEW_PENDING') . ' · URL reachability not checked in B713' : 'Access: ' . $escape($check['status'] ?? ('UNKNOWN (' . ($sourceAuditStatus ?? 'UNAVAILABLE') . ')')) . (isset($check['http_status']) && $check['http_status'] !== null ? ' · HTTP ' . $escape($check['http_status']) : '') ?><?= !empty($record['version']) ? ' · ' . $escape($record['version']) : '' ?></small>
                        <?php if ($isProposal && !empty($sourceItem['limitations'])): ?><ul><?php foreach ($sourceItem['limitations'] as $limitation): ?><li><small><?= $escape($limitation) ?></small></li><?php endforeach; ?></ul><?php endif; ?>
                        <?php if ($record === null): ?><span class="badge badge-warning">Source ID missing from registry</span><?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p class="empty-state">No registered source IDs are attached directly or through study relationships.</p>
        <?php endif; ?>

        <h3>Exact lesson-section relationships</h3>
        <?php if ($exactLinks !== []): ?>
            <ul><?php foreach ($exactLinks as $reference): ?>
                <?php
                $rawLink = null;
                foreach (($studyLinkRecords ?? []) as $candidate) {
                    if (($candidate['material_id'] ?? '') === ($reference['material_id'] ?? '')
                        && trim((string) ($candidate['section_id'] ?? $candidate['lesson_section_id'] ?? '')) === trim((string) ($reference['section_id'] ?? ''))) {
                        $rawLink = $candidate;
                        break;
                    }
                }
                ?>
                <li><a href="<?= $escape($reference['url'] ?? '') ?>"><?= $escape($reference['title'] ?? $reference['material_id'] ?? '') ?> — <?= $escape($reference['section_id'] ?? '') ?></a> · Review state: <?= $escape($rawLink['review_status'] ?? $rawLink['academic_review_status'] ?? 'UNKNOWN / NOT RECORDED') ?></li>
            <?php endforeach; ?></ul>
        <?php else: ?>
            <p>No exact lesson-section relationship is recorded.</p>
        <?php endif; ?>

        <h3>Source-only study relationships</h3>
        <?php if ($sourceOnlyLinks !== []): ?>
            <ul><?php foreach ($sourceOnlyLinks as $reference): ?>
                <li><a href="<?= $escape($reference['url'] ?? '') ?>"><?= $escape($reference['title'] ?? $reference['material_id'] ?? '') ?></a> · Source-only; no precise lesson section is asserted.</li>
            <?php endforeach; ?></ul>
        <?php else: ?>
            <p>No source-only study relationship is recorded.</p>
        <?php endif; ?>
    </section>
</div>
