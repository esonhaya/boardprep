<!DOCTYPE html>
<html lang="en">
<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        <?= $pageTitle ?? "BoardPrep Developer" ?>
    </title>

    <link
        rel="stylesheet"
        href="/assets/css/style.css"
    >

</head>

<body>

<div class="developer-shell">

    <?php require __DIR__ . "/../developer/sidebar.php"; ?>

    <div class="developer-content">

        <?php require __DIR__ . "/../developer/topbar.php"; ?>

        <main>

            <?= $content ?>

        </main>

    </div>

</div>

</body>
</html>
