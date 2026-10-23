<?php
$taxonomy = is_array($question['taxonomy'] ?? null) ? $question['taxonomy'] : [];
$choices = is_array($question['choices'] ?? null) ? $question['choices'] : [];
$answerKey = $question['answer'] ?? $question['correctAnswer'] ?? $question['correct_answer'] ?? null;
$explanation = $question['explanation'] ?? $question['rationale'] ?? '';
$escape = static fn(mixed $value): string => htmlspecialchars((string) (is_scalar($value) ? $value : ''), ENT_QUOTES, 'UTF-8');
$explicitReview = $question['academic_review_status'] ?? $question['review_status'] ?? $question['academic_status'] ?? null;
$exactLinks = array_values(array_filter($studyReferences ?? [], static fn(array $reference): bool => trim((string) ($reference['section_id'] ?? '')) !== ''));
$sourceOnlyLinks = array_values(array_filter($studyReferences ?? [], static fn(array $reference): bool => trim((string) ($reference['section_id'] ?? '')) === ''));
?>
<div class="ui-page-detail" data-ui-archetype="detail">
    <section class="page-header">
        <div>
            <p class="eyebrow">Question inspector</p>
            <h1>Question #<?= $escape($question['id'] ?? '') ?></h1>
            <p>Inspect canonical content, board scope, references, and review state.</p>
        </div>
        <div class="page-actions">
            <a class="button secondary" href="<?= $escape($returnUrl ?? '/question-editor') ?>">Back to repository</a>
            <a class="button" href="/question-editor/edit?id=<?= rawurlencode((string) ($question['id'] ?? '')) ?>&amp;return=<?= rawurlencode((string) ($returnUrl ?? '/question-editor')) ?>">Edit question</a>
        </div>
    </section>

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
                            <strong class="badge badge-info">Stored answer key</strong>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>
        <p><strong>Stored answer metadata:</strong> <code><?= $escape(is_scalar($answerKey) ? $answerKey : json_encode($answerKey, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></code></p>
        <h3>Rationale</h3>
        <p><?= trim((string) $explanation) !== '' ? nl2br($escape($explanation)) : 'No explanation is recorded.' ?></p>
    </section>

    <section class="card">
        <h2>Record and academic review state</h2>
        <div class="ui-metadata-grid">
            <?php foreach (['status' => 'Repository status', 'difficulty' => 'Difficulty'] as $key => $label): ?>
                <div class="ui-metadata-item"><small><?= $escape($label) ?></small><strong><?= $escape($question[$key] ?? 'Not recorded') ?></strong></div>
            <?php endforeach; ?>
            <div class="ui-metadata-item"><small>Question academic review</small><strong><?= $escape(is_scalar($explicitReview) && trim((string) $explicitReview) !== '' ? $explicitReview : 'UNKNOWN / NOT RECORDED') ?></strong></div>
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
        <h3>Usage statistics</h3>
        <div class="ui-metadata-grid">
            <div class="ui-metadata-item"><small>Times used</small><strong><?= (int) ($question['timesUsed'] ?? 0) ?></strong></div>
            <div class="ui-metadata-item"><small>Correct</small><strong><?= (int) ($question['timesCorrect'] ?? 0) ?></strong></div>
            <div class="ui-metadata-item"><small>Incorrect</small><strong><?= (int) ($question['timesIncorrect'] ?? 0) ?></strong></div>
        </div>
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
                        <br><small>Access: <?= $escape($check['status'] ?? ('UNKNOWN (' . ($sourceAuditStatus ?? 'UNAVAILABLE') . ')')) ?><?= isset($check['http_status']) && $check['http_status'] !== null ? ' · HTTP ' . $escape($check['http_status']) : '' ?><?= !empty($record['version']) ? ' · ' . $escape($record['version']) : '' ?></small>
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
