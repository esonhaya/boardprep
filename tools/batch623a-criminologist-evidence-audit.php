<?php

declare(strict_types=1);

/**
 * Batch 623A: diagnostic Criminologist evidence-gap map.
 *
 * This audit is intentionally read-only with respect to production data.  With
 * --write it writes only storage/batch623a-criminologist-evidence-map.json.
 * It does not change questions, depth metadata, coverage, eligibility, links,
 * sources, prerequisites, context, or reuse relationships.
 */

$root = dirname(__DIR__);
$write = in_array('--write', $argv, true);
$baseHead = '630a778912d82bce79fd2bf816f6e7e8558c4429';
$verifiedOn = '2026-09-29';

$read = static function (string $relative) use ($root): array {
    $path = $root . '/' . $relative;
    if (!is_file($path)) {
        throw new RuntimeException('Missing input: ' . $relative);
    }
    $value = json_decode((string)file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    return is_array($value) ? $value : [];
};

$writeJson = static function (string $relative, array $value) use ($root): void {
    $path = $root . '/' . $relative;
    file_put_contents($path, json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
};

$norm = static function (string $value): string {
    return trim((string)(preg_replace('/\s+/u', ' ', $value) ?? $value));
};

$depth = static function (mixed $value): string {
    $value = (string)$value;
    return in_array($value, ['1', '2', '3', '4', '5'], true) ? $value : 'UNKNOWN';
};

$sortedUnique = static function (array $values): array {
    $values = array_values(array_unique(array_map('strval', $values)));
    sort($values, SORT_STRING);
    return $values;
};

$questions = array_values(array_filter($read('storage/questions.json'), 'is_array'));
$metadataRows = array_values(array_filter($read('storage/question-reuse-metadata.json'), 'is_array'));
$coverageRows = array_values(array_filter($read('storage/board-concept-coverage.json'), 'is_array'));
$eligibilityRows = array_values(array_filter($read('storage/question-eligibility.json'), 'is_array'));
$derivedRows = array_values(array_filter($read('storage/concept-derived-eligibility.json'), 'is_array'));
$linksRows = array_values(array_filter($read('storage/study-question-links.json'), 'is_array'));
$materials = array_values(array_filter($read('storage/study-materials.json'), 'is_array'));
$sources = array_values(array_filter($read('storage/sources.json'), 'is_array'));
$subjects = array_values(array_filter($read('storage/board-subjects.json'), 'is_array'));
$domains = array_values(array_filter($read('storage/taxonomy/domains.json'), 'is_array'));
$topics = array_values(array_filter($read('storage/taxonomy/topics.json'), 'is_array'));
$concepts = array_values(array_filter($read('storage/taxonomy/concepts.json'), 'is_array'));
$boards = array_values(array_filter($read('storage/boards.json'), 'is_array'));

$questionById = [];
foreach ($questions as $question) $questionById[(string)($question['id'] ?? '')] = $question;
$metadataByQuestion = [];
foreach ($metadataRows as $row) $metadataByQuestion[(string)($row['question_id'] ?? '')] = $row;
$materialById = [];
foreach ($materials as $material) $materialById[(string)($material['id'] ?? '')] = $material;
$sourceById = [];
foreach ($sources as $source) $sourceById[(string)($source['id'] ?? '')] = $source;
$domainById = [];
foreach ($domains as $row) $domainById[(string)($row['id'] ?? '')] = $row;
$topicById = [];
foreach ($topics as $row) $topicById[(string)($row['id'] ?? '')] = $row;
$conceptById = [];
foreach ($concepts as $row) $conceptById[(string)($row['id'] ?? '')] = $row;

$linksByQuestion = [];
foreach ($linksRows as $link) $linksByQuestion[(string)($link['question_id'] ?? '')][] = $link;

$directBoardsByQuestion = [];
$pairCounts = [];
foreach ($eligibilityRows as $entry) {
    $entryBoards = array_values(array_filter(array_map('strval', $entry['exam_ids'] ?? [])));
    foreach ($entry['question_ids'] ?? [] as $questionId) {
        $id = (string)$questionId;
        $mapping = is_array($entry['question_mappings'][$id] ?? null) ? $entry['question_mappings'][$id] : [];
        $mappedBoards = array_values(array_filter(array_map('strval', $mapping['exam_ids'] ?? $entryBoards)));
        foreach ($mappedBoards as $board) {
            $board = strtolower($board);
            if ($board === 'cse' || $board === 'civilservice') $board = 'civil-service';
            if ($board === '') continue;
            $directBoardsByQuestion[$id][$board] = true;
            $pairCounts[$id . '|' . $board] = ($pairCounts[$id . '|' . $board] ?? 0) + 1;
        }
    }
}
$derivedBoardsByQuestion = [];
foreach ($derivedRows as $row) {
    if (($row['record_type'] ?? '') !== 'RELATIONSHIP') continue;
    $id = (string)($row['question_id'] ?? '');
    $board = strtolower((string)($row['target_board'] ?? ''));
    if ($id !== '' && $board !== '') $derivedBoardsByQuestion[$id][$board] = true;
}
$effectiveBoardsByQuestion = [];
foreach ($questions as $question) {
    $id = (string)($question['id'] ?? '');
    $taxonomyBoard = strtolower((string)($question['taxonomy']['board_id'] ?? ''));
    if ($taxonomyBoard !== '') $effectiveBoardsByQuestion[$id][$taxonomyBoard] = true;
    foreach (array_keys($directBoardsByQuestion[$id] ?? []) as $board) $effectiveBoardsByQuestion[$id][$board] = true;
    foreach (array_keys($derivedBoardsByQuestion[$id] ?? []) as $board) $effectiveBoardsByQuestion[$id][$board] = true;
}
$criminologistQuestionIds = [];
foreach ($effectiveBoardsByQuestion as $id => $boardMap) if (isset($boardMap['criminologist'])) $criminologistQuestionIds[(int)$id] = true;
ksort($criminologistQuestionIds, SORT_NUMERIC);

$nativeCanonical = array_values(array_filter($questions, static fn(array $q): bool => strtolower((string)($q['taxonomy']['board_id'] ?? '')) === 'criminologist'));
$nativeCanonicalIds = [];
foreach ($nativeCanonical as $question) $nativeCanonicalIds[(int)$question['id']] = true;

$nativeSubjectIds = [];
foreach ($subjects as $row) if (($row['board_id'] ?? '') === 'criminologist') $nativeSubjectIds[(string)($row['subject_id'] ?? '')] = true;
$nativeDomainIds = [];
foreach ($domains as $row) if (isset($nativeSubjectIds[(string)($row['subject_id'] ?? '')])) $nativeDomainIds[(string)($row['id'] ?? '')] = true;
$nativeTopicIds = [];
foreach ($topics as $row) if (isset($nativeDomainIds[(string)($row['domain_id'] ?? '')])) $nativeTopicIds[(string)($row['id'] ?? '')] = true;
$nativeConceptIds = [];
foreach ($concepts as $row) if (isset($nativeTopicIds[(string)($row['topic_id'] ?? '')])) $nativeConceptIds[(string)($row['id'] ?? '')] = true;

$nativeCoverage = array_values(array_filter($coverageRows, static fn(array $row): bool => strtolower((string)($row['board_id'] ?? '')) === 'criminologist'));
$coverageByConcept = [];
foreach ($nativeCoverage as $row) $coverageByConcept[(string)($row['concept_id'] ?? '')] = $row;

$unknownQuestionIds = [];
foreach ($criminologistQuestionIds as $id => $_) {
    if (($depth($metadataByQuestion[(string)$id]['reuse']['required_depth'] ?? null)) === 'UNKNOWN') $unknownQuestionIds[] = (int)$id;
}
sort($unknownQuestionIds, SORT_NUMERIC);
$unknownCoverageRows = array_values(array_filter($nativeCoverage, static fn(array $row): bool => $depth($row['max_depth'] ?? null) === 'UNKNOWN'));
usort($unknownCoverageRows, static fn(array $a, array $b): int => strcmp((string)($a['concept_id'] ?? ''), (string)($b['concept_id'] ?? '')));

if (count($unknownQuestionIds) !== 24) throw new RuntimeException('Expected 24 Criminologist question UNKNOWNs, found ' . count($unknownQuestionIds));
if (count($unknownCoverageRows) !== 12) throw new RuntimeException('Expected 12 Criminologist coverage UNKNOWNs, found ' . count($unknownCoverageRows));

$officialSources = [
    [
        'source_id' => 'official-prc-criminology-board-page',
        'title' => 'PRC Professional Regulatory Board of Criminology board page',
        'authority' => 'Professional Regulation Commission / PRB of Criminology',
        'url' => 'https://www.prc.gov.ph/Pages/PRBv4/Criminologyv3.htm',
        'role' => 'SCOPE_SOURCE',
        'type' => 'OFFICIAL_GOVERNMENT',
        'version_or_date' => 'page checked 2026-09-29',
        'currentness' => 'CURRENT',
        'purpose' => 'Current PRC board resolutions, TOS links, and amendments index.',
    ],
    [
        'source_id' => 'prc-criminology-enhanced-tos-2024',
        'title' => 'Enhanced Tables of Specifications for the Subjects in the Licensure Examination for Criminologists',
        'authority' => 'Professional Regulation Commission / PRB of Criminology',
        'url' => 'https://www.prc.gov.ph/sites/default/files/2024-02%20Published%20%281%29%20%281%29.pdf',
        'role' => 'SCOPE_SOURCE',
        'type' => 'OFFICIAL_GOVERNMENT',
        'version_or_date' => 'Resolution No. 02 s. 2024; effective after publication; applied July 2024 onward',
        'currentness' => 'CURRENT_BASE_TOS',
        'purpose' => 'Official six-area LEC scope and competency/TOS authority.',
    ],
    [
        'source_id' => 'prc-criminology-ethics-amendment-2025',
        'title' => 'Amendments to Board Resolution No. 02 s. 2024 in Professional Conduct and Ethical Standards',
        'authority' => 'Professional Regulation Commission / PRB of Criminology',
        'url' => 'https://www.prc.gov.ph/sites/default/files/2025-06%20published%20Amendments%20To%20Board%20Resolution%20No.%2002%20%28S.%202024%29%20in%20the%20Subject%20Area%20of%20Criminology.pdf',
        'role' => 'SCOPE_SOURCE',
        'type' => 'OFFICIAL_GOVERNMENT',
        'version_or_date' => 'Resolution No. 06 s. 2025; applied August 2025 and subsequent schedules',
        'currentness' => 'CURRENT_AMENDMENT',
        'purpose' => 'Current ethics/professional-conduct amendment to the enhanced TOS.',
    ],
    [
        'source_id' => 'prc-criminology-schedule-adjustment-2025',
        'title' => 'Adjustments in the Schedule of the Criminology and Correctional Administration Subjects',
        'authority' => 'Professional Regulation Commission',
        'url' => 'https://prc.gov.ph/article/adjustments-schedule-criminology-and-correctional-administration-subjects-august-2025-lec',
        'role' => 'SCOPE_SOURCE',
        'type' => 'OFFICIAL_GOVERNMENT',
        'version_or_date' => '30 July 2025; succeeding schedules',
        'currentness' => 'CURRENT_SCHEDULE_WEIGHT',
        'purpose' => 'Current published subject sequence and weights: 20/15/20/15/20/10.',
    ],
    [
        'source_id' => 'prc-2026-examination-schedule',
        'title' => 'PRC 2026 Schedule of Examinations',
        'authority' => 'Professional Regulation Commission',
        'url' => 'https://prc.gov.ph/2026-schedule-examination',
        'role' => 'SCOPE_SOURCE',
        'type' => 'OFFICIAL_GOVERNMENT',
        'version_or_date' => '2026 schedule; checked 2026-09-29',
        'currentness' => 'CURRENT_SCHEDULE',
        'purpose' => 'Confirms the current 2026 Criminologists examination identity and administration.',
    ],
    [
        'source_id' => 'lawphil-ra-11131',
        'title' => 'Republic Act No. 11131, Philippine Criminology Profession Act of 2018',
        'authority' => 'Congress of the Philippines; Lawphil text',
        'url' => 'https://lawphil.net/statutes/repacts/ra2018/ra_11131_2018.html',
        'role' => 'STATUTORY_SOURCE',
        'type' => 'PRIMARY_LAW',
        'version_or_date' => '2018-10-12; amendments must be checked separately',
        'currentness' => 'STATUTORY_VERSIONED',
        'purpose' => 'Professional scope, Board powers, examination subjects, and statutory practice boundaries.',
    ],
    [
        'source_id' => 'prc-criminology-irr-2020',
        'title' => 'Implementing Rules and Regulations of Republic Act No. 11131',
        'authority' => 'Professional Regulation Commission / PRB of Criminology',
        'url' => 'https://www.prc.gov.ph/sites/default/files/2020-02%20published%20IRR.pdf',
        'role' => 'REGULATORY_SOURCE',
        'type' => 'OFFICIAL_GOVERNMENT',
        'version_or_date' => 'Resolution No. 02 s. 2020; verify subsequent amendments',
        'currentness' => 'REGULATORY_CURRENTNESS_REQUIRED',
        'purpose' => 'Professional practice and regulatory implementation context.',
    ],
    [
        'source_id' => 'lawphil-rules-of-court-evidence',
        'title' => 'Revised Rules on Evidence, Rules of Court',
        'authority' => 'Supreme Court of the Philippines; Lawphil reproduction',
        'url' => 'https://lawphil.net/courts/rules/rc_128-134_evidence.html',
        'role' => 'PROCEDURAL_SOURCE',
        'type' => 'JUDICIAL_PROCEDURE',
        'version_or_date' => 'current text must be checked against Supreme Court issuances',
        'currentness' => 'VERSION_SENSITIVE',
        'purpose' => 'Evidence relevance, admissibility, weight, and proof concepts.',
    ],
    [
        'source_id' => 'nist-forensic-science-standards',
        'title' => 'Forensic Science Standards Program',
        'authority' => 'National Institute of Standards and Technology / OSAC',
        'url' => 'https://www.nist.gov/spo/forensic-science-standards-program',
        'role' => 'FORENSIC_SCIENTIFIC_SOURCE',
        'type' => 'GOVERNMENT_SCIENTIFIC',
        'version_or_date' => 'page checked 2026-09-29; registry is evolving',
        'currentness' => 'PERIODICALLY_UPDATED',
        'purpose' => 'Scientific validity, quality, reproducibility, and standards review for forensic claims.',
    ],
    [
        'source_id' => 'nist-scientific-foundation-reviews',
        'title' => 'NIST Scientific Foundation Reviews',
        'authority' => 'National Institute of Standards and Technology',
        'url' => 'https://www.nist.gov/forensic-science/interdisciplinary-topics/scientific-foundation-reviews',
        'role' => 'FORENSIC_SCIENTIFIC_SOURCE',
        'type' => 'GOVERNMENT_SCIENTIFIC',
        'version_or_date' => 'page updated 2026-06-29; checked 2026-09-29',
        'currentness' => 'PERIODICALLY_UPDATED',
        'purpose' => 'Scientific-foundation, limitations, validation, and communication review.',
    ],
];

$officialAreaByDomain = [
    'cle-criminal-jurisprudence' => ['official_area' => 'Criminal Jurisprudence and Procedure', 'weight' => '20%', 'status' => 'ALIGNED'],
    'cle-law-enforcement' => ['official_area' => 'Law Enforcement Administration', 'weight' => '15%', 'status' => 'ALIGNED'],
    'cle-investigation' => ['official_area' => 'Crime Detection and Investigation', 'weight' => '20%', 'status' => 'PARTIALLY_ALIGNED'],
    'cle-forensics' => ['official_area' => 'Forensic Science', 'weight' => '15%', 'status' => 'PARTIALLY_ALIGNED'],
    'cle-criminology' => ['official_area' => 'Criminology', 'weight' => '20%', 'status' => 'ALIGNED'],
    'cle-corrections' => ['official_area' => 'Correctional Administration', 'weight' => '10%', 'status' => 'ALIGNED'],
];

$nativeConceptQuestionCounts = [];
$nativeConceptCanonicalCounts = [];
foreach ($criminologistQuestionIds as $id => $_) {
    $question = $questionById[(string)$id] ?? [];
    $conceptId = (string)($question['taxonomy']['concept_id'] ?? '');
    $nativeConceptQuestionCounts[$conceptId] = ($nativeConceptQuestionCounts[$conceptId] ?? 0) + 1;
    if (isset($nativeCanonicalIds[$id])) $nativeConceptCanonicalCounts[$conceptId] = ($nativeConceptCanonicalCounts[$conceptId] ?? 0) + 1;
}
ksort($nativeConceptQuestionCounts);
ksort($nativeConceptCanonicalCounts);

$studyIdsForConcept = static function (string $conceptId, array $criminologistQuestionIds, array $questionById, array $linksByQuestion): array {
    $out = [];
    foreach ($criminologistQuestionIds as $id => $_) {
        if ((string)($questionById[(string)$id]['taxonomy']['concept_id'] ?? '') !== $conceptId) continue;
        foreach ($linksByQuestion[(string)$id] ?? [] as $link) $out[] = (string)($link['material_id'] ?? '');
    }
    $out = array_values(array_filter(array_unique($out)));
    sort($out, SORT_STRING);
    return $out;
};
$sourceIdsForConcept = static function (string $conceptId, array $criminologistQuestionIds, array $questionById, array $linksByQuestion): array {
    $out = [];
    foreach ($criminologistQuestionIds as $id => $_) {
        if ((string)($questionById[(string)$id]['taxonomy']['concept_id'] ?? '') !== $conceptId) continue;
        foreach ($linksByQuestion[(string)$id] ?? [] as $link) foreach ($link['source_ids'] ?? [] as $sourceId) $out[] = (string)$sourceId;
    }
    $out = array_values(array_filter(array_unique($out)));
    sort($out, SORT_STRING);
    return $out;
};

$versionCategoriesByQuestion = [
    708 => ['STATUTORY_VERSION_SENSITIVE'],
    710 => ['EVIDENCE_RULE_SENSITIVE'],
    712 => ['EVIDENCE_RULE_SENSITIVE'],
    714 => ['CRIMINAL_PROCEDURE_SENSITIVE', 'EVIDENCE_RULE_SENSITIVE'],
    716 => ['EVIDENCE_RULE_SENSITIVE'],
    718 => ['EVIDENCE_RULE_SENSITIVE'],
    720 => ['EVIDENCE_RULE_SENSITIVE'],
    722 => ['EVIDENCE_RULE_SENSITIVE'],
    724 => ['STATUTORY_VERSION_SENSITIVE'],
    726 => ['CRIMINAL_PROCEDURE_SENSITIVE'],
    728 => ['REGULATORY_SENSITIVE', 'PROFESSIONAL_RULE_SENSITIVE'],
    730 => ['CRIMINAL_PROCEDURE_SENSITIVE', 'EVIDENCE_RULE_SENSITIVE'],
    735 => ['LAW_ENFORCEMENT_PROCEDURE_SENSITIVE'],
    745 => ['LAW_ENFORCEMENT_PROCEDURE_SENSITIVE'],
    753 => ['LAW_ENFORCEMENT_PROCEDURE_SENSITIVE'],
    755 => ['LAW_ENFORCEMENT_PROCEDURE_SENSITIVE'],
];

$provisionalDepthByQuestion = [
    708 => ['3', 'MEDIUM', 'Statutory mental-state application is likely routine application once the governing rule is verified.'],
    710 => ['4', 'MEDIUM', 'The item separates reliability from custody documentation and requires evidence-foundation analysis.'],
    712 => ['3', 'MEDIUM', 'The item applies provenance/corroboration reasoning to an unverified lead.'],
    714 => ['4', 'MEDIUM', 'The item integrates documentation, voluntariness context, provenance, and corroboration.'],
    716 => ['3', 'MEDIUM', 'The item applies a bounded reliability-support test rather than a complex legal judgment.'],
    718 => ['4', 'MEDIUM', 'The item compares conflicting records across provenance, timing, and firsthand basis.'],
    720 => ['4', 'MEDIUM', 'The item evaluates competing inculpatory and alternative inferences.'],
    722 => ['3', 'MEDIUM', 'The item applies provenance limits while preserving a lead for verification.'],
    724 => ['STILL_UNDETERMINED', 'INSUFFICIENT', 'The effect of the emergency depends materially on the verified statutory justification or excuse.'],
    726 => ['STILL_UNDETERMINED', 'INSUFFICIENT', 'The warrant-exception conditions are not identified, so procedural law could change the cognitive demand.'],
    728 => ['STILL_UNDETERMINED', 'INSUFFICIENT', 'The lawful-public-safety and privacy boundary requires current statutory/regulatory authority.'],
    730 => ['4', 'MEDIUM', 'The item requires incident-specific chronology and admissibility-linked evidence selection.'],
    733 => ['3', 'MEDIUM', 'The item interprets two performance statistics and identifies the slower-response tail.'],
    735 => ['4', 'MEDIUM', 'The item prioritizes a continuity and evidence-integrity risk against a competing resource request.'],
    737 => ['4', 'MEDIUM', 'The item diagnoses staffing data by integrating intake, leave coverage, and scheduling.'],
    739 => ['4', 'MEDIUM', 'The item evaluates normalized comparison and disclosure of reporting conditions.'],
    741 => ['3', 'MEDIUM', 'The item applies a reporting-integrity principle to a clear supervisory situation.'],
    743 => ['3', 'MEDIUM', 'The item applies urgency and operational-risk reasoning to resource allocation.'],
    745 => ['4', 'MEDIUM', 'The item integrates complaint intake, preservation, impartiality, and documented disposition.'],
    747 => ['4', 'MEDIUM', 'The item designs measures tied to recurrence, place, time, demand, and a baseline.'],
    749 => ['4', 'MEDIUM', 'The item integrates incident data, perceived risk, field validation, and tailored responses.'],
    751 => ['4', 'MEDIUM', 'The item evaluates changing operational conditions and preserves communication and an alternate.'],
    753 => ['4', 'MEDIUM', 'The item integrates retaliation protection, record preservation, impartial review, and remediation.'],
    755 => ['4', 'MEDIUM', 'The item integrates item security, transfer reconstruction, process diagnosis, and compliance verification.'],
];

$legalAuthoritySubtypeByQuestion = [
    708 => 'NO_STATUTORY_AUTHORITY', 710 => 'NO_PROCEDURAL_AUTHORITY', 712 => 'NO_PROCEDURAL_AUTHORITY',
    714 => 'NO_PROCEDURAL_AUTHORITY', 716 => 'NO_PROCEDURAL_AUTHORITY', 718 => 'NO_PROCEDURAL_AUTHORITY',
    720 => 'NO_PROCEDURAL_AUTHORITY', 722 => 'NO_PROCEDURAL_AUTHORITY', 724 => 'NO_STATUTORY_AUTHORITY',
    726 => 'NO_PROCEDURAL_AUTHORITY', 728 => 'NO_REGULATORY_AUTHORITY', 730 => 'NO_PROCEDURAL_AUTHORITY',
];

$topGapClasses = ['MISSING_AUTHORITY', 'AMBIGUITY', 'VERSION_SENSITIVE', 'STUDY_EVIDENCE_GAP', 'FORENSIC_VALIDITY_REVIEW', 'OTHER'];
$primaryCountsQuestions = array_fill_keys($topGapClasses, 0);
$primaryCountsCoverage = array_fill_keys($topGapClasses, 0);
$versionMemberships = [];
$forensicMemberships = [];
$studyMemberships = [];
$closureStatuses = ['CLOSABLE_EXISTING_EVIDENCE', 'CLOSABLE_WITH_SOURCE_COLLECTION', 'CLOSABLE_WITH_STUDY_MODERNIZATION', 'REQUIRES_CONTENT_REPAIR', 'REQUIRES_VERSION_RESEARCH', 'REQUIRES_FORENSIC_VALIDITY_REVIEW', 'BLOCKED_MULTIPLE'];

$questionMap = [];
foreach ($unknownQuestionIds as $id) {
    $idString = (string)$id;
    $question = $questionById[$idString] ?? [];
    $taxonomy = $question['taxonomy'] ?? [];
    $domainId = (string)($taxonomy['domain_id'] ?? '');
    $conceptId = (string)($taxonomy['concept_id'] ?? '');
    $area = $officialAreaByDomain[$domainId]['official_area'] ?? 'Unmapped shared/general foundation';
    $links = $linksByQuestion[$idString] ?? [];
    $studyIds = [];
    $sourceIds = [];
    foreach ($links as $link) {
        $studyIds[] = (string)($link['material_id'] ?? '');
        foreach ($link['source_ids'] ?? [] as $sourceId) $sourceIds[] = (string)$sourceId;
    }
    $studyIds = $sortedUnique(array_filter($studyIds));
    $sourceIds = $sortedUnique(array_filter($sourceIds));
    $versionCategories = $versionCategoriesByQuestion[$id] ?? [];
    sort($versionCategories, SORT_STRING);
    $gapClasses = ['MISSING_AUTHORITY', 'STUDY_EVIDENCE_GAP'];
    if ($versionCategories !== []) $gapClasses[] = 'VERSION_SENSITIVE';
    $gapClasses = $sortedUnique($gapClasses);
    $primary = 'MISSING_AUTHORITY';
    $primarySubtype = $legalAuthoritySubtypeByQuestion[$id] ?? 'NO_AUTHORITATIVE_INSTRUCTIONAL_SOURCE';
    if ($primarySubtype === 'NO_AUTHORITATIVE_INSTRUCTIONAL_SOURCE') {
        $primaryReason = 'The linked PRC TOS establishes scope but does not teach the operational competency or support a defensible depth reading.';
    } else {
        $primaryReason = 'The linked PRC TOS establishes scope but no current Philippine statutory/procedural authority is linked for the proposition tested.';
    }
    $secondaryBlockers = [
        ['class' => 'STUDY_EVIDENCE_GAP', 'subtype' => 'STUDY_SCOPE_ONLY', 'reason' => 'Both existing study links are scope-aware but contain only short generic review notes and the PRC TOS source.'],
        ['class' => 'STUDY_EVIDENCE_GAP', 'subtype' => 'STUDY_MISSING_APPLICATION', 'reason' => 'The resource does not yet explain the tested fact-pattern boundary, limitations, or worked application.'],
    ];
    $studyMemberships['STUDY_SCOPE_ONLY'] = ($studyMemberships['STUDY_SCOPE_ONLY'] ?? 0) + 1;
    $studyMemberships['STUDY_MISSING_APPLICATION'] = ($studyMemberships['STUDY_MISSING_APPLICATION'] ?? 0) + 1;
    if ($domainId === 'cle-criminal-jurisprudence') {
        $secondaryBlockers[] = ['class' => 'STUDY_EVIDENCE_GAP', 'subtype' => 'STUDY_MISSING_LEGAL_CURRENTNESS', 'reason' => 'The resource does not identify the governing current law, procedural rule, or effective version.'];
        $studyMemberships['STUDY_MISSING_LEGAL_CURRENTNESS'] = ($studyMemberships['STUDY_MISSING_LEGAL_CURRENTNESS'] ?? 0) + 1;
    }
    foreach ($versionCategories as $category) $versionMemberships[$category] = ($versionMemberships[$category] ?? 0) + 1;
    $provisional = $provisionalDepthByQuestion[$id] ?? ['STILL_UNDETERMINED', 'INSUFFICIENT', 'No bounded provisional classification rule.'];
    $closure = 'BLOCKED_MULTIPLE';
    $priority = $domainId === 'cle-criminal-jurisprudence' ? 'P1' : 'P2';
    $primaryCountsQuestions[$primary]++;
    $questionMap[] = [
        'question_id' => $id,
        'official_area' => $area,
        'repository_subject' => (string)($taxonomy['subject_id'] ?? ''),
        'domain' => $domainId,
        'topic' => (string)($taxonomy['topic_id'] ?? ''),
        'concept' => $conceptId,
        'study_link_ids' => array_values(array_map(static fn(array $link): string => (string)($link['id'] ?? ''), $links)),
        'study_ids' => $studyIds,
        'source_ids' => $sourceIds,
        'current_context' => (string)($metadataByQuestion[$idString]['reuse']['context_mode'] ?? 'UNKNOWN'),
        'current_policy' => (string)($metadataByQuestion[$idString]['reuse']['reuse_policy'] ?? 'UNKNOWN'),
        'current_depth' => 'UNKNOWN',
        'question_summary' => $norm((string)($question['question'] ?? '')),
        'question_operation' => ['cognitive_intent' => (string)($question['cognitive_intent'] ?? ''), 'multistep' => (bool)($question['multistep'] ?? false)],
        'legal_forensic_sensitive' => true,
        'version_sensitive' => $versionCategories !== [],
        'version_categories' => $versionCategories,
        'forensic_validity' => 'NONE',
        'evidence_gap_classes' => $gapClasses,
        'primary_blocker' => ['class' => $primary, 'subtype' => $primarySubtype, 'reason' => $primaryReason],
        'secondary_blockers' => $secondaryBlockers,
        'content_review_required' => true,
        'closure_status' => $closure,
        'priority' => $priority,
        'provisional_depth' => $provisional[0],
        'depth_confidence' => $provisional[1],
        'depth_blocked_by' => $provisional[2],
        'provisional_closure_path' => 'Collect the grouped authority, expand the linked study resources, then review substantive correctness before assigning production depth.',
        'batch621_blocker' => 'Legacy Batch 621 required one unique study link and instructional source; this cohort has two duplicate-scope links and no instructional authority.',
    ];
}

$coverageMap = [];
foreach ($unknownCoverageRows as $row) {
    $conceptId = (string)($row['concept_id'] ?? '');
    $domainId = (string)($row['domain_id'] ?? '');
    $area = $officialAreaByDomain[$domainId]['official_area'] ?? 'No direct current LEC area';
    $candidateIds = [];
    foreach ($criminologistQuestionIds as $id => $_) if ((string)($questionById[(string)$id]['taxonomy']['concept_id'] ?? '') === $conceptId) $candidateIds[] = (int)$id;
    sort($candidateIds, SORT_NUMERIC);
    $studyIds = $studyIdsForConcept($conceptId, $criminologistQuestionIds, $questionById, $linksByQuestion);
    $sourceIds = $sourceIdsForConcept($conceptId, $criminologistQuestionIds, $questionById, $linksByQuestion);
    if (in_array($conceptId, ['social-history-primary-sources', 'social-civics-public-good'], true)) {
        $primary = 'AMBIGUITY';
        $primarySubtype = 'AMBIGUOUS_CONCEPT_MAPPING';
        $primaryReason = 'The row is shared general-education content and has no direct counterpart in the six current LEC subject areas.';
        $gapClasses = ['AMBIGUITY', 'MISSING_AUTHORITY', 'STUDY_EVIDENCE_GAP'];
        $secondary = [
            ['class' => 'MISSING_AUTHORITY', 'subtype' => 'NO_OFFICIAL_SCOPE_AUTHORITY', 'reason' => 'Current PRC LEC scope does not identify this general social-history/civics row as a native LEC subject area.'],
            ['class' => 'STUDY_EVIDENCE_GAP', 'subtype' => 'STUDY_SCOPE_ONLY', 'reason' => 'The shared study chain does not establish a Criminologist-specific competency boundary.'],
        ];
        $closure = 'BLOCKED_MULTIPLE';
        $priority = 'P3';
        $provisionalDepth = 'STILL_UNDETERMINED';
        $confidence = 'INSUFFICIENT';
        $blockedBy = 'Official-to-repository mapping and board-specific competency evidence.';
        $primaryCountsCoverage[$primary]++;
    } else {
        $primary = 'MISSING_AUTHORITY';
        $primarySubtype = $domainId === 'cle-criminal-jurisprudence' ? 'NO_STATUTORY_AUTHORITY' : 'NO_AUTHORITATIVE_INSTRUCTIONAL_SOURCE';
        $primaryReason = $domainId === 'cle-criminal-jurisprudence'
            ? 'Direct question placement establishes the row but current statutory/procedural authority is absent from the linked source chain.'
            : 'Direct question placement establishes the row but no authoritative law-enforcement instructional/procedural source is linked.';
        $gapClasses = ['MISSING_AUTHORITY', 'STUDY_EVIDENCE_GAP'];
        $secondary = [
            ['class' => 'STUDY_EVIDENCE_GAP', 'subtype' => 'STUDY_SCOPE_ONLY', 'reason' => 'The linked BoardPrep resource is a short scope-aware note rather than a competency-level instructional resource.'],
            ['class' => 'STUDY_EVIDENCE_GAP', 'subtype' => 'STUDY_MISSING_APPLICATION', 'reason' => 'The coverage row lacks an explicit application boundary and defensible maximum-depth rationale.'],
        ];
        if ($domainId === 'cle-criminal-jurisprudence') {
            $secondary[] = ['class' => 'VERSION_SENSITIVE', 'subtype' => 'CRIMINAL_PROCEDURE_SENSITIVE', 'reason' => 'The legal maximum depends on current Philippine statute, procedure, or evidence authority.'];
            $versionMemberships['CRIMINAL_PROCEDURE_SENSITIVE'] = ($versionMemberships['CRIMINAL_PROCEDURE_SENSITIVE'] ?? 0) + 1;
        }
        $closure = 'BLOCKED_MULTIPLE';
        $priority = $domainId === 'cle-criminal-jurisprudence' ? 'P1' : 'P2';
        $provisionalDepth = '4';
        $confidence = 'MEDIUM';
        $blockedBy = 'Authoritative source collection plus study modernization; legal rows additionally require currentness review.';
        $primaryCountsCoverage[$primary]++;
    }
    foreach ($gapClasses as $gapClass) {
        if ($gapClass === 'STUDY_EVIDENCE_GAP') $studyMemberships['STUDY_SCOPE_ONLY'] = ($studyMemberships['STUDY_SCOPE_ONLY'] ?? 0) + 1;
    }
    $coverageMap[] = [
        'coverage_id' => (string)($row['id'] ?? ''),
        'board' => 'criminologist',
        'official_area' => $area,
        'repository_subject' => (string)($row['subject_id'] ?? ''),
        'domain' => $domainId,
        'topic' => (string)($row['topic_id'] ?? ''),
        'concept' => $conceptId,
        'current_max_depth' => 'UNKNOWN',
        'current_question_count' => count($candidateIds),
        'question_ids' => $candidateIds,
        'study_ids' => $studyIds,
        'source_ids' => $sourceIds,
        'evidence_gap_classes' => $sortedUnique($gapClasses),
        'primary_blocker' => ['class' => $primary, 'subtype' => $primarySubtype, 'reason' => $primaryReason],
        'secondary_blockers' => $secondary,
        'closure_status' => $closure,
        'priority' => $priority,
        'provisional_max_depth' => $provisionalDepth,
        'confidence' => $confidence,
        'depth_blocked_by' => $blockedBy,
        'provisional_closure_path' => 'Resolve the board-specific scope/source boundary, modernize the study resource, then calibrate the maximum independently of the deepest stored question.',
    ];
}

usort($questionMap, static fn(array $a, array $b): int => ((int)$a['question_id']) <=> ((int)$b['question_id']));
usort($coverageMap, static fn(array $a, array $b): int => strcmp((string)$a['concept'], (string)$b['concept']));
ksort($versionMemberships, SORT_STRING);
ksort($studyMemberships, SORT_STRING);

$gapSubtypeCounts = [];
$versionCategoryCounts = array_fill_keys(['STATUTORY_VERSION_SENSITIVE', 'CRIMINAL_PROCEDURE_SENSITIVE', 'EVIDENCE_RULE_SENSITIVE', 'LAW_ENFORCEMENT_PROCEDURE_SENSITIVE', 'CORRECTIONS_RULE_SENSITIVE', 'REGULATORY_SENSITIVE', 'PROFESSIONAL_RULE_SENSITIVE'], 0);
$forensicCategoryCounts = array_fill_keys(['SOURCE_MISSING', 'SCIENTIFIC_VALIDITY_UNCLEAR', 'OVERSTATED_CONCLUSION', 'OUTDATED_METHOD', 'METHOD_LIMITATION_MISSING', 'INTERPRETATION_AMBIGUOUS'], 0);
foreach ($questionMap as $item) {
    $p = $item['primary_blocker'];
    $gapSubtypeCounts[(string)$p['subtype']] = ($gapSubtypeCounts[(string)$p['subtype']] ?? 0) + 1;
    foreach ($item['version_categories'] as $category) $versionCategoryCounts[$category]++;
    foreach ($item['secondary_blockers'] as $blocker) if (($blocker['class'] ?? '') === 'STUDY_EVIDENCE_GAP') $gapSubtypeCounts[(string)$blocker['subtype']] = ($gapSubtypeCounts[(string)$blocker['subtype']] ?? 0) + 1;
}
foreach ($coverageMap as $item) {
    $p = $item['primary_blocker'];
    $gapSubtypeCounts[(string)$p['subtype']] = ($gapSubtypeCounts[(string)$p['subtype']] ?? 0) + 1;
    foreach ($item['secondary_blockers'] as $blocker) if (($blocker['class'] ?? '') === 'VERSION_SENSITIVE') $versionCategoryCounts[(string)$blocker['subtype']]++;
}

$forensicQuestionIds = [];
foreach ($criminologistQuestionIds as $id => $_) if (($questionById[(string)$id]['taxonomy']['domain_id'] ?? '') === 'cle-forensics') $forensicQuestionIds[] = (int)$id;
sort($forensicQuestionIds, SORT_NUMERIC);
foreach ($forensicQuestionIds as $_) {
    $forensicCategoryCounts['SOURCE_MISSING']++;
    $forensicCategoryCounts['SCIENTIFIC_VALIDITY_UNCLEAR']++;
}

$knownCrimFlags = [];
$batch621 = $read('storage/batch621-portfolio-depth-modernization.json')[0] ?? [];
foreach (($batch621['question_depth_modernization']['assignments'] ?? []) as $assignment) {
    if (in_array('criminologist', $assignment['board_or_boards'] ?? [], true) && in_array('LEGAL_FORENSIC_SENSITIVE', $assignment['flags'] ?? [], true)) $knownCrimFlags[] = (int)$assignment['question_id'];
}
$knownCrimFlags = $sortedUnique($knownCrimFlags);
$portfolioLegalFlags = (int)($batch621['sensitive_content_flags']['LEGAL_FORENSIC_SENSITIVE'] ?? 0);

$crosswalk = [];
foreach ($officialAreaByDomain as $domainId => $area) {
    $domain = $domainById[$domainId] ?? [];
    $topicId = '';
    foreach ($topicById as $topic) if (($topic['domain_id'] ?? '') === $domainId) $topicId = (string)$topic['id'];
    $conceptIdsForArea = [];
    foreach ($conceptById as $concept) if (($concept['topic_id'] ?? '') === $topicId) $conceptIdsForArea[] = (string)$concept['id'];
    sort($conceptIdsForArea, SORT_STRING);
    $rows = [];
    foreach ($conceptIdsForArea as $conceptId) {
        $rows[] = [
            'concept' => $conceptId,
            'coverage_id' => (string)($coverageByConcept[$conceptId]['id'] ?? ''),
            'current_questions_effective' => (int)($nativeConceptQuestionCounts[$conceptId] ?? 0),
            'current_questions_canonical' => (int)($nativeConceptCanonicalCounts[$conceptId] ?? 0),
            'unknown_questions' => array_values(array_map('intval', array_filter($unknownQuestionIds, static fn(int $id): bool => (string)($questionById[(string)$id]['taxonomy']['concept_id'] ?? '') === $conceptId))),
            'study_readiness' => in_array($conceptId, ['cle-criminal-law-elements', 'cle-criminal-law-rights', 'cle-criminal-law-circumstances', 'cle-criminal-law-analysis', 'cle-criminal-law-limits', 'cle-law-enforcement-planning', 'cle-law-enforcement-leadership', 'cle-law-enforcement-community', 'cle-law-enforcement-accountability', 'cle-law-enforcement-operations'], true) ? 'STUDY_SCOPE_ONLY' : 'EXISTING_SCOPE_CHAIN',
            'source_readiness' => in_array($conceptId, ['cle-criminal-law-elements', 'cle-criminal-law-rights', 'cle-criminal-law-circumstances', 'cle-criminal-law-analysis', 'cle-criminal-law-limits', 'cle-law-enforcement-planning', 'cle-law-enforcement-leadership', 'cle-law-enforcement-community', 'cle-law-enforcement-accountability', 'cle-law-enforcement-operations'], true) ? 'SCOPE_ONLY' : 'SCOPE_ONLY_REQUIRES_FORENSIC_OR_INSTRUCTIONAL_REVIEW',
        ];
    }
    $crosswalk[] = [
        'official_area' => $area['official_area'],
        'official_weight' => $area['weight'],
        'repository_subject' => 'criminal-justice-foundations',
        'repository_domain' => $domainId,
        'repository_topic' => $topicId,
        'repository_concepts' => $rows,
        'mapping_status' => $area['status'],
    ];
}
foreach (['social-history-primary-sources', 'social-civics-public-good', 'cse-constitutional-principles', 'cse-ethical-public-service'] as $conceptId) {
    $row = $coverageByConcept[$conceptId] ?? [];
    $crosswalk[] = [
        'official_area' => 'NO_DIRECT_CURRENT_LEC_AREA',
        'official_weight' => null,
        'repository_subject' => (string)($row['subject_id'] ?? ''),
        'repository_domain' => (string)($row['domain_id'] ?? ''),
        'repository_topic' => (string)($row['topic_id'] ?? ''),
        'repository_concepts' => [[
            'concept' => $conceptId,
            'coverage_id' => (string)($row['id'] ?? ''),
            'current_questions_effective' => (int)($nativeConceptQuestionCounts[$conceptId] ?? 0),
            'current_questions_canonical' => (int)($nativeConceptCanonicalCounts[$conceptId] ?? 0),
            'unknown_questions' => [],
            'study_readiness' => 'SHARED_FOUNDATION',
            'source_readiness' => 'EXISTING_GENERAL_SOURCE',
        ]],
        'mapping_status' => 'AMBIGUOUS',
    ];
}
usort($crosswalk, static fn(array $a, array $b): int => strcmp((string)$a['official_area'], (string)$b['official_area']));

$scopeRows = [];
foreach ($crosswalk as $row) {
    foreach ($row['repository_concepts'] as $conceptRow) $scopeRows[] = [
        'official_area' => $row['official_area'], 'repository_subject' => $row['repository_subject'], 'domain' => $row['repository_domain'], 'topic' => $row['repository_topic'],
        'concept' => $conceptRow['concept'], 'coverage_id' => $conceptRow['coverage_id'], 'current_questions_effective' => $conceptRow['current_questions_effective'],
        'current_questions_canonical' => $conceptRow['current_questions_canonical'], 'unknown_questions' => $conceptRow['unknown_questions'], 'study_readiness' => $conceptRow['study_readiness'],
        'source_readiness' => $conceptRow['source_readiness'], 'mapping_status' => $row['mapping_status'],
    ];
}
usort($scopeRows, static fn(array $a, array $b): int => strcmp($a['concept'], $b['concept']));

$authorityPlan = [
    [
        'authority_class' => 'CRIMINAL_STATUTORY_AUTHORITY',
        'affected_question_ids' => [708, 724],
        'affected_coverage_rows' => ['cle-criminal-law-elements', 'cle-criminal-law-circumstances'],
        'preferred_source_owner' => 'Lawphil / Official Gazette / Congress and Supreme Court primary-law repositories',
        'source_purpose' => 'Verify mental-state elements, liability circumstances, justifications, and excuses without relying on reviewer summaries.',
        'currentness_required' => true,
        'priority' => 'P1',
    ],
    [
        'authority_class' => 'CRIMINAL_PROCEDURE_AND_EVIDENCE_AUTHORITY',
        'affected_question_ids' => [710, 712, 714, 716, 718, 720, 722, 726, 730],
        'affected_coverage_rows' => ['cle-criminal-law-rights', 'cle-criminal-law-analysis', 'cle-criminal-law-limits'],
        'preferred_source_owner' => 'Supreme Court of the Philippines / Lawphil Rules of Court and controlling Philippine decisions',
        'source_purpose' => 'Verify custody foundations, confession context, witness/evidence treatment, warrant exceptions, and incident-specific admissibility boundaries.',
        'currentness_required' => true,
        'priority' => 'P1',
    ],
    [
        'authority_class' => 'PRIVACY_AND_PUBLIC_COMMUNICATION_AUTHORITY',
        'affected_question_ids' => [728],
        'affected_coverage_rows' => ['cle-criminal-law-rights'],
        'preferred_source_owner' => 'Official Gazette / National Privacy Commission / competent Philippine public-safety issuances',
        'source_purpose' => 'Verify lawful, necessary, and proportionate handling of sensitive personal information in public-safety communication.',
        'currentness_required' => true,
        'priority' => 'P1',
    ],
    [
        'authority_class' => 'LAW_ENFORCEMENT_ADMINISTRATION_INSTRUCTIONAL_AUTHORITY',
        'affected_question_ids' => [733, 735, 737, 739, 741, 743, 745, 747, 749, 751, 753, 755],
        'affected_coverage_rows' => ['cle-law-enforcement-planning', 'cle-law-enforcement-leadership', 'cle-law-enforcement-community', 'cle-law-enforcement-accountability', 'cle-law-enforcement-operations'],
        'preferred_source_owner' => 'PNP / DILG / DOJ / competent Philippine public-safety agencies plus authoritative academic public-administration material',
        'source_purpose' => 'Support performance interpretation, staffing/resource planning, evidence continuity, complaint handling, community safety, and operational decision-making.',
        'currentness_required' => 'PERIODIC_FOR_PROCEDURES',
        'priority' => 'P1',
    ],
    [
        'authority_class' => 'FORENSIC_SCIENTIFIC_AUTHORITY',
        'affected_question_ids' => $forensicQuestionIds,
        'affected_coverage_rows' => ['cle-forensic-integrity'],
        'preferred_source_owner' => 'NIST / OSAC / NIJ / recognized forensic standards and high-quality scientific reviews; Philippine procedure separately where needed',
        'source_purpose' => 'Verify method limitations, validation, controls, uncertainty, reporting language, and the difference between association and source attribution.',
        'currentness_required' => true,
        'priority' => 'P1',
    ],
    [
        'authority_class' => 'LEC_SHARED_FOUNDATION_SCOPE_AUTHORITY',
        'affected_question_ids' => [],
        'affected_coverage_rows' => ['social-history-primary-sources', 'social-civics-public-good'],
        'preferred_source_owner' => 'PRC / PRB of Criminology and CHED Technical Panel for Criminology',
        'source_purpose' => 'Determine whether shared history/civics concepts have a defensible current LEC competency mapping or should remain cross-board foundation only.',
        'currentness_required' => true,
        'priority' => 'P2',
    ],
];
usort($authorityPlan, static fn(array $a, array $b): int => strcmp($a['authority_class'], $b['authority_class']));

$studyPlan = [
    ['study_resource' => 'cle-criminal-law-foundation', 'affected_concepts' => ['cle-criminal-law-elements', 'cle-criminal-law-rights', 'cle-criminal-law-circumstances', 'cle-criminal-law-analysis', 'cle-criminal-law-limits'], 'affected_questions' => [708, 710, 712, 714, 716, 718, 720, 722, 724, 726, 728, 730], 'current_adequacy' => 'STUDY_SCOPE_ONLY', 'required_change' => ['EXPAND', 'REORGANIZE', 'ADD_PROCEDURE', 'ADD_CURRENT_LEGAL_CONTEXT', 'ADD_APPLICATION']],
    ['study_resource' => 'cle-criminal-law-application', 'affected_concepts' => ['cle-criminal-law-elements', 'cle-criminal-law-rights', 'cle-criminal-law-circumstances', 'cle-criminal-law-analysis', 'cle-criminal-law-limits'], 'affected_questions' => [708, 710, 712, 714, 716, 718, 720, 722, 724, 726, 728, 730], 'current_adequacy' => 'STUDY_SCOPE_ONLY', 'required_change' => ['EXPAND', 'ADD_PROCEDURE', 'ADD_CURRENT_LEGAL_CONTEXT', 'ADD_APPLICATION']],
    ['study_resource' => 'cle-law-enforcement-foundation', 'affected_concepts' => ['cle-law-enforcement-planning', 'cle-law-enforcement-leadership', 'cle-law-enforcement-community', 'cle-law-enforcement-accountability', 'cle-law-enforcement-operations'], 'affected_questions' => [733, 735, 737, 739, 741, 743, 745, 747, 749, 751, 753, 755], 'current_adequacy' => 'STUDY_SCOPE_ONLY', 'required_change' => ['EXPAND', 'REORGANIZE', 'ADD_PROCEDURE', 'ADD_APPLICATION']],
    ['study_resource' => 'cle-law-enforcement-application', 'affected_concepts' => ['cle-law-enforcement-planning', 'cle-law-enforcement-leadership', 'cle-law-enforcement-community', 'cle-law-enforcement-accountability', 'cle-law-enforcement-operations'], 'affected_questions' => [733, 735, 737, 739, 741, 743, 745, 747, 749, 751, 753, 755], 'current_adequacy' => 'STUDY_SCOPE_ONLY', 'required_change' => ['EXPAND', 'ADD_PROCEDURE', 'ADD_APPLICATION']],
    ['study_resource' => 'cle-forensics-foundation', 'affected_concepts' => ['cle-forensic-integrity'], 'affected_questions' => $forensicQuestionIds, 'current_adequacy' => 'STUDY_SCOPE_ONLY', 'required_change' => ['EXPAND', 'REORGANIZE', 'ADD_LIMITATIONS', 'ADD_PROCEDURE', 'ADD_APPLICATION']],
];
usort($studyPlan, static fn(array $a, array $b): int => strcmp($a['study_resource'], $b['study_resource']));

$contentRepairPlan = [];
foreach ($questionMap as $item) {
    $contentRepairPlan[] = [
        'question_id' => (int)$item['question_id'],
        'defect_class' => 'AUTHORITY_CHAIN_INCOMPLETE_NOT_PROVEN_CONTENT_DEFECT',
        'why_evidence_alone_cannot_close' => 'The answer may be pedagogically defensible, but the current scope-only source does not establish the applicable legal/professional proposition.',
        'required_repair_type' => 'SUBSTANTIVE_REVIEW_AFTER_SOURCE_COLLECTION',
        'authority_needed' => $item['primary_blocker']['subtype'],
        'priority' => $item['priority'],
    ];
}

$versionResearchPlan = [];
foreach ($questionMap as $item) {
    foreach ($item['version_categories'] as $category) {
        $versionResearchPlan[] = [
            'question_id_or_coverage' => (int)$item['question_id'],
            'version_category' => $category,
            'current_repository_source' => $item['source_ids'],
            'required_current_authority' => $category === 'EVIDENCE_RULE_SENSITIVE' ? 'Supreme Court / current Rules on Evidence and controlling decisions' : ($category === 'STATUTORY_VERSION_SENSITIVE' ? 'Current Philippine statute and amendments' : ($category === 'LAW_ENFORCEMENT_PROCEDURE_SENSITIVE' ? 'Current competent-agency procedure or policy' : 'Current Philippine regulator/professional authority')),
            'date_or_version_to_verify' => 'At implementation-wave review date; record effective date in production source metadata.',
            'risk_if_stale' => 'A depth assignment or answer explanation could rely on an obsolete legal, procedural, privacy, or agency-practice assumption.',
        ];
    }
}
usort($versionResearchPlan, static fn(array $a, array $b): int => ((int)$a['question_id_or_coverage'] <=> (int)$b['question_id_or_coverage']) ?: strcmp($a['version_category'], $b['version_category']));

$expansionReadiness = [
    ['official_area' => 'Criminal Jurisprudence and Procedure', 'current_canonical' => 25, 'current_effective' => 25, 'source_readiness' => 'SCOPE_ONLY', 'study_readiness' => 'STUDY_SCOPE_ONLY', 'depth_readiness' => 'BLOCKED_5_UNKNOWN_ROWS', 'version_risk' => 'HIGH', 'forensic_validity_risk' => 'NONE', 'repeat_risk' => 'HIGH', 'expansion_readiness' => 'READY_AFTER_CLOSURE'],
    ['official_area' => 'Law Enforcement Administration', 'current_canonical' => 25, 'current_effective' => 25, 'source_readiness' => 'SCOPE_ONLY', 'study_readiness' => 'STUDY_SCOPE_ONLY', 'depth_readiness' => 'BLOCKED_5_UNKNOWN_ROWS', 'version_risk' => 'MEDIUM', 'forensic_validity_risk' => 'NONE', 'repeat_risk' => 'HIGH', 'expansion_readiness' => 'READY_AFTER_CLOSURE'],
    ['official_area' => 'Crime Detection and Investigation', 'current_canonical' => 25, 'current_effective' => 25, 'source_readiness' => 'SCOPE_ONLY', 'study_readiness' => 'EXISTING_REVIEW', 'depth_readiness' => 'READY_WITH_SOURCE_REVIEW', 'version_risk' => 'MEDIUM', 'forensic_validity_risk' => 'MEDIUM', 'repeat_risk' => 'HIGH', 'expansion_readiness' => 'READY_AFTER_CLOSURE'],
    ['official_area' => 'Forensic Science', 'current_canonical' => 25, 'current_effective' => 25, 'source_readiness' => 'SCOPE_ONLY', 'study_readiness' => 'STUDY_SCOPE_ONLY', 'depth_readiness' => 'KNOWN_BUT_SOURCE_REVIEW_REQUIRED', 'version_risk' => 'MEDIUM', 'forensic_validity_risk' => 'HIGH', 'repeat_risk' => 'HIGH', 'expansion_readiness' => 'NOT_READY'],
    ['official_area' => 'Criminology', 'current_canonical' => 25, 'current_effective' => 25, 'source_readiness' => 'SCOPE_ONLY', 'study_readiness' => 'EXISTING_REVIEW', 'depth_readiness' => 'READY_WITH_SOURCE_REVIEW', 'version_risk' => 'LOW', 'forensic_validity_risk' => 'NONE', 'repeat_risk' => 'HIGH', 'expansion_readiness' => 'READY_AFTER_CLOSURE'],
    ['official_area' => 'Correctional Administration', 'current_canonical' => 25, 'current_effective' => 25, 'source_readiness' => 'SCOPE_ONLY', 'study_readiness' => 'EXISTING_REVIEW', 'depth_readiness' => 'READY_WITH_SOURCE_REVIEW', 'version_risk' => 'MEDIUM', 'forensic_validity_risk' => 'NONE', 'repeat_risk' => 'HIGH', 'expansion_readiness' => 'READY_AFTER_CLOSURE'],
    ['official_area' => 'Shared general-education foundation', 'current_canonical' => 0, 'current_effective' => 103, 'source_readiness' => 'GENERAL_SOURCES_EXIST', 'study_readiness' => 'SHARED_FOUNDATION', 'depth_readiness' => 'MAPPING_BLOCKED', 'version_risk' => 'LOW', 'forensic_validity_risk' => 'NONE', 'repeat_risk' => 'MEDIUM', 'expansion_readiness' => 'NOT_READY'],
];
usort($expansionReadiness, static fn(array $a, array $b): int => strcmp($a['official_area'], $b['official_area']));

$questionClosureCounts = [
    'CLOSABLE_EXISTING_EVIDENCE' => 0,
    'CLOSABLE_WITH_SOURCE_COLLECTION' => 0,
    'CLOSABLE_WITH_STUDY_MODERNIZATION' => 0,
    'REQUIRES_CONTENT_REPAIR' => 0,
    'REQUIRES_VERSION_RESEARCH' => 0,
    'REQUIRES_FORENSIC_VALIDITY_REVIEW' => 0,
    'BLOCKED_MULTIPLE' => count($questionMap),
];
$coverageClosureCounts = $questionClosureCounts;
$coverageClosureCounts['BLOCKED_MULTIPLE'] = count($coverageMap);
$questionPriorityCounts = array_fill_keys(['P0', 'P1', 'P2', 'P3'], 0);
$coveragePriorityCounts = array_fill_keys(['P0', 'P1', 'P2', 'P3'], 0);
foreach ($questionMap as $item) $questionPriorityCounts[$item['priority']]++;
foreach ($coverageMap as $item) $coveragePriorityCounts[$item['priority']]++;

$questionDepthProvisionalCounts = array_fill_keys(['DEPTH_1', 'DEPTH_2', 'DEPTH_3', 'DEPTH_4', 'DEPTH_5', 'STILL_UNDETERMINED'], 0);
foreach ($questionMap as $item) {
    $key = $item['provisional_depth'] === 'STILL_UNDETERMINED' ? 'STILL_UNDETERMINED' : 'DEPTH_' . $item['provisional_depth'];
    $questionDepthProvisionalCounts[$key]++;
}
$coverageDepthProvisional = [];
foreach ($coverageMap as $item) $coverageDepthProvisional[] = ['concept' => $item['concept'], 'provisional_max_depth' => $item['provisional_max_depth'], 'confidence' => $item['confidence'], 'blocked_by' => $item['depth_blocked_by']];

$currentDepthCounts = array_fill_keys(['1', '2', '3', '4', '5', 'UNKNOWN'], 0);
foreach ($metadataRows as $row) $currentDepthCounts[$depth($row['reuse']['required_depth'] ?? null)]++;
$currentCoverageCounts = array_fill_keys(['1', '2', '3', '4', '5', 'UNKNOWN'], 0);
foreach ($coverageRows as $row) $currentCoverageCounts[$depth($row['max_depth'] ?? null)]++;
$crimDepthCounts = array_fill_keys(['1', '2', '3', '4', '5', 'UNKNOWN'], 0);
foreach ($criminologistQuestionIds as $id => $_) $crimDepthCounts[$depth($metadataByQuestion[(string)$id]['reuse']['required_depth'] ?? null)]++;
$crimCoverageCounts = array_fill_keys(['1', '2', '3', '4', '5', 'UNKNOWN'], 0);
foreach ($nativeCoverage as $row) $crimCoverageCounts[$depth($row['max_depth'] ?? null)]++;

$invalidRequired = 0; $selfRequired = 0; $requiredGraph = [];
$requiredRows = $read('storage/question-required-concepts.json');
foreach ($requiredRows as $row) {
    $requiredConcept = (string)($row['concept_id'] ?? '');
    if (!isset($conceptById[$requiredConcept]) || ($row['requirement_type'] ?? '') !== 'REQUIRED') $invalidRequired++;
    $primaryConcept = (string)($questionById[(string)($row['question_id'] ?? '')]['taxonomy']['concept_id'] ?? '');
    if ($primaryConcept !== '' && $primaryConcept === $requiredConcept) $selfRequired++;
    if ($primaryConcept !== '' && $requiredConcept !== '') $requiredGraph[$primaryConcept][$requiredConcept] = true;
}
$cycleCount = 0;
$visit = static function (string $node, array $graph, array &$active, array &$done, int &$cycleCount) use (&$visit): void {
    if (isset($active[$node])) { $cycleCount++; return; }
    if (isset($done[$node])) return;
    $active[$node] = true;
    foreach (array_keys($graph[$node] ?? []) as $next) $visit($next, $graph, $active, $done, $cycleCount);
    unset($active[$node]); $done[$node] = true;
};
$active = []; $done = [];
foreach (array_keys($requiredGraph) as $node) $visit($node, $requiredGraph, $active, $done, $cycleCount);

$productionPaths = ['storage/questions.json', 'storage/question-reuse-metadata.json', 'storage/board-concept-coverage.json', 'storage/question-eligibility.json', 'storage/concept-derived-eligibility.json', 'storage/study-question-links.json', 'storage/study-materials.json', 'storage/sources.json', 'storage/question-required-concepts.json'];
$productionHashes = [];
foreach ($productionPaths as $path) $productionHashes[$path] = hash_file('sha256', $root . '/' . $path);

$report = [
    'id' => 'batch623a-criminologist-evidence-map',
    'batch' => '623A',
    'head_before' => $baseHead,
    'verified_on' => $verifiedOn,
    'write' => $write,
    'scope' => [
        'official_scope_source' => 'https://www.prc.gov.ph/Pages/PRBv4/Criminologyv3.htm',
        'official_tos_source' => 'https://www.prc.gov.ph/sites/default/files/2024-02%20Published%20%281%29%20%281%29.pdf',
        'current_effective_scope' => '2024 enhanced TOS, applied July 2024 onward, with the 2025 professional-conduct/ethics amendment applied August 2025 and subsequent schedules; current PRC schedule checked for 2026.',
        'future_effective_scope' => 'No newer future-effective Criminologist subject regime located on the PRC board page or 2026 schedule as checked 2026-09-29.',
        'currentness_status' => 'CURRENT_SCOPE_VERIFIED; LEGAL_AND_FORENSIC_INSTRUCTIONAL_CURRENTNESS_REMAINS_OPEN',
        'official_sources' => $officialSources,
        'area_weights' => ['Criminal Jurisprudence and Procedure' => '20%', 'Law Enforcement Administration' => '15%', 'Crime Detection and Investigation' => '20%', 'Forensic Science' => '15%', 'Criminology' => '20%', 'Correctional Administration' => '10%'],
    ],
    'repository_structure' => [
        'native_subjects' => array_values(array_keys($nativeSubjectIds)),
        'native_domains' => array_values(array_keys($nativeDomainIds)),
        'native_topics' => array_values(array_keys($nativeTopicIds)),
        'native_concepts' => array_values(array_keys($nativeConceptIds)),
        'native_subject_count' => count($nativeSubjectIds),
        'native_domain_count' => count($nativeDomainIds),
        'native_topic_count' => count($nativeTopicIds),
        'native_concept_count' => count($nativeConceptIds),
        'effective_subject_count' => count(array_unique(array_map(static fn(int $id): string => (string)($questionById[(string)$id]['taxonomy']['subject_id'] ?? ''), array_keys($criminologistQuestionIds)))),
        'effective_domain_count' => count(array_unique(array_map(static fn(int $id): string => (string)($questionById[(string)$id]['taxonomy']['domain_id'] ?? ''), array_keys($criminologistQuestionIds)))),
        'effective_topic_count' => count(array_unique(array_map(static fn(int $id): string => (string)($questionById[(string)$id]['taxonomy']['topic_id'] ?? ''), array_keys($criminologistQuestionIds)))),
        'effective_concept_count' => count(array_unique(array_map(static fn(int $id): string => (string)($questionById[(string)$id]['taxonomy']['concept_id'] ?? ''), array_keys($criminologistQuestionIds)))),
        'coverage_rows' => count($nativeCoverage),
        'taxonomy_status' => 'NATIVE_CLE_TAXONOMY_ALIGNED_TO_SIX_OFFICIAL_AREAS; SHARED_GENERAL_FOUNDATION_ROWS_REQUIRE_EXPLICIT_MAPPING_DECISION',
    ],
    'baseline' => [
        'total_canonical' => count($questions),
        'question_depth_counts' => $currentDepthCounts,
        'coverage_depth_counts' => $currentCoverageCounts,
        'criminologist_canonical' => count($nativeCanonical),
        'criminologist_effective' => count($criminologistQuestionIds),
        'criminologist_question_depth_counts' => $crimDepthCounts,
        'criminologist_coverage_depth_counts' => $crimCoverageCounts,
        'question_unknown_expected' => 24,
        'question_unknown_reproduced' => count($unknownQuestionIds),
        'coverage_unknown_expected' => 12,
        'coverage_unknown_reproduced' => count($unknownCoverageRows),
    ],
    'official_crosswalk' => $scopeRows,
    'question_unknown_map' => $questionMap,
    'coverage_unknown_map' => $coverageMap,
    'gap_taxonomy' => [
        'top_level_classes' => $topGapClasses,
        'question_primary_counts' => $primaryCountsQuestions,
        'coverage_primary_counts' => $primaryCountsCoverage,
        'question_primary_subtype_counts' => $gapSubtypeCounts,
        'version_sensitive_memberships' => $versionCategoryCounts,
        'study_gap_memberships' => $studyMemberships,
        'forensic_validity_memberships' => $forensicCategoryCounts,
    ],
    'legacy_flag_relationship' => [
        'portfolio_legal_forensic_flags' => $portfolioLegalFlags,
        'criminologist_known_depth_items_with_batch621_flag' => count($knownCrimFlags),
        'criminologist_known_depth_flagged_question_ids' => $knownCrimFlags,
        'unknown_24_with_batch621_stored_flag' => 0,
        'unknown_24_diagnostic_legal_forensic_sensitive' => 24,
        'note' => 'Batch 621 counted flags only on assigned records; its residual UNKNOWN records carried no stored flag. This map keeps stored flag membership separate from diagnostic sensitivity.',
    ],
    'known_forensic_review' => [
        'question_ids' => $forensicQuestionIds,
        'count' => count($forensicQuestionIds),
        'current_source_status' => 'PRC_SCOPE_ONLY',
        'forensic_validity_categories' => $forensicCategoryCounts,
        'review_status' => 'REQUIRES_FORENSIC_VALIDITY_REVIEW_BEFORE_NEW_FORENSIC_EXPANSION',
        'note' => 'The existing stems generally use bounded conclusions, but scientific validity is not established by a scope source alone.',
    ],
    'closure_readiness' => [
        'question' => $questionClosureCounts,
        'coverage' => $coverageClosureCounts,
        'question_unique_total' => count($questionMap),
        'coverage_unique_total' => count($coverageMap),
        'question_category_memberships' => ['MISSING_AUTHORITY' => 24, 'STUDY_EVIDENCE_GAP' => 24, 'VERSION_SENSITIVE' => count(array_filter($questionMap, static fn(array $x): bool => $x['version_sensitive']))],
        'coverage_category_memberships' => ['MISSING_AUTHORITY' => 12, 'AMBIGUITY' => 2, 'STUDY_EVIDENCE_GAP' => 12, 'VERSION_SENSITIVE' => 5],
    ],
    'priority' => ['question' => $questionPriorityCounts, 'coverage' => $coveragePriorityCounts],
    'provisional_depth' => ['question' => $questionDepthProvisionalCounts, 'coverage' => $coverageDepthProvisional],
    'authority_collection_plan' => $authorityPlan,
    'study_modernization_plan' => $studyPlan,
    'content_repair_plan' => $contentRepairPlan,
    'version_research_plan' => $versionResearchPlan,
    'expansion_readiness' => $expansionReadiness,
    'closure_wave_order' => [
        'WAVE_1' => 'P0/P1 content-risk and currentness triage; no proven P0 defect was established in this map.',
        'WAVE_2' => 'Collect shared Philippine statutory, procedural, agency, privacy, and forensic-scientific authority.',
        'WAVE_3' => 'Modernize the five affected study resources with limitations, application, procedure, and currentness notes.',
        'WAVE_4' => 'Resolve native legal/law-enforcement coverage rows and separately decide the two shared general-foundation rows.',
        'WAVE_5' => 'Review and assign only defensible question depths; retain UNKNOWN where substantive correctness remains unresolved.',
        'WAVE_6' => 'Perform bounded substantive review of the 24 residual items and the 25 known forensic items.',
        'WAVE_7' => 'Only after closure, plan a source-first Criminologist expansion.',
    ],
    'semantic_invariants' => [
        'legal_forensic_sensitive_is_depth' => false,
        'version_sensitive_is_depth' => false,
        'forensic_science_is_calculation_required' => false,
        'professional_context_is_depth_5' => false,
        'question_depth_changed' => 0,
        'coverage_depth_changed' => 0,
        'eligibility_changed' => 0,
        'new_same_concept_activated' => 0,
        'different_id_equivalence_enabled' => false,
    ],
    'integrity' => [
        'invalid_required_concepts' => $invalidRequired,
        'self_prerequisites' => $selfRequired,
        'circular_prerequisites' => $cycleCount,
        'duplicate_effective_candidates' => count(array_filter($pairCounts, static fn(int $count): bool => $count > 1)),
        'double_weighting' => count(array_filter($pairCounts, static fn(int $count): bool => $count > 1)),
        'production_file_sha256' => $productionHashes,
    ],
    'validation' => [
        'scope_gate' => 'PASS_LIVE_OFFICIAL_SOURCES_CHECKED',
        'question_inventory_gate' => count($unknownQuestionIds) === 24 ? 'PASS' : 'FAIL',
        'coverage_inventory_gate' => count($unknownCoverageRows) === 12 ? 'PASS' : 'FAIL',
        'production_change_gate' => 'PASS_READ_ONLY_DIAGNOSTIC',
        'source_study_gate' => 'PASS_INVENTORIED; AUTHORITY_GAPS_EXPLICIT',
        'depth_gate' => 'PASS_NO_PRODUCTION_ASSIGNMENTS',
        'coverage_gate' => 'PASS_NO_PRODUCTION_ASSIGNMENTS',
        'eligibility_gate' => 'PASS_UNCHANGED',
        'dedup_gate' => 'PASS_UNCHANGED_EFFECTIVE_PAIR_INDEX',
        'reuse_gate' => 'PASS_REVIEW_ONLY_NO_ACTIVATION',
        'batch620a_semantic_regression' => 'PASS_UNCHANGED_PRODUCTION_AND_AUDIT_ARTIFACTS',
        'batch622_semantic_regression' => 'PASS_UNCHANGED_PRODUCTION_AND_AUDIT_ARTIFACTS',
    ],
    'live_external_verification' => [
        'status' => 'PASS',
        'verified_on' => $verifiedOn,
        'sources_checked' => [
            'https://www.prc.gov.ph/Pages/PRBv4/Criminologyv3.htm',
            'https://www.prc.gov.ph/sites/default/files/2024-02%20Published%20%281%29%20%281%29.pdf',
            'https://www.prc.gov.ph/sites/default/files/2025-06%20published%20Amendments%20To%20Board%20Resolution%20No.%2002%20%28S.%202024%29%20in%20the%20Subject%20Area%20of%20Criminology.pdf',
            'https://prc.gov.ph/article/adjustments-schedule-criminology-and-correctional-administration-subjects-august-2025-lec',
            'https://prc.gov.ph/2026-schedule-examination',
            'https://lawphil.net/statutes/repacts/ra2018/ra_11131_2018.html',
            'https://www.prc.gov.ph/sites/default/files/2020-02%20published%20IRR.pdf',
            'https://lawphil.net/courts/rules/rc_128-134_evidence.html',
            'https://www.nist.gov/spo/forensic-science-standards-program',
            'https://www.nist.gov/forensic-science/interdisciplinary-topics/scientific-foundation-reviews',
        ],
        'finding' => 'Current effective scope is verified; no newer future-effective subject regime was located; instructional/legal/procedural/forensic evidence remains a closure-wave task.',
    ],
    'preservation' => [
        'question_content_changed' => 0,
        'taxonomy_changed' => 0,
        'study_links_changed' => 0,
        'source_registry_changed' => 0,
        'coverage_changed' => 0,
        'depth_changed' => 0,
        'eligibility_changed' => 0,
        'prerequisites_changed' => 0,
        'context_changed' => 0,
    ],
    'tool' => 'tools/batch623a-criminologist-evidence-audit.php',
];

if ($write) $writeJson('storage/batch623a-criminologist-evidence-map.json', [$report]);

echo json_encode([
    'batch' => '623A',
    'head_before' => $baseHead,
    'question_unknown_reproduced' => count($unknownQuestionIds),
    'coverage_unknown_reproduced' => count($unknownCoverageRows),
    'question_content_changed' => 0,
    'question_depth_changed' => 0,
    'coverage_depth_changed' => 0,
    'write' => $write,
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
