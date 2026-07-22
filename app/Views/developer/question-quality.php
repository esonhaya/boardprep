<h2>

Repository Health Dashboard

</h2>
<h3>

Overall Repository Health

</h3>

<h1>

<?= $healthScore ?>%

</h1>

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

<p>

Developer overview of the question repository.

</p>

<hr>

<table border="1" cellpadding="8">

<tr>

<th>Metric</th>

<th>Count</th>

<th>Status</th>

</tr>

<tr>

<td>

📝 Draft Questions

</td>

<td>

<?= count($drafts ?? []) ?>

</td>

<td>

<?= count($drafts ?? []) == 0 ? "✅" : "⚠" ?>

</td>

</tr>

<tr>

<td>

📖 Missing Explanations

</td>

<td>

<?= count($missingExplanation ?? []) ?>

</td>

<td>

<?= count($missingExplanation ?? []) == 0 ? "✅" : "⚠" ?>

</td>

</tr>

<tr>

<td>

❌ Invalid Answers

</td>

<td>

<?= count($invalidAnswers ?? []) ?>

</td>

<td>

<?= count($invalidAnswers ?? []) == 0 ? "✅" : "❌" ?>

</td>

</tr>

<tr>

<td>

📋 Missing Choices

</td>

<td>

<?= count($missingChoices ?? []) ?>

</td>

<td>

<?= count($missingChoices ?? []) == 0 ? "✅" : "❌" ?>

</td>

</tr>

<tr>

<td>

🔁 Duplicate Choices

</td>

<td>

<?= count($duplicateChoices ?? []) ?>

</td>

<td>

<?= count($duplicateChoices ?? []) == 0 ? "✅" : "⚠" ?>

</td>

</tr>

<tr>

<td>

❓ Empty Questions

</td>

<td>

<?= count($emptyQuestion ?? []) ?>

</td>

<td>

<?= count($emptyQuestion ?? []) == 0 ? "✅" : "❌" ?>

</td>

</tr>

</table>

<hr>

<p>

<a href="?page=question-editor">

← Back to Question Editor

</a>

</p>
