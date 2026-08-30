<!DOCTYPE html>
<html lang="en">
<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>

        <?= htmlspecialchars((string) ($pageTitle ?? "BoardPrep"), ENT_QUOTES, "UTF-8") ?>

    </title>

    <link
        rel="stylesheet"
        href="/assets/css/style.css"
    >

</head>

<body>

<a class="skip-link" href="#main-content">Skip to main content</a>

<header class="site-header">

    <a class="site-brand" href="/" aria-label="BoardPrep home">
        <span aria-hidden="true">📚</span> BoardPrep
    </a>

    <p>
        Prepare with Confidence.
    </p>

    <?php $currentPath = parse_url($_SERVER["REQUEST_URI"] ?? "/", PHP_URL_PATH) ?: "/"; ?>
    <nav aria-label="Primary navigation">

        <a href="/"<?= $currentPath === "/" ? ' aria-current="page"' : "" ?>>
            Home
        </a>
        <a href="/dashboard"<?= $currentPath === "/dashboard" ? ' aria-current="page"' : "" ?>>
            Dashboard
        </a>
        <a href="/history"<?= $currentPath === "/history" ? ' aria-current="page"' : "" ?>>
            History
        </a>
        <a href="/progress"<?= $currentPath === "/progress" ? ' aria-current="page"' : "" ?>>
            Progress
        </a>
        <a href="/study"<?= $currentPath === "/study" ? ' aria-current="page"' : "" ?>>
            Study Plan
        </a>
        <a href="/profile"<?= $currentPath === "/profile" ? ' aria-current="page"' : "" ?>>
            Profile
        </a>
        <a href="/quiz"<?= $currentPath === "/quiz" ? ' aria-current="page"' : "" ?>>
            Quiz
        </a>
        <a href="/developer"<?= str_starts_with($currentPath, "/developer") ? ' aria-current="page"' : "" ?>>
            Developer
        </a>

    </nav>

    <hr>

</header>

<main id="main-content" tabindex="-1">

<?php $layoutFlash = \SessionService::consumeFlash(); ?>
<?php if (is_array($layoutFlash) && is_scalar($layoutFlash["message"] ?? null)): ?>
<p class="flash-message flash-<?= htmlspecialchars((string) ($layoutFlash["type"] ?? "info")) ?>" role="alert">
    <?= htmlspecialchars((string) $layoutFlash["message"]) ?>
</p>
<?php endif; ?>

    <?= $content ?>

</main>

<?php

if (
    !empty($showDeveloperFooter)
) {

    require __DIR__ . "/../developer/footer.php";

}

?>


<footer class="site-footer">

    <hr>

    <p>

        BoardPrep v0.1

    </p>

</footer>

</body>
</html>
