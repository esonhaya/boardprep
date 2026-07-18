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



<form method="POST" action="?page=quiz">


<?php foreach($question["choices"] as $key=>$choice): ?>


<label>

<input 
type="radio"
name="answer"
value="<?= chr(65+$key) ?>"
>


<?= $choice ?>


</label>


<br>


<?php endforeach; ?>


<br>


<button type="submit">

<?php

if($current + 1 >= $total)
{
    echo "Finish";
}
else
{
    echo "Next";
}

?>

</button>


</form>
