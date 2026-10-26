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
    <?php $qualityEvidenceState = (string) ($qualityEvidenceState ?? 'UNKNOWN'); ?>
    <p><strong>Quality analysis:</strong> <?= htmlspecialchars($qualityEvidenceState, ENT_QUOTES, 'UTF-8') ?>
        <?php if (!empty($qualityEvidenceGeneratedAt)): ?> · generated <?= htmlspecialchars((string) $qualityEvidenceGeneratedAt, ENT_QUOTES, 'UTF-8') ?><?php endif; ?>
        <?php if ($qualityEvidenceState === 'CACHED_CURRENT'): ?> · cached evidence matched the current Git/worktree fingerprint and freshness window<?php elseif ($qualityEvidenceState === 'FRESH_ANALYSIS'): ?> · freshly analyzed repository state<?php endif; ?>
    </p>
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

<?php
$qualityQueue = is_array($qualityQueue ?? null) ? $qualityQueue : [
    'items' => [], 'total' => 0, 'page' => 1, 'pages' => 1, 'per_page' => 25,
    'filters' => [], 'status_counts' => [], 'category_counts' => [], 'issue_type_counts' => [],
    'entity_counts' => [], 'board_counts' => [], 'source_count' => 0,
];
$qualityFilters = (array) ($qualityQueue['filters'] ?? []);
$qualityQuery = array_filter([
    'queue' => (string) ($qualityFilters['queue'] ?? ''),
    'status' => (string) ($qualityFilters['status'] ?? ''),
    'category' => (string) ($qualityFilters['category'] ?? ''),
    'issue_type' => (string) ($qualityFilters['issue_type'] ?? ''),
    'entity_type' => (string) ($qualityFilters['entity_type'] ?? ''),
    'board' => (string) ($qualityFilters['board'] ?? ''),
    'search' => (string) ($qualityFilters['search'] ?? ''),
    'per_page' => (string) ($qualityFilters['per_page'] ?? 25),
], static fn(string $value): bool => $value !== '');
$qualityPageUrl = static fn(int $page): string => '/question-quality?' . http_build_query($qualityQuery + ['page_num' => $page]);
$formatQualityEvidence = static function (mixed $value): string {
    if (is_bool($value)) return $value ? 'yes' : 'no';
    if (is_scalar($value) || $value === null) return (string) ($value ?? 'unknown');
    $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return is_string($encoded) ? $encoded : 'unavailable';
};
?>
<section class="card" aria-labelledby="needs-review-heading">
    <h2 id="needs-review-heading">Needs Review</h2>
    <p>Machine findings and review signals help developers triage work. A warning or review signal does not establish that a question or answer is incorrect. Academic decisions for B695 proposals remain separate.</p>
    <p class="page-actions">
        <a class="button secondary" href="/question-editor">Canonical question inventory</a>
        <a class="button secondary" href="/question-editor?technical_status=clear">Canonical questions with no detected technical issue (<?= (int) ($canonicalNoTechnicalIssueCount ?? 0) ?>)</a>
        <a class="button secondary" href="/question-editor?proposal_batch=695">Pending B695 proposals</a>
        <a class="button secondary" href="/question-quality?queue=needs-review">Needs Review queue</a>
    </p>
    <p><?= (int) ($academicApprovalPendingCount ?? 0) ?> B695 proposals have no current-version academic approval. These flags identify pending evidence work and do not claim that an answer is incorrect.</p>
    <div class="metric-list" aria-label="Quality issue status counts">
        <?php foreach (['FAIL' => 'Deterministic findings', 'WARN' => 'Warnings to triage', 'REVIEW' => 'Human review signals', 'INFO' => 'Informational'] as $status => $label): ?>
            <div class="metric-row"><strong><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></strong><span><?= (int) ($qualityQueue['status_counts'][$status] ?? 0) ?></span></div>
        <?php endforeach; ?>
    </div>
    <form method="get" action="/question-quality" class="filter-bar" aria-label="Filter question quality issues">
        <label for="quality-search">Search question ID, issue code, or evidence text</label>
        <input id="quality-search" name="search" type="search" maxlength="120" value="<?= htmlspecialchars((string) ($qualityFilters['search'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        <label for="quality-status">Machine status</label>
        <select id="quality-status" name="status">
            <option value="">All statuses</option>
            <?php foreach (['FAIL' => 'Deterministic finding', 'WARN' => 'Warning', 'REVIEW' => 'Human review signal', 'INFO' => 'Information'] as $value => $label): ?>
                <option value="<?= $value ?>" <?= ($qualityFilters['status'] ?? '') === $value ? 'selected' : '' ?>><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?> (<?= (int) ($qualityQueue['status_counts'][$value] ?? 0) ?>)</option>
            <?php endforeach; ?>
        </select>
        <label for="quality-category">Issue category</label>
        <select id="quality-category" name="category">
            <option value="">All categories</option>
            <?php foreach ((array) ($qualityQueue['category_counts'] ?? []) as $value => $count): ?>
                <option value="<?= htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') ?>" <?= ($qualityFilters['category'] ?? '') === $value ? 'selected' : '' ?>><?= htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') ?> (<?= (int) $count ?>)</option>
            <?php endforeach; ?>
        </select>
        <label for="quality-issue-type">Review or technical flag</label>
        <select id="quality-issue-type" name="issue_type">
            <option value="">All flags</option>
            <?php foreach ((array) ($qualityQueue['issue_type_counts'] ?? []) as $value => $count): ?>
                <option value="<?= htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') ?>" <?= ($qualityFilters['issue_type'] ?? '') === $value ? 'selected' : '' ?>><?= htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') ?> (<?= (int) $count ?>)</option>
            <?php endforeach; ?>
        </select>
        <label for="quality-entity">Record type</label>
        <select id="quality-entity" name="entity_type">
            <option value="">All record types</option>
            <?php foreach ((array) ($qualityQueue['entity_counts'] ?? []) as $value => $count): ?>
                <option value="<?= htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') ?>" <?= ($qualityFilters['entity_type'] ?? '') === $value ? 'selected' : '' ?>><?= htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') ?> (<?= (int) $count ?>)</option>
            <?php endforeach; ?>
        </select>
        <label for="quality-board">Board ID</label>
        <select id="quality-board" name="board">
            <option value="">All boards</option>
            <?php foreach ((array) ($qualityQueue['board_counts'] ?? []) as $value => $count): ?>
                <option value="<?= htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') ?>" <?= ($qualityFilters['board'] ?? '') === $value ? 'selected' : '' ?>><?= htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') ?> (<?= (int) $count ?>)</option>
            <?php endforeach; ?>
        </select>
        <label for="quality-queue">Queue</label>
        <select id="quality-queue" name="queue">
            <option value="">All signals</option>
            <option value="needs-review" <?= ($qualityFilters['queue'] ?? '') === 'needs-review' ? 'selected' : '' ?>>Needs review (exclude informational)</option>
        </select>
        <label for="quality-page-size">Rows per page</label>
        <select id="quality-page-size" name="per_page">
            <?php foreach ([25, 50, 100] as $size): ?>
                <option value="<?= $size ?>" <?= (int) ($qualityFilters['per_page'] ?? 25) === $size ? 'selected' : '' ?>><?= $size ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit">Filter issues</button>
        <a class="button button-outline" href="/question-quality">Clear filters</a>
    </form>
    <h3>Repository issue records</h3>
    <p>Showing <?= count($qualityQueue['items'] ?? []) ?> of <?= (int) ($qualityQueue['total'] ?? 0) ?> matching records from <?= (int) ($qualityQueue['source_count'] ?? 0) ?> current quality findings and proposal review signals · page <?= (int) ($qualityQueue['page'] ?? 1) ?> of <?= (int) ($qualityQueue['pages'] ?? 1) ?>.</p>
    <?php if (empty($qualityQueue['items'])): ?>
        <div class="empty-state">No issue records match these filters.</div>
    <?php else: ?>
        <div class="record-list">
            <?php foreach ($qualityQueue['items'] as $issue): ?>
                <?php
                $evidence = is_array($issue['evidence'] ?? null) ? $issue['evidence'] : [];
                $affectedIds = array_values(array_unique(array_map('strval', (array) ($evidence['affected_question_ids'] ?? []))));
                $hasAffectedCount = array_key_exists('affected_question_count', $evidence);
                $status = strtoupper((string) ($issue['status'] ?? ''));
                $badgeClass = match ($status) {
                    'FAIL' => 'badge-danger',
                    'WARN', 'REVIEW' => 'badge-warning',
                    default => 'badge-success',
                };
                $entityType = (string) ($issue['entity_type'] ?? 'repository');
                $entityId = (string) ($issue['entity_id'] ?? '');
                ?>
                <article class="record-card">
                    <div>
                        <strong><?= htmlspecialchars($entityType, ENT_QUOTES, 'UTF-8') ?> #<?= htmlspecialchars($entityId, ENT_QUOTES, 'UTF-8') ?></strong>
                        <?php if ($entityType === 'question' && $entityId !== ''): ?><p><a href="/question-inspector?id=<?= rawurlencode($entityId) ?>">Inspect question #<?= htmlspecialchars($entityId, ENT_QUOTES, 'UTF-8') ?></a></p><?php endif; ?>
                        <?php if ($entityType === 'proposal' && $entityId !== ''): ?><p><a href="/question-editor?proposal_batch=695&amp;search=<?= rawurlencode($entityId) ?>">Open proposal #<?= htmlspecialchars($entityId, ENT_QUOTES, 'UTF-8') ?></a></p><?php endif; ?>
                        <?php if (trim((string) ($issue['current_status'] ?? '')) !== ''): ?><small>Current status: <code><?= htmlspecialchars((string) $issue['current_status'], ENT_QUOTES, 'UTF-8') ?></code></small><br><?php endif; ?>
                        <?php if (trim((string) ($issue['question_version_sha256'] ?? '')) !== ''): ?><small>Question version SHA-256: <code><?= htmlspecialchars((string) $issue['question_version_sha256'], ENT_QUOTES, 'UTF-8') ?></code></small><br><?php endif; ?>
                        <p><?= htmlspecialchars((string) ($issue['message'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                        <?php if (isset($evidence['url'])): ?><small>URL: <code><?= htmlspecialchars((string) $evidence['url'], ENT_QUOTES, 'UTF-8') ?></code></small><?php endif; ?>
                        <?php foreach ([
                            'access_status' => 'Access status',
                            'source_text_verified' => 'Full source text verified',
                            'human_review_status' => 'Independent review status',
                            'http_status' => 'HTTP status',
                            'check_status' => 'URL check result',
                            'error' => 'Check detail',
                            'checked_at_utc' => 'URL checked',
                            'board_id' => 'Board',
                            'override_type' => 'Eligibility override',
                            'review_status' => 'Eligibility hold status',
                            'reason' => 'Hold reason',
                            'question_version_sha256' => 'Question version SHA-256',
                            'readiness_version_status' => 'Readiness version status',
                            'source_ids' => 'Cited source IDs',
                            'pending_source_review_ids' => 'Sources awaiting review',
                            'source_snapshot_status' => 'Source snapshot status',
                            'source_snapshot_validation_status' => 'Proposal source-reference validation',
                            'readiness_source_snapshot_validation_status' => 'Readiness source-snapshot validation',
                            'source_registry_snapshots_current' => 'Source registry snapshots current',
                            'currentness_flags' => 'Currentness evidence',
                            'source_scope_flags' => 'Scope evidence',
                            'taxonomy_topic_id' => 'Topic mapping',
                            'taxonomy_topic_registered' => 'Topic registered',
                            'taxonomy_concept_id' => 'Concept mapping',
                            'taxonomy_concept_registered' => 'Concept registered',
                            'study_material_id' => 'Study material',
                            'study_section_id' => 'Study section',
                            'study_status' => 'Study-link status',
                            'study_import_requirement' => 'Study-link requirement',
                            'reviewer_assignment' => 'Reviewer assignment',
                            'academic_approval' => 'Academic approval',
                            'technical_status' => 'Technical status',
                            'technical_validator' => 'Technical validator',
                            'technical_validation_status' => 'Technical validation status',
                            'technical_issue_count' => 'Deterministic technical issue count',
                            'validator_issue_type' => 'Validation issue type',
                            'validator_severity' => 'Validation severity',
                            'duplicate_scope' => 'Duplicate comparison scope',
                            'duplicate_match_method' => 'Duplicate match method',
                            'duplicate_question_ids' => 'Matching question IDs',
                        ] as $field => $label): ?>
                            <?php if (array_key_exists($field, $evidence)): ?>
                                <small><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>: <code><?= htmlspecialchars($formatQualityEvidence($evidence[$field]), ENT_QUOTES, 'UTF-8') ?></code></small><br>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        <?php if ($affectedIds !== [] || $hasAffectedCount): ?>
                            <p><small>Affects <?= (int) ($evidence['affected_question_count'] ?? count($affectedIds)) ?> question(s):
                                <?php if ($affectedIds === []): ?>none currently linked<?php endif; ?>
                                <?php foreach (array_slice($affectedIds, 0, 12) as $index => $questionId): ?>
                                    <?php if ($index > 0): ?>, <?php endif; ?><a href="/question-inspector?id=<?= rawurlencode($questionId) ?>"><code>#<?= htmlspecialchars($questionId, ENT_QUOTES, 'UTF-8') ?></code></a>
                                <?php endforeach; ?>
                                <?php if (count($affectedIds) > 12): ?> and <?= count($affectedIds) - 12 ?> more<?php endif; ?>
                            </small></p>
                        <?php endif; ?>
                        <?php if (trim((string) ($issue['suggested_action'] ?? '')) !== ''): ?><p><strong>Next action:</strong> <?= htmlspecialchars((string) $issue['suggested_action'], ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
                    </div>
                    <div class="record-meta"><span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?></span><code><?= htmlspecialchars((string) ($issue['code'] ?? ''), ENT_QUOTES, 'UTF-8') ?></code><small><?= htmlspecialchars((string) ($issue['issue_type'] ?? ''), ENT_QUOTES, 'UTF-8') ?></small><small><?= htmlspecialchars((string) ($issue['quality_classification'] ?? ''), ENT_QUOTES, 'UTF-8') ?></small></div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <nav class="pagination question-quality-pagination" aria-label="Question quality issue pages">
        <span>Page <?= (int) ($qualityQueue['page'] ?? 1) ?> of <?= (int) ($qualityQueue['pages'] ?? 1) ?></span>
        <div class="page-actions">
            <?php if (($qualityQueue['page'] ?? 1) > 1): ?><a class="button button-outline" rel="prev" href="<?= htmlspecialchars($qualityPageUrl((int) $qualityQueue['page'] - 1), ENT_QUOTES, 'UTF-8') ?>">Previous page</a><?php endif; ?>
            <?php if (($qualityQueue['page'] ?? 1) < ($qualityQueue['pages'] ?? 1)): ?><a class="button button-outline" rel="next" href="<?= htmlspecialchars($qualityPageUrl((int) $qualityQueue['page'] + 1), ENT_QUOTES, 'UTF-8') ?>">Next page</a><?php endif; ?>
        </div>
    </nav>
</section>
