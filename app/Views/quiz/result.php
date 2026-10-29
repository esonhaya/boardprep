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
$reviewFilter = is_string($reviewFilter ?? null) && in_array($reviewFilter, ['all', 'incorrect', 'unanswered', 'correct'], true)
    ? $reviewFilter
    : 'all';
$reviewFilterBaseUrl = is_string($reviewFilterBaseUrl ?? null)
    ? $reviewFilterBaseUrl
    : ($historical ? '/history' : '/quiz?action=result');
$allReviewCount = is_array($review) ? count($review) : 0;
$reviewFilterCounts = ['all' => $allReviewCount, 'incorrect' => 0, 'unanswered' => 0, 'correct' => 0];
$visibleReview = [];
foreach (is_array($review) ? $review : [] as $reviewIndex => $item) {
    if (!is_array($item)) continue;
    $answered = ($item['answered'] ?? false) === true;
    $correct = ($item['correct'] ?? false) === true;
    if (!$answered) $reviewFilterCounts['unanswered']++;
    elseif ($correct) $reviewFilterCounts['correct']++;
    else $reviewFilterCounts['incorrect']++;

    $include = $reviewFilter === 'all'
        || ($reviewFilter === 'incorrect' && $answered && !$correct)
        || ($reviewFilter === 'unanswered' && !$answered)
        || ($reviewFilter === 'correct' && $answered && $correct);
    if ($include) $visibleReview[$reviewIndex] = $item;
}
$review = $visibleReview;
$scoreTotal = max(0, (int) ($summary['total'] ?? 0));
$scoreCorrect = min($scoreTotal, max(0, (int) ($summary['score'] ?? $summary['correct'] ?? 0)));
$scoreUnanswered = min($scoreTotal, max(0, (int) ($summary['unanswered'] ?? 0)));
$scoreAnswered = max(0, $scoreTotal - $scoreUnanswered);
$scoreIncorrect = max(0, $scoreAnswered - $scoreCorrect);
$scorePercentage = is_numeric($summary['percentage'] ?? null) ? (float) $summary['percentage'] : 0.0;
$answerAccuracy = $scoreAnswered > 0 ? ($scoreCorrect / $scoreAnswered) * 100 : null;
$formatPercentage = static fn(float $value): string => rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.') . '%';
$notesPracticeReview = is_array($notesPracticeReview ?? null) ? $notesPracticeReview : null;
$mistakeRecovery = is_array($mistakeRecovery ?? null) ? $mistakeRecovery : null;
$taxonomyLabel = static fn(string $collection, mixed $id): string => \App\Services\Shared\TaxonomyStorageService::label($collection, $id);

?>

<div class="quiz-result-page">
<section class="page-header"><div><p class="eyebrow"><?= $historical ? 'Attempt review' : ($isExamSimulation ? 'Exam simulation complete' : 'Practice complete') ?></p><h1><?= $historical ? 'Historical Quiz Result' : ($isExamSimulation ? 'Exam Simulation Result' : 'Quiz Result') ?></h1><p>Review what you know and choose your next useful step.</p><?php if ($isExamSimulation || ($historical && $historicalMode === 'exam')): ?><p class="exam-simulation-disclosure">This BoardPrep study simulator is separate from the official board examination.</p><?php endif; ?><?php if ($isExamSimulation): ?><?php if (($quizSession['timer_mode'] ?? '') === 'timed'): ?><p class="muted-note"><?= !empty($quizSession['time_expired']) ? 'Time expired; your saved answers were submitted.' : 'Timed session completed.' ?> Time limit: <?= (int) ($quizSession['time_limit_minutes'] ?? 0) ?> minutes.</p><?php else: ?><p class="muted-note">Untimed session.</p><?php endif; ?><?php endif; ?><?php if ($historical): ?><?php $context = array_values(array_filter([$taxonomyLabel('boards', $historicalContext['board'] ?? ''), $taxonomyLabel('subjects', $historicalContext['subject'] ?? ''), $historicalModeLabel, $historicalMode === 'exam' ? ($historicalTimerMode === 'timed' ? 'Timed simulation' . ($historicalTimeLimit > 0 ? ' · ' . $historicalTimeLimit . ' min' : '') : 'Untimed simulation') : null])); ?><p class="muted-note"><?= htmlspecialchars(implode(' · ', $context), ENT_QUOTES, 'UTF-8') ?><?php if ($completedAt !== null): ?> · Completed <?= htmlspecialchars(\App\Support\Presentation\UiFormatter::formatDateTime($completedAt)) ?><?php endif; ?></p><?php endif; ?></div></section>

<section class="result-summary" aria-label="Quiz score summary">
    <div class="result-score">
        <p class="eyebrow">Score</p>
        <h2><?= $scoreCorrect ?> / <?= $scoreTotal ?></h2>
    </div>
    <div class="result-percentage">
        <p class="eyebrow">Score percentage</p>
        <h3><?= htmlspecialchars($formatPercentage($scorePercentage), ENT_QUOTES, 'UTF-8') ?></h3>
        <p class="muted-note">Correct answers out of all questions, including unanswered questions.</p>
    </div>
    <div class="result-answer-accuracy">
        <p class="eyebrow">Question answer accuracy</p>
        <h3><?= $answerAccuracy === null ? '—' : htmlspecialchars($formatPercentage($answerAccuracy), ENT_QUOTES, 'UTF-8') ?></h3>
        <p class="muted-note"><?= $scoreAnswered > 0 ? $scoreAnswered . ' answered question' . ($scoreAnswered === 1 ? '' : 's') . '; unanswered items are excluded.' : 'No answers were submitted.' ?></p>
    </div>
</section>

<dl class="result-outcome-breakdown" aria-label="Question outcomes">
    <div><dt>Correct</dt><dd><?= $scoreCorrect ?></dd></div>
    <div><dt>Incorrect</dt><dd><?= $scoreIncorrect ?></dd></div>
    <div><dt>Unanswered</dt><dd><?= $scoreUnanswered ?></dd></div>
</dl>

<?php if ($notesPracticeReview !== null): ?>
<section class="notes-practice-result card" aria-labelledby="notes-practice-result-title">
    <p class="eyebrow">Practice from your notes</p>
    <h2 id="notes-practice-result-title"><?= htmlspecialchars((string) ($notesPracticeReview['note_title'] ?? 'My notes'), ENT_QUOTES, 'UTF-8') ?></h2>
    <p>Your personal notes helped identify relevant existing questions. They are learner-written material, not independently validated sources, and do not verify an answer.</p>
    <p><strong>Result:</strong> <?= (int) ($notesPracticeReview['correct'] ?? 0) ?> correct · <?= (int) ($notesPracticeReview['incorrect'] ?? 0) ?> incorrect</p>
    <?php foreach ([
        'Topics practiced' => $notesPracticeReview['topics_practiced'] ?? [],
        'Topics to revisit' => $notesPracticeReview['topics_to_revisit'] ?? [],
        'Topics with limited question coverage' => $notesPracticeReview['limited_topics'] ?? [],
        'Topics not yet covered' => $notesPracticeReview['topics_not_yet_covered'] ?? [],
    ] as $label => $topics): ?>
        <?php if (is_array($topics) && $topics !== []): ?><p><strong><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>:</strong> <?= htmlspecialchars(implode(', ', array_map('strval', $topics)), ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
    <?php endforeach; ?>
    <?php if (($notesPracticeReview['saved_note_available'] ?? false) === true && preg_match('/\Anote-[a-f0-9]{32}\z/', (string) ($notesPracticeReview['note_id'] ?? '')) === 1): ?><p><a href="/my-notes?note=<?= rawurlencode((string) $notesPracticeReview['note_id']) ?>">Return to this note</a></p><?php elseif (!empty($notesPracticeReview['note_id'])): ?><p class="muted-note">This note is no longer saved. Its bounded snapshot remains in this result and your mistake review.</p><?php endif; ?>
</section>
<?php endif; ?>

<?php if ($mistakeRecovery !== null): ?>
<section class="mistake-recovery-result card" aria-labelledby="mistake-recovery-result-title">
    <p class="eyebrow">Weakness recovery</p>
    <h2 id="mistake-recovery-result-title"><?= ($mistakeRecovery['mode'] ?? '') === 'RELATED_QUESTIONS' ? 'Related-topic practice complete' : 'Mistake retry complete' ?></h2>
    <p>Your result below uses BoardPrep’s normal quiz scoring. A correct retry is recorded as a new attempt; the original mistake remains in your history.</p>
    <p><strong>Questions retried:</strong> <?= count((array) ($mistakeRecovery['questions'] ?? [])) ?> · <strong>Correct:</strong> <?= (int) ($mistakeRecovery['correct'] ?? 0) ?> · <strong>Incorrect:</strong> <?= (int) ($mistakeRecovery['incorrect'] ?? 0) ?> · <strong>Unanswered:</strong> <?= (int) ($mistakeRecovery['unanswered'] ?? 0) ?></p>
    <?php foreach (['Topics practiced' => $mistakeRecovery['topics_practiced'] ?? [], 'Topics to revisit' => $mistakeRecovery['topics_to_revisit'] ?? []] as $label => $topics): ?><?php if (is_array($topics) && $topics !== []): ?><p><strong><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>:</strong> <?= htmlspecialchars(implode(', ', array_map('strval', $topics)), ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?><?php endforeach; ?>
    <p><strong>Eligible recorded mistakes still available to retry:</strong> <?= (int) ($mistakeRecovery['remaining_eligible_mistakes'] ?? 0) ?></p>
    <p class="muted-note">These labels describe recorded attempts. They do not declare a topic mastered.</p>
    <p><a class="button button-outline" href="/mistake-notebook">Open Mistake Notebook</a></p>
</section>
<?php endif; ?>

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
<?php $filterNames = ['all' => 'All questions', 'incorrect' => 'Incorrect', 'unanswered' => 'Unanswered', 'correct' => 'Correct']; $filterSeparator = str_contains($reviewFilterBaseUrl, '?') ? '&' : '?'; ?>
<nav class="answer-review-filters" aria-label="Filter answer review">
    <?php foreach ($filterNames as $filterKey => $filterName): ?>
        <a class="button button-outline" href="<?= htmlspecialchars($reviewFilterBaseUrl . $filterSeparator . 'review_filter=' . rawurlencode($filterKey), ENT_QUOTES, 'UTF-8') ?>"<?= $reviewFilter === $filterKey ? ' aria-current="page"' : '' ?>><?= htmlspecialchars($filterName, ENT_QUOTES, 'UTF-8') ?> (<?= (int) $reviewFilterCounts[$filterKey] ?>)</a>
    <?php endforeach; ?>
</nav>
<p class="muted-note" role="status">Showing <?= count($review) ?> of <?= $allReviewCount ?> questions. Review marks are not carried into the saved result.</p>

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
        <?php $questionId = is_scalar($item['question']['id'] ?? null) ? (string) $item['question']['id'] : ''; $noteLink = $notesPracticeReview['question_notes'][$questionId] ?? null; ?>
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
        <?php if (is_array($noteLink)): ?>
            <div class="notes-answer-connection">
                <h4>From your note · <?= htmlspecialchars((string) ($noteLink['topic'] ?? ''), ENT_QUOTES, 'UTF-8') ?></h4>
                <blockquote><?= htmlspecialchars((string) ($noteLink['excerpt'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></blockquote>
                <?php if (($noteLink['possible_answer_discrepancy'] ?? false) === true): ?><p class="notes-discrepancy" role="note"><strong>Compare this carefully:</strong> your note excerpt includes a different listed answer choice. This is only a possible discrepancy; the canonical answer and explanation were not changed.</p><?php endif; ?>
            </div>
        <?php endif; ?>
        <?php $studyReferences = $studyReferencesByQuestion[$questionId] ?? []; ?>
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
<?php if ($review === []): ?><p class="empty-state">No questions match this answer filter.</p><?php endif; ?>
</section>
<?php endif; ?>
</div>
<script src="/assets/js/quiz-focus.js" defer></script>
