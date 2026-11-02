# Batch 739 Doctor failure reproduction

```text
BASELINE_SHA=50f716ccf7cf2d43ce710f08a35e660306c1dc29
PRE_FIX_SHA=6d8898e985b616221eb053cac036e2abfa065742
BRANCH=batch-739-exam-session-resilience
WORKTREE=/data/data/com.termux/files/home/boardprep-batch-706
COMMAND=tools/doctor.php --release --json
EXIT_CODE=1
```

## Confirmed failure

The full Doctor run reported one P0 release gate in `SIMULATOR_RECOVERY`. Its focused evidence shows `ExamSimulationHttpTest.php` and `SimulationFixtureLifecycleTest.php` passing, while `DoctorSimulatorStateIsolationTest.php` failed the simulated quiz lifecycle:

```text
Expected successful response, received HTTP 303.
Expected response to contain "Question 2 / 3".
Expected response to contain "Exam Simulation Result".
Practice and exam completions were not persisted exactly once each (new=1, exam=0, types=quiz).
```

The full Doctor artifact is `batch-739-full-doctor-20261102T144429Z.json`; it records a deterministic P0 `SIMULATOR_RECOVERY` blocker.

## Root cause

The quiz now correctly requires its server-issued session ID on exam form POSTs. The standalone `HttpSimulator` captures the hidden value from rendered HTML and binds it to later requests. `ApplicationSimulator`, used by the Doctor lifecycle scenario, constructed a new `HttpSimulator` for every request, discarding that per-cookie captured value. The scenario's browser-like POST therefore lacked the hidden field and was redirected as a stale or invalid form.

This is a simulator request-context defect. The production route and standalone exam HTTP simulation already enforce the binding successfully.

## Regression test added before the fix

`tools/Tests/ApplicationSimulatorQuizSessionBindingTest.php` exercises `ApplicationSimulator` against an isolated synthetic entry point. It renders a quiz session token and verifies that the next POST carries that token. The test is expected to fail before the simulator fix.

The new test failed before the fix with:

```text
The simulated browser did not preserve its rendered quiz session token across requests: received-quiz-session=missing
```

## Fix and verification

`ApplicationSimulator` now retains one `HttpSimulator` for its request sequence. This preserves the existing per-cookie rendered-form binding; the server-side session ID check remains authoritative.

After the fix:

```text
ApplicationSimulatorQuizSessionBindingTest.php: PASS
ExamSimulationHttpTest.php: PASS
ApplicationSimulator.php PHP lint: PASS
```

The full Doctor must be rerun against the committed fix before the local technical gate can pass.
