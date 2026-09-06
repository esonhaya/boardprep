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

<?php
$old = is_array($old ?? null) ? $old : [];
$difficulty = [
    'easy' => $old['easy'] ?? 40,
    'medium' => $old['medium'] ?? 50,
    'hard' => $old['hard'] ?? 10,
];
?>


<form method="POST" action="/blueprints/save">


<label for="blueprint-board">
Board
</label>

<br>

<select id="blueprint-board" name="board">

<?php foreach ($boards as $board): ?>

<option
value="<?= htmlspecialchars($board["id"]) ?>"
<?= (($old['board'] ?? '') === ($board['id'] ?? '')) ? 'selected' : '' ?>
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
<?= (($old['subject'] ?? '') === ($subject['id'] ?? '')) ? 'selected' : '' ?>
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
value="<?= htmlspecialchars((string) ($old['name'] ?? '')) ?>"
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
value="<?= htmlspecialchars((string) ($old['questionCount'] ?? 150)) ?>"
min="1"
required
>


<div class="form-spacer"></div>



<h3>
Difficulty Distribution
</h3>


<label for="easy">
Easy:
<span id="easyValue"><?= htmlspecialchars((string) $difficulty['easy']) ?></span>%
</label>

<br>

<label class="sr-only" for="easyInput">Easy percentage</label>
<input
type="range"
id="easy"
name="easy"
min="0"
max="100"
value="<?= htmlspecialchars((string) $difficulty['easy']) ?>"
>

<input
type="number"
id="easyInput"
min="0"
max="100"
value="<?= htmlspecialchars((string) $difficulty['easy']) ?>"
>


<div class="form-spacer"></div>



<label for="medium">
Medium:
<span id="mediumValue"><?= htmlspecialchars((string) $difficulty['medium']) ?></span>%
</label>

<br>

<label class="sr-only" for="mediumInput">Medium percentage</label>
<input
type="range"
id="medium"
name="medium"
min="0"
max="100"
value="<?= htmlspecialchars((string) $difficulty['medium']) ?>"
>

<input
type="number"
id="mediumInput"
min="0"
max="100"
value="<?= htmlspecialchars((string) $difficulty['medium']) ?>"
>


<div class="form-spacer"></div>



<label for="hard">
Hard:
<span id="hardValue"><?= htmlspecialchars((string) $difficulty['hard']) ?></span>%
</label>

<br>

<label class="sr-only" for="hardInput">Hard percentage</label>
<input
type="range"
id="hard"
name="hard"
min="0"
max="100"
value="<?= htmlspecialchars((string) $difficulty['hard']) ?>"
>

<input
type="number"
id="hardInput"
min="0"
max="100"
value="<?= htmlspecialchars((string) $difficulty['hard']) ?>"
>


<div class="form-spacer"></div>



<h3>

Total:

<output id="total" aria-live="polite">
100
</output>%

<span id="status" role="status" aria-live="polite">
Valid allocation.
</span>

</h3>

<p class="form-help">Adjusting one value redistributes the remaining percentage. The total must equal 100%.</p>



<button
type="submit"
id="submitButton"
>

Create Blueprint

</button>


</form>


<script src="/assets/js/blueprint/create.js"></script>
