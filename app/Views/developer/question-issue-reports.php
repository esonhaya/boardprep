<?php
$queue = is_array($queue ?? null) ? $queue : [];
$items = is_array($queue['items'] ?? null) ? $queue['items'] : [];
$csrfToken = is_string($csrfToken ?? null) ? $csrfToken : '';
$status = is_string($queue['status'] ?? null) ? $queue['status'] : 'OPEN';
$escape = static fn(mixed $value): string => htmlspecialchars(is_scalar($value) ? (string) $value : '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<section class="page-header"><div><p class="eyebrow">Scoped reviewer queue</p><h1>Learner Question Reports</h1><p>Learner concerns are separate from academic proposals. Status updates do not alter canonical questions, answer keys, eligibility, source reviews, or academic decisions. Learner identity details are not displayed.</p></div><a class="button button-outline" href="/developer/academic-review">Academic review</a></section>
<?php if (is_string($error ?? null)): ?><p class="alert alert-danger" role="alert"><?= $escape($error) ?></p><?php endif; ?>
<section class="card" aria-labelledby="question-report-queue-heading">
    <div class="section-heading"><div><h2 id="question-report-queue-heading"><?= (int) ($queue['total'] ?? 0) ?> reports</h2><p>Only reports within your qualified board and subject scopes are shown.</p></div></div>
    <form class="ui-filter-bar" method="get" action="/developer/question-reports"><label for="question-report-status">Status</label><select id="question-report-status" name="status"><?php foreach (['OPEN' => 'Open reports', 'SUBMITTED' => 'Submitted', 'UNDER_REVIEW' => 'Under review', 'RESOLVED' => 'Resolved', 'REJECTED' => 'Rejected'] as $key => $label): ?><option value="<?= $escape($key) ?>"<?= $status === $key ? ' selected' : '' ?>><?= $escape($label) ?></option><?php endforeach; ?></select><button class="button button-outline" type="submit">Filter</button></form>
    <?php if ($items === []): ?><p class="empty-state" role="status">No reports are available in this review scope and status.</p>
    <?php else: ?><div class="record-list"><?php foreach ($items as $item): if (!is_array($item)) continue; ?>
        <article class="card learner-question-report-record">
            <div class="section-heading"><h3>Question <?= $escape($item['question_id'] ?? '') ?></h3><span class="badge badge-info"><?= $escape(str_replace('_', ' ', (string) ($item['status'] ?? 'SUBMITTED'))) ?></span></div>
            <p><strong>Scope:</strong> <?= $escape($item['board_id'] ?? '') ?> · <?= $escape($item['subject_id'] ?? '') ?></p>
            <p><strong>Result reference:</strong> <?= $escape($item['attempt_id'] ?? '') ?: 'Not linked in this legacy report' ?></p>
            <p><strong>Concern:</strong> <?= $escape($item['type_label'] ?? '') ?></p>
            <?php if (trim((string) ($item['description'] ?? '')) !== ''): ?><p><?= nl2br($escape($item['description'])) ?></p><?php endif; ?>
            <p class="muted-note">Submitted <?= $escape($item['created_at'] ?? '') ?> · question version <?= $escape(substr((string) ($item['question_version_sha256'] ?? ''), 0, 16)) ?></p>
            <?php $statusHistory = is_array($item['status_history'] ?? null) ? $item['status_history'] : []; if ($statusHistory !== []): ?><ol class="question-report-status-history" aria-label="Question report status history"><?php foreach ($statusHistory as $transition): if (!is_array($transition)) continue; $from = strtoupper((string) ($transition['from'] ?? '')); $to = strtoupper((string) ($transition['to'] ?? $transition['status'] ?? '')); ?><li><?= $from !== '' ? $escape(str_replace('_', ' ', $from)) . ' → ' : '' ?><?= $escape(str_replace('_', ' ', $to)) ?> · <?= $escape($transition['at'] ?? '') ?></li><?php endforeach; ?></ol><?php endif; ?>
            <?php if (($item['status'] ?? '') === 'SUBMITTED'): ?><form method="post" action="/developer/question-reports/update" class="page-actions"><input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>"><input type="hidden" name="report_id" value="<?= $escape($item['id'] ?? '') ?>"><button class="button button-outline" name="status" value="UNDER_REVIEW">Mark under review</button><button class="button button-outline" name="status" value="REJECTED">Reject report</button></form>
            <?php elseif (($item['status'] ?? '') === 'UNDER_REVIEW'): ?><form method="post" action="/developer/question-reports/update" class="page-actions"><input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>"><input type="hidden" name="report_id" value="<?= $escape($item['id'] ?? '') ?>"><button class="button button-green" name="status" value="RESOLVED">Mark resolved</button><button class="button button-outline" name="status" value="REJECTED">Reject report</button></form><?php endif; ?>
        </article>
    <?php endforeach; ?></div>
        <?php if ((int) ($queue['pages'] ?? 1) > 1): ?><nav class="pagination" aria-label="Question report pages"><?php if ((int) ($queue['page'] ?? 1) > 1): ?><a class="button button-outline" rel="prev" href="/developer/question-reports?status=<?= rawurlencode($status) ?>&amp;page=<?= (int) $queue['page'] - 1 ?>">Previous</a><?php endif; ?><span>Page <?= (int) $queue['page'] ?> of <?= (int) $queue['pages'] ?></span><?php if ((int) ($queue['page'] ?? 1) < (int) ($queue['pages'] ?? 1)): ?><a class="button button-outline" rel="next" href="/developer/question-reports?status=<?= rawurlencode($status) ?>&amp;page=<?= (int) $queue['page'] + 1 ?>">Next</a><?php endif; ?></nav><?php endif; ?>
    <?php endif; ?>
</section>
