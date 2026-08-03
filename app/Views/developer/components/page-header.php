<?php

use App\ViewModels\Developer\PageHeaderViewModel;

/** @var PageHeaderViewModel $pageHeader */

?>

<h2>

<?= htmlspecialchars(
    $pageHeader->title
) ?>

</h2>

<?php if ($pageHeader->description !== ""): ?>

<p>

<?= htmlspecialchars(
    $pageHeader->description
) ?>

</p>

<?php endif; ?>

<hr>
