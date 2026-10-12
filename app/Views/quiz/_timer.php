<?php
$timerSession = is_array($quizSession ?? null) ? $quizSession : [];
$remainingSeconds = \App\Services\Quiz\ExamTimerService::remainingSeconds($timerSession);
if ($remainingSeconds !== null):
    $timerCsrfToken = \App\Services\Shared\CsrfTokenService::token();
?>
<div class="exam-countdown" data-remaining-seconds="<?= $remainingSeconds ?>" role="timer" aria-label="Time remaining" aria-atomic="true">
    <span class="exam-countdown-label">Time remaining</span>
    <time class="exam-countdown-value" id="exam-countdown-value"><?= htmlspecialchars(\App\Services\Quiz\ExamTimerService::formatRemaining($remainingSeconds), ENT_QUOTES, 'UTF-8') ?></time>
</div>
<p class="sr-only" id="exam-countdown-announcement" aria-live="polite" aria-atomic="true"></p>
<form id="exam-expiry-check" class="sr-only" method="POST" action="/quiz" hidden>
    <input type="hidden" name="action" value="timer-check">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($timerCsrfToken, ENT_QUOTES, 'UTF-8') ?>">
</form>
<script src="/assets/js/exam-timer.js" defer></script>
<?php endif; ?>
