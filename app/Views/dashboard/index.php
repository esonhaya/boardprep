<?php
$dashboard = $dashboard ?? [];
$progress = $dashboard["progress"] ?? [];
$recommendations = $dashboard["recommendations"] ?? [];
$history = $history ?? [];
$completed = (int) ($progress["completedAttempts"] ?? 0);
?>

<section class="page-header"><div><h1>Welcome back</h1><p>Your completed quiz results and the next useful study action.</p></div><div class="page-actions"><a class="button" href="/quiz">Start practice</a><a class="button secondary" href="/history">View history</a></div></section>

<section class="stats-grid" aria-label="Learning overview">
    <h2>Learning Overview</h2>
    <?php if ($completed === 0): ?>
        <p>No completed quizzes yet. Start a practice quiz to build your learning profile.</p>
    <?php endif; ?>
    <article class="card stat-card"><span class="stat-label">Completed quizzes</span><strong class="stat-value"><?= $completed ?></strong></article>
    <article class="card stat-card"><span class="stat-label">Average score</span><strong class="stat-value"><?= (int) ($progress["averageScore"] ?? 0) ?>%</strong></article>
    <article class="card stat-card"><span class="stat-label">Best score</span><strong class="stat-value"><?= (int) ($progress["bestScore"] ?? 0) ?>%</strong></article>
    <article class="card stat-card"><span class="stat-label">Recent trend</span><strong class="stat-value"><?= htmlspecialchars(ucwords(str_replace("_", " ", (string) ($progress["trend"]["direction"] ?? "insufficient_history")))) ?></strong></article>
</section>

<hr>

<section class="card">
    <h2>Recommended Next Step</h2>
    <?php $recommendation = $recommendations[0] ?? null; ?>
    <?php if (is_array($recommendation)): ?>
        <h3><?= htmlspecialchars((string) ($recommendation["title"] ?? "Keep practicing")) ?></h3>
        <p><?= htmlspecialchars((string) ($recommendation["reason"] ?? "")) ?></p>
        <a href="<?= htmlspecialchars((string) ($recommendation["action"] ?? "/quiz")) ?>">
            <?= htmlspecialchars((string) ($recommendation["label"] ?? "Start practice")) ?>
        </a>
    <?php endif; ?>
</section>

<hr>

<section data-ui-collection="preview" data-ui-limit="5">
    <h2>Recent Activity</h2>
    <?php if ($history === []): ?>
        <p>Your completed quizzes will appear here.</p>
    <?php else: ?>
        <?php foreach ($history as $attempt): ?>
            <?php require dirname(__DIR__) . "/history/attempt.php"; ?>
        <?php endforeach; ?>
        <p><a class="ui-view-all" data-ui-view-all href="/history">View all quiz history</a></p>
    <?php endif; ?>
</section>

<hr>
<nav class="related-navigation" aria-label="Learning pages">
    <a href="/profile">Learning Profile</a>
    <a href="/progress">Progress</a>
    <a href="/study">Study Plan</a>
</nav>
