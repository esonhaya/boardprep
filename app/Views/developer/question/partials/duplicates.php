<?php if (!empty($duplicates)): ?>

<div
style="
border:2px solid orange;
padding:10px;
margin:10px 0;
"
>

<h3>

⚠ Possible Duplicate Questions

</h3>

<p>

The system found similar questions already in the bank.

</p>

<?php foreach (
    $duplicates as $duplicate
): ?>

<p>

<strong>

Question #

<?= htmlspecialchars(
    $duplicate["question"]["id"] ?? ""
) ?>

</strong>

<br>

Similarity:

<?= htmlspecialchars(
    (string) ($duplicate["score"] ?? 0)
) ?>%

<br>

<?= htmlspecialchars(
    $duplicate["question"]["question"] ?? ""
) ?>

</p>

<hr>

<?php endforeach; ?>

</div>

<?php endif; ?>
