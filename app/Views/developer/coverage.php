<section class="page-header"><div><h1>Coverage matrix</h1><p>Use these counts to identify subjects and topics with the least authored content.</p></div></section>
<p>Use these counts to identify subjects and topics with the least authored content.</p>

<hr>

<?php if (empty($statistics->questionsPerDomain)): ?>
<p>No domain coverage data found.</p>
<?php else: ?>
<table border="1" cellpadding="6">
<tr><th>Domain</th><th>Questions</th></tr>
<?php foreach ($statistics->questionsPerDomain as $domain => $count): ?>
<tr>
<td><?= htmlspecialchars((string) $domain) ?></td>
<td><?= (int) $count ?></td>
</tr>
<?php endforeach; ?>
</table>
<?php endif; ?>

<hr>

<h3>Topics</h3>
<?php if (empty($statistics->questionsPerTopic)): ?>
<p>No topic coverage data found.</p>
<?php else: ?>
<table border="1" cellpadding="6">
<tr><th>Topic</th><th>Questions</th></tr>
<?php foreach ($statistics->questionsPerTopic as $topic => $count): ?>
<tr>
<td><?= htmlspecialchars((string) $topic) ?></td>
<td><?= (int) $count ?></td>
</tr>
<?php endforeach; ?>
</table>
<?php endif; ?>
