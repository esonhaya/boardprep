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

<label for="content-blocks-json">Structured content <span class="muted-note">(optional JSON: equation, table, chart, or figure)</span></label>
<textarea id="content-blocks-json" name="content_blocks_json" rows="6" placeholder='[{"type":"equation","value":"A = b × h","fallback":"A equals base times height"}]'><?= htmlspecialchars(json_encode($question['content_blocks'] ?? [], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)) ?></textarea>
<p class="form-help">Figures require a repository-local asset path and meaningful alt text. Raw HTML and external URLs are rejected.</p>

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
