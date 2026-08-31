<h1>
Create Subject
</h1>

<p>
Create a reusable subject that can be attached to one or more boards.
</p>

<hr>

<form
method="POST"
action="/subject/save"
>

<label for="subject-name">

Name

</label>

<br>

<input
type="text"
id="subject-name"
name="name"
required
maxlength="100"
>

<div class="form-spacer"></div>

<label for="subject-description">

Description

</label>

<br>

<textarea
 id="subject-description"
 name="description"
rows="5"
></textarea>

<div class="form-spacer"></div>

<button type="submit">

Create Subject

</button>

<a href="/subjects">

Cancel

</a>

</form>
