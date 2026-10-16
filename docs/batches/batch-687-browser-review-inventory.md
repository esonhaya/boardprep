# Batch 687 browser acceptance inventory

## Exact-commit run

- Workflow: [Browser acceptance, run 38931866113](https://github.com/esonhaya/boardprep/actions/runs/38931866113)
- Exact commit: `e314a99956b846950a5cb97d56f1b9f49f3999ef`
- Run status: **PASS**, 4m 50s.
- Browser job: completed successfully.
- Artifact: `boardprep-chromium-visual-qa`, shown by GitHub as 124 MB, retained remotely. It was not downloaded because the Termux filesystem was below the 1.5 GiB floor.

## Evidence limits

The workflow used its configured desktop (`1366×768`) and mobile (`390×844`) browser viewports and completed without a failed job. The public run page does not expose per-scenario assertion counts; those remain **NOT_ACCESSIBLE** here. The remote screenshot, trace, console, and result artifact was not manually inspected. This is automated browser evidence, not human visual or accessibility acceptance.

## Related release gates

The same exact commit was evaluated by [Release gates, run 38931866048](https://github.com/esonhaya/boardprep/actions/runs/38931866048). The security/privacy matrix (six tests), ten-board learner HTTP journey, and production preflight/smoke passed. Full Doctor release and formal Product Readiness failed. See [the gate evidence](../release-evidence/batch-687-release-gates.json).
