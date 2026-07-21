<?php

$isEdit =
    ($contentMode ?? "") === "edit";

$question =
    $question ?? [];

$errors =
    $errors ?? [];


function fieldError(
    array $errors,
    string $field
): string
{

    return $errors[$field] ?? "";

}


?>

<h2>

<?= $isEdit
    ? "Edit Question"
    : "Create Question"
?>

</h2>

<?php if (!empty($duplicates)): ?>

<div style="border:2px solid orange; padding:10px; margin:10px 0;">

<h3>
⚠ Possible Duplicate Questions
</h3>

<p>
The system found similar questions already in the bank:
</p>


<?php foreach($duplicates as $duplicate): ?>

<p>

<strong>
Question #<?= $duplicate["question"]["id"] ?? "" ?>
</strong>

<br>

Similarity:
<?= $duplicate["score"] ?>%

<br>

<?= htmlspecialchars(
    $duplicate["question"]["question"] ?? ""
) ?>

</p>

<hr>

<?php endforeach; ?>

</div>

<?php endif; ?>


<form
method="POST"
action="?page=question-editor&action=<?= $isEdit ? "update&id=" . ($question["id"] ?? 0) : "save" ?>"
>


<label>

Domain

</label>

<br>

<select name="domain">

<?php foreach(($domains ?? []) as $domain): ?>

<option
value="<?= htmlspecialchars($domain) ?>"
<?= (($question["domain"] ?? "") === $domain) ? "selected" : "" ?>
>

<?= htmlspecialchars($domain) ?>

</option>

<?php endforeach; ?>

</select>


<br><br>


<label>

Topic

</label>

<br>

<select name="topic">

<?php foreach(($topics ?? []) as $group): ?>

<?php foreach(($group["topics"] ?? []) as $topic): ?>

<option
value="<?= htmlspecialchars($topic) ?>"
<?= (($question["topic"] ?? "") === $topic) ? "selected" : "" ?>
>

<?= htmlspecialchars($topic) ?>

</option>

<?php endforeach; ?>

<?php endforeach; ?>

</select>


<br><br>


<label>

Concept

</label>

<br>

<select name="concept">

<?php foreach(($concepts ?? []) as $group): ?>

<?php foreach(($group["concepts"] ?? []) as $concept): ?>

<option
value="<?= htmlspecialchars($concept) ?>"
<?= (($question["concept"] ?? "") === $concept) ? "selected" : "" ?>
>

<?= htmlspecialchars($concept) ?>

</option>

<?php endforeach; ?>

<?php endforeach; ?>

</select>


<br><br>


<label>

Difficulty

</label>

<br>

<select name="difficulty">

<option
value="easy"
<?= (($question["difficulty"] ?? "") === "easy") ? "selected" : "" ?>
>

Easy

</option>

<option
value="medium"
<?= (($question["difficulty"] ?? "") === "medium") ? "selected" : "" ?>
>

Medium

</option>

<option
value="hard"
<?= (($question["difficulty"] ?? "") === "hard") ? "selected" : "" ?>
>

Hard

</option>

</select>

<label>

Question

</label>

<br>

<?php if (fieldError($errors, "question")): ?>

<p style="color:red; margin:4px 0;">

<?= htmlspecialchars(
    fieldError(
        $errors,
        "question"
    )
) ?>

</p>

<?php endif; ?>

<textarea
name="question"
rows="4"
cols="60"
required
style="<?= fieldError($errors, "question")
    ? "border:2px solid red;"
    : "" ?>"
><?= htmlspecialchars(
    $question["question"] ?? ""
) ?></textarea>

<br><br>



<br><br>


<label>

Choice A

</label>

<br>

<input
name="choice_a"
value="<?= htmlspecialchars($question["choices"][0] ?? "") ?>"
required
>


<br><br>


<label>

Choice B

</label>

<br>

<input
name="choice_b"
value="<?= htmlspecialchars($question["choices"][1] ?? "") ?>"
required
>


<br><br>


<label>

Choice C

</label>

<br>

<input
name="choice_c"
value="<?= htmlspecialchars($question["choices"][2] ?? "") ?>"
required
>


<br><br>


<label>

Choice D

</label>

<br>

<input
name="choice_d"
value="<?= htmlspecialchars($question["choices"][3] ?? "") ?>"
required
>


<br><br>

<label>

Correct Answer

</label>

<br>

<?php if (fieldError($errors, "answer")): ?>

<p style="color:red; margin:4px 0;">

<?= htmlspecialchars(
    fieldError(
        $errors,
        "answer"
    )
) ?>

</p>

<?php endif; ?>

<input
name="answer"
value="<?= htmlspecialchars($question["answer"] ?? "") ?>"
required
style="<?= fieldError($errors, "answer")
    ? "border:2px solid red;"
    : "" ?>"
>


<br><br>


<label>

Explanation

</label>

<br>

<?php if (fieldError($errors, "explanation")): ?>

<p style="color:red; margin:4px 0;">

<?= htmlspecialchars(
    fieldError(
        $errors,
        "explanation"
    )
) ?>

</p>

<?php endif; ?>

<textarea
name="explanation"
rows="5"
cols="60"
required
style="<?= fieldError($errors, "explanation")
    ? "border:2px solid red;"
    : "" ?>"
><?= htmlspecialchars(
    $question["explanation"] ?? ""
) ?></textarea>

<br><br>


<button type="submit">

<?= $isEdit
? "Update Question"
: "Save Question"
?>

</button>

</form>
