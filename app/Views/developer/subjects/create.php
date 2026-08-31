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

<label>

Name

</label>

<br>

<input
type="text"
name="name"
required
maxlength="100"
>

<div class="form-spacer"></div>

<label>

Description

</label>

<br>

<textarea
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
