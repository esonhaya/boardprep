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
    <link rel="stylesheet" href="/assets/css/rich-product.css">

</head>

<body class="ui-theme">

<a class="skip-link" href="#developer-main">Skip to main content</a>

<div class="developer-shell">

    <?php require __DIR__ . "/../developer/sidebar.php"; ?>

    <div class="developer-content">

        <?php require __DIR__ . "/../developer/topbar.php"; ?>

        <main id="developer-main" tabindex="-1">

            <?= $content ?>

        </main>

    </div>

</div>

<script src="/assets/js/app-shell.js"></script>

</body>
</html>
