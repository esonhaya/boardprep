<h2>Developer Dashboard</h2>

<p>

Welcome to the BoardPrep Developer CMS.

</p>

<hr>

<h3>Repository Overview</h3>

<table border="1" cellpadding="8">

<tr>
<th>Metric</th>
<th>Value</th>
</tr>

<tr>
<td>Health Score</td>
<td><?= $healthScore ?>%</td>
</tr>

<tr>
<td>Total Questions</td>
<td><?= $statistics->totalQuestions ?></td>
</tr>

<tr>
<td>Total Issues</td>
<td><?= $statistics->totalIssues ?></td>
</tr>

<tr>
<td>Errors</td>
<td><?= $statistics->errors ?></td>
</tr>

<tr>
<td>Warnings</td>
<td><?= $statistics->warnings ?></td>
</tr>

<tr>
<td>Information</td>
<td><?= $statistics->infos ?></td>
</tr>

</table>

<hr>

<h3>Quick Actions</h3>

<ul>

<li>
<a href="/question-quality">
Repository Health Dashboard
</a>
</li>

<li>
<a href="/question-inspector">
Question Inspector
</a>
</li>

<li>
<a href="/metadata-repair">
Metadata Repair
</a>
</li>

<li>
<a href="/coverage">
Coverage Matrix
</a>
</li>

<li>
<a href="/blueprint-health">
Blueprint Health
</a>
</li>

<li>
<a href="/question-editor">
Question Editor
</a>
</li>

</ul>

<hr>

<h3>Recent Repository Issues</h3>

<?php if (empty($recentIssues)): ?>

<p>✅ No issues found.</p>

<?php else: ?>

<table border="1" cellpadding="6">

<tr>

<th>Question</th>

<th>Severity</th>

<th>Code</th>

<th>Message</th>

</tr>

<?php foreach ($recentIssues as $issue): ?>

<tr>

<td>#<?= htmlspecialchars((string)$issue->entityId) ?></td>

<td><?= htmlspecialchars($issue->severity) ?></td>

<td><?= htmlspecialchars($issue->code) ?></td>

<td><?= htmlspecialchars($issue->message) ?></td>

</tr>

<?php endforeach; ?>

</table>

<?php endif; ?>
