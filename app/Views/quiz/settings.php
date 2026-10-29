<?php $flash = \SessionService::consumeFlash(); $csrfToken = \App\Services\Shared\CsrfTokenService::token(); ?>

<?php if (is_array($flash) && ($flash['type'] ?? '') === 'error'): ?>
<p class="alert alert-danger" role="alert">
<?= htmlspecialchars((string) ($flash['message'] ?? 'Unable to generate that quiz.')) ?>
</p>
<?php endif; ?>

<div class="practice-setup-page"><section class="page-header"><div><p class="eyebrow">Practice setup</p><h1>Choose practice or an exam simulation</h1><p>Set the examination context first, then tailor the session to your review goal. Candidate variants can be chosen here for this session, or saved in your <a href="/profile">learner profile</a>.</p></div></section>

<?php $settings = is_array($settings ?? null) ? $settings : []; $preferences = is_array($preferences ?? null) ? $preferences : []; ?>
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


<?php $boardContext = is_array($boardContext ?? null) ? $boardContext : null; $subjectContext = ($subjectContext ?? false) === true; ?>
<?php if ($boardContext !== null): ?><div class="quiz-context-summary"><p class="eyebrow">Your selected exam</p><p><strong><?= htmlspecialchars((string) ($boardContext['name'] ?? $boardContext['code'] ?? 'Exam'), ENT_QUOTES, 'UTF-8') ?></strong><?php if ($subjectContext && ($settings['subject'] ?? '*') !== '*'): ?> · <?= htmlspecialchars(\App\Services\Shared\TaxonomyStorageService::label('subjects', $settings['subject']), ENT_QUOTES, 'UTF-8') ?><?php endif; ?></p><a href="/exams?exam=<?= rawurlencode((string) ($boardContext['code'] ?? '')) ?>">Change board or subject</a></div><?php endif; ?>
<div class="form-field" <?= $boardContext !== null ? 'hidden' : '' ?>><label for="quiz-exam">Examination</label><select id="quiz-exam" name="exam" <?= $boardContext !== null ? 'disabled' : '' ?>><?php foreach (($boards ?? []) as $board): ?><option value="<?= htmlspecialchars((string) ($board['code'] ?? '')) ?>" <?= strtoupper((string) ($settings['board'] ?? 'LET')) === strtoupper((string) ($board['code'] ?? '')) ? 'selected' : '' ?>><?= htmlspecialchars((string) ($board['name'] ?? '')) ?></option><?php endforeach; ?></select><?php if ($boardContext !== null): ?><input type="hidden" name="exam" value="<?= htmlspecialchars((string) ($boardContext['code'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"><?php endif; ?></div>

<?php
$selectedBoardKey = strtolower(trim((string) ($settings['board'] ?? '')));
$selectedLetLevel = strtoupper(trim((string) ($settings['let_level'] ?? '')));
$selectedLetSpecialization = trim((string) ($settings['let_specialization'] ?? ''));
$selectedCivilServiceLevel = strtoupper(trim((string) ($settings['civil_service_level'] ?? '')));
$letSpecializations = is_array($letSpecializations ?? null) ? $letSpecializations : [];
?>
<div class="candidate-variant-fields" aria-label="Candidate examination variant">
    <fieldset id="quiz-let-variant-fields" class="form-field candidate-variant-group" data-candidate-variant="let" aria-describedby="quiz-let-variant-help" <?= $selectedBoardKey === 'let' ? '' : 'hidden' ?>>
        <legend>LET candidate variant</legend>
        <p id="quiz-let-variant-help" class="form-help">Choose your candidate level to scope eligible LET questions. A Secondary candidate must also choose a registered specialization. These choices apply to this session and do not change your saved profile.</p>
        <label for="quiz-let-level">Candidate level</label>
        <select id="quiz-let-level" name="let_level" aria-describedby="quiz-let-variant-help">
            <option value="" <?= $selectedLetLevel === '' ? 'selected' : '' ?>>No level selected</option>
            <option value="ELEMENTARY" <?= $selectedLetLevel === 'ELEMENTARY' ? 'selected' : '' ?>>Elementary</option>
            <option value="SECONDARY" <?= $selectedLetLevel === 'SECONDARY' ? 'selected' : '' ?>>Secondary</option>
        </select>
        <label for="quiz-let-specialization">Specialization</label>
        <select id="quiz-let-specialization" name="let_specialization" aria-describedby="quiz-let-variant-help">
            <option value="" <?= $selectedLetSpecialization === '' ? 'selected' : '' ?>>No specialization</option>
            <?php foreach ($letSpecializations as $specialization): if (!is_array($specialization)) continue; $specializationId = (string) ($specialization['id'] ?? ''); if ($specializationId === '') continue; $specializationLevel = strtoupper((string) ($specialization['level'] ?? '')); ?>
                <option value="<?= htmlspecialchars($specializationId, ENT_QUOTES, 'UTF-8') ?>" data-level="<?= htmlspecialchars($specializationLevel, ENT_QUOTES, 'UTF-8') ?>" <?= $selectedLetSpecialization === $specializationId ? 'selected' : '' ?>><?= htmlspecialchars((string) ($specialization['official_name'] ?? $specializationId), ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars(ucfirst(strtolower($specializationLevel)), ENT_QUOTES, 'UTF-8') ?>)</option>
            <?php endforeach; ?>
        </select>
    </fieldset>
    <fieldset id="quiz-cse-variant-fields" class="form-field candidate-variant-group" data-candidate-variant="cse civil-service" aria-describedby="quiz-cse-variant-help" <?= in_array($selectedBoardKey, ['cse', 'civil-service'], true) ? '' : 'hidden' ?>>
        <legend>Civil Service candidate variant</legend>
        <p id="quiz-cse-variant-help" class="form-help">Choose Professional or Subprofessional. The selected level controls eligible subjects and questions. This choice applies to this session and does not change your saved profile.</p>
        <label for="quiz-cse-level">Candidate level</label>
        <select id="quiz-cse-level" name="civil_service_level" aria-describedby="quiz-cse-variant-help">
            <option value="" <?= $selectedCivilServiceLevel === '' ? 'selected' : '' ?>>Choose a level</option>
            <option value="PROFESSIONAL" <?= $selectedCivilServiceLevel === 'PROFESSIONAL' ? 'selected' : '' ?>>Professional</option>
            <option value="SUBPROFESSIONAL" <?= $selectedCivilServiceLevel === 'SUBPROFESSIONAL' ? 'selected' : '' ?>>Subprofessional</option>
        </select>
    </fieldset>
</div>
<noscript><p class="form-help">Without JavaScript, both variant sections are shown. Fill in only the section for the selected board; the server validates the choice.</p><style>.candidate-variant-group[hidden]{display:block!important}</style></noscript>

<div class="form-field" <?= $subjectContext ? 'hidden' : '' ?>><label for="quiz-subject">Subject</label>

<select id="quiz-subject" name="subject" <?= $subjectContext ? 'disabled' : '' ?>>
<option value="*" data-exam="all" <?= ($settings['subject'] ?? '') === '*' ? 'selected' : '' ?>>All available subjects</option>
<?php foreach (($boards ?? []) as $board): foreach (($board['practice_subjects'] ?? []) as $subject): $subjectValue = (string) ($subject['id'] ?? ''); ?><option value="<?= htmlspecialchars($subjectValue, ENT_QUOTES, 'UTF-8') ?>" data-exam="<?= htmlspecialchars((string) ($board['code'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" <?= ($settings['subject'] ?? '') === $subjectValue ? 'selected' : '' ?>><?= htmlspecialchars((string) ($subject['name'] ?? $subjectValue), ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; endforeach; ?>
</select><?php if ($subjectContext): ?><input type="hidden" name="subject" value="<?= htmlspecialchars((string) ($settings['subject'] ?? '*'), ENT_QUOTES, 'UTF-8') ?>"><?php endif; ?></div>


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

</select><small id="quiz-mode-description" class="form-help" role="status" aria-live="polite"><?= ($settings['mode'] ?? 'practice') === 'exam' ? 'Exam simulation withholds answer feedback until submission and lets you review answers first. It is a BoardPrep study tool, separate from the official board examination.' : 'Practice shows answer feedback after each response. Focused practice uses the selected subject; adaptive selection is available for practice.' ?></small><small class="form-help mode-disclosure">No BoardPrep simulation represents an official board examination or passing threshold.</small></div>

<p class="exam-blueprint-focused-note form-help" data-exam-blueprint-focused-note role="status" hidden>A focused subject simulation uses only that subject's eligible questions; it does not represent the full-board subject distribution.</p>
<?php $examBlueprintPreviews = is_array($examBlueprintPreviews ?? null) ? $examBlueprintPreviews : []; $selectedPreviewKey = strtolower((string) ($settings['board'] ?? 'let')); ?>
<?php foreach ($examBlueprintPreviews as $previewBoard => $examBlueprintPreview): ?>
<?php $examBlueprintPreview = is_array($examBlueprintPreview) ? $examBlueprintPreview : []; $previewHeadingId = 'exam-blueprint-preview-heading-' . preg_replace('/[^a-z0-9_-]/i', '', (string) $previewBoard); ?>
<section class="exam-blueprint-preview" data-exam-blueprint-preview data-board="<?= htmlspecialchars((string) $previewBoard, ENT_QUOTES, 'UTF-8') ?>" aria-labelledby="<?= htmlspecialchars($previewHeadingId, ENT_QUOTES, 'UTF-8') ?>" <?= ($settings['mode'] ?? 'practice') === 'exam' && $selectedPreviewKey === strtolower((string) $previewBoard) ? '' : 'hidden' ?>>
    <div class="exam-blueprint-preview-heading">
        <div><p class="eyebrow">Exam simulation setup</p><h2 id="<?= htmlspecialchars($previewHeadingId, ENT_QUOTES, 'UTF-8') ?>">Subject distribution</h2></div>
        <span class="badge badge-info"><?= htmlspecialchars((string) ($examBlueprintPreview['status_label'] ?? 'Blueprint status unavailable'), ENT_QUOTES, 'UTF-8') ?></span>
    </div>
    <?php if (($examBlueprintPreview['lines'] ?? []) !== []): ?>
    <ul class="exam-blueprint-preview-list">
        <?php foreach ($examBlueprintPreview['lines'] as $line): ?>
        <li data-blueprint-weight="<?= htmlspecialchars((string) ($line['percentage'] ?? 0), ENT_QUOTES, 'UTF-8') ?>">
            <span class="exam-blueprint-preview-area"><strong><?= htmlspecialchars((string) ($line['name'] ?? 'Configured area'), ENT_QUOTES, 'UTF-8') ?></strong><span><?= rtrim(rtrim(number_format((float) ($line['percentage'] ?? 0), 2, '.', ''), '0'), '.') ?>%</span></span>
            <small><span data-blueprint-count><?= (int) ($line['question_count'] ?? 0) ?></span> of <span data-blueprint-total><?= (int) ($settings['count'] ?? 0) ?></span> requested questions</small>
            <?php if (($line['subareas'] ?? []) !== []): ?><small class="exam-blueprint-preview-subareas">BoardPrep splits this combined area across its mapped pools: <?php foreach ($line['subareas'] as $index => $subarea): ?><?php if ($index > 0): ?>; <?php endif; ?><span data-blueprint-subarea-weight="<?= htmlspecialchars((string) ($subarea['weight'] ?? 1), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) ($subarea['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?> (<span data-blueprint-subarea-count><?= (int) ($subarea['question_count'] ?? 0) ?></span>)</span><?php endforeach; ?>. No official subarea weights are asserted.</small><?php endif; ?>
        </li>
        <?php endforeach; ?>
    </ul>
    <?php else: ?>
    <p class="exam-blueprint-preview-empty" role="status">No weighted full-board distribution is available for this configuration.</p>
    <?php endif; ?>
    <p class="form-help exam-blueprint-preview-note"><?= htmlspecialchars((string) ($examBlueprintPreview['note'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
</section>
<?php endforeach; ?>

<div id="exam-timer-fields" class="quiz-variant-fields" <?= ($settings['mode'] ?? 'practice') === 'exam' ? '' : 'hidden' ?>>
<div class="form-field"><label for="quiz-timer-mode">Exam simulation timer</label><select id="quiz-timer-mode" name="timer_mode" aria-controls="quiz-time-limit"><option value="untimed" <?= ($settings['timer_mode'] ?? 'untimed') === 'untimed' ? 'selected' : '' ?>>Untimed</option><option value="timed" <?= ($settings['timer_mode'] ?? '') === 'timed' ? 'selected' : '' ?>>Timed</option></select><small class="form-help">Choose a timer for this study session. This does not represent an official examination duration.</small></div>
<div id="quiz-time-limit-field" class="form-field" <?= ($settings['timer_mode'] ?? 'untimed') === 'timed' ? '' : 'hidden' ?>><label for="quiz-time-limit">Time limit in minutes</label><input id="quiz-time-limit" name="time_limit_minutes" type="number" min="1" max="600" step="1" value="<?= htmlspecialchars((string) ($settings['time_limit_minutes_input'] ?? (($settings['time_limit_minutes'] ?? 0) > 0 ? $settings['time_limit_minutes'] : 60)), ENT_QUOTES, 'UTF-8') ?>"><small class="form-help">Use a whole number from 1 to 600. The timer continues when you move between questions or refresh.</small></div>
<p class="small-note" id="timer-mode-help">Untimed sessions ignore the minutes value. Choose Timed to enter a duration. With JavaScript off, both timer choices and the duration stay available.</p>
</div>
<noscript><style>#exam-timer-fields[hidden],#quiz-time-limit-field[hidden]{display:block!important}</style><p class="small-note">The distribution preview updates when you change boards, subjects or question counts. JavaScript is needed for that live preview; exam simulation remains available without it.</p></noscript>



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

In practice mode, prioritizes topics with repeated lower question-level accuracy and favors less recently seen questions when eligible alternatives share the same difficulty. Exam simulations keep their configured blueprint allocation.

</small></div>

<div class="form-actions"><button id="start-session-button" type="submit" class="practice-start-button">
Start session
</button></div>


</form>
</div>

<script src="/assets/js/quiz-settings.js" defer></script>
