<?php
$date = \App\Services\Learning\LearningHistoryService::dateOf($attempt);
$topic = \App\Services\Learning\LearningHistoryService::topicOf($attempt);
$mode = is_scalar($attempt["mode"] ?? null) ? (string) $attempt["mode"] : "practice";
$score = (int) ($attempt["score"] ?? 0);
$total = (int) ($attempt["total"] ?? 0);
$percentage = (int) ($attempt["percentage"] ?? 0);
?>
<article class="quiz-history-item">
    <h3><?= htmlspecialchars($topic) ?></h3>
    <p>
        <span class="badge badge-info"><?= htmlspecialchars(ucfirst($mode !== "" ? $mode : "practice")) ?></span>
        <strong><?= $percentage ?>%</strong> —
        <?php if ($total > 0): ?><?= $score ?>/<?= $total ?> · <?php endif; ?><?= $percentage ?>%
    </p>
    <?php if ($date !== null): ?>
        <p><?= htmlspecialchars($date) ?></p>
    <?php endif; ?>
</article>
