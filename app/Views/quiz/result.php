<?php

$result = $result ?? [];

$mode = $mode ?? "practice";

?>

<h2>
Quiz Result
</h2>


<h3>
Mode:
<?= ucfirst($mode) ?>
</h3>


<h3>
Score:
<?= $result["score"] ?? 0 ?>
/
<?= $result["total"] ?? 0 ?>
</h3>



<?php foreach(($result["results"] ?? []) as $item): ?>

<hr>


<h4>
<?= htmlspecialchars($item["question"] ?? "") ?>
</h4>


<p>
Your Answer:

<?= htmlspecialchars(
$item["userAnswer"] ?? "No answer"
) ?>

</p>


<p>
Correct Answer:

<?= htmlspecialchars(
$item["correctAnswer"] ?? ""
) ?>

</p>



<?php if($item["correct"] ?? false): ?>

<p>
✅ Correct
</p>

<?php else: ?>

<p>
❌ Incorrect
</p>

<?php endif; ?>



<?php if($mode !== "exam"): ?>

<p>
<strong>
Explanation:
</strong>
</p>

<p>
<?= htmlspecialchars(
$item["explanation"] ?? ""
) ?>
</p>


<?php endif; ?>


<?php endforeach; ?>



<hr>


<a href="?page=dashboard">

<button type="button">
View Performance Dashboard
</button>

</a>


<br><br>


<a href="?page=quiz">

<button type="button">
Take Another Quiz
</button>

</a>


<br><br>


<a href="?page=home">

<button type="button">
Back to Home
</button>

</a>
