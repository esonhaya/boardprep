# Owner privacy and hosting decisions

Complete and approve this sheet before a public launch. Blank fields are
unresolved; this document does not itself constitute legal approval. Do not
publish example values or promises that have not been verified with the actual
operator and provider.

## Owner decisions

| Decision | Owner-approved value/evidence |
|---|---|
| Operator identity (person or legal entity) | `OWNER TO COMPLETE` |
| Public learner contact method and monitored address | `OWNER TO COMPLETE` |
| Privacy-notice reviewer and approval date | `OWNER TO COMPLETE` |
| Application learner-state retention period | `OWNER TO DECIDE; include deletion/expiry behavior` |
| Server access/error log fields and retention | `VERIFY PROVIDER; include IP/user-agent if collected` |
| Backup frequency, location, access, and retention | `VERIFY PROVIDER; application deletion does not erase backups` |
| Hosting provider, account region, and data location | `VERIFY LIVE HOST` |
| HTTPS certificate, redirect, renewal, and proxy behavior | `VERIFY LIVE HOST; require HTTPS for public use` |
| Learner deletion limitation wording | `OWNER TO REVIEW against logs, backups, and provider copies` |
| Final privacy/terms approval | `NOT APPROVED` |

## Verified application behavior (not provider promises)

- BoardPrep does not require a learner account. A random learner identity is
  tied to a PHP browser session; it does not provide account recovery or
  cross-device synchronization.
- The application stores completed attempts and derived learning/progress
  state, and provides a session-scoped export and deletion control.
- The application cannot guarantee deletion of hosting access/error logs,
  external backups, or copies retained outside its own mutable storage.
- PHP session cookies are configured HttpOnly and SameSite=Lax. The Secure
  flag depends on HTTPS state being correctly passed to PHP by the live host.
- Actual log fields/retention, backup behavior, region, TLS, and provider
  handling remain unverified until the owner checks the selected hosting
  account and deployment.

## Release handoff

Attach provider documentation or owner observations for each provider-dependent
field. Update the production privacy notice only with approved, evidence-backed
values. Re-run the privacy and production readiness checks after configuration;
do not mark owner approval or live HTTPS as complete based on local tests.

See [human acceptance instructions](HUMAN_TESTER_READINESS.md) for the safe
same-device Android preview. It binds only to loopback and is not a public or
provider deployment test.
