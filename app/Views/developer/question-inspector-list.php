<h2>

Question Inspector

</h2>

<p>

Select a question to inspect.

</p>

<hr>

<table border="1" cellpadding="8">

<tr>

<th>ID</th>

<th>Question</th>

<th>Topic</th>

<th>Inspect</th>

</tr>

<?php foreach ($questions as $question): ?>

<tr>

<td>

<?= $question["id"] ?>

</td>

<td>

<?= htmlspecialchars($question["question"]) ?>

</td>

<td>

<?= htmlspecialchars($question["topic"]) ?>

</td>

<td>

<a href="?page=question-inspector&id=<?= $question["id"] ?>">

Inspect

</a>

</td>

</tr>

<?php endforeach; ?>

</table>

<hr>

<p>

<a href="?page=developer">

← Back to Developer Tools

</a>

</p>

