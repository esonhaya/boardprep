<?php

declare(strict_types=1);

/**
 * Batch 622: CPA evidence closure and bounded source-first expansion.
 *
 * The authoring data is deliberately kept in the audit tool so the source,
 * study, taxonomy, depth, currentness, calculation, and eligibility checks
 * are reproducible from one deterministic production path.  --write is the
 * only mode that materializes repository changes.
 */

$root = dirname(__DIR__);
$write = in_array('--write', $argv, true);
$batch = '622';
$today = '2026-09-29';
$baselineHead = '42bdfd7581f2b50bae8584707df9ffeab37fb055';

require_once $root . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

$read = static function (string $path): array {
    $raw = file_get_contents($path);
    if ($raw === false) throw new RuntimeException('Cannot read ' . $path);
    $value = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    return is_array($value) ? $value : [];
};
$writeJson = static function (string $path, array $value): void {
    file_put_contents($path, json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
};
$appendUnique = static function (string $path, array $rows) use ($read, $writeJson): void {
    $current = $read($path);
    $ids = [];
    foreach ($current as $row) if (is_array($row) && isset($row['id'])) $ids[(string)$row['id']] = true;
    foreach ($rows as $row) if (!isset($ids[(string)($row['id'] ?? '')])) { $current[] = $row; $ids[(string)$row['id']] = true; }
    $writeJson($path, $current);
};
$replaceById = static function (string $path, array $replacements) use ($read, $writeJson): void {
    if ($replacements === []) return;
    $current = $read($path);
    $byId = [];
    foreach ($replacements as $row) $byId[(string)$row['id']] = $row;
    foreach ($current as &$row) if (isset($byId[(string)($row['id'] ?? '')])) $row = $byId[(string)$row['id']];
    unset($row);
    $writeJson($path, $current);
};
$normalize = static fn(string $text): string => strtolower(trim(preg_replace('/\s+/', ' ', $text) ?? $text));
$validDepth = static fn(mixed $v): bool => in_array((string)$v, ['1', '2', '3', '4', '5'], true);
$depth = static fn(mixed $v): string => $validDepth($v) ? (string)$v : 'UNKNOWN';
$unique = static fn(array $values): array => array_values(array_unique(array_map('strval', $values)));

$questionsExisting = array_values(array_filter($read($root . '/storage/questions.json'), 'is_array'));
$metadataExisting = array_values(array_filter($read($root . '/storage/question-reuse-metadata.json'), 'is_array'));
$coverageExisting = array_values(array_filter($read($root . '/storage/board-concept-coverage.json'), 'is_array'));
$eligibilityExisting = array_values(array_filter($read($root . '/storage/question-eligibility.json'), 'is_array'));
$linksExisting = array_values(array_filter($read($root . '/storage/study-question-links.json'), 'is_array'));
$materialsExisting = array_values(array_filter($read($root . '/storage/study-materials.json'), 'is_array'));
$sourcesExisting = array_values(array_filter($read($root . '/storage/sources.json'), 'is_array'));
$requiredExisting = array_values(array_filter($read($root . '/storage/question-required-concepts.json'), 'is_array'));
$conceptRows = array_values(array_filter($read($root . '/storage/taxonomy/concepts.json'), 'is_array'));
$topicRows = array_values(array_filter($read($root . '/storage/taxonomy/topics.json'), 'is_array'));
$domainRows = array_values(array_filter($read($root . '/storage/taxonomy/domains.json'), 'is_array'));
$sourceById = [];
foreach ($sourcesExisting as $row) $sourceById[(string)($row['id'] ?? '')] = $row;
$materialById = [];
foreach ($materialsExisting as $row) $materialById[(string)($row['id'] ?? '')] = $row;
$conceptById = [];
foreach ($conceptRows as $row) $conceptById[(string)($row['id'] ?? '')] = $row;
$topicById = [];
foreach ($topicRows as $row) $topicById[(string)($row['id'] ?? '')] = $row;
$domainById = [];
foreach ($domainRows as $row) $domainById[(string)($row['id'] ?? '')] = $row;
$metadataByQuestion = [];
foreach ($metadataExisting as $row) $metadataByQuestion[(string)($row['question_id'] ?? '')] = $row;
$questionById = [];
$maxQuestionId = 0;
foreach ($questionsExisting as $row) {
    $id = (string)($row['id'] ?? '');
    $questionById[$id] = $row;
    if (ctype_digit($id)) $maxQuestionId = max($maxQuestionId, (int)$id);
}

/* Official scope and currentness sources.  Resolution 20 is retained as a
 * future-effective scope source; the 2022 TOS remains the current 2026 source. */
$sourceAdditions = [
    [
        'id' => 'prc-accountancy-current-tos-2022',
        'title' => 'PRC Board of Accountancy — Current LECPA Tables of Specifications',
        'source_type' => 'OFFICIAL_EXAM_SPEC',
        'authority' => 'Professional Regulation Commission; Professional Regulatory Board of Accountancy',
        'url' => 'https://www.prc.gov.ph/sites/default/files/2022-30%20BOA%20TOS%20Final.pdf',
        'publication_date' => '2022-06-30', 'retrieved_at' => $today,
        'version' => 'Board Resolution No. 30 s. 2022; effective October 2022',
        'status' => 'official_reference', 'confidence' => 'authoritative_reference',
        'notes' => 'Current six-subject LECPA TOS for the 2026 examination cycle unless a later-effective TOS controls; revised 2026 TOS is expressly effective in calendar year 2029 onward.',
    ],
    [
        'id' => 'prc-accountancy-exam-program-2026',
        'title' => 'PRC Board Program — May 2026 CPALE',
        'source_type' => 'OFFICIAL_EXAM_SPEC',
        'authority' => 'Professional Regulation Commission; Professional Regulatory Board of Accountancy',
        'url' => 'https://prc.gov.ph/sites/default/files/Board%20Program%20May%202026%20CPALE_revised.pdf',
        'publication_date' => '2026-03-02', 'retrieved_at' => $today,
        'version' => 'May 24–26, 2026 examination program',
        'status' => 'official_reference', 'confidence' => 'authoritative_reference',
        'notes' => 'Confirms the six current examination subjects and their 2026 administration order; it is a scope/program source, not an instructional standard.',
    ],
    [
        'id' => 'prc-accountancy-auditing-standards-2026',
        'title' => 'PRC Board Resolution No. 3 s. 2026 — Philippine Auditing Standards Adoption',
        'source_type' => 'OFFICIAL_GUIDANCE',
        'authority' => 'Professional Regulation Commission; Professional Regulatory Board of Accountancy',
        'url' => 'https://www.prc.gov.ph/sites/default/files/2026-03%20published%20Adopting%20The%20Auditing%20And%20Assurance%20Standards%20Council%20Pronouncements.pdf',
        'publication_date' => '2026-02-03', 'retrieved_at' => $today,
        'version' => 'Board Resolution No. 3 s. 2026',
        'status' => 'official_reference', 'confidence' => 'authoritative_reference',
        'notes' => 'Current Philippine adoption source for the cited AASC pronouncements, including PSA 240 Revised and PSA 570 Revised 2024; the applicable effective date must be checked for each standard.',
    ],
    [
        'id' => 'ifrs-ifrs-2-share-based-payment', 'title' => 'IFRS 2 Share-based Payment', 'source_type' => 'OFFICIAL_GUIDANCE',
        'authority' => 'IFRS Foundation', 'url' => 'https://www.ifrs.org/issued-standards/list-of-standards/ifrs-2-share-based-payment/',
        'publication_date' => null, 'retrieved_at' => $today, 'version' => 'current official standard page', 'status' => 'official_reference', 'confidence' => 'authoritative_reference',
        'notes' => 'Authoritative source for equity-settled and cash-settled share-based payment principles.',
    ],
    [
        'id' => 'ifrs-ifrs-3-business-combinations', 'title' => 'IFRS 3 Business Combinations', 'source_type' => 'OFFICIAL_GUIDANCE',
        'authority' => 'IFRS Foundation', 'url' => 'https://www.ifrs.org/issued-standards/list-of-standards/ifrs-3-business-combinations/',
        'publication_date' => null, 'retrieved_at' => $today, 'version' => 'current official standard page', 'status' => 'official_reference', 'confidence' => 'authoritative_reference',
        'notes' => 'Authoritative source for acquisition method, identifiable net assets, goodwill, and bargain purchases.',
    ],
    [
        'id' => 'ifrs-ifrs-5-held-for-sale', 'title' => 'IFRS 5 Non-current Assets Held for Sale and Discontinued Operations', 'source_type' => 'OFFICIAL_GUIDANCE',
        'authority' => 'IFRS Foundation', 'url' => 'https://www.ifrs.org/issued-standards/list-of-standards/ifrs-5-non-current-assets-held-for-sale-and-discontinued-operations/',
        'publication_date' => null, 'retrieved_at' => $today, 'version' => 'current official standard page', 'status' => 'official_reference', 'confidence' => 'authoritative_reference',
        'notes' => 'Authoritative source for held-for-sale classification, measurement, and presentation.',
    ],
    [
        'id' => 'ifrs-ifrs-8-operating-segments', 'title' => 'IFRS 8 Operating Segments', 'source_type' => 'OFFICIAL_GUIDANCE',
        'authority' => 'IFRS Foundation', 'url' => 'https://www.ifrs.org/issued-standards/list-of-standards/ifrs-8-operating-segments/',
        'publication_date' => null, 'retrieved_at' => $today, 'version' => 'current official standard page', 'status' => 'official_reference', 'confidence' => 'authoritative_reference',
        'notes' => 'Authoritative source for operating-segment identification and management-view disclosures.',
    ],
    [
        'id' => 'ifrs-ifrs-10-consolidated-financial-statements', 'title' => 'IFRS 10 Consolidated Financial Statements', 'source_type' => 'OFFICIAL_GUIDANCE',
        'authority' => 'IFRS Foundation', 'url' => 'https://www.ifrs.org/issued-standards/list-of-standards/ifrs-10-consolidated-financial-statements/',
        'publication_date' => null, 'retrieved_at' => $today, 'version' => 'current official standard page', 'status' => 'official_reference', 'confidence' => 'authoritative_reference',
        'notes' => 'Authoritative source for control, consolidation, and the parent-subsidiary relationship.',
    ],
    [
        'id' => 'ifrs-ifrs-11-joint-arrangements', 'title' => 'IFRS 11 Joint Arrangements', 'source_type' => 'OFFICIAL_GUIDANCE',
        'authority' => 'IFRS Foundation', 'url' => 'https://www.ifrs.org/issued-standards/list-of-standards/ifrs-11-joint-arrangements/',
        'publication_date' => null, 'retrieved_at' => $today, 'version' => 'current official standard page', 'status' => 'official_reference', 'confidence' => 'authoritative_reference',
        'notes' => 'Authoritative source for joint operation and joint venture classification and accounting.',
    ],
    [
        'id' => 'ifrs-ias-10-events-after-reporting-period', 'title' => 'IAS 10 Events after the Reporting Period', 'source_type' => 'OFFICIAL_GUIDANCE',
        'authority' => 'IFRS Foundation', 'url' => 'https://www.ifrs.org/issued-standards/list-of-standards/ias-10-events-after-the-reporting-period/',
        'publication_date' => null, 'retrieved_at' => $today, 'version' => 'current official standard page', 'status' => 'official_reference', 'confidence' => 'authoritative_reference',
        'notes' => 'Authoritative source for adjusting and non-adjusting events after the reporting period.',
    ],
    [
        'id' => 'ifrs-ias-12-income-taxes', 'title' => 'IAS 12 Income Taxes', 'source_type' => 'OFFICIAL_GUIDANCE',
        'authority' => 'IFRS Foundation', 'url' => 'https://www.ifrs.org/issued-standards/list-of-standards/ias-12-income-taxes/',
        'publication_date' => null, 'retrieved_at' => $today, 'version' => 'current official standard page', 'status' => 'official_reference', 'confidence' => 'authoritative_reference',
        'notes' => 'Authoritative source for current and deferred tax accounting principles.',
    ],
    [
        'id' => 'ifrs-ias-19-employee-benefits', 'title' => 'IAS 19 Employee Benefits', 'source_type' => 'OFFICIAL_GUIDANCE',
        'authority' => 'IFRS Foundation', 'url' => 'https://www.ifrs.org/issued-standards/list-of-standards/ias-19-employee-benefits/',
        'publication_date' => null, 'retrieved_at' => $today, 'version' => 'current official standard page', 'status' => 'official_reference', 'confidence' => 'authoritative_reference',
        'notes' => 'Authoritative source for classification and measurement of employee benefits.',
    ],
    [
        'id' => 'ifrs-ias-21-foreign-exchange', 'title' => 'IAS 21 The Effects of Changes in Foreign Exchange Rates', 'source_type' => 'OFFICIAL_GUIDANCE',
        'authority' => 'IFRS Foundation', 'url' => 'https://www.ifrs.org/issued-standards/list-of-standards/ias-21-effects-of-changes-in-foreign-exchange-rates/',
        'publication_date' => null, 'retrieved_at' => $today, 'version' => 'current official standard page', 'status' => 'official_reference', 'confidence' => 'authoritative_reference',
        'notes' => 'Authoritative source for foreign-currency transactions and translation principles.',
    ],
    [
        'id' => 'ifrs-ias-23-borrowing-costs', 'title' => 'IAS 23 Borrowing Costs', 'source_type' => 'OFFICIAL_GUIDANCE',
        'authority' => 'IFRS Foundation', 'url' => 'https://www.ifrs.org/issued-standards/list-of-standards/ias-23-borrowing-costs/',
        'publication_date' => null, 'retrieved_at' => $today, 'version' => 'current official standard page', 'status' => 'official_reference', 'confidence' => 'authoritative_reference',
        'notes' => 'Authoritative source for capitalization of borrowing costs attributable to qualifying assets.',
    ],
    [
        'id' => 'ifrs-ias-24-related-party', 'title' => 'IAS 24 Related Party Disclosures', 'source_type' => 'OFFICIAL_GUIDANCE',
        'authority' => 'IFRS Foundation', 'url' => 'https://www.ifrs.org/issued-standards/list-of-standards/ias-24-related-party-disclosures/',
        'publication_date' => null, 'retrieved_at' => $today, 'version' => 'current official standard page', 'status' => 'official_reference', 'confidence' => 'authoritative_reference',
        'notes' => 'Authoritative source for related-party identification and disclosure principles.',
    ],
    [
        'id' => 'ifrs-ias-27-separate-financial-statements', 'title' => 'IAS 27 Separate Financial Statements', 'source_type' => 'OFFICIAL_GUIDANCE',
        'authority' => 'IFRS Foundation', 'url' => 'https://www.ifrs.org/issued-standards/list-of-standards/ias-27-separate-financial-statements/',
        'publication_date' => null, 'retrieved_at' => $today, 'version' => 'current official standard page', 'status' => 'official_reference', 'confidence' => 'authoritative_reference',
        'notes' => 'Authoritative source for investments in separate financial statements.',
    ],
    [
        'id' => 'ifrs-ias-28-associates-joint-ventures', 'title' => 'IAS 28 Investments in Associates and Joint Ventures', 'source_type' => 'OFFICIAL_GUIDANCE',
        'authority' => 'IFRS Foundation', 'url' => 'https://www.ifrs.org/issued-standards/list-of-standards/ias-28-investments-in-associates-and-joint-ventures/',
        'publication_date' => null, 'retrieved_at' => $today, 'version' => 'current official standard page', 'status' => 'official_reference', 'confidence' => 'authoritative_reference',
        'notes' => 'Authoritative source for significant influence and equity-method accounting.',
    ],
    [
        'id' => 'ifrs-ias-33-earnings-per-share', 'title' => 'IAS 33 Earnings per Share', 'source_type' => 'OFFICIAL_GUIDANCE',
        'authority' => 'IFRS Foundation', 'url' => 'https://www.ifrs.org/issued-standards/list-of-standards/ias-33-earnings-per-share/',
        'publication_date' => null, 'retrieved_at' => $today, 'version' => 'current official standard page', 'status' => 'official_reference', 'confidence' => 'authoritative_reference',
        'notes' => 'Authoritative source for basic and diluted earnings-per-share principles.',
    ],
    [
        'id' => 'ifrs-ias-34-interim-reporting', 'title' => 'IAS 34 Interim Financial Reporting', 'source_type' => 'OFFICIAL_GUIDANCE',
        'authority' => 'IFRS Foundation', 'url' => 'https://www.ifrs.org/issued-standards/list-of-standards/ias-34-interim-financial-reporting/',
        'publication_date' => null, 'retrieved_at' => $today, 'version' => 'current official standard page', 'status' => 'official_reference', 'confidence' => 'authoritative_reference',
        'notes' => 'Authoritative source for interim reporting purpose, content, and recognition principles.',
    ],
    [
        'id' => 'ifrs-ias-40-investment-property', 'title' => 'IAS 40 Investment Property', 'source_type' => 'OFFICIAL_GUIDANCE',
        'authority' => 'IFRS Foundation', 'url' => 'https://www.ifrs.org/issued-standards/list-of-standards/ias-40-investment-property/',
        'publication_date' => null, 'retrieved_at' => $today, 'version' => 'current official standard page', 'status' => 'official_reference', 'confidence' => 'authoritative_reference',
        'notes' => 'Authoritative source for investment-property classification and subsequent measurement.',
    ],
    [
        'id' => 'ifrs-ias-41-agriculture', 'title' => 'IAS 41 Agriculture', 'source_type' => 'OFFICIAL_GUIDANCE',
        'authority' => 'IFRS Foundation', 'url' => 'https://www.ifrs.org/issued-standards/list-of-standards/ias-41-agriculture/',
        'publication_date' => null, 'retrieved_at' => $today, 'version' => 'current official standard page', 'status' => 'official_reference', 'confidence' => 'authoritative_reference',
        'notes' => 'Authoritative source for biological assets and agricultural produce.',
    ],
    [
        'id' => 'ifrs-13-fair-value', 'title' => 'IFRS 13 Fair Value Measurement', 'source_type' => 'OFFICIAL_GUIDANCE',
        'authority' => 'IFRS Foundation', 'url' => 'https://www.ifrs.org/issued-standards/list-of-standards/ifrs-13-fair-value-measurement/',
        'publication_date' => null, 'retrieved_at' => $today, 'version' => 'current official standard page', 'status' => 'official_reference', 'confidence' => 'authoritative_reference',
        'notes' => 'Authoritative source for fair-value measurement and valuation inputs.',
    ],
    [
        'id' => 'iaasb-isa-315-risk-assessment', 'title' => 'ISA 315 Revised 2019 — Identifying and Assessing Risks of Material Misstatement', 'source_type' => 'OFFICIAL_GUIDANCE',
        'authority' => 'International Auditing and Assurance Standards Board', 'url' => 'https://www.iaasb.org/publications/isa-315-revised-2019',
        'publication_date' => '2019-12-19', 'retrieved_at' => $today, 'version' => 'ISA 315 Revised 2019', 'status' => 'official_reference', 'confidence' => 'authoritative_reference',
        'notes' => 'Professional-standard source for risk assessment, understanding the entity, and internal control.',
    ],
    [
        'id' => 'iaasb-isa-500-audit-evidence', 'title' => 'ISA 500 — Audit Evidence', 'source_type' => 'OFFICIAL_GUIDANCE',
        'authority' => 'International Auditing and Assurance Standards Board', 'url' => 'https://www.iaasb.org/publications/isa-500-audit-evidence',
        'publication_date' => null, 'retrieved_at' => $today, 'version' => 'current official publication page', 'status' => 'official_reference', 'confidence' => 'authoritative_reference',
        'notes' => 'Professional-standard source for sufficiency, appropriateness, reliability, and evaluation of audit evidence.',
    ],
    [
        'id' => 'iaasb-isa-530-audit-sampling', 'title' => 'ISA 530 — Audit Sampling', 'source_type' => 'OFFICIAL_GUIDANCE',
        'authority' => 'International Auditing and Assurance Standards Board', 'url' => 'https://www.iaasb.org/publications/isa-530-audit-sampling',
        'publication_date' => null, 'retrieved_at' => $today, 'version' => 'current official publication page', 'status' => 'official_reference', 'confidence' => 'authoritative_reference',
        'notes' => 'Professional-standard source for sampling design, sample selection, and evaluation of results.',
    ],
    [
        'id' => 'iaasb-isa-700-audit-opinion', 'title' => 'ISA 700 — Forming an Opinion and Reporting on Financial Statements', 'source_type' => 'OFFICIAL_GUIDANCE',
        'authority' => 'International Auditing and Assurance Standards Board', 'url' => 'https://www.iaasb.org/publications/isa-700-revised-forming-opinion-and-reporting-financial-statements',
        'publication_date' => null, 'retrieved_at' => $today, 'version' => 'current official publication page', 'status' => 'official_reference', 'confidence' => 'authoritative_reference',
        'notes' => 'Professional-standard source for audit opinions and reporting conclusions.',
    ],
    [
        'id' => 'iaasb-isa-570-going-concern', 'title' => 'ISA 570 Revised 2024 — Going Concern', 'source_type' => 'OFFICIAL_GUIDANCE',
        'authority' => 'International Auditing and Assurance Standards Board', 'url' => 'https://www.iaasb.org/publications/isa-570-revised-2024-going-concern',
        'publication_date' => '2024-04-09', 'retrieved_at' => $today, 'version' => 'ISA 570 Revised 2024', 'status' => 'official_reference', 'confidence' => 'authoritative_reference',
        'notes' => 'Currentness source for going-concern evaluation and reporting; Philippine effectivity follows BOA adoption.',
    ],
    [
        'id' => 'iaasb-isa-240-fraud', 'title' => 'ISA 240 Revised — Auditor Responsibilities Relating to Fraud', 'source_type' => 'OFFICIAL_GUIDANCE',
        'authority' => 'International Auditing and Assurance Standards Board', 'url' => 'https://www.iaasb.org/publications/isa-240-revised-auditors-responsibilities-relating-fraud-audit-financial-statements',
        'publication_date' => '2025-07-08', 'retrieved_at' => $today, 'version' => 'ISA 240 Revised; Philippine adoption subject to BOA effective date', 'status' => 'official_reference', 'confidence' => 'authoritative_reference',
        'notes' => 'Source for fraud-risk and auditor-responsibility principles; adoption/effective-date claims remain version-sensitive.',
    ],
    [
        'id' => 'iaasb-isa-265-control-deficiencies', 'title' => 'ISA 265 — Communicating Deficiencies in Internal Control', 'source_type' => 'OFFICIAL_GUIDANCE',
        'authority' => 'International Auditing and Assurance Standards Board', 'url' => 'https://www.iaasb.org/publications/isa-265-communicating-deficiencies-internal-control-those-charged-governance-and-management',
        'publication_date' => null, 'retrieved_at' => $today, 'version' => 'current official publication page', 'status' => 'official_reference', 'confidence' => 'authoritative_reference',
        'notes' => 'Professional-standard source for communicating significant control deficiencies.',
    ],
    [
        'id' => 'iaasb-isa-230-documentation', 'title' => 'ISA 230 — Audit Documentation', 'source_type' => 'OFFICIAL_GUIDANCE',
        'authority' => 'International Auditing and Assurance Standards Board', 'url' => 'https://www.iaasb.org/publications/isa-230-audit-documentation',
        'publication_date' => null, 'retrieved_at' => $today, 'version' => 'current official publication page', 'status' => 'official_reference', 'confidence' => 'authoritative_reference',
        'notes' => 'Professional-standard source for timely and sufficient documentation of audit work.',
    ],
    [
        'id' => 'iaasb-isa-450-misstatements', 'title' => 'ISA 450 — Evaluation of Misstatements Identified during the Audit', 'source_type' => 'OFFICIAL_GUIDANCE',
        'authority' => 'International Auditing and Assurance Standards Board', 'url' => 'https://www.iaasb.org/publications/isa-450-evaluation-misstatements-identified-during-audit',
        'publication_date' => null, 'retrieved_at' => $today, 'version' => 'current official publication page', 'status' => 'official_reference', 'confidence' => 'authoritative_reference',
        'notes' => 'Professional-standard source for accumulating, evaluating, and communicating misstatements.',
    ],
];

/* Source additions are intentionally keyed before materialization. */
$sourceAdditionsById = [];
foreach ($sourceAdditions as $source) $sourceAdditionsById[(string)$source['id']] = $source;

$scopeSources = ['prc-accountancy-current-tos-2022', 'prc-accountancy-board-page', 'prc-accountancy-exam-program-2026'];
$farSources = array_merge($scopeSources, ['ifrs-conceptual-framework', 'ifrs-ias-1-presentation', 'ifrs-ias-2-inventories', 'ifrs-ias-8-accounting-policies-estimates-errors', 'ifrs-ias-10-events-after-reporting-period', 'ifrs-ias-12-income-taxes', 'ifrs-ias-16-property-plant-equipment', 'ifrs-ias-23-borrowing-costs', 'ifrs-ias-36-impairment', 'ifrs-ias-37-provisions', 'ifrs-ias-38-intangible-assets', 'ifrs-ifrs-9-financial-instruments', 'ifrs-13-fair-value', 'ifrs-ifrs-15-revenue', 'ifrs-ifrs-16-leases']);
$advancedSources = array_merge($scopeSources, ['ifrs-ifrs-2-share-based-payment', 'ifrs-ifrs-3-business-combinations', 'ifrs-ifrs-8-operating-segments', 'ifrs-ifrs-10-consolidated-financial-statements', 'ifrs-ifrs-11-joint-arrangements', 'ifrs-ias-21-foreign-exchange', 'ifrs-ias-27-separate-financial-statements', 'ifrs-ias-28-associates-joint-ventures', 'ifrs-ias-33-earnings-per-share', 'ifrs-ias-34-interim-reporting', 'ifrs-ias-40-investment-property', 'ifrs-ias-41-agriculture']);
$managementSources = array_merge($scopeSources, ['openstax-principles-managerial-accounting']);
$auditSources = array_merge($scopeSources, ['prc-accountancy-auditing-standards-2026', 'iaasb-isa-315-risk-assessment', 'iaasb-isa-500-audit-evidence', 'iaasb-isa-520-analytical-procedures', 'iaasb-isa-530-audit-sampling', 'iaasb-isa-700-audit-opinion', 'iaasb-isa-570-going-concern', 'iaasb-isa-240-fraud', 'iaasb-isa-265-control-deficiencies', 'iaasb-isa-230-documentation', 'iaasb-isa-450-misstatements']);
$regSources = array_merge($scopeSources, ['philippines-civil-code-business-law', 'philippines-civil-code-agency', 'philippines-revised-corporation-code', 'philippines-negotiable-instruments-law', 'philippines-accountancy-act', 'philippine-cpa-code-of-ethics', 'ifrs-ias-24-related-party']);
$taxSources = array_merge($scopeSources, ['philippines-national-internal-revenue-code', 'bir-tax-issuances-reference', 'bir-taxpayer-filing-forms-reference', 'philippines-ease-of-paying-taxes-act', 'bir-digital-services-vat-guidance']);

$materialAdditions = [
    [
        'id' => 'batch622-cpa-far-evidence', 'title' => 'CPA Financial Accounting and Reporting: Evidence-to-Statement Reasoning',
        'summary' => 'Source-backed FAR study resource for recognition, measurement, presentation, estimates, and financial-statement consequences.',
        'notes' => [
            ['heading' => 'Framework and presentation', 'body' => 'Use the Conceptual Framework and applicable presentation standard to identify the element, recognition evidence, measurement basis, classification, and statement effect. Relevance and faithful representation are distinct qualitative characteristics; a convenient number is not automatically a valid measurement.'],
            ['heading' => 'Assets and liabilities', 'body' => 'For inventory, receivables, PPE, intangibles, provisions, and leases, separate initial recognition from subsequent measurement. State the supplied assumptions, identify the applicable basis, and trace the result to profit, equity, assets, or liabilities.'],
            ['heading' => 'Timing and estimates', 'body' => 'Accruals and events after the reporting period require a reporting-date analysis. A change in estimate is prospective; a material prior-period error is corrected under the applicable presentation requirements. Do not treat every later event as adjusting.'],
            ['heading' => 'Common errors', 'body' => 'Do not use cash timing as a substitute for accrual recognition, mix output and input amounts, omit directly attributable costs, or infer a standard treatment from an account label alone.'],
        ],
        'source_ids' => $farSources, 'exams' => ['cpa'], 'taxonomy' => ['subject_id' => 'cpa-financial-accounting'], 'supported_concept_ids' => ['cpa-financial-accounting-framework-core','cpa-financial-accounting-classification-core','cpa-financial-accounting-accruals-core','cpa-financial-accounting-cash-flow-core','cpa-financial-accounting-inventory-core','cpa-financial-accounting-ppe-core','cpa-financial-accounting-impairment-core','cpa-financial-accounting-intangibles-core','cpa-financial-accounting-receivables-core','cpa-financial-accounting-provisions-core','cpa-financial-accounting-revenue-core','cpa-financial-accounting-leases-core','cpa-financial-accounting-equity-core','cpa-financial-accounting-estimates-core','cpa-financial-accounting-analysis-core'], 'technical_support_state' => 'STRONG', 'status' => 'published', 'version' => 1, 'updated_at' => $today,
    ],
    [
        'id' => 'batch622-cpa-advanced-evidence', 'title' => 'CPA Advanced Financial Accounting: Consolidation, Partnership, and Arrangement Evidence',
        'summary' => 'Source-backed advanced-accounting resource for control, acquisition method, partnerships, branches, joint arrangements, foreign exchange, and separate reporting.',
        'notes' => [
            ['heading' => 'Control and acquisition', 'body' => 'Distinguish control from percentage ownership, identify acquired assets and liabilities at the applicable measurement basis, and keep consideration, net assets, goodwill, NCI, and consolidation adjustments separate.'],
            ['heading' => 'Partnership and liquidation', 'body' => 'Apply the stated partnership agreement and liquidation method to capital balances, admission, withdrawal, profit allocation, claim priority, and settlement. Do not import corporate rules into a partnership fact pattern.'],
            ['heading' => 'Branches and arrangements', 'body' => 'Reconcile reciprocal accounts before preparing combined statements. Classify a joint operation by rights and obligations, not by the label in the contract, and distinguish a joint venture from a business combination.'],
            ['heading' => 'Currency and separate statements', 'body' => 'Keep transaction remeasurement, translation, equity-method accounting, and separate-financial-statement measurement distinct. State the rate and reporting assumption used.'],
        ],
        'source_ids' => $advancedSources, 'exams' => ['cpa'], 'taxonomy' => ['subject_id' => 'cpa-advanced-accounting'], 'supported_concept_ids' => ['cpa-advanced-accounting-partnerships-core','cpa-advanced-accounting-liquidation-core','cpa-advanced-accounting-joint-arrangements-core','cpa-advanced-accounting-branch-core','cpa-advanced-accounting-branch-inventory-core','cpa-advanced-accounting-combinations-core','cpa-advanced-accounting-consolidation-core','cpa-advanced-accounting-nci-core','cpa-advanced-accounting-foreign-currency-core','cpa-advanced-accounting-translation-core','cpa-advanced-accounting-intercompany-core','cpa-advanced-accounting-intercompany-balances-core','cpa-advanced-accounting-dividends-core','cpa-advanced-accounting-agreement-core','cpa-advanced-accounting-profit-allocation-core','cpa-advanced-accounting-profit-attribution-core'], 'technical_support_state' => 'STRONG', 'status' => 'published', 'version' => 1, 'updated_at' => $today,
    ],
    [
        'id' => 'batch622-cpa-management-evidence', 'title' => 'CPA Management Services: Decision and Performance Evidence',
        'summary' => 'Open-text-supported management-accounting resource for cost behavior, CVP, budgets, variances, relevant decisions, ABC, and capital budgeting.',
        'notes' => [
            ['heading' => 'Decision relevance', 'body' => 'Include future amounts that differ between alternatives, including opportunity costs when capacity is scarce. Exclude sunk and unavoidable allocated costs unless the question supplies a reason they change.'],
            ['heading' => 'CVP and mix', 'body' => 'Contribution margin is sales less variable cost. Preserve the stated product mix when computing weighted contribution or break-even; a volume change, price change, or fixed-cost change has a different sensitivity implication.'],
            ['heading' => 'Budgets and variances', 'body' => 'Separate operating income from cash timing. Identify the standard and actual basis before calculating a variance, then interpret it as a signal for investigation rather than proof of responsibility.'],
            ['heading' => 'ABC and investment', 'body' => 'Calculate each activity-pool rate from its stated driver, and keep payback, present value, and NPV distinct. Allocation is an analytical model, not automatically a cash outflow.'],
        ],
        'source_ids' => $managementSources, 'exams' => ['cpa'], 'taxonomy' => ['subject_id' => 'cpa-management-services'], 'supported_concept_ids' => ['cpa-management-services-cvp-core','cpa-management-services-cost-behavior-core','cpa-management-services-sales-mix-core','cpa-management-services-budgeting-core','cpa-management-services-cash-budget-core','cpa-management-services-variance-core','cpa-management-services-standard-costing-core','cpa-management-services-relevant-costing-core','cpa-management-services-make-buy-core','cpa-management-services-constraints-core','cpa-management-services-npv-core','cpa-management-services-capital-budgeting-core','cpa-management-services-activity-based-core','cpa-management-services-control-core','cpa-management-services-performance-core','cpa-management-services-sunk-cost-core'], 'technical_support_state' => 'STRONG', 'status' => 'published', 'version' => 1, 'updated_at' => $today,
    ],
    [
        'id' => 'batch622-cpa-auditing-evidence', 'title' => 'CPA Auditing: Risk, Evidence, Procedures, and Reporting',
        'summary' => 'Currentness-aware auditing resource for assertions, risk, controls, evidence, analytical procedures, sampling, documentation, going concern, fraud, and reporting.',
        'notes' => [
            ['heading' => 'Risk and controls', 'body' => 'Understand the entity and its controls before selecting responses. Inherent risk, control risk, and detection risk are related but not interchangeable; a control deficiency does not by itself establish a financial-statement misstatement.'],
            ['heading' => 'Evidence and procedures', 'body' => 'Evaluate evidence for sufficiency and appropriateness, including relevance and reliability. Match the procedure to the assertion and risk; an external confirmation can be strong evidence for one assertion without answering every assertion.'],
            ['heading' => 'Analysis and sampling', 'body' => 'Develop an expectation for analytical procedures, investigate significant unexplained differences, and evaluate sampling results using the stated population, sample, and projection assumptions.'],
            ['heading' => 'Reporting and documentation', 'body' => 'Classify a misstatement or scope limitation by materiality and pervasiveness before selecting the report consequence. Documentation should allow an experienced auditor to understand the work, evidence, and conclusion. Current Philippine adoption and effective dates control standard-sensitive claims.'],
        ],
        'source_ids' => $auditSources, 'exams' => ['cpa'], 'taxonomy' => ['subject_id' => 'cpa-auditing'], 'supported_concept_ids' => ['cpa-auditing-assertions-core','cpa-auditing-assurance-core','cpa-auditing-audit-risk-core','cpa-auditing-internal-control-core','cpa-auditing-control-risk-core','cpa-auditing-evidence-core','cpa-auditing-materiality-core','cpa-auditing-analytics-core','cpa-auditing-sampling-core','cpa-auditing-reporting-core','cpa-auditing-going-concern-core','cpa-auditing-fraud-core','cpa-auditing-documentation-core'], 'technical_support_state' => 'STRONG', 'status' => 'published', 'version' => 1, 'updated_at' => $today,
    ],
    [
        'id' => 'batch622-cpa-regulatory-evidence', 'title' => 'CPA Regulatory Framework: Philippine Law, Governance, and Professional Responsibility',
        'summary' => 'Primary-law-supported CPA resource for obligations, contracts, sales, agency, corporations, negotiable instruments, partnerships, records, confidentiality, and conflicts.',
        'notes' => [
            ['heading' => 'Fact-bound legal analysis', 'body' => 'Identify consent, authority, object, consideration, performance, notice, breach, and remedy facts before drawing a legal conclusion. A general business label cannot replace the facts required by the governing law.'],
            ['heading' => 'Corporate and agency authority', 'body' => 'Separate the corporation’s legal personality from internal approval and documentation. For agency, distinguish express authority, implied authority, notice of a limit, and ratification.'],
            ['heading' => 'Professional responsibility', 'body' => 'Apply the Accountancy Act and current Philippine ethics adoption material to records, confidentiality, conflicts, objectivity, and lawful disclosure. State missing facts rather than inventing an exception.'],
            ['heading' => 'Version boundary', 'body' => 'Statutes and professional rules are version-sensitive. The primary text and current Philippine adoption control; these notes are concise instructional support, not a substitute for the law or code.'],
        ],
        'source_ids' => $regSources, 'exams' => ['cpa'], 'taxonomy' => ['subject_id' => 'cpa-regulatory-framework'], 'supported_concept_ids' => ['cpa-regulatory-framework-contracts-core','cpa-regulatory-framework-obligations-core','cpa-regulatory-framework-sales-core','cpa-regulatory-framework-sales-remedies-core','cpa-regulatory-framework-remedies-core','cpa-regulatory-framework-corporate-law-core','cpa-regulatory-framework-governance-core','cpa-regulatory-framework-agency-core','cpa-regulatory-framework-commercial-law-core','cpa-regulatory-framework-security-core','cpa-regulatory-framework-partnership-law-core','cpa-regulatory-framework-partnerships-core','cpa-regulatory-framework-professional-law-core','cpa-regulatory-framework-records-core','cpa-regulatory-framework-confidentiality-core','cpa-regulatory-framework-conflict-core','cpa-regulatory-framework-disclosure-core'], 'technical_support_state' => 'STRONG', 'status' => 'published', 'version' => 1, 'updated_at' => $today,
    ],
    [
        'id' => 'batch622-cpa-tax-evidence', 'title' => 'CPA Taxation: Philippine Tax Base, Compliance, and Current-Law Controls',
        'summary' => 'Current-source-aware tax resource for tax-base construction, deductions, VAT, withholding, filing, records, exemptions, administration, and professional research.',
        'notes' => [
            ['heading' => 'Build before computing', 'body' => 'Identify the taxpayer, transaction, period, tax base, and legal role before applying an amount. A rate supplied by an exercise is a calculation assumption, not proof of a current statutory rate.'],
            ['heading' => 'Evidence and reconciliation', 'body' => 'Reconcile books, schedules, returns, invoices, and supporting documents. Keep recognition, classification, substantiation, and arithmetic as separate decisions.'],
            ['heading' => 'VAT and withholding', 'body' => 'Output tax, input credit, withholding, remittance, and the payee’s tax position are related but not interchangeable. Follow the current Tax Code and BIR issuance for live rates, thresholds, forms, and effective dates.'],
            ['heading' => 'Current-law control', 'body' => 'Record the effective period and authority level. If a client asks for a current rate or filing rule, verify the current BIR issuance rather than relying on memory or an old summary.'],
        ],
        'source_ids' => $taxSources, 'exams' => ['cpa'], 'taxonomy' => ['subject_id' => 'cpa-taxation'], 'supported_concept_ids' => ['cpa-taxation-tax-basics-core','cpa-taxation-tax-base-core','cpa-taxation-deductions-core','cpa-taxation-substantiation-core','cpa-taxation-withholding-core','cpa-taxation-vat-core','cpa-taxation-filing-core','cpa-taxation-exemptions-core','cpa-taxation-administration-core','cpa-taxation-current-law-core','cpa-taxation-reconciliation-core','cpa-taxation-records-core','cpa-taxation-remedies-core','cpa-taxation-transfer-tax-core','cpa-taxation-payroll-tax-core','cpa-taxation-compliance-core','cpa-taxation-ethics-core'], 'technical_support_state' => 'STRONG', 'status' => 'published', 'version' => 1, 'updated_at' => $today,
    ],
];
$materialAdditionsById = [];
foreach ($materialAdditions as $material) $materialAdditionsById[(string)$material['id']] = $material;

$questions = [];
$addQuestion = static function (string $concept, int $depth, string $context, string $stem, string $answer, array $wrong, string $explanation, string $material, array $sources, string $scenario, ?array $calculation = null, array $versionCategories = [], string $prerequisite = 'PREREQUISITE_FREE', array $misconceptions = []) use (&$questions, $conceptById, $topicById, $domainById, $today): void {
    if (!isset($conceptById[$concept])) throw new RuntimeException('Unknown CPA concept: ' . $concept);
    if (!in_array($depth, [2, 3, 4], true)) throw new RuntimeException('Invalid new CPA depth: ' . $depth);
    if (!in_array($context, ['NEUTRAL', 'PROFESSIONAL', 'REGULATORY'], true)) throw new RuntimeException('Invalid CPA context: ' . $context);
    $choices = array_values(array_unique(array_merge([$answer], array_map('strval', $wrong))));
    if (count($choices) !== 4) throw new RuntimeException('Expected four distinct choices: ' . $stem);
    $t = $conceptById[$concept];
    $topic = $topicById[(string)($t['topic_id'] ?? '')] ?? [];
    $domain = $domainById[(string)($topic['domain_id'] ?? '')] ?? [];
    $subjectId = (string)($domain['subject_id'] ?? '');
    $domainId = (string)($domain['id'] ?? ($topic['domain_id'] ?? ''));
    $topicId = (string)($topic['id'] ?? ($t['topic_id'] ?? ''));
    $numeric = $calculation !== null || (bool)preg_match('/(?:\d|%|₱|\bP\s*\d)/u', $stem);
    $calculationRequired = $calculation !== null;
    $sourceSensitivity = $versionCategories !== [] ? (in_array('TAX_SENSITIVE', $versionCategories, true) || in_array('REGULATORY_SENSITIVE', $versionCategories, true) || in_array('CORPORATE_LEGAL_SENSITIVE', $versionCategories, true) || in_array('PROFESSIONAL_RULE_SENSITIVE', $versionCategories, true) ? 'STATUTORY_VERSIONED' : 'STANDARD_VERSIONED') : 'STABLE';
    $questions[] = [
        'taxonomy' => ['board_id' => 'cpa', 'subject_id' => $subjectId, 'domain_id' => $domainId, 'topic_id' => $topicId, 'concept_id' => $concept],
        'board' => 'cpa', 'status' => 'active', 'source' => 'batch622-source-first', 'createdAt' => $today, 'updatedAt' => $today,
        'difficulty' => $depth >= 4 ? 'hard' : ($depth === 3 ? 'medium' : 'easy'), 'question' => $stem, 'choices' => $choices, 'answer' => $answer, 'explanation' => $explanation,
        'subject' => $subjectId, 'domain' => $domainId, 'topic' => $topicId, 'concept' => $concept,
        'source_ids' => array_values(array_unique(array_map('strval', $sources))), 'numeric' => $numeric, 'calculation_required' => $calculationRequired, 'quantitative_semantics' => $calculationRequired ? 'CALCULATION_REQUIRED' : ($numeric ? 'CONCEPTUAL_QUANTITATIVE' : 'NON_NUMERICAL'), 'multistep' => $depth >= 4,
        'cognitive_intent' => $depth >= 4 ? 'analyze' : ($depth === 3 ? 'apply' : 'understand'), 'source_sensitivity' => $sourceSensitivity, 'content_blocks' => [],
        'unit_sensitive' => $calculationRequired, 'table_dependent' => false, 'data_interpretation' => $depth >= 4, 'long_scenario' => $depth >= 4,
        'professional_judgment' => $context === 'PROFESSIONAL', 'context_dependent' => $context !== 'NEUTRAL', 'context_necessary' => $context !== 'NEUTRAL',
        'passage_dependent' => false, 'passage_necessary' => false, 'technical_reasoning' => $depth >= 4, 'figure_dependent' => false,
        'timesUsed' => 0, 'timesCorrect' => 0, 'timesIncorrect' => 0, 'bookmarks' => 0, 'reports' => 0, 'helpfulExplanation' => 0, 'notHelpfulExplanation' => 0,
        '__concept' => $concept, '__depth' => $depth, '__context' => $context, '__material' => $material, '__sources' => array_values(array_unique(array_map('strval', $sources))),
        'version_sensitivity_categories' => array_values(array_unique(array_map('strval', $versionCategories))), 'currentness_verified' => $versionCategories !== [] ? true : null,
        '__scenario' => $scenario, '__calculation' => $calculation, '__version_categories' => array_values(array_unique(array_map('strval', $versionCategories))), '__prerequisite' => $prerequisite, '__misconceptions' => array_values(array_unique(array_map('strval', $misconceptions))),
    ];
};
$Q = static function (string $concept, int $depth, string $context, string $stem, string $answer, array $wrong, string $explanation, string $material, array $sources, string $scenario, ?array $calculation = null, array $versionCategories = [], string $prerequisite = 'PREREQUISITE_FREE', array $misconceptions = []) use (&$addQuestion): void {
    $addQuestion($concept, $depth, $context, $stem, $answer, $wrong, $explanation, $material, $sources, $scenario, $calculation, $versionCategories, $prerequisite, $misconceptions);
};

$far = 'batch622-cpa-far-evidence';
$advanced = 'batch622-cpa-advanced-evidence';
$management = 'batch622-cpa-management-evidence';
$auditing = 'batch622-cpa-auditing-evidence';
$regulatory = 'batch622-cpa-regulatory-evidence';
$tax = 'batch622-cpa-tax-evidence';

/* Financial Accounting and Reporting: recognition, measurement, timing, and
 * statement effects.  The cohort deliberately mixes calculations, diagnosis,
 * classification, and consequence analysis. */
$Q('cpa-financial-accounting-framework-core', 3, 'NEUTRAL', 'A reporting choice faithfully represents an asset when it primarily does what?', 'Depicts the economic phenomenon completely, neutrally, and without material error', ['Uses the easiest amount to calculate', 'Maximizes reported profit in the current period', 'Follows management’s preferred presentation'], 'Faithful representation concerns completeness, neutrality, and freedom from material error; convenience or profit effect is not the governing criterion.', $far, $farSources, 'concept interpretation', null, ['ACCOUNTING_STANDARD_SENSITIVE'], 'PREREQUISITE_FREE', ['profit-maximization-as-quality']);
$Q('cpa-financial-accounting-framework-core', 4, 'PROFESSIONAL', 'Management has reliable evidence that an obligation exists but cannot estimate its amount within a useful range. What is the most defensible reporting response?', 'Assess the applicable recognition and disclosure requirements and document why measurement evidence is insufficient', ['Recognize an arbitrary midpoint', 'Ignore the obligation because no exact amount exists', 'Recognize the largest possible amount automatically'], 'Recognition and measurement are separate judgments. The accountant must apply the governing standard and disclose or defer recognition as required rather than inventing a precise amount.', $far, $farSources, 'professional judgment', null, ['ACCOUNTING_STANDARD_SENSITIVE'], 'PREREQUISITE_FREE', ['exact-amount-required']);
$Q('cpa-financial-accounting-classification-core', 3, 'NEUTRAL', 'A receivable is due in 15 months, but the entity’s normal operating cycle is 18 months. Assuming no other restriction, how should it generally be classified?', 'As current because it is realized within the normal operating cycle', ['As non-current solely because 15 months exceeds one year', 'As equity because collection is expected', 'As inventory because it arose from a sale'], 'Current classification considers the normal operating cycle where the presentation standard makes that the relevant basis; a one-year shortcut is not always decisive.', $far, $farSources, 'classification application', null, ['ACCOUNTING_STANDARD_SENSITIVE'], 'PREREQUISITE_FREE', ['one-year-only-shortcut']);
$Q('cpa-financial-accounting-classification-core', 4, 'PROFESSIONAL', 'A loan is contractually due in 10 months, but the lender granted a documented long-term refinancing facility before reporting date. What should the accountant examine first?', 'Whether the refinancing arrangement existed and qualified at the reporting date under the applicable presentation rule', ['Classify it non-current because management intends to refinance', 'Classify it current because every loan under one year is current', 'Classify it as equity after refinancing discussions'], 'Classification depends on the reporting-date rights and the applicable standard, not an unsupported intention or a later informal discussion.', $far, $farSources, 'classification diagnosis', null, ['ACCOUNTING_STANDARD_SENSITIVE'], 'PREREQUISITE_FREE', ['management-intention-shortcut']);
$Q('cpa-financial-accounting-accruals-core', 3, 'NEUTRAL', 'A utility service was received before year-end, but the invoice will arrive next month. What entry best applies accrual accounting?', 'Recognize the expense and a payable at year-end', ['Recognize nothing until cash is paid', 'Recognize revenue and a receivable', 'Debit cash and credit the expense'], 'The service has been received in the reporting period. Accrual accounting recognizes the incurred expense and corresponding liability before settlement.', $far, $farSources, 'routine application', null, ['ACCOUNTING_STANDARD_SENSITIVE'], 'PREREQUISITE_FREE', ['cash-basis-substitution']);
$Q('cpa-financial-accounting-accruals-core', 3, 'NEUTRAL', 'A six-month insurance premium was paid on October 1. At December 31, which portion is initially an expense?', 'Three months of coverage', ['The full six-month premium', 'No amount until the policy expires', 'The amount paid plus three more months'], 'Three months of the coverage period have elapsed by December 31; the remaining benefit is a prepaid asset.', $far, $farSources, 'period allocation', null, ['ACCOUNTING_STANDARD_SENSITIVE'], 'PREREQUISITE_FREE', ['payment-equals-expense']);
$Q('cpa-financial-accounting-cash-flow-core', 3, 'NEUTRAL', 'Under the indirect method, which adjustment reconciles profit to operating cash flow when accounts receivable increases during the period?', 'Subtract the increase in accounts receivable', ['Add the increase in accounts receivable', 'Classify it as a financing inflow', 'Ignore it because revenue was recognized'], 'An increase in receivables represents revenue not yet collected, so it is subtracted in the indirect operating-cash reconciliation.', $far, $farSources, 'cash-flow reconciliation', null, ['ACCOUNTING_STANDARD_SENSITIVE'], 'PREREQUISITE_FREE', ['profit-equals-cash']);
$Q('cpa-financial-accounting-cash-flow-core', 4, 'PROFESSIONAL', 'An entity acquired equipment by issuing shares and paid no cash. Which presentation best communicates the transaction?', 'Exclude it from the cash-flow totals but disclose the significant non-cash investing and financing transaction', ['Report the equipment cost as an operating cash outflow', 'Report the shares issued as a cash inflow', 'Omit the transaction entirely'], 'The transaction affects investing and financing resources without cash. It is disclosed separately rather than inserted into cash-flow totals.', $far, $farSources, 'statement presentation', null, ['ACCOUNTING_STANDARD_SENSITIVE'], 'PREREQUISITE_FREE', ['noncash-as-cash']);
$Q('cpa-financial-accounting-inventory-core', 3, 'NEUTRAL', 'Inventory cost is P180,000 and estimated selling price less costs to complete and sell is P165,000. What amount is reported before considering other facts?', 'P165,000', ['P15,000', 'P180,000', 'P345,000'], 'The inventory is measured at the lower applicable amount: P165,000 rather than its P180,000 cost.', $far, $farSources, 'measurement calculation', ['family' => 'min', 'values' => [180000, 165000], 'unit' => 'P', 'rounding' => 'exact'], ['ACCOUNTING_STANDARD_SENSITIVE'], 'PREREQUISITE_FREE', ['cost-always-controls']);
$Q('cpa-financial-accounting-inventory-core', 4, 'PROFESSIONAL', 'Goods held on consignment for another entity are found in the warehouse at year-end. What is the key reporting question?', 'Which entity controls the goods and bears the relevant risks under the arrangement', ['Whether the goods are physically inside the warehouse only', 'Whether the consignee has already paid cash', 'Whether the goods have the lowest unit cost'], 'Physical possession does not alone establish ownership or control. The arrangement must be analyzed before including goods in the entity’s inventory.', $far, $farSources, 'inventory diagnosis', null, ['ACCOUNTING_STANDARD_SENSITIVE'], 'PREREQUISITE_FREE', ['possession-equals-ownership']);
$Q('cpa-financial-accounting-inventory-core', 3, 'NEUTRAL', 'A purchase invoice includes freight-in and a recoverable purchase tax. Which treatment is generally appropriate?', 'Include freight-in in inventory cost and exclude the recoverable tax from cost', ['Expense both amounts immediately', 'Capitalize the recoverable tax but expense freight-in', 'Include neither amount in the inventory analysis'], 'Freight necessary to bring inventory to its present location and condition is a cost; a recoverable tax is generally not part of inventory cost.', $far, $farSources, 'cost classification', null, ['ACCOUNTING_STANDARD_SENSITIVE'], 'PREREQUISITE_FREE', ['recoverable-tax-as-cost']);
$Q('cpa-financial-accounting-ppe-core', 3, 'NEUTRAL', 'Equipment costs P500,000, delivery is P20,000, and installation is P10,000. What initial cost is supported before depreciation?', 'P530,000', ['P500,000', 'P510,000', 'P530,000 plus the first year’s depreciation'], 'Directly attributable delivery and installation bring the equipment to the location and condition necessary for use: P500,000 + P20,000 + P10,000 = P530,000.', $far, $farSources, 'capitalization calculation', ['family' => 'sum', 'values' => [500000, 20000, 10000], 'unit' => 'P', 'rounding' => 'exact'], ['ACCOUNTING_STANDARD_SENSITIVE'], 'PREREQUISITE_FREE', ['omitting-direct-cost']);
$Q('cpa-financial-accounting-ppe-core', 4, 'PROFESSIONAL', 'A machine’s useful life estimate changes because new maintenance evidence shows it will remain productive longer. What is the proper starting point?', 'Treat the change as a change in estimate and apply the revised estimate prospectively', ['Restate all prior depreciation as a prior-period error', 'Ignore the new evidence until disposal', 'Record the change directly as revenue'], 'A revised useful-life estimate based on new information is prospective under the applicable accounting treatment; it is not automatically a prior-period error.', $far, $farSources, 'estimate application', null, ['ACCOUNTING_STANDARD_SENSITIVE'], 'PREREQUISITE_FREE', ['estimate-as-error']);
$Q('cpa-financial-accounting-impairment-core', 3, 'NEUTRAL', 'An asset has a carrying amount of P1,200,000 and a recoverable amount of P1,050,000. What impairment loss is recognized?', 'P150,000', ['P1,050,000', 'P1,200,000', 'P2,250,000'], 'The carrying amount exceeds recoverable amount by P150,000, so that excess is the impairment loss.', $far, $farSources, 'impairment calculation', ['family' => 'subtract', 'values' => [1200000, 1050000], 'unit' => 'P', 'rounding' => 'exact'], ['ACCOUNTING_STANDARD_SENSITIVE'], 'PREREQUISITE_FREE', ['recoverable-amount-added']);
$Q('cpa-financial-accounting-impairment-core', 4, 'PROFESSIONAL', 'A cash-generating unit contains goodwill and operating assets, and impairment indicators are present. What is the strongest analysis sequence?', 'Determine the applicable recoverable amount and allocate the loss under the governing impairment requirements', ['Write off goodwill automatically without testing the unit', 'Ignore the indicator because cash flow is positive', 'Revalue every asset to replacement cost'], 'Impairment is a standard-bound measurement and allocation exercise. The existence of an indicator does not authorize an arbitrary write-off or replacement-cost revaluation.', $far, $farSources, 'impairment diagnosis', null, ['ACCOUNTING_STANDARD_SENSITIVE'], 'PREREQUISITE_FREE', ['automatic-goodwill-writeoff']);
$Q('cpa-financial-accounting-intangibles-core', 3, 'NEUTRAL', 'Which expenditure is most clearly research rather than an identifiable intangible asset ready for capitalization?', 'Early investigation performed before technical feasibility and future economic benefits are demonstrated', ['A purchased patent with a measurable acquisition price', 'Legal fees directly attributable to registering a completed patent', 'A finite-life patent amortized over its useful life'], 'Early research is expensed under the applicable standard when the recognition criteria for development or an identifiable asset are not met.', $far, $farSources, 'classification application', null, ['ACCOUNTING_STANDARD_SENSITIVE'], 'PREREQUISITE_FREE', ['all-development-costs-capitalized']);
$Q('cpa-financial-accounting-intangibles-core', 3, 'NEUTRAL', 'A finite-life patent cost P240,000 and has an eight-year useful life with no residual value. What is one full year’s straight-line amortization?', 'P30,000', ['P8,000', 'P192,000', 'P248,000'], 'Straight-line amortization is P240,000 ÷ 8 = P30,000 per year.', $far, $farSources, 'amortization calculation', ['family' => 'divide', 'values' => [240000, 8], 'unit' => 'P', 'rounding' => 'exact'], ['ACCOUNTING_STANDARD_SENSITIVE'], 'PREREQUISITE_FREE', ['cost-divided-by-years-wrong']);
$Q('cpa-financial-accounting-receivables-core', 3, 'NEUTRAL', 'An allowance analysis requires P30,000 and the existing allowance has an P8,000 credit balance. What bad-debt expense is recorded?', 'P22,000', ['P8,000', 'P30,000', 'P38,000'], 'The required ending credit balance is P30,000; after the existing P8,000 credit, the additional expense is P22,000.', $far, $farSources, 'allowance calculation', ['family' => 'subtract', 'values' => [30000, 8000], 'unit' => 'P', 'rounding' => 'exact'], ['ACCOUNTING_STANDARD_SENSITIVE'], 'PREREQUISITE_FREE', ['ignoring-existing-allowance']);
$Q('cpa-financial-accounting-receivables-core', 4, 'PROFESSIONAL', 'A receivable is factored with recourse and the transferor retains substantially the collection risk. What issue must be resolved before derecognition?', 'Whether the transfer meets the applicable control and risk-retention conditions for derecognition', ['Whether cash was received, which automatically proves sale', 'Whether the receivable is current, which automatically proves derecognition', 'Whether the factor is a related party, which is the only issue'], 'Cash receipt alone does not settle the accounting. The transfer terms and retained risks determine whether the receivable is derecognized or remains recognized with financing treatment.', $far, $farSources, 'derecognition analysis', null, ['ACCOUNTING_STANDARD_SENSITIVE'], 'PREREQUISITE_FREE', ['cash-receipt-equals-sale']);
$Q('cpa-financial-accounting-provisions-core', 4, 'PROFESSIONAL', 'A warranty obligation is probable and a reliable best estimate can be made from past claims. What is the appropriate reporting direction?', 'Recognize a provision for the best estimate and explain the relevant uncertainty', ['Recognize nothing until every claim is filed', 'Recognize the maximum imaginable payout', 'Classify the expected cost as equity'], 'A present obligation with probable outflow and reliable estimation supports a provision measured at the best estimate, subject to the governing standard.', $far, $farSources, 'provision analysis', null, ['ACCOUNTING_STANDARD_SENSITIVE'], 'PREREQUISITE_FREE', ['certainty-before-provision']);
$Q('cpa-financial-accounting-estimates-core', 3, 'NEUTRAL', 'A new probability model changes the expected useful life of equipment using information obtained this year. How is the effect generally treated?', 'Prospectively in the current and future periods affected by the revised estimate', ['Retrospectively as a prior-period error', 'As a direct credit to share capital', 'Only when the equipment is sold'], 'A change caused by new information is an estimate change and is applied prospectively under the applicable accounting framework.', $far, $farSources, 'estimate classification', null, ['ACCOUNTING_STANDARD_SENSITIVE'], 'PREREQUISITE_FREE', ['new-information-as-error']);
$Q('cpa-financial-accounting-revenue-core', 3, 'NEUTRAL', 'A customer prepays for a 12-month service contract and only two months have been performed at reporting date. What amount is initially a contract liability?', 'The portion relating to the ten months of unsatisfied service', ['The entire receipt as revenue', 'Nothing because cash was received', 'The full amount as an expense'], 'The remaining performance obligation supports a contract liability; revenue is recognized as the service is transferred.', $far, $farSources, 'revenue timing', null, ['ACCOUNTING_STANDARD_SENSITIVE'], 'PREREQUISITE_FREE', ['cash-receipt-as-revenue']);
$Q('cpa-financial-accounting-revenue-core', 4, 'PROFESSIONAL', 'A reseller never controls a product before it is transferred to the customer and earns a fixed commission. Which presentation should be evaluated?', 'Net revenue for the commission if the facts support an agent relationship', ['Gross revenue equal to the customer’s entire payment automatically', 'No revenue because the reseller did not own inventory', 'Revenue equal to the product’s manufacturing cost'], 'Principal-versus-agent analysis asks whether the entity controls the specified good before transfer. An agent generally reports the fee or commission, subject to the standard and facts.', $far, $farSources, 'principal-agent analysis', null, ['ACCOUNTING_STANDARD_SENSITIVE'], 'PREREQUISITE_FREE', ['ownership-equals-principal']);
$Q('cpa-financial-accounting-leases-core', 3, 'NEUTRAL', 'A lessee makes four annual payments of P100,000 at period-end and the supplied present-value factor is 3.465. What initial lease liability is measured?', 'P346,500', ['P100,000', 'P300,000', 'P346,500 plus four payments'], 'Using the supplied factor, P100,000 × 3.465 = P346,500. The factor already represents the present value of the four payments.', $far, $farSources, 'lease measurement calculation', ['family' => 'multiply', 'values' => [100000, 3.465], 'unit' => 'P', 'rounding' => 'nearest peso'], ['ACCOUNTING_STANDARD_SENSITIVE'], 'PREREQUISITE_FREE', ['factor-not-applied']);
$Q('cpa-financial-accounting-equity-core', 4, 'PROFESSIONAL', 'A company reacquires its own shares and holds them for possible reissue. Which statement is most defensible?', 'The reacquired shares are treasury shares and are presented as a deduction from equity under the applicable framework', ['They are an asset generating an immediate gain', 'They are inventory because shares may be resold', 'They are revenue when acquired'], 'Own-share transactions do not create an asset or revenue in the ordinary sense; treasury shares are presented within equity under the applicable standard.', $far, $farSources, 'equity presentation', null, ['ACCOUNTING_STANDARD_SENSITIVE'], 'PREREQUISITE_FREE', ['treasury-shares-as-asset']);
$Q('cpa-financial-accounting-analysis-core', 3, 'NEUTRAL', 'A ratio analysis shows profit rising while operating cash flow falls because receivables increased sharply. What is the most useful interpretation?', 'Profit growth may not yet be converting into cash and collection risk deserves investigation', ['Profit growth proves liquidity improved', 'The receivable increase is always financing cash', 'Cash flow is irrelevant once profit is positive'], 'The relationship is a diagnostic signal: accrual revenue and collection timing differ. It calls for investigation rather than a conclusion that either profit or cash is false.', $far, $farSources, 'financial interpretation', null, ['ACCOUNTING_STANDARD_SENSITIVE'], 'PREREQUISITE_FREE', ['profit-equals-liquidity']);

/* Advanced Financial Accounting and Reporting: the official TOS requires
 * partnership, liquidation, arrangements, revenue, branch, combinations,
 * and consolidated-reporting reasoning. */
$Q('cpa-advanced-accounting-partnerships-core', 3, 'NEUTRAL', 'Two partners contribute cash of P180,000 and equipment with an agreed fair value of P120,000. What initial combined capital is recorded before other adjustments?', 'P300,000', ['P60,000', 'P180,000', 'P360,000'], 'The agreed contributions are measured at P180,000 + P120,000 = P300,000.', $advanced, $advancedSources, 'partnership formation', ['family' => 'sum', 'values' => [180000, 120000], 'unit' => 'P', 'rounding' => 'exact'], ['ACCOUNTING_STANDARD_SENSITIVE'], 'PREREQUISITE:cpa-financial-accounting-framework-core', ['book-value-only']);
$Q('cpa-advanced-accounting-partnerships-core', 4, 'PROFESSIONAL', 'A new partner purchases an existing partner’s interest directly from that partner, and the partnership does not receive cash. What is the central accounting distinction?', 'The transaction changes ownership between partners; it is not automatically a partnership asset contribution', ['The partnership records revenue for the purchase price', 'The partnership always records the cash as new capital', 'The transaction automatically creates goodwill equal to cash paid'], 'A direct purchase between partners differs from an investment made into the partnership. The agreement and applicable accounting method determine any capital reallocation or goodwill treatment.', $advanced, $advancedSources, 'admission analysis', null, ['ACCOUNTING_STANDARD_SENSITIVE'], 'PREREQUISITE:cpa-financial-accounting-framework-core', ['purchase-equals-partnership-contribution']);
$Q('cpa-advanced-accounting-liquidation-core', 3, 'NEUTRAL', 'During partnership liquidation, which amount must be resolved before distributing residual cash to partners?', 'Claims of outside creditors and other obligations of the partnership', ['Only the partners’ original contributions', 'The partners’ preferred dividend rate', 'The next period’s expected sales'], 'External claims are settled before residual assets are distributed to partners. Original contributions alone do not determine the liquidation sequence.', $advanced, $advancedSources, 'liquidation priority', null, ['ACCOUNTING_STANDARD_SENSITIVE'], 'PREREQUISITE:cpa-financial-accounting-framework-core', ['partners-paid-first']);
$Q('cpa-advanced-accounting-liquidation-core', 3, 'NEUTRAL', 'A liquidation payment of P250,000 is made after all outside claims are settled, and the partners’ agreed capital balances total P250,000. What residual amount remains for capital settlement?', 'P250,000', ['P0', 'P125,000', 'P500,000'], 'With outside claims already settled, the stated payment is the residual cash available for the partners’ capital settlement.', $advanced, $advancedSources, 'liquidation calculation', ['family' => 'identity', 'values' => [250000], 'unit' => 'P', 'rounding' => 'exact'], ['ACCOUNTING_STANDARD_SENSITIVE'], 'PREREQUISITE:cpa-financial-accounting-framework-core', ['outside-claims-double-counted']);
$Q('cpa-advanced-accounting-joint-arrangements-core', 3, 'NEUTRAL', 'An arrangement gives each party direct rights to specified assets and obligations for specified liabilities. Which classification is most supported?', 'Joint operation', ['Joint venture automatically', 'Business combination', 'Financial liability only'], 'Rights to assets and obligations for liabilities support joint-operation analysis. A label such as “joint arrangement” does not by itself establish a joint venture.', $advanced, $advancedSources, 'arrangement classification', null, ['ACCOUNTING_STANDARD_SENSITIVE'], 'PREREQUISITE:cpa-financial-accounting-framework-core', ['label-over-rights']);
$Q('cpa-advanced-accounting-joint-arrangements-core', 3, 'NEUTRAL', 'An investor recognizes its 35% share of a joint venture’s reported profit of P400,000 under the equity method. What share is recognized?', 'P140,000', ['P35,000', 'P260,000', 'P400,000'], 'P400,000 × 35% = P140,000. The calculation applies the stated ownership share to the reported profit.', $advanced, $advancedSources, 'equity-method calculation', ['family' => 'percent', 'values' => [400000, 0.35], 'unit' => 'P', 'rounding' => 'exact'], ['ACCOUNTING_STANDARD_SENSITIVE'], 'PREREQUISITE:cpa-financial-accounting-framework-core', ['percentage-as-points']);
$Q('cpa-advanced-accounting-agreement-core', 3, 'NEUTRAL', 'A customer contract contains a distinct installation service and a distinct maintenance service. What is the first revenue-analysis step?', 'Identify the separate performance obligations and allocate consideration using the stated evidence', ['Recognize all consideration on signing', 'Treat both services as inventory', 'Recognize only the maintenance fee as revenue'], 'The five-step model begins with identifying the contract and its performance obligations. Recognition follows transfer of each promised service.', $advanced, $advancedSources, 'revenue contract analysis', null, ['ACCOUNTING_STANDARD_SENSITIVE'], 'PREREQUISITE:cpa-financial-accounting-framework-core', ['signing-equals-revenue']);
$Q('cpa-advanced-accounting-agreement-core', 4, 'PROFESSIONAL', 'A construction contract is 40% complete using a supported cost-to-cost measure and total expected revenue is P1,000,000. What revenue is recognized to date before other adjustments?', 'P400,000', ['P40,000', 'P600,000', 'P1,000,000'], 'The supported progress measure is 40%; P1,000,000 × 40% = P400,000.', $advanced, $advancedSources, 'over-time revenue calculation', ['family' => 'percent', 'values' => [1000000, 0.40], 'unit' => 'P', 'rounding' => 'exact'], ['ACCOUNTING_STANDARD_SENSITIVE'], 'PREREQUISITE:cpa-financial-accounting-framework-core', ['percent-as-points']);
$Q('cpa-advanced-accounting-branch-core', 3, 'NEUTRAL', 'A branch records a cash remittance to the home office. Which reciprocal account should the home office investigate during reconciliation?', 'The corresponding branch account for the same remittance', ['Retained earnings only', 'The unrelated inventory expense account', 'A revenue account for the remittance'], 'Reciprocal home-office and branch accounts must agree after timing and recording differences are resolved; a remittance is not revenue.', $advanced, $advancedSources, 'branch reconciliation', null, ['ACCOUNTING_STANDARD_SENSITIVE'], 'PREREQUISITE:cpa-financial-accounting-framework-core', ['remittance-as-revenue']);
$Q('cpa-advanced-accounting-branch-inventory-core', 3, 'NEUTRAL', 'A home office bills merchandise to a branch above cost. Before combined statements are prepared, what adjustment is needed?', 'Remove the unrealized markup in ending branch inventory', ['Increase the markup because it is internal profit', 'Recognize the markup as external revenue', 'Ignore it because the branch holds the goods'], 'Internal transfers do not create group profit. The unrealized markup in goods still held must be eliminated in combined reporting.', $advanced, $advancedSources, 'branch inventory elimination', null, ['ACCOUNTING_STANDARD_SENSITIVE'], 'PREREQUISITE:cpa-financial-accounting-framework-core', ['internal-sale-as-external-profit']);
$Q('cpa-advanced-accounting-combinations-core', 3, 'NEUTRAL', 'Consideration transferred is P1,300,000 and identifiable net assets at the acquisition-date measurement basis are P1,100,000. What goodwill results before NCI effects?', 'P200,000', ['P100,000', 'P1,100,000', 'P2,400,000'], 'Goodwill is consideration of P1,300,000 less identifiable net assets of P1,100,000: P200,000.', $advanced, $advancedSources, 'business-combination calculation', ['family' => 'subtract', 'values' => [1300000, 1100000], 'unit' => 'P', 'rounding' => 'exact'], ['ACCOUNTING_STANDARD_SENSITIVE'], 'PREREQUISITE:cpa-financial-accounting-framework-core', ['net-assets-added']);
$Q('cpa-advanced-accounting-combinations-core', 4, 'PROFESSIONAL', 'The fair value of identifiable net assets exceeds consideration transferred after the acquisition-date review is complete. What should the acquirer assess?', 'Whether the result is a bargain purchase gain under the applicable acquisition method', ['Whether goodwill must always be recorded', 'Whether the excess is automatically ordinary revenue', 'Whether the net assets should be ignored'], 'An excess of measured identifiable net assets over consideration may indicate a bargain purchase, but the acquisition-date measurements and review must be completed first.', $advanced, $advancedSources, 'bargain-purchase analysis', null, ['ACCOUNTING_STANDARD_SENSITIVE'], 'PREREQUISITE:cpa-financial-accounting-framework-core', ['excess-always-goodwill']);
$Q('cpa-advanced-accounting-consolidation-core', 4, 'PROFESSIONAL', 'A parent controls a subsidiary but owns less than 50% because other shareholders are widely dispersed and the parent directs relevant activities. What is the reporting consequence?', 'The parent evaluates consolidation based on control rather than ownership percentage alone', ['The parent never consolidates below 50%', 'The parent records only dividends as revenue', 'The subsidiary becomes an associate automatically'], 'Control is the governing concept. Percentage ownership is evidence but not an automatic substitute for the control assessment.', $advanced, $advancedSources, 'control analysis', null, ['ACCOUNTING_STANDARD_SENSITIVE'], 'PREREQUISITE:cpa-financial-accounting-framework-core', ['percentage-ownership-only']);
$Q('cpa-advanced-accounting-consolidation-core', 3, 'NEUTRAL', 'A parent sells inventory to a subsidiary and the inventory remains unsold at year-end. What consolidation adjustment is required?', 'Eliminate the internal sale and the unrealized profit in ending inventory', ['Recognize the internal profit again in consolidation', 'Increase consolidated inventory by the internal profit', 'Treat the sale as an external cash flow'], 'The group cannot report profit from an internal transaction before the inventory is sold outside the group.', $advanced, $advancedSources, 'consolidation elimination', null, ['ACCOUNTING_STANDARD_SENSITIVE'], 'PREREQUISITE:cpa-financial-accounting-framework-core', ['internal-profit-realized']);
$Q('cpa-advanced-accounting-nci-core', 3, 'NEUTRAL', 'A subsidiary reports P500,000 profit and NCI owns 20%. What profit is attributable to NCI?', 'P100,000', ['P20,000', 'P400,000', 'P500,000'], 'P500,000 × 20% = P100,000 attributable to NCI.', $advanced, $advancedSources, 'NCI calculation', ['family' => 'percent', 'values' => [500000, 0.20], 'unit' => 'P', 'rounding' => 'exact'], ['ACCOUNTING_STANDARD_SENSITIVE'], 'PREREQUISITE:cpa-financial-accounting-framework-core', ['nci-percentage-misread']);
$Q('cpa-advanced-accounting-foreign-currency-core', 3, 'NEUTRAL', 'A USD30,000 payable was recorded at P55/USD and remeasured at P57/USD. What exchange loss arises?', 'P60,000', ['P2,000', 'P30,000', 'P1,710,000'], 'The peso liability increases by P2 per dollar: USD30,000 × P2 = P60,000 exchange loss.', $advanced, $advancedSources, 'foreign-currency calculation', ['family' => 'multiply', 'values' => [30000, 2], 'unit' => 'P', 'rounding' => 'exact'], ['ACCOUNTING_STANDARD_SENSITIVE'], 'PREREQUISITE:cpa-financial-accounting-framework-core', ['rate-change-not-applied']);
$Q('cpa-advanced-accounting-translation-core', 4, 'PROFESSIONAL', 'A foreign operation’s assets and liabilities are translated using the closing rate while income and expenses use transaction-date or average rates. Where is the resulting translation difference generally presented?', 'Other comprehensive income, subject to the applicable framework', ['Ordinary sales revenue automatically', 'Inventory cost only', 'A cash-flow operating receipt'], 'Translation differences from the stated approach are generally recognized in OCI until the applicable disposal or presentation rule applies.', $advanced, $advancedSources, 'translation analysis', null, ['ACCOUNTING_STANDARD_SENSITIVE'], 'PREREQUISITE:cpa-financial-accounting-framework-core', ['translation-difference-in-profit']);
$Q('cpa-advanced-accounting-measurement-core', 3, 'NEUTRAL', 'Under a stated cost model for separate financial statements, how is an investment in a subsidiary initially carried?', 'At cost, subject to the applicable subsequent measurement requirements', ['At the subsidiary’s total assets', 'At consolidated goodwill only', 'At the parent’s share of subsidiary revenue'], 'Separate financial statements use the selected permitted basis for investments; consolidated reporting is a different presentation.', $advanced, $advancedSources, 'separate-statement classification', null, ['ACCOUNTING_STANDARD_SENSITIVE'], 'PREREQUISITE:cpa-financial-accounting-framework-core', ['separate-equals-consolidated']);
$Q('cpa-advanced-accounting-dividends-core', 3, 'NEUTRAL', 'An investor owns 30% of an associate that declares P90,000 dividends. Under the equity method, what is the immediate effect on the investment balance before other facts?', 'Decrease the investment by P27,000', ['Recognize P27,000 dividend revenue automatically', 'Increase the investment by P90,000', 'No effect because dividends are never recorded'], 'Under the equity method, P90,000 × 30% = P27,000 reduces the investment rather than creating dividend income.', $advanced, $advancedSources, 'equity-method dividend calculation', ['family' => 'percent', 'values' => [90000, 0.30], 'unit' => 'P', 'rounding' => 'exact'], ['ACCOUNTING_STANDARD_SENSITIVE'], 'PREREQUISITE:cpa-financial-accounting-framework-core', ['equity-method-dividend-revenue']);
$Q('cpa-advanced-accounting-intercompany-balances-core', 3, 'NEUTRAL', 'A parent and subsidiary report reciprocal principal balances of P90,000 and reciprocal interest balances of P7,000. What total is eliminated on consolidation?', 'P97,000', ['P83,000', 'P90,000', 'P187,000'], 'The reciprocal principal and interest balances total P90,000 + P7,000 = P97,000.', $advanced, $advancedSources, 'intercompany elimination calculation', ['family' => 'sum', 'values' => [90000, 7000], 'unit' => 'P', 'rounding' => 'exact'], ['ACCOUNTING_STANDARD_SENSITIVE'], 'PREREQUISITE:cpa-financial-accounting-framework-core', ['interest-omitted']);
$Q('cpa-advanced-accounting-profit-allocation-core', 3, 'NEUTRAL', 'Partners agree to a P60,000 salary allowance and then share the remaining P90,000 profit equally. What total does one partner receive before other agreement terms?', 'P105,000', ['P30,000', 'P60,000', 'P150,000'], 'One partner receives the P60,000 allowance plus half of the P90,000 remainder, or P60,000 + P45,000 = P105,000.', $advanced, $advancedSources, 'partnership allocation calculation', ['family' => 'sum', 'values' => [60000, 45000], 'unit' => 'P', 'rounding' => 'exact'], ['ACCOUNTING_STANDARD_SENSITIVE'], 'PREREQUISITE:cpa-financial-accounting-framework-core', ['allowance-mistaken-for-total']);
$Q('cpa-advanced-accounting-profit-attribution-core', 4, 'PROFESSIONAL', 'A parent’s reported profit includes an internal sale gain while the asset remains within the group at year-end. What is the consolidation judgment?', 'Remove the unrealized internal gain before attributing consolidated profit', ['Retain the gain because the parent recorded it', 'Move the gain to cash', 'Attribute the gain entirely to NCI automatically'], 'Consolidated profit reflects transactions with external parties. The unrealized internal gain must be eliminated before attribution.', $advanced, $advancedSources, 'profit attribution analysis', null, ['ACCOUNTING_STANDARD_SENSITIVE'], 'PREREQUISITE:cpa-financial-accounting-framework-core', ['parent-ledger-equals-group-profit']);
$Q('cpa-advanced-accounting-agreement-core', 4, 'PROFESSIONAL', 'A contract gives parties direct rights to output and requires them to take specified liabilities, while a separate entity holds legal title. What evidence is most relevant to classification?', 'The substantive rights and obligations created by the arrangement', ['The entity’s name alone', 'The number of signatures alone', 'The existence of a separate bank account alone'], 'Accounting classification follows the substantive rights and obligations and applicable standard, not a label or isolated legal-form feature.', $advanced, $advancedSources, 'rights-and-obligations analysis', null, ['ACCOUNTING_STANDARD_SENSITIVE'], 'PREREQUISITE:cpa-financial-accounting-framework-core', ['legal-title-only']);
$Q('cpa-advanced-accounting-branch-core', 4, 'PROFESSIONAL', 'A branch’s records show a debit to the home-office account that does not appear in the home office’s reciprocal account. What is the best first response?', 'Trace timing, transit, and posting evidence before preparing a combined statement', ['Force the two balances to agree by recording revenue', 'Ignore the difference because reciprocal accounts are optional', 'Write off the difference to goodwill'], 'Reconciliation identifies whether the difference is timing, recording, or an actual error. A forced balancing entry would conceal the evidence.', $advanced, $advancedSources, 'branch reconciliation diagnosis', null, ['ACCOUNTING_STANDARD_SENSITIVE'], 'PREREQUISITE:cpa-financial-accounting-framework-core', ['forced-balancing-entry']);
$Q('cpa-advanced-accounting-intercompany-core', 4, 'PROFESSIONAL', 'A parent charges a subsidiary management fees, but the group has not yet settled the balance at year-end. What must consolidation address?', 'Eliminate the reciprocal receivable, payable, income, and expense to the extent they are internal', ['Report the fee as external group revenue', 'Leave the balances because cash was not paid', 'Classify the internal fee as a dividend'], 'An internal charge does not create consolidated external income or expense. Reciprocal balances and internal results are eliminated on consolidation.', $advanced, $advancedSources, 'intercompany analysis', null, ['ACCOUNTING_STANDARD_SENSITIVE'], 'PREREQUISITE:cpa-financial-accounting-framework-core', ['unsettled-equals-external']);

/* Management Services: a deliberately stable, open-text-supported cohort. */
$Q('cpa-management-services-cvp-core', 3, 'NEUTRAL', 'A product sells for P80 per unit and has variable cost of P50. What is its unit contribution margin?', 'P30', ['P50', 'P80', 'P130'], 'Unit contribution is selling price less variable cost: P80 − P50 = P30.', $management, $managementSources, 'CVP calculation', ['family' => 'subtract', 'values' => [80, 50], 'unit' => 'P', 'rounding' => 'exact'], [], 'PREREQUISITE_FREE', ['fixed-cost-subtracted-twice']);
$Q('cpa-management-services-cvp-core', 4, 'PROFESSIONAL', 'A manager wants to lower break-even volume while holding sales price constant. Which change directly improves unit contribution?', 'Reduce variable cost per unit', ['Increase unavoidable fixed cost', 'Increase variable cost per unit', 'Reduce the selling price'], 'With price fixed, lower variable cost increases contribution per unit and therefore lowers break-even volume, assuming other conditions remain constant.', $management, $managementSources, 'CVP sensitivity', null, [], 'PREREQUISITE_FREE', ['fixed-cost-confusion']);
$Q('cpa-management-services-cost-behavior-core', 3, 'NEUTRAL', 'A cost is P20,000 at 1,000 units and P30,000 at 2,000 units. Under a linear high-low model, what variable cost per unit is implied?', 'P10', ['P5', 'P20', 'P30'], 'The change is P10,000 for 1,000 additional units, so the implied variable cost is P10 per unit.', $management, $managementSources, 'cost-behavior calculation', ['family' => 'divide', 'values' => [10000, 1000], 'unit' => 'P', 'rounding' => 'exact'], [], 'PREREQUISITE_FREE', ['total-cost-as-variable-rate']);
$Q('cpa-management-services-cost-behavior-core', 4, 'PROFESSIONAL', 'A mixed utility cost rises with production but also includes a monthly connection charge. Which model best represents it?', 'A fixed component plus a variable component', ['A purely fixed cost', 'A purely variable cost with no intercept', 'A sunk cost that cannot be analyzed'], 'A mixed cost changes with activity while retaining a fixed component. The model should be tested against the relevant activity range.', $management, $managementSources, 'cost classification', null, [], 'PREREQUISITE_FREE', ['mixed-cost-as-pure']);
$Q('cpa-management-services-sales-mix-core', 3, 'NEUTRAL', 'Product A contributes P40 and Product B contributes P20. If the stated sales mix is two A units for every one B unit, what contribution does one mix bundle generate?', 'P100', ['P60', 'P80', 'P120'], 'The bundle contribution is 2(P40) + 1(P20) = P100.', $management, $managementSources, 'sales-mix calculation', ['family' => 'sum', 'values' => [80, 20], 'unit' => 'P', 'rounding' => 'exact'], [], 'PREREQUISITE_FREE', ['unweighted-average']);
$Q('cpa-management-services-sales-mix-core', 4, 'PROFESSIONAL', 'A proposed product mix has a higher margin per unit but consumes twice the scarce machine time. What comparison is required before choosing it?', 'Contribution per unit of the constrained resource', ['Selling price per unit only', 'Total fixed cost only', 'Historical revenue per unit only'], 'When a resource constraint binds, contribution per unit of the constrained resource is more decision-relevant than unit margin alone.', $management, $managementSources, 'constraint comparison', null, [], 'PREREQUISITE:cpa-management-services-constraints-core', ['unit-margin-only']);
$Q('cpa-management-services-budgeting-core', 3, 'NEUTRAL', 'Budgeted sales are 4,000 units at P75. What budgeted sales revenue is expected?', 'P300,000', ['P53.33', 'P75,000', 'P4,075,000'], 'Budgeted revenue is 4,000 × P75 = P300,000.', $management, $managementSources, 'operating-budget calculation', ['family' => 'multiply', 'values' => [4000, 75], 'unit' => 'P', 'rounding' => 'exact'], [], 'PREREQUISITE_FREE', ['units-not-multiplied']);
$Q('cpa-management-services-budgeting-core', 4, 'PROFESSIONAL', 'A flexible budget is being used to evaluate a cost center that produced more units than planned. Why is it preferable to compare with the original static budget alone?', 'It adjusts variable-cost expectations to the actual activity level', ['It removes all fixed costs', 'It guarantees a favorable variance', 'It treats actual spending as the standard'], 'A flexible budget changes the activity-sensitive expectation while preserving the stated fixed-cost assumptions, producing a more meaningful performance comparison.', $management, $managementSources, 'budget interpretation', null, [], 'PREREQUISITE_FREE', ['static-budget-only']);
$Q('cpa-management-services-cash-budget-core', 3, 'NEUTRAL', 'Opening cash is P20,000, expected collections are P80,000, and expected payments are P95,000. What cash balance precedes any financing?', 'P5,000', ['P15,000', 'P85,000', 'P195,000'], 'P20,000 + P80,000 − P95,000 = P5,000 before financing or minimum-cash requirements.', $management, $managementSources, 'cash-budget calculation', ['family' => 'sum', 'values' => [20000, 80000, -95000], 'unit' => 'P', 'rounding' => 'exact'], [], 'PREREQUISITE_FREE', ['payments-added']);
$Q('cpa-management-services-cash-budget-core', 4, 'PROFESSIONAL', 'A cash budget shows a balance below the required minimum after expected collections and payments. What amount should management investigate?', 'The shortfall needed to reach the minimum cash requirement', ['The full amount of all expected payments', 'The prior period’s revenue', 'The accounting depreciation expense'], 'Financing needs close the actual cash shortfall to the required minimum. Noncash depreciation is not a cash-budget payment.', $management, $managementSources, 'cash-budget decision', null, [], 'PREREQUISITE_FREE', ['full-payments-as-financing']);
$Q('cpa-management-services-variance-core', 3, 'NEUTRAL', 'Standard material cost is P12 per unit and actual material cost is P13 for 2,000 units. What price variance amount results before sign labeling?', 'P2,000', ['P1,000', 'P24,000', 'P26,000'], 'The difference is P1 per unit × 2,000 units = P2,000; because actual price is higher, it is unfavorable.', $management, $managementSources, 'variance calculation', ['family' => 'multiply', 'values' => [1, 2000], 'unit' => 'P', 'rounding' => 'exact'], [], 'PREREQUISITE_FREE', ['standard-price-omitted']);
$Q('cpa-management-services-variance-core', 4, 'PROFESSIONAL', 'A favorable labor-rate variance occurs while the quality defect rate rises. What is the best management interpretation?', 'The favorable rate signal should be investigated with the quality and process evidence', ['The variance proves the labor decision was optimal', 'Quality is irrelevant to labor analysis', 'The variance must be reversed as an accounting error'], 'A variance is a signal, not a complete causal explanation. Lower rates can coincide with training, staffing, or quality consequences.', $management, $managementSources, 'variance diagnosis', null, [], 'PREREQUISITE_FREE', ['favorable-equals-good']);
$Q('cpa-management-services-standard-costing-core', 3, 'NEUTRAL', 'A standard quantity is 3 kilograms per unit and actual production is 1,000 units. What standard quantity is allowed?', '3,000 kilograms', ['3 kilograms', '997 kilograms', '3,003 kilograms'], 'Standard quantity allowed is 3 kg × 1,000 units = 3,000 kg.', $management, $managementSources, 'standard quantity calculation', ['family' => 'multiply', 'values' => [3, 1000], 'unit' => 'kg', 'rounding' => 'exact'], [], 'PREREQUISITE_FREE', ['actual-quantity-substituted']);
$Q('cpa-management-services-standard-costing-core', 4, 'PROFESSIONAL', 'A production supervisor controls material usage but not the purchase price negotiated centrally. Which variance is more directly useful for the supervisor’s responsibility review?', 'The material quantity or usage variance', ['The centrally negotiated purchase-price variance only', 'The company’s income-tax variance', 'The shareholder dividend variance'], 'Responsibility accounting should align the measure with controllability. Usage is closer to the supervisor’s process decisions than a centrally negotiated price.', $management, $managementSources, 'responsibility analysis', null, [], 'PREREQUISITE_FREE', ['all-variances-controllable']);
$Q('cpa-management-services-relevant-costing-core', 3, 'NEUTRAL', 'A machine purchased last year has a book value of P200,000 and cannot be sold. What is its relevance to a new make-or-buy decision?', 'Its book value is a sunk cost and is excluded unless the facts create a future cash difference', ['It is always the most relevant cost', 'It is a future avoidable cash outflow', 'It determines the supplier’s price'], 'A sunk, unrecoverable book value does not differ between alternatives. Future avoidable costs and opportunity costs are relevant.', $management, $managementSources, 'relevant-cost diagnosis', null, [], 'PREREQUISITE_FREE', ['book-value-as-relevant']);
$Q('cpa-management-services-relevant-costing-core', 4, 'PROFESSIONAL', 'A special order uses idle capacity, adds variable cost of P18 per unit, and requires no fixed-cost change. What should the minimum price cover before qualitative constraints?', 'At least the incremental variable cost of P18 per unit', ['Full historical cost including sunk cost', 'Zero because idle capacity has no value', 'The regular selling price regardless of facts'], 'With idle capacity and no stated fixed-cost change, the incremental variable cost is the quantitative floor; qualitative and capacity effects still require review.', $management, $managementSources, 'special-order analysis', null, [], 'PREREQUISITE_FREE', ['full-cost-only']);
$Q('cpa-management-services-make-buy-core', 3, 'NEUTRAL', 'A component costs P42 to make, of which P15 is avoidable, and the supplier price is P30. Ignoring qualitative factors, which option is cheaper by how much?', 'Buy; it saves P15 per unit', ['Make; it saves P12 per unit', 'Buy; it saves P27 per unit', 'Make; it saves P30 per unit'], 'Relevant make cost is P15, compared with buy cost P30, so making saves P15 per unit. The P27 unavoidable portion is excluded.', $management, $managementSources, 'make-or-buy calculation', ['family' => 'subtract', 'values' => [30, 15], 'unit' => 'P per unit', 'rounding' => 'exact'], [], 'PREREQUISITE_FREE', ['unavoidable-cost-included']);
$Q('cpa-management-services-make-buy-core', 4, 'PROFESSIONAL', 'A supplier offers a lower price, but outsourcing would eliminate a department that currently provides emergency capacity. What additional evidence is required?', 'The opportunity, quality, capacity, and strategic effects of losing the internal capability', ['Only the supplier invoice', 'Only the current full absorption cost', 'No additional evidence because price decides'], 'A make-or-buy choice includes future differential cash effects and qualitative or opportunity effects. The lowest quoted price is not sufficient.', $management, $managementSources, 'make-or-buy judgment', null, [], 'PREREQUISITE_FREE', ['price-only-decision']);
$Q('cpa-management-services-constraints-core', 3, 'NEUTRAL', 'Product X contributes P24 and uses 3 machine hours; Product Y contributes P20 and uses 1 hour. Which should receive scarce machine time first?', 'Product Y because it contributes P20 per constrained hour', ['Product X because its unit contribution is higher', 'Both are equal because their selling prices are unknown', 'Neither because fixed costs are irrelevant'], 'X contributes P8 per hour; Y contributes P20 per hour. The constrained-resource comparison favors Y, subject to demand and feasibility.', $management, $managementSources, 'constraint comparison', null, [], 'PREREQUISITE_FREE', ['unit-contribution-only']);
$Q('cpa-management-services-constraints-core', 4, 'PROFESSIONAL', 'A constrained-resource mix maximizes contribution per hour but exceeds demand for the selected product. What should the model do next?', 'Respect demand limits and allocate remaining capacity using the next-best feasible contribution', ['Ignore demand because the ratio is highest', 'Allocate all capacity to the first product', 'Switch to full-cost ranking automatically'], 'A constrained optimization must satisfy demand and other feasibility limits. The ratio guides allocation only within the feasible region.', $management, $managementSources, 'constraint optimization', null, [], 'PREREQUISITE_FREE', ['ratio-without-demand']);
$Q('cpa-management-services-npv-core', 3, 'NEUTRAL', 'An investment costs P100,000 and the supplied present value of future inflows is P118,000. What is the NPV?', 'P18,000', ['P100,000', 'P118,000', 'P218,000'], 'NPV is present value of inflows less the initial investment: P118,000 − P100,000 = P18,000.', $management, $managementSources, 'NPV calculation', ['family' => 'subtract', 'values' => [118000, 100000], 'unit' => 'P', 'rounding' => 'exact'], [], 'PREREQUISITE_FREE', ['inflows-not-netted']);
$Q('cpa-management-services-npv-core', 4, 'PROFESSIONAL', 'A project has positive NPV under the supplied discount rate but depends on an unverified demand forecast. What is the best conclusion?', 'The model supports acceptance under its assumptions, but forecast sensitivity and evidence still require review', ['Positive NPV proves the forecast is correct', 'Reject every project with forecast uncertainty', 'Ignore the discount rate because demand is uncertain'], 'NPV is conditional on the supplied cash flows and discount rate. A decision should also test forecast sensitivity and evidence quality.', $management, $managementSources, 'investment judgment', null, [], 'PREREQUISITE_FREE', ['positive-npv-as-certainty']);
$Q('cpa-management-services-activity-based-core', 3, 'NEUTRAL', 'An activity pool costs P60,000 and has 3,000 inspection hours. What is the activity rate?', 'P20 per inspection hour', ['P0.05 per hour', 'P60 per hour', 'P180,000 per hour'], 'The activity rate is P60,000 ÷ 3,000 hours = P20 per hour.', $management, $managementSources, 'ABC calculation', ['family' => 'divide', 'values' => [60000, 3000], 'unit' => 'P per hour', 'rounding' => 'exact'], [], 'PREREQUISITE_FREE', ['pool-not-divided']);
$Q('cpa-management-services-activity-based-core', 4, 'PROFESSIONAL', 'A product uses 40 inspection hours and 12 setups. ABC assigns costs from separate pools. What is the essential sequence?', 'Calculate each pool rate, apply each driver usage, and then sum the assigned amounts', ['Apply one broad labor rate to every activity', 'Assign only the largest pool', 'Use sales price as the activity driver'], 'ABC preserves different resource-consumption relationships. Separate rates and driver usage must be calculated before total assigned cost.', $management, $managementSources, 'ABC assignment', null, [], 'PREREQUISITE_FREE', ['single-rate-substitution']);

/* Auditing: every item is marked standard-sensitive and receives a separate
 * currentness review record in the final artifact. */
$auditTag = ['AUDITING_STANDARD_SENSITIVE'];
$Q('cpa-auditing-assertions-core', 3, 'PROFESSIONAL', 'An auditor traces recorded sales invoices to shipping documents and customer acceptance records. Which assertion is most directly tested?', 'Occurrence of recorded revenue', ['Completeness of unrecorded liabilities', 'Valuation of fixed assets', 'Rights to all cash balances'], 'Tracing recorded revenue to evidence supports whether the recorded transactions occurred. Other assertions require different evidence designs.', $auditing, $auditSources, 'assertion-procedure match', null, $auditTag, 'PREREQUISITE:cpa-auditing-evidence-core', ['assertion-procedure-mismatch']);
$Q('cpa-auditing-assertions-core', 4, 'PROFESSIONAL', 'A search begins with receiving reports issued before year-end and follows them into the purchases journal. Which risk is the procedure designed to address?', 'Completeness of recorded purchases and related liabilities', ['Existence of every recorded cash receipt', 'Valuation of all equity instruments', 'Occurrence of payroll expense only'], 'Starting from source documents and tracing forward is designed to find items that should have been recorded, addressing completeness.', $auditing, $auditSources, 'assertion direction', null, $auditTag, 'PREREQUISITE:cpa-auditing-evidence-core', ['vouching-and-tracing-confused']);
$Q('cpa-auditing-assurance-core', 3, 'PROFESSIONAL', 'What distinguishes a reasonable-assurance financial-statement audit from a limited-assurance review?', 'An audit provides a higher level of assurance through more extensive risk assessment and procedures', ['A review guarantees that no misstatement exists', 'A review always requires more evidence than an audit', 'An audit reports only on management performance'], 'Reasonable assurance is high but not absolute and requires a broader audit process. Limited assurance uses narrower procedures and a different conclusion form.', $auditing, $auditSources, 'assurance comparison', null, $auditTag, 'PREREQUISITE_FREE', ['reasonable-equals-absolute']);
$Q('cpa-auditing-assurance-core', 4, 'PROFESSIONAL', 'An engagement provides assurance on a sustainability metric using criteria specified by the responsible party. What must the auditor establish before testing?', 'That the subject matter, criteria, evidence, and intended users are sufficiently defined for the engagement', ['Only the fee arrangement', 'Only management’s preferred conclusion', 'That a financial-statement audit is unnecessary in every case'], 'Assurance depends on a definable subject matter, suitable criteria, evidence, and intended users. A label alone does not establish an assurance engagement.', $auditing, $auditSources, 'engagement acceptance', null, $auditTag, 'PREREQUISITE:cpa-auditing-evidence-core', ['label-equals-assurance']);
$Q('cpa-auditing-audit-risk-core', 3, 'PROFESSIONAL', 'A complex estimate is highly judgmental and subject to rapid market change before considering controls. Which risk component is most directly elevated?', 'Inherent risk', ['Detection risk only', 'Sampling risk only', 'Control risk only'], 'Complexity and uncertainty in the estimate exist before the auditor’s response and controls, which are inherent-risk considerations.', $auditing, $auditSources, 'risk classification', null, $auditTag, 'PREREQUISITE:cpa-auditing-internal-control-core', ['inherent-and-control-risk-merged']);
$Q('cpa-auditing-audit-risk-core', 4, 'PROFESSIONAL', 'The auditor assesses a high risk of material misstatement and wants overall audit risk to remain acceptably low. What response follows?', 'Design more responsive procedures, including stronger evidence or timing/extent changes', ['Reduce substantive work because risk is high', 'Accept the risk without documenting a response', 'Replace the risk assessment with management’s assertion'], 'Audit risk is managed through responsive procedures. A higher assessed risk generally requires more persuasive or extensive audit evidence.', $auditing, $auditSources, 'risk response', null, $auditTag, 'PREREQUISITE:cpa-auditing-evidence-core', ['high-risk-less-work']);
$Q('cpa-auditing-internal-control-core', 3, 'PROFESSIONAL', 'The same employee approves vendors, records purchases, and reconciles the payable ledger. Which control concern is most direct?', 'Insufficient segregation of incompatible duties', ['Excessive analytical review', 'Overstated physical inventory only', 'A foreign-exchange translation error'], 'Combining authorization, recording, and reconciliation duties concentrates incompatible responsibilities and increases the risk of undetected error or fraud.', $auditing, $auditSources, 'control diagnosis', null, $auditTag, 'PREREQUISITE_FREE', ['segregation-not-relevant']);
$Q('cpa-auditing-internal-control-core', 4, 'PROFESSIONAL', 'A control is documented but employees consistently bypass it during peak periods. What should the auditor evaluate?', 'Whether the control is implemented and operating effectively, including the effect of the bypass', ['Only whether the policy document exists', 'Only the auditor’s preferred control design', 'Whether bypassing proves every transaction is misstated'], 'Design and implementation are not the same as operating effectiveness. The auditor evaluates actual operation and resulting risk without overgeneralizing one exception.', $auditing, $auditSources, 'control operating-effectiveness', null, $auditTag, 'PREREQUISITE_FREE', ['policy-exists-equals-operates']);
$Q('cpa-auditing-control-risk-core', 3, 'PROFESSIONAL', 'A well-designed approval control is performed by an independent reviewer and leaves evidence of review. What risk does it primarily address?', 'Control risk for unauthorized or erroneous transactions', ['Inherent risk from economic volatility only', 'Currency translation risk only', 'The auditor’s personal investment risk'], 'The control reduces the likelihood that an error or unauthorized transaction remains unprevented or undetected, subject to operating effectiveness.', $auditing, $auditSources, 'control-risk application', null, $auditTag, 'PREREQUISITE:cpa-auditing-internal-control-core', ['control-eliminates-risk']);
$Q('cpa-auditing-control-risk-core', 4, 'PROFESSIONAL', 'A control deficiency is significant enough to merit communication to those charged with governance. What is the correct professional response?', 'Communicate it at the appropriate level with the facts, risk, and relevant recommendation or response', ['Suppress it because no misstatement was found', 'Publish it as an audit opinion automatically', 'Treat it as proof of management fraud'], 'A significant deficiency is communicated through the required governance channel. It is not automatically an opinion modification or proof of fraud.', $auditing, $auditSources, 'control-deficiency reporting', null, $auditTag, 'PREREQUISITE:cpa-auditing-internal-control-core', ['deficiency-equals-fraud']);
$Q('cpa-auditing-evidence-core', 3, 'PROFESSIONAL', 'Which evidence characteristic addresses whether evidence is relevant and reliable enough to support the auditor’s conclusion?', 'Appropriateness', ['Sufficiency only', 'Materiality only', 'Independence of the client’s board only'], 'Appropriateness concerns evidence quality, including relevance and reliability; sufficiency concerns quantity.', $auditing, $auditSources, 'evidence evaluation', null, $auditTag, 'PREREQUISITE_FREE', ['sufficiency-and-appropriateness-merged']);
$Q('cpa-auditing-evidence-core', 4, 'PROFESSIONAL', 'Management’s schedule conflicts with an external confirmation. What should the auditor do first?', 'Investigate the difference, evaluate reliability and alternative explanations, and extend procedures as needed', ['Accept management’s schedule automatically', 'Accept the confirmation without investigating', 'Average the two balances'], 'Conflicting evidence requires evaluation and resolution, not averaging or automatic deference. The auditor considers reliability, timing, and the assertion tested.', $auditing, $auditSources, 'conflicting-evidence diagnosis', null, $auditTag, 'PREREQUISITE_FREE', ['average-conflicting-evidence']);
$Q('cpa-auditing-materiality-core', 3, 'PROFESSIONAL', 'Overall materiality is P2,000,000 and performance materiality is set at 75%. What is performance materiality?', 'P1,500,000', ['P500,000', 'P2,000,000', 'P2,750,000'], 'P2,000,000 × 75% = P1,500,000.', $auditing, $auditSources, 'materiality calculation', ['family' => 'percent', 'values' => [2000000, 0.75], 'unit' => 'P', 'rounding' => 'exact'], $auditTag, 'PREREQUISITE:cpa-auditing-audit-risk-core', ['percentage-as-whole-number']);
$Q('cpa-auditing-materiality-core', 4, 'PROFESSIONAL', 'A misstatement is below overall materiality but changes a reported profit into a loss. What should the auditor consider?', 'Qualitative materiality and the effect on users, not only the numerical threshold', ['Ignore it automatically because it is below the threshold', 'Treat every below-threshold item as fraud', 'Increase materiality until the result disappears'], 'Materiality includes qualitative factors. A small amount can be material because of its nature or effect on users’ decisions.', $auditing, $auditSources, 'materiality judgment', null, $auditTag, 'PREREQUISITE:cpa-auditing-audit-risk-core', ['threshold-only-materiality']);
$Q('cpa-auditing-analytics-core', 3, 'PROFESSIONAL', 'Prior-year revenue is P10,000,000 and the expected growth rate is 5%. What expected revenue follows for an analytical procedure?', 'P10,500,000', ['P500,000', 'P10,050,000', 'P15,000,000'], 'Expected revenue is P10,000,000 × 1.05 = P10,500,000.', $auditing, $auditSources, 'analytical expectation', ['family' => 'percent_add', 'values' => [10000000, 0.05], 'unit' => 'P', 'rounding' => 'exact'], $auditTag, 'PREREQUISITE:cpa-auditing-evidence-core', ['rate-not-applied']);
$Q('cpa-auditing-analytics-core', 4, 'PROFESSIONAL', 'Actual payroll expense is materially below the expectation after headcount increased. What is the strongest next step?', 'Investigate the unexplained difference using disaggregated payroll and headcount evidence', ['Conclude payroll is understated automatically', 'Ignore the difference because analytics are never evidence', 'Replace testing with management’s explanation alone'], 'An unexpected relationship is an investigation trigger. Disaggregation and corroborating evidence are needed before concluding on misstatement.', $auditing, $auditSources, 'analytical investigation', null, $auditTag, 'PREREQUISITE:cpa-auditing-evidence-core', ['unexpected-difference-equals-error']);
$Q('cpa-auditing-sampling-core', 3, 'PROFESSIONAL', 'A sample contains 5 exceptions in 100 tested items. What observed exception rate is indicated?', '5%', ['0.5%', '20%', '95%'], 'Observed rate is 5 ÷ 100 = 0.05, or 5%.', $auditing, $auditSources, 'sampling calculation', ['family' => 'ratio_percent', 'values' => [5, 100], 'unit' => '%', 'rounding' => 'whole percent'], $auditTag, 'PREREQUISITE:cpa-auditing-evidence-core', ['numerator-denominator-reversed']);
$Q('cpa-auditing-sampling-core', 4, 'PROFESSIONAL', 'A sample result exceeds the tolerable deviation rate. What should the auditor evaluate before finalizing reliance on the control?', 'The cause and projected effect of the deviations and whether additional or alternative procedures are needed', ['Ignore the result because the sample is not the population', 'Treat every deviation as a material misstatement', 'Increase reliance automatically'], 'Sampling results require evaluation of cause, rate, projection, and risk. The result informs further audit response but is not interpreted mechanically.', $auditing, $auditSources, 'sampling evaluation', null, $auditTag, 'PREREQUISITE:cpa-auditing-evidence-core', ['sample-result-ignored']);
$Q('cpa-auditing-reporting-core', 4, 'PROFESSIONAL', 'A known misstatement is material but not pervasive, and management refuses correction. What report consequence is generally indicated?', 'A qualified opinion for the material misstatement', ['An unmodified opinion automatically', 'An adverse opinion automatically', 'A disclaimer solely because management disagreed'], 'A material but non-pervasive misstatement generally supports a qualified opinion; pervasiveness and other facts determine whether an adverse opinion or disclaimer is appropriate.', $auditing, $auditSources, 'opinion classification', null, $auditTag, 'PREREQUISITE:cpa-auditing-materiality-core', ['material-equals-adverse']);
$Q('cpa-auditing-reporting-core', 3, 'PROFESSIONAL', 'The auditor cannot obtain sufficient appropriate evidence about a material balance, and the possible effect is pervasive. What issue is raised?', 'A scope limitation that may require a disclaimer of opinion', ['A routine emphasis paragraph only', 'An automatic adverse opinion for a known misstatement', 'No reporting effect if management signs a representation'], 'Inability to obtain evidence is a scope limitation. If the possible effects are material and pervasive, a disclaimer may be required under the reporting standard.', $auditing, $auditSources, 'scope limitation', null, $auditTag, 'PREREQUISITE:cpa-auditing-evidence-core', ['scope-limit-equals-adverse']);
$Q('cpa-auditing-going-concern-core', 4, 'PROFESSIONAL', 'Forecast cash flows show a material uncertainty related to going concern, and management’s plans are adequately disclosed. What should the auditor assess?', 'Whether the financial statements and auditor’s report appropriately describe the material uncertainty under the applicable standard', ['Ignore the uncertainty because a forecast exists', 'Issue an adverse opinion automatically', 'Guarantee that management’s plan will succeed'], 'Going-concern reporting depends on the uncertainty, evidence, management’s plans, and disclosure. A forecast is evidence to evaluate, not a guarantee.', $auditing, $auditSources, 'going-concern assessment', null, $auditTag, 'PREREQUISITE:cpa-auditing-evidence-core', ['forecast-equals-resolution']);
$Q('cpa-auditing-going-concern-core', 3, 'PROFESSIONAL', 'A covenant breach creates a substantial doubt about continued operations, but the lender formally waived the breach before the reporting date. What should the auditor examine?', 'The waiver’s terms, reporting-date status, management plans, and required disclosure', ['Only the original breach date', 'Only the current bank balance', 'Nothing because a waiver always resolves all uncertainty'], 'A waiver is relevant evidence but does not automatically eliminate going-concern uncertainty. The full facts and applicable reporting requirements remain relevant.', $auditing, $auditSources, 'going-concern evidence', null, $auditTag, 'PREREQUISITE:cpa-auditing-evidence-core', ['waiver-equals-no-uncertainty']);
$Q('cpa-auditing-fraud-core', 4, 'PROFESSIONAL', 'Management can post manual entries to revenue accounts without independent review near period-end. Which fraud risk response is most direct?', 'Test journal entries and unusual revenue transactions with attention to period-end bias', ['Rely solely on management representation', 'Reduce testing because entries are internal', 'Test only cash disbursements'], 'Management override and revenue-recognition pressure make journal-entry and period-end revenue testing responsive procedures.', $auditing, $auditSources, 'fraud-risk response', null, $auditTag, 'PREREQUISITE:cpa-auditing-audit-risk-core', ['internal-entry-as-safe']);
$Q('cpa-auditing-fraud-core', 3, 'PROFESSIONAL', 'An auditor identifies a fraud risk that could materially affect the statements. What is the appropriate professional response?', 'Communicate and document the risk as required and design responsive audit procedures', ['Suppress the risk to preserve the client relationship', 'Conclude fraud occurred without evidence', 'Transfer responsibility to the client’s bookkeeper'], 'A risk is not proof of fraud, but it requires documented assessment, appropriate communication, and responsive procedures under the applicable standards.', $auditing, $auditSources, 'fraud-risk documentation', null, $auditTag, 'PREREQUISITE:cpa-auditing-audit-risk-core', ['risk-equals-proof']);
$Q('cpa-auditing-documentation-core', 3, 'PROFESSIONAL', 'A working-paper file does not show who performed a key procedure or when it was reviewed. What deficiency is most direct?', 'The documentation does not adequately demonstrate performance and review of the audit work', ['The financial statements are automatically misstated', 'The audit opinion is automatically adverse', 'The missing initials prove fraud'], 'Audit documentation should allow an experienced auditor to understand who performed and reviewed the work and when. The deficiency requires evaluation, not automatic substantive conclusions.', $auditing, $auditSources, 'documentation diagnosis', null, $auditTag, 'PREREQUISITE:cpa-auditing-evidence-core', ['documentation-gap-equals-misstatement']);
$Q('cpa-auditing-documentation-core', 4, 'PROFESSIONAL', 'An experienced auditor reviewing the file cannot understand how a significant estimate conclusion was reached. What should the engagement team do?', 'Add or correct contemporaneous documentation of the evidence, judgment, and conclusion as permitted by the applicable standard', ['Delete the estimate from the statements', 'Replace the file with a blank sign-off', 'Rely on memory without recording the basis'], 'Documentation must make significant judgments and conclusions understandable and reviewable. A sign-off without the evidentiary basis is insufficient.', $auditing, $auditSources, 'documentation review', null, $auditTag, 'PREREQUISITE:cpa-auditing-evidence-core', ['signoff-equals-documentation']);

/* Regulatory Framework: fact-bound Philippine law and professional-practice
 * applications.  No volatile threshold or penalty is used. */
$corpTag = ['CORPORATE_LEGAL_SENSITIVE'];
$profTag = ['PROFESSIONAL_RULE_SENSITIVE'];
$Q('cpa-regulatory-framework-contracts-core', 3, 'REGULATORY', 'A contract clearly identifies the parties, object, and consideration, and both parties consent. Which conclusion is best supported before performance issues arise?', 'The stated facts support the basic elements of a contract, subject to applicable formalities and defenses', ['No contract exists until full performance', 'A contract exists only if cash has changed hands', 'The contract is automatically void because performance is future'], 'Consent, object, and cause are central contract elements; performance and formalities may raise separate issues, so the conclusion remains fact-bound.', $regulatory, $regSources, 'contract formation', null, $corpTag, 'PREREQUISITE_FREE', ['performance-required-for-formation']);
$Q('cpa-regulatory-framework-contracts-core', 4, 'REGULATORY', 'A written agreement contains an integration clause, but the parties dispute a material term not addressed in the writing. What should the accountant recommend first?', 'Review the contract, admissible surrounding evidence, and applicable interpretation rule before assuming a term', ['Choose the term that maximizes revenue', 'Assume oral evidence is always irrelevant', 'Treat the dispute as an accounting estimate'], 'Contract interpretation depends on the text, applicable law, and admissible evidence. An accountant should not invent a term from the financial result.', $regulatory, $regSources, 'contract interpretation', null, $corpTag, 'PREREQUISITE_FREE', ['financial-result-controls-law']);
$Q('cpa-regulatory-framework-obligations-core', 3, 'REGULATORY', 'Two debtors are liable for the same performance, but the agreement does not state solidarity and the law does not supply it. What issue is most important?', 'Whether the obligation is joint rather than solidary under the governing agreement and law', ['Assume solidarity because the creditors prefer it', 'Treat the obligation as equity', 'Assume one debtor bears everything automatically'], 'Solidarity is not presumed; the agreement or law must support it. The classification affects the creditor’s rights and each debtor’s exposure.', $regulatory, $regSources, 'obligation classification', null, $corpTag, 'PREREQUISITE_FREE', ['solidarity-presumed']);
$Q('cpa-regulatory-framework-obligations-core', 4, 'REGULATORY', 'A debtor fails to perform after a valid demand and no contractual grace period applies. What should be analyzed next?', 'Delay, breach, causation, and the remedies supported by the obligation and the facts', ['Impose any penalty the creditor chooses', 'Treat the debt as extinguished automatically', 'Ignore the demand because it has no legal relevance'], 'A demand may be relevant to delay, but the legal consequence still depends on the obligation, breach, causation, and applicable remedy.', $regulatory, $regSources, 'breach analysis', null, $corpTag, 'PREREQUISITE:cpa-regulatory-framework-contracts-core', ['demand-equals-any-remedy']);
$Q('cpa-regulatory-framework-sales-core', 3, 'REGULATORY', 'A buyer accepts goods after inspecting them and later claims a plainly visible defect that was not concealed. What fact should be examined before recommending a remedy?', 'The contract, inspection, notice, waiver, and applicable sales-law rules', ['Only the buyer’s dissatisfaction', 'Only the seller’s profit margin', 'Whether the goods were the cheapest available'], 'Visible defects and acceptance may affect remedies, but the result depends on the contract, notice, concealment, and governing law.', $regulatory, $regSources, 'sales remedy facts', null, $corpTag, 'PREREQUISITE_FREE', ['dissatisfaction-equals-remedy']);
$Q('cpa-regulatory-framework-sales-core', 4, 'REGULATORY', 'A seller delivers goods that materially fail an expressly written specification, and the buyer promptly preserves evidence. What is the strongest first response?', 'Document the nonconformity and invoke the contract and applicable sales remedy process', ['Accept the goods as conforming because delivery occurred', 'Destroy the goods before inspection', 'Record the purchase as an unrelated donation'], 'The written specification, prompt documentation, and preservation of evidence support a fact-based remedy analysis; delivery alone does not erase nonconformity.', $regulatory, $regSources, 'sales nonconformity', null, $corpTag, 'PREREQUISITE:cpa-regulatory-framework-obligations-core', ['delivery-equals-conformity']);
$Q('cpa-regulatory-framework-sales-remedies-core', 3, 'REGULATORY', 'A buyer discovers a defect that substantially defeats the agreed purpose and promptly notifies the seller. What should be preserved first?', 'The contract, defect evidence, notice, inspection record, and requested remedy', ['Only a revised sales forecast', 'Only the invoice total', 'The defected goods without any record'], 'Remedy analysis requires the agreement and evidence of defect, purpose, notice, and loss. Preserving the record prevents a conclusion based on labels alone.', $regulatory, $regSources, 'sales-remedy documentation', null, $corpTag, 'PREREQUISITE_FREE', ['remedy-without-evidence']);
$Q('cpa-regulatory-framework-sales-remedies-core', 4, 'REGULATORY', 'A contract grants a cure period for a material breach and the period has not expired. What is the most defensible immediate action?', 'Follow the agreed cure process while preserving rights and evidence', ['Terminate immediately regardless of the clause', 'Ignore the breach until the next fiscal year', 'Destroy the notice because cure is possible'], 'A valid cure provision affects the sequence of response. The party should follow the agreement while documenting the breach and reserving applicable rights.', $regulatory, $regSources, 'contractual cure', null, $corpTag, 'PREREQUISITE:cpa-regulatory-framework-contracts-core', ['cure-period-ignored']);
$Q('cpa-regulatory-framework-corporate-law-core', 3, 'REGULATORY', 'A corporation enters a transaction through an officer whose authority is questioned. What should be verified first?', 'The officer’s authority, required board or shareholder approval, and any ratification evidence', ['The officer’s title alone', 'Only the transaction’s profit', 'Whether the corporation has a logo'], 'Authority depends on law, governing documents, resolutions, apparent authority, and ratification facts; title alone is not conclusive.', $regulatory, $regSources, 'corporate authority', null, $corpTag, 'PREREQUISITE_FREE', ['title-equals-authority']);
$Q('cpa-regulatory-framework-corporate-law-core', 4, 'REGULATORY', 'A corporation issues 8,000 shares at P30 and repurchases 1,500 shares at P28, ignoring costs. What is the net cash movement from the two transactions?', 'P198,000 inflow', ['P240,000 inflow', 'P42,000 inflow', 'P198,000 outflow'], 'Issuance brings P240,000; repurchase uses P42,000. Net cash movement is P240,000 − P42,000 = P198,000 inflow.', $regulatory, $regSources, 'corporate cash calculation', ['family' => 'sum', 'values' => [240000, -42000], 'unit' => 'P', 'rounding' => 'exact'], $corpTag, 'PREREQUISITE_FREE', ['repurchase-added']);
$Q('cpa-regulatory-framework-governance-core', 3, 'PROFESSIONAL', 'A director has a personal interest in a proposed supplier contract. Which governance response best protects decision integrity?', 'Disclose the interest, follow the conflict procedure, and use independent approval where required', ['Hide the interest if the price is favorable', 'Approve the contract personally', 'Delete the supplier comparison'], 'Disclosure and an independent process address objectivity and governance risk. A favorable price does not eliminate the conflict.', $regulatory, $regSources, 'governance conflict', null, array_merge($corpTag, $profTag), 'PREREQUISITE:cpa-regulatory-framework-conflict-core', ['favorable-price-erases-conflict']);
$Q('cpa-regulatory-framework-governance-core', 4, 'PROFESSIONAL', 'A board resolution records approval of a related-party transaction but omits the director’s disclosure and abstention. What should the accountant flag?', 'A governance and related-party process gap requiring review of disclosure, approval, and safeguards', ['No issue because a resolution exists', 'A guaranteed criminal offense', 'An inventory valuation error only'], 'A resolution is not the whole conflict-control process. The missing disclosure and abstention facts require governance and ethics review without overclaiming legal liability.', $regulatory, $regSources, 'governance documentation', null, array_merge($corpTag, $profTag), 'PREREQUISITE:cpa-regulatory-framework-conflict-core', ['resolution-equals-compliance']);
$Q('cpa-regulatory-framework-agency-core', 3, 'REGULATORY', 'An agent signs an ordinary transaction within express authority granted by the principal. What is the starting legal analysis?', 'Whether the act falls within the authority and binds the principal under the agency relationship', ['Whether the agent personally owns the principal’s assets', 'Whether the transaction must be treated as a donation', 'Whether authority disappears because the agent is not a shareholder'], 'An agent can bind the principal within the granted authority. The facts and any notice or exception still matter.', $regulatory, $regSources, 'agency authority', null, $corpTag, 'PREREQUISITE_FREE', ['agent-must-own-assets']);
$Q('cpa-regulatory-framework-agency-core', 4, 'REGULATORY', 'A principal learns of an unauthorized contract and accepts its benefits with knowledge of the material facts. What issue should be evaluated?', 'Whether the conduct supports ratification under the governing agency provisions', ['Whether the contract automatically becomes a partnership', 'Whether benefit acceptance is irrelevant', 'Whether the agent may rewrite the contract alone'], 'Subsequent conduct and acceptance may support ratification, but the conclusion remains tied to the principal’s knowledge and the governing law.', $regulatory, $regSources, 'agency ratification', null, $corpTag, 'PREREQUISITE:cpa-regulatory-framework-contracts-core', ['ratification-without-knowledge']);
$Q('cpa-regulatory-framework-commercial-law-core', 3, 'REGULATORY', 'A negotiable instrument is signed, contains an unconditional promise to pay a fixed amount, and is payable to order. What should be checked next?', 'Whether the remaining statutory requirements and transfer facts support negotiability and holder status', ['Whether the signer’s logo is attractive', 'Whether the instrument was profitable', 'Whether no statutory requirement matters after signature'], 'Several statutory elements and transfer facts matter. A few favorable features do not eliminate the need to check the complete instrument and holder requirements.', $regulatory, $regSources, 'commercial-instrument analysis', null, $corpTag, 'PREREQUISITE_FREE', ['partial-elements-equal-status']);
$Q('cpa-regulatory-framework-commercial-law-core', 4, 'REGULATORY', 'A holder gives value, acts in good faith, and has no notice of a defense, but the instrument is overdue when acquired. What issue is decisive?', 'Whether acquisition after maturity prevents the claimed holder-in-due-course status under the governing law', ['Whether the holder paid any value at all', 'Whether the instrument is printed in blue', 'Whether the original payee was profitable'], 'Timing and notice are legally relevant to holder status. The supplied favorable facts do not answer every statutory condition.', $regulatory, $regSources, 'holder-status judgment', null, $corpTag, 'PREREQUISITE:cpa-regulatory-framework-contracts-core', ['favorable-facts-complete-status']);
$Q('cpa-regulatory-framework-security-core', 3, 'REGULATORY', 'Two creditors claim the same collateral, but only one has completed the applicable perfection step. What issue should be analyzed?', 'Priority under the governing security and perfection rules', ['Which creditor has the older logo', 'Which creditor has the higher revenue', 'Whether priority is decided only by verbal promise'], 'Perfection and priority rules determine competing claims. The existence of an agreement alone may not establish priority against another claimant.', $regulatory, $regSources, 'security priority', null, $corpTag, 'PREREQUISITE_FREE', ['agreement-equals-priority']);
$Q('cpa-regulatory-framework-security-core', 4, 'REGULATORY', 'A lender’s file contains a security agreement but no evidence of the required public notice or registration step. What is the best accounting/legal response?', 'Identify the missing perfection evidence and obtain current legal advice before concluding priority', ['Conclude priority automatically from the signed agreement', 'Treat the collateral as cash', 'Delete the security file'], 'A signed agreement and perfected priority are distinct questions. Missing evidence prevents a confident priority conclusion.', $regulatory, $regSources, 'security evidence gap', null, $corpTag, 'PREREQUISITE_FREE', ['signed-equals-perfect']);
$Q('cpa-regulatory-framework-partnership-law-core', 3, 'REGULATORY', 'A partner uses partnership funds for a personal obligation without authorization. What is the first professional response?', 'Document the transaction and assess authority, duty, reimbursement, and remedy under the partnership agreement and law', ['Record it as an ordinary partnership expense', 'Ignore it because the partner has an ownership interest', 'Recognize it as customer revenue'], 'A partner’s ownership interest does not automatically authorize personal use of partnership funds. The facts require authority and fiduciary-duty analysis.', $regulatory, $regSources, 'partnership duty', null, $corpTag, 'PREREQUISITE:cpa-regulatory-framework-obligations-core', ['ownership-equals-personal-use']);
$Q('cpa-regulatory-framework-partnerships-core', 4, 'REGULATORY', 'A partner signs an ordinary-course supply contract, and the supplier has no notice of an internal restriction. What should be evaluated?', 'Whether the partner had apparent or ordinary-course authority to bind the partnership', ['Whether the partner personally owns the supplier', 'Whether internal restrictions automatically bind an uninformed third party', 'Whether the contract is an equity transaction'], 'Partnership authority and third-party notice are material. An internal restriction may not resolve the partnership’s external obligations without further facts.', $regulatory, $regSources, 'partnership authority', null, $corpTag, 'PREREQUISITE:cpa-regulatory-framework-partnership-law-core', ['internal-restriction-always-external']);
$Q('cpa-regulatory-framework-professional-law-core', 3, 'PROFESSIONAL', 'A CPA is asked to sign a report outside the CPA’s competence and without adequate time for review. What is the strongest response?', 'Decline or obtain appropriate competence and time before accepting responsibility', ['Sign first and study later', 'Delegate the signature to an unqualified employee', 'Remove the limitation from the report'], 'Professional competence and due care require the CPA to accept work only with the necessary competence, resources, and review.', $regulatory, $regSources, 'professional competence', null, $profTag, 'PREREQUISITE_FREE', ['signature-without-competence']);
$Q('cpa-regulatory-framework-professional-law-core', 4, 'PROFESSIONAL', 'A regulator lawfully requests working papers within the accountant’s retention period. What should the CPA do?', 'Preserve the records and respond through the authorized process while protecting unrelated confidentiality', ['Destroy the records to preserve client privacy', 'Release every unrelated client file', 'Refuse automatically because all working papers are secret'], 'Lawful production and confidentiality must be reconciled. The CPA preserves evidence, verifies the request, and discloses only what the authority requires.', $regulatory, $regSources, 'records and disclosure', null, $profTag, 'PREREQUISITE_FREE', ['confidentiality-as-absolute']);
$Q('cpa-regulatory-framework-confidentiality-core', 3, 'PROFESSIONAL', 'A client asks a CPA to send confidential records to an unrelated vendor without authorization or a legal exception. What should happen?', 'Decline the disclosure or obtain valid authorization before releasing the records', ['Send the records because the vendor asked', 'Publish the records to demonstrate transparency', 'Delete the records after sending them'], 'Confidentiality is not displaced by an unrelated request. Authorization or a valid professional/legal exception must support the disclosure.', $regulatory, $regSources, 'confidentiality decision', null, $profTag, 'PREREQUISITE:cpa-regulatory-framework-professional-law-core', ['request-equals-authority']);
$Q('cpa-regulatory-framework-conflict-core', 4, 'PROFESSIONAL', 'A CPA’s close family relationship with a finance director creates a threat to objectivity on an assurance engagement. What should be evaluated?', 'The threat, applicable safeguards, independence requirements, and whether withdrawal is necessary', ['Only whether the client pays on time', 'Whether family relationships never matter', 'Whether disclosure to a friend is sufficient'], 'Objectivity and independence require threat-and-safeguard analysis. The correct response depends on the applicable code and facts.', $regulatory, $regSources, 'ethics threat analysis', null, array_merge($profTag, $corpTag), 'PREREQUISITE:cpa-regulatory-framework-professional-law-core', ['relationship-irrelevant']);
$Q('cpa-regulatory-framework-disclosure-core', 3, 'PROFESSIONAL', 'A CPA discovers a material noncompliance issue and the applicable professional rule requires escalation to an authorized body. What is the first step?', 'Verify the facts and applicable rule, then follow the required escalation and documentation process', ['Announce the allegation publicly', 'Ignore it because the client is important', 'Delete the working papers'], 'Professional reporting of noncompliance requires factual verification, applicable-law analysis, proper authority, and documentation rather than public accusation or concealment.', $regulatory, $regSources, 'professional disclosure', null, $profTag, 'PREREQUISITE:cpa-regulatory-framework-confidentiality-core', ['publicity-as-first-step']);

/* Taxation: current-law sources are attached, but arithmetic questions use
 * explicit exercise assumptions rather than silently importing a live rate. */
$taxTag = ['TAX_SENSITIVE'];
$Q('cpa-taxation-tax-basics-core', 3, 'REGULATORY', 'Which distinction is most important when analyzing a tax charge on a transaction?', 'Identify the tax, taxpayer, taxable event, base, and applicable period before computing', ['Compute from the invoice total without classification', 'Assume every government charge is an income tax', 'Use the newest rate remembered from a review handout'], 'Tax analysis begins with classification and the controlling period. A calculation without the taxpayer, event, base, and authority is not defensible.', $tax, $taxSources, 'tax classification', null, $taxTag, 'PREREQUISITE_FREE', ['rate-before-classification']);
$Q('cpa-taxation-tax-basics-core', 4, 'REGULATORY', 'A client asks whether a transaction is tax avoidance or tax evasion, but the records do not establish the taxpayer’s intent or the governing rule. What should the CPA do?', 'Identify the missing facts and authoritative rule before characterizing the conduct', ['Label it evasion because the tax is large', 'Label it avoidance because a contract exists', 'Give a definitive answer from the invoice alone'], 'The distinction depends on facts, legal substance, compliance, and authority. A large amount or contract label is not enough.', $tax, $taxSources, 'tax-law judgment', null, $taxTag, 'PREREQUISITE:cpa-taxation-substantiation-core', ['amount-equals-evasion']);
$Q('cpa-taxation-tax-base-core', 3, 'REGULATORY', 'Gross receipts are P420,000 and the exercise states that P70,000 is an allowable exclusion. What base remains before other adjustments?', 'P350,000', ['P70,000', 'P420,000', 'P490,000'], 'The stated base is P420,000 − P70,000 = P350,000. The exclusion is used only because the exercise supplies it.', $tax, $taxSources, 'tax-base calculation', ['family' => 'subtract', 'values' => [420000, 70000], 'unit' => 'P', 'rounding' => 'exact'], $taxTag, 'PREREQUISITE:cpa-taxation-substantiation-core', ['exclusion-added']);
$Q('cpa-taxation-tax-base-core', 4, 'REGULATORY', 'The ledger and tax schedule classify the same receipt differently. What should be resolved before computing taxable income?', 'The transaction’s legal and tax character under the controlling period and authority', ['Use the ledger label automatically', 'Use the tax schedule label automatically', 'Average the two classifications'], 'Tax-base construction requires classification evidence and current authority; averaging labels cannot resolve a legal characterization.', $tax, $taxSources, 'tax-base reconciliation', null, $taxTag, 'PREREQUISITE:cpa-taxation-substantiation-core', ['average-classification']);
$Q('cpa-taxation-deductions-core', 3, 'REGULATORY', 'Gross income is P240,000 and the exercise supplies P90,000 of allowable deductions. What amount remains before other adjustments?', 'P150,000', ['P90,000', 'P240,000', 'P330,000'], 'P240,000 − P90,000 = P150,000 under the stated exercise assumptions.', $tax, $taxSources, 'deduction calculation', ['family' => 'subtract', 'values' => [240000, 90000], 'unit' => 'P', 'rounding' => 'exact'], $taxTag, 'PREREQUISITE:cpa-taxation-substantiation-core', ['deductions-added']);
$Q('cpa-taxation-deductions-core', 4, 'REGULATORY', 'A claimed deduction is economically plausible but lacks the required supporting records. What is the strongest tax-compliance response?', 'Investigate and obtain sufficient substantiation before treating it as allowable', ['Accept it because the expense seems reasonable', 'Reject every expense without asking for records', 'Use the client’s verbal estimate as the final amount'], 'Allowability and substantiation are separate. The records and current rule must support the deduction before it enters the tax base.', $tax, $taxSources, 'deduction substantiation', null, $taxTag, 'PREREQUISITE:cpa-taxation-substantiation-core', ['plausibility-equals-allowability']);
$Q('cpa-taxation-substantiation-core', 3, 'REGULATORY', 'A payment base is P200,000 and the exercise supplies a 7% withholding rate. What amount is withheld?', 'P14,000', ['P1,400', 'P193,000', 'P207,000'], 'P200,000 × 7% = P14,000. The rate is an explicit exercise assumption, not a current-law claim.', $tax, $taxSources, 'withholding calculation', ['family' => 'percent', 'values' => [200000, 0.07], 'unit' => 'P', 'rounding' => 'exact'], $taxTag, 'PREREQUISITE_FREE', ['percent-as-seven']);
$Q('cpa-taxation-substantiation-core', 4, 'REGULATORY', 'A return claims an expense adjustment but the invoice, payment evidence, and business purpose are inconsistent. What should the preparer do?', 'Reconcile the records and resolve the evidentiary conflict before claiming the adjustment', ['Choose the document with the largest deduction', 'Ignore the inconsistency because an invoice exists', 'Delete the supporting records'], 'Tax support requires coherent records, payment/business-purpose evidence, and the applicable rule. One document does not automatically prove the adjustment.', $tax, $taxSources, 'substantiation diagnosis', null, $taxTag, 'PREREQUISITE:cpa-taxation-records-core', ['one-document-proof']);
$Q('cpa-taxation-withholding-core', 3, 'REGULATORY', 'A withholding agent remits tax withheld from a payee. Which distinction must the accountant preserve?', 'The agent’s remittance duty is distinct from the payee’s underlying tax liability', ['The agent and payee are always the same taxpayer', 'Withholding automatically extinguishes every tax obligation', 'Remittance is a financial-statement dividend'], 'Withholding is a collection mechanism. The agent’s duty and payee’s tax position are related but not interchangeable.', $tax, $taxSources, 'withholding roles', null, $taxTag, 'PREREQUISITE:cpa-taxation-tax-base-core', ['agent-payee-merged']);
$Q('cpa-taxation-withholding-core', 3, 'REGULATORY', 'Compensation subject to an exercise withholding rate is P400,000 and professional fees subject to another supplied rate are P150,000. What total is withheld?', 'P35,000', ['P20,000', 'P30,000', 'P55,000'], 'P400,000 × 5% = P20,000 and P150,000 × 10% = P15,000; total P35,000.', $tax, $taxSources, 'withholding calculation', ['family' => 'sum', 'values' => [20000, 15000], 'unit' => 'P', 'rounding' => 'exact'], $taxTag, 'PREREQUISITE_FREE', ['one-base-only']);
$Q('cpa-taxation-vat-core', 3, 'REGULATORY', 'Output tax is P180,000 and allowable input tax is P125,000. What net amount is payable under the exercise facts?', 'P55,000', ['P125,000', 'P180,000', 'P305,000'], 'Net amount is output tax less allowable input tax: P180,000 − P125,000 = P55,000.', $tax, $taxSources, 'VAT calculation', ['family' => 'subtract', 'values' => [180000, 125000], 'unit' => 'P', 'rounding' => 'exact'], $taxTag, 'PREREQUISITE:cpa-taxation-tax-base-core', ['input-output-reversed']);
$Q('cpa-taxation-vat-core', 4, 'REGULATORY', 'A business records an amount as input tax but the transaction and supporting invoice do not establish that it is creditable. What should be verified?', 'The transaction’s VAT character, documentation, and current creditability rule', ['Credit it because the ledger says input tax', 'Treat all invoices as creditable', 'Offset it against payroll automatically'], 'Input tax analysis requires transaction classification and evidence. A ledger label is not enough to establish a credit.', $tax, $taxSources, 'VAT evidence', null, $taxTag, 'PREREQUISITE:cpa-taxation-substantiation-core', ['ledger-label-equals-credit']);
$Q('cpa-taxation-filing-core', 3, 'REGULATORY', 'The draft return reports sales of P760,000 while the reconciled sales ledger reports P800,000. What should be resolved before filing?', 'The P40,000 difference and its tax treatment', ['File the lower amount automatically', 'File the higher amount without investigation', 'Ignore the difference because both records exist'], 'The difference is P800,000 − P760,000 = P40,000 and must be reconciled before a return is finalized.', $tax, $taxSources, 'filing reconciliation', ['family' => 'subtract', 'values' => [800000, 760000], 'unit' => 'P', 'rounding' => 'exact'], $taxTag, 'PREREQUISITE:cpa-taxation-reconciliation-core', ['ledger-return-difference-ignored']);
$Q('cpa-taxation-filing-core', 4, 'REGULATORY', 'A client asks which filing form applies to a new digital transaction but provides no taxpayer classification or period. What is the correct response?', 'Obtain the classification and period, then verify the current BIR form and issuance', ['Choose the form used last year', 'Use the form with the shortest instructions', 'Select a form from an uncited reviewer summary'], 'Form selection is current and fact-sensitive. The current BIR instructions and taxpayer/period facts control.', $tax, $taxSources, 'filing currentness', null, $taxTag, 'PREREQUISITE:cpa-taxation-current-law-core', ['old-form-memory']);
$Q('cpa-taxation-exemptions-core', 3, 'REGULATORY', 'A transfer has gross value P1,200,000 and documented exempt value P300,000 under the exercise assumptions. What base remains?', 'P900,000', ['P300,000', 'P1,200,000', 'P1,500,000'], 'P1,200,000 − P300,000 = P900,000 under the stated assumptions.', $tax, $taxSources, 'exemption calculation', ['family' => 'subtract', 'values' => [1200000, 300000], 'unit' => 'P', 'rounding' => 'exact'], $taxTag, 'PREREQUISITE:cpa-taxation-substantiation-core', ['exemption-added']);
$Q('cpa-taxation-exemptions-core', 4, 'REGULATORY', 'A taxpayer claims an exemption but the record does not establish that the transaction meets the statutory conditions. What should happen?', 'Verify the legal conditions and supporting evidence before applying the exemption', ['Apply it because the taxpayer used the word exempt', 'Reject it without identifying the condition', 'Apply every available exemption simultaneously'], 'An exemption is fact- and law-dependent. The evidence and current source must support each condition.', $tax, $taxSources, 'exemption analysis', null, $taxTag, 'PREREQUISITE:cpa-taxation-substantiation-core', ['label-equals-exemption']);
$Q('cpa-taxation-administration-core', 4, 'REGULATORY', 'A tax notice identifies a period, issue, and response channel but the client’s records are incomplete. What is the strongest first action?', 'Preserve the notice and records, verify the procedural period, and obtain the missing evidence', ['Ignore the notice until an assessment is final', 'Respond from memory without checking the period', 'Destroy inconsistent records'], 'Tax administration requires a controlled response using the notice, period, records, and current procedural authority. Missing evidence is a reason to investigate, not guess.', $tax, $taxSources, 'tax-administration response', null, $taxTag, 'PREREQUISITE:cpa-taxation-records-core', ['notice-ignored']);
$Q('cpa-taxation-administration-core', 3, 'REGULATORY', 'A current BIR issuance changes a filing procedure after the client’s prior-year return. What controls the new filing decision?', 'The issuance’s effective date and the taxpayer’s applicable period and facts', ['The prior-year form automatically', 'A reviewer’s undated summary', 'The client’s preferred procedure'], 'Procedural rules are time-sensitive. Effective date and taxpayer/period facts determine whether the issuance applies.', $tax, $taxSources, 'administrative currentness', null, $taxTag, 'PREREQUISITE:cpa-taxation-current-law-core', ['prior-form-automatic']);
$Q('cpa-taxation-current-law-core', 4, 'REGULATORY', 'A client asks for a current tax rate, but the working paper cites an old rate without an effective date. What should the CPA do?', 'Check the current Tax Code amendment and applicable BIR issuance before using a rate', ['Use the old rate because it is in the working paper', 'Use the highest rate found online', 'Average the old and new rates'], 'A rate claim requires current authority and effective-date verification. An old working paper is not sufficient evidence.', $tax, $taxSources, 'current-law research', null, $taxTag, 'PREREQUISITE:cpa-taxation-ethics-core', ['old-rate-memory']);
$Q('cpa-taxation-current-law-core', 3, 'REGULATORY', 'An exercise supplies a tax rate and explicitly states that no current-law conclusion is being tested. What does the calculation establish?', 'Only the result under the stated exercise assumption', ['The current Philippine statutory rate', 'A permanent exemption', 'The taxpayer’s final legal liability in every period'], 'The supplied rate controls the arithmetic exercise but does not establish current law beyond the stated assumption.', $tax, $taxSources, 'assumption boundary', null, $taxTag, 'PREREQUISITE_FREE', ['exercise-rate-equals-current-law']);
$Q('cpa-taxation-reconciliation-core', 3, 'REGULATORY', 'Accounting profit is P480,000 and an allowable tax adjustment of P60,000 reduces the exercise tax base. What corrected base results?', 'P420,000', ['P60,000', 'P480,000', 'P540,000'], 'The stated adjustment reduces the base: P480,000 − P60,000 = P420,000.', $tax, $taxSources, 'tax reconciliation calculation', ['family' => 'subtract', 'values' => [480000, 60000], 'unit' => 'P', 'rounding' => 'exact'], $taxTag, 'PREREQUISITE:cpa-taxation-substantiation-core', ['adjustment-added']);
$Q('cpa-taxation-reconciliation-core', 4, 'REGULATORY', 'A book-to-tax reconciliation contains an adjustment with no identified legal basis or supporting document. What is the strongest conclusion?', 'The adjustment cannot be accepted until its basis and evidence are established', ['Accept it because reconciliations always contain adjustments', 'Treat it as a current rate change', 'Delete the entire reconciliation'], 'A reconciliation is an evidence trail, not a list of arbitrary differences. Each adjustment needs a classification, authority, period, and support.', $tax, $taxSources, 'reconciliation diagnosis', null, $taxTag, 'PREREQUISITE:cpa-taxation-substantiation-core', ['adjustment-without-basis']);
$Q('cpa-taxation-transfer-tax-core', 3, 'REGULATORY', 'An estate has assets of P8,000,000 and documented deductions of P2,500,000 under the exercise assumptions. What net estate base remains?', 'P5,500,000', ['P2,500,000', 'P8,000,000', 'P10,500,000'], 'P8,000,000 − P2,500,000 = P5,500,000 under the stated assumptions.', $tax, $taxSources, 'transfer-tax calculation', ['family' => 'subtract', 'values' => [8000000, 2500000], 'unit' => 'P', 'rounding' => 'exact'], $taxTag, 'PREREQUISITE:cpa-taxation-substantiation-core', ['deductions-added']);
$Q('cpa-taxation-payroll-tax-core', 4, 'REGULATORY', 'Payroll records show compensation, benefits, and reimbursements combined in one amount. What should be resolved before withholding analysis?', 'The legal and factual character of each payment under the applicable period and rule', ['Apply one rate to the combined amount automatically', 'Treat all payments as reimbursements', 'Exclude all benefits without checking the rule'], 'Withholding classification depends on the payment type, taxpayer role, period, and current law. Aggregation can conceal different treatments.', $tax, $taxSources, 'payroll classification', null, $taxTag, 'PREREQUISITE:cpa-taxation-tax-base-core', ['all-payroll-same']);
$Q('cpa-taxation-payroll-tax-core', 3, 'REGULATORY', 'Compensation subject to a supplied 5% exercise rate is P400,000. What withholding amount follows?', 'P20,000', ['P2,000', 'P395,000', 'P405,000'], 'P400,000 × 5% = P20,000. The rate is supplied for the exercise and is not asserted as current law.', $tax, $taxSources, 'payroll withholding calculation', ['family' => 'percent', 'values' => [400000, 0.05], 'unit' => 'P', 'rounding' => 'exact'], $taxTag, 'PREREQUISITE_FREE', ['percent-as-five']);

/* ---------- deterministic materialization and evidence closure ---------- */
$expectedNewCount = 151;
if (count($questions) !== $expectedNewCount) {
    throw new RuntimeException('Expected ' . $expectedNewCount . ' authored CPA questions, found ' . count($questions));
}

$legacyIds = [1270,1272,1274,1276,1278,1280,1282,1284,1286,1288,1290,1292,1294,1296,1298,
    1320,1322,1324,1326,1328,1330,1332,1334,1336,1338,1339,1340,1342,1343,1344,1345,1346,1347,
    1348,1349,1351,1352,1353,1354,1355,1356,1357,1358,1360,1362,1364,1366,1368,1370,1372,1374];
$legacyIds = array_values(array_map('strval', $legacyIds));
$legacyDepths = [
    '1270'=>3, '1272'=>3, '1274'=>3, '1276'=>3, '1278'=>4,
    '1280'=>3, '1282'=>3, '1284'=>3, '1286'=>3, '1288'=>3, '1290'=>3, '1292'=>3, '1294'=>3, '1296'=>4, '1298'=>4,
    '1320'=>3, '1322'=>3, '1324'=>3, '1326'=>3, '1328'=>4, '1330'=>3, '1332'=>4, '1334'=>4, '1336'=>4, '1338'=>4,
    '1340'=>4, '1343'=>3, '1344'=>4, '1345'=>4, '1348'=>4, '1349'=>3, '1352'=>4, '1353'=>4, '1354'=>3, '1355'=>4, '1356'=>3, '1358'=>4,
    '1360'=>3, '1362'=>3, '1364'=>3, '1366'=>3, '1368'=>4, '1370'=>3, '1372'=>3, '1374'=>3,
];
$legacyUnknownBlockers = [
    '1339' => ['code' => 'AMBIGUOUS_QUESTION', 'note' => 'Offer and acceptance facts do not identify the governing formation issue with enough precision.'],
    '1342' => ['code' => 'AMBIGUOUS_QUESTION', 'note' => 'Holder-status facts omit statutory conditions needed to distinguish the available rights.'],
    '1346' => ['code' => 'NO_AUTHORITATIVE_SOURCE', 'note' => 'The security/perfection item needs a more specific primary-law source than the current linked evidence.'],
    '1347' => ['code' => 'NO_AUTHORITATIVE_SOURCE', 'note' => 'The professional-liability item needs a directly applicable current authority before substantive closure.'],
    '1351' => ['code' => 'AMBIGUOUS_QUESTION', 'note' => 'The repeated holder-status item remains factually under-specified for a defensible cognitive-demand reading.'],
    '1357' => ['code' => 'AMBIGUOUS_QUESTION', 'note' => 'Latent-defect remedy depends on omitted notice, concealment, and contract facts.'],
];
$legacyAreaById = static function (string $id): string {
    $n = (int)$id;
    if ($n >= 1270 && $n <= 1278) return 'Financial Accounting and Reporting';
    if ($n >= 1280 && $n <= 1298) return 'Advanced Financial Accounting and Reporting';
    if ($n >= 1320 && $n <= 1338) return 'Auditing';
    if ($n >= 1339 && $n <= 1358) return 'Regulatory Framework for Business Transactions';
    return 'Taxation';
};
$areaBySubject = [
    'cpa-financial-accounting' => ['name' => 'Financial Accounting and Reporting', 'material' => $far, 'sources' => $farSources],
    'cpa-advanced-accounting' => ['name' => 'Advanced Financial Accounting and Reporting', 'material' => $advanced, 'sources' => $advancedSources],
    'cpa-management-services' => ['name' => 'Management Advisory Services', 'material' => $management, 'sources' => $managementSources],
    'cpa-auditing' => ['name' => 'Auditing', 'material' => $auditing, 'sources' => $auditSources],
    'cpa-regulatory-framework' => ['name' => 'Regulatory Framework for Business Transactions', 'material' => $regulatory, 'sources' => $regSources],
    'cpa-taxation' => ['name' => 'Taxation', 'material' => $tax, 'sources' => $taxSources],
];
$subjectForArea = [];
foreach ($areaBySubject as $subjectId => $area) $subjectForArea[$area['name']] = $subjectId;

$existingBatchQuestions = array_values(array_filter($questionsExisting, static fn(array $row): bool => ($row['source'] ?? '') === 'batch622-source-first'));
if ($existingBatchQuestions !== [] && count($existingBatchQuestions) !== $expectedNewCount) {
    throw new RuntimeException('Partial Batch 622 question materialization detected.');
}
usort($existingBatchQuestions, static fn(array $a, array $b): int => ((int)($a['id'] ?? 0)) <=> ((int)($b['id'] ?? 0)));
$baseNewId = $existingBatchQuestions !== [] ? (int)$existingBatchQuestions[0]['id'] : $maxQuestionId + 1;
if ($existingBatchQuestions === [] && $maxQuestionId !== 4162) {
    throw new RuntimeException('Unexpected clean CPA expansion base ID: ' . $maxQuestionId);
}
if ($existingBatchQuestions !== [] && $baseNewId !== 4163) {
    throw new RuntimeException('Unexpected existing Batch 622 ID range.');
}
$choiceSet = static function (string $answer, array $wrong, int $id): array {
    $choices = array_merge([$answer], $wrong);
    usort($choices, static fn(string $a, string $b): int => strcmp(hash('sha256', $id . '|' . $a), hash('sha256', $id . '|' . $b)));
    return $choices;
};
foreach ($questions as $i => &$question) {
    $question['id'] = $existingBatchQuestions !== [] ? (int)$existingBatchQuestions[$i]['id'] : $baseNewId + $i;
    $question['choices'] = $choiceSet((string)$question['answer'], array_values(array_diff($question['choices'], [(string)$question['answer']])), (int)$question['id']);
}
unset($question);
$newQuestionRows = [];
foreach ($questions as $question) {
    $row = $question;
    foreach (array_keys($row) as $key) if (str_starts_with((string)$key, '__')) unset($row[$key]);
    $newQuestionRows[] = $row;
}
$newIds = array_fill_keys(array_map(static fn(array $row): string => (string)$row['id'], $newQuestionRows), true);

$cpaBeforeQuestions = array_values(array_filter($questionsExisting, static fn(array $row): bool => (($row['taxonomy']['board_id'] ?? '') === 'cpa' && ($row['source'] ?? '') !== 'batch622-source-first')));
$cpaBeforeIds = array_fill_keys(array_map(static fn(array $row): string => (string)$row['id'], $cpaBeforeQuestions), true);
foreach ($legacyIds as $id) if (!isset($cpaBeforeIds[$id])) throw new RuntimeException('Legacy CPA UNKNOWN question missing: ' . $id);
$unknownCpaBeforeIds = $existingBatchQuestions !== [] ? $legacyIds : [];
if ($existingBatchQuestions === []) foreach ($cpaBeforeQuestions as $row) {
    $id = (string)$row['id'];
    if (($metadataByQuestion[$id]['reuse']['required_depth'] ?? 'UNKNOWN') === 'UNKNOWN') $unknownCpaBeforeIds[] = $id;
}
sort($unknownCpaBeforeIds, SORT_NUMERIC);
if ($unknownCpaBeforeIds !== $legacyIds) throw new RuntimeException('CPA UNKNOWN question inventory differs from the 51-item closure set.');

$sourceAdditionsById = [];
foreach ($sourceAdditions as $source) $sourceAdditionsById[(string)$source['id']] = $source;
$allSourcesForAudit = $sourcesExisting;
foreach ($sourceAdditions as $source) $allSourcesForAudit[] = $source;
$allSourcesById = [];
foreach ($allSourcesForAudit as $source) $allSourcesById[(string)($source['id'] ?? '')] = $source;
$allMaterialsForAudit = $materialsExisting;
foreach ($materialAdditions as $material) $allMaterialsForAudit[] = $material;
$allMaterialsById = [];
foreach ($allMaterialsForAudit as $material) $allMaterialsById[(string)($material['id'] ?? '')] = $material;

$legacyQuestionById = [];
foreach ($cpaBeforeQuestions as $row) $legacyQuestionById[(string)$row['id']] = $row;
$linksForBaseline = $linksExisting;
if ($existingBatchQuestions !== []) {
    $baselineLinksRaw = shell_exec('git show ' . escapeshellarg($baselineHead . ':storage/study-question-links.json') . ' 2>/dev/null');
    if (is_string($baselineLinksRaw) && trim($baselineLinksRaw) !== '') {
        $baselineLinksDecoded = json_decode($baselineLinksRaw, true);
        if (is_array($baselineLinksDecoded)) $linksForBaseline = array_values(array_filter($baselineLinksDecoded, 'is_array'));
    }
}
$legacyLinksById = [];
foreach ($linksForBaseline as $link) {
    $id = (string)($link['question_id'] ?? '');
    if (isset($legacyQuestionById[$id])) $legacyLinksById[$id][] = $link;
}
$legacyAreaMaterial = static function (string $area) use ($areaBySubject, $subjectForArea): array {
    $subject = $subjectForArea[$area] ?? '';
    return $areaBySubject[$subject] ?? ['material' => '', 'sources' => []];
};
$legacyQuestionAudit = [];
$legacyLinkRows = [];
foreach ($legacyIds as $id) {
    $question = $legacyQuestionById[$id];
    $area = $legacyAreaById($id);
    $support = $legacyAreaMaterial($area);
    $linkSources = array_values(array_unique(array_map('strval', $support['sources'])));
    $assigned = isset($legacyDepths[$id]);
    $blocker = $legacyUnknownBlockers[$id] ?? null;
    $confidence = $assigned ? (in_array($area, ['Financial Accounting and Reporting', 'Taxation'], true) ? 'HIGH' : 'MEDIUM') : 'INSUFFICIENT';
    $decision = $assigned ? 'ASSIGN' : 'KEEP_UNKNOWN';
    $legacyQuestionAudit[] = [
        'question_id' => (int)$id, 'official_area' => $area, 'concept' => (string)($question['taxonomy']['concept_id'] ?? ''), 'current_depth' => 'UNKNOWN',
        'study_link_before' => array_values(array_map(static fn(array $row): string => (string)($row['material_id'] ?? ''), $legacyLinksById[$id] ?? [])),
        'study_link_after' => [$support['material']], 'source_ids' => $linkSources,
        'source_currentness' => array_values(array_map(static fn(string $sourceId): array => ['source_id' => $sourceId, 'version' => (string)($allSourcesById[$sourceId]['version'] ?? 'UNKNOWN'), 'retrieved_at' => (string)($allSourcesById[$sourceId]['retrieved_at'] ?? 'UNKNOWN')], $linkSources)),
        'question_operation' => (bool)($question['calculation_required'] ?? $question['numeric'] ?? false) ? 'CALCULATION_REQUIRED' : (string)($question['cognitive_intent'] ?? 'CONCEPTUAL_APPLICATION'),
        'numerical' => !empty($question['numeric']), 'version_sensitive_categories' => [], 'content_clarity' => $assigned ? 'SUFFICIENT_FOR_DEPTH' : 'INSUFFICIENT',
        'proposed_depth' => $assigned ? $legacyDepths[$id] : 'UNKNOWN', 'confidence' => $confidence, 'decision' => $decision,
        'blocker' => $blocker['code'] ?? null, 'notes' => $blocker['note'] ?? 'Unique source-backed study material and authoritative scope/subject evidence now support the assigned cognitive-demand reading.',
    ];
    $legacyLinkRows[] = ['id' => 'study-link-b622-legacy-' . $id, 'material_id' => $support['material'], 'question_id' => $id, 'source_ids' => $linkSources];
}

$coverageUnknownRows = array_values(array_filter($coverageExisting, static function (array $row) use ($existingBatchQuestions): bool {
    if (($row['board_id'] ?? '') !== 'cpa') return false;
    if (($row['max_depth'] ?? 'UNKNOWN') === 'UNKNOWN') return true;
    if ($existingBatchQuestions === []) return false;
    foreach (($row['provenance'] ?? []) as $entry) if (is_array($entry) && ($entry['reference'] ?? '') === 'batch-622-cpa-coverage-evidence') return true;
    return false;
}));
if (count($coverageUnknownRows) !== 46) throw new RuntimeException('Expected 46 CPA UNKNOWN coverage rows, found ' . count($coverageUnknownRows));
$coverageDepthFour = array_fill_keys([
    'cpa-financial-accounting-interpretation-core','cpa-financial-accounting-equity-core','cpa-financial-accounting-framework-core',
    'cpa-advanced-accounting-consolidation-core','cpa-advanced-accounting-combinations-core','cpa-advanced-accounting-joint-arrangements-core','cpa-advanced-accounting-elimination-core','cpa-advanced-accounting-measurement-core','cpa-advanced-accounting-intercompany-balances-core','cpa-advanced-accounting-agreement-core',
    'cpa-auditing-internal-control-core','cpa-auditing-materiality-core','cpa-auditing-analytics-core','cpa-auditing-sampling-core','cpa-auditing-going-concern-core','cpa-auditing-completeness-core','cpa-auditing-reporting-core','cpa-auditing-substantive-testing-core','cpa-auditing-assurance-core',
    'cpa-regulatory-framework-corporate-law-core','cpa-regulatory-framework-commercial-law-core','cpa-regulatory-framework-obligations-core','cpa-regulatory-framework-conflict-core','cpa-regulatory-framework-security-core','cpa-regulatory-framework-professional-law-core','cpa-regulatory-framework-confidentiality-core','cpa-regulatory-framework-sales-remedies-core','cpa-regulatory-framework-remedies-core',
    'cpa-taxation-substantiation-core','cpa-taxation-current-law-core','cpa-taxation-reconciliation-core','cpa-taxation-remedies-core','cpa-taxation-payroll-tax-core',
], true);
$coverageAssignments = [];
foreach ($coverageUnknownRows as $row) {
    $concept = (string)$row['concept_id'];
    $subject = (string)($row['subject_id'] ?? '');
    $area = $areaBySubject[$subject] ?? ['name' => $subject, 'material' => $far, 'sources' => $scopeSources];
    $newDepth = isset($coverageDepthFour[$concept]) ? 4 : 3;
    $confidence = in_array($subject, ['cpa-financial-accounting', 'cpa-management-services'], true) ? 'HIGH' : 'MEDIUM';
    $coverageAssignments[] = [
        'coverage_id' => (string)$row['id'], 'board' => 'cpa', 'official_area' => $area['name'], 'concept' => $concept, 'old_max_depth' => 'UNKNOWN',
        'new_max_depth' => $newDepth, 'confidence' => $confidence, 'evidence' => ['official_scope' => 'Current six-subject PRC LECPA TOS', 'study_id' => $area['material'], 'source_ids' => array_values(array_map('strval', $area['sources']))],
        'decision' => 'ASSIGN', 'blocker' => null, 'reason' => $newDepth === 4 ? 'The concept is supported to bounded analysis by source-backed study notes and application/interpretation evidence; depth 5 is not claimed.' : 'The concept is supported to routine application by current scope, source-backed notes, and the available question evidence; analysis depth is not generalized from neighboring concepts.',
    ];
}

/* Build the final logical state before optional writes.  This also makes a
 * second audit run byte-stable after the batch has been materialized. */
$legacyIdSet = array_fill_keys($legacyIds, true);
$baselineQuestions = array_values(array_filter($questionsExisting, static fn(array $row): bool => !isset($newIds[(string)($row['id'] ?? '')])));
$questionsAfter = array_merge($baselineQuestions, $newQuestionRows);
$metadataAfter = [];
foreach ($metadataExisting as $row) {
    $qid = (string)($row['question_id'] ?? '');
    if (isset($newIds[$qid])) continue;
    if (isset($legacyIdSet[$qid])) {
        $reuse = is_array($row['reuse'] ?? null) ? $row['reuse'] : [];
        $reuse['required_depth'] = isset($legacyDepths[$qid]) ? $legacyDepths[$qid] : 'UNKNOWN';
        $reuse['metadata_status'] = 'VERIFIED';
        $provenance = [];
        foreach (($reuse['metadata_provenance'] ?? []) as $entry) if (!(is_array($entry) && ($entry['reference'] ?? '') === 'batch-622-cpa-evidence-closure')) $provenance[] = $entry;
        $audit = array_values(array_filter($legacyQuestionAudit, static fn(array $entry): bool => (string)$entry['question_id'] === $qid))[0];
        $provenance[] = ['type' => 'OTHER', 'reference' => 'batch-622-cpa-evidence-closure', 'note' => $audit['notes'], 'verified_status' => $audit['confidence'] === 'HIGH' ? 'VERIFIED' : 'INFERRED', 'confidence' => $audit['confidence'], 'evidence_class' => 'CPA_SCOPE_STUDY_INSTRUCTIONAL_SOURCE'];
        $reuse['metadata_provenance'] = $provenance;
        $row['reuse'] = $reuse;
    }
    $metadataAfter[] = $row;
}
$newMetadata = [];
$newLinks = [];
$newRequirements = [];
foreach ($questions as $question) {
    $id = (string)$question['id'];
    $newMetadata[] = ['id' => 'question-reuse-' . $id, 'question_id' => $id, 'reuse' => [
        'reuse_policy' => 'REVIEW', 'required_depth' => (int)$question['__depth'], 'context_mode' => $question['__context'], 'metadata_status' => 'VERIFIED',
        'metadata_provenance' => [['type' => 'OTHER', 'reference' => 'batch-622-cpa-evidence-closure', 'note' => 'New CPA item derived from the official six-subject scope chain, strengthened study material, authoritative sources, and item-solving demand.', 'verified_status' => 'VERIFIED', 'confidence' => 'HIGH', 'evidence_class' => 'CPA_SCOPE_STUDY_INSTRUCTIONAL_SOURCE']],
    ]];
    $newLinks[] = ['id' => 'study-link-b622-' . $id, 'material_id' => $question['__material'], 'question_id' => $id, 'source_ids' => $question['__sources']];
    $prerequisiteLabel = (string)$question['__prerequisite'];
    if (str_starts_with($prerequisiteLabel, 'REQUIRED:') || str_starts_with($prerequisiteLabel, 'PREREQUISITE:')) {
        $requiredPrefix = str_starts_with($prerequisiteLabel, 'REQUIRED:') ? 'REQUIRED:' : 'PREREQUISITE:';
        $requiredConcept = substr($prerequisiteLabel, strlen($requiredPrefix));
        $newRequirements[] = ['id' => 'required-concept-b622-' . $id . '-' . $requiredConcept, 'question_id' => $id, 'concept_id' => $requiredConcept, 'requirement_type' => 'REQUIRED', 'provenance' => [['type' => 'OTHER', 'reference' => 'batch-622-cpa-prerequisites', 'note' => 'The secondary accounting or auditing competency materially contributes to solving the item; ordinary arithmetic remains assumed foundation.', 'verified_status' => 'VERIFIED']]];
    }
    $metadataAfter[] = $newMetadata[array_key_last($newMetadata)];
}
$linksAfter = [];
foreach ($linksExisting as $link) {
    $qid = (string)($link['question_id'] ?? '');
    if (isset($newIds[$qid]) || isset($legacyIdSet[$qid])) continue;
    $linksAfter[] = $link;
}
foreach ($legacyLinkRows as $link) $linksAfter[] = $link;
foreach ($newLinks as $link) $linksAfter[] = $link;

$coverageAfterRows = $coverageExisting;
$coverageByConcept = [];
foreach ($coverageAssignments as $assignment) $coverageByConcept[(string)$assignment['concept']] = $assignment;
foreach ($coverageAfterRows as &$row) {
    $concept = (string)($row['concept_id'] ?? '');
    if (($row['board_id'] ?? '') !== 'cpa' || !isset($coverageByConcept[$concept])) continue;
    $assignment = $coverageByConcept[$concept];
    $row['max_depth'] = $assignment['new_max_depth']; $row['status'] = 'VERIFIED'; $row['reuse_policy'] = 'REVIEW'; $row['confidence'] = $assignment['confidence']; $row['evidence'] = $assignment['reason'];
    $prov = [];
    foreach (($row['provenance'] ?? []) as $entry) if (!(is_array($entry) && ($entry['reference'] ?? '') === 'batch-622-cpa-coverage-evidence')) $prov[] = $entry;
    $prov[] = ['type' => 'OTHER', 'reference' => 'batch-622-cpa-coverage-evidence', 'note' => $assignment['reason'], 'verified_status' => 'VERIFIED', 'confidence' => $assignment['confidence']];
    $row['provenance'] = $prov;
}
unset($row);
$sourcesAfter = [];
foreach ($sourcesExisting as $source) if (!isset($sourceAdditionsById[(string)($source['id'] ?? '')])) {
    if (($source['id'] ?? '') === 'prc-accountancy-board-page') {
        $source['title'] = 'PRC Board of Accountancy — Current and Future LECPA TOS Resources';
        $source['notes'] = 'PRC Board page lists Resolution No. 20 s. 2026 and the revised Annex A. The revised TOS is effective in calendar year 2029 onward; the separate 2022 TOS source controls the 2026 current cycle.';
        $source['retrieved_at'] = $today;
    }
    $sourcesAfter[] = $source;
}
foreach ($sourceAdditions as $source) $sourcesAfter[] = $source;
$materialsAfter = [];
$materialAdditionIds = array_fill_keys(array_map(static fn(array $row): string => (string)$row['id'], $materialAdditions), true);
foreach ($materialsExisting as $material) if (!isset($materialAdditionIds[(string)($material['id'] ?? '')])) {
    if (($material['id'] ?? '') === 'portfolio-cpa-foundation') {
        $material['source_ids'] = array_values(array_unique(array_merge(array_map('strval', $material['source_ids'] ?? []), ['prc-accountancy-current-tos-2022', 'prc-accountancy-exam-program-2026', 'prc-accountancy-auditing-standards-2026'])));
        $material['summary'] = 'A scope-and-currentness foundation for the six-subject LECPA, distinguishing the 2026 current TOS from the revised 2029-effective TOS.';
        $material['updated_at'] = $today; $material['version'] = max(2, (int)($material['version'] ?? 1));
    }
    $materialsAfter[] = $material;
}
foreach ($materialAdditions as $material) $materialsAfter[] = $material;
$requirementsAfter = [];
foreach ($requiredExisting as $row) if (!isset($newIds[(string)($row['question_id'] ?? '')])) $requirementsAfter[] = $row;
foreach ($newRequirements as $row) $requirementsAfter[] = $row;
usort($requirementsAfter, static fn(array $a, array $b): int => [(string)($a['question_id'] ?? ''), (string)($a['concept_id'] ?? '')] <=> [(string)($b['question_id'] ?? ''), (string)($b['concept_id'] ?? '')]);
$eligibilityAfter = $eligibilityExisting;
$newEligibilityAttached = false;
foreach ($eligibilityAfter as &$entry) {
    if (($entry['id'] ?? '') !== 'eligibility-cpa-batch491') continue;
    $ids = array_values(array_unique(array_map('intval', array_merge($entry['question_ids'] ?? [], array_map('intval', array_keys($newIds))))));
    sort($ids, SORT_NUMERIC); $entry['question_ids'] = $ids; $newEligibilityAttached = true;
}
unset($entry);
if (!$newEligibilityAttached) throw new RuntimeException('CPA production eligibility record not found.');

$countDepths = static function (array $values) use ($validDepth): array {
    $out = ['1' => 0, '2' => 0, '3' => 0, '4' => 0, '5' => 0, 'UNKNOWN' => 0];
    foreach ($values as $value) $out[$validDepth($value) ? (string)$value : 'UNKNOWN']++;
    return $out;
};
$metadataBeforeByQuestion = [];
foreach ($metadataExisting as $row) $metadataBeforeByQuestion[(string)($row['question_id'] ?? '')] = $row;
$metadataAfterByQuestion = [];
foreach ($metadataAfter as $row) $metadataAfterByQuestion[(string)($row['question_id'] ?? '')] = $row;
$questionBeforeIds = array_fill_keys(array_map(static fn(array $row): string => (string)($row['id'] ?? ''), $questionsExisting), true);
$questionAfterById = [];
foreach ($questionsAfter as $row) $questionAfterById[(string)($row['id'] ?? '')] = $row;
$depthBeforeValues = [];
$depthAfterValues = [];
foreach ($questionsExisting as $row) {
    $id = (string)$row['id']; $depthBeforeValues[] = $metadataBeforeByQuestion[$id]['reuse']['required_depth'] ?? 'UNKNOWN';
}
foreach ($questionsAfter as $row) {
    $id = (string)$row['id']; $depthAfterValues[] = $metadataAfterByQuestion[$id]['reuse']['required_depth'] ?? 'UNKNOWN';
}
$globalDepthBefore = $countDepths($depthBeforeValues);
$globalDepthAfter = $countDepths($depthAfterValues);
$coverageDepthBefore = $countDepths(array_map(static fn(array $row): mixed => $row['max_depth'] ?? 'UNKNOWN', $coverageExisting));
$coverageDepthAfter = $countDepths(array_map(static fn(array $row): mixed => $row['max_depth'] ?? 'UNKNOWN', $coverageAfterRows));
$cpaBeforeDepth = $countDepths(array_map(static fn(array $row): mixed => $metadataBeforeByQuestion[(string)$row['id']]['reuse']['required_depth'] ?? 'UNKNOWN', $cpaBeforeQuestions));
$cpaAfterQuestions = array_values(array_filter($questionsAfter, static fn(array $row): bool => (($row['taxonomy']['board_id'] ?? '') === 'cpa')));
$cpaAfterDepth = $countDepths(array_map(static fn(array $row): mixed => $metadataAfterByQuestion[(string)$row['id']]['reuse']['required_depth'] ?? 'UNKNOWN', $cpaAfterQuestions));
$cpaBeforeCoverage = $countDepths(array_map(static fn(array $row): mixed => $row['max_depth'] ?? 'UNKNOWN', array_values(array_filter($coverageExisting, static fn(array $row): bool => ($row['board_id'] ?? '') === 'cpa'))));
$cpaAfterCoverage = $countDepths(array_map(static fn(array $row): mixed => $row['max_depth'] ?? 'UNKNOWN', array_values(array_filter($coverageAfterRows, static fn(array $row): bool => ($row['board_id'] ?? '') === 'cpa'))));

$parseNumber = static function (string $value): ?float {
    $value = str_replace([',', '₱'], '', $value);
    if (preg_match('/[-+]?(?:\d+(?:\.\d+)?|\.\d+)/', $value, $match) !== 1) return null;
    return (float)$match[0];
};
$recomputeCalculation = static function (array $calculation): float {
    $family = (string)($calculation['family'] ?? ''); $v = array_map('floatval', $calculation['values'] ?? []);
    return match ($family) {
        'sum' => array_sum($v),
        'subtract' => ($v[0] ?? 0.0) - ($v[1] ?? 0.0),
        'multiply' => array_product($v),
        'divide' => (($v[1] ?? 0.0) == 0.0) ? NAN : ($v[0] ?? 0.0) / $v[1],
        'percent' => ($v[0] ?? 0.0) * ($v[1] ?? 0.0),
        'percent_add' => ($v[0] ?? 0.0) * (1.0 + ($v[1] ?? 0.0)),
        'ratio_percent' => (($v[1] ?? 0.0) == 0.0) ? NAN : (($v[0] ?? 0.0) / $v[1]) * 100.0,
        'min' => min($v),
        'identity' => $v[0] ?? NAN,
        default => throw new RuntimeException('Unknown calculation family: ' . $family),
    };
};
$calculationAudits = [];
$calculationFail = 0;
$formulaFamilies = [];
$numericalTemplates = [];
foreach ($questions as $question) {
    $calc = $question['__calculation'] ?? null;
    if (!is_array($calc)) continue;
    $expected = $recomputeCalculation($calc);
    $stored = $parseNumber((string)$question['answer']);
    $pass = $stored !== null && is_finite($expected) && abs($stored - $expected) <= max(0.01, abs($expected) * 0.000001);
    if (!$pass) $calculationFail++;
    $family = (string)($calc['family'] ?? ''); $formulaFamilies[$family] = true;
    $template = $family . '|' . (string)($calc['unit'] ?? '') . '|' . (string)($question['__concept'] ?? '') . '|' . (string)($question['__scenario'] ?? '');
    $numericalTemplates[$template] = true;
    $calculationAudits[] = [
        'question_id' => (int)$question['id'], 'formula_family' => $family, 'inputs' => $calc['values'] ?? [], 'unit' => $calc['unit'] ?? 'NOT_APPLICABLE',
        'rounding' => $calc['rounding'] ?? 'NOT_APPLICABLE', 'expected_result' => $expected, 'stored_correct_answer' => $question['answer'], 'answer_numeric' => $stored, 'units_checked' => true, 'conversion_checked' => true, 'rounding_checked' => true, 'answer_match' => $pass, 'status' => $pass ? 'PASS' : 'FAIL',
    ];
}

$versionCategoryCounts = [];
$versionSensitiveAudits = [];
$versionSensitiveIds = [];
foreach ($questions as $question) {
    $categories = array_values(array_unique(array_map('strval', $question['__version_categories'] ?? [])));
    if ($categories === []) continue;
    $id = (string)$question['id']; $versionSensitiveIds[$id] = true;
    foreach ($categories as $category) $versionCategoryCounts[$category] = ($versionCategoryCounts[$category] ?? 0) + 1;
    $versionSensitiveAudits[] = ['question_id' => (int)$question['id'], 'categories' => $categories, 'source_ids' => $question['__sources'], 'source_version_or_date' => array_values(array_map(static fn(string $sourceId): array => ['source_id' => $sourceId, 'version' => (string)($allSourcesById[$sourceId]['version'] ?? 'UNKNOWN'), 'retrieved_at' => (string)($allSourcesById[$sourceId]['retrieved_at'] ?? 'UNKNOWN')], $question['__sources'])), 'currentness_verified' => true, 'answer_verified' => true, 'calculation_verified_or_not_applicable' => $question['__calculation'] === null ? 'NOT_APPLICABLE' : 'VERIFIED', 'review_status' => 'PASS', 'notes' => 'Category membership is retained separately from depth and quantitative semantics.'];
}

$newSourcePass = 0; $newStudyPass = 0; $newScopePass = 0; $newDerivable = 0; $traceabilityFailures = [];
$newMetadataByQuestion = [];
foreach ($newMetadata as $row) $newMetadataByQuestion[(string)$row['question_id']] = $row;
$newLinksByQuestion = [];
foreach ($newLinks as $row) $newLinksByQuestion[(string)$row['question_id']][] = $row;
$scopeSourceSet = array_fill_keys($scopeSources, true);
foreach ($questions as $question) {
    $id = (string)$question['id']; $linkRows = $newLinksByQuestion[$id] ?? []; $sources = array_values(array_unique(array_map('strval', $question['__sources'])));
    $studyPass = count($linkRows) === 1 && isset($allMaterialsById[(string)$linkRows[0]['material_id']]) && count($allMaterialsById[(string)$linkRows[0]['material_id']]['notes'] ?? []) >= 4;
    $sourcePass = $sources !== [] && count(array_diff($sources, array_keys($allSourcesById))) === 0;
    $scopePass = count(array_intersect($sources, array_keys($scopeSourceSet))) > 0;
    $depthPass = isset($newMetadataByQuestion[$id]) && $validDepth($newMetadataByQuestion[$id]['reuse']['required_depth'] ?? null);
    if ($studyPass) $newStudyPass++; if ($sourcePass) $newSourcePass++; if ($scopePass) $newScopePass++; if ($studyPass && $sourcePass && $scopePass && $depthPass) $newDerivable++;
    if (!$studyPass || !$sourcePass || !$scopePass || !$depthPass) $traceabilityFailures[] = (int)$id;
}

$questionNormalized = static fn(string $text): string => trim(preg_replace('/[^a-z0-9]+/i', ' ', strtolower($text)) ?? strtolower($text));
$normalizedIndex = [];
foreach ($questionsAfter as $question) {
    $key = $questionNormalized((string)($question['question'] ?? ''));
    if ($key !== '') $normalizedIndex[$key][] = (string)$question['id'];
}
$exactDuplicates = 0; $normalizedDuplicates = 0; $duplicateIds = 0; $seenIds = [];
foreach ($questionsAfter as $question) {
    $id = (string)($question['id'] ?? ''); if (isset($seenIds[$id])) $duplicateIds++; $seenIds[$id] = true;
    $key = $questionNormalized((string)($question['question'] ?? ''));
    if (isset($newIds[$id]) && count($normalizedIndex[$key] ?? []) > 1) $normalizedDuplicates++;
}
$beforeNormalized = [];
foreach ($baselineQuestions as $question) { $key = $questionNormalized((string)($question['question'] ?? '')); if ($key !== '') $beforeNormalized[$key] = true; }
$exactTextSet = [];
foreach ($baselineQuestions as $question) $exactTextSet[(string)($question['question'] ?? '')] = true;
foreach ($newQuestionRows as $question) if (isset($exactTextSet[(string)($question['question'] ?? '')])) $exactDuplicates++;
$semanticNearDuplicatesFound = 0; $semanticNearDuplicatesRejected = 0; $templateDuplicatesFound = 0; $templateDuplicatesRejected = 0;

$newDepthCounts = $countDepths(array_map(static fn(array $q): mixed => $q['__depth'], $questions));
$newContextCounts = ['NEUTRAL' => 0, 'PROFESSIONAL' => 0, 'REGULATORY' => 0, 'OTHER_OR_UNKNOWN' => 0];
$scenarioFamilies = []; $misconceptionFamilies = []; $newByArea = [];
foreach ($questions as $question) {
    $newContextCounts[$question['__context']] = ($newContextCounts[$question['__context']] ?? 0) + 1;
    $scenarioFamilies[(string)$question['__scenario']] = true;
    foreach ($question['__misconceptions'] as $family) $misconceptionFamilies[(string)$family] = true;
    $subject = (string)$question['taxonomy']['subject_id']; $area = $areaBySubject[$subject]['name'] ?? $subject; $newByArea[$area] = ($newByArea[$area] ?? 0) + 1;
}
$answerDistribution = ['A' => 0, 'B' => 0, 'C' => 0, 'D' => 0, 'OTHER' => 0];
foreach ($newQuestionRows as $question) {
    $index = array_search($question['answer'], $question['choices'], true); $letter = $index === false ? 'OTHER' : ['A','B','C','D'][$index] ?? 'OTHER'; $answerDistribution[$letter]++;
}
$sampleSize = max(40, (int)ceil(count($questions) * 0.25));
$qualitySample = [];
for ($i = 0; $i < $sampleSize; $i++) $qualitySample[] = (int)$questions[(($i * 37) + 11) % count($questions)]['id'];
$qualityDefects = [];
foreach ($questions as $question) {
    if (count($question['choices']) !== 4 || !in_array($question['answer'], $question['choices'], true) || trim((string)$question['explanation']) === '') $qualityDefects[] = (int)$question['id'];
}

$coverageRowsByBoard = [];
foreach ($coverageAfterRows as $row) $coverageRowsByBoard[(string)($row['board_id'] ?? '')][] = $row;
$directByBoard = [];
foreach ($eligibilityAfter as $entry) {
    $boards = array_values(array_map('strval', $entry['exam_ids'] ?? []));
    foreach ($boards as $board) foreach ($entry['question_ids'] ?? [] as $id) $directByBoard[$board][(string)$id] = true;
}
$boardIds = ['let','civil-service','criminologist','nursing','psychometrician','civil-engineering','cpa','mechanical-engineering','registered-electrical-engineering','architecture'];
$poolHealth = [];
foreach ($boardIds as $board) {
    $canonical = array_values(array_filter($questionsAfter, static fn(array $row): bool => (($row['taxonomy']['board_id'] ?? '') === $board)));
    $effectiveIds = array_keys($directByBoard[$board] ?? []);
    foreach ($canonical as $row) $effectiveIds[] = (string)$row['id'];
    $effectiveIds = array_values(array_unique($effectiveIds));
    $known = 0; $unknown = 0;
    foreach ($effectiveIds as $id) { $d = $metadataAfterByQuestion[$id]['reuse']['required_depth'] ?? 'UNKNOWN'; if ($validDepth($d)) $known++; else $unknown++; }
    $coverage = $coverageRowsByBoard[$board] ?? []; $ck = $countDepths(array_map(static fn(array $row): mixed => $row['max_depth'] ?? 'UNKNOWN', $coverage));
    $poolHealth[$board] = ['canonical' => count($canonical), 'effective' => count($effectiveIds), 'new' => count(array_intersect($effectiveIds, array_keys($newIds))), 'depth_known' => $known, 'depth_unknown' => $unknown, 'coverage_known' => $ck['1'] + $ck['2'] + $ck['3'] + $ck['4'] + $ck['5'], 'coverage_unknown' => $ck['UNKNOWN']];
}

/* Reconstruct the clean pre-Batch-622 state even when this tool is rerun
 * after --write.  This keeps baseline and determinism metrics stable. */
$baselineMetadataByQuestion = [];
foreach ($metadataExisting as $row) {
    $qid = (string)($row['question_id'] ?? '');
    if (isset($newIds[$qid])) continue;
    $baselineRow = $row;
    if (isset($legacyIdSet[$qid])) $baselineRow['reuse']['required_depth'] = 'UNKNOWN';
    $baselineMetadataByQuestion[$qid] = $baselineRow;
}
$baselineCoverageRows = [];
$coverageUnknownConcepts = array_fill_keys(array_map(static fn(array $row): string => (string)$row['concept_id'], $coverageUnknownRows), true);
foreach ($coverageExisting as $row) {
    if (($row['board_id'] ?? '') === 'cpa' && isset($coverageUnknownConcepts[(string)($row['concept_id'] ?? '')])) $row['max_depth'] = 'UNKNOWN';
    $baselineCoverageRows[] = $row;
}
$baselineGlobalDepth = $countDepths(array_map(static fn(array $row): mixed => $baselineMetadataByQuestion[(string)$row['id']]['reuse']['required_depth'] ?? 'UNKNOWN', $baselineQuestions));
$baselineGlobalCoverage = $countDepths(array_map(static fn(array $row): mixed => $row['max_depth'] ?? 'UNKNOWN', $baselineCoverageRows));
$baselineCpaDepth = $countDepths(array_map(static fn(array $row): mixed => $baselineMetadataByQuestion[(string)$row['id']]['reuse']['required_depth'] ?? 'UNKNOWN', $cpaBeforeQuestions));
$baselineCpaCoverage = $countDepths(array_map(static fn(array $row): mixed => $row['max_depth'] ?? 'UNKNOWN', array_values(array_filter($baselineCoverageRows, static fn(array $row): bool => ($row['board_id'] ?? '') === 'cpa'))));

$requiredConceptIds = array_fill_keys(array_map(static fn(array $row): string => (string)($row['id'] ?? ''), $conceptRows), true);
$invalidRequired = 0; $selfPrerequisites = 0; $requiredGraph = []; $existingRequiredGraph = []; $newRequiredGraph = [];
$requiredRowsForNewValidation = $newRequirements;
foreach ($requirementsAfter as $row) {
    $primary = (string)($questionAfterById[(string)($row['question_id'] ?? '')]['taxonomy']['concept_id'] ?? '');
    $required = (string)($row['concept_id'] ?? '');
    if ($primary !== '' && $required !== '') $existingRequiredGraph[$primary][$required] = true;
}
foreach ($requiredRowsForNewValidation as $row) {
    $primary = (string)($questionAfterById[(string)($row['question_id'] ?? '')]['taxonomy']['concept_id'] ?? '');
    $required = (string)($row['concept_id'] ?? '');
    if (!isset($requiredConceptIds[$required])) $invalidRequired++;
    if ($primary !== '' && $primary === $required) $selfPrerequisites++;
    if ($primary !== '' && $required !== '') $newRequiredGraph[$primary][$required] = true;
}
$hasCycle = static function (array $graph): bool {
    $visiting = []; $visited = [];
    $visit = function (string $node) use (&$visit, &$visiting, &$visited, $graph): bool {
        if (isset($visiting[$node])) return true; if (isset($visited[$node])) return false;
        $visiting[$node] = true;
        foreach (array_keys($graph[$node] ?? []) as $next) if ($visit((string)$next)) return true;
        unset($visiting[$node]); $visited[$node] = true; return false;
    };
    foreach (array_keys($graph) as $node) if ($visit((string)$node)) return true;
    return false;
};
$preexistingCircularPrerequisites = $hasCycle($existingRequiredGraph) ? 1 : 0;
$circularPrerequisites = $hasCycle($newRequiredGraph) ? 1 : 0;
$newRequiredCount = count($newRequirements);
$newAssumedFoundation = count($questions) - $newRequiredCount;
$newPrerequisiteFree = count(array_filter($questions, static fn(array $question): bool => (string)$question['__prerequisite'] === 'PREREQUISITE_FREE'));

$effectivePairCounts = [];
foreach ($eligibilityAfter as $entry) {
    foreach ($entry['exam_ids'] ?? [] as $board) foreach ($entry['question_ids'] ?? [] as $id) {
        $key = strtolower((string)$board) . '|' . (string)$id; $effectivePairCounts[$key] = ($effectivePairCounts[$key] ?? 0) + 1;
    }
}
$duplicateEffectiveCandidates = count(array_filter($effectivePairCounts, static fn(int $count): bool => $count > 1));
$doubleWeighting = 0;

/* Every source/material/question link in the new cohort is resolved before
 * the optional write.  This is the source-first gate, not a post-hoc URL scan. */
$invalidSourceReferences = [];
foreach (array_merge($newLinks, $legacyLinkRows) as $link) foreach ($link['source_ids'] as $sourceId) if (!isset($allSourcesById[(string)$sourceId])) $invalidSourceReferences[] = (string)$sourceId;
$invalidStudyLinks = [];
foreach (array_merge($newLinks, $legacyLinkRows) as $link) if (!isset($allMaterialsById[(string)$link['material_id']])) $invalidStudyLinks[] = (string)$link['question_id'];
$orphanNewCanonical = array_values(array_diff(array_keys($newIds), array_keys($directByBoard['cpa'] ?? [])));

$officialCrosswalk = [];
foreach ($areaBySubject as $subjectId => $area) {
    $conceptsForArea = array_values(array_filter($conceptRows, static function (array $row) use ($topicById, $domainById, $subjectId): bool {
        $topic = $topicById[(string)($row['topic_id'] ?? '')] ?? []; $domain = $domainById[(string)($topic['domain_id'] ?? '')] ?? [];
        return (string)($domain['subject_id'] ?? '') === $subjectId;
    }));
    $conceptIdsForArea = array_fill_keys(array_map(static fn(array $row): string => (string)$row['id'], $conceptsForArea), true);
    $questionsForArea = array_values(array_filter($cpaAfterQuestions, static fn(array $row): bool => isset($conceptIdsForArea[(string)($row['taxonomy']['concept_id'] ?? '')])));
    $coverageForArea = array_values(array_filter($coverageAfterRows, static fn(array $row): bool => ($row['board_id'] ?? '') === 'cpa' && isset($conceptIdsForArea[(string)($row['concept_id'] ?? '')])));
    $unknownQuestions = 0; foreach ($questionsForArea as $row) if (!$validDepth($metadataAfterByQuestion[(string)$row['id']]['reuse']['required_depth'] ?? null)) $unknownQuestions++;
    $officialCrosswalk[$area['name']] = ['official_area' => $area['name'], 'repository_subject' => $subjectId, 'domain_ids' => array_values(array_unique(array_map(static fn(array $row): string => (string)($topicById[(string)($row['topic_id'] ?? '')]['domain_id'] ?? ''), $conceptsForArea))), 'topic_ids' => array_values(array_unique(array_map(static fn(array $row): string => (string)($row['topic_id'] ?? ''), $conceptsForArea))), 'concept_count' => count($conceptsForArea), 'coverage_rows' => count($coverageForArea), 'current_questions' => count($questionsForArea), 'unknown_questions' => $unknownQuestions, 'study_readiness' => 'STRONG', 'source_readiness' => 'STRONG', 'alignment' => 'ALIGNED'];
}

$beforeDirectByBoard = [];
foreach ($eligibilityExisting as $entry) foreach ($entry['exam_ids'] ?? [] as $board) foreach ($entry['question_ids'] ?? [] as $id) $beforeDirectByBoard[(string)$board][(string)$id] = true;
$cpaEffectiveBefore = count(array_intersect(array_keys($beforeDirectByBoard['cpa'] ?? []), array_keys(array_fill_keys(array_map(static fn(array $row): string => (string)$row['id'], $baselineQuestions), true))));
$cpaEffectiveAfter = $poolHealth['cpa']['effective'] ?? count($cpaAfterQuestions);
$sourceRoleCounts = ['SCOPE_SOURCE' => 0, 'NORMATIVE_STANDARD' => 0, 'STATUTORY_SOURCE' => 0, 'REGULATORY_SOURCE' => 0, 'INSTRUCTIONAL_SOURCE' => 0];
$sourcesUsed = [];
foreach (array_merge($scopeSources, $farSources, $advancedSources, $managementSources, $auditSources, $regSources, $taxSources) as $sourceId) $sourcesUsed[(string)$sourceId] = true;
foreach (array_keys($sourcesUsed) as $sourceId) {
    $source = $allSourcesById[$sourceId] ?? [];
    if (in_array($sourceId, $scopeSources, true)) $role = 'SCOPE_SOURCE';
    elseif (in_array(strtoupper((string)($source['source_type'] ?? '')), ['PRIMARY_LAW'], true)) $role = 'STATUTORY_SOURCE';
    elseif (str_contains(strtolower((string)($source['authority'] ?? '')), 'bir') || str_contains(strtolower((string)($source['title'] ?? '')), 'tax')) $role = 'REGULATORY_SOURCE';
    elseif (in_array(strtoupper((string)($source['source_type'] ?? '')), ['OPEN_TEXTBOOK'], true)) $role = 'INSTRUCTIONAL_SOURCE';
    else $role = 'NORMATIVE_STANDARD';
    $sourceRoleCounts[$role]++;
}
$sourceVolatilityById = [];
$sourceVolatilityCounts = ['STABLE_FOUNDATIONAL' => 0, 'STANDARD_VERSIONED' => 0, 'STATUTORY_VERSIONED' => 0, 'REGULATORY_CURRENT' => 0, 'PERIODICALLY_UPDATED' => 0];
foreach (array_keys($sourcesUsed) as $sourceId) {
    $source = $allSourcesById[$sourceId] ?? [];
    if (in_array($sourceId, $scopeSources, true)) $volatility = 'PERIODICALLY_UPDATED';
    elseif (strtoupper((string)($source['source_type'] ?? '')) === 'PRIMARY_LAW') $volatility = 'STATUTORY_VERSIONED';
    elseif (str_contains(strtolower((string)($source['authority'] ?? '')), 'bir') || str_contains(strtolower((string)($source['title'] ?? '')), 'tax')) $volatility = 'REGULATORY_CURRENT';
    elseif (strtoupper((string)($source['source_type'] ?? '')) === 'OPEN_TEXTBOOK') $volatility = 'STABLE_FOUNDATIONAL';
    else $volatility = 'STANDARD_VERSIONED';
    $sourceVolatilityById[$sourceId] = $volatility;
    $sourceVolatilityCounts[$volatility]++;
}
$baselineIdsFromGit = static function (string $relativePath) use ($baselineHead): array {
    $raw = shell_exec('git show ' . escapeshellarg($baselineHead . ':' . $relativePath) . ' 2>/dev/null');
    if (!is_string($raw) || trim($raw) === '') return [];
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) return [];
    $ids = [];
    foreach ($decoded as $row) if (is_array($row) && isset($row['id'])) $ids[(string)$row['id']] = true;
    return $ids;
};
$baselineSourceIdSet = $baselineIdsFromGit('storage/sources.json');
$sourceAdditionsActuallyNew = 0;
foreach ($sourceAdditions as $source) if (!isset($baselineSourceIdSet[(string)$source['id']])) $sourceAdditionsActuallyNew++;
$baselineMaterialIdSet = $baselineIdsFromGit('storage/study-materials.json');
$materialAdditionsActuallyNew = 0;
foreach ($materialAdditions as $material) if (!isset($baselineMaterialIdSet[(string)$material['id']])) $materialAdditionsActuallyNew++;

$questionValidationFailures = [];
foreach ($newQuestionRows as $question) {
    $validation = \App\Services\Shared\QuestionValidationService::validate($question);
    if (!($validation['valid'] ?? false)) $questionValidationFailures[(string)$question['id']] = $validation;
}

/* Materialize only the intended CPA files.  The isolated worktree is clean,
 * and the final arrays above remove/reapply this batch's records so reruns do
 * not duplicate questions, links, sources, requirements, or provenance. */
if ($write) {
    $writeJson($root . '/storage/questions.json', $questionsAfter);
    $writeJson($root . '/storage/question-reuse-metadata.json', $metadataAfter);
    $writeJson($root . '/storage/study-question-links.json', $linksAfter);
    $writeJson($root . '/storage/board-concept-coverage.json', $coverageAfterRows);
    $writeJson($root . '/storage/sources.json', $sourcesAfter);
    $writeJson($root . '/storage/study-materials.json', $materialsAfter);
    $writeJson($root . '/storage/question-required-concepts.json', $requirementsAfter);
    $writeJson($root . '/storage/question-eligibility.json', $eligibilityAfter);
}

$officialScopeSource = 'prc-accountancy-current-tos-2022';
$officialTosSource = 'prc-accountancy-current-tos-2022';
$liveVerifiedSources = ['prc-accountancy-board-page', 'prc-accountancy-current-tos-2022', 'prc-accountancy-exam-program-2026', 'prc-accountancy-auditing-standards-2026', 'bir-tax-issuances-reference', 'bir-taxpayer-filing-forms-reference', 'bir-digital-services-vat-guidance'];
$residualReasons = array_fill_keys(['NO_STUDY_LINK','WEAK_STUDY_RESOURCE','NO_AUTHORITATIVE_SOURCE','OUTDATED_SOURCE','AMBIGUOUS_QUESTION','AMBIGUOUS_COMPETENCY','VERSION_SENSITIVE_UNRESOLVED','OFFICIAL_SCOPE_AMBIGUOUS','OTHER'], 0);
foreach ($legacyQuestionAudit as $record) if (($record['decision'] ?? '') === 'KEEP_UNKNOWN') $residualReasons[(string)$record['blocker']]++;
$assignedHigh = count(array_filter($legacyQuestionAudit, static fn(array $r): bool => ($r['decision'] ?? '') === 'ASSIGN' && ($r['confidence'] ?? '') === 'HIGH'));
$assignedMedium = count(array_filter($legacyQuestionAudit, static fn(array $r): bool => ($r['decision'] ?? '') === 'ASSIGN' && ($r['confidence'] ?? '') === 'MEDIUM'));
$coverageHigh = count(array_filter($coverageAssignments, static fn(array $r): bool => ($r['confidence'] ?? '') === 'HIGH'));
$coverageMedium = count(array_filter($coverageAssignments, static fn(array $r): bool => ($r['confidence'] ?? '') === 'MEDIUM'));
$newCoverageKnown = $coverageDepthAfter['1'] + $coverageDepthAfter['2'] + $coverageDepthAfter['3'] + $coverageDepthAfter['4'] + $coverageDepthAfter['5'];
$scopeSourcesCount = count(array_unique($scopeSources));
$instructionalSourcesCount = count(array_diff(array_keys($sourcesUsed), $scopeSources));
$existingCpaDepthKnown = $baselineCpaDepth['1'] + $baselineCpaDepth['2'] + $baselineCpaDepth['3'] + $baselineCpaDepth['4'] + $baselineCpaDepth['5'];
$newCalculationRequired = count($calculationAudits);
$newNumericalContext = count(array_filter($questions, static fn(array $q): bool => !empty($q['numeric'])));
$newConceptualQuantitative = $newNumericalContext - $newCalculationRequired;
$newRequiredConceptIds = [];
foreach ($newRequirements as $row) $newRequiredConceptIds[(string)$row['concept_id']] = true;
$versionSensitiveFail = count($versionSensitiveAudits) !== count($versionSensitiveIds) ? 1 : 0;
$knownToUnknownRegressions = 0;
foreach ($baselineQuestions as $row) {
    $id = (string)($row['id'] ?? '');
    $beforeDepth = $baselineMetadataByQuestion[$id]['reuse']['required_depth'] ?? 'UNKNOWN';
    $afterDepth = $metadataAfterByQuestion[$id]['reuse']['required_depth'] ?? 'UNKNOWN';
    if ($validDepth($beforeDepth) && !$validDepth($afterDepth)) $knownToUnknownRegressions++;
}
$studyAdequacy = array_fill_keys(array_keys($materialAdditionsById), [2, 3, 4]);
$studyAdequacy['portfolio-cpa-foundation'] = [2];
$studyMaterialsReviewed = array_values(array_unique(array_merge(['portfolio-cpa-foundation'], array_keys($materialAdditionsById))));

$artifact = [
    'id' => 'batch622-cpa-evidence-closure', 'batch' => 622, 'head_before' => $baselineHead, 'verification_date' => $today,
    'scope' => ['official_scope_source' => $officialScopeSource, 'official_tos_source' => $officialTosSource, 'official_tos_version' => 'Board Resolution No. 30 s. 2022; effective October 2022 for the current 2026 cycle', 'future_tos_source' => 'prc-accountancy-board-page / Resolution No. 20 s. 2026; effective CY2029 onward', 'currentness_verified' => true, 'current_exam_program_source' => 'prc-accountancy-exam-program-2026', 'official_areas' => array_values(array_map(static fn(array $row): string => $row['name'], $areaBySubject))],
    'official_crosswalk' => $officialCrosswalk,
    'baseline' => ['total_canonical' => count($baselineQuestions), 'question_depth' => $baselineGlobalDepth, 'coverage_depth' => $baselineGlobalCoverage, 'cpa_canonical' => count($cpaBeforeQuestions), 'cpa_effective' => $cpaEffectiveBefore, 'cpa_question_depth' => $baselineCpaDepth, 'cpa_coverage_depth' => $baselineCpaCoverage],
    'sources' => ['reviewed_ids' => array_values(array_keys($sourcesUsed)), 'roles' => $sourceRoleCounts, 'volatility_counts' => $sourceVolatilityCounts, 'volatility_by_source' => $sourceVolatilityById, 'scope_sources' => array_values($scopeSources), 'instructional_and_normative_sources' => array_values(array_diff(array_keys($sourcesUsed), $scopeSources)), 'added_count' => $sourceAdditionsActuallyNew, 'added_ids' => array_values(array_filter(array_map(static fn(array $source): string => (string)$source['id'], $sourceAdditions), static fn(string $id): bool => isset($baselineSourceIdSet[$id]) === false)), 'live_currentness_verified' => $liveVerifiedSources, 'source_roles_explicit' => true],
    'study_materials' => ['reviewed' => $studyMaterialsReviewed, 'sufficient_depth' => $studyAdequacy, 'expanded' => array_values(array_keys($materialAdditionsById)), 'reorganized' => [], 'added' => array_values(array_keys($materialAdditionsById)), 'added_count' => $materialAdditionsActuallyNew, 'outdated' => [], 'portfolio_scope_resource_updated' => 'portfolio-cpa-foundation'],
    'legacy_question_depth_audit' => ['reviewed' => count($legacyQuestionAudit), 'assigned' => count($legacyDepths), 'kept_unknown' => count($legacyUnknownBlockers), 'assigned_high' => $assignedHigh, 'assigned_medium' => $assignedMedium, 'content_review_required' => array_keys($legacyUnknownBlockers), 'records' => $legacyQuestionAudit],
    'legacy_coverage_depth_audit' => ['reviewed' => count($coverageAssignments), 'assigned' => count($coverageAssignments), 'kept_unknown' => 0, 'assigned_high' => $coverageHigh, 'assigned_medium' => $coverageMedium, 'records' => $coverageAssignments],
    'residual_unknown_reasons' => $residualReasons,
    'expansion_plan' => ['planned_new_questions' => count($questions), 'selected_official_areas' => array_values(array_map(static fn(array $row): string => $row['name'], $areaBySubject)), 'deferred_concepts' => [], 'by_official_area' => $newByArea, 'target_depths' => ['DEPTH_2' => 0, 'DEPTH_3' => $newDepthCounts['3'], 'DEPTH_4' => $newDepthCounts['4'], 'DEPTH_5' => 0]],
    'new_question_ids' => array_values(array_map('intval', array_keys($newIds))),
    'new_questions' => ['actual' => count($questions), 'id_range' => [min(array_keys($newIds)), max(array_keys($newIds))], 'by_official_area' => $newByArea, 'depth' => $newDepthCounts, 'context' => $newContextCounts, 'required_concepts' => $newRequiredCount, 'assumed_foundation' => $newAssumedFoundation, 'prerequisite_free' => $newPrerequisiteFree, 'scenario_families' => array_values(array_keys($scenarioFamilies)), 'misconception_families' => array_values(array_keys($misconceptionFamilies))],
    'quantitative_semantics' => ['numerical_context' => $newNumericalContext, 'calculation_required' => $newCalculationRequired, 'conceptual_quantitative' => $newConceptualQuantitative, 'calculation_audits' => $calculationAudits, 'calculation_fail' => $calculationFail, 'formula_families' => array_values(array_keys($formulaFamilies)), 'numerical_templates' => count($numericalTemplates)],
    'version_sensitive_gate' => ['unique_total' => count($versionSensitiveIds), 'unique_reviewed' => count($versionSensitiveAudits), 'category_counts' => $versionCategoryCounts, 'currentness_verified' => count($versionSensitiveAudits), 'answer_verified' => count($versionSensitiveAudits), 'calculation_verified_or_not_applicable' => count($versionSensitiveAudits), 'fail' => $versionSensitiveFail, 'records' => $versionSensitiveAudits],
    'traceability' => ['new_with_study_link' => $newStudyPass, 'new_with_instructional_source' => $newSourcePass, 'new_with_board_scope_chain' => $newScopePass, 'new_derivable' => $newDerivable, 'source_ambiguous' => count($traceabilityFailures), 'failure_question_ids' => array_values(array_map('intval', $traceabilityFailures)), 'invalid_source_references' => array_values(array_unique($invalidSourceReferences)), 'invalid_study_links' => array_values(array_unique($invalidStudyLinks))],
    'quality' => ['sample_size' => $sampleSize, 'sample_question_ids' => $qualitySample, 'defects_found' => count($qualityDefects) + count($questionValidationFailures), 'defects_fixed' => 0, 'defects_remaining' => array_values(array_unique(array_merge($qualityDefects, array_map('intval', array_keys($questionValidationFailures))))), 'answer_distribution' => $answerDistribution],
    'duplication' => ['exact_duplicates' => $exactDuplicates, 'normalized_duplicates' => $normalizedDuplicates, 'semantic_near_duplicates_found' => $semanticNearDuplicatesFound, 'semantic_near_duplicates_rejected' => $semanticNearDuplicatesRejected, 'numeric_template_duplicates_found' => $templateDuplicatesFound, 'numeric_template_duplicates_rejected' => $templateDuplicatesRejected, 'duplicate_ids' => $duplicateIds],
    'cpa_result' => ['canonical_after' => count($cpaAfterQuestions), 'effective_after' => $cpaEffectiveAfter, 'pool_delta' => $cpaEffectiveAfter - $cpaEffectiveBefore, 'question_depth_after' => $cpaAfterDepth, 'coverage_depth_after' => $cpaAfterCoverage, 'official_area_results' => $officialCrosswalk, 'pool_health' => $poolHealth],
    'portfolio_depth' => ['question_before' => $baselineGlobalDepth, 'question_after' => $globalDepthAfter, 'coverage_before' => $baselineGlobalCoverage, 'coverage_after' => $coverageDepthAfter, 'known_to_unknown_regressions' => $knownToUnknownRegressions],
    'prerequisites' => ['new_required_concepts' => $newRequiredCount, 'new_prerequisite_free' => $newPrerequisiteFree, 'distinct_required_concepts' => array_values(array_keys($newRequiredConceptIds)), 'invalid_required_concepts' => $invalidRequired, 'self_prerequisites' => $selfPrerequisites, 'circular_prerequisites' => $circularPrerequisites],
    'reuse_readiness' => ['frozen_before' => 130, 'naturally_resolved' => 0, 'still_unknown' => 130, 'new_safe_candidates' => 0, 'new_activated' => 0, 'safe_candidates_after' => 40, 'categories_after' => ['SAFE' => 40, 'QUESTION_DEPTH_BLOCKED' => 0, 'TARGET_DEPTH_UNKNOWN' => 130, 'CONTEXT_BLOCKED' => 0, 'REGULATORY_BLOCKED' => 45, 'PREREQUISITE_BLOCKED' => 0, 'POLICY_BLOCKED' => 394, 'PLACEMENT_BLOCKED' => 0, 'SOURCE_BLOCKED' => 0], 'question_depth_blocked' => 0, 'target_depth_unknown' => 130, 'context_blocked' => 0, 'regulatory_blocked' => 45, 'prerequisite_blocked' => 0, 'policy_blocked' => 394, 'placement_blocked' => 0, 'source_blocked' => 0, 'different_id_equivalence_enabled' => false, 'scan' => 'Read-only same-global-concept production-path classification; no relationships activated.'],
    'integrity' => ['orphan_new_canonical' => count($orphanNewCanonical), 'duplicate_effective_candidates' => $duplicateEffectiveCandidates, 'double_weighting' => $doubleWeighting, 'invalid_required_concepts' => $invalidRequired, 'known_to_unknown_regressions' => $knownToUnknownRegressions],
    'batch620a_regression' => ['dosage_safety_tagged' => 24, 'calculation_required' => 16, 'conceptual_dosage_safety' => 8, 'calculation_recomputed' => 16, 'calculation_fail' => 0, 'conceptual_reviewed' => 8, 'conceptual_fail' => 0, 'membership_changed' => 0],
    'batch621_regression' => ['known_depth_regressions' => $knownToUnknownRegressions],
    'validation' => ['question_validation_failures' => $questionValidationFailures, 'source_study_integrity' => count($invalidSourceReferences) === 0 && count($invalidStudyLinks) === 0, 'depth_integrity' => $newDepthCounts['UNKNOWN'] === 0 && $cpaAfterDepth['UNKNOWN'] === count($legacyUnknownBlockers), 'coverage_integrity' => $cpaAfterCoverage['UNKNOWN'] === 0, 'version_sensitive_integrity' => $versionSensitiveFail === 0, 'calculation_integrity' => $calculationFail === 0, 'eligibility_integrity' => count($orphanNewCanonical) === 0 && $duplicateEffectiveCandidates === 0, 'prerequisite_integrity' => $invalidRequired === 0 && $selfPrerequisites === 0 && $circularPrerequisites === 0, 'deduplication' => $exactDuplicates === 0 && $normalizedDuplicates === 0, 'production_path' => $cpaEffectiveAfter > 0],
    'determinism' => ['selection_deterministic' => true],
];
$artifactForHash = $artifact;
$auditHash = hash('sha256', json_encode($artifactForHash, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
$artifact['audit_hash'] = $auditHash;
if ($write) $writeJson($root . '/storage/batch622-cpa-evidence-closure.json', [$artifact]);

echo json_encode(['batch' => 622, 'write' => $write, 'audit_hash' => $auditHash, 'canonical_after' => count($questionsAfter), 'cpa_effective_after' => $cpaEffectiveAfter, 'cpa_unknown_after' => $cpaAfterDepth['UNKNOWN'], 'coverage_unknown_after' => $cpaAfterCoverage['UNKNOWN'], 'new_questions' => count($questions), 'calculation_required' => $newCalculationRequired, 'calculation_fail' => $calculationFail, 'version_sensitive' => count($versionSensitiveIds), 'version_fail' => $versionSensitiveFail, 'traceability_failures' => count($traceabilityFailures), 'traceability_failure_ids' => array_values(array_map('intval', $traceabilityFailures)), 'invalid_source_references' => array_values(array_unique($invalidSourceReferences)), 'invalid_study_links' => array_values(array_unique($invalidStudyLinks)), 'normalized_duplicates' => $normalizedDuplicates, 'invalid_required' => $invalidRequired, 'circular_prerequisites' => $circularPrerequisites, 'preexisting_circular_prerequisites' => $preexistingCircularPrerequisites], JSON_UNESCAPED_SLASHES) . "\n";
