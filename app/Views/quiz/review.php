<?php
$questions = is_array($questions ?? null) ? array_values($questions) : [];
$answers = is_array($answers ?? null) ? $answers : [];
$quizSession = \SessionService::get('quiz_session', []);
$quizSession = is_array($quizSession) ? $quizSession : [];
$csrfToken = \App\Services\Shared\CsrfTokenService::token();
$currentIndex = \SessionService::get('currentQuestion', 0);
$currentIndex = is_int($currentIndex) || (is_string($currentIndex) && preg_match('/^\d+$/', $currentIndex) === 1)
    ? (int) $currentIndex
    : 0;
$currentQuestionId = is_array($questions[$currentIndex] ?? null) && is_scalar($questions[$currentIndex]['id'] ?? null)
    ? trim((string) $questions[$currentIndex]['id'])
    : '';
$answeredCount = 0;
$markedCount = 0;
$reviewStates = \App\Services\Quiz\ExamReviewFlagService::states($questions);
foreach ($questions as $question) {
    $id = is_array($question) && is_scalar($question['id'] ?? null) ? (string) $question['id'] : '';
    if ($id !== '' && array_key_exists($id, $answers) && is_scalar($answers[$id])) {
        $answeredCount++;
    }
}
$questionCount = count($questions);
$answerLabel = $answeredCount === 1 ? 'answer' : 'answers';
for ($index = 0; $index < $questionCount; $index++) {
    if ($reviewStates[$index]['marked']) $markedCount++;
}
$unansweredCount = count($questions) - $answeredCount;
?>
<section class="exam-review-page" aria-labelledby="exam-review-heading">
    <header class="page-header">
        <div>
            <p class="eyebrow">Exam simulation review</p>
            <h1 id="exam-review-heading">Review your answers</h1>
            <p>This is a BoardPrep study simulation. It is separate from the official board examination.</p>
        </div>
        <div class="exam-review-header-actions">
            <?php include __DIR__ . '/_timer.php'; ?>
            <a class="button button-outline" href="/quiz">Return to current question</a>
        </div>
    </header>

    <div class="exam-review-summary" role="status" aria-live="polite">
        <strong><?= count($questions) ?> questions · <?= $answeredCount ?> <?= $answerLabel ?> recorded · <?= $unansweredCount ?> unanswered · <?= $markedCount ?> marked for review.</strong>
        <?php if ($unansweredCount > 0): ?>
            <span><?= $unansweredCount ?> will be marked unanswered if you submit now.</span>
        <?php else: ?>
            <span>All questions have an answer. You can still change any response.</span>
        <?php endif; ?>
        <span>Only answers already stored by the server are counted here. A pending answer is not included.</span>
    </div>

    <ol class="exam-review-list" aria-label="Exam simulation questions">
        <?php foreach ($questions as $index => $question): ?>
            <?php
            $id = is_scalar($question['id'] ?? null) ? (string) $question['id'] : '';
            $answered = $id !== '' && array_key_exists($id, $answers) && is_scalar($answers[$id]);
            $flagState = $reviewStates[$index] ?? ['marked' => false, 'version' => 0];
            $answer = $answered ? trim((string) $answers[$id]) : '';
            $answerLabel = $answer;
            if (preg_match('/^[A-Z]$/i', $answer) === 1) {
                $choiceIndex = ord(strtoupper($answer)) - ord('A');
                $choice = array_values(is_array($question['choices'] ?? null) ? $question['choices'] : [])[$choiceIndex] ?? null;
                if (is_scalar($choice)) {
                    $answerLabel = strtoupper($answer) . '. ' . (string) $choice;
                }
            }
            ?>
            <li class="exam-review-item <?= $answered ? 'is-answered' : 'is-unanswered' ?><?= $flagState['marked'] ? ' is-marked' : '' ?>">
                <div class="exam-review-item-copy">
                    <h2>Question <?= $index + 1 ?></h2>
                    <p><?= htmlspecialchars((string) ($question['question'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                    <p class="exam-answer-status">
                        <strong><?= $answered ? 'Answered' : 'Unanswered' ?></strong>
                        <?php if ($flagState['marked']): ?><span class="exam-review-mark-state">Marked for review</span><?php endif; ?>
                        <?php if ($answered): ?>
                            <span><?= htmlspecialchars($answerLabel, ENT_QUOTES, 'UTF-8') ?></span>
                        <?php endif; ?>
                    </p>
                </div>
                <div class="exam-review-item-actions">
                    <form method="POST" action="/quiz" class="exam-review-jump-form">
                        <input type="hidden" name="action" value="jump">
                        <input type="hidden" name="index" value="<?= $index ?>">
                        <input type="hidden" name="from_question_id" value="<?= htmlspecialchars($currentQuestionId, ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                        <button type="submit" class="button button-outline">Open question <?= $index + 1 ?></button>
                    </form>
                    <form method="POST" action="/quiz" class="exam-review-flag-form">
                        <input type="hidden" name="action" value="review-flag">
                        <input type="hidden" name="index" value="<?= $index ?>">
                        <input type="hidden" name="question_id" value="<?= htmlspecialchars($id, ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="marked" value="<?= $flagState['marked'] ? '0' : '1' ?>">
                        <input type="hidden" name="version" value="<?= (int) $flagState['version'] ?>">
                        <input type="hidden" name="return_to" value="review">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                        <button type="submit" class="button button-outline" aria-pressed="<?= $flagState['marked'] ? 'true' : 'false' ?>">
                            <?= $flagState['marked'] ? 'Remove mark' : 'Mark for review' ?>
                        </button>
                    </form>
                </div>
            </li>
        <?php endforeach; ?>
    </ol>

    <section class="exam-final-submit card" aria-labelledby="exam-submit-heading">
        <h2 id="exam-submit-heading">Ready to submit?</h2>
        <p>Your result will include every question. Unanswered questions count as unanswered and do not earn a point.</p>
        <form method="POST" action="/quiz" data-exam-submit-form>
            <input type="hidden" name="action" value="finish">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <button type="submit">Submit exam simulation</button>
        </form>
        <p id="exam-submit-status" class="exam-submit-status" role="status" aria-live="polite" aria-atomic="true" hidden></p>
    </section>
</section>
<script src="/assets/js/quiz-focus.js" defer></script>
<script src="/assets/js/exam-submit.js" defer></script>
