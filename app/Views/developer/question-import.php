<h2>

Import Questions

</h2>

<p>

Upload a JSON question file.

Imports use the same validation and duplicate checks as the question editor.
Records without a status are active and quiz eligible.

</p>

<p><strong>Expected format:</strong> a JSON array of question records. Each record uses the same fields as the editor, including <code>id</code>, <code>taxonomy</code>, <code>difficulty</code>, <code>question</code>, <code>options</code>, and <code>explanation</code>.</p>


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

<?php if (isset($result)): ?>
<hr>
<h3>Import result</h3>
<p role="status"><?= ($result['success'] ?? false) ? '✅ Import completed.' : '⚠ Import was not completed.' ?></p>
<?php foreach (($result['errors'] ?? []) as $error): ?><p class="form-error"><strong>Error:</strong> <?= htmlspecialchars((string) $error) ?></p><?php endforeach; ?>
<table>
<tr><th>Outcome</th><th>Count</th></tr>
<tr><td>Accepted</td><td><?= count($result['imported'] ?? []) ?></td></tr>
<tr><td>Updated</td><td><?= count($result['updated'] ?? []) ?></td></tr>
<tr><td>Rejected</td><td><?= count($result['failed'] ?? []) + count($result['skipped'] ?? []) ?></td></tr>
</table>
<?php foreach (array_merge($result['failed'] ?? [], $result['skipped'] ?? []) as $rejection): ?>
<p><strong>Rejected:</strong> <?= htmlspecialchars((string) ($rejection['reason'] ?? 'Unknown reason')) ?></p>
<?php endforeach; ?>
<?php endif; ?>
