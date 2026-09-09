<?php

$summary = $summary ?? [];
$review = $review ?? [];
$actions = $actions ?? [];
$historical = ($historical ?? false) === true;
$completedAt = is_string($completedAt ?? null) ? $completedAt : null;
$historicalContext = is_array($historicalContext ?? null) ? $historicalContext : [];
$reviewUnavailable = ($reviewUnavailable ?? false) === true;
$taxonomyLabel = static fn(string $collection, mixed $id): string => \App\Services\Shared\TaxonomyStorageService::label($collection, $id);

?>

<section class="page-header"><div><h1><?= $historical ? 'Historical Quiz Result' : 'Quiz Result' ?></h1><p>Review what you know and choose your next useful step.</p><?php if ($historical): ?><p class="muted-note"><?php $context = array_values(array_filter([$taxonomyLabel('boards', $historicalContext['board'] ?? ''), $taxonomyLabel('subjects', $historicalContext['subject'] ?? ''), \App\Support\Presentation\UiFormatter::statusLabel((string) ($historicalContext['mode'] ?? ''))])); ?><?= htmlspecialchars(implode(' · ', $context)) ?><?php if ($completedAt !== null): ?> · Completed <?= htmlspecialchars(\App\Support\Presentation\UiFormatter::formatDateTime($completedAt)) ?><?php endif; ?></p><?php endif; ?></div></section>

<div class="result-summary">
    <div class="result-score">
        <p class="eyebrow">Score</p>
        <h2><?= $summary["score"] ?? 0 ?> / <?= $summary["total"] ?? 0 ?></h2>
    </div>
    <div class="result-percentage">
        <p class="eyebrow">Accuracy</p>
        <h3><?= $summary["percentage"] ?? 0 ?>%</h3>
    </div>
</div>

<?php if (!empty($actions)): ?>
<section class="result-next-steps">
    <h2>What Next?</h2>

    <?php foreach ($actions as $action): ?>
        <div class="result-action">
            <h3>
                <a href="<?= htmlspecialchars((string) ($action["url"] ?? "/study")) ?>">
                    <?= htmlspecialchars((string) ($action["label"] ?? "Continue")) ?>
                </a>
            </h3>
            <p>
                <?= htmlspecialchars((string) ($action["reason"] ?? "")) ?>
            </p>
        </div>
    <?php endforeach; ?>
</section>
<?php endif; ?>

<?php if ($reviewUnavailable): ?>
<div class="empty-state"><h2>Detailed review is unavailable</h2><p>This attempt's score is preserved, but its question-by-question details were not saved.</p><p><a class="button button-outline" href="/history">Back to History</a></p></div>
<?php else: ?>
<h2>Answer Review</h2>

<?php foreach ($review as $index => $item): ?>
<article class="question-review <?= !empty($item["correct"]) ? "is-correct" : "is-incorrect" ?>">
    <h3>
        <?= ($index + 1) ?>.
        <?= htmlspecialchars(
            $item["question"]["question"] ?? ""
        ) ?>
    </h3>
    <?php if (!empty($item["question"]["content_blocks"])): ?>
        <?= \App\Services\Question\StructuredContentService::render($item["question"]) ?>
    <?php endif; ?>

    <div class="answer-review">
        <div class="answer-review-selected">
            <p class="answer-label">Your answer</p>
            <strong class="answer-value"><?= htmlspecialchars(
                ($item["answered"] ?? false)
                    ? (string) ($item["userAnswer"] ?? '')
                    : "No answer"
            ) ?></strong>
        </div>

        <?php if (!empty($item["correct"])): ?>
            <p class="answer-status answer-status-correct"><strong>Correct answer</strong></p>
        <?php else: ?>
            <p class="answer-status answer-status-incorrect"><strong>Incorrect answer</strong></p>

            <div class="answer-review-correct">
                <p class="answer-label">Correct answer</p>
                <strong class="answer-value"><?= htmlspecialchars(
                    $item["question"]["answer"] ?? ""
                ) ?></strong>
            </div>
        <?php endif; ?>

        <div class="answer-review-explanation">
            <p class="answer-label">Explanation</p>
            <p><?= htmlspecialchars(
                $item["question"]["explanation"] ?? ""
            ) ?></p>
        </div>
    </div>
</article>

<hr>
<?php endforeach; ?>
<?php endif; ?>
