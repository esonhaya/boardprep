<?php

$question = $question ?? null;
$current = $current ?? 0;
$total = $total ?? 0;
$mode = $mode ?? "practice";
$feedback = $feedback ?? null;

?>

<p class="quiz-progress">
Question <?= $current + 1 ?> / <?= $total ?>
</p>

<p class="quiz-mode">
Mode:
<strong>
<?= ucfirst($mode) ?>
</strong>
</p>


<?php if(!$question): ?>

<p role="status">
No question available.
</p>

<?php exit; ?>

<?php endif; ?>


<h1 class="quiz-question">
<?= htmlspecialchars($question["question"]) ?>
</h1>


<?php if($feedback): ?>

<hr>

<section class="quiz-feedback <?= $feedback["correct"] ? "is-correct" : "is-incorrect" ?>" role="status" aria-live="polite">

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


<?php if($current + 1 >= $total): ?>

<form method="GET" action="">

<input
type="hidden"
name="page"
value="quiz"
>

<input
type="hidden"
name="action"
value="finish"
>

<button type="submit">
Finish Quiz
</button>

</form>


<?php else: ?>


<form method="POST" action="/quiz?action=next">

<button type="submit">
Next Question
</button>

</form>


<?php endif; ?>

</section>


<?php else: ?>


<form class="quiz-answer-form" method="POST" action="/quiz?action=submit">

<input
type="hidden"
name="question_id"
value="<?= htmlspecialchars((string) ($question["id"] ?? "")) ?>"
>


<fieldset class="quiz-choices">
<legend>Choose one answer</legend>

<?php foreach($question["choices"] as $key=>$choice): ?>

<?php $answerValue = chr(65 + $key); ?>


<label class="quiz-choice" for="answer-<?= $answerValue ?>">

<input
id="answer-<?= $answerValue ?>"
type="radio"
name="answer"
value="<?= $answerValue ?>"
required
>

<span><strong><?= $answerValue ?>.</strong> <?= htmlspecialchars($choice) ?></span>

</label>


<?php endforeach; ?>

</fieldset>


<br>

<button type="submit">
Submit Answer
</button>


</form>


<?php endif; ?>
