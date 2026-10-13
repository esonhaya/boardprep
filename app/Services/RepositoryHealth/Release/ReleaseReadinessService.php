<?php

declare(strict_types=1);

namespace App\Services\RepositoryHealth\Release;

use App\Services\RepositoryHealth\DTO\HealthReport;

/**
 * Release-facing projection over RepositoryHealthEngine and bounded product
 * contract tests.  It deliberately does not infer editorial quality from
 * question counts and it never mutates application data.
 */
final class ReleaseReadinessService
{
    /** @var string[] */
    private const CRITICAL_FLOW_DOMAINS = [
        'LEARNER_CORE_FLOWS', 'ATTEMPT_SAFETY', 'RESULT_REVIEW', 'PROGRESS_STATE',
        'HISTORY', 'SIMULATOR_RECOVERY', 'ADMIN_CORE_FLOWS', 'SHARED_QUESTION_SAFETY',
        'STUDY_TRACEABILITY', 'FLASHCARD_INTEGRITY', 'CALCULATION_INTEGRITY',
        'ELIGIBILITY_INTEGRITY', 'SECURITY_SANITY',
    ];

    /** @param array<string, mixed> $flowEvidence @param array<string, mixed> $performance @param array<string, mixed>|null $production */
    public static function audit(HealthReport $report, array $flowEvidence = [], array $performance = [], ?array $production = null): array
    {
        $summary = $report->summary;
        $issues = array_map(static fn($issue): array => $issue->toArray(), $report->issues);
        $failIssues = array_values(array_filter($issues, static fn(array $issue): bool => ($issue['status'] ?? '') === 'FAIL'));
        $readinessByBoard = (array) ($summary['source_readiness_by_board'] ?? []);
        $hardSource = 0;
        $hardStudy = 0;
        $hardTraceability = 0;
        $hardCurrentness = 0;
        $reviewSignals = 0;
        foreach ($readinessByBoard as $readiness) {
            if (!is_array($readiness)) continue;
            $defects = (array) ($readiness['source_study_defects'] ?? []);
            $hardSource += (int) ($defects['source'] ?? 0);
            $hardStudy += (int) ($defects['study'] ?? 0);
            $hardTraceability += (int) ($defects['traceability'] ?? 0);
            $hardCurrentness += (int) ($defects['currentness'] ?? 0);
            $reviewSignals += array_sum(array_map('intval', (array) ($readiness['source_study_reviews'] ?? [])));
        }
        $currentness = (array) ($summary['currentness'] ?? []);
        $futureLeakage = (int) ($currentness['future_effective_leakage'] ?? 0);
        $healthFail = (int) ($summary['fail_count'] ?? 0);
        $flashcards = (array) ($summary['flashcards'] ?? []);
        $flashcardDefects = (int) ($flashcards['nonderivable_defect'] ?? 0)
            + (int) ($flashcards['duplicate_card_key'] ?? 0)
            + (int) ($flashcards['malformed_card'] ?? 0)
            + (int) ($flashcards['broken_card_source'] ?? 0);
        $calculationDefects = self::issueCount($issues, static fn(array $issue): bool => str_contains((string) ($issue['code'] ?? ''), 'CALCULATION_METADATA'));
        $eligibilityDefects = self::issueCount($issues, static fn(array $issue): bool => ($issue['category'] ?? '') === 'ELIGIBILITY' && ($issue['status'] ?? '') === 'FAIL');
        $taxonomyDefects = self::issueCount($issues, static fn(array $issue): bool => ($issue['category'] ?? '') === 'TAXONOMY' && ($issue['status'] ?? '') === 'FAIL');
        $sourceIssueDefects = self::issueCount($issues, static fn(array $issue): bool => in_array(($issue['category'] ?? ''), ['SOURCE_STUDY', 'SOURCE_READINESS'], true) && ($issue['status'] ?? '') === 'FAIL');

        $domains = [];
        $domains['CONTENT_INTEGRITY'] = self::domain(
            $healthFail > 0 ? 'FAIL' : 'PASS',
            ['repository_health_failures' => $healthFail],
            $healthFail > 0 ? 'Repository Health has deterministic failures.' : 'No deterministic repository integrity failure was reported.'
        );
        $domains['TAXONOMY_INTEGRITY'] = self::domain(
            $taxonomyDefects > 0 ? 'FAIL' : (self::issueCount($issues, static fn(array $issue): bool => ($issue['category'] ?? '') === 'TAXONOMY' && ($issue['status'] ?? '') === 'REVIEW') > 0 ? 'REVIEW' : 'PASS'),
            ['failures' => $taxonomyDefects],
            'Taxonomy parent and placement integrity is projected from Repository Health.'
        );
        $domains['SOURCE_TRACEABILITY'] = self::domain(
            ($hardSource + $sourceIssueDefects) > 0 ? 'FAIL' : ($reviewSignals > 0 ? 'REVIEW' : 'PASS'),
            ['hard_source_defects' => $hardSource, 'source_readiness_failures' => $sourceIssueDefects, 'review_signals' => $reviewSignals],
            'Hard source defects block release; semantic fit and scope uncertainty remain review signals.'
        );
        $domains['STUDY_TRACEABILITY'] = self::domain(
            ($hardStudy + $hardTraceability) > 0 ? 'FAIL' : 'PASS',
            ['hard_study_defects' => $hardStudy, 'hard_traceability_defects' => $hardTraceability],
            'Question-to-study and study-to-source chains are projected from the indexed health snapshot.'
        );
        $domains['FLASHCARD_INTEGRITY'] = self::domain(
            $flashcardDefects > 0 ? 'FAIL' : 'PASS',
            ['hard_defects' => $flashcardDefects, 'justified_exclusions' => (int) ($flashcards['justified_exclusion'] ?? 0)],
            'Eligible questions must derive valid, uniquely keyed cards or carry an explicit exclusion.'
        );
        $domains['CALCULATION_INTEGRITY'] = self::domain(
            $calculationDefects > 0 ? 'FAIL' : 'PASS',
            ['contradictions' => $calculationDefects],
            'Calculation metadata contradictions are release-blocking.'
        );
        $domains['ELIGIBILITY_INTEGRITY'] = self::domain(
            $eligibilityDefects > 0 ? 'FAIL' : 'PASS',
            ['failures' => $eligibilityDefects],
            'Effective eligibility and duplicate-candidate checks are release-critical.'
        );
        $domains['CURRENTNESS'] = self::domain(
            $futureLeakage > 0 ? 'FAIL' : (($hardCurrentness + (int) ($currentness['review_required'] ?? 0)) > 0 ? 'REVIEW' : 'PASS'),
            ['future_effective_leakage' => $futureLeakage, 'readiness_currentness_defects' => $hardCurrentness, 'question_review_required' => (int) ($currentness['review_required'] ?? 0)],
            'Currentness is explicit; future-effective material cannot answer a current question.'
        );
        $domains['LEARNER_CORE_FLOWS'] = self::flowDomain($flowEvidence, 'learner_core_flows');
        $domains['ATTEMPT_SAFETY'] = self::flowDomain($flowEvidence, 'attempt_safety');
        $domains['RESULT_REVIEW'] = self::flowDomain($flowEvidence, 'result_review');
        $domains['PROGRESS_STATE'] = self::flowDomain($flowEvidence, 'progress_state', 'learner_core_flows');
        $domains['HISTORY'] = self::flowDomain($flowEvidence, 'history', 'result_review');
        $domains['SIMULATOR_RECOVERY'] = self::flowDomain($flowEvidence, 'simulator_recovery');
        $domains['ADMIN_CORE_FLOWS'] = self::flowDomain($flowEvidence, 'admin_core_flows');
        $domains['SHARED_QUESTION_SAFETY'] = self::flowDomain($flowEvidence, 'shared_question_safety');
        $domains['STUDY_EXPERIENCE'] = self::flowDomain($flowEvidence, 'study_experience');
        $domains['DEVELOPER_HEALTH'] = self::domain(
            $healthFail > 0 ? 'FAIL' : 'PASS',
            ['health_failures' => $healthFail],
            'The same RepositoryHealthEngine snapshot powers the release summary.'
        );
        $domains['PERFORMANCE'] = self::performanceDomain($performance);
        $domains['REPOSITORY_DETERMINISM'] = self::flowDomain($flowEvidence, 'repository_determinism');
        $domains['ROUTE_COMPLETENESS'] = self::domainFromRouteScan($flowEvidence);
        $domains['SECURITY_SANITY'] = self::flowDomain($flowEvidence, 'security_sanity');
        $domains['STUDY_FLASHCARD_EXPERIENCE'] = $domains['STUDY_EXPERIENCE'];
        $production ??= self::productionConfiguration(dirname(__DIR__, 4));
        $productionFlow = is_array($flowEvidence['checks']['production_configuration'] ?? null)
            ? $flowEvidence['checks']['production_configuration']
            : null;
        if ($productionFlow !== null) {
            $production['runtime_tests'] = [
                'status' => (string) ($productionFlow['status'] ?? 'UNKNOWN'),
                'tests' => $productionFlow['tests'] ?? [],
            ];
            if (($productionFlow['status'] ?? '') === 'FAIL') {
                $production['status'] = 'FAIL';
                $production['reason'] = 'Production configuration or bootstrap contract failed.';
            }
        }
        $domains['PRODUCTION_CONFIGURATION'] = self::domain(
            strtoupper((string) ($production['status'] ?? 'UNKNOWN')),
            $production,
            (string) ($production['reason'] ?? 'Production configuration audit.')
        );
        $domains['RELEASE_PACKAGING'] = self::flowDomain($flowEvidence, 'release_packaging');

        $actions = [];
        foreach ($domains as $name => $domain) {
            $status = (string) ($domain['status'] ?? 'UNKNOWN');
            if ($status === 'FAIL') {
                $actions[] = self::action('P0', $name, $name, (string) ($domain['reason'] ?? 'Release-critical domain failed.'), true, 'Repair the deterministic failure and rerun the release gate.', 'PRE_RELEASE');
            } elseif ($status === 'REVIEW') {
                $sourceReviewOnly = $name === 'SOURCE_TRACEABILITY'
                    && (int) (($domain['evidence']['hard_source_defects'] ?? 0)) === 0
                    && (int) (($domain['evidence']['source_readiness_failures'] ?? 0)) === 0;
                $actions[] = self::action(
                    $sourceReviewOnly ? 'P2' : 'P1',
                    $name,
                    $name,
                    (string) ($domain['reason'] ?? 'Domain requires bounded review.'),
                    false,
                    $sourceReviewOnly ? 'Retain the conservative editorial review queue and revisit it with new evidence.' : 'Review the recorded evidence before release or explicitly accept the debt.',
                    $sourceReviewOnly ? 'POST_RELEASE' : 'PRE_RELEASE'
                );
            } elseif ($status === 'WARN') {
                $actions[] = self::action('P2', $name, $name, (string) ($domain['reason'] ?? 'Domain has a non-blocking warning.'), false, 'Track the warning in the next maintenance pass.', 'MAINTENANCE');
            }
        }
        $actions[] = self::action('P3', 'CONTENT', 'evidence-exhausted-and-thin-leaves', 'Numeric thinness and evidence-exhausted leaves remain planning signals, not release blockers.', false, 'Keep future expansion post-release and evidence-backed.', 'POST_RELEASE');
        $actions[] = self::action('P3', 'FEATURE', 'architecture-visual-backlog', 'Architecture visual question capability is useful later and does not block v1 text release.', false, 'Plan as a post-release feature.', 'POST_RELEASE');
        $actions = self::sortActions($actions);
        $p0 = array_values(array_filter($actions, static fn(array $action): bool => ($action['priority'] ?? '') === 'P0'));
        $counts = ['P0' => 0, 'P1' => 0, 'P2' => 0, 'P3' => 0];
        foreach ($actions as $action) {
            $priority = (string) ($action['priority'] ?? 'P3');
            if (isset($counts[$priority])) $counts[$priority]++;
        }
        $gate = self::evaluateGate($domains, $p0);
        $decision = $gate['status'] === 'PASS'
            ? ($counts['P1'] + $counts['P2'] > 0 ? 'RELEASE_READY_WITH_NONBLOCKING_DEBT' : 'RELEASE_READY')
            : 'NOT_RELEASE_READY';

        return [
            'release_domains' => $domains,
            'action_queue' => $actions,
            'priority_counts' => $counts,
            'p0_blockers' => $p0,
            'release_gate' => $gate,
            'release_decision' => $decision,
            'release_criteria' => [
                'p0_blockers' => count($p0) === 0,
                'repository_health_fail' => $healthFail === 0,
                'critical_learner_and_admin_flows' => self::criticalFlowsPass($domains),
                'eligibility_integrity' => ($domains['ELIGIBILITY_INTEGRITY']['status'] ?? 'UNKNOWN') === 'PASS',
                'source_study_hard_defects' => ($hardSource + $hardStudy + $hardTraceability) === 0,
                'flashcard_hard_defects' => $flashcardDefects === 0,
                'calculation_hard_defects' => $calculationDefects === 0,
                'future_effective_leakage' => $futureLeakage === 0,
            ],
        ];
    }

    /** @param array<string, mixed> $domains @param array<int, array<string, mixed>> $p0 */
    public static function evaluateGate(array $domains, array $p0): array
    {
        if ($p0 !== []) return ['status' => 'FAIL', 'reason' => 'One or more actual P0 release blockers remain.', 'p0_count' => count($p0)];
        foreach (self::CRITICAL_FLOW_DOMAINS as $domain) {
            $status = (string) (($domains[$domain]['status'] ?? 'UNKNOWN'));
            if ($status === 'FAIL') return ['status' => 'FAIL', 'reason' => "Critical domain {$domain} failed.", 'p0_count' => 0];
            if (!in_array($status, ['PASS'], true)) return ['status' => 'REVIEW', 'reason' => "Critical domain {$domain} lacks a passing evidence result.", 'p0_count' => 0];
        }
        return ['status' => 'PASS', 'reason' => 'No P0 blockers and every critical flow/integrity domain passed.', 'p0_count' => 0];
    }

    /**
     * Scan registered application routes and user-facing templates for
     * explicit stub markers.  This is intentionally structural: a semantic
     * product review remains outside the default release gate.
     *
     * @return array<string, mixed>
     */
    public static function routeCompleteness(string $root): array
    {
        $routePath = rtrim($root, '/') . '/routes/web.php';
        $routeText = is_file($routePath) ? (string) file_get_contents($routePath) : '';
        $routeCount = preg_match_all(
            '/\$router->(?:get|post|put|patch|delete)\s*\(\s*([\"\'])(.*?)\1/s',
            $routeText,
            $matches,
            PREG_SET_ORDER
        ) ?: 0;
        $internalRoutes = 0;
        foreach ($matches as $match) {
            if (str_starts_with((string) ($match[2] ?? ''), '/developer')) {
                $internalRoutes++;
            }
        }

        $markers = [];
        $viewsRoot = rtrim($root, '/') . '/app/Views';
        if (is_dir($viewsRoot)) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($viewsRoot, \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($iterator as $file) {
                if (!$file->isFile()) continue;
                $contents = (string) file_get_contents($file->getPathname());
                foreach (['not implemented', 'stub route'] as $marker) {
                    if (stripos($contents, $marker) !== false) {
                        $markers[] = $marker . ':' . str_replace(rtrim($root, '/') . '/', '', $file->getPathname());
                    }
                }
            }
        }
        $stub = preg_match_all('/not\s+implemented|stub\s+route/i', $routeText, $routeMarkers) ?: 0;
        $stub += count($markers);

        return [
            'active' => $routeCount,
            'intentional_internal' => $internalRoutes,
            'deprecated' => 0,
            'stub' => $stub,
            'broken' => 0,
            'unknown' => 0,
            'marker_examples' => array_slice($markers, 0, 10),
        ];
    }

    /**
     * Bounded static production contract over the existing application
     * architecture. It checks configuration and safety boundaries without
     * attempting a semantic penetration test or printing secret values.
     *
     * @return array<string, mixed>
     */
    public static function productionConfiguration(string $root): array
    {
        $root = rtrim($root, '/');
        $checks = [];
        $findings = [];

        $read = static function (string $relative) use ($root): string {
            $path = $root . '/' . ltrim($relative, '/');
            return is_file($path) ? (string) file_get_contents($path) : '';
        };
        $has = static function (string $relative, string $needle) use ($read): bool {
            return str_contains($read($relative), $needle);
        };
        $record = static function (string $code, string $status, string $reason, array $evidence = []) use (&$checks): void {
            $checks[$code] = ['status' => $status, 'reason' => $reason, 'evidence' => $evidence];
        };

        $requiredFiles = ['.env.example', 'config/app.php', 'config/release.php', 'config/deployment-profile.json', 'public/index.php', 'public/health.php', 'routes/web.php', 'app/Core/App.php', 'app/Core/ExceptionHandler.php', 'app/Core/SessionConfiguration.php', 'app/Core/SecurityHeaders.php', 'app/Storage/JsonStorage.php'];
        $missingFiles = array_values(array_filter($requiredFiles, static fn(string $file): bool => !is_file($root . '/' . $file)));
        $record('REQUIRED_RUNTIME_FILES', $missingFiles === [] ? 'PASS' : 'FAIL', $missingFiles === [] ? 'Configuration, front-controller, route, exception, session, and storage contracts are present.' : 'Required runtime files are missing.', ['missing' => $missingFiles]);

        $debugGuard = $has('app/Core/App.php', "APP_DEBUG must be false when APP_ENV=production")
            && $has('public/index.php', "ini_set('display_errors', '0')")
            && $has('public/api/doctor.php', "ini_set('display_errors', '0')");
        $record('PRODUCTION_DEBUG_GUARD', $debugGuard ? 'PASS' : 'FAIL', $debugGuard ? 'Production rejects debug mode and suppresses display_errors.' : 'Production debug/display-errors guard is incomplete.');

        $errorSafe = $has('app/Core/ExceptionHandler.php', 'error_log(')
            && $has('app/Core/ExceptionHandler.php', 'Something went wrong.')
            && $has('app/Core/ExceptionHandler.php', 'getTraceAsString');
        $record('ERROR_PRESENTATION', $errorSafe ? 'PASS' : 'FAIL', $errorSafe ? 'Production errors are generic while diagnostic detail is logged/debug-gated.' : 'Production error presentation contract is incomplete.');

        $developerGuard = $has('routes/web.php', "App::config('environment') !== 'production'")
            && $has('app/Controllers/DoctorApiController.php', "App::config('environment') === 'production'")
            && $has('app/Controllers/DoctorRunController.php', "App::config('environment') === 'production'");
        $record('DEVELOPER_ADMIN_BOUNDARY', $developerGuard ? 'PASS' : 'FAIL', $developerGuard ? 'Developer and admin maintenance surfaces are unavailable in production.' : 'Developer/admin route boundary is incomplete.');

        $securityHeaders = $has('app/Core/SecurityHeaders.php', 'X-Content-Type-Options: nosniff')
            && $has('app/Core/SecurityHeaders.php', 'X-Frame-Options: SAMEORIGIN')
            && $has('public/index.php', 'SecurityHeaders::apply');
        $record('SECURITY_HEADERS', $securityHeaders ? 'PASS' : 'FAIL', $securityHeaders ? 'Baseline browser security headers are applied by the front controller.' : 'Baseline browser security headers are incomplete.');

        $sessionSafe = $has('app/Core/SessionConfiguration.php', "session.use_strict_mode")
            && $has('app/Core/SessionConfiguration.php', "'httponly' => true")
            && $has('app/Core/SessionConfiguration.php', "'samesite' => 'Lax'")
            && $has('app/Core/SessionConfiguration.php', "'secure' => " . '$https');
        $record('SESSION_COOKIE_SAFETY', $sessionSafe ? 'PASS' : 'FAIL', $sessionSafe ? 'Strict session IDs and HttpOnly/SameSite/Secure-aware cookies are configured.' : 'Session cookie safety contract is incomplete.');

        $storageSafe = $has('app/Storage/JsonStorage.php', 'LOCK_EX')
            && $has('app/Storage/JsonStorage.php', 'rename($temp, $path)')
            && $has('app/Core/Database.php', 'is_readable($path)')
            && $has('app/Core/Database.php', 'is_writable($path)');
        $record('STORAGE_WRITE_SAFETY', $storageSafe ? 'PASS' : 'FAIL', $storageSafe ? 'File-backed writes are locked/atomic and storage paths are validated.' : 'Storage write safety contract is incomplete.');

        $csrfSafe = $has('app/Services/Shared/CsrfTokenService.php', 'hash_equals')
            && $has('app/Controllers/QuestionEditorController.php', 'self::requireCsrf()')
            && $has('app/Controllers/QuizFlowController.php', 'CsrfTokenService::valid');
        $record('CSRF_MUTATION_BOUNDARY', $csrfSafe ? 'PASS' : 'FAIL', $csrfSafe ? 'Critical browser mutations use the existing session CSRF contract.' : 'Critical mutation CSRF coverage is incomplete.');

        $debugFindings = self::scanTrackedProductionFiles($root, '/\b(?:var_dump|print_r|dd|debug_backtrace|phpinfo)\s*\(/i', static function (string $path): bool {
            return str_starts_with($path, 'app/') || str_starts_with($path, 'public/') || str_starts_with($path, 'routes/') || str_starts_with($path, 'config/');
        });
        $record('DEBUG_LEAKAGE', $debugFindings === [] ? 'PASS' : 'FAIL', $debugFindings === [] ? 'No obvious debug dump calls were found in production-executable paths.' : 'Debug dump calls were found in production-executable paths.', ['count' => count($debugFindings), 'paths' => array_slice($debugFindings, 0, 10)]);

        $secretFindings = self::scanTrackedProductionFiles($root, '/-----BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY-----|\bAKIA[0-9A-Z]{16}\b|\b(?:gh[pousr]|xox[baprs])-[A-Za-z0-9-]{20,}\b/i');
        $record('SECRET_SAFETY', $secretFindings === [] ? 'PASS' : 'FAIL', $secretFindings === [] ? 'No high-confidence tracked credential or private-key markers were found.' : 'High-confidence tracked secret markers were found; rotate and remove them.', ['count' => count($secretFindings), 'paths' => array_slice($secretFindings, 0, 10)]);

        $readme = $read('README.md');
        $documentation = str_contains($readme, 'public/')
            && str_contains($readme, 'APP_STORAGE_PATH')
            && str_contains($readme, 'tools/doctor.php')
            && str_contains($readme, 'backup')
            && is_file($root . '/docs/RELEASE_RUNBOOK_V1.md');
        $record('OPERATING_DOCUMENTATION', $documentation ? 'PASS' : 'WARN', $documentation ? 'README documents runtime, storage, health, tests, and backup expectations.' : 'Operating documentation is incomplete.');

        $status = 'PASS';
        foreach ($checks as $check) {
            if (($check['status'] ?? '') === 'FAIL') {
                $status = 'FAIL';
                break;
            }
            if (($check['status'] ?? '') === 'WARN') $status = 'WARN';
        }
        foreach ($checks as $code => $check) {
            if (($check['status'] ?? '') === 'FAIL') {
                $findings[] = ['code' => $code, 'severity' => 'P0', 'path_count' => count((array) ($check['evidence']['paths'] ?? []))];
            }
        }

        return [
            'status' => $status,
            'reason' => $status === 'PASS' ? 'Production configuration and safety contracts passed.' : 'Production configuration requires remediation.',
            'checks' => $checks,
            'findings' => $findings,
            'secret_findings_count' => count($secretFindings),
            'debug_leakage_count' => count($debugFindings),
        ];
    }

    /** @return array<int, string> */
    private static function scanTrackedProductionFiles(string $root, string $pattern, ?callable $filter = null): array
    {
        $command = 'git -C ' . escapeshellarg($root) . ' ls-files -z';
        $output = shell_exec($command);
        if (!is_string($output)) return [];
        $findings = [];
        foreach (explode("\0", $output) as $path) {
            if ($path === '' || ($filter !== null && !$filter($path))) continue;
            $absolute = $root . '/' . $path;
            if (!is_file($absolute)) continue;
            $contents = (string) file_get_contents($absolute);
            if (preg_match($pattern, $contents) === 1) $findings[] = $path;
        }
        sort($findings, SORT_STRING);
        return $findings;
    }

    /** @param array<string, mixed> $flowEvidence */
    private static function flowDomain(array $flowEvidence, string $key, ?string $fallback = null): array
    {
        $result = $flowEvidence['checks'][$key] ?? ($fallback === null ? null : ($flowEvidence['checks'][$fallback] ?? null));
        if (!is_array($result)) return self::domain('UNKNOWN', ['check' => $key], 'No focused release-flow evidence was supplied.');
        $status = strtoupper((string) ($result['status'] ?? 'UNKNOWN'));
        if (!in_array($status, ['PASS', 'WARN', 'REVIEW', 'FAIL', 'UNKNOWN'], true)) $status = 'UNKNOWN';
        return self::domain($status, ['tests' => $result['tests'] ?? [], 'passed' => $result['passed'] ?? null], (string) ($result['reason'] ?? 'Focused release-flow evidence.'));
    }

    /** @param array<string, mixed> $performance */
    private static function performanceDomain(array $performance): array
    {
        if (!array_key_exists('status', $performance)) return self::domain('UNKNOWN', [], 'Performance evidence is collected separately from normal health.');
        return self::domain(strtoupper((string) $performance['status']), $performance, 'Performance evidence was supplied.');
    }

    /** @param array<string, mixed> $flowEvidence */
    private static function domainFromRouteScan(array $flowEvidence): array
    {
        $scan = $flowEvidence['route_completeness'] ?? null;
        if (!is_array($scan)) return self::domain('UNKNOWN', [], 'Route completeness scan was not supplied.');
        $status = ((int) ($scan['stub'] ?? 0) + (int) ($scan['broken'] ?? 0)) > 0 ? 'FAIL' : ((int) ($scan['unknown'] ?? 0) > 0 ? 'REVIEW' : 'PASS');
        return self::domain($status, $scan, 'User-facing route and placeholder scan.');
    }

    /** @param array<string, mixed> $evidence */
    private static function domain(string $status, array $evidence, string $reason): array
    {
        return ['status' => $status, 'evidence' => $evidence, 'reason' => $reason];
    }

    /** @param array<string, mixed> $issue */
    private static function action(string $priority, string $domain, string $entity, string $reason, bool $deterministic, string $next, string $timing): array
    {
        return [
            'priority' => $priority,
            'domain' => $domain,
            'entity' => $entity,
            'reason' => $reason,
            'deterministic_or_editorial' => $deterministic ? 'DETERMINISTIC' : 'EDITORIAL_OR_REVIEW',
            'release_blocking' => $deterministic,
            'recommended_timing' => $timing,
            'suggested_next_action' => $next,
        ];
    }

    /** @param array<int, array<string, mixed>> $actions @return array<int, array<string, mixed>> */
    private static function sortActions(array $actions): array
    {
        usort($actions, static function (array $a, array $b): int {
            $priority = ['P0' => 0, 'P1' => 1, 'P2' => 2, 'P3' => 3];
            return ($priority[$a['priority'] ?? 'P3'] ?? 9) <=> ($priority[$b['priority'] ?? 'P3'] ?? 9)
                ?: strcmp((string) ($a['domain'] ?? ''), (string) ($b['domain'] ?? ''))
                ?: strcmp((string) ($a['entity'] ?? ''), (string) ($b['entity'] ?? ''));
        });
        return $actions;
    }

    /** @param array<string, mixed> $domains */
    private static function criticalFlowsPass(array $domains): bool
    {
        foreach (self::CRITICAL_FLOW_DOMAINS as $domain) {
            if (($domains[$domain]['status'] ?? 'UNKNOWN') !== 'PASS') return false;
        }
        return true;
    }

    /** @param array<int, array<string, mixed>> $issues @param callable(array<string, mixed>):bool $predicate */
    private static function issueCount(array $issues, callable $predicate): int
    {
        return count(array_filter($issues, $predicate));
    }

    /** @return array<string, mixed> */
    public static function runFlowChecks(?string $root = null): array
    {
        $root ??= dirname(__DIR__, 4);
        $testStoragePath = self::createFlowTestStorage($root);
        $groups = [
            'learner_core_flows' => ['tools/Tests/LearnerJourneyHttpTest.php', 'tools/Tests/LearnerDashboardReliabilityTest.php', 'tools/Tests/LearnerExperienceConsistencyTest.php', 'tools/Tests/LearningProfileActionViewTest.php', 'tools/Tests/ProgressRecommendationViewTest.php', 'tools/Tests/LearnerNavigationUxTest.php', 'tools/Tests/LearnerMobileAccessibilityViewTest.php'],
            'attempt_safety' => ['tools/Tests/MultiAttemptLearningHttpTest.php', 'tools/Tests/QuizStartFailureHttpTest.php', 'tools/Tests/ApplicationResilienceTest.php'],
            'result_review' => ['tools/Tests/HistoryResultHttpTest.php', 'tools/Tests/QuizCompletionHttpTest.php'],
            'simulator_recovery' => ['tools/Tests/ExamSimulationHttpTest.php'],
            'admin_core_flows' => ['tools/Tests/QuestionEditorEligibilityTest.php', 'tools/Tests/QuestionAuthoringWorkflowIntegrationTest.php'],
            'shared_question_safety' => ['tools/Tests/CrossBoardCanonicalReuseTest.php', 'tools/Tests/Batch669NursingIntegrationTest.php'],
            'study_experience' => ['tools/Tests/StudyLibraryNavigationTest.php', 'tools/Tests/FlashcardIntegrationTest.php'],
            'security_sanity' => ['tools/Tests/ProductionQuizCsrfTest.php'],
            'repository_determinism' => ['tools/Tests/ReleaseReadinessContractTest.php', 'tools/Tests/QuizAnswerStatisticsWeaknessTest.php'],
            'production_configuration' => ['tools/Tests/RuntimeConfigurationTest.php', 'tools/Tests/ProductionFoundationTest.php'],
            'release_packaging' => ['tools/Tests/ReleasePackageTest.php'],
        ];
        $checks = [];
        try {
            foreach ($groups as $group => $scripts) {
                $tests = [];
                $allPass = true;
                foreach ($scripts as $script) {
                    $path = $root . '/' . $script;
                    if (!is_file($path)) {
                        $tests[] = ['script' => $script, 'status' => 'UNKNOWN', 'exit_code' => null];
                        $allPass = false;
                        continue;
                    }
                    self::resetFlowTestLearnerState($testStoragePath);
                    [$exitCode, $output] = self::runScript($path, $root, $testStoragePath);
                    $status = $exitCode === 0 ? 'PASS' : 'FAIL';
                    $tests[] = ['script' => $script, 'status' => $status, 'exit_code' => $exitCode, 'output_tail' => substr($output, -600)];
                    if ($status !== 'PASS') $allPass = false;
                }
                $checks[$group] = ['status' => $allPass ? 'PASS' : 'FAIL', 'tests' => $tests, 'passed' => $allPass];
            }
            return ['checks' => $checks];
        } finally {
            self::removeFlowTestStorage($testStoragePath);
        }
    }

    private static function createFlowTestStorage(string $root): string
    {
        $sourceRoot = $root . '/storage';
        if (!is_dir($sourceRoot)) {
            throw new \RuntimeException('Release flow tests require the repository storage fixture.');
        }

        $storagePath = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
            . '/boardprep-release-test-storage-' . bin2hex(random_bytes(6));
        if (!mkdir($storagePath, 0700, true)) {
            throw new \RuntimeException('Unable to create isolated release-test storage.');
        }

        try {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($sourceRoot, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::SELF_FIRST
            );
            $mutableCollections = ['attempts.json', 'weakness.json', 'question-statistics.json', 'flashcard_state.json'];
            foreach ($iterator as $item) {
                $source = $item->getPathname();
                $relative = substr($source, strlen($sourceRoot) + 1);
                if ($relative === false || $relative === ''
                    || str_starts_with($relative, 'doctor/simulation-sessions/')) {
                    continue;
                }
                $destination = $storagePath . DIRECTORY_SEPARATOR
                    . str_replace('/', DIRECTORY_SEPARATOR, $relative);
                if ($item->isDir()) {
                    if (!is_dir($destination)
                        && !mkdir($destination, 0700, true)
                        && !is_dir($destination)) {
                        throw new \RuntimeException('Unable to create isolated release-test storage directory.');
                    }
                    continue;
                }
                if (!$item->isFile() || in_array($relative, $mutableCollections, true)) {
                    continue;
                }
                $directory = dirname($destination);
                if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
                    throw new \RuntimeException('Unable to create isolated release-test storage directory.');
                }
                if (!copy($source, $destination)) {
                    throw new \RuntimeException('Unable to copy repository data into isolated release-test storage.');
                }
            }

            foreach ($mutableCollections as $collection) {
                if (file_put_contents($storagePath . DIRECTORY_SEPARATOR . $collection, "[]\n", LOCK_EX) === false) {
                    throw new \RuntimeException('Unable to seed isolated learner test state.');
                }
            }
            return $storagePath;
        } catch (\Throwable $exception) {
            self::removeFlowTestStorage($storagePath);
            throw $exception;
        }
    }

    private static function removeFlowTestStorage(string $storagePath): void
    {
        $prefix = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR . 'boardprep-release-test-storage-';
        if (!str_starts_with($storagePath, $prefix) || !is_dir($storagePath)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($storagePath, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $item) {
            $path = $item->getPathname();
            if ($item->isDir() && !$item->isLink()) {
                @rmdir($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($storagePath);
    }

    private static function resetFlowTestLearnerState(string $storagePath): void
    {
        foreach (['attempts.json', 'weakness.json', 'question-statistics.json', 'flashcard_state.json'] as $collection) {
            $path = $storagePath . DIRECTORY_SEPARATOR . $collection;
            if (file_put_contents($path, "[]\n", LOCK_EX) === false) {
                throw new \RuntimeException('Unable to reset isolated learner test state.');
            }
        }
    }

    /** @return array{0:int,1:string} */
    private static function runScript(string $path, string $root, string $storagePath): array
    {
        $descriptor = [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $sessionPath = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
            . '/boardprep-release-sessions-' . bin2hex(random_bytes(6));
        if (!mkdir($sessionPath, 0700, true) && !is_dir($sessionPath)) {
            return [255, 'Unable to create isolated release-test session storage.'];
        }
        $cleanupSessionPath = static function () use ($sessionPath): void {
            foreach (glob($sessionPath . DIRECTORY_SEPARATOR . '*') ?: [] as $sessionFile) {
                if (is_file($sessionFile) || is_link($sessionFile)) {
                    @unlink($sessionFile);
                }
            }
            @rmdir($sessionPath);
        };
        // The repository's focused integration tests load the full indexed
        // application graph. Keep the release projection independent of the
        // caller's small CLI memory default.
        $command = 'env APP_ENV=testing APP_DEBUG=false DB_DRIVER=json APP_STORAGE_PATH='
            . escapeshellarg($storagePath) . ' APP_SESSION_PATH='
            . escapeshellarg($sessionPath) . ' '
            . escapeshellarg(PHP_BINARY) . ' -d memory_limit=1024M ' . escapeshellarg($path);
        $process = proc_open($command, $descriptor, $pipes, $root);
        if (!is_resource($process)) {
            $cleanupSessionPath();
            return [255, 'Unable to start release test.'];
        }
        $stdout = stream_get_contents($pipes[1]) ?: '';
        $stderr = stream_get_contents($pipes[2]) ?: '';
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);
        $cleanupSessionPath();
        return [$exitCode, $stdout . $stderr];
    }
}
