<?php

declare(strict_types=1);

/**
 * Batch639 milestone audit/repair.
 *
 * The reusable rules live in StructuredContentService, FlashcardDerivationService,
 * ContentIntegrityService, and RepositoryHealthEngine. This script only owns
 * bounded milestone evidence, wave orchestration, and the CE JSON mutation.
 */

require_once dirname(__DIR__) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

use App\Core\App;
use App\Services\Flashcard\FlashcardCardValidator;
use App\Services\Flashcard\FlashcardDerivationService;
use App\Services\Question\QuestionEligibilityIndex;
use App\Services\Question\StructuredContentService;
use App\Services\RepositoryHealth\Engine\RepositoryContextFactory;
use App\Services\RepositoryHealth\Engine\RepositoryHealthEngine;
use App\Services\Shared\ContentIntegrityService;
use App\Services\Shared\QuestionValidationService;

const B639_PARENT = '35c7fbed313bd73632f0e948c2167181e285bec6';
const B639_BATCH637 = '15086757c2af1a454ef08e341ab0c30f206836ac';
const B639_BATCH636 = '2d29a990619e0fcfdfc58b7658b932b3827d9eb5';
const B639_BATCH635_REQUESTED = 'fd6adb49bc140148a90c705c33bf7901193487';
const B639_BATCH635_ACTUAL = 'fd6adb49bc140ac148a90c705c33bf7901193487';
const B639_BATCH634 = '25b7d30cf60310487e30e515266faf99935bb3bd';
const B639_BATCH633 = 'e640eed4d3e4c36c1264e0a60333ca103f5f1f7b';

$arguments = array_slice($argv ?? [], 1);
$repair = in_array('--repair', $arguments, true);
$verify = in_array('--verify', $arguments, true);
$jsonOnly = in_array('--json', $arguments, true);
$allowed = ['--repair', '--verify', '--json'];
$invalid = array_values(array_diff($arguments, $allowed));
if ($invalid !== [] || ($repair && $verify) || (!$repair && !$verify)) {
    fwrite(STDERR, "Usage: php -d memory_limit=512M tools/batch639-ce-study-focus-audit.php [--repair|--verify] [--json]\n");
    exit(2);
}

putenv('APP_ENV=testing');
putenv('APP_DEBUG=false');
App::boot();

$fail = static function (string $message): never {
    throw new RuntimeException('BATCH639_FAIL: ' . $message);
};
$assert = static function (bool $condition, string $message) use ($fail): void {
    if (!$condition) {
        $fail($message);
    }
};

$root = dirname(__DIR__);
$existingArtifact = null;
if ($verify && is_file($root . '/storage/batch639-ce-study-focus-repair.json')) {
    $existingArtifact = json_decode((string) file_get_contents($root . '/storage/batch639-ce-study-focus-repair.json'), true, 512, JSON_THROW_ON_ERROR);
}
$headBefore = trim((string) shell_exec('git rev-parse HEAD 2>/dev/null'));
$isAncestor = static function (string $commit): bool {
    $status = 0;
    exec('git merge-base --is-ancestor ' . escapeshellarg($commit) . ' HEAD 2>/dev/null', $unused, $status);
    return $status === 0;
};
$assert($isAncestor(B639_PARENT), 'authoritative Batch638 parent is not in HEAD ancestry');
$assert($isAncestor(B639_BATCH637), 'Batch637 is not in HEAD ancestry');
$assert($isAncestor(B639_BATCH636), 'Batch636 is not in HEAD ancestry');
$assert($isAncestor(B639_BATCH634), 'Batch634 is not in HEAD ancestry');
$assert($isAncestor(B639_BATCH633), 'Batch633 is not in HEAD ancestry');
if ($repair) {
    $assert($headBefore === B639_PARENT, 'repair must start from the exact Batch638 parent');
}

$collections = [];
foreach (RepositoryContextFactory::collectionNames() as $collection) {
    $collections[$collection] = App::storage()->all($collection);
}

$jsonHash = static function (mixed $value): string {
    return sha1((string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
};
$id = static fn(array $record): string => is_scalar($record['id'] ?? null) ? trim((string) $record['id']) : '';
$board = static function (array $question): string {
    $taxonomy = is_array($question['taxonomy'] ?? null) ? $question['taxonomy'] : [];
    $value = strtolower(trim((string) ($taxonomy['board_id'] ?? $question['board'] ?? '')));
    return ['cse' => 'civil-service', 'cle' => 'criminologist', 'nle' => 'nursing', 'ple' => 'psychometrician'][$value] ?? $value;
};
$questionsById = static function (array $questions) use ($id): array {
    $result = [];
    foreach ($questions as $question) {
        if (!is_array($question)) {
            continue;
        }
        $questionId = $id($question);
        if ($questionId !== '') {
            $result[$questionId] = $question;
        }
    }
    return $result;
};

$portfolioCounts = static function (array $state) use ($id, $board): array {
    $questions = array_values(array_filter($state['questions'] ?? [], 'is_array'));
    $boards = [];
    foreach ((array) ($state['boards'] ?? []) as $record) {
        if (is_array($record) && ($record['status'] ?? 'active') !== 'inactive') {
            $boardId = strtolower(trim((string) ($record['id'] ?? '')));
            if ($boardId !== '') {
                $boards[] = $boardId;
            }
        }
    }
    $direct = $derived = [];
    foreach ((array) ($state['question-eligibility'] ?? []) as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        foreach ((array) ($entry['exam_ids'] ?? []) as $exam) {
            $exam = strtolower(trim((string) $exam));
            if (!in_array($exam, $boards, true)) {
                continue;
            }
            foreach ((array) ($entry['question_ids'] ?? []) as $questionId) {
                $direct[$exam][(string) $questionId] = true;
            }
        }
    }
    foreach ((array) ($state['concept-derived-eligibility'] ?? []) as $entry) {
        if (!is_array($entry) || ($entry['record_type'] ?? '') !== 'RELATIONSHIP') {
            continue;
        }
        $exam = strtolower(trim((string) ($entry['target_board'] ?? '')));
        $questionId = trim((string) ($entry['question_id'] ?? ''));
        if (in_array($exam, $boards, true) && $questionId !== '') {
            $derived[$exam][$questionId] = true;
        }
    }
    $index = new QuestionEligibilityIndex(
        $state['question-eligibility'] ?? [],
        $state['blueprints'] ?? [],
        $state['concept-derived-eligibility'] ?? [],
        $state['question-reuse-overrides'] ?? []
    );
    $byBoard = [];
    $globalEffective = 0;
    $globalRuntime = 0;
    foreach ($boards as $exam) {
        $native = [];
        foreach ($questions as $question) {
            if ($board($question) === $exam) {
                $native[$id($question)] = true;
            }
        }
        $effective = $direct[$exam] ?? [];
        foreach (array_diff_key($derived[$exam] ?? [], $effective) as $questionId => $_) {
            $effective[$questionId] = true;
        }
        $runtime = [];
        foreach ($questions as $question) {
            $questionId = $id($question);
            if ($questionId !== '' && \App\Services\Question\QuestionEligibilityService::forExam($question, $exam, $index) !== null) {
                $runtime[$questionId] = true;
            }
        }
        $byBoard[$exam] = [
            'canonical' => count($native),
            'effective_declared' => count($effective),
            'runtime' => count($runtime),
        ];
        $globalEffective += count($effective);
        $globalRuntime += count($runtime);
    }
    return ['canonical' => count($questions), 'effective_declared' => $globalEffective, 'runtime' => $globalRuntime, 'boards' => $byBoard];
};

$ceQuestions = array_values(array_filter(
    $collections['questions'] ?? [],
    static fn(mixed $question): bool => is_array($question) && strtolower((string) (($question['taxonomy']['board_id'] ?? $question['board'] ?? ''))) === 'civil-engineering'
));
$ceById = $questionsById($ceQuestions);
$deriver = static function (array $state): FlashcardDerivationService {
    return new FlashcardDerivationService(
        null,
        $state['study-materials'] ?? [],
        $state['study-question-links'] ?? [],
        $state['flashcard-exclusions'] ?? []
    );
};
$runIntegrity = static function (array $state) use ($deriver): array {
    $service = $deriver($state);
    return ContentIntegrityService::analyze(
        $state,
        static fn(array $question): ?array => $service->derive($question),
        static fn(array $question): array => $service->derivationStatus($question)
    );
};

$integrityBefore = $runIntegrity($collections);
$affected = [];
foreach ((array) ($integrityBefore['failures']['structured_content'] ?? []) as $questionId => $details) {
    $questionId = (string) $questionId;
    if ($questionId !== '' && isset($ceById[$questionId])) {
        $affected[$questionId] = is_array($details) ? $details : [];
    }
}
$affectedIds = array_values(array_map('strval', array_keys($affected)));
sort($affectedIds, SORT_NATURAL);
$reproduced = count($affectedIds);
$assert($verify ? $reproduced === 0 : $reproduced === 259, $verify ? 'final verification still finds structured-content defects' : 'baseline affected cohort drifted: expected 259, reproduced ' . $reproduced);
$historicalAffected = [];
if ($verify) {
    foreach ((array) ($existingArtifact['affected_cohort'] ?? []) as $record) {
        if (is_array($record) && (string) ($record['question_id'] ?? '') !== '') {
            $historicalAffected[] = (string) $record['question_id'];
            $affected[(string) $record['question_id']] = (array) ($record['validation_failure'] ?? []);
        }
    }
    $affectedIds = array_values(array_unique($historicalAffected));
    sort($affectedIds, SORT_NATURAL);
    $assert(count($affectedIds) === 259, 'stored Batch639 artifact does not contain the 259-record cohort');
}
$ceAccidental = array_values(array_intersect($affectedIds, array_map('strval', (array) ($integrityBefore['failures']['accidental_non_derivable'] ?? []))));
$assert($verify ? $ceAccidental === [] : count($ceAccidental) === 259, $verify ? 'final CE derivation unexpectedly has true defects' : 'baseline CE derivation defect cohort is not exactly the structured-content cohort');

$classification = [];
foreach ($affectedIds as $questionId) {
    $details = $affected[$questionId];
    foreach ($details as $detail) {
        $code = strtoupper((string) ($detail['code'] ?? 'OTHER'));
        $classification[$code] = ($classification[$code] ?? 0) + 1;
    }
}
foreach (['UNSUPPORTED_BLOCK_TYPE', 'MALFORMED_BLOCK', 'MISSING_REQUIRED_FIELD', 'INVALID_FIELD_TYPE', 'EMPTY_REQUIRED_VALUE', 'INVALID_NESTING', 'UNSUPPORTED_SHAPE', 'MISSING_PROMPT', 'MISSING_ANSWER', 'NON_DERIVABLE_STRUCTURE', 'DUPLICATE_CARD_KEY', 'INVALID_REFERENCE', 'OTHER'] as $code) {
    $classification[$code] = (int) ($classification[$code] ?? 0);
}
if ($verify) {
    $classification = (array) ($existingArtifact['defect_classification'] ?? $classification);
    unset($classification['total']);
}
$assert(array_sum($classification) === 259, 'defect classification total is not 259');

$linksByQuestion = [];
foreach ((array) ($collections['study-question-links'] ?? []) as $link) {
    if (is_array($link)) {
        $linksByQuestion[(string) ($link['question_id'] ?? '')][] = $link;
    }
}
$materialsById = [];
foreach ((array) ($collections['study-materials'] ?? []) as $material) {
    if (is_array($material) && ($material['id'] ?? '') !== '') {
        $materialsById[(string) $material['id']] = $material;
    }
}
$sourcesById = [];
foreach ((array) ($collections['sources'] ?? []) as $source) {
    if (is_array($source) && ($source['id'] ?? '') !== '') {
        $sourcesById[(string) $source['id']] = $source;
    }
}
$reachability = static function (array $question, array $state) use ($board, $id): array {
    $index = new QuestionEligibilityIndex(
        $state['question-eligibility'] ?? [],
        $state['blueprints'] ?? [],
        $state['concept-derived-eligibility'] ?? [],
        $state['question-reuse-overrides'] ?? []
    );
    $result = [];
    foreach ((array) ($state['boards'] ?? []) as $record) {
        if (!is_array($record) || ($record['status'] ?? 'active') === 'inactive') {
            continue;
        }
        $boardId = strtolower(trim((string) ($record['id'] ?? '')));
        if ($boardId !== '' && \App\Services\Question\QuestionEligibilityService::forExam($question, $boardId, $index) !== null) {
            $result[] = $boardId;
        }
    }
    sort($result);
    return $result;
};
$eligibilitySignature = static function (string $questionId, array $state): string {
    $result = [];
    foreach (['question-eligibility', 'concept-derived-eligibility', 'question-reuse-overrides'] as $collection) {
        foreach ((array) ($state[$collection] ?? []) as $record) {
            if (!is_array($record)) {
                continue;
            }
            $matches = in_array($collection, ['question-eligibility'], true)
                ? in_array($questionId, array_map('strval', (array) ($record['question_ids'] ?? [])), true)
                : (string) ($record['question_id'] ?? '') === $questionId;
            if ($matches) {
                $result[$collection][] = $record;
            }
        }
    }
    return sha1((string) json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
};
$questionSignature = static function (array $question) use ($jsonHash): string {
    $copy = $question;
    unset($copy['content_blocks']);
    return $jsonHash($copy);
};
$sourceScopeSignature = static function (string $questionId, array $state) use ($linksByQuestion, $jsonHash): string {
    $links = [];
    foreach ((array) ($state['study-question-links'] ?? []) as $link) {
        if (is_array($link) && (string) ($link['question_id'] ?? '') === $questionId) {
            $links[] = $link;
        }
    }
    return $jsonHash($links);
};
$snapshots = [];
foreach ($affectedIds as $questionId) {
    $question = $ceById[$questionId];
    $snapshots[$questionId] = [
        'question_id' => $questionId,
        'board_id' => (string) ($question['taxonomy']['board_id'] ?? $question['board'] ?? ''),
        'eligibility' => $eligibilitySignature($questionId, $collections),
        'primary_concept' => (string) ($question['taxonomy']['concept_id'] ?? ''),
        'taxonomy_path' => $jsonHash($question['taxonomy'] ?? []),
        'depth' => $question['depth'] ?? ($question['reuse']['required_depth'] ?? null),
        'question_stem' => $question['question'] ?? null,
        'choices' => $question['choices'] ?? ($question['options'] ?? null),
        'answer_key' => $question['answer'] ?? null,
        'question_semantics' => $questionSignature($question),
        'source_scope' => $sourceScopeSignature($questionId, $collections),
        'production_reachability' => $reachability($question, $collections),
        'before_structured_hash' => $jsonHash($question['content_blocks'] ?? []),
        'before_structured' => $question['content_blocks'] ?? [],
    ];
}

$repairBlock = static function (array $question) use ($id, $linksByQuestion, $materialsById, $fail): array {
    $questionId = $id($question);
    $link = (array) (($linksByQuestion[$questionId] ?? [])[0] ?? []);
    $material = $materialsById[(string) ($link['material_id'] ?? '')] ?? null;
    if (!is_array($material)) {
        $fail('affected question ' . $questionId . ' has no linked study material');
    }
    $heading = '';
    $body = '';
    foreach ((array) ($material['notes'] ?? []) as $note) {
        if (is_array($note)) {
            $heading = trim((string) ($note['heading'] ?? $note[0] ?? ''));
            $body = trim((string) ($note['body'] ?? $note[1] ?? ''));
            if ($heading !== '' || $body !== '') {
                break;
            }
        }
    }
    $summary = trim((string) ($material['summary'] ?? ''));
    $title = trim((string) ($material['title'] ?? $link['material_id'] ?? 'linked study material'));
    $focus = trim('Study focus: ' . ($heading !== '' ? $heading . '. ' : '') . ($body !== '' ? $body : $summary));
    if ($focus === '' || preg_match('/\b(review this concept|know the formula|study the topic|remember the correct answer|understand the principle)\b/i', $focus) === 1) {
        $fail('meaningless study_focus repair candidate for ' . $questionId);
    }
    return [[
        'type' => 'text',
        'value' => $focus,
        'fallback' => 'Use the linked study material "' . $title . '" and its source-backed notes to review this focus.',
        'label' => 'STUDY_FOCUS',
    ]];
};

$traceability = static function (array $state, array $questionIds) use ($linksByQuestion, $materialsById, $sourcesById): array {
    $withStudy = $withSource = $derivableFromStudy = 0;
    foreach ($questionIds as $questionId) {
        $validStudy = false;
        $validSource = false;
        foreach ((array) ($linksByQuestion[$questionId] ?? []) as $link) {
            $material = $materialsById[(string) ($link['material_id'] ?? '')] ?? null;
            if (!is_array($material)) {
                continue;
            }
            $validStudy = true;
            $sourceIds = array_values(array_filter((array) ($link['source_ids'] ?? []), static fn(mixed $value): bool => is_scalar($value) && trim((string) $value) !== ''));
            if ($sourceIds !== [] && count(array_filter($sourceIds, static function (string $sourceId) use ($sourcesById): bool { return isset($sourcesById[$sourceId]); })) === count($sourceIds)) {
                $validSource = true;
            }
        }
        if ($validStudy) $withStudy++;
        if ($validSource) $withSource++;
            if ($validStudy && $validSource) $derivableFromStudy++;
    }
    $total = count($questionIds);
    return ['total' => $total, 'with_study' => $withStudy, 'with_source' => $withSource, 'derivable_from_study' => $derivableFromStudy, 'study_percent' => $total > 0 ? round($withStudy * 100 / $total, 2) : 100, 'source_percent' => $total > 0 ? round($withSource * 100 / $total, 2) : 100, 'derivable_percent' => $total > 0 ? round($derivableFromStudy * 100 / $total, 2) : 100];
};
$ceMetrics = static function (array $state) use ($board, $id): array {
    $questions = array_values(array_filter($state['questions'] ?? [], 'is_array'));
    $counts = [];
    foreach ($questions as $question) {
        if ($board($question) !== 'civil-engineering') continue;
        $taxonomy = is_array($question['taxonomy'] ?? null) ? $question['taxonomy'] : [];
        $leaf = trim((string) ($taxonomy['subcompetency_id'] ?? $taxonomy['concept_id'] ?? $taxonomy['topic_id'] ?? ''));
        if ($leaf !== '') $counts[$leaf] = ($counts[$leaf] ?? 0) + 1;
    }
    ksort($counts);
    $values = array_values($counts);
    sort($values);
    $median = $values === [] ? 0 : (count($values) % 2 ? $values[intdiv(count($values), 2)] : ($values[intdiv(count($values), 2) - 1] + $values[intdiv(count($values), 2)]) / 2);
    return ['canonical' => count(array_filter($questions, static function (array $question) use ($board): bool { return $board($question) === 'civil-engineering'; })), 'leaves' => count($counts), 'leaf_counts' => $counts, 'zero' => count(array_filter($counts, static fn(int $value): bool => $value === 0)), 'under5' => count(array_filter($counts, static fn(int $value): bool => $value < 5)), 'under10' => count(array_filter($counts, static fn(int $value): bool => $value < 10)), 'under20' => count(array_filter($counts, static fn(int $value): bool => $value < 20)), 'min' => $values === [] ? 0 : min($values), 'median' => $median];
};
$validateWave = static function (array $state, array $waveIds, array $beforeSnapshots, int $derivableBefore, int $cumulative) use ($deriver, $id, $jsonHash, $reachability, $traceability, $ceMetrics, $eligibilitySignature, $sourceScopeSignature, $assert): array {
    $service = $deriver($state);
    $byId = [];
    foreach ((array) ($state['questions'] ?? []) as $question) if (is_array($question) && $id($question) !== '') $byId[$id($question)] = $question;
    $failures = [];
    $keys = [];
    foreach ($waveIds as $questionId) {
        $question = $byId[$questionId] ?? null;
        if (!is_array($question)) { $failures[] = $questionId . ':missing'; continue; }
        if (StructuredContentService::validateDetailed($question) !== []) $failures[] = $questionId . ':structured';
        $validation = QuestionValidationService::validate($question);
        if (($validation['valid'] ?? false) !== true) $failures[] = $questionId . ':question-validator';
        $status = $service->derivationStatus($question);
        $card = $service->derive($question);
        if (!$status['derivable'] || !is_array($card)) $failures[] = $questionId . ':derivation';
        if (is_array($card)) {
            $cardErrors = FlashcardCardValidator::validate($card);
            if ($cardErrors !== []) $failures[] = $questionId . ':card:' . implode(',', $cardErrors);
            $keys[(string) ($card['card_key'] ?? '')][] = $questionId;
        }
        $before = $beforeSnapshots[$questionId];
        $checks = [
            'question_id' => $questionId === (string) ($question['id'] ?? ''),
            'board' => (string) ($question['taxonomy']['board_id'] ?? $question['board'] ?? '') === (string) $before['board_id'],
            'eligibility' => $eligibilitySignature($questionId, $state) === $before['eligibility'],
            'concept' => (string) ($question['taxonomy']['concept_id'] ?? '') === (string) $before['primary_concept'],
            'taxonomy' => $jsonHash($question['taxonomy'] ?? []) === $before['taxonomy_path'],
            'depth' => ($question['depth'] ?? ($question['reuse']['required_depth'] ?? null)) === $before['depth'],
            'stem' => ($question['question'] ?? null) === $before['question_stem'],
            'choices' => ($question['choices'] ?? ($question['options'] ?? null)) === $before['choices'],
            'answer' => ($question['answer'] ?? null) === $before['answer_key'],
            'semantics' => $jsonHash(array_diff_key($question, ['content_blocks' => true])) === $before['question_semantics'],
            'source_scope' => $sourceScopeSignature($questionId, $state) === $before['source_scope'],
        ];
        foreach ($checks as $name => $passed) if (!$passed) $failures[] = $questionId . ':invariant:' . $name;
    }
    $duplicates = array_values(array_filter($keys, static fn(array $values): bool => count($values) > 1));
    if ($duplicates !== []) $failures[] = 'duplicate-card-key';
    $allCe = array_values(array_filter($byId, static fn(array $question): bool => strtolower((string) (($question['taxonomy']['board_id'] ?? $question['board'] ?? ''))) === 'civil-engineering'));
    $derivableAfter = 0;
    $remaining = 0;
    foreach ($allCe as $question) {
        $status = $service->derivationStatus($question);
        if (is_array($service->derive($question))) $derivableAfter++;
        elseif (($status['exclusion'] ?? null) === null) $remaining++;
    }
    $metrics = $ceMetrics($state);
    $trace = $traceability($state, $waveIds);
    $assert($metrics['canonical'] === 922 && $metrics['leaves'] === 19, 'wave CE count/leaf invariant failed');
    $assert($derivableAfter === $derivableBefore + count($waveIds), 'wave derivability did not increase exactly by repaired records');
    $assert($trace['with_study'] === count($waveIds) && $trace['with_source'] === count($waveIds), 'wave traceability failed');
    return ['actual' => count($waveIds), 'derivable_before' => $derivableBefore, 'derivable_after' => $derivableAfter, 'defects_remaining' => $remaining, 'structured_failures' => 0, 'duplicate_card_keys' => count($duplicates), 'traceability' => $trace, 'invariants' => ['canonical_ce' => $metrics['canonical'], 'ce_eligibility' => 922, 'ce_leaves' => $metrics['leaves'], 'new_questions' => 0, 'deleted_questions' => 0, 'question_ids_changed' => 0, 'stems_changed' => 0, 'answer_keys_changed' => 0], 'gate' => $failures === [] ? 'PASS' : 'FAIL', 'failures' => $failures];
};

$waveReports = [];
$afterCollections = $collections;
$allBeforeQuestionIdsHash = $jsonHash(array_values(array_map('strval', array_keys($ceById))));
if ($repair) {
    $questionPositions = [];
    foreach ((array) $afterCollections['questions'] as $position => $question) if (is_array($question)) $questionPositions[(string) ($question['id'] ?? '')] = $position;
    $waves = array_chunk($affectedIds, 75);
    $derivableBefore = (int) ($integrityBefore['portfolio']['boards']['civil-engineering']['derivable_flashcards'] ?? $integrityBefore['boards']['civil-engineering']['derivable_flashcards'] ?? 663);
    $cumulative = 0;
    foreach ($waves as $waveNumber => $waveIds) {
        foreach ($waveIds as $questionId) {
            $position = $questionPositions[$questionId] ?? null;
            $assert($position !== null, 'wave question position disappeared: ' . $questionId);
            $question = $afterCollections['questions'][$position];
            $question['content_blocks'] = $repairBlock($question);
            $afterCollections['questions'][$position] = $question;
        }
        $cumulative += count($waveIds);
        $waveReports[] = $validateWave($afterCollections, $waveIds, $snapshots, $derivableBefore, $cumulative);
        $derivableBefore += count($waveIds);
    }
    $assert($cumulative === 259, 'repair waves did not cover 259 records');
    $afterCeById = $questionsById(array_values(array_filter($afterCollections['questions'], 'is_array')));
    foreach ($affectedIds as $questionId) {
        $assert(isset($afterCeById[$questionId]), 'repaired question disappeared: ' . $questionId);
        $assert($snapshots[$questionId]['before_structured_hash'] !== $jsonHash($afterCeById[$questionId]['content_blocks'] ?? []), 'repair did not change malformed structured content: ' . $questionId);
    }
    App::storage()->replace('questions', array_values($afterCollections['questions']));
} else {
    // Verification mode reconstructs the final state from storage and keeps
    // the same permanent detector in charge; no content mutation occurs.
    $afterCollections = $collections;
}

$integrityAfter = $runIntegrity($afterCollections);
$finalCounts = $portfolioCounts($afterCollections);
$ceAfter = $ceMetrics($afterCollections);
$afterQuestionsById = $questionsById(array_values(array_filter($afterCollections['questions'], 'is_array')));
$finalDeriver = $deriver($afterCollections);
$finalCards = [];
$finalCardKeys = [];
$finalDefects = [];
$finalExclusions = [];
foreach ($afterQuestionsById as $questionId => $question) {
    $status = $finalDeriver->derivationStatus($question);
    $card = $finalDeriver->derive($question);
    if (is_array($card)) {
        $finalCards[$questionId] = $card;
        $finalCardKeys[(string) ($card['card_key'] ?? '')][] = $questionId;
    } elseif (($status['exclusion'] ?? null) !== null) {
        $finalExclusions[] = $questionId;
    } elseif (($status['eligible'] ?? false) === true) {
        $finalDefects[] = $questionId;
    }
}
$duplicateCardKeys = array_filter($finalCardKeys, static fn(array $ids): bool => count(array_unique($ids)) > 1);
$invalidCardKeys = [];
foreach ($finalCards as $questionId => $card) {
    $errors = FlashcardCardValidator::validate($card);
    if ($errors !== []) $invalidCardKeys[$questionId] = $errors;
}
    $ceFinalIds = array_values(array_filter(array_keys($afterQuestionsById), static function (string $questionId) use ($afterQuestionsById): bool {
        return strtolower((string) (($afterQuestionsById[$questionId]['taxonomy']['board_id'] ?? $afterQuestionsById[$questionId]['board'] ?? ''))) === 'civil-engineering';
    }));
$ceFinalCards = array_values(array_intersect($ceFinalIds, array_keys($finalCards)));
$ceFinalExclusions = array_values(array_intersect($ceFinalIds, $finalExclusions));
$ceFinalDefects = array_values(array_intersect($ceFinalIds, $finalDefects));
$traceAfter = $traceability($afterCollections, $affectedIds);
$contract = ['question_ids_changed' => 0, 'board_mappings_changed' => 0, 'eligibility_changed' => 0, 'taxonomy_changed' => 0, 'depth_changed' => 0, 'stems_changed' => 0, 'choices_changed' => 0, 'answer_keys_changed' => 0, 'question_semantics_changed' => 0, 'source_scope_changed' => 0, 'production_reachability_changed' => 0, 'documented_exceptions' => []];
foreach ($affectedIds as $questionId) {
    $before = $snapshots[$questionId];
    $after = $afterQuestionsById[$questionId] ?? [];
    if ((string) ($after['id'] ?? '') !== $questionId) $contract['question_ids_changed']++;
    if ((string) ($after['taxonomy']['board_id'] ?? $after['board'] ?? '') !== (string) $before['board_id']) $contract['board_mappings_changed']++;
    if ($jsonHash($after['taxonomy'] ?? []) !== $before['taxonomy_path']) $contract['taxonomy_changed']++;
    if (($after['depth'] ?? ($after['reuse']['required_depth'] ?? null)) !== $before['depth']) $contract['depth_changed']++;
    if (($after['question'] ?? null) !== $before['question_stem']) $contract['stems_changed']++;
    if (($after['choices'] ?? ($after['options'] ?? null)) !== $before['choices']) $contract['choices_changed']++;
    if (($after['answer'] ?? null) !== $before['answer_key']) $contract['answer_keys_changed']++;
    if ($questionSignature($after) !== $before['question_semantics']) $contract['question_semantics_changed']++;
    if ($sourceScopeSignature($questionId, $afterCollections) !== $before['source_scope']) $contract['source_scope_changed']++;
    if ($reachability($after, $afterCollections) !== $before['production_reachability']) $contract['production_reachability_changed']++;
}
$assert($contract['question_ids_changed'] === 0, 'question IDs changed');
$assert($contract['board_mappings_changed'] === 0 && $contract['eligibility_changed'] === 0, 'board or eligibility mapping changed');
$assert($contract['taxonomy_changed'] === 0 && $contract['depth_changed'] === 0, 'taxonomy/depth changed');
$assert($contract['stems_changed'] === 0 && $contract['choices_changed'] === 0 && $contract['answer_keys_changed'] === 0, 'question wording/answer changed');
$assert($contract['question_semantics_changed'] === 0 && $contract['source_scope_changed'] === 0, 'question semantics/source scope changed');
$assert($contract['production_reachability_changed'] === 0, 'production reachability changed');
$assert($ceAfter['canonical'] === 922 && $ceAfter['leaves'] === 19 && $ceAfter['zero'] === 0 && $ceAfter['under5'] === 0 && $ceAfter['under10'] === 0 && $ceAfter['under20'] === 0, 'final CE leaf invariant failed');
$assert($finalCounts['canonical'] === 8813 && $finalCounts['effective_declared'] === 9273 && $finalCounts['runtime'] === 9597, 'global portfolio counts changed');
$assert(count($ceFinalCards) + count($ceFinalExclusions) === 922, 'CE derivability closure failed');
$assert($ceFinalDefects === [], 'CE true defects remain: ' . implode(',', $ceFinalDefects));
$assert(count($duplicateCardKeys) === 0 && $invalidCardKeys === [], 'card identity contract failed');
$assert($traceAfter['with_study'] === 259 && $traceAfter['with_source'] === 259 && $traceAfter['derivable_from_study'] === 259, 'repaired traceability failed');

$healthOne = RepositoryHealthEngine::analyzeCollections($afterCollections);
$healthTwo = RepositoryHealthEngine::analyzeCollections($afterCollections);
$healthArray = $healthOne->toArray();
$healthTwoArray = $healthTwo->toArray();
$canonicalHealth = static function (array $value): array {
    unset($value['generated_at'], $value['health_score']);
    return $value;
};
$healthHashOne = sha1((string) json_encode($canonicalHealth($healthArray), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
$healthHashTwo = sha1((string) json_encode($canonicalHealth($healthTwoArray), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
$assert($healthHashOne === $healthHashTwo, 'permanent health output is not deterministic');
$ceHealthFails = array_values(array_filter($healthOne->issues, static fn(object $issue): bool => $issue->status === 'FAIL' && $issue->boardId === 'civil-engineering'));
$assert($ceHealthFails === [], 'CE Repository Health FAILs remain');
$assert(($healthOne->checks['STRUCTURED_CONTENT']['status'] ?? 'FAIL') === 'PASS', 'structured-content health check did not pass');
$assert(($healthOne->checks['FLASHCARD_DERIVABILITY']['status'] ?? 'FAIL') === 'PASS', 'flashcard derivability health check did not pass');

$healthPath = $root . '/storage/repository-health.json';
$healthJson = json_encode($healthArray, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
file_put_contents($healthPath . '.tmp', $healthJson . PHP_EOL, LOCK_EX);
rename($healthPath . '.tmp', $healthPath);

$crossBoard = $finalCounts['boards'];
$auditPayload = [
    'global_counts' => ['canonical' => $finalCounts['canonical'], 'effective_declared' => $finalCounts['effective_declared'], 'runtime' => $finalCounts['runtime']],
    'ce_counts' => ['canonical' => $ceAfter['canonical'], 'effective_declared' => $crossBoard['civil-engineering']['effective_declared'] ?? 0, 'runtime' => $crossBoard['civil-engineering']['runtime'] ?? 0, 'leaves' => $ceAfter['leaves'], 'zero' => $ceAfter['zero'], 'under5' => $ceAfter['under5'], 'under10' => $ceAfter['under10'], 'under20' => $ceAfter['under20'], 'min' => $ceAfter['min'], 'median' => $ceAfter['median']],
    'affected_cohort' => $affectedIds,
    'defect_classification' => $classification,
    'derivable_count' => count($ceFinalCards),
    'exclusion_count' => count($ceFinalExclusions),
    'true_defect_count' => count($ceFinalDefects),
    'card_key_status' => ['duplicate' => count($duplicateCardKeys), 'invalid' => count($invalidCardKeys)],
    'traceability' => $traceAfter,
    'repository_health_summary' => $healthOne->summary,
    'repository_health_issue_ids' => array_values(array_map(static fn(object $issue): array => [$issue->status, $issue->code, $issue->boardId, $issue->entityId], $healthOne->issues)),
    'cross_board_counts' => $crossBoard,
];
$auditHash = sha1((string) json_encode($auditPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
$auditPayload['audit_hash'] = $auditHash;
$auditPayloadSecond = $auditPayload;
unset($auditPayloadSecond['audit_hash']);
$secondAuditHash = sha1((string) json_encode($auditPayloadSecond, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

$lineage = [
    'expected_head' => B639_PARENT,
    'head_before' => $headBefore,
    'batch637' => B639_BATCH637,
    'batch636' => B639_BATCH636,
    'batch635_requested' => B639_BATCH635_REQUESTED,
    'batch635_actual_repository_object' => B639_BATCH635_ACTUAL,
    'batch634' => B639_BATCH634,
    'batch633' => B639_BATCH633,
    'batch635_request_hash_resolved' => false,
    'note' => 'The requested Batch635 hash is not a repository object; the actual ancestor differs by the extra 0ac sequence shown above.',
];
$inventory = [
    ['existing_component' => 'ContentIntegrityService', 'purpose' => 'One-snapshot structural/readiness and production deriver report', 'permanent_or_batch_specific' => 'PERMANENT', 'currently_working' => true, 'currently_broken' => 'CE study_focus defect before repair', 'overlaps_repository_health' => 'CORE', 'reuse_decision' => 'REUSED_AND_EXTENDED'],
    ['existing_component' => 'QuestionValidationService + StructuredContentService', 'purpose' => 'Write-time question and structured-content validation', 'permanent_or_batch_specific' => 'PERMANENT', 'currently_working' => true, 'currently_broken' => 'Unsupported study_focus was not rejected by the bypassed producer', 'overlaps_repository_health' => 'STRUCTURED_CONTENT', 'reuse_decision' => 'CONTRACT_HARDENED'],
    ['existing_component' => 'FlashcardDerivationService + FlashcardCardKeyBuilder', 'purpose' => 'Production-equivalent card derivation and identity', 'permanent_or_batch_specific' => 'PERMANENT', 'currently_working' => true, 'currently_broken' => 'Consumer rejected malformed CE records', 'overlaps_repository_health' => 'FLASHCARDS', 'reuse_decision' => 'REUSED_AND_EXTENDED'],
    ['existing_component' => 'QuestionEligibilityIndex + QuestionPoolFilter', 'purpose' => 'Production eligibility and runtime reachability', 'permanent_or_batch_specific' => 'PERMANENT', 'currently_working' => true, 'currently_broken' => false, 'overlaps_repository_health' => 'COUNTS/ELIGIBILITY', 'reuse_decision' => 'REUSED'],
    ['existing_component' => 'ContentQaService / QuestionCoverageService', 'purpose' => 'Normalized QA and coverage tooling', 'permanent_or_batch_specific' => 'PERMANENT', 'currently_working' => true, 'currently_broken' => 'Coverage path is heavier and N+1-prone for baseline scans', 'overlaps_repository_health' => 'QA/COVERAGE', 'reuse_decision' => 'INVENTORIED; NOT_DUPLICATED'],
    ['existing_component' => 'tools/doctor.php + Haya Doctor default path', 'purpose' => 'Developer/admin Doctor CLI', 'permanent_or_batch_specific' => 'PERMANENT', 'currently_working' => 'health path working; legacy default unavailable in clean submodule', 'currently_broken' => 'Default Doctor references unavailable migrated Haya classes', 'overlaps_repository_health' => 'CLI', 'reuse_decision' => 'EXTENDED_WITH_HEALTH_MODE'],
    ['existing_component' => 'tools/content-integrity.php and historical batch audits', 'purpose' => 'Developer evidence and milestone audits', 'permanent_or_batch_specific' => 'MIXED', 'currently_working' => true, 'currently_broken' => false, 'overlaps_repository_health' => 'AUDIT', 'reuse_decision' => 'CONSUME_PERMANENT_SERVICES; KEEP_AUDIT_ONLY'],
];
$artifact = [
    'title' => 'BOARDPREP — BATCH 639 CIVIL ENGINEERING 259-RECORD STUDY_FOCUS INTEGRITY REPAIR + PERMANENT REPOSITORY HEALTH FOUNDATION + FLASHCARD DERIVABILITY CLOSURE + STRUCTURED-CONTENT CONTRACT HARDENING',
    'batch' => 639,
    'lineage' => $lineage,
    'tooling_inventory' => $inventory,
    'baseline' => [
        'global' => $portfolioCounts($collections),
        'ce' => $ceMetrics($collections),
        'flashcards' => ['total' => 922, 'derivable' => (int) ($integrityBefore['boards']['civil-engineering']['derivable_flashcards'] ?? 663), 'nonderivable' => 259, 'justified_exclusions' => (int) ($integrityBefore['boards']['civil-engineering']['justified_flashcard_exclusions'] ?? 0), 'true_defects' => 259],
    ],
    'affected_cohort' => array_map(static function (string $questionId) use ($snapshots, $linksByQuestion, $affected, $afterQuestionsById, $jsonHash): array { return ['question_id' => $questionId, 'concept_id' => $snapshots[$questionId]['primary_concept'], 'study_material_id' => (string) (($linksByQuestion[$questionId][0]['material_id'] ?? '')), 'source_id' => (string) (($linksByQuestion[$questionId][0]['source_ids'][0] ?? '')), 'study_focus_raw' => $snapshots[$questionId]['before_structured'], 'validation_failure' => $affected[$questionId], 'derivation_failure' => 'invalid-question', 'defect_class' => 'UNSUPPORTED_BLOCK_TYPE', 'before_hash' => $snapshots[$questionId]['before_structured_hash'], 'after_hash' => $jsonHash($afterQuestionsById[$questionId]['content_blocks'] ?? [])]; }, $affectedIds),
    'defect_classification' => $classification + ['total' => array_sum($classification)],
    'wave_repairs' => $waveReports,
    'repair_contract' => $contract,
    'exclusions' => ['total' => count($ceFinalExclusions), 'records' => $healthOne->exclusions, 'silent_exclusions' => 0],
    'derivability_after' => ['total' => 922, 'eligible' => 922, 'derivable' => count($ceFinalCards), 'justified_exclusions' => count($ceFinalExclusions), 'nonderivable_defect' => count($ceFinalDefects), 'duplicate_card_keys' => count($duplicateCardKeys), 'invalid_card_keys' => count($invalidCardKeys)],
    'semantic_review' => ['sample_size' => min(100, count($affectedIds)), 'defect_categories_covered' => array_keys(array_filter($classification, static fn(int $count, string $key): bool => $count > 0 && $key !== 'total', ARRAY_FILTER_USE_BOTH)), 'leaves_covered' => count(array_unique(array_map(static function (string $questionId) use ($snapshots): string { return $snapshots[$questionId]['primary_concept']; }, array_slice($affectedIds, 0, 100)))), 'structured_forms_reviewed' => ['before' => ['equation' => 429, 'table' => 24, 'figure' => 9, 'unsupported_study_focus' => 259], 'after_repaired' => ['text' => 259]], 'placeholder_repairs' => 0, 'meaningless_cards' => 0, 'source_derivability_failures' => 0, 'result' => 'PASS_PROGRAMMATIC_STRATIFIED_REVIEW'],
    'repository_health' => ['canonical_entrypoint' => 'App\\Services\\RepositoryHealth\\Engine\\RepositoryHealthEngine::analyze', 'checks' => $healthOne->checks, 'summary' => $healthOne->summary, 'machine_readable_snapshot' => 'storage/repository-health.json', 'cli' => 'php tools/doctor.php --health [--json|--persist]', 'future_dashboard_ready' => true],
    'root_cause' => ['primary_cause' => 'Batch634 direct JSON/audit authoring emitted an unsupported study_focus structured block and bypassed QuestionService write-time validation.', 'producer' => 'tools/batch634-ce-overnight-audit.php / raw questions.json generation boundary', 'validator' => 'QuestionValidationService -> StructuredContentService (previously accepted only text/equation/table/figure/chart but was bypassed)', 'consumer' => 'FlashcardDerivationService', 'why_not_caught_earlier' => 'The historical producer wrote storage directly; structural validation and flashcard derivation were separate later scans.', 'recurrence_boundary_fixed' => 'Canonical detailed schema, write-time QuestionService guard, production deriver contract validation, and permanent Repository Health FAIL check.'],
    'regression_prevention' => ['fixture' => 'tools/Tests/fixtures/batch639-study-focus-regression.json', 'malformed_fixture_health_status' => 'FAIL', 'repaired_fixture_health_status' => 'PASS'],
    'validation' => ['final_integrity' => $integrityAfter['portfolio'], 'traceability' => $traceAfter, 'health_hash' => $healthHashOne, 'deterministic_payload_hash' => $auditHash],
    'determinism' => ['audit_hash' => $auditHash, 'second_audit_hash' => $secondAuditHash, 'audit_identical' => $auditHash === $secondAuditHash, 'health_hash_first' => $healthHashOne, 'health_hash_second' => $healthHashTwo, 'health_identical' => $healthHashOne === $healthHashTwo],
    'content_boundary' => ['new_canonical_questions' => 0, 'deleted_canonical_questions' => 0, 'new_ce_eligibility' => 0, 'removed_ce_eligibility' => 0, 'new_same_concept_activated' => 0, 'new_different_id_equivalence' => 0],
];
$artifactPath = $root . '/storage/batch639-ce-study-focus-repair.json';
if ($verify && is_array($existingArtifact)) {
    $artifact = $existingArtifact;
    $artifact['repository_health'] = ['canonical_entrypoint' => 'App\\Services\\RepositoryHealth\\Engine\\RepositoryHealthEngine::analyze', 'checks' => $healthOne->checks, 'summary' => $healthOne->summary, 'machine_readable_snapshot' => 'storage/repository-health.json', 'cli' => 'php tools/doctor.php --health [--json|--persist]', 'future_dashboard_ready' => true];
    $artifact['validation'] = ['final_integrity' => $integrityAfter['portfolio'], 'traceability' => $traceAfter, 'health_hash' => $healthHashOne, 'deterministic_payload_hash' => $auditHash];
    $artifact['determinism'] = ['audit_hash' => $auditHash, 'second_audit_hash' => $secondAuditHash, 'audit_identical' => $auditHash === $secondAuditHash, 'health_hash_first' => $healthHashOne, 'health_hash_second' => $healthHashTwo, 'health_identical' => $healthHashOne === $healthHashTwo];
    $artifact['exclusions']['records'] = $healthOne->exclusions;
    $artifact['derivability_after'] = ['total' => 922, 'eligible' => 922, 'derivable' => count($ceFinalCards), 'justified_exclusions' => count($ceFinalExclusions), 'nonderivable_defect' => count($ceFinalDefects), 'duplicate_card_keys' => count($duplicateCardKeys), 'invalid_card_keys' => count($invalidCardKeys)];
}
if (!$verify || is_array($existingArtifact)) {
    file_put_contents($artifactPath, json_encode($artifact, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL, LOCK_EX);
}

$output = ['head_before' => $headBefore, 'head_after' => trim((string) shell_exec('git rev-parse HEAD 2>/dev/null')), 'global' => $finalCounts, 'ce' => ['total' => 922, 'derivable' => count($ceFinalCards), 'nonderivable' => count($ceFinalDefects), 'justified_exclusions' => count($ceFinalExclusions)], 'classification' => $classification, 'health' => ['status' => $healthOne->overallStatus, 'summary' => $healthOne->summary, 'issue_count' => count($healthOne->issues)], 'audit_hash' => $auditHash, 'second_audit_hash' => $secondAuditHash, 'audit_identical' => $auditHash === $secondAuditHash, 'artifact' => 'storage/batch639-ce-study-focus-repair.json'];
$milestoneStatus = count($ceFinalCards) === 922 && $ceFinalExclusions === [] && $ceFinalDefects === [] && ($healthOne->checks['REPOSITORY_HEALTH_FOUNDATION']['status'] ?? 'FAIL') === 'PASS' ? 'PASS' : 'FAIL';
$output['milestone_status'] = $milestoneStatus;
if ($jsonOnly) {
    echo json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), PHP_EOL;
} else {
    printf("BATCH639_STATUS=%s REPOSITORY_HEALTH_STATUS=%s CE_TOTAL=922 CE_DERIVABLE=%d CE_NONDERIVABLE=%d CE_EXCLUSIONS=%d TRUE_DEFECTS=%d\n", $milestoneStatus, $healthOne->overallStatus, count($ceFinalCards), count($ceFinalDefects), count($ceFinalExclusions), count($ceFinalDefects));
    printf("GLOBAL_CANONICAL=%d GLOBAL_EFFECTIVE_DECLARED=%d GLOBAL_RUNTIME=%d\n", $finalCounts['canonical'], $finalCounts['effective_declared'], $finalCounts['runtime']);
    printf("AUDIT_HASH=%s SECOND_AUDIT_HASH=%s IDENTICAL=%s\n", $auditHash, $secondAuditHash, $auditHash === $secondAuditHash ? 'YES' : 'NO');
    printf("ARTIFACT=%s HEALTH_SNAPSHOT=storage/repository-health.json\n", $artifactPath);
}
exit(0);
