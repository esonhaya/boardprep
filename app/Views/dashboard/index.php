<h2>📚 Learning Center</h2>

<hr>

<h3>📊 Progress</h3>

<p>

Attempts:

<strong>

<?= $progress["attempts"] ?>

</strong>

</p>

<p>

Average:

<strong>

<?= $progress["average"] ?>%

</strong>

</p>

<p>

Best:

<strong>

<?= $progress["best"] ?>%

</strong>

</p>

<hr>

<h3>🎯 Today's Focus</h3>

<?php if($recommendation): ?>

<p>

Study:

<strong>

<?= htmlspecialchars($recommendation) ?>

</strong>

</p>

<?php else: ?>

<p>

Keep answering quizzes.

BoardPrep is still learning.

</p>

<?php endif; ?>

<hr>

<h3>🧠 Mastery</h3>

<?php if(empty($mastery)): ?>

<p>

No mastery data yet.

</p>

<?php else: ?>

<ul>

<?php foreach($mastery as $topic => $data): ?>

<li>

<strong>

<?= htmlspecialchars($topic) ?>

</strong>

-

<?= $data["percentage"] ?>%

(

<?= $data["status"] ?>

)

</li>

<?php endforeach; ?>

</ul>

<?php endif; ?>

<hr>

<h3>📝 Latest Quiz</h3>

<?php if($latest): ?>

<p>

<?= htmlspecialchars($latest["subject"]) ?>

-

<?= htmlspecialchars($latest["topic"]) ?>

</p>

<p>

<?= $latest["percentage"] ?>%

</p>

<?php else: ?>

<p>

No attempts yet.

</p>

<?php endif; ?>
