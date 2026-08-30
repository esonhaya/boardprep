<h2>

Question Library

</h2>

<p>

Browse, search and manage your question repository.

</p>

<hr>

<h3>

Repository Summary

</h3>

<p>

<strong>

Total Questions:

</strong>

<?= count($questions ?? []) ?>

</p>

<p>

Use the filters below to quickly locate questions.

</p>

<hr>

<form method="GET">

<input
type="hidden"
name="page"
value="question-editor"
>

<label>

Search

</label>

<br>

<input
type="text"
name="search"
placeholder="Question, topic or concept..."
value="<?= htmlspecialchars(
    $search ?? ""
) ?>"
style="width:100%;max-width:500px;"
>

<br><br>

<label>

Domain

</label>

<br>

<select
name="domain"
style="width:100%;max-width:500px;"
>

<option value="">

All Domains

</option>

<?php foreach (($domains ?? []) as $item): ?>

<option
value="<?= htmlspecialchars((string) ($item["id"] ?? "")) ?>"
<?= (($domain ?? "") === ($item["id"] ?? ""))
    ? "selected"
    : "" ?>
>

<?= htmlspecialchars((string) ($item["name"] ?? $item["id"] ?? "")) ?>

</option>

<?php endforeach; ?>

</select>

<br><br>

<label>

Difficulty

</label>

<br>

<select
name="difficulty"
style="width:100%;max-width:500px;"
>

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

Topic

</label>

<br>

<select
name="topic"
style="width:100%;max-width:500px;"
>

<option value="">

All Topics

</option>

<?php foreach (($topics ?? []) as $item): ?>

<option
value="<?= htmlspecialchars((string) ($item["id"] ?? "")) ?>"
<?= (($topic ?? "") === ($item["id"] ?? ""))
    ? "selected"
    : "" ?>
>

<?= htmlspecialchars((string) ($item["name"] ?? $item["id"] ?? "")) ?>

</option>

<?php endforeach; ?>

</select>

<br><br>

<button type="submit">

🔍 Apply Filters

</button>

<a href="/question-editor">

<button
type="button"
>

Reset

</button>

</a>

</form>

<hr>

<h3>

Questions

</h3>

<?php if (empty($questions)): ?>

<p>

No questions matched your filters.

</p>

<?php else: ?>

<?php foreach (($questions ?? []) as $question): ?>

<div
style="
border:1px solid #d1d5db;
padding:16px;
margin-bottom:16px;
border-radius:8px;
background:white;
"
>

<p>

<strong>

#<?= htmlspecialchars(
    (string) ($question["id"] ?? "")
) ?>

</strong>

</p>

<p>

<?= htmlspecialchars(
    $question["question"] ?? ""
) ?>

</p>

<p>

<strong>Difficulty:</strong>

<?= htmlspecialchars(
    ucfirst(
        $question["difficulty"] ?? "Unknown"
    )
) ?>

<br>

<strong>Status:</strong>

<?= htmlspecialchars(
    ucfirst(
        $question["status"] ?? "Approved"
    )
) ?>

</p>

<p>

<strong>Topic:</strong>

<?= htmlspecialchars(
    $question["taxonomy"]["topic_id"] ?? ""
) ?>

</p>

<div>

<a href="/question-inspector?id=<?= urlencode((string) $question["id"]) ?>">

🔍 Inspect

</a>

|

<a href="/question-editor/edit?id=<?= urlencode((string) $question["id"]) ?>">

✏️ Edit

</a>

|

<?php if (
    ($question["status"] ?? "approved")
    === "approved"
): ?>

<form method="POST" action="/question-editor/archive?id=<?= urlencode((string) $question["id"]) ?>" style="display:inline" onsubmit="return confirm('Archive this question?')">

<button type="submit">

🗃️ Archive

</button>

</form>

<?php else: ?>

<form method="POST" action="/question-editor/restore?id=<?= urlencode((string) $question["id"]) ?>" style="display:inline" onsubmit="return confirm('Restore this question?')">

<button type="submit">

♻️ Restore

</button>

</form>

<?php endif; ?>

</div>

</div>

<?php endforeach; ?>

<?php endif; ?>

<hr>

<p
style="
margin-top:24px;
"
>

<a
href="/question-editor/create"
>

<button
type="button"
style="
width:100%;
padding:14px;
font-size:16px;
"
>

➕ Create New Question

</button>

</a>

</p>

<hr>

<p>

<strong>Tip:</strong>

Use <em>Save &amp; Next</em> when encoding multiple questions, or
<em>Save Similar</em> when creating variations of an existing question.

</p>
