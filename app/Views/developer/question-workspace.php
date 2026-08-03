<?php

$contentMode =
    $contentMode ?? "create";

$context =
    $context ?? [];

$breadcrumbs = [];

foreach (

    [

        "board",
        "subject",
        "domain",
        "topic",
        "concept"

    ]

    as $key

) {

    if (

        !empty($context[$key])

    ) {

        $breadcrumbs[] =
            $context[$key];

    }

}

?>

<h2>

Question Workspace

</h2>

<p>

<?= ucfirst($contentMode) ?>

Question

</p>

<?php if (!empty($breadcrumbs)): ?>

<p>

<strong>

<?= htmlspecialchars(

    implode(

        " > ",

        $breadcrumbs

    )

) ?>

</strong>

</p>

<?php endif; ?>

<hr>

<details open>

<summary>

Workspace

</summary>

<br>

<?php

require __DIR__

    . "/partials/workspace-header.php";

?>

</details>

<br>

<details>

<summary>

Quick Actions

</summary>

<br>

<p>

<a href="/developer">

🏠 Dashboard

</a>

</p>

<p>

<a href="/coverage">

📊 Coverage Matrix

</a>

</p>

<p>

<a href="/taxonomy">

🧬 Taxonomy

</a>

</p>

<p>

<a href="/blueprints">

📋 Blueprints

</a>

</p>

<p>

<a href="/developer?action=analyze">

🩺 Analyze Repository

</a>

</p>

</details>

<hr>

<?php

require __DIR__

    . "/question-form.php";

?>

<hr>

<p>

<a href="/question-editor">

← Back to Question Library

</a>

</p>
