<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$home = file_get_contents($root . '/app/Views/home/index.php');
$exams = file_get_contents($root . '/app/Views/home/exams.php');
$preview = file_get_contents($root . '/app/Views/components/product-preview.php');
$css = file_get_contents($root . '/public/assets/css/rich-product.css');
$boards = json_decode(file_get_contents($root . '/storage/boards/boards.json'), true, 512, JSON_THROW_ON_ERROR);

foreach ([
    'data-ui-archetype="marketing"' => $home,
    'product-preview-hero' => $home,
    '$preview=' . "'practice'" => $home,
    '$preview=' . "'insights'" => $home,
    'Select the board or eligibility exam' => $home,
    'ui-product-preview-window' => $preview,
    'product-preview-chart' => $preview,
    'product-preview-options' => $preview,
    'product-preview-insight' => $preview,
    '.button,.button:visited' => $css,
    '.button:focus-visible' => $css,
    '@media(prefers-reduced-motion:reduce)' => $css,
    'min-height:44px' => $css,
] as $needle => $haystack) {
    if (strpos($haystack, $needle) === false) {
        throw new RuntimeException('missing visual contract: ' . $needle);
    }
}

if (strpos($exams, 'button-disabled" href=') !== false || strpos($exams, 'role="status"') === false) {
    throw new RuntimeException('unavailable exam state must not be a link');
}
if (($boards[0]['visual']['icon'] ?? '') !== 'let' || ($boards[1]['visual']['icon'] ?? '') !== 'cse') {
    throw new RuntimeException('exam identity registry is missing LET/CSE marks');
}

echo '[PASS] Homepage CTA, preview, exam identity, process, and reduced-motion contracts verified.' . PHP_EOL;
