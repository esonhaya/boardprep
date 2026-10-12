<?php

declare(strict_types=1);

$notFound = (int) ($statusCode ?? 404) === 404;
?>
<section class="ui-page error-page" aria-labelledby="http-error-title">
    <p class="eyebrow"><?= $notFound ? 'Page not found' : 'Request unavailable' ?></p>
    <h1 id="http-error-title"><?= $notFound ? 'We couldn’t find that page' : 'That action isn’t available' ?></h1>
    <p><?= $notFound
        ? 'The address may be incorrect, or the page may have moved.'
        : 'This page does not accept that type of request. Return to BoardPrep and choose an available action.' ?></p>
    <div class="page-actions" aria-label="Where to go next">
        <a class="button button-green" href="/">Go to home</a>
        <a class="button button-outline" href="/exams">Browse examinations</a>
        <a class="button button-outline" href="/dashboard">Open dashboard</a>
    </div>
</section>
