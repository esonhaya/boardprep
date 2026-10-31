<?php
$reports = is_array($reports ?? null) ? $reports : [];
$escape = static fn(mixed $value): string => htmlspecialchars(is_scalar($value) ? (string) $value : '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<div class="page-container learner-question-reports">
    <nav class="breadcrumbs" aria-label="Breadcrumb"><ol><li><a href="/progress">Progress</a></li><li aria-current="page">My question reports</li></ol></nav>
    <section class="page-header"><div><p class="eyebrow">Question feedback</p><h1>My question reports</h1><p>Reports are private to your current account or browser session. Review status is shown only after an authorized reviewer changes it.</p></div><a class="button button-outline" href="/history">Quiz history</a></section>
    <?php if (is_string($error ?? null)): ?><p class="alert alert-danger" role="alert"><?= $escape($error) ?></p>
    <?php elseif ($reports === []): ?><div class="empty-state" role="status"><h2>No reports yet</h2><p>After a quiz, use “Report this question” in answer review if something needs attention.</p><a class="button button-outline" href="/history">Open quiz history</a></div>
    <?php else: ?><section aria-labelledby="question-report-list-heading"><h2 id="question-report-list-heading">Recent reports</h2><p class="muted-note">Showing up to 50 of your most recent reports.</p><div class="record-list">
        <?php foreach ($reports as $report): if (!is_array($report)) continue; ?>
            <article class="card learner-question-report-record">
                <div class="section-heading"><h3>Question <?= $escape($report['question_id'] ?? '') ?></h3><span class="badge badge-info"><?= $escape(str_replace('_', ' ', (string) ($report['status'] ?? 'SUBMITTED'))) ?></span></div>
                <p><strong>Concern:</strong> <?= $escape($report['type_label'] ?? '') ?></p>
                <?php if (trim((string) ($report['description'] ?? '')) !== ''): ?><p><?= nl2br($escape($report['description'])) ?></p><?php endif; ?>
                <p class="muted-note">Submitted <?= $escape($report['created_at'] ?? '') ?><?php if (($report['status'] ?? 'SUBMITTED') !== 'SUBMITTED'): ?> · Status updated <?= $escape($report['status_changed_at'] ?? '') ?><?php endif; ?></p>
            </article>
        <?php endforeach; ?>
    </div></section><?php endif; ?>
</div>
