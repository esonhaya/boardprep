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


<form method="POST" action="/board/save">

    <label for="board-name">

        Board Name

    </label>

    <br>

    <input
        type="text"
        id="board-name"
        name="name"
        value="<?= htmlspecialchars($old["name"] ?? "") ?>"
        required
    >

    <div class="form-spacer"></div>


    <label for="board-description">

        Description

    </label>

    <br>

    <textarea
        id="board-description"
        name="description"
        rows="4"
        cols="60"
        required
    ><?= htmlspecialchars($old["description"] ?? "") ?></textarea>

    <div class="form-spacer"></div>


    <p>

        <strong>Board ID</strong>

        <br>

        Automatically generated after saving.

    </p>


    <button type="submit">

        Create Board

    </button>

</form>
