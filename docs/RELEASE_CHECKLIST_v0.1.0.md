# BoardPrep MVP v0.1.0 — Release Candidate Checklist

## Before tag

- [ ] Intended Git state is clean; allowed local files remain unstaged.
- [ ] QuizTest, HTTP regression, runtime/session checks, and developer tests pass.
- [ ] Canonical simulation reports `SCENARIOS=7`, `PASS=7`, `FAIL=0`, and
      `SIMULATION_STATUS=PASS`; all six personas pass.
- [ ] Deployment/runtime sanity is green: PHP/extensions, config, sessions,
      writable storage, public entry point, secrets, and production errors.
- [ ] Scope, release notes, checklist, and version metadata are current.
- [ ] Release blockers and critical findings are zero.
- [ ] Human acceptance is recorded honestly as not executed / assumed pass by
      release decision.

## Tag (do not execute automatically)

```sh
git tag -a v0.1.0 -m "BoardPrep MVP v0.1.0"
```

## Push (release owner executes)

```sh
git push origin main
git push origin v0.1.0
```

## Post-deploy smoke

- [ ] Load the app and complete a learner smoke quiz.
- [ ] Open developer smoke surfaces in the non-production operator environment.
- [ ] Confirm storage is writable and session creation/reload works.
- [ ] Check server logs and error responses for unexpected failures.
