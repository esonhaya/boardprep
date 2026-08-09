<h2>Blueprint Manager</h2>
<p>Manage blueprint versions and exam templates.</p>
<hr>
<p><strong>Total Blueprints:</strong> <?= count($blueprints ?? []) ?></p>
<p><a href="/blueprints/create"><button type="button">➕ Create Blueprint</button></a></p>
<hr>
<?php if (empty($blueprints)): ?>
<p>No blueprints found.</p>
<?php else: ?>
<?php foreach ($blueprints as $blueprint): ?>
<div style="border:1px solid #d1d5db;border-radius:8px;padding:16px;margin-bottom:16px;background:white;">
<h3><?= htmlspecialchars((string) ($blueprint['name'] ?? '')) ?></h3>
<p><strong>ID:</strong> <?= htmlspecialchars((string) ($blueprint['id'] ?? '')) ?></p>
<p><strong>Board:</strong> <?= htmlspecialchars((string) ($blueprint['board'] ?? '')) ?></p>
<p><strong>Subject:</strong> <?= htmlspecialchars((string) ($blueprint['subject'] ?? '')) ?></p>
<p><strong>Version:</strong> <?= htmlspecialchars((string) ($blueprint['version'] ?? '')) ?></p>
</div>
<?php endforeach; ?>
<?php endif; ?>
<hr>
<p><a href="/developer">🏠 Back to Developer Dashboard</a></p>
