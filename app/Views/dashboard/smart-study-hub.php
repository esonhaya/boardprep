<?php
$smartStudyHub = is_array($smartStudyHub ?? null) ? $smartStudyHub : ['actions' => []];
$smartStudyActions = is_array($smartStudyHub['actions'] ?? null) ? $smartStudyHub['actions'] : [];
?>
<?php if ($smartStudyActions !== []): ?>
<section class="dashboard-smart-study card" aria-labelledby="smart-study-heading" data-learner-state="<?= htmlspecialchars((string) ($smartStudyHub['learner_state'] ?? 'NO_HISTORY'), ENT_QUOTES, 'UTF-8') ?>" data-readiness-state="<?= htmlspecialchars((string) ($smartStudyHub['readiness_state'] ?? 'INSUFFICIENT_PRACTICE_DATA'), ENT_QUOTES, 'UTF-8') ?>">
    <div class="section-heading">
        <div>
            <p class="eyebrow">Smart Study Hub</p>
            <h2 id="smart-study-heading">Next study actions</h2>
            <?php if (($smartStudyHub['learner_state'] ?? '') === 'NO_HISTORY'): ?>
                <p class="muted-note">Start with a board or a published lesson. Personalized progress appears after completed practice.</p>
            <?php else: ?>
                <p class="muted-note">Actions use your saved history and the selected board's current practice availability.</p>
            <?php endif; ?>
        </div>
    </div>
    <nav class="dashboard-smart-study-actions" aria-label="Next study actions">
        <?php foreach (array_slice($smartStudyActions, 0, 3) as $index => $action): ?>
            <a class="card dashboard-smart-study-action" href="<?= htmlspecialchars((string) ($action['href'] ?? '/study-library'), ENT_QUOTES, 'UTF-8') ?>" data-action-type="<?= htmlspecialchars((string) ($action['type'] ?? 'study_library'), ENT_QUOTES, 'UTF-8') ?>" data-availability="<?= htmlspecialchars((string) ($action['availability_state'] ?? 'BROWSE'), ENT_QUOTES, 'UTF-8') ?>">
                <span class="dashboard-smart-study-step">Step <?= $index + 1 ?></span>
                <strong><?= htmlspecialchars((string) ($action['title'] ?? 'Continue studying'), ENT_QUOTES, 'UTF-8') ?></strong>
                <span><?= htmlspecialchars((string) ($action['reason'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                <span class="button button-outline dashboard-smart-study-link"><?= htmlspecialchars((string) ($action['label'] ?? 'Open'), ENT_QUOTES, 'UTF-8') ?></span>
            </a>
        <?php endforeach; ?>
    </nav>
</section>
<?php endif; ?>
