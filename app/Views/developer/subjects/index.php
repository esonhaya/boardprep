<h1>
Subjects
</h1>

<p>
Manage the global subject repository.
Subjects can be attached to multiple boards.
</p>

<hr>

<form method="GET">

<input
type="hidden"
name="page"
value="subjects"
>

<input
type="search"
name="search"
placeholder="Search subjects..."
>

<button type="submit">
Search
</button>

<a href="/subject/create">
Create Subject
</a>

</form>

<br>

<p>

<strong>
<?= count($subjects) ?>
</strong>

subjects

</p>

<hr>

<?php if(empty($subjects)): ?>

<p>

No subjects found.

</p>

<?php else: ?>

<?php foreach($subjects as $subject): ?>

<div
style="
border:1px solid #ddd;
padding:16px;
margin-bottom:12px;
border-radius:8px;
"
>

<h3>

<?= htmlspecialchars(
    $subject["name"]
) ?>

</h3>

<p>

<?= htmlspecialchars(
    $subject["description"] ?? ""
) ?>

</p>

<p>

Status:

<strong>

<?= htmlspecialchars(
    ucfirst(
        $subject["status"] ?? "active"
    )
) ?>

</strong>

</p>

<a
href="/subject/view?id=<?= urlencode($subject["id"]) ?>"
>

Open

</a>

|

<a
href="/subject/edit?id=<?= urlencode($subject["id"]) ?>"
>

Edit

</a>

|

<?php if(
($subject["status"] ?? "active")
=== \App\Constants\Status::ACTIVE
): ?>

<a
href="/subject/archive?id=<?= urlencode($subject["id"]) ?>"
>

Archive

</a>

<?php else: ?>

<a
href="/subject/activate?id=<?= urlencode($subject["id"]) ?>"
>

Activate

</a>

<?php endif; ?>

</div>

<?php endforeach; ?>

<?php endif; ?>
