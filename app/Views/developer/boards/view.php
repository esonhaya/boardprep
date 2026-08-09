<h1>
<?= htmlspecialchars($board["name"] ?? "") ?>
</h1>

<p>
<?= htmlspecialchars($board["description"] ?? "") ?>
</p>

<hr>

<h2>
Board Information
</h2>

<table border="1" cellpadding="8" cellspacing="0">

<tr>
    <td><strong>ID</strong></td>
    <td><?= htmlspecialchars($board["id"] ?? "") ?></td>
</tr>

<tr>
    <td><strong>Status</strong></td>
    <td><?= htmlspecialchars(ucfirst($board["status"] ?? "")) ?></td>
</tr>

<tr>
    <td><strong>Subjects</strong></td>
    <td><?= count($board["subjects"] ?? []) ?></td>
</tr>

</table>

<hr>

<h2>
Subjects
</h2>

<?php if (empty($board["subjects"])): ?>

<p>
No subjects have been added yet.
</p>

<?php else: ?>

<ul>

<?php foreach ($board["subjects"] as $subject): ?>

<li>
    <a href="/subject/view?id=<?= urlencode($subject["id"] ?? "") ?>">
        <?= htmlspecialchars(
            $subject["name"]
            ?? $subject["code"]
            ?? $subject["id"]
            ?? ""
        ) ?>
    </a>
</li>

<?php endforeach; ?>

</ul>

<?php endif; ?>

<p>
<a href="/subject/create?board=<?= urlencode($board["id"] ?? "") ?>">
+ Add Subject
</a>
</p>

<hr>

<h2>
Blueprints
</h2>

<p>
Blueprint management will be available here.
</p>

<p>
<a href="/blueprints">
Open Blueprint Manager
</a>
</p>

<hr>

<p>
<a href="/boards">
← Back to Board Manager
</a>
</p>
