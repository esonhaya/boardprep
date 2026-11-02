<?php

declare(strict_types=1);

$e = static fn(mixed $value): string => htmlspecialchars(is_scalar($value) ? (string) $value : '', ENT_QUOTES, 'UTF-8');
$data = is_array($readiness ?? null) ? $readiness : [];
$technical = is_array($data['technical_gate'] ?? null) ? $data['technical_gate'] : [];
$academic = is_array($data['academic_approval'] ?? null) ? $data['academic_approval'] : [];
$browser = is_array($data['browser_acceptance'] ?? null) ? $data['browser_acceptance'] : [];
$backup = is_array($data['encrypted_backup_acceptance'] ?? null) ? $data['encrypted_backup_acceptance'] : [];
$hosted = is_array($data['hosted_configuration_acceptance'] ?? null) ? $data['hosted_configuration_acceptance'] : [];
$reports = is_array($data['learner_question_reports'] ?? null) ? $data['learner_question_reports'] : [];
$counts = is_array($technical['priority_counts'] ?? null) ? $technical['priority_counts'] : [];
$state = strtoupper((string) ($technical['status'] ?? 'UNVERIFIED'));
$badgeClass = match ($state) { 'CURRENT_PASS' => 'badge-success', 'FAIL' => 'badge-danger', default => 'badge-warning' };
$artifactPath = is_string($technical['artifact'] ?? null) && $technical['artifact'] !== ''
    ? '<code>' . $e($technical['artifact']) . '</code>'
    : 'UNAVAILABLE';
?>

<header class="page-header">
    <div>
        <p class="eyebrow">Read-only operational evidence</p>
        <h1>Readiness Overview</h1>
        <p>Technical checks, browser execution, encrypted backup, hosted configuration, academic review, and public-release authorization are separate states. This page reads saved evidence and does not run diagnostics or grant access.</p>
    </div>
    <nav class="related-navigation" aria-label="Readiness evidence links">
        <?php foreach ((array) ($data['links'] ?? []) as $link): ?>
            <?php if (!is_array($link) || !str_starts_with((string) ($link['href'] ?? ''), '/developer/')) continue; ?>
            <a class="button button-outline" href="<?= $e($link['href']) ?>"><?= $e($link['label'] ?? 'Open evidence') ?></a>
        <?php endforeach; ?>
    </nav>
</header>

<section class="card" aria-labelledby="readiness-gates-heading">
    <h2 id="readiness-gates-heading">Independent gates</h2>
    <div class="stats-grid">
        <article class="card"><h3>Technical gate</h3><p><span class="badge <?= $e($badgeClass) ?>"><?= $e($state) ?></span></p><p>Recorded release gate: <strong><?= $e($technical['release_gate'] ?? 'UNKNOWN') ?></strong></p><p>Health: <?= $e($technical['health_status'] ?? 'UNKNOWN') ?></p></article>
        <article class="card"><h3>Browser acceptance</h3><p><span class="badge badge-warning"><?= $e($browser['status'] ?? 'NOT_EXECUTED') ?></span></p><p>Browser PASS requires an explicit supported-runner result in Full Doctor evidence.</p></article>
        <article class="card"><h3>Encrypted backup acceptance</h3><p><span class="badge badge-warning"><?= $e($backup['status'] ?? 'NOT_EXECUTED') ?></span></p><p><?= $e($backup['failure_reason'] ?? 'No current sodium round-trip evidence is registered.') ?></p><p>Provenance: <?= $e($backup['freshness'] ?? 'NO_EVIDENCE') ?> · environment <?= $e($backup['environment'] ?? 'Not recorded') ?> · commit <?= $e($backup['tested_sha'] ?? 'Not recorded') ?> · run <?= $e($backup['timestamp'] ?? 'Not recorded') ?> · command <?= $e($backup['command'] ?? 'Not recorded') ?> · exit <?= $e($backup['exit_code'] ?? 'Not recorded') ?> · artifact <?= $e($backup['artifact'] ?? 'Not recorded') ?> · SHA-256 <?= $e($backup['artifact_sha256'] ?? 'Not recorded') ?>.</p><p class="muted"><?= $e($backup['source'] ?? 'No validated acceptance artifact is registered.') ?></p></article>
        <article class="card"><h3>Hosted configuration acceptance</h3><p><span class="badge badge-warning"><?= $e($hosted['status'] ?? 'NOT_EXECUTED') ?></span></p><p><?= $e($hosted['failure_reason'] ?? 'No authorized hosted configuration evidence is registered.') ?></p><p>Provenance: <?= $e($hosted['freshness'] ?? 'NO_EVIDENCE') ?> · environment <?= $e($hosted['environment'] ?? 'Not recorded') ?> · commit <?= $e($hosted['tested_sha'] ?? 'Not recorded') ?> · run <?= $e($hosted['timestamp'] ?? 'Not recorded') ?> · command <?= $e($hosted['command'] ?? 'Not recorded') ?> · exit <?= $e($hosted['exit_code'] ?? 'Not recorded') ?> · artifact <?= $e($hosted['artifact'] ?? 'Not recorded') ?> · SHA-256 <?= $e($hosted['artifact_sha256'] ?? 'Not recorded') ?>.</p><p class="muted"><?= $e($hosted['source'] ?? 'No validated acceptance artifact is registered.') ?></p></article>
        <article class="card"><h3>Academic approval</h3><p><span class="badge badge-warning"><?= $e($academic['status'] ?? 'UNVERIFIED') ?></span></p><p>Pending proposals: <strong><?= $e($academic['pending_proposals'] ?? 'UNKNOWN') ?></strong></p><p>Human reviewer authorization remains required.</p></article>
        <article class="card"><h3>Public release</h3><p><span class="badge badge-danger">NOT AUTHORIZED</span></p><p>A passing technical gate does not authorize public release.</p></article>
    </div>
</section>

<section class="card" aria-labelledby="doctor-evidence-heading">
    <h2 id="doctor-evidence-heading">Full Doctor provenance</h2>
    <p>Evidence freshness: <strong><?= $e($technical['freshness_reason'] ?? 'evidence_unavailable') ?></strong>. Saved evidence is PASS only when its JSON, checksum, exit code, tested commit, and age all validate against this checkout.</p>
    <dl class="definition-grid">
        <dt>Generated</dt><dd><?= $e($technical['generated_at'] ?? 'UNKNOWN') ?></dd>
        <dt>Tested commit</dt><dd><code><?= $e($technical['tested_sha'] ?? 'UNKNOWN') ?></code></dd>
        <dt>Current commit</dt><dd><code><?= $e($technical['current_sha'] ?? 'UNKNOWN') ?></code></dd>
        <dt>Process exit</dt><dd><?= $e($technical['exit_code'] ?? 'UNKNOWN') ?></dd>
        <dt>Artifact</dt><dd><?= $artifactPath ?></dd>
        <dt>Artifact SHA-256</dt><dd><code><?= $e($technical['artifact_sha256'] ?? 'UNKNOWN') ?></code></dd>
        <dt>Command</dt><dd><code><?= $e($technical['command'] ?? 'UNKNOWN') ?></code></dd>
    </dl>
    <h3>Reported priority counts</h3>
    <ul class="related-navigation" aria-label="Doctor priority counts">
        <?php foreach (['P0', 'P1', 'P2', 'P3'] as $priority): ?><li><?= $priority ?>: <strong><?= (int) ($counts[$priority] ?? 0) ?></strong></li><?php endforeach; ?>
    </ul>
    <p>Functional flows: <?= $e($technical['functional_tests']['status'] ?? 'UNVERIFIED') ?> · <?= (int) ($technical['functional_tests']['passed'] ?? 0) ?>/<?= (int) ($technical['functional_tests']['total'] ?? 0) ?>.</p>
    <p>Integration flows: <?= $e($technical['integration_tests']['status'] ?? 'UNVERIFIED') ?> · <?= (int) ($technical['integration_tests']['passed'] ?? 0) ?>/<?= (int) ($technical['integration_tests']['total'] ?? 0) ?>.</p>
    <p>Security: <?= $e($technical['security_tests']['status'] ?? 'UNVERIFIED') ?> · <?= (int) ($technical['security_tests']['passed'] ?? 0) ?>/<?= (int) ($technical['security_tests']['total'] ?? 0) ?>.</p>
    <p class="muted">Source: <?= $e($technical['source'] ?? 'Full Doctor report not found') ?></p>
</section>

<section class="card" aria-labelledby="academic-integrity-heading">
    <h2 id="academic-integrity-heading">Academic integrity and reviewer workflow</h2>
    <p>Pending academic proposals: <strong><?= $e($academic['pending_proposals'] ?? 'UNKNOWN') ?></strong>. Active decision backlog: <strong><?= $e($academic['decision_backlog'] ?? 'UNKNOWN') ?></strong>.</p>
    <p>Reviewer authorization: <strong><?= $e($academic['reviewer_authorization'] ?? 'UNVERIFIED') ?></strong>.</p>
    <p>Source traceability: <strong><?= $e($academic['source_traceability'] ?? 'UNKNOWN') ?></strong>; review signals: <?= $e($academic['source_review_signals'] ?? 'UNKNOWN') ?>; links unreviewed or with unknown status: <?= $e($academic['unreviewed_or_unknown_source_links'] ?? 'UNKNOWN') ?>.</p>
    <p>Question eligibility: <strong><?= $e($academic['eligibility'] ?? 'UNKNOWN') ?></strong>; reported failures: <?= $e($academic['eligibility_failures'] ?? 'UNKNOWN') ?>.</p>
    <p class="muted">Source: <?= $e($academic['source'] ?? 'Academic evidence unavailable') ?>. Learner concerns do not count as approved academic findings.</p>
</section>

<section class="card" aria-labelledby="learner-concerns-heading">
    <h2 id="learner-concerns-heading">Learner question concerns</h2>
    <p>Status: <strong><?= $e($reports['status'] ?? 'UNVERIFIED') ?></strong> · Total: <?= (int) ($reports['counts']['total'] ?? 0) ?> · Submitted: <?= (int) ($reports['counts']['submitted'] ?? 0) ?> · Under review: <?= (int) ($reports['counts']['under_review'] ?? 0) ?> · Resolved: <?= (int) ($reports['counts']['resolved'] ?? 0) ?> · Rejected: <?= (int) ($reports['counts']['rejected'] ?? 0) ?>.</p>
    <p class="muted"><?= $e($reports['source'] ?? 'Report evidence unavailable') ?>.</p>
</section>
