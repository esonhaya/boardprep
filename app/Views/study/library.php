<?php
$materials = is_array($materials ?? null) ? $materials : [];
$exam = (string) ($exam ?? '');
?>
<div class="ui-page-index study-library-page">
<section class="page-header"><div><p class="eyebrow">Study Library<?= $exam !== '' ? ' · ' . htmlspecialchars(strtoupper($exam)) : '' ?></p><h1>Source-backed study materials</h1><p>Review concise BoardPrep notes with visible provenance and exam-specific focus.</p></div></section>
<?php if ($materials === []): ?><div class="empty-state">No published study materials are available for this examination yet.</div><?php else: ?><div class="ui-feature-grid study-material-grid"><?php foreach ($materials as $material): ?><article class="card study-material-card"><p class="eyebrow">Study material · v<?= (int) ($material['version'] ?? 1) ?></p><h2><?= htmlspecialchars((string) ($material['title'] ?? '')) ?></h2><p><?= htmlspecialchars((string) ($material['summary'] ?? '')) ?></p><div class="study-material-notes"><?php foreach (array_slice($material['notes'] ?? [], 0, 4) as $note): ?><section><h3><?= htmlspecialchars((string) ($note['heading'] ?? '')) ?></h3><p><?= htmlspecialchars((string) ($note['body'] ?? '')) ?></p></section><?php endforeach; ?></div><p class="muted-note">Sources: <?php foreach ($material['sources'] ?? [] as $source): ?><a href="<?= htmlspecialchars((string) ($source['url'] ?? '#')) ?>" target="_blank" rel="noopener"><?= htmlspecialchars((string) ($source['title'] ?? 'Verified reference')) ?></a><?php endforeach; ?></p></article><?php endforeach; ?></div><?php endif; ?>
</div>
