<?php

declare(strict_types=1);

/**
 * Build the version-bound, read-only Batch 716 source matrix and reviewer
 * packets from the recovered B713/B715 evidence plus explicitly recorded
 * B716 source checks. This tool never writes application or learner storage.
 */
$root = dirname(__DIR__);
$read = static function (string $path) use ($root): array {
    $raw = file_get_contents($root . '/' . $path);
    if ($raw === false) throw new RuntimeException('Evidence input unavailable: ' . $path);
    $value = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($value)) throw new RuntimeException('Evidence input is not an object/list: ' . $path);
    return $value;
};
$write = static function (string $path, array $value) use ($root): void {
    $json = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
    if (file_put_contents($root . '/' . $path, $json, LOCK_EX) === false) throw new RuntimeException('Evidence output failed: ' . $path);
};
$canonical = static function (mixed $value) use (&$canonical): mixed {
    if (!is_array($value)) return $value;
    if (array_is_list($value)) return array_map($canonical, $value);
    ksort($value, SORT_STRING);
    foreach ($value as $key => $item) $value[$key] = $canonical($item);
    return $value;
};
$hashRecord = static fn(array $row): string => hash('sha256', json_encode($canonical($row), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

$cohortPath = 'docs/reports/batch-715-pilot-cohort.json';
$readinessPath = 'docs/reports/batch-715-review-readiness.json';
$b713Path = 'docs/reports/batch-713-cohort-disposition.json';
$cohort = $read($cohortPath);
$readiness = $read($readinessPath);
$b713 = $read($b713Path);
$registry = $read('storage/sources.json');
$registryById = [];
$registryDuplicateIds = [];
foreach ($registry as $record) {
    if (!is_array($record) || trim((string) ($record['id'] ?? '')) === '') continue;
    $id = (string) $record['id'];
    if (isset($registryById[$id])) $registryDuplicateIds[] = $id;
    $registryById[$id] = $record;
}
$readinessByQuestion = [];
foreach ((array) ($readiness['items'] ?? []) as $item) $readinessByQuestion[(string) ($item['question_id'] ?? '')] = $item;
$b713ByQuestion = [];
foreach ((array) ($b713['items'] ?? []) as $item) $b713ByQuestion[(string) ($item['question_id'] ?? '')] = $item;
$pilotItems = array_values(array_filter((array) ($cohort['items'] ?? []), 'is_array'));
$selectedIds = array_values(array_map(static fn(array $item): string => (string) ($item['question_id'] ?? ''), $pilotItems));
if (count($pilotItems) !== 20 || count(array_unique($selectedIds)) !== 20) throw new RuntimeException('B715 pilot cohort is not the expected 20 unique items.');

$annexId = 'prc-resolution-20-2025-annex-a';
$annexPageChecks = [
    '9128' => ['pages' => [9, 10], 'annex' => 'Annex C · Culture and Arts Education', 'scope' => 'The table of specifications lists creative-expression disciplinary knowledge, arts teaching practice, and ethical responsibilities. Page 10 expressly includes ethical guidance for arts production/research, with Indigenous cultural ownership as an example. This establishes examination-scope context only; it does not establish the legal requirements of RA 8371 or validate the proposed response.'],
    '9140' => ['pages' => [9, 10], 'annex' => 'Annex C · Culture and Arts Education', 'scope' => 'The table of specifications includes arts as social/cultural expression and ethical responsibilities in arts production/research, with Indigenous cultural ownership as an example. It does not establish the text or application of RA 8371; that authority and the proposed answer require separate review.'],
    '9164' => ['pages' => [9, 10], 'annex' => 'Annex C · Culture and Arts Education', 'scope' => 'The table of specifications includes pedagogical practice, creative-expression competency, planning/production/evaluation of arts outputs, and research/extension. It provides broad examination-scope context, not item-level validation of an assessment-alignment claim.'],
    '9200' => ['pages' => [1, 3], 'annex' => 'Annex A · Early Childhood Education', 'scope' => 'The table of specifications identifies Early Childhood Education competencies in content/pedagogy, diversity, curriculum/planning, assessment/reporting, community engagement, and professional growth. It does not establish the item’s portfolio-specific conclusion.'],
    '9204' => ['pages' => [1, 3], 'annex' => 'Annex A · Early Childhood Education', 'scope' => 'The table of specifications lists Early Childhood Education domains and competencies including curriculum planning, inclusive/developmentally appropriate pedagogy, and culturally relevant materials. It is scope context only; proposed topic/concept taxonomy still requires a separate governed decision.'],
    '9210' => ['pages' => [1, 3], 'annex' => 'Annex A · Early Childhood Education', 'scope' => 'The table of specifications includes child-development-responsive and culturally relevant Early Childhood Education competencies. It does not establish the separate language/family evidence claim in the proposed item.'],
    '9228' => ['pages' => [1, 3], 'annex' => 'Annex A · Early Childhood Education', 'scope' => 'The table of specifications lists Early Childhood Education topics in learner diversity, children’s rights/equity, community, and professional responsibility. It does not establish a school’s current child-protection procedure or resolve later DepEd issuances.'],
    '9238' => ['pages' => [11, 12], 'annex' => 'Annex D · Physical Education', 'scope' => 'The table of specifications includes physical-health and movement competencies, movement-pattern mastery, and evaluation of critical motor-skill elements. It does not verify the specific movement-analysis answer or its proposed interpretation.'],
    '9250' => ['pages' => [11, 12], 'annex' => 'Annex D · Physical Education', 'scope' => 'The table of specifications includes health/fitness and physical-activity competencies. It does not define or validate the item’s specific muscular-endurance construct or its measurement.'],
    '9280' => ['pages' => [11, 12], 'annex' => 'Annex D · Physical Education', 'scope' => 'The table of specifications includes developmentally appropriate and inclusive physical-education programs that address diverse learner needs. It does not validate this particular adaptation or its safety and effect.'],
    '9290' => ['pages' => [4, 8], 'annex' => 'Annex B · Special Needs Education', 'scope' => 'The table of specifications covers respectful, meaningful learning for learners with additional needs, family collaboration, inclusive environments, individual learning differences, and evidence-based instructional/assessment decisions. It does not establish that the proposed UDL option is uniquely best.'],
    '9302' => ['pages' => [4, 8], 'annex' => 'Annex B · Special Needs Education', 'scope' => 'The table of specifications includes assistive and information/communication technology, access, differentiated learning, and assessment. It does not establish the learner-specific accommodation or construct validity in the proposed item.'],
    '9320' => ['pages' => [4, 8], 'annex' => 'Annex B · Special Needs Education', 'scope' => 'The table of specifications includes observing learner/environment interactions, evidence-based instructional decisions, and positive behavior support while avoiding aversive techniques. It does not by itself establish the proposed functional-behavior inference or intervention.'],
    '9348' => ['pages' => [65, 66], 'annex' => 'BTVTEd common competencies', 'scope' => 'Page 65 identifies common BTVTEd competencies, including tool/equipment use, mensuration, plans/drawings, and workplace occupational health/safety/ethics; page 66 lists relevant qualifications and curriculum references. This is scope context only, not answer validation.'],
    '9360' => ['pages' => [65, 66], 'annex' => 'BTVTEd common competencies', 'scope' => 'Page 65 identifies common BTVTEd competencies, including workplace-related standards; page 66 lists qualification/curriculum references. This is scope context only and does not verify the proposed evidence-collection rule.'],
    '9378' => ['pages' => [65, 66], 'annex' => 'BTVTEd common competencies', 'scope' => 'Page 65 identifies workplace occupational-health, safety, and ethical values among BTVTEd common competencies; page 66 lists related curriculum references. It does not supply a machine-specific guarding or site-reporting procedure.'],
];
$annexSourceInspection = [
    'source_id' => $annexId,
    'official_annex_url' => 'https://www.prc.gov.ph/sites/default/files/2025-20%20Annex%20teachers.pdf',
    'publisher_index_url' => 'https://prc.gov.ph/Pages/PRBv4/ProfessionalTeachersv9.htm',
    'publisher_index_sha256' => '741a2122381f8d384aa96f950bcface53680789a38ea8378d04c0de3b2c0a66b',
    'publisher_index_bytes' => 27640,
    'publisher_index_finding' => 'Official PRC Professional Teachers page lists Board Resolution No. 20 (s. 2025) and an Annex A link whose URL resolves to the registered Annex PDF.',
    'http_status' => 200,
    'content_type' => 'application/pdf',
    'content_length_bytes' => 18103170,
    'etag' => '"1143b82-63a1968917a44"',
    'last_modified' => 'Thu, 17 Jul 2025 05:37:43 GMT',
    'complete_document_sha256' => null,
    'document_identity_limit' => 'Official listing and PDF header establish the linked PRC Annex identity; the complete oversized file was not downloaded or hashed.',
    'bounded_range_inspection' => [
        'per_request_block_limit_bytes' => 262144,
        'aggregate_unique_range_bytes_reported' => 17054594,
        'aggregate_safety_cap_bytes' => 18000000,
        'range_responses_validated' => 'HTTP 206, byte count, and Content-Range were checked for every request by the temporary reader.',
        'per_interval_log_retained' => false,
        'whole_binary_saved' => false,
        'range_budget_exceeded' => false,
        'pdf_page_count_from_partial_header' => 66,
        'pages_text_examined' => [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 65, 66],
        'pages_examined_locator' => 'Direct text extraction from verified HTTP byte ranges for PDF pages 1–12 and 65–66. Page labels identify Annex A (ECE), B (SNE), C (Culture and Arts), D (Physical Education), and BTVTEd common competencies.',
        'extracted_content_summary' => 'Pages 1–3 identify Early Childhood Education competencies; pages 4–8 identify Special Needs Education competencies; pages 9–10 identify Culture and Arts Education competencies and ethics; pages 11–12 identify Physical Education competencies; pages 65–66 identify BTVTEd common competencies and references. Page-specific row summaries state observed scope only; no page proves a proposed answer.',
        'short_observed_excerpt' => 'Application of occupational health, safety, and ethical values in the workplace.',
        'source_content_sha256' => null,
        'inspection_limit' => 'Two bounded sparse byte-range extraction runs returned 17,054,594 bytes each in verified ranges, each below the 18,000,000-byte per-run cap; neither was the complete 18,103,170-byte file. Individual range intervals and extracted page-text bytes were not retained, so an expert should repeat exact page retrieval if byte-level reproducibility is required.',
    ],
    'parent_resolution' => [
        'title' => 'Board Resolution No. 20 (s. 2025), Alignment of the Commission on Higher Education’s Teacher Education Curriculum and the Licensure Examination for Professional Teachers',
        'url' => 'https://www.prc.gov.ph/sites/default/files/2025-20%20published%20teachers.pdf',
        'http_status' => 200,
        'content_type' => 'application/pdf',
        'content_length_bytes' => 1187502,
        'sha256' => 'cca3b6104c751d9a0547e7e43645030773b5ca0d5c51681554fc60700cd589c4',
        'pages_examined' => [1, 2, 3],
        'retrieval_date_utc' => '2026-10-25',
        'observed_context' => 'The resolution describes alignment to the newer teacher-education specializations beginning in September 2025 and states that the associated topics/subtopics/competencies are in an annex for that and subsequent schedules.',
        'appendix_label_ambiguity' => 'The PRC webpage labels the child link “Annex A”; the resolution text calls it “Annex A-1”; the B695 source snapshot calls the file Annex A and assigns sub-areas to pages. Preserve this naming mismatch for reviewer confirmation.',
        'does_not_establish' => 'The parent resolution does not establish that any B695 answer is correct or that every cited annex page was examined.',
    ],
    'citation_rows_with_direct_page_inspection' => array_map('intval', array_keys($annexPageChecks)),
    'citation_rows_requiring_human_page_mapping_confirmation' => array_map('intval', array_keys($annexPageChecks)),
    'unresolved_citation_rows_without_page_inspection' => [],
];
$depedInspection = [
    'source_id' => 'deped-do-40-2012-child-protection',
    'policy_index_url' => 'https://www.deped.gov.ph/deped-policies-and-child-protection-related-laws/',
    'prior_policy_index_sha256' => '0f71ab487fb7cc5f97617c00c384ba3efd36584ba4823a3843574b3cd2e37fdd',
    'policy_index_sha256' => 'aac36f6de6501a52e3eae3228c54b425a2667c2785d4cf465044e3beaff74363',
    'policy_index_bytes' => 279417,
    'latest_direct_recheck_date_utc' => '2026-10-25',
    'index_http_status' => 200,
    'index_inspection' => 'Direct current HTML inspection lists DO 40 s. 2012, DO 55 s. 2013 (Anti-Bullying Act IRR), and DO 3 s. 2021 (Child Protection Unit / Child Rights in Education Desk). The response-byte hash differs from the prior retrieval; this is recorded as snapshot drift without inferring a policy-content change.',
    'official_order_pdf_url' => 'https://www.deped.gov.ph/wp-content/uploads/2012/05/DO_s2012_40.pdf',
    'official_order_pdf_sha256' => 'e679d74e981c12ba1976b521749ba15f6cd7a9c950468a63d331ef90fd50c87d',
    'official_order_pdf_bytes' => 1188026,
    'official_order_pdf_pages' => 26,
    'pdf_rechecked_at_utc_date' => '2026-10-25',
    'pdf_http_status' => 200,
    'pdf_text_extraction' => 'NO_TEXT_LAYER; directly retrieved within the 1.5 MB cap, pypdf parsed 26 pages, and all pages yielded zero extractable characters. This is not content examination of the policy procedure.',
    'historical_b695_review' => 'The preserved B695 review package records the policy scope and its own official-index check, and explicitly does not infer detailed procedure from the scanned annex.',
    'scope_and_currentness_conclusion' => 'The generic child-protection concern in Q9228 remains a candidate fit; a qualified reviewer must verify the exact school procedure, later applicable issuances, and whether the item wording makes a legal finding it should not promise.',
    'supplemental_guidance_recheck' => [
        'url' => 'https://tangub.deped.gov.ph/wp-content/uploads/2025/03/divmemo064_2025.pdf',
        'http_status' => 502,
        'rechecked_at_utc_date' => '2026-10-25',
        'content_examined' => false,
        'note' => 'The direct bounded recheck returned HTTP 502, so the memo is not treated as examined evidence.',
    ],
];
$architectureInspection = [
    'official_schedule_index_url' => 'https://prc.gov.ph/exam-schedules-menu',
    'official_schedule_index_sha256' => '988bbc9218bf361972e914f87b1c07c7382151edf9ea92312c82163f531fd1fb',
    'official_schedule_index_bytes' => 136371,
    'official_2026_schedule_url' => 'https://prc.gov.ph/2026-schedule-examination',
    'official_2026_schedule_sha256' => 'e0f1a48a0fb5ed9a10b23284c04bd0cfa5168e02fdcd045ad60ca8ed569c7ab0',
    'official_2026_schedule_bytes' => 842174,
    'latest_direct_recheck_date_utc' => '2026-10-25',
    'latest_direct_recheck' => 'HTTP 200 from the official PRC schedule index and 2026 schedule. The schedule index linked 2026 and 2025 and no 2027 schedule; the 2026 calendar includes Architecture January 20/22, June 8/10, and October 5–7 events. These calendar entries are not a current detailed subject program.',
    '2026_architects_exam_dates' => ['2026-01-20/22', '2026-06-08/10', '2026-10-05/07'],
    'june_program_status' => 'The B715 June 2026 Architecture program is historical. The official 2026 calendar also lists an October 5–7 Architecture examination, which has passed as of the latest recheck. No future/current detailed Architecture subject program was verified.',
    'jan_url_candidate' => 'https://www.prc.gov.ph/sites/default/files/RA%20Architects%2001-2026%20cebu.pdf',
    'jan_url_candidate_sha256' => 'e645b9814f4e04a6335783b25e02f44fed66a851295b9aa7805dbb3394a4503f',
    'jan_url_candidate_bytes' => 3508806,
    'jan_url_candidate_disposition' => 'NOT_ACCEPTED_AS_EXAM_PROGRAM: direct PDF inspection shows a 31-page regional room assignment/advisory and unrelated examination measures, not the dated subject program claimed by its indexed snippet. Do not substitute it as scope evidence.',
    'review_blocker' => 'All four Architecture items retain current examination-scope/currentness review; the January, June, and October 2026 calendar entries do not establish a future detailed subject program.',
];

$rows = [];
$sourceIds = [];
$seenCitationKeys = [];
$missingRegistry = [];
$staleSnapshots = [];
$examinedCount = 0;
$notExaminedCount = 0;
$reachableCitationCount = 0;
$identityConfirmedCitationCount = 0;
$machineCandidateCount = 0;
foreach ($pilotItems as $pilot) {
    $questionId = (string) ($pilot['question_id'] ?? '');
    $proposalId = (string) ($pilot['proposal_id'] ?? '');
    $evidenceItem = $readinessByQuestion[$questionId] ?? null;
    $b713Item = $b713ByQuestion[$questionId] ?? null;
    if (!is_array($evidenceItem) || !is_array($b713Item)) throw new RuntimeException('Missing B713/B715 evidence for pilot Q' . $questionId);
    $sourceEvidenceById = [];
    foreach ((array) ($b713Item['source_evidence'] ?? []) as $evidence) {
        if (is_array($evidence)) $sourceEvidenceById[(string) ($evidence['source_id'] ?? '')] = $evidence;
    }
    foreach ((array) ($evidenceItem['sources'] ?? []) as $source) {
        if (!is_array($source)) continue;
        $sourceId = (string) ($source['source_id'] ?? '');
        $sourceIds[$sourceId] = true;
        $citationKey = $questionId . '::' . $sourceId;
        if (isset($seenCitationKeys[$citationKey])) throw new RuntimeException('Duplicate proposal/source citation: ' . $citationKey);
        $seenCitationKeys[$citationKey] = true;
        $b713Evidence = $sourceEvidenceById[$sourceId] ?? [];
        $snapshot = is_array($b713Evidence['source_record_snapshot'] ?? null) ? $b713Evidence['source_record_snapshot'] : [];
        $current = $registryById[$sourceId] ?? null;
        if (!is_array($current)) $missingRegistry[] = $citationKey;
        $snapshotMatches = is_array($current) && $snapshot !== [] && $canonical($snapshot) === $canonical($current);
        if (!$snapshotMatches) $staleSnapshots[] = $citationKey;

        $priorExam = is_array($source['source_examination'] ?? null) ? $source['source_examination'] : [];
        $annexPageCheck = $sourceId === $annexId ? ($annexPageChecks[$questionId] ?? null) : null;
        $isNewAnnexPageCheck = is_array($annexPageCheck);
        $depedPdfContentUnavailable = $questionId === '9228' && $sourceId === 'deped-do-40-2012-child-protection';
        $identityConfirmed = !empty($source['source_identity_confirmed']) || ($sourceId === $annexId);
        $contentExamined = (!empty($source['content_examined']) || $isNewAnnexPageCheck) && !$depedPdfContentUnavailable;
        if ($contentExamined) $examinedCount++; else $notExaminedCount++;
        if (!empty($source['url_reachable']) || $sourceId === $annexId) $reachableCitationCount++;
        if ($identityConfirmed) $identityConfirmedCitationCount++;
        $candidate = is_array($source['claim_support_candidate'] ?? null) ? $source['claim_support_candidate'] : [];
        if (!$depedPdfContentUnavailable && trim((string) ($candidate['status'] ?? '')) !== '' && (string) ($candidate['status'] ?? '') !== 'NOT_ASSESSED') $machineCandidateCount++;
        $scopeWarnings = array_values(array_filter(array_merge(
            (array) ($source['b695_limitations'] ?? []),
            !empty($priorExam['scope_limit']) ? [(string) $priorExam['scope_limit']] : []
        ), 'is_string'));
        $currentnessWarnings = [];
        if (in_array((int) $questionId, [9124, 9125, 9126, 9127], true) && $sourceId === 'prc-architecture-exam-program-june-2026') {
            $currentnessWarnings[] = 'Historical June 2026 program; no current/upcoming Architecture subject scope was verified.';
        }
        if ((int) $questionId === 9228 && $sourceId === 'deped-do-40-2012-child-protection') {
            $currentnessWarnings[] = 'Confirm later applicable child-protection issuances and the exact current school process; the scanned annex did not yield text.';
        }
        $blockers = ['QUALIFIED_SOURCE_REVIEW_PENDING'];
        if (!$contentExamined) $blockers[] = 'CITATION_CONTENT_NOT_EXAMINED';
        if ($currentnessWarnings !== []) $blockers[] = 'SOURCE_CURRENTNESS_REQUIRES_HUMAN_RECHECK';
        if ($sourceId === $annexId) $blockers[] = 'ANNEX_LABEL_OR_PAGE_MAPPING_REQUIRES_REVIEWER_CONFIRMATION';
        if ($current === null) $blockers[] = 'SOURCE_REGISTRY_RECORD_MISSING';
        elseif (!$snapshotMatches) $blockers[] = 'SOURCE_REGISTRY_SNAPSHOT_STALE';

        $row = [
            'citation_key' => $citationKey,
            'proposal_id' => $proposalId,
            'question_id' => $questionId,
            'question_version' => (string) ($evidenceItem['question_version'] ?? ''),
            'source_identity' => [
                'source_id' => $sourceId,
                'title' => (string) ($source['title'] ?? $sourceId),
                'authority' => (string) ($source['authority'] ?? ''),
                'url' => (string) ($source['url'] ?? ''),
                'role' => (string) ($source['source_role'] ?? ''),
                'version' => (string) ($source['version'] ?? ''),
            ],
            'source_registry' => [
                'record_present' => is_array($current),
                'record_snapshot_sha256' => $snapshot === [] ? null : $hashRecord($snapshot),
                'current_record_sha256' => is_array($current) ? $hashRecord($current) : null,
                'snapshot_matches_current_record' => $snapshotMatches,
            ],
            'locator' => [
                'proposal_source_locator' => (string) ($evidenceItem['proposed_source_locator'] ?? ''),
                'examined_locator' => $isNewAnnexPageCheck ? 'PRC Resolution 20 s. 2025 linked PDF ' . $annexPageCheck['annex'] . ', pp. ' . implode('–', $annexPageCheck['pages']) . ' (direct text extraction from verified HTTP byte ranges).' : ($contentExamined ? (string) ($priorExam['inspection_locator'] ?? '') : null),
                'page_or_section' => $isNewAnnexPageCheck ? $annexPageCheck['pages'] : null,
            ],
            'retrieval' => [
                'url_reachable' => !empty($source['url_reachable']) || $sourceId === $annexId,
                'url_reachability_evidence' => $sourceId === $annexId ? 'Official PRC page link plus HTTP 200/206 PDF responses' : (string) ($priorExam['reachability'] ?? 'B715_RECORDED'),
                'identity_confirmed' => $identityConfirmed,
                'identity_confirmation_basis' => $sourceId === $annexId ? 'Official PRC Professional Teachers index lists Resolution 20 s. 2025 and links Annex A to the exact registered PDF URL.' : (!empty($source['source_identity_confirmed']) ? 'B715 source inspection record' : 'Not confirmed'),
                'retrieved_at_utc' => $priorExam['retrieved_at_utc'] ?? null,
                'retrieval_time_status' => !empty($priorExam['retrieved_at_utc']) ? 'RECORDED' : 'EXACT_TIME_NOT_RETAINED',
                'retrieval_time_note' => !empty($priorExam['retrieved_at_utc']) ? null : 'The recoverable evidence records a calendar date but not a trustworthy UTC timestamp; no time was inferred.',
                'retrieval_date' => $isNewAnnexPageCheck ? '2026-10-25' : ($priorExam['retrieval_date'] ?? null),
                'content_sha256' => $isNewAnnexPageCheck ? null : ($priorExam['content_digest_sha256'] ?? null),
                'content_hash_note' => $isNewAnnexPageCheck ? 'No complete Annex hash: full source was not downloaded. Parent resolution SHA is recorded separately.' : null,
            ],
            'content_examined' => $contentExamined,
            'content_examined_provenance' => $isNewAnnexPageCheck ? 'B716_DIRECT_PAGE_RANGE_INSPECTION' : ($depedPdfContentUnavailable ? 'CONTENT_NOT_EXAMINED_SOURCE_SCANNED' : (!empty($source['content_examined']) ? 'B715_RECORDED_SOURCE_EXAMINATION' : 'NOT_EXAMINED')),
            'examined_content_scope' => $isNewAnnexPageCheck
                ? $annexPageCheck['scope']
                : ($depedPdfContentUnavailable ? null
                : ($contentExamined ? (string) ($priorExam['content_summary'] ?? '') : null)),
            'specific_claim_to_review' => (string) ($evidenceItem['specific_claim_to_review'] ?? ''),
            'machine_claim_check' => [
                'status' => (string) ($candidate['status'] ?? 'NOT_ASSESSED'),
                'interpretation' => 'UNADJUDICATED_MACHINE_CANDIDATE; not a correctness, source-review, or approval finding',
                'candidate_note' => (string) ($candidate['note'] ?? ''),
            ],
            'source_scope_warnings' => array_values(array_unique($scopeWarnings)),
            'currentness_warnings' => $currentnessWarnings,
            'possible_counterevidence' => 'No complete counterevidence search was performed in B716. The qualified reviewer must check complete governing materials and contradictory authorities; absence of a recorded contradiction is not evidence that none exists.',
            'human_source_review' => 'PENDING_QUALIFIED_REVIEWER',
            'academic_approval' => 'NOT_APPROVED',
            'blocking_reasons' => array_values(array_unique($blockers)),
        ];
        if ($sourceId === $annexId) {
            $row['additional_official_context'] = $annexSourceInspection;
            if ($isNewAnnexPageCheck) {
                $row['machine_claim_check']['interpretation'] = 'EXAM_SCOPE_CONTEXT_OBSERVED; item answer and pedagogical/technical claim still require qualified review';
                $row['machine_claim_check']['status'] = 'EXAM_SCOPE_ONLY';
                $row['machine_claim_check']['candidate_note'] = 'Directly inspected page label and subject scope are recorded, but this PRC source does not independently establish the proposed answer or its item-level claim.';
                $row['source_scope_warnings'][] = 'Direct page examination establishes only the listed examination-scope context; item-level content support remains unadjudicated.';
                $row['content_examined_provenance'] = 'B716_DIRECT_PAGE_RANGE_INSPECTION';
                $row['retrieval']['identity_confirmation_basis'] = 'Official PRC Professional Teachers index links the registered PDF URL; direct page labels were inspected for the proposal-specific Annex section. The registry title/Annex label remains subject to qualified confirmation.';
            }
        }
        if ((int) $questionId === 9228 && $sourceId === 'deped-do-40-2012-child-protection') $row['additional_official_context'] = $depedInspection;
        if ($depedPdfContentUnavailable) {
            $row['machine_claim_check'] = [
                'status' => 'CONTENT_NOT_EXAMINED',
                'interpretation' => 'The reachable official PDF is scanned; its relevant procedural text was not obtained or examined.',
                'candidate_note' => 'The official index confirms related orders exist, but the scanned DO 40 PDF and unavailable supplemental guidance do not support an item-level procedure finding in this pass.',
            ];
            $row['source_scope_warnings'][] = 'The retrieved order PDF has no extractable text layer; title/index inspection is not content examination of the applicable school procedure.';
            $row['blocking_reasons'][] = 'CITATION_CONTENT_NOT_EXAMINED';
            $row['blocking_reasons'][] = 'SOURCE_CURRENTNESS_REQUIRES_HUMAN_RECHECK';
        }
        if (in_array((int) $questionId, [9124, 9125, 9126, 9127], true) && $sourceId === 'prc-architecture-exam-program-june-2026') $row['additional_official_context'] = $architectureInspection;
        $rows[] = $row;
    }
}
if (count($rows) !== 41 || count($sourceIds) !== 12) throw new RuntimeException('Expected 41 citation rows and 12 unique source identities.');

$matrix = [
    'schema' => 'boardprep.batch-716-source-evidence-matrix.v1',
    'generated_at_utc' => gmdate(DATE_ATOM),
    'provenance' => [
        'pilot_manifest' => ['path' => $cohortPath, 'sha256' => hash_file('sha256', $root . '/' . $cohortPath)],
        'B715_readiness' => ['path' => $readinessPath, 'sha256' => hash_file('sha256', $root . '/' . $readinessPath)],
        'B713_cohort_inventory' => ['path' => $b713Path, 'sha256' => hash_file('sha256', $root . '/' . $b713Path)],
        'source_registry' => ['path' => 'storage/sources.json', 'sha256' => hash_file('sha256', $root . '/storage/sources.json'), 'records' => count($registry)],
        'source_registry_baseline_sha256' => '7c640636dcd3ddd0f865106ba4506a5af5dcd4a02a2074c065f695da5ef2c271',
        'B695_historical_source' => ['commit' => (string) ($b713['source']['commit_sha'] ?? ''), 'manifest_sha256' => (string) ($b713['source']['manifest_sha256'] ?? ''), 'review_package_sha256' => (string) ($b713['source']['review_package_sha256'] ?? '')],
        'currentness_note' => 'B715 examination timestamps retain their original recorded date; B716 direct retrieval times are recorded only where the HTTP response timestamp was retained. Null timestamps are not inferred.',
    ],
    'summary' => [
        'pilot_proposals' => 20,
        'citation_rows' => count($rows),
        'unique_sources' => count($sourceIds),
        'url_reachable_citation_rows' => $reachableCitationCount,
        'identity_confirmed_citation_rows' => $identityConfirmedCitationCount,
        'content_examined_citation_rows' => $examinedCount,
        'content_not_examined_citation_rows' => $notExaminedCount,
        'machine_claim_candidate_rows' => $machineCandidateCount,
        'machine_claims_verified' => 0,
        'qualified_source_reviews_complete' => 0,
        'qualified_source_reviews_pending' => count($rows),
        'academic_approvals' => 0,
        'duplicate_citation_keys' => count($rows) - count($seenCitationKeys),
        'missing_source_registry_records' => count(array_unique($missingRegistry)),
        'stale_source_registry_snapshots' => count(array_unique($staleSnapshots)),
        'duplicate_source_registry_ids' => count(array_unique($registryDuplicateIds)),
        'proposal_version_conflicts' => 0,
        'proposal_version_revalidation' => 'EXACT_VERSION_RECHECKED_BY Batch716EvidenceHandoffTest.php against AcademicReviewWorkflowService::version()',
        'annex_rows_newly_content_examined' => count($annexPageChecks),
        'annex_rows_still_content_unexamined' => count($annexSourceInspection['unresolved_citation_rows_without_page_inspection']),
    ],
    'state_separation' => [
        'url_reachable' => 'Transport response only; not identity or content proof.',
        'source_identity_confirmed' => 'Publisher/record identity check; not content examination.',
        'content_examined' => 'Relevant specified pages/text directly inspected; partial source examination is scoped to exact pages.',
        'machine_claim_check' => 'Candidate only; not expert source adjudication.',
        'human_source_review' => 'All 41 citation rows remain pending qualified review.',
        'academic_approval' => 'All 20 pilot proposals remain pending; no real approval/import exists.',
    ],
    'additional_official_inspections' => [$annexSourceInspection, $depedInspection, $architectureInspection],
    'citation_rows' => $rows,
];

$matrixPath = 'docs/reports/batch-716-source-evidence-matrix.json';
$write($matrixPath, $matrix);
$rowsByQuestion = [];
foreach ($rows as $row) $rowsByQuestion[(string) $row['question_id']][] = $row;
$packets = [];
foreach ($pilotItems as $pilot) {
    $id = (string) $pilot['question_id'];
    $item = $readinessByQuestion[$id];
    $itemWarnings = [];
    foreach ((array) ($item['sources'] ?? []) as $itemSource) {
        if (!is_array($itemSource)) continue;
        $itemWarnings = array_merge($itemWarnings, (array) ($itemSource['b695_limitations'] ?? []));
        $scopeLimit = (string) ($itemSource['source_examination']['scope_limit'] ?? '');
        if ($scopeLimit !== '') $itemWarnings[] = $scopeLimit;
    }
    $packets[] = [
        'proposal_id' => (string) $item['proposal_id'],
        'question_id' => $id,
        'question_version' => (string) $item['question_version'],
        'version_basis' => (string) $item['version_basis'],
        'review_state' => (string) $item['review_state'],
        'academic_approval' => 'NOT_APPROVED',
        'production_status' => 'NONCANONICAL_NOT_IN_LEARNER_POOLS',
        'board' => (array) ($pilot['board'] ?? []),
        'subject' => (array) ($pilot['subject'] ?? []),
        'selection_rationale' => (string) ($cohort['selection_algorithm']['selection_rationales_by_question_id'][$id] ?? ''),
        'question' => (array) ($item['question'] ?? []),
        'taxonomy_candidates' => (array) ($item['taxonomy_resolution'] ?? []),
        'study_link_candidates' => (array) ($item['study_resolution'] ?? []),
        'review_questions' => array_values((array) ($item['review_questions'] ?? [])),
        'claim_to_review' => [
            'statement' => (string) ($item['specific_claim_to_review'] ?? ''),
            'proposal_source_locator' => (string) ($item['proposed_source_locator'] ?? ''),
            'sources' => $rowsByQuestion[$id] ?? [],
            'possible_counterevidence' => 'Not exhaustively searched by machine preparation. Reviewer should check complete governing material, exceptions, later versions, and credible contradictory authorities.',
        ],
        'reviewer_checklist' => [
            'question_accuracy' => 'PENDING_HUMAN_REVIEW',
            'proposed_answer_and_key' => 'PENDING_HUMAN_REVIEW',
            'explanation_correctness' => 'PENDING_HUMAN_REVIEW',
            'examination_relevance_and_currentness' => 'PENDING_HUMAN_REVIEW',
            'board_subject_classification' => 'PENDING_HUMAN_REVIEW',
            'source_support_and_limitations' => 'PENDING_QUALIFIED_SOURCE_REVIEW',
            'taxonomy_mapping' => (array) ($item['taxonomy_resolution'] ?? []),
            'study_relationship' => (array) ($item['study_resolution'] ?? []),
            'existing_warnings' => array_values(array_unique(array_values(array_filter($itemWarnings, 'is_string')))),
            'decision_questions' => array_values((array) ($item['review_questions'] ?? [])),
            'answer_key_review_questions' => [
                'Does the stem have one defensible best answer under the cited authority and stated assumptions?',
                'Does the proposed answer follow from the exact cited passage, including exceptions and currentness?',
                'Does the explanation accurately distinguish the rule/evidence from professional judgment and unstated assumptions?',
            ],
        ],
        'next_required_actions' => [
            'Independently inspect every citation at its exact locator and record source review per citation.',
            'Confirm source scope and currentness; leave unavailable or partial evidence unresolved.',
            'Resolve taxonomy only to an existing valid mapping or submit a separate governance request.',
            'Resolve a study relationship only when the exact published section exists and fits; never treat it as source support.',
            'A qualified scoped reviewer records an explicit version-bound academic decision.',
            'Only after all separate gates pass may a separately authorized importer preview and confirm this one item.',
        ],
        'blocking_actions' => array_values(array_unique(array_merge(
            ['NO_REAL_REVIEWER_GRANT_OR_ASSIGNMENT', 'NO_QUALIFIED_SOURCE_REVIEW', 'NO_ACADEMIC_DECISION', 'NO_IMPORT_AUTHORIZATION'],
            array_merge(...array_map(static fn(array $source): array => (array) ($source['blocking_reasons'] ?? []), $rowsByQuestion[$id] ?? []))
        ))),
        'citation_keys' => array_values(array_map(static fn(array $row): string => (string) $row['citation_key'], $rowsByQuestion[$id] ?? [])),
    ];
}
$packetFile = [
    'schema' => 'boardprep.batch-716-pilot-review-packets.v1',
    'generated_at_utc' => gmdate(DATE_ATOM),
    'purpose' => 'Restricted workbench aid for qualified human reviewers; machine-prepared content, not source expert findings or academic approval.',
    'cohort_manifest_sha256' => hash_file('sha256', $root . '/' . $cohortPath),
    'source_evidence_matrix_sha256' => hash_file('sha256', $root . '/' . $matrixPath),
    'packet_count' => count($packets),
    'real_approvals' => 0,
    'real_imports' => 0,
    'items' => $packets,
];
$write('docs/reports/batch-716-pilot-review-packets.json', $packetFile);
fwrite(STDOUT, json_encode([
    'matrix' => $matrixPath,
    'packets' => 'docs/reports/batch-716-pilot-review-packets.json',
    'citation_rows' => count($rows),
    'unique_sources' => count($sourceIds),
    'content_examined' => $examinedCount,
    'content_not_examined' => $notExaminedCount,
    'registry_snapshot_differences' => count(array_unique($staleSnapshots)),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
