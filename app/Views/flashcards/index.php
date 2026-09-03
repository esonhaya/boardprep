<?php
$filters = is_array($filters ?? null) ? $filters : ['include_new' => true];
$selection = is_array($selection ?? null) ? $selection : [];
$boards = is_array($boards ?? null) ? $boards : [];
$subjects = is_array($subjects ?? null) ? $subjects : [];
$topics = is_array($topics ?? null) ? $topics : [];
$query = ['include_new' => !empty($filters['include_new']) ? 1 : 0];
foreach (['board', 'subject', 'topic'] as $name) {
    if (isset($filters[$name]) && is_string($filters[$name]) && $filters[$name] !== '__invalid__') {
        $query[$name] = $filters[$name];
    }
}
$reviewUrl = '/flashcards/review?' . http_build_query($query);
$totalCount = (int) ($selection['total_count'] ?? 0);
?>
<div class="ui-page-index flashcard-page">
    <section class="page-header">
        <div><p class="eyebrow">Spaced review</p><h1>Flashcards</h1><p>Strengthen memory with short, source-backed reviews from your BoardPrep question bank.</p></div>
        <?php if ($totalCount > 0): ?><a class="button button-green" href="<?= htmlspecialchars($reviewUrl, ENT_QUOTES, 'UTF-8') ?>">Start review</a><?php endif; ?>
    </section>

    <?php if (($error ?? null) !== null): ?><p class="alert alert-danger" role="alert"><?= htmlspecialchars((string) $error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>

    <section class="card flashcard-filter-card" aria-labelledby="flashcard-filter-heading">
        <div class="section-heading"><div><p class="eyebrow">Choose your focus</p><h2 id="flashcard-filter-heading">Review filters</h2></div></div>
        <form class="ui-filter-bar" method="GET" action="/flashcards">
            <div><label for="flashcard-board">Board</label><select id="flashcard-board" name="board"><option value="">All boards</option><?php foreach ($boards as $option): ?><option value="<?= htmlspecialchars((string) ($option['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" <?= ($filters['board'] ?? '') === ($option['id'] ?? '') ? 'selected' : '' ?>><?= htmlspecialchars((string) ($option['name'] ?? $option['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select></div>
            <div><label for="flashcard-subject">Subject</label><select id="flashcard-subject" name="subject"><option value="">All subjects</option><?php foreach ($subjects as $option): ?><option value="<?= htmlspecialchars((string) ($option['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" <?= ($filters['subject'] ?? '') === ($option['id'] ?? '') ? 'selected' : '' ?>><?= htmlspecialchars((string) ($option['name'] ?? $option['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select></div>
            <div><label for="flashcard-topic">Topic</label><select id="flashcard-topic" name="topic"><option value="">All topics</option><?php foreach ($topics as $option): ?><option value="<?= htmlspecialchars((string) ($option['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" <?= ($filters['topic'] ?? '') === ($option['id'] ?? '') ? 'selected' : '' ?>><?= htmlspecialchars((string) ($option['name'] ?? $option['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select></div>
            <div class="flashcard-checkbox"><label for="flashcard-include-new"><input id="flashcard-include-new" type="checkbox" name="include_new" value="1" <?= !empty($filters['include_new']) ? 'checked' : '' ?>> Include new cards</label></div>
            <button type="submit">Apply filters</button>
        </form>
        <p class="muted-note">Due reviews are shown first. New cards are included only when selected.</p>
    </section>

    <section class="ui-metric-grid flashcard-metrics" aria-label="Flashcard availability">
        <article class="card"><span class="stat-label">Due reviews</span><strong class="stat-value"><?= (int) ($selection['due_count'] ?? 0) ?></strong></article>
        <article class="card"><span class="stat-label">New cards</span><strong class="stat-value"><?= (int) ($selection['new_count'] ?? 0) ?></strong></article>
        <article class="card"><span class="stat-label">Available in this focus</span><strong class="stat-value"><?= $totalCount ?></strong></article>
    </section>

    <?php if ($totalCount === 0): ?>
        <div class="empty-state"><h2>No cards ready for this focus</h2><p>Try including new cards or removing a restrictive filter.</p><a href="/study">Return to study</a></div>
    <?php else: ?>
        <section class="card flashcard-start-panel"><p class="eyebrow">Ready when you are</p><h2>Review one card at a time</h2><p>Answer from memory, reveal the explanation, then choose the rating that best matches your recall.</p><a class="button button-green" href="<?= htmlspecialchars($reviewUrl, ENT_QUOTES, 'UTF-8') ?>">Review now</a></section>
    <?php endif; ?>
</div>
