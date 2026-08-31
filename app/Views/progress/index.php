<?php

$summary = $summary ?? [];
$history = $history ?? [];
$topics = $topics ?? [];
$subjects = $subjects ?? [];
$weakestTopics = $weakestTopics ?? [];
$insight = $insight ?? [];
$recommendations = $recommendations ?? [];
$streak = (int) ($streak ?? 0);

$total = (int) ($summary["totalAttempts"] ?? 0);
$completed = (int) ($summary["completedAttempts"] ?? $total);
$average = (int) ($summary["averageScore"] ?? 0);
$best = (int) ($summary["bestScore"] ?? 0);
?>

<h1>Progress</h1>

<section class="progress-summary">
    <h2>Learning Overview</h2>

    <p>Completed Quizzes: <strong><?= $completed ?></strong></p>
    <p>Average Score: <strong><?= $average ?>%</strong></p>
    <p>Best Score: <strong><?= $best ?>%</strong></p>
    <p>Current Streak: <strong><?= $streak ?> day<?= $streak === 1 ? "" : "s" ?></strong></p>
    <p>Total Attempts: <strong><?= $total ?></strong></p>
    <?php $trend = $summary["trend"] ?? []; ?>
    <p>Recent Trend: <strong><?= htmlspecialchars(ucwords(str_replace("_", " ", (string) ($trend["direction"] ?? "insufficient_history")))) ?></strong></p>
</section>

<hr>

<section class="study-insight">
    <h2><?= htmlspecialchars((string) ($insight["headline"] ?? "Study Insight")) ?></h2>
    <p><?= htmlspecialchars((string) ($insight["message"] ?? "")) ?></p>

    <?php if (!empty($insight["actions"])): ?>
        <ul>
            <?php foreach ($insight["actions"] as $action): ?>
                <li><?= htmlspecialchars((string) $action) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>

<hr>

<section class="study-recommendations">
    <h2>Recommended Next Steps</h2>

    <?php if (empty($recommendations)): ?>
        <p>Complete a quiz to receive a personalized next step.</p>
    <?php endif; ?>
    <?php foreach ($recommendations as $recommendation): ?>
        <article>
            <h3><?= htmlspecialchars((string) ($recommendation["title"] ?? "")) ?></h3>
            <p><?= htmlspecialchars((string) ($recommendation["reason"] ?? "")) ?></p>
            <a
                href="<?= htmlspecialchars((string) ($recommendation["action"] ?? "/quiz")) ?>"
                aria-label="<?= htmlspecialchars((string) ($recommendation["label"] ?? "Start practice")) ?>"
            >
                <?= htmlspecialchars((string) ($recommendation["label"] ?? "Start practice")) ?>
            </a>
        </article>
    <?php endforeach; ?>
</section>

<hr>

<section class="progress-topics">
    <h2>Subject Performance</h2>
    <?php if ($subjects === []): ?>
        <p>No subject performance available yet.</p>
    <?php else: ?>
        <?php foreach ($subjects as $subject): ?>
            <p><strong><?= htmlspecialchars((string) $subject["subject"]) ?></strong> — <?= (int) $subject["average"] ?>% average · <?= (int) $subject["attempts"] ?> attempt<?= ((int) $subject["attempts"]) === 1 ? "" : "s" ?></p>
        <?php endforeach; ?>
    <?php endif; ?>

    <hr>
    <h2>Topic Performance</h2>

    <?php if (empty($topics)): ?>
        <p>No topic performance available yet.</p>
    <?php else: ?>
        <?php foreach ($topics as $topic): ?>
            <article class="progress-topic">
                <strong><?= htmlspecialchars((string) $topic["topic"]) ?></strong>
                <?php if (($topic["subject"] ?? "") !== ""): ?> (<?= htmlspecialchars((string) $topic["subject"]) ?>)<?php endif; ?>
                <p>
                    Average: <?= (int) $topic["average"] ?>%
                    — Best: <?= (int) $topic["best"] ?>%
                    — Attempts: <?= (int) $topic["attempts"] ?>
                </p>
            </article>
        <?php endforeach; ?>
    <?php endif; ?>
</section>

<hr>

<section class="progress-focus">
    <h2>Focus Areas</h2>

    <?php if (empty($weakestTopics)): ?>
        <p>Complete more quizzes to identify focus areas.</p>
    <?php else: ?>
        <?php foreach ($weakestTopics as $topic): ?>
            <p>
                <strong><?= htmlspecialchars((string) $topic["topic"]) ?></strong>
                — <?= (int) $topic["average"] ?>% average
            </p>
        <?php endforeach; ?>
    <?php endif; ?>
</section>

<hr>

<section class="progress-history ui-preview-list" data-ui-collection="preview" data-ui-limit="5">
    <h2>Recent Quiz History</h2>

    <?php if (empty($history)): ?>
        <p>No completed quizzes yet.</p>
    <?php else: ?>
        <?php foreach ($history as $attempt): ?>
            <?php require dirname(__DIR__) . "/history/attempt.php"; ?>
        <?php endforeach; ?>
        <?php if (!empty($historyHasMore)): ?><p><a class="ui-view-all" data-ui-view-all href="/history">View all quiz history</a></p><?php endif; ?>
    <?php endif; ?>
</section>
