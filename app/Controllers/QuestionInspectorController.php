<?php

declare(strict_types=1);

namespace App\Controllers;
use App\Core\App;
use App\Core\Request;
use App\Core\Response;
use App\Services\Question\QuestionEditorService;
use App\Services\RepositoryHealth\SourceUrlHealthSnapshot;
use App\Services\Study\SourceRegistryService;
use App\Services\Study\StudyLibraryService;

class QuestionInspectorController extends BaseDeveloperController
{
    public static function index(): void
    {
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
}
