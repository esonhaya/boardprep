<h2>Quiz Result</h2>


<h3>
Mode:
<?= ucfirst($_SESSION["mode"]) ?>
</h3>


<h3>
Score:
<?= $result["score"] ?>
/
<?= $result["total"] ?>
</h3>



<?php foreach ($result["results"] as $item): ?>


<hr>


<h4>
<?= $item["question"]["question"] ?>
</h4>


<p>
Your Answer:
<?= $item["userAnswer"] ?? "No answer" ?>
</p>


<p>
Correct Answer:
<?= $item["correctAnswer"] ?>
</p>



<?php if ($item["correct"]): ?>

<p>
✅ Correct
</p>


<?php else: ?>

<p>
❌ Incorrect
</p>


<?php endif; ?>



<?php if ($_SESSION["mode"] !== "exam"): ?>


<p>
<strong>Explanation:</strong>
</p>


<p>
<?= $item["question"]["explanation"] ?>
</p>


<?php endif; ?>



<?php endforeach; ?>
