<?php

declare(strict_types=1);

/**
 * Rebuilds the curated Batch 715 pilot package from the recovered B695
 * inventory and the current read-only taxonomy/study snapshots.
 *
 * The fixed cohort is intentional: all four Architecture proposals are
 * included because their only cited exam program is historical; the LET
 * selections cover all five represented subjects and the evidence/readiness
 * strata described in the package metadata. This tool never writes learner
 * collections, reviewer decisions, taxonomy, study links, or canonical items.
 */

$root = dirname(__DIR__, 2);
$readJson = static function (string $path): array {
    $decoded = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($decoded)) {
        throw new RuntimeException('Expected a JSON object or list at ' . $path);
    }
    return $decoded;
};

$inventoryPath = $root . '/docs/reports/batch-713-cohort-disposition.json';
$inventory = $readJson($inventoryPath);
$items = [];
foreach ((array) ($inventory['items'] ?? []) as $item) {
    if (is_array($item)) $items[(string) ($item['question_id'] ?? '')] = $item;
}

$selection = [
    '9124' => 'Include all four Architecture proposals: this one is a bounded accessibility rule application and its cited June 2026 exam program is historical.',
    '9125' => 'Include all four Architecture proposals: this one tests a numeric grating requirement and the same historical exam-scope warning.',
    '9126' => 'Include all four Architecture proposals: this one tests accessible-corridor coordination and the same historical exam-scope warning.',
    '9127' => 'Include all four Architecture proposals: this one tests statutory architecture scope and the same historical exam-scope warning.',
    '9128' => 'Culture and Arts sample: observable visual-form evidence; pair the current Grades 4/7 curriculum with the unavailable-this-pass PRC scope annex.',
    '9140' => 'Culture and Arts sample: include the Indigenous Peoples Rights Act citation to make the boundary between cultural-rights support and image attribution reviewable.',
    '9164' => 'Culture and Arts sample: include an assessment-alignment case with an accessible official curriculum assessment section.',
    '9200' => 'ECE sample: an exact published study section exists while the proposed topic is not registered; checks portfolio-context claims.',
    '9204' => 'ECE sample: the proposed topic and exact published study section exist; the proposed concept remains unregistered.',
    '9210' => 'ECE sample: a second exact published study section exists while the proposal needs separate language/family evidence review.',
    '9228' => 'ECE sample: include the known child-protection scope limitation; only the official order page was machine-inspected, not its scanned annex.',
    '9238' => 'PE sample: movement-analysis case whose official curriculum pages were downloaded and inspected in this pass.',
    '9250' => 'PE sample: fitness-construct case; inspected curriculum passages do not establish the specific muscular-endurance claim.',
    '9280' => 'PE sample: inclusive activity adaptation case; reviewer must distinguish curricular inclusion from evidence for this exact adaptation.',
    '9290' => 'SNE sample: compares the official inclusive-education statute with current CAST UDL guidance.',
    '9302' => 'SNE sample: an accessible-material accommodation case with a separate construct-validity question for the expert.',
    '9320' => 'SNE sample: include behavior-in-context reasoning where the cited statute does not itself establish the proposed instructional inference.',
    '9348' => 'TVTEd sample: translate an observable workplace standard into an outcome; TESDA definitions provide a direct candidate locator.',
    '9360' => 'TVTEd sample: direct performance/product evidence; TESDA competency-assessment definitions are inspectable.',
    '9378' => 'TVTEd sample: safety-sensitive item; the glossary does not establish a site-specific guard/reporting procedure.',
];

$liveSources = [
    'bp344-2024-irr' => [
        'reachability' => 'REACHABLE_OFFICIAL_PAGE_TEXT_FETCHED', 'identity_confirmed_this_pass' => true,
        'content_examined_this_pass' => true, 'retrieval_date' => '2026-10-26',
        'inspection_locator' => 'NCDA 2024 BP 344 IRR page: Accessible Ramps; Floors and Ground Surfaces; Gratings.',
        'content_digest_sha256' => null, 'retention' => 'No source binary retained; official page text was inspected.',
        'content_summary' => 'The current page states that level changes require ramps subject to listed alternatives, limits grating gaps to 13 mm, calls for level and slip-resistant grates, and says wiring/outlets must not create trip hazards and should be away from accessible routes.',
        'scope_limit' => 'Each question still needs item-level expert review against the complete rule and exceptions; the page does not validate the complete route design.',
    ],
    'prc-architecture-exam-program-june-2026' => [
        'reachability' => 'REACHABLE_OFFICIAL_PRC_INDEX_TEXT_FETCHED', 'identity_confirmed_this_pass' => true,
        'content_examined_this_pass' => true, 'retrieval_date' => '2026-10-26',
        'inspection_locator' => 'PRC June 2026 Architecture program; PRC 2026 exam schedule lists the June 8 and 10, 2026 examination.',
        'content_digest_sha256' => null, 'retention' => 'PDF exceeded the bounded 18 MB download cap; official PRC indexed text was inspected instead.',
        'content_summary' => 'The official indexed program identifies the June 2026 Architecture subject areas and weights. The PRC 2026 schedule confirms that cycle has passed as of this package date.',
        'scope_limit' => 'Historical program scope only. A current exam program/scope was not established; every Architecture item retains a currentness blocker.',
    ],
    'ra-9266-architecture-act' => [
        'reachability' => 'REACHABLE_LEGAL_TEXT_FETCHED', 'identity_confirmed_this_pass' => true,
        'content_examined_this_pass' => true, 'retrieval_date' => '2026-10-26',
        'inspection_locator' => 'Republic Act No. 9266, §3(4)(g), Lawphil text matching the registry identity.',
        'content_digest_sha256' => null, 'retention' => 'No source binary retained; official law text page was inspected.',
        'content_summary' => 'The statutory list of architecture services includes architectural layout of mechanical, electrical, electronic, sanitary, plumbing, communications and other utility systems.',
        'scope_limit' => 'The cited provision establishes scope language, not detailed engineering compliance or a substitute for coordination with the relevant specialists.',
    ],
    'prc-resolution-20-2025-annex-a' => [
        'reachability' => 'HTTP_200_PDF_BOUNDED_DOWNLOAD_LIMIT', 'identity_confirmed_this_pass' => false,
        'content_examined_this_pass' => false, 'retrieval_date' => '2026-10-26',
        'inspection_locator' => 'Official PRC PDF URL returned application/pdf; full transfer exceeded the 18,000,000-byte limit. B695 records author-reported text extraction at the relevant annexes.',
        'content_digest_sha256' => null, 'retention' => 'No partial PDF retained; content was not inspected in this pass.',
        'content_summary' => 'No content claim is made from the title, URL, or B695 author note. The assigned reviewer must inspect the applicable Annex A/B/C/D pages.',
        'scope_limit' => 'Source identity/contents require independent reviewer confirmation; no claim about answer correctness follows from the exam-scope citation.',
    ],
    'deped-matatag-music-arts-2023-g4-g7' => [
        'reachability' => 'REACHABLE_OFFICIAL_PDF_TEXT_FETCHED', 'identity_confirmed_this_pass' => true,
        'content_examined_this_pass' => true, 'retrieval_date' => '2026-10-26',
        'inspection_locator' => 'DepEd MATATAG Music and Arts Curriculum Guide, Grades 4 and 7, inspected pages 17–18 and 22 (PDF page labels 16–17 and 21 where shown).',
        'content_digest_sha256' => null, 'retention' => 'Official PDF text was inspected through a bounded text extraction; no binary retained.',
        'content_summary' => 'The guide describes formative/summative assessment against standards and performance criteria, with assessment strategies aligned to learning standards; the competencies use local arts works and cultural context.',
        'scope_limit' => 'Grades 4 and 7 only; it is not a complete all-grade curriculum and does not by itself determine a unique answer for a specific item.',
    ],
    'ra-8371-ipra-e-library' => [
        'reachability' => 'REACHABLE_SUPREME_COURT_E_LIBRARY_TEXT_FETCHED', 'identity_confirmed_this_pass' => true,
        'content_examined_this_pass' => true, 'retrieval_date' => '2026-10-26',
        'inspection_locator' => 'Republic Act No. 8371, §§2–3; Supreme Court E-Library.',
        'content_digest_sha256' => null, 'retention' => 'No source binary retained; statutory text page was inspected.',
        'content_summary' => 'The Act recognizes cultural preservation, cultural integrity and participation; it defines Free and Prior Informed Consent in relation to covered activities.',
        'scope_limit' => 'These provisions do not establish copyright, attribution, or permission rules for an arbitrary online image; the dossier flags that gap.',
    ],
    'deped-kindergarten-curriculum-guide-2023' => [
        'reachability' => 'REACHABLE_OFFICIAL_PDF_TEXT_FETCHED', 'identity_confirmed_this_pass' => true,
        'content_examined_this_pass' => true, 'retrieval_date' => '2026-10-26',
        'inspection_locator' => 'MATATAG Kindergarten Curriculum Guide, including the curriculum framework and assessment section (B695 proposed locators pp. 5, 13, 16, 30).',
        'content_digest_sha256' => null, 'retention' => 'Official PDF text was inspected through bounded page text extraction; no binary retained.',
        'content_summary' => 'The guide identifies play-based, learner-centered learning and describes regular, continuous observation for monitoring children’s growth and development.',
        'scope_limit' => 'Kindergarten scope only. The inspected passages do not by themselves verify the proposed portfolio documentation fields or every home-language claim.',
    ],
    'deped-do-40-2012-child-protection' => [
        'reachability' => 'HTTP_200_OFFICIAL_DEPED_POLICY_PAGE', 'identity_confirmed_this_pass' => true,
        'content_examined_this_pass' => true, 'retrieval_date' => '2026-10-26',
        'inspection_locator' => 'DepEd DO 40, s. 2012 official order page; order title names abuse, violence, exploitation, discrimination, bullying and other forms of abuse.',
        'content_digest_sha256' => '68cb78e0d09f6dc6a81b25eaca089068ee57ad92d3c95ff51ae38bd5b0f61b4f', 'retention' => 'Bounded 263,125-byte HTML response hashed and discarded after text inspection; scanned annex not retained or text-extracted.',
        'content_summary' => 'The official order page identifies the policy and expressly names bullying and discrimination in the covered school harms.',
        'scope_limit' => 'The scanned policy annex and current local reporting/referral procedure were not examined; no deadline or specific response protocol is inferred.',
    ],
    'deped-matatag-pe-health-2023-g4-g7' => [
        'reachability' => 'HTTP_200_OFFICIAL_DEPED_PDF', 'identity_confirmed_this_pass' => true,
        'content_examined_this_pass' => true, 'retrieval_date' => '2026-10-26',
        'inspection_locator' => 'MATATAG Physical Education and Health Curriculum Guide, Grades 4 and 7, PDF pp. 5–8, 15–16, 20–23.',
        'content_digest_sha256' => '5637b2f067a6bac007e214aca1343c37df80d7c13345cfdd4b308116ca453dc3', 'retention' => 'Bounded 878,014-byte PDF downloaded, signature-checked, page text extracted, hashed, then discarded.',
        'content_summary' => 'The guide lists body, space, effort and relationships as movement concepts, and describes fitness/movement competencies and assessment. The checked Grade 4 pages emphasize agility, balance, coordination and speed; “muscular endurance” was not located in the checked passages.',
        'scope_limit' => 'Grades 4 and 7 only and not medical guidance. Do not infer an age-specific fitness test or safety protocol from these pages.',
    ],
    'republic-act-11650' => [
        'reachability' => 'REACHABLE_SUPREME_COURT_E_LIBRARY_TEXT_FETCHED', 'identity_confirmed_this_pass' => true,
        'content_examined_this_pass' => true, 'retrieval_date' => '2026-10-26',
        'inspection_locator' => 'Republic Act No. 11650, §§2–4; Supreme Court E-Library.',
        'content_digest_sha256' => null, 'retention' => 'No source binary retained; statutory text page was inspected.',
        'content_summary' => 'The Act addresses accessible inclusive education, participation, accommodations and modifications; its definitions include accessible instructional media and reasonable accommodation.',
        'scope_limit' => 'The scanned implementing-rules extraction cited in B695 was unavailable there; this Act does not itself establish every assessment-validity or behavior-support inference in the pilot.',
    ],
    'cast-udl-guidelines-3-0-2024' => [
        'reachability' => 'REACHABLE_CAST_GUIDELINES_TEXT_FETCHED', 'identity_confirmed_this_pass' => true,
        'content_examined_this_pass' => true, 'retrieval_date' => '2026-10-26',
        'inspection_locator' => 'CAST UDL Guidelines 3.0, public guideline page: multiple means of engagement, representation, and action/expression.',
        'content_digest_sha256' => null, 'retention' => 'Official guideline page text was inspected; no PDF binary retained.',
        'content_summary' => 'The framework presents options for perception, multiple media, accessible materials and technologies, varied methods of response, and learner agency.',
        'scope_limit' => 'Professional guidance, not Philippine law and not a subject-expert judgment about the exact proposed answer.',
    ],
    'tesda-tvet-glossary-2025' => [
        'reachability' => 'REACHABLE_TESDA_OFFICIAL_PDF_TEXT_FETCHED', 'identity_confirmed_this_pass' => true,
        'content_examined_this_pass' => true, 'retrieval_date' => '2026-10-26',
        'inspection_locator' => 'TESDA 2025 TVET Glossary, pp. 25–27 and competency-assessment definitions; official 180-page PDF text.',
        'content_digest_sha256' => null, 'retention' => 'Official indexed PDF text was inspected; no full PDF retained.',
        'content_summary' => 'Definitions connect competency to demonstrated workplace performance, define competency assessment as evidence collection/judgment, and describe competency-based curricula through outcomes, conditions and assessment methods.',
        'scope_limit' => 'The glossary is not a current machine-specific occupational-safety procedure; it does not establish the exact local response to a loose guard.',
    ],
];

$itemNotes = [
    '9124' => ['alignment' => ['bp344-2024-irr' => ['status' => 'DIRECT_RULE_CANDIDATE', 'note' => 'The current NCDA page describes level-change ramp requirements and listed alternatives; reviewer must test whether the stem supplies enough context for one strongest response.']], 'questions' => ['Does the proposed alternative match the exact site/building conditions and current rule exceptions?', 'Does the question give enough detail to distinguish a ramp from other permitted step-free alternatives?', 'Does the current Architecture exam scope still cover this competency?']],
    '9125' => ['alignment' => ['bp344-2024-irr' => ['status' => 'DIRECT_NUMERIC_RULE_CANDIDATE', 'note' => 'The page states a 13 mm maximum spacing between grates and separately addresses levelness/slip resistance; confirm the cited clause applies to this floor grate/path context.']], 'questions' => ['Does the 13 mm requirement apply to the exact grate location shown in the stem?', 'Are the other stated grate properties independently supported and not conflated with the identified defect?', 'Is the item relevant to the current Architecture program?']],
    '9126' => ['alignment' => ['bp344-2024-irr' => ['status' => 'DIRECT_RULE_CANDIDATE', 'note' => 'The page states wiring/outlets should not create trip hazards and should be positioned away from accessible pathways/corridors. Confirm exact applicability and any contextual assumptions.']], 'questions' => ['Does the source require the stated relocation/rerouting in this scenario?', 'Is the proposed answer uniquely best without adding unsupported site assumptions?', 'Is the current Architecture exam scope verified?']],
    '9127' => ['alignment' => ['ra-9266-architecture-act' => ['status' => 'SCOPE_LANGUAGE_CANDIDATE', 'note' => 'RA 9266 §3(4)(g) includes utility-system layout within architecture services; it does not resolve detailed engineering performance.']], 'questions' => ['Does the item distinguish architectural coordination from the specialist’s detailed engineering responsibility?', 'Does the cited Act directly support the whole answer, or only professional scope?', 'Is the historical PRC program still a valid exam-scope reference?']],
    '9128' => ['alignment' => ['deped-matatag-music-arts-2023-g4-g7' => ['status' => 'PARTIAL_CURRICULUM_CANDIDATE', 'note' => 'The guide grounds learning in observable creative work and local context; it does not alone establish that this is the unique strongest first interpretive response.']], 'questions' => ['Does describing observable formal features before interpreting meaning match the board-specific competency?', 'Are multiple interpretations possible from the stem’s sparse visual description?', 'Which current PRC Annex C competency is the item intended to assess?']],
    '9140' => ['alignment' => ['ra-8371-ipra-e-library' => ['status' => 'RIGHTS_CONTEXT_ONLY', 'note' => 'RA 8371 supports cultural integrity and participation, but does not establish attribution/copyright or permission rules for a found image.']], 'questions' => ['What exact primary source governs creator attribution and reuse of this image?', 'Does the item avoid equating online availability with consent or permission?', 'Is the answer supported by the cited curriculum and law rather than general intuition?']],
    '9164' => ['alignment' => ['deped-matatag-music-arts-2023-g4-g7' => ['status' => 'DIRECT_ASSESSMENT_ALIGNMENT_CANDIDATE', 'note' => 'The guide says assessment should align with standards and teaching strategies and refers to performance criteria; the reviewer must verify the texture-specific learning target.']], 'questions' => ['Does the selected evidence directly measure the stated texture-effect objective?', 'Could another distractor also elicit the same objective?', 'Does the exact learning competency apply to the cited grade band?']],
    '9200' => ['alignment' => ['deped-kindergarten-curriculum-guide-2023' => ['status' => 'PARTIAL_OBSERVATION_CANDIDATE', 'note' => 'The guide describes regular continuous observation for growth/development; the checked text does not explicitly require every portfolio field in the answer.']], 'questions' => ['Does the curriculum or another authoritative assessment source require the proposed context/date/learner-explanation evidence?', 'Is the answer stronger than alternatives based on the exact planning objective?', 'Does the existing section’s content match the new question without creating an unapproved link?']],
    '9204' => ['alignment' => ['deped-kindergarten-curriculum-guide-2023' => ['status' => 'PLAY_BASED_CURRICULUM_CANDIDATE', 'note' => 'The guide describes learner-centered play-based learning; verify that the optional prompt/material preserves the child’s initiative in this particular vignette.']], 'questions' => ['Is the prompt genuinely optional and responsive rather than directing the play?', 'Is there authoritative support for the proposed scaffolding decision?', 'Should the existing exact study section be linked after content and board/subject fit review?']],
    '9210' => ['alignment' => ['deped-kindergarten-curriculum-guide-2023' => ['status' => 'LANGUAGE_SUPPORT_NOT_LOCATED', 'note' => 'The official guide was inspected, but the proposed specific home-language partnership claim was not located in the checked passages; the source title alone is not support.']], 'questions' => ['Which current DepEd policy/curriculum passage supports using this home-language bridge?', 'Does the activity preserve the child’s language choice and avoid making a family-reported example a diagnostic claim?', 'Is the source locator sufficiently specific?']],
    '9228' => ['alignment' => ['deped-do-40-2012-child-protection' => ['status' => 'POLICY_IDENTITY_AND_SCOPE_ONLY', 'note' => 'The official order page names bullying and discrimination within the policy scope; the scanned annex and operational procedure were not inspected.']], 'questions' => ['Which current Child Protection Committee/school process applies, and has the policy been amended or supplemented?', 'Does “recognize as a concern and use applicable process” avoid promising a specific legal finding?', 'What exact annex provision supports classification of this scenario?']],
    '9238' => ['alignment' => ['deped-matatag-pe-health-2023-g4-g7' => ['status' => 'DIRECT_MOVEMENT_CONCEPT_CANDIDATE', 'note' => 'The guide defines space as where the body moves and lists body/space/effort/relationships; examine whether stepping outside a lane is best diagnosed as pathway/space.']], 'questions' => ['Is the lane task about space/pathway rather than balance, control or task comprehension?', 'Does the answer use the curriculum’s actual movement-concept terms?', 'Does the cited Grade 4/7 guide cover the intended learner level?']],
    '9250' => ['alignment' => ['deped-matatag-pe-health-2023-g4-g7' => ['status' => 'SPECIFIC_CONSTRUCT_NOT_LOCATED', 'note' => 'The checked curriculum pages list fitness concepts such as agility, balance, coordination and speed; “muscular endurance” was not located in those inspected passages.']], 'questions' => ['Which current grade-level standard defines muscular endurance for this task?', 'Is repeated controlled action a valid measure under the stated conditions, and are competing fitness components excluded?', 'Does this source edition actually cover the intended grade and construct?']],
    '9280' => ['alignment' => ['deped-matatag-pe-health-2023-g4-g7' => ['status' => 'PARTIAL_INCLUSION_CANDIDATE', 'note' => 'The guide includes inclusive participation as a curriculum principle, but the checked passages do not establish the exact lighter-equipment/closer-target adaptation.']], 'questions' => ['Does the adaptation preserve the target skill and decision process for all learners in the scenario?', 'What source supports this specific adaptation and not only inclusion in general?', 'Could another response preserve the same skill more safely or equitably?']],
    '9290' => ['alignment' => ['republic-act-11650' => ['status' => 'STATUTORY_ACCESS_CANDIDATE', 'note' => 'The Act addresses accessible learning and reasonable accommodations; it does not specify this exact plant-growth instructional option.'], 'cast-udl-guidelines-3-0-2024' => ['status' => 'FRAMEWORK_CANDIDATE', 'note' => 'CAST lists multiple ways to perceive information, accessible materials and varied expression; it is professional guidance, not Philippine law.']], 'questions' => ['Do both options preserve the same learning goal while removing the stated text-access barrier?', 'Does the item distinguish accessible representation from changing the science construct?', 'Which exact CAST UDL consideration best supports the proposed option?']],
    '9302' => ['alignment' => ['republic-act-11650' => ['status' => 'ACCOMMODATION_CANDIDATE_VALIDITY_GAP', 'note' => 'The Act defines reasonable accommodation and accessible instructional materials; a separate assessment-validity source is needed for the “same reasoning task” condition.']], 'questions' => ['Does enlargement/digital access remove an irrelevant barrier for this learner without altering the assessed construct?', 'What assessment guidance supports preserving the same reasoning demand?', 'Does the answer avoid prescribing an accommodation without learner context?']],
    '9320' => ['alignment' => ['republic-act-11650' => ['status' => 'INCLUSION_CONTEXT_ONLY_BEHAVIOR_CLAIM_NOT_ESTABLISHED', 'note' => 'The Act provides an inclusion/accommodation framework but does not itself establish the proposed behavior-function inference.']], 'questions' => ['What qualified pedagogical source supports examining task format and duration first?', 'Does one observation justify the inference, or should the item ask the teacher to gather more evidence?', 'Does the explanation avoid diagnosis or overclaiming causation?']],
    '9348' => ['alignment' => ['tesda-tvet-glossary-2025' => ['status' => 'DIRECT_COMPETENCY_OUTCOME_CANDIDATE', 'note' => 'The glossary ties competency to workplace performance and describes outcome/condition components in competency-based curricula; inspect the specific applicable definition and source circular.']], 'questions' => ['Does the outcome identify an observable task, conditions/tools and a checkable criterion?', 'Does the cited workplace standard actually require this measurement/cut/verification task?', 'Is the PRC common-competency scope current?']],
    '9360' => ['alignment' => ['tesda-tvet-glossary-2025' => ['status' => 'DIRECT_EVIDENCE_COLLECTION_CANDIDATE', 'note' => 'The glossary defines competency assessment through collecting evidence and making judgments against performance standards; verify the cited assessment context.']], 'questions' => ['Are observation and product evidence both required by the task standard?', 'Are the criteria valid, sufficient and current for this qualification?', 'Does the answer distinguish direct performance evidence from indirect knowledge evidence?']],
    '9378' => ['alignment' => ['tesda-tvet-glossary-2025' => ['status' => 'SAFETY_PROCEDURE_NOT_ESTABLISHED', 'note' => 'The glossary does not establish the exact machine-guard hazard control or training-site reporting procedure; a current OSH and site procedure source is needed.']], 'questions' => ['Which current TESDA/OHS/industry rule applies to this machine and hazard?', 'Does the training site procedure require isolation, tagging or a specific report route?', 'Does the answer avoid inventing a universal procedure from a glossary?']],
];

$taxonomyTopics = $readJson($root . '/storage/taxonomy/topics.json');
$taxonomyConcepts = $readJson($root . '/storage/taxonomy/concepts.json');
$domains = $readJson($root . '/storage/taxonomy/domains.json');
$materials = $readJson($root . '/storage/study-materials.json');
$lessons = $readJson($root . '/storage/study-lessons.json');
$links = $readJson($root . '/storage/study-question-links.json');
$registry = $readJson($root . '/storage/sources.json');
$topicById = [];
foreach ($taxonomyTopics as $row) if (is_array($row)) $topicById[(string) ($row['id'] ?? '')] = $row;
$conceptById = [];
foreach ($taxonomyConcepts as $row) if (is_array($row)) $conceptById[(string) ($row['id'] ?? '')] = $row;
$domainById = [];
foreach ($domains as $row) if (is_array($row)) $domainById[(string) ($row['id'] ?? '')] = $row;
$materialById = [];
foreach ($materials as $row) if (is_array($row)) $materialById[(string) ($row['id'] ?? '')] = $row;
$lessonByMaterial = [];
foreach ($lessons as $row) if (is_array($row)) $lessonByMaterial[(string) ($row['material_id'] ?? '')] = $row;
$registryById = [];
foreach ($registry as $row) if (is_array($row)) $registryById[(string) ($row['id'] ?? '')] = $row;

$normalize = static function (string $value): string {
    $value = mb_strtolower($value, 'UTF-8');
    $value = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $value) ?? $value;
    return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
};
$tokens = static function (string $value) use ($normalize): array {
    $stop = ['and', 'or', 'the', 'of', 'to', 'in', 'for', 'a', 'an', 'with', 'from'];
    return array_values(array_unique(array_filter(explode(' ', $normalize($value)), static fn(string $token): bool => $token !== '' && !in_array($token, $stop, true))));
};
$similarity = static function (string $left, string $right) use ($tokens): float {
    $a = $tokens($left); $b = $tokens($right);
    if ($a === [] || $b === []) return 0.0;
    $shared = count(array_intersect($a, $b));
    return $shared / count(array_unique(array_merge($a, $b)));
};

$cohortItems = [];
$readinessItems = [];
$cohortHash = hash_file('sha256', $inventoryPath);
foreach ($selection as $questionId => $reason) {
    $questionId = (string) $questionId;
    if (!isset($items[$questionId])) throw new RuntimeException('Selected question missing from recovered B695 inventory: ' . $questionId);
    $item = $items[$questionId];
    $proposal = (array) ($item['proposed_question'] ?? []);
    $sourceIds = array_values(array_map('strval', (array) ($item['source_ids'] ?? [])));
    $versionPayload = [
        'proposal_id' => (string) ($item['id'] ?? ''),
        'question_id' => $questionId,
        'board_id' => (string) ($item['board_id'] ?? ''),
        'subject_id' => (string) ($item['subject_id'] ?? ''),
        'question' => $proposal,
        'source_ids' => $sourceIds,
        'source_evidence' => array_values((array) ($item['source_evidence'] ?? [])),
    ];
    $questionVersion = hash('sha256', json_encode($versionPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

    $topicId = (string) ($item['topic_id'] ?? '');
    $conceptId = (string) ($item['concept_id'] ?? '');
    $topic = $topicById[$topicId] ?? null;
    $concept = $conceptById[$conceptId] ?? null;
    $domainId = (string) ($item['domain_id'] ?? '');
    $topicCandidates = [];
    if (!is_array($topic)) {
        foreach ($taxonomyTopics as $candidate) {
            if (!is_array($candidate) || (string) ($candidate['domain_id'] ?? '') !== $domainId) continue;
            $score = $similarity((string) ($item['topic_name'] ?? ''), (string) ($candidate['name'] ?? ''));
            if ($score >= 0.35) $topicCandidates[] = ['id' => (string) $candidate['id'], 'name' => (string) $candidate['name'], 'similarity' => round($score, 3), 'status' => 'REVIEW_CANDIDATE_ONLY'];
        }
        usort($topicCandidates, static fn(array $a, array $b): int => ($b['similarity'] <=> $a['similarity']) ?: strcmp($a['id'], $b['id']));
        $topicCandidates = array_slice($topicCandidates, 0, 3);
    } else {
        $topicCandidates[] = ['id' => $topicId, 'name' => (string) ($topic['name'] ?? ''), 'similarity' => 1.0, 'status' => 'EXACT_REGISTERED_TOPIC_REQUIRES_REVIEWER_MAPPING'];
    }
    $conceptCandidates = [];
    if (!is_array($concept)) {
        $activeParentTopic = is_array($topic) ? $topicId : '';
        foreach ($taxonomyConcepts as $candidate) {
            if (!is_array($candidate)) continue;
            if ($activeParentTopic !== '' && (string) ($candidate['topic_id'] ?? '') !== $activeParentTopic) continue;
            if ($activeParentTopic === '' && !in_array((string) ($candidate['topic_id'] ?? ''), array_column($topicCandidates, 'id'), true)) continue;
            $score = $similarity((string) ($item['concept_name'] ?? ''), (string) ($candidate['name'] ?? ''));
            if ($score >= 0.35) $conceptCandidates[] = ['id' => (string) $candidate['id'], 'topic_id' => (string) $candidate['topic_id'], 'name' => (string) $candidate['name'], 'similarity' => round($score, 3), 'status' => 'REVIEW_CANDIDATE_ONLY'];
        }
        usort($conceptCandidates, static fn(array $a, array $b): int => ($b['similarity'] <=> $a['similarity']) ?: strcmp($a['id'], $b['id']));
        $conceptCandidates = array_slice($conceptCandidates, 0, 3);
    } else {
        $conceptCandidates[] = ['id' => $conceptId, 'name' => (string) ($concept['name'] ?? ''), 'similarity' => 1.0, 'status' => 'EXACT_REGISTERED_CONCEPT_REQUIRES_REVIEWER_MAPPING'];
    }

    $study = is_array($item['study_relationship'] ?? null) ? $item['study_relationship'] : [];
    $materialId = (string) ($study['material_id'] ?? '');
    $sectionId = (string) ($study['section_id'] ?? '');
    $lesson = $lessonByMaterial[$materialId] ?? null;
    $section = null;
    foreach ((array) ($lesson['sections'] ?? []) as $sectionRow) {
        if (is_array($sectionRow) && (string) ($sectionRow['id'] ?? '') === $sectionId) { $section = $sectionRow; break; }
    }
    $currentQuestionLink = false;
    foreach ($links as $link) {
        if (is_array($link) && (string) ($link['question_id'] ?? '') === $questionId
            && (string) ($link['material_id'] ?? '') === $materialId && (string) ($link['section_id'] ?? '') === $sectionId) {
            $currentQuestionLink = true; break;
        }
    }

    $sourceSummaries = [];
    $sourceDossiers = [];
    foreach ($sourceIds as $sourceId) {
        $ev = null;
        foreach ((array) ($item['source_evidence'] ?? []) as $sourceEvidence) {
            if (is_array($sourceEvidence) && (string) ($sourceEvidence['source_id'] ?? '') === $sourceId) { $ev = $sourceEvidence; break; }
        }
        $snapshot = is_array($ev['source_record_snapshot'] ?? null) ? $ev['source_record_snapshot'] : [];
        $current = $registryById[$sourceId] ?? [];
        $observation = $liveSources[$sourceId] ?? [
            'reachability' => 'NOT_RECHECKED_THIS_PASS', 'identity_confirmed_this_pass' => false,
            'content_examined_this_pass' => false, 'retrieval_date' => null,
            'inspection_locator' => 'No current source inspection was performed for this source in the pilot preparation pass.',
            'content_digest_sha256' => null, 'retention' => 'No downloaded content retained.',
            'content_summary' => 'No item-level claim is asserted from this source in this dossier.',
            'scope_limit' => 'Qualified reviewer must inspect the source directly.',
        ];
        $alignment = $itemNotes[$questionId]['alignment'][$sourceId] ?? [
            'status' => (($snapshot['source_role'] ?? $current['source_role'] ?? '') === 'SCOPE_SOURCE') ? 'EXAM_SCOPE_ONLY' : 'NOT_PRIMARY_ITEM_SUPPORT',
            'note' => 'This source does not independently establish the proposed answer; inspect only for its assigned role.',
        ];
        $sourceDossiers[] = [
            'source_id' => $sourceId,
            'title' => (string) ($snapshot['title'] ?? $current['title'] ?? $sourceId),
            'authority' => (string) ($snapshot['authority'] ?? $current['authority'] ?? ''),
            'url' => (string) ($snapshot['url'] ?? $current['url'] ?? ''),
            'source_role' => (string) ($snapshot['source_role'] ?? $current['source_role'] ?? ''),
            'source_type' => (string) ($snapshot['source_type'] ?? $current['source_type'] ?? ''),
            'publication_date' => $snapshot['publication_date'] ?? $current['publication_date'] ?? null,
            'version' => (string) ($snapshot['version'] ?? $current['version'] ?? ''),
            'registry_snapshot_match' => isset($current['id']) && (string) ($current['title'] ?? '') === (string) ($snapshot['title'] ?? ''),
            'b695_evidence_status' => (string) ($ev['source_evidence_status'] ?? 'NOT_RECORDED'),
            'b695_limitations' => array_values((array) ($ev['limitations'] ?? [])),
            'url_reachable' => str_starts_with((string) $observation['reachability'], 'REACHABLE') || str_starts_with((string) $observation['reachability'], 'HTTP_200'),
            'source_identity_confirmed' => (bool) $observation['identity_confirmed_this_pass'],
            'content_examined' => (bool) $observation['content_examined_this_pass'],
            'source_examination' => $observation,
            'claim_support_candidate' => $alignment,
            'expert_source_review' => 'PENDING_QUALIFIED_REVIEWER',
        ];
        $sourceSummaries[] = ['id' => $sourceId, 'title' => $sourceDossiers[array_key_last($sourceDossiers)]['title'], 'role' => $sourceDossiers[array_key_last($sourceDossiers)]['source_role']];
    }

    $studyStatus = is_array($section) ? 'EXACT_SECTION_MATCH' : 'MISSING_SECTION';
    if ($currentQuestionLink) $studyStatus = 'EXISTING_QUESTION_LINK';
    $blockers = ['PENDING_QUALIFIED_ACADEMIC_REVIEW', 'PENDING_INDEPENDENT_ITEM_LEVEL_SOURCE_REVIEW'];
    if (!is_array($topic)) $blockers[] = 'PROPOSED_TOPIC_NOT_REGISTERED';
    if (!is_array($concept)) $blockers[] = 'PROPOSED_CONCEPT_NOT_REGISTERED';
    if (!$currentQuestionLink && $studyStatus === 'MISSING_SECTION') $blockers[] = 'PROPOSED_STUDY_SECTION_MISSING';
    if (in_array('HISTORICAL_EXAM_PROGRAM_CURRENTNESS_RECHECK', (array) ($item['secondary_flags'] ?? []), true)) $blockers[] = 'HISTORICAL_EXAM_SCOPE_RECHECK_REQUIRED';
    if (in_array('LIMITED_SOURCE_SCOPE_RECHECK', (array) ($item['secondary_flags'] ?? []), true)) $blockers[] = 'SOURCE_SCOPE_RECHECK_REQUIRED';

    $cohortItems[] = [
        'proposal_id' => (string) $item['id'], 'question_id' => $questionId,
        'board' => ['id' => (string) $item['board_id'], 'name' => (string) $item['board_name']],
        'subject' => ['id' => (string) $item['subject_id'], 'name' => (string) $item['subject_name']],
        'topic' => ['id' => $topicId, 'proposed_name' => (string) $item['topic_name'], 'registered' => is_array($topic)],
        'concept' => ['id' => $conceptId, 'proposed_name' => (string) $item['concept_name'], 'registered' => is_array($concept)],
        'question_version' => $questionVersion,
        'review_state' => 'PENDING_REVIEW', 'production_status' => 'NONCANONICAL_NOT_IN_LEARNER_POOLS',
        'source_identities' => $sourceSummaries,
        'source_scope' => ['scope_source_ids' => array_values(array_map(static fn(array $s): string => $s['id'], array_filter($sourceSummaries, static fn(array $s): bool => $s['role'] === 'SCOPE_SOURCE'))), 'status' => in_array('HISTORICAL_EXAM_PROGRAM_CURRENTNESS_RECHECK', (array) ($item['secondary_flags'] ?? []), true) ? 'HISTORICAL_PROGRAM_RECHECK_REQUIRED' : 'CURRENT_SCOPE_SOURCE_INSPECTION_REQUIRED'],
        'source_currentness' => ['status' => in_array('HISTORICAL_EXAM_PROGRAM_CURRENTNESS_RECHECK', (array) ($item['secondary_flags'] ?? []), true) ? 'HISTORICAL_SCOPE_NOT_CURRENT' : 'VERSIONED_OR_REGULATORY_SOURCE_REVIEW_REQUIRED', 'checked_at' => '2026-10-26'],
        'study_relationships' => ['material_id' => $materialId, 'material_title' => (string) ($materialById[$materialId]['title'] ?? $study['material_title'] ?? ''), 'section_id' => $sectionId, 'section_heading' => (string) ($section['heading'] ?? $study['section_heading'] ?? ''), 'status' => $studyStatus, 'current_material_exists' => isset($materialById[$materialId]) && ($materialById[$materialId]['status'] ?? 'published') === 'published', 'current_section_exists' => is_array($section), 'current_question_link_exists' => $currentQuestionLink, 'required_for_import' => false],
        'review_blockers' => array_values(array_unique($blockers)),
        'required_reviewer_capability' => ['REVIEW', 'SOURCE_REVIEW', 'TAXONOMY_RECONCILE', 'STUDY_LINK'],
        'selection_rationale' => $reason,
    ];

    $sourceItems = [];
    foreach ($sourceDossiers as $sourceDossier) {
        $sourceItems[] = $sourceDossier;
    }
    $readinessItems[] = [
        'proposal_id' => (string) $item['id'], 'question_id' => $questionId,
        'question_version' => $questionVersion,
        'version_basis' => 'B714 AcademicReviewWorkflowService::version() payload for the original B695 proposal; any later revision makes this package version stale.',
        'review_state' => 'PENDING_REVIEW', 'academic_approval' => 'NOT_APPROVED', 'production_status' => 'NONCANONICAL_NOT_IN_LEARNER_POOLS',
        'question' => ['stem' => (string) ($proposal['stem'] ?? ''), 'choices' => array_values((array) ($proposal['choices'] ?? [])), 'proposed_answer' => (string) ($proposal['proposed_answer'] ?? ''), 'explanation' => (string) ($proposal['explanation'] ?? ''), 'difficulty' => (string) ($proposal['difficulty'] ?? '')],
        'specific_claim_to_review' => (string) ($item['proposal_reason']['specific_claim_to_review'] ?? ''),
        'proposed_source_locator' => (string) ($item['source_support_locator'] ?? ''),
        'sources' => $sourceItems,
        'source_review_status' => 'PENDING_QUALIFIED_SOURCE_REVIEW',
        'taxonomy_resolution' => [
            'proposed_domain' => ['id' => $domainId, 'name' => (string) ($domainById[$domainId]['name'] ?? '')],
            'proposed_topic' => ['id' => $topicId, 'name' => (string) $item['topic_name'], 'registered' => is_array($topic)],
            'topic_candidates' => $topicCandidates,
            'proposed_concept' => ['id' => $conceptId, 'name' => (string) $item['concept_name'], 'registered' => is_array($concept)],
            'concept_candidates' => $conceptCandidates,
            'status' => is_array($topic) && is_array($concept) ? 'EXACT_REGISTERED_MAPPING_REQUIRES_REVIEW' : 'AWAITING_REVIEW_OR_TAXONOMY_GOVERNANCE',
            'activation' => 'NONE; recommendations are read-only and require an authorized reviewer decision or governance action.',
        ],
        'study_resolution' => [
            'status' => $studyStatus,
            'material_id' => $materialId, 'material_title' => (string) ($materialById[$materialId]['title'] ?? $study['material_title'] ?? ''),
            'section_id' => $sectionId, 'section_heading' => (string) ($section['heading'] ?? $study['section_heading'] ?? ''),
            'section_body' => is_array($section) ? (string) ($section['body'] ?? '') : null,
            'current_material_exists' => isset($materialById[$materialId]) && ($materialById[$materialId]['status'] ?? 'published') === 'published',
            'current_section_exists' => is_array($section), 'current_question_link_exists' => $currentQuestionLink,
            'relationship_activation' => 'NONE; an exact section still requires an authorized reviewer action. Missing sections remain unresolved; no lesson was created.',
        ],
        'review_questions' => array_values((array) $itemNotes[$questionId]['questions']),
        'reviewer_action' => 'Inspect the cited passages, record item-level source evidence and currentness, resolve taxonomy only against active scoped taxonomy, decide whether the exact study section is appropriate, then make an independent academic decision. Do not treat this dossier as approval.',
        'required_independent_review' => ['SOURCE_EXPERT_REVIEW' => 'PENDING', 'ACADEMIC_APPROVAL' => 'PENDING'],
    ];
}

if (count($cohortItems) !== 20 || count($readinessItems) !== 20) throw new RuntimeException('The pilot package must contain exactly 20 source-backed records.');

$algorithm = [
    'id' => 'b715-curated-stratified-pilot-v1',
    'target' => 20,
    'deterministic' => true,
    'tie_breaker' => 'Explicit proposal IDs are fixed in the selection manifest; source inventory order and random sampling are not used.',
    'rules' => [
        'Include all 4 Architecture items so every known historical-program currentness recheck is represented.',
        'Select 3 items each from Culture and Arts, Physical Education, Special Needs Education, and Technical-Vocational Teacher Education, using distinct proposed concepts and different evidence questions.',
        'Select 4 Early Childhood Education items to cover an exact registered topic, exact published study sections, and one known child-protection source-scope limitation.',
        'Cover all five LET subjects. The 4-of-20 Architecture share intentionally oversamples the small board cohort to exercise its unique currentness risk; the remaining 16 represent the LET-heavy cohort.',
        'Prefer proposals with an inspectable authoritative source where possible, while retaining cases with missing/limited evidence and both exact and missing study-section relationships.',
    ],
    'selection_rationales_by_question_id' => $selection,
];
$cohort = [
    'schema' => 'boardprep.batch-715-pilot-cohort.v1',
    'generated_at_utc' => gmdate('c'),
    'source_cohort' => ['path' => 'docs/reports/batch-713-cohort-disposition.json', 'sha256' => $cohortHash, 'b695_commit' => (string) ($inventory['source']['commit_sha'] ?? 'c3d6b217aed480ebda1c81c1900f907a749befe3'), 'expected_total' => 276],
    'selection_algorithm' => $algorithm,
    'selected_count' => count($cohortItems),
    'boards_represented' => array_values(array_unique(array_map(static fn(array $i): string => (string) $i['board']['id'], $cohortItems))),
    'subjects_represented' => array_values(array_unique(array_map(static fn(array $i): string => (string) $i['subject']['id'], $cohortItems))),
    'items' => $cohortItems,
];
$readiness = [
    'schema' => 'boardprep.batch-715-review-readiness.v1',
    'generated_at_utc' => gmdate('c'),
    'source_cohort_sha256' => $cohortHash,
    'source_inspection_date' => '2026-10-26',
    'interpretation' => 'Machine-prepared evidence dossier. Source reachability, registry identity, examined content, candidate item support, qualified source review, and academic approval are independent states. No selected item is approved or canonical.',
    'items' => $readinessItems,
];

$cohortOut = $root . '/docs/reports/batch-715-pilot-cohort.json';
$readinessOut = $root . '/docs/reports/batch-715-review-readiness.json';
file_put_contents($cohortOut, json_encode($cohort, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n", LOCK_EX);
file_put_contents($readinessOut, json_encode($readiness, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n", LOCK_EX);
echo 'Wrote 20-pilot package and readiness dossiers.' . PHP_EOL;
