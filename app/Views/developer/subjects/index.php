<?php

use App\Services\Developer\DeveloperViewService;
use App\Constants\Status;

$pageHeader =
    DeveloperViewService::pageHeader(

        "Subjects",

        "Manage the global subject repository."

    );

$summary =
    DeveloperViewService::summary([

        "Total Subjects" =>
            count($subjects)

    ]);

$actionBar =
    DeveloperViewService::actionBar([

        [

            "label" =>
                "➕ Create Subject",

            "href" =>
                "/subject/create"

        ]

    ]);

require __DIR__
    . "/../components/page-header.php";

require __DIR__
    . "/../components/summary.php";

?>

<form method="GET">

<input
type="hidden"
name="page"
value="subjects"
>

<input
type="search"
name="search"
placeholder="Search subjects..."
value="<?= htmlspecialchars(
    $search ?? ""
) ?>"
>

<button>

🔍 Search

</button>

</form>

<?php

require __DIR__
    . "/../components/action-bar.php";

if (empty($subjects)) {

    $emptyMessage =
        "No subjects found.";

    require __DIR__
        . "/../components/empty-state.php";

    return;

}

foreach ($subjects as $subject) {

    $entityCard =
        DeveloperViewService::entity(

            $subject,

            [

                "ID" =>
                    $subject["id"],

                "Status" =>
                    ucfirst(
                        $subject["status"]
                    )

            ],

            [

                [

                    "label" =>
                        "👁 View",

                    "href" =>
                        "/subject/view?id="
                        .
                        urlencode(
                            $subject["id"]
                        )

                ],

                [

                    "label" =>
                        "✏ Edit",

                    "href" =>
                        "/subject/edit?id="
                        .
                        urlencode(
                            $subject["id"]
                        )

                ],

                [

                    "label" =>

                        ($subject["status"] === Status::ACTIVE)

                        ?

                        "🗃 Archive"

                        :

                        "♻ Activate",

                    "method" => "POST",

                    "href" =>

                        (($subject["status"] === Status::ACTIVE)
                            ? "/subject/archive?id="
                            : "/subject/activate?id=")
                        . urlencode($subject["id"])

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
