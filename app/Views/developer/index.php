<h2>Developer Tools</h2>

<hr>

<h3>Question Bank Audit</h3>

<p>
Total Questions:
<strong>
<?= $audit["total"] ?>
</strong>
</p>


<h3>Subjects</h3>

<ul>

<?php foreach($audit["subjects"] as $name => $count): ?>

<li>

<?= htmlspecialchars($name) ?>

:

<?= $count ?>

</li>

<?php endforeach; ?>

</ul>


<h3>Topics</h3>

<ul>

<?php foreach($audit["topics"] as $name => $count): ?>

<li>

<?= htmlspecialchars($name) ?>

:

<?= $count ?>

</li>

<?php endforeach; ?>

</ul>
<h3>Concepts</h3>

<ul>

<?php foreach($audit["concepts"] as $name => $count): ?>

<li>

<?= htmlspecialchars($name) ?>

:

<?= $count ?>

</li>

<?php endforeach; ?>

</ul>

<h3>Difficulty</h3>

<ul>

<?php foreach($audit["difficulty"] as $name => $count): ?>

<li>

<?= htmlspecialchars($name) ?>

:

<?= $count ?>

</li>

<?php endforeach; ?>

</ul>


<h3>Validation</h3>

<p>

Duplicate IDs:

<strong>

<?= count($audit["duplicateIds"]) ?>

</strong>

</p>

<p>

Missing Explanations:

<strong>

<?= $audit["missingExplanation"] ?>

</strong>

</p>

<p>

Missing Choices:

<strong>

<?= $audit["missingChoices"] ?>

</strong>

</p>

<hr>

<h2>📚 Question Management</h2>

<ul>

<li>
<a href="?page=question-editor">
Question Editor
</a>
</li>

<li>
<a href="?page=question-import">
Import Questions
</a>
</li>

<li>
<a href="?page=question-export">
Export Questions
</a>
</li>

<li>
<a href="?page=question-quality">
Repository Health
</a>
</li>

<li>
<a href="?page=question-inspector">
Question Inspector
</a>
</li>

</ul>
