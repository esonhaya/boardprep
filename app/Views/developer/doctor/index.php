<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>BoardPrep Doctor</title>
<link rel="stylesheet" href="/assets/css/style.css">
</head>
<body class="ui-theme">

<h1>BoardPrep Doctor</h1>

<div class="actions page-actions">
<a class="button" href="/developer/doctor/run">
Run Doctor
</a>
</div>

<div class="stats-grid">

<div class="card">
<div>Health</div>
<div class="value"><?= $report['health'] ?? '--' ?>%</div>
</div>

<?php $uiCheck = null; foreach (($report['checks'] ?? []) as $check) { if (($check['title'] ?? '') === 'UI Contract Engine') { $uiCheck = $check; break; } } ?>
<?php if (is_array($uiCheck)): ?>
<section class="card" aria-labelledby="ui-health-heading"><div class="section-heading"><h2 id="ui-health-heading">UI Health</h2><strong class="health-score"><?= (int) ($uiCheck['score'] ?? 0) ?>%</strong></div><p><?= htmlspecialchars((string) ($uiCheck['summary'] ?? '')) ?></p><div class="record-list"><?php foreach (($uiCheck['details'] ?? []) as $detail): ?><div class="metric-row"><span><?= htmlspecialchars((string) $detail) ?></span></div><?php endforeach; ?></div></section>
<?php endif; ?>

<div class="card">
<div>PASS</div>
<div class="value"><?= $report['summary']['pass'] ?? 0 ?></div>
</div>

<div class="card">
<div>WARNING</div>
<div class="value"><?= $report['summary']['warning'] ?? 0 ?></div>
</div>

<div class="card">
<div>FAIL</div>
<div class="value"><?= $report['summary']['fail'] ?? 0 ?></div>
</div>

<div class="card">
<div>Checks</div>
<div class="value"><?= $report['summary']['checks'] ?? 0 ?></div>
</div>

</div>

<div class="table-scroll ui-dense-table-wrap"><table class="data-table" data-ui-dense-table>

<thead>
<tr>
<th>Check</th>
<th>Status</th>
<th>Summary</th>
</tr>
</thead>

<tbody>

<?php foreach (($report['checks'] ?? []) as $check): ?>

<tr>
<td><?= htmlspecialchars($check['title']) ?></td>
<td class="<?= strtolower($check['status']) ?>">
<?= htmlspecialchars($check['status']) ?>
</td>
<td><?= htmlspecialchars($check['summary']) ?></td>
</tr>

<?php endforeach; ?>

</tbody>

</table></div>

</body>
</html>
