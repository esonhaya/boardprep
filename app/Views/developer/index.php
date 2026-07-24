<h2>Developer Tools</h2>

<hr>

<?php if (!empty($taxonomyStatus)): ?>

<h3>Taxonomy Rebuilt</h3>

<p>
Domains:
<strong>
<?= $taxonomyStatus["domains"] ?>
</strong>
</p>

<p>
Topics:
<strong>
<?= $taxonomyStatus["topics"] ?>
</strong>
</p>

<p>
Concepts:
<strong>
<?= $taxonomyStatus["concepts"] ?>
</strong>
</p>

<hr>

<?php endif; ?>


<h3>Maintenance</h3>

<a href="?page=developer&action=rebuild-taxonomy">

<button>
Rebuild Taxonomy
</button>

</a>


<hr>


<h3>Question Bank Audit</h3>


<p>
Total Questions:

<strong>
<?= $audit["questions"]["total"] ?? 0 ?>
</strong>

</p>



<h3>Taxonomy</h3>

<pre>

<?= htmlspecialchars(
    json_encode(
        $audit["taxonomy"] ?? [],
        JSON_PRETTY_PRINT
    )
) ?>

</pre>



<h3>Quality</h3>

<pre>

<?= htmlspecialchars(
    json_encode(
        $audit["quality"] ?? [],
        JSON_PRETTY_PRINT
    )
) ?>

</pre>



<h3>Coverage</h3>

<pre>

<?= htmlspecialchars(
    json_encode(
        $audit["coverage"] ?? [],
        JSON_PRETTY_PRINT
    )
) ?>

</pre>



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
