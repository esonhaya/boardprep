<section class="page-header">
    <div><h1>Repository Health Dashboard</h1><p>Question Quality · Review actionable quality signals across the question repository.</p></div>
    <span class="health-score"><?= (int) $healthScore ?>%</span>
</section>

<div class="stats-grid">
    <article class="card stat-card"><span class="stat-label">Health score</span><strong class="stat-value"><?= (int) $healthScore ?>%</strong></article>
    <article class="card stat-card"><span class="stat-label">Errors</span><strong class="stat-value"><?= (int) ($severitySummary['error'] ?? 0) ?></strong></article>
    <article class="card stat-card"><span class="stat-label">Warnings</span><strong class="stat-value"><?= (int) ($severitySummary['warning'] ?? 0) ?></strong></article>
    <article class="card stat-card"><span class="stat-label">Review required</span><strong class="stat-value"><?= (int) ($severitySummary['review'] ?? 0) ?></strong></article>
    <article class="card stat-card"><span class="stat-label">Info</span><strong class="stat-value"><?= (int) ($severitySummary['info'] ?? 0) ?></strong></article>
</div>

<section class="card">
    <h2>Priority Issue Summary</h2>
    <div class="record-list">
        <?php foreach ($issueGroupLabels as $key => $label): ?>
            <div class="metric-row"><strong><?= htmlspecialchars($label) ?></strong><span class="badge badge-warning"><?= count($issueGroups[$key] ?? []) ?></span></div>
        <?php endforeach; ?>
        <div class="metric-row"><strong>Unclassified issues</strong><span class="badge badge-warning"><?= count($issueGroups['unclassifiedIssues'] ?? []) ?></span></div>
    </div>
</section>

<?php
$relationshipSummary = (array) ($report->summary['study_relationships'] ?? []);
$urlAuditSummary = (array) ($report->summary['source_url_audit'] ?? []);
$checkedSources = $urlAuditSummary['checked_source_count'] ?? null;
$totalUrlSources = $urlAuditSummary['total_url_source_count'] ?? null;
$uncheckedSources = $urlAuditSummary['unchecked_source_count'] ?? null;
?>
<section class="card">
    <h2>Study relationship and review status</h2>
    <p>Source references, material links, exact section links, and independent approval are separate states. A missing review status is shown as unknown, never as approval.</p>
    <div class="metric-list">
        <?php foreach ([
            'Canonical questions with source-only references' => 'canonical_questions_with_source_only_references',
            'Source-only study-link rows' => 'source_only_link_rows',
            'Material-level links without an exact section' => 'material_level_link_rows',
            'Exact section links' => 'exact_section_link_rows',
            'Broken or unresolved section anchors' => 'broken_or_unresolved_section_link_rows',
            'Exact links pending review or lacking approval evidence' => 'exact_links_pending_or_approval_evidence_missing',
            'Exact links with unknown or unreviewed status' => 'exact_links_unreviewed_or_status_unknown',
            'Exact links approved with explicit evidence' => 'exact_links_approved_with_explicit_evidence',
            'Explicitly rejected mapping rows' => 'explicitly_rejected_mapping_rows',
        ] as $label => $key): ?>
            <div class="metric-row"><strong><?= htmlspecialchars($label) ?></strong><span><?= (int) ($relationshipSummary[$key] ?? 0) ?></span></div>
        <?php endforeach; ?>
    </div>
</section>

<section class="card">
    <h2>Registered source URL audit</h2>
    <div class="metric-list">
        <div class="metric-row"><strong>Snapshot status</strong><span><?= htmlspecialchars((string) ($urlAuditSummary['status'] ?? 'UNAVAILABLE')) ?></span></div>
        <div class="metric-row"><strong>Checked registered sources</strong><span><?= is_numeric($checkedSources) && is_numeric($totalUrlSources) ? (int) $checkedSources . ' / ' . (int) $totalUrlSources : 'unknown' ?></span></div>
        <div class="metric-row"><strong>Unchecked source URLs</strong><span><?= is_numeric($uncheckedSources) ? (int) $uncheckedSources : 'unknown' ?></span></div>
        <div class="metric-row"><strong>Snapshot generated</strong><span><?= htmlspecialchars((string) ($urlAuditSummary['generated_at_utc'] ?? 'unknown')) ?></span></div>
    </div>
    <p>URL availability is a bounded snapshot. The dashboard makes no network requests; reachable URLs do not establish claim support.</p>
</section>

<section class="card">
    <h2>Repository summary</h2>
    <div class="metric-list">
        <?php foreach (['Total Questions' => 'totalQuestions', 'Total Issues' => 'totalIssues', 'Errors' => 'errors', 'Warnings' => 'warnings', 'Information' => 'infos'] as $label => $key): ?>
            <div class="metric-row"><strong><?= htmlspecialchars($label) ?></strong><span><?= (int) $report->statistics->{$key} ?></span></div>
        <?php endforeach; ?>
    </div>
</section>

<?php $distributions = ['Difficulty' => $report->statistics->questionsPerDifficulty, 'Status' => $report->statistics->questionsPerStatus, 'Board' => $report->statistics->questionsPerBoard, 'Subject' => $report->statistics->questionsPerSubject, 'Domain' => $report->statistics->questionsPerDomain, 'Topic' => $report->statistics->questionsPerTopic, 'Concept' => $report->statistics->questionsPerConcept]; ?>
<section><h2>Coverage and distributions</h2><div class="card-grid">
    <?php foreach ($distributions as $title => $items): ?>
        <article class="card"><h3><?= htmlspecialchars($title) ?></h3>
            <?php if (empty($items)): ?><p class="muted-note">No data</p>
            <?php else: ?><div class="metric-list"><?php foreach ($items as $name => $count): ?><div class="metric-row"><span><?= htmlspecialchars((string) $name) ?></span><strong><?= (int) $count ?></strong></div><?php endforeach; ?></div><?php endif; ?>
        </article>
    <?php endforeach; ?>
</div></section>

<section class="card">
    <h2>Repository issues</h2>
    <?php if (empty($report->issues)): ?>
        <div class="empty-state">No issues found.</div>
    <?php else: ?>
        <div class="record-list">
            <?php foreach ($report->issues as $issue): ?>
                <?php
                $evidence = is_array($issue->evidence ?? null) ? $issue->evidence : [];
                $affectedIds = array_values(array_unique(array_map('strval', (array) ($evidence['affected_question_ids'] ?? []))));
                $badgeClass = match (strtoupper((string) $issue->status)) {
                    'FAIL' => 'badge-danger',
                    'WARN' => 'badge-warning',
                    'REVIEW' => 'badge-warning',
                    default => 'badge-success',
                };
                ?>
                <article class="record-card">
                    <div>
                        <strong><?= htmlspecialchars((string) ($issue->entityType ?? 'repository')) ?> #<?= htmlspecialchars((string) ($issue->entityId ?? '')) ?></strong>
                        <p><?= htmlspecialchars($issue->message) ?></p>
                        <?php if (isset($evidence['url'])): ?><small>URL: <code><?= htmlspecialchars((string) $evidence['url']) ?></code></small><?php endif; ?>
                        <?php foreach ([
                            'access_status' => 'Access status',
                            'source_text_verified' => 'Full source text verified',
                            'human_review_status' => 'Independent review status',
                            'http_status' => 'HTTP status',
                            'checked_at_utc' => 'URL checked',
                        ] as $field => $label): ?>
                            <?php if (array_key_exists($field, $evidence)): ?>
                                <small><?= htmlspecialchars($label) ?>: <code><?= htmlspecialchars(is_bool($evidence[$field]) ? ($evidence[$field] ? 'yes' : 'no') : (string) ($evidence[$field] ?? 'unknown')) ?></code></small><br>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        <?php if ($affectedIds !== []): ?>
                            <p><small>Affects <?= (int) ($evidence['affected_question_count'] ?? count($affectedIds)) ?> question(s):
                                <?php foreach (array_slice($affectedIds, 0, 12) as $index => $questionId): ?>
                                    <?php if ($index > 0): ?>, <?php endif; ?><a href="/question-editor/edit?id=<?= rawurlencode($questionId) ?>"><code>#<?= htmlspecialchars($questionId) ?></code></a>
                                <?php endforeach; ?>
                                <?php if (count($affectedIds) > 12): ?> and <?= count($affectedIds) - 12 ?> more<?php endif; ?>
                            </small></p>
                        <?php endif; ?>
                    </div>
                    <div class="record-meta"><span class="badge <?= $badgeClass ?>"><?= htmlspecialchars((string) $issue->status) ?></span><code><?= htmlspecialchars($issue->code) ?></code></div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
