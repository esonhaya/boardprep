<?php
$history = $history ?? [];
$summary = $summary ?? [];
$hasCompletedAttempts = (int) ($summary['completedAttempts'] ?? 0) > 0;
$averageScore = $hasCompletedAttempts ? (int) ($summary['averageScore'] ?? 0) . '%' : '—';
$bestScore = $hasCompletedAttempts ? (int) ($summary['bestScore'] ?? 0) . '%' : '—';
$historyPage = max(1, (int) ($historyPage ?? 1));
$historyPages = max(1, (int) ($historyPages ?? 1));
$historyTotal = max(0, (int) ($historyTotal ?? count($history)));
$historyPageSize = max(1, (int) ($historyPageSize ?? 20));
$historyFirst = $historyTotal === 0 ? 0 : (($historyPage - 1) * $historyPageSize) + 1;
$historyLast = min($historyTotal, ($historyPage - 1) * $historyPageSize + count($history));
?>

<div class="history-page">
<section class="page-header"><div><p class="eyebrow">Learning history</p><h1>Quiz history</h1><p>Completed quizzes, newest first.</p></div><div class="page-actions"><a class="button button-outline" href="/mistake-notebook">Mistake Notebook</a><a class="button button-outline" href="/quiz">Start practice</a></div></section>

<section class="history-summary" aria-label="Quiz summary">
    <div><span class="stat-label">Completed quizzes</span><strong><?= (int) ($summary["completedAttempts"] ?? 0) ?></strong></div>
    <div><span class="stat-label">Average score</span><strong><?= htmlspecialchars($averageScore, ENT_QUOTES, 'UTF-8') ?></strong><?php if (!$hasCompletedAttempts): ?><small class="muted-note">No completed quizzes yet</small><?php endif; ?></div>
    <div><span class="stat-label">Best score</span><strong><?= htmlspecialchars($bestScore, ENT_QUOTES, 'UTF-8') ?></strong><?php if (!$hasCompletedAttempts): ?><small class="muted-note">No completed quizzes yet</small><?php endif; ?></div>
</section>

<?php if ($history === []): ?>
    <div class="empty-state"><h2>No completed quizzes yet</h2><p>Your completed practice sessions will appear here.</p>
    <p><a class="button button-green" href="/quiz">Set up your first practice quiz</a></p>
    </div>
<?php else: ?>
    <?php if ($historyPages > 1): ?><nav class="history-pagination" aria-label="Quiz history pages"><p role="status">Showing <?= $historyFirst ?>–<?= $historyLast ?> of <?= $historyTotal ?> completed quizzes · Page <?= $historyPage ?> of <?= $historyPages ?></p><div><?php if ($historyPage > 1): ?><a class="button button-outline" rel="prev" href="/history?page=<?= $historyPage - 1 ?>">Previous</a><?php endif; ?><?php if ($historyPage < $historyPages): ?><a class="button button-outline" rel="next" href="/history?page=<?= $historyPage + 1 ?>">Next</a><?php endif; ?></div></nav><?php endif; ?>
    <?php foreach ($history as $attempt): ?>
        <?php require __DIR__ . "/attempt.php"; ?>
    <?php endforeach; ?>
    <?php if ($historyPages > 1): ?><nav class="history-pagination" aria-label="Quiz history pages"><p role="status">Showing <?= $historyFirst ?>–<?= $historyLast ?> of <?= $historyTotal ?> completed quizzes · Page <?= $historyPage ?> of <?= $historyPages ?></p><div><?php if ($historyPage > 1): ?><a class="button button-outline" rel="prev" href="/history?page=<?= $historyPage - 1 ?>">Previous</a><?php endif; ?><?php if ($historyPage < $historyPages): ?><a class="button button-outline" rel="next" href="/history?page=<?= $historyPage + 1 ?>">Next</a><?php endif; ?></div></nav><?php endif; ?>
<?php endif; ?>

<hr>
<nav class="related-navigation" aria-label="Learning pages">
    <a href="/dashboard">Dashboard</a>
    <a href="/progress">Progress</a>
    <a href="/study">Study Plan</a>
</nav>
</div>
