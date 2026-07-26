<h1>Create Board</h1>

<p>

Create a new board examination.

</p>

<hr>

<?php if (!empty($errors)): ?>

<div>

    <strong>Errors</strong>

    <ul>

        <?php foreach ($errors as $error): ?>

        <li>

            <?= htmlspecialchars($error) ?>

        </li>

        <?php endforeach; ?>

    </ul>

</div>

<hr>

<?php endif; ?>


<form method="POST" action="?page=board-save">

    <label>

        Board Name

    </label>

    <br>

    <input
        type="text"
        name="name"
        value="<?= htmlspecialchars($old["name"] ?? "") ?>"
        required
    >

    <br><br>


    <label>

        Description

    </label>

    <br>

    <textarea
        name="description"
        rows="4"
        cols="60"
        required
    ><?= htmlspecialchars($old["description"] ?? "") ?></textarea>

    <br><br>


    <p>

        <strong>Board ID</strong>

        <br>

        Automatically generated after saving.

    </p>


    <button type="submit">

        Create Board

    </button>

</form>
