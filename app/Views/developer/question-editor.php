<h2>Question Editor</h2>

<p>

Shared Question Bank

</p>

<hr>

<p>

<strong>

Total Questions:

</strong>

<?= count($questions ?? []) ?>

</p>

<table border="1" cellpadding="6">

<tr>

<th>ID</th>

<th>Topic</th>

<th>Difficulty</th>

<th>Status</th>

<th>Question</th>

<th>Actions</th>

</tr>

<?php foreach(($questions ?? []) as $question): ?>

<tr>

<td>

<?= $question["id"] ?>

</td>

<td>

<?= htmlspecialchars(
    $question["topic"] ?? ""
) ?>

</td>

<td>

<?= htmlspecialchars(
    $question["difficulty"] ?? ""
) ?>

</td>

<td>

<?php if (
    ($question["status"] ?? "active")
    ===
    "archived"
): ?>

<span style="color:red;">

Archived

</span>

<?php else: ?>

<span style="color:green;">

Active

</span>

<?php endif; ?>

</td>

<td>

<?= htmlspecialchars(
    $question["question"] ?? ""
) ?>

</td>

<td>

<?php if (
    ($question["status"] ?? "active")
    ===
    "active"
): ?>

<a href="?page=question-editor&action=edit&id=<?= $question["id"] ?>">

✏ Edit

</a>

|

<a
href="?page=question-editor&action=archive&id=<?= $question["id"] ?>"
onclick="return confirm('Archive this question?')"
>

🗃 Archive

</a>

<?php else: ?>

<a
href="?page=question-editor&action=restore&id=<?= $question["id"] ?>"
>

♻ Restore

</a>

<?php endif; ?>

</td>

</tr>

<?php endforeach; ?>

</table>

<hr>

<a href="?page=question-editor&action=create">

<button type="button">

➕ New Question

</button>

</a>
