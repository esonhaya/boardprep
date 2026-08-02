<?php

$isEdit =
    ($contentMode ?? "") === "edit";

$context =
    $context ?? [];

$question =
    $question ?? [];

$errors =
    $errors ?? [];

$duplicates =
    $duplicates ?? [];

$subjects =
    $subjects ?? [];

$domains =
    $domains ?? [];

$topics =
    $topics ?? [];

$concepts =
    $concepts ?? [];

function fieldError(
    array $errors,
    string $field
): string {

    return $errors[$field] ?? "";

}

?>

<h2>

<?= $isEdit
    ? "Edit Question"
    : "Create Question"
?>

</h2>

<?php

require __DIR__
    . "/partials/question-duplicates.php";

?>

<form
method="POST"
action="/question-editor&action=<?= $isEdit
    ? "update&id=" . ($question["id"] ?? "")
    : "save" ?>"
>

<?php

require __DIR__
    . "/partials/question-taxonomy.php";

?>

<?php

require __DIR__
    . "/partials/question-question.php";

?>

<?php

require __DIR__
    . "/partials/question-options.php";

?>

<?php

require __DIR__
    . "/partials/question-actions.php";

?>

</form>

<script
id="taxonomy-domains"
type="application/json"
><?= json_encode(
    $domains,
    JSON_UNESCAPED_UNICODE
    |
    JSON_UNESCAPED_SLASHES
) ?></script>

<script
id="taxonomy-topics"
type="application/json"
><?= json_encode(
    $topics,
    JSON_UNESCAPED_UNICODE
    |
    JSON_UNESCAPED_SLASHES
) ?></script>

<script
id="taxonomy-concepts"
type="application/json"
><?= json_encode(
    $concepts,
    JSON_UNESCAPED_UNICODE
    |
    JSON_UNESCAPED_SLASHES
) ?></script>

<script
src="assets/js/taxonomy-selector.js"
></script>
