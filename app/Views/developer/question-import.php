<h2>

Import Questions

</h2>

<p>

Upload a JSON question file.

Imports use the same validation and duplicate checks as the question editor.
Records without a status are active and quiz eligible.

</p>


<hr>


<form
method="POST"
action="/question-import/import"
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
