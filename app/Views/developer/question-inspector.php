<h2>

Question Inspector

</h2>

<hr>

<p>

<strong>ID:</strong>

<?= $question["id"] ?>

</p>

<p>

<strong>Status:</strong>

<?= htmlspecialchars(
    $question["status"] ?? "active"
) ?>

</p>

<p>

<strong>Source:</strong>

<?= htmlspecialchars(
    $question["source"] ?? "manual"
) ?>

</p>

<hr>

<h3>

Repository Statistics

</h3>

<p>

Times Used:

<?= $question["timesUsed"] ?? 0 ?>

</p>

<p>

Correct:

<?= $question["timesCorrect"] ?? 0 ?>

</p>

<p>

Incorrect:

<?= $question["timesIncorrect"] ?? 0 ?>

</p>

<p>

Bookmarks:

<?= $question["bookmarks"] ?? 0 ?>

</p>

<p>

Reports:

<?= $question["reports"] ?? 0 ?>

</p>

<hr>

<p>

<a href="?page=question-editor">

← Back

</a>

</p>
