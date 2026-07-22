<h2>Question Editor</h2>

<p>

Shared Question Bank

</p>

<hr>

<form method="GET">

<input
type="hidden"
name="page"
value="question-editor"
>


<input
type="text"
name="search"
placeholder="Search question, topic, concept..."
value="<?= htmlspecialchars(
    $search ?? ""
) ?>"
>


<br><br>


<label>

Domain:

</label>

<select name="domain">

<option value="">

All Domains

</option>

<?php foreach(($domains ?? []) as $item): ?>

<option
value="<?= htmlspecialchars($item) ?>"
<?= (($domain ?? "") === $item)
    ? "selected"
    : "" ?>
>

<?= htmlspecialchars($item) ?>

</option>

<?php endforeach; ?>

</select>


<br><br>


<label>

Difficulty:

</label>

<select name="difficulty">

<option value="">

All Difficulties

</option>

<option
value="easy"
<?= (($difficulty ?? "") === "easy")
    ? "selected"
    : "" ?>
>

Easy

</option>

<option
value="medium"
<?= (($difficulty ?? "") === "medium")
    ? "selected"
    : "" ?>
>

Medium

</option>

<option
value="hard"
<?= (($difficulty ?? "") === "hard")
    ? "selected"
    : "" ?>
>

Hard

</option>

</select>


<br><br>


<label>

Topic:

</label>

<select name="topic">

<option value="">

All Topics

</option>

<?php foreach(($topics ?? []) as $group): ?>

<?php foreach(($group["topics"] ?? []) as $item): ?>

<option
value="<?= htmlspecialchars($item) ?>"
<?= (($topic ?? "") === $item)
    ? "selected"
    : "" ?>
>

<?= htmlspecialchars($item) ?>

</option>

<?php endforeach; ?>

<?php endforeach; ?>

</select>


<br><br>


<button type="submit">

🔍 Apply Filters

</button>

</form>

<br>

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

<th>Question</th>

<th>Status</th>

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

<?= htmlspecialchars(
    $question["question"] ?? ""
) ?>

</td>

<td>

<?= htmlspecialchars(
    $question["status"] ?? "active"
) ?>

</td>

<td>

<a href="?page=question-inspector&id=<?= $question["id"] ?>">

🔍 Inspect

</a>

|

<a href="?page=question-editor&action=edit&id=<?= $question["id"] ?>">

✏ Edit

</a>

|

<?php if (($question["status"] ?? "active") === "active"): ?>

<a
href="?page=question-editor&action=archive&id=<?= $question["id"] ?>"
onclick="return confirm('Archive this question?')"
>

🗃 Archive

</a>

<?php else: ?>

<a
href="?page=question-editor&action=restore&id=<?= $question["id"] ?>"
onclick="return confirm('Restore this question?')"
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
