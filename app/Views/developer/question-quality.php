<h2>Repository Health Dashboard</h2>

<hr>

<h3>Overall Repository Health</h3>

<h1><?= $healthScore ?>%</h1>

<p>
<?php

if ($healthScore >= 95) {
    echo "🟢 Excellent";
}
elseif ($healthScore >= 85) {
    echo "🟡 Good";
}
elseif ($healthScore >= 70) {
    echo "🟠 Needs Attention";
}
else {
    echo "🔴 Poor";
}

?>
</p>

<hr>

<h3>Repository Summary</h3>

<table border="1" cellpadding="8">
<tr>
    <th>Metric</th>
    <th>Value</th>
</tr>

<tr>
    <td>Total Questions</td>
    <td><?= $report->statistics->totalQuestions ?></td>
</tr>

<tr>
    <td>Total Issues</td>
    <td><?= $report->statistics->totalIssues ?></td>
</tr>

<tr>
    <td>Errors</td>
    <td><?= $report->statistics->errors ?></td>
</tr>

<tr>
    <td>Warnings</td>
    <td><?= $report->statistics->warnings ?></td>
</tr>

<tr>
    <td>Information</td>
    <td><?= $report->statistics->infos ?></td>
</tr>

</table>

<hr>

<?php

function renderDistribution(string $title, array $items): void
{
    echo "<h3>{$title}</h3>";

    echo "<table border='1' cellpadding='6'>";

    echo "<tr>";
    echo "<th>Name</th>";
    echo "<th>Count</th>";
    echo "</tr>";

    if (empty($items)) {

        echo "<tr>";
        echo "<td colspan='2'>No data</td>";
        echo "</tr>";

    } else {

        foreach ($items as $name => $count) {

            echo "<tr>";
            echo "<td>" . htmlspecialchars((string)$name) . "</td>";
            echo "<td>{$count}</td>";
            echo "</tr>";

        }

    }

    echo "</table><br>";

}

renderDistribution(
    "Difficulty",
    $report->statistics->questionsPerDifficulty
);

renderDistribution(
    "Status",
    $report->statistics->questionsPerStatus
);

renderDistribution(
    "Board",
    $report->statistics->questionsPerBoard
);

renderDistribution(
    "Subject",
    $report->statistics->questionsPerSubject
);

renderDistribution(
    "Domain",
    $report->statistics->questionsPerDomain
);

renderDistribution(
    "Topic",
    $report->statistics->questionsPerTopic
);

renderDistribution(
    "Concept",
    $report->statistics->questionsPerConcept
);

?>

<hr>

<h3>Issues by Category</h3>

<table border="1" cellpadding="6">

<tr>
    <th>Category</th>
    <th>Count</th>
</tr>

<?php foreach ($report->statistics->issuesByCategory as $category => $count): ?>

<tr>
    <td><?= htmlspecialchars($category) ?></td>
    <td><?= $count ?></td>
</tr>

<?php endforeach; ?>

</table>

<br>

<h3>Issues by Validator</h3>

<table border="1" cellpadding="6">

<tr>
    <th>Validator</th>
    <th>Count</th>
</tr>

<?php foreach ($report->statistics->issuesByValidator as $validator => $count): ?>

<tr>
    <td><?= htmlspecialchars($validator) ?></td>
    <td><?= $count ?></td>
</tr>

<?php endforeach; ?>

</table>

<hr>

<h3>Repository Issues</h3>

<?php if (empty($report->issues)): ?>

<p>✅ No issues found.</p>

<?php else: ?>

<table border="1" cellpadding="6">

<tr>
    <th>Question</th>
    <th>Severity</th>
    <th>Validator</th>
    <th>Code</th>
    <th>Message</th>
</tr>

<?php foreach ($report->issues as $issue): ?>

<tr>

<td>#<?= htmlspecialchars((string)$issue->entityId) ?></td>

<td><?= htmlspecialchars($issue->severity) ?></td>

<td><?= htmlspecialchars($issue->validator) ?></td>

<td><?= htmlspecialchars($issue->code) ?></td>

<td><?= htmlspecialchars($issue->message) ?></td>

</tr>

<?php endforeach; ?>

</table>

<?php endif; ?>

<hr>

<p>

<a href="/question-editor">
← Back to Question Editor
</a>

</p>
