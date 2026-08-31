<?php
$context = $context ?? [];
$breadcrumbs = [];
foreach (['board','subject','domain','topic','concept'] as $key) { if (!empty($context[$key])) { $breadcrumbs[] = is_array($context[$key]) ? ($context[$key]['name'] ?? '') : (string) $context[$key]; } }
?>
<div class="workspace-layout">
<aside class="card workspace-sidebar"><h2>Developer Workspace</h2><p class="form-help"><?= empty($breadcrumbs) ? 'Global Workspace' : htmlspecialchars(implode(' › ', $breadcrumbs)) ?></p><h3>Quick Actions</h3><nav class="workspace-links"><a href="/developer">Repository Health</a><a href="/coverage">Coverage</a><a href="/blueprints">Blueprints</a><a href="/question-editor">Search Questions</a><a href="/question-import">Import Questions</a></nav><h3>Workspace</h3><p>Mode: <strong><?= htmlspecialchars(ucfirst($contentMode ?? 'create')) ?></strong></p></aside>
<section class="workspace-main"><?php require __DIR__ . '/../question/form.php'; ?></section>
</div>
