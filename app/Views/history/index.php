<?php
$history = $history ?? [];
$summary = $summary ?? [];
$historyTotal = max(0, (int) ($historyTotal ?? count($history)));
$historyAllTotal = max($historyTotal, (int) ($historyAllTotal ?? $historyTotal));
$historyPage = max(1, (int) ($historyPage ?? 1));
$historyPages = max(1, (int) ($historyPages ?? 1));
$historyPageSize = max(1, (int) ($historyPageSize ?? 20));
$historyFirst = $historyTotal === 0 ? 0 : (($historyPage - 1) * $historyPageSize) + 1;
$historyLast = min($historyTotal, ($historyPage - 1) * $historyPageSize + count($history));
$historyBoardOptions = is_array($historyBoardOptions ?? null) ? $historyBoardOptions : [];
$historySubjectOptions = is_array($historySubjectOptions ?? null) ? $historySubjectOptions : [];
$historyBoardFilter = is_string($historyBoardFilter ?? null) ? $historyBoardFilter : '';
$historySubjectFilter = is_string($historySubjectFilter ?? null) ? $historySubjectFilter : '';
$historyFiltersActive = ($historyFiltersActive ?? false) === true;
$historyFiltersAdjusted = ($historyFiltersAdjusted ?? false) === true;
$historyFilterQuery = is_string($historyFilterQuery ?? null) ? $historyFilterQuery : '';
$historyFilterSuffix = $historyFilterQuery === '' ? '' : '&' . $historyFilterQuery;
$historyReturnUrl = is_string($historyReturnUrl ?? null) ? $historyReturnUrl : '/history';
$hasCompletedAttempts = (int) ($summary['completedAttempts'] ?? 0) > 0;
$averageScore = $hasCompletedAttempts ? (int) ($summary['averageScore'] ?? 0) . '%' : '—';
$bestScore = $hasCompletedAttempts ? (int) ($summary['bestScore'] ?? 0) . '%' : '—';
$showHistoryFilters = $historyAllTotal > 0 && ($historyBoardOptions !== [] || $historySubjectOptions !== []);
$historyPageUrl = static fn(int $page): string => '/history?page=' . $page . $historyFilterSuffix;
?>

<div class="history-page">
<section class="page-header">
    <div><p class="eyebrow">Learning history</p><h1>Quiz history</h1><p>Completed quizzes, newest first.</p></div>
    <div class="page-actions"><a class="button button-outline" href="/mistake-notebook">Mistake Notebook</a><a class="button button-outline" href="/quiz">Start practice</a></div>
</section>

<section class="history-summary" aria-label="Quiz summary for matching attempts">
    <div><span class="stat-label">Completed quizzes</span><strong><?= (int) ($summary["completedAttempts"] ?? 0) ?></strong></div>
    <div><span class="stat-label">Average score</span><strong><?= htmlspecialchars($averageScore, ENT_QUOTES, 'UTF-8') ?></strong><?php if (!$hasCompletedAttempts): ?><small class="muted-note">No completed quizzes yet</small><?php endif; ?></div>
    <div><span class="stat-label">Best score</span><strong><?= htmlspecialchars($bestScore, ENT_QUOTES, 'UTF-8') ?></strong><?php if (!$hasCompletedAttempts): ?><small class="muted-note">No completed quizzes yet</small><?php endif; ?></div>
</section>

<?php if ($showHistoryFilters): ?>
    <form method="get" action="/history" class="history-filter-form" aria-label="Filter quiz history">
        <label for="history-board-filter">Board
            <select id="history-board-filter" name="board">
                <option value="">All boards</option>
                <?php foreach ($historyBoardOptions as $option): ?>
                    <option value="<?= htmlspecialchars((string) $option['value'], ENT_QUOTES, 'UTF-8') ?>" <?= $historyBoardFilter === $option['value'] ? 'selected' : '' ?>><?= htmlspecialchars((string) $option['label'], ENT_QUOTES, 'UTF-8') ?> (<?= (int) $option['count'] ?>)</option>
                <?php endforeach; ?>
            </select>
        </label>
        <label for="history-subject-filter">Subject
            <select id="history-subject-filter" name="subject">
                <option value="">All subjects</option>
                <?php foreach ($historySubjectOptions as $option): ?>
                    <option value="<?= htmlspecialchars((string) $option['value'], ENT_QUOTES, 'UTF-8') ?>" <?= $historySubjectFilter === $option['value'] ? 'selected' : '' ?>><?= htmlspecialchars((string) $option['label'], ENT_QUOTES, 'UTF-8') ?> (<?= (int) $option['count'] ?>)</option>
                <?php endforeach; ?>
            </select>
        </label>
        <div class="history-filter-actions">
            <button class="button button-green" type="submit">Apply filters</button>
            <?php if ($historyFiltersActive): ?><a class="button button-outline" href="/history">Clear filters</a><?php endif; ?>
        </div>
    </form>
<?php endif; ?>

<?php if ($historyFiltersAdjusted): ?>
    <p class="history-filter-status" role="status" aria-live="polite">An unrecognized board or subject filter was ignored.</p>
<?php elseif ($historyFiltersActive && $historyTotal > 0): ?>
    <p class="history-filter-status" role="status" aria-live="polite">Showing <?= $historyTotal ?> completed quiz<?= $historyTotal === 1 ? '' : 'zes' ?> for these filters. The summary above uses this filtered history.</p>
<?php elseif ($historyFiltersActive): ?>
    <p class="history-filter-status" role="status" aria-live="polite">No completed quizzes match these filters.</p>
<?php endif; ?>

<?php if (!$historyFiltersActive && !$historyFiltersAdjusted && $historyTotal > 0 && $historyPages === 1): ?>
    <p class="history-results-status" role="status" aria-live="polite">Showing <?= $historyTotal ?> <?= $historyTotal === 1 ? 'completed quiz' : 'completed quizzes' ?>.</p>
<?php endif; ?>

<?php if ($historyTotal === 0): ?>
    <?php if ($historyAllTotal > 0): ?>
        <div class="empty-state"><h2>No completed quizzes match these filters</h2><p>Clear the filters to see all <?= $historyAllTotal ?> saved quizzes.</p><p><a class="button button-outline" href="/history">Clear filters</a></p></div>
    <?php else: ?>
        <div class="empty-state"><h2>No completed quizzes yet</h2><p>Your completed practice sessions will appear here.</p><p><a class="button button-green" href="/quiz">Set up your first practice quiz</a></p></div>
    <?php endif; ?>
<?php else: ?>
    <?php if ($historyPages > 1): ?><nav class="history-pagination" aria-label="Quiz history pages"><p role="status">Showing <?= $historyFirst ?>–<?= $historyLast ?> of <?= $historyTotal ?> completed quizzes · Page <?= $historyPage ?> of <?= $historyPages ?></p><div><?php if ($historyPage > 1): ?><a class="button button-outline" rel="prev" href="<?= htmlspecialchars($historyPageUrl($historyPage - 1), ENT_QUOTES, 'UTF-8') ?>">Previous</a><?php endif; ?><?php if ($historyPage < $historyPages): ?><a class="button button-outline" rel="next" href="<?= htmlspecialchars($historyPageUrl($historyPage + 1), ENT_QUOTES, 'UTF-8') ?>">Next</a><?php endif; ?></div></nav><?php endif; ?>
    <?php foreach ($history as $attempt): ?>
        <?php require __DIR__ . "/attempt.php"; ?>
    <?php endforeach; ?>
    <?php if ($historyPages > 1): ?><nav class="history-pagination" aria-label="Quiz history pages"><p role="status">Showing <?= $historyFirst ?>–<?= $historyLast ?> of <?= $historyTotal ?> completed quizzes · Page <?= $historyPage ?> of <?= $historyPages ?></p><div><?php if ($historyPage > 1): ?><a class="button button-outline" rel="prev" href="<?= htmlspecialchars($historyPageUrl($historyPage - 1), ENT_QUOTES, 'UTF-8') ?>">Previous</a><?php endif; ?><?php if ($historyPage < $historyPages): ?><a class="button button-outline" rel="next" href="<?= htmlspecialchars($historyPageUrl($historyPage + 1), ENT_QUOTES, 'UTF-8') ?>">Next</a><?php endif; ?></div></nav><?php endif; ?>
<?php endif; ?>

<hr>
<nav class="related-navigation" aria-label="Learning pages">
    <a href="/dashboard">Dashboard</a>
    <a href="/progress">Progress</a>
    <a href="/study">Study Plan</a>
</nav>
</div>
