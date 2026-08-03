<?php

use App\ViewModels\Developer\ActionBarViewModel;

/** @var ActionBarViewModel $actionBar */

?>

<?php if (!empty($actionBar->actions)): ?>

<p>

<?php foreach ($actionBar->actions as $action): ?>

<a
href="<?= htmlspecialchars($action["href"]) ?>"
>

<button
type="button"
>

<?= htmlspecialchars($action["label"]) ?>

</button>

</a>

&nbsp;

<?php endforeach; ?>

</p>

<hr>

<?php endif; ?>
