<section class="page-header"><div><h1>Metadata Repair</h1><p>Review questions with repairable metadata issues.</p></div></section>
<div class="card"><strong><?= count($repairableIssues ?? []) ?></strong> repairable issues</div>
<?php if (empty($repairableIssues)): ?><div class="empty-state">No repairable metadata issues found.</div>
<?php else: ?><div class="record-list"><?php foreach ($repairableIssues as $issue): ?><article class="card record-card"><div><strong>#<?= htmlspecialchars((string) ($issue->entityId ?? '')) ?></strong><p><?= htmlspecialchars((string) $issue->message) ?></p></div><div class="record-meta"><span class="badge badge-warning"><?= htmlspecialchars((string) $issue->severity) ?></span><code><?= htmlspecialchars((string) $issue->code) ?></code></div></article><?php endforeach; ?></div><?php endif; ?>
<p class="muted-note">Total repository issues: <?= count($report->issues ?? []) ?></p>
