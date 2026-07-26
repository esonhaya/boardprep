<h1>Board Manager</h1>

<p>

Manage all supported board examinations.

</p>

<hr>

<?php

$totalBoards = count($boards);

$activeBoards = 0;
$archivedBoards = 0;

foreach ($boards as $board) {

    if (($board["status"] ?? "") === "active") {

        $activeBoards++;

    } else {

        $archivedBoards++;

    }

}

?>

<p>

<strong>Total Boards:</strong>
<?= $totalBoards; ?>

<br>

<strong>Active:</strong>
<?= $activeBoards; ?>

<br>

<strong>Archived:</strong>
<?= $archivedBoards; ?>

</p>

<p>

<a href="?page=board-create">

+ Create Board

</a>

</p>

<hr>

<?php if (empty($boards)): ?>

<p>

No boards have been created yet.

</p>

<?php else: ?>

<table border="1" cellpadding="8" cellspacing="0">

<tr>

    <th>Name</th>
    <th>ID</th>
    <th>Status</th>
    <th>Subjects</th>
    <th>Actions</th>

</tr>

<?php foreach ($boards as $board): ?>

<tr>

    <td>

        <a href="?page=board&id=<?= urlencode($board["id"]) ?>">

            <?= htmlspecialchars($board["name"]) ?>

        </a>

    </td>

    <td>

        <?= htmlspecialchars($board["id"]) ?>

    </td>

    <td>

        <?= ucfirst($board["status"]) ?>

    </td>

    <td>

        <?= count($board["subjects"] ?? []) ?>

    </td>

    <td>

        <?php if (($board["status"] ?? "") === "active"): ?>

            <a href="?page=board-archive&id=<?= urlencode($board["id"]) ?>">

                Archive

            </a>

        <?php else: ?>

            <a href="?page=board-activate&id=<?= urlencode($board["id"]) ?>">

                Activate

            </a>

        <?php endif; ?>

    </td>

</tr>

<?php endforeach; ?>

</table>

<?php endif; ?>
