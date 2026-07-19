<h1>📊 Dashboard</h1>

<hr>

<h2>Recent Attempts</h2>

<?php if(empty($attempts)): ?>

<p>No quiz attempts yet.</p>

<?php else: ?>

<table border="1" cellpadding="6">

<tr>

<th>Date</th>
<th>Mode</th>
<th>Topic</th>
<th>Score</th>
<th>%</th>

</tr>

<?php foreach($attempts as $attempt): ?>

<tr>

<td><?= htmlspecialchars($attempt["date"]) ?></td>

<td><?= htmlspecialchars(ucfirst($attempt["mode"])) ?></td>

<td><?= htmlspecialchars($attempt["topic"]) ?></td>

<td>
<?= $attempt["score"] ?>
/
<?= $attempt["total"] ?>
</td>

<td>
<?= $attempt["percentage"] ?>%
</td>

</tr>

<?php endforeach; ?>

</table>

<?php endif; ?>

<hr>

<h2>Quick Navigation</h2>

<a href="?page=profile">

<button>

🧠 Learning Profile

</button>

</a>

<br><br>

<a href="?page=quiz">

<button>

📚 Take Quiz

</button>

</a>

<br><br>

<a href="?page=home">

<button>

🏠 Home

</button>

</a>
