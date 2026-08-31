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
            <span class="sr-only">Toggle navigation</span><span aria-hidden="true">☰</span>
        </button>
    </div>

    <?php $currentPath = parse_url($_SERVER["REQUEST_URI"] ?? "/", PHP_URL_PATH) ?: "/"; ?>
    <nav id="learner-nav" aria-label="Primary navigation">

        <a href="/"<?= $currentPath === "/" ? ' aria-current="page"' : "" ?>>
            <?php $icon='home';$iconSize='sm';require __DIR__ . "/../components/icon.php"; ?>Home
        </a>
        <a href="/quiz"<?= $currentPath === "/quiz" ? ' aria-current="page"' : "" ?>><?php $icon='practice';$iconSize='sm';require __DIR__ . "/../components/icon.php"; ?>Practice</a>
        <a href="/quiz?mode=exam"><?php $icon='exam';$iconSize='sm';require __DIR__ . "/../components/icon.php"; ?>Exam mode</a>
        <a href="/history"<?= $currentPath === "/history" ? ' aria-current="page"' : "" ?>>
            <?php $icon='history';$iconSize='sm';require __DIR__ . "/../components/icon.php"; ?>History
        </a>
        <a href="/progress"<?= $currentPath === "/progress" ? ' aria-current="page"' : "" ?>>
            <?php $icon='progress';$iconSize='sm';require __DIR__ . "/../components/icon.php"; ?>Progress
        </a>
        <a href="/exams"<?= $currentPath === "/exams" ? ' aria-current="page"' : "" ?>><?php $icon='book';$iconSize='sm';require __DIR__ . "/../components/icon.php"; ?>Exams &amp; subjects</a>
        <?php if (\App\Core\App::config('environment') !== 'production'): ?>
            <a href="/developer"<?= str_starts_with($currentPath, "/developer") ? ' aria-current="page"' : "" ?>>
                <?php $icon='settings';$iconSize='sm';require __DIR__ . "/../components/icon.php"; ?>Developer
            </a>
        <?php endif; ?>

    </nav>

</header>

<main id="main-content" tabindex="-1">

<span class="sr-only">Learning Overview Recommended Next Step Subject Performance Recent Quiz History</span>

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


<footer class="site-footer"><p>BoardPrep v0.1 · Prepare with confidence.</p></footer>
<script src="/assets/js/app-shell.js"></script>

</body>
</html>
