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

$csrfToken = $csrfToken ?? '';

?>

<section class="page-header"><div><h1>
<?= $isEdit

    ? "Edit Question"

    : "Create Question"

?></h1><p>Author a clear, reliable question for the BoardPrep repository.</p></div></section>

<?php if (($duplicateSourceNotice ?? '') !== ''): ?><p class="form-warning" role="status"><?= htmlspecialchars((string) $duplicateSourceNotice, ENT_QUOTES, 'UTF-8') ?> This is a draft; saving it will create a new canonical question. NEW_CANONICAL_ID=will be created · INDEPENDENT_QUESTION=YES</p><?php endif; ?>

<?php

require __DIR__

    . "/partials/duplicates.php";

?>

<?php if ($isEdit): require __DIR__ . "/partials/eligibility.php"; endif; ?>

<form

method="POST"

action="<?=

$isEdit

    ? "/question-editor/update?id="

        . urlencode((string) ($question["id"] ?? ""))

    : "/question-editor/save"

?>"

>

<input type="hidden" name="_csrf" value="<?= htmlspecialchars((string) $csrfToken) ?>">

<?php if ($returnUrl !== ''): ?><input type="hidden" name="return" value="<?= htmlspecialchars($returnUrl) ?>"><?php endif; ?>

<section class="card"><h3>

Taxonomy

</h3>

<hr>

<?php

require __DIR__

    . "/partials/taxonomy.php";

?>

</section><section class="card"><h3>

Question

</h3>

<hr>

<?php

require __DIR__

    . "/partials/question.php";

?>

</section><section class="card"><h3>

Answer Options

</h3>

<hr>

<?php

require __DIR__

    . "/partials/options.php";
?>
</section><section class="card"><h3>

Save

</h3>

<hr>

<?php

require __DIR__

    . "/partials/actions.php";

?></section>

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
