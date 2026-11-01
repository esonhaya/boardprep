# Batch 736 independent acceptance runbook

This runbook prepares browser and encrypted-account-backup acceptance on a disposable supported Linux host. It does not authorize deployment or public release. Use synthetic accounts only. Never copy production learner data to this host.

## Browser acceptance on Linux

### Prerequisites

- Ubuntu 24.04 x86_64 (the same runner class as `.github/workflows/browser-acceptance.yml`).
- A clean clone/checkout of the exact commit under review. The browser fixture is created from `git archive HEAD`; a dirty checkout cannot produce `BROWSER_EXECUTED_PASS`.
- PHP 8.3 with `json`, `session`, `pdo_sqlite`, and `sodium` extensions; Git and `tar`.
- Node.js `22.23.3`, npm, network access to the npm registry and Playwright browser CDN, and permission to install the Chromium system dependencies.
- Enough temporary storage for Playwright's pinned Chromium build and traces/screenshots. The browser package must be the version from `package-lock.json` (`@playwright/test` 1.63.0).

The web server binds only to loopback. It starts disposable PHP servers, copies committed canonical storage into temporary locations, replaces learner collections with empty fixtures, and creates synthetic account/reviewer fixtures. The fixture is removed after child servers exit. The test workflow has a 15-minute job timeout; Playwright tests have bounded per-test and server-start timeouts and do not retry.

### Commands

Run from the repository root after checking out the exact tested commit:

```sh
set -eu
test "$(node --version)" = "v22.23.3"
php -r 'if (PHP_VERSION_ID < 80300 || PHP_VERSION_ID >= 80400) { fwrite(STDERR, "PHP 8.3 is required\n"); exit(2); } foreach (["json", "session", "pdo_sqlite", "sodium"] as $extension) { if (!extension_loaded($extension)) { fwrite(STDERR, "Missing PHP extension: {$extension}\n"); exit(2); } }'
test -z "$(git status --porcelain)"
git rev-parse HEAD

evidence_dir="$(mktemp -d "${TMPDIR:-/tmp}/boardprep-browser-acceptance.XXXXXX")"
chmod 700 "$evidence_dir"
export BOARDPREP_BROWSER_EVIDENCE_DIR="$evidence_dir"

timeout 300s npm ci
timeout 300s npm run test:browser:contract
timeout 300s php tools/Tests/BackupManifestPathSafetyTest.php
timeout 300s php tools/Tests/ProductionQuizCsrfTest.php
timeout 300s php tools/Tests/AnonymousLearnerPrivacyHttpTest.php
timeout 300s php tools/Tests/SessionConfigurationTest.php
timeout 300s php tools/Tests/SecurityTrustBoundaryTest.php
timeout 300s php tools/Tests/FlashcardControllerReadPathTest.php
timeout 300s php tools/Tests/FlashcardHttpTest.php
timeout 300s npm run test:browser:fixture
timeout 1200s npx playwright install --with-deps chromium
timeout 60s node tools/browser-preflight.cjs > "$evidence_dir/browser-preflight.json"
CI=1 timeout 900s npm run test:browser
node tools/summarize-browser-results.cjs
node tools/collect-browser-artifacts.cjs
node tools/write-browser-ci-manifest.cjs
```

The preflight must report `PASS` before interpreting a browser result. Review `test-results/browser-execution.json`, `browser-ci-manifest.json`, the JSON/HTML Playwright reports, screenshots, traces, and captured browser diagnostics in the evidence directory. A complete browser PASS requires both desktop and mobile Chromium projects, no failures or flaky tests, the expected responsive journeys, and an exact tested SHA with a clean worktree. Preserve the evidence directory and hash it. A blocked preflight, contract-only test, partial project, missing artifact, or failed test is not browser acceptance.

Do not put passwords or recovery codes on command lines. Test account values are synthetic and supplied by the fixtures; do not print them in logs. If a run is interrupted, let the Playwright web server stop its children before removing temporary files. The test's lifecycle contract exercises bounded readiness and cleanup.

## Encrypted account-backup acceptance with PHP sodium

### Prerequisites

- Ubuntu 24.04 x86_64 and PHP 8.3 with `sodium`, `json`, `session`, `pdo_sqlite`, and `mbstring`; Git and a clean checkout of the exact tested commit.
- ext-sodium must provide `sodium_crypto_pwhash`, XChaCha20-Poly1305 encrypt/decrypt, and `sodium_memzero`. Missing sodium is an environment block. The existing test deliberately accepts a safe `DEPENDENCY_MISSING` response in that case; that branch is not round-trip acceptance.
- Synthetic fixture data only. Tests create private temporary stores and clean them on completion. The JSON and SQLite source-store cases restore into a new isolated JSON destination; they do not activate or overwrite an account.

### Commands

Run from the repository root:

```sh
set -eu
test "$(php -r 'echo PHP_MAJOR_VERSION, ".", PHP_MINOR_VERSION;')" = "8.3"
timeout 30s php -r 'if (!extension_loaded("sodium") || !function_exists("sodium_crypto_pwhash") || !function_exists("sodium_crypto_aead_xchacha20poly1305_ietf_encrypt") || !function_exists("sodium_crypto_aead_xchacha20poly1305_ietf_decrypt") || !function_exists("sodium_memzero")) { fwrite(STDERR, "PHP ext-sodium with the required primitives is mandatory\n"); exit(2); } if (!extension_loaded("pdo_sqlite")) { fwrite(STDERR, "PHP pdo_sqlite is mandatory for the storage matrix\n"); exit(2); }'
test -z "$(git status --porcelain)"
git rev-parse HEAD

timeout 300s php tools/Tests/AccountBackupTest.php
timeout 300s env BOARDPREP_BACKUP_TEST_DRIVER=sqlite php tools/Tests/AccountBackupTest.php
timeout 300s php tools/Tests/AccountBackupCliTest.php
timeout 300s php tools/Tests/AccountLifecycleJsonTest.php
timeout 300s php tools/Tests/AccountLifecycleSqliteTest.php
```

The synthetic backup test checks successful authenticated encryption/decryption and isolated restore, passphrase rejection, ciphertext/header tampering, truncation, unsupported format, ownership mismatch, path/symlink safety, collision handling, and deletion/backup concurrency. The CLI test checks descriptor-fed secrets and safe output. Preserve stdout, stderr, exit codes, runtime versions, tested commit SHA, and artifact hashes. Only a successful sodium-enabled round-trip on both source storage drivers can be reported as `ENCRYPTED_BACKUP_ROUNDTRIP=PASS`.

## Production configuration review

Use the repository's production-mode preflight on a supported host with an isolated copy of storage and the deployment's intended PHP configuration. Set `APP_ENV=production`, `APP_DEBUG=false`, a valid `APP_TIMEZONE`, supported `DB_DRIVER`, and absolute writable paths outside the document root. Confirm `.env` permissions and ensure it is outside the web root. Do not expose secrets in logs or test output.

When HTTPS terminates at a proxy, configure the web server/PHP SAPI so `$_SERVER['HTTPS']` accurately reflects the externally encrypted request. The application intentionally does not trust an arbitrary `X-Forwarded-Proto` header. Verify the actual Secure cookie and HSTS headers through that host before any public use; local preflight cannot establish that evidence. The release package does not include production storage or `.env`.

## Result and provenance rules

- Record the exact `git rev-parse HEAD`, runtime versions, start/end UTC, command, exit status, and SHA-256 of every retained result.
- Preserve failures, stderr, test reports, traces, and screenshots. Do not overwrite prior evidence.
- A local report must identify itself as local. It is not a GitHub workflow registration or public-release authorization.
- Browser contract tests do not substitute for a real browser run. Sodium fail-closed tests do not substitute for a successful encrypted round-trip. Technical gates do not substitute for qualified academic review.
- Keep the release decision and public-release authorization separate. This procedure cannot authorize a release.
