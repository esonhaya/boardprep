<?php

$csrfToken = \App\Services\Shared\CsrfTokenService::token();
$question = $question ?? null;
$current = $current ?? 0;
$total = $total ?? 0;
$mode = $mode ?? "practice";
$feedback = $feedback ?? null;
$studyReferences = is_array($studyReferences ?? null) ? $studyReferences : [];
$progressValue = $total > 0 ? min(100, max(0, (int) round(($current + 1) / $total * 100))) : 0;
$quizSession = \SessionService::get('quiz_session', []);
$quizSession = is_array($quizSession) ? $quizSession : [];
$isExamSimulation = $mode === 'exam';
$answers = \SessionService::get('answers', []);
$answers = is_array($answers) ? $answers : [];
$questionId = is_array($question) && is_scalar($question['id'] ?? null) ? (string) $question['id'] : '';
$savedAnswer = $questionId !== '' && is_scalar($answers[$questionId] ?? null)
    ? strtoupper(trim((string) $answers[$questionId]))
    : '';
$boardValue = $quizSession['board'] ?? $quizSession['exam'] ?? '';
$boardLabel = \App\Services\Shared\TaxonomyStorageService::label('boards', $boardValue);
if ($boardLabel === '' && is_scalar($boardValue)) {
    $boardLabel = trim((string) $boardValue);
}
$subjectValue = is_scalar($quizSession['subject'] ?? null) ? trim((string) $quizSession['subject']) : '';
$subjectLabel = $subjectValue === '*'
    ? 'All available subjects'
    : \App\Services\Shared\TaxonomyStorageService::label('subjects', $subjectValue);
if ($subjectLabel === '' && $subjectValue !== '*') {
    $subjectLabel = $subjectValue;
}
$variantLabels = [];
$letLevel = is_scalar($quizSession['let_level'] ?? null) ? trim((string) $quizSession['let_level']) : '';
if ($letLevel !== '') {
    $variantLabels[] = ucfirst(strtolower($letLevel));
}
$specializationId = is_scalar($quizSession['let_specialization_id'] ?? null)
    ? trim((string) $quizSession['let_specialization_id'])
    : '';
if ($specializationId !== '') {
    $specializationLabel = \App\Services\Shared\TaxonomyStorageService::label('let-specializations', $specializationId);
    if ($specializationLabel !== '') {
        $variantLabels[] = $specializationLabel;
    }
}
$civilServiceLevel = is_scalar($quizSession['civil_service_level'] ?? null)
    ? trim((string) $quizSession['civil_service_level'])
    : '';
if ($civilServiceLevel !== '') {
    $variantLabels[] = ucfirst(strtolower(str_replace('_', ' ', $civilServiceLevel)));
}

?>

<section class="quiz-shell" aria-label="<?= $isExamSimulation ? 'Exam simulation question' : 'Practice question' ?>">
<?php if ($boardLabel !== '' || $subjectLabel !== '' || $variantLabels !== []): ?>
<p class="quiz-context" aria-label="Selected study context">
    <?php if ($boardLabel !== ''): ?><span><strong>Board</strong> <?= htmlspecialchars($boardLabel, ENT_QUOTES, 'UTF-8') ?></span><?php endif; ?>
    <?php if ($subjectLabel !== ''): ?><span><strong>Subject</strong> <?= htmlspecialchars($subjectLabel, ENT_QUOTES, 'UTF-8') ?></span><?php endif; ?>
    <?php foreach ($variantLabels as $variantLabel): ?><span><?= htmlspecialchars($variantLabel, ENT_QUOTES, 'UTF-8') ?></span><?php endforeach; ?>
</p>
<?php endif; ?>
<?php if (is_scalar($quizSession['selection_notice'] ?? null) && trim((string) $quizSession['selection_notice']) !== ''): ?>
<p class="quiz-selection-notice" role="status"><?= htmlspecialchars((string) $quizSession['selection_notice'], ENT_QUOTES, 'UTF-8') ?></p>
<?php endif; ?>
<div class="quiz-meta"><span class="quiz-position">Question <?= $current + 1 ?> / <?= $total ?></span><span class="quiz-mode-label">
<?= $isExamSimulation ? 'Exam simulation' : 'Mode' ?>:
<strong><?= $isExamSimulation ? 'No official exam authority' : htmlspecialchars(ucfirst((string) $mode), ENT_QUOTES, 'UTF-8') ?></strong>
</span></div>
<?php if ($isExamSimulation): ?>
<p class="exam-simulation-disclosure">BoardPrep’s exam simulator is for independent study and is separate from the official board examination.</p>
<?php include __DIR__ . '/_timer.php'; ?>
<?php endif; ?>
<div class="progress-bar" role="progressbar" aria-label="Practice progress" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= $progressValue ?>"><span aria-hidden="true" style="width:<?= $progressValue ?>%"></span></div>


<?php if(!$question): ?>

<p role="status">
No question available.
</p>

<p><a class="button button-outline" href="/quiz">Return to practice setup</a></p>

<?php exit; ?>

<?php endif; ?>


<h1 class="quiz-question" id="practice-question-heading">
<?= htmlspecialchars($question["question"]) ?>
</h1>

<?php if (!empty($question['content_blocks'])): ?>
    <?= \App\Services\Question\StructuredContentService::render($question) ?>
<?php endif; ?>


<?php if($feedback): ?>

<hr>

<section class="quiz-feedback <?= $feedback["correct"] ? "is-correct" : "is-incorrect" ?>" role="status" aria-live="polite">

<?php if($feedback["correct"]): ?>

<h3>
✅ Correct!
</h3>

<?php else: ?>

<h3>
❌ Incorrect
</h3>

<p>
Correct Answer:
<?= htmlspecialchars($question["answer"]) ?>
</p>

<?php endif; ?>


<p>
<strong>
Explanation:
</strong>
</p>

<p>
<?= htmlspecialchars($question["explanation"] ?? "") ?>
</p>

<?php if ($studyReferences !== []): ?>
<section class="quiz-study-support" aria-label="Related study materials">
<h4>Study support</h4>
<ul><?php foreach ($studyReferences as $reference): ?><?php $materialId = is_scalar($reference['material_id'] ?? null) ? (string) $reference['material_id'] : ''; ?><?php if ($materialId !== ''): ?><li><a href="/study-library?material=<?= rawurlencode($materialId) ?>"><?= htmlspecialchars((string) ($reference['title'] ?? 'Source-backed study material'), ENT_QUOTES, 'UTF-8') ?></a></li><?php endif; ?><?php endforeach; ?></ul>
</section>
<?php endif; ?>


<?php if($current + 1 >= $total): ?>

<form method="POST" action="/quiz">

<input
type="hidden"
name="page"
value="quiz"
>

<input
type="hidden"
name="action"
value="finish"
>

<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

<button type="submit">
Finish Quiz
</button>

</form>


<?php else: ?>


<form method="POST" action="/quiz?action=next">

<input type="hidden" name="action" value="next">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

<button type="submit">
Next Question
</button>

</form>


<?php endif; ?>

</section>


<?php else: ?>


<form class="quiz-answer-form card" method="POST" action="/quiz?action=submit">

<input
type="hidden"
name="question_id"
value="<?= htmlspecialchars((string) ($question["id"] ?? "")) ?>"
>

<input type="hidden" name="action" value="submit">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">


<fieldset class="quiz-choices">
<legend>Choose one answer</legend>

<?php foreach($question["choices"] as $key=>$choice): ?>

<?php $answerValue = chr(65 + $key); ?>


<label class="quiz-choice" for="answer-<?= $answerValue ?>">

<input
id="answer-<?= $answerValue ?>"
type="radio"
name="answer"
value="<?= $answerValue ?>"
required
<?= $savedAnswer === $answerValue ? 'checked' : '' ?>
>

<span><strong><?= $answerValue ?>.</strong> <?= htmlspecialchars($choice) ?></span>

</label>


<?php endforeach; ?>

</fieldset>


<div class="quiz-actions"><button type="submit">
<?= $isExamSimulation ? 'Save answer and continue' : 'Submit answer' ?>
 </button></div>


</form>

<?php if ($isExamSimulation): ?>
<nav class="exam-question-navigation" aria-label="Exam simulation question navigation">
    <form method="POST" action="/quiz">
        <input type="hidden" name="action" value="previous">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
        <button type="submit" class="button button-outline" <?= $current <= 0 ? 'disabled' : '' ?>>Previous question</button>
    </form>
    <form method="POST" action="/quiz">
        <input type="hidden" name="action" value="next">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
        <button type="submit" class="button button-outline"><?= $current + 1 >= $total ? 'Review answers' : 'Skip to next question' ?></button>
    </form>
    <a class="button button-outline" href="/quiz?action=review">Review all answers</a>
</nav>
<?php endif; ?>


<?php endif; ?></section>
<script src="/assets/js/quiz-focus.js" defer></script>
