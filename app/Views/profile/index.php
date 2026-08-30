<h1>🧠 Learning Profile</h1>

<hr>

<h2>Current Level</h2>
<p>
    <?= htmlspecialchars((string) ($profile["level"] ?? "Beginner")) ?>
</p>

<h2>Performance</h2>
<ul>
    <li>Overall Accuracy: <?= (int) ($profile["overallAccuracy"] ?? 0) ?>%</li>
    <li>Best Score: <?= (int) ($profile["bestScore"] ?? 0) ?>%</li>
    <li>Latest Score: <?= (int) ($profile["latestScore"] ?? 0) ?>%</li>
    <li>Total Quizzes: <?= (int) ($profile["totalQuizzes"] ?? 0) ?></li>
    <li>Current Streak: <?= (int) ($streak ?? 0) ?> day<?= ((int) ($streak ?? 0)) === 1 ? "" : "s" ?></li>
</ul>

<hr>

<h2>Study Insight</h2>
<p>
    <?= htmlspecialchars((string) ($insight["message"] ?? "Complete quizzes to receive personalized study insights.")) ?>
</p>

<?php if (!empty($studyRecommendations)): ?>
<h3>Recommended Next Steps</h3>
<ul>
<?php foreach ($studyRecommendations as $recommendation): ?>
    <li>
        <strong><?= htmlspecialchars((string) ($recommendation["title"] ?? "")) ?></strong>
        —
        <?= htmlspecialchars((string) ($recommendation["reason"] ?? "")) ?>
        <a
            href="<?= htmlspecialchars((string) ($recommendation["action"] ?? "/quiz")) ?>"
            aria-label="<?= htmlspecialchars((string) ($recommendation["label"] ?? "Start practice")) ?>"
        >
            <?= htmlspecialchars((string) ($recommendation["label"] ?? "Start practice")) ?>
        </a>
    </li>
<?php endforeach; ?>
</ul>
<?php endif; ?>

<hr>

<h2>Topic Performance</h2>

<?php if (empty($topics)): ?>
<p>No topic performance available yet.</p>
<?php else: ?>
<?php foreach (array_slice($topics, 0, 5) as $topic): ?>
<p>
    <strong><?= htmlspecialchars((string) $topic["topic"]) ?></strong>
    —
    <?= (int) $topic["average"] ?>% average
    /
    <?= (int) $topic["attempts"] ?> attempt<?= ((int) $topic["attempts"]) === 1 ? "" : "s" ?>
</p>
<?php endforeach; ?>
<?php endif; ?>

<hr>

<h2>Recent Learning Activity</h2>

<?php if (empty($timeline)): ?>
<p>No completed quizzes yet. Take a quiz to start building your learning history.</p>
<?php else: ?>
<?php foreach ($timeline as $item): ?>
<div style="margin-bottom: 15px;">
    <strong><?= htmlspecialchars(ucfirst((string) ($item["mode"] ?? "practice"))) ?></strong>
    —
    <?= htmlspecialchars((string) ($item["topic"] ?? "General")) ?>
    <br>
    Score:
    <?= (int) ($item["score"] ?? 0) ?>/<?= (int) ($item["total"] ?? 0) ?>
    (<?= (int) ($item["percentage"] ?? 0) ?>%)
    <?php if (!empty($item["date"])): ?>
        <br><?= htmlspecialchars((string) $item["date"]) ?>
    <?php endif; ?>
</div>
<?php endforeach; ?>
<?php endif; ?>

<hr>

<a href="/progress">View Progress</a>
<br><br>
<a href="/history">Quiz History</a>
<br><br>
<a href="/quiz">Take Quiz</a>
<br><br>
<a href="/dashboard">Dashboard</a>
