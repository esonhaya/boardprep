<h1>🧠 Learning Profile</h1>

<hr>

<h2>Current Level</h2>

<p>

<?= htmlspecialchars($profile["level"]) ?>

</p>

<hr>

<h2>Performance</h2>

<ul>

<li>

Overall Accuracy:
<?= $profile["overallAccuracy"] ?>%

</li>

<li>

Best Score:
<?= $profile["bestScore"] ?>%

</li>

<li>

Latest Score:
<?= $profile["latestScore"] ?>%

</li>

<li>

Total Quizzes:
<?= $profile["totalQuizzes"] ?>

</li>

</ul>

<hr>

<h2>Coach</h2>

<ul>

<?php foreach($coach as $message): ?>

<li>

<?= htmlspecialchars($message) ?>

</li>

<?php endforeach; ?>

</ul>

<hr>

<a href="?page=dashboard">

<button>

Dashboard

</button>

</a>


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
