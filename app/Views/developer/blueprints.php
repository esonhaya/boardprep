<?php

$pageTitle =
    "Blueprint Manager";

$pageDescription =
    "Manage blueprint versions and exam templates.";

$summary = [

    "Total Blueprints" =>
        count($blueprints ?? [])

];

$actions = [

    [

        "label" =>
            "➕ Create Blueprint",

        "href" =>
            "/blueprints/create"

    ]

];

require __DIR__
    . "/components/page-header.php";

require __DIR__
    . "/components/summary.php";

require __DIR__
    . "/components/action-bar.php";

if (empty($blueprints)) {

    $emptyMessage =
        "No blueprints found.";

    require __DIR__
        . "/components/empty-state.php";

    return;

}

foreach (($blueprints ?? []) as $blueprint) {

    $entity = [

        "name" =>
            $blueprint["name"] ?? "",

        "description" =>
            ""

    ];

    $entityDetails = [

        "ID" =>
            $blueprint["id"] ?? "",

        "Board" =>
            $blueprint["board"] ?? "",

        "Subject" =>
            $blueprint["subject"] ?? "",

        "Version" =>
            $blueprint["version"] ?? ""

    ];

    $entityActions = [

        [

            "label" =>
                "👁️ View",

            "href" =>
                "/blueprint/view?id="
                . urlencode(
                    $blueprint["id"] ?? ""
                )

        ],

        [

            "label" =>
                "✏️ Edit",

            "href" =>
                "/blueprint/edit?id="
                . urlencode(
                    $blueprint["id"] ?? ""
                )

        ]

    ];

    require __DIR__
        . "/components/entity-card.php";

}

?>

<hr>

<p>

<a href="/developer">

🏠 Back to Developer Dashboard

</a>

</p>
