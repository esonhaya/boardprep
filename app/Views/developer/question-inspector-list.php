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

<?= htmlspecialchars((string) ($question["id"] ?? "")) ?>

</td>

<td>

<?= htmlspecialchars((string) ($question["question"] ?? "")) ?>

</td>

<td>

<?= htmlspecialchars((string) ($question["topic"] ?? "")) ?>

</td>

<td>

<a href="/question-inspector?id=<?= urlencode((string) ($question["id"] ?? "")) ?>">

Inspect

</a>

</td>

</tr>

<?php endforeach; ?>

</table>

<hr>

<p>

<a href="/developer">

← Back to Developer Tools

</a>

</p>
