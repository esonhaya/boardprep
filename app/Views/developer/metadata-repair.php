<h2>Metadata Repair</h2>

<hr>

<?php if ($repaired !== null): ?>

<p>

<strong>

Repaired:

<?= $repaired ?>

question(s)

</strong>

</p>

<hr>

<?php endif; ?>


<p>

Questions needing repair:

<strong>

<?= count($report) ?>

</strong>

</p>


<p>

<a href="?page=metadata-repair&action=repair">

<button>

Apply Repairs

</button>

</a>

</p>

<hr>


<?php if (empty($report)): ?>

<p>

No metadata issues found.

</p>

<?php else: ?>

<table border="1" cellpadding="6">

<tr>

<th>ID</th>

<th>Question</th>

<th>Issues</th>

</tr>

<?php foreach ($report as $row): ?>

<tr>

<td>

<?= $row["id"] ?>

</td>

<td>

<?= htmlspecialchars($row["question"]) ?>

</td>

<td>

<?= implode(", ", $row["issues"]) ?>

</td>

</tr>

<?php endforeach; ?>

</table>

<?php endif; ?>
