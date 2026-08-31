<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>BoardPrep Doctor</title>
<style>
body{font-family:system-ui;margin:32px;background:#f5f5f5}
.cards{display:grid;grid-template-columns:repeat(5,1fr);gap:16px}
.card{background:#fff;padding:16px;border-radius:10px;box-shadow:0 1px 4px rgba(0,0,0,.08)}
.value{font-size:2rem;font-weight:bold}
.actions{margin:24px 0}
.button{display:inline-block;padding:10px 18px;background:#2563eb;color:#fff;text-decoration:none;border-radius:8px;font-weight:bold}
table{width:100%;margin-top:24px;border-collapse:collapse;background:#fff}
th,td{padding:10px;border-bottom:1px solid #ddd;text-align:left}
.pass{color:#15803d;font-weight:bold}
.warning{color:#d97706;font-weight:bold}
.fail{color:#dc2626;font-weight:bold}
</style>
</head>
<body>

<h1>BoardPrep Doctor</h1>

<div class="actions">
<a class="button" href="/developer/doctor/run">
▶ Run Doctor
</a>
</div>

<div class="cards">

<div class="card">
<div>Health</div>
<div class="value"><?= $report['health'] ?? '--' ?>%</div>
</div>

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

<div class="table-scroll"><table class="data-table">

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
