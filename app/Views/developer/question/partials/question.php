<label>
Question
</label>

<br>

<?php

$questionError =
    $errors["question"] ?? "";

if (
    is_array($questionError)
) {
    $questionError =
        $questionError["message"]
        ?? $questionError[0]
        ?? "";
}

$explanationError =
    $errors["explanation"] ?? "";

if (
    is_array($explanationError)
) {
    $explanationError =
        $explanationError["message"]
        ?? $explanationError[0]
        ?? "";
}

$questionError =
    is_string($questionError)
        ? $questionError
        : "";

$explanationError =
    is_string($explanationError)
        ? $explanationError
        : "";

?>

<?php if ($questionError !== ""): ?>

<p
style="
color:red;
margin:4px 0;
"
>

<?= htmlspecialchars(
    $questionError
) ?>

</p>

<?php endif; ?>

<textarea
name="question"
rows="4"
cols="60"
required
style="<?= $questionError !== ""
    ? "border:2px solid red;"
    : "" ?>"
><?= htmlspecialchars(
    $question["question"] ?? ""
) ?></textarea>

<br><br>

<label>
Explanation
</label>

<br>

<?php if ($explanationError !== ""): ?>

<p
style="
color:red;
margin:4px 0;
"
>

<?= htmlspecialchars(
    $explanationError
) ?>

</p>

<?php endif; ?>

<textarea
name="explanation"
rows="5"
cols="60"
required
style="<?= $explanationError !== ""
    ? "border:2px solid red;"
    : "" ?>"
><?= htmlspecialchars(
    $question["explanation"] ?? ""
) ?></textarea>

<br><br>
