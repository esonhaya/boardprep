<h2>

<?= htmlspecialchars(
    $pageTitle ?? "Question Workspace"
) ?>

</h2>

<?php if (!empty($context)): ?>

<p
style="
color:#666;
margin-bottom:20px;
"
>

Workspace Context

<?php foreach ($context as $key => $value): ?>

•

<strong>

<?= ucfirst($key) ?>

</strong>

=

<?= htmlspecialchars(
    is_array($value)
        ? ($value["name"] ?? "")
        : (string) $value
) ?>

<?php endforeach; ?>

</p>

<?php endif; ?>
