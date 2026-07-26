<h2>Blueprint Health</h2>

<hr>

<?php foreach ($results as $result): ?>

<h3>

<?= htmlspecialchars(
    $result["blueprint"]["name"] ?? ""
) ?>

</h3>

<?php if ($result["validation"]["valid"]): ?>

<p>✅ Valid Blueprint</p>

<?php else: ?>

<p>❌ Validation Errors</p>

<ul>

<?php foreach ($result["validation"]["errors"] as $error): ?>

<li><?= htmlspecialchars($error) ?></li>

<?php endforeach; ?>

</ul>

<?php endif; ?>

<hr>

<?php endforeach; ?>
