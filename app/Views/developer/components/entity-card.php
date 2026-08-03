<?php

use App\ViewModels\Developer\EntityCardViewModel;

/** @var EntityCardViewModel $entityCard */

?>

<div
style="
border:1px solid #d1d5db;
border-radius:8px;
padding:16px;
margin-bottom:16px;
background:white;
"
>

<h3>

<?= htmlspecialchars(
    $entityCard->entity["name"] ?? ""
) ?>

</h3>

<?php if (!empty($entityCard->entity["description"])): ?>

<p>

<?= htmlspecialchars(
    $entityCard->entity["description"]
) ?>

</p>

<?php endif; ?>

<?php foreach ($entityCard->details as $label => $value): ?>

<p>

<strong>

<?= htmlspecialchars($label) ?>:

</strong>

<?= htmlspecialchars((string) $value) ?>

</p>

<?php endforeach; ?>

<?php if (!empty($entityCard->actions)): ?>

<p>

<?php foreach ($entityCard->actions as $index => $action): ?>

<?php if ($index > 0): ?>

|

<?php endif; ?>

<a
href="<?= htmlspecialchars($action["href"]) ?>"
>

<?= htmlspecialchars($action["label"]) ?>

</a>

<?php endforeach; ?>

</p>

<?php endif; ?>

</div>
