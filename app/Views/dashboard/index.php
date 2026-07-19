<h1>📚 BoardPrep Dashboard</h1>

<h2>Recent Attempts</h2>

<?php if(empty($attempts)): ?>

<p>
No quiz attempts yet.
</p>

<?php else: ?>

<table border="1" cellpadding="6">

<tr>

<th>Date</th>

<th>Mode</th>

<th>Topic</th>

<th>Score</th>

<th>Percentage</th>

</tr>

<?php foreach($attempts as $attempt): ?>

<tr>

<td>
<?= htmlspecialchars($attempt["date"]) ?>
</td>

<td>
<?= htmlspecialchars(ucfirst($attempt["mode"])) ?>
</td>

<td>
<?= htmlspecialchars($attempt["topic"]) ?>
</td>

<td>
<?= htmlspecialchars($attempt["score"]) ?>
/
<?= htmlspecialchars($attempt["total"]) ?>
</td>

<td>
<?= htmlspecialchars($attempt["percentage"]) ?>%
</td>

</tr>

<?php endforeach; ?>

</table>

<?php endif; ?>


<br><br>


<h2>Weakness Analysis</h2>

<?php if(empty($weaknesses)): ?>

<p>
No weakness data available yet.
</p>

<?php else: ?>

<ul>

<?php foreach($weaknesses as $weakness): ?>

<li>

<?= htmlspecialchars($weakness["topic"] ?? "Unknown") ?>

-

<?= htmlspecialchars($weakness["accuracy"] ?? 0) ?>%

</li>

<?php endforeach; ?>

</ul>

<?php endif; ?>


<br><br>


<a href="?page=quiz">

<button>

Take Quiz

</button>

</a>


<a href="?page=home">

<button>

Home

</button>

</a>
