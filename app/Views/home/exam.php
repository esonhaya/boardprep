<?php
$board = is_array($board ?? null) ? $board : [];
$subjects = is_array($learnerSubjects ?? null) ? $learnerSubjects : [];
$studyAvailability = is_array($studyAvailability ?? null) ? $studyAvailability : [];
$boardId = (string) ($board['id'] ?? '');
$boardCode = (string) ($board['code'] ?? $boardId);
$escape = static fn(mixed $value): string => htmlspecialchars(is_scalar($value) ? (string) $value : '', ENT_QUOTES, 'UTF-8');
?>
<div class="ui-page-detail exam-detail">
    <nav class="breadcrumbs" aria-label="Breadcrumb"><ol><li><a href="/exams">Examinations</a></li><li aria-current="page"><?= $escape($board['name'] ?? '') ?></li></ol></nav>
    <section class="exam-detail-hero accent-<?= $escape($board['visual']['accent'] ?? 'blue') ?>">
        <div class="exam-card-mark"><?php $icon = $board['visual']['icon'] ?? 'exam'; $iconSize = 'lg'; require dirname(__DIR__) . '/components/icon.php'; ?></div>
        <div>
            <p class="eyebrow"><?= $escape($boardCode) ?> examination</p>
            <h1><?= $escape($board['name'] ?? '') ?></h1>
            <p><?= $escape($board['description'] ?? '') ?></p>
            <div class="page-actions">
                <a class="button button-green" href="/quiz?exam=<?= rawurlencode($boardCode) ?>">Configure practice</a>
                <a class="button button-ghost-dark" href="/quiz?exam=<?= rawurlencode($boardCode) ?>&amp;mode=exam">Exam simulation</a>
                <a class="button button-outline" href="/study-library?exam=<?= rawurlencode($boardId) ?>">Study materials</a>
            </div>
            <p class="form-help">BoardPrep exam simulations are separate from the official board examination.</p>
        </div>
    </section>

    <div class="section-heading"><div><p class="eyebrow">Study map</p><h2>Choose a subject</h2><p>Study materials and practice stay within the selected subject.</p></div></div>
    <?php if ($subjects === []): ?>
        <div class="empty-state"><h2>Subject options are being prepared</h2><p>No subjects are currently available for this examination.</p><p class="page-actions"><a class="button button-outline" href="/exams">Choose an exam</a><a class="button button-outline" href="/dashboard">Dashboard</a></p></div>
    <?php else: ?>
        <div class="subject-grid">
            <?php foreach ($subjects as $subject): ?>
                <?php
                if (!is_array($subject)) continue;
                $subjectId = (string) ($subject['id'] ?? '');
                if ($subjectId === '') continue;
                $availableQuestions = max(0, (int) ($subject['available_questions'] ?? 0));
                $availability = $studyAvailability[strtolower($subjectId)] ?? [];
                $lessonCount = max(0, (int) ($availability['lesson_count'] ?? 0));
                ?>
                <article class="card subject-card">
                    <div class="subject-icon"><?php $icon = 'book'; require dirname(__DIR__) . '/components/icon.php'; ?></div>
                    <h3><?= $escape($subject['name'] ?? $subjectId) ?></h3>
                    <p><?= $availableQuestions > 0 ? number_format($availableQuestions) . ' eligible practice questions' : 'Practice questions are not currently available.' ?></p>
                    <p><?= $lessonCount > 0 ? $lessonCount . ' published ' . ($lessonCount === 1 ? 'lesson' : 'lessons') : 'No published lessons yet.' ?></p>
                    <?php if (($subject['study_material_available'] ?? false) === true): ?>
                        <p><a href="/study-library?exam=<?= rawurlencode($boardId) ?>&amp;subject=<?= rawurlencode($subjectId) ?>">Explore this subject’s study materials →</a></p>
                    <?php else: ?>
                        <p class="muted">No subject-specific study materials are published yet.</p>
                    <?php endif; ?>
                    <?php if ($availableQuestions > 0): ?>
                        <p><a href="/quiz?exam=<?= rawurlencode($boardCode) ?>&amp;subject=<?= rawurlencode($subjectId) ?>">Practice this subject →</a></p>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
