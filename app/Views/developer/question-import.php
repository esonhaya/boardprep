<h2>

Import Questions

</h2>

<p>

Upload a JSON question file.

Imported questions will be saved as draft.

</p>


<hr>


<form
method="POST"
action="?page=question-import&action=import"
enctype="multipart/form-data"
>


<label>

JSON File:

</label>


<br>


<input
type="file"
name="file"
accept=".json"
required
>


<br><br>


<button type="submit">

📥 Import Questions

</button>


</form>
