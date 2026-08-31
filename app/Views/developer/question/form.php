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

$returnUrl = $returnUrl ?? '';

$subjects =
    $subjects ?? [];

$domains =
    $domains ?? [];

$topics =
    $topics ?? [];

$concepts =
    $concepts ?? [];

?>

<h2>

<?= $isEdit

    ? "Edit Question"

    : "Create Question"

?>

</h2>

<?php

require __DIR__

    . "/partials/duplicates.php";

?>

<form

method="POST"

action="<?=

$isEdit

    ? "/question-editor/update?id="

        . urlencode((string) ($question["id"] ?? ""))

    : "/question-editor/save"

?>"

>

<?php if ($returnUrl !== ''): ?><input type="hidden" name="return" value="<?= htmlspecialchars($returnUrl) ?>"><?php endif; ?>

<h3>

Taxonomy

</h3>

<hr>

<?php

require __DIR__

    . "/partials/taxonomy.php";

?>

<h3>

Question

</h3>

<hr>

<?php

require __DIR__

    . "/partials/question.php";

?>

<h3>

Answer Options

</h3>

<hr>

<?php

require __DIR__

    . "/partials/options.php";
?>
<h3>

Save

</h3>

<hr>

<?php

require __DIR__

    . "/partials/actions.php";

?>

</form>

<script

id="taxonomy-board-subjects"

type="application/json"

><?= json_encode(

    $boardSubjects ?? [],

    JSON_UNESCAPED_UNICODE
    |
    JSON_UNESCAPED_SLASHES

) ?></script>

<script

id="taxonomy-subjects"

type="application/json"

><?= json_encode(

    $subjects ?? [],

    JSON_UNESCAPED_UNICODE
    |
    JSON_UNESCAPED_SLASHES

) ?></script>

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

src="/assets/js/taxonomy-selector.js"

></script>
