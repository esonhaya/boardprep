<h2>Create Blueprint</h2>

<hr>


<?php if (!empty($errors)): ?>

<h3>Errors</h3>

<ul>

<?php foreach ($errors as $error): ?>

<li>
<?= htmlspecialchars($error) ?>
</li>

<?php endforeach; ?>

</ul>

<?php endif; ?>


<form method="POST" action="/blueprints/save">


<label>
Board
</label>

<br>

<select name="board">

<?php foreach ($boards as $board): ?>

<option
value="<?= htmlspecialchars($board["id"]) ?>"
>

<?= htmlspecialchars($board["name"]) ?>

</option>

<?php endforeach; ?>

</select>


<br><br>



<label>
Subject
</label>

<br>

<select name="subject">

<?php foreach ($subjects as $subject): ?>

<option
value="<?= htmlspecialchars($subject["id"]) ?>"
>

<?= htmlspecialchars($subject["name"]) ?>

</option>

<?php endforeach; ?>

</select>


<br><br>



<label>
Blueprint Name
</label>

<br>

<input
type="text"
name="name"
placeholder="LET English Professional"
required
>


<br><br>



<label>
Question Count
</label>

<br>

<input
type="number"
name="questionCount"
value="150"
min="1"
required
>


<br><br>



<h3>
Difficulty Distribution
</h3>


<label>
Easy:
<span id="easyValue">40</span>%
</label>

<br>

<input
type="range"
id="easy"
name="easy"
min="0"
max="100"
value="40"
>

<input
type="number"
id="easyInput"
min="0"
max="100"
value="40"
>


<br><br>



<label>
Medium:
<span id="mediumValue">50</span>%
</label>

<br>

<input
type="range"
id="medium"
name="medium"
min="0"
max="100"
value="50"
>

<input
type="number"
id="mediumInput"
min="0"
max="100"
value="50"
>


<br><br>



<label>
Hard:
<span id="hardValue">10</span>%
</label>

<br>

<input
type="range"
id="hard"
name="hard"
min="0"
max="100"
value="10"
>

<input
type="number"
id="hardInput"
min="0"
max="100"
value="10"
>


<br><br>



<h3>

Total:

<span id="total">
100
</span>%

<span id="status">
✅
</span>

</h3>



<button
type="submit"
id="submitButton"
>

Create Blueprint

</button>


</form>


<script src="/assets/js/blueprint/create.js"></script>
