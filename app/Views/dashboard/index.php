<?php
$dashboard = $dashboard ?? [];
$progress = $dashboard["progress"] ?? [];
$recommendations = $dashboard["recommendations"] ?? [];
$history = $history ?? [];
$completed = (int) ($progress["completedAttempts"] ?? 0);
?>

<h1>Learner Dashboard</h1>
<p>Your completed quiz results and the next useful study action.</p>

<section>
    <h2>Learning Overview</h2>
    <?php if ($completed === 0): ?>
        <p>No completed quizzes yet. Start a practice quiz to build your learning profile.</p>
    <?php endif; ?>
    <p>Completed Quizzes: <strong><?= $completed ?></strong></p>
    <p>Average Score: <strong><?= (int) ($progress["averageScore"] ?? 0) ?>%</strong></p>
    <p>Best Score: <strong><?= (int) ($progress["bestScore"] ?? 0) ?>%</strong></p>
</section>

<hr>

<section>
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

<section>
    <h2>Recent Activity</h2>
    <?php if ($history === []): ?>
        <p>Your completed quizzes will appear here.</p>
    <?php else: ?>
        <?php foreach ($history as $attempt): ?>
            <?php require dirname(__DIR__) . "/history/attempt.php"; ?>
        <?php endforeach; ?>
        <p><a href="/history">View all quiz history</a></p>
    <?php endif; ?>
</section>

<hr>
<nav>
    <a href="/profile">Learning Profile</a> ·
    <a href="/progress">Progress</a> ·
    <a href="/study">Study Plan</a>
</nav>
