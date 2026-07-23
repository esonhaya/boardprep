<?php

$summary =
    $result["summary"];

$review =
    $result["review"];

?>

<h1>
    Quiz Result
</h1>


<div class="result-summary">

    <h2>
        Score:
        <?= $summary["score"] ?>
        /
        <?= $summary["total"] ?>
    </h2>


    <h3>
        <?= $summary["percentage"] ?>%
    </h3>

</div>



<h2>
    Answer Review
</h2>


<?php foreach ($review as $index => $item): ?>

<div class="question-review">


    <h3>
        <?= ($index + 1) ?>.
        <?= htmlspecialchars(
            $item["question"]["question"]
        ) ?>
    </h3>


    <p>
        Your Answer:
        <strong>
            <?= htmlspecialchars(
                $item["userAnswer"] ?? "No answer"
            ) ?>
        </strong>
    </p>


    <?php if ($item["correct"]): ?>

        <p>
            ✅ Correct
        </p>

    <?php else: ?>

        <p>
            ❌ Incorrect
        </p>


        <p>
            Correct Answer:
            <strong>
                <?= htmlspecialchars(
                    $item["question"]["answer"]
                ) ?>
            </strong>
        </p>

    <?php endif; ?>


    <p>
        Explanation:
        <?= htmlspecialchars(
            $item["question"]["explanation"] ?? ""
        ) ?>
    </p>


</div>

<hr>

<?php endforeach; ?>
