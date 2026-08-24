<?php

$dashboard = $dashboard ?? [];
$progress = $dashboard["progress"] ?? [];
$topics = $dashboard["topics"] ?? [];
$weakest = $dashboard["weakestTopics"] ?? [];
$streak = (int) ($dashboard["streak"] ?? 0);
$insight = $dashboard["insight"] ?? [];
$recommendations = $dashboard["recommendations"] ?? [];
$history = $history ?? [];

$average = (int) ($progress["averageScore"] ?? 0);
$best = (int) ($progress["bestScore"] ?? 0);
$total = (int) ($progress["totalAttempts"] ?? 0);
$completed = (int) ($progress["completedAttempts"] ?? $total);
?>

<h1>Study Dashboard</h1>

<p>
    Your personalized study view, built from your completed quiz history.
</p>

<hr>

<section class="study-dashboard-summary">
    <h2>At a Glance</h2>

    <ul>
        <li>Completed Quizzes: <strong><?= $completed ?></strong></li>
        <li>Average Score: <strong><?= $average ?>%</strong></li>
        <li>Best Score: <strong><?= $best ?>%</strong></li>
        <li>Learning Streak: <strong><?= $streak ?> day<?= $streak === 1 ? "" : "s" ?></strong></li>
        <li>Total Attempts: <strong><?= $total ?></strong></li>
    </ul>
</section>

<hr>

<section class="study-dashboard-insight">
    <h2><?= htmlspecialchars((string) ($insight["headline"] ?? "Study Insight")) ?></h2>

    <p>
        <?= htmlspecialchars((string) ($insight["message"] ?? "")) ?>
    </p>

    <?php if (!empty($insight["actions"])): ?>
        <h3>What to do next</h3>
        <ul>
            <?php foreach ($insight["actions"] as $action): ?>
                <li><?= htmlspecialchars((string) $action) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>

<hr>

<section class="study-dashboard-recommendations">
    <h2>Recommended Next Steps</h2>

    <?php if (empty($recommendations)): ?>
        <p>Complete a quiz to receive personalized recommendations.</p>
    <?php else: ?>
        <?php foreach ($recommendations as $recommendation): ?>
            <article>
                <h3><?= htmlspecialchars((string) ($recommendation["title"] ?? "")) ?></h3>
                <p><?= htmlspecialchars((string) ($recommendation["reason"] ?? "")) ?></p>
                <?php if (!empty($recommendation["topic"])): ?>
                    <p>
                        <strong>Topic:</strong>
                        <?= htmlspecialchars((string) $recommendation["topic"]) ?>
                    </p>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    <?php endif; ?>
</section>

<hr>

<section class="study-dashboard-focus">
    <h2>Focus Areas</h2>

    <?php if (empty($weakest)): ?>
        <p>Complete more quizzes to identify your weakest topics.</p>
    <?php else: ?>
        <?php foreach ($weakest as $topic): ?>
            <article>
                <h3><?= htmlspecialchars((string) ($topic["topic"] ?? "General")) ?></h3>
                <p>
                    Average:
                    <strong><?= (int) ($topic["average"] ?? 0) ?>%</strong>
                    —
                    <?= (int) ($topic["attempts"] ?? 0) ?> attempt<?= ((int) ($topic["attempts"] ?? 0)) === 1 ? "" : "s" ?>
                </p>
            </article>
        <?php endforeach; ?>
    <?php endif; ?>
</section>

<hr>

<section class="study-dashboard-topics">
    <h2>Topic Performance</h2>

    <?php if (empty($topics)): ?>
        <p>No topic performance is available yet.</p>
    <?php else: ?>
        <?php foreach ($topics as $topic): ?>
            <article>
                <h3><?= htmlspecialchars((string) ($topic["topic"] ?? "General")) ?></h3>
                <p>
                    Average: <?= (int) ($topic["average"] ?? 0) ?>%
                    —
                    Best: <?= (int) ($topic["best"] ?? 0) ?>%
                    —
                    Attempts: <?= (int) ($topic["attempts"] ?? 0) ?>
                </p>
            </article>
        <?php endforeach; ?>
    <?php endif; ?>
</section>

<hr>

<section class="study-dashboard-history">
    <h2>Recent Activity</h2>

    <?php if (empty($history)): ?>
        <p>No completed quizzes yet.</p>
    <?php else: ?>
        <?php foreach ($history as $attempt): ?>
            <?php
            $date = \App\Services\Learning\LearningHistoryService::dateOf($attempt);
            $topic = \App\Services\Learning\LearningHistoryService::topicOf($attempt);
            ?>
            <article>
                <h3><?= htmlspecialchars($topic) ?></h3>
                <p>
                    <?= htmlspecialchars(ucfirst((string) ($attempt["mode"] ?? "practice"))) ?>
                    —
                    <?= (int) ($attempt["score"] ?? 0) ?>/<?= (int) ($attempt["total"] ?? 0) ?>
                    (<?= (int) ($attempt["percentage"] ?? 0) ?>%)
                </p>
                <?php if ($date !== null): ?>
                    <p><?= htmlspecialchars($date) ?></p>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    <?php endif; ?>
</section>

<hr>

<nav>
    <a href="/quiz">Take Quiz</a>
    <br><br>
    <a href="/progress">Progress</a>
    <br><br>
    <a href="/profile">Learning Profile</a>
    <br><br>
    <a href="/dashboard">Dashboard</a>
</nav>
