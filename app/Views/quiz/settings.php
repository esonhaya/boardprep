<?php $flash = \SessionService::consumeFlash(); $csrfToken = \App\Services\Shared\CsrfTokenService::token(); ?>

<?php if (is_array($flash) && ($flash['type'] ?? '') === 'error'): ?>
<p class="alert alert-danger" role="alert">
<?= htmlspecialchars((string) ($flash['message'] ?? 'Unable to generate that quiz.')) ?>
</p>
<?php endif; ?>

<div class="practice-setup-page"><section class="page-header"><div><p class="eyebrow">Practice setup</p><h1>Choose practice or an exam simulation</h1><p>Set the examination context first, then tailor the session to your review goal.</p></div></section>

<?php $settings = is_array($settings ?? null) ? $settings : []; ?>
<?php $activeSession = is_array($activeSession ?? null) ? $activeSession : null; ?>

<?php if ($activeSession !== null): ?>
<aside class="active-session-warning card" aria-labelledby="active-session-heading">
    <div>
        <p class="eyebrow">Session in progress</p>
        <h2 id="active-session-heading">Choose whether to resume or start a new session</h2>
        <p><strong><?= htmlspecialchars((string) ($activeSession['board_label'] ?? $activeSession['board'] ?? 'Board'), ENT_QUOTES, 'UTF-8') ?></strong><?php if (($activeSession['subject_label'] ?? '') !== ''): ?> · <?= htmlspecialchars((string) $activeSession['subject_label'], ENT_QUOTES, 'UTF-8') ?><?php endif; ?> · <?= htmlspecialchars((string) ($activeSession['mode_label'] ?? 'Practice'), ENT_QUOTES, 'UTF-8') ?></p>
        <p><?= (int) ($activeSession['answered_count'] ?? 0) ?> of <?= (int) ($activeSession['question_count'] ?? 0) ?> questions answered. Starting the session below will end this unfinished attempt and discard its answers. Completed attempts in your history will remain unchanged.</p>
    </div>
    <a class="button button-outline" href="/quiz">Resume current session</a>
</aside>
<?php endif; ?>

<form class="quiz-settings card" method="POST" action="/quiz">


<input
type="hidden"
name="page"
value="quiz"
>

<input
type="hidden"
name="action"
value="start"
>

<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

<input
type="hidden"
name="topic"
value="<?= htmlspecialchars((string) (($settings['topics'][0] ?? '')), ENT_QUOTES, 'UTF-8') ?>"
>


<div class="form-field"><label for="quiz-exam">Examination</label><select id="quiz-exam" name="exam"><?php foreach (($boards ?? []) as $board): ?><option value="<?= htmlspecialchars((string) ($board['code'] ?? '')) ?>" <?= strtoupper((string) ($settings['board'] ?? 'LET')) === strtoupper((string) ($board['code'] ?? '')) ? 'selected' : '' ?>><?= htmlspecialchars((string) ($board['name'] ?? '')) ?></option><?php endforeach; ?></select></div>

<div id="let-variant-fields" class="quiz-variant-fields" <?= strtolower((string) ($settings['board'] ?? '')) === 'let' ? '' : 'hidden' ?>>
<div class="form-field"><label for="let-level">LET candidate level</label><select id="let-level" name="let_level"><option value="">Legacy board-wide practice</option><option value="ELEMENTARY" <?= strtoupper((string) ($settings['let_level'] ?? '')) === 'ELEMENTARY' ? 'selected' : '' ?>>Elementary</option><option value="SECONDARY" <?= strtoupper((string) ($settings['let_level'] ?? '')) === 'SECONDARY' ? 'selected' : '' ?>>Secondary</option></select><small class="form-help">Choose a level for variant-safe blueprint filtering. Secondary requires a specialization.</small></div>
<div class="form-field"><label for="let-specialization">LET specialization</label><select id="let-specialization" name="let_specialization"><option value="">No specialization</option><?php foreach (($letSpecializations ?? []) as $specialization): ?><option value="<?= htmlspecialchars((string) ($specialization['id'] ?? '')) ?>" data-level="<?= htmlspecialchars((string) ($specialization['level'] ?? '')) ?>" <?= (string) (($settings['let_specialization'] ?? '')) === (string) ($specialization['id'] ?? '') ? 'selected' : '' ?>><?= htmlspecialchars((string) ($specialization['official_name'] ?? $specialization['id'] ?? '')) ?> (<?= htmlspecialchars((string) ($specialization['level'] ?? '')) ?>)</option><?php endforeach; ?></select><small class="form-help">Historical labels resolve to stable identities; ambiguous MAPEH/BTLEd qualification labels are not silently assigned.</small></div>
</div>

<div id="civil-service-fields" class="quiz-variant-fields" <?= in_array(strtolower((string) ($settings['board'] ?? '')), ['cse', 'civil-service'], true) ? '' : 'hidden' ?>>
<div class="form-field"><label for="civil-service-level">Civil Service examination level</label><select id="civil-service-level" name="civil_service_level"><option value="">Select level</option><option value="PROFESSIONAL" <?= strtoupper((string) ($settings['civil_service_level'] ?? '')) === 'PROFESSIONAL' ? 'selected' : '' ?>>Professional</option><option value="SUBPROFESSIONAL" <?= strtoupper((string) ($settings['civil_service_level'] ?? '')) === 'SUBPROFESSIONAL' ? 'selected' : '' ?>>Subprofessional</option></select><small class="form-help">Subprofessional includes Clerical Ability; Professional selections exclude it.</small></div>
</div>

<div class="form-field"><label for="quiz-subject">Subject</label>

<select id="quiz-subject" name="subject">
<option value="*" data-exam="all" <?= ($settings['subject'] ?? '') === '*' ? 'selected' : '' ?>>All available subjects</option>
<?php foreach (($boards ?? []) as $board): foreach (($board['practice_subjects'] ?? []) as $subject): $subjectValue = (string) ($subject['id'] ?? ''); ?><option value="<?= htmlspecialchars($subjectValue, ENT_QUOTES, 'UTF-8') ?>" data-exam="<?= htmlspecialchars((string) ($board['code'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" <?= ($settings['subject'] ?? '') === $subjectValue ? 'selected' : '' ?>><?= htmlspecialchars((string) ($subject['name'] ?? $subjectValue), ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; endforeach; ?>
</select></div>


<div class="form-field"><label for="quiz-count">Number of questions</label>


<select id="quiz-count" name="count">

<option value="5" <?= ($settings['count'] ?? 10) === 5 ? 'selected' : '' ?>>
5 Questions
</option>

<option value="10" <?= ($settings['count'] ?? 10) === 10 ? 'selected' : '' ?>>
10 Questions
</option>

<option value="20" <?= ($settings['count'] ?? 10) === 20 ? 'selected' : '' ?>>
20 Questions
</option>

<option value="150" <?= ($settings['count'] ?? 10) === 150 ? 'selected' : '' ?>>150 Questions (Exam)</option>

</select></div>



<div class="form-field"><label for="quiz-difficulty">Difficulty</label>


<select id="quiz-difficulty" name="difficulty">

<option value="mixed" <?= ($settings['difficulty'] ?? 'mixed') === 'mixed' ? 'selected' : '' ?>>
Mixed
</option>

<option value="easy" <?= ($settings['difficulty'] ?? '') === 'easy' ? 'selected' : '' ?>>
Easy
</option>

<option value="medium" <?= ($settings['difficulty'] ?? '') === 'medium' ? 'selected' : '' ?>>
Medium
</option>

<option value="hard" <?= ($settings['difficulty'] ?? '') === 'hard' ? 'selected' : '' ?>>
Hard
</option>

</select></div>



<div class="form-field"><label for="quiz-mode">Session type</label>


<select id="quiz-mode" name="mode">

<option value="practice" <?= ($settings['mode'] ?? 'practice') === 'practice' ? 'selected' : '' ?>>
Practice Mode
</option>

<option value="exam" <?= ($settings['mode'] ?? '') === 'exam' ? 'selected' : '' ?>>
Exam simulation
</option>

<option value="review" <?= ($settings['mode'] ?? '') === 'review' ? 'selected' : '' ?>>
Review Mode
</option>

</select><small class="form-help mode-disclosure">Exam simulation withholds answer feedback until submission. It is a BoardPrep study tool, separate from the official board examination.</small></div>

<div id="exam-timer-fields" class="quiz-variant-fields" <?= ($settings['mode'] ?? 'practice') === 'exam' ? '' : 'hidden' ?>>
<div class="form-field"><label for="quiz-timer-mode">Exam simulation timer</label><select id="quiz-timer-mode" name="timer_mode" aria-controls="quiz-time-limit"><option value="untimed" <?= ($settings['timer_mode'] ?? 'untimed') === 'untimed' ? 'selected' : '' ?>>Untimed</option><option value="timed" <?= ($settings['timer_mode'] ?? '') === 'timed' ? 'selected' : '' ?>>Timed</option></select><small class="form-help">Choose a timer for this study session. This does not represent an official examination duration.</small></div>
<div class="form-field"><label for="quiz-time-limit">Time limit in minutes</label><input id="quiz-time-limit" name="time_limit_minutes" type="number" min="1" max="600" step="1" value="<?= (int) ($settings['time_limit_minutes'] ?? 0) > 0 ? (int) $settings['time_limit_minutes'] : 60 ?>"><small class="form-help">Use a whole number from 1 to 600. The timer continues when you move between questions or refresh.</small></div>
</div>



<div class="form-field adaptive-option"><label for="quiz-adaptive">

<input
id="quiz-adaptive"
type="checkbox"
name="adaptive"
value="1"
<?= !empty($settings['adaptive']) ? 'checked' : '' ?>
>

Adaptive Learning

</label>

<small class="form-help">

Prioritizes questions from your weak topics.

</small></div>

<div class="form-actions"><button id="start-session-button" type="submit" class="practice-start-button">
Start session
</button></div>


</form>
</div>

<script src="/assets/js/quiz-settings.js" defer></script>
