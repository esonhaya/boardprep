<?php

$summary = $summary ?? [];
$review = $review ?? [];
$quizSession = is_array($quizSession ?? null) ? $quizSession : [];
$studyReferencesByQuestion = is_array($studyReferencesByQuestion ?? null) ? $studyReferencesByQuestion : [];
$actions = $actions ?? [];
$historical = ($historical ?? false) === true;
$isExamSimulation = !$historical && ($quizSession['mode'] ?? '') === 'exam';
$completedAt = is_string($completedAt ?? null) ? $completedAt : null;
$historicalContext = is_array($historicalContext ?? null) ? $historicalContext : [];
$historicalMode = is_scalar($historicalContext['mode'] ?? null) ? (string) $historicalContext['mode'] : '';
$historicalModeLabel = $historicalMode === 'exam'
    ? 'Exam simulation'
    : ($historicalMode !== '' ? \App\Support\Presentation\UiFormatter::statusLabel($historicalMode) : null);
$historicalTimerMode = is_scalar($historicalContext['timer_mode'] ?? null)
    ? (string) $historicalContext['timer_mode']
    : '';
$historicalTimeLimit = is_numeric($historicalContext['time_limit_minutes'] ?? null)
    ? max(0, (int) $historicalContext['time_limit_minutes'])
    : 0;
$reviewUnavailable = ($reviewUnavailable ?? false) === true;
$taxonomyLabel = static fn(string $collection, mixed $id): string => \App\Services\Shared\TaxonomyStorageService::label($collection, $id);

?>

<div class="quiz-result-page">
<section class="page-header"><div><p class="eyebrow"><?= $historical ? 'Attempt review' : ($isExamSimulation ? 'Exam simulation complete' : 'Practice complete') ?></p><h1><?= $historical ? 'Historical Quiz Result' : ($isExamSimulation ? 'Exam Simulation Result' : 'Quiz Result') ?></h1><p>Review what you know and choose your next useful step.</p><?php if ($isExamSimulation || ($historical && $historicalMode === 'exam')): ?><p class="exam-simulation-disclosure">This BoardPrep study simulator is separate from the official board examination.</p><?php endif; ?><?php if ($isExamSimulation): ?><?php if (($quizSession['timer_mode'] ?? '') === 'timed'): ?><p class="muted-note"><?= !empty($quizSession['time_expired']) ? 'Time expired; your saved answers were submitted.' : 'Timed session completed.' ?> Time limit: <?= (int) ($quizSession['time_limit_minutes'] ?? 0) ?> minutes.</p><?php else: ?><p class="muted-note">Untimed session.</p><?php endif; ?><?php endif; ?><?php if ($historical): ?><?php $context = array_values(array_filter([$taxonomyLabel('boards', $historicalContext['board'] ?? ''), $taxonomyLabel('subjects', $historicalContext['subject'] ?? ''), $historicalModeLabel, $historicalMode === 'exam' ? ($historicalTimerMode === 'timed' ? 'Timed simulation' . ($historicalTimeLimit > 0 ? ' · ' . $historicalTimeLimit . ' min' : '') : 'Untimed simulation') : null])); ?><p class="muted-note"><?= htmlspecialchars(implode(' · ', $context), ENT_QUOTES, 'UTF-8') ?><?php if ($completedAt !== null): ?> · Completed <?= htmlspecialchars(\App\Support\Presentation\UiFormatter::formatDateTime($completedAt)) ?><?php endif; ?></p><?php endif; ?></div></section>

<section class="result-summary" aria-label="Quiz score summary">
    <div class="result-score">
        <p class="eyebrow">Score</p>
        <h2><?= $summary["score"] ?? 0 ?> / <?= $summary["total"] ?? 0 ?></h2>
    </div>
    <div class="result-percentage">
        <p class="eyebrow">Accuracy</p>
        <h3><?= $summary["percentage"] ?? 0 ?>%</h3>
    </div>
</section>

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
<section class="result-answer-review" aria-labelledby="answer-review-heading">
<h2 id="answer-review-heading">Answer Review</h2>

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
        <?php $questionId = is_scalar($item['question']['id'] ?? null) ? (string) $item['question']['id'] : ''; $studyReferences = $studyReferencesByQuestion[$questionId] ?? []; ?>
        <?php if (is_array($studyReferences) && $studyReferences !== []): ?>
            <div class="quiz-study-support">
                <h4>Study support</h4>
                <ul>
                    <?php foreach ($studyReferences as $reference): ?>
                        <?php
                        $materialId = is_scalar($reference['material_id'] ?? null) ? (string) $reference['material_id'] : '';
                        $reviewGuidance = is_scalar($reference['review_guidance'] ?? null) ? trim((string) $reference['review_guidance']) : '';
                        $lessonId = is_scalar($reference['lesson_id'] ?? null) ? trim((string) $reference['lesson_id']) : '';
                        $sectionId = is_scalar($reference['section_id'] ?? null) ? trim((string) $reference['section_id']) : '';
                        $sourceReferences = array_values(array_filter((array) ($reference['source_references'] ?? []), static fn(mixed $source): bool => is_array($source) && trim((string) ($source['url'] ?? '')) !== ''));
                        ?>
                        <?php if ($materialId !== ''): ?>
                            <li>
                                <?php if ($sectionId !== '' && $lessonId !== '' && is_scalar($reference['url'] ?? null)): ?>
                                    <a href="<?= htmlspecialchars((string) $reference['url'], ENT_QUOTES, 'UTF-8') ?>">Open supporting lesson section: <?= htmlspecialchars((string) ($reference['title'] ?? 'Study lesson'), ENT_QUOTES, 'UTF-8') ?></a>
                                <?php elseif ($sourceReferences !== []): ?>
                                    <p class="quiz-review-guidance">This source association has no exact lesson section link.</p>
                                    <ul>
                                        <?php foreach ($sourceReferences as $source): ?>
                                            <li><a href="<?= htmlspecialchars((string) $source['url'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">Open source reference: <?= htmlspecialchars((string) ($source['title'] ?? 'Registered source'), ENT_QUOTES, 'UTF-8') ?></a><?php if (trim((string) ($source['authority'] ?? '')) !== ''): ?> · <?= htmlspecialchars((string) $source['authority'], ENT_QUOTES, 'UTF-8') ?><?php endif; ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php else: ?>
                                    <span>Source or exact lesson-section mapping is under review.</span>
                                <?php endif; ?>
                                <?php if ($reviewGuidance !== ''): ?>
                                    <p class="quiz-review-guidance"><?= htmlspecialchars($reviewGuidance, ENT_QUOTES, 'UTF-8') ?></p>
                                <?php endif; ?>
                            </li>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
    </div>
</article>

<hr>
<?php endforeach; ?>
</section>
<?php endif; ?>
</div>
<script src="/assets/js/quiz-focus.js" defer></script>
