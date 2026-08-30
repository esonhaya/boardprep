<?php
$history = $history ?? [];
$summary = $summary ?? [];
?>

<h1>Quiz History</h1>
<p>Completed quizzes, newest first.</p>

<p>
    Completed Quizzes: <strong><?= (int) ($summary["completedAttempts"] ?? 0) ?></strong> ·
    Average Score: <strong><?= (int) ($summary["averageScore"] ?? 0) ?>%</strong> ·
    Best Score: <strong><?= (int) ($summary["bestScore"] ?? 0) ?>%</strong>
</p>

<hr>

<?php if ($history === []): ?>
    <p>No completed quizzes yet.</p>
    <p><a href="/quiz?action=start&amp;subject=English&amp;topic=&amp;mode=practice&amp;count=5&amp;difficulty=mixed">Start your first practice quiz</a></p>
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
