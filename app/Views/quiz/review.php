<?php
$questions = is_array($questions ?? null) ? array_values($questions) : [];
$answers = is_array($answers ?? null) ? $answers : [];
$quizSession = \SessionService::get('quiz_session', []);
$quizSession = is_array($quizSession) ? $quizSession : [];
$csrfToken = \App\Services\Shared\CsrfTokenService::token();
$answeredCount = 0;
foreach ($questions as $question) {
    $id = is_array($question) && is_scalar($question['id'] ?? null) ? (string) $question['id'] : '';
    if ($id !== '' && array_key_exists($id, $answers) && is_scalar($answers[$id])) {
        $answeredCount++;
    }
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
        <strong><?= $answeredCount ?> of <?= count($questions) ?> answered.</strong>
        <?php if ($unansweredCount > 0): ?>
            <span><?= $unansweredCount ?> will be marked unanswered if you submit now.</span>
        <?php else: ?>
            <span>All questions have an answer. You can still change any response.</span>
        <?php endif; ?>
    </div>

    <ol class="exam-review-list" aria-label="Exam simulation questions">
        <?php foreach ($questions as $index => $question): ?>
            <?php
            $id = is_scalar($question['id'] ?? null) ? (string) $question['id'] : '';
            $answered = $id !== '' && array_key_exists($id, $answers) && is_scalar($answers[$id]);
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
            <li class="exam-review-item <?= $answered ? 'is-answered' : 'is-unanswered' ?>">
                <div class="exam-review-item-copy">
                    <h2>Question <?= $index + 1 ?></h2>
                    <p><?= htmlspecialchars((string) ($question['question'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                    <p class="exam-answer-status">
                        <strong><?= $answered ? 'Answered' : 'Unanswered' ?></strong>
                        <?php if ($answered): ?>
                            <span><?= htmlspecialchars($answerLabel, ENT_QUOTES, 'UTF-8') ?></span>
                        <?php endif; ?>
                    </p>
                </div>
                <form method="POST" action="/quiz" class="exam-review-jump-form">
                    <input type="hidden" name="action" value="jump">
                    <input type="hidden" name="index" value="<?= $index ?>">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                    <button type="submit" class="button button-outline">Review question <?= $index + 1 ?></button>
                </form>
            </li>
        <?php endforeach; ?>
    </ol>

    <section class="exam-final-submit card" aria-labelledby="exam-submit-heading">
        <h2 id="exam-submit-heading">Ready to submit?</h2>
        <p>Your result will include every question. Unanswered questions count as unanswered and do not earn a point.</p>
        <form method="POST" action="/quiz">
            <input type="hidden" name="action" value="finish">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <button type="submit">Submit exam simulation</button>
        </form>
    </section>
</section>
<script src="/assets/js/quiz-focus.js" defer></script>
