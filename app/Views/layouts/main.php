<!DOCTYPE html>
<html lang="en">
<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>

        <?= $pageTitle ?? "BoardPrep" ?>

    </title>

    <link
        rel="stylesheet"
        href="/assets/css/style.css"
    >

</head>

<body>

<header>

    <h1>
        📚 BoardPrep
    </h1>

    <p>
        Prepare with Confidence.
    </p>

    <nav>

        <a href="/">
            Home
        </a>
        <a href="/dashboard">
            Dashboard
        </a>
        <a href="/profile">
            Profile
        </a>
        <a href="/quiz">
            Quiz
        </a>
        <a href="/developer">
            Developer
        </a>

    </nav>

    <hr>

</header>

<main>

    <?= $content ?>

</main>

<?php

if (
    !empty($showDeveloperFooter)
) {

    require __DIR__ . "/../developer/footer.php";

}

?>


<footer>

    <hr>

    <p>

        BoardPrep v0.1

    </p>

</footer>

</body>
</html>
