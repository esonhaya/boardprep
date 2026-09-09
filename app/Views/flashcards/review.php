<?php
$filters = is_array($filters ?? null) ? $filters : ['include_new' => true];
$card = is_array($card ?? null) ? $card : null;
$selection = is_array($selection ?? null) ? $selection : [];
$mode = in_array(($mode ?? 'normal'), ['normal', 'weakness', 'missed'], true) ? ($mode ?? 'normal') : 'normal';
$revealed = ($revealed ?? false) === true;
$query = ['include_new' => !empty($filters['include_new']) ? 1 : 0];
if ($mode !== 'normal') { $query['mode'] = $mode; }
foreach (['board', 'subject', 'topic'] as $name) {
    if (isset($filters[$name]) && is_string($filters[$name]) && $filters[$name] !== '__invalid__') {
        $query[$name] = $filters[$name];
    }
}
$filterQuery = http_build_query($query);
$reviewUrl = '/flashcards/review?' . $filterQuery;
$taxonomyLabel = static function (string $collection, mixed $id): string {
    $value = is_scalar($id) ? trim((string) $id) : '';
    if ($value === '') {
        return '';
    }
    $record = \App\Services\Shared\TaxonomyStorageService::find($collection, $value);
    $name = is_array($record) && is_scalar($record['name'] ?? null) ? trim((string) $record['name']) : '';
    return $name !== '' ? $name : $value;
};
$boardLabel = $taxonomyLabel('boards', $card['board'] ?? '');
$subjectLabel = $taxonomyLabel('subjects', $card['subject'] ?? '');
$topicLabel = $taxonomyLabel('topics', $card['topic'] ?? '');
?>
<div class="flashcard-review-page">
    <section class="page-header">
        <div><p class="eyebrow">Flashcard review</p><h1><?= $revealed ? 'Check your reasoning' : 'Recall before revealing' ?></h1><p>Use your memory first, then rate how well the card came back.</p></div>
    </section>

    <?php if (($error ?? null) !== null): ?><p class="alert alert-danger" role="alert"><?= htmlspecialchars((string) $error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>

    <?php if ($card === null): ?>
        <div class="empty-state"><h2>No review card is available</h2><p>There are no due cards matching this focus. Include new cards or adjust the filters to continue.</p><p><a class="button button-outline" href="/flashcards?<?= htmlspecialchars($filterQuery, ENT_QUOTES, 'UTF-8') ?>">Adjust flashcards</a> <a class="button button-outline" href="/progress">View progress</a></p></div>
    <?php else: ?>
        <article class="card flashcard-card">
            <div class="flashcard-context"><span><small>Exam</small><?= htmlspecialchars($boardLabel, ENT_QUOTES, 'UTF-8') ?></span><span><small>Subject</small><?= htmlspecialchars($subjectLabel, ENT_QUOTES, 'UTF-8') ?></span><span><small>Topic</small><?= htmlspecialchars($topicLabel, ENT_QUOTES, 'UTF-8') ?></span></div>
            <p class="eyebrow">Prompt</p>
            <h2 class="flashcard-prompt"><?= htmlspecialchars((string) ($card['prompt'] ?? ''), ENT_QUOTES, 'UTF-8') ?></h2>
            <?= \App\Services\Question\StructuredContentService::render($card) ?>

            <?php if (!$revealed): ?>
                <div class="flashcard-reveal-action"><a class="button button-green" href="<?= htmlspecialchars($reviewUrl . '&card_key=' . rawurlencode((string) $card['card_key']) . '&reveal=1', ENT_QUOTES, 'UTF-8') ?>">Reveal answer</a><p class="muted-note">Revealing does not record a review.</p></div>
            <?php else: ?>
                <section class="flashcard-answer" aria-labelledby="flashcard-answer-heading">
                    <p class="eyebrow">Answer</p><h3 id="flashcard-answer-heading"><?= htmlspecialchars((string) ($card['answer'] ?? ''), ENT_QUOTES, 'UTF-8') ?></h3>
                    <div class="flashcard-explanation"><p class="eyebrow">Why</p><p><?= nl2br(htmlspecialchars((string) ($card['explanation'] ?? ''), ENT_QUOTES, 'UTF-8')) ?></p></div>
                    <?php if (!empty($card['study_references'])): ?><div class="flashcard-study-reference"><p class="eyebrow">Study support</p><ul><?php foreach ($card['study_references'] as $reference): ?><li><a href="/study-library"><?= htmlspecialchars((string) ($reference['title'] ?? 'Source-backed study material'), ENT_QUOTES, 'UTF-8') ?></a></li><?php endforeach; ?></ul></div><?php else: ?><p class="muted-note">No linked study material is available for this card yet.</p><?php endif; ?>
                </section>
                <section aria-labelledby="flashcard-rating-heading"><p class="eyebrow">How did it feel?</p><h3 id="flashcard-rating-heading">Rate your recall</h3><div class="flashcard-rating-grid"><?php foreach ([['again', 'Again', 'Forgot it'], ['hard', 'Hard', 'Needed effort'], ['good', 'Good', 'Remembered'], ['easy', 'Easy', 'Very easy']] as $rating): ?><form method="POST" action="/flashcards/review"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) ($csrfToken ?? ''), ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="card_key" value="<?= htmlspecialchars((string) ($card['card_key'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"><?php foreach ($query as $key => $value): ?><input type="hidden" name="<?= htmlspecialchars((string) $key, ENT_QUOTES, 'UTF-8') ?>" value="<?= htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') ?>"><?php endforeach; ?><input type="hidden" name="rating" value="<?= $rating[0] ?>"><button type="submit" class="flashcard-rating flashcard-rating-<?= $rating[0] ?>"><strong><?= $rating[1] ?></strong><span><?= $rating[2] ?></span></button></form><?php endforeach; ?></div></section>
            <?php endif; ?>
        </article>
        <nav class="flashcard-context-navigation" aria-label="Flashcard navigation"><a class="button button-outline" href="/flashcards?<?= htmlspecialchars($filterQuery, ENT_QUOTES, 'UTF-8') ?>">Back to flashcards</a><a class="button button-outline" href="/progress">Progress</a><a class="button button-outline" href="/dashboard">Dashboard</a></nav>
        <?php if (!$revealed && ((int) ($selection['total_count'] ?? 0)) > 0): ?><p class="flashcard-progress" aria-live="polite">Due: <?= (int) ($selection['due_count'] ?? 0) ?> · New: <?= (int) ($selection['new_count'] ?? 0) ?></p><?php endif; ?>
    <?php endif; ?>
</div>
