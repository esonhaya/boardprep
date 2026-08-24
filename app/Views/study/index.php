<?php

$dashboard = $dashboard ?? [];
$progress = $dashboard["progress"] ?? [];
$topics = $dashboard["topics"] ?? [];
$weakest = $dashboard["weakestTopics"] ?? [];
$streak = (int) ($dashboard["streak"] ?? 0);
$insight = $dashboard["insight"] ?? [];
$recommendations = $dashboard["recommendations"] ?? [];
$studyPlan = $dashboard["studyPlan"] ?? [];
$history = $history ?? [];
?>

<h1>Study Dashboard</h1>

<p>
    Your personalized study view, built from your completed quiz history.
</p>

<hr>

<section class="study-dashboard-summary">
    <h2>At a Glance</h2>
    <ul>
        <li>Completed Quizzes: <strong><?= (int) ($progress["completedAttempts"] ?? $progress["totalAttempts"] ?? 0) ?></strong></li>
        <li>Average Score: <strong><?= (int) ($progress["averageScore"] ?? 0) ?>%</strong></li>
        <li>Best Score: <strong><?= (int) ($progress["bestScore"] ?? 0) ?>%</strong></li>
        <li>Learning Streak: <strong><?= $streak ?> day<?= $streak === 1 ? "" : "s" ?></strong></li>
    </ul>
</section>

<hr>

<section class="study-plan">
    <h2>Today's Study Plan</h2>

    <?php if (empty($studyPlan)): ?>
        <p>Complete a quiz to build your personalized study plan.</p>
    <?php else: ?>
        <?php foreach ($studyPlan as $index => $item): ?>
            <article class="study-plan-item">
                <h3>
                    <?= $index + 1 ?>.
                    <?= htmlspecialchars((string) ($item["topic"] ?? "General")) ?>
                </h3>

                <p>
                    <?= htmlspecialchars((string) ($item["type"] ?? "Study")) ?>

                    <?php if ($item["average"] !== null): ?>
                        — Current average:
                        <strong><?= (int) $item["average"] ?>%</strong>
                    <?php endif; ?>
                </p>

                <a href="<?= htmlspecialchars((string) ($item["action"] ?? "/quiz")) ?>">
                    Start practice
                </a>
            </article>
        <?php endforeach; ?>
    <?php endif; ?>
</section>

<hr>

<section class="study-dashboard-insight">
    <h2><?= htmlspecialchars((string) ($insight["headline"] ?? "Study Insight")) ?></h2>
    <p><?= htmlspecialchars((string) ($insight["message"] ?? "")) ?></p>

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
</nav>
