<?php

use App\ViewModels\Developer\SummaryViewModel;

/** @var SummaryViewModel $summary */

?>

<?php if (!empty($summary->items)): ?>

<h3>

Repository Summary

</h3>

<?php foreach ($summary->items as $label => $value): ?>

<p>

<strong>

<?= htmlspecialchars($label) ?>:

</strong>

<?= htmlspecialchars((string) $value) ?>

</p>

<?php endforeach; ?>

<hr>

<?php endif; ?>
