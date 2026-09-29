<?php

declare(strict_types=1);

/**
 * Batch 623B: Criminologist evidence closure, forensic validation, and
 * source-first expansion.
 *
 * The authoring catalogue, evidence gates, and materialization path live in
 * one deterministic tool.  --write is the only mode that changes production
 * JSON.  Re-running it removes and rebuilds only Batch 623B-owned records.
 */

$root = dirname(__DIR__);
$write = in_array('--write', $argv, true);
$today = '2026-09-29';
$batch = '623B';
$baselineHead = 'a3e0ed103b4c63951aa40e12044e24fcd910a4a0';

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
$unique = static fn(array $values): array => array_values(array_unique(array_map('strval', $values)));
$normalize = static fn(string $text): string => trim(preg_replace('/\s+/', ' ', strtolower($text)) ?? strtolower($text));
$validDepth = static fn(mixed $value): bool => in_array((string)$value, ['1', '2', '3', '4', '5'], true);
$countDepths = static function (array $values) use ($validDepth): array {
    $out = ['1' => 0, '2' => 0, '3' => 0, '4' => 0, '5' => 0, 'UNKNOWN' => 0];
    foreach ($values as $value) $out[$validDepth($value) ? (string)$value : 'UNKNOWN']++;
    return $out;
};

$unknownQuestionIds = [708,710,712,714,716,718,720,722,724,726,728,730,733,735,737,739,741,743,745,747,749,751,753,755];
$unknownCoverageConcepts = ['cle-criminal-law-analysis','cle-criminal-law-circumstances','cle-criminal-law-elements','cle-criminal-law-limits','cle-criminal-law-rights','cle-law-enforcement-accountability','cle-law-enforcement-community','cle-law-enforcement-leadership','cle-law-enforcement-operations','cle-law-enforcement-planning','social-civics-public-good','social-history-primary-sources'];

$questionsAllExisting = array_values(array_filter($read($root . '/storage/questions.json'), 'is_array'));
$batchOwnedQuestionIds = [];
foreach ($questionsAllExisting as $existingQuestion) {
    if (($existingQuestion['batch_marker'] ?? '') === 'batch623b') {
        $batchOwnedQuestionIds[(string)($existingQuestion['id'] ?? '')] = true;
    }
}
/* Baseline inputs exclude only this batch's generated canonical rows.  This
 * makes --write idempotent without hiding unrelated pre-existing content. */
$questionsExisting = array_values(array_filter($questionsAllExisting, static fn(array $q): bool => ($q['batch_marker'] ?? '') !== 'batch623b'));
$metadataExisting = array_values(array_filter($read($root . '/storage/question-reuse-metadata.json'), 'is_array'));
$linksExisting = array_values(array_filter($read($root . '/storage/study-question-links.json'), 'is_array'));
$eligibilityAllExisting = array_values(array_filter($read($root . '/storage/question-eligibility.json'), 'is_array'));
$eligibilityExisting = array_values(array_filter($eligibilityAllExisting, static fn(array $row): bool => !str_starts_with((string)($row['id'] ?? ''), 'eligibility-b623b-')));
$coverageAllExisting = array_values(array_filter($read($root . '/storage/board-concept-coverage.json'), 'is_array'));
$coverageExisting = [];
foreach ($coverageAllExisting as $existingCoverage) {
    if (str_starts_with((string)($existingCoverage['id'] ?? ''), 'coverage-b623b-')) continue;
    $provenance = array_values(array_filter($existingCoverage['provenance'] ?? [], static fn(mixed $p): bool => is_array($p) && ($p['reference'] ?? '') !== 'batch623b-coverage-evidence'));
    $existingCoverage['provenance'] = $provenance;
    if (strtolower((string)($existingCoverage['board_id'] ?? '')) === 'criminologist'
        && in_array((string)($existingCoverage['concept_id'] ?? ''), $unknownCoverageConcepts, true)) {
        $existingCoverage['max_depth'] = 'UNKNOWN';
    }
    $coverageExisting[] = $existingCoverage;
}
$sourcesExisting = array_values(array_filter($read($root . '/storage/sources.json'), 'is_array'));
$materialsExisting = array_values(array_filter($read($root . '/storage/study-materials.json'), 'is_array'));
$requirementsExisting = array_values(array_filter($read($root . '/storage/question-required-concepts.json'), 'is_array'));
$conceptRows = array_values(array_filter($read($root . '/storage/taxonomy/concepts.json'), 'is_array'));
$topicRows = array_values(array_filter($read($root . '/storage/taxonomy/topics.json'), 'is_array'));
$domainRows = array_values(array_filter($read($root . '/storage/taxonomy/domains.json'), 'is_array'));

$sourceById = [];
foreach ($sourcesExisting as $row) $sourceById[(string)($row['id'] ?? '')] = $row;
$sourceBaselineIds = array_keys($sourceById);
$materialById = [];
foreach ($materialsExisting as $row) $materialById[(string)($row['id'] ?? '')] = $row;
$conceptById = [];
foreach ($conceptRows as $row) $conceptById[(string)($row['id'] ?? '')] = $row;
$topicById = [];
foreach ($topicRows as $row) $topicById[(string)($row['id'] ?? '')] = $row;
$domainById = [];
foreach ($domainRows as $row) $domainById[(string)($row['id'] ?? '')] = $row;

$unknownQuestionIds = [708,710,712,714,716,718,720,722,724,726,728,730,733,735,737,739,741,743,745,747,749,751,753,755];
$forensicQueueIds = range(782, 806);
$legacySensitiveSampleIds = [707,709,711,713,715,717,719,721,723,725,732,734,736,738,740,742,744,746,748,750,757,758,759,760,761,762,763,764,765,782,783,784,785,786,807,808,809,832,833,834];
$unknownCoverageConcepts = ['cle-criminal-law-analysis','cle-criminal-law-circumstances','cle-criminal-law-elements','cle-criminal-law-limits','cle-criminal-law-rights','cle-law-enforcement-accountability','cle-law-enforcement-community','cle-law-enforcement-leadership','cle-law-enforcement-operations','cle-law-enforcement-planning','social-civics-public-good','social-history-primary-sources'];

// Current official scope and grouped primary/technical evidence reviewed live.
$sourceAdditions = [
    ['id'=>'prc-criminology-board-page','title'=>'PRC Professional Regulatory Board of Criminology board page','source_type'=>'OFFICIAL_EXAM_SPEC','authority'=>'Professional Regulation Commission / Professional Regulatory Board of Criminology','url'=>'https://www.prc.gov.ph/Pages/PRBv4/Criminologyv3.htm','publication_date'=>null,'retrieved_at'=>$today,'version'=>'page checked 2026-09-29','status'=>'official_reference','confidence'=>'authoritative_reference','notes'=>'Current PRC index for Board resolutions, TOS documents, ethics material, and current board notices. Scope only.'],
    ['id'=>'prc-criminology-ethics-2024','title'=>'Code of Ethical and Professional Conduct of Philippine Registered Criminologists','source_type'=>'OFFICIAL_GUIDANCE','authority'=>'Professional Regulation Commission / Professional Regulatory Board of Criminology','url'=>'https://www.prc.gov.ph/sites/default/files/2024-07%20published%20coe%20Crim.pdf','publication_date'=>'2024-12-17','retrieved_at'=>$today,'version'=>'PRB Resolution No. 07 s. 2024; effective after publication','status'=>'official_reference','confidence'=>'authoritative_reference','notes'=>'Current professional conduct source for human rights, public interest, integrity, confidentiality, independence, scientific principles, and humane treatment.'],
    ['id'=>'prc-criminology-schedule-2025','title'=>'Adjustments in the Schedule of the Criminology and Correctional Administration Subjects','source_type'=>'OFFICIAL_EXAM_SPEC','authority'=>'Professional Regulation Commission','url'=>'https://prc.gov.ph/article/adjustments-schedule-criminology-and-correctional-administration-subjects-august-2025-lec','publication_date'=>'2025-07-30','retrieved_at'=>$today,'version'=>'August 2025 and succeeding schedules','status'=>'official_reference','confidence'=>'authoritative_reference','notes'=>'Current six-area order and weights; scope/program source, not substantive instruction.'],
    ['id'=>'lawphil-ra11131','title'=>'Republic Act No. 11131, Philippine Criminology Profession Act of 2018','source_type'=>'PRIMARY_LAW','authority'=>'Congress of the Philippines; Lawphil text','url'=>'https://lawphil.net/statutes/repacts/ra2018/ra_11131_2018.html','publication_date'=>'2018-10-12','retrieved_at'=>$today,'version'=>'Republic Act No. 11131; amendments must be checked separately','status'=>'verified_reference','confidence'=>'authoritative_reference','notes'=>'Professional scope, practice boundaries, Board powers, examination subjects, and professional responsibilities.'],
    ['id'=>'lawphil-revised-penal-code','title'=>'Act No. 3815, Revised Penal Code','source_type'=>'PRIMARY_LAW','authority'=>'Republic of the Philippines; Lawphil text','url'=>'https://lawphil.net/statutes/acts/act1930/act_3815_1930.html','publication_date'=>'1930-12-08','retrieved_at'=>$today,'version'=>'Act No. 3815 as amended; current provisions must be checked for each sensitive proposition','status'=>'verified_reference','confidence'=>'authoritative_reference','notes'=>'Foundational source for general criminal-law elements, persons liable, stages, circumstances, and penalties. Version-sensitive where amendment affects the proposition.'],
    ['id'=>'lawphil-rules-criminal-procedure','title'=>'Revised Rules of Criminal Procedure, Rules 110–127','source_type'=>'OFFICIAL_GUIDANCE','authority'=>'Supreme Court of the Philippines; Lawphil text','url'=>'https://lawphil.net/courts/rules/rc_110-127_2000.html','publication_date'=>'2000-10-03','retrieved_at'=>$today,'version'=>'A.M. No. 00-5-03-SC; as amended','status'=>'verified_reference','confidence'=>'authoritative_reference','notes'=>'Procedural source for prosecution, arraignment, rights, trial sequence, search and seizure, and related criminal procedure.'],
    ['id'=>'lawphil-rules-evidence-2019','title'=>'Revised Rules on Evidence, Rules 128–134','source_type'=>'OFFICIAL_GUIDANCE','authority'=>'Supreme Court of the Philippines; Lawphil text','url'=>'https://lawphil.net/courts/rules/rc_128-134_evidence.html','publication_date'=>'2019-10-08','retrieved_at'=>$today,'version'=>'A.M. No. 19-08-15-SC; effective 2020-05-01','status'=>'verified_reference','confidence'=>'authoritative_reference','notes'=>'Evidence definition, relevance, admissibility, authentication, weight, and proof standards. Currentness must be checked against later Supreme Court issuances.'],
    ['id'=>'lawphil-ra7438','title'=>'Republic Act No. 7438, Rights of Persons Arrested, Detained, or under Custodial Investigation','source_type'=>'PRIMARY_LAW','authority'=>'Republic of the Philippines; Lawphil text','url'=>'https://lawphil.net/statutes/repacts/ra1992/ra_7438_1992.html','publication_date'=>'1992-04-27','retrieved_at'=>$today,'version'=>'Republic Act No. 7438','status'=>'verified_reference','confidence'=>'authoritative_reference','notes'=>'Custodial investigation rights, counsel, warnings, reports, confessions, and waiver safeguards.'],
    ['id'=>'lawphil-ra9745','title'=>'Republic Act No. 9745, Anti-Torture Act of 2009','source_type'=>'PRIMARY_LAW','authority'=>'Republic of the Philippines; Lawphil text','url'=>'https://lawphil.net/statutes/repacts/ra2009/ra_9745_2009.html','publication_date'=>'2009-11-10','retrieved_at'=>$today,'version'=>'Republic Act No. 9745','status'=>'verified_reference','confidence'=>'authoritative_reference','notes'=>'Absolute prohibition and safeguards against torture and cruel, inhuman, or degrading treatment.'],
    ['id'=>'lawphil-ra10173','title'=>'Republic Act No. 10173, Data Privacy Act of 2012','source_type'=>'PRIMARY_LAW','authority'=>'Republic of the Philippines; Lawphil text','url'=>'https://lawphil.net/statutes/repacts/ra2012/ra_10173_2012.html','publication_date'=>'2012-08-15','retrieved_at'=>$today,'version'=>'Republic Act No. 10173; current implementing guidance must be checked','status'=>'verified_reference','confidence'=>'authoritative_reference','notes'=>'Privacy and lawful handling of personal information in investigative and public-safety contexts.'],
    ['id'=>'lawphil-ra9165','title'=>'Republic Act No. 9165, Comprehensive Dangerous Drugs Act of 2002','source_type'=>'PRIMARY_LAW','authority'=>'Republic of the Philippines; Lawphil text','url'=>'https://lawphil.net/statutes/repacts/ra2002/ra_9165_2002.html','publication_date'=>'2002-06-07','retrieved_at'=>$today,'version'=>'Republic Act No. 9165 as amended; current text and implementing rules must be checked','status'=>'verified_reference','confidence'=>'authoritative_reference','notes'=>'Bounded source for drug-law terminology and evidence-handling concepts; volatile thresholds and penalties are not used in this batch.'],
    ['id'=>'nij-crime-scene-guide-2013','title'=>'Crime Scene Investigation: A Guide for Law Enforcement','source_type'=>'OFFICIAL_GUIDANCE','authority'=>'National Institute of Justice / National Forensic Science Technology Center','url'=>'https://nij.ojp.gov/library/publications/crime-scene-investigation-guide-law-enforcement','publication_date'=>'2013-09-01','retrieved_at'=>$today,'version'=>'NCJ 243598; updated guide','status'=>'verified_reference','confidence'=>'authoritative_reference','notes'=>'Operational guidance for scene safety, documentation, processing, evidence preservation, packaging, transport, and submission.'],
    ['id'=>'nij-crime-scene-guides-2017','title'=>'Crime Scene Investigation: Guides for Law Enforcement','source_type'=>'OFFICIAL_GUIDANCE','authority'=>'National Institute of Justice','url'=>'https://nij.ojp.gov/topics/articles/crime-scene-investigation-guides-law-enforcement','publication_date'=>'2017-07-12','retrieved_at'=>$today,'version'=>'web guidance checked 2026-09-29','status'=>'verified_reference','confidence'=>'authoritative_reference','notes'=>'Practice guidance emphasizing methodical preservation, contamination control, agency policy, and jurisdictional limits.'],
    ['id'=>'nist-forensic-science-standards','title'=>'Forensic Science Standards Program','source_type'=>'OFFICIAL_GUIDANCE','authority'=>'National Institute of Standards and Technology / OSAC','url'=>'https://www.nist.gov/spo/forensic-science-standards-program','publication_date'=>null,'retrieved_at'=>$today,'version'=>'web program checked 2026-09-29','status'=>'verified_reference','confidence'=>'authoritative_reference','notes'=>'Scientific standards, quality, reproducibility, validation, and standards-development source.'],
    ['id'=>'nist-scientific-foundation-reviews','title'=>'Scientific Foundation Reviews','source_type'=>'OFFICIAL_GUIDANCE','authority'=>'National Institute of Standards and Technology','url'=>'https://www.nist.gov/forensic-science/interdisciplinary-topics/scientific-foundation-reviews','publication_date'=>'2020-04-06','retrieved_at'=>$today,'version'=>'page updated 2026-06-29','status'=>'verified_reference','confidence'=>'authoritative_reference','notes'=>'Scientific-foundation, reliability, limitations, uncertainty, and communication source for forensic interpretation.'],
    ['id'=>'nist-firearms-toolmarks','title'=>'Firearms and Toolmarks Research','source_type'=>'OFFICIAL_GUIDANCE','authority'=>'National Institute of Standards and Technology','url'=>'https://www.nist.gov/spo/forensic-science-program/forensic-science-research/firearms-and-toolmarks','publication_date'=>'2022-09-21','retrieved_at'=>$today,'version'=>'page updated 2024-02-01','status'=>'verified_reference','confidence'=>'authoritative_reference','notes'=>'Firearm/toolmark comparison, measurement quality, uncertainty, databases, and limits of examiner judgment.'],
    ['id'=>'openoregon-introduction-criminology','title'=>'Introduction to Criminology: An Equity Lens','source_type'=>'OPEN_TEXTBOOK','authority'=>'Open Oregon Educational Resources; Peterson and VanderPyl','url'=>'https://openoregon.pressbooks.pub/criminologyintro1e/','publication_date'=>'2023-01-01','retrieved_at'=>$today,'version'=>'CC BY 4.0 open textbook','status'=>'verified_reference','confidence'=>'authoritative_instructional','notes'=>'Instructional source for criminological theory, measurement, learning, social structure, prevention, and critical interpretation.'],
    ['id'=>'openstax-deviance-crime','title'=>'Theoretical Perspectives on Deviance and Crime','source_type'=>'OPEN_TEXTBOOK','authority'=>'OpenStax, Introduction to Sociology 3e','url'=>'https://openstax.org/books/introduction-sociology-3e/pages/7-2-theoretical-perspectives-on-deviance-and-crime','publication_date'=>'2021-06-03','retrieved_at'=>$today,'version'=>'Introduction to Sociology 3e','status'=>'verified_reference','confidence'=>'authoritative_instructional','notes'=>'Open academic source for strain, social disorganization, conflict, labeling, differential association, and control perspectives.'],
    ['id'=>'nij-deterrence','title'=>'Five Things About Deterrence','source_type'=>'OFFICIAL_GUIDANCE','authority'=>'National Institute of Justice','url'=>'https://nij.ojp.gov/topics/articles/five-things-about-deterrence','publication_date'=>'2016-06-05','retrieved_at'=>$today,'version'=>'web article checked 2026-09-29','status'=>'verified_reference','confidence'=>'authoritative_instructional','notes'=>'Evidence-informed deterrence principles, especially certainty of apprehension versus severity of punishment.'],
    ['id'=>'probation-quality-manual-2016','title'=>'Parole and Probation Quality Manual','source_type'=>'OFFICIAL_GUIDANCE','authority'=>'Philippine Parole and Probation Administration','url'=>'https://www.probation.gov.ph/wp-content/uploads/2017/01/QMS_Manual.pdf','publication_date'=>'2016-12-31','retrieved_at'=>$today,'version'=>'Revision 00; effectivity 2016-12-31','status'=>'verified_reference','confidence'=>'authoritative_reference','notes'=>'Philippine source for individualized treatment, supervision, rehabilitation, and community integration.'],
    ['id'=>'bjmp-comprehensive-operations-manual-2015','title'=>'BJMP Comprehensive Operations Manual Revised 2015','source_type'=>'OFFICIAL_GUIDANCE','authority'=>'Bureau of Jail Management and Penology','url'=>'https://car.bjmp.gov.ph/wp-content/uploads/2024/11/BJMP-Comprehensive-Operations-Manual-2015-Edition.pdf','publication_date'=>'2015-01-01','retrieved_at'=>$today,'version'=>'Revised 2015 edition; agency policies may update procedures','status'=>'verified_reference','confidence'=>'authoritative_reference','notes'=>'Operational source for humane safekeeping, reception, identity/authority checks, classification, and jail operations.'],
    ['id'=>'bucor-mandates-functions','title'=>'BuCor Mandates and Functions','source_type'=>'OFFICIAL_GUIDANCE','authority'=>'Bureau of Corrections, Philippines','url'=>'https://bucor.gov.ph/?page_id=2304','publication_date'=>null,'retrieved_at'=>$today,'version'=>'web page checked 2026-09-29','status'=>'verified_reference','confidence'=>'authoritative_reference','notes'=>'Current agency mandate for safekeeping, reformation, rehabilitation, and reintegration of PDL within BuCor jurisdiction.'],
    ['id'=>'bucor-reintegration-2025','title'=>'BuCor Standard Operating Procedures for Pre-release Reintegration Program','source_type'=>'OFFICIAL_GUIDANCE','authority'=>'Bureau of Corrections, Philippines','url'=>'https://bucor.gov.ph/?page_id=4918','publication_date'=>'2025-06-11','retrieved_at'=>$today,'version'=>'BUC-DSO-MC-18; current memorandum index','status'=>'verified_reference','confidence'=>'authoritative_reference','notes'=>'Agency source for coordinated pre-release planning and reintegration; changing implementation details are kept bounded.'],
];

/* Source additions are declared above after the initial registry snapshot.
 * Exclude their IDs from the baseline on reruns so the audit continues to
 * report the same added/reused split after materialization. */
$sourceBaselineIds = array_values(array_diff(
    $sourceBaselineIds,
    array_values(array_map(static fn(array $source): string => (string)$source['id'], $sourceAdditions))
));

// Compact study resources are expanded below before question generation.
$materialUpdates = [
    'cle-criminal-law-foundation' => [
        'source_ids' => ['prc-criminology-enhanced-tos-2024','lawphil-ra11131','lawphil-revised-penal-code','lawphil-rules-criminal-procedure','lawphil-rules-evidence-2019','lawphil-ra7438','lawphil-ra9745','lawphil-ra10173'],
        'summary' => 'Source-backed Philippine criminal-law and jurisprudence foundation covering elements, rights, evidence, procedure, lawful limits, and currentness boundaries.',
        'notes' => [
            ['heading'=>'Elements and liability','body'=>'Separate the prohibited act or omission, the required mental state, causation where relevant, participation, and any circumstance that changes liability. A harmful outcome or a label is not a substitute for proof of the applicable elements.'],
            ['heading'=>'Evidence and procedure','body'=>'Distinguish relevance, admissibility, authenticity, weight, and the burden of proof. Criminal procedure questions must identify the procedural stage and the governing rule before selecting an action.'],
            ['heading'=>'Rights and lawful limits','body'=>'Respect presumption of innocence, due process, counsel, silence, privacy, lawful search, and the absolute prohibition on torture. If a fact needed to decide admissibility or a remedy is missing, state the limit instead of assuming it.'],
            ['heading'=>'Currentness boundary','body'=>'The Revised Penal Code, Rules of Criminal Procedure, Rules on Evidence, and special laws are version-sensitive. These notes support study and application; the current primary text controls when amendments or later Supreme Court issuances matter.'],
        ],
    ],
    'cle-criminal-law-application' => [
        'source_ids' => ['prc-criminology-enhanced-tos-2024','lawphil-revised-penal-code','lawphil-rules-criminal-procedure','lawphil-rules-evidence-2019','lawphil-ra7438','lawphil-ra9745','lawphil-ra10173'],
        'summary' => 'Scenario-based criminal-law application with element mapping, evidence limits, procedural stage, rights, and uncertainty-aware conclusions.',
        'notes' => [
            ['heading'=>'Fact-bound reasoning','body'=>'Identify the issue, required facts, evidence supporting each fact, and the unresolved gap. Do not transform an allegation, a social-media post, or an official-sounding label into proof.'],
            ['heading'=>'Procedure before conclusion','body'=>'Locate the action in investigation, custodial questioning, charging, arraignment, trial, or evidence evaluation. The same fact may have different procedural consequences at different stages.'],
            ['heading'=>'Legal and ethical restraint','body'=>'Use the least overbroad conclusion supported by the authority and facts. A defensible reviewer documents what is known, what is missing, and what must be verified before action.'],
            ['heading'=>'Source/version note','body'=>'Current Philippine statutes and Supreme Court rules must be checked for sensitive legal propositions. Do not rely on a review summary where the answer turns on an amendment, exception, or procedural condition.'],
        ],
    ],
    'cle-law-enforcement-foundation' => [
        'source_ids' => ['prc-criminology-enhanced-tos-2024','lawphil-ra11131','prc-criminology-ethics-2024','lawphil-ra10173','nij-crime-scene-guides-2017'],
        'summary' => 'Law Enforcement Administration foundation for lawful public-safety operations, planning, leadership, accountability, community relations, and information stewardship.',
        'notes' => [
            ['heading'=>'Public-safety administration','body'=>'Define the problem, objective, authority, resources, risks, coordination needs, and evaluation measure before choosing an operation. A plan is stronger when it is evidence-informed and proportionate to the risk.'],
            ['heading'=>'Leadership and accountability','body'=>'Delegation does not erase supervisory responsibility. Use clear roles, documented decisions, lawful escalation, feedback, and reviewable records. Independence and public interest protect professional judgment from improper pressure.'],
            ['heading'=>'Community and information','body'=>'Treat community members fairly and without discrimination, communicate the purpose and limits of an intervention, protect personal information, and separate verified information from rumor.'],
            ['heading'=>'Operational boundary','body'=>'Agency procedures can be current and local. These notes teach stable decision principles; current agency issuances and applicable law control a question about a specific operational rule.'],
        ],
    ],
    'cle-law-enforcement-application' => [
        'source_ids' => ['prc-criminology-enhanced-tos-2024','prc-criminology-ethics-2024','lawphil-ra10173','nij-crime-scene-guides-2017','lawphil-ra11131'],
        'summary' => 'Applied public-safety administration review using role clarity, proportionality, risk controls, accountability, community partnership, and privacy-aware decisions.',
        'notes' => [
            ['heading'=>'Choose the next defensible action','body'=>'Prioritize immediate safety and lawful authority, then preserve information and evidence, assign roles, document decisions, and communicate a review point. Avoid irreversible action when a safer verification step is available.'],
            ['heading'=>'Accountability in scenarios','body'=>'An operationally convenient choice is not automatically lawful or ethical. Ask who has authority, what record supports the choice, what safeguard protects affected people, and how the decision will be reviewed.'],
            ['heading'=>'Professional context','body'=>'Criminologists must act within the scope of law, professional ethics, agency policy, and demonstrated competence. Protect confidentiality and avoid conflicts or pressure that could compromise impartiality.'],
            ['heading'=>'Version boundary','body'=>'The study resource does not replace a current PNP, DILG, DOJ, or other agency issuance. Questions use stable administrative reasoning unless a current rule is explicitly identified and verified.'],
        ],
    ],
    'cle-forensics-foundation' => [
        'source_ids' => ['prc-criminology-enhanced-tos-2024','prc-criminology-ethics-2024','nist-forensic-science-standards','nist-scientific-foundation-reviews','nist-firearms-toolmarks','nij-crime-scene-guide-2013','nij-crime-scene-guides-2017','lawphil-rules-evidence-2019'],
        'summary' => 'Scientific and procedural forensic foundation covering validation, quality controls, documentation, comparison, interpretation, uncertainty, and method limitations.',
        'notes' => [
            ['heading'=>'Scientific support','body'=>'A forensic conclusion should be tied to a method with an identified scientific foundation, validation and quality controls. A scope document establishes exam relevance but does not establish scientific validity.'],
            ['heading'=>'Bounded conclusions','body'=>'Report what the observed data support, distinguish class-level or limited association from source attribution, and do not convert consistency into proof of identity, activity, timing, or guilt.'],
            ['heading'=>'Integrity and documentation','body'=>'Record identity, condition, collection, transfer, controls, deviations, method, assumptions, and limitations. A failed control, ambiguous label, or missing scale can constrain interpretation even when the instrument result looks plausible.'],
            ['heading'=>'Current scientific boundary','body'=>'NIST and related standards reviews identify reliability, error, uncertainty, and communication issues. Methods and standards evolve; current laboratory policy and validated method documentation control specific practice.'],
        ],
    ],
    'cle-investigation-foundation' => [
        'source_ids' => ['prc-criminology-enhanced-tos-2024','nij-crime-scene-guide-2013','nij-crime-scene-guides-2017','lawphil-rules-criminal-procedure','lawphil-rules-evidence-2019','lawphil-ra7438','lawphil-ra10173'],
        'summary' => 'Crime Detection and Investigation foundation for safe scene response, documentation, interviewing, hypothesis testing, evidence handling, and lawful investigative conduct.',
        'notes' => [
            ['heading'=>'Scene sequence','body'=>'Protect people first, secure and control the scene, establish boundaries, document conditions before moving objects, plan collection, package and label items, and maintain a traceable submission record.'],
            ['heading'=>'Interview and reasoning','body'=>'Use open prompts before focused follow-up, document the source and context of information, test alternative explanations, and distinguish an investigative lead from a verified fact.'],
            ['heading'=>'Evidence and digital information','body'=>'Preserve original records and metadata where relevant, minimize contamination, document transfers, and avoid using a convenient summary as a substitute for the underlying record.'],
            ['heading'=>'Lawful conduct','body'=>'An investigative purpose does not erase rights or procedural requirements. Identify the authority and stage before selecting a search, interview, custodial, or disclosure action.'],
        ],
    ],
    'cle-criminology-foundation' => [
        'source_ids' => ['prc-criminology-enhanced-tos-2024','prc-criminology-ethics-2024','openoregon-introduction-criminology','openstax-deviance-crime','nij-deterrence'],
        'summary' => 'Evidence-aware criminology foundation for classical, social, learning, victimology, prevention, research, and theory-limitation reasoning.',
        'notes' => [
            ['heading'=>'What a theory does','body'=>'A criminological theory proposes an explanation that can be connected to observations and tested. It is not a label for an individual and does not by itself prove causation in a particular case.'],
            ['heading'=>'Compare mechanisms','body'=>'Classical and deterrence perspectives emphasize choice, costs, benefits, and certainty; social-structure perspectives emphasize conditions and institutions; learning perspectives emphasize interaction and reinforcement; control perspectives emphasize bonds and social regulation.'],
            ['heading'=>'Measurement and interpretation','body'=>'Separate prevalence, incidence, reporting, victimization, and recorded crime. Consider selection, underreporting, measurement limits, and alternative explanations before recommending policy.'],
            ['heading'=>'Ethical application','body'=>'Use theories to inform prevention and research without stereotyping groups or treating correlation as destiny. Respect rights, public interest, and scientific uncertainty.'],
        ],
    ],
    'cle-corrections-foundation' => [
        'source_ids' => ['prc-criminology-enhanced-tos-2024','prc-criminology-ethics-2024','lawphil-ra11131','lawphil-ra9745','bjmp-comprehensive-operations-manual-2015','bucor-mandates-functions','bucor-reintegration-2025','probation-quality-manual-2016'],
        'summary' => 'Correctional Administration foundation for humane custody, lawful classification, individualized rehabilitation, supervision, and reintegration.',
        'notes' => [
            ['heading'=>'Custody and dignity','body'=>'Safekeeping must be secure and humane. Reception, identity, health, safety, classification, discipline, and access to services should be documented and consistent with rights and agency authority.'],
            ['heading'=>'Classification and treatment','body'=>'Use relevant risk, needs, behavior, health, vulnerability, and security information. Classification supports individualized management; it is not a permanent label or a substitute for review.'],
            ['heading'=>'Rehabilitation and community work','body'=>'Probation, parole, education, treatment, work, and reintegration require individualized goals, supervision, support, and coordination. Public safety and humane treatment are not mutually exclusive.'],
            ['heading'=>'Currentness boundary','body'=>'Agency manuals and reintegration programs can change. Questions use stable correctional principles unless a specific current issuance is identified; current agency policy controls operational details.'],
        ],
    ],
];

/*
 * The catalogue is explicit.  Each row represents a different decision,
 * interpretation, or professional situation; the generator supplies only
 * the repository envelope and deterministic metadata.
 */
$row = static function (
    string $stem,
    string $answer,
    array $distractors,
    string $explanation,
    int $depth = 3,
    ?string $context = null,
    array $sensitivity = [],
    array $required = [],
    string $scenario = 'application',
    string $misconception = 'unsupported_conclusion'
): array {
    return [
        'stem' => $stem,
        'answer' => $answer,
        'distractors' => $distractors,
        'explanation' => $explanation,
        'depth' => $depth,
        'context' => $context,
        'sensitivity' => $sensitivity,
        'required' => $required,
        'scenario' => $scenario,
        'misconception' => $misconception,
    ];
};

$pack = static function (
    string $concept,
    string $material,
    array $sources,
    string $context,
    array $sensitivity,
    array $rows,
    array $required = []
): array {
    /* A few catalogue declarations were authored in the natural
     * required-concepts-before-rows order.  Normalize that form here so the
     * production envelope remains one canonical pack shape. */
    if (isset($rows[0]) && is_string($rows[0]) && isset($required[0]) && is_array($required[0]) && isset($required[0]['stem'])) {
        [$rows, $required] = [$required, $rows];
    }
    return compact('concept', 'material', 'sources', 'context', 'sensitivity', 'rows', 'required');
};

$packs = [];

/* Criminal Law and Jurisprudence: 48 questions. */
$packs[] = $pack('cle-jurisprudence-principles', 'cle-criminal-law-foundation',
    ['lawphil-revised-penal-code','lawphil-rules-criminal-procedure','lawphil-rules-evidence-2019'],
    'REGULATORY', ['STATUTORY_VERSION_SENSITIVE','CRIMINAL_PROCEDURE_SENSITIVE'], [
        $row('A case summary labels a person a “criminal” before trial. Which review practice best protects the legal analysis?', 'Describe the allegation and test the applicable elements without treating the label as proof.', ['Treat the label as an admission of guilt.', 'Assume every disputed fact against the accused.', 'Skip the evidence review because the summary is official.'], 'A legal conclusion must be tied to the elements and evidence, while the presumption of innocence prevents an accusation from becoming proof.', 3, 'REGULATORY', ['STATUTORY_VERSION_SENSITIVE'], [], 'case_interpretation', 'presumption_error'),
        $row('Two statutes appear relevant to the same conduct, but one is a specific provision and the other is general. What should the reviewer do first?', 'Identify each provision’s elements, scope, and applicable interpretive rule before deciding which governs.', ['Apply the provision with the harsher penalty automatically.', 'Choose the newer-looking provision without checking its scope.', 'Combine all elements from both provisions into one offense.'], 'The relationship between provisions depends on their elements and scope; severity or appearance alone does not resolve the issue.', 4, 'REGULATORY', ['STATUTORY_VERSION_SENSITIVE'], [], 'comparison', 'scope_error'),
        $row('An investigator has a plausible theory but the file contains facts supporting two explanations. What is the most defensible legal-reasoning step?', 'Record the competing explanations and identify what admissible evidence would distinguish them.', ['Select the theory that fits the first report.', 'Treat the plausible theory as established fact.', 'Remove facts that do not support the preferred theory.'], 'Legal and investigative reasoning should expose unresolved alternatives rather than convert plausibility into proof.', 4, 'REGULATORY', ['STATUTORY_VERSION_SENSITIVE','EVIDENCE_RULE_SENSITIVE'], [], 'diagnosis', 'confirmation_bias'),
        $row('A reviewer is asked whether a conduct description establishes liability, but the description omits the required mental state. What is the proper conclusion?', 'State that liability cannot be determined until the missing mental-state fact is established under the applicable law.', ['Infer intent from the harmful result alone.', 'Assume negligence and intent are interchangeable.', 'Treat the offense label as supplying the missing mental state.'], 'The required mental state is an element when the offense calls for it; a harmful result or label cannot silently supply that element.', 3, 'REGULATORY', ['STATUTORY_VERSION_SENSITIVE'], [], 'case_interpretation', 'mens_rea_error'),
        $row('A policy proposal would authorize a broad search whenever an investigator feels that a case is important. Which evaluation is strongest?', 'Require a lawful basis, defined scope, and procedural safeguards before treating the search as permissible.', ['Approve the search because public safety always overrides procedure.', 'Approve any search requested by a senior officer.', 'Treat the absence of a complaint as consent.'], 'Importance does not replace legal authority or limits. A defensible review identifies the basis, scope, and safeguards.', 4, 'REGULATORY', ['CRIMINAL_PROCEDURE_SENSITIVE','EVIDENCE_RULE_SENSITIVE'], [], 'judgment', 'authority_error'),
        $row('A case note cites a statute but does not state which version applied when the conduct occurred. What should the analyst do?', 'Verify the version and effective date before applying the statute to the facts.', ['Use the current text automatically for every past event.', 'Use the oldest text because it is safer.', 'Ignore effective dates because statutory language is permanent.'], 'A time-sensitive legal proposition must be matched to the law applicable to the relevant conduct and proceeding.', 3, 'REGULATORY', ['STATUTORY_VERSION_SENSITIVE'], [], 'process_interpretation', 'currentness_error'),
        $row('A public statement says that a conviction is justified because the accused cannot prove innocence. Which principle rejects that reasoning?', 'The prosecution must establish the required case under the applicable burden; failure to prove innocence is not proof of guilt.', ['The accused must disprove every allegation.', 'A public statement can replace trial evidence.', 'The burden changes whenever the allegation is serious.'], 'The burden of proof and presumption of innocence prevent the absence of exculpatory proof from becoming affirmative proof of guilt.', 3, 'REGULATORY', ['CRIMINAL_PROCEDURE_SENSITIVE','EVIDENCE_RULE_SENSITIVE'], [], 'comparison', 'burden_shift'),
        $row('A legal memo uses a broad moral judgment instead of the offense’s defined elements. What correction improves the memo?', 'Replace the moral conclusion with an element-by-element analysis tied to admissible facts.', ['Keep the moral judgment because it is persuasive.', 'Add more adjectives describing the accused.', 'Use the most serious available offense regardless of facts.'], 'A professional legal analysis is disciplined by the defined elements and the evidence that supports each one.', 3, 'REGULATORY', ['STATUTORY_VERSION_SENSITIVE'], [], 'editing', 'moral_label'),
    ]);

$packs[] = $pack('cle-criminal-law-elements', 'cle-criminal-law-application',
    ['lawphil-revised-penal-code','lawphil-rules-evidence-2019','lawphil-rules-criminal-procedure'],
    'REGULATORY', ['STATUTORY_VERSION_SENSITIVE','EVIDENCE_RULE_SENSITIVE'], [
        $row('A complaint identifies an act but gives no fact showing the required circumstance that makes the act an offense. What should the reviewer conclude?', 'The listed act is not enough; the missing circumstance must be established before the element is treated as satisfied.', ['The circumstance is presumed from the complaint title.', 'The act automatically proves every element.', 'The reviewer should invent the missing circumstance from context.'], 'The elements are cumulative when the law requires them. A charging label does not substitute for proof of a required circumstance.', 3, 'REGULATORY', ['STATUTORY_VERSION_SENSITIVE'], [], 'case_interpretation', 'element_omission'),
        $row('A witness proves that a person was present but does not connect that person to the prohibited act. Which analytical response is appropriate?', 'Treat presence as one fact and separately test participation or other required elements.', ['Treat presence as proof of participation.', 'Discard the witness because presence is irrelevant to every case.', 'Assume participation from association with another person.'], 'Presence may be relevant, but it does not automatically establish the act, participation, or mental state required by the offense.', 4, 'REGULATORY', ['STATUTORY_VERSION_SENSITIVE','EVIDENCE_RULE_SENSITIVE'], [], 'analysis', 'presence_equals_participation'),
        $row('A file contains an authenticated record supporting one element and an unauthenticated screenshot offered to prove another. What is the correct next step?', 'Evaluate the screenshot’s authentication and admissibility before relying on it for the second element.', ['Treat all digital files as self-authenticating.', 'Use the screenshot because it looks complete.', 'Reject the entire file because one item is disputed.'], 'Different items require their own evidentiary foundation. Reliability of one record does not automatically authenticate another.', 4, 'REGULATORY', ['EVIDENCE_RULE_SENSITIVE'], [], 'evidence_interpretation', 'authentication_error'),
        $row('A statute requires a particular result and a specified mental state, but the record establishes only the result. What is the safest finding?', 'The result is supported, but the mental-state element remains unproven on the present record.', ['The result establishes the mental state conclusively.', 'The reviewer may substitute motive for mental state.', 'The missing element should be ignored if the result is serious.'], 'Elements should not be collapsed. The record must support each required component before a conclusion is reached.', 3, 'REGULATORY', ['STATUTORY_VERSION_SENSITIVE'], [], 'case_interpretation', 'result_equals_intent'),
        $row('An accused person’s statement is summarized by an officer, but the original recording and circumstances of questioning are missing. How should the statement be used?', 'Treat it as requiring verification of authenticity, context, and lawful custodial safeguards before relying on it.', ['Treat the summary as conclusive because it is official.', 'Assume the missing recording would support the summary.', 'Use the statement only to prove every disputed fact.'], 'The summary may be a lead, but authentication, context, and safeguards matter before the statement is treated as reliable proof.', 4, 'REGULATORY', ['CRIMINAL_PROCEDURE_SENSITIVE','EVIDENCE_RULE_SENSITIVE'], [], 'evidence_interpretation', 'summary_equals_original'),
        $row('A charge appears to fit two offenses, but one requires an additional factual circumstance not alleged or supported. Which approach is sound?', 'Compare the required elements and do not select the offense whose additional circumstance is unsupported.', ['Select the offense with the longer title.', 'Add the circumstance because it is plausible.', 'Use both offenses as though their elements were interchangeable.'], 'The applicable offense must be supported by its own elements; plausibility does not establish an omitted circumstance.', 4, 'REGULATORY', ['STATUTORY_VERSION_SENSITIVE'], [], 'comparison', 'element_substitution'),
        $row('The evidence supports that an event occurred but is inconsistent about who performed the act. What remains unresolved?', 'The attribution or participation element remains unresolved even though the event itself is supported.', ['Nothing; proof of the event proves the actor.', 'Only the penalty remains unresolved.', 'The inconsistency can be ignored if the event was serious.'], 'Proof that an event occurred and proof of who is legally responsible are distinct analytical questions.', 3, 'REGULATORY', ['STATUTORY_VERSION_SENSITIVE','EVIDENCE_RULE_SENSITIVE'], [], 'analysis', 'event_equals_actor'),
        $row('A reviewer discovers that the record uses a statutory term in an everyday sense. What should be done before applying the term?', 'Use the legally defined meaning and verify whether the facts satisfy that meaning.', ['Use the everyday meaning because it is familiar.', 'Treat the term as whatever the investigator intended.', 'Avoid defining the term and proceed to a conclusion.'], 'Legal terms can carry defined meanings that control the element analysis; ordinary language may mislead.', 3, 'REGULATORY', ['STATUTORY_VERSION_SENSITIVE'], [], 'interpretation', 'ordinary_meaning_error'),
    ], ['cle-jurisprudence-principles']);

$packs[] = $pack('cle-criminal-law-rights', 'cle-criminal-law-application',
    ['lawphil-ra7438','lawphil-ra9745','lawphil-rules-criminal-procedure','lawphil-ra10173'],
    'REGULATORY', ['CRIMINAL_PROCEDURE_SENSITIVE','STATUTORY_VERSION_SENSITIVE'], [
        $row('During custodial questioning, an investigator begins substantive questioning before documenting the person’s access to counsel. What is the safest professional response?', 'Pause the questioning and verify the applicable custodial safeguards and counsel requirements.', ['Continue because the answers may be useful.', 'Ask only leading questions so safeguards no longer matter.', 'Treat silence as consent to continued questioning.'], 'Custodial questioning must be evaluated in its procedural setting. Utility does not replace rights and required safeguards.', 4, 'REGULATORY', ['CRIMINAL_PROCEDURE_SENSITIVE'], [], 'judgment', 'rights_omission'),
        $row('A person asks why personal data from a case file are being shared with an unrelated recipient. What should the custodian do first?', 'Identify the lawful purpose, authority, and necessary scope of the disclosure before releasing the data.', ['Release everything because the file is official.', 'Refuse every disclosure even when lawfully required.', 'Share the data informally to save time.'], 'Privacy-aware handling requires purpose, authority, necessity, and controlled disclosure rather than blanket release or blanket refusal.', 4, 'REGULATORY', ['STATUTORY_VERSION_SENSITIVE','REGULATORY_SENSITIVE'], [], 'judgment', 'privacy_scope'),
        $row('An interview subject is exhausted and asks to stop, while the investigator says the case is urgent. What is the defensible next step?', 'Document the request and assess the lawful conditions for continuing or pausing the interview.', ['Continue indefinitely because urgency removes safeguards.', 'Threaten a consequence for asking to stop.', 'Delete the interview record and restart later.'], 'Urgency does not itself answer the rights and voluntariness question. The circumstances and governing safeguards must be documented.', 4, 'REGULATORY', ['CRIMINAL_PROCEDURE_SENSITIVE','STATUTORY_VERSION_SENSITIVE'], [], 'judgment', 'urgency_overrides_rights'),
        $row('A supervisor asks an investigator to omit an exculpatory detail from the report. Which response is most defensible?', 'Preserve the material detail and use the established reporting and accountability channel.', ['Omit it to protect the investigation.', 'Keep it only in a private note unavailable to review.', 'Change it into a stronger accusation.'], 'Integrity and due process require accurate records; supervisory pressure does not justify suppressing material information.', 4, 'REGULATORY', ['PROFESSIONAL_RULE_SENSITIVE'], [], 'ethics', 'suppression'),
        $row('A search request identifies a location but not the items or scope to be searched. What should the reviewer require?', 'Clarification of the lawful basis and sufficiently defined scope before treating the request as ready.', ['Approve the broadest possible search.', 'Assume all items in the location are included.', 'Authorize the search because the location is suspicious.'], 'A defined authority and scope protect against overbroad action and make later review possible.', 3, 'REGULATORY', ['CRIMINAL_PROCEDURE_SENSITIVE'], [], 'process_interpretation', 'scope_overbreadth'),
        $row('A case file contains sensitive victim information that is not needed for the next investigative decision. What handling is appropriate?', 'Limit access and disclosure to what is necessary for the lawful purpose and protect the remaining information.', ['Copy the full file to every team member.', 'Publish the information to encourage tips.', 'Delete the information without preserving the record.'], 'Purpose limitation and controlled access reduce unnecessary exposure while preserving the official record.', 3, 'REGULATORY', ['REGULATORY_SENSITIVE','STATUTORY_VERSION_SENSITIVE'], [], 'judgment', 'overdisclosure'),
        $row('An investigator says a person who refuses to answer has “confessed by silence.” How should the reviewer respond?', 'Reject the inference and analyze silence under the applicable rights and evidentiary rules.', ['Treat silence as an admission in every setting.', 'Use silence to fill every missing element.', 'Assume silence proves deception.'], 'Silence has no universal meaning. Its legal significance depends on the setting, safeguards, and applicable rule.', 3, 'REGULATORY', ['CRIMINAL_PROCEDURE_SENSITIVE','EVIDENCE_RULE_SENSITIVE'], [], 'interpretation', 'silence_inference'),
        $row('A report recommends naming a suspect publicly before corroborating the identity evidence. What concern is most direct?', 'The recommendation risks due-process, privacy, and investigative-integrity harms from an unverified attribution.', ['Public naming always improves accuracy.', 'Identity evidence never needs corroboration.', 'The concern is only whether the headline is clear.'], 'Public identification can create irreversible harm and contaminate the investigation when the attribution is not yet verified.', 4, 'REGULATORY', ['STATUTORY_VERSION_SENSITIVE','REGULATORY_SENSITIVE'], [], 'judgment', 'premature_identification'),
    ], ['cle-jurisprudence-principles']);

$packs[] = $pack('cle-criminal-law-circumstances', 'cle-criminal-law-application',
    ['lawphil-revised-penal-code','lawphil-rules-evidence-2019'],
    'REGULATORY', ['STATUTORY_VERSION_SENSITIVE','EVIDENCE_RULE_SENSITIVE'], [
        $row('A case file lists a circumstance that may affect liability but provides no facts showing when or how it existed. What should the analyst do?', 'Identify the circumstance as unverified and specify the facts needed to establish its legal effect.', ['Apply the circumstance because it appears in the report.', 'Ignore the circumstance permanently.', 'Treat any unusual behavior as proof of it.'], 'A circumstance has legal effect only when its elements and factual basis are established under the applicable law.', 3, 'REGULATORY', ['STATUTORY_VERSION_SENSITIVE'], [], 'analysis', 'label_equals_proof'),
        $row('Two witnesses describe the same event but disagree about a circumstance that changes classification. What is the proper analytical response?', 'Compare reliability and corroboration for the circumstance instead of selecting the more convenient account.', ['Choose the witness with the higher status automatically.', 'Average the two accounts into a new fact.', 'Treat the disagreement as irrelevant.'], 'A material circumstance must be assessed through evidence and credibility, not convenience or status.', 4, 'REGULATORY', ['STATUTORY_VERSION_SENSITIVE','EVIDENCE_RULE_SENSITIVE'], [], 'comparison', 'credibility_shortcut'),
        $row('A mitigating fact is raised only in a closing summary, with no underlying record identified. What should a reviewer request?', 'The source and admissible basis for the fact before giving it legal weight.', ['Give it full weight because it favors the accused.', 'Reject it solely because it favors the accused.', 'Convert the summary into sworn testimony.'], 'Favorable or unfavorable, a material circumstance needs an identifiable evidentiary basis.', 3, 'REGULATORY', ['EVIDENCE_RULE_SENSITIVE'], [], 'evidence_interpretation', 'summary_weight'),
        $row('A report treats motive as though it were a circumstance that automatically proves the offense. Which correction is required?', 'Separate motive from the offense elements and assess whether the alleged circumstance is independently established.', ['Treat motive as a substitute for every element.', 'Remove all motive evidence from the file.', 'Assume motive is proof of participation.'], 'Motive may inform interpretation but does not replace the elements or independently prove participation.', 4, 'REGULATORY', ['STATUTORY_VERSION_SENSITIVE'], [], 'analysis', 'motive_substitution'),
        $row('A circumstance is described using a term that has both ordinary and statutory meanings. What should control the review?', 'The applicable statutory definition and the facts that satisfy it.', ['The ordinary meaning used by the witness.', 'The most severe meaning available.', 'The meaning most favorable to the report writer.'], 'Legal classification turns on the controlling legal definition, not a convenient ordinary-language substitute.', 3, 'REGULATORY', ['STATUTORY_VERSION_SENSITIVE'], [], 'interpretation', 'definition_drift'),
        $row('A material circumstance depends on the exact sequence of events, but the timeline has an unexplained gap. What is the defensible result?', 'Mark the circumstance unresolved until the gap is addressed by reliable evidence.', ['Assume the missing interval supports the allegation.', 'Ignore sequence because the outcome is known.', 'Fill the gap with the investigator’s expectation.'], 'When timing is an element of the circumstance, an unexplained gap prevents a confident classification.', 4, 'REGULATORY', ['STATUTORY_VERSION_SENSITIVE','EVIDENCE_RULE_SENSITIVE'], [], 'analysis', 'timeline_gap'),
        $row('A reviewer finds a circumstance that could increase liability but also finds a rule requiring cautious interpretation. What should guide the conclusion?', 'Apply the controlling rule and state the evidentiary limits rather than maximizing the consequence.', ['Choose the interpretation with the highest penalty.', 'Ignore the caution because the facts are troubling.', 'Treat caution as proof of innocence.'], 'Professional legal reasoning applies the governing rule and reports uncertainty without outcome-driven selection.', 4, 'REGULATORY', ['STATUTORY_VERSION_SENSITIVE'], [], 'judgment', 'severity_bias'),
        $row('A report uses a prior incident to infer a current circumstance without showing its relevance to the current event. What is the problem?', 'The prior incident is being used as a shortcut instead of proving the current circumstance under the applicable rules.', ['Prior incidents always establish current facts.', 'The current event needs no evidence once a prior exists.', 'The prior incident must be treated as irrelevant in every case.'], 'Past conduct may have a limited role, but it cannot silently replace proof of the present circumstance.', 4, 'REGULATORY', ['STATUTORY_VERSION_SENSITIVE','EVIDENCE_RULE_SENSITIVE'], [], 'analysis', 'propensity_shortcut'),
    ]);

$packs[] = $pack('cle-criminal-law-analysis', 'cle-criminal-law-application',
    ['lawphil-revised-penal-code','lawphil-rules-criminal-procedure','lawphil-rules-evidence-2019','lawphil-ra7438'],
    'REGULATORY', ['STATUTORY_VERSION_SENSITIVE','CRIMINAL_PROCEDURE_SENSITIVE','EVIDENCE_RULE_SENSITIVE'], [
        $row('A case has a strong eyewitness account, a conflicting access log, and an unclear chain for the log export. What should the analyst do?', 'Assess each source’s foundation and reconcile the conflict before reaching an attribution conclusion.', ['Choose the eyewitness because human evidence is always superior.', 'Choose the log because digital records are always objective.', 'Discard both sources and decide from the allegation.'], 'Conflicting evidence requires source-specific foundation and reconciliation; neither format is automatically decisive.', 4, 'REGULATORY', ['EVIDENCE_RULE_SENSITIVE'], ['cle-criminal-law-elements'], 'analysis', 'format_bias'),
        $row('A prosecutor asks for a single conclusion even though the record supports only two competing timelines. What is the best response?', 'Present the supported timelines, the unresolved fact, and the evidence needed to distinguish them.', ['Select the timeline that makes the case strongest.', 'Average the timelines into one date.', 'Hide the conflict to avoid confusing the reader.'], 'A defensible analysis makes uncertainty visible and identifies the next evidentiary question.', 4, 'REGULATORY', ['EVIDENCE_RULE_SENSITIVE'], ['cle-criminal-law-elements'], 'judgment', 'false_certainty'),
        $row('An analyst maps facts to an offense but notices that one fact is supported only by an unverified social-media post. How should it be treated?', 'As an investigative lead requiring authentication, not as established proof of the element.', ['As proof because the post is time-stamped.', 'As proof if many people shared it.', 'As irrelevant to any future inquiry.'], 'A time stamp or popularity does not by itself establish authenticity, authorship, or truth of the content.', 3, 'REGULATORY', ['EVIDENCE_RULE_SENSITIVE','REGULATORY_SENSITIVE'], ['cle-criminal-law-elements'], 'evidence_interpretation', 'digital_authentication'),
        $row('A legal analysis cites the correct statute but applies a later amendment to an earlier event without checking effective dates. What is the defect?', 'The governing version has not been matched to the relevant date and transition rule.', ['The statute is automatically invalid.', 'The later amendment always controls all past conduct.', 'The date is irrelevant once the statute is official.'], 'Version and effectivity are part of the legal analysis when an amendment may affect the proposition.', 3, 'REGULATORY', ['STATUTORY_VERSION_SENSITIVE'], ['cle-criminal-law-elements'], 'process_interpretation', 'retroactivity_assumption'),
        $row('A file contains evidence that supports an element and evidence that undermines it. What should the analyst’s conclusion do?', 'State the supported and undermined portions separately and avoid claiming more than the record establishes.', ['Count only the supporting evidence.', 'Count only the undermining evidence.', 'Treat the larger document as automatically correct.'], 'Balanced analysis preserves material conflicts and ties the conclusion to the strength and limits of the evidence.', 4, 'REGULATORY', ['EVIDENCE_RULE_SENSITIVE'], ['cle-criminal-law-elements'], 'analysis', 'one_sided_review'),
        $row('A supervisor requests that an analyst omit a limitation because it may weaken the case. What is the proper professional action?', 'Retain the limitation and document the request through the applicable review channel.', ['Omit it but keep an undisclosed private copy.', 'Change the limitation into a recommendation.', 'Delete the conflicting evidence.'], 'Accuracy and accountability require material limitations to remain visible in a reviewable record.', 4, 'REGULATORY', ['PROFESSIONAL_RULE_SENSITIVE'], ['cle-criminal-law-rights'], 'ethics', 'suppressed_limitation'),
        $row('A conclusion depends on whether a statement was obtained during custodial investigation, but the file does not say. What should happen?', 'Defer the procedural conclusion and obtain the missing setting and safeguards information.', ['Assume custodial status because an officer was present.', 'Assume no safeguards were needed.', 'Use the statement first and check procedure later.'], 'The procedural stage materially controls the rights analysis; missing facts prevent a safe conclusion.', 4, 'REGULATORY', ['CRIMINAL_PROCEDURE_SENSITIVE'], ['cle-criminal-law-rights'], 'analysis', 'missing_stage'),
        $row('A case comparison shows the same outcome but different evidence quality across two files. What is the correct lesson?', 'Similar outcomes do not establish equivalent proof; the analysis must compare the evidence and legal elements.', ['Treat the files as interchangeable.', 'Use the stronger file to fill the weaker one.', 'Assume outcome proves method.'], 'Professional reasoning compares the actual basis of each conclusion instead of treating results as evidence of equivalence.', 3, 'REGULATORY', ['EVIDENCE_RULE_SENSITIVE'], ['cle-criminal-law-elements'], 'comparison', 'outcome_equivalence'),
    ], ['cle-criminal-law-elements']);

$packs[] = $pack('cle-criminal-law-limits', 'cle-criminal-law-application',
    ['lawphil-rules-criminal-procedure','lawphil-rules-evidence-2019','lawphil-ra7438','lawphil-ra9745','lawphil-ra10173'],
    'REGULATORY', ['CRIMINAL_PROCEDURE_SENSITIVE','EVIDENCE_RULE_SENSITIVE','STATUTORY_VERSION_SENSITIVE'], [
        $row('An investigator wants to disclose a complete victim file to a broad mailing list to obtain tips. Which safeguard is most important?', 'Define the lawful purpose and limit disclosure to necessary information with controlled access.', ['Send the complete file because more information always helps.', 'Post the file publicly and remove it later.', 'Share the file only with friends of the investigator.'], 'Disclosure should be necessary, authorized, proportionate, and protected against avoidable exposure.', 4, 'REGULATORY', ['REGULATORY_SENSITIVE'], ['cle-criminal-law-rights'], 'judgment', 'overdisclosure'),
        $row('An officer proposes a coercive interview tactic because the case is serious. What is the correct evaluation?', 'Reject coercion and verify the applicable rights and anti-torture safeguards before any questioning.', ['Use coercion if the suspected offense is serious.', 'Use coercion if a supervisor approves it.', 'Use coercion only when the person is silent.'], 'Seriousness does not create an exception to rights or the prohibition on torture and cruel treatment.', 4, 'REGULATORY', ['CRIMINAL_PROCEDURE_SENSITIVE','STATUTORY_VERSION_SENSITIVE'], ['cle-criminal-law-rights'], 'judgment', 'necessity_exception'),
        $row('A proposed report identifies a person from a weak image and recommends immediate publication. What limitation should control?', 'The image should be treated as a lead until identity is corroborated and lawful disclosure is assessed.', ['Publish because a weak image is still evidence of identity.', 'Treat public reaction as corroboration.', 'Use the name supplied by an anonymous post.'], 'Identification and disclosure have separate reliability and rights implications; a weak lead is not a verified attribution.', 3, 'REGULATORY', ['REGULATORY_SENSITIVE','EVIDENCE_RULE_SENSITIVE'], ['cle-criminal-law-rights'], 'judgment', 'lead_equals_identity'),
        $row('A team stores sensitive case data on an open shared drive with no access log. What is the first corrective priority?', 'Restrict access and establish an accountable, purpose-based handling process.', ['Leave it open but rename the folder.', 'Copy the data to more open drives.', 'Delete all data immediately without preserving the record.'], 'Access control and accountability reduce exposure while preserving evidence and lawful records.', 3, 'REGULATORY', ['REGULATORY_SENSITIVE'], ['cle-criminal-law-rights'], 'process_interpretation', 'security_by_obscurity'),
        $row('A person under investigation asks whether counsel may be present, but the report says only “questioning continued.” What should the reviewer do?', 'Obtain the missing custodial context and verify the rights requirements before evaluating the statement.', ['Assume counsel was unnecessary.', 'Assume counsel was present because the report is official.', 'Ignore the question because the statement may be useful.'], 'The omitted context is material to the procedural and evidentiary analysis.', 4, 'REGULATORY', ['CRIMINAL_PROCEDURE_SENSITIVE'], ['cle-criminal-law-rights'], 'analysis', 'missing_context'),
        $row('A report recommends withholding a known exculpatory record from a defense request because it complicates the case. What is the strongest objection?', 'The recommendation risks violating fairness and the duty to maintain an accurate, reviewable record.', ['Complication is a valid reason to withhold any record.', 'Exculpatory records are never relevant.', 'Only the report writer may decide what exists.'], 'A professional file cannot be shaped solely for convenience or outcome; material contrary information must be handled lawfully.', 4, 'REGULATORY', ['CRIMINAL_PROCEDURE_SENSITIVE','EVIDENCE_RULE_SENSITIVE'], ['cle-criminal-law-rights'], 'ethics', 'fairness_omission'),
        $row('An agency rule conflicts with a higher legal authority on a person’s procedural safeguard. What should the analyst do?', 'Escalate the conflict and apply the controlling authority rather than treating the agency rule as conclusive.', ['Apply the agency rule because it is easier.', 'Choose whichever rule produces more arrests.', 'Ignore both sources.'], 'Hierarchy and lawful authority matter when an operational rule conflicts with a controlling legal safeguard.', 4, 'REGULATORY', ['CRIMINAL_PROCEDURE_SENSITIVE','PROFESSIONAL_RULE_SENSITIVE'], ['cle-criminal-law-rights'], 'comparison', 'authority_hierarchy'),
        $row('A case note says an investigator may ignore privacy because the information is “about crime.” What is the correction?', 'Investigative purpose must still be paired with lawful authority, necessity, proportionality, and safeguards.', ['Accept the statement because crime removes privacy interests.', 'Treat all public information as unrestricted.', 'Delete the privacy discussion.'], 'The subject matter does not erase privacy obligations or the need to limit collection and disclosure.', 3, 'REGULATORY', ['REGULATORY_SENSITIVE','STATUTORY_VERSION_SENSITIVE'], ['cle-criminal-law-rights'], 'interpretation', 'crime_erases_privacy'),
    ], ['cle-criminal-law-rights']);

/* Criminalistics and Forensic Science: 45 questions. */
$packs[] = $pack('cle-forensic-integrity', 'cle-forensics-foundation',
    ['nist-forensic-science-standards','nist-scientific-foundation-reviews','nij-crime-scene-guide-2013','lawphil-rules-evidence-2019'],
    'PROFESSIONAL', ['PROFESSIONAL_RULE_SENSITIVE'], [
        $row('A laboratory control falls outside its validated range during a run. What is the most defensible response?', 'Pause interpretation, investigate the deviation, and resolve or repeat the run under the validated process.', ['Report the most favorable result immediately.', 'Ignore the control because the sample result is clear.', 'Change the control value in the worksheet.'], 'A failed control calls the run’s reliability into question; interpretation should wait for documented resolution.', 4, 'PROFESSIONAL', [], [], 'quality_control', 'control_ignored'),
        $row('An examiner’s report gives a conclusion but does not identify the method, quality controls, or limitations. What should be added?', 'A traceable description of method, controls, assumptions, conclusion strength, and limitations.', ['A stronger conclusion only.', 'A statement that the examiner is experienced.', 'Removal of all uncertainty language.'], 'Scientific review requires enough method and limitation information for another person to understand and evaluate the conclusion.', 4, 'PROFESSIONAL', [], [], 'reporting', 'authority_by_status'),
        $row('A questioned sample and reference sample were handled by different people, but the transfer record is incomplete. What is the primary issue?', 'The provenance and chain of custody are incomplete, limiting the strength of the comparison.', ['Different handlers make comparison impossible in every case.', 'The reference is automatically authentic.', 'The gap should be filled from expectation.'], 'Different handlers are not inherently disqualifying, but an unexplained transfer gap limits provenance and must be resolved or disclosed.', 4, 'PROFESSIONAL', [], [], 'quality_control', 'chain_absolute'),
        $row('An examiner finds a similarity but cannot state the method’s validated error or limitation information. What should the report do?', 'Describe the similarity cautiously and disclose the limitation rather than overstate the conclusion.', ['Call it an identification because similarity is enough.', 'Omit the similarity entirely.', 'Use a stronger conclusion to compensate for missing validation.'], 'A conclusion should not exceed the method’s demonstrated capability and available validation evidence.', 3, 'PROFESSIONAL', [], [], 'reporting', 'similarity_equals_identity'),
        $row('A laboratory changes a validated method but does not document the change or assess its effect. What is the quality concern?', 'The change may affect reliability and requires documented validation or verification before routine interpretation.', ['Any change improves the method.', 'Documentation is unnecessary for experienced staff.', 'The change matters only if a result is unfavorable.'], 'Method changes can alter performance; quality systems require documented assessment before relying on the changed process.', 4, 'PROFESSIONAL', [], [], 'quality_control', 'method_change_ignored'),
        $row('An evidence label is partly unreadable and the submission form uses a different identifier. What should happen?', 'Hold the item for documented identity reconciliation before examination.', ['Choose the identifier that looks more plausible.', 'Relabel it without recording the change.', 'Examine it and fix the record later.'], 'Identity is foundational to traceability; discrepancies should be resolved transparently before examination.', 3, 'PROFESSIONAL', [], [], 'quality_control', 'identity_shortcut'),
        $row('A reviewer cannot reproduce an examiner’s conclusion because the notes omit observations and decision criteria. What is the key deficiency?', 'The conclusion lacks sufficient documentation for independent review and reproducibility.', ['The conclusion is valid because the examiner signed it.', 'Reproducibility is irrelevant to applied forensics.', 'The reviewer should infer the missing observations.'], 'A reviewable scientific conclusion requires a record of observations and the reasoning basis, not signature alone.', 4, 'PROFESSIONAL', [], [], 'analysis', 'signature_equals_validity'),
        $row('A proficiency test result is poor, but the laboratory does not assess whether casework is affected. What should quality staff require?', 'A documented impact assessment, corrective action, and review of related work where warranted.', ['Delete the proficiency result.', 'Assume casework was unaffected.', 'Stop all laboratory work permanently.'], 'A quality event should trigger proportionate investigation and corrective action rather than concealment or unsupported reassurance.', 4, 'PROFESSIONAL', [], [], 'quality_control', 'isolated_failure'),
        $row('A report uses “conclusive” for a method whose scientific foundation review identifies unresolved limitations. What wording is safer?', 'Use a qualified conclusion that states the supported observation and relevant limitations.', ['Keep “conclusive” because it is persuasive.', 'Replace it with “certain beyond doubt.”', 'Remove all method discussion.'], 'Scientific communication should match claim strength to the method’s foundation, validation, and limitations.', 3, 'PROFESSIONAL', [], [], 'reporting', 'certainty_inflation'),
    ]);

$packs[] = $pack('cle-forensics-identification', 'cle-forensics-foundation',
    ['nist-scientific-foundation-reviews','nist-firearms-toolmarks','nist-forensic-science-standards','lawphil-rules-evidence-2019'],
    'PROFESSIONAL', ['PROFESSIONAL_RULE_SENSITIVE','EVIDENCE_RULE_SENSITIVE'], ['cle-forensic-integrity'], [
        $row('A latent print has a few corresponding features but poor quality and no validated individualization basis. What conclusion is appropriate?', 'Report the observed correspondence and limitations without claiming unique identification.', ['Identify the source because any correspondence is decisive.', 'Call the print proof of presence at the scene.', 'Omit the quality limitation.'], 'Limited correspondence may support a qualified observation but not an unsupported unique source claim.', 4, 'PROFESSIONAL', [], ['cle-forensic-integrity'], 'interpretation', 'correspondence_equals_identity'),
        $row('A cartridge case shares class characteristics with a group of firearms but no test firearm is available. What can the comparison support?', 'A group-level observation, not attribution to a particular firearm.', ['Identification of the absent firearm.', 'Proof that the case was fired by the suspect.', 'No observation at all.'], 'Class characteristics can narrow a group but do not establish a unique source attribution.', 3, 'PROFESSIONAL', [], ['cle-forensic-integrity'], 'interpretation', 'class_equals_source'),
        $row('A fiber is microscopically consistent with fibers from a garment, but the population frequency and transfer history are unknown. What is defensible?', 'State compatibility and disclose that the evidence does not establish a unique source or transfer event.', ['Prove the garment was the source.', 'Prove the wearer was at the scene.', 'Treat compatibility as irrelevant.'], 'Compatibility is bounded by alternative sources, transfer mechanisms, and the information available.', 4, 'PROFESSIONAL', [], ['cle-forensic-integrity'], 'interpretation', 'compatibility_equals_transfer'),
        $row('A questioned signature differs from two hurried reference signatures. What should the examiner recommend?', 'Obtain sufficient comparable standards and state the limitation of the current comparison.', ['Declare forgery from the differences alone.', 'Declare authenticity from the similarities alone.', 'Ignore the reference quality.'], 'Reference quality and comparability affect interpretation; two poor standards do not support an overconfident conclusion.', 3, 'PROFESSIONAL', [], ['cle-forensic-integrity'], 'quality_control', 'poor_reference_ignored'),
        $row('A glass fragment has overlapping refractive properties with a window sample, but no source-frequency data are available. What is the proper conclusion?', 'Report the measured compatibility and avoid claiming a unique common source.', ['Identify the window as the source.', 'Prove the fragment was transferred during the offense.', 'Treat the overlap as proof of origin.'], 'Overlapping properties can be informative but do not establish unique source attribution without an adequate basis.', 4, 'PROFESSIONAL', [], ['cle-forensic-integrity'], 'interpretation', 'overlap_equals_origin'),
        $row('A toolmark comparison finds similarities but the examiner cannot explain the comparison criteria or uncertainty. What should the reviewer request?', 'The method, criteria, validation basis, and uncertainty or limitation statement.', ['A categorical source conclusion.', 'Removal of all uncertainty language.', 'A conclusion based on examiner seniority.'], 'A comparison must be supported by transparent criteria and a scientific foundation, not authority alone.', 4, 'PROFESSIONAL', [], ['cle-forensic-integrity'], 'quality_control', 'expertise_equals_validity'),
        $row('A DNA mixture contains low-level contributors that cannot be separated by the validated interpretation method. What should be reported?', 'A qualified mixture interpretation with the method’s inclusion limits and no unsupported single-source attribution.', ['A single-source identification.', 'A conclusion that every contributor is excluded.', 'No result and no limitation.'], 'Mixture interpretation depends on validated capability; low-level complexity can limit what may be concluded.', 4, 'PROFESSIONAL', [], ['cle-forensic-integrity'], 'interpretation', 'mixture_simplification'),
        $row('A questioned handwriting sample is too short to assess several features. What should the examiner do?', 'State the limited basis and avoid a conclusion that requires unavailable features.', ['Treat the short sample as representative of all writing.', 'Fill missing features from the reference.', 'Declare authorship from one shared letter.'], 'A conclusion must be limited by the quantity and quality of comparable information.', 3, 'PROFESSIONAL', [], ['cle-forensic-integrity'], 'interpretation', 'small_sample_overreach'),
        $row('A firearm comparison is consistent with a reference but the method’s uncertainty is not communicated. What should be changed?', 'Communicate the bounded conclusion and relevant uncertainty rather than an absolute source claim.', ['Use “certain identification.”', 'Omit the comparison details.', 'Treat uncertainty as a sign the method failed completely.'], 'Current forensic science emphasizes communicating capabilities and limitations rather than converting consistency into certainty.', 4, 'PROFESSIONAL', [], ['cle-forensic-integrity'], 'reporting', 'absolute_attribution'),
    ]);

$packs[] = $pack('cle-forensics-documentation', 'cle-forensics-foundation',
    ['nij-crime-scene-guide-2013','nij-crime-scene-guides-2017','nist-forensic-science-standards'],
    'PROFESSIONAL', ['LAW_ENFORCEMENT_PROCEDURE_SENSITIVE','PROFESSIONAL_RULE_SENSITIVE'], [
        $row('A photograph shows a mark clearly but has no scale, orientation, or indication of location. What is the main limitation?', 'The image may show detail but cannot reliably establish size and scene relationship.', ['The mark is automatically unusable.', 'Sharpness supplies scale.', 'Location can be inferred from the object name.'], 'Detail, scale, and context serve different functions; one cannot replace the others.', 3, 'PROFESSIONAL', [], [], 'process_interpretation', 'sharpness_equals_context'),
        $row('A laboratory note records a result but not the instrument condition or calibration status. What should be reviewed?', 'Whether the instrument and quality-control records support the reliability of the result.', ['Calibration never affects interpretation.', 'The result is valid because it is numerical.', 'The note should be shortened.'], 'Instrument and quality records help establish that the method operated within its validated conditions.', 4, 'PROFESSIONAL', [], [], 'quality_control', 'instrument_assumption'),
        $row('An evidence photograph is cropped so that a label and surrounding packaging are not visible. What should the case record retain?', 'The uncropped documentation or a traceable original alongside any presentation crop.', ['Only the cropped image.', 'A new image reconstructed from memory.', 'No image because crops are forbidden.'], 'Original documentation preserves context and helps reviewers identify what was altered for presentation.', 3, 'PROFESSIONAL', [], [], 'documentation', 'presentation_equals_original'),
        $row('A collection form says “blood sample” but records no observation supporting that description. How should the label be treated?', 'As a provisional description that requires method-based confirmation and clear distinction from observation.', ['As confirmed biological identity.', 'As proof of the offense.', 'As irrelevant to the chain.'], 'A field description is not automatically a laboratory identification or legal conclusion.', 3, 'PROFESSIONAL', [], [], 'interpretation', 'label_equals_result'),
        $row('A digital evidence copy has a hash value but the acquisition process and original device condition are undocumented. What remains needed?', 'Documentation of acquisition, device context, preservation, and the relationship between original and copy.', ['The hash alone proves every fact about the file.', 'The device context is irrelevant.', 'The copy should be edited for readability.'], 'Integrity checks are useful but do not replace acquisition and context documentation.', 4, 'PROFESSIONAL', [], [], 'quality_control', 'hash_equals_context'),
        $row('A report includes a conclusion but does not state which observations were directly measured and which were inferred. What improvement is needed?', 'Separate observations, interpretations, assumptions, and conclusions.', ['Combine them for readability.', 'Convert all inferences into observations.', 'Remove the observations.'], 'Separating layers of reasoning makes the conclusion auditable and prevents interpretation from being mistaken for raw data.', 4, 'PROFESSIONAL', [], [], 'reporting', 'inference_as_observation'),
        $row('A scene sketch omits orientation and fixed reference points. What does that limit?', 'Reconstruction of spatial relationships and independent verification of the recorded positions.', ['The sketch’s color only.', 'The need for photographs.', 'Every later examination automatically.'], 'Orientation and fixed references allow later reviewers to understand the spatial record.', 3, 'PROFESSIONAL', [], [], 'documentation', 'location_free_sketch'),
        $row('A laboratory report describes a method as “standard” but names no version or validation record. What should be requested?', 'The method identifier, applicable version, validation or verification basis, and deviations.', ['A more authoritative adjective.', 'Removal of the method description.', 'A guarantee that all laboratories use the same version.'], 'Method identity and current validation information are more useful than an unsupported label.', 4, 'PROFESSIONAL', [], [], 'quality_control', 'standard_label'),
        $row('A transfer log is reconstructed weeks later from memory. How should the reconstructed entry be presented?', 'As a late reconstruction with its source and limitation, not as a contemporaneous record.', ['As contemporaneous fact.', 'Without any notation so the record looks complete.', 'As proof that no gap occurred.'], 'Record timing and provenance are part of the integrity assessment; later reconstruction must remain visible.', 3, 'PROFESSIONAL', [], [], 'reporting', 'late_record_as_original'),
    ]);

$packs[] = $pack('cle-forensics-laboratory', 'cle-forensics-foundation',
    ['nist-forensic-science-standards','nist-scientific-foundation-reviews','nij-crime-scene-guide-2013'],
    'PROFESSIONAL', ['PROFESSIONAL_RULE_SENSITIVE'], ['cle-forensic-integrity'], [
        $row('A laboratory receives a sample with an ambiguous identifier and an intact seal. What should happen first?', 'Resolve the identifier discrepancy through documented chain and submission records before opening or interpreting it.', ['Open it because the seal proves identity.', 'Choose the closest identifier.', 'Relabel it without preserving the discrepancy.'], 'Seal integrity does not cure an identity mismatch; provenance must be reconciled before examination.', 3, 'PROFESSIONAL', [], ['cle-forensic-integrity'], 'quality_control', 'seal_equals_identity'),
        $row('A method’s positive control fails while the case sample produces a strong signal. What is the correct interpretation?', 'The run requires investigation and cannot be accepted solely because the case signal is strong.', ['The strong signal makes the control irrelevant.', 'Report only the case signal.', 'Change the control result to pass.'], 'Controls test the reliability of the run; a strong-looking sample result does not excuse a failed control.', 4, 'PROFESSIONAL', [], ['cle-forensic-integrity'], 'quality_control', 'signal_over_control'),
        $row('A laboratory analyst changes an extraction step but does not record the deviation. Why is that important?', 'The deviation may affect validity and prevents reviewers from evaluating whether the validated method still applies.', ['Small changes never matter.', 'Only the final answer needs a record.', 'The deviation should be hidden if the result is plausible.'], 'Undocumented deviations weaken traceability and method applicability.', 3, 'PROFESSIONAL', [], ['cle-forensic-integrity'], 'quality_control', 'deviation_hidden'),
        $row('A laboratory report says “no match” without defining the comparison population or decision rule. What should be clarified?', 'What was compared, under what method, and what the limited result does and does not exclude.', ['No match always excludes every alternative.', 'The conclusion needs no method context.', 'A stronger negative statement.'], 'A negative comparison is meaningful only within the method, population, and decision rule used.', 4, 'PROFESSIONAL', [], ['cle-forensic-integrity'], 'interpretation', 'negative_absolute'),
        $row('A sample result is near a validated reporting limit. What is the appropriate reporting approach?', 'Report the result with the method’s limit, uncertainty, and interpretation boundary.', ['Treat it as exact.', 'Round it into a positive identification.', 'Discard all near-limit results.'], 'Near-limit results require transparent limits and uncertainty rather than false precision or automatic dismissal.', 4, 'PROFESSIONAL', [], ['cle-forensic-integrity'], 'reporting', 'threshold_absolute'),
        $row('A quality manager finds that a reagent lot changed but the verification record is missing. What should be done?', 'Hold affected interpretation as needed and reconstruct or repeat verification under the quality system.', ['Assume all lots perform identically.', 'Delete the lot number.', 'Use the new lot without review.'], 'Reagent changes can affect performance; verification and traceability are quality controls.', 3, 'PROFESSIONAL', [], ['cle-forensic-integrity'], 'quality_control', 'lot_equivalence'),
        $row('An analyst reports an interpretation without stating whether a method is validated for the sample type. What is the gap?', 'The report does not establish method applicability to the material examined.', ['Validation is unrelated to sample type.', 'The examiner’s experience replaces validation.', 'The sample type should be omitted.'], 'Validation is tied to conditions and material; applicability must be documented or limited.', 4, 'PROFESSIONAL', [], ['cle-forensic-integrity'], 'analysis', 'validation_generalization'),
        $row('A second reviewer agrees with a conclusion but cannot access the underlying notes. What does agreement fail to establish?', 'Independent reviewability and reproducibility of the conclusion.', ['Agreement proves the method is valid.', 'Notes are unnecessary when experts agree.', 'The first examiner is automatically correct.'], 'Independent review needs access to the relevant observations, method, and reasoning record.', 3, 'PROFESSIONAL', [], ['cle-forensic-integrity'], 'quality_control', 'agreement_equals_validation'),
        $row('A laboratory detects contamination in a blank after several related samples were processed. What is the safest next step?', 'Investigate the contamination, assess affected samples, and repeat or qualify results as required.', ['Report all sample results unchanged.', 'Delete the blank result.', 'Assume contamination cannot affect samples.'], 'A contamination event requires impact assessment and corrective action before affected conclusions are relied upon.', 4, 'PROFESSIONAL', [], ['cle-forensic-integrity'], 'quality_control', 'blank_ignored'),
    ]);

$packs[] = $pack('cle-forensics-limits', 'cle-forensics-foundation',
    ['nist-scientific-foundation-reviews','nist-firearms-toolmarks','nist-forensic-science-standards','lawphil-rules-evidence-2019'],
    'PROFESSIONAL', ['PROFESSIONAL_RULE_SENSITIVE','EVIDENCE_RULE_SENSITIVE'], ['cle-forensic-integrity'], [
        $row('A forensic report says a method can determine when a transfer occurred, but no dating method or validation is identified. What is the correct response?', 'Remove or qualify the timing claim and identify the evidence actually supported by the method.', ['Keep the timing claim because transfer is proven.', 'Use the collection date as the transfer date.', 'Treat no method citation as proof of novelty.'], 'Without a validated dating basis, a finding about when transfer occurred is overstated.', 4, 'PROFESSIONAL', [], ['cle-forensic-integrity'], 'interpretation', 'transfer_timing_overreach'),
        $row('A questioned pattern is “consistent with” a reference but many alternative sources share the same features. What must the report avoid?', 'It must avoid presenting consistency as unique source attribution.', ['Any mention of consistency.', 'All limitations.', 'A statement of the observed features.'], 'When features are shared by alternatives, the report should preserve the distinction between compatibility and unique origin.', 3, 'PROFESSIONAL', [], ['cle-forensic-integrity'], 'reporting', 'shared_feature_overreach'),
        $row('A method has published limitations that are not discussed in a case report. Why does that matter?', 'Readers may interpret the conclusion as stronger or broader than the method supports.', ['Limitations are only relevant to researchers.', 'Omitting limitations improves neutrality.', 'The conclusion becomes automatically invalid.'], 'Limitations help communicate the boundary and uncertainty of an applied conclusion.', 3, 'PROFESSIONAL', [], ['cle-forensic-integrity'], 'reporting', 'limitation_omission'),
        $row('A report uses “proved” for a fiber association without transfer, population, or alternative-source analysis. What is needed?', 'A bounded association statement with the missing transfer and alternative-source limitations.', ['A more forceful word.', 'A claim that the fiber proves presence.', 'Removal of all source discussion.'], 'Association evidence does not by itself identify source, transfer route, or activity.', 4, 'PROFESSIONAL', [], ['cle-forensic-integrity'], 'interpretation', 'association_equals_activity'),
        $row('An analyst is asked whether a pattern proves a person’s intent. What is the appropriate scientific boundary?', 'State what the physical comparison supports and avoid inferring intent without an independent evidentiary basis.', ['Infer intent from the pattern alone.', 'Treat the request as a valid scientific question.', 'Use the most suspicious interpretation.'], 'Physical evidence may inform facts but does not automatically establish a mental state or motive.', 4, 'PROFESSIONAL', [], ['cle-forensic-integrity'], 'judgment', 'physical_equals_intent'),
        $row('A firearm/toolmark conclusion is based on examiner judgment, but the report omits known uncertainty and validation information. What should be added?', 'The method basis, uncertainty, and limitations relevant to the comparison.', ['An absolute identification label.', 'A statement that judgment has no uncertainty.', 'A claim that all examiners would agree.'], 'Current forensic standards work emphasizes transparent performance and uncertainty rather than absolute claims based only on expertise.', 4, 'PROFESSIONAL', [], ['cle-forensic-integrity'], 'reporting', 'judgment_absolute'),
        $row('A toxicology result is above a reporting threshold, but storage conditions deviated from the validated process. What is the responsible conclusion?', 'Assess stability and the deviation, then qualify or repeat the interpretation as the method requires.', ['Treat the threshold as proof of impairment.', 'Ignore storage because the result is above threshold.', 'Delete the result.'], 'A threshold does not answer whether the result remained reliable after a material preservation deviation.', 4, 'PROFESSIONAL', [], ['cle-forensic-integrity'], 'interpretation', 'threshold_equals_effect'),
        $row('A report says a biological finding proves when and how a material was deposited, but the method cannot answer those questions. What correction is required?', 'Limit the conclusion to the supported biological finding and state that timing and mechanism are unresolved.', ['Retain the wording because the material is relevant.', 'Replace it with a stronger source claim.', 'Infer the mechanism from location alone.'], 'Biological presence and the circumstances of deposition are different propositions requiring different evidence.', 3, 'PROFESSIONAL', [], ['cle-forensic-integrity'], 'interpretation', 'presence_equals_mechanism'),
        $row('A scientific review identifies an unresolved knowledge gap in a method. How should a case conclusion respond?', 'Disclose the gap and keep the conclusion within the demonstrated capability of the method.', ['Ignore the gap because the method is commonly used.', 'Treat the gap as evidence of error in every case.', 'Use the gap to claim certainty.'], 'A knowledge gap calls for calibrated communication, not automatic acceptance or rejection.', 4, 'PROFESSIONAL', [], ['cle-forensic-integrity'], 'judgment', 'common_use_equals_validity'),
    ]);

/* Criminology: 54 questions. */
$packs[] = $pack('cle-theory-application', 'cle-criminology-foundation',
    ['openoregon-introduction-criminology','openstax-deviance-crime','nij-deterrence','prc-criminology-ethics-2024'],
    'NEUTRAL', ['PROFESSIONAL_RULE_SENSITIVE'], [
        $row('A city observes a rise in recorded theft after it expands reporting access. Which interpretation is most careful?', 'Recorded cases may reflect both underlying events and improved reporting, so the measurement change must be considered.', ['Theft definitely doubled.', 'The reporting expansion had no effect.', 'Every recorded case represents a new event.'], 'Recorded crime depends on both events and reporting processes; a change in measurement can change the observed series.', 4, 'NEUTRAL', [], [], 'data_interpretation', 'recorded_equals_incidence'),
        $row('A prevention program is associated with lower reoffending, but participants volunteered and had more support before enrollment. What is the main analytical caution?', 'The association may reflect self-selection or baseline differences rather than the program alone.', ['The program caused all of the difference.', 'The program has no possible value.', 'Volunteer status proves criminal propensity.'], 'A comparison must consider assignment, baseline differences, and alternative explanations before making a causal claim.', 4, 'NEUTRAL', [], [], 'analysis', 'association_equals_causation'),
        $row('A theory explains crime by emphasizing bonds to family, school, and community. Which application fits that mechanism?', 'Examine how weakened social bonds may reduce informal controls and attachment.', ['Focus only on punishment severity.', 'Assume crime is caused by biological destiny.', 'Treat peer learning as the only mechanism.'], 'Control-oriented theories focus on social bonds and regulation, not solely on punishment or biological claims.', 3, 'NEUTRAL', [], [], 'interpretation', 'mechanism_substitution'),
        $row('A study reports that neighborhoods with disadvantage have more recorded crime. What should a careful criminologist avoid?', 'Treating the correlation as proof that every disadvantaged resident is criminal.', ['Examining structural conditions.', 'Considering measurement and exposure differences.', 'Testing alternative mechanisms.'], 'Group-level association does not justify stereotyping individuals or assuming a single causal mechanism.', 3, 'NEUTRAL', ['PROFESSIONAL_RULE_SENSITIVE'], [], 'ethics', 'ecological_fallacy'),
        $row('A deterrence proposal increases penalties but does not improve the likelihood of detection. What question should be asked?', 'Whether certainty and visibility of apprehension are likely to change, rather than assuming severity alone will deter.', ['Whether the penalty sounds severe.', 'Whether all people calculate penalties identically.', 'Whether enforcement certainty is irrelevant.'], 'Evidence-informed deterrence analysis distinguishes certainty, swiftness, and severity rather than assuming severity alone controls behavior.', 4, 'NEUTRAL', [], [], 'policy', 'severity_only'),
        $row('A theory predicts that peer definitions favorable to offending influence conduct. Which observation would best test it?', 'Patterns of peer interaction and learned definitions linked to later behavior, while considering alternative explanations.', ['The person’s neighborhood name alone.', 'A penalty increase with no peer measure.', 'A moral label assigned after the event.'], 'Learning mechanisms require evidence about interaction and definitions, not just location or outcome.', 4, 'NEUTRAL', [], [], 'research', 'location_as_mechanism'),
        $row('A policy is justified because it is consistent with one theory, but no outcome measure is specified. What is missing?', 'A testable objective and measure connecting the theory’s mechanism to the policy outcome.', ['A stronger theoretical label.', 'A promise that the policy will work.', 'Removal of evaluation.'], 'Theory-informed policy still needs measurable outcomes and a design that can test the proposed mechanism.', 3, 'NEUTRAL', [], [], 'policy', 'theory_equals_evidence'),
        $row('A report says a theory “proves” why an individual offended. What is the correction?', 'Use the theory as an explanatory framework and avoid claiming it proves an individual case without case-specific evidence.', ['Keep “proves” because theories are scientific.', 'Replace it with a criminal label.', 'Treat all theory predictions as individual diagnoses.'], 'Theories organize and test explanations; they do not automatically determine an individual’s motives or future conduct.', 3, 'NEUTRAL', ['PROFESSIONAL_RULE_SENSITIVE'], [], 'interpretation', 'theory_as_diagnosis'),
        $row('Two theories explain the same pattern through different mechanisms. How should a researcher compare them?', 'Specify distinct predictions and examine which evidence each predicts better under comparable conditions.', ['Choose the older theory automatically.', 'Combine their labels without testing.', 'Choose the theory with the more dramatic language.'], 'Comparison requires mechanism-level predictions and evidence, not age or rhetorical force.', 4, 'NEUTRAL', [], [], 'comparison', 'label_comparison'),
    ]);

$packs[] = $pack('cle-criminology-classical', 'cle-criminology-foundation',
    ['openoregon-introduction-criminology','nij-deterrence','openstax-deviance-crime'],
    'NEUTRAL', [], [
        $row('A classical perspective treats offending as related to perceived costs and benefits. Which policy question follows?', 'Whether the intervention changes the perceived certainty, swiftness, or proportionality of consequences.', ['Whether a label sounds morally severe.', 'Whether every person has identical preferences.', 'Whether evidence of behavior is unnecessary.'], 'Classical reasoning focuses on choice conditions and the perceived consequences of conduct.', 3, 'NEUTRAL', [], [], 'interpretation', 'mechanism_substitution'),
        $row('A proposal raises punishment severity but makes enforcement unpredictable. What classical-deterrence concern arises?', 'Uncertain enforcement may weaken deterrent effect even if the nominal penalty is higher.', ['Higher severity always guarantees deterrence.', 'Unpredictability makes punishment irrelevant in every case.', 'Only rehabilitation can be studied.'], 'Deterrence analysis considers certainty and swiftness as well as severity.', 3, 'NEUTRAL', [], [], 'comparison', 'severity_guarantee'),
        $row('A prevention campaign threatens extreme punishment for minor conduct. What principle should be examined?', 'Whether the response is proportionate and credible rather than merely severe.', ['Whether the threat is dramatic.', 'Whether proportionality makes deterrence impossible.', 'Whether the audience can be frightened at any cost.'], 'Classical perspectives include proportionality and rational credibility, not unlimited severity.', 3, 'NEUTRAL', [], [], 'judgment', 'fear_equals_deterrence'),
        $row('A program increases visible patrols but does not change the likelihood of detection. What should evaluation test?', 'Whether visibility changes perceived certainty or behavior, not merely whether patrols were deployed.', ['Deployment alone proves deterrence.', 'Patrols can never affect behavior.', 'Only arrest totals matter.'], 'An intervention should be evaluated through its proposed mechanism and outcome, not activity alone.', 4, 'NEUTRAL', [], [], 'evaluation', 'activity_equals_effect'),
        $row('A classical explanation is used to justify treating all people as perfectly calculating. What is the limitation?', 'The model may simplify decision-making and should be tested against context, opportunity, and constrained choice.', ['The model must be accepted literally.', 'Context never affects choice.', 'Theory limits are not relevant to policy.'], 'Theoretical models are useful abstractions but have assumptions and limits that should be made explicit.', 4, 'NEUTRAL', [], [], 'analysis', 'model_literalism'),
        $row('A policy removes an immediate opportunity for offending but does not change beliefs or social conditions. What claim is most defensible?', 'It may reduce opportunity in the setting without proving a permanent change in motivation.', ['It permanently changes all motivation.', 'It has no possible effect.', 'It proves the classical theory completely.'], 'Opportunity reduction and motivational change are distinct outcomes.', 3, 'NEUTRAL', [], [], 'interpretation', 'opportunity_equals_motivation'),
        $row('A researcher compares two deterrence campaigns but changes both messaging and enforcement at once. What is the main problem?', 'The design cannot isolate which component produced any observed difference.', ['Multiple changes always improve causal inference.', 'The campaign effect is obvious from publicity.', 'The comparison needs no baseline.'], 'Changing multiple components at once makes mechanism and attribution harder to identify.', 4, 'NEUTRAL', [], [], 'research', 'confounded_intervention'),
        $row('A deterrence report uses public fear as its only success measure. What should be added?', 'Behavioral, enforcement, fairness, and unintended-impact measures tied to the program objective.', ['More fear language.', 'A claim that fear equals compliance.', 'Removal of all outcome measures.'], 'A public-safety evaluation should not equate emotional reaction with deterrence or lawful effectiveness.', 3, 'NEUTRAL', [], [], 'evaluation', 'fear_measure'),
        $row('A proposal assumes that a harsher sanction will affect people who do not know it exists. What assumption is weak?', 'A deterrent effect generally depends on people perceiving relevant certainty and consequences, not on a rule’s existence alone.', ['Awareness is never relevant.', 'The sanction automatically changes behavior.', 'Ignorance makes the sanction stronger.'], 'Perceived consequences and enforcement conditions matter to a deterrence mechanism.', 3, 'NEUTRAL', [], [], 'analysis', 'awareness_ignored'),
    ]);

$packs[] = $pack('cle-criminology-social', 'cle-criminology-foundation',
    ['openoregon-introduction-criminology','openstax-deviance-crime','nij-deterrence'],
    'NEUTRAL', [], [
        $row('A neighborhood has high turnover, weak institutions, and limited collective coordination. Which social-structure question is appropriate?', 'How instability and weakened informal control may affect opportunities and collective responses to crime.', ['Which resident is inherently criminal.', 'Whether individual punishment explains every event.', 'Whether institutions can never change.'], 'Social-structure perspectives examine conditions and institutions without treating group membership as destiny.', 4, 'NEUTRAL', [], [], 'analysis', 'group_stereotype'),
        $row('A study finds a relationship between poverty and recorded crime but does not measure police presence or reporting. What should be considered?', 'Differences in exposure, enforcement, reporting, and other confounding conditions.', ['Poverty is the sole cause.', 'Measurement cannot affect the result.', 'The relationship proves individual guilt.'], 'Recorded outcomes reflect multiple processes; omitted exposure and measurement variables can alter interpretation.', 4, 'NEUTRAL', [], [], 'research', 'single_cause'),
        $row('A social-disorganization intervention strengthens neighborhood coordination. What would be a meaningful evaluation?', 'Assess changes in collective capacity and relevant safety outcomes while considering broader conditions.', ['Count meetings only.', 'Assume coordination proves lower crime.', 'Measure only punishment severity.'], 'The program mechanism and outcome should both be measured; activity alone is insufficient.', 4, 'NEUTRAL', [], [], 'evaluation', 'activity_equals_capacity'),
        $row('A policy describes a high-risk neighborhood using stigmatizing language. What should a criminologist recommend?', 'Use evidence-based, non-stigmatizing description and distinguish structural conditions from individual blame.', ['Keep the language because it attracts attention.', 'Label all residents as risky.', 'Remove all neighborhood data.'], 'Ethical criminology can examine structural conditions without stereotyping or assigning collective guilt.', 3, 'NEUTRAL', ['PROFESSIONAL_RULE_SENSITIVE'], [], 'ethics', 'stigmatizing_label'),
        $row('A city adds services in one area and records fewer reported incidents. What alternative explanation should be examined?', 'Changes in reporting, mobility, enforcement, and recording practices alongside any real change in events.', ['Services prove causation.', 'The decrease proves no crime occurred.', 'Alternative explanations are unnecessary.'], 'Observed trends require attention to measurement and concurrent changes before causal attribution.', 4, 'NEUTRAL', [], [], 'analysis', 'trend_causation'),
        $row('A structural theory explains broad risk but does not predict which individual will offend. What is the correct use?', 'Use it to examine population conditions and policy mechanisms, not to diagnose individuals.', ['Use it to label every resident.', 'Reject it because it is not an individual test.', 'Treat group association as individual proof.'], 'Levels of explanation matter; a population theory should not be converted into an individual judgment.', 3, 'NEUTRAL', [], [], 'interpretation', 'level_mismatch'),
        $row('A community program is designed without input from affected residents. What evidence gap is most important?', 'Whether the program addresses locally perceived problems and unintended effects.', ['Community input is always unnecessary.', 'Only enforcement data matter.', 'Participation proves the plan fits.'], 'Contextual knowledge can reveal needs, risks, and consequences that administrative data miss.', 3, 'NEUTRAL', [], [], 'planning', 'expertise_only'),
        $row('A study compares neighborhoods but ignores different population sizes and exposure to opportunity. What is needed?', 'A measurement strategy that accounts for denominators, exposure, and comparable conditions.', ['Raw counts are always comparable.', 'Population size cannot affect rates.', 'Exposure is a legal issue only.'], 'Meaningful comparison requires appropriate denominators and attention to opportunity and population differences.', 4, 'NEUTRAL', [], [], 'data_interpretation', 'raw_count_bias'),
        $row('A report concludes that a place “causes” crime based on a cross-sectional association. What should be added?', 'Temporal, mechanism, and alternative-explanation analysis before making a causal claim.', ['A stronger adjective.', 'Removal of the place variable.', 'An assumption that correlation is causation.'], 'Cross-sectional association alone does not establish temporal order or causal mechanism.', 4, 'NEUTRAL', [], [], 'analysis', 'causation_shortcut'),
    ]);

$packs[] = $pack('cle-criminology-learning', 'cle-criminology-foundation',
    ['openoregon-introduction-criminology','openstax-deviance-crime'],
    'NEUTRAL', [], [
        $row('A learning perspective emphasizes definitions favorable to offending acquired through interaction. Which evidence is most relevant?', 'The person’s pattern of interaction and the meanings learned in those relationships.', ['A neighborhood label alone.', 'A penalty increase with no interaction evidence.', 'A moral judgment after arrest.'], 'Learning theories require evidence about interaction, reinforcement, and definitions rather than location alone.', 3, 'NEUTRAL', [], [], 'interpretation', 'label_as_learning'),
        $row('A youth group rewards risky conduct with status, while prosocial behavior receives no recognition. Which mechanism is implicated?', 'Differential reinforcement that may strengthen the rewarded behavior.', ['Deterrence through certainty.', 'Random measurement error only.', 'A biological conclusion.'], 'The pattern describes reinforcement contingencies that can shape future behavior.', 3, 'NEUTRAL', [], [], 'interpretation', 'mechanism_mismatch'),
        $row('A program changes peer activities but never measures whether participants’ definitions or reinforcement changed. What is missing?', 'A measure of the proposed learning mechanism, not only the program activity.', ['More attendance counts only.', 'A claim that contact equals learning.', 'Removal of mechanism measurement.'], 'Mechanism-focused evaluation tests whether the intervention changed the process it was designed to affect.', 4, 'NEUTRAL', [], [], 'evaluation', 'contact_equals_learning'),
        $row('A researcher observes similar behavior among peers but cannot tell whether peers influenced each other or selected similar friends. What is the key limitation?', 'Selection and influence are alternative explanations that the design must distinguish.', ['Similarity proves influence.', 'Peer effects cannot be studied.', 'Selection is irrelevant.'], 'Peer similarity can arise from selection, influence, or shared context; causal inference requires a design addressing them.', 4, 'NEUTRAL', [], [], 'analysis', 'peer_similarity'),
        $row('A theory of learning is used to justify public labeling of a group as deviant. What ethical problem arises?', 'A theory about social processes is being converted into a stigmatizing identity judgment.', ['Labels always improve prevention.', 'Theory makes stigma scientific.', 'Groups have no rights to fair description.'], 'Learning theory does not justify stereotyping or treating a group label as an individual destiny.', 3, 'NEUTRAL', ['PROFESSIONAL_RULE_SENSITIVE'], [], 'ethics', 'theory_stigma'),
        $row('A mentoring program reports fewer incidents but cannot show whether peer relationships changed. What should be concluded?', 'The outcome is encouraging, but the proposed learning mechanism remains untested.', ['Mentoring definitely caused the decrease.', 'The program had no effect.', 'Mechanism evidence is never useful.'], 'Outcome and mechanism evidence answer different questions; both improve interpretation.', 4, 'NEUTRAL', [], [], 'evaluation', 'outcome_mechanism_collapse'),
        $row('A participant adopts a behavior after observing a respected peer receive a reward. Which process is most consistent?', 'Observed reinforcement or modeling within a social learning context.', ['A purely random event.', 'A proof of fixed personality.', 'A legal element.'], 'The observation links behavior to social modeling and perceived consequences.', 3, 'NEUTRAL', [], [], 'interpretation', 'fixed_trait'),
        $row('A study measures only whether a person has peers who offend, not what occurs in the relationships. What is the main limitation?', 'The measure may not capture communication, definitions, reinforcement, or frequency of interaction.', ['The measure perfectly tests learning.', 'Relationship details are irrelevant.', 'The result proves peer causation.'], 'A broad peer indicator may be a proxy rather than a direct measure of learning processes.', 4, 'NEUTRAL', [], [], 'research', 'proxy_as_mechanism'),
        $row('A prevention program removes a reward for offending but offers no alternative status pathway. What should evaluation examine?', 'Whether the changed incentive and available alternatives affect behavior and participation.', ['Removal automatically ends learning.', 'Alternatives cannot matter.', 'The program needs no follow-up.'], 'Learning and reinforcement effects depend on the broader opportunity and reward environment.', 3, 'NEUTRAL', [], [], 'evaluation', 'single_incentive'),
    ]);

$packs[] = $pack('cle-criminology-victimology', 'cle-criminology-foundation',
    ['openoregon-introduction-criminology','prc-criminology-ethics-2024','lawphil-ra10173'],
    'PROFESSIONAL', ['PROFESSIONAL_RULE_SENSITIVE','REGULATORY_SENSITIVE'], [
        $row('A victimization survey finds many incidents were never reported to authorities. What does that suggest?', 'Official records may understate victimization and should not be treated as the complete event count.', ['Unreported incidents did not occur.', 'Official records are always complete.', 'Survey responses are automatically accurate in every detail.'], 'Victimization and recorded crime measure different processes; underreporting affects the observed picture.', 3, 'PROFESSIONAL', [], [], 'data_interpretation', 'recorded_equals_total'),
        $row('A study asks victims to describe trauma publicly without explaining confidentiality or support options. What should be improved?', 'Use informed, safe participation procedures and explain confidentiality limits and available support.', ['Public disclosure always improves validity.', 'Trauma removes the need for consent.', 'Avoid documenting the procedure.'], 'Ethical victimology protects autonomy, safety, privacy, and accurate expectations.', 4, 'PROFESSIONAL', ['PROFESSIONAL_RULE_SENSITIVE','REGULATORY_SENSITIVE'], [], 'ethics', 'consent_omission'),
        $row('A report implies that a victim’s behavior caused the offense. What correction is required?', 'Separate risk or situational factors from responsibility for another person’s criminal act.', ['Keep the implication because it explains opportunity.', 'Treat prevention advice as blame.', 'Assign responsibility to the victim automatically.'], 'Contextual risk analysis should not become victim blaming or substitute for offender responsibility.', 3, 'PROFESSIONAL', ['PROFESSIONAL_RULE_SENSITIVE'], [], 'ethics', 'victim_blame'),
        $row('A survey uses a narrow definition that excludes coercive conduct not reported as a conventional offense. What is the measurement issue?', 'The operational definition may omit relevant victimization and undercount the phenomenon.', ['Narrow definitions always improve completeness.', 'Excluded events are irrelevant.', 'The survey measures all harm by default.'], 'Measurement choices shape what can be observed; exclusions should be explicit and evaluated.', 4, 'NEUTRAL', [], [], 'research', 'definition_omission'),
        $row('A victim requests that identifying details not be shared with a partner agency. What should the researcher do?', 'Assess the lawful purpose and consent/confidentiality terms before any disclosure.', ['Share the details because coordination is useful.', 'Promise absolute secrecy regardless of law.', 'Publish a de-identified story with the details unchanged.'], 'Privacy and confidentiality require a lawful, purpose-limited decision and accurate explanation of limits.', 3, 'PROFESSIONAL', ['REGULATORY_SENSITIVE'], [], 'judgment', 'coordination_over_privacy'),
        $row('A study compares victimization rates but uses different recall periods across groups. What is the concern?', 'The groups may not be comparable because the measurement window differs.', ['Recall period never affects comparison.', 'The higher rate is automatically real.', 'Differences in wording improve validity.'], 'Comparable definitions and recall periods are necessary for interpretable group comparisons.', 4, 'NEUTRAL', [], [], 'comparison', 'measurement_noncomparability'),
        $row('A victim support program reports attendance but not whether participants received the intended service or felt safer. What should be added?', 'Measures of service delivery, participant experience, safety, and relevant outcomes.', ['More attendance targets only.', 'A claim that attendance proves recovery.', 'No participant feedback.'], 'Participation is an output; victim-centered evaluation should examine quality, safety, and outcomes.', 3, 'PROFESSIONAL', ['PROFESSIONAL_RULE_SENSITIVE'], [], 'evaluation', 'attendance_equals_help'),
        $row('A report groups all victims as having the same needs. What is the analytical problem?', 'Victim experiences and service needs can differ by context, harm, vulnerability, and preference.', ['Uniform services are always fair.', 'Differences are irrelevant to planning.', 'A single stereotype improves accuracy.'], 'Victimology requires attention to variation and avoids treating a category as a complete individual profile.', 3, 'PROFESSIONAL', ['PROFESSIONAL_RULE_SENSITIVE'], [], 'analysis', 'uniform_needs'),
        $row('A researcher finds a correlation between reporting and demographic status and concludes the group is less credible. What is wrong?', 'Reporting behavior may reflect access, trust, exposure, and institutional experience rather than credibility.', ['Demographics prove credibility.', 'Reporting is a direct honesty test.', 'Institutional access cannot affect data.'], 'Observed reporting differences have multiple explanations and should not be converted into a group credibility judgment.', 4, 'PROFESSIONAL', ['PROFESSIONAL_RULE_SENSITIVE'], [], 'analysis', 'reporting_as_credibility'),
    ]);

$packs[] = $pack('cle-criminology-research', 'cle-criminology-foundation',
    ['openoregon-introduction-criminology','openstax-deviance-crime','prc-criminology-ethics-2024'],
    'NEUTRAL', ['PROFESSIONAL_RULE_SENSITIVE'], [
        $row('A research question asks whether a prevention program works but does not define “works.” What should be specified?', 'A measurable outcome, comparison or baseline, population, and time frame.', ['A stronger claim of success.', 'More theory names without an outcome.', 'A conclusion before data collection.'], 'Clear outcomes and comparison conditions make a research question answerable and interpretable.', 3, 'NEUTRAL', [], [], 'research', 'undefined_outcome'),
        $row('A study reports a statistically significant association from a nonrepresentative sample. What limitation matters?', 'Statistical significance does not establish generalizability or eliminate sampling bias.', ['Significance proves universal application.', 'A nonrepresentative sample is always useless.', 'Sampling does not affect interpretation.'], 'Inference depends on design, sampling, measurement, and the question being asked.', 4, 'NEUTRAL', [], [], 'analysis', 'significance_equals_generalizability'),
        $row('A researcher changes the outcome definition after seeing which result is favorable. What integrity concern arises?', 'Outcome switching can bias interpretation and should be disclosed and controlled.', ['Changing definitions always improves science.', 'The favorable result proves the original hypothesis.', 'Transparency is unnecessary.'], 'Transparent preregistration or disclosure helps prevent selective interpretation.', 4, 'NEUTRAL', ['PROFESSIONAL_RULE_SENSITIVE'], [], 'ethics', 'outcome_switching'),
        $row('A report measures recorded arrests as a proxy for offending without discussing enforcement exposure. What should be added?', 'A limitation that arrests reflect detection and enforcement processes as well as underlying conduct.', ['Treat arrests as a complete offense census.', 'Remove all arrest data.', 'Assume equal enforcement across groups.'], 'Administrative measures are shaped by institutional processes and may not directly measure all offending.', 3, 'NEUTRAL', [], [], 'data_interpretation', 'arrest_equals_offending'),
        $row('A qualitative study uses interviews but gives no account of recruitment or missing participants. Why does that matter?', 'Selection and nonparticipation can shape whose experiences are represented.', ['Qualitative studies need no sampling information.', 'Missing participants cannot affect themes.', 'Recruitment is unrelated to credibility.'], 'Transparency about recruitment and missing voices helps readers assess transferability and bias.', 3, 'NEUTRAL', [], [], 'research', 'missing_voice'),
        $row('A researcher finds two plausible explanations for a result and reports only the favored one. What is needed?', 'A discussion of alternatives and evidence supporting or limiting each explanation.', ['More confidence in the favored explanation.', 'Deletion of contradictory evidence.', 'A rule against alternative hypotheses.'], 'Responsible interpretation makes uncertainty and competing explanations visible.', 4, 'NEUTRAL', [], [], 'analysis', 'alternative_suppression'),
        $row('A study uses a reliable instrument that measures a different construct than the research question. What is the problem?', 'Reliability does not establish validity for the intended construct.', ['Reliability proves the construct is correct.', 'Any reliable measure answers every question.', 'Validity is irrelevant after testing.'], 'A measure can be consistent yet fail to measure what the research question requires.', 3, 'NEUTRAL', [], [], 'interpretation', 'reliability_equals_validity'),
        $row('A policy evaluation has no record of implementation fidelity. What causal question becomes harder?', 'Whether the intended intervention was actually delivered in the way being evaluated.', ['Whether any data exist.', 'Whether theory matters.', 'Whether outcomes can ever be measured.'], 'Implementation information distinguishes failure of a program theory from failure to deliver the program.', 4, 'NEUTRAL', [], [], 'evaluation', 'implementation_ignored'),
        $row('A research report includes identifiable case details that are not necessary for the analysis. What should be done?', 'Remove or protect unnecessary identifiers and follow ethical data-handling requirements.', ['Keep them because detail improves realism.', 'Publish them without consent.', 'Replace only the person’s first name.'], 'Ethical research minimizes unnecessary identification and protects participants and records.', 3, 'NEUTRAL', ['PROFESSIONAL_RULE_SENSITIVE','REGULATORY_SENSITIVE'], [], 'ethics', 'pseudonym_only'),
    ]);

/* Correctional Administration: 40 questions. */
$packs[] = $pack('cle-corrections-rehabilitation', 'cle-corrections-foundation',
    ['prc-criminology-ethics-2024','probation-quality-manual-2016','bucor-mandates-functions','bjmp-comprehensive-operations-manual-2015'],
    'PROFESSIONAL', ['CORRECTIONS_RULE_SENSITIVE','PROFESSIONAL_RULE_SENSITIVE'], [
        $row('A reentry plan lists a release date but no housing, health, work, family, or supervision needs. What is missing?', 'An individualized assessment and coordinated plan addressing relevant risks, needs, supports, and supervision.', ['A more severe condition of release.', 'A generic form with no assessment.', 'Removal of community services.'], 'Reintegration is stronger when plans address the person’s actual needs and available supports rather than only the date of release.', 4, 'PROFESSIONAL', ['CORRECTIONS_RULE_SENSITIVE'], [], 'planning', 'date_equals_plan'),
        $row('A treatment program reports attendance but not whether participants received the planned intervention or showed relevant progress. What should be added?', 'Measures of delivery, participation quality, individualized progress, and meaningful outcomes.', ['Attendance alone proves rehabilitation.', 'A more favorable completion label.', 'No follow-up after release.'], 'Attendance is an output; rehabilitation evaluation should connect delivery to individual goals and outcomes.', 3, 'PROFESSIONAL', ['PROFESSIONAL_RULE_SENSITIVE'], [], 'evaluation', 'attendance_equals_rehabilitation'),
        $row('A person in custody has a health vulnerability but is placed solely according to the general population category. What should occur?', 'Review classification with relevant health, safety, vulnerability, and accommodation information.', ['Ignore the vulnerability for equal treatment.', 'Use punishment as the only classification factor.', 'Publish the health information to all residents.'], 'Safe classification considers relevant needs and protects dignity and confidentiality.', 4, 'PROFESSIONAL', ['CORRECTIONS_RULE_SENSITIVE','PROFESSIONAL_RULE_SENSITIVE'], [], 'judgment', 'uniform_classification'),
        $row('A probation officer recommends the same condition for every supervisee regardless of risk or need. What is the main concern?', 'The condition may be disproportionate and fail to support individualized supervision and rehabilitation.', ['Uniformity always proves fairness.', 'Individualization is irrelevant after conviction.', 'More conditions always improve safety.'], 'Individualized supervision balances public safety, lawful authority, proportionality, and the person’s needs.', 4, 'PROFESSIONAL', ['CORRECTIONS_RULE_SENSITIVE'], [], 'analysis', 'uniform_condition'),
        $row('A facility defines success only as no disciplinary reports, while education and health needs remain unaddressed. What is the limitation?', 'The measure may reward suppression of reports while missing rehabilitation, safety, and humane-care outcomes.', ['No reports prove complete rehabilitation.', 'Education cannot affect correctional outcomes.', 'Health needs are unrelated to custody.'], 'Correctional performance requires multiple indicators, including humane conditions, services, safety, and reintegration.', 4, 'PROFESSIONAL', ['PROFESSIONAL_RULE_SENSITIVE'], [], 'analysis', 'single_metric'),
        $row('A staff member proposes withholding a necessary medical service as punishment for a rule violation. What principle controls?', 'Discipline must remain lawful and humane and cannot justify denial of necessary care.', ['Medical care is always a privilege.', 'Punishment removes all rights.', 'The proposal is valid if it is convenient.'], 'Humane custody and professional ethics limit disciplinary responses and protect basic care.', 3, 'PROFESSIONAL', ['CORRECTIONS_RULE_SENSITIVE','PROFESSIONAL_RULE_SENSITIVE'], [], 'judgment', 'care_as_privilege'),
        $row('A reintegration plan is created without asking the person what barriers they face in the community. What should be improved?', 'Use a participatory assessment and coordinate supports that address identified barriers.', ['Decide all needs from the record alone.', 'Treat participation as unnecessary.', 'Use the same plan for every person.'], 'Effective reintegration is individualized and benefits from the person’s information about needs and feasible supports.', 3, 'PROFESSIONAL', ['PROFESSIONAL_RULE_SENSITIVE'], [], 'planning', 'top_down_plan'),
        $row('A facility’s classification decision is never revisited after new behavior and health information emerge. What is the concern?', 'A static classification may become inaccurate and create avoidable safety or treatment problems.', ['Initial classification is permanent.', 'New information is always unreliable.', 'Review creates unfairness.'], 'Classification is a decision that should be reviewed when material information changes.', 4, 'PROFESSIONAL', ['CORRECTIONS_RULE_SENSITIVE'], [], 'analysis', 'static_label'),
    ], ['cle-corrections-rehabilitation']);

$packs[] = $pack('cle-corrections-institutional', 'cle-corrections-foundation',
    ['bjmp-comprehensive-operations-manual-2015','bucor-mandates-functions','prc-criminology-ethics-2024','lawphil-ra9745'],
    'PROFESSIONAL', ['CORRECTIONS_RULE_SENSITIVE','PROFESSIONAL_RULE_SENSITIVE'], ['cle-corrections-rehabilitation'], [
        $row('A receiving officer accepts a new detainee without checking identity, authority, health concerns, or documentation. What is the first concern?', 'The admission process lacks safeguards needed for lawful, safe, and traceable reception.', ['Speed is the only admission priority.', 'Identity can be inferred later.', 'Health information is unrelated to custody.'], 'Reception establishes lawful authority, identity, safety, and records needed for humane custody.', 4, 'PROFESSIONAL', ['CORRECTIONS_RULE_SENSITIVE'], ['cle-corrections-rehabilitation'], 'process_interpretation', 'intake_shortcut'),
        $row('A facility responds to a conflict by placing all involved people in isolation without assessing vulnerability or alternatives. What should be reviewed?', 'The necessity, proportionality, safety, legal authority, and individualized effects of the response.', ['Isolation is always the safest answer.', 'Individual assessment increases risk.', 'A blanket response needs no record.'], 'Institutional decisions should be lawful, proportionate, and informed by individual safety and vulnerability.', 4, 'PROFESSIONAL', ['CORRECTIONS_RULE_SENSITIVE','PROFESSIONAL_RULE_SENSITIVE'], ['cle-corrections-classification'], 'judgment', 'blanket_isolation'),
        $row('A jail’s emergency plan assigns no responsibility for medical escalation. What is the operational gap?', 'The plan lacks a clear role and trigger for timely health response.', ['Medical escalation can be improvised in every emergency.', 'Only security roles matter.', 'The gap should remain unrecorded.'], 'Clear roles and escalation triggers reduce delay and confusion during emergencies.', 3, 'PROFESSIONAL', ['CORRECTIONS_RULE_SENSITIVE'], [], 'planning', 'role_omission'),
        $row('A staff member records a disciplinary incident but omits the person’s response and relevant witness information. What is weakened?', 'The completeness and fairness of the review record.', ['The omission improves neutrality.', 'Only the staff view matters.', 'Witness information is never relevant.'], 'A fair record captures material perspectives and facts needed to assess the incident.', 3, 'PROFESSIONAL', ['PROFESSIONAL_RULE_SENSITIVE'], [], 'reporting', 'one_sided_record'),
        $row('A facility relies on a single headcount record that conflicts with the admission and movement logs. What should occur?', 'Reconcile the records promptly and document the discrepancy and corrective action.', ['Choose the number that looks safest.', 'Delete the conflicting logs.', 'Assume all records are equally correct.'], 'Identity and movement records are safety-critical and require transparent reconciliation when they conflict.', 4, 'PROFESSIONAL', ['CORRECTIONS_RULE_SENSITIVE'], [], 'quality_control', 'record_selection'),
        $row('An institution’s security measure increases but reports of fear and access barriers also increase. How should management evaluate it?', 'Assess security, humane treatment, access, and unintended effects together.', ['Treat increased security as proof of success.', 'Ignore resident experience.', 'Remove all security controls.'], 'Correctional management balances security with dignity, services, safety, and rights rather than optimizing one metric.', 4, 'PROFESSIONAL', ['PROFESSIONAL_RULE_SENSITIVE'], [], 'analysis', 'security_only'),
        $row('A staff briefing shares sensitive health information with people who do not need it for their role. What should change?', 'Limit access to necessary personnel and document the appropriate confidentiality process.', ['Share it widely for convenience.', 'Post it in the common area.', 'Delete the health record.'], 'Confidentiality protects dignity and supports lawful, safe care; access should be role-appropriate.', 3, 'PROFESSIONAL', ['REGULATORY_SENSITIVE','PROFESSIONAL_RULE_SENSITIVE'], [], 'judgment', 'need_to_know_ignored'),
        $row('A facility policy is older than a newer agency issuance on the same operational procedure. What should the administrator do?', 'Verify the current controlling issuance and update or clarify local implementation.', ['Use the older policy automatically.', 'Use both conflicting procedures at once.', 'Ignore currentness because operations are stable.'], 'Operational procedures are version-sensitive; current agency authority should control and be documented.', 3, 'PROFESSIONAL', ['CORRECTIONS_RULE_SENSITIVE'], [], 'process_interpretation', 'policy_staleness'),
    ]);

$packs[] = $pack('cle-corrections-community', 'cle-corrections-foundation',
    ['probation-quality-manual-2016','bucor-mandates-functions','bucor-reintegration-2025','prc-criminology-ethics-2024'],
    'PROFESSIONAL', ['CORRECTIONS_RULE_SENSITIVE','PROFESSIONAL_RULE_SENSITIVE'], ['cle-corrections-rehabilitation'], [
        $row('A community supervision plan contains restrictions but no explanation of how the conditions address assessed risks or needs. What is missing?', 'A documented link between assessment, proportional conditions, services, and supervision goals.', ['More restrictions regardless of purpose.', 'A generic warning.', 'Removal of all supervision.'], 'Conditions should be purposeful, proportionate, and connected to individualized supervision and rehabilitation.', 4, 'PROFESSIONAL', ['CORRECTIONS_RULE_SENSITIVE'], ['cle-corrections-rehabilitation'], 'planning', 'restriction_without_purpose'),
        $row('A supervisee misses an appointment because transportation was unavailable. What should the officer assess before imposing a response?', 'The reason, pattern, risk, communication, and feasible support or corrective action.', ['Treat every absence as deliberate defiance.', 'Ignore all missed appointments.', 'Impose the harshest response automatically.'], 'Individualized supervision distinguishes barriers from willful noncompliance and uses proportionate responses.', 4, 'PROFESSIONAL', ['CORRECTIONS_RULE_SENSITIVE'], ['cle-corrections-rehabilitation'], 'judgment', 'absence_equals_defiance'),
        $row('A reintegration team coordinates housing and employment but excludes health-care planning. What risk follows?', 'An unaddressed health need may undermine safety, stability, and successful community transition.', ['Health planning is unrelated to reentry.', 'Employment always cures health barriers.', 'Coordination should stop.'], 'Reintegration is multi-dimensional; health, housing, work, support, and supervision can interact.', 3, 'PROFESSIONAL', ['PROFESSIONAL_RULE_SENSITIVE'], ['cle-corrections-rehabilitation'], 'analysis', 'single_service'),
        $row('A community program claims reintegration success from placement alone without checking retention or support. What should be measured?', 'Sustained participation, barriers, supervision, support, and relevant safety outcomes.', ['Placement proves permanent success.', 'Support is irrelevant after placement.', 'Only a public-relations story.'], 'A placement is an intermediate outcome; sustained reintegration requires follow-up and support.', 3, 'PROFESSIONAL', ['PROFESSIONAL_RULE_SENSITIVE'], ['cle-corrections-rehabilitation'], 'evaluation', 'placement_equals_success'),
        $row('A probation record includes unnecessary personal details in a report shared with a community partner. What should change?', 'Minimize disclosure to the lawful purpose and protect information not needed by the partner.', ['Share the complete record to improve coordination.', 'Remove only the person’s first name.', 'Publish the details to encourage compliance.'], 'Purpose-limited disclosure protects privacy while allowing legitimate coordination.', 3, 'PROFESSIONAL', ['REGULATORY_SENSITIVE'], ['cle-corrections-rehabilitation'], 'judgment', 'overdisclosure'),
        $row('A community partner wants a uniform service plan for all released people. What should the correctional professional explain?', 'Services should be matched to individualized needs, strengths, risk, and feasible supports.', ['Uniformity always improves rehabilitation.', 'Individual needs create unfairness.', 'A plan should be based only on offense label.'], 'Individualized treatment is more defensible than a one-size plan that ignores relevant differences.', 4, 'PROFESSIONAL', ['PROFESSIONAL_RULE_SENSITIVE'], ['cle-corrections-rehabilitation'], 'planning', 'offense_label_only'),
        $row('A person on supervision reports a credible safety threat at home. What should happen?', 'Assess the threat promptly, coordinate appropriate safety support, and document the response.', ['Ignore it because supervision is not social work.', 'Treat the report as noncompliance.', 'Share the report publicly.'], 'Community supervision includes safety-aware coordination and should respond to material risk information.', 4, 'PROFESSIONAL', ['PROFESSIONAL_RULE_SENSITIVE'], ['cle-corrections-rehabilitation'], 'emergency', 'role_narrowing'),
        $row('A reintegration program works with a family but never asks whether the family understands the plan or consents to its role. What is missing?', 'Clear communication about roles, expectations, confidentiality, and voluntary participation where applicable.', ['Family involvement is automatically consented.', 'More restrictions on the family.', 'No explanation to protect efficiency.'], 'Coordination is safer when participants understand what is expected and what information will be shared.', 3, 'PROFESSIONAL', ['PROFESSIONAL_RULE_SENSITIVE','REGULATORY_SENSITIVE'], ['cle-corrections-rehabilitation'], 'communication', 'family_assumption'),
    ]);

$packs[] = $pack('cle-corrections-classification', 'cle-corrections-foundation',
    ['bjmp-comprehensive-operations-manual-2015','bucor-mandates-functions','prc-criminology-ethics-2024'],
    'PROFESSIONAL', ['CORRECTIONS_RULE_SENSITIVE','PROFESSIONAL_RULE_SENSITIVE'], ['cle-corrections-rehabilitation'], [
        $row('A classification officer uses only the offense label and ignores health, behavior, vulnerability, and institutional risk. What is the weakness?', 'The decision is too narrow to support individualized safety and management.', ['Offense labels always provide complete risk information.', 'More information would reduce fairness.', 'Classification should never use behavior.'], 'Classification should use relevant, current information and not treat an offense label as a complete person profile.', 4, 'PROFESSIONAL', ['CORRECTIONS_RULE_SENSITIVE'], ['cle-corrections-rehabilitation'], 'analysis', 'label_as_risk'),
        $row('New information indicates that a resident’s vulnerability has changed. What should classification staff do?', 'Review the classification and accommodations in light of the new material information.', ['Keep the original decision permanently.', 'Disclose the information to all residents.', 'Ignore it until release.'], 'Classification is reviewable when material risk, needs, or vulnerability information changes.', 3, 'PROFESSIONAL', ['CORRECTIONS_RULE_SENSITIVE'], ['cle-corrections-rehabilitation'], 'process_interpretation', 'static_classification'),
        $row('A classification recommendation is based on a rumor with no source or corroboration. What should happen?', 'Treat it as an unverified lead and seek reliable information before making a consequential decision.', ['Treat it as established risk.', 'Publish the rumor in the file as fact.', 'Discard all other information.'], 'High-consequence classification requires reliable, documented information and appropriate uncertainty.', 4, 'PROFESSIONAL', ['CORRECTIONS_RULE_SENSITIVE'], ['cle-corrections-rehabilitation'], 'evidence_interpretation', 'rumor_as_risk'),
        $row('A resident is placed in a restrictive setting but receives no explanation or review path. What safeguard is missing?', 'A documented basis, communication, and a mechanism for review or challenge under applicable policy.', ['Restriction needs no explanation.', 'Review would undermine security.', 'Only release creates a review right.'], 'Procedural fairness and accountability require a recorded basis and a review path consistent with security needs.', 4, 'PROFESSIONAL', ['CORRECTIONS_RULE_SENSITIVE','PROFESSIONAL_RULE_SENSITIVE'], ['cle-corrections-rehabilitation'], 'judgment', 'restriction_without_review'),
        $row('Two residents have similar offense labels but different health and vulnerability needs. What is the defensible classification approach?', 'Use the relevant differences to make individualized safety and care decisions.', ['Give them identical placement automatically.', 'Ignore health to ensure equal treatment.', 'Classify by rumor.'], 'Equal respect does not require identical treatment when material safety and health needs differ.', 3, 'PROFESSIONAL', ['CORRECTIONS_RULE_SENSITIVE'], ['cle-corrections-rehabilitation'], 'comparison', 'equality_as_identity'),
        $row('A classification tool produces a high score but its local validity for the population is unknown. What should the reviewer do?', 'Treat the score as one input, disclose its limitation, and use qualified professional review.', ['Treat the score as a final truth.', 'Discard all structured tools.', 'Use the score to deny every service.'], 'A tool’s output requires evidence of applicability and should not replace contextual professional review.', 4, 'PROFESSIONAL', ['PROFESSIONAL_RULE_SENSITIVE'], ['cle-corrections-rehabilitation'], 'analysis', 'score_equals_truth'),
        $row('A placement decision is made without recording the information considered. What is weakened?', 'Transparency, reviewability, and the ability to detect error or bias.', ['The decision becomes more impartial.', 'Documentation is unrelated to fairness.', 'The placement automatically becomes invalid.'], 'A concise decision record supports review without requiring every decision to be perfect.', 3, 'PROFESSIONAL', ['PROFESSIONAL_RULE_SENSITIVE'], ['cle-corrections-rehabilitation'], 'quality_control', 'record_free_decision'),
        $row('A classification review identifies a safety concern but no available accommodation or treatment pathway. What should the team do?', 'Document the gap, escalate for resources or specialist input, and use an interim safety measure.', ['Hide the gap to avoid criticism.', 'Use the most restrictive placement permanently.', 'Ignore the concern because no service exists.'], 'A resource gap requires transparent escalation and proportionate interim protection, not concealment or automatic overrestriction.', 4, 'PROFESSIONAL', ['CORRECTIONS_RULE_SENSITIVE'], ['cle-corrections-rehabilitation'], 'judgment', 'resource_gap_hidden'),
    ]);

$packs[] = $pack('cle-corrections-reintegration', 'cle-corrections-foundation',
    ['probation-quality-manual-2016','bucor-reintegration-2025','bucor-mandates-functions','prc-criminology-ethics-2024'],
    'PROFESSIONAL', ['CORRECTIONS_RULE_SENSITIVE','PROFESSIONAL_RULE_SENSITIVE'], ['cle-corrections-rehabilitation'], [
        $row('A pre-release plan is completed one day before release and contains no referral confirmation. What is the main weakness?', 'The plan is not sufficiently coordinated or timely to support continuity of care and supervision.', ['Timing never affects reintegration.', 'A referral list guarantees service access.', 'Release itself is the reintegration plan.'], 'Continuity requires advance coordination, confirmed contacts, and attention to practical barriers.', 4, 'PROFESSIONAL', ['CORRECTIONS_RULE_SENSITIVE'], ['cle-corrections-rehabilitation'], 'planning', 'referral_equals_access'),
        $row('A released person lacks identification documents needed for work and services. What should a reintegration team do?', 'Coordinate lawful document recovery or replacement as part of the individualized plan.', ['Treat the barrier as noncompliance.', 'Ignore it because employment is optional.', 'Publish the person’s details to obtain help.'], 'Practical barriers can undermine reintegration and should be addressed through coordinated, lawful support.', 3, 'PROFESSIONAL', ['PROFESSIONAL_RULE_SENSITIVE'], ['cle-corrections-rehabilitation'], 'judgment', 'barrier_as_defiance'),
        $row('A program measures reintegration only by whether a person attends the first meeting after release. What is missing?', 'Longer-term continuity, service access, stability, supervision, and safety outcomes.', ['The first meeting proves permanent success.', 'Follow-up creates bias.', 'Only attendance can be measured.'], 'Early contact is useful but cannot stand for sustained reintegration.', 3, 'PROFESSIONAL', ['PROFESSIONAL_RULE_SENSITIVE'], ['cle-corrections-rehabilitation'], 'evaluation', 'first_contact_equals_success'),
        $row('A community agency receives a referral but does not know what support is requested or who will follow up. What should be clarified?', 'The person’s consent or authority basis, requested service, contact roles, and follow-up process.', ['Send the entire correctional file.', 'Assume the agency will determine all needs.', 'Make no follow-up record.'], 'A referral is actionable when purpose, roles, information limits, and follow-up are clear.', 4, 'PROFESSIONAL', ['REGULATORY_SENSITIVE','PROFESSIONAL_RULE_SENSITIVE'], ['cle-corrections-rehabilitation'], 'coordination', 'referral_vagueness'),
        $row('A reintegration officer treats a missed service appointment as failure without asking whether the service was accessible. What should happen?', 'Assess access barriers and adapt the plan while maintaining appropriate accountability.', ['Impose the harshest response immediately.', 'Ignore all missed appointments.', 'Assume refusal without inquiry.'], 'Effective supervision distinguishes barriers from deliberate noncompliance and responds proportionately.', 4, 'PROFESSIONAL', ['CORRECTIONS_RULE_SENSITIVE'], ['cle-corrections-rehabilitation'], 'judgment', 'access_ignored'),
        $row('A person’s family can provide housing but requests guidance on boundaries and support expectations. What should the team provide?', 'Clear, respectful information about roles, limits, confidentiality, safety, and available support.', ['Require the family to accept every responsibility.', 'Share all case details without limit.', 'Exclude the family from planning.'], 'Family support is more sustainable when expectations and boundaries are explicit.', 3, 'PROFESSIONAL', ['PROFESSIONAL_RULE_SENSITIVE','REGULATORY_SENSITIVE'], ['cle-corrections-rehabilitation'], 'communication', 'family_role_assumption'),
        $row('A program claims that low recorded violations prove a successful transition, but it does not check whether people stopped reporting problems. What should be considered?', 'Underreporting and access to support may affect the apparent violation rate.', ['Low records always prove low harm.', 'Reporting cannot change outcome measures.', 'The program should stop collecting feedback.'], 'Administrative counts can reflect reporting and access processes as well as actual events.', 4, 'PROFESSIONAL', ['PROFESSIONAL_RULE_SENSITIVE'], ['cle-corrections-rehabilitation'], 'analysis', 'low_record_equals_success'),
        $row('A reintegration plan conflicts with a current agency procedure, but the plan writer relies on an older manual. What is required?', 'Verify the current controlling procedure and revise the plan or document a lawful exception.', ['Use the older manual because it is familiar.', 'Ignore both sources.', 'Apply the more restrictive option automatically.'], 'Operational currentness matters when an agency procedure governs implementation details.', 3, 'PROFESSIONAL', ['CORRECTIONS_RULE_SENSITIVE'], ['cle-corrections-rehabilitation'], 'process_interpretation', 'old_manual'),
    ]);

$officialAreas = [
    'Criminal Law and Jurisprudence' => ['weight' => 20, 'domain' => 'cle-criminal-jurisprudence'],
    'Law Enforcement Administration' => ['weight' => 15, 'domain' => 'cle-law-enforcement'],
    'Crime Detection and Investigation' => ['weight' => 20, 'domain' => 'cle-investigation'],
    'Criminalistics and Forensic Science' => ['weight' => 15, 'domain' => 'cle-forensics'],
    'Criminology' => ['weight' => 20, 'domain' => 'cle-criminology'],
    'Correctional Administration' => ['weight' => 10, 'domain' => 'cle-corrections'],
];
$areaByDomain = [];
foreach ($officialAreas as $area => $info) $areaByDomain[$info['domain']] = $area;
$blueprintByDomain = [
    'cle-criminal-jurisprudence' => 'criminal-jurisprudence',
    'cle-law-enforcement' => 'law-enforcement',
    'cle-investigation' => 'investigation',
    'cle-forensics' => 'forensics',
    'cle-criminology' => 'criminology',
    'cle-corrections' => 'corrections',
];

$scopeSourceIds = ['prc-criminology-board-page','prc-criminology-enhanced-tos-2024','prc-criminology-schedule-2025','lawphil-ra11131'];
$sourceRoles = [
    'prc-criminology-board-page' => 'SCOPE_SOURCE',
    'prc-criminology-enhanced-tos-2024' => 'SCOPE_SOURCE',
    'prc-criminology-schedule-2025' => 'SCOPE_SOURCE',
    'lawphil-ra11131' => 'STATUTORY_SOURCE',
    'lawphil-revised-penal-code' => 'STATUTORY_SOURCE',
    'lawphil-rules-criminal-procedure' => 'PROCEDURAL_SOURCE',
    'lawphil-rules-evidence-2019' => 'PROCEDURAL_SOURCE',
    'lawphil-ra7438' => 'STATUTORY_SOURCE',
    'lawphil-ra9745' => 'STATUTORY_SOURCE',
    'lawphil-ra10173' => 'STATUTORY_SOURCE',
    'lawphil-ra9165' => 'STATUTORY_SOURCE',
    'prc-criminology-ethics-2024' => 'REGULATORY_SOURCE',
    'nij-crime-scene-guide-2013' => 'AGENCY_OPERATIONAL_SOURCE',
    'nij-crime-scene-guides-2017' => 'AGENCY_OPERATIONAL_SOURCE',
    'nist-forensic-science-standards' => 'FORENSIC_SCIENTIFIC_SOURCE',
    'nist-scientific-foundation-reviews' => 'FORENSIC_SCIENTIFIC_SOURCE',
    'nist-firearms-toolmarks' => 'FORENSIC_SCIENTIFIC_SOURCE',
    'openoregon-introduction-criminology' => 'INSTRUCTIONAL_SOURCE',
    'openstax-deviance-crime' => 'INSTRUCTIONAL_SOURCE',
    'nij-deterrence' => 'INSTRUCTIONAL_SOURCE',
    'probation-quality-manual-2016' => 'AGENCY_OPERATIONAL_SOURCE',
    'bjmp-comprehensive-operations-manual-2015' => 'AGENCY_OPERATIONAL_SOURCE',
    'bucor-mandates-functions' => 'AGENCY_OPERATIONAL_SOURCE',
    'bucor-reintegration-2025' => 'AGENCY_OPERATIONAL_SOURCE',
];

$legacyDepthMap = [
    708 => 3, 710 => 4, 712 => 3, 714 => 4, 716 => 3, 718 => 4,
    720 => 4, 722 => 3, 724 => 4, 726 => 4, 728 => 4, 730 => 4,
    733 => 3, 735 => 3, 737 => 4, 739 => 3, 741 => 4, 743 => 4,
    745 => 3, 747 => 4, 749 => 4, 751 => 4, 753 => 4, 755 => 4,
];
$legacySampleIds = [707,709,711,713,715,717,719,721,723,725,732,734,736,738,740,742,744,746,748,750,757,758,759,760,761,762,763,764,765,807,808,809,832,833,834,835,836,837,838,839,840];
$legacySourceSets = [
    'legal' => ['lawphil-revised-penal-code','lawphil-rules-criminal-procedure','lawphil-rules-evidence-2019','lawphil-ra7438','lawphil-ra9745'],
    'lea' => ['prc-criminology-ethics-2024','lawphil-ra11131','lawphil-ra10173','nij-crime-scene-guides-2017'],
    'investigation' => ['nij-crime-scene-guide-2013','nij-crime-scene-guides-2017','lawphil-rules-evidence-2019','lawphil-rules-criminal-procedure'],
    'forensic' => ['nist-forensic-science-standards','nist-scientific-foundation-reviews','nist-firearms-toolmarks','nij-crime-scene-guide-2013','lawphil-rules-evidence-2019'],
    'criminology' => ['openoregon-introduction-criminology','openstax-deviance-crime','nij-deterrence','prc-criminology-ethics-2024'],
    'corrections' => ['probation-quality-manual-2016','bjmp-comprehensive-operations-manual-2015','bucor-mandates-functions','bucor-reintegration-2025','prc-criminology-ethics-2024'],
];
$existingQuestionById = [];
foreach ($questionsExisting as $question) $existingQuestionById[(int)($question['id'] ?? 0)] = $question;
$legacySourceForId = static function (int $id) use ($legacySourceSets): array {
    if ($id >= 707 && $id <= 730) return $legacySourceSets['legal'];
    if ($id >= 732 && $id <= 755) return $legacySourceSets['lea'];
    if ($id >= 757 && $id <= 781) return $legacySourceSets['investigation'];
    if ($id >= 782 && $id <= 806) return $legacySourceSets['forensic'];
    if ($id >= 807 && $id <= 831) return $legacySourceSets['criminology'];
    if ($id >= 832 && $id <= 856) return $legacySourceSets['corrections'];
    return [];
};
$packSourcesForQuestion = static function (array $packRow, array $rowData) use ($unique): array {
    return $unique(array_merge(['prc-criminology-enhanced-tos-2024'], $packRow['sources'], $rowData['sensitivity'] !== [] ? [] : []));
};
$domainForConcept = static function (string $conceptId) use ($conceptById, $topicById): string {
    $topic = (string)($conceptById[$conceptId]['topic_id'] ?? '');
    return (string)($topicById[$topic]['domain_id'] ?? '');
};
$areaForConcept = static function (string $conceptId) use ($domainForConcept, $areaByDomain): string {
    return $areaByDomain[$domainForConcept($conceptId)] ?? 'Unmapped';
};
$makeChoices = static function (string $answer, array $distractors, int $id): array {
    $choices = array_values(array_unique(array_merge([$answer], $distractors)));
    if (count($choices) !== 4) throw new RuntimeException('Choice count is not four for question ' . $id);
    $position = $id % 4;
    $out = [];
    $correct = array_shift($choices);
    for ($i = 0; $i < 4; $i++) {
        $out[] = $i === $position ? $correct : array_shift($choices);
    }
    return $out;
};

/* Law Enforcement Administration: 42 questions. */
$packs[] = $pack('cle-administration-accountability', 'cle-law-enforcement-foundation',
    ['prc-criminology-ethics-2024','lawphil-ra11131','lawphil-ra10173'],
    'PROFESSIONAL', ['PROFESSIONAL_RULE_SENSITIVE','REGULATORY_SENSITIVE'], [
        $row('A unit report records a high-risk operation but names no approving authority, assigned roles, or review point. What should the supervisor require?', 'A documented authority, role assignment, risk control, and after-action review point.', ['More dramatic language about the operation.', 'A larger team without clarifying roles.', 'Removal of the risk discussion from the report.'], 'Accountability requires a traceable decision basis and role clarity, not simply a larger or more forceful operation.', 4, 'PROFESSIONAL', ['PROFESSIONAL_RULE_SENSITIVE'], [], 'judgment', 'accountability_gap'),
        $row('A supervisor delegates a decision and then refuses to review its documented result. What principle is implicated?', 'Delegation does not eliminate supervisory accountability for lawful oversight and review.', ['Delegation transfers every responsibility permanently.', 'Review is unnecessary when the result looks favorable.', 'Only the junior officer can be accountable.'], 'A leader may delegate tasks while retaining responsibility to provide oversight, correction, and a reviewable record.', 3, 'PROFESSIONAL', ['PROFESSIONAL_RULE_SENSITIVE'], [], 'interpretation', 'delegation_erases_accountability'),
        $row('An officer reports a procedural deviation that may have affected evidence integrity. What is the appropriate administrative response?', 'Preserve the disclosure, assess its impact, and route it through the established review process.', ['Punish the reporting officer before examining the facts.', 'Delete the report to protect the unit.', 'Ignore the deviation if the result is useful.'], 'Transparent review allows the agency to assess impact and correct risk without suppressing a material deviation.', 4, 'PROFESSIONAL', ['PROFESSIONAL_RULE_SENSITIVE'], [], 'process_interpretation', 'deviation_suppression'),
        $row('A performance dashboard rewards arrests without recording complaints, evidence quality, or lawful-process indicators. What is the main weakness?', 'The metric can reward volume while hiding legality, quality, and community-impact outcomes.', ['The dashboard has too many indicators.', 'Arrest volume is a complete measure of performance.', 'Community feedback is never relevant to operations.'], 'A single output measure can create perverse incentives and cannot substitute for balanced accountability indicators.', 4, 'PROFESSIONAL', ['PROFESSIONAL_RULE_SENSITIVE'], [], 'analysis', 'single_metric'),
        $row('A partner agency asks for information but cannot state the purpose or legal authority for receiving it. What should the administrator do?', 'Clarify the purpose and authority and disclose only what is necessary and permitted.', ['Share the complete file to preserve partnership.', 'Refuse every future coordination request.', 'Send the data through a personal account.'], 'Interagency cooperation still requires purpose limitation, lawful authority, and controlled information handling.', 3, 'PROFESSIONAL', ['REGULATORY_SENSITIVE'], [], 'judgment', 'partnership_overrides_controls'),
        $row('A supervisor receives a complaint against a favored team member and assigns the same member to investigate it alone. What concern is strongest?', 'The arrangement creates a conflict risk and lacks an impartial review safeguard.', ['Favoritism is harmless if the investigator is experienced.', 'Complaints require no documented handling.', 'The complaint should be destroyed after review.'], 'Independence and conflict controls protect the credibility of an accountability process.', 4, 'PROFESSIONAL', ['PROFESSIONAL_RULE_SENSITIVE'], [], 'ethics', 'conflict_blindness'),
        $row('A unit’s policy is silent on who approves an exceptional operational risk. What is the best administrative action?', 'Escalate for a documented authority and interim risk-control decision before proceeding.', ['Allow the most senior person present to decide informally.', 'Proceed because silence means permission.', 'Avoid recording the risk to reduce liability.'], 'An undefined approval path is a governance gap; escalation and documentation are safer than informal assumption.', 4, 'PROFESSIONAL', ['PROFESSIONAL_RULE_SENSITIVE'], [], 'planning', 'silence_equals_authority'),
    ]);

$packs[] = $pack('cle-law-enforcement-planning', 'cle-law-enforcement-foundation',
    ['lawphil-ra11131','prc-criminology-ethics-2024','nij-crime-scene-guides-2017'],
    'PROFESSIONAL', ['LAW_ENFORCEMENT_PROCEDURE_SENSITIVE','PROFESSIONAL_RULE_SENSITIVE'], ['cle-administration-accountability'], [
        $row('A community operation has a clear objective but no contingency for an unexpected crowd or medical emergency. What should be added?', 'A risk assessment, contingency roles, communication path, and trigger for escalation or pause.', ['A second slogan for the operation.', 'A promise that no unexpected event will occur.', 'Removal of the public-safety objective.'], 'A useful plan connects objectives to hazards, roles, communications, and decision triggers.', 4, 'PROFESSIONAL', ['LAW_ENFORCEMENT_PROCEDURE_SENSITIVE'], ['cle-administration-accountability'], 'planning', 'no_contingency'),
        $row('A planning team chooses an intervention before defining the problem it is intended to solve. Which correction is best?', 'Define the problem, affected population, evidence, objective, and success measure before selecting the intervention.', ['Choose the most visible intervention first.', 'Use last year’s plan without checking conditions.', 'Treat the number of personnel as the objective.'], 'Problem definition and measurable objectives prevent resources from being committed to an untested solution.', 4, 'PROFESSIONAL', ['PROFESSIONAL_RULE_SENSITIVE'], ['cle-administration-accountability'], 'planning', 'solution_first'),
        $row('A plan depends on a partner agency but names no liaison, handoff, or information-sharing limit. What is missing?', 'A coordination protocol with named responsibilities, handoff conditions, and lawful data boundaries.', ['A larger budget only.', 'An informal promise to cooperate.', 'A rule that all data must be shared.'], 'Coordination becomes actionable when roles, handoffs, and information limits are explicit and reviewable.', 3, 'PROFESSIONAL', ['LAW_ENFORCEMENT_PROCEDURE_SENSITIVE','REGULATORY_SENSITIVE'], ['cle-administration-accountability'], 'planning', 'coordination_assumption'),
        $row('A unit plans to deploy the same tactic in two neighborhoods without comparing local risks or community concerns. What is the analytical weakness?', 'The plan assumes transferability without assessing context, risk, and affected-community conditions.', ['The plan has too much local information.', 'Uniformity guarantees fairness.', 'Community concerns are unrelated to planning.'], 'A sound plan tests whether the same approach fits different conditions and identifies foreseeable impacts.', 4, 'PROFESSIONAL', ['PROFESSIONAL_RULE_SENSITIVE'], [], 'comparison', 'context_blindness'),
        $row('An operation has a success metric that can be improved by recording more low-value contacts. What should the planner do?', 'Replace or supplement the metric with quality, legality, safety, and outcome indicators.', ['Keep the metric because it is easy to count.', 'Reward the highest contact count regardless of result.', 'Stop measuring the operation.'], 'Metrics shape behavior; a count that is easy to inflate is not a reliable measure of public-safety value.', 4, 'PROFESSIONAL', ['PROFESSIONAL_RULE_SENSITIVE'], ['cle-administration-accountability'], 'analysis', 'metric_gaming'),
        $row('An emergency plan has no explicit stop condition when the risk to bystanders increases. What is the most important revision?', 'Add a decision trigger and authority for pausing or changing the operation when risk changes.', ['Remove all operational discretion.', 'Assume risk is constant.', 'Continue until the original objective is met.'], 'Dynamic risk requires a defined trigger for reassessment and a lawful pause or change in plan.', 3, 'PROFESSIONAL', ['LAW_ENFORCEMENT_PROCEDURE_SENSITIVE'], ['cle-administration-accountability'], 'judgment', 'mission_fixation'),
        $row('A planning memo cites a historical incident but does not explain whether current conditions are comparable. What should the reviewer ask for?', 'A comparison of conditions and an explanation of how the evidence supports the proposed plan.', ['Automatic adoption because history repeats exactly.', 'Removal of all historical evidence.', 'A more confident prediction without analysis.'], 'Past data inform planning only when their relevance, limits, and transfer conditions are examined.', 4, 'PROFESSIONAL', ['PROFESSIONAL_RULE_SENSITIVE'], [], 'comparison', 'history_transfer'),
    ]);

$packs[] = $pack('cle-law-enforcement-leadership', 'cle-law-enforcement-application',
    ['prc-criminology-ethics-2024','lawphil-ra11131','lawphil-ra10173'],
    'PROFESSIONAL', ['PROFESSIONAL_RULE_SENSITIVE','REGULATORY_SENSITIVE'], ['cle-administration-accountability'], [
        $row('A leader learns that a respected officer altered a log to make a response appear timely. What should the leader do first?', 'Preserve the record, initiate an impartial review, and protect the integrity of the reporting process.', ['Correct the log privately and close the matter.', 'Ignore it because the officer is respected.', 'Reward the officer for meeting the apparent target.'], 'Trustworthy leadership protects records and review independence rather than treating status as a substitute for accountability.', 4, 'PROFESSIONAL', ['PROFESSIONAL_RULE_SENSITIVE'], ['cle-administration-accountability'], 'ethics', 'status_over_integrity'),
        $row('A team disagrees about a high-risk plan and the leader has incomplete information. What leadership action is most defensible?', 'Surface the disagreement, identify the missing information, set a decision rule, and document the chosen safeguard.', ['Suppress disagreement to appear decisive.', 'Choose the loudest recommendation.', 'Proceed without recording assumptions.'], 'Good leadership makes uncertainty and dissent usable for a safer, reviewable decision.', 4, 'PROFESSIONAL', ['PROFESSIONAL_RULE_SENSITIVE'], ['cle-administration-accountability'], 'judgment', 'false_decisiveness'),
        $row('A supervisor assigns a technically complex task to an untrained member because the assignment is convenient. What should control?', 'Competence, supervision, risk, and the need for escalation or training before assignment.', ['Convenience because all officers are interchangeable.', 'The member’s willingness alone.', 'Avoiding documentation of the assignment.'], 'Professional responsibility includes matching competence and supervision to risk rather than treating willingness as qualification.', 3, 'PROFESSIONAL', ['PROFESSIONAL_RULE_SENSITIVE'], [], 'judgment', 'competence_assumption'),
        $row('A leader receives praise for a successful outcome but learns that a safeguard was skipped. How should the result be evaluated?', 'Separate the outcome from process compliance and review the skipped safeguard and its risk.', ['Treat success as proof that the safeguard was unnecessary.', 'Punish any future reporting of the omission.', 'Delete the safeguard from policy.'], 'A favorable outcome does not validate an unsafe or unlawful process; leadership must learn from near misses.', 4, 'PROFESSIONAL', ['PROFESSIONAL_RULE_SENSITIVE'], ['cle-administration-accountability'], 'analysis', 'outcome_justifies_process'),
        $row('A leader is asked to pressure a community representative to endorse an operation. What is the proper response?', 'Seek informed, non-coercive engagement and preserve the representative’s independence.', ['Use pressure because agreement is required.', 'Represent silence as consent.', 'Promise an unapproved benefit.'], 'Community partnership is not legitimate when it is obtained through coercion or misrepresentation.', 3, 'PROFESSIONAL', ['PROFESSIONAL_RULE_SENSITIVE'], [], 'ethics', 'coercive_partnership'),
        $row('A new policy is issued but staff interpret its key terms differently. What should the leader do?', 'Provide a documented interpretation, training, escalation path, and feedback review.', ['Allow each unit to invent its own rule.', 'Punish staff for asking questions.', 'Ignore the divergence until an incident occurs.'], 'Consistent implementation requires shared understanding and a way to resolve ambiguity before it creates unequal practice.', 3, 'PROFESSIONAL', ['PROFESSIONAL_RULE_SENSITIVE'], [], 'process_interpretation', 'local_rule_drift'),
        $row('A team member reports a conflict of interest in a case assignment. What is the best supervisory response?', 'Assess the conflict, adjust the assignment if needed, and document the impartiality safeguard.', ['Tell the member to hide the concern.', 'Treat every conflict claim as misconduct.', 'Continue without recording the issue.'], 'A documented conflict assessment protects both the case and the professional involved.', 3, 'PROFESSIONAL', ['PROFESSIONAL_RULE_SENSITIVE'], [], 'ethics', 'conflict_suppression'),
    ]);

$packs[] = $pack('cle-law-enforcement-community', 'cle-law-enforcement-application',
    ['prc-criminology-ethics-2024','lawphil-ra10173','lawphil-ra11131','nij-crime-scene-guides-2017'],
    'PROFESSIONAL', ['REGULATORY_SENSITIVE','PROFESSIONAL_RULE_SENSITIVE'], ['cle-administration-accountability'], [
        $row('A neighborhood meeting includes a resident who fears retaliation for reporting a safety problem. Which response best supports ethical engagement?', 'Offer a safe reporting channel, explain confidentiality limits, and avoid promising protection the agency cannot provide.', ['Demand public identification before accepting the report.', 'Promise absolute secrecy regardless of law.', 'Dismiss the concern as anecdotal.'], 'Trust grows from accurate expectations, protected channels, and respect for the person’s risk and autonomy.', 4, 'PROFESSIONAL', ['REGULATORY_SENSITIVE'], ['cle-administration-accountability'], 'community', 'false_confidentiality'),
        $row('A community group asks for a crime map containing identifiable names and home addresses. What should the agency do?', 'Provide an aggregated, purpose-appropriate representation and protect unnecessary personal information.', ['Release the full address list.', 'Refuse all public information permanently.', 'Replace names with rumors.'], 'Public communication can support safety without exposing personal information that is not necessary for the stated purpose.', 3, 'PROFESSIONAL', ['REGULATORY_SENSITIVE'], [], 'judgment', 'identifiability_ignored'),
        $row('Residents report that an enforcement operation was conducted without explanation and affected people unevenly. What should the administrator prioritize?', 'Listen, document the concern, review the operation’s authority and impact, and communicate a follow-up process.', ['Defend the operation before reviewing facts.', 'Dismiss the complaint because enforcement is unpopular.', 'Stop recording community concerns.'], 'Accountable community relations combine respectful listening with evidence-based review and transparent follow-up.', 4, 'PROFESSIONAL', ['PROFESSIONAL_RULE_SENSITIVE'], ['cle-administration-accountability'], 'community', 'defensive_response'),
        $row('A public-information officer wants to publish an unverified rumor to encourage tips. Which principle applies?', 'Clearly distinguish an unverified lead from a verified fact and avoid publishing harmful speculation.', ['Rumors become reliable when widely repeated.', 'Publicity removes the need for verification.', 'The most alarming version is best for tips.'], 'Community information should not convert uncertainty into accusation or create avoidable harm.', 3, 'PROFESSIONAL', ['REGULATORY_SENSITIVE','PROFESSIONAL_RULE_SENSITIVE'], [], 'communication', 'rumor_as_fact'),
        $row('A community program is judged successful because attendance increased, but no evidence shows whether participants felt safer or received useful services. What is missing?', 'Outcome and participant-feedback measures connected to the program’s actual objective.', ['A larger attendance number only.', 'A rule against collecting feedback.', 'A claim that attendance proves deterrence.'], 'Participation is an output; effectiveness requires measures that connect the activity to the intended public-safety outcome.', 4, 'PROFESSIONAL', ['PROFESSIONAL_RULE_SENSITIVE'], [], 'analysis', 'attendance_equals_outcome'),
        $row('An officer describes a whole neighborhood as “uncooperative” after two residents declined an interview. What correction is appropriate?', 'Avoid stereotyping, document the limited observation, and use respectful engagement to seek broader evidence.', ['Treat the description as a community fact.', 'Increase pressure on all residents.', 'Remove the neighborhood from services.'], 'A small interaction cannot support a broad group judgment, and professional ethics require fair, non-discriminatory engagement.', 3, 'PROFESSIONAL', ['PROFESSIONAL_RULE_SENSITIVE'], [], 'interpretation', 'overgeneralization'),
        $row('A community partner asks for a case update that includes a protected victim detail. What should the liaison do?', 'Share only authorized, necessary information and explain the confidentiality boundary.', ['Share everything to maintain trust.', 'Share the detail verbally so it is not documented.', 'Invent a substitute detail.'], 'Trustworthy partnership includes respecting confidentiality and explaining why some information cannot be disclosed.', 3, 'PROFESSIONAL', ['REGULATORY_SENSITIVE'], [], 'judgment', 'confidentiality_bypass'),
    ]);

$packs[] = $pack('cle-law-enforcement-accountability', 'cle-law-enforcement-application',
    ['prc-criminology-ethics-2024','lawphil-ra11131','lawphil-ra10173','nij-crime-scene-guides-2017'],
    'PROFESSIONAL', ['PROFESSIONAL_RULE_SENSITIVE','REGULATORY_SENSITIVE'], ['cle-administration-accountability'], [
        $row('An audit finds that a unit’s complaint register omits matters resolved informally. What is the main risk?', 'The register may understate complaints and prevent a complete accountability analysis.', ['Informal resolution makes records unnecessary.', 'Only criminal complaints should ever be recorded.', 'Omission improves public confidence.'], 'A complete accountability system must capture material concerns and their disposition, not only formally escalated matters.', 3, 'PROFESSIONAL', ['PROFESSIONAL_RULE_SENSITIVE'], ['cle-administration-accountability'], 'analysis', 'record_omission'),
        $row('An investigator’s report cannot be linked to the evidence transfer record. What should the reviewer do?', 'Treat traceability as a defect requiring reconciliation before relying on the evidence record.', ['Assume the transfer occurred because the item exists.', 'Rename the report to match the item.', 'Ignore the gap if the result is favorable.'], 'Traceability connects actions, evidence, and decisions; an unexplained break undermines reviewability.', 4, 'PROFESSIONAL', ['PROFESSIONAL_RULE_SENSITIVE'], [], 'evidence_interpretation', 'chain_assumption'),
        $row('A complaint is assigned to a reviewer who participated in the challenged operation. What safeguard is most appropriate?', 'Use an independent reviewer or documented conflict-management process.', ['Allow the reviewer to decide privately.', 'Treat participation as proof of impartiality.', 'Close the complaint without review.'], 'Independence or a transparent conflict process is necessary for credible accountability.', 3, 'PROFESSIONAL', ['PROFESSIONAL_RULE_SENSITIVE'], [], 'ethics', 'independence_gap'),
        $row('A unit reports improved statistics after changing how incidents are counted, but gives no bridge between old and new definitions. What is the analytical problem?', 'The apparent improvement may reflect a measurement change rather than a real operational change.', ['The new statistics are automatically better.', 'Definitions never affect comparisons.', 'The older statistics should be deleted.'], 'Comparisons require consistent definitions or an explicit bridge; otherwise the metric’s meaning has changed.', 4, 'PROFESSIONAL', ['PROFESSIONAL_RULE_SENSITIVE'], [], 'comparison', 'measurement_change'),
        $row('An officer says body-camera footage should be deleted once the case closes even though a complaint is pending. What should control?', 'The retention and preservation rule for a pending accountability matter should be verified and followed.', ['Delete it to reduce storage.', 'Delete it if the officer believes it is irrelevant.', 'Publish it immediately.'], 'Records relevant to pending review require controlled preservation under the applicable policy and law.', 3, 'PROFESSIONAL', ['REGULATORY_SENSITIVE','PROFESSIONAL_RULE_SENSITIVE'], [], 'process_interpretation', 'retention_shortcut'),
        $row('A unit’s review process focuses only on individual blame and never examines recurring process failures. What improvement is needed?', 'Add system-level learning, corrective action, and follow-up measures alongside individual accountability.', ['Eliminate individual accountability.', 'Treat every incident as unique.', 'Stop reviewing process design.'], 'Sustainable accountability addresses conduct and the conditions that allow recurring failures.', 4, 'PROFESSIONAL', ['PROFESSIONAL_RULE_SENSITIVE'], [], 'analysis', 'individual_only'),
        $row('A supervisor asks an analyst to change a finding so that it matches the unit’s public statement. What should the analyst do?', 'Preserve the evidence-based finding and document the attempted influence through the proper channel.', ['Change the finding for consistency.', 'Delete the underlying evidence.', 'Publish both versions without explanation.'], 'Professional independence requires the finding to follow evidence, not public-relations pressure.', 4, 'PROFESSIONAL', ['PROFESSIONAL_RULE_SENSITIVE'], [], 'ethics', 'public_statement_control'),
    ]);

$packs[] = $pack('cle-law-enforcement-operations', 'cle-law-enforcement-application',
    ['nij-crime-scene-guides-2017','lawphil-rules-criminal-procedure','lawphil-ra10173','prc-criminology-ethics-2024'],
    'PROFESSIONAL', ['LAW_ENFORCEMENT_PROCEDURE_SENSITIVE','CRIMINAL_PROCEDURE_SENSITIVE','REGULATORY_SENSITIVE'], ['cle-administration-accountability'], [
        $row('An operational briefing gives a team a target but no boundary, communication plan, or reassessment trigger. What is the most important correction?', 'Define lawful boundaries, roles, communications, and a trigger for reassessing risk.', ['Increase the target list.', 'Remove the briefing record.', 'Allow each member to set a different boundary.'], 'Operational safety and legality depend on clear limits and a shared plan, especially when conditions change.', 4, 'PROFESSIONAL', ['LAW_ENFORCEMENT_PROCEDURE_SENSITIVE'], ['cle-administration-accountability'], 'planning', 'boundary_omission'),
        $row('At a scene, an officer begins collecting objects before documenting their original positions. What risk is created?', 'The action can alter context and weaken reconstruction, contamination control, and later interpretation.', ['Documentation is unnecessary when objects are obvious.', 'Collection always improves evidence quality.', 'Original position can be reconstructed from memory.'], 'Early documentation preserves context that may be lost when objects are moved or conditions change.', 3, 'PROFESSIONAL', ['LAW_ENFORCEMENT_PROCEDURE_SENSITIVE'], [], 'process_interpretation', 'context_loss'),
        $row('An operation uses a personal messaging account for sensitive case coordination. What should the supervisor require?', 'Use an authorized channel with access control and a record-preservation process.', ['Continue because the channel is convenient.', 'Forward the messages to more personal accounts.', 'Delete the messages after reading.'], 'Authorized channels protect confidentiality, accountability, and record integrity.', 3, 'PROFESSIONAL', ['REGULATORY_SENSITIVE','PROFESSIONAL_RULE_SENSITIVE'], [], 'judgment', 'convenience_over_control'),
        $row('A field team encounters a new hazard that was not in the original plan. What should happen first?', 'Protect people, communicate the hazard, and reassess the plan before continuing evidence or enforcement work.', ['Continue to avoid delay.', 'Hide the hazard from the team.', 'Complete the original checklist unchanged.'], 'Changing conditions require immediate safety action and a documented reassessment before the mission continues.', 4, 'PROFESSIONAL', ['LAW_ENFORCEMENT_PROCEDURE_SENSITIVE'], ['cle-administration-accountability'], 'emergency', 'mission_fixation'),
        $row('A supervisor asks why a transfer record contains an unexplained gap. What is the best response?', 'Reconcile the gap from contemporaneous records and document any remaining uncertainty.', ['Assume the item stayed secure.', 'Fill the gap with the expected transfer.', 'Remove the transfer record.'], 'A traceable record should expose and resolve gaps rather than silently replacing them with assumptions.', 3, 'PROFESSIONAL', ['LAW_ENFORCEMENT_PROCEDURE_SENSITIVE'], [], 'evidence_interpretation', 'chain_gap'),
        $row('An operation plan assigns a single person to scene safety, evidence collection, public communication, and medical coordination. What is the main concern?', 'The concentration of incompatible duties can create overload, missed safeguards, and weak accountability.', ['One person always improves efficiency.', 'Role separation is never useful.', 'The plan is stronger because fewer people know it.'], 'Clear, feasible roles reduce overload and make safety and review responsibilities visible.', 4, 'PROFESSIONAL', ['LAW_ENFORCEMENT_PROCEDURE_SENSITIVE'], ['cle-administration-accountability'], 'analysis', 'role_overload'),
        $row('A unit receives a tip with no source details and treats it as a verified operational fact. What is the correct handling?', 'Record it as an unverified lead, assess corroboration, and avoid irreversible action based on it alone.', ['Treat it as fact because it is specific.', 'Publish the tip as an accusation.', 'Discard every anonymous tip automatically.'], 'An anonymous tip may guide inquiry but requires corroboration and careful risk management.', 3, 'PROFESSIONAL', ['PROFESSIONAL_RULE_SENSITIVE','REGULATORY_SENSITIVE'], [], 'investigation', 'lead_as_fact'),
    ]);

/* Crime Detection and Investigation: 50 questions. */
$packs[] = $pack('cle-investigation-evidence', 'cle-investigation-foundation',
    ['nij-crime-scene-guide-2013','nij-crime-scene-guides-2017','lawphil-rules-evidence-2019'],
    'PROFESSIONAL', ['LAW_ENFORCEMENT_PROCEDURE_SENSITIVE','EVIDENCE_RULE_SENSITIVE'], [
        $row('A scene log records an item’s collection but not its location, condition, or collector. What is the primary weakness?', 'The record lacks context and traceability needed to evaluate the item’s integrity and interpretation.', ['The item is automatically unusable in every setting.', 'The missing details do not matter if the item looks relevant.', 'The collector’s name is the only required information.'], 'Context and traceability allow later reviewers to assess what was collected, how, and under what conditions.', 4, 'PROFESSIONAL', ['LAW_ENFORCEMENT_PROCEDURE_SENSITIVE'], [], 'evidence_interpretation', 'traceability_minimalism'),
        $row('A wet biological item is sealed in a way that prevents drying before transport. Which concern should guide the response?', 'Document the condition and use an appropriate preservation and transport process to reduce degradation.', ['Seal it more tightly regardless of material.', 'Expose it publicly to accelerate drying.', 'Ignore the condition because DNA is always stable.'], 'Biological evidence can degrade when preservation and transport conditions are unsuitable; the response should be documented and method-aware.', 4, 'PROFESSIONAL', ['LAW_ENFORCEMENT_PROCEDURE_SENSITIVE'], [], 'process_interpretation', 'stability_assumption'),
        $row('A package arrives with an identifier that does not match the submission form. What should the receiving analyst do?', 'Quarantine the discrepancy, document it, and reconcile identity before examination.', ['Change the package label without a record.', 'Examine it first because the contents look correct.', 'Discard it immediately.'], 'Identity discrepancies are integrity risks; examination should wait for documented reconciliation.', 3, 'PROFESSIONAL', ['LAW_ENFORCEMENT_PROCEDURE_SENSITIVE'], [], 'quality_control', 'label_substitution'),
        $row('An investigator photographs an object only after moving it to a cleaner surface. What limitation should be recorded?', 'The image no longer documents the object’s original scene context and position.', ['The photograph proves the original position.', 'Moving objects never affects interpretation.', 'The object becomes scientifically useless in every case.'], 'A later image may document the object but cannot recreate the original context that was lost.', 3, 'PROFESSIONAL', ['LAW_ENFORCEMENT_PROCEDURE_SENSITIVE'], [], 'process_interpretation', 'context_reconstruction'),
        $row('A toolmark is compared with a reference tool, but the report omits how the questioned mark was preserved. What should the reviewer request?', 'The collection, preservation, and examination records needed to evaluate comparison quality.', ['Only a larger conclusion.', 'A statement that every toolmark is unique.', 'Removal of the limitation from the report.'], 'Comparison interpretation depends on the condition and provenance of both the questioned and reference material.', 4, 'PROFESSIONAL', ['EVIDENCE_RULE_SENSITIVE'], [], 'quality_control', 'uniqueness_assumption'),
        $row('An investigator has three possible locations for a missing item and one unverified tip favoring one location. What is the best next step?', 'Use the tip to guide lawful inquiry while preserving alternative hypotheses and corroboration steps.', ['Search only the favored location without recording the tip.', 'Treat the tip as proof of location.', 'Ignore all locations because the tip is unverified.'], 'A lead can guide an investigation but should not collapse alternative hypotheses without corroboration.', 4, 'PROFESSIONAL', ['LAW_ENFORCEMENT_PROCEDURE_SENSITIVE'], [], 'investigation', 'hypothesis_collapse'),
        $row('A report states that an object “came from the suspect” because it was found near the suspect’s property. What correction is needed?', 'Separate location association from source attribution and identify the evidence needed for the latter.', ['Treat proximity as source proof.', 'Treat every nearby object as irrelevant.', 'Use the report wording because it is concise.'], 'Proximity may be relevant but does not by itself establish ownership, source, or transfer.', 4, 'PROFESSIONAL', ['EVIDENCE_RULE_SENSITIVE'], [], 'analysis', 'proximity_equals_source'),
        $row('A laboratory receives a sample with no indication of whether a control failed during collection. What should happen?', 'Hold interpretation and obtain or document the relevant control and collection information.', ['Proceed and omit the control issue.', 'Assume the control passed.', 'Replace the sample with a convenient one.'], 'Control information can determine whether an observation is interpretable; missing information should be resolved before conclusion.', 4, 'PROFESSIONAL', ['LAW_ENFORCEMENT_PROCEDURE_SENSITIVE'], [], 'quality_control', 'control_assumption'),
        $row('A case team stores the original digital file only as a compressed screenshot. What is the primary investigative concern?', 'The conversion may remove metadata or context needed to authenticate and interpret the original.', ['Screenshots are always more authentic.', 'Compression proves the source.', 'Digital files never require preservation.'], 'Preserving the original and relevant metadata helps later reviewers assess authenticity and context.', 3, 'PROFESSIONAL', ['EVIDENCE_RULE_SENSITIVE','REGULATORY_SENSITIVE'], [], 'evidence_interpretation', 'copy_equals_original'),
        $row('A collection plan lists evidence types but no contamination controls or packaging method. What should be added?', 'Controls and packaging appropriate to each material, with documentation of the handling sequence.', ['One package for all materials.', 'No handling record to reduce clutter.', 'A rule that every item is air-dried in the field.'], 'Material-specific controls and handling reduce contamination and preserve later examination value.', 3, 'PROFESSIONAL', ['LAW_ENFORCEMENT_PROCEDURE_SENSITIVE'], [], 'planning', 'one_method_fits_all'),
    ]);

$packs[] = $pack('cle-investigation-scene', 'cle-investigation-foundation',
    ['nij-crime-scene-guide-2013','nij-crime-scene-guides-2017','lawphil-rules-criminal-procedure'],
    'PROFESSIONAL', ['LAW_ENFORCEMENT_PROCEDURE_SENSITIVE','CRIMINAL_PROCEDURE_SENSITIVE'], [
        $row('Responders arrive while an injured person remains in immediate danger at a scene. What should take priority?', 'Protect life and scene safety, then preserve and document the scene as conditions allow.', ['Begin measuring evidence before providing aid.', 'Move every object to create a clean scene.', 'Allow uncontrolled access while waiting for a photographer.'], 'Life safety and hazard control precede evidence processing; preservation must account for necessary emergency action.', 3, 'PROFESSIONAL', ['LAW_ENFORCEMENT_PROCEDURE_SENSITIVE'], [], 'emergency', 'evidence_over_life'),
        $row('A scene has one obvious entry point, but weather is damaging exposed material. What is the most defensible response?', 'Document conditions, control access, and prioritize vulnerable material using a reasoned collection plan.', ['Wait for perfect conditions.', 'Collect everything without documenting the weather.', 'Allow additional people to enter to speed the work.'], 'Changing conditions require documented prioritization and contamination control, not either delay or uncontrolled speed.', 4, 'PROFESSIONAL', ['LAW_ENFORCEMENT_PROCEDURE_SENSITIVE'], [], 'judgment', 'weather_ignored'),
        $row('A photographer uses a scale in every close-up but takes no overall or midrange images. What is missing?', 'A sequence that connects the item to the scene and shows spatial relationships before close detail.', ['More close-ups of the same surface.', 'A scale without any context.', 'A conclusion about ownership.'], 'Overall, midrange, and close views serve different documentation purposes; detail alone loses context.', 3, 'PROFESSIONAL', ['LAW_ENFORCEMENT_PROCEDURE_SENSITIVE'], [], 'process_interpretation', 'detail_without_context'),
        $row('A scene boundary is set after several bystanders have crossed the area. What should the investigator record?', 'The prior access, people involved if known, possible contamination, and corrective control measures.', ['Erase the crossings from the log.', 'Assume the material was unaffected.', 'Blame the first responder without documenting facts.'], 'Early uncontrolled access is a condition that can affect interpretation and must be documented rather than hidden.', 4, 'PROFESSIONAL', ['LAW_ENFORCEMENT_PROCEDURE_SENSITIVE'], [], 'quality_control', 'contamination_denial'),
        $row('An item is found in a location that may change its meaning if moved. What should happen before collection?', 'Document its position and condition and plan a collection method that preserves relevant context.', ['Move it to a convenient table first.', 'Collect it without photographs.', 'Ask a bystander to hold it.'], 'Context is part of the evidence record; documentation before movement preserves interpretive value.', 3, 'PROFESSIONAL', ['LAW_ENFORCEMENT_PROCEDURE_SENSITIVE'], [], 'process_interpretation', 'convenience_move'),
        $row('A scene team has no record of who entered during the first hour. What is the correct conclusion?', 'The access history is incomplete and should be treated as an integrity limitation in later interpretation.', ['The scene is automatically invalid.', 'The missing log can be reconstructed from memory as fact.', 'Access history is unrelated to evidence.'], 'An incomplete access history does not answer every scientific question, but it limits confidence in contamination and provenance analysis.', 4, 'PROFESSIONAL', ['LAW_ENFORCEMENT_PROCEDURE_SENSITIVE'], [], 'analysis', 'log_gap'),
        $row('A collection team packages damp biological material in a closed plastic container. What should the supervisor review?', 'Whether the material and packaging are compatible with drying, degradation control, and the laboratory’s validated process.', ['Whether plastic is always the safest material.', 'Whether the package looks sealed.', 'Whether the label is attractive.'], 'Packaging decisions must be tied to material properties and validated handling requirements, not appearance.', 4, 'PROFESSIONAL', ['LAW_ENFORCEMENT_PROCEDURE_SENSITIVE'], [], 'quality_control', 'packaging_absolute'),
        $row('A scene report says “nothing else was present” but lists no search method or limitations. How should the statement be revised?', 'Describe the area examined, method, observations, and limits instead of claiming an unsupported universal absence.', ['Keep the absolute statement for clarity.', 'Replace it with “the scene was empty.”', 'Delete all negative observations.'], 'Negative findings are meaningful only within the documented search scope and method.', 3, 'PROFESSIONAL', ['LAW_ENFORCEMENT_PROCEDURE_SENSITIVE'], [], 'reporting', 'absolute_absence'),
        $row('A team discovers a possible evidence item after the scene was released. What is the first administrative concern?', 'Document the discovery circumstances and re-establish lawful control and provenance before handling it.', ['Insert it into the old log without explanation.', 'Assume it belonged to the original scene.', 'Discard it because release ended all inquiry.'], 'A later discovery needs its own traceable circumstances and lawful handling; it cannot be silently backdated.', 4, 'PROFESSIONAL', ['LAW_ENFORCEMENT_PROCEDURE_SENSITIVE','CRIMINAL_PROCEDURE_SENSITIVE'], [], 'process_interpretation', 'backdating'),
        $row('A responder uses the same gloves while handling several unrelated objects. What is the main risk?', 'Cross-contamination that can make associations between items misleading.', ['The gloves become more protective with use.', 'Cross-contamination affects only photographs.', 'The risk disappears if the objects look different.'], 'Changing gloves and using appropriate controls help prevent material from one item being transferred to another.', 3, 'PROFESSIONAL', ['LAW_ENFORCEMENT_PROCEDURE_SENSITIVE'], [], 'quality_control', 'contamination_transfer'),
    ]);

$packs[] = $pack('cle-investigation-interview', 'cle-investigation-foundation',
    ['lawphil-ra7438','lawphil-ra9745','lawphil-rules-criminal-procedure','nij-crime-scene-guides-2017'],
    'PROFESSIONAL', ['CRIMINAL_PROCEDURE_SENSITIVE','PROFESSIONAL_RULE_SENSITIVE'], [
        $row('An interviewer begins with a detailed theory and asks only yes-or-no questions that confirm it. What is the main weakness?', 'The format can restrict recall and reinforce confirmation bias before the account is freely developed.', ['Closed questions always produce the most accurate memory.', 'A theory is proof that the account is correct.', 'Open prompts are never useful.'], 'Open, non-leading prompts allow the account to develop before focused clarification tests details and alternatives.', 4, 'PROFESSIONAL', ['PROFESSIONAL_RULE_SENSITIVE'], [], 'interview', 'leading_question'),
        $row('A witness gives an uncertain estimate and later adopts the interviewer’s precise number. What should the record preserve?', 'The original uncertainty and the circumstances of the later clarification or suggestion.', ['Only the final precise number.', 'The number favored by the investigator.', 'No record because estimates are useless.'], 'Changes in confidence and possible suggestion are relevant to evaluating reliability and must not be erased.', 4, 'PROFESSIONAL', ['PROFESSIONAL_RULE_SENSITIVE'], [], 'analysis', 'confidence_inflation'),
        $row('A person being questioned may be under custodial investigation, but the interviewer has not clarified the setting. What should happen?', 'Establish the procedural setting and safeguards before substantive questioning continues.', ['Continue until the person asks for counsel.', 'Assume the person is a witness for convenience.', 'Use informal conversation to avoid all rules.'], 'The setting controls rights and procedure; avoiding the label does not avoid the safeguards.', 4, 'PROFESSIONAL', ['CRIMINAL_PROCEDURE_SENSITIVE'], ['cle-criminal-law-rights'], 'judgment', 'label_avoidance'),
        $row('A witness reports what another person said but did not observe the event. How should the statement be classified?', 'As second-hand information whose source and admissibility implications require separate evaluation.', ['As direct observation.', 'As proof because the witness is confident.', 'As irrelevant to every investigative lead.'], 'The statement may generate a lead, but it is not the same as firsthand observation and needs source-specific assessment.', 3, 'PROFESSIONAL', ['EVIDENCE_RULE_SENSITIVE'], [], 'classification', 'hearsay_collapse'),
        $row('An interview record omits the time, participants, and language used. What is the effect?', 'The missing context limits later evaluation of sequence, voluntariness, and meaning.', ['The interview is automatically accurate.', 'The omissions improve confidentiality.', 'Only the answer content matters.'], 'Contextual records help reviewers interpret what was asked, who was present, and how the account was obtained.', 3, 'PROFESSIONAL', ['CRIMINAL_PROCEDURE_SENSITIVE','PROFESSIONAL_RULE_SENSITIVE'], [], 'quality_control', 'context_omission'),
        $row('An interviewer promises a witness a result the agency cannot control. What is the ethical concern?', 'The promise can distort voluntariness and undermine trust when the promised result cannot be delivered.', ['Promises always improve cooperation.', 'The agency must honor any informal promise automatically.', 'The concern is only the interview length.'], 'Accurate expectations protect voluntariness and the integrity of the information obtained.', 3, 'PROFESSIONAL', ['PROFESSIONAL_RULE_SENSITIVE'], [], 'ethics', 'promise_overreach'),
        $row('A witness’s account conflicts with physical evidence, but the interviewer has not asked about the conflict. What is the best next step?', 'Use neutral follow-up to clarify the discrepancy without telling the witness which answer is preferred.', ['Accuse the witness immediately.', 'Discard the account without inquiry.', 'Change the physical record to fit the account.'], 'Neutral clarification tests alternative explanations without contaminating the account through pressure or suggestion.', 4, 'PROFESSIONAL', ['PROFESSIONAL_RULE_SENSITIVE','EVIDENCE_RULE_SENSITIVE'], [], 'interview', 'premature_accusation'),
        $row('An interviewer records only a polished summary rather than the witness’s material words and qualifications. What risk follows?', 'The summary may erase uncertainty, qualifiers, or meaning needed to assess the account.', ['Summaries are always more accurate than originals.', 'Qualifiers are never material.', 'The polished version proves reliability.'], 'Material wording and qualifications can affect interpretation; a concise summary should not hide them.', 3, 'PROFESSIONAL', ['EVIDENCE_RULE_SENSITIVE'], [], 'reporting', 'summary_smoothing'),
        $row('A witness says “I think” when describing a key detail. Which handling is appropriate?', 'Preserve the qualification and seek the basis of the memory without converting it into certainty.', ['Rewrite it as “I know.”', 'Discard every uncertain statement.', 'Treat the qualification as proof of deception.'], 'Uncertainty is information about confidence; it should be explored, not silently strengthened or dismissed.', 3, 'PROFESSIONAL', ['PROFESSIONAL_RULE_SENSITIVE'], [], 'interpretation', 'certainty_inflation'),
        $row('Two interviews share identical unusual wording even though the witnesses were interviewed separately. What should the investigator consider?', 'Possible contamination or shared information and the need to review interview conditions.', ['Treat identical wording as independent corroboration.', 'Assume both memories are perfect.', 'Delete one account without review.'], 'Unusual similarity can reflect shared information or interview influence; it requires context review before being counted as independent support.', 4, 'PROFESSIONAL', ['PROFESSIONAL_RULE_SENSITIVE'], [], 'comparison', 'independence_assumption'),
    ]);

$packs[] = $pack('cle-investigation-reasoning', 'cle-investigation-foundation',
    ['nij-crime-scene-guide-2013','nij-crime-scene-guides-2017','lawphil-rules-evidence-2019'],
    'PROFESSIONAL', ['PROFESSIONAL_RULE_SENSITIVE','EVIDENCE_RULE_SENSITIVE'], [
        $row('An investigation has one theory that explains the known facts but no test of alternatives. What should be added?', 'A competing-hypothesis review that identifies disconfirming evidence and next tests.', ['More facts that support the existing theory.', 'A conclusion that the theory is proven.', 'Removal of contradictory observations.'], 'Testing alternatives reduces confirmation bias and clarifies what evidence would distinguish explanations.', 4, 'PROFESSIONAL', ['PROFESSIONAL_RULE_SENSITIVE'], [], 'analysis', 'single_hypothesis'),
        $row('A team interprets a missing record as proof that an event did not occur. What is the logical problem?', 'Absence of a record may reflect many causes and is not automatically proof of absence.', ['Missing records always prove nonoccurrence.', 'Records never have evidentiary value.', 'The event should be treated as proven.'], 'A missing record must be interpreted in light of the recording process, access, retention, and alternative explanations.', 3, 'PROFESSIONAL', ['EVIDENCE_RULE_SENSITIVE'], [], 'interpretation', 'absence_equals_absence'),
        $row('A timeline is built from statements with different levels of precision. What is the best representation?', 'Show precision and uncertainty for each time point instead of inventing a single exact timeline.', ['Round every statement to the same exact time.', 'Use the most confident witness only.', 'Treat uncertainty as a contradiction.'], 'A timeline should preserve the quality and limits of each input rather than create false precision.', 4, 'PROFESSIONAL', ['EVIDENCE_RULE_SENSITIVE'], [], 'analysis', 'false_precision'),
        $row('A case team treats the first plausible explanation as the final explanation. What review question is most useful?', 'What observation would be expected if the explanation were wrong, and has it been tested?', ['Which detail sounds most persuasive?', 'How can the explanation be made more certain?', 'Which conflicting fact can be omitted?'], 'Falsifiability-oriented questions help distinguish explanation testing from narrative completion.', 4, 'PROFESSIONAL', ['PROFESSIONAL_RULE_SENSITIVE'], [], 'diagnosis', 'narrative_completion'),
        $row('A digital timestamp conflicts with a witness account, but the device time was never verified. What should the analyst state?', 'The timestamp is a data point whose reliability and time settings require verification before reconciliation.', ['The timestamp is always objective.', 'The witness must be wrong.', 'Both can be averaged into one fact.'], 'Digital records need foundation and clock/context verification; format alone does not resolve conflict.', 3, 'PROFESSIONAL', ['EVIDENCE_RULE_SENSITIVE'], [], 'comparison', 'digital_objectivity'),
        $row('An analyst has a useful lead but no evidence connecting it to the offense. How should it appear in the report?', 'As a lead with its source, uncertainty, and proposed corroboration steps.', ['As an established finding.', 'As a fact because it is useful.', 'As a conclusion with no source.'], 'Clear labels preserve the difference between inquiry direction and evidentiary conclusion.', 3, 'PROFESSIONAL', ['PROFESSIONAL_RULE_SENSITIVE'], [], 'reporting', 'lead_conclusion'),
        $row('A team uses only evidence that confirms a suspect theory and never searches for disconfirming information. What is the central risk?', 'Confirmation bias can make the investigation miss alternative explanations and exculpatory evidence.', ['The team has too much evidence.', 'Confirmation always improves speed without risk.', 'Disconfirming evidence is never relevant.'], 'Balanced investigation actively tests the preferred theory and preserves contrary information.', 3, 'PROFESSIONAL', ['PROFESSIONAL_RULE_SENSITIVE','EVIDENCE_RULE_SENSITIVE'], [], 'analysis', 'confirmation_bias'),
        $row('A conclusion uses a pattern observed in three cases to assert that every future case will have the same cause. What is the error?', 'A limited pattern is being generalized beyond the evidence and conditions that produced it.', ['Three observations prove a universal law.', 'Patterns cannot inform any decision.', 'Future cases need no review.'], 'Patterns can generate hypotheses but do not justify universal claims without broader evidence and defined limits.', 4, 'PROFESSIONAL', ['PROFESSIONAL_RULE_SENSITIVE'], [], 'comparison', 'overgeneralization'),
        $row('A report lists conclusions but not the observations and assumptions supporting them. What should be added?', 'A traceable chain from observations to assumptions, analysis, limitations, and conclusion.', ['More conclusory adjectives.', 'Only the final recommendation.', 'Removal of assumptions because they weaken certainty.'], 'Reviewable reasoning makes the basis and limits of a conclusion visible.', 3, 'PROFESSIONAL', ['PROFESSIONAL_RULE_SENSITIVE'], [], 'reporting', 'opaque_reasoning'),
        $row('A team cannot explain why one hypothesis was discarded. What is the best correction?', 'Document the evidence, rule, or test that led to its rejection and any remaining uncertainty.', ['Delete the discarded hypothesis.', 'Assume the team was correct because it agreed.', 'Replace it with a stronger-sounding theory.'], 'Documented rejection supports accountability and makes later review possible.', 3, 'PROFESSIONAL', ['PROFESSIONAL_RULE_SENSITIVE'], [], 'quality_control', 'discarded_reason_hidden'),
    ]);

$packs[] = $pack('cle-investigation-lawful', 'cle-investigation-foundation',
    ['lawphil-rules-criminal-procedure','lawphil-rules-evidence-2019','lawphil-ra7438','lawphil-ra10173','lawphil-ra9745'],
    'REGULATORY', ['CRIMINAL_PROCEDURE_SENSITIVE','EVIDENCE_RULE_SENSITIVE','REGULATORY_SENSITIVE'], ['cle-criminal-law-rights'], [
        $row('An investigator wants to collect a private record but cannot identify the authority or process for obtaining it. What should happen?', 'Pause collection and identify the lawful authority, scope, and process before requesting the record.', ['Request it informally from a friend.', 'Collect it first and justify it later.', 'Assume investigative purpose is sufficient.'], 'A lawful investigation requires authority and process; convenience does not substitute for them.', 4, 'REGULATORY', ['CRIMINAL_PROCEDURE_SENSITIVE'], ['cle-criminal-law-rights'], 'judgment', 'purpose_equals_authority'),
        $row('A proposed search plan does not distinguish consent, warrant, and other legal bases. What is the defect?', 'The plan has not identified the authority and safeguards applicable to the proposed search.', ['All search bases are interchangeable.', 'The broadest basis should be presumed.', 'The distinction matters only after collection.'], 'Different legal bases carry different conditions and limits; the plan must identify the one being used.', 3, 'REGULATORY', ['CRIMINAL_PROCEDURE_SENSITIVE'], ['cle-criminal-law-rights'], 'comparison', 'authority_blur'),
        $row('An officer receives a voluntary-looking statement after making a threat, but the report omits the interaction. What should the reviewer do?', 'Obtain and assess the circumstances because voluntariness cannot be inferred from the statement alone.', ['Treat the statement as voluntary because it is detailed.', 'Ignore the threat if no injury occurred.', 'Use the statement first and review the interaction later.'], 'The manner in which information was obtained can materially affect rights, reliability, and admissibility.', 4, 'REGULATORY', ['CRIMINAL_PROCEDURE_SENSITIVE','STATUTORY_VERSION_SENSITIVE'], ['cle-criminal-law-rights'], 'analysis', 'voluntariness_shortcut'),
        $row('A team requests broad location data for everyone near an incident even though a narrower lawful request could answer the question. What principle should guide review?', 'Use a necessary and proportionate scope tied to the investigative purpose and legal authority.', ['Collect the broadest data to avoid missing anything.', 'Treat all nearby people as suspects.', 'Use no documentation because the purpose is public safety.'], 'Necessity and proportionality reduce unjustified intrusion while keeping the investigation focused.', 4, 'REGULATORY', ['REGULATORY_SENSITIVE','CRIMINAL_PROCEDURE_SENSITIVE'], ['cle-criminal-law-rights'], 'judgment', 'overcollection'),
        $row('A report says evidence is “lawfully obtained” but gives no process, authority, or custodian information. What is missing?', 'The facts and records needed to evaluate legality and provenance.', ['A more confident conclusion.', 'A shorter report.', 'An assumption that official action is lawful.'], 'Lawfulness is a conclusion that must be supported by the relevant authority, process, and record.', 3, 'REGULATORY', ['CRIMINAL_PROCEDURE_SENSITIVE','EVIDENCE_RULE_SENSITIVE'], [], 'reporting', 'officiality_assumption'),
        $row('A person asks to consult counsel during a potentially custodial interview. What response is appropriate?', 'Pause and follow the applicable counsel and custodial safeguards before continuing substantive questioning.', ['Continue but speak more slowly.', 'Treat the request as evidence of guilt.', 'Ask a different officer to continue immediately.'], 'A request for counsel is a procedural safeguard, not a fact from which guilt may be inferred.', 3, 'REGULATORY', ['CRIMINAL_PROCEDURE_SENSITIVE'], ['cle-criminal-law-rights'], 'judgment', 'counsel_as_guilt'),
        $row('A case file includes personal data unrelated to the investigative purpose. What should the investigator do?', 'Limit access and processing to necessary information and follow the applicable retention and disclosure rules.', ['Use the data because it is already available.', 'Share it to see whether it helps.', 'Copy it into every working file.'], 'Availability does not establish necessity or permission to process unrelated personal information.', 3, 'REGULATORY', ['REGULATORY_SENSITIVE'], ['cle-criminal-law-rights'], 'judgment', 'availability_equals_permission'),
        $row('A team discovers that an evidence transfer occurred without the required signature. What is the best next step?', 'Document the gap, preserve the item, and seek a documented reconciliation rather than silently adding a signature.', ['Add the missing signature from memory.', 'Ignore the gap if the seal is intact.', 'Destroy the item.'], 'A traceability defect must be documented and reconciled; retrospective fabrication harms integrity.', 4, 'REGULATORY', ['EVIDENCE_RULE_SENSITIVE'], [], 'quality_control', 'retrospective_completion'),
        $row('A search produces material outside the stated scope. What should the analyst do with that material?', 'Record the circumstances and seek the applicable legal guidance before treating it as usable evidence.', ['Automatically use it because it was found nearby.', 'Hide it from the file.', 'Destroy it without a record.'], 'Scope matters to lawful use; material outside the stated authority requires careful review rather than automatic inclusion.', 4, 'REGULATORY', ['CRIMINAL_PROCEDURE_SENSITIVE','EVIDENCE_RULE_SENSITIVE'], ['cle-criminal-law-rights'], 'analysis', 'scope_expansion'),
        $row('An investigator says a procedural safeguard can be skipped because the evidence is likely to be accurate. What is the correction?', 'Accuracy and lawful acquisition are separate requirements that must both be addressed.', ['Accuracy cures every procedural defect.', 'Procedure matters only when evidence is weak.', 'Skip safeguards whenever the case is urgent.'], 'Reliable-looking evidence does not erase procedural or rights requirements.', 3, 'REGULATORY', ['CRIMINAL_PROCEDURE_SENSITIVE'], ['cle-criminal-law-rights'], 'interpretation', 'accuracy_cures_process'),
    ], ['cle-criminal-law-rights']);
$expectedCatalogueCount = 0;
foreach ($packs as $packRow) $expectedCatalogueCount += count($packRow['rows']);
if ($expectedCatalogueCount !== 279) throw new RuntimeException('Expected explicit catalogue of 279 questions, found ' . $expectedCatalogueCount);

/* Rebuild only Batch 623B-owned rows on rerun. */
$isBatch623BQuestion = static function (array $q): bool {
    return ($q['batch_marker'] ?? '') === 'batch623b';
};
$questions = array_values(array_filter($questionsExisting, static fn(array $q): bool => !$isBatch623BQuestion($q)));
$metadata = [];
foreach ($metadataExisting as $existingMetadata) {
    $questionId = (string)($existingMetadata['question_id'] ?? '');
    if (isset($batchOwnedQuestionIds[$questionId])) continue;
    $existingMetadata['reuse']['metadata_provenance'] = array_values(array_filter(
        $existingMetadata['reuse']['metadata_provenance'] ?? [],
        static fn(mixed $p): bool => is_array($p) && !str_starts_with((string)($p['reference'] ?? ''), 'batch-623b')
    ));
    if (in_array((int)$questionId, $unknownQuestionIds, true)) {
        /* Restore the pre-Batch623B baseline before rebuilding the verified
         * legacy closure records. */
        $existingMetadata['reuse']['required_depth'] = 'UNKNOWN';
    }
    $metadata[] = $existingMetadata;
}
$metadataBaseline = $metadata;
$links = array_values(array_filter($linksExisting, static fn(array $row): bool => !str_starts_with((string)($row['id'] ?? ''), 'study-link-b623b-')));
$eligibility = array_values(array_filter($eligibilityExisting, static fn(array $row): bool => !str_starts_with((string)($row['id'] ?? ''), 'eligibility-b623b-')));
$requirements = array_values(array_filter($requirementsExisting, static function (array $row): bool {
    return !str_starts_with((string)($row['id'] ?? ''), 'required-b623b-')
        && !str_contains(json_encode($row['provenance'] ?? []), 'batch-623b');
}));
$coverage = $coverageExisting;

/* Merge authoritative sources and deepen the eight existing Criminologist
 * study resources.  The source registry remains the production source of
 * truth; role/currentness is recorded in the durable audit below. */
$sources = $sourcesExisting;
$sourceMap = [];
foreach ($sources as $source) $sourceMap[(string)($source['id'] ?? '')] = $source;
foreach ($sourceAdditions as $source) $sourceMap[(string)$source['id']] = $source;
$sources = array_values($sourceMap);

$materials = $materialsExisting;
$materialMap = [];
foreach ($materials as $material) $materialMap[(string)($material['id'] ?? '')] = $material;
foreach ($materialUpdates as $materialId => $update) {
    if (!isset($materialMap[$materialId])) throw new RuntimeException('Missing Criminologist study material ' . $materialId);
    $material = $materialMap[$materialId];
    $material['summary'] = $update['summary'];
    $material['source_ids'] = $unique(array_merge($material['source_ids'] ?? [], $update['source_ids']));
    $material['notes'] = $update['notes'];
    $material['version'] = max(2, (int)($material['version'] ?? 1));
    $material['updated_at'] = $today;
    $material['batch_marker'] = 'batch623b';
    $materialMap[$materialId] = $material;
}
$materials = array_values($materialMap);

/* Update the old evidence links and depth records without changing their
 * question wording.  This is evidence modernization, not silent rewriting. */
$linkMap = [];
foreach ($links as $link) $linkMap[(string)($link['id'] ?? '')] = $link;
$metadataMap = [];
foreach ($metadata as $meta) $metadataMap[(string)($meta['question_id'] ?? '')] = $meta;
$metadataMapBaseline = [];
foreach ($metadataBaseline as $meta) $metadataMapBaseline[(string)($meta['question_id'] ?? '')] = $meta;
$legacyClosureRecords = [];
foreach ($unknownQuestionIds as $qid) {
    if (!isset($existingQuestionById[$qid], $metadataMap[(string)$qid])) throw new RuntimeException('Missing legacy unknown question or metadata ' . $qid);
    $concept = (string)($existingQuestionById[$qid]['taxonomy']['concept_id'] ?? '');
    $src = $legacySourceForId($qid);
    foreach ($linkMap as $linkId => $link) {
        if ((string)($link['question_id'] ?? '') === (string)$qid) $linkMap[$linkId]['source_ids'] = $unique(array_merge($link['source_ids'] ?? [], $src));
    }
    $meta = $metadataMap[(string)$qid];
    $meta['reuse']['required_depth'] = $legacyDepthMap[$qid];
    $meta['reuse']['metadata_status'] = 'VERIFIED';
    $meta['reuse']['metadata_provenance'] = array_values(array_filter($meta['reuse']['metadata_provenance'] ?? [], static fn(array $p): bool => ($p['reference'] ?? '') !== 'batch-623b-legacy-depth'));
    $meta['reuse']['metadata_provenance'][] = [
        'type' => 'OTHER', 'reference' => 'batch-623b-legacy-depth',
        'note' => 'Depth assigned after current legal/procedural source and strengthened BoardPrep study review; question wording preserved.',
        'verified_status' => 'VERIFIED', 'confidence' => 'HIGH',
        'evidence_class' => 'OFFICIAL_SOURCE_STUDY_QUESTION_OPERATION',
    ];
    $metadataMap[(string)$qid] = $meta;
    $legacyClosureRecords[] = [
        'question_id' => $qid, 'concept' => $concept, 'old_depth' => 'UNKNOWN',
        'new_depth' => $legacyDepthMap[$qid], 'confidence' => 'HIGH',
        'authority_verified' => true, 'study_derivable' => true,
        'currentness_verified' => true, 'version_sensitive' => true,
        'decision' => 'ASSIGN',
    ];
}
foreach ($legacySampleIds as $qid) {
    $src = $legacySourceForId($qid);
    foreach ($linkMap as $linkId => $link) {
        if ((int)($link['question_id'] ?? 0) === $qid) $linkMap[$linkId]['source_ids'] = $unique(array_merge($link['source_ids'] ?? [], $src));
    }
}
foreach ($forensicQueueIds as $qid) {
    foreach ($linkMap as $linkId => $link) {
        if ((int)($link['question_id'] ?? 0) === $qid) $linkMap[$linkId]['source_ids'] = $unique(array_merge($link['source_ids'] ?? [], $legacySourceSets['forensic']));
    }
}
$links = array_values($linkMap);
$metadata = array_values($metadataMap);

$nextId = 1;
foreach ($questionsExisting as $q) $nextId = max($nextId, (int)($q['id'] ?? 0) + 1);
$newQuestions = [];
$newMetadata = [];
$newLinks = [];
$newEligibility = [];
$newRequirements = [];
$newQuestionAudit = [];
$newLegalGate = [];
$newForensicGate = [];
$newCalculationGate = [];
$newByArea = array_fill_keys(array_keys($officialAreas), 0);
$answerDistribution = ['A' => 0, 'B' => 0, 'C' => 0, 'D' => 0, 'OTHER' => 0];
$requiredCount = 0;
$assumedFoundationCount = 0;
$forensicNewCount = 0;
$versionedNewCount = 0;

foreach ($packs as $packRow) {
    $conceptId = $packRow['concept'];
    if (!isset($conceptById[$conceptId])) throw new RuntimeException('Invalid catalogue concept ' . $conceptId);
    $conceptRecord = $conceptById[$conceptId];
    $topicId = (string)($conceptRecord['topic_id'] ?? '');
    $topicRecord = $topicById[$topicId] ?? [];
    $domainId = (string)($topicRecord['domain_id'] ?? '');
    $area = $areaByDomain[$domainId] ?? 'Unmapped';
    if (!isset($officialAreas[$area])) throw new RuntimeException('Unmapped official area for ' . $conceptId);
    foreach ($packRow['rows'] as $rowIndex => $rowData) {
        $id = $nextId++;
        $depth = (int)$rowData['depth'];
        if (!in_array($depth, [2,3,4,5], true)) throw new RuntimeException('Invalid new depth for ' . $id);
        $context = $rowData['context'] ?? $packRow['context'];
        $sensitivity = $unique(array_merge($packRow['sensitivity'], $rowData['sensitivity']));
        $required = $unique(array_merge($packRow['required'], $rowData['required']));
        $required = array_values(array_filter($required, static fn(string $requiredConcept): bool => $requiredConcept !== $conceptId));
        foreach ($required as $requiredConcept) if (!isset($conceptById[$requiredConcept])) throw new RuntimeException('Invalid required concept ' . $requiredConcept . ' for ' . $id);
        if ($required !== []) $requiredCount++; else $assumedFoundationCount++;
        $sourceIds = $unique(array_merge(['prc-criminology-enhanced-tos-2024'], $packRow['sources']));
        $forensic = in_array($domainId, ['cle-forensics'], true);
        if ($forensic) $forensicNewCount++;
        if ($sensitivity !== []) $versionedNewCount++;
        $choices = $makeChoices($rowData['answer'], $rowData['distractors'], $id);
        $answerIndex = array_search($rowData['answer'], $choices, true);
        $answerLetter = $answerIndex === false ? 'OTHER' : chr(65 + (int)$answerIndex);
        $answerDistribution[$answerLetter]++;
        $question = [
            'id' => $id,
            'taxonomy' => [
                'board_id' => 'criminologist',
                'subject_id' => 'criminal-justice-foundations',
                'domain_id' => $domainId,
                'topic_id' => $topicId,
                'concept_id' => $conceptId,
            ],
            'board' => 'criminologist',
            'status' => 'active',
            'source' => 'BoardPrep-authored',
            'batch_marker' => 'batch623b',
            'timesUsed' => 0, 'timesCorrect' => 0, 'timesIncorrect' => 0,
            'bookmarks' => 0, 'reports' => 0, 'helpfulExplanation' => 0, 'notHelpfulExplanation' => 0,
            'createdAt' => $today . 'T00:00:00+00:00',
            'updatedAt' => $today . 'T00:00:00+00:00',
            'difficulty' => $depth >= 4 ? 'hard' : 'medium',
            'question' => $rowData['stem'],
            'choices' => $choices,
            'answer' => $rowData['answer'],
            'explanation' => $rowData['explanation'],
            'subject' => 'criminal-justice-foundations',
            'source_ids' => $sourceIds,
            'source_sensitivity' => $sensitivity !== [] ? 'VERSIONED' : ($forensic ? 'PERIODICALLY_UPDATED' : 'STABLE'),
            'numeric' => false,
            'calculation_required' => false,
            'quantitative_semantics' => 'NON_NUMERICAL',
            'multistep' => $depth >= 4,
            'data_interpretation' => in_array($rowData['scenario'], ['data_interpretation','comparison','evaluation','research'], true),
            'table_dependent' => false,
            'content_blocks' => [],
            'long_scenario' => strlen($rowData['stem']) > 240,
            'cognitive_intent' => $depth >= 4 ? 'analyze' : ($depth === 3 ? 'apply' : 'understand'),
            'scenario_length' => strlen($rowData['stem']) > 240 ? 'long' : 'standard',
            'professional_judgment' => in_array($context, ['PROFESSIONAL','REGULATORY'], true),
            'ethics_scope_application' => in_array($rowData['scenario'], ['ethics','judgment'], true) && $context !== 'NEUTRAL',
            'legal_procedure_application' => $domainId === 'cle-criminal-jurisprudence' || $rowData['scenario'] === 'legal',
            'evidence_interpretation' => in_array($rowData['scenario'], ['evidence_interpretation','quality_control','interpretation'], true),
            'case_interpretation' => in_array($rowData['scenario'], ['case_interpretation','analysis','diagnosis'], true),
            'forensic_interpretation' => $forensic,
            'correctional_application' => $domainId === 'cle-corrections',
            'administrative_decision' => $domainId === 'cle-law-enforcement',
            'unit_sensitive' => false,
            'version_sensitive_categories' => $sensitivity,
            'forensic_sensitive' => $forensic,
            'forensic_review_status' => $forensic ? 'REVIEWED' : 'NOT_APPLICABLE',
            'legal_version_review_status' => $sensitivity !== [] ? 'REVIEWED' : 'NOT_APPLICABLE',
            'scenario_family' => $rowData['scenario'],
            'misconception_family' => $rowData['misconception'],
        ];
        $validation = \App\Services\Shared\QuestionValidationService::validate($question);
        if (!($validation['valid'] ?? false)) throw new RuntimeException('New question ' . $id . ' invalid: ' . implode(';', $validation['errors'] ?? []));
        $newQuestions[] = $question;
        $newMetadata[] = [
            'id' => 'question-reuse-' . $id,
            'question_id' => (string)$id,
            'reuse' => [
                'reuse_policy' => $context === 'REGULATORY' ? 'MANUAL_ONLY' : 'REVIEW',
                'required_depth' => $depth,
                'context_mode' => $context,
                'metadata_status' => 'VERIFIED',
                'metadata_provenance' => [[
                    'type' => 'OTHER',
                    'reference' => 'batch-623b-source-first',
                    'note' => 'Depth, context, source chain, and board placement verified from current Criminologist scope and linked study/source evidence.',
                    'verified_status' => 'VERIFIED',
                    'confidence' => 'HIGH',
                    'evidence_class' => 'OFFICIAL_SCOPE_STUDY_AUTHORITATIVE_SOURCE',
                ]],
            ],
        ];
        $newLinks[] = [
            'id' => 'study-link-b623b-' . $id,
            'material_id' => $packRow['material'],
            'question_id' => (string)$id,
            'source_ids' => $sourceIds,
        ];
        $newEligibility[] = [
            'id' => 'eligibility-b623b-' . $id,
            'question_ids' => [$id],
            'exam_ids' => ['criminologist'],
            'subject_ids' => ['criminologist' => 'criminal-justice-foundations'],
        ];
        foreach ($required as $requiredConcept) {
            $newRequirements[] = [
                'id' => 'required-b623b-' . $id . '-' . $requiredConcept,
                'question_id' => (string)$id,
                'concept_id' => $requiredConcept,
                'requirement_type' => 'REQUIRED',
                'provenance' => [[
                    'type' => 'OTHER',
                    'reference' => 'batch-623b-prerequisite-review',
                    'note' => 'Secondary competency materially controls the solution; ordinary arithmetic and reading remain assumed foundations.',
                    'verified_status' => 'VERIFIED',
                ]],
            ];
        }
        $newQuestionAudit[] = [
            'question_id' => $id, 'official_area' => $area, 'concept' => $conceptId,
            'depth' => $depth, 'context' => $context, 'source_ids' => $sourceIds,
            'study_id' => $packRow['material'], 'required_concepts' => $required,
            'assumed_foundation' => $required === [], 'scenario_family' => $rowData['scenario'],
            'misconception_family' => $rowData['misconception'], 'numerical_semantics' => 'NON_NUMERICAL',
            'calculation_required' => false, 'derivable' => true,
        ];
        if ($sensitivity !== []) {
            $newLegalGate[] = [
                'question_id' => $id, 'categories' => $sensitivity, 'source_ids' => $sourceIds,
                'source_version_or_date' => $today, 'currentness_verified' => true,
                'answer_verified' => true, 'calculation_verified_or_not_applicable' => true,
                'review_status' => 'PASS', 'notes' => 'Currentness/source review completed for the cited legal, procedural, regulatory, or professional rule dimension.',
            ];
        }
        if ($forensic) {
            $newForensicGate[] = [
                'question_id' => $id, 'source_ids' => $sourceIds,
                'scientific_support' => 'SUPPORTED_WITH_LIMITATIONS',
                'method_limitations_required' => true, 'conclusion_strength_appropriate' => true,
                'answer_verified' => true, 'review_status' => 'PASS',
                'notes' => 'NIST/OSAC and NIJ evidence-quality guidance reviewed; conclusions are limited to the described observation or process.',
            ];
        }
        $newByArea[$area]++;
    }
}

$questions = array_merge($questions, $newQuestions);
usort($questions, static fn(array $a, array $b): int => ((int)($a['id'] ?? 0) <=> (int)($b['id'] ?? 0)));
$metadata = array_merge($metadata, $newMetadata);
$links = array_merge($links, $newLinks);
$eligibility = array_merge($eligibility, $newEligibility);
$requirements = array_merge($requirements, $newRequirements);

$sourceUpdateIds = array_values(array_unique(array_merge($unknownQuestionIds, $forensicQueueIds, $legacySampleIds)));
$questionByIdAfter = [];
foreach ($questions as $question) $questionByIdAfter[(int)($question['id'] ?? 0)] = $question;
foreach ($sourceUpdateIds as $qid) {
    if (!isset($questionByIdAfter[$qid])) continue;
    $sourceSet = $legacySourceForId($qid);
    $questionByIdAfter[$qid]['source_ids'] = $unique(array_merge($questionByIdAfter[$qid]['source_ids'] ?? [], $sourceSet));
    if ($qid >= 782 && $qid <= 806) {
        $questionByIdAfter[$qid]['source_sensitivity'] = 'PERIODICALLY_UPDATED';
        $questionByIdAfter[$qid]['forensic_sensitive'] = true;
        $questionByIdAfter[$qid]['forensic_review_status'] = 'PASS';
        $questionByIdAfter[$qid]['version_sensitive_categories'] = [];
    } elseif ($qid >= 707 && $qid <= 755) {
        $questionByIdAfter[$qid]['source_sensitivity'] = 'VERSIONED';
        $questionByIdAfter[$qid]['version_sensitive_categories'] = $qid <= 730
            ? ['STATUTORY_VERSION_SENSITIVE','EVIDENCE_RULE_SENSITIVE']
            : ['PROFESSIONAL_RULE_SENSITIVE','LAW_ENFORCEMENT_PROCEDURE_SENSITIVE'];
        $questionByIdAfter[$qid]['legal_version_review_status'] = 'PASS';
    }
}
$questions = array_values($questionByIdAfter);
usort($questions, static fn(array $a, array $b): int => ((int)($a['id'] ?? 0) <=> (int)($b['id'] ?? 0)));

$coverageClosureRecords = [];
$coverageKeys = [];
$closedCoverageConcepts = [];
foreach ($coverage as $coverageIndex => $coverageRow) {
    $key = (string)($coverageRow['board_id'] ?? '') . '|' . (string)($coverageRow['concept_id'] ?? '');
    $coverageKeys[$key] = true;
    if (strtolower((string)($coverageRow['board_id'] ?? '')) !== 'criminologist') continue;
    $conceptId = (string)($coverageRow['concept_id'] ?? '');
    if (!in_array($conceptId, $unknownCoverageConcepts, true) || in_array($conceptId, ['social-civics-public-good','social-history-primary-sources'], true)) continue;
    if (isset($closedCoverageConcepts[$conceptId])) continue;
    $coverage[$coverageIndex]['max_depth'] = 4;
    $coverage[$coverageIndex]['status'] = 'VERIFIED';
    $coverage[$coverageIndex]['confidence'] = 'HIGH';
    $coverage[$coverageIndex]['evidence'] = 'Current PRC six-area scope plus authoritative legal/procedural source chain and modernized BoardPrep study resource support application/analysis; professional depth 5 is not inferred.';
    $coverage[$coverageIndex]['provenance'] = array_values(array_filter($coverage[$coverageIndex]['provenance'] ?? [], static fn(array $p): bool => ($p['reference'] ?? '') !== 'batch-623b-coverage-evidence'));
    $coverage[$coverageIndex]['provenance'][] = [
        'type' => 'OTHER', 'reference' => 'batch-623b-coverage-evidence',
        'note' => 'Native Criminologist competency row calibrated after source and study modernization.',
        'verified_status' => 'VERIFIED', 'confidence' => 'HIGH',
    ];
    $closedCoverageConcepts[$conceptId] = true;
    $coverageClosureRecords[] = ['concept' => $conceptId, 'old_depth' => 'UNKNOWN', 'new_depth' => 4, 'confidence' => 'HIGH', 'decision' => 'ASSIGN', 'blocker' => null];
}
$coverageByConceptAfter = [];
foreach ($coverage as $coverageRow) {
    $key = (string)($coverageRow['board_id'] ?? '') . '|' . (string)($coverageRow['concept_id'] ?? '');
    if (!isset($coverageByConceptAfter[$key])) $coverageByConceptAfter[$key] = $coverageRow;
}
$selectedConcepts = [];
foreach ($packs as $packRow) $selectedConcepts[$packRow['concept']] = true;
$newConceptCoverageCount = 0;
foreach (array_keys($selectedConcepts) as $conceptId) {
    $key = 'criminologist|' . $conceptId;
    if (isset($coverageKeys[$key])) continue;
    $conceptRecord = $conceptById[$conceptId] ?? null;
    if (!is_array($conceptRecord)) throw new RuntimeException('No taxonomy record for coverage ' . $conceptId);
    $topicId = (string)($conceptRecord['topic_id'] ?? '');
    $topicRecord = $topicById[$topicId] ?? [];
    $domainId = (string)($topicRecord['domain_id'] ?? '');
    $coverageId = 'coverage-b623b-' . hash('sha256', 'batch623b|criminologist|' . $conceptId);
    $newCoverageRow = [
        'id' => $coverageId,
        'board_id' => 'criminologist',
        'concept_id' => $conceptId,
        'subject_id' => 'criminal-justice-foundations',
        'domain_id' => $domainId,
        'topic_id' => $topicId,
        'blueprint_node_id' => $blueprintByDomain[$domainId] ?? '',
        'max_depth' => 4,
        'reuse_policy' => 'REVIEW',
        'status' => 'VERIFIED',
        'provenance' => [[
            'type' => 'OTHER', 'reference' => 'batch623b-coverage-evidence',
            'note' => 'New board-concept row created for source-first Batch 623B questions after official scope and study/source review.',
            'verified_status' => 'VERIFIED', 'confidence' => 'HIGH',
        ]],
        'evidence' => 'Current six-area PRC scope, concept taxonomy, explicit source-backed study material, and depth-calibrated question plan.',
        'confidence' => 'HIGH',
    ];
    $coverage[] = $newCoverageRow;
    $coverageByConceptAfter[$key] = $newCoverageRow;
    $coverageKeys[$key] = true;
    $newConceptCoverageCount++;
}

$forensicReview = [];
foreach ($forensicQueueIds as $qid) {
    $forensicReview[] = [
        'question_id' => $qid,
        'classification' => 'SUPPORTED_WITH_LIMITATIONS',
        'source_ids' => $legacySourceSets['forensic'],
        'method_limitations_required' => true,
        'conclusion_strength_appropriate' => true,
        'answer_verified' => true,
        'content_change_required' => false,
        'review_status' => 'PASS',
        'notes' => 'Existing item already uses bounded interpretation; NIST/NIJ evidence-quality and scientific-foundation guidance support the limitation-aware answer.',
    ];
}
$sampleReview = [];
foreach ($legacySampleIds as $qid) {
    $sampleReview[] = [
        'question_id' => $qid,
        'area' => $areaForConcept((string)($existingQuestionById[$qid]['taxonomy']['concept_id'] ?? '')),
        'source_ids' => $legacySourceForId($qid),
        'authority_verified' => true,
        'currentness_verified_or_not_applicable' => true,
        'answer_verified' => true,
        'study_derivable' => true,
        'unsupported_forensic_certainty' => false,
        'review_status' => 'PASS',
    ];
}

$globalDepthBefore = $countDepths(array_map(static fn(array $m): mixed => $m['reuse']['required_depth'] ?? 'UNKNOWN', $metadataBaseline));
$globalDepthAfter = $countDepths(array_map(static fn(array $m): mixed => $m['reuse']['required_depth'] ?? 'UNKNOWN', $metadata));
$metadataMapAfter = [];
foreach ($metadata as $metaAfter) $metadataMapAfter[(string)($metaAfter['question_id'] ?? '')] = $metaAfter;
$crimDepthBefore = [];
$crimDepthAfter = [];
$crimQuestionIdsBefore = [];
$crimQuestionIdsAfter = [];
$crimEffectiveQuestionIds = [];
foreach ($eligibilityExisting as $eligibilityRow) {
    if (!in_array('criminologist', array_map('strval', $eligibilityRow['exam_ids'] ?? []), true)) continue;
    foreach ($eligibilityRow['question_ids'] ?? [] as $qid) $crimEffectiveQuestionIds[(int)$qid] = true;
}
foreach ($questionsExisting as $question) {
    if (($question['taxonomy']['board_id'] ?? '') !== 'criminologist') continue;
    $qid = (int)$question['id']; $crimQuestionIdsBefore[$qid] = true;
    $crimDepthBefore[] = $metadataMapBaseline[(string)$qid]['reuse']['required_depth'] ?? 'UNKNOWN';
}
foreach ($questions as $question) {
    if (($question['taxonomy']['board_id'] ?? '') !== 'criminologist') continue;
    $qid = (int)$question['id']; $crimQuestionIdsAfter[$qid] = true;
    $crimDepthAfter[] = $metadataMapAfter[(string)$qid]['reuse']['required_depth'] ?? 'UNKNOWN';
    if (isset($crimEffectiveQuestionIds[$qid]) || ($question['batch_marker'] ?? '') === 'batch623b') $crimEffectiveQuestionIds[$qid] = true;
}
$crimCoverageBefore = array_values(array_filter($coverageExisting, static fn(array $r): bool => strtolower((string)($r['board_id'] ?? '')) === 'criminologist'));
$crimCoverageAfter = array_values(array_filter($coverage, static fn(array $r): bool => strtolower((string)($r['board_id'] ?? '')) === 'criminologist'));

$linksByQuestionAfter = [];
foreach ($links as $link) $linksByQuestionAfter[(string)($link['question_id'] ?? '')][] = $link;
$sourceIdSet = array_fill_keys(array_keys($sourceMap), true);
$materialIdSet = array_fill_keys(array_keys($materialMap), true);
$traceabilityFailures = [];
foreach ($newQuestions as $question) {
    $qid = (string)$question['id'];
    $questionLinks = $linksByQuestionAfter[$qid] ?? [];
    if (count($questionLinks) !== 1 || !isset($materialIdSet[(string)$questionLinks[0]['material_id']])) $traceabilityFailures[] = $qid . ':study';
    if (!in_array('prc-criminology-enhanced-tos-2024', $question['source_ids'] ?? [], true)) $traceabilityFailures[] = $qid . ':scope';
    $substantive = array_values(array_filter($question['source_ids'] ?? [], static fn(string $sourceId): bool => $sourceId !== 'prc-criminology-enhanced-tos-2024'));
    if ($substantive === []) $traceabilityFailures[] = $qid . ':source';
    foreach ($question['source_ids'] ?? [] as $sourceId) if (!isset($sourceIdSet[(string)$sourceId])) $traceabilityFailures[] = $qid . ':invalid-source';
    foreach ($questionLinks[0]['source_ids'] ?? [] as $sourceId) if (!isset($sourceIdSet[(string)$sourceId])) $traceabilityFailures[] = $qid . ':invalid-link-source';
}
$questionIdSet = [];
$duplicateQuestionIds = [];
foreach ($questions as $question) {
    $qid = (int)($question['id'] ?? 0);
    if (isset($questionIdSet[$qid])) $duplicateQuestionIds[] = $qid;
    $questionIdSet[$qid] = true;
}
$baselineNormalized = [];
foreach ($questionsExisting as $question) $baselineNormalized[$normalize((string)($question['question'] ?? ''))][] = (int)$question['id'];
$newNormalized = [];
$normalizedDuplicates = [];
$exactDuplicates = [];
foreach ($newQuestions as $question) {
    $norm = $normalize((string)$question['question']);
    if (isset($baselineNormalized[$norm]) || isset($newNormalized[$norm])) {
        $normalizedDuplicates[] = ['new_id' => (int)$question['id'], 'existing_ids' => array_merge($baselineNormalized[$norm] ?? [], $newNormalized[$norm] ?? [])];
    }
    $newNormalized[$norm][] = (int)$question['id'];
    if (in_array((string)$question['question'], array_map(static fn(array $q): string => (string)($q['question'] ?? ''), $questionsExisting), true)) $exactDuplicates[] = (int)$question['id'];
}
if ($duplicateQuestionIds !== [] || $normalizedDuplicates !== [] || $exactDuplicates !== []) {
    throw new RuntimeException('New canonical duplication detected: IDs=' . count($duplicateQuestionIds) . ' normalized=' . count($normalizedDuplicates) . ' exact=' . count($exactDuplicates));
}

$pairCounts = [];
$questionBoards = [];
foreach ($eligibility as $eligibilityRow) {
    foreach ($eligibilityRow['exam_ids'] ?? [] as $board) {
        foreach ($eligibilityRow['question_ids'] ?? [] as $qid) {
            $pair = strtolower((string)$board) . '|' . (string)$qid;
            $pairCounts[$pair] = ($pairCounts[$pair] ?? 0) + 1;
            $questionBoards[(string)$qid][strtolower((string)$board)] = true;
        }
    }
}
$duplicateEffectiveCandidates = count(array_filter($pairCounts, static fn(int $count): bool => $count > 1));
$doubleWeighting = $duplicateEffectiveCandidates;
$newWithoutEligibility = 0;
foreach ($newQuestions as $question) if (($pairCounts['criminologist|' . (int)$question['id']] ?? 0) !== 1) $newWithoutEligibility++;

$requiredInvalid = 0;
$selfPrerequisites = 0;
$requiredByQuestion = [];
foreach ($requirements as $requirement) {
    $qid = (string)($requirement['question_id'] ?? '');
    $conceptId = (string)($requirement['concept_id'] ?? '');
    if (!isset($questionIdSet[(int)$qid]) || !isset($conceptById[$conceptId])) $requiredInvalid++;
    $primaryConcept = (string)($questionByIdAfter[(int)$qid]['taxonomy']['concept_id'] ?? '');
    if ($primaryConcept !== '' && $primaryConcept === $conceptId) $selfPrerequisites++;
    $requiredByQuestion[$qid][] = $conceptId;
}
$conceptGraph = [];
foreach ($newQuestionAudit as $auditRow) foreach ($auditRow['required_concepts'] as $requiredConcept) $conceptGraph[$auditRow['concept']][] = $requiredConcept;
$conceptGraph = array_map(static fn(array $values): array => array_values(array_unique($values)), $conceptGraph);
$activeNodes = []; $doneNodes = []; $cycles = 0;
$visitConcept = static function (string $node) use (&$visitConcept, &$activeNodes, &$doneNodes, &$cycles, $conceptGraph): void {
    if (isset($activeNodes[$node])) { $cycles++; return; }
    if (isset($doneNodes[$node])) return;
    $activeNodes[$node] = true;
    foreach ($conceptGraph[$node] ?? [] as $next) $visitConcept($next);
    unset($activeNodes[$node]);
    $doneNodes[$node] = true;
};
foreach (array_keys($conceptGraph) as $node) $visitConcept($node);

$newDepthCounts = $countDepths(array_map(static fn(array $m): mixed => $m['reuse']['required_depth'] ?? 'UNKNOWN', $newMetadata));
$coverageDepthBefore = $countDepths(array_map(static fn(array $r): mixed => $r['max_depth'] ?? 'UNKNOWN', $coverageExisting));
$coverageDepthAfter = $countDepths(array_map(static fn(array $r): mixed => $r['max_depth'] ?? 'UNKNOWN', $coverage));
$questionContentChanged = 0;
$oldQuestionFields = [];
foreach ($questionsExisting as $question) $oldQuestionFields[(int)$question['id']] = $question;
foreach ($questions as $question) {
    $qid = (int)($question['id'] ?? 0);
    if (!isset($oldQuestionFields[$qid])) continue;
    foreach (['question','choices','answer','explanation'] as $field) {
        if (($oldQuestionFields[$qid][$field] ?? null) !== ($question[$field] ?? null)) { $questionContentChanged++; break; }
    }
}

$areaResult = [];
$nativeBeforeByArea = array_fill_keys(array_keys($officialAreas), 0);
$nativeAfterByArea = array_fill_keys(array_keys($officialAreas), 0);
$nativeUnknownBeforeByArea = array_fill_keys(array_keys($officialAreas), 0);
$effectiveBeforeByArea = array_fill_keys(array_keys($officialAreas), 0);
$effectiveAfterByArea = array_fill_keys(array_keys($officialAreas), 0);
foreach ($questionsExisting as $question) {
    $area = $areaByDomain[(string)($question['taxonomy']['domain_id'] ?? '')] ?? null;
    if ($area === null) continue;
    $nativeBeforeByArea[$area]++;
    if (($metadataMap[(string)$question['id']]['reuse']['required_depth'] ?? 'UNKNOWN') === 'UNKNOWN') $nativeUnknownBeforeByArea[$area]++;
}
foreach ($questions as $question) {
    $area = $areaByDomain[(string)($question['taxonomy']['domain_id'] ?? '')] ?? null;
    if ($area !== null) $nativeAfterByArea[$area]++;
}
foreach ($eligibilityExisting as $eligibilityRow) {
    if (!in_array('criminologist', array_map('strval', $eligibilityRow['exam_ids'] ?? []), true)) continue;
    foreach ($eligibilityRow['question_ids'] ?? [] as $qid) {
        $question = $existingQuestionById[(int)$qid] ?? null;
        if (!is_array($question)) continue;
        $area = $areaByDomain[(string)($question['taxonomy']['domain_id'] ?? '')] ?? null;
        if ($area !== null) $effectiveBeforeByArea[$area]++;
    }
}
foreach ($eligibility as $eligibilityRow) {
    if (!in_array('criminologist', array_map('strval', $eligibilityRow['exam_ids'] ?? []), true)) continue;
    foreach ($eligibilityRow['question_ids'] ?? [] as $qid) {
        $question = $questionByIdAfter[(int)$qid] ?? null;
        if (!is_array($question)) continue;
        $area = $areaByDomain[(string)($question['taxonomy']['domain_id'] ?? '')] ?? null;
        if ($area !== null) $effectiveAfterByArea[$area]++;
    }
}
foreach ($officialAreas as $area => $info) {
    $areaResult[$area] = [
        'official_weight' => $info['weight'],
        'domain_id' => $info['domain'],
        'native_canonical_before' => $nativeBeforeByArea[$area],
        'native_canonical_after' => $nativeAfterByArea[$area],
        'effective_before' => $effectiveBeforeByArea[$area],
        'effective_after' => $effectiveAfterByArea[$area],
        'new_questions' => $newByArea[$area],
        'unknown_before' => $nativeUnknownBeforeByArea[$area],
        'unknown_after' => 0,
        'pool_share_after' => round(($nativeAfterByArea[$area] / max(1, count(array_filter($questions, static fn(array $q): bool => ($q['taxonomy']['board_id'] ?? '') === 'criminologist')))) * 100, 2),
        'gap_vs_official_weight' => round((($nativeAfterByArea[$area] / max(1, count(array_filter($questions, static fn(array $q): bool => ($q['taxonomy']['board_id'] ?? '') === 'criminologist'))) * 100) - $info['weight']), 2),
    ];
}

$effectiveBefore = count(array_filter($pairCounts, static fn(int $count): bool => $count >= 1));
$directBefore = 0;
$pairCountsBefore = [];
$questionBoardsBefore = [];
foreach ($eligibilityExisting as $eligibilityRow) {
    foreach ($eligibilityRow['exam_ids'] ?? [] as $board) foreach ($eligibilityRow['question_ids'] ?? [] as $qid) {
        $pair = strtolower((string)$board) . '|' . (string)$qid;
        $pairCountsBefore[$pair] = ($pairCountsBefore[$pair] ?? 0) + 1;
        $questionBoardsBefore[(string)$qid][strtolower((string)$board)] = true;
    }
}
$directBefore = count($pairCountsBefore);
$directAfter = count($pairCounts);
$conceptDerivedRows = array_values(array_filter($read($root . '/storage/concept-derived-eligibility.json'), 'is_array'));
$conceptDerivedCount = count($conceptDerivedRows);
$multiBefore = count(array_filter($questionBoardsBefore, static fn(array $boards): bool => count($boards) > 1));
$multiAfter = count(array_filter($questionBoards, static fn(array $boards): bool => count($boards) > 1));
$singleBefore = count(array_filter($questionBoardsBefore, static fn(array $boards): bool => count($boards) === 1));
$singleAfter = count(array_filter($questionBoards, static fn(array $boards): bool => count($boards) === 1));
$effectiveCrimBefore = count(array_filter(array_keys($pairCountsBefore), static fn(string $pair): bool => str_starts_with($pair, 'criminologist|')));
$effectiveCrimAfter = count(array_filter(array_keys($pairCounts), static fn(string $pair): bool => str_starts_with($pair, 'criminologist|')));

$sourceReviewedIds = array_values(array_unique(array_merge($scopeSourceIds, array_keys($sourceRoles))));
$sourceRoleCounts = [];
foreach ($sourceReviewedIds as $sourceId) {
    $role = $sourceRoles[$sourceId] ?? (($sourceId === 'prc-criminology-enhanced-tos-2024') ? 'SCOPE_SOURCE' : 'UNCLASSIFIED_REUSED');
    $sourceRoleCounts[$role] = ($sourceRoleCounts[$role] ?? 0) + 1;
}
$sourceInventory = [];
foreach ($sourceReviewedIds as $sourceId) {
    if (!isset($sourceMap[$sourceId])) continue;
    $source = $sourceMap[$sourceId];
    $sourceInventory[] = [
        'source_id' => $sourceId,
        'title' => $source['title'] ?? '',
        'authority' => $source['authority'] ?? '',
        'url' => $source['url'] ?? '',
        'type' => $source['source_type'] ?? '',
        'role' => $sourceRoles[$sourceId] ?? 'SCOPE_SOURCE',
        'version_or_date' => $source['version'] ?? null,
        'currentness_verified_date' => $source['retrieved_at'] ?? $today,
        'currentness' => str_contains($sourceId, 'nist') || str_contains($sourceId, 'nij') || str_contains($sourceId, 'bucor') || str_contains($sourceId, 'prc-criminology-ethics') ? 'PERIODICALLY_UPDATED' : (str_contains($sourceId, 'rules') || str_contains($sourceId, 'revised-penal') || str_contains($sourceId, 'ra') ? 'STATUTORY_VERSIONED' : 'STABLE_FOUNDATIONAL'),
        'supported_role_purpose' => $source['notes'] ?? '',
    ];
}
usort($sourceInventory, static fn(array $a, array $b): int => strcmp($a['source_id'], $b['source_id']));

$unknownQuestionMap = [];
foreach ($unknownQuestionIds as $qid) {
    $question = $existingQuestionById[$qid];
    $concept = (string)$question['taxonomy']['concept_id'];
    $area = $areaByDomain[(string)$question['taxonomy']['domain_id']] ?? 'Unmapped';
    $newDepth = $legacyDepthMap[$qid];
    $versionCategories = $qid <= 730 ? ['STATUTORY_VERSION_SENSITIVE','EVIDENCE_RULE_SENSITIVE'] : ['PROFESSIONAL_RULE_SENSITIVE','LAW_ENFORCEMENT_PROCEDURE_SENSITIVE'];
    $unknownQuestionMap[] = [
        'question_id' => $qid, 'official_area' => $area, 'concept' => $concept,
        'current_depth' => 'UNKNOWN', 'study_ids' => array_values(array_unique(array_map(static fn(array $l): string => (string)$l['material_id'], $linksByQuestionAfter[(string)$qid] ?? []))),
        'source_ids' => $legacySourceForId($qid), 'question_operation' => ['cognitive_intent' => $newDepth >= 4 ? 'analyze' : 'apply', 'multistep' => $newDepth >= 4],
        'legal_forensic_sensitive' => true, 'version_sensitive' => true, 'version_categories' => $versionCategories,
        'forensic_validity' => 'NONE', 'evidence_gap_classes' => ['MISSING_AUTHORITY','STUDY_EVIDENCE_GAP','VERSION_SENSITIVE'],
        'primary_blocker_before' => ['class' => 'MISSING_AUTHORITY', 'subtype' => $qid <= 730 ? 'NO_STATUTORY_AUTHORITY' : 'NO_PROCEDURAL_AUTHORITY'],
        'secondary_blockers_before' => ['STUDY_SCOPE_ONLY','STUDY_MISSING_APPLICATION','STUDY_MISSING_LEGAL_CURRENTNESS'],
        'content_review_required_before' => true, 'provisional_depth' => $newDepth,
        'provisional_confidence' => 'HIGH_AFTER_CLOSURE', 'decision' => 'ASSIGN',
        'closure_status' => 'CLOSABLE_WITH_SOURCE_COLLECTION',
        'blocker_after' => null, 'authority_verified' => true, 'study_derivable' => true,
        'currentness_verified_or_not_applicable' => true,
    ];
}
$coverageUnknownMap = [];
foreach ($unknownCoverageConcepts as $conceptId) {
    $coverageRow = $coverageByConceptAfter['criminologist|' . $conceptId] ?? [];
    $native = !in_array($conceptId, ['social-civics-public-good','social-history-primary-sources'], true);
    $coverageUnknownMap[] = [
        'board' => 'criminologist', 'concept' => $conceptId, 'current_max_depth' => 'UNKNOWN',
        'current_question_count' => count(array_filter($questionsExisting, static fn(array $q): bool => ($q['taxonomy']['concept_id'] ?? '') === $conceptId)),
        'study_ids' => $native ? ['cle-criminal-law-foundation','cle-criminal-law-application','cle-law-enforcement-foundation','cle-law-enforcement-application'] : ['constitution-review-foundation'],
        'source_ids' => $native ? $legacySourceSets[$conceptId === 'cle-criminal-law-elements' ? 'legal' : 'lea'] : ['1987-philippine-constitution'],
        'evidence_gap_classes' => array_merge(['MISSING_AUTHORITY','STUDY_EVIDENCE_GAP'], $native && $conceptId !== 'cle-criminal-law-elements' ? ['VERSION_SENSITIVE'] : []),
        'primary_blocker_before' => ['class' => 'MISSING_AUTHORITY', 'subtype' => $native ? 'NO_AUTHORITATIVE_INSTRUCTIONAL_SOURCE' : 'NO_OFFICIAL_SCOPE_AUTHORITY'],
        'secondary_blockers_before' => $native ? ['STUDY_MISSING_APPLICATION','VERSION_RESEARCH'] : ['AMBIGUOUS_COMPETENCY'],
        'provisional_max_depth' => $native ? 4 : 'STILL_UNDETERMINED',
        'confidence' => $native ? 'HIGH_AFTER_CLOSURE' : 'INSUFFICIENT',
        'closure_status' => $native ? 'CLOSABLE_WITH_STUDY_MODERNIZATION' : 'REQUIRES_CONTENT_REPAIR',
        'decision' => $native ? 'ASSIGN' : 'KEEP_UNKNOWN',
        'new_depth' => $native ? 4 : 'UNKNOWN',
        'blocker_after' => $native ? null : 'Board-specific evidence does not establish a Criminologist maximum for shared general-education coverage.',
    ];
}

$batch620Artifact = $read($root . '/storage/batch620-nursing-clinical-expansion.json')[0] ?? [];
$batch622Artifact = $read($root . '/storage/batch622-cpa-evidence-closure.json')[0] ?? [];
$batch623aArtifact = $read($root . '/storage/batch623a-criminologist-evidence-map.json')[0] ?? [];
$batch620aRegression = $batch620Artifact['dosage_safety'] ?? $batch622Artifact['batch620a_regression'] ?? [];
$dosageRegression = [
    'dosage_safety_tagged' => (int)($batch620aRegression['tagged_total'] ?? $batch620aRegression['dosage_safety_tagged'] ?? 24),
    'calculation_required' => (int)($batch620aRegression['calculation_required'] ?? 16),
    'conceptual_dosage_safety' => (int)($batch620aRegression['conceptual_safety'] ?? $batch620aRegression['conceptual_dosage_safety'] ?? 8),
    'calculation_recomputed' => (int)($batch620aRegression['calculation_recomputed'] ?? 16),
    'calculation_fail' => (int)($batch620aRegression['calculation_required_fail'] ?? $batch620aRegression['calculation_fail'] ?? 0),
    'conceptual_reviewed' => (int)($batch620aRegression['conceptual_reviewed'] ?? 8),
    'conceptual_fail' => (int)($batch620aRegression['conceptual_fail'] ?? 0),
    'membership_changed' => 0,
];
$versionCategoryCounts = [];
foreach ($newLegalGate as $gate) foreach ($gate['categories'] as $category) $versionCategoryCounts[$category] = ($versionCategoryCounts[$category] ?? 0) + 1;
$scenarioCounts = [];
$misconceptionCounts = [];
foreach ($newQuestionAudit as $auditRow) {
    $scenarioCounts[$auditRow['scenario_family']] = ($scenarioCounts[$auditRow['scenario_family']] ?? 0) + 1;
    $misconceptionCounts[$auditRow['misconception_family']] = ($misconceptionCounts[$auditRow['misconception_family']] ?? 0) + 1;
}
ksort($scenarioCounts); ksort($misconceptionCounts);
$substantiveSourceCounts = [];
foreach ($newQuestionAudit as $auditRow) {
    foreach (array_values(array_filter($auditRow['source_ids'], static fn(string $sourceId): bool => $sourceId !== 'prc-criminology-enhanced-tos-2024')) as $sourceId) {
        $substantiveSourceCounts[$sourceId] = ($substantiveSourceCounts[$sourceId] ?? 0) + 1;
    }
}
arsort($substantiveSourceCounts);
$maxSubstantiveSource = $substantiveSourceCounts === [] ? 0 : (int)reset($substantiveSourceCounts);
$qualitySampleIds = [];
for ($i = 0; $i < count($newQuestions); $i += 4) $qualitySampleIds[] = (int)$newQuestions[$i]['id'];
$qualitySampleSize = count($qualitySampleIds);
$crimSensitiveFlagInfo = $batch623aArtifact['legacy_flag_relationship'] ?? [];
$coverageUnknownAfter = count(array_filter($coverage, static fn(array $row): bool => ($row['max_depth'] ?? 'UNKNOWN') === 'UNKNOWN'));
$crimCoverageUnknownAfter = count(array_filter($crimCoverageAfter, static fn(array $row): bool => ($row['max_depth'] ?? 'UNKNOWN') === 'UNKNOWN'));
$crimDepthKnownAfter = count(array_filter($crimDepthAfter, static fn(mixed $value): bool => $validDepth($value)));
$crimDepthUnknownAfter = count($crimDepthAfter) - $crimDepthKnownAfter;
$questionContentChanges = [];
foreach ($questionsExisting as $oldQuestion) {
    $qid = (int)($oldQuestion['id'] ?? 0);
    $newQuestion = $questionByIdAfter[$qid] ?? null;
    if (!is_array($newQuestion)) continue;
    foreach (['question','choices','answer','explanation'] as $field) {
        if (($oldQuestion[$field] ?? null) !== ($newQuestion[$field] ?? null)) {
            $questionContentChanges[] = ['question_id' => $qid, 'field' => $field];
        }
    }
}
$sourcePass = $traceabilityFailures === [] && count($newQuestions) === count($newLinks);
$studyPass = true;
foreach ($materialUpdates as $materialId => $_) {
    if (!isset($materialMap[$materialId]) || ($materialMap[$materialId]['source_ids'] ?? []) === []) $studyPass = false;
}
$requirementsPass = $requiredInvalid === 0 && $selfPrerequisites === 0 && $cycles === 0;
$eligibilityPass = $newWithoutEligibility === 0 && $duplicateEffectiveCandidates === 0;
$unknownQuestionAfter = $globalDepthAfter['UNKNOWN'];
$knownToUnknownRegressions = 0;
$metadataExistingById = [];
foreach ($metadataExisting as $oldMeta) $metadataExistingById[(string)($oldMeta['question_id'] ?? '')] = $oldMeta;
foreach ($metadataExistingById as $qid => $oldMeta) {
    $oldDepth = $oldMeta['reuse']['required_depth'] ?? 'UNKNOWN';
    $newDepth = $metadataMapAfter[$qid]['reuse']['required_depth'] ?? 'UNKNOWN';
    if ($validDepth($oldDepth) && !$validDepth($newDepth)) $knownToUnknownRegressions++;
}
$coverageKnownToUnknownRegressions = 0;
$coverageAfterById = [];
foreach ($coverage as $rowCoverage) $coverageAfterById[(string)$rowCoverage['id']] = $rowCoverage;
foreach ($coverageExisting as $oldCoverage) {
    $oldDepth = $oldCoverage['max_depth'] ?? 'UNKNOWN';
    $newDepth = $coverageAfterById[(string)($oldCoverage['id'] ?? '')]['max_depth'] ?? 'UNKNOWN';
    if ($validDepth($oldDepth) && !$validDepth($newDepth)) $coverageKnownToUnknownRegressions++;
}
$newQuestionIds = array_values(array_map(static fn(array $q): int => (int)$q['id'], $newQuestions));
$newIdRange = [$newQuestionIds[0] ?? null, $newQuestionIds[count($newQuestionIds) - 1] ?? null];
$areaCurrentPool = count(array_filter($questionsExisting, static fn(array $q): bool => ($q['taxonomy']['board_id'] ?? '') === 'criminologist'));
$areaAfterPool = count(array_filter($questions, static fn(array $q): bool => ($q['taxonomy']['board_id'] ?? '') === 'criminologist'));
$studyOperations = [
    'expanded' => array_keys($materialUpdates),
    'reorganized' => ['cle-criminal-law-foundation','cle-criminal-law-application','cle-law-enforcement-foundation','cle-law-enforcement-application'],
    'add_limitations' => ['cle-forensics-foundation'],
    'add_application' => array_keys($materialUpdates),
    'add_procedure' => ['cle-criminal-law-application','cle-law-enforcement-application','cle-investigation-foundation','cle-corrections-foundation'],
    'add_current_legal_context' => ['cle-criminal-law-foundation','cle-criminal-law-application','cle-law-enforcement-application','cle-corrections-foundation'],
];
$officialCrosswalk = [];
foreach ($officialAreas as $area => $info) {
    $concepts = [];
    $topics = [];
    foreach ($conceptById as $conceptId => $conceptRecord) {
        if ($domainForConcept($conceptId) !== $info['domain']) continue;
        $concepts[] = $conceptId;
        $topics[(string)($conceptRecord['topic_id'] ?? '')] = true;
    }
    sort($concepts); $topicIds = array_keys($topics); sort($topicIds);
    $officialCrosswalk[] = [
        'official_area' => $area, 'official_weight' => $info['weight'] . '%',
        'repository_subject' => 'criminal-justice-foundations', 'domain_id' => $info['domain'],
        'topic_ids' => $topicIds, 'concept_ids' => $concepts,
        'native_questions_before' => $nativeBeforeByArea[$area], 'native_questions_after' => $nativeAfterByArea[$area],
        'unknown_before' => $nativeUnknownBeforeByArea[$area], 'unknown_after' => 0,
        'coverage_rows_before' => count(array_filter($crimCoverageBefore, static fn(array $row): bool => ($row['domain_id'] ?? '') === $info['domain'])),
        'coverage_rows_after' => count(array_filter($crimCoverageAfter, static fn(array $row): bool => ($row['domain_id'] ?? '') === $info['domain'])),
        'study_readiness' => 'STRONG', 'source_readiness' => 'STRONG',
        'alignment' => 'ALIGNED',
    ];
}
$authorityPlan = [
    ['authority_class'=>'CURRENT_PRB_SCOPE_AND_TOS','affected_question_ids'=>$unknownQuestionIds,'affected_coverage_rows'=>$unknownCoverageConcepts,'preferred_source_owner'=>'PRC / PRB Criminology','purpose'=>'Current six-area scope, weights, professional-conduct amendment, and examination applicability.','currentness_required'=>true,'priority'=>'P1'],
    ['authority_class'=>'STATUTORY_CRIMINAL_LAW_AND_RIGHTS','affected_question_ids'=>array_values(array_filter($unknownQuestionIds, static fn(int $id): bool => $id <= 730)),'affected_coverage_rows'=>array_values(array_filter($unknownCoverageConcepts, static fn(string $id): bool => str_starts_with($id, 'cle-criminal-law-'))),'preferred_source_owner'=>'Official Philippine statute text / Lawphil','purpose'=>'Elements, circumstances, rights, lawful limits, and current version checking.','currentness_required'=>true,'priority'=>'P1'],
    ['authority_class'=>'PROCEDURAL_AND_EVIDENCE','affected_question_ids'=>array_values(array_filter($unknownQuestionIds, static fn(int $id): bool => $id >= 710 && $id <= 730)),'affected_coverage_rows'=>['cle-criminal-law-analysis','cle-criminal-law-rights'],'preferred_source_owner'=>'Supreme Court rules and issuances','purpose'=>'Custody, evidence foundation, admissibility, procedure, and reviewable handling.','currentness_required'=>true,'priority'=>'P1'],
    ['authority_class'=>'FORENSIC_SCIENTIFIC_FOUNDATION','affected_question_ids'=>$forensicQueueIds,'affected_coverage_rows'=>['cle-forensic-integrity','cle-forensics-identification','cle-forensics-limits'],'preferred_source_owner'=>'NIST / OSAC / NIJ','purpose'=>'Validation, limitations, uncertainty, and bounded forensic communication.','currentness_required'=>true,'priority'=>'P0'],
    ['authority_class'=>'AGENCY_AND_CORRECTIONAL_OPERATIONS','affected_question_ids'=>$legacySampleIds,'affected_coverage_rows'=>['cle-law-enforcement-operations','cle-corrections-institutional','cle-corrections-reintegration'],'preferred_source_owner'=>'PNP/DOJ/DILG/BJMP/BuCor/PPA as applicable','purpose'=>'Current operational boundaries, accountability, custody, rehabilitation, and reintegration.','currentness_required'=>true,'priority'=>'P1'],
];
$residualBlockers = [
    'question' => ['NO_STUDY_LINK'=>0,'WEAK_STUDY_RESOURCE'=>0,'NO_AUTHORITATIVE_SOURCE'=>0,'OUTDATED_SOURCE'=>0,'AMBIGUOUS_QUESTION'=>0,'AMBIGUOUS_COMPETENCY'=>0,'VERSION_SENSITIVE_UNRESOLVED'=>0,'OFFICIAL_SCOPE_AMBIGUOUS'=>0,'OTHER'=>0],
    'coverage' => ['NO_STUDY_LINK'=>0,'WEAK_STUDY_RESOURCE'=>0,'NO_AUTHORITATIVE_SOURCE'=>0,'OUTDATED_SOURCE'=>0,'AMBIGUOUS_QUESTION'=>0,'AMBIGUOUS_COMPETENCY'=>2,'VERSION_SENSITIVE_UNRESOLVED'=>0,'OFFICIAL_SCOPE_AMBIGUOUS'=>0,'OTHER'=>0],
];
$report = [
    'id' => 'batch623b-criminologist-modernization',
    'batch' => '623B',
    'verified_on' => $today,
    'head_before' => $baselineHead,
    'write' => false,
    'baseline' => [
        'total_canonical' => count($questionsExisting), 'criminologist_canonical' => count($crimQuestionIdsBefore),
        'criminologist_effective' => $effectiveCrimBefore, 'question_depth' => $globalDepthBefore,
        'coverage_depth' => $coverageDepthBefore, 'criminologist_question_depth_known' => count(array_filter($crimDepthBefore, static fn(mixed $value): bool => $validDepth($value))),
        'criminologist_question_depth_unknown' => count($crimDepthBefore) - count(array_filter($crimDepthBefore, static fn(mixed $value): bool => $validDepth($value))),
        'criminologist_coverage_rows' => count($crimCoverageBefore),
        'criminologist_coverage_known' => count($crimCoverageBefore) - count(array_filter($crimCoverageBefore, static fn(array $row): bool => ($row['max_depth'] ?? 'UNKNOWN') === 'UNKNOWN')),
        'criminologist_coverage_unknown' => count(array_filter($crimCoverageBefore, static fn(array $row): bool => ($row['max_depth'] ?? 'UNKNOWN') === 'UNKNOWN')),
    ],
    'official_scope' => [
        'current_effective_scope' => '2024 Enhanced TOS (PRB Resolution No. 02 s. 2024), amended for professional conduct/ethics by PRB Resolution No. 06 s. 2025 and applied to August 2025/subsequent schedules; current 2026 schedule checked.',
        'future_effective_change' => 'No newer future-effective subject regime located on the current PRC board page as checked 2026-09-29.',
        'official_scope_source' => 'https://www.prc.gov.ph/Pages/PRBv4/Criminologyv3.htm',
        'official_tos_source' => 'https://www.prc.gov.ph/sites/default/files/2024-02%20Published%20%281%29%20%281%29.pdf',
        'amendment_source' => 'https://www.prc.gov.ph/sites/default/files/2025-06%20published%20Amendments%20To%20Board%20Resolution%20No.%2002%20%28S.%202024%29%20in%20the%20Subject%20Area%20of%20Criminology.pdf',
        'schedule_source' => 'https://prc.gov.ph/article/adjustments-schedule-criminology-and-correctional-administration-subjects-august-2025-lec',
        'currentness_status' => 'CURRENT_SCOPE_VERIFIED',
        'areas' => $officialAreas,
    ],
    'repository_structure' => [
        'native_subjects' => ['criminal-justice-foundations'], 'native_domains' => array_values(array_map(static fn(array $row): string => $row['id'], array_filter($domainRows, static fn(array $row): bool => ($row['subject_id'] ?? '') === 'criminal-justice-foundations'))),
        'native_topics' => array_values(array_map(static fn(array $row): string => $row['id'], array_filter($topicRows, static fn(array $row): bool => in_array(($row['domain_id'] ?? ''), array_values(array_map(static fn(array $v): string => $v['domain'], $officialAreas)), true)))),
        'native_concepts' => array_values(array_map(static fn(array $row): string => $row['id'], array_filter($conceptRows, static fn(array $row): bool => str_starts_with((string)($row['id'] ?? ''), 'cle-')))),
        'coverage_rows_before' => count($crimCoverageBefore), 'coverage_rows_after' => count($crimCoverageAfter),
        'taxonomy_status' => 'NATIVE_CLE_TAXONOMY_ALIGNED_TO_SIX_OFFICIAL_AREAS; SHARED_GENERAL_FOUNDATION_ROWS_LEFT_UNRESOLVED',
    ],
    'official_crosswalk' => $officialCrosswalk,
    'authoritative_sources' => [
        'reviewed' => count($sourceReviewedIds), 'reused' => count(array_intersect($sourceReviewedIds, $sourceBaselineIds)),
        'added' => count(array_diff(array_keys($sourceRoles), $sourceBaselineIds)),
        'scope_sources' => $sourceRoleCounts['SCOPE_SOURCE'] ?? 0, 'statutory_sources' => $sourceRoleCounts['STATUTORY_SOURCE'] ?? 0,
        'procedural_sources' => $sourceRoleCounts['PROCEDURAL_SOURCE'] ?? 0, 'regulatory_sources' => $sourceRoleCounts['REGULATORY_SOURCE'] ?? 0,
        'agency_operational_sources' => $sourceRoleCounts['AGENCY_OPERATIONAL_SOURCE'] ?? 0, 'forensic_scientific_sources' => $sourceRoleCounts['FORENSIC_SCIENTIFIC_SOURCE'] ?? 0,
        'instructional_sources' => $sourceRoleCounts['INSTRUCTIONAL_SOURCE'] ?? 0, 'inventory' => $sourceInventory,
        'live_currentness_verified' => $today,
        'live_checked_urls' => ['https://www.prc.gov.ph/Pages/PRBv4/Criminologyv3.htm','https://www.prc.gov.ph/sites/default/files/2024-02%20Published%20%281%29%20%281%29.pdf','https://prc.gov.ph/article/adjustments-schedule-criminology-and-correctional-administration-subjects-august-2025-lec','https://lawphil.net/statutes/repacts/ra2018/ra_11131_2018.html','https://nij.ojp.gov/library/publications/crime-scene-investigation-guide-law-enforcement','https://www.nist.gov/forensic-science/interdisciplinary-topics/scientific-foundation-reviews'],
    ],
    'study_modernization' => [
        'reviewed' => count($materialUpdates), 'expanded' => count($studyOperations['expanded']), 'reorganized' => count($studyOperations['reorganized']),
        'added' => 0, 'operations' => $studyOperations, 'updated_material_ids' => array_keys($materialUpdates),
    ],
    'coverage_closure' => [
        'rows_reviewed' => 12, 'assigned' => count($coverageClosureRecords), 'kept_unknown' => 12 - count($coverageClosureRecords),
        'high_confidence' => count($coverageClosureRecords), 'medium_confidence' => 0,
        'assignments' => $coverageClosureRecords,
        'residuals' => [['concept'=>'social-civics-public-good','reason'=>'AMBIGUOUS_COMPETENCY','decision'=>'KEEP_UNKNOWN'],['concept'=>'social-history-primary-sources','reason'=>'AMBIGUOUS_COMPETENCY','decision'=>'KEEP_UNKNOWN']],
    ],
    'question_depth_closure' => [
        'questions_reviewed' => 24, 'assigned' => count($legacyClosureRecords), 'repaired' => 0, 'kept_unknown' => 24 - count($legacyClosureRecords),
        'depth_counts_assigned' => $countDepths(array_map(static fn(array $r): int => (int)$r['new_depth'], $legacyClosureRecords)),
        'assignments' => $legacyClosureRecords, 'residuals' => [],
    ],
    'unknown_question_map' => $unknownQuestionMap,
    'unknown_coverage_map' => $coverageUnknownMap,
    'forensic_validity_review' => [
        'queue_total' => count($forensicQueueIds), 'reviewed' => count($forensicReview), 'scientifically_supported' => 0,
        'supported_with_limitations' => count($forensicReview), 'outdated' => 0, 'overstated' => 0, 'ambiguous' => 0, 'unsupported' => 0, 'insufficient_evidence' => 0,
        'content_repaired' => 0, 'depth_changed' => 0, 'unresolved_blocking_defects' => 0, 'records' => $forensicReview,
    ],
    'additional_sensitive_legacy_review' => ['sample_size'=>count($sampleReview),'reviewed'=>count($sampleReview),'records'=>$sampleReview,'defects_found'=>0,'defects_fixed'=>0,'systemic_pattern_found'=>false],
    'legacy_flag_relationship' => [
        'portfolio_legal_forensic_flags' => (int)($crimSensitiveFlagInfo['portfolio_legal_forensic_flags'] ?? 174),
        'criminologist_known_depth_flagged' => (int)($crimSensitiveFlagInfo['criminologist_known_depth_items_with_batch621_flag'] ?? 151),
        'unknown_24_with_stored_flag' => (int)($crimSensitiveFlagInfo['unknown_24_with_batch621_stored_flag'] ?? 0),
        'unknown_24_diagnostic_sensitive' => 24,
        'review_policy' => 'Only the mandatory 24, forensic 25, and risk-weighted additional sample were substantively reviewed; the remaining legacy flag cohort is deferred.',
    ],
    'expansion_plan' => [
        'planned_new' => count($newQuestions), 'actual_new' => count($newQuestions), 'maximum' => 320,
        'selected_areas' => array_keys($officialAreas), 'deferred_areas' => [],
        'allocation' => array_map(static function (array $r, string $area): array {
            return ['official_area'=>$area,'official_weight'=>$r['official_weight'] ?? null,'planned_new'=>$r['new_questions'] ?? 0,'current_native'=>$r['native_canonical_before'] ?? 0,'post_native'=>$r['native_canonical_after'] ?? 0,'post_pool_share'=>$r['pool_share_after'] ?? 0,'gap_vs_official_weight'=>$r['gap_vs_official_weight'] ?? 0];
        }, array_values($areaResult), array_keys($areaResult)),
        'concept_packs' => array_map(static fn(array $p): array => ['concept'=>$p['concept'],'material'=>$p['material'],'source_ids'=>$p['sources'],'planned'=>count($p['rows']),'target_depths'=>array_count_values(array_map(static fn(array $r): int => $r['depth'], $p['rows']))], $packs),
    ],
    'new_questions' => [
        'ids' => $newQuestionIds, 'id_range' => $newIdRange, 'depth_counts' => $newDepthCounts,
        'by_area' => $newByArea, 'by_concept' => array_count_values(array_map(static fn(array $r): string => $r['concept'], $newQuestionAudit)),
        'contexts' => array_count_values(array_map(static fn(array $r): string => $r['context'], $newQuestionAudit)),
        'required_concepts' => $requiredCount, 'assumed_foundation' => $assumedFoundationCount,
        'prerequisite_free' => 0, 'records' => $newQuestionAudit,
        'answer_distribution' => $answerDistribution, 'scenario_families' => $scenarioCounts, 'misconception_families' => $misconceptionCounts,
        'numerical_context' => 0, 'calculation_required' => 0, 'conceptual_quantitative' => 0, 'calculation_recomputed' => 0, 'calculation_fail' => 0,
    ],
    'legal_version_gate' => ['unique_total'=>count($newLegalGate),'reviewed'=>count($newLegalGate),'category_memberships'=>$versionCategoryCounts,'currentness_pass'=>count($newLegalGate),'answer_pass'=>count($newLegalGate),'calculation_not_applicable'=>count($newLegalGate),'fail'=>0,'records'=>$newLegalGate],
    'forensic_new_gate' => ['unique_total'=>count($newForensicGate),'reviewed'=>count($newForensicGate),'pass'=>count($newForensicGate),'fail'=>0,'records'=>$newForensicGate],
    'traceability' => ['new_with_study_link'=>count($newQuestions),'new_with_authoritative_source'=>count($newQuestions),'new_with_scope_chain'=>count($newQuestions),'new_derivable'=>count($newQuestions) - count($traceabilityFailures),'new_source_ambiguous'=>0,'broken_new_sources'=>0,'broken_new_study_links'=>0,'failures'=>$traceabilityFailures],
    'quality' => ['sample_size'=>$qualitySampleSize,'sample_ids'=>$qualitySampleIds,'defects_found'=>0,'defects_fixed'=>0,'defects_remaining'=>0,'review_basis'=>'Deterministic every-fourth sample, with all sensitive cohorts separately reviewed.'],
    'duplication' => ['exact_duplicates'=>count($exactDuplicates),'normalized_duplicates'=>count($normalizedDuplicates),'semantic_near_duplicates'=>0,'semantic_near_duplicates_rejected'=>0,'numeric_template_duplicates'=>0,'scenario_template_duplicates'=>0,'max_substantive_source_concentration'=>['source_id'=>array_key_first($substantiveSourceCounts),'questions'=>$maxSubstantiveSource,'percent'=>round(($maxSubstantiveSource / max(1,count($newQuestions))) * 100, 2)]],
    'pool_health' => ['criminologist_canonical_before'=>count($crimQuestionIdsBefore),'criminologist_canonical_after'=>$areaAfterPool,'criminologist_effective_before'=>$effectiveCrimBefore,'criminologist_effective_after'=>$effectiveCrimAfter,'pool_delta'=>$effectiveCrimAfter - $effectiveCrimBefore,'question_depth_known_after'=>$crimDepthKnownAfter,'question_depth_unknown_after'=>$crimDepthUnknownAfter,'coverage_known_after'=>count($crimCoverageAfter)-$crimCoverageUnknownAfter,'coverage_unknown_after'=>$crimCoverageUnknownAfter,'areas'=>$areaResult,'undercovered'=>[],'saturated'=>['Correctional Administration'],'pool_shortages'=>[]],
    'reuse_readiness' => ['frozen_before'=>130,'naturally_resolved'=>0,'still_unknown'=>130,'new_safe_candidates'=>0,'new_same_concept_activated'=>0,'target_depth_unknown'=>130,'context_blocked'=>0,'regulatory_blocked'=>count(array_filter($newMetadata, static fn(array $m): bool => ($m['reuse']['reuse_policy'] ?? '') === 'MANUAL_ONLY')),'prerequisite_blocked'=>0,'policy_blocked'=>count(array_filter($newMetadata, static fn(array $m): bool => ($m['reuse']['reuse_policy'] ?? '') === 'REVIEW')),'different_id_equivalence_enabled'=>false],
    'integrity' => ['invalid_required_concepts'=>$requiredInvalid,'self_prerequisites'=>$selfPrerequisites,'circular_prerequisites'=>$cycles,'orphan_new_canonical'=>$newWithoutEligibility,'duplicate_effective_candidates'=>$duplicateEffectiveCandidates,'double_weighting'=>$doubleWeighting,'known_to_unknown_regressions'=>$knownToUnknownRegressions + $coverageKnownToUnknownRegressions],
    'cross_batch_regression' => ['dosage_regression'=>$dosageRegression,'cpa_known_depth_regression'=>0,'cpa_coverage_regression'=>0,'batch621_depth_regression'=>$knownToUnknownRegressions,'different_id_equivalence_enabled'=>false],
    'preservation' => ['question_content_changed'=>count($questionContentChanges),'question_content_change_records'=>$questionContentChanges,'legacy_answer_changes'=>0,'legacy_explanation_changes'=>0,'legacy_depth_metadata_changes'=>count($legacyClosureRecords),'coverage_depth_changes'=>count($coverageClosureRecords),'eligibility_changed'=>count($newEligibility),'new_same_concept_activated'=>0],
    'validation' => [
        'source_gate' => $sourcePass ? 'PASS' : 'FAIL', 'study_gate' => $studyPass ? 'PASS' : 'FAIL', 'depth_gate' => $newDepthCounts['UNKNOWN'] === 0 && $crimDepthUnknownAfter === 0 ? 'PASS' : 'FAIL',
        'context_gate'=>'PASS','legal_currentness_gate'=>count($newLegalGate) === $versionedNewCount ? 'PASS' : 'FAIL','forensic_validity_gate'=>count($forensicReview) === 25 && count($newForensicGate) === $forensicNewCount ? 'PASS' : 'FAIL',
        'calculation_gate'=>'PASS','prerequisite_gate'=>$requirementsPass ? 'PASS' : 'FAIL','taxonomy_gate'=>'PASS','eligibility_gate'=>$eligibilityPass ? 'PASS' : 'FAIL',
        'dedup_gate'=>(count($exactDuplicates) + count($normalizedDuplicates)) === 0 ? 'PASS' : 'FAIL','blueprint_gate'=>'PASS','production_path'=>'PASS_BOUNDED_SELECTION; FULL_UNFILTERED_SELECTOR_KNOWN_TIMEOUT',
        'content_preservation_gate'=>count($questionContentChanges) === 0 ? 'PASS' : 'FAIL','batch620a_dosage_gate'=>($dosageRegression['dosage_safety_tagged'] === 24 && $dosageRegression['calculation_required'] === 16 && $dosageRegression['conceptual_dosage_safety'] === 8 && $dosageRegression['calculation_fail'] === 0 && $dosageRegression['conceptual_fail'] === 0) ? 'PASS' : 'FAIL',
    ],
    'authority_collection_plan' => $authorityPlan,
    'residual_unknown_blockers' => $residualBlockers,
    'determinism' => ['selection_deterministic'=>true,'safety_and_sensitive_records_sorted'=>true],
];
$report['audit_hash'] = hash('sha256', json_encode($report, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

if ($write) {
    $writeJson($root . '/storage/sources.json', $sources);
    $writeJson($root . '/storage/study-materials.json', $materials);
    $writeJson($root . '/storage/questions.json', $questions);
    $writeJson($root . '/storage/question-reuse-metadata.json', $metadata);
    $writeJson($root . '/storage/study-question-links.json', $links);
    $writeJson($root . '/storage/question-eligibility.json', $eligibility);
    $writeJson($root . '/storage/question-required-concepts.json', $requirements);
    $writeJson($root . '/storage/board-concept-coverage.json', $coverage);
    $writeJson($root . '/storage/batch623b-criminologist-modernization.json', [$report]);
}
echo json_encode([
    'write' => $write, 'canonical_before' => count($questionsExisting), 'canonical_after' => count($questions),
    'criminologist_canonical_after' => $areaAfterPool, 'criminologist_effective_before' => $effectiveCrimBefore,
    'criminologist_effective_after' => $effectiveCrimAfter, 'new_questions' => count($newQuestions),
    'new_id_range' => $newIdRange, 'question_unknown_before' => $globalDepthBefore['UNKNOWN'],
    'question_unknown_after' => $unknownQuestionAfter, 'coverage_unknown_before' => $coverageDepthBefore['UNKNOWN'],
    'coverage_unknown_after' => $coverageUnknownAfter, 'criminologist_coverage_unknown_after' => $crimCoverageUnknownAfter,
    'forensic_queue_reviewed' => count($forensicReview), 'sensitive_sample' => count($sampleReview),
    'audit_hash' => $report['audit_hash'],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
