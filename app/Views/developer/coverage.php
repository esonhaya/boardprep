<h2>Coverage Matrix</h2>

<hr>

<?php foreach ($coverage as $domain => $topics): ?>

<h3>

<?= htmlspecialchars($domain) ?>

</h3>

<table border="1" cellpadding="6">

<tr>

<th>Topic</th>

<th>Questions</th>

<th>Status</th>

</tr>

<?php foreach ($topics as $row): ?>

<tr>

<td>

<?= htmlspecialchars($row["topic"]) ?>

</td>

<td>

<?= $row["count"] ?>

</td>

<td>

<?= $row["status"] ?>

</td>

</tr>

<?php endforeach; ?>

</table>

<br>

<?php endforeach; ?>
