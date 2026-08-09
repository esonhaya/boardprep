<h2>Metadata Repair</h2>

<hr>

<p>Questions with repairable metadata issues:
<strong><?= count($repairableIssues ?? []) ?></strong></p>

<?php if (empty($repairableIssues)): ?>
<p>No repairable metadata issues found.</p>
<?php else: ?>
<table border="1" cellpadding="6">
<tr><th>ID</th><th>Severity</th><th>Code</th><th>Message</th></tr>
<?php foreach ($repairableIssues as $issue): ?>
<tr>
<td><?= htmlspecialchars((string) ($issue->entityId ?? '')) ?></td>
<td><?= htmlspecialchars((string) $issue->severity) ?></td>
<td><?= htmlspecialchars((string) $issue->code) ?></td>
<td><?= htmlspecialchars((string) $issue->message) ?></td>
</tr>
<?php endforeach; ?>
</table>
<?php endif; ?>

<hr>
<p>Total repository issues: <?= count($report->issues ?? []) ?></p>
