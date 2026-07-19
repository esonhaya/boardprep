<?php

$question = $question ?? null;
$current = $current ?? 0;
$total = $total ?? 0;
$mode = $mode ?? "practice";
$feedback = $feedback ?? null;

?>

<h3>
Question <?= $current + 1 ?> / <?= $total ?>
</h3>

<p>
Mode:
<strong>
<?= ucfirst($mode) ?>
</strong>
</p>


<?php if(!$question): ?>

<p>
No question available.
</p>

<?php exit; ?>

<?php endif; ?>


<h2>
<?= htmlspecialchars($question["question"]) ?>
</h2>


<?php if($feedback): ?>

<hr>

<?php if($feedback["correct"]): ?>

<h3>
✅ Correct!
</h3>

<?php else: ?>

<h3>
❌ Incorrect
</h3>

<p>
Correct Answer:
<?= htmlspecialchars($question["answer"]) ?>
</p>

<?php endif; ?>


<p>
<strong>
Explanation:
</strong>
</p>

<p>
<?= htmlspecialchars($question["explanation"] ?? "") ?>
</p>


<form method="POST" action="?page=quiz&action=next">

<button type="submit">
Next Question
</button>

</form>


<?php else: ?>


<form method="POST" action="?page=quiz&action=submit">


<?php foreach($question["choices"] as $key=>$choice): ?>


<label>

<input
type="radio"
name="answer"
value="<?= chr(65+$key) ?>"
required
>

<?= htmlspecialchars($choice) ?>

</label>

<br>


<?php endforeach; ?>


<br>

<button type="submit">
Submit Answer
</button>


</form>


<?php endif; ?>
