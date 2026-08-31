<section class="page-header"><div><h1>Import Questions</h1><p>Bring validated question records into the repository with duplicate checks.</p></div><a class="button secondary" href="/question-export">Export questions</a></section>

<p>

Upload a JSON question file.

Imports use the same validation and duplicate checks as the question editor.
Records without a status are active and quiz eligible.

</p>

<p class="card"><strong>Expected format:</strong> a JSON array of question records. Each record uses the same fields as the editor, including <code>id</code>, <code>taxonomy</code>, <code>difficulty</code>, <code>question</code>, <code>options</code>, and <code>explanation</code>.</p>


<hr>


<form class="card"
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


<div class="form-spacer"></div>


<button type="submit">

📥 Import Questions

</button>


</form>

<?php if (isset($result)): ?>
<hr>
<h3>Import result</h3>
<p role="status"><?= ($result['success'] ?? false) ? '✅ Import completed.' : '⚠ Import was not completed.' ?></p>
<?php foreach (($result['errors'] ?? []) as $error): ?><p class="form-error"><strong>Error:</strong> <?= htmlspecialchars((string) $error) ?></p><?php endforeach; ?>
<div class="stats-grid"><article class="card stat-card"><span class="stat-label">Processed</span><strong class="stat-value"><?= count($result['imported'] ?? []) + count($result['updated'] ?? []) + count($result['failed'] ?? []) + count($result['skipped'] ?? []) ?></strong></article><article class="card stat-card"><span class="stat-label">Imported</span><strong class="stat-value"><?= count($result['imported'] ?? []) ?></strong></article><article class="card stat-card"><span class="stat-label">Updated</span><strong class="stat-value"><?= count($result['updated'] ?? []) ?></strong></article><article class="card stat-card"><span class="stat-label">Rejected</span><strong class="stat-value"><?= count($result['failed'] ?? []) + count($result['skipped'] ?? []) ?></strong></article></div>
<?php foreach (array_merge($result['failed'] ?? [], $result['skipped'] ?? []) as $rejection): ?>
<p><strong>Rejected:</strong> <?= htmlspecialchars((string) ($rejection['reason'] ?? 'Unknown reason')) ?></p>
<?php endforeach; ?>
<?php endif; ?>
