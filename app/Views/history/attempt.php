<?php
$date = \App\Services\Learning\LearningHistoryService::dateOf($attempt);
$topic = \App\Services\Shared\TaxonomyStorageService::label(
    'taxonomy/topics',
    \App\Services\Learning\LearningHistoryService::topicOf($attempt)
);
$board = \App\Services\Shared\TaxonomyStorageService::label('boards', $attempt['board'] ?? '');
$subject = \App\Services\Shared\TaxonomyStorageService::label('subjects', $attempt['subject'] ?? '');
$mode = is_scalar($attempt["mode"] ?? null) ? (string) $attempt["mode"] : "practice";
$modeLabel = $mode === 'exam' ? 'Exam simulation' : ucfirst($mode !== '' ? $mode : 'practice');
$timerMode = is_scalar($attempt['timer_mode'] ?? null) ? (string) $attempt['timer_mode'] : '';
$timeLimitMinutes = is_numeric($attempt['time_limit_minutes'] ?? null)
    ? max(0, (int) $attempt['time_limit_minutes'])
    : 0;
$score = (int) ($attempt["score"] ?? 0);
$total = (int) ($attempt["total"] ?? 0);
$percentage = (int) ($attempt["percentage"] ?? 0);
$attemptId = is_scalar($attempt["id"] ?? null)
    ? trim((string) $attempt["id"])
    : "";
?>
<article class="quiz-history-item">
    <h3><?= htmlspecialchars($topic) ?></h3>
    <?php if ($board !== '' || $subject !== ''): ?><p class="muted-note"><?= htmlspecialchars(implode(' · ', array_filter([$board, $subject]))) ?></p><?php endif; ?>
    <p>
        <span class="badge badge-info"><?= htmlspecialchars($modeLabel, ENT_QUOTES, 'UTF-8') ?></span>
        <strong><?= \App\Support\Presentation\UiFormatter::formatPercentage($percentage) ?></strong> —
        <?php if ($total > 0): ?><?= $score ?>/<?= $total ?> · <?php endif; ?><?= $percentage ?>%
    </p>
    <?php if ($mode === 'exam'): ?><p class="muted-note"><?= $timerMode === 'timed' ? 'Timed simulation' . ($timeLimitMinutes > 0 ? ' · ' . $timeLimitMinutes . ' min' : '') : 'Untimed simulation' ?></p><?php endif; ?>
    <?php if ($date !== null): ?>
        <p><?= htmlspecialchars(\App\Support\Presentation\UiFormatter::formatDateTime($date)) ?></p>
    <?php endif; ?>
    <?php if ($attemptId !== ""): ?>
        <p><a class="button button-outline" href="/history/result?id=<?= rawurlencode($attemptId) ?>">View result</a></p>
    <?php else: ?>
        <p class="muted-note">Detailed review is unavailable for this legacy attempt.</p>
    <?php endif; ?>
</article>
