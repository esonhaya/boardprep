<?php

declare(strict_types=1);

/**
 * Build read-only Batch 717 evidence and 276-proposal readiness packages.
 * The tool reads repository snapshots and explicitly supplied, bounded source
 * inspection manifests. It never writes application or learner storage.
 */
$root = dirname(__DIR__);
$reportDir = $root . '/docs/reports';
$read = static function (string $path) use ($root): array {
    $raw = file_get_contents($root . '/' . $path);
    if ($raw === false) throw new RuntimeException('Evidence input unavailable: ' . $path);
    $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($decoded)) throw new RuntimeException('Evidence input has an invalid shape: ' . $path);
    return $decoded;
};
$readTemp = static function (string $path): array {
    $raw = file_get_contents($path);
    if ($raw === false) throw new RuntimeException('Required bounded source evidence is unavailable: ' . $path);
    $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($decoded)) throw new RuntimeException('Bounded source evidence has an invalid shape.');
    return $decoded;
};
$write = static function (string $path, array $value) use ($root): void {
    $json = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
    if (file_put_contents($root . '/' . $path, $json, LOCK_EX) === false) throw new RuntimeException('Evidence output failed: ' . $path);
};
$canonical = static function (mixed $value) use (&$canonical): mixed {
    if (!is_array($value)) return $value;
    if (array_is_list($value)) return array_map($canonical, $value);
    ksort($value, SORT_STRING);
    foreach ($value as $key => $child) $value[$key] = $canonical($child);
    return $value;
};
$hashRecord = static fn(array $value): string => hash('sha256', json_encode($canonical($value), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
$fileHash = static fn(string $path): string => hash_file('sha256', $root . '/' . $path) ?: '';

$b713Path = 'docs/reports/batch-713-cohort-disposition.json';
$pilotPath = 'docs/reports/batch-715-pilot-cohort.json';
$readinessPath = 'docs/reports/batch-715-review-readiness.json';
$matrixPath = 'docs/reports/batch-716-source-evidence-matrix.json';
$packetPath = 'docs/reports/batch-716-pilot-review-packets.json';
$governancePath = 'docs/reports/batch-716-taxonomy-study-governance.json';
$cohort = $read($b713Path);
$pilot = $read($pilotPath);
$readiness = $read($readinessPath);
$matrix = $read($matrixPath);
$packets = $read($packetPath);
$governance = $read($governancePath);
$registry = $read('storage/sources.json');
$topics = $read('storage/taxonomy/topics.json');
$concepts = $read('storage/taxonomy/concepts.json');
$canonicalQuestions = $read('storage/questions.json');
$links = $read('storage/study-question-links.json');

$proposalRows = array_values(array_filter((array) ($cohort['items'] ?? []), 'is_array'));
$pilotRows = array_values(array_filter((array) ($pilot['items'] ?? []), 'is_array'));
$matrixRows = array_values(array_filter((array) ($matrix['citation_rows'] ?? []), 'is_array'));
if (count($proposalRows) !== 276 || count($pilotRows) !== 20 || count($matrixRows) !== 41 || count($registry) !== 330) {
    throw new RuntimeException('B713/B715/B716/source-registry baseline does not reconcile; refusing to generate readiness output.');
}
$pilotIds = array_values(array_map(static fn(array $item): string => (string) ($item['question_id'] ?? ''), $pilotRows));
if (count(array_unique($pilotIds)) !== 20) throw new RuntimeException('The B715 pilot IDs are duplicated.');
$pilotByQuestion = [];
foreach ($pilotRows as $item) $pilotByQuestion[(string) $item['question_id']] = $item;
$readinessByQuestion = [];
foreach (array_values(array_filter((array) ($readiness['items'] ?? []), 'is_array')) as $item) $readinessByQuestion[(string) $item['question_id']] = $item;
$proposalByQuestion = [];
foreach ($proposalRows as $item) $proposalByQuestion[(string) $item['question_id']] = $item;
$topicIds = array_fill_keys(array_map(static fn(array $item): string => (string) ($item['id'] ?? ''), array_values(array_filter($topics, 'is_array'))), true);
$conceptIds = array_fill_keys(array_map(static fn(array $item): string => (string) ($item['id'] ?? ''), array_values(array_filter($concepts, 'is_array'))), true);
$sourceById = [];
$duplicateSourceIds = [];
foreach ($registry as $source) {
    if (!is_array($source) || trim((string) ($source['id'] ?? '')) === '') continue;
    $id = (string) $source['id'];
    if (isset($sourceById[$id])) $duplicateSourceIds[] = $id;
    $sourceById[$id] = $source;
}
if ($duplicateSourceIds !== []) throw new RuntimeException('Duplicate source IDs in current registry.');

// The workflow's version function uses this exact immutable payload when no
// revision exists. Read current JSON collections without booting production
// storage (which would create absent collection files as a side effect).
$workflowPath = $root . '/storage/academic-proposal-workflow.json';
$workflowRows = is_file($workflowPath) ? json_decode((string) file_get_contents($workflowPath), true, 512, JSON_THROW_ON_ERROR) : [];
$workflowById = [];
foreach (array_values(array_filter((array) $workflowRows, 'is_array')) as $row) $workflowById[(string) ($row['id'] ?? '')] = $row;
$questionById = [];
foreach ($canonicalQuestions as $question) if (is_array($question)) $questionById[(string) ($question['id'] ?? '')] = $question;
$versionFor = static function (array $proposal) use ($workflowById): string {
    $record = $workflowById[(string) ($proposal['id'] ?? '')] ?? [];
    $revisions = array_values(array_filter((array) ($record['revisions'] ?? []), 'is_array'));
    $latest = $revisions === [] ? [] : $revisions[array_key_last($revisions)];
    $question = is_array($latest['proposed_question'] ?? null)
        ? $latest['proposed_question']
        : (is_array($proposal['proposed_question'] ?? null) ? $proposal['proposed_question'] : []);
    $payload = [
        'proposal_id' => (string) ($proposal['id'] ?? ''),
        'question_id' => (string) ($proposal['question_id'] ?? ''),
        'board_id' => (string) ($proposal['board_id'] ?? ''),
        'subject_id' => (string) ($proposal['subject_id'] ?? ''),
        'question' => $question,
        'source_ids' => array_values((array) ($proposal['source_ids'] ?? [])),
        'source_evidence' => array_values((array) ($proposal['source_evidence'] ?? [])),
    ];
    return hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
};

$sourceStateFiles = [
    'academic-review-decisions' => 'storage/academic-review-decisions.json',
    'academic-source-reviews' => 'storage/academic-source-reviews.json',
    'academic-reviewer-authorizations' => 'storage/academic-reviewer-authorizations.json',
    'academic-import-journal' => 'storage/academic-import-journal.json',
];
$realStateCounts = [];
foreach ($sourceStateFiles as $name => $path) {
    $rows = is_file($root . '/' . $path) ? json_decode((string) file_get_contents($root . '/' . $path), true, 512, JSON_THROW_ON_ERROR) : [];
    $rows = array_values(array_filter((array) $rows, 'is_array'));
    $realStateCounts[$name] = count($rows);
}
$legacyDecisionRows = is_file($root . '/storage/academic-review-decisions.json')
    ? json_decode((string) file_get_contents($root . '/storage/academic-review-decisions.json'), true, 512, JSON_THROW_ON_ERROR) : [];
$legacySourceRows = is_file($root . '/storage/academic-source-reviews.json')
    ? json_decode((string) file_get_contents($root . '/storage/academic-source-reviews.json'), true, 512, JSON_THROW_ON_ERROR) : [];
$pilotSet = array_fill_keys($pilotIds, true);
$realPilotDecisions = [];
$cohortIdSet = [];
foreach ($proposalRows as $item) $cohortIdSet[(string) ($item['question_id'] ?? '')] = true;
$cohortHumanState = [];
foreach (array_merge((array) $legacyDecisionRows, (array) $legacySourceRows, (array) $workflowRows) as $row) {
    if (!is_array($row)) continue;
    $qid = (string) ($row['question_id'] ?? '');
    if ($qid === '' && preg_match('/^batch695-(\d+)$/', (string) ($row['id'] ?? ''), $m) === 1) $qid = $m[1];
    $proposalId = (string) ($row['proposal_id'] ?? $row['id'] ?? '');
    $matchesCohort = isset($cohortIdSet[$qid]) || preg_match('/^batch695-(\d+)$/', $proposalId, $m) === 1 && isset($cohortIdSet[$m[1]]);
    if (!$matchesCohort) continue;
    $cohortHumanState[] = $qid !== '' ? $qid : $proposalId;
    if (isset($pilotSet[$qid]) && !empty($row['decisions'])) $realPilotDecisions[] = $qid;
}
if ($realPilotDecisions !== [] || $cohortHumanState !== []) throw new RuntimeException('A real cohort workflow/decision record appeared; inspect and preserve it before regenerating readiness output.');

$sourceHash = $fileHash('storage/sources.json');
$questionHash = $fileHash('storage/questions.json');
$eligibilityHash = $fileHash('storage/question-eligibility.json');
$studyHash = $fileHash('storage/study-question-links.json');
$statisticsHash = is_file($root . '/storage/question-statistics.json') ? $fileHash('storage/question-statistics.json') : null;
$questionCount = count($canonicalQuestions);

// Reproducible PRC page-range evidence. The sparse reader has a hard 18 MB
// total-byte budget, each request is <=262,144 bytes, and every response is 206.
$rangePath = '/data/data/com.termux/files/usr/tmp/b717-prc-annex-range-log.json';
$pagePath = '/data/data/com.termux/files/usr/tmp/b717-prc-annex-extracted-pages.json';
$rangeLog = $readTemp($rangePath);
$pageLog = $readTemp($pagePath);
$ranges = array_values(array_filter((array) ($rangeLog['ranges'] ?? []), 'is_array'));
$rangeBytes = array_sum(array_map(static fn(array $row): int => (int) ($row['bytes'] ?? 0), $ranges));
if (($rangeLog['declared_size'] ?? null) !== 18103170 || count($ranges) !== 66 || $rangeBytes !== 17054594 || $rangeBytes > 18000000
    || count((array) ($pageLog['pages'] ?? [])) !== 14) {
    throw new RuntimeException('PRC Annex page extraction does not match the bounded retrieval contract.');
}
foreach ($ranges as $row) {
    if (($row['status'] ?? null) !== 206 || (int) ($row['bytes'] ?? 0) > 262144
        || !preg_match('/\A[0-9a-f]{64}\z/', (string) ($row['sha256'] ?? ''))) {
        throw new RuntimeException('PRC Annex range log contains a status, size, or digest failure.');
    }
}
$pageRecords = [];
foreach ((array) $pageLog['pages'] as $page) {
    if (!is_array($page)) continue;
    $pageNo = (int) ($page['page'] ?? 0);
    $text = (string) ($page['text'] ?? '');
    if ($pageNo < 1 || $text === '') throw new RuntimeException('PRC Annex extraction has an empty or invalid page.');
    $pageRecords[$pageNo] = [
        'pdf_page' => $pageNo,
        'extracted_text_sha256' => hash('sha256', $text),
        'extracted_characters' => mb_strlen($text),
    ];
}
$rangeBytesByFile = (int) ($rangeLog['aggregate_bytes'] ?? 0);
if ($rangeBytesByFile !== $rangeBytes) throw new RuntimeException('PRC Annex range byte totals do not match the logged intervals.');
$rangeManifestHash = hash_file('sha256', $rangePath) ?: '';

$annexContext = [
    1 => 'PDF page 1 labels Annex A and the Bachelor of Early Childhood Education table of specifications. It includes child-rights/equity and professional-responsibility outcomes on pages 1–3.',
    2 => 'PDF page 2 continues the ECE outcomes, including curriculum design, learner diversity, and community/professional engagement.',
    3 => 'PDF page 3 closes the ECE table and lists references; it is not a local school safeguarding procedure.',
    4 => 'PDF page 4 labels Annex B and the Bachelor of Special Needs Education table of specifications.',
    5 => 'PDF page 5 includes learner development, individual learning differences, safe/inclusive environment, and educational needs outcomes.',
    6 => 'PDF page 6 continues the BSNEd outcomes, including specialized curricula and learning environments for learners with additional needs.',
    7 => 'PDF page 7 continues the BSNEd table of specifications; the page title and section remain within Special Needs Education.',
    8 => 'PDF page 8 closes the BSNEd table and references.',
    9 => 'PDF page 9 labels Annex C and the Bachelor of Culture and Arts Education table of specifications.',
    10 => 'PDF page 10 includes ethics in arts production/research and Indigenous cultural ownership as an example; this does not adjudicate a particular answer.',
    11 => 'PDF page 11 labels Annex D and the Bachelor of Physical Education table of specifications.',
    12 => 'PDF page 12 includes movement competency and program-planning outcomes; these describe exam scope rather than item correctness.',
    65 => 'PDF page 65 identifies BTVTEd common competencies including tools/equipment, mensuration, plans/drawings, and workplace health/safety/ethics.',
    66 => 'PDF page 66 lists statutes, standards, and curriculum references for the BTVTEd program.',
];
$expectedPageNumbers = [1,2,3,4,5,6,7,8,9,10,11,12,65,66];
if (array_values(array_map('intval', array_keys($pageRecords))) !== $expectedPageNumbers) {
    throw new RuntimeException('PRC Annex extraction pages do not match the B716 cited page set.');
}
foreach ($pageRecords as $pageNo => &$record) $record['observed_context'] = $annexContext[$pageNo];
unset($record);

$do40Pages = [];
$ocrText = file_get_contents('/data/data/com.termux/files/usr/tmp/b717-do40-ocr.txt');
if (!is_string($ocrText)) throw new RuntimeException('Targeted DO 40 OCR record is unavailable.');
foreach ([3,4,5,6,7,8] as $pdfPage) {
    $pattern = '/===== PDF PAGE ' . $pdfPage . ' =====\n(.*?)(?=\n===== PDF PAGE |\z)/s';
    if (preg_match($pattern, $ocrText, $matches) !== 1) throw new RuntimeException('Missing targeted DO 40 OCR page ' . $pdfPage . '.');
    $text = trim((string) $matches[1]);
    $do40Pages[] = [
        'pdf_page' => $pdfPage,
        'printed_page' => $pdfPage - 1,
        'ocr_text_sha256' => hash('sha256', $text),
        'ocr_characters' => mb_strlen($text),
        'inspection_method' => 'pypdf image extraction + Tesseract English OCR; printed page 7/PDF page 8 additionally visually inspected',
    ];
}
$do40Pdf = '/data/data/com.termux/files/usr/tmp/b717-do40.pdf';
$do40PdfHash = hash_file('sha256', $do40Pdf) ?: '';
$do40PdfBytes = filesize($do40Pdf) ?: 0;
if ($do40PdfHash !== 'e679d74e981c12ba1976b521749ba15f6cd7a9c950468a63d331ef90fd50c87d' || $do40PdfBytes !== 1188026) {
    throw new RuntimeException('Downloaded official DO 40 PDF does not match the inspected source snapshot.');
}
$irrPdf = '/data/data/com.termux/files/usr/tmp/b717-deped-anti-bullying-irr.pdf';
$irrPdfHash = hash_file('sha256', $irrPdf) ?: '';
$irrPdfBytes = filesize($irrPdf) ?: 0;
if ($irrPdfHash !== 'bfa893fa05eefd18fa4dd4e618c9516adcddaeee63d200c6d3cddd458f05c6cd' || $irrPdfBytes !== 251534) {
    throw new RuntimeException('Downloaded official 2025 Revised Anti-Bullying IRR differs from the inspected snapshot.');
}
$prcDownloadsPath = '/data/data/com.termux/files/usr/tmp/b717-prc-downloads.html';
$prcSchedulePath = '/data/data/com.termux/files/usr/tmp/b717-prc-schedule-2026.html';
$prcScheduleIndexPath = '/data/data/com.termux/files/usr/tmp/b717-prc-schedule-index.html';
$prcJunePath = '/data/data/com.termux/files/usr/tmp/b717-prc-june-architecture.pdf';
$prcJanuaryPath = '/data/data/com.termux/files/usr/tmp/b717-prc-jan-architecture.pdf';
$prcDownloadsHtml = file_get_contents($prcDownloadsPath);
$prcScheduleHtml = file_get_contents($prcSchedulePath);
$prcScheduleIndexHtml = file_get_contents($prcScheduleIndexPath);
if (!is_string($prcDownloadsHtml) || !is_string($prcScheduleHtml) || !is_string($prcScheduleIndexHtml)
    || !str_contains($prcDownloadsHtml, 'January 2026 Examination Program for Architects_rev.pdf')
    || !str_contains($prcDownloadsHtml, 'Program for June 2026 LEA(Architect)_rev.pdf')
    || !str_contains($prcDownloadsHtml, 'Oct 2026 NALE Program.pdf')
    || stripos($prcDownloadsHtml, 'OCTOBER 2026 ARCHITECTS LICENSURE EXAMINATION') !== false
    || !str_contains($prcScheduleHtml, 'Cancellation of the Licensure Examination for Architects in Davao City')
    || !str_contains($prcScheduleHtml, 'October 05, 06, and 07, 2026')
    || !str_contains($prcScheduleHtml, 'Naval Architects (CBLE)')
    || !str_contains($prcScheduleIndexHtml, '/2026-schedule-examination')
    || !str_contains($prcScheduleIndexHtml, '/2025-schedule-examination')
    || str_contains($prcScheduleIndexHtml, '/2027-schedule-examination')) {
    throw new RuntimeException('Current official PRC program/schedule pages did not reproduce the recorded Architecture distinction.');
}
$prcJuneSha = hash_file('sha256', $prcJunePath) ?: '';
$prcJanuarySha = hash_file('sha256', $prcJanuaryPath) ?: '';
if ($prcJuneSha !== '6c46447a59a221e599bf5a224e12b3a3a33855f73c1e26344145ec4af2cab243'
    || $prcJanuarySha !== 'ca846b6907328922cab06b4340b19d5081799603f26630f87ecd6dfeb8793574') {
    throw new RuntimeException('Downloaded official PRC Architecture programs do not match their inspected snapshots.');
}

$sourceRecordsByKey = [];
foreach ($matrixRows as $row) {
    $qid = (string) ($row['question_id'] ?? '');
    $sid = (string) ($row['source_identity']['source_id'] ?? '');
    $key = $qid . '::' . $sid;
    if ($qid === '' || $sid === '' || isset($sourceRecordsByKey[$key])) throw new RuntimeException('Missing or duplicate B716 citation identity: ' . $key);
    $currentSource = $sourceById[$sid] ?? null;
    if (!is_array($currentSource)) throw new RuntimeException('B716 cited source is missing from the current registry: ' . $sid);
    $currentSourceHash = $hashRecord($currentSource);
    if (!hash_equals((string) ($row['source_registry']['current_record_sha256'] ?? ''), $currentSourceHash)
        || empty($row['source_registry']['snapshot_matches_current_record'])) {
        throw new RuntimeException('B716 source snapshot drifted for ' . $key);
    }
    $proposal = $proposalByQuestion[$qid] ?? null;
    $expectedVersion = is_array($proposal) ? $versionFor($proposal) : '';
    if ($expectedVersion === '' || !hash_equals($expectedVersion, (string) ($row['question_version'] ?? ''))) {
        throw new RuntimeException('B716 citation is version-stale for ' . $key);
    }
    $sourceRecordsByKey[$key] = [$row, $currentSourceHash];
}

$statusFor = static function (array $row, bool $freshlyExamined = false): string {
    $old = strtoupper((string) ($row['machine_claim_check']['status'] ?? ''));
    if (!$freshlyExamined && empty($row['content_examined'])) return 'NOT_EXAMINED';
    if (str_contains($old, 'CONFLICT')) return 'CONFLICT_DETECTED';
    if (in_array($old, ['EXAM_SCOPE_ONLY', 'RIGHTS_CONTEXT_ONLY'], true)) return 'LOCATOR_CONFIRMED';
    foreach (['NOT_LOCATED', 'NOT_ESTABLISHED', 'VALIDITY_GAP', 'NOT_EXAMINED', 'UNSUPPORTED', 'NOT_SUPPORTED'] as $token) {
        if (str_contains($old, $token)) return 'INSUFFICIENT_EVIDENCE';
    }
    if (str_contains($old, 'CANDIDATE')) return 'CLAIM_CANDIDATE';
    return $freshlyExamined ? 'LOCATOR_CONFIRMED' : 'AWAITING_EXPERT_REVIEW';
};
$annexPageByQuestion = [];
foreach ($matrixRows as $row) {
    if (($row['source_identity']['source_id'] ?? '') !== 'prc-resolution-20-2025-annex-a') continue;
    $annexPageByQuestion[(string) ($row['question_id'] ?? '')] = array_map('intval', (array) ($row['locator']['page_or_section'] ?? []));
}

$citationRows = [];
$machineStatusCounts = array_fill_keys(['NOT_EXAMINED','LOCATOR_CONFIRMED','CLAIM_CANDIDATE','CONFLICT_DETECTED','INSUFFICIENT_EVIDENCE','AWAITING_EXPERT_REVIEW'], 0);
foreach ($matrixRows as $row) {
    $qid = (string) $row['question_id'];
    $sid = (string) $row['source_identity']['source_id'];
    $key = $qid . '::' . $sid;
    [$sourceRow, $currentSourceHash] = $sourceRecordsByKey[$key];
    $freshlyExamined = $sid === 'deped-do-40-2012-child-protection';
    $examined = !empty($row['content_examined']) || $freshlyExamined;
    $status = $freshlyExamined ? 'CLAIM_CANDIDATE' : $statusFor($row);
    $locator = is_array($row['locator'] ?? null) ? $row['locator'] : [];
    $rangePages = [];
    if ($sid === 'prc-resolution-20-2025-annex-a') {
        $rangePages = array_values(array_map('intval', (array) ($locator['page_or_section'] ?? [])));
        if ($rangePages === [] || array_diff($rangePages, array_keys($pageRecords)) !== []) throw new RuntimeException('PRC page locators do not have reproduced page evidence: ' . $key);
    }
    $do40PageEvidence = $freshlyExamined ? $do40Pages : [];
    $citationRows[] = [
        'citation_id' => $key,
        'proposal_id' => (string) ($row['proposal_id'] ?? ''),
        'question_id' => $qid,
        'question_version_sha256' => $versionFor($proposalByQuestion[$qid]),
        'source_id' => $sid,
        'source_identity' => $row['source_identity'],
        'source_registry' => [
            'current_record_sha256' => $currentSourceHash,
            'proposal_snapshot_matches_current_record' => true,
        ],
        'url_reachable' => (bool) ($row['retrieval']['url_reachable'] ?? false),
        'source_identity_confirmed' => (bool) ($row['retrieval']['identity_confirmed'] ?? false),
        'content_examined' => $examined,
        'content_examined_provenance' => $freshlyExamined
            ? 'B717_BOUNDED_OFFICIAL_PDF_RETRIEVAL_AND_TARGETED_OCR'
            : ($row['content_examined_provenance'] ?? null),
        'locator' => [
            'proposal_locator' => $locator['proposal_source_locator'] ?? null,
            'examined_locator' => $freshlyExamined
                ? 'DepEd DO 40 s. 2012, printed pp. 2–7 (PDF pp. 3–8), targeted OCR; printed p. 7/PDF p. 8 visually checked.'
                : ($locator['examined_locator'] ?? null),
            'pdf_pages' => $freshlyExamined ? [3,4,5,6,7,8] : ($rangePages !== [] ? $rangePages : null),
            'printed_pages' => $freshlyExamined ? [2,3,4,5,6,7] : null,
            'page_text_sha256' => $freshlyExamined
                ? array_values(array_map(static fn(array $p): string => (string) $p['ocr_text_sha256'], $do40PageEvidence))
                : array_values(array_map(static fn(int $p): string => (string) ($pageRecords[$p]['extracted_text_sha256'] ?? ''), $rangePages)),
        ],
        'examined_content_scope' => $freshlyExamined
            ? 'DO 40 §2 identifies the policy scope; §3(E) defines child protection; §3(J) includes disability in its discrimination definition; §3(L) defines school-personnel violence; §3(M) addresses peer bullying and vulnerable learners; §3(M)(1)(d) includes public humiliation/status imputation. The old order does not by itself establish the current local procedure.'
            : ($row['examined_content_scope'] ?? 'No page-specific content was examined in the cited source.'),
        'machine_claim_evidence_status' => $status,
        'machine_claim_candidate' => $status === 'CLAIM_CANDIDATE',
        'machine_claim_verified' => false,
        'independent_expert_source_review' => 'PENDING_QUALIFIED_REVIEWER',
        'academic_approval' => 'NOT_APPROVED',
        'source_scope_warnings' => array_values((array) ($row['source_scope_warnings'] ?? [])),
        'currentness_warnings' => array_values((array) ($row['currentness_warnings'] ?? [])),
        'counterevidence_search' => 'NOT_EXHAUSTIVE; absence of recorded contradiction is not proof no counterevidence exists',
        'content_hashes' => [
            'registered_source_record_sha256' => $currentSourceHash,
            'source_response_sha256' => $freshlyExamined ? $do40PdfHash : ($row['retrieval']['content_sha256'] ?? null),
            'source_response_bytes' => $freshlyExamined ? $do40PdfBytes : null,
        ],
        'blocking_reasons' => array_values(array_unique(array_merge(
            array_values((array) ($row['blocking_reasons'] ?? [])),
            ['QUALIFIED_SOURCE_REVIEW_PENDING', 'ACADEMIC_DECISION_PENDING'],
            $freshlyExamined ? ['CURRENT_POLICY_AND_SCHOOL_PROCEDURE_NOT_FULLY_VERIFIED'] : []
        ))),
    ];
    $machineStatusCounts[$status]++;
}
if (count($citationRows) !== 41) throw new RuntimeException('B717 citation matrix must reconcile to 41 proposal citation rows.');

$supplemental = [
    'deped_do_40_s_2012' => [
        'source_identity' => 'Official Department of Education Order No. 40, s. 2012, Child Protection Policy.',
        'official_order_page' => 'https://www.deped.gov.ph/2012/05/14/do-40-s-2012-deped-child-protection-policy/',
        'official_pdf_url' => 'https://www.deped.gov.ph/wp-content/uploads/2012/05/DO_s2012_40.pdf',
        'retrieval_date_utc' => '2026-10-25',
        'retrieval_time_status' => 'EXACT_TIME_NOT_RETAINED',
        'http_status' => 200,
        'content_type' => 'application/pdf',
        'bytes' => $do40PdfBytes,
        'sha256' => $do40PdfHash,
        'page_count' => 26,
        'text_layer' => 'NONE; page image OCR was used only on PDF pp. 3–8, with PDF p. 8 also visually checked.',
        'inspected_pages' => $do40Pages,
        'passage_contexts' => [
            ['locator' => '§2, printed p. 2 / PDF p. 3', 'observation' => 'Policy scope names child abuse, exploitation, violence, discrimination, bullying, and other abuse.'],
            ['locator' => '§3(E), printed p. 3 / PDF p. 4', 'observation' => 'Child protection includes programs, services, procedures, and structures intended to prevent and respond to specified harms.'],
            ['locator' => '§3(J), printed p. 4 / PDF p. 5', 'observation' => 'The discrimination definition lists disability among the grounds/statuses.'],
            ['locator' => '§3(L), printed p. 5 / PDF p. 6', 'observation' => 'The order defines school-personnel violence against children and includes psychological harm, harassment, and related abuse.'],
            ['locator' => '§3(M), printed p. 6 / PDF p. 7', 'observation' => 'The peer-abuse definition describes aggressive conduct toward a victim who may have a disability or other vulnerability.'],
            ['locator' => '§3(M)(1)(d), printed p. 7 / PDF p. 8', 'excerpt_under_25_words' => '“Public humiliation, or public and malicious imputation of a crime or of a vice or defect”', 'observation' => 'This example can be relevant to mockery/humiliation; the image was visually checked.'],
        ],
        'limitations' => ['OCR is machine extraction and was checked against the source image only for printed p. 7.', 'Only targeted policy pages were examined, not the complete 26-page policy.', 'This historical policy does not establish the current school-specific process or the applicability of every fact in a proposal.'],
    ],
    'deped_2025_revised_anti_bullying_irr' => [
        'source_identity' => 'Official DepEd Revised Implementing Rules and Regulations of Republic Act No. 10627, enclosure to DepEd Memorandum No. 090, s. 2025.',
        'official_pdf_url' => 'https://www.deped.gov.ph/wp-content/uploads/Anti-bullying-IRR-Clean-version-as-of-25-March-2025_1.pdf',
        'official_memo_url' => 'https://www.deped.gov.ph/wp-content/uploads/DM_s2025_090r.pdf',
        'retrieved_at_utc' => '2026-10-25T23:50:09Z',
        'http_status' => 200,
        'content_type' => 'application/pdf',
        'bytes' => $irrPdfBytes,
        'sha256' => $irrPdfHash,
        'page_count' => 18,
        'inspection_method' => 'Direct pypdf text extraction; whole PDF downloaded below 18 MB cap, exact pages hashed.',
        'inspected_pages' => [
            ['pdf_page' => 2, 'section' => '§4(d), v. Social Bullying', 'text_sha256' => '0995353ec2f424151309225a1f8912719cf4f4a37bc90011f3dc5413dcf51231', 'observation' => 'The definition addresses social reputation/relationships and explicitly includes deliberate, repetitive, or aggressive social behavior against learners with disabilities.'],
            ['pdf_page' => 15, 'section' => '§14, Handling of Bullying Incidents', 'text_sha256' => '0742ba3f7dd3a271cd1d82b0667129cf55a81e7af966a9851a90da08b618cfde', 'excerpt_under_25_words' => '“immediate response and reporting, thorough investigation, intervention programs”', 'observation' => 'Schools must adopt context-specific procedures that meet minimum standards; the exact local process is not supplied by this IRR.'],
            ['pdf_page' => 17, 'section' => '§15, Confidentiality', 'text_sha256' => '1af2a7c56c9e6a471abe934d944ca83011d33d6e997831e492e7b59a383521b6', 'observation' => 'Identity and personal circumstances of all parties in bullying/retaliation incidents are to be treated with utmost confidentiality.'],
            ['pdf_page' => 18, 'section' => '§§20–21, Repealing Clause and Effectivity', 'text_sha256' => 'af579df41b3b14c79d52e80f54229d316441f2b6eebe4ef237b9c0964b3243be', 'observation' => 'Inconsistent prior provisions are repealed/revised/modified; the IRR states effectiveness follows publication by 15 days. The machine inspection did not establish every publication/registration detail.'],
        ],
        'supplemental_not_in_b695_source_snapshot' => true,
        'limitations' => ['Contextual authority only; not added to the proposal source registry or the 41 original citation rows.', 'Does not prove a school-specific response, answer correctness, or the factual classification of any incident.', 'A qualified source reviewer must determine whether it should be formally cited and how it interacts with later DO 006, s. 2026.'],
    ],
    'deped_do_006_s_2026' => [
        'source_identity' => 'DepEd Order No. 006, s. 2026, Guidelines on Ensuring a Safe and Motivating Learning Environment.',
        'official_pdf_url' => 'https://www.deped.gov.ph/wp-content/uploads/DO_s2026_006r.pdf',
        'last_modified' => '2026-03-24T07:42:01Z',
        'head_rechecked_at_utc' => '2026-10-25T23:50:10Z',
        'http_status' => 200,
        'content_type' => 'application/pdf',
        'content_length_bytes' => 21580650,
        'maximum_download_bytes' => 18000000,
        'whole_document_downloaded' => false,
        'content_examined' => false,
        'machine_status' => 'NOT_EXAMINED',
        'observed_official_metadata_only' => 'Official PDF HEAD metadata confirms title/version and size. The official indexed search excerpt describes a harmonized framework and references DO 40, s. 2012 and DM-OUOPS-2024-05-07998; this is not a complete-document inspection.',
        'blocking_action' => 'A qualified source reviewer must obtain and inspect the relevant bounded official pages or the official source document through a method complying with the 18 MB per-download cap, then verify effectivity and applicability.',
    ],
    'prc_architecture_program_recheck' => [
        'official_downloads_page_url' => 'https://prc.gov.ph/downloads',
        'official_downloads_page_sha256' => hash_file('sha256', $prcDownloadsPath) ?: '',
        'official_downloads_page_bytes' => filesize($prcDownloadsPath) ?: 0,
        'official_2026_schedule_url' => 'https://prc.gov.ph/2026-schedule-examination',
        'official_2026_schedule_sha256' => hash_file('sha256', $prcSchedulePath) ?: '',
        'official_2026_schedule_bytes' => filesize($prcSchedulePath) ?: 0,
        'official_schedule_index_url' => 'https://prc.gov.ph/exam-schedules-menu',
        'official_schedule_index_sha256' => hash_file('sha256', $prcScheduleIndexPath) ?: '',
        'official_schedule_index_bytes' => filesize($prcScheduleIndexPath) ?: 0,
        'retrieval_completion_mtime_local' => date(DATE_ATOM, filemtime($prcDownloadsPath) ?: 0),
        'schedule_index_finding' => 'The current official schedule menu lists 2026 and 2025 schedules; no 2027 schedule link was present in the fetched page.',
        'downloads_page_finding' => 'The current official downloads page lists January 2026 and June 2026 Architects programs; the October program link is for Naval Architects, a separate examination.',
        'october_2026_schedule_correction' => 'The October 05–07 row is Naval Architects (CBLE), not Architects. The October 2026 Naval Architects program link is distinct. This corrects the earlier B716 report wording; no examination scope or question was changed.',
        'january_2026_architects_program' => [
            'url' => 'https://www.prc.gov.ph/sites/default/files/January%202026%20Examination%20Program%20for%20Architects_rev.pdf',
            'bytes' => filesize($prcJanuaryPath) ?: 0, 'sha256' => $prcJanuarySha,
            'pdf_pages' => 3, 'page_1_text_sha256' => '99237dbb2377f6c70963d85236ea7c9c48660ee9fddd4aa49bd224d2c50bce85',
            'exam_dates' => ['2026-01-20', '2026-01-22'],
        ],
        'june_2026_architects_program' => [
            'url' => 'https://www.prc.gov.ph/sites/default/files/Program%20for%20June%202026%20LEA%28Architect%29_rev.pdf',
            'bytes' => filesize($prcJunePath) ?: 0, 'sha256' => $prcJuneSha,
            'pdf_pages' => 16, 'page_1_text_sha256' => '9c54c6be6c5870feea6857f8410352c8729f5f29022e893796d5d7182b12b498',
            'exam_dates' => ['2026-06-08', '2026-06-10'],
            'scope_table' => 'History & Theory of Architecture / Principles of Planning & Architectural Practice 30%; Utilities, Structural Conceptualization, Building Materials & Technology 30%; Architectural Design and Site Planning 40%.',
        ],
        'latest_detailed_architecture_program_found' => 'JUNE 2026; both January and June programs are historical as of the 2026-10-25 source recheck.',
        'schedule_is_not_detailed_syllabus' => true,
        'current_or_future_detailed_architecture_program_found' => false,
        'question_ids_blocked' => ['9124','9125','9126','9127'],
        'source_review' => 'PENDING_QUALIFIED_REVIEWER',
    ],
    'prc_resolution_20_s_2025_annex' => [
        'source_identity' => 'PRC Resolution No. 20, s. 2025 linked teacher-examination annex; registry record remains unchanged.',
        'official_pdf_url' => 'https://www.prc.gov.ph/sites/default/files/2025-20%20Annex%20teachers.pdf',
        'publisher_page_url' => 'https://prc.gov.ph/Pages/PRBv4/ProfessionalTeachersv9.htm',
        'full_document_content_length_bytes' => 18103170,
        'full_document_sha256' => null,
        'download_cap_bytes' => 18000000,
        'whole_document_downloaded' => false,
        'bounded_sparse_retrieval' => [
            'retrieval_date_utc' => '2026-10-25',
            'retrieval_time_status' => 'EXACT_TIME_NOT_RETAINED',
            'range_request_count' => count($ranges),
            'max_block_bytes' => 262144,
            'actual_bytes_retrieved' => $rangeBytes,
            'aggregate_cap_bytes' => 18000000,
            'http_status_each' => 206,
            'all_content_range_and_byte_counts_checked' => true,
            'individual_range_records' => $ranges,
            'range_log_sha256' => $rangeManifestHash,
            'extracted_page_records' => array_values($pageRecords),
            'page_text_extract_status' => 'DIRECT_TEXT_EXTRACTED_FROM_VERIFIED_SPARSE_RANGES; not a complete-document inspection',
        ],
        'page_mapping' => [
            '1-3' => 'Annex A / BECEd', '4-8' => 'Annex B / BSNEd', '9-10' => 'Annex C / BCAEd',
            '11-12' => 'Annex D / BPEd', '65-66' => 'BTVTEd common competencies',
        ],
        'page_numbering_caveat' => 'Locators in the matrix are one-based PDF page indexes. B695 source labels and the PRC publisher page use parent/child Annex naming differently; retain the precise internal Annex label and verify its relationship to the parent resolution. The extracted tables are scope context only.',
        'excerpt_under_25_words' => '“Application of occupational health, safety, and ethical values in the workplace.”',
        'limitations' => ['The 18,103,170-byte PDF exceeds the 18,000,000-byte cap, so no complete file was saved or hashed.', 'Sparse page extraction confirms page labels and selected context, not all document content.', 'All 16 citation rows require qualified confirmation of exact item-to-page fit; none establishes a proposed answer.'],
    ],
];

$irrSupplementPages = [2 => 'Social Bullying includes deliberate, repetitive, or aggressive social behavior against learners with disabilities.', 15 => 'Section 14 requires school-specific procedures meeting minimum response/reporting, investigation, intervention, due-process, and referral standards.', 17 => 'Section 15 makes identities and personal circumstances in bullying/retaliation incidents confidential.'];
$widerScopeIssues = [];
for ($id = 9228; $id <= 9233; $id++) {
    $proposal = $proposalByQuestion[(string) $id] ?? null;
    if (!is_array($proposal)) throw new RuntimeException('Missing known limited-scope proposal Q' . $id);
    $contexts = match ($id) {
        9228 => [
            ['source' => 'DO 40 §3(J), §3(M); 2025 Revised Anti-Bullying IRR §4(d), PDF p. 2', 'relevance' => 'Disability-based discrimination and peer/social bullying are directly within the examined policy vocabulary; the repetitive mocking/exclusion fact pattern is a support candidate.'],
            ['source' => '2025 Revised Anti-Bullying IRR §14, PDF p. 15', 'relevance' => 'The regulation requires response/reporting and school-specific procedures; the actual local procedure and current DO 006 guidance remain unexamined.'],
        ],
        9229 => [
            ['source' => 'DO 40 §2 and §3(L), PDF pp. 3 and 6', 'relevance' => 'The order covers violence and defines school-personnel violence against a child; the question says “adult” but does not establish the adult’s role or the type of act.'],
        ],
        9230 => [
            ['source' => 'DO 40 §2 and §3(K), PDF pp. 3 and 6', 'relevance' => 'The policy scope and exploitation definition support classifying exploitation as a protection concern; the exact reporting/referral process is unresolved.'],
        ],
        9231 => [
            ['source' => 'DO 40 §3(M), PDF pp. 7–8; 2025 Revised Anti-Bullying IRR §4(d), PDF p. 2', 'relevance' => 'The examined definitions address peer abuse, intimidation, and severe/repeated conduct; exact facts and school response level require expert interpretation.'],
        ],
        9232 => [
            ['source' => '2025 Revised Anti-Bullying IRR §15, PDF p. 17', 'relevance' => 'The examined confidentiality provision supports avoiding disclosure of incident parties’ identities/personal circumstances in a general class channel; precise privacy and safeguarding exceptions require expert review.'],
        ],
        9233 => [
            ['source' => 'DO 40 §3(J), PDF p. 5; PRC Resolution 20 Annex A pp. 1–3', 'relevance' => 'The examined policy defines discrimination; the ECE table covers child rights/equity outcomes. Whether the instructional option is sound remains an ECE academic decision.'],
        ],
    };
    $widerScopeIssues[] = [
        'question_id' => (string) $id,
        'board' => (string) $proposal['board_name'],
        'subject' => (string) $proposal['subject_name'],
        'proposal_version_sha256' => $versionFor($proposal),
        'original_scope_flag' => 'LIMITED_SOURCE_SCOPE_RECHECK',
        'machine_resolvable_findings' => $contexts,
        'source_claim_classification' => $id === 9232 ? 'CLAIM_CANDIDATE_WITH_PRIVACY_SCOPE_LIMIT' : 'CLAIM_CANDIDATE_FOR_POLICY_SCOPE_ONLY',
        'authoritative_acquisition_required' => ['Current DO 006 s. 2026 relevant full text', 'Applicable local school child-protection/anti-bullying policy and referral path'],
        'qualified_interpretation_required' => true,
        'cannot_safely_proceed_with' => $id === 9229 ? 'Do not assume an adult is school personnel or select a referral path without facts.' : 'Do not represent a policy-scope candidate as a legally determined incident or a final response procedure.',
        'source_review' => 'PENDING_QUALIFIED_REVIEWER',
        'academic_decision' => 'PENDING_REVIEW',
    ];
}

$supplementalQuestions = [];
foreach ($pilotIds as $qid) {
    $proposal = $proposalByQuestion[$qid] ?? null;
    $evidence = $readinessByQuestion[$qid] ?? null;
    if (!is_array($proposal) || !is_array($evidence)) throw new RuntimeException('Missing exact pilot dossier Q' . $qid);
    $version = $versionFor($proposal);
    if (!hash_equals((string) ($pilotByQuestion[$qid]['question_version'] ?? ''), $version)
        || !hash_equals((string) ($evidence['question_version'] ?? ''), $version)) {
        throw new RuntimeException('Pilot question version mismatch for Q' . $qid);
    }
    $itemCitations = array_values(array_filter($citationRows, static fn(array $row): bool => $row['question_id'] === $qid));
    $claim = (string) ($evidence['specific_claim_to_review'] ?? $proposal['proposal_reason']['specific_claim_to_review'] ?? '');
    $question = (array) ($evidence['question'] ?? $proposal['proposed_question'] ?? []);
    $reviewQuestions = array_values(array_filter((array) ($evidence['review_questions'] ?? []), 'is_string'));
    $claimStatus = 'AWAITING_EXPERT_REVIEW';
    if ($qid === '9228') {
        $claimStatus = 'CLAIM_CANDIDATE';
        $reviewQuestions[] = 'Does the repeated disability-related mockery/exclusion satisfy the applicable current definition of bullying/discrimination on the facts given?';
        $reviewQuestions[] = 'Does the selected answer stay within the policy evidence without claiming an unverified school procedure?';
        $reviewQuestions[] = 'What current school-specific response/referral path applies, and is it appropriate to ask for it in this item?';
        $reviewQuestions[] = 'The rationale claim “document observable facts” is not established by the inspected DO 40 or Revised IRR pages; retain or revise it only after an authoritative source and academic decision.';
    }
    $supplementalQuestions[] = [
        'proposal_id' => (string) $proposal['id'],
        'question_id' => $qid,
        'question_version_sha256' => $version,
        'version_basis' => 'Same immutable payload as AcademicReviewWorkflowService::version(); any saved revision changes this hash.',
        'current_version_matches_all_b715_b716_artifacts' => true,
        'question' => [
            'stem' => (string) ($question['stem'] ?? ''),
            'choices' => array_values((array) ($question['choices'] ?? [])),
            'proposed_answer' => (string) ($question['proposed_answer'] ?? ''),
            'explanation' => (string) ($question['explanation'] ?? ''),
            'difficulty' => (string) ($question['difficulty'] ?? ''),
        ],
        'rationale_claims_for_expert' => array_values(array_filter([
            $claim,
            (string) ($question['explanation'] ?? ''),
        ], static fn(string $value): bool => trim($value) !== '')),
        'machine_claim_status' => $claimStatus,
        'machine_claim_verified' => false,
        'citation_rows' => $itemCitations,
        'supplemental_official_evidence' => $qid === '9228' ? [
            'DO40_POLICY_SCOPE' => $supplemental['deped_do_40_s_2012'],
            '2025_REVISED_ANTI_BULLYING_IRR' => $supplemental['deped_2025_revised_anti_bullying_irr'],
            'DO006_2026_CURRENTNESS' => $supplemental['deped_do_006_s_2026'],
        ] : [],
        'evidence_limitations' => $qid === '9228'
            ? ['DO 40 is historical and only targeted pages were OCR inspected.', 'The 2025 IRR has directly examined relevant sections, but the 2026 DO 006 is metadata-only due its 21,580,650-byte size.', 'Local school procedure was not obtained.', 'The B695 specific “document observable facts” rationale claim has not been supported by the inspected source passages.']
            : ['Machine page/context checks do not establish answer correctness.', 'Source role/scope and currentness require a qualified human review.'],
        'reviewer_questions' => array_values(array_unique($reviewQuestions)),
        'source_review_status' => 'PENDING_QUALIFIED_REVIEWER',
        'academic_decision_status' => 'PENDING_REVIEW',
        'import_status' => 'NOT_IMPORT_READY',
        'review_blockers' => array_values(array_unique(array_merge(
            ['QUALIFIED_SOURCE_REVIEW_PENDING', 'ACADEMIC_DECISION_PENDING', 'APPROVED_TAXONOMY_MAPPING_REQUIRED'],
            $qid === '9228' ? ['CURRENT_LOCAL_POLICY_NOT_EXAMINED', 'RATIONALE_CLAIM_NOT_ESTABLISHED'] : []
        ))),
    ];
}

$evidenceOutput = [
    'schema' => 'boardprep.batch-717-evidence-verification.v1',
    'generated_at_utc' => gmdate('Y-m-d\TH:i:s\Z'),
    'purpose' => 'Version-bound machine evidence and source provenance for the existing B715 pilot; this is not qualified source review or academic approval.',
    'provenance' => [
        $b713Path => $fileHash($b713Path), $pilotPath => $fileHash($pilotPath), $readinessPath => $fileHash($readinessPath),
        $matrixPath => $fileHash($matrixPath), $packetPath => $fileHash($packetPath), $governancePath => $fileHash($governancePath),
        'storage/sources.json' => $sourceHash, 'storage/questions.json' => $questionHash,
        'storage/question-eligibility.json' => $eligibilityHash, 'storage/study-question-links.json' => $studyHash,
    ],
    'state_separation' => [
        'url_reachable' => 'Transport result only; not source identity or content evidence.',
        'source_identity_confirmed' => 'Publisher/source-document identity check; does not mean the relevant page or claim was examined.',
        'content_examined' => 'Only the exact recorded page/section and extraction method were examined; does not establish claim support.',
        'machine_claim_candidate' => 'A relevance candidate for a human to assess; it is not verification.',
        'independent_expert_source_review' => 'A qualified human with an active scoped SOURCE_REVIEW grant must record the decision against the exact proposal version and current registry snapshot.',
        'academic_approval' => 'Separate qualified human REVIEW decision; none recorded.',
        'import' => 'Separate authorized importer decision after source, academic, taxonomy, and snapshot gates; none recorded.',
    ],
    'summary' => [
        'pilot_proposals' => 20, 'citation_rows' => 41, 'unique_sources' => count(array_unique(array_map(static fn(array $row): string => $row['source_id'], $citationRows))),
        'url_reachable_rows' => count(array_filter($citationRows, static fn(array $row): bool => $row['url_reachable'])),
        'source_identity_confirmed_rows' => count(array_filter($citationRows, static fn(array $row): bool => $row['source_identity_confirmed'])),
        'content_examined_rows' => count(array_filter($citationRows, static fn(array $row): bool => $row['content_examined'])),
        'content_unexamined_rows' => count(array_filter($citationRows, static fn(array $row): bool => !$row['content_examined'])),
        'current_registry_missing_or_stale_rows' => 0,
        'exact_question_version_conflicts' => 0,
        'machine_claim_status_counts' => $machineStatusCounts,
        'machine_claim_verified_rows' => 0, 'qualified_source_reviews' => 0, 'academic_approvals' => 0,
        'real_reviewer_grants' => (int) $realStateCounts['academic-reviewer-authorizations'],
        'real_imports' => 0,
        'annex_page_records_reproduced' => count($pageRecords),
        'annex_rows_reexamined_with_range_log' => 16,
        'do40_pages_targeted_and_ocr_examined' => count($do40Pages),
        'do40_item_level_procedure_resolved' => false,
        'source_scope_rechecks_in_full_cohort' => 6,
        'architecture_program_currentness_warnings' => 4,
    ],
    'supplemental_source_inspections' => $supplemental,
    'citation_rows' => $citationRows,
    'items' => $supplementalQuestions,
    'wider_source_scope_issues' => $widerScopeIssues,
];

$cohortItems = [];
$versionConflicts = [];
$missingCanonicalIds = [];
$registryMissing = [];
$registryDrift = [];
$topicRefCounts = ['registered' => 0, 'unregistered' => 0];
$conceptRefCounts = ['registered' => 0, 'unregistered' => 0];
$studyStatusCounts = ['EXACT_SECTION_CANDIDATE_UNLINKED' => 0, 'MISSING_SECTION_OPTIONAL' => 0, 'ACTIVE_LINK' => 0];
$scopeIssueIds = [];
$architectureIssueIds = [];
$pilotIdSet = array_fill_keys($pilotIds, true);
$studyPairs = [];
$topicReferences = [];
$conceptReferences = [];
foreach ($proposalRows as $proposal) {
    $qid = (string) ($proposal['question_id'] ?? '');
    $proposalId = (string) ($proposal['id'] ?? '');
    $version = $versionFor($proposal);
    $pilotVersion = $pilotByQuestion[$qid]['question_version'] ?? null;
    if ($pilotVersion !== null && !hash_equals($version, (string) $pilotVersion)) $versionConflicts[] = $qid;
    $canonicalExists = isset($questionById[$qid]);
    if (!$canonicalExists) $missingCanonicalIds[] = $qid;
    $sourceRefs = [];
    foreach (array_values((array) ($proposal['source_evidence'] ?? [])) as $sourceEvidence) {
        if (!is_array($sourceEvidence)) continue;
        $sid = (string) ($sourceEvidence['source_id'] ?? '');
        $current = $sourceById[$sid] ?? null;
        $snapshot = is_array($sourceEvidence['source_record_snapshot'] ?? null) ? $sourceEvidence['source_record_snapshot'] : [];
        $snapshotMatch = is_array($current) && $snapshot !== [] && hash_equals($hashRecord($snapshot), $hashRecord($current));
        if (!is_array($current)) $registryMissing[] = $qid . '::' . $sid;
        elseif (!$snapshotMatch) $registryDrift[] = $qid . '::' . $sid;
        $sourceRefs[] = [
            'source_id' => $sid,
            'registry_record_sha256' => is_array($current) ? $hashRecord($current) : null,
            'proposal_snapshot_matches_current_registry' => $snapshotMatch,
            'author_reported_source_status' => (string) ($sourceEvidence['source_evidence_status'] ?? ''),
            'limitations' => array_values((array) ($sourceEvidence['limitations'] ?? [])),
            'human_source_review' => 'PENDING_QUALIFIED_REVIEWER',
        ];
    }
    $topicId = (string) ($proposal['topic_id'] ?? '');
    $conceptId = (string) ($proposal['concept_id'] ?? '');
    $topicPresent = isset($topicIds[$topicId]);
    $conceptPresent = isset($conceptIds[$conceptId]);
    $topicRefCounts[$topicPresent ? 'registered' : 'unregistered']++;
    $conceptRefCounts[$conceptPresent ? 'registered' : 'unregistered']++;
    $topicReferences[$topicId] = $topicPresent;
    $conceptReferences[$conceptId] = $conceptPresent;
    $study = is_array($proposal['study_relationship'] ?? null) ? $proposal['study_relationship'] : [];
    $studyStatus = !empty($study['current_section_exists']) ? 'EXACT_SECTION_CANDIDATE_UNLINKED' : 'MISSING_SECTION_OPTIONAL';
    $studyStatusCounts[$studyStatus]++;
    $pair = (string) ($study['material_id'] ?? '') . '::' . (string) ($study['section_id'] ?? '');
    $studyPairs[$pair] = !empty($study['current_section_exists']);
    $flags = array_values((array) ($proposal['secondary_flags'] ?? []));
    $scopeFlags = array_values(array_intersect($flags, ['LIMITED_SOURCE_SCOPE_RECHECK']));
    $currentnessFlags = array_values(array_intersect($flags, ['HISTORICAL_EXAM_PROGRAM_CURRENTNESS_RECHECK']));
    if ($scopeFlags !== []) $scopeIssueIds[] = $qid;
    if ($currentnessFlags !== []) $architectureIssueIds[] = $qid;
    $decisionMatches = false;
    foreach (array_merge((array) $legacyDecisionRows, (array) $legacySourceRows, (array) $workflowRows) as $existing) {
        if (!is_array($existing)) continue;
        if ((string) ($existing['question_id'] ?? '') === $qid
            || (string) ($existing['proposal_id'] ?? '') === $proposalId
            || (string) ($existing['id'] ?? '') === $proposalId) $decisionMatches = true;
    }
    $reviewState = $decisionMatches ? 'HUMAN_RECORD_PRESENT_REQUIRES_INSPECTION' : 'PENDING_REVIEW';
    $blocked = ['CURRENT_VERSION_SOURCE_REVIEW_REQUIRED', 'VERSION_BOUND_ACADEMIC_DECISION_REQUIRED', 'APPROVED_TAXONOMY_MAPPING_REQUIRED', 'SEPARATE_IMPORT_AUTHORIZATION_REQUIRED'];
    if (!$topicPresent || !$conceptPresent) $blocked[] = 'PROPOSED_TOPIC_OR_CONCEPT_NOT_IN_ACTIVE_TAXONOMY';
    if ($currentnessFlags !== []) $blocked[] = 'CURRENT_EXAM_PROGRAM_OR_SCOPE_CONFIRMATION_REQUIRED';
    if ($scopeFlags !== []) $blocked[] = 'ITEM_LEVEL_POLICY_SCOPE_INTERPRETATION_REQUIRED';
    if ($canonicalExists) $blocked[] = 'CANONICAL_ID_COLLISION_REQUIRES_REVIEW';
    $cohortItems[] = [
        'proposal_id' => $proposalId,
        'question_id' => $qid,
        'question_version_sha256' => $version,
        'question_version_basis' => 'AcademicReviewWorkflowService::version() payload; source-question and scope IDs are included.',
        'board' => ['id' => (string) ($proposal['board_id'] ?? ''), 'name' => (string) ($proposal['board_name'] ?? '')],
        'subject' => ['id' => (string) ($proposal['subject_id'] ?? ''), 'name' => (string) ($proposal['subject_name'] ?? '')],
        'pilot_member' => isset($pilotIdSet[$qid]),
        'proposal_kind' => (string) ($proposal['proposal_kind'] ?? ''),
        'current_production_status' => (string) ($proposal['production_status'] ?? ''),
        'canonical_record_exists' => $canonicalExists,
        'academic_state' => $reviewState,
        'primary_disposition' => (string) ($proposal['primary_disposition'] ?? ''),
        'source_readiness' => [
            'source_ids' => array_values((array) ($proposal['source_ids'] ?? [])),
            'source_records' => $sourceRefs,
            'citation_source_snapshot_status' => 'CURRENT_REGISTRY_RECONCILED; item-level source review remains pending',
            'source_scope_flags' => $scopeFlags,
            'currentness_flags' => $currentnessFlags,
            'qualified_source_reviews_required' => count($sourceRefs),
        ],
        'taxonomy' => [
            'domain_id' => (string) ($proposal['domain_id'] ?? ''),
            'topic_id' => $topicId, 'topic_name' => (string) ($proposal['topic_name'] ?? ''),
            'topic_registered' => $topicPresent,
            'concept_id' => $conceptId, 'concept_name' => (string) ($proposal['concept_name'] ?? ''),
            'concept_registered' => $conceptPresent,
            'activation' => 'NONE; require an authorized same-board/subject mapping or owner-governed taxonomy approval.',
        ],
        'study_relationship' => [
            'material_id' => (string) ($study['material_id'] ?? ''), 'material_title' => (string) ($study['material_title'] ?? ''),
            'section_id' => (string) ($study['section_id'] ?? ''), 'section_heading' => (string) ($study['section_heading'] ?? ''),
            'status' => $studyStatus,
            'current_material_exists' => (bool) ($study['current_material_exists'] ?? false),
            'exact_section_exists_in_b713_snapshot' => (bool) ($study['current_section_exists'] ?? false),
            'active_question_link_exists' => (bool) ($study['current_question_link_exists'] ?? false),
            'current_link_count' => count(array_filter($links, static fn(mixed $link): bool => is_array($link) && (string) ($link['question_id'] ?? '') === $qid)),
            'import_requirement' => 'OPTIONAL_ENRICHMENT; if a link is proposed, its target must exist exactly and a scoped reviewer must authorize it.',
        ],
        'source_evidence_status' => 'MACHINE_REFERENCES_RECONCILED; QUALIFIED_REVIEW_PENDING',
        'version_concern' => $versionConflicts === [] ? 'CURRENT_BASE_PROPOSAL_VERSION; no saved workflow revision was present' : 'VERSION_CONFLICT',
        'review_blockers' => $blocked,
        'next_legitimate_action' => $currentnessFlags !== []
            ? 'Owner acquires the current official detailed examination program when published; then a scoped qualified reviewer rechecks this exact version.'
            : ($scopeFlags !== []
                ? 'Qualified source reviewer examines current policy and item facts, records a version-bound finding, then subject expert independently decides the item.'
                : 'Owner verifies and grants an existing qualified reviewer only the exact board/subject/source capabilities; reviewer then inspects the complete evidence and records the exact-version decision.'),
    ];
}
if ($versionConflicts !== [] || $registryMissing !== [] || $registryDrift !== []) throw new RuntimeException('Readiness reconciliation detected version/source snapshot drift.');

$evidenceSummary = $evidenceOutput['summary'];
$fullSourceSnapshots = 0;
foreach ($cohortItems as $item) $fullSourceSnapshots += count($item['source_readiness']['source_records']);
$uniqueTopicMissing = count(array_filter($topicReferences, static fn(bool $present): bool => !$present));
$uniqueConceptMissing = count(array_filter($conceptReferences, static fn(bool $present): bool => !$present));
$uniqueStudyTargets = count($studyPairs);
$uniqueMissingStudyTargets = count(array_filter($studyPairs, static fn(bool $present): bool => !$present));
$cohortOutput = [
    'schema' => 'boardprep.batch-717-cohort-readiness.v1',
    'generated_at_utc' => gmdate('Y-m-d\TH:i:s\Z'),
    'purpose' => 'Dependency-aware, read-only readiness inventory for all 276 recovered B695 proposals; no assignments, approvals, taxonomy activations, links, or imports are created.',
    'provenance' => [
        $b713Path => $fileHash($b713Path), $pilotPath => $fileHash($pilotPath),
        'storage/sources.json' => $sourceHash, 'storage/taxonomy/topics.json' => $fileHash('storage/taxonomy/topics.json'),
        'storage/taxonomy/concepts.json' => $fileHash('storage/taxonomy/concepts.json'),
        'storage/study-question-links.json' => $studyHash, 'storage/questions.json' => $questionHash,
    ],
    'summary' => [
        'cohort_total' => count($cohortItems), 'pilot_total' => count($pilotIds),
        'academic_pending' => count(array_filter($cohortItems, static fn(array $item): bool => $item['academic_state'] === 'PENDING_REVIEW')),
        'real_decisions' => 0, 'real_assignments' => 0, 'real_imports' => 0,
        'canonical_questions' => $questionCount, 'canonical_proposal_ids_present' => count($cohortItems) - count($missingCanonicalIds),
        'source_registry_records' => count($registry), 'source_refs_in_276_records' => $fullSourceSnapshots,
        'source_snapshot_missing' => count($registryMissing), 'source_snapshot_stale' => count($registryDrift),
        'source_snapshot_hash' => $sourceHash,
        'limited_source_scope_proposals' => count($scopeIssueIds), 'limited_source_scope_question_ids' => array_values($scopeIssueIds),
        'historical_exam_program_proposals' => count($architectureIssueIds), 'historical_exam_program_question_ids' => array_values($architectureIssueIds),
        'topic_reference_instances_registered' => $topicRefCounts['registered'],
        'topic_reference_instances_unregistered' => $topicRefCounts['unregistered'],
        'unique_topic_ids_referenced' => count($topicReferences), 'unique_missing_topic_ids' => $uniqueTopicMissing,
        'concept_reference_instances_registered' => $conceptRefCounts['registered'],
        'concept_reference_instances_unregistered' => $conceptRefCounts['unregistered'],
        'unique_concept_ids_referenced' => count($conceptReferences), 'unique_missing_concept_ids' => $uniqueConceptMissing,
        'study_item_relationships' => $studyStatusCounts,
        'unique_study_target_pairs' => $uniqueStudyTargets, 'unique_missing_study_targets' => $uniqueMissingStudyTargets,
        'active_study_question_links_for_cohort' => array_sum(array_map(static fn(array $item): int => $item['study_relationship']['current_link_count'], $cohortItems)),
        'question_version_conflicts' => count($versionConflicts),
        'source_scope_machine_resolvable_portions' => 6,
        'source_scope_item_interpretations_remaining' => 6,
        'architecture_newer_current_program_found' => false,
        'taxonomy_records_activated' => 0, 'study_links_activated' => 0,
    ],
    'dependency_policy' => [
        'machine_resolvable_once' => [
            'Reconcile source identities/registry snapshots and revalidate proposal version hashes.',
            'Acquire/update official exam programs when PRC publishes them; schedule pages are not detailed subject programs.',
            'Deduplicate taxonomy label proposals by parent and label before governance; no label is activated by this inventory.',
            'Recheck exact study section IDs against StudyLibraryService; a material-only match is not a section match.',
        ],
        'requires_human_per_item' => [
            'Qualified reviewer decides source identity/content/claim fit/currentness for each cited source.',
            'Qualified subject expert checks stem, every option, proposed key, explanation, ambiguity, board relevance, and exact version.',
            'Scoped taxonomy reviewer decides whether an existing approved topic/concept mapping fits that item; missing taxonomy requires owner governance.',
            'Scoped study reviewer may approve only an exact existing lesson section; no missing lesson may be inferred.',
        ],
        'next_cohort_recommendation' => 'Do not expand yet. Complete human source and academic decisions for the curated 20 first, resolve their taxonomy blockers, and assess actual reviewer capacity. If that succeeds, prepare a later cohort from pending proposals with current authoritative sources and existing same-scope taxonomy; select IDs only through a new owner-approved cohort decision.',
    ],
    'items' => $cohortItems,
];

$write('docs/reports/batch-717-evidence-verification.json', $evidenceOutput);
$write('docs/reports/batch-717-cohort-readiness.json', $cohortOutput);
fwrite(STDOUT, json_encode([
    'evidence_rows' => count($citationRows), 'examined' => $evidenceSummary['content_examined_rows'],
    'claim_candidates' => $machineStatusCounts['CLAIM_CANDIDATE'], 'cohort_items' => count($cohortItems),
    'topic_missing' => $topicRefCounts['unregistered'], 'concept_missing' => $conceptRefCounts['unregistered'],
    'study_exact_candidates' => $studyStatusCounts['EXACT_SECTION_CANDIDATE_UNLINKED'],
    'study_missing' => $studyStatusCounts['MISSING_SECTION_OPTIONAL'], 'source_registry_hash' => $sourceHash,
], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
