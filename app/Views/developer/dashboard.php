<section class="page-header">
    <div><h1>Developer Dashboard</h1><p>Monitor repository health and move quickly through the BoardPrep content workflow.</p></div>
    <div class="page-actions"><a class="button" href="/question-editor/create">Create question</a><a class="button secondary" href="/question-import">Import</a></div>
</section>
<section aria-labelledby="health-heading"><div class="section-heading"><h2 id="health-heading">Repository Health</h2><span class="badge <?= $healthScore >= 80 ? 'badge-success' : 'badge-warning' ?>"><?= (int) $healthScore ?>% health</span></div>
<div class="stats-grid developer-stats">
<article class="card stat-card"><span class="stat-label">Health score</span><strong class="stat-value"><?= $healthScore ?>%</strong></article>
<article class="card stat-card"><span class="stat-label">Total questions</span><strong class="stat-value"><?= $statistics->totalQuestions ?></strong></article>
<article class="card stat-card"><span class="stat-label">Total issues</span><strong class="stat-value"><?= $statistics->totalIssues ?></strong></article>
<article class="card stat-card"><span class="stat-label">Errors</span><strong class="stat-value"><?= $statistics->errors ?></strong><span class="badge badge-danger">Needs review</span></article>
<article class="card stat-card"><span class="stat-label">Warnings</span><strong class="stat-value"><?= $statistics->warnings ?></strong></article>
<article class="card stat-card"><span class="stat-label">Info</span><strong class="stat-value"><?= $statistics->infos ?></strong></article>
</div></section>
<section aria-labelledby="actions-heading"><div class="section-heading"><h2 id="actions-heading">Quick Actions</h2></div>
<div class="action-card-grid">
<article class="card action-group"><h3>Repository</h3><p>Run diagnostics and apply supported repairs.</p><div class="page-actions"><a href="/developer?action=analyze">Analyze Repository</a><a href="/developer?action=fix-all">Fix Everything</a></div></article>
<article class="card action-group"><h3>Questions</h3><p>Author, search, and inspect your content bank.</p><div class="page-actions"><a href="/question-editor/create">Question Workspace</a><a href="/question-editor">Question Library</a></div></article>
<article class="card action-group"><h3>Quality</h3><p>See coverage and content health signals.</p><div class="page-actions"><a href="/coverage">Coverage Matrix</a><a href="/question-quality">Question Quality</a></div></article>
<article class="card action-group"><h3>Management</h3><p>Maintain taxonomy and exam blueprints.</p><div class="page-actions"><a href="/taxonomy">Taxonomy</a><a href="/blueprints">Blueprints</a></div></article>
</div></section>
<section aria-labelledby="status-heading"><div class="section-heading"><h2 id="status-heading">Repository Status</h2></div>
<div class="status-grid">
<article class="card status-item"><strong>Questions</strong><span class="badge <?= $statistics->errors === 0 ? 'badge-success' : 'badge-warning' ?>"><?= $statistics->errors === 0 ? 'Healthy' : 'Review required' ?></span></article>
<article class="card status-item"><strong>Repository Health</strong><span class="badge <?= $healthScore >= 95 ? 'badge-success' : ($healthScore >= 80 ? 'badge-warning' : 'badge-danger') ?>"><?= $healthScore >= 95 ? 'Excellent' : ($healthScore >= 80 ? 'Good' : 'Needs attention') ?></span></article>
<article class="card status-item"><strong>Taxonomy</strong><span class="badge badge-success">Available</span></article>
<article class="card status-item"><strong>Coverage</strong><span class="badge badge-success">Available</span></article>
</div></section>
<section aria-labelledby="issues-heading"><div class="section-heading"><h2 id="issues-heading">Recent Repository Issues</h2></div>
<?php if (empty($recentIssues)): ?><div class="empty-state">No repository issues were detected.</div>
<?php else: ?><div class="record-list"><?php foreach ($recentIssues as $issue): ?><article class="card record-card"><div><strong>#<?= htmlspecialchars((string) $issue->entityId) ?></strong><p><?= htmlspecialchars($issue->message) ?></p></div><div class="record-meta"><span class="badge <?= strtolower($issue->severity) === 'error' ? 'badge-danger' : 'badge-warning' ?>"><?= htmlspecialchars($issue->severity) ?></span><code><?= htmlspecialchars($issue->code) ?></code></div></article><?php endforeach; ?></div><?php endif; ?></section>
<section aria-labelledby="modules-heading"><div class="section-heading"><h2 id="modules-heading">Developer Modules</h2></div>
<div class="action-card-grid">
<article class="card action-group"><h3>Question Workspace</h3><p>Create and edit questions using the authoring workflow.</p><a href="/question-editor/create">Open Workspace →</a></article>
<article class="card action-group"><h3>Question Library</h3><p>Search, inspect, and manage your repository.</p><a href="/question-editor">Open Library →</a></article>
<article class="card action-group"><h3>Repository Health</h3><p>Analyze quality and repair detected issues.</p><a href="/developer?action=analyze">Analyze →</a></article>
<article class="card action-group"><h3>Taxonomy &amp; Blueprints</h3><p>Manage exam structure and content hierarchy.</p><a href="/taxonomy">Open Taxonomy →</a></article>
</div></section>
