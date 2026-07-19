<h2>My Progress</h2>

<p><strong>Overall Average:</strong>
<?= $stats["average"] ?>%</p>

<p><strong>Total Quizzes:</strong>
<?= $stats["totalAttempts"] ?></p>

<p><strong>Practice Sessions:</strong>
<?= $stats["practice"] ?></p>

<p><strong>Exam Sessions:</strong>
<?= $stats["exam"] ?></p>

<p><strong>Best Score:</strong>
<?= $stats["best"] ?>%</p>

<?php if ($stats["latest"]) : ?>

<p><strong>Latest Score:</strong>
<?= $stats["latest"]["percentage"] ?>%</p>

<?php endif; ?>

<hr>

<h3>Recent Attempts</h3>

<?php foreach ($stats["attempts"] as $attempt) : ?>

<div style="margin-bottom:15px;">

<strong><?= ucfirst($attempt["mode"]) ?></strong><br>

<?= $attempt["subject"] ?>
-
<?= $attempt["topic"] ?><br>

<?= $attempt["percentage"] ?>%

</div>

<?php endforeach; ?>
