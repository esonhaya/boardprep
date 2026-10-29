<?php
$continuityAttempt = $studyContinuity['latest_attempt'];
$continuityId = is_scalar($continuityAttempt['id'] ?? null) ? trim((string) $continuityAttempt['id']) : '';
$continuityTopic = \App\Services\Learning\LearningHistoryService::topicOf($continuityAttempt);
$continuityBoard = \App\Services\Shared\TaxonomyStorageService::label('boards', $continuityAttempt['board'] ?? '');
$continuitySubject = \App\Services\Shared\TaxonomyStorageService::label('subjects', $continuityAttempt['subject'] ?? '');
$continuityScore = (int) ($continuityAttempt['score'] ?? 0);
$continuityTotal = (int) ($continuityAttempt['total'] ?? 0);
$continuityPercentage = (int) ($continuityAttempt['percentage'] ?? 0);
$continuityContext = implode(' · ', array_filter([$continuityBoard, $continuitySubject]));
?>
<section class="dashboard-study-continuity" aria-labelledby="dashboard-continuity-heading">
    <article class="card dashboard-continuity-result">
        <div>
            <p class="eyebrow">Pick up where you left off</p>
            <h2 id="dashboard-continuity-heading">Your latest completed quiz</h2>
            <p class="dashboard-continuity-topic"><?= htmlspecialchars($continuityTopic, ENT_QUOTES, 'UTF-8') ?></p>
            <?php if ($continuityContext !== ''): ?><p class="muted-note"><?= htmlspecialchars($continuityContext, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
            <p><strong><?= $continuityScore ?>/<?= $continuityTotal ?></strong> correct · <?= $continuityPercentage ?>% score</p>
            <?php if (($studyContinuity['latest_review_available'] ?? false) !== true): ?>
                <p class="muted-note">Detailed answer review was not saved for this older quiz.</p>
            <?php endif; ?>
        </div>
        <a class="button button-green" href="<?= htmlspecialchars((string) ($studyContinuity['latest_result_url'] ?? '/history'), ENT_QUOTES, 'UTF-8') ?>">
            <?= $continuityId !== '' ? 'Review latest result' : 'Open quiz history' ?>
        </a>
    </article>
    <nav class="dashboard-continuity-actions" aria-label="Continue studying">
        <?php if (($studyContinuity['has_mistake_or_skipped_review'] ?? false) === true): ?>
            <a class="card dashboard-continuity-action" href="/mistake-notebook">
                <strong>Review your Mistake Notebook</strong>
                <span>Revisit recorded incorrect answers and skipped questions.</span>
            </a>
        <?php endif; ?>
        <a class="card dashboard-continuity-action" href="/study-library">
            <strong>Browse published study materials</strong>
            <span>Open available lessons and references.</span>
        </a>
        <a class="card dashboard-continuity-action" href="/flashcards">
            <strong>Continue flashcard review</strong>
            <span>Review cards available for your selected study filters.</span>
        </a>
    </nav>
</section>
