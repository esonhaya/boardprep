<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>BoardPrep Doctor</title>
<link rel="stylesheet" href="/assets/css/style.css">
</head>
<body class="ui-theme">

<h1>BoardPrep Doctor</h1>

<div class="actions page-actions">
<?php $csrfToken = \App\Services\Shared\CsrfTokenService::token(); ?>
<form method="post" action="/developer/doctor/run"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>"><button class="button" type="submit">Run Doctor</button></form>
</div>

<div class="stats-grid">

<div class="card">
<div>Health</div>
<div class="value"><?= $report['health'] ?? '--' ?>%</div>
</div>

</div>

<?php if (is_array($repositoryHealth ?? null) && $repositoryHealth !== []): ?>
<section class="card" aria-labelledby="repository-health-heading">
<div class="section-heading"><div><p class="eyebrow">Canonical developer health snapshot</p><h2 id="repository-health-heading">Repository Health Center</h2></div><span class="badge <?= htmlspecialchars(strtolower((string)($repositoryHealth['overall_status'] ?? 'unknown'))) ?>"><?= htmlspecialchars((string)($repositoryHealth['overall_status'] ?? 'UNKNOWN')) ?></span></div>
<p class="muted">Last run: <?= htmlspecialchars((string)($repositoryHealth['generated_at'] ?? 'unknown')) ?> · Refresh with <code>php tools/doctor.php --health --persist</code>. This page reads the persisted result and never mutates repository data.</p>
<?php $healthSummary = is_array($repositoryHealth['summary'] ?? null) ? $repositoryHealth['summary'] : []; $healthStatistics = is_array($repositoryHealth['statistics'] ?? null) ? $repositoryHealth['statistics'] : []; ?>
<div class="stats-grid"><div class="card"><div>PASS</div><div class="value"><?= (int)($healthSummary['pass_count'] ?? 0) ?></div></div><div class="card"><div>WARN</div><div class="value"><?= (int)($healthSummary['warn_count'] ?? 0) ?></div></div><div class="card"><div>FAIL</div><div class="value"><?= (int)($healthSummary['fail_count'] ?? 0) ?></div></div><div class="card"><div>REVIEW</div><div class="value"><?= (int)($healthSummary['review_count'] ?? 0) ?></div></div></div>
<?php $totals = is_array($healthStatistics['totals'] ?? null) ? $healthStatistics['totals'] : []; ?>
<p><strong>Repository totals:</strong> canonical <?= (int)($totals['canonical'] ?? 0) ?> · effective <?= (int)($totals['effective_declared'] ?? 0) ?> · runtime <?= (int)($totals['runtime'] ?? 0) ?></p>
</section>

<section class="card" aria-labelledby="health-checks-heading"><div class="section-heading"><h2 id="health-checks-heading">Checks</h2><a href="/api/doctor.php?health=1">Machine-readable JSON</a></div><div class="table-scroll ui-dense-table-wrap"><table class="data-table"><thead><tr><th>Check</th><th>Owner</th><th>Status</th><th>Issues</th></tr></thead><tbody><?php foreach ((array)($repositoryHealth['checks'] ?? []) as $check): ?><tr><th scope="row"><code><?= htmlspecialchars((string)($check['check_code'] ?? '')) ?></code><br><small><?= htmlspecialchars((string)($check['description'] ?? '')) ?></small></th><td><?= htmlspecialchars((string)($check['owner'] ?? '')) ?></td><td><?= htmlspecialchars((string)($check['status'] ?? '')) ?></td><td><?= (int)($check['issue_count'] ?? 0) ?></td></tr><?php endforeach; ?></tbody></table></div></section>

<section class="card" aria-labelledby="health-boards-heading"><div class="section-heading"><h2 id="health-boards-heading">Board Health</h2></div><div class="table-scroll ui-dense-table-wrap"><table class="data-table"><thead><tr><th>Board</th><th>Canonical / Effective / Runtime</th><th>Active leaves</th><th>Thin leaves</th><th>Cards</th></tr></thead><tbody><?php foreach ((array)($repositoryHealth['board_summaries'] ?? []) as $board): ?><tr><th scope="row"><code><?= htmlspecialchars((string)($board['board_id'] ?? '')) ?></code></th><td><?= (int)($board['canonical'] ?? 0) ?> / <?= (int)($board['effective_declared'] ?? 0) ?> / <?= (int)($board['runtime'] ?? 0) ?></td><td><?= (int)($board['active_leaves'] ?? 0) ?> (zero <?= (int)($board['zero'] ?? 0) ?>)</td><td>&lt;5 <?= (int)($board['under5'] ?? 0) ?> · &lt;10 <?= (int)($board['under10'] ?? 0) ?> · &lt;20 <?= (int)($board['under20'] ?? 0) ?></td><td><?= (int)($board['flashcards']['derivable'] ?? 0) ?> / <?= (int)($board['flashcards']['eligible'] ?? 0) ?></td></tr><?php endforeach; ?></tbody></table></div></section>

<?php $criminologistHealth = is_array($repositoryHealth['board_summaries']['criminologist'] ?? null) ? $repositoryHealth['board_summaries']['criminologist'] : []; $readiness = is_array($criminologistHealth['source_readiness'] ?? null) ? $criminologistHealth['source_readiness'] : []; $readinessCounts = is_array($readiness['readiness_counts'] ?? null) ? $readiness['readiness_counts'] : []; ?>
<section class="card" aria-labelledby="source-readiness-heading"><div class="section-heading"><div><p class="eyebrow">Criminologist expansion gate</p><h2 id="source-readiness-heading">Source readiness</h2></div><span class="badge <?= (($readiness['valid'] ?? false) === true) ? 'pass' : 'fail' ?>"><?= (($readiness['valid'] ?? false) === true) ? 'VALID' : 'INVALID' ?></span></div><div class="stats-grid"><div class="card"><div>READY</div><div class="value"><?= (int)($readinessCounts['READY'] ?? 0) ?></div></div><div class="card"><div>READY WITH LIMITS</div><div class="value"><?= (int)($readinessCounts['READY_WITH_LIMITS'] ?? 0) ?></div></div><div class="card"><div>REVIEW</div><div class="value"><?= (int)($readinessCounts['REVIEW'] ?? 0) ?></div></div><div class="card"><div>DEFER</div><div class="value"><?= (int)($readinessCounts['DEFER'] ?? 0) ?></div></div></div><p class="muted">Each active leaf has one permanent readiness record. Numerical deficits are planning signals; readiness and supported scope control automatic authoring.</p><div class="table-scroll ui-dense-table-wrap"><table class="data-table"><thead><tr><th>Leaf</th><th>Current / deficits</th><th>Source</th><th>Study</th><th>Depth</th><th>Headroom / plan</th><th>Readiness / reason</th></tr></thead><tbody><?php foreach ((array)($readiness['records'] ?? []) as $readinessRecord): ?><tr><th scope="row"><code><?= htmlspecialchars((string)($readinessRecord['leaf_id'] ?? '')) ?></code><br><small><?= htmlspecialchars((string)($readinessRecord['leaf_name'] ?? '')) ?></small></th><td><?= (int)($readinessRecord['current_question_count'] ?? 0) ?><br><small>&lt;5 <?= (int)($readinessRecord['deficit_to_5'] ?? 0) ?> · &lt;10 <?= (int)($readinessRecord['deficit_to_10'] ?? 0) ?> · &lt;20 <?= (int)($readinessRecord['deficit_to_20'] ?? 0) ?></small></td><td><?= htmlspecialchars((string)($readinessRecord['source_authority'] ?? '')) ?><br><small><?= htmlspecialchars((string)($readinessRecord['source_currentness'] ?? '')) ?> / <?= htmlspecialchars((string)($readinessRecord['source_scope_fit'] ?? '')) ?></small></td><td><?= htmlspecialchars((string)($readinessRecord['study_coverage'] ?? '')) ?><br><small><?= htmlspecialchars((string)($readinessRecord['study_source_traceability'] ?? '')) ?></small></td><td>D<?= (int)($readinessRecord['supported_depth_min'] ?? 0) ?>–D<?= (int)($readinessRecord['supported_depth_max'] ?? 0) ?></td><td><?= (int)($readinessRecord['safe_headroom_min'] ?? 0) ?> / <?= (int)($readinessRecord['safe_headroom_preferred'] ?? 0) ?> / <?= (int)($readinessRecord['safe_headroom_max'] ?? 0) ?><br><small>planned <?= (int)($readinessRecord['planned_new_questions'] ?? 0) ?></small></td><td><strong><?= htmlspecialchars((string)($readinessRecord['readiness'] ?? '')) ?></strong><br><small><?= htmlspecialchars((string)($readinessRecord['readiness_reason'] ?? $readinessRecord['review_reason'] ?? $readinessRecord['defer_reason'] ?? '')) ?></small></td></tr><?php endforeach; ?></tbody></table></div></section>

<?php $psychometricianHealth = is_array($repositoryHealth['board_summaries']['psychometrician'] ?? null) ? $repositoryHealth['board_summaries']['psychometrician'] : []; $psychReadiness = is_array($psychometricianHealth['source_readiness'] ?? null) ? $psychometricianHealth['source_readiness'] : []; $psychReadinessCounts = is_array($psychReadiness['readiness_counts'] ?? null) ? $psychReadiness['readiness_counts'] : []; ?>
<section class="card" aria-labelledby="psychometrician-readiness-heading"><div class="section-heading"><div><p class="eyebrow">Psychometrician taxonomy/readiness gate</p><h2 id="psychometrician-readiness-heading">Granular source readiness</h2></div><span class="badge <?= (($psychReadiness['valid'] ?? false) === true) ? 'pass' : 'fail' ?>"><?= (($psychReadiness['valid'] ?? false) === true) ? 'VALID' : 'INVALID' ?></span></div><div class="stats-grid"><div class="card"><div>READY</div><div class="value"><?= (int)($psychReadinessCounts['READY'] ?? 0) ?></div></div><div class="card"><div>READY WITH LIMITS</div><div class="value"><?= (int)($psychReadinessCounts['READY_WITH_LIMITS'] ?? 0) ?></div></div><div class="card"><div>REVIEW</div><div class="value"><?= (int)($psychReadinessCounts['REVIEW'] ?? 0) ?></div></div><div class="card"><div>DEFER</div><div class="value"><?= (int)($psychReadinessCounts['DEFER'] ?? 0) ?></div></div></div><p class="muted">Psychometrician leaves are BoardPrep-derived subcompetencies under the current official areas. The existing broad concepts remain structural parents.</p><div class="table-scroll ui-dense-table-wrap"><table class="data-table"><thead><tr><th>Leaf</th><th>Current / deficits</th><th>Source / study</th><th>Depth</th><th>Headroom</th><th>Readiness / reason</th></tr></thead><tbody><?php foreach ((array)($psychReadiness['records'] ?? []) as $readinessRecord): ?><tr><th scope="row"><code><?= htmlspecialchars((string)($readinessRecord['leaf_id'] ?? '')) ?></code><br><small><?= htmlspecialchars((string)($readinessRecord['leaf_name'] ?? '')) ?></small></th><td><?= (int)($readinessRecord['current_question_count'] ?? 0) ?><br><small>&lt;5 <?= (int)($readinessRecord['deficit_to_5'] ?? 0) ?> · &lt;10 <?= (int)($readinessRecord['deficit_to_10'] ?? 0) ?> · &lt;20 <?= (int)($readinessRecord['deficit_to_20'] ?? 0) ?></small></td><td><?= htmlspecialchars((string)($readinessRecord['source_authority'] ?? '')) ?> / <?= htmlspecialchars((string)($readinessRecord['study_coverage'] ?? '')) ?><br><small><?= htmlspecialchars((string)($readinessRecord['source_currentness'] ?? '')) ?> / <?= htmlspecialchars((string)($readinessRecord['study_source_traceability'] ?? '')) ?></small></td><td>D<?= (int)($readinessRecord['supported_depth_min'] ?? 0) ?>–D<?= (int)($readinessRecord['supported_depth_max'] ?? 0) ?></td><td><?= (int)($readinessRecord['safe_headroom_min'] ?? 0) ?> / <?= (int)($readinessRecord['safe_headroom_preferred'] ?? 0) ?> / <?= (int)($readinessRecord['safe_headroom_max'] ?? 0) ?></td><td><strong><?= htmlspecialchars((string)($readinessRecord['readiness'] ?? '')) ?></strong><br><small><?= htmlspecialchars((string)($readinessRecord['readiness_reason'] ?? $readinessRecord['review_reason'] ?? $readinessRecord['defer_reason'] ?? '')) ?></small></td></tr><?php endforeach; ?></tbody></table></div></section>

<section class="card" aria-labelledby="health-queue-heading"><div class="section-heading"><div><h2 id="health-queue-heading">Action Queue</h2><p class="muted">Showing up to 200 matching issues; the JSON endpoint contains the complete queue.</p></div></div><form method="get" action="/developer/doctor"><div class="form-grid"><label>Severity <input name="severity" value="<?= htmlspecialchars((string)($healthFilters['severity'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" placeholder="FAIL"></label><label>Board <input name="board" value="<?= htmlspecialchars((string)($healthFilters['board'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" placeholder="cpa"></label><label>Category <input name="category" value="<?= htmlspecialchars((string)($healthFilters['category'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" placeholder="CALCULATION"></label><label>Entity type <input name="entity_type" value="<?= htmlspecialchars((string)($healthFilters['entity_type'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" placeholder="question"></label></div><p><button type="submit">Filter queue</button> <a class="button secondary" href="/developer/doctor">Clear</a></p></form><?php if (($healthIssues ?? []) === []): ?><p class="empty-state">No health issues match these filters.</p><?php else: ?><div class="table-scroll ui-dense-table-wrap"><table class="data-table"><thead><tr><th>Severity / code</th><th>Entity</th><th>Evidence</th><th>Suggested action</th></tr></thead><tbody><?php foreach (array_slice($healthIssues, 0, 200) as $issue): ?><tr><td><strong><?= htmlspecialchars((string)($issue['status'] ?? '')) ?></strong><br><code><?= htmlspecialchars((string)($issue['code'] ?? '')) ?></code></td><td><?php $entityId = (string)($issue['entity_id'] ?? ''); ?><?php if (($issue['entity_type'] ?? '') === 'question' && $entityId !== ''): ?><a href="/question-editor/edit?id=<?= urlencode($entityId) ?>"><code><?= htmlspecialchars($entityId) ?></code></a><?php else: ?><code><?= htmlspecialchars($entityId) ?></code><?php endif; ?><br><small><?= htmlspecialchars((string)($issue['board_id'] ?? 'UNSCOPED')) ?></small></td><td><?= htmlspecialchars((string)($issue['message'] ?? '')) ?><br><small class="muted"><?= htmlspecialchars((string)json_encode($issue['evidence'] ?? [], JSON_UNESCAPED_SLASHES)) ?></small></td><td><?= htmlspecialchars((string)($issue['suggested_action'] ?? '')) ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section>
<?php else: ?>
<section class="card" aria-labelledby="repository-health-heading"><h2 id="repository-health-heading">Repository Health Center</h2><p>No persisted Repository Health snapshot is available. Run <code>php tools/doctor.php --health --persist</code> from the repository root.</p></section>
<?php endif; ?>

<div class="stats-grid">
<?php $uiCheck = null; foreach (($report['checks'] ?? []) as $check) { if (($check['title'] ?? '') === 'UI Contract Engine') { $uiCheck = $check; break; } } ?>
<?php if (is_array($uiCheck)): ?>
<section class="card" aria-labelledby="ui-health-heading"><div class="section-heading"><h2 id="ui-health-heading">UI Health</h2><strong class="health-score"><?= (int) ($uiCheck['score'] ?? 0) ?>%</strong></div><p><?= htmlspecialchars((string) ($uiCheck['summary'] ?? '')) ?></p><div class="record-list"><?php foreach (($uiCheck['details'] ?? []) as $detail): ?><div class="metric-row"><span><?= htmlspecialchars((string) $detail) ?></span></div><?php endforeach; ?></div></section>
<?php endif; ?>

<div class="card">
<div>PASS</div>
<div class="value"><?= $report['summary']['pass'] ?? 0 ?></div>
</div>

<div class="card">
<div>WARNING</div>
<div class="value"><?= $report['summary']['warning'] ?? 0 ?></div>
</div>

<div class="card">
<div>FAIL</div>
<div class="value"><?= $report['summary']['fail'] ?? 0 ?></div>
</div>

<div class="card">
<div>Checks</div>
<div class="value"><?= $report['summary']['checks'] ?? 0 ?></div>
</div>

</div>

<div class="table-scroll ui-dense-table-wrap"><table class="data-table" data-ui-dense-table>

<thead>
<tr>
<th>Check</th>
<th>Status</th>
<th>Summary</th>
</tr>
</thead>

<tbody>

<?php foreach (($report['checks'] ?? []) as $check): ?>

<tr>
<td><?= htmlspecialchars($check['title']) ?></td>
<td class="<?= strtolower($check['status']) ?>">
<?= htmlspecialchars($check['status']) ?>
</td>
<td><?= htmlspecialchars($check['summary']) ?></td>
</tr>

<?php endforeach; ?>

</tbody>

</table></div>

</body>
</html>
