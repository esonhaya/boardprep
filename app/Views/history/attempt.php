<?php
$date = \App\Services\Learning\LearningHistoryService::dateOf($attempt);
$topic = \App\Services\Learning\LearningHistoryService::topicOf($attempt);
$mode = is_scalar($attempt["mode"] ?? null) ? (string) $attempt["mode"] : "practice";
$score = (int) ($attempt["score"] ?? 0);
$total = (int) ($attempt["total"] ?? 0);
$percentage = (int) ($attempt["percentage"] ?? 0);
$attemptId = is_scalar($attempt["id"] ?? null)
    ? trim((string) $attempt["id"])
    : "";
?>
<article class="quiz-history-item">
    <h3><?= htmlspecialchars($topic) ?></h3>
    <p>
        <span class="badge badge-info"><?= htmlspecialchars(ucfirst($mode !== "" ? $mode : "practice")) ?></span>
        <strong><?= \App\Support\Presentation\UiFormatter::formatPercentage($percentage) ?></strong> —
        <?php if ($total > 0): ?><?= $score ?>/<?= $total ?> · <?php endif; ?><?= $percentage ?>%
    </p>
    <?php if ($date !== null): ?>
        <p><?= htmlspecialchars(\App\Support\Presentation\UiFormatter::formatDateTime($date)) ?></p>
    <?php endif; ?>
    <?php if ($attemptId !== ""): ?>
        <p><a class="button button-outline" href="/history/result?id=<?= rawurlencode($attemptId) ?>">View result</a></p>
    <?php else: ?>
        <p class="muted-note">Detailed review is unavailable for this legacy attempt.</p>
    <?php endif; ?>
</article>
