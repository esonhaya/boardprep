<?php

$summary = $summary ?? [];
$review = $review ?? [];
$actions = $actions ?? [];

?>

<h1>
    Quiz Result
</h1>

<div class="result-summary">
    <h2>
        Score:
        <?= $summary["score"] ?? 0 ?>
        /
        <?= $summary["total"] ?? 0 ?>
    </h2>

    <h3>
        <?= $summary["percentage"] ?? 0 ?>%
    </h3>
</div>

<?php if (!empty($actions)): ?>
<section class="result-next-steps">
    <h2>What Next?</h2>

    <?php foreach ($actions as $action): ?>
        <div class="result-action">
            <h3>
                <a href="<?= htmlspecialchars((string) ($action["url"] ?? "/study")) ?>">
                    <?= htmlspecialchars((string) ($action["label"] ?? "Continue")) ?>
                </a>
            </h3>
            <p>
                <?= htmlspecialchars((string) ($action["reason"] ?? "")) ?>
            </p>
        </div>
    <?php endforeach; ?>
</section>
<?php endif; ?>

<h2>
    Answer Review
</h2>

<?php foreach ($review as $index => $item): ?>
<div class="question-review">
    <h3>
        <?= ($index + 1) ?>.
        <?= htmlspecialchars(
            $item["question"]["question"] ?? ""
        ) ?>
    </h3>

    <p>
        Your Answer:
        <strong>
            <?= htmlspecialchars(
                ($item["answered"] ?? false)
                    ? (string) ($item["userAnswer"] ?? '')
                    : "No answer"
            ) ?>
        </strong>
    </p>

    <?php if (!empty($item["correct"])): ?>
        <p>Correct</p>
    <?php else: ?>
        <p>Incorrect</p>

        <p>
            Correct Answer:
            <strong>
                <?= htmlspecialchars(
                    $item["question"]["answer"] ?? ""
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
