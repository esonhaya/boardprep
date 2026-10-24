<section class="page-header"><div><h1>Question Inventory</h1><p>Question Editor · Browse, search and manage your question repository.</p></div><a class="button" href="/question-editor/create">New question</a></section>

<hr>

<?php if (isset($_GET['saved'])): ?><p role="status">✅ Question saved.</p><?php endif; ?>
<?php if (($_GET['notice'] ?? '') === 'archived'): ?><p role="status">🗃️ Question archived.</p><?php elseif (($_GET['notice'] ?? '') === 'restored'): ?><p role="status">♻️ Question restored.</p><?php endif; ?>

<h3>

Repository Summary

</h3>

<p>

<strong>

Canonical questions in repository:

</strong>

<?= (int) ($canonicalQuestionCount ?? 0) ?>

<br>

<strong>Matching current filters:</strong>
<?= (int) ($totalQuestions ?? count($questions ?? [])) ?>

</p>

<p>

Use the filters below to quickly locate questions.

</p>

<hr>

<form class="filter-bar" method="GET">

<input
type="hidden"
name="page"
value="question-editor"
>

<label for="question-search">

Search

</label>

<br>

<input
type="text"
id="question-search"
name="search"
placeholder="Question ID, text, topic or concept..."
value="<?= htmlspecialchars(
    $search ?? ""
) ?>"
class="field-control"
>

<div class="form-spacer"></div>

<label for="question-board">Examination board</label>
<br>
<select id="question-board" name="board" class="field-control">
<option value="">All boards</option>
<?php foreach (($boards ?? []) as $item): ?>
<?php $boardId = (string) ($item['id'] ?? ''); ?>
<?php if ($boardId === '') { continue; } ?>
<option value="<?= htmlspecialchars($boardId, ENT_QUOTES, 'UTF-8') ?>" <?= (($board ?? '') === $boardId) ? 'selected' : '' ?>><?= htmlspecialchars((string) ($item['name'] ?? $boardId), ENT_QUOTES, 'UTF-8') ?></option>
<?php endforeach; ?>
</select>

<div class="form-spacer"></div>

<label for="question-subject">Eligible subject</label>
<br>
<select id="question-subject" name="subject" class="field-control">
<option value="">All eligible subjects</option>
<?php foreach (($subjects ?? []) as $item): ?>
<?php $subjectId = (string) ($item['id'] ?? ''); ?>
<?php if ($subjectId === '') { continue; } ?>
<option value="<?= htmlspecialchars($subjectId, ENT_QUOTES, 'UTF-8') ?>" <?= (($subject ?? '') === $subjectId) ? 'selected' : '' ?>><?= htmlspecialchars((string) ($item['name'] ?? $subjectId), ENT_QUOTES, 'UTF-8') ?></option>
<?php endforeach; ?>
</select>

<div class="form-spacer"></div>

<label for="question-domain">

Domain

</label>

<br>

<select
id="question-domain"
name="domain"
class="field-control"
>

<option value="">

All Domains

</option>

<?php foreach (($domains ?? []) as $item): ?>

<option
value="<?= htmlspecialchars((string) ($item["id"] ?? "")) ?>"
<?= (($domain ?? "") === ($item["id"] ?? ""))
    ? "selected"
    : "" ?>
>

<?= htmlspecialchars((string) ($item["name"] ?? $item["id"] ?? "")) ?>

</option>

<?php endforeach; ?>

</select>

<div class="form-spacer"></div>

<label for="question-difficulty">

Difficulty

</label>

<br>

<select
id="question-difficulty"
name="difficulty"
class="field-control"
>

<option value="">

All Difficulties

</option>

<option
value="easy"
<?= (($difficulty ?? "") === "easy")
    ? "selected"
    : "" ?>
>

Easy

</option>

<option
value="medium"
<?= (($difficulty ?? "") === "medium")
    ? "selected"
    : "" ?>
>

Medium

</option>

<option
value="hard"
<?= (($difficulty ?? "") === "hard")
    ? "selected"
    : "" ?>
>

Hard

</option>

</select>

<div class="form-spacer"></div>

<label for="question-topic">

Topic

</label>

<br>

<select
id="question-topic"
name="topic"
class="field-control"
>

<option value="">

All Topics

</option>

<?php foreach (($topics ?? []) as $item): ?>

<option
value="<?= htmlspecialchars((string) ($item["id"] ?? "")) ?>"
<?= (($topic ?? "") === ($item["id"] ?? ""))
    ? "selected"
    : "" ?>
>

<?= htmlspecialchars((string) ($item["name"] ?? $item["id"] ?? "")) ?>

</option>

<?php endforeach; ?>

</select>

<div class="form-spacer"></div>

<label for="status">Status</label>

<br>

<select name="status" id="status" class="field-control">
<option value="">All Statuses</option>
<?php foreach (['active' => 'Active', 'draft' => 'Draft', 'approved' => 'Approved', 'archived' => 'Archived'] as $value => $label): ?>
<option value="<?= $value ?>" <?= (($status ?? '') === $value) ? 'selected' : '' ?>><?= $label ?></option>
<?php endforeach; ?>
</select>

<div class="form-spacer"></div>

<button type="submit">

Apply Filters

</button>

<a class="button button-outline" href="/question-editor">Reset</a>

</form>

<hr>

<h3>

Questions

</h3>

<?php if (empty($questions)): ?>

<p>

No questions matched your filters.

</p>

<?php else: ?>

<?php foreach (($questions ?? []) as $question): ?>

<article class="card inventory-item">

<p>

<strong>

#<?= htmlspecialchars(
    (string) ($question["id"] ?? "")
) ?>

</strong>

</p>

<p>

<?= htmlspecialchars(
    $question["question"] ?? ""
) ?>

</p>

<p>

<strong>Difficulty:</strong>

<?= htmlspecialchars(
    ucfirst(
        $question["difficulty"] ?? "Unknown"
    )
) ?>

<br>

<strong>Status:</strong>

<?= htmlspecialchars(
    ucfirst(
        $question["status"] ?? "Approved"
    )
) ?>

</p>

<p>

<?php $questionTaxonomy = is_array($question['taxonomy'] ?? null) ? $question['taxonomy'] : []; ?>
<?php if (!empty($questionTaxonomy['subject_id'])): ?><span class="chip"><?= htmlspecialchars((string) ($taxonomyNames['subject'][$questionTaxonomy['subject_id']] ?? $questionTaxonomy['subject_id'])) ?></span><?php endif; ?>
<?php if (!empty($questionTaxonomy['topic_id'])): ?><span class="chip"><?= htmlspecialchars((string) ($taxonomyNames['topic'][$questionTaxonomy['topic_id']] ?? $questionTaxonomy['topic_id'])) ?></span><?php endif; ?>

</p>

<div>

<a href="/question-inspector?id=<?= urlencode((string) $question["id"]) ?>&amp;return=<?= urlencode((string) ($_SERVER['REQUEST_URI'] ?? '/question-editor')) ?>">

Inspect

</a>

|

<a href="/question-editor/edit?id=<?= urlencode((string) $question["id"]) ?>&amp;return=<?= urlencode((string) ($_SERVER['REQUEST_URI'] ?? '/question-editor')) ?>">

Edit

</a>

|

<?php if (
    ($question["status"] ?? "approved")
    !== "archived"
): ?>

<form class="inline-form" method="POST" action="/question-editor/archive?id=<?= urlencode((string) $question["id"]) ?>" onsubmit="return confirm('Archive this question?')">

<input type="hidden" name="_csrf" value="<?= htmlspecialchars((string) ($csrfToken ?? ''), ENT_QUOTES, 'UTF-8') ?>">

<button type="submit">

Archive

</button>

</form>

<?php else: ?>

<form class="inline-form" method="POST" action="/question-editor/restore?id=<?= urlencode((string) $question["id"]) ?>" onsubmit="return confirm('Restore this question?')">

<input type="hidden" name="_csrf" value="<?= htmlspecialchars((string) ($csrfToken ?? ''), ENT_QUOTES, 'UTF-8') ?>">

<button type="submit">

Restore

</button>

</form>

<?php endif; ?>

</article>

<?php endforeach; ?>

<?php endif; ?>

<?php
$pageFilters = array_filter([
    'page' => 'question-editor',
    'search' => (string) ($search ?? ''),
    'domain' => (string) ($domain ?? ''),
    'difficulty' => (string) ($difficulty ?? ''),
    'topic' => (string) ($topic ?? ''),
    'status' => (string) ($status ?? ''),
    'board' => (string) ($board ?? ''),
    'subject' => (string) ($subject ?? ''),
], static fn(string $value): bool => $value !== '');
$previousPageUrl = '?' . http_build_query($pageFilters + ['page_num' => max(1, (int) ($pageNumber ?? 1) - 1)]);
$nextPageUrl = '?' . http_build_query($pageFilters + ['page_num' => min((int) ($pageCount ?? 1), (int) ($pageNumber ?? 1) + 1)]);
?>
<nav class="pagination question-inventory-pagination" aria-label="Question inventory pages">
    <span>Showing <?= count($questions ?? []) ?> of <?= (int) ($totalQuestions ?? 0) ?> matches · <?= (int) ($canonicalQuestionCount ?? 0) ?> canonical questions in repository · Page <?= (int) ($pageNumber ?? 1) ?> of <?= (int) ($pageCount ?? 1) ?></span>
    <div class="page-actions">
        <?php if (($pageNumber ?? 1) > 1): ?><a class="button button-outline" rel="prev" href="<?= htmlspecialchars($previousPageUrl, ENT_QUOTES, 'UTF-8') ?>">Previous page</a><?php endif; ?>
        <?php if (($pageNumber ?? 1) < ($pageCount ?? 1)): ?><a class="button button-outline" rel="next" href="<?= htmlspecialchars($nextPageUrl, ENT_QUOTES, 'UTF-8') ?>">Next page</a><?php endif; ?>
    </div>
</nav>

<hr>

<p class="page-actions">
    <a class="button" href="/question-editor/create">Create New Question</a>
</p>

<hr>

<p>

<strong>Tip:</strong>

Use <em>Save &amp; Next</em> when encoding multiple questions, or
<em>Save Similar</em> when creating variations of an existing question.

</p>
