<?php
$history = $history ?? [];
$summary = $summary ?? [];
?>

<div class="history-page">
<section class="page-header"><div><p class="eyebrow">Learning history</p><h1>Quiz history</h1><p>Completed quizzes, newest first.</p></div><a class="button" href="/quiz">Start practice</a></section>

<section class="history-summary" aria-label="Quiz summary">
    <div><span class="stat-label">Completed quizzes</span><strong><?= (int) ($summary["completedAttempts"] ?? 0) ?></strong></div>
    <div><span class="stat-label">Average score</span><strong><?= (int) ($summary["averageScore"] ?? 0) ?>%</strong></div>
    <div><span class="stat-label">Best score</span><strong><?= (int) ($summary["bestScore"] ?? 0) ?>%</strong></div>
</section>

<?php if ($history === []): ?>
    <div class="empty-state"><p>No completed quizzes yet.</p>
    <p><a href="/quiz?action=start&amp;subject=English&amp;topic=&amp;mode=practice&amp;count=5&amp;difficulty=mixed">Set up your first practice quiz</a></p>
    </div>
<?php else: ?>
    <?php foreach ($history as $attempt): ?>
        <?php require __DIR__ . "/attempt.php"; ?>
    <?php endforeach; ?>
<?php endif; ?>

<hr>
<nav class="related-navigation" aria-label="Learning pages">
    <a href="/dashboard">Dashboard</a>
    <a href="/progress">Progress</a>
    <a href="/study">Study Plan</a>
</nav>
</div>
