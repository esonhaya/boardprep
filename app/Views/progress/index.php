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
$hasCompletedAttempts = (int) ($summary['completedAttempts'] ?? 0) > 0;
$averageAccuracy = $hasCompletedAttempts ? (int) ($summary['averageScore'] ?? 0) . '%' : '—';
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

    <section class="ui-metric-grid progress-summary" data-ui-component="metric-grid" aria-label="Progress summary">
        <article class="card stat-card"><span class="stat-label">Completed quizzes</span><strong class="stat-value"><?= (int)($summary['completedAttempts']??0) ?></strong></article>
        <article class="card stat-card"><span class="stat-label">Average accuracy</span><strong class="stat-value"><?= htmlspecialchars($averageAccuracy) ?></strong><?php if (!$hasCompletedAttempts): ?><small class="muted-note">No completed quizzes yet</small><?php endif; ?></article>
        <article class="card stat-card"><span class="stat-label">Best score</span><strong class="stat-value"><?= htmlspecialchars($bestScore) ?></strong><?php if (!$hasCompletedAttempts): ?><small class="muted-note">No completed quizzes yet</small><?php endif; ?></article>
        <article class="card stat-card"><span class="stat-label">Recent trend</span><strong class="stat-value stat-value-small"><?= htmlspecialchars(ucwords(str_replace('_',' ',$trend))) ?></strong></article>
    </section>

    <section class="progress-next-steps" aria-labelledby="progress-next-steps-heading">
        <div class="section-heading">
            <div>
                <p class="eyebrow">Next step</p>
                <h2 id="progress-next-steps-heading">Choose what to review</h2>
            </div>
        </div>
        <p class="progress-flashcard-action"><a class="button button-outline" href="/flashcards">Review flashcards</a><?php if ($weaknesses !== []): ?> <a class="button button-outline" href="/flashcards/review?mode=weakness">Review weak topics</a><?php endif; ?></p>
        <?php if ($history === []): ?><p class="muted-note">No completed quizzes yet. <a href="/quiz">Practice now</a> to start building your progress.</p><?php endif; ?>
    </section>

    <?php if (!empty($recommendations)): ?><section class="progress-recommendations" aria-labelledby="progress-recommendations-heading"><div class="section-heading"><div><p class="eyebrow">Guidance</p><h2 id="progress-recommendations-heading">Recommended next steps</h2></div></div><?php foreach ($recommendations as $recommendation): ?><article><h3><?= htmlspecialchars((string) ($recommendation['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></h3><p><?= htmlspecialchars((string) ($recommendation['reason'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p><?php if (!empty($recommendation['action'])): ?><a class="button button-outline" href="<?= htmlspecialchars((string) $recommendation['action'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) ($recommendation['label'] ?? 'Start practice'), ENT_QUOTES, 'UTF-8') ?></a><?php endif; ?></article><?php endforeach; ?></section><?php endif; ?>

    <div class="report-grid">
        <section><div class="section-heading"><h2>Topics below context average</h2></div><div class="ui-insight-list card"><?php if(!$weakestTopics): ?><p class="muted-note">No topic is below its average for the same board and exam variant yet. Complete more quizzes to build a useful comparison.</p><?php else: foreach($weakestTopics as $row): ?><div class="ui-performance-row"><div><strong><?= htmlspecialchars($taxonomyLabel('taxonomy/topics', $row['topic']??'')) ?></strong><small><?= htmlspecialchars($taxonomyLabel('subjects', $row['subject']??'')) ?><?php $contextLabel = $learningContextLabel($row); ?><?= $contextLabel !== '' ? ' · ' . htmlspecialchars($contextLabel) : '' ?> · <?= (int)($row['attempts']??0) ?> attempts</small></div><span><?= (int)($row['average']??0) ?>%</span></div><?php endforeach; endif; ?></div></section>
        <section><div class="section-heading"><h2>Subjects above context average</h2></div><div class="ui-insight-list card"><?php if(!$strongestSubjects): ?><p class="muted-note">Practice more than one subject in the same board and exam variant to compare strengths.</p><?php else: foreach($strongestSubjects as $row): ?><div class="ui-performance-row"><div><strong><?= htmlspecialchars($taxonomyLabel('subjects', $row['subject']??'')) ?></strong><small><?php $contextLabel = $learningContextLabel($row); ?><?= $contextLabel !== '' ? htmlspecialchars($contextLabel) . ' · ' : '' ?><?= (int)($row['attempts']??0) ?> attempts</small></div><span><?= (int)($row['average']??0) ?>%</span></div><?php endforeach; endif; ?></div></section>
    </div>

    <section><div class="section-heading"><h2>Subject performance</h2></div><div class="performance-table card"><?php if(!$subjects): ?><p class="muted-note">No subject performance available yet.</p><?php else: foreach($subjects as $row): ?><div class="ui-performance-row"><div><strong><?= htmlspecialchars($taxonomyLabel('subjects', $row['subject']??'')) ?></strong><small><?php $contextLabel = $learningContextLabel($row); ?><?= $contextLabel !== '' ? htmlspecialchars($contextLabel) : '' ?></small><div class="progress-bar"><span style="width:<?= min(100,max(0,(int)$row['average'])) ?>%"></span></div></div><span><?= (int)$row['average'] ?>% · <?= (int)$row['attempts'] ?> attempts</span></div><?php endforeach; endif; ?></div></section>
    <section><div class="section-heading"><h2>Topic performance</h2></div><div class="ui-insight-list card"><?php if(!$topics): ?><p class="muted-note">No topic performance available yet.</p><?php else: foreach($topics as $row): ?><div class="ui-performance-row"><div><strong><?= htmlspecialchars($taxonomyLabel('taxonomy/topics', $row['topic']??'')) ?></strong><small><?= htmlspecialchars($taxonomyLabel('subjects', $row['subject']??'')) ?><?php $contextLabel = $learningContextLabel($row); ?><?= $contextLabel !== '' ? ' · ' . htmlspecialchars($contextLabel) : '' ?></small></div><span><?= (int)($row['average']??0) ?>%</span></div><?php endforeach; endif; ?></div></section>
    <section class="progress-history ui-preview-list" data-ui-collection="preview" data-ui-limit="5"><div class="section-heading"><h2>Recent activity</h2><a class="ui-view-all" data-ui-view-all href="/history">View all history</a></div><?php foreach($history as $attempt): ?><?php require dirname(__DIR__).'/history/attempt.php'; ?><?php endforeach; ?></section>
    </div>
</div>
