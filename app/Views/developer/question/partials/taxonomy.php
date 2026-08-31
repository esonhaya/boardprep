<?php

$boards = $boards ?? [];
$boardSubjects = $boardSubjects ?? [];
$subjects = $subjects ?? [];
$domains = $domains ?? [];
$topics = $topics ?? [];
$concepts = $concepts ?? [];
$context = $context ?? [];
$taxonomy = is_array($question['taxonomy'] ?? null) ? $question['taxonomy'] : [];

$fieldValue = static function (string $key) use ($context, $taxonomy): string {
    return trim((string) ($context[$key] ?? $taxonomy[$key . '_id'] ?? ''));
};

$boardValue = $fieldValue('board');
$subjectValue = $fieldValue('subject');
$domainValue = $fieldValue('domain');
$topicValue = $fieldValue('topic');
$conceptValue = $fieldValue('concept');
?>

<?php if (!empty($context['board'])): ?>
<input type="hidden" name="board" value="<?= htmlspecialchars($boardValue) ?>">
<?php else: ?>
<label for="board">Board</label><br>
<select name="board" id="board" data-selected="<?= htmlspecialchars($boardValue) ?>" required>
<option value="">Select Board</option>
<?php foreach ($boards as $board): ?>
<option value="<?= htmlspecialchars((string) ($board['id'] ?? '')) ?>" <?= $boardValue === ($board['id'] ?? '') ? 'selected' : '' ?>>
<?= htmlspecialchars((string) ($board['name'] ?? $board['id'] ?? '')) ?>
</option>
<?php endforeach; ?>
</select><div class="form-spacer"></div>
<?php endif; ?>

<?php if (!empty($context['subject'])): ?>
<input type="hidden" name="subject" value="<?= htmlspecialchars($subjectValue) ?>">
<?php else: ?>
<label for="subject">Subject</label><br>
<select name="subject" id="subject" data-selected="<?= htmlspecialchars($subjectValue) ?>" required>
<option value="">Select Subject</option>
<?php foreach ($subjects as $subject): ?>
<option value="<?= htmlspecialchars((string) ($subject['id'] ?? '')) ?>" <?= $subjectValue === ($subject['id'] ?? '') ? 'selected' : '' ?>>
<?= htmlspecialchars((string) ($subject['name'] ?? $subject['id'] ?? '')) ?>
</option>
<?php endforeach; ?>
</select><div class="form-spacer"></div>
<?php endif; ?>

<?php if (!empty($context['domain'])): ?>
<input type="hidden" name="domain" value="<?= htmlspecialchars($domainValue) ?>">
<?php else: ?>
<label for="domain">Domain</label><br>
<select name="domain" id="domain" data-selected="<?= htmlspecialchars($domainValue) ?>" required>
<option value="">Select Domain</option>
<?php foreach ($domains as $domain): ?>
<option value="<?= htmlspecialchars((string) ($domain['id'] ?? '')) ?>" <?= $domainValue === ($domain['id'] ?? '') ? 'selected' : '' ?>>
<?= htmlspecialchars((string) ($domain['name'] ?? $domain['id'] ?? '')) ?>
</option>
<?php endforeach; ?>
</select><div class="form-spacer"></div>
<?php endif; ?>

<?php if (!empty($context['topic'])): ?>
<input type="hidden" name="topic" value="<?= htmlspecialchars($topicValue) ?>">
<?php else: ?>
<label for="topic">Topic</label><br>
<select name="topic" id="topic" data-selected="<?= htmlspecialchars($topicValue) ?>" required>
<option value="">Select Topic</option>
<?php foreach ($topics as $topic): ?>
<option value="<?= htmlspecialchars((string) ($topic['id'] ?? '')) ?>" <?= $topicValue === ($topic['id'] ?? '') ? 'selected' : '' ?>>
<?= htmlspecialchars((string) ($topic['name'] ?? $topic['id'] ?? '')) ?>
</option>
<?php endforeach; ?>
</select><div class="form-spacer"></div>
<?php endif; ?>

<?php if (!empty($context['concept'])): ?>
<input type="hidden" name="concept" value="<?= htmlspecialchars($conceptValue) ?>">
<?php else: ?>
<label for="concept">Concept</label><br>
<select name="concept" id="concept" data-selected="<?= htmlspecialchars($conceptValue) ?>" required>
<option value="">Select Concept</option>
<?php foreach ($concepts as $concept): ?>
<option value="<?= htmlspecialchars((string) ($concept['id'] ?? '')) ?>" <?= $conceptValue === ($concept['id'] ?? '') ? 'selected' : '' ?>>
<?= htmlspecialchars((string) ($concept['name'] ?? $concept['id'] ?? '')) ?>
</option>
<?php endforeach; ?>
</select><div class="form-spacer"></div>
<?php endif; ?>

<label for="difficulty">Difficulty</label><br>
<select name="difficulty" id="difficulty" required>
<?php foreach (['easy' => 'Easy', 'medium' => 'Medium', 'hard' => 'Hard'] as $value => $label): ?>
<option value="<?= $value ?>" <?= (($question['difficulty'] ?? '') === $value) ? 'selected' : '' ?>><?= $label ?></option>
<?php endforeach; ?>
</select><div class="form-spacer"></div>

<label for="status">Status</label><br>
<select name="status" id="status" required>
<?php foreach (['active' => 'Active', 'draft' => 'Draft', 'approved' => 'Approved', 'archived' => 'Archived'] as $value => $label): ?>
<option value="<?= $value ?>" <?= (($question['status'] ?? 'active') === $value) ? 'selected' : '' ?>><?= $label ?></option>
<?php endforeach; ?>
</select><div class="form-spacer"></div>
