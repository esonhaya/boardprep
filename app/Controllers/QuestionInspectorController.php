<?php

declare(strict_types=1);

namespace App\Controllers;
use App\Core\App;
use App\Core\Request;
use App\Core\Response;
use App\Services\Question\QuestionEditorService;
use App\Services\Question\AcademicReviewProposalService;
use App\Services\RepositoryHealth\SourceUrlHealthSnapshot;
use App\Services\Study\SourceRegistryService;
use App\Services\Study\StudyLibraryService;

class QuestionInspectorController extends BaseDeveloperController
{
    public static function index(): void
    {
        $proposalId = Request::queryString('proposal_id');
        if ($proposalId !== '') {
            self::proposal($proposalId);
            return;
        }

        $id = Request::queryString('id');

        if ($id === '') {
            Response::redirect('/question-editor');
        }

        $question = QuestionEditorService::find($id);

        if ($question === null) {
            Response::redirect('/question-editor?search=' . rawurlencode($id));
        }

        $questionId = (string) ($question['id'] ?? $id);
        $references = StudyLibraryService::forQuestion($questionId);
        $linkRecords = array_values(array_filter(
            App::storage()->all('study-question-links'),
            static fn(mixed $link): bool => is_array($link)
                && (string) ($link['question_id'] ?? '') === $questionId
        ));
        $sourceIds = [];
        foreach ((array) ($question['source_ids'] ?? []) as $sourceId) {
            if (is_scalar($sourceId) && trim((string) $sourceId) !== '') {
                $sourceIds[trim((string) $sourceId)] = true;
            }
        }
        foreach ($linkRecords as $link) {
            foreach ((array) ($link['source_ids'] ?? []) as $sourceId) {
                if (is_scalar($sourceId) && trim((string) $sourceId) !== '') {
                    $sourceIds[trim((string) $sourceId)] = true;
                }
            }
        }
        foreach ($references as $reference) {
            foreach ((array) ($reference['source_ids'] ?? []) as $sourceId) {
                if (is_scalar($sourceId) && trim((string) $sourceId) !== '') {
                    $sourceIds[trim((string) $sourceId)] = true;
                }
            }
        }
        $sourceById = [];
        foreach (SourceRegistryService::all() as $source) {
            if (is_array($source) && is_scalar($source['id'] ?? null)) {
                $sourceById[(string) $source['id']] ??= $source;
            }
        }
        $snapshots = App::storage()->all('source-url-health');
        $urlAudit = SourceUrlHealthSnapshot::inspect($snapshots, array_values($sourceById));
        $urlStatusById = [];
        if (str_starts_with((string) ($urlAudit['status'] ?? ''), 'CURRENT_')) {
            foreach ((array) ($snapshots[0]['records'] ?? []) as $record) {
                if (is_array($record) && is_scalar($record['source_id'] ?? null)) {
                    $urlStatusById[(string) $record['source_id']] = [
                        'status' => (string) ($record['status'] ?? 'UNKNOWN'),
                        'checked_at_utc' => (string) ($record['checked_at_utc'] ?? ''),
                        'http_status' => $record['http_status'] ?? null,
                        'final_url' => (string) ($record['final_url'] ?? $record['url'] ?? ''),
                    ];
                }
            }
        }
        $sources = [];
        foreach (array_keys($sourceIds) as $sourceId) {
            $source = $sourceById[$sourceId] ?? null;
            $sources[] = [
                'id' => $sourceId,
                'record' => is_array($source) ? $source : null,
                'url_status' => $urlStatusById[$sourceId] ?? null,
            ];
        }
        $selectionHolds = array_values(array_filter(
            App::storage()->all('question-reuse-overrides'),
            static fn(mixed $override): bool => is_array($override)
                && (string) ($override['question_id'] ?? '') === $questionId
                && ($override['override_type'] ?? '') === 'EXCLUDE'
        ));
        $return = Request::queryString('return');
        $returnPath = is_string($return) ? parse_url($return, PHP_URL_PATH) : false;
        if (!is_string($return) || $return === '' || $return[0] !== '/'
            || str_starts_with($return, '//') || $returnPath !== '/question-editor') {
            $return = '/question-editor';
        }

        self::renderDeveloper(
            'developer/question-inspector',
            [
                'pageTitle' => 'Question Inspector',
                'question' => $question,
                'studyReferences' => $references,
                'studyLinkRecords' => $linkRecords,
                'sources' => $sources,
                'sourceAuditStatus' => (string) ($urlAudit['status'] ?? 'UNAVAILABLE'),
                'sourceAuditGeneratedAt' => (string) ($urlAudit['summary']['generated_at_utc'] ?? ''),
                'selectionHolds' => $selectionHolds,
                'returnUrl' => $return,
            ]
        );
    }

    private static function proposal(string $proposalId): void
    {
        $proposal = AcademicReviewProposalService::find($proposalId);
        if ($proposal === null) {
            Response::redirect('/question-editor?proposal_batch=695');
        }

        $candidate = is_array($proposal['proposed_question'] ?? null) ? $proposal['proposed_question'] : [];
        $proposalStudy = is_array($proposal['study_relationship'] ?? null) ? $proposal['study_relationship'] : [];
        $studySectionUrl = '';
        $studyMaterialId = trim((string) ($proposalStudy['material_id'] ?? ''));
        $studySectionId = trim((string) ($proposalStudy['section_id'] ?? ''));
        if (!empty($proposalStudy['current_section_exists']) && $studyMaterialId !== '' && $studySectionId !== '') {
            $material = StudyLibraryService::find($studyMaterialId);
            $lesson = is_array($material['lesson'] ?? null) ? $material['lesson'] : [];
            foreach ((array) ($lesson['sections'] ?? []) as $section) {
                if (!is_array($section) || (string) ($section['id'] ?? '') !== $studySectionId) {
                    continue;
                }
                $lessonId = trim((string) ($lesson['id'] ?? ''));
                if ($lessonId !== '') {
                    $anchor = StudyLibraryService::lessonSectionAnchorId($lessonId, $studySectionId);
                    $studySectionUrl = '/study-library?material=' . rawurlencode($studyMaterialId)
                        . '#' . rawurlencode($anchor);
                }
                break;
            }
        }
        $question = [
            'id' => (string) ($proposal['question_id'] ?? ''),
            'question' => (string) ($candidate['stem'] ?? ''),
            'choices' => is_array($candidate['choices'] ?? null) ? $candidate['choices'] : [],
            'answer' => (string) ($candidate['proposed_answer'] ?? ''),
            'explanation' => (string) ($candidate['explanation'] ?? ''),
            'difficulty' => (string) ($candidate['difficulty'] ?? 'Unknown'),
            'status' => 'PENDING_PROPOSAL',
            'taxonomy' => [
                'board_id' => (string) ($proposal['board_id'] ?? ''),
                'subject_id' => (string) ($proposal['subject_id'] ?? ''),
                'domain_id' => (string) ($proposal['domain_id'] ?? ''),
                'topic_id' => (string) ($proposal['topic_id'] ?? ''),
                'concept_id' => (string) ($proposal['concept_id'] ?? ''),
            ],
            'source_ids' => is_array($proposal['source_ids'] ?? null) ? $proposal['source_ids'] : [],
        ];
        $sources = [];
        foreach ((array) ($proposal['source_evidence'] ?? []) as $sourceEvidence) {
            if (!is_array($sourceEvidence)) {
                continue;
            }
            $record = is_array($sourceEvidence['source_record_snapshot'] ?? null)
                ? $sourceEvidence['source_record_snapshot']
                : null;
            $sources[] = [
                'id' => (string) ($sourceEvidence['source_id'] ?? ''),
                'record' => $record,
                'url_status' => null,
                'evidence_status' => (string) ($sourceEvidence['source_evidence_status'] ?? 'SOURCE_REVIEW_PENDING'),
                'limitations' => is_array($sourceEvidence['limitations'] ?? null) ? $sourceEvidence['limitations'] : [],
            ];
        }

        $return = Request::queryString('return');
        $returnPath = is_string($return) ? parse_url($return, PHP_URL_PATH) : false;
        if (!is_string($return) || $return === '' || $return[0] !== '/'
            || str_starts_with($return, '//') || $returnPath !== '/question-editor') {
            $return = '/question-editor?proposal_batch=695';
        }

        self::renderDeveloper(
            'developer/question-inspector',
            [
                'pageTitle' => 'Academic Review Proposal',
                'question' => $question,
                'proposal' => $proposal,
                'isProposal' => true,
                'studyReferences' => [],
                'studyLinkRecords' => [],
                'sources' => $sources,
                'proposalStudyUrl' => $studySectionUrl,
                'sourceAuditStatus' => 'NOT_CHECKED_FOR_BATCH713_PROPOSAL',
                'sourceAuditGeneratedAt' => '',
                'selectionHolds' => [],
                'returnUrl' => $return,
            ]
        );
    }
}
