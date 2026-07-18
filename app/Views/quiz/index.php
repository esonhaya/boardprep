<h2>
<?= ucfirst($_SESSION["mode"]) ?> Mode
</h2>


<?php

$current = $_SESSION["current"];

$total = count($_SESSION["questions"]);

$question = $_SESSION["questions"][$current];

?>


<h3>
Question <?= $current + 1 ?> / <?= $total ?>
</h3>


<h2>
<?= $question["question"] ?>
</h2>


<?php if(isset($_SESSION["feedback"])): ?>


<hr>


<?php if($_SESSION["feedback"]["correct"]): ?>

<h3>
✅ Correct!
</h3>

<?php else: ?>

<h3>
❌ Incorrect
</h3>


<p>
Correct Answer:
<?= $question["answer"] ?>
</p>

<?php endif; ?>


<p>
<strong>Explanation:</strong>
</p>

<p>
<?= $question["explanation"] ?>
</p>



<form method="POST" action="?page=quiz">

<button type="submit" name="next" value="1">

Next Question

</button>

</form>



<?php unset($_SESSION["feedback"]); ?>


<?php else: ?>


<form method="POST" action="?page=quiz">


<?php foreach($question["choices"] as $key=>$choice): ?>


<label>

<input 
type="radio"
name="answer"
value="<?= chr(65+$key) ?>"
required
>

<?= $choice ?>

</label>


<br>


<?php endforeach; ?>


<br>


<button type="submit">

Submit Answer

</button>


</form>


<?php endif; ?>
