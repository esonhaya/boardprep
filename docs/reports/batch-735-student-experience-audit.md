# Batch 735 Student Experience Audit

Audit date: 2026-11-01 UTC (worktree local date: 2026-11-02 Asia/Manila)
Baseline: `bfcfde035581d6fa49b2b57e84fb023f836e7701`
Branch: `batch-735-student-experience-learning-intelligence`
Worktree: `/data/data/com.termux/files/home/boardprep-batch-706`

This audit records the application behavior found before Batch 735 implementation. `COMPLETE` means the inspected flow has a server-side implementation and direct regression coverage; it does not imply rendered browser acceptance. `UNVERIFIED` is used for behavior that requires a supported browser or assistive technology run.

## Findings

| Area | Status | Existing behavior and evidence |
|---|---|---|
| Learner dashboard | COMPLETE | Shows learner-owned calendar next-session state, recent activity, and progress summaries. Calendar summary ownership and dashboard rendering are covered by `StudyCalendarAcceptanceTest` and `StudyCalendarReadinessHttpTest`. |
| Study calendar | PARTIAL | Generates deterministic sessions from board subjects, available weekdays, rest dates, daily minutes, evidence, and exam-date bounds. It supports completion, skip, defer, collision rejection, idempotent edits, regeneration, and completed-work preservation in JSON and SQLite. Two missed-session recovery defects are confirmed below. |
| Study recommendations | PARTIAL | Calendar reasons identify due-card, mistake, topic-focus, or practice/rotation evidence. Focus details include recorded average and attempt count. Flashcard evidence is currently board-scoped while the generated action is subject-scoped, so a due-review task can open an empty subject queue (confirmed defect below). Exam date caps the planning window; no exam-proximity academic priority is inferred. |
| Practice exams | COMPLETE | Server-side answer validation, scoring, persisted result ownership, repeat submission, and history result review are covered by `QuizCompletionHttpTest`, `HistoryResultHttpTest`, and `ProductionQuizCsrfTest`. |
| Timed exams | COMPLETE | Timer state is session-backed, remaining time is computed server-side, and expiration routes through normal submission. Timer expiry and resume behavior have dedicated exam-session tests. |
| Question navigation | COMPLETE | Exam review exposes question position, answer state, mark state, and per-question jump forms. Current-position and question-ID checks protect navigation; exam recovery contracts are tested. |
| Flagged and unanswered questions | COMPLETE | Review marks are versioned and bound to the current question set. The review page counts recorded answers and marks, labels unanswered questions, and explains submission behavior. Flags are carried into saved result review without reconstructing unavailable legacy flags. |
| Exam results | COMPLETE | Saved score and answer snapshots drive result review; incorrect, unanswered, correct, and marked filters are available. Server scoring and attempt-bound question reporting are covered. |
| Question explanations and source indicators | PARTIAL | Existing explanations and registered study references are rendered from canonical question snapshots. Missing explanation text currently has no explicit learner-facing fallback; source associations without exact mappings are identified as unavailable or under review. No academic content or source was changed for this audit. |
| History and performance trends | COMPLETE | History is owner-scoped, filterable, newest-first, and paginated. Progress distinguishes answer accuracy from quiz score, reports answer-event counts, labels limited evidence, and disclaims official pass prediction. Existing history and readiness tests cover aggregation and context. |
| Learning insights and weak-area analysis | PARTIAL | Existing metrics use saved answered question events, show recent accuracy and score trends, and identify repeated misses. A subject is `LIMITED_EVIDENCE` until it has at least 2 completed sessions and 5 answered events; no accuracy label is assigned below that threshold. Dashboard calendar completion is visible. Flashcard due evidence lacked subject-level attribution, causing C-3. |
| Mistake notebook | COMPLETE | Wrong and skipped responses retain separate classifications and answer snapshots, board/subject/topic filters are supported, retry uses the ordinary quiz scoring path, and learner note/report links remain scoped. `Batch712MistakeRecoveryTest` and Batch 734 workflow tests cover the flows. |
| Flashcard scheduling | COMPLETE | Due-state updates are idempotent and owner-scoped; subject filters and due counts are covered by `FlashcardHttpTest`, `FlashcardFoundationTest`, and `FlashcardSelectionOptimizationTest`. Calendar integration has the subject-evidence defect above. |
| Question reporting | COMPLETE | Reports require a learner-owned completed attempt and a question from its saved snapshot; deletion locks and reviewer history are covered by Batch 734 report workflow tests. Reports do not change canonical answers. |
| Mobile layout and accessibility | PARTIAL / UNVERIFIED | Static view checks cover semantic labels, form labels, focus helpers, and key learner actions. A supported browser and assistive-technology run is unavailable in this Android/arm64 Termux environment, so rendered overflow, touch targets, screen-reader announcements, and reduced-motion behavior remain unverified. |
| Empty and error states | PARTIAL | Dashboard, calendar, history, flashcard, and report flows provide explicit empty or fail-closed states. The calendar's past-date recovery suggestion is invalid for the date input, and missing explanation text has no explicit fallback. |

## Confirmed defects and proposed improvements

### C-1 — Regeneration discards untouched overdue sessions (P2)

- **Reproduction:** Generate a calendar with a task on a future weekday. Advance `today` past that date without editing or completing the task. Save a new configuration with the current revision and a new request ID.
- **Observed behavior:** `StudyCalendarService::mergeGeneratedSessions()` retains completed/skipped history and learner-edited future work, but not an untouched `planned`/`deferred` session scheduled before `today`.
- **Impact:** A missed session disappears on regeneration without being completed or explicitly skipped, so the learner loses the recovery prompt and the plan understates unfinished work.
- **Root cause:** The merge partitions previous sessions into terminal history and user-edited future sessions, then merges newly generated future sessions; it has no overdue/backlog partition.
- **Source paths:** `app/Services/Learning/StudyCalendarService.php` (`mergeGeneratedSessions()`); `app/Views/study/calendar.php`.
- **Existing coverage gap:** `StudyCalendarAcceptanceTest` tests preservation of completed/skipped and edited future tasks, but has no overdue regeneration case.
- **Acceptance criteria:** Preserve overdue active tasks through regeneration; make their overdue state clear; allow a bounded move to an available date; prevent collisions; preserve completion/skip history and idempotency on JSON and SQLite.

### C-2 — Missed-session date suggestion may be in the past (P2)

- **Reproduction:** Set `today` to `2026-11-01`, use Monday–Friday availability, and render an active task originally scheduled for `2026-10-26`.
- **Observed behavior:** The form's date input has `min="2026-11-01"`, but `nextAvailable()` searches forward from the old session date and can choose `2026-10-27` as its value.
- **Impact:** The prefilled recovery date is invalid and the learner must notice and replace it before saving.
- **Root cause:** The suggestion helper considers the old scheduled date but does not clamp its search to today.
- **Source paths:** `app/Views/study/calendar.php` (`$nextAvailable` and the session date form).
- **Existing coverage gap:** `StudyCalendarReadinessHttpTest` checks for the date control, not the generated default value for overdue tasks.
- **Acceptance criteria:** The suggested date is on or after today, honors configured weekdays/rest days and exam date, and excludes occupied dates.

### C-3 — Board-level due-card evidence can create a subject queue with no due cards (P2)

- **Reproduction:** Have at least one due card in subject A and none in subject B for the selected board; use a plan where subject rotation assigns the next flashcard task to B.
- **Observed behavior:** The controller records a board-wide `due_flashcard_count`; the planner selects the rotating subject; the task action then filters `/flashcards/review` to that subject.
- **Impact:** The learner can receive a due-review recommendation whose filtered queue is empty while another subject owns the due cards.
- **Root cause:** Due evidence has no per-subject counts, although the generated navigation is subject-scoped.
- **Source paths:** `app/Controllers/StudyCalendarController.php`; `app/Services/Flashcard/FlashcardService.php`; `app/Services/Learning/StudyCalendarPlanner.php`.
- **Existing coverage gap:** `StudyCalendarAcceptanceTest` confirms that the action preserves its assigned board and subject, but does not validate that the subject has due evidence.
- **Acceptance criteria:** Derive due counts by subject in the existing bounded card-selection pass; only recommend review for a subject with due cards; keep the action scope and reason consistent with that evidence.

### C-4 — Retained work can exceed a newly lowered daily limit (P2)

- **Reproduction:** Generate a plan at 90 minutes per day, defer a task so it is learner-edited, then change the plan to 30 minutes per day and regenerate.
- **Observed behavior:** Regeneration retained the task's old `duration_minutes` even though newly generated tasks used the new 30-minute limit. The same issue applied to preserved overdue work.
- **Impact:** The learner can be shown an active task above the newly selected daily study-time limit.
- **Root cause:** `mergeGeneratedSessions()` preserved the task record without refreshing its configuration-derived duration.
- **Source path:** `app/Services/Learning/StudyCalendarService.php` (`mergeGeneratedSessions()`).
- **Existing coverage gap:** Changed-settings tests preserved task status and dates, but did not lower daily minutes or compare retained active durations to the new setting.
- **Acceptance criteria:** Preserve task identity, date, and status while updating active retained work to the new daily duration; leave completed/skipped history unchanged.

## Proposed and unverified work

| Proposal | Existing behavior | Learner benefit |
|---|---|---|
| Explain rotation priority in each practice recommendation | Practice sessions follow configured low/normal/high subject weights. Batch 735 now names the selected priority and says the assignment is not a measured weakness. | Learners can see why a subject is on today's plan without attributing an unsupported weakness. |
| Make missing explanation content explicit | Empty explanation strings previously rendered blank. Batch 735 now shows an explicit availability message in immediate feedback and saved results. | Learners can distinguish unavailable explanation content from a rendering failure without inventing academic material. |
| Confirm mobile and assistive behavior in a supported environment | Static contracts are available; real-browser acceptance has not run. | Validate actual narrow-screen layout, keyboard focus, announcements, and reduced motion. |
| Add exam-proximity context without unsupported prediction | The exam date bounds generation; Batch 735 now explains that tasks stop on or before the target date and does not change the ranking of academic subjects. | The calendar makes its date boundary visible while keeping subject priorities evidence-based. |

## Batch 735 implementation outcomes

- **C-1 fixed:** Calendar regeneration now preserves active overdue sessions alongside completed/skipped history and future learner-edited work. The existing 28-active-task bound applies to preserved overdue work plus newly generated tasks. JSON and SQLite regression cases cover preservation, bounded active count, collision checks, and recovery.
- **C-2 fixed:** The planner's recovery-date suggestion starts at today for overdue work, then honors availability, rest dates, active-session collisions, and the exam date. A focused regression case proves the suggested date skips a collision and a rest day.
- **C-3 fixed:** Flashcard selection now reports due counts by subject as part of its existing card-state pass. Calendar generation places at most one due-review task per subject with due cards, attributes the action to that subject, and explains the observed due count. A narrow exam window exposes due subjects that did not fit instead of adding work beyond the available dates.
- **C-4 fixed:** Calendar regeneration updates retained active overdue and learner-edited tasks to the newly selected daily duration while preserving their identity, date, and status. Completed and skipped history keeps its recorded duration.
- **Recommendation transparency improved:** Practice reasons include the selected priority or supported topic average and attempt count; mistake reasons include the saved wrong/skipped count; calendar text explains the target-exam date boundary.
- **Missing explanation copy fixed:** Practice feedback and saved result review explicitly identify an unavailable explanation without supplying academic content.
- **Calendar verification:** `StudyCalendarAcceptanceTest`, `StudyCalendarReadinessHttpTest`, and `FlashcardSelectionOptimizationTest` passed after the calendar changes. Their coverage includes deterministic rotation, due subject queues, missed recovery, collisions, date/rest bounds, daily duration limits, task capacity, regeneration, ownership, JSON/SQLite persistence, and account restore compatibility.
- **Exam and analytics audit:** Existing server-scored exam, timer, resume, navigation, flags, unanswered review, exact-once finalization, answer accuracy, evidence thresholds, repeated mistakes, and history pagination paths have direct integration or contract coverage. Batch 735 made no scoring, eligibility, account-isolation, or academic-content changes.
- **Mobile status:** Static markup/accessibility checks passed after Batch 735 updates. Rendered mobile, screen-reader, reduced-motion, and real-browser acceptance remain `UNVERIFIED` in this environment.
- **Performance:** The subject due-count grouping is added to the existing bounded iteration over already-derived cards. It is not a performance optimization; no speedup claim or before/after benchmark is made.

No browser, visual, or assistive-technology acceptance is claimed by this code and contract audit.
