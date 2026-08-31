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


<label for="blueprint-board">
Board
</label>

<br>

<select id="blueprint-board" name="board">

<?php foreach ($boards as $board): ?>

<option
value="<?= htmlspecialchars($board["id"]) ?>"
>

<?= htmlspecialchars($board["name"]) ?>

</option>

<?php endforeach; ?>

</select>


<div class="form-spacer"></div>



<label for="blueprint-subject">
Subject
</label>

<br>

<select id="blueprint-subject" name="subject">

<?php foreach ($subjects as $subject): ?>

<option
value="<?= htmlspecialchars($subject["id"]) ?>"
>

<?= htmlspecialchars($subject["name"]) ?>

</option>

<?php endforeach; ?>

</select>


<div class="form-spacer"></div>



<label for="blueprint-name">
Blueprint Name
</label>

<br>

<input
type="text"
id="blueprint-name"
name="name"
placeholder="LET English Professional"
required
>


<div class="form-spacer"></div>



<label for="blueprint-count">
Question Count
</label>

<br>

<input
type="number"
id="blueprint-count"
name="questionCount"
value="150"
min="1"
required
>


<div class="form-spacer"></div>



<h3>
Difficulty Distribution
</h3>


<label for="easy">
Easy:
<span id="easyValue">40</span>%
</label>

<br>

<label class="sr-only" for="easyInput">Easy percentage</label>
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


<div class="form-spacer"></div>



<label for="medium">
Medium:
<span id="mediumValue">50</span>%
</label>

<br>

<label class="sr-only" for="mediumInput">Medium percentage</label>
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


<div class="form-spacer"></div>



<label for="hard">
Hard:
<span id="hardValue">10</span>%
</label>

<br>

<label class="sr-only" for="hardInput">Hard percentage</label>
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


<div class="form-spacer"></div>



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
