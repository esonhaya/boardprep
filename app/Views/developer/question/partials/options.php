<?php

$options =
    $question["options"] ?? [];

while (
    count($options) < 4
) {

    $options[] = [

        "id" =>
            "option-" . (count($options) + 1),

        "text" =>
            "",

        "correct" =>
            false

    ];

}

$correctOption =
    "option-1";

foreach (
    $options as $option
) {

    if (
        !empty($option["correct"])
    ) {

        $correctOption =
            $option["id"];

        break;

    }

}

?>

<?php foreach (
    $options as $index => $option
): ?>

<label>

Option <?= $index + 1 ?>

</label>

<br>

<input
name="option_<?= $index + 1 ?>"
value="<?= htmlspecialchars($option["text"] ?? "") ?>"
required
>

<div class="form-spacer"></div>

<?php endforeach; ?>

<label>

Correct Option

</label>

<br>

<select
name="correct_option"
required
>

<?php foreach (
    $options as $index => $option
): ?>

<option
value="<?= htmlspecialchars($option["id"]) ?>"
<?= $correctOption === ($option["id"] ?? "")
? "selected"
: "" ?>
>

Option <?= $index + 1 ?>

</option>

<?php endforeach; ?>

</select>

<div class="form-spacer"></div>
