<?php

declare(strict_types=1);

$e = static fn(mixed $value): string => htmlspecialchars(is_scalar($value) ? (string) $value : '', ENT_QUOTES, 'UTF-8');
$boards = array_values(array_filter((array) ($boards ?? []), static fn(mixed $board): bool => is_array($board) && (array) ($board['practice_subjects'] ?? []) !== []));
$plan = is_array($plan ?? null) ? $plan : null;
$configuration = is_array($plan['configuration'] ?? null) ? $plan['configuration'] : [];
$preferences = is_array($preferences ?? null) ? $preferences : [];
$selectedBoard = (string) ($configuration['board_id'] ?? $preferences['preferred_board'] ?? ($boards[0]['id'] ?? ''));
$revision = (int) ($plan['revision'] ?? 0);
$sessions = is_array($plan['sessions'] ?? null) ? $plan['sessions'] : [];
$weekdays = array_map('intval', (array) ($configuration['weekdays'] ?? [1, 2, 3, 4, 5]));
$restDates = array_values((array) ($configuration['rest_dates'] ?? []));
$subjectPriorities = is_array($configuration['subject_priorities'] ?? null) ? $configuration['subject_priorities'] : [];
$today = (string) ($today ?? date('Y-m-d'));
$examDateLimit = (string) ($configuration['exam_date'] ?? '');
$latestRescheduleDate = $examDateLimit !== '' ? $examDateLimit : (new DateTimeImmutable($today))->modify('+90 days')->format('Y-m-d');
$weekdayNames = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'];
$nextAvailable = static fn(string $after, array $config, array $allSessions, string $exceptTaskId): string =>
    \App\Services\Learning\StudyCalendarPlanner::suggestedRescheduleDate(
        $after,
        $config,
        $allSessions,
        $exceptTaskId,
        $today
    );
?>

<header class="page-header">
    <div>
        <p class="eyebrow">Your study schedule</p>
        <h1>Study Calendar</h1>
        <p>Plan practice from this board’s currently available subjects. Recommendations use your saved quiz and review history when there is enough evidence; no pass probability or official exam coverage is estimated.</p>
    </div>
    <nav aria-label="Study navigation" class="related-navigation">
        <a class="button button-outline" href="/study">Study dashboard</a>
        <a class="button button-outline" href="/flashcards">Flashcards</a>
        <a class="button button-outline" href="/mistake-notebook">Mistake notebook</a>
    </nav>
</header>

<?php if (is_string($error ?? null) && $error !== ''): ?>
    <p class="alert alert-danger" role="alert"><?= $e($error) ?></p>
<?php endif; ?>
<?php if (is_array($notice ?? null) && is_string($notice['message'] ?? null)): ?>
    <p class="alert alert-<?= $e($notice['type'] ?? 'info') ?>" role="status" aria-live="polite"><?= $e($notice['message']) ?></p>
<?php endif; ?>

<section class="card" aria-labelledby="calendar-settings-heading">
    <h2 id="calendar-settings-heading"><?= $plan === null ? 'Set up your plan' : 'Adjust your plan' ?></h2>
    <p>Sessions are generated for up to 42 days at a time (up to 28 study days). Re-saving settings replaces untouched future sessions while keeping completed work and sessions you edited.</p>
    <?php if ($boards === []): ?>
        <p class="empty-state">There are no boards with currently available practice subjects. You can keep using your dashboard and review queue; a calendar can be configured when subject availability exists.</p>
    <?php else: ?>
    <?php if ((int) ($plan['retained_user_edits'] ?? 0) > 0): ?>
        <p class="alert alert-info" role="status"><?= (int) $plan['retained_user_edits'] ?> future session(s) you edited were retained during the last regeneration. Review their dates below.</p>
    <?php endif; ?>
    <form method="post" action="/study-calendar" class="form-stack">
        <input type="hidden" name="csrf_token" value="<?= $e($csrfToken ?? '') ?>">
        <input type="hidden" name="expected_revision" value="<?= $revision ?>">
        <input type="hidden" name="request_id" value="<?= $e(bin2hex(random_bytes(16))) ?>">

        <label for="calendar-board">Target board examination</label>
        <select id="calendar-board" name="board_id" required>
            <?php foreach ($boards as $board): ?>
                <?php $boardId = (string) ($board['id'] ?? ''); ?>
                <option value="<?= $e($boardId) ?>"<?= strcasecmp($selectedBoard, $boardId) === 0 || strcasecmp($selectedBoard, (string) ($board['code'] ?? '')) === 0 ? ' selected' : '' ?>><?= $e($board['name'] ?? $board['code'] ?? $boardId) ?> · <?= count((array) ($board['practice_subjects'] ?? [])) ?> available subject(s)</option>
            <?php endforeach; ?>
        </select>

        <div class="form-grid">
            <div>
                <label for="calendar-exam-date">Target exam date <span class="muted">(optional)</span></label>
                <input id="calendar-exam-date" name="exam_date" type="date" min="<?= $e($today) ?>" value="<?= $e($configuration['exam_date'] ?? '') ?>">
            </div>
            <div>
                <label for="calendar-start-date">Study start date</label>
                <input id="calendar-start-date" name="start_date" type="date" min="<?= $e($today) ?>" value="<?= $e($configuration['start_date'] ?? $today) ?>" required>
            </div>
            <div>
                <label for="calendar-minutes">Available study minutes per day</label>
                <input id="calendar-minutes" name="daily_minutes" type="number" min="15" max="240" step="5" value="<?= (int) ($configuration['daily_minutes'] ?? 45) ?>" required>
            </div>
            <div>
                <label for="calendar-intensity">Preferred review intensity</label>
                <select id="calendar-intensity" name="review_intensity">
                    <?php foreach (['light' => 'Light', 'balanced' => 'Balanced', 'intensive' => 'Intensive'] as $value => $label): ?>
                        <option value="<?= $e($value) ?>"<?= ($configuration['review_intensity'] ?? 'balanced') === $value ? ' selected' : '' ?>><?= $e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <fieldset>
            <legend>Available study weekdays</legend>
            <div class="form-grid">
                <?php foreach ($weekdayNames as $weekday => $label): ?>
                    <label><input type="checkbox" name="weekdays[]" value="<?= $weekday ?>"<?= in_array($weekday, $weekdays, true) ? ' checked' : '' ?>> <?= $e($label) ?></label>
                <?php endforeach; ?>
            </div>
        </fieldset>

        <div>
            <label for="calendar-rest-days">Optional rest dates</label>
            <input id="calendar-rest-days" name="rest_dates" type="text" maxlength="1090" value="<?= $e(implode(', ', $restDates)) ?>" placeholder="YYYY-MM-DD, YYYY-MM-DD">
            <small>Enter up to 90 dates separated by commas. Rest dates must be today or later.</small>
        </div>

        <fieldset>
            <legend>Subject priorities</legend>
            <p>Choose a priority for each available subject. The planner rotates through the real subject list and gives higher priority more sessions.</p>
            <?php foreach ($boards as $board): ?>
                <?php $boardId = (string) ($board['id'] ?? ''); $boardSubjects = (array) ($board['practice_subjects'] ?? []); ?>
                <details<?= strcasecmp($selectedBoard, $boardId) === 0 || strcasecmp($selectedBoard, (string) ($board['code'] ?? '')) === 0 ? ' open' : '' ?>>
                    <summary><?= $e($board['name'] ?? $board['code'] ?? $boardId) ?> subjects</summary>
                    <div class="form-grid">
                        <?php foreach ($boardSubjects as $subject): ?>
                            <?php if (!is_array($subject)) continue; $subjectId = strtolower((string) ($subject['id'] ?? '')); if ($subjectId === '') continue; ?>
                            <div>
                                <label for="priority-<?= $e($boardId) ?>-<?= $e($subjectId) ?>"><?= $e($subject['name'] ?? $subjectId) ?> <small>(<?= (int) ($subject['available_questions'] ?? 0) ?> practice questions available)</small></label>
                                <select id="priority-<?= $e($boardId) ?>-<?= $e($subjectId) ?>" name="subject_priorities[<?= $e($boardId) ?>][<?= $e($subjectId) ?>]">
                                    <?php $priority = (string) ($subjectPriorities[$subjectId] ?? 'normal'); foreach (['low' => 'Low', 'normal' => 'Normal', 'high' => 'High'] as $value => $label): ?>
                                        <option value="<?= $e($value) ?>"<?= $priority === $value ? ' selected' : '' ?>><?= $e($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </details>
            <?php endforeach; ?>
        </fieldset>
        <button type="submit"><?= $plan === null ? 'Generate study calendar' : 'Save settings and regenerate future sessions' ?></button>
    </form>
    <?php endif; ?>
</section>

<?php if ($plan !== null): ?>
    <section class="card" aria-labelledby="calendar-plan-heading">
        <h2 id="calendar-plan-heading">Your sessions</h2>
        <p>Board: <strong><?= $e($configuration['board_name'] ?? $configuration['board_code'] ?? '') ?></strong> · Daily time: <strong><?= (int) ($configuration['daily_minutes'] ?? 0) ?> minutes</strong> · Plan through <strong><?= $e($plan['window_end_date'] ?? '') ?></strong></p>
        <p role="status" aria-live="polite">Performance evidence: <strong><?= $e(str_replace('_', ' ', (string) ($plan['performance_status'] ?? 'INSUFFICIENT_DATA'))) ?></strong>. <?= $e(($plan['explanations'][0] ?? 'No learner performance history is available.')) ?></p>
        <ul>
            <?php foreach ((array) ($plan['explanations'] ?? []) as $explanation): ?><li><?= $e($explanation) ?></li><?php endforeach; ?>
        </ul>
        <?php if ($sessions === []): ?>
            <p class="empty-state">No sessions are scheduled. Adjust available weekdays or rest dates and regenerate.</p>
        <?php else: ?>
            <ol class="study-calendar-list">
                <?php foreach ($sessions as $session): ?>
                    <?php if (!is_array($session)) continue; $status = (string) ($session['status'] ?? 'planned'); $taskId = (string) ($session['task_id'] ?? ''); $isOverdue = in_array($status, ['planned', 'deferred'], true) && (string) ($session['scheduled_date'] ?? '') < $today; $action = (string) ($session['action'] ?? '/study'); if (!preg_match('#^/(?:quiz|flashcards/review|mistake-notebook)(?:\?|$)#', $action)) $action = '/study'; ?>
                    <li class="study-calendar-session" id="session-<?= $e($taskId) ?>">
                        <article class="study-plan-item">
                            <div class="page-header">
                                <div>
                                    <p class="eyebrow"><?= $isOverdue ? 'OVERDUE · RECOVERY' : $e(strtoupper(str_replace('_', ' ', $status))) ?> · <?= $e($session['kind'] ?? 'PRACTICE') ?></p>
                                    <h3><?= $e($session['scheduled_date'] ?? '') ?> — <?= $e($session['subject_name'] ?? $session['subject_id'] ?? '') ?><?= is_string($session['board_name'] ?? null) && $session['board_name'] !== '' ? ' · ' . $e($session['board_name']) : '' ?></h3>
                                </div>
                                <span class="badge"><?= (int) ($session['duration_minutes'] ?? 0) ?> min</span>
                            </div>
                            <?php if ($isOverdue): ?><p class="alert alert-warning" role="status">This session was missed. You can complete it, skip it, or move it to an available date on or before your target examination.</p><?php endif; ?>
                            <?php if (is_string($session['topic'] ?? null) && $session['topic'] !== ''): ?><p>Focus topic: <strong><?= $e($session['topic']) ?></strong><?php if (is_numeric($session['topic_average'] ?? null)): ?> · recorded average <?= (int) $session['topic_average'] ?>% over <?= (int) ($session['evidence_attempts'] ?? 0) ?> attempt(s)<?php endif; ?></p><?php endif; ?>
                            <p><?= $e($session['reason'] ?? '') ?></p>
                            <p><a class="button button-outline" href="<?= $e($action) ?>">Open this study activity</a></p>
                            <?php if (in_array($status, ['planned', 'deferred'], true)): ?>
                                <?php foreach (['complete' => 'Mark complete', 'skip' => 'Skip session'] as $operation => $label): ?>
                                    <form method="post" action="/study-calendar/task" class="inline-form">
                                        <input type="hidden" name="csrf_token" value="<?= $e($csrfToken ?? '') ?>"><input type="hidden" name="task_id" value="<?= $e($taskId) ?>"><input type="hidden" name="expected_revision" value="<?= $revision ?>"><input type="hidden" name="request_id" value="<?= $e(bin2hex(random_bytes(16))) ?>"><input type="hidden" name="operation" value="<?= $e($operation) ?>">
                                        <button type="submit" class="button button-outline"><?= $e($label) ?></button>
                                    </form>
                                <?php endforeach; ?>
                                <?php $deferDate = $nextAvailable((string) ($session['scheduled_date'] ?? $today), $configuration, $sessions, $taskId); ?>
                                <form method="post" action="/study-calendar/task" class="form-grid study-calendar-edit-form">
                                    <input type="hidden" name="csrf_token" value="<?= $e($csrfToken ?? '') ?>"><input type="hidden" name="task_id" value="<?= $e($taskId) ?>"><input type="hidden" name="expected_revision" value="<?= $revision ?>"><input type="hidden" name="request_id" value="<?= $e(bin2hex(random_bytes(16))) ?>">
                                    <div><label for="date-<?= $e($taskId) ?>">Move session to an available weekday</label><input id="date-<?= $e($taskId) ?>" name="scheduled_date" type="date" min="<?= $e($today) ?>" max="<?= $e($latestRescheduleDate) ?>" value="<?= $e($deferDate) ?>" required></div>
                                    <div><label for="operation-<?= $e($taskId) ?>">Update type</label><select id="operation-<?= $e($taskId) ?>" name="operation"><option value="defer">Defer</option><option value="reschedule">Reschedule</option></select><button type="submit" class="button button-outline">Save date</button></div>
                                </form>
                            <?php elseif ($status === 'completed'): ?>
                                <p>Completed<?= is_string($session['completed_at'] ?? null) ? ' at ' . $e($session['completed_at']) : '' ?>.</p>
                            <?php endif; ?>
                        </article>
                    </li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>
    </section>
<?php endif; ?>
