<?php
$summary = $summary ?? [];
$subjects = $subjects ?? [];
$topics = $topics ?? [];
$weakestTopics = $weakestTopics ?? [];
$focusTopics = $focusTopics ?? $weakestTopics;
$strongestSubjects = $strongestSubjects ?? [];
$weakestTopics = $focusTopics;
$history = $history ?? [];
$recommendations = $recommendations ?? [];
$trend = $summary['trend']['direction'] ?? 'insufficient_history';
$weaknesses = is_array($weaknesses ?? null) ? $weaknesses : [];
$readiness = is_array(($dashboard ?? [])['readiness'] ?? null) ? $dashboard['readiness'] : [];
$readinessSubjects = is_array($readiness['subjects'] ?? null) ? $readiness['subjects'] : [];
$readinessActions = [];
foreach ((array) ($readiness['recommendations'] ?? []) as $readinessRecommendation) {
    if (is_array($readinessRecommendation) && is_string($readinessRecommendation['subject'] ?? null)) {
        $readinessActions[$readinessRecommendation['subject']] = $readinessRecommendation;
    }
}
$hasCompletedAttempts = (int) ($summary['completedAttempts'] ?? 0) > 0;
$hasAnsweredQuestions = (int) ($summary['answeredCount'] ?? 0) > 0;
$hasRecentAnswerAccuracy = is_numeric($summary['recentAccuracy'] ?? null);
$eligibleSubjectCount = $readiness['subject_coverage']['eligible'] ?? null;
$readinessCoverageLabel = is_numeric($eligibleSubjectCount)
    ? (int) ($readiness['subject_coverage']['practiced'] ?? 0) . ' of ' . (int) $eligibleSubjectCount . ' subjects with eligible questions practiced'
    : (int) ($readiness['subject_coverage']['configured'] ?? 0) . ' configured subjects · choose an examination variant to check available pools';
$answerAccuracy = $hasAnsweredQuestions ? (int) ($summary['accuracy'] ?? 0) . '%' : '—';
$recentAnswerAccuracy = $hasRecentAnswerAccuracy ? (int) $summary['recentAccuracy'] . '%' : '—';
$recentAccuracyDetail = $hasRecentAnswerAccuracy
    ? number_format((int) ($summary['recentAnsweredCount'] ?? 0)) . ' answer events across '
        . (int) ($summary['recentAttemptCount'] ?? 0) . ' recent quizzes'
    : ((int) ($summary['recentAnsweredCount'] ?? 0) === 0
        ? 'No recent answered questions yet'
        : 'Need at least 5 recent answer events; '
            . (int) $summary['recentAnsweredCount'] . ' available');
$averageQuizScore = $hasCompletedAttempts ? (int) ($summary['averageScore'] ?? 0) . '%' : '—';
$bestScore = $hasCompletedAttempts ? (int) ($summary['bestScore'] ?? 0) . '%' : '—';
if (!$hasCompletedAttempts) {
    $trend = 'not_started';
}
$taxonomyLabel = static fn(string $collection, mixed $id): string => \App\Services\Shared\TaxonomyStorageService::label($collection, $id);
$learningContextLabel = static function (array $row) use ($taxonomyLabel): string {
    $labels = [];
    if (($row['board'] ?? '') !== '') {
        $labels[] = $taxonomyLabel('boards', $row['board']);
    }
    if (($row['civil_service_level'] ?? '') !== '') {
        $labels[] = ucfirst(strtolower(str_replace('_', ' ', (string) $row['civil_service_level'])));
    }
    if (($row['let_level'] ?? '') !== '') {
        $labels[] = ucfirst(strtolower((string) $row['let_level']));
    }
    if (($row['let_specialization'] ?? '') !== '') {
        $specialization = \App\Services\Shared\TaxonomyStorageService::find('let-specializations', (string) $row['let_specialization']);
        $labels[] = is_array($specialization)
            ? (string) ($specialization['official_name'] ?? $row['let_specialization'])
            : $taxonomyLabel('let-specializations', $row['let_specialization']);
    }
    return implode(' · ', $labels);
};
?>
<div class="ui-page-report progress-page">
    <section class="page-header">
        <div>
            <p class="eyebrow">Progress report</p>
            <h1>Know what to review next</h1>
            <p>Use your actual quiz history to balance strengths and focus areas.</p>
        </div>
        <a class="button button-green" href="/quiz">Practice now</a>
    </section>

    <section class="readiness-overview card" aria-labelledby="readiness-overview-heading">
        <div class="section-heading">
            <div>
                <p class="eyebrow">Preparation snapshot</p>
                <h2 id="readiness-overview-heading"><?= htmlspecialchars((string) ($readiness['board_label'] ?? 'Board-specific'), ENT_QUOTES, 'UTF-8') ?><?= !empty($readiness['variant_label']) ? ' · ' . htmlspecialchars((string) $readiness['variant_label'], ENT_QUOTES, 'UTF-8') : '' ?></h2>
            </div>
            <span class="badge <?= ($readiness['status'] ?? '') === 'PRACTICE_DATA_AVAILABLE' ? 'badge-success' : 'badge-warning' ?>"><?= htmlspecialchars(ucwords(strtolower(str_replace('_', ' ', (string) ($readiness['status'] ?? 'INSUFFICIENT_PRACTICE_DATA')))), ENT_QUOTES, 'UTF-8') ?></span>
        </div>
        <p class="muted-note">This is a summary of BoardPrep practice coverage and observed answers. It is not an official examination pass predictor and does not measure mastery.</p>
        <?php if (!empty($readiness['blueprint_context']['label'])): ?><p class="readiness-blueprint-note"><strong>Simulation context:</strong> <?= htmlspecialchars((string) $readiness['blueprint_context']['label'], ENT_QUOTES, 'UTF-8') ?>. <?= htmlspecialchars((string) ($readiness['blueprint_context']['note'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
        <?php if (!empty($readiness['blueprint_context']['content_depth_caveat'])): ?><p class="readiness-caveat" role="note"><?= htmlspecialchars((string) $readiness['blueprint_context']['content_depth_caveat'], ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
        <div class="readiness-summary-row">
            <strong><?= htmlspecialchars($readinessCoverageLabel, ENT_QUOTES, 'UTF-8') ?></strong>
            <span><?= (int) ($readiness['completed_attempts'] ?? 0) ?> completed sessions · <?= (int) ($readiness['answered_questions'] ?? 0) ?> answered events</span>
            <?php if (is_numeric($readiness['accuracy'] ?? null)): ?><span>Observed accuracy <?= (int) $readiness['accuracy'] ?>%<?php if (is_numeric($readiness['recent_accuracy'] ?? null)): ?> · recent <?= (int) $readiness['recent_accuracy'] ?>%<?php endif; ?></span><?php endif; ?>
        </div>
        <?php foreach ((array) ($readiness['explanations'] ?? []) as $explanation): ?><p class="readiness-explanation"><?= htmlspecialchars((string) $explanation, ENT_QUOTES, 'UTF-8') ?></p><?php endforeach; ?>
        <?php if ($readinessSubjects !== []): ?>
            <div class="readiness-subjects" aria-label="Configured subject practice coverage">
                <?php foreach ($readinessSubjects as $readinessSubject): ?>
                    <?php $subjectAction = $readinessActions[(string) ($readinessSubject['subject'] ?? '')] ?? null; ?>
                    <article class="readiness-subject" data-readiness-status="<?= htmlspecialchars((string) ($readinessSubject['status'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                        <div><strong><?= htmlspecialchars((string) ($readinessSubject['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong><span><?= htmlspecialchars(ucwords(strtolower(str_replace('_', ' ', (string) ($readinessSubject['status'] ?? 'UNASSESSED')))), ENT_QUOTES, 'UTF-8') ?></span></div>
                        <?php if (($readinessSubject['status'] ?? '') === 'NO_ELIGIBLE_PRACTICE_POOL'): ?><small>No currently eligible practice questions; this is a pool availability gap, not a learner weakness.</small>
                        <?php elseif (($readinessSubject['status'] ?? '') === 'VARIANT_REQUIRED'): ?><small>Choose the candidate variant to check this subject's eligible practice pool.</small>
                        <?php elseif (($readinessSubject['status'] ?? '') === 'OBSERVED_PERFORMANCE' && is_numeric($readinessSubject['accuracy'] ?? null)): ?><small><?= (int) $readinessSubject['accuracy'] ?>% observed answer accuracy from <?= (int) ($readinessSubject['answered_questions'] ?? 0) ?> answer events<?php if (is_numeric($readinessSubject['recent_accuracy'] ?? null)): ?> · recent <?= (int) $readinessSubject['recent_accuracy'] ?>%<?php endif; ?></small>
                        <?php elseif (($readinessSubject['status'] ?? '') === 'LIMITED_EVIDENCE'): ?><small><?= (int) ($readinessSubject['completed_attempts'] ?? 0) ?> session · <?= (int) ($readinessSubject['answered_questions'] ?? 0) ?> answered events; more practice is needed before a stable comparison.</small>
                        <?php else: ?><small>No subject-attributed answers recorded in this board and variant<?php if (is_int($readinessSubject['eligible_question_count'] ?? null)): ?> · <?= (int) $readinessSubject['eligible_question_count'] ?> eligible questions available<?php endif; ?>.</small><?php endif; ?>
                        <?php if (is_array($subjectAction) && !empty($subjectAction['action'])): ?><a href="<?= htmlspecialchars((string) $subjectAction['action'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) ($subjectAction['label'] ?? 'Practice this subject'), ENT_QUOTES, 'UTF-8') ?></a><?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <?php if (!empty($readiness['persistent_mistakes'])): ?><p class="readiness-mistakes"><strong>Repeated mistakes:</strong> <?php foreach ($readiness['persistent_mistakes'] as $index => $mistake): ?><?= $index > 0 ? ', ' : '' ?><?= htmlspecialchars($taxonomyLabel('taxonomy/topics', $mistake['topic'] ?? ''), ENT_QUOTES, 'UTF-8') ?> (<?= (int) ($mistake['repeated_mistakes'] ?? 0) ?>)<?php endforeach; ?> · <a href="/mistake-notebook">Review recorded mistakes</a></p><?php endif; ?>
    </section>

    <section class="ui-metric-grid progress-summary" data-ui-component="metric-grid" aria-label="Progress summary">
        <article class="card stat-card"><span class="stat-label">Completed quizzes</span><strong class="stat-value"><?= (int)($summary['completedAttempts']??0) ?></strong></article>
        <article class="card stat-card"><span class="stat-label">Answer accuracy</span><strong class="stat-value"><?= htmlspecialchars($answerAccuracy) ?></strong><small class="muted-note"><?= number_format((int) ($summary['answeredCount'] ?? 0)) ?> answered question events</small><?php if (!$hasAnsweredQuestions): ?><small class="muted-note">No answered questions yet</small><?php endif; ?></article>
        <article class="card stat-card"><span class="stat-label">Recent answer accuracy</span><strong class="stat-value"><?= htmlspecialchars($recentAnswerAccuracy) ?></strong><small class="muted-note"><?= htmlspecialchars($recentAccuracyDetail, ENT_QUOTES, 'UTF-8') ?></small></article>
        <article class="card stat-card"><span class="stat-label">Average quiz score</span><strong class="stat-value"><?= htmlspecialchars($averageQuizScore) ?></strong><?php if (!$hasCompletedAttempts): ?><small class="muted-note">No completed quizzes yet</small><?php endif; ?></article>
        <article class="card stat-card"><span class="stat-label">Best score</span><strong class="stat-value"><?= htmlspecialchars($bestScore) ?></strong><?php if (!$hasCompletedAttempts): ?><small class="muted-note">No completed quizzes yet</small><?php endif; ?></article>
        <article class="card stat-card"><span class="stat-label">Recent trend</span><strong class="stat-value stat-value-small"><?= htmlspecialchars(ucwords(str_replace('_',' ',$trend))) ?></strong><small class="muted-note">Compares the latest two completed quiz scores.</small></article>
    </section>

    <section class="progress-answer-totals" aria-labelledby="progress-answer-totals-heading">
        <div class="section-heading"><h2 id="progress-answer-totals-heading">Question answer totals</h2></div>
        <p class="muted-note">Answer accuracy uses correct answers divided by answered question events. Skipped questions are separate, and repeated questions count each time they were answered.</p>
        <div class="ui-metric-grid" aria-label="Answer event totals">
            <article class="card stat-card"><span class="stat-label">Questions answered</span><strong class="stat-value"><?= (int) ($summary['answeredCount'] ?? 0) ?></strong></article>
            <article class="card stat-card"><span class="stat-label">Correct answers</span><strong class="stat-value"><?= (int) ($summary['correctCount'] ?? 0) ?></strong></article>
            <article class="card stat-card"><span class="stat-label">Incorrect answers</span><strong class="stat-value"><?= (int) ($summary['incorrectCount'] ?? 0) ?></strong></article>
            <article class="card stat-card"><span class="stat-label">Unanswered questions</span><strong class="stat-value"><?= (int) ($summary['unansweredCount'] ?? 0) ?></strong></article>
        </div>
    </section>

    <section class="progress-next-steps" aria-labelledby="progress-next-steps-heading">
        <div class="section-heading">
            <div>
                <p class="eyebrow">Next step</p>
                <h2 id="progress-next-steps-heading">Choose what to review</h2>
            </div>
        </div>
        <p class="progress-flashcard-action"><a class="button button-outline" href="/mistake-notebook">Review mistakes</a> <a class="button button-outline" href="/flashcards">Review flashcards</a><?php if ($weaknesses !== []): ?> <a class="button button-outline" href="/flashcards/review?mode=weakness">Review weak topics</a><?php endif; ?></p>
        <?php if ($history === []): ?><p class="muted-note">No completed quizzes yet. <a href="/quiz">Practice now</a> to start building your progress.</p><?php endif; ?>
    </section>

    <?php if (!empty($recommendations)): ?><section class="progress-recommendations" aria-labelledby="progress-recommendations-heading"><div class="section-heading"><div><p class="eyebrow">Guidance</p><h2 id="progress-recommendations-heading">Recommended next steps</h2></div></div><?php foreach ($recommendations as $recommendation): ?><article><h3><?= htmlspecialchars((string) ($recommendation['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></h3><p><?= htmlspecialchars((string) ($recommendation['reason'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p><?php if (!empty($recommendation['action'])): ?><a class="button button-outline" href="<?= htmlspecialchars((string) $recommendation['action'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) ($recommendation['label'] ?? 'Start practice'), ENT_QUOTES, 'UTF-8') ?></a><?php endif; ?></article><?php endforeach; ?></section><?php endif; ?>

    <div class="report-grid">
        <section><div class="section-heading"><h2>Topics below context average</h2></div><div class="ui-insight-list card"><?php if(!$weakestTopics): ?><p class="muted-note">No topic is below its average for the same board and exam variant yet. Complete more quizzes to build a useful comparison.</p><?php else: foreach($weakestTopics as $row): ?><div class="ui-performance-row"><div><strong><?= htmlspecialchars($taxonomyLabel('taxonomy/topics', $row['topic']??'')) ?></strong><small><?= htmlspecialchars($taxonomyLabel('subjects', $row['subject']??'')) ?><?php $contextLabel = $learningContextLabel($row); ?><?= $contextLabel !== '' ? ' · ' . htmlspecialchars($contextLabel) : '' ?> · <?= (int)($row['attempts']??0) ?> attempts</small></div><span><?= (int)($row['average']??0) ?>%</span></div><?php endforeach; endif; ?></div></section>
        <section><div class="section-heading"><h2>Subjects above context average</h2></div><div class="ui-insight-list card"><?php if(!$strongestSubjects): ?><p class="muted-note">Practice more than one subject in the same board and exam variant to compare strengths.</p><?php else: foreach($strongestSubjects as $row): ?><div class="ui-performance-row"><div><strong><?= htmlspecialchars($taxonomyLabel('subjects', $row['subject']??'')) ?></strong><small><?php $contextLabel = $learningContextLabel($row); ?><?= $contextLabel !== '' ? htmlspecialchars($contextLabel) . ' · ' : '' ?><?= (int)($row['attempts']??0) ?> attempts</small></div><span><?= (int)($row['average']??0) ?>%</span></div><?php endforeach; endif; ?></div></section>
    </div>

    <section><div class="section-heading"><h2>Subject performance</h2></div><p class="muted-note">Quiz score uses all questions in each attempt. Observed answer accuracy uses correct answers divided by answered events; repeated questions count each time.</p><div class="performance-table card"><?php if(!$subjects): ?><p class="muted-note">No subject performance available yet.</p><?php else: foreach($subjects as $row): ?><div class="ui-performance-row"><div><strong><?= htmlspecialchars($taxonomyLabel('subjects', $row['subject']??'')) ?></strong><small><?php $contextLabel = $learningContextLabel($row); ?><?= $contextLabel !== '' ? htmlspecialchars($contextLabel) . ' · ' : '' ?><?= (int) ($row['answered_questions'] ?? 0) ?> answered events · <?= htmlspecialchars(str_replace('_', ' ', strtolower((string) ($row['recent_accuracy_status'] ?? 'NO_ANSWERED_QUESTIONS'))), ENT_QUOTES, 'UTF-8') ?></small><div class="progress-bar"><span style="width:<?= min(100,max(0,(int)$row['average'])) ?>%"></span></div></div><span><?= (int)$row['average'] ?>% quiz score · <?= (int)$row['attempts'] ?> sessions<?php if (is_numeric($row['accuracy'] ?? null)): ?><small class="muted-note"> <?= (int) $row['accuracy'] ?>% answer accuracy</small><?php endif; ?></span></div><?php endforeach; endif; ?></div></section>
    <section><div class="section-heading"><h2>Topic performance</h2></div><div class="ui-insight-list card"><?php if(!$topics): ?><p class="muted-note">No topic performance available yet.</p><?php else: foreach($topics as $row): ?><div class="ui-performance-row"><div><strong><?= htmlspecialchars($taxonomyLabel('taxonomy/topics', $row['topic']??'')) ?></strong><small><?= htmlspecialchars($taxonomyLabel('subjects', $row['subject']??'')) ?><?php $contextLabel = $learningContextLabel($row); ?><?= $contextLabel !== '' ? ' · ' . htmlspecialchars($contextLabel) : '' ?></small></div><span><?= (int)($row['average']??0) ?>%</span></div><?php endforeach; endif; ?></div></section>
    <section class="progress-history ui-preview-list" data-ui-collection="preview" data-ui-limit="5"><div class="section-heading"><h2>Recent activity</h2><a class="ui-view-all" data-ui-view-all href="/history">View all history</a></div><?php foreach($history as $attempt): ?><?php require dirname(__DIR__).'/history/attempt.php'; ?><?php endforeach; ?></section>
    </div>
</div>
