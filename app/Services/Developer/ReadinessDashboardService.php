<?php

declare(strict_types=1);

namespace App\Services\Developer;

use App\Contracts\StorageInterface;
use App\Core\App;
use App\Services\Question\AcademicReviewProposalService;
use DateTimeImmutable;
use Throwable;

/** Read-only projection of bounded technical and academic evidence sources. */
final class ReadinessDashboardService
{
    private StorageInterface $storage;

    public function __construct(?StorageInterface $storage = null, private ?FullDoctorEvidenceService $doctor = null)
    {
        $this->storage = $storage ?? App::storage();
        $this->doctor ??= new FullDoctorEvidenceService();
    }

    /** @return array<string,mixed> */
    public function build(string $repositoryRoot, ?DateTimeImmutable $now = null): array
    {
        $evidence = $this->doctor->latest($repositoryRoot, $now);
        $proposalState = 'AVAILABLE';
        $proposalItems = [];
        try {
            $proposalItems = AcademicReviewProposalService::all();
        } catch (Throwable) {
            $proposalState = 'UNAVAILABLE';
        }

        $pendingInventory = 0;
        $decisionBacklog = 0;
        $workflowState = 'AVAILABLE';
        $workflowStates = ['PENDING_REVIEW', 'DEFERRED', 'ASSIGNED', 'IN_REVIEW', 'REVISION_REQUESTED'];
        $workflowsById = [];
        try {
            $workflows = $this->storage->all('academic-proposal-workflow');
            if (count($workflows) > 1000) {
                $workflowState = 'LIMIT_EXCEEDED';
            } else {
                foreach ($workflows as $workflowRecord) {
                    if (!is_array($workflowRecord)) continue;
                    $workflowId = trim((string) ($workflowRecord['id'] ?? ''));
                    if ($workflowId !== '') $workflowsById[$workflowId] = $workflowRecord;
                }
            }
        } catch (Throwable) {
            $workflowState = 'UNAVAILABLE';
        }
        foreach ($proposalItems as $proposal) {
            if (!is_array($proposal)) continue;
            $id = trim((string) ($proposal['id'] ?? ''));
            $review = is_array($proposal['academic_review'] ?? null) ? $proposal['academic_review'] : [];
            if (strtoupper((string) ($review['status'] ?? '')) === 'PENDING') $pendingInventory++;
            $workflow = $id !== '' ? ($workflowsById[$id] ?? null) : null;
            $state = is_array($workflow) ? strtoupper((string) ($workflow['state'] ?? '')) : '';
            if ($state === '' && strtoupper((string) ($review['status'] ?? '')) === 'PENDING') $state = 'PENDING_REVIEW';
            if (in_array($state, $workflowStates, true)) $decisionBacklog++;
        }

        $reportState = 'AVAILABLE';
        $reportCounts = ['total' => 0, 'submitted' => 0, 'under_review' => 0, 'resolved' => 0, 'rejected' => 0];
        try {
            $reports = $this->storage->all('learner-question-reports');
            if (count($reports) > 5000) {
                $reportState = 'LIMIT_EXCEEDED';
            } else {
                $reportCounts['total'] = count($reports);
                foreach ($reports as $report) {
                    if (!is_array($report)) continue;
                    $status = strtolower((string) ($report['status'] ?? ''));
                    if (isset($reportCounts[$status])) $reportCounts[$status]++;
                }
            }
        } catch (Throwable) {
            $reportState = 'UNAVAILABLE';
        }

        $sourceState = (string) ($evidence['source_traceability']['status'] ?? 'UNKNOWN');
        if (($evidence['state'] ?? '') !== 'CURRENT_PASS' && $sourceState !== 'UNKNOWN') {
            $sourceState = 'STALE_RECORDED_' . $sourceState;
        }
        $eligibilityState = (string) ($evidence['eligibility']['status'] ?? 'UNKNOWN');
        if (($evidence['state'] ?? '') !== 'CURRENT_PASS' && $eligibilityState !== 'UNKNOWN') {
            $eligibilityState = 'STALE_RECORDED_' . $eligibilityState;
        }

        return [
            'technical_gate' => [
                'status' => (string) ($evidence['state'] ?? 'UNVERIFIED'),
                'release_gate' => (string) ($evidence['release_gate'] ?? 'UNKNOWN'),
                'health_status' => (string) ($evidence['health_status'] ?? 'UNKNOWN'),
                'evidence_state' => (string) ($evidence['state'] ?? 'UNVERIFIED'),
                'reason' => (string) ($evidence['reason'] ?? 'evidence_unavailable'),
                'generated_at' => (string) ($evidence['generated_at'] ?? ''),
                'tested_sha' => (string) ($evidence['tested_sha'] ?? ''),
                'current_sha' => (string) ($evidence['current_sha'] ?? ''),
                'exit_code' => $evidence['exit_code'] ?? null,
                'artifact' => (string) ($evidence['artifact'] ?? ''),
                'artifact_sha256' => (string) ($evidence['artifact_sha256'] ?? ''),
                'command' => (string) ($evidence['command'] ?? ''),
                'priority_counts' => is_array($evidence['priority_counts'] ?? null) ? $evidence['priority_counts'] : ['P0' => 0, 'P1' => 0, 'P2' => 0, 'P3' => 0],
                'functional_tests' => is_array($evidence['functional_tests'] ?? null) ? $evidence['functional_tests'] : [],
                'integration_tests' => is_array($evidence['integration_tests'] ?? null) ? $evidence['integration_tests'] : [],
                'security_tests' => is_array($evidence['security_tests'] ?? null) ? $evidence['security_tests'] : [],
                'freshness_reason' => (string) ($evidence['reason'] ?? 'evidence_unavailable'),
                'source' => 'latest direct-child docs/reports/batch-*-full-doctor-*.json with matching .meta.txt provenance',
            ],
            'browser_acceptance' => [
                'status' => (string) ($evidence['browser_acceptance'] ?? 'NOT_EXECUTED'),
                'source' => 'Full Doctor release_domains.BROWSER_ACCEPTANCE; absent evidence is NOT_EXECUTED',
            ],
            'encrypted_backup_acceptance' => self::unexecutedAcceptance(
                'No sodium-enabled encrypted backup and authorized restore result is recorded for this checkout.',
                'No validated current-commit sodium round-trip artifact is available. A fail-closed missing-extension check is not encrypted backup acceptance.'
            ),
            'hosted_configuration_acceptance' => self::unexecutedAcceptance(
                'No authorized disposable-host configuration result is recorded for this checkout.',
                'Local production preflight does not establish HTTPS, proxy, cookie, session, or filesystem behavior on a hosted system.'
            ),
            'academic_approval' => [
                'status' => $proposalState !== 'AVAILABLE' || $workflowState !== 'AVAILABLE'
                    ? 'UNVERIFIED'
                    : (($pendingInventory > 0 || $sourceState !== 'PASS') ? 'REVIEW' : 'NO_PENDING_EVIDENCE'),
                'pending_proposals' => $proposalState === 'AVAILABLE' ? $pendingInventory : null,
                'decision_backlog' => $proposalState === 'AVAILABLE' && $workflowState === 'AVAILABLE' ? $decisionBacklog : null,
                'inventory_state' => $proposalState,
                'workflow_state' => $workflowState,
                'source_traceability' => $sourceState,
                'unreviewed_or_unknown_source_links' => $evidence['source_traceability']['unreviewed_or_unknown_links'] ?? null,
                'source_review_signals' => $evidence['source_traceability']['review_signals'] ?? null,
                'eligibility' => $eligibilityState,
                'eligibility_failures' => $evidence['eligibility']['failures'] ?? null,
                'reviewer_authorization' => 'SCOPED_PER_ACCOUNT; checked by academic workflow; not grantable from this dashboard',
                'source' => 'Batch 695 disposition inventory, academic-proposal-workflow collection, and current Full Doctor release evidence',
            ],
            'learner_question_reports' => [
                'status' => $reportState,
                'counts' => $reportCounts,
                'source' => 'learner-question-reports collection; aggregate counts only, no learner identifiers or report text',
            ],
            'public_release_authorization' => [
                'status' => 'NOT_AUTHORIZED',
                'source' => 'No public-release authorization is represented by a technical Doctor result.',
            ],
            'links' => [
                ['label' => 'Full Doctor dashboard', 'href' => '/developer/doctor'],
                ['label' => 'Academic review queue', 'href' => '/developer/academic-review'],
                ['label' => 'Learner question concerns', 'href' => '/developer/question-reports'],
                ['label' => 'Source registry and review guidance', 'href' => '/developer/academic-review'],
                ['label' => 'Release gate documentation', 'href' => '/developer/doctor'],
            ],
        ];
    }

    /** @return array<string,mixed> */
    private static function unexecutedAcceptance(string $reason, string $source): array
    {
        return [
            'status' => 'NOT_EXECUTED',
            'environment' => null,
            'tested_sha' => null,
            'timestamp' => null,
            'command' => null,
            'exit_code' => null,
            'artifact' => null,
            'artifact_sha256' => null,
            'failure_reason' => $reason,
            'freshness' => 'NO_EVIDENCE',
            'source' => $source,
        ];
    }
}
