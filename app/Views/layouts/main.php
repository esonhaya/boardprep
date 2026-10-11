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
    <link rel="stylesheet" href="/assets/css/rich-product.css">

</head>

<body class="ui-theme">

<a class="skip-link" href="#main-content">Skip to main content</a>

<header class="site-header">
    <div class="site-header-inner">
        <?php $brandClass = "brand site-brand"; require __DIR__ . "/../components/brand.php"; ?>
        <button class="mobile-menu-button" type="button" aria-expanded="false" aria-controls="learner-nav">
            <span class="sr-only">Open primary navigation</span><span aria-hidden="true">☰</span>
        </button>
    </div>

    <?php $currentPath = parse_url($_SERVER["REQUEST_URI"] ?? "/", PHP_URL_PATH) ?: "/"; ?>
    <?php
    $isStudyPath = in_array($currentPath, ["/study", "/study-library"], true);
    $isProgressPath = in_array($currentPath, ["/progress", "/dashboard"], true);
    ?>
    <nav id="learner-nav" class="learner-navigation" aria-label="Primary navigation">
        <a class="site-nav-link" href="/"<?= $currentPath === "/" ? ' aria-current="page"' : "" ?>>
            <?php $icon='home';$iconSize='sm';require __DIR__ . "/../components/icon.php"; ?>Home
        </a>
        <a class="site-nav-link" href="/exams"<?= $currentPath === "/exams" ? ' aria-current="page"' : "" ?>><?php $icon='exam';$iconSize='sm';require __DIR__ . "/../components/icon.php"; ?>Exams</a>
        <a class="site-nav-link" href="/study-library"<?= $isStudyPath ? ' aria-current="page"' : "" ?>><?php $icon='book';$iconSize='sm';require __DIR__ . "/../components/icon.php"; ?>Study</a>
        <a class="site-nav-link" href="/quiz"<?= str_starts_with($currentPath, "/quiz") ? ' aria-current="page"' : "" ?>><?php $icon='practice';$iconSize='sm';require __DIR__ . "/../components/icon.php"; ?>Practice</a>
        <a class="site-nav-link" href="/flashcards"<?= str_starts_with($currentPath, "/flashcards") ? ' aria-current="page"' : "" ?>><?php $icon='book';$iconSize='sm';require __DIR__ . "/../components/icon.php"; ?>Flashcards</a>
        <a class="site-nav-link" href="/progress"<?= $isProgressPath ? ' aria-current="page"' : "" ?>><?php $icon='progress';$iconSize='sm';require __DIR__ . "/../components/icon.php"; ?>Progress</a>
        <a class="site-nav-link" href="/history"<?= str_starts_with($currentPath, "/history") ? ' aria-current="page"' : "" ?>>
            <?php $icon='history';$iconSize='sm';require __DIR__ . "/../components/icon.php"; ?>History
        </a>
        <a class="site-nav-link" href="/privacy"<?= str_starts_with($currentPath, "/privacy") ? ' aria-current="page"' : "" ?>><?php $icon='settings';$iconSize='sm';require __DIR__ . "/../components/icon.php"; ?>Privacy</a>
        <?php if (\App\Core\App::config('environment') !== 'production'): ?>
            <a class="site-nav-link" href="/developer"<?= str_starts_with($currentPath, "/developer") ? ' aria-current="page"' : "" ?>>
                <?php $icon='settings';$iconSize='sm';require __DIR__ . "/../components/icon.php"; ?>Developer
            </a>
        <?php endif; ?>

    </nav>

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


<footer class="site-footer"><p>BoardPrep v<?= htmlspecialchars((string) \App\Core\App::config('version', '1.0.1'), ENT_QUOTES, 'UTF-8') ?> · Prepare with confidence. <a href="/privacy">Data and privacy</a></p></footer>
<script src="/assets/js/app-shell.js"></script>

</body>
</html>
