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

    /** @param array<string, mixed> $flowEvidence @param array<string, mixed> $performance */
    public static function audit(HealthReport $report, array $flowEvidence = [], array $performance = []): array
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

        $actions = [];
        foreach ($domains as $name => $domain) {
            $status = (string) ($domain['status'] ?? 'UNKNOWN');
            if ($status === 'FAIL') {
                $actions[] = self::action('P0', $name, $name, (string) ($domain['reason'] ?? 'Release-critical domain failed.'), true, 'Repair the deterministic failure and rerun the release gate.');
            } elseif ($status === 'REVIEW') {
                $actions[] = self::action('P1', $name, $name, (string) ($domain['reason'] ?? 'Domain requires bounded review.'), false, 'Review the recorded evidence before release or explicitly accept the debt.');
            } elseif ($status === 'WARN') {
                $actions[] = self::action('P2', $name, $name, (string) ($domain['reason'] ?? 'Domain has a non-blocking warning.'), false, 'Track the warning in the next maintenance pass.');
            }
        }
        $actions[] = self::action('P3', 'CONTENT', 'evidence-exhausted-and-thin-leaves', 'Numeric thinness and evidence-exhausted leaves remain planning signals, not release blockers.', false, 'Keep future expansion post-release and evidence-backed.');
        $actions[] = self::action('P3', 'FEATURE', 'architecture-visual-backlog', 'Architecture visual question capability is useful later and does not block v1 text release.', false, 'Plan as a post-release feature.');
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
    private static function action(string $priority, string $domain, string $entity, string $reason, bool $deterministic, string $next): array
    {
        return [
            'priority' => $priority,
            'domain' => $domain,
            'entity' => $entity,
            'reason' => $reason,
            'deterministic_or_editorial' => $deterministic ? 'DETERMINISTIC' : 'EDITORIAL_OR_REVIEW',
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
        $groups = [
            'learner_core_flows' => ['tools/Tests/LearnerJourneyHttpTest.php', 'tools/Tests/LearnerDashboardReliabilityTest.php', 'tools/Tests/LearnerExperienceConsistencyTest.php', 'tools/Tests/LearningProfileActionViewTest.php', 'tools/Tests/ProgressRecommendationViewTest.php', 'tools/Tests/LearnerNavigationUxTest.php', 'tools/Tests/LearnerMobileAccessibilityViewTest.php'],
            'attempt_safety' => ['tools/Tests/MultiAttemptLearningHttpTest.php', 'tools/Tests/ApplicationResilienceTest.php'],
            'result_review' => ['tools/Tests/HistoryResultHttpTest.php', 'tools/Tests/QuizCompletionHttpTest.php'],
            'simulator_recovery' => ['tools/Tests/ExamSimulationHttpTest.php'],
            'admin_core_flows' => ['tools/Tests/QuestionEditorEligibilityTest.php', 'tools/Tests/QuestionAuthoringWorkflowIntegrationTest.php'],
            'shared_question_safety' => ['tools/Tests/CrossBoardCanonicalReuseTest.php'],
            'study_experience' => ['tools/Tests/FlashcardIntegrationTest.php'],
            'security_sanity' => ['tools/Tests/ProductionQuizCsrfTest.php'],
            'repository_determinism' => ['tools/Tests/ReleaseReadinessContractTest.php'],
        ];
        $checks = [];
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
                [$exitCode, $output] = self::runScript($path, $root);
                $status = $exitCode === 0 ? 'PASS' : 'FAIL';
                $tests[] = ['script' => $script, 'status' => $status, 'exit_code' => $exitCode, 'output_tail' => substr($output, -600)];
                if ($status !== 'PASS') $allPass = false;
            }
            $checks[$group] = ['status' => $allPass ? 'PASS' : 'FAIL', 'tests' => $tests, 'passed' => $allPass];
        }
        return ['checks' => $checks];
    }

    /** @return array{0:int,1:string} */
    private static function runScript(string $path, string $root): array
    {
        $descriptor = [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        // The repository's focused integration tests load the full indexed
        // application graph. Keep the release projection independent of the
        // caller's small CLI memory default.
        $command = 'env APP_ENV=testing APP_DEBUG=false '
            . escapeshellarg(PHP_BINARY) . ' -d memory_limit=1024M ' . escapeshellarg($path);
        $process = proc_open($command, $descriptor, $pipes, $root);
        if (!is_resource($process)) return [255, 'Unable to start release test.'];
        $stdout = stream_get_contents($pipes[1]) ?: '';
        $stderr = stream_get_contents($pipes[2]) ?: '';
        fclose($pipes[1]);
        fclose($pipes[2]);
        return [proc_close($process), $stdout . $stderr];
    }
}
