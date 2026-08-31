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

<p class="form-error">

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
class="<?= $questionError !== "" ? "field-invalid" : "" ?>"
><?= htmlspecialchars(
    $question["question"] ?? ""
) ?></textarea>

<div class="form-spacer"></div>

<label>
Explanation
</label>

<br>

<?php if ($explanationError !== ""): ?>

<p class="form-error">

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
class="<?= $explanationError !== "" ? "field-invalid" : "" ?>"
><?= htmlspecialchars(
    $question["explanation"] ?? ""
) ?></textarea>

<div class="form-spacer"></div>
