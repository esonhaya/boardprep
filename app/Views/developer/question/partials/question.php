<label>

Question

</label>

<br>

<?php if (
    fieldError(
        $errors,
        "question"
    )
): ?>

<p
style="
color:red;
margin:4px 0;
"
>

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

<label>

Explanation

</label>

<br>

<?php if (
    fieldError(
        $errors,
        "explanation"
    )
): ?>

<p
style="
color:red;
margin:4px 0;
"
>

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
