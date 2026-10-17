<?php

declare(strict_types=1);

namespace App\Services\Acceptance;

final class AcceptanceFeedbackReport
{
    /** @return array<string,mixed> */
    public static function build(array $session, ?string $root = null): array
    {
        $scenarios = AcceptanceScenarioCatalog::all($root);
        $byId = [];
        foreach ($scenarios as $scenario) {
            $byId[$scenario['case_id']] = $scenario;
        }

        $counts = ['PASS' => 0, 'FAIL' => 0, 'BLOCKED' => 0, 'NOT_APPLICABLE' => 0, 'NOT_RUN' => 0];
        $cases = [];
        $observations = [];
        foreach ($scenarios as $scenario) {
            $caseId = (string) $scenario['case_id'];
            $entry = (array) (($session['cases'] ?? [])[$caseId] ?? []);
            $result = (string) ($entry['result'] ?? 'NOT_RUN');
            if (!in_array($result, array_keys($counts), true)) {
                $result = 'NOT_RUN';
            }
            if ($result === 'PASS' && ($entry['pass_confirmed'] ?? false) !== true) {
                $result = 'NOT_RUN';
            }
            $counts[$result]++;
            $items = array_values(array_filter((array) ($entry['observations'] ?? []), 'is_array'));
            foreach ($items as $item) {
                $observations[] = ['case_id' => $caseId] + $item;
            }
            $categories = array_values(array_unique(array_map(
                static fn(array $item): string => (string) ($item['category'] ?? 'OTHER'),
                $items
            )));
            $priorities = array_values(array_unique(array_map(
                static fn(array $item): string => (string) ($item['priority'] ?? 'UNSURE'),
                $items
            )));
            $cases[] = [
                'CASE_ID' => $caseId,
                'CASE_TITLE' => (string) $scenario['title'],
                'TESTED_ROUTE' => array_values(array_unique(array_map('strval', (array) ($entry['tested_routes'] ?? [])))),
                'HUMAN_RESULT' => $result,
                'FEEDBACK_CATEGORY' => $categories,
                'PRIORITY' => $priorities,
                'COMMENTS' => array_values(array_map(static fn(array $item): string => (string) ($item['comments'] ?? ''), $items)),
                'EXPECTED_BEHAVIOR' => array_values(array_map(static fn(array $item): string => (string) ($item['expected_behavior'] ?? ''), $items)),
                'ACTUAL_BEHAVIOR' => array_values(array_map(static fn(array $item): string => (string) ($item['actual_behavior'] ?? ''), $items)),
                'SUGGESTED_IMPROVEMENT' => array_values(array_map(static fn(array $item): string => (string) ($item['suggested_improvement'] ?? ''), $items)),
                'REPRODUCTION_STEPS' => array_values(array_map(static fn(array $item): string => (string) ($item['reproduction_steps'] ?? ''), $items)),
                'EVIDENCE_REFERENCES' => array_values(array_filter(
                    array_map(static fn(array $item): string => trim((string) ($item['evidence_reference'] ?? '')), $items),
                    static fn(string $reference): bool => $reference !== ''
                )),
                'OBSERVATIONS' => $items,
            ];
        }

        $overall = 'NOT_RUN';
        if ($counts['PASS'] === count($cases)) {
            $overall = 'HUMAN_PASS';
        } elseif ($counts['FAIL'] > 0) {
            $overall = 'HUMAN_FAIL';
        } elseif ($counts['BLOCKED'] > 0) {
            $overall = 'BLOCKED';
        } elseif ($counts['NOT_APPLICABLE'] === count($cases)) {
            $overall = 'NOT_APPLICABLE';
        }

        $generalFeedback = array_values(array_filter((array) ($session['general_feedback'] ?? []), 'is_array'));
        $triageObservations = array_merge(
            $observations,
            array_map(static fn(array $item): array => ['case_id' => 'GENERAL_FEEDBACK'] + $item, $generalFeedback)
        );
        $bugs = array_values(array_filter($triageObservations, static fn(array $item): bool => ($item['category'] ?? '') === 'BUG'));
        $improvements = array_values(array_filter(
            $triageObservations,
            static fn(array $item): bool => in_array(($item['category'] ?? ''), ['IMPROVEMENT', 'MISSING_FEATURE'], true)
                || trim((string) ($item['suggested_improvement'] ?? '')) !== ''
        ));
        $unresolved = array_values(array_map(
            static fn(array $case): array => ['CASE_ID' => $case['CASE_ID'], 'HUMAN_RESULT' => $case['HUMAN_RESULT']],
            array_filter($cases, static fn(array $case): bool => $case['HUMAN_RESULT'] !== 'PASS')
        ));
        $categoryCounts = [];
        foreach ($triageObservations as $observation) {
            $category = (string) ($observation['category'] ?? 'OTHER');
            $categoryCounts[$category] = ($categoryCounts[$category] ?? 0) + 1;
        }
        ksort($categoryCounts);

        return [
            'PROJECT' => 'BoardPrep',
            'REPORT_TYPE' => 'HUMAN_ACCEPTANCE_FEEDBACK',
            'REPORT_SCHEMA_VERSION' => '1.0',
            'SESSION_ID' => (string) ($session['session_id'] ?? ''),
            'BUILD_SHA' => (string) ($session['build_sha'] ?? ''),
            'TEST_DATE' => (string) ($session['test_date'] ?? ''),
            'TESTER_ALIAS' => (string) ($session['tester_alias'] ?? ''),
            'DEVICE' => (string) ($session['device'] ?? ''),
            'OS_AND_VERSION' => (string) ($session['operating_system'] ?? ''),
            'BROWSER' => (string) ($session['browser'] ?? ''),
            'VIEWPORT' => (string) ($session['viewport'] ?? ''),
            'ENVIRONMENT' => (string) ($session['environment'] ?? 'local-isolated-preview'),
            'TESTS_TOTAL' => count($cases),
            'TESTS_PASSED' => $counts['PASS'],
            'TESTS_FAILED' => $counts['FAIL'],
            'TESTS_BLOCKED' => $counts['BLOCKED'],
            'TESTS_NOT_RUN' => $counts['NOT_RUN'],
            'TESTS_NOT_APPLICABLE' => $counts['NOT_APPLICABLE'],
            'OVERALL_HUMAN_ACCEPTANCE_STATUS' => $overall,
            'CASE_RESULTS' => $cases,
            'GENERAL_FEEDBACK' => $generalFeedback,
            'ALL_REPORTED_BUGS' => $bugs,
            'ALL_REQUESTED_IMPROVEMENTS' => $improvements,
            'UNRESOLVED_TESTS' => $unresolved,
            'FEEDBACK_COUNTS_BY_CATEGORY' => $categoryCounts,
            'SUMMARY_FOR_NEXT_CODEX_BATCH' => self::triageSummary($counts, $categoryCounts, $unresolved, count($generalFeedback)),
        ];
    }

    private static function triageSummary(array $counts, array $categoryCounts, array $unresolved, int $generalCount): string
    {
        $unresolvedIds = array_column($unresolved, 'CASE_ID');
        $parts = [
            'Review the attached tester observations as written; the report adds no inferred priority or diagnosis.',
            sprintf(
                'Recorded outcomes: %d PASS, %d FAIL, %d BLOCKED, %d NOT_APPLICABLE, %d NOT_RUN.',
                $counts['PASS'],
                $counts['FAIL'],
                $counts['BLOCKED'],
                $counts['NOT_APPLICABLE'],
                $counts['NOT_RUN']
            ),
            'Unresolved cases: ' . ($unresolvedIds === [] ? 'none' : implode(', ', $unresolvedIds)) . '.',
            'Feedback categories: ' . ($categoryCounts === [] ? 'none recorded' : implode(', ', array_map(
                static fn(string $category, int $count): string => $category . '=' . $count,
                array_keys($categoryCounts),
                array_values($categoryCounts)
            ))) . '.',
            'General observations: ' . $generalCount . '.',
        ];
        return implode(' ', $parts);
    }
}
