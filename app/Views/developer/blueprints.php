<h2>Blueprint Manager</h2>

<hr>

<p>

<a href="?page=blueprints&action=create">

Create Blueprint

</a>

</p>

<?php if (empty($blueprints)): ?>

<p>No blueprints found.</p>

<?php else: ?>

<table border="1" cellpadding="8">

<tr>

<th>ID</th>

<th>Name</th>

<th>Board</th>

<th>Subject</th>

<th>Version</th>

</tr>

<?php foreach ($blueprints as $blueprint): ?>

<tr>

<td><?= htmlspecialchars($blueprint["id"] ?? "") ?></td>

<td><?= htmlspecialchars($blueprint["name"] ?? "") ?></td>

<td><?= htmlspecialchars($blueprint["board"] ?? "") ?></td>

<td><?= htmlspecialchars($blueprint["subject"] ?? "") ?></td>

<td><?= htmlspecialchars($blueprint["version"] ?? "") ?></td>

</tr>

<?php endforeach; ?>

</table>

<?php endif; ?>
