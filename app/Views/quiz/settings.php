<?php $flash = \SessionService::consumeFlash(); ?>

<?php if (is_array($flash) && ($flash['type'] ?? '') === 'error'): ?>
<p role="alert">
<?= htmlspecialchars((string) ($flash['message'] ?? 'Unable to generate that quiz.')) ?>
</p>
<?php endif; ?>

<section class="page-header"><div><h1>Set up your quiz</h1><p>Choose a focused practice session or simulate a full exam using the available question bank.</p></div></section>

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

<input
type="hidden"
name="topic"
value="<?= htmlspecialchars((string) (($settings['topics'][0] ?? '')), ENT_QUOTES, 'UTF-8') ?>"
>


<div class="form-field"><label for="quiz-subject">Subject</label>

<select id="quiz-subject" name="subject">
<option value="English" selected>English</option>
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



<label>

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
