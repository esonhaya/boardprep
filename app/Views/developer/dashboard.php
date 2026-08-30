<h2>Developer Dashboard</h2>

<p>

Welcome back.

This is the operational center for your BoardPrep repository.

</p>

<hr>

<h3>Repository Health</h3>

<table border="1" cellpadding="8">

<tr>

<th>Health Score</th>
<th>Total Questions</th>
<th>Total Issues</th>
<th>Errors</th>
<th>Warnings</th>
<th>Info</th>

</tr>

<tr>

<td>
<strong><?= $healthScore ?>%</strong>
</td>

<td>
<?= $statistics->totalQuestions ?>
</td>

<td>
<?= $statistics->totalIssues ?>
</td>

<td>
<?= $statistics->errors ?>
</td>

<td>
<?= $statistics->warnings ?>
</td>

<td>
<?= $statistics->infos ?>
</td>

</tr>

</table>

<hr>

<h3>Quick Actions</h3>

<table border="1" cellpadding="8">

<tr>

<td>

<strong>Repository</strong>

<br><br>

<a href="/developer?action=analyze">

Analyze Repository

</a>

<br><br>

<a href="/developer?action=fix-all">

Fix Everything

</a>

</td>

<td>

<strong>Questions</strong>

<br><br>

<a href="/question-editor/create">

Question Workspace

</a>

<br><br>

<a href="/question-editor">

Question Library

</a>

</td>

<td>

<strong>Quality</strong>

<br><br>

<a href="/coverage">

Coverage Matrix

</a>

<br><br>

<a href="/question-quality">

Question Quality

</a>

</td>

<td>

<strong>Management</strong>

<br><br>

<a href="/taxonomy">

Taxonomy

</a>

<br><br>

<a href="/blueprints">

Blueprints

</a>

</td>

</tr>

</table>

<hr>

<h3>Repository Status</h3>

<table border="1" cellpadding="8">

<tr>

<th>Component</th>
<th>Status</th>

</tr>

<tr>

<td>Questions</td>

<td>

<?= $statistics->errors === 0 ? "🟢 Healthy" : "🟡 Review Required" ?>

</td>

</tr>

<tr>

<td>Repository Health</td>

<td>

<?= $healthScore >= 95 ? "🟢 Excellent" : ($healthScore >= 80 ? "🟡 Good" : "🔴 Needs Attention") ?>

</td>

</tr>

<tr>

<td>Taxonomy</td>

<td>🟢 Available</td>

</tr>

<tr>

<td>Coverage</td>

<td>🟢 Available</td>

</tr>

</table>

<hr>

<h3>Recent Repository Issues</h3>

<?php if (empty($recentIssues)): ?>

<p>

✅ No repository issues were detected.

</p>

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

<td>

#<?= htmlspecialchars(
    (string) $issue->entityId
) ?>

</td>

<td>

<?= htmlspecialchars(
    $issue->severity
) ?>

</td>

<td>

<?= htmlspecialchars(
    $issue->code
) ?>

</td>

<td>

<?= htmlspecialchars(
    $issue->message
) ?>

</td>

</tr>

<?php endforeach; ?>

</table>

<?php endif; ?>

<hr>

<h3>Developer Modules</h3>

<table border="1" cellpadding="8" width="100%">

<tr>

<td>

<strong>📝 Question Workspace</strong>

<br><br>

Create and edit questions using the new workspace.

<br><br>

<a href="/question-editor/create">

Open Workspace →

</a>

</td>

</tr>

<tr>

<td>

<strong>📚 Question Library</strong>

<br><br>

Search, inspect and manage your repository.

<br><br>

<a href="/question-editor">

Open Library →

</a>

</td>

</tr>

<tr>

<td>

<strong>🧬 Repository Health</strong>

<br><br>

Analyze repository quality and repair detected issues.

<br><br>

<a href="/developer?action=analyze">

Analyze →

</a>

</td>

</tr>

<tr>

<td>

<strong>🏛 Taxonomy</strong>

<br><br>

Manage boards, subjects, domains, topics and concepts.

<br><br>

<a href="/taxonomy">

Open Taxonomy →

</a>

</td>

</tr>

<tr>

<td>

<strong>📋 Blueprints</strong>

<br><br>

Manage blueprint versions and exam templates.

<br><br>

<a href="/blueprints">

Open Blueprints →

</a>

</td>

</tr>

</table>
