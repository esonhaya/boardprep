<h2>Create Quiz</h2>


<form method="GET" action="">


<input
type="hidden"
name="page"
value="quiz"
>


<label>
Number of Questions:
</label>


<select name="count">

<option value="5">
5 Questions
</option>

<option value="10">
10 Questions
</option>

<option value="20">
20 Questions
</option>

</select>


<br><br>



<label>
Difficulty:
</label>


<select name="difficulty">

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



<label>
Mode:
</label>


<select name="mode">

<option value="practice">
Practice Mode
</option>

<option value="exam">
Exam Mode
</option>

<option value="review">
Review Mode
</option>

</select>


<br><br>



<label>

<input
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
