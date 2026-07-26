<h2>Blueprint Health</h2>

<hr>

<table border="1" cellpadding="8">

<tr>

<th>Name</th>

<th>Board</th>

<th>Subject</th>

<th>Questions</th>

<th>Status</th>

<th>Errors</th>

</tr>

<?php foreach ($results as $blueprint): ?>

<tr>

<td><?= htmlspecialchars($blueprint["name"]) ?></td>

<td><?= htmlspecialchars($blueprint["board"]) ?></td>

<td><?= htmlspecialchars($blueprint["subject"]) ?></td>

<td><?= $blueprint["questionCount"] ?></td>

<td>

<?= $blueprint["valid"]

? "✅ Valid"

: "❌ Invalid"

?>

</td>

<td>

<?= $blueprint["errors"] ?>

</td>

</tr>

<?php endforeach; ?>

</table>
