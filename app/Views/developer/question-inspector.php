<h2>

Question Inspector

</h2>

<p>

Inspect repository information and question health.

</p>

<hr>

<h3>

Question Information

</h3>

<p>

<strong>ID:</strong>

<?= htmlspecialchars(
    (string) ($question["id"] ?? "")
) ?>

</p>

<p>

<strong>Status:</strong>

<?= htmlspecialchars(
    ucfirst(
        $question["status"] ?? "Approved"
    )
) ?>

</p>

<p>

<strong>Difficulty:</strong>

<?= htmlspecialchars(
    ucfirst(
        $question["difficulty"] ?? "Unknown"
    )
) ?>

</p>

<p>

<strong>Source:</strong>

<?= htmlspecialchars(
    $question["source"] ?? "Manual"
) ?>

</p>

<hr>

<h3>

Taxonomy

</h3>

<?php

$taxonomy =
    $question["taxonomy"] ?? [];

?>

<p>

<strong>Board:</strong>

<?= htmlspecialchars(
    $taxonomy["board_id"] ?? "-"
) ?>

</p>

<p>

<strong>Subject:</strong>

<?= htmlspecialchars(
    $taxonomy["subject_id"] ?? "-"
) ?>

</p>

<p>

<strong>Domain:</strong>

<?= htmlspecialchars(
    $taxonomy["domain_id"] ?? "-"
) ?>

</p>

<p>

<strong>Topic:</strong>

<?= htmlspecialchars(
    $taxonomy["topic_id"] ?? "-"
) ?>

</p>

<p>

<strong>Concept:</strong>

<?= htmlspecialchars(
    $taxonomy["concept_id"] ?? "-"
) ?>

</p>

<hr>

<h3>

Repository Statistics

</h3>

<p>

<strong>Times Used:</strong>

<?= (int) ($question["timesUsed"] ?? 0) ?>

</p>

<p>

<strong>Correct Answers:</strong>

<?= (int) ($question["timesCorrect"] ?? 0) ?>

</p>

<p>

<strong>Incorrect Answers:</strong>

<?= (int) ($question["timesIncorrect"] ?? 0) ?>

</p>

<p>

<strong>Bookmarks:</strong>

<?= (int) ($question["bookmarks"] ?? 0) ?>

</p>

<p>

<strong>Reports:</strong>

<?= (int) ($question["reports"] ?? 0) ?>

</p>

<hr>

<h3>

Quick Actions

</h3>

<p>

<a href="/question-editor/edit?id=<?= urlencode((string) ($question["id"] ?? "")) ?>">

✏️ Edit Question

</a>

</p>

<p>

<a href="/question-editor">

📚 Back to Question Library

</a>

</p>

<p>

<a href="/developer">

🏠 Developer Dashboard

</a>

</p>

<hr>

<p>

<strong>Inspection Tip:</strong>

Review the taxonomy, source, and statistics before making changes. If something looks incorrect, edit the question and verify its metadata before saving.

</p>
