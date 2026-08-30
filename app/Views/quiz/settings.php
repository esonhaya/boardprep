<?php $flash = \SessionService::consumeFlash(); ?>

<?php if (is_array($flash) && ($flash['type'] ?? '') === 'error'): ?>
<p role="alert">
<?= htmlspecialchars((string) ($flash['message'] ?? 'Unable to generate that quiz.')) ?>
</p>
<?php endif; ?>

<h1>Create Quiz</h1>

<form class="quiz-settings" method="GET" action="/quiz">


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


<label for="quiz-subject">
Subject:
</label>

<select id="quiz-subject" name="subject">
<option value="English" selected>English</option>
</select>

<br><br>


<label for="quiz-count">
Number of Questions:
</label>


<select id="quiz-count" name="count">

<option value="5">
5 Questions
</option>

<option value="10">
10 Questions
</option>

<option value="20">
20 Questions
</option>

<option value="50">50 Questions</option>
<option value="100">100 Questions</option>
<option value="150">150 Questions</option>

</select>


<br><br>



<label for="quiz-difficulty">
Difficulty:
</label>


<select id="quiz-difficulty" name="difficulty">

<option value="mixed">
Mixed
</option>

<option value="easy">
Easy
</option>

<option value="medium">
Medium
</option>

<option value="hard">
Hard
</option>

</select>


<br><br>



<label for="quiz-mode">
Mode:
</label>


<select id="quiz-mode" name="mode">

<option value="practice">
Practice Mode
</option>

<option value="exam">
Exam Simulation
</option>

<option value="review">
Review Mode
</option>

</select>


<br><br>



<label>

<input
id="quiz-adaptive"
type="checkbox"
name="adaptive"
value="1"
>

Adaptive Learning

</label>

<br>

<small>

Prioritizes questions from your weak topics.

</small>


<br><br>


<button type="submit">
Generate Quiz
</button>


</form>
