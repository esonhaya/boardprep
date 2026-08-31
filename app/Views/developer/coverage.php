<section class="page-header"><div><h1>Coverage Matrix</h1><p>Use these counts to identify subjects and topics with the least authored content.</p></div></section>
<section class="card"><h2>Domains</h2>
<?php if (empty($statistics->questionsPerDomain)): ?><div class="empty-state">No domain coverage data found.</div>
<?php else: ?><div class="record-list"><?php foreach ($statistics->questionsPerDomain as $domain => $count): ?><div class="metric-row"><strong><?= htmlspecialchars((string) $domain) ?></strong><span class="badge badge-info"><?= (int) $count ?> questions</span></div><?php endforeach; ?></div><?php endif; ?></section>
<section class="card"><h2>Topics</h2>
<?php if (empty($statistics->questionsPerTopic)): ?><div class="empty-state">No topic coverage data found.</div>
<?php else: ?><div class="record-list"><?php foreach ($statistics->questionsPerTopic as $topic => $count): ?><div class="metric-row"><strong><?= htmlspecialchars((string) $topic) ?></strong><span class="badge badge-info"><?= (int) $count ?> questions</span></div><?php endforeach; ?></div><?php endif; ?></section>
