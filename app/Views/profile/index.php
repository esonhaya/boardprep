<?php
$taxonomyLabel = static fn(string $collection, mixed $id): string => \App\Services\Shared\TaxonomyStorageService::label($collection, $id);
$hasCompletedQuizzes = (int) ($profile['totalQuizzes'] ?? 0) > 0;
$overallAccuracy = $hasCompletedQuizzes ? (int) ($profile['overallAccuracy'] ?? 0) . '%' : '—';
$bestScore = $hasCompletedQuizzes ? (int) ($profile['bestScore'] ?? 0) . '%' : '—';
$latestScore = $hasCompletedQuizzes ? (int) ($profile['latestScore'] ?? 0) . '%' : '—';
$recentTrend = $hasCompletedQuizzes
    ? ucwords(str_replace('_', ' ', (string) ($analytics['trend']['direction'] ?? 'insufficient_history')))
    : 'Not started';
?>

<h1>Learning Profile</h1>

<?php $preferences = is_array($preferences ?? null) ? $preferences : []; $flash = is_array($flash ?? null) ? $flash : null; ?>
<?php if ($flash !== null): ?><p class="alert <?= ($flash['type'] ?? '') === 'error' ? 'alert-danger' : 'alert-success' ?>" role="status"><?= htmlspecialchars((string) ($flash['message'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>

<section class="card learner-preferences" aria-labelledby="learner-preferences-title">
    <h2 id="learner-preferences-title">Your exam preferences</h2>
    <p>These are stable choices for this browser session. They are separate from the Beginner, Intermediate, or Advanced level estimated from quiz results. Explicit board and subject links always take priority.</p>
    <form method="POST" action="/profile/preferences">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) ($csrfToken ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        <div class="form-field"><label for="preferred-board">Preferred board</label><select id="preferred-board" name="preferred_board"><option value="">No preference</option><?php foreach (($boards ?? []) as $board): ?><option value="<?= htmlspecialchars((string) ($board['code'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" <?= strtoupper((string) ($preferences['preferred_board'] ?? '')) === strtoupper((string) ($board['code'] ?? '')) ? 'selected' : '' ?>><?= htmlspecialchars((string) ($board['name'] ?? $board['code'] ?? ''), ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select></div>
        <div class="form-field"><label for="preferred-subject">Preferred subject</label><select id="preferred-subject" name="preferred_subject"><option value="">No preference</option><?php foreach (($boards ?? []) as $board): foreach (($board['practice_subjects'] ?? []) as $subject): ?><option value="<?= htmlspecialchars((string) ($subject['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" data-board="<?= htmlspecialchars((string) ($board['code'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" <?= strtoupper((string) ($preferences['preferred_board'] ?? '')) === strtoupper((string) ($board['code'] ?? '')) && (string) ($preferences['preferred_subject'] ?? '') === (string) ($subject['id'] ?? '') ? 'selected' : '' ?>><?= htmlspecialchars((string) ($board['name'] ?? 'Board'), ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars((string) ($subject['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; endforeach; ?></select></div>
        <div class="form-field"><label for="profile-let-level">LET candidate level</label><select id="profile-let-level" name="let_level"><option value="">Not selected</option><option value="ELEMENTARY" <?= ($preferences['let_level'] ?? '') === 'ELEMENTARY' ? 'selected' : '' ?>>Elementary</option><option value="SECONDARY" <?= ($preferences['let_level'] ?? '') === 'SECONDARY' ? 'selected' : '' ?>>Secondary</option></select></div>
        <div class="form-field"><label for="profile-let-specialization">LET specialization</label><select id="profile-let-specialization" name="let_specialization"><option value="">None</option><?php foreach (($letSpecializations ?? []) as $specialization): ?><option value="<?= htmlspecialchars((string) ($specialization['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" <?= (string) ($preferences['let_specialization'] ?? '') === (string) ($specialization['id'] ?? '') ? 'selected' : '' ?>><?= htmlspecialchars((string) ($specialization['official_name'] ?? $specialization['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars((string) ($specialization['level'] ?? ''), ENT_QUOTES, 'UTF-8') ?>)</option><?php endforeach; ?></select></div>
        <div class="form-field"><label for="profile-cse-level">Civil Service candidate level</label><select id="profile-cse-level" name="civil_service_level"><option value="">Not selected</option><option value="PROFESSIONAL" <?= ($preferences['civil_service_level'] ?? '') === 'PROFESSIONAL' ? 'selected' : '' ?>>Professional</option><option value="SUBPROFESSIONAL" <?= ($preferences['civil_service_level'] ?? '') === 'SUBPROFESSIONAL' ? 'selected' : '' ?>>Subprofessional</option></select></div>
        <details><summary>Subject interests (optional)</summary><p>These can guide study suggestions. They do not change board eligibility or quiz scoring.</p><div class="profile-interest-list"><?php foreach (\App\Services\Shared\TaxonomyStorageService::subjects() as $interest): if (strtolower((string) ($interest['status'] ?? 'active')) === 'archived') continue; $interestId = (string) ($interest['id'] ?? ''); ?><label><input type="checkbox" name="subject_interests[]" value="<?= htmlspecialchars($interestId, ENT_QUOTES, 'UTF-8') ?>" <?= in_array($interestId, (array) ($preferences['subject_interests'] ?? []), true) ? 'checked' : '' ?>> <?= htmlspecialchars((string) ($interest['name'] ?? $interestId), ENT_QUOTES, 'UTF-8') ?></label><?php endforeach; ?></div></details>
        <button type="submit">Save exam preferences</button>
    </form>
</section>

<hr>

<h2>Current Level</h2>
<p>
    <?= htmlspecialchars((string) ($profile["level"] ?? "Beginner")) ?>
</p>

<h2>Performance</h2>
<ul>
    <li>Overall Accuracy: <?= htmlspecialchars($overallAccuracy, ENT_QUOTES, 'UTF-8') ?></li>
    <li>Best Score: <?= htmlspecialchars($bestScore, ENT_QUOTES, 'UTF-8') ?></li>
    <li>Latest Score: <?= htmlspecialchars($latestScore, ENT_QUOTES, 'UTF-8') ?></li>
    <li>Total Quizzes: <?= (int) ($profile["totalQuizzes"] ?? 0) ?></li>
    <li>Current Streak: <?= (int) ($streak ?? 0) ?> day<?= ((int) ($streak ?? 0)) === 1 ? "" : "s" ?></li>
    <li>Recent Trend: <?= htmlspecialchars($recentTrend, ENT_QUOTES, 'UTF-8') ?></li>
</ul>
<?php if (!$hasCompletedQuizzes): ?><p class="muted-note" role="status">No completed quizzes yet. Your performance summary will appear here after your first quiz.</p><?php endif; ?>

<hr>

<h2>Study Insight</h2>
<p>
    <?= htmlspecialchars((string) ($insight["message"] ?? "Complete quizzes to receive personalized study insights.")) ?>
</p>

<?php if (!empty($studyRecommendations)): ?>
<h3>Recommended Next Steps</h3>
<ul>
<?php foreach ($studyRecommendations as $recommendation): ?>
    <li>
        <strong><?= htmlspecialchars((string) ($recommendation["title"] ?? "")) ?></strong>
        —
        <?= htmlspecialchars((string) ($recommendation["reason"] ?? "")) ?>
        <a
            href="<?= htmlspecialchars((string) ($recommendation["action"] ?? "/quiz")) ?>"
            aria-label="<?= htmlspecialchars((string) ($recommendation["label"] ?? "Start practice")) ?>"
        >
            <?= htmlspecialchars((string) ($recommendation["label"] ?? "Start practice")) ?>
        </a>
    </li>
<?php endforeach; ?>
</ul>
<?php endif; ?>

<hr>

<h2>Topic Performance</h2>

<?php if (!empty($subjects)): ?>
<h3>By Subject</h3>
<?php foreach ($subjects as $subject): ?>
<p><strong><?= htmlspecialchars($taxonomyLabel('subjects', $subject["subject"])) ?></strong> — <?= (int) $subject["average"] ?>% average</p>
<?php endforeach; ?>
<?php endif; ?>

<?php if (empty($topics)): ?>
<p>No topic performance available yet.</p>
<?php else: ?>
<?php foreach (array_slice($topics, 0, 5) as $topic): ?>
<p>
    <strong><?= htmlspecialchars($taxonomyLabel('taxonomy/topics', $topic["topic"])) ?></strong>
    —
    <?= (int) $topic["average"] ?>% average
    /
    <?= (int) $topic["attempts"] ?> attempt<?= ((int) $topic["attempts"]) === 1 ? "" : "s" ?>
</p>
<?php endforeach; ?>
<?php endif; ?>

<hr>

<h2>Recent Learning Activity</h2>

<?php if (empty($timeline)): ?>
<p>No completed quizzes yet. Take a quiz to start building your learning history.</p>
<?php else: ?>
<div class="ui-preview-list" data-ui-collection="preview" data-ui-limit="5">
<?php foreach ($timeline as $item): ?>
<div class="record-card">
    <strong><?= htmlspecialchars(ucfirst((string) ($item["mode"] ?? "practice"))) ?></strong>
    —
    <?= htmlspecialchars($taxonomyLabel('taxonomy/topics', $item["topic"] ?? "General")) ?>
    <br>
    Score:
    <?= (int) ($item["score"] ?? 0) ?>/<?= (int) ($item["total"] ?? 0) ?>
    (<?= (int) ($item["percentage"] ?? 0) ?>%)
    <?php if (!empty($item["date"])): ?>
        <br><?= htmlspecialchars((string) $item["date"]) ?>
    <?php endif; ?>
</div>
<?php endforeach; ?>
</div>
<?php if (!empty($timelineHasMore)): ?><p><a class="ui-view-all" data-ui-view-all href="/history">View all quiz history</a></p><?php endif; ?>
<?php endif; ?>

<hr>

<a href="/progress">View Progress</a>
<div class="form-spacer"></div>
<a href="/history">Quiz History</a>
<div class="form-spacer"></div>
<a href="/quiz">Take Quiz</a>
<div class="form-spacer"></div>
<a href="/dashboard">Dashboard</a>

<script src="/assets/js/profile-preferences.js" defer></script>
