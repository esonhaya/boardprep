<?php
$materials = is_array($materials ?? null) ? $materials : [];
$exam = (string) ($exam ?? '');
$materialRequested = ($materialRequested ?? false) === true;
?>
<div class="ui-page-index study-library-page">
<nav class="breadcrumbs" aria-label="Breadcrumb"><ol><li><a href="/exams">Examinations</a></li><li aria-current="page">Study library</li></ol></nav>
<section class="page-header"><div><p class="eyebrow">Study Library<?= $exam !== '' ? ' · ' . htmlspecialchars(strtoupper($exam)) : '' ?></p><h1>Source-backed study materials</h1><p>Review concise BoardPrep notes with visible provenance and exam-specific focus.</p></div><div class="page-actions"><a class="button button-green" href="/quiz">Practice now</a><a class="button button-outline" href="/flashcards">Review flashcards</a></div></section>
<?php if ($materials === []): ?>
    <div class="empty-state">
        <h2><?= $materialRequested ? 'Study material unavailable' : 'No study materials are available yet' ?></h2>
        <p><?= $materialRequested ? 'This linked study material is no longer available.' : 'There are no published notes for this examination. You can choose another exam or start practice while materials are prepared.' ?></p>
        <p><a class="button button-outline" href="/study-library">Browse all study materials</a> <a class="button button-outline" href="/exams">Choose an exam</a> <a class="button button-outline" href="/dashboard">Dashboard</a></p>
    </div>
<?php else: ?>
    <div class="ui-feature-grid study-material-grid">
        <?php foreach ($materials as $material): ?>
            <article id="study-material-<?= htmlspecialchars((string) ($material['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" class="card study-material-card">
                <p class="eyebrow">Study material · v<?= (int) ($material['version'] ?? 1) ?></p>
                <h2><?= htmlspecialchars((string) ($material['title'] ?? '')) ?></h2>
                <p><?= htmlspecialchars((string) ($material['summary'] ?? '')) ?></p>
                <?php if (is_array($material['priority'] ?? null)): ?>
                    <p><strong>Priority: <?= htmlspecialchars((string) ($material['priority']['level'] ?? 'LOW')) ?></strong></p>
                    <ul><?php foreach (array_slice($material['priority']['reasons'] ?? [], 0, 2) as $reason): ?><li><?= htmlspecialchars((string) $reason) ?></li><?php endforeach; ?></ul>
                <?php endif; ?>
                <div class="study-material-notes">
                    <?php foreach (array_slice((array) ($material['notes'] ?? []), 0, 4) as $note): ?>
                        <?php
                        if (!is_array($note)) {
                            continue;
                        }
                        $noteHeading = $note['heading'] ?? $note[0] ?? '';
                        $noteBody = $note['body'] ?? $note[1] ?? '';
                        if (!is_scalar($noteHeading) || !is_scalar($noteBody)) {
                            continue;
                        }
                        $noteHeading = trim((string) $noteHeading);
                        $noteBody = trim((string) $noteBody);
                        if ($noteHeading === '' && $noteBody === '') {
                            continue;
                        }
                        ?>
                        <section>
                            <?php if ($noteHeading !== ''): ?><h3><?= htmlspecialchars($noteHeading, ENT_QUOTES, 'UTF-8') ?></h3><?php endif; ?>
                            <?php if ($noteBody !== ''): ?><p><?= htmlspecialchars($noteBody, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
                        </section>
                    <?php endforeach; ?>
                </div>
                <?php if (!empty($material['sources'])): ?>
                    <section class="study-material-sources" aria-label="Sources">
                        <h3>Sources</h3>
                        <ul><?php foreach ($material['sources'] as $source): ?><li><a href="<?= htmlspecialchars((string) ($source['url'] ?? '#'), ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener"><?= htmlspecialchars((string) ($source['title'] ?? 'Verified reference'), ENT_QUOTES, 'UTF-8') ?></a></li><?php endforeach; ?></ul>
                    </section>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
</div>
