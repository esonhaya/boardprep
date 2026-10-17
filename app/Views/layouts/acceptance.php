<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="color-scheme" content="light">
    <title><?= htmlspecialchars((string) ($pageTitle ?? 'Human Acceptance Center · BoardPrep'), ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="/assets/css/acceptance-center.css">
    <script src="/assets/js/acceptance-center.js" defer></script>
</head>
<body>
<?= $content ?>
</body>
</html>
