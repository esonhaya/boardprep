<?php $flash = \SessionService::consumeFlash(); $csrfToken = \App\Services\Shared\CsrfTokenService::token(); ?>

<?php if (is_array($flash) && ($flash['type'] ?? '') === 'error'): ?>
<p role="alert">
<?= htmlspecialchars((string) ($flash['message'] ?? 'Unable to generate that quiz.')) ?>
</p>
<?php endif; ?>

<section class="page-header"><div><p class="eyebrow">Practice setup</p><h1>Choose how you want to study</h1><p>Set the examination context first, then tailor the session to your review goal.</p></div></section>

<?php $settings = is_array($settings ?? null) ? $settings : []; ?>

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

<div id="let-variant-fields" <?= strtolower((string) ($settings['board'] ?? '')) === 'let' ? '' : 'hidden' ?>>
<div class="form-field"><label for="let-level">LET candidate level</label><select id="let-level" name="let_level"><option value="">Legacy board-wide practice</option><option value="ELEMENTARY" <?= strtoupper((string) ($settings['let_level'] ?? '')) === 'ELEMENTARY' ? 'selected' : '' ?>>Elementary</option><option value="SECONDARY" <?= strtoupper((string) ($settings['let_level'] ?? '')) === 'SECONDARY' ? 'selected' : '' ?>>Secondary</option></select><small class="form-help">Choose a level for variant-safe blueprint filtering. Secondary requires a specialization.</small></div>
<div class="form-field"><label for="let-specialization">LET specialization</label><select id="let-specialization" name="let_specialization"><option value="">No specialization</option><?php foreach (($letSpecializations ?? []) as $specialization): ?><option value="<?= htmlspecialchars((string) ($specialization['id'] ?? '')) ?>" data-level="<?= htmlspecialchars((string) ($specialization['level'] ?? '')) ?>" <?= (string) (($settings['let_specialization'] ?? '')) === (string) ($specialization['id'] ?? '') ? 'selected' : '' ?>><?= htmlspecialchars((string) ($specialization['official_name'] ?? $specialization['id'] ?? '')) ?> (<?= htmlspecialchars((string) ($specialization['level'] ?? '')) ?>)</option><?php endforeach; ?></select><small class="form-help">Historical labels resolve to stable identities; ambiguous MAPEH/BTLEd qualification labels are not silently assigned.</small></div>
</div>

<div id="civil-service-fields" <?= in_array(strtolower((string) ($settings['board'] ?? '')), ['cse', 'civil-service'], true) ? '' : 'hidden' ?>>
<div class="form-field"><label for="civil-service-level">Civil Service examination level</label><select id="civil-service-level" name="civil_service_level"><option value="">Select level</option><option value="PROFESSIONAL" <?= strtoupper((string) ($settings['civil_service_level'] ?? '')) === 'PROFESSIONAL' ? 'selected' : '' ?>>Professional</option><option value="SUBPROFESSIONAL" <?= strtoupper((string) ($settings['civil_service_level'] ?? '')) === 'SUBPROFESSIONAL' ? 'selected' : '' ?>>Subprofessional</option></select><small class="form-help">Subprofessional includes Clerical Ability; Professional selections exclude it.</small></div>
</div>

<div class="form-field"><label for="quiz-subject">Subject</label>

<select id="quiz-subject" name="subject">
<option value="English" data-exam="default" <?= ($settings['subject'] ?? 'English') === 'English' ? 'selected' : '' ?>>English</option>
<option value="general-information" data-exam="cse" <?= ($settings['subject'] ?? '') === 'general-information' ? 'selected' : '' ?>>General Information</option>
<option value="verbal-ability" data-exam="cse" <?= ($settings['subject'] ?? '') === 'verbal-ability' ? 'selected' : '' ?>>Verbal Ability</option>
<option value="analytical-logical" data-exam="cse" <?= ($settings['subject'] ?? '') === 'analytical-logical' ? 'selected' : '' ?>>Analytical Ability</option>
<option value="numerical-ability" data-exam="cse" <?= ($settings['subject'] ?? '') === 'numerical-ability' ? 'selected' : '' ?>>Numerical Ability</option>
<option value="clerical-ability" data-exam="cse" data-level="SUBPROFESSIONAL" <?= ($settings['subject'] ?? '') === 'clerical-ability' ? 'selected' : '' ?>>Clerical Ability</option>
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



<div class="form-field"><label for="quiz-mode">Mode</label>


<select id="quiz-mode" name="mode">

<option value="practice" <?= ($settings['mode'] ?? 'practice') === 'practice' ? 'selected' : '' ?>>
Practice Mode
</option>

<option value="exam" <?= ($settings['mode'] ?? '') === 'exam' ? 'selected' : '' ?>>
Exam Simulation
</option>

<option value="review" <?= ($settings['mode'] ?? '') === 'review' ? 'selected' : '' ?>>
Review Mode
</option>

</select></div>

<small class="form-help">Exam mode hides answer feedback until you finish. It does not enforce a timer or reproduce every official board-exam format.</small>



<label for="quiz-adaptive">

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

</small>


<br>


<button type="submit">
Start quiz
</button>


</form>

<script src="/assets/js/quiz-settings.js" defer></script>
