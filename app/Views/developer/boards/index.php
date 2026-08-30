<?php

use App\Services\Developer\DeveloperViewService;

$pageHeader =
    DeveloperViewService::pageHeader(
        "Board Manager",
        "Manage all supported board examinations."
    );

$summary =
    DeveloperViewService::summary([

        "Total Boards" =>
            count($boards ?? []),

        "Active" =>
            count(
                array_filter(
                    $boards ?? [],
                    fn(array $board): bool =>
                        ($board["status"] ?? "")
                        ===
                        "active"
                )
            ),

        "Archived" =>
            count(
                array_filter(
                    $boards ?? [],
                    fn(array $board): bool =>
                        ($board["status"] ?? "")
                        !==
                        "active"
                )
            )

    ]);

$actionBar =
    DeveloperViewService::actionBar([

        [

            "label" =>
                "➕ Create Board",

            "href" =>
                "/board/create"

        ]

    ]);

require __DIR__
    . "/../components/page-header.php";

require __DIR__
    . "/../components/summary.php";

require __DIR__
    . "/../components/action-bar.php";

if (empty($boards)) {

    $emptyMessage =
        "No boards have been created yet.";

    require __DIR__
        . "/../components/empty-state.php";

    return;

}

foreach ($boards as $board) {

    $entityCard =
        DeveloperViewService::entity(

            $board,

            [

                "ID" =>
                    $board["id"],

                "Status" =>
                    ucfirst(
                        $board["status"]
                    ),

                "Subjects" =>
                    count(
                        $board["subjects"] ?? []
                    )

            ],

            [

                [

                    "label" =>
                        "👁 View",

                    "href" =>
                        "/board/view?id="
                        .
                        urlencode(
                            $board["id"]
                        )

                ],

                [

                    "label" =>

                        ($board["status"] === "active")

                        ?

                        "🗃 Archive"

                        :

                        "♻ Activate",

                    "method" => "POST",

                    "href" =>

                        (($board["status"] === "active")
                            ? "/board/archive?id="
                            : "/board/activate?id=")
                        . urlencode($board["id"])

                ]

            ]

        );

    require __DIR__
        . "/../components/entity-card.php";

}

?>

<hr>

<p>

<a href="/developer">

🏠 Back to Dashboard

</a>

</p>
