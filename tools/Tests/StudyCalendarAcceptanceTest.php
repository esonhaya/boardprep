<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

use App\Services\Accounts\AccountDataInventoryService;
use App\Services\Accounts\AccountRestoreService;
use App\Services\Learning\StudyCalendarPlanner;
use App\Services\Learning\StudyCalendarService;
use App\Services\Learning\StudyCalendarEvidenceService;
use App\Storage\JsonStorage;
use App\Storage\SqliteStorage;

function calendarAssert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function calendarRejects(callable $operation, string $message): void
{
    try { $operation(); } catch (InvalidArgumentException) { return; }
    throw new RuntimeException($message);
}

$fixtureRoot = sys_get_temp_dir() . '/boardprep-study-calendar-' . bin2hex(random_bytes(8));
if (!mkdir($fixtureRoot, 0700, true)) throw new RuntimeException('Calendar fixture directory could not be created.');
$today = '2026-11-01';
$boards = [[
    'id' => 'let',
    'code' => 'LET',
    'name' => 'Synthetic LET Board',
    'practice_subjects' => [
        ['id' => 'english', 'name' => 'English', 'available_questions' => 30],
        ['id' => 'mathematics', 'name' => 'Mathematics', 'available_questions' => 20],
    ],
]];
$input = [
    'board_id' => 'LET',
    'exam_date' => '2026-11-25',
    'start_date' => $today,
    'weekdays' => [1, 2, 3, 4, 5],
    'daily_minutes' => 45,
    'review_intensity' => 'balanced',
    'rest_dates' => ['2026-11-04'],
    'subject_priorities' => ['let' => ['english' => 'high', 'mathematics' => 'normal']],
    'expected_revision' => 0,
    'request_id' => str_repeat('1', 32),
];
$evidence = [
    'attempt_count' => 0,
    'focus_topics' => [['topic' => 'Unsupported synthetic weakness', 'board' => 'LET', 'subject' => 'english', 'attempts' => 20, 'average' => 10]],
    'due_flashcard_count' => 5,
    'due_flashcard_subject_counts' => ['english' => 5],
    'due_flashcard_status' => 'AVAILABLE',
    'mistake_subject_counts' => ['english' => 3],
];

try {
    $validated = StudyCalendarPlanner::validateConfiguration($input, $boards, $today);
    $generated = StudyCalendarPlanner::generate($validated, $evidence, $today);
    $sessionDates = array_column($generated['sessions'], 'scheduled_date');
    calendarAssert($generated['performance_status'] === 'INSUFFICIENT_DATA', 'A plan without quiz history claimed performance evidence.');
    calendarAssert(count($generated['sessions']) > 0 && count($generated['sessions']) <= StudyCalendarPlanner::MAX_FUTURE_SESSIONS, 'Initial schedule was empty or exceeded its bound.');
    calendarAssert(count($sessionDates) === count(array_unique($sessionDates)), 'Generation created duplicate sessions on a day.');
    calendarAssert(count(array_filter($generated['sessions'], static fn(array $s): bool => (int) $s['duration_minutes'] > (int) $validated['daily_minutes'])) === 0, 'A session exceeded the learner’s daily study-minute limit.');
    calendarAssert(!in_array('2026-11-04', $sessionDates, true), 'A configured rest day received a session.');
    calendarAssert((new DateTimeImmutable(max($sessionDates))) <= new DateTimeImmutable('2026-11-25'), 'A generated session exceeded the configured examination date.');
    $flashcardSessions = array_values(array_filter($generated['sessions'], static fn(array $s): bool => $s['kind'] === 'FLASHCARDS'));
    calendarAssert(count($flashcardSessions) === 1 && $flashcardSessions[0]['subject_id'] === 'english', 'Due-card evidence did not create one subject-scoped review for the subject with recorded due cards.');
    foreach ($flashcardSessions as $session) {
        parse_str((string) parse_url((string) ($session['action'] ?? ''), PHP_URL_QUERY), $actionQuery);
        calendarAssert(($actionQuery['board'] ?? null) === 'LET'
            && ($actionQuery['subject'] ?? null) === ($session['subject_id'] ?? null),
            'A flashcard task did not preserve its board and assigned subject filters.');
        calendarAssert(str_contains((string) $session['reason'], '5 due flashcards'), 'Flashcard recommendation did not explain the recorded subject queue.');
    }
    $mistakeSessions = array_values(array_filter($generated['sessions'], static fn(array $s): bool => $s['kind'] === 'MISTAKES'));
    calendarAssert($mistakeSessions !== [], 'Recorded mistake evidence was not integrated into the schedule.');
    foreach ($mistakeSessions as $session) {
        parse_str((string) parse_url((string) ($session['action'] ?? ''), PHP_URL_QUERY), $actionQuery);
        calendarAssert(parse_url((string) ($session['action'] ?? ''), PHP_URL_PATH) === '/mistake-notebook'
            && ($actionQuery['board'] ?? null) === 'let'
            && ($actionQuery['subject'] ?? null) === 'english'
            && ($session['subject_id'] ?? null) === 'english',
            'A mistake task did not open the matching learner-owned board/subject notebook.');
    }
    calendarAssert(count(array_filter($generated['sessions'], static fn(array $s): bool => $s['topic'] !== null)) === 0, 'Insufficient history was misrepresented as a weak topic.');
    calendarAssert(str_starts_with($generated['sessions'][0]['action'], '/'), 'Study task action was not an internal route.');

    $withoutExamDate = StudyCalendarPlanner::validateConfiguration(array_merge($input, ['exam_date' => '']), $boards, $today);
    $defaultHorizon = StudyCalendarPlanner::generate($withoutExamDate, [], $today);
    calendarAssert($withoutExamDate['exam_date'] === null
        && $defaultHorizon['window_end_date'] === (new DateTimeImmutable($today))->modify('+13 days')->format('Y-m-d'),
        'Missing exam date did not produce a bounded default planning horizon.');

    $narrowWindowInput = array_merge($input, [
        'start_date' => $today,
        'exam_date' => $today,
        'weekdays' => [7],
        'rest_dates' => [],
    ]);
    $narrowWindow = StudyCalendarPlanner::validateConfiguration($narrowWindowInput, $boards, $today);
    $narrowPlan = StudyCalendarPlanner::generate($narrowWindow, [
        'due_flashcard_subject_counts' => ['english' => 5, 'mathematics' => 3],
        'due_flashcard_status' => 'AVAILABLE',
    ], $today);
    calendarAssert(count($narrowPlan['sessions']) === 1
        && $narrowPlan['sessions'][0]['subject_id'] === 'english'
        && $narrowPlan['unscheduled_due_subjects'] === ['mathematics'],
        'A narrow exam-date window exceeded available time or failed to disclose an unplaced due subject queue.');
    calendarAssert(str_contains(implode(' ', $narrowPlan['explanations']), 'did not fit'), 'Insufficient calendar capacity was not explained.');
    calendarAssert(!StudyCalendarPlanner::validScheduledDate('2026-11-26', $validated, $today), 'A recovery date after the examination was accepted.');

    $overdueSuggestionPlan = [
        ['task_id' => 'task-' . str_repeat('a', 24), 'scheduled_date' => '2026-10-26', 'status' => 'planned'],
        ['task_id' => 'task-' . str_repeat('b', 24), 'scheduled_date' => '2026-11-02', 'status' => 'planned'],
        ['task_id' => 'task-' . str_repeat('c', 24), 'scheduled_date' => '2026-11-03', 'status' => 'deferred'],
    ];
    $recoverySuggestion = StudyCalendarPlanner::suggestedRescheduleDate(
        '2026-10-26', $validated, $overdueSuggestionPlan, 'task-' . str_repeat('a', 24), $today
    );
    calendarAssert($recoverySuggestion === '2026-11-05', 'Overdue recovery suggestion did not clamp to today and avoid occupied/rest dates.');

    calendarRejects(static fn() => StudyCalendarPlanner::validateConfiguration(array_merge($input, ['exam_date' => '2026-10-01']), $boards, $today), 'Past exam date was accepted.');
    calendarRejects(static fn() => StudyCalendarPlanner::validateConfiguration(array_merge($input, ['start_date' => '2026-10-31']), $boards, $today), 'Past study start date was accepted.');
    calendarRejects(static fn() => StudyCalendarPlanner::validateConfiguration(array_merge($input, ['weekdays' => []]), $boards, $today), 'An empty weekday set was accepted.');
    calendarRejects(static fn() => StudyCalendarPlanner::validateConfiguration(array_merge($input, ['daily_minutes' => 1000]), $boards, $today), 'Unbounded daily workload was accepted.');
    calendarRejects(static fn() => StudyCalendarPlanner::validateConfiguration(array_merge($input, ['review_intensity' => 'extreme']), $boards, $today), 'Unsupported review intensity was accepted.');
    calendarRejects(static fn() => StudyCalendarPlanner::validateConfiguration(array_merge($input, ['rest_dates' => ['not-a-date']]), $boards, $today), 'Malformed rest date was accepted.');
    calendarRejects(static fn() => StudyCalendarPlanner::validateConfiguration(array_merge($input, ['subject_priorities' => ['let' => ['unknown-subject' => 'high']]]), $boards, $today), 'Unavailable subject priority was accepted.');
    calendarRejects(static fn() => StudyCalendarPlanner::validateConfiguration($input, [['id' => 'empty', 'practice_subjects' => []]], $today), 'Empty subject catalog was accepted.');

    $enoughEvidence = StudyCalendarPlanner::generate($validated, [
        'attempt_count' => 4,
        'focus_topics' => [
            ['topic' => 'Foreign board weak area', 'board' => 'CSE', 'subject' => 'English', 'attempts' => 20, 'average' => 10],
            ['topic' => 'Reading Comprehension', 'board' => 'LET', 'subject' => 'English', 'attempts' => 2, 'average' => 54],
        ],
        'due_flashcard_count' => 0,
        'mistake_count' => 0,
    ], $today);
    calendarAssert($enoughEvidence['performance_status'] === 'EVIDENCE_AVAILABLE', 'Recorded history was not distinguished from insufficient data.');
    calendarAssert(count(array_filter($enoughEvidence['sessions'], static fn(array $s): bool => $s['topic'] === 'Reading Comprehension')) > 0, 'Supported topic focus was omitted.');
    calendarAssert(count(array_filter($enoughEvidence['sessions'], static fn(array $s): bool => $s['topic'] === 'Foreign board weak area')) === 0, 'A different board’s weak topic was applied to this plan.');

    $unscopedMistakeEvidence = StudyCalendarPlanner::generate($validated, [
        'mistake_count' => 99,
        'due_flashcard_count' => 0,
    ], $today);
    calendarAssert(count(array_filter($unscopedMistakeEvidence['sessions'], static fn(array $s): bool => $s['kind'] === 'MISTAKES')) === 0,
        'Unscoped aggregate mistake evidence was assigned to a subject without board/subject provenance.');

    $attemptEvidence = [
        [
            'id' => 'calendar-evidence-let', 'user_id' => 'learner-' . str_repeat('e', 32), 'completed' => true, 'score' => 0, 'total' => 3,
            'board' => 'let', 'subject' => 'english', 'completed_at' => '2026-10-31T10:00:00+00:00',
            'review' => [
                ['question' => ['id' => 'let-english-wrong', 'taxonomy' => ['board_id' => 'let', 'subject_id' => 'english']], 'answered' => true, 'correct' => false],
                ['question' => ['id' => 'let-english-skipped', 'taxonomy' => ['board_id' => 'let', 'subject_id' => 'english']], 'answered' => false, 'correct' => false],
            ],
        ],
        [
            'id' => 'calendar-evidence-let-math', 'user_id' => 'learner-' . str_repeat('e', 32), 'completed' => true, 'score' => 0, 'total' => 1,
            'board' => 'let', 'subject' => 'mathematics', 'completed_at' => '2026-10-29T10:00:00+00:00',
            'review' => [['question' => ['id' => 'let-math-wrong', 'taxonomy' => ['board_id' => 'let', 'subject_id' => 'mathematics']], 'answered' => true, 'correct' => false]],
        ],
        [
            'id' => 'calendar-evidence-cse', 'user_id' => 'learner-' . str_repeat('e', 32), 'completed' => true, 'score' => 0, 'total' => 1,
            'board' => 'cse', 'subject' => 'english', 'completed_at' => '2026-10-30T10:00:00+00:00',
            'review' => [['question' => ['id' => 'cse-english-wrong', 'taxonomy' => ['board_id' => 'cse', 'subject_id' => 'english']], 'answered' => true, 'correct' => false]],
        ],
        [
            'id' => 'calendar-evidence-mixed-snapshot', 'user_id' => 'learner-' . str_repeat('e', 32), 'completed' => true, 'score' => 0, 'total' => 1,
            'board' => 'let', 'subject' => 'english', 'completed_at' => '2026-10-28T10:00:00+00:00',
            'review' => [['question' => ['id' => 'let-taxonomy-mathematics', 'taxonomy' => ['board_id' => 'let', 'subject_id' => 'mathematics']], 'answered' => true, 'correct' => false]],
        ],
    ];
    $scopedMistakes = StudyCalendarEvidenceService::mistakeSubjectCounts($attemptEvidence, $validated);
    calendarAssert($scopedMistakes === ['english' => 2, 'mathematics' => 2],
        'Calendar mistake evidence did not prefer question-level subject provenance and count selected-board skips: ' . json_encode($scopedMistakes));

    foreach (['json', 'sqlite'] as $driver) {
        $driverPath = $fixtureRoot . '/' . $driver;
        mkdir($driverPath, 0700, true);
        $pdo = null;
        if ($driver === 'json') {
            $storage = new JsonStorage($driverPath);
        } else {
            $pdo = new PDO('sqlite:' . $driverPath . '/fixture.sqlite');
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->exec('CREATE TABLE storage_records (collection TEXT NOT NULL, record_id TEXT NOT NULL, payload TEXT NOT NULL, created_at TEXT NOT NULL, updated_at TEXT NOT NULL, PRIMARY KEY(collection, record_id))');
            $storage = new SqliteStorage($pdo);
        }
        $learnerA = 'learner-' . str_repeat($driver === 'json' ? 'a' : 'c', 32);
        $learnerB = 'learner-' . str_repeat($driver === 'json' ? 'b' : 'd', 32);
        $service = new StudyCalendarService($storage, $learnerA);
        $first = $service->saveConfiguration($input, $boards, $evidence, $today);
        calendarAssert(!$first['duplicate'] && (int) $first['plan']['revision'] === 1, $driver . ' initial plan was not persisted.');
        $reader = new StudyCalendarService($storage, $learnerA);
        calendarAssert(count((array) ($reader->current()['sessions'] ?? [])) === count($first['plan']['sessions']), $driver . ' plan did not survive a new service instance.');
        calendarAssert((new StudyCalendarService($storage, $learnerB))->current() === null, $driver . ' plan crossed learner ownership.');
        $summary = $service->dashboardSummary($today);
        calendarAssert(($summary['state'] ?? '') === 'UPCOMING' && (int) ($summary['total'] ?? 0) === count($first['plan']['sessions']), $driver . ' dashboard did not surface the next learner-owned calendar session.');

        $summaryLearner = 'learner-' . str_repeat($driver === 'json' ? 'e' : 'f', 32);
        $summaryService = new StudyCalendarService($storage, $summaryLearner);
        $mistakePlanInput = array_merge($input, ['request_id' => str_repeat('8', 32)]);
        $mistakePlan = $summaryService->saveConfiguration($mistakePlanInput, $boards, [
            'attempt_count' => 0, 'due_flashcard_count' => 0, 'mistake_subject_counts' => ['english' => 1],
        ], $today);
        $practiceTask = $mistakePlan['plan']['sessions'][0];
        $nextMistakeTask = array_values(array_filter($mistakePlan['plan']['sessions'], static fn(array $row): bool => $row['kind'] === 'MISTAKES'))[0] ?? null;
        calendarAssert(is_array($nextMistakeTask), $driver . ' scoped mistake session was not generated for dashboard routing.');
        $summaryService->updateTask([
            'task_id' => $practiceTask['task_id'], 'operation' => 'complete', 'expected_revision' => 1,
            'request_id' => str_repeat('9', 32),
        ], $today);
        $nextSummary = $summaryService->dashboardSummary($today);
        calendarAssert(($nextSummary['action'] ?? null) === ($nextMistakeTask['action'] ?? null)
            && str_starts_with((string) ($nextSummary['action'] ?? ''), '/mistake-notebook?'),
            $driver . ' dashboard discarded the valid scoped mistake-notebook action.');

        $recoveryLearner = 'learner-' . str_repeat($driver === 'json' ? '7' : '8', 32);
        $recoveryService = new StudyCalendarService($storage, $recoveryLearner);
        $initialRecoveryInput = array_merge($input, [
            'start_date' => '2026-10-26', 'exam_date' => '2026-11-25', 'rest_dates' => [],
            'expected_revision' => 0, 'request_id' => str_repeat($driver === 'json' ? 'a' : 'b', 32),
        ]);
        $initialRecovery = $recoveryService->saveConfiguration($initialRecoveryInput, $boards, $evidence, '2026-10-26');
        $missedTask = array_values(array_filter($initialRecovery['plan']['sessions'], static fn(array $row): bool => $row['scheduled_date'] === '2026-10-26'))[0] ?? null;
        calendarAssert(is_array($missedTask), $driver . ' recovery fixture had no task on its initial study day.');
        $recoveryRegenerationInput = array_merge($initialRecoveryInput, [
            'start_date' => '2026-10-27', 'daily_minutes' => 30, 'expected_revision' => 1,
            'request_id' => str_repeat($driver === 'json' ? 'c' : 'd', 32),
        ]);
        $recoveredPlan = $recoveryService->saveConfiguration($recoveryRegenerationInput, $boards, $evidence, '2026-10-27');
        $retainedMissed = array_values(array_filter($recoveredPlan['plan']['sessions'], static fn(array $row): bool => $row['task_id'] === $missedTask['task_id']))[0] ?? null;
        $activeRecovered = array_values(array_filter($recoveredPlan['plan']['sessions'], static fn(array $row): bool => in_array($row['status'] ?? 'planned', ['planned', 'deferred'], true)));
        calendarAssert(is_array($retainedMissed) && $retainedMissed['scheduled_date'] === '2026-10-26'
            && ($retainedMissed['status'] ?? '') === 'planned'
            && (int) ($retainedMissed['duration_minutes'] ?? 0) === 30, $driver . ' regeneration discarded a missed session or retained a duration above the new daily limit.');
        calendarAssert(count($activeRecovered) <= StudyCalendarService::MAX_ACTIVE_TASKS, $driver . ' overdue preservation exceeded the active-task bound.');
        calendarAssert(count(array_unique(array_column($activeRecovered, 'scheduled_date'))) === count($activeRecovered), $driver . ' regeneration introduced an active date collision.');
        foreach ($activeRecovered as $activeSession) {
            calendarAssert($activeSession['scheduled_date'] <= '2026-11-25'
                && (int) ($activeSession['duration_minutes'] ?? 0) <= 30, $driver . ' recovery regeneration exceeded the exam boundary or the new daily limit.');
        }
        $recoveryDate = StudyCalendarPlanner::suggestedRescheduleDate(
            '2026-10-26', $recoveredPlan['plan']['configuration'], $activeRecovered, $missedTask['task_id'], '2026-10-27'
        );
        calendarAssert($recoveryDate !== '' && $recoveryDate >= '2026-10-27'
            && StudyCalendarPlanner::validScheduledDate($recoveryDate, $recoveredPlan['plan']['configuration'], '2026-10-27'),
            $driver . ' recovery suggestion was not a bounded available date.');
        $occupiedRecoverySession = array_values(array_filter($activeRecovered, static fn(array $row): bool =>
            ($row['task_id'] ?? null) !== $missedTask['task_id']
            && (string) ($row['scheduled_date'] ?? '') >= '2026-10-27'
        ))[0] ?? null;
        calendarAssert(is_array($occupiedRecoverySession), $driver . ' recovery fixture did not retain any available future work.');
        $collisionDate = (string) ($occupiedRecoverySession['scheduled_date'] ?? '');
        calendarRejects(static fn() => $recoveryService->updateTask([
            'task_id' => $missedTask['task_id'], 'operation' => 'defer', 'scheduled_date' => $collisionDate,
            'expected_revision' => 2, 'request_id' => str_repeat($driver === 'json' ? 'e' : 'f', 32),
        ], '2026-10-27'), $driver . ' allowed missed-session recovery to collide with an occupied date.');
        $recoveredTaskPlan = $recoveryService->updateTask([
            'task_id' => $missedTask['task_id'], 'operation' => 'defer', 'scheduled_date' => $recoveryDate,
            'expected_revision' => 2, 'request_id' => str_repeat($driver === 'json' ? '1' : '2', 32),
        ], '2026-10-27');
        $movedMissed = array_values(array_filter($recoveredTaskPlan['sessions'], static fn(array $row): bool => $row['task_id'] === $missedTask['task_id']))[0] ?? null;
        calendarAssert(is_array($movedMissed) && $movedMissed['status'] === 'deferred'
            && $movedMissed['scheduled_date'] === $recoveryDate
            && (int) ($movedMissed['duration_minutes'] ?? 0) === 30, $driver . ' missed task could not be recovered to an available day within the current duration limit.');

        $task = $first['plan']['sessions'][0];
        $deferDate = '2026-11-13';
        calendarAssert(StudyCalendarPlanner::validScheduledDate($deferDate, $first['plan']['configuration'], $today), 'Fixture defer date was not valid.');
        $deferred = $service->updateTask([
            'task_id' => $task['task_id'], 'operation' => 'defer', 'scheduled_date' => $deferDate,
            'expected_revision' => 1, 'request_id' => str_repeat('2', 32),
        ], $today);
        $deferredTask = array_values(array_filter($deferred['sessions'], static fn(array $row): bool => $row['task_id'] === $task['task_id']))[0] ?? null;
        calendarAssert(is_array($deferredTask) && $deferredTask['status'] === 'deferred' && $deferredTask['scheduled_date'] === $deferDate, $driver . ' rescheduling did not persist.');
        $duplicateTaskUpdate = $service->updateTask([
            'task_id' => $task['task_id'], 'operation' => 'defer', 'scheduled_date' => $deferDate,
            'expected_revision' => 1, 'request_id' => str_repeat('2', 32),
        ], $today);
        calendarAssert((int) $duplicateTaskUpdate['revision'] === 2, $driver . ' repeated task update was not idempotent.');

        $secondTask = $deferred['sessions'][1];
        calendarRejects(static fn() => $service->updateTask([
            'task_id' => $task['task_id'], 'operation' => 'reschedule', 'scheduled_date' => $secondTask['scheduled_date'],
            'expected_revision' => 2, 'request_id' => str_repeat('6', 32),
        ], $today), $driver . ' allowed two active sessions on one date.');

        $thirdTask = $deferred['sessions'][2];
        $skipped = $service->updateTask([
            'task_id' => $thirdTask['task_id'], 'operation' => 'skip', 'expected_revision' => 2,
            'request_id' => str_repeat('5', 32),
        ], $today);
        $skippedTask = array_values(array_filter($skipped['sessions'], static fn(array $row): bool => $row['task_id'] === $thirdTask['task_id']))[0] ?? null;
        calendarAssert(is_array($skippedTask) && $skippedTask['status'] === 'skipped', $driver . ' skipped-session state did not persist.');

        $completed = $service->updateTask([
            'task_id' => $secondTask['task_id'], 'operation' => 'complete', 'expected_revision' => 3,
            'request_id' => str_repeat('3', 32),
        ], $today);
        $completedTask = array_values(array_filter($completed['sessions'], static fn(array $row): bool => $row['task_id'] === $secondTask['task_id']))[0] ?? null;
        calendarAssert(is_array($completedTask) && $completedTask['status'] === 'completed' && is_string($completedTask['completed_at']), $driver . ' completion progress did not persist.');
        calendarAssert((int) ($service->dashboardSummary($today)['completed'] ?? 0) === 1, $driver . ' dashboard completion summary did not preserve learner progress.');

        $changedInput = array_merge($input, [
            'expected_revision' => 4,
            'request_id' => str_repeat('4', 32),
            'exam_date' => '2026-11-30',
            'weekdays' => [1, 3, 5],
            'daily_minutes' => 30,
        ]);
        $regenerated = $service->saveConfiguration($changedInput, $boards, $evidence, $today);
        $statuses = array_column($regenerated['plan']['sessions'], 'status', 'task_id');
        $retainedEditedTask = array_values(array_filter($regenerated['plan']['sessions'], static fn(array $row): bool => $row['task_id'] === $task['task_id']))[0] ?? null;
        calendarAssert(($statuses[$task['task_id']] ?? '') === 'deferred'
            && is_array($retainedEditedTask) && (int) ($retainedEditedTask['duration_minutes'] ?? 0) === 30,
            $driver . ' regeneration discarded a learner-edited session or retained a duration above the new daily limit.');
        calendarAssert(($statuses[$secondTask['task_id']] ?? '') === 'completed', $driver . ' regeneration discarded completed progress.');
        calendarAssert(($statuses[$thirdTask['task_id']] ?? '') === 'skipped', $driver . ' regeneration discarded skipped progress.');
        calendarAssert((int) $regenerated['plan']['revision'] === 5 && $regenerated['retained_user_edits'] >= 1, $driver . ' calendar regeneration did not report preserved edits.');
        foreach ($regenerated['plan']['sessions'] as $session) {
            if (in_array($session['status'], ['completed', 'skipped'], true)) continue;
            calendarAssert((int) ($session['duration_minutes'] ?? 0) <= 30, $driver . ' active task exceeded the new daily study-minute limit.');
            calendarAssert($session['scheduled_date'] <= '2026-11-30', $driver . ' regeneration exceeded a changed exam date.');
            calendarAssert((int) (new DateTimeImmutable($session['scheduled_date']))->format('N') !== 3 || $session['scheduled_date'] !== '2026-11-04', 'Rest day survived a regenerated plan.');
            if (($session['user_modified'] ?? false) !== true) {
                calendarAssert(in_array((int) (new DateTimeImmutable($session['scheduled_date']))->format('N'), [1, 3, 5], true), $driver . ' regenerated an untouched session on a newly unavailable weekday.');
            }
        }
        $retry = $service->saveConfiguration($changedInput, $boards, $evidence, $today);
        calendarAssert($retry['duplicate'] && (int) $retry['plan']['revision'] === 5, $driver . ' duplicate plan request created a second revision.');
    }

    $definitions = AccountDataInventoryService::collectionDefinitions();
    calendarAssert(($definitions[StudyCalendarService::COLLECTION]['export_name'] ?? null) === 'study_calendar', 'Account export inventory omitted the study calendar.');
    $accountId = 'account-' . str_repeat('a', 32);
    $accountLearner = 'account-learner-' . str_repeat('a', 32);
    $restoreCalendar = ['id' => 'study-plan-' . str_repeat('b', 32), 'user_id' => $accountLearner, 'schema_version' => 1, 'revision' => 1, 'configuration' => [], 'sessions' => []];
    $accountCollections = [
        'quiz_attempts' => [], 'weakness_summaries' => [], 'flashcard_reviews' => [],
        'learner_notes' => [], 'question_issue_reports' => [], 'study_calendar' => [$restoreCalendar],
    ];
    $names = array_keys($accountCollections);
    $counts = array_map('count', $accountCollections);
    $counts['credential_profile'] = 1;
    $manifestNames = [...$names, 'credential_profile'];
    AccountRestoreService::validatePayload([
        'payload_schema_version' => 1,
        'manifest' => [
            'format' => 'boardprep-account-backup-payload-v1', 'created_at' => gmdate(DATE_ATOM),
            'included_collections' => $manifestNames, 'record_counts' => $counts,
            'collection_schema_versions' => array_fill_keys($manifestNames, 1),
        ],
        'account' => [
            'account_id' => $accountId, 'learner_id' => $accountLearner, 'username' => 'calendar-fixture',
            'password_hash' => password_hash('fixture-password', PASSWORD_DEFAULT), 'created_at' => gmdate(DATE_ATOM),
            'updated_at' => gmdate(DATE_ATOM), 'auth_version' => 1, 'status' => 'active', 'preferences' => [], 'recovery_code_hashes' => [],
        ],
        'collections' => $accountCollections,
    ]);
    echo "[PASS] Deterministic generation, missed-session recovery, subject-scoped due cards, workload limits, exam/rest-day bounds, transparent evidence reasons, collision/idempotency controls, ownership, JSON/SQLite persistence, progress preservation, and account restore compatibility.\n";
} finally {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($fixtureRoot, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($iterator as $entry) {
        if ($entry->isDir() && !$entry->isLink()) @rmdir($entry->getPathname());
        else @unlink($entry->getPathname());
    }
    @rmdir($fixtureRoot);
}
