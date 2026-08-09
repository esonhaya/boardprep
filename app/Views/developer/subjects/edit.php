<h1>
Edit Subject
</h1>

<p>
Update the reusable subject definition.
</p>

<hr>

<form
    method="POST"
    action="/subject/update?id=<?= urlencode($subject["id"] ?? "") ?>"
>

<label>
Name
</label>

<br>

<input
    type="text"
    name="name"
    value="<?= htmlspecialchars($subject["name"] ?? "") ?>"
    required
    maxlength="100"
>

<br><br>

<label>
Description
</label>

<br>

<textarea
    name="description"
    rows="5"
><?= htmlspecialchars($subject["description"] ?? "") ?></textarea>

<br><br>

<button type="submit">
Save Changes
</button>

<a href="/subjects">
Cancel
</a>

</form>
