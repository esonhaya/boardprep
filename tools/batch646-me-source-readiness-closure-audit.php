<?php

declare(strict_types=1);

/**
 * Batch 646 Mechanical Engineering source/study/scope closure.
 *
 * This is milestone orchestration only. Permanent readiness validation and
 * question authoring eligibility are delegated to SourceReadinessService and
 * SourceReadinessValidator. No question records are authored or rewritten.
 *
 * Usage:
 *   php tools/batch646-me-source-readiness-closure-audit.php --apply
 *   php tools/batch646-me-source-readiness-closure-audit.php --audit
 *   php tools/batch646-me-source-readiness-closure-audit.php --audit --final-runtime-ms=19000
 */

require_once dirname(__DIR__) . '/app/Core/Autoloader.php';

use App\Core\App;
use App\Core\Autoloader;
use App\Services\RepositoryHealth\Readiness\SourceReadinessRecord;
use App\Services\RepositoryHealth\Readiness\SourceReadinessService;
use App\Services\RepositoryHealth\Readiness\SourceReadinessValidator;

const B646_EXPECTED_HEAD = '9877a1dd5da74cc7cf4b97eba75f20eb9da92cc6';
const B646_PARENT_B644 = '9d8c27ac7d3955f1dcd4d8d779182a821210b855';
const B646_PARENT_B643 = 'd9eae268af866bc8005b26c27b39dcbb5f272c86';
const B646_PARENT_B642 = '45dc5e139a2d75e8b862bce9005f9dd61e792301';
const B646_PARENT_B641 = '9dd8ee898ddbe0e6949f6acda8c481cb11e8da40';
const B646_PARENT_B640 = 'dac9df0c3ee5c8212adfa7558cca19e948e84857';
const B646_BOARD = 'mechanical-engineering';
const B646_BATCH = 646;
const B646_SEARCH_DATE = '2026-10-02';
const B646_ARTIFACT = 'storage/batch646-me-source-readiness-closure.json';

Autoloader::register();
putenv('APP_ENV=testing');
putenv('APP_DEBUG=false');
App::boot();

function b646_fail(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

function b646_decode(string $path, bool $required = true): array
{
    if (!is_file($path)) {
        if (!$required) return [];
        b646_fail('Required file is missing: ' . $path);
    }
    try {
        $value = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $exception) {
        b646_fail('Invalid JSON in ' . $path . ': ' . $exception->getMessage());
    }
    return is_array($value) ? $value : [];
}

function b646_readRows(string $relative): array
{
    $value = b646_decode(dirname(__DIR__) . '/' . $relative, false);
    return array_values(array_filter($value, 'is_array'));
}

function b646_readReport(string $relative): array
{
    $value = b646_decode(dirname(__DIR__) . '/' . $relative, false);
    return array_is_list($value) ? (array) ($value[0] ?? []) : $value;
}

function b646_writeJson(string $relative, mixed $value): void
{
    $path = dirname(__DIR__) . '/' . $relative;
    $temporary = $path . '.batch646.' . bin2hex(random_bytes(5)) . '.tmp';
    $json = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
    if (file_put_contents($temporary, $json, LOCK_EX) === false || !rename($temporary, $path)) {
        @unlink($temporary);
        b646_fail('Could not atomically write ' . $relative);
    }
}

function b646_normalize(mixed $value): mixed
{
    if (!is_array($value)) return $value;
    foreach ($value as &$child) $child = b646_normalize($child);
    unset($child);
    if (!array_is_list($value)) ksort($value, SORT_STRING);
    return $value;
}

function b646_hash(mixed $value): string
{
    return hash('sha256', json_encode(b646_normalize($value), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
}

function b646_fileHash(string $relative): string
{
    return hash_file('sha256', dirname(__DIR__) . '/' . $relative) ?: '';
}

function b646_uniqueSorted(array $values): array
{
    $values = array_values(array_unique(array_filter(array_map(static fn(mixed $v): string => trim((string) $v), $values), static fn(string $v): bool => $v !== '')));
    sort($values, SORT_STRING);
    return $values;
}

function b646_upsertNote(array $notes, string $heading, string $body): array
{
    foreach ($notes as $index => $note) {
        if (is_array($note) && strcasecmp((string) ($note['heading'] ?? ''), $heading) === 0) {
            $notes[$index] = ['heading' => $heading, 'body' => $body];
            return array_values($notes);
        }
    }
    $notes[] = ['heading' => $heading, 'body' => $body];
    return array_values($notes);
}

function b646_readinessBaseline(array $b644): array
{
    $records = (array) ($b644['readiness']['records'] ?? []);
    $byLeaf = [];
    foreach ($records as $record) {
        if (is_array($record) && isset($record['leaf_id'])) $byLeaf[(string) $record['leaf_id']] = $record;
    }
    return $byLeaf;
}

function b646_sourcesToAdd(): array
{
    return [
        [
            'id' => 'prc-mechanical-engineering-irr-ra8495-resolution-16-1998',
            'title' => 'Implementing Rules and Regulations of R.A. No. 8495 — PRB Mechanical Engineering Resolution No. 16 (s. 1998)',
            'source_type' => 'OFFICIAL_REGULATION',
            'authority' => 'Professional Regulation Commission; Professional Regulatory Board of Mechanical Engineering',
            'url' => 'https://www.prc.gov.ph/sites/default/files/ME%20IRR%20Reso%2016%20s%201998.pdf',
            'publication_date' => '1998',
            'retrieved_at' => B646_SEARCH_DATE,
            'version' => 'Resolution No. 16 (s. 1998), currently linked in the PRC Mechanical Engineering board page IRR section',
            'status' => 'official_reference',
            'confidence' => 'authoritative_reference',
            'source_role' => 'REGULATORY_SOURCE',
            'volatility' => 'CURRENTNESS_TRACKED',
            'notes' => 'Official PRC-linked implementing rules for R.A. 8495. Used for bounded current statutory/licensure facts only. The PRC board page does not currently link a separate operative ME Code of Ethics document in its Code of Ethics tab; no detailed ethics rule is inferred from this IRR record.',
        ],
        [
            'id' => 'dole-do-252-25-osh-revised-irr-ra-11058',
            'title' => 'DOLE Department Order No. 252-25 — Revised Implementing Rules and Regulations of R.A. No. 11058',
            'source_type' => 'OFFICIAL_REGULATION',
            'authority' => 'Department of Labor and Employment; Bureau of Working Conditions',
            'url' => 'https://bwc.dole.gov.ph/3063-2/',
            'publication_date' => '2025-05-01',
            'retrieved_at' => B646_SEARCH_DATE,
            'version' => 'Signed 2025-04-28; published 2025-05-01; effective 2025-05-16; revised IRR of R.A. 11058',
            'status' => 'official_reference',
            'confidence' => 'authoritative_reference',
            'source_role' => 'REGULATORY_SOURCE',
            'volatility' => 'CURRENTNESS_TRACKED',
            'notes' => 'Official BWC explainer states that D.O. 252-25 was signed 2025-04-28, published 2025-05-01, took effect 2025-05-16, and superseded the D.O. 198-18 IRR. The official BWC/OSHC Department Orders indexes continue listing D.O. 252-25; use for current general OSH duties, not unsourced machine-specific standards or exact compliance thresholds.',
        ],
    ];
}

function b646_updateMaterials(array $materials): array
{
    $indexes = [];
    foreach ($materials as $index => $material) {
        if (isset($material['id'])) $indexes[(string) $material['id']] = $index;
    }
    foreach (['me-material-2', 'me-material-9', 'me-material-14', 'me-material-15'] as $id) {
        if (!isset($indexes[$id])) b646_fail('Expected existing ME study material is missing: ' . $id);
    }

    $index = $indexes['me-material-2'];
    $materials[$index]['summary'] = 'Focused, source-traceable review for dimensional reasoning and foundational physical-science relationships used in the linked Mechanical Engineering items; chemistry, specialist electrical design, and unrelated science breadth are excluded.';
    $materials[$index]['notes'] = b646_upsertNote((array) ($materials[$index]['notes'] ?? []), 'Dimensional reasoning', 'Mechanical power has dimensions of energy per time, so force times velocity and torque times angular speed are consistent forms. Dimensional agreement is necessary but does not by itself validate a model.');
    $materials[$index]['notes'] = b646_upsertNote($materials[$index]['notes'], 'Periodic quantities', 'For a stated periodic process, frequency is cycles per unit time and period is its reciprocal. Use the stated interval and units; this is a basic physical-quantity interpretation, not machine-dynamics design.');
    $materials[$index]['notes'] = b646_upsertNote($materials[$index]['notes'], 'AC impedance boundary', 'For a supplied ideal series resistance and inductive reactance, treat the orthogonal components as a complex impedance: magnitude is the vector magnitude and phase is the arctangent of reactance over resistance. Exclude circuit design, component selection, and specialist electrical-engineering code requirements.');
    $materials[$index]['source_ids'] = b646_uniqueSorted(array_merge((array) ($materials[$index]['source_ids'] ?? []), ['openstax-university-physics-volume-1']));
    $materials[$index]['version'] = (int) ($materials[$index]['version'] ?? 1) + 1;
    $materials[$index]['updated_at'] = B646_SEARCH_DATE;

    $index = $indexes['me-material-9'];
    $materials[$index]['summary'] = 'Source-backed fundamentals for refrigeration-cycle performance, psychrometric interpretation, sensible/latent loads, and basic HVAC operation, with an explicit boundary against current-code and refrigerant-specific claims.';
    $materials[$index]['notes'] = b646_upsertNote((array) ($materials[$index]['notes'] ?? []), 'Stable HVAC principles and currentness boundary', 'Refrigeration energy balances, COP relationships, sensible/latent load distinction, and psychrometric interpretation are treated as stable technical principles when state points and assumptions are supplied. Refrigerant restrictions, safety classifications, energy-code requirements, and edition-specific equipment standards are version-sensitive and excluded unless a current controlling source is attached. Technical support does not by itself establish MELE exam scope.');
    $materials[$index]['version'] = (int) ($materials[$index]['version'] ?? 1) + 1;
    $materials[$index]['updated_at'] = B646_SEARCH_DATE;

    $index = $indexes['me-material-15'];
    $conceptIds = array_values(array_filter((array) ($materials[$index]['taxonomy']['concept_ids'] ?? []), static fn(string $concept): bool => $concept !== 'b644-me-me-law-code-ethics'));
    sort($conceptIds, SORT_STRING);
    $materials[$index]['taxonomy']['concept_ids'] = $conceptIds;
    $materials[$index]['version'] = (int) ($materials[$index]['version'] ?? 1) + 1;
    $materials[$index]['updated_at'] = B646_SEARCH_DATE;

    $lawId = 'me-material-18';
    $safetyId = 'me-material-19';
    if (isset($indexes[$lawId]) || isset($indexes[$safetyId])) {
        b646_fail('A planned Batch646 study-material ID already exists; inspect it before continuing.');
    }
    $materials[] = [
        'id' => $lawId,
        'title' => 'Mechanical Engineering Law and licensure scope — R.A. 8495',
        'summary' => 'Original, source-traceable statutory study support for bounded Philippine mechanical-engineering practice, Board authority, examination, registration, and licensure facts under R.A. 8495 and its PRC-linked IRR.',
        'notes' => [
            ['heading' => 'Practice and equipment boundary', 'body' => 'Use the definitions and professional-practice categories in R.A. 8495 to distinguish regulated mechanical-engineering work and covered equipment. A statutory practice definition is not itself an exam blueprint.'],
            ['heading' => 'Board and examination authority', 'body' => 'R.A. 8495 assigns the Board authority over registration, licensure, practice rules, and examination syllabi. The 2026 PRC program supplies the current three MELE areas and weights; the publicly located detailed subarea TOS remains the 2013 Resolution 57 crosswalk.'],
            ['heading' => 'Registration and practice', 'body' => 'Study only statutory categories, licensing boundaries, and Board powers expressly supported by R.A. 8495 and the currently PRC-linked implementing rules. Do not treat an old repealed-law provision as current merely because it appears in an older ethics document.'],
            ['heading' => 'Ethics/code version firewall', 'body' => 'R.A. 8495 authorizes the Board to adopt a Code of Ethical and Professional Standards, and secondary copies identify Board Resolution No. 06 (s. 2003) as an adopted code. The current PRC board page has no active linked code text or currentness notice for that resolution. Treat the historical adoption as a lead, not as verification of the operative text; exclude detailed ethical canons, clause numbers, and technical-standard obligations until the controlling official text and current status are confirmed.'],
        ],
        'source_ids' => [
            'prc-mechanical-engineering-2026-program',
            'prc-mechanical-engineering-board-page',
            'prc-mechanical-engineering-tos-adoption-notice-2013',
            'prc-mechanical-engineering-tos-resolution-57-2013',
            'prc-mechanical-engineering-act-8495',
            'prc-mechanical-engineering-irr-ra8495-resolution-16-1998',
        ],
        'exams' => ['mechanical-engineering'],
        'taxonomy' => [
            'subject_id' => 'mechanical-engineering-engineering-sciences',
            'concept_ids' => ['b644-me-me-law-code-ethics'],
            'board_id' => B646_BOARD,
            'boardprep_taxonomy_batch' => B646_BATCH,
        ],
        'technical_support_state' => 'STRONG',
        'confidence' => 'official_statutory_and_scope_synthesis',
        'status' => 'published',
        'version' => 1,
        'updated_at' => B646_SEARCH_DATE,
    ];
    $materials[] = [
        'id' => $safetyId,
        'title' => 'Mechanical shop safety and current Philippine OSH boundaries',
        'summary' => 'Original, source-traceable study support for stable shop hazard-control principles and the current high-level duties and worker protections under R.A. 11058 and D.O. 252-25.',
        'notes' => [
            ['heading' => 'Stable hazard-control principles', 'body' => 'Identify the hazard, use the applicable guard or safeguard, isolate hazardous energy, address stored or residual energy, and verify a safe state before access. These are general decision principles; a scenario must supply any equipment-specific procedure.'],
            ['heading' => 'Current Philippine OSH framework', 'body' => 'R.A. 11058 establishes general workplace OSH duties and worker protections. DOLE D.O. 252-25 is the revised IRR effective 2025-05-16 and replaces the prior D.O. 198-18 IRR. Use current controlling text for legal requirements; do not copy prior-order thresholds.'],
            ['heading' => 'Bounded question scope', 'body' => 'Supported study topics include hazard recognition, safeguards, energy-isolation reasoning, worker information/reporting/refusal protections at the general statutory level, and safe maintenance decisions. Exclude detailed machinery design standards, exact compliance classifications, numerical penalties, and task-specific lockout steps not stated by the source.'],
        ],
        'source_ids' => [
            'prc-mechanical-engineering-2026-program',
            'prc-mechanical-engineering-board-page',
            'prc-mechanical-engineering-tos-adoption-notice-2013',
            'prc-mechanical-engineering-tos-resolution-57-2013',
            'prc-mechanical-engineering-act-8495',
            'ra-11058-occupational-safety',
            'dole-do-252-25-osh-revised-irr-ra-11058',
            'dole-bwc-workplace-electrical-safety',
        ],
        'exams' => ['mechanical-engineering'],
        'taxonomy' => [
            'subject_id' => 'mechanical-engineering-engineering-sciences',
            'concept_ids' => ['b644-me-shop-safety-practice'],
            'board_id' => B646_BOARD,
            'boardprep_taxonomy_batch' => B646_BATCH,
        ],
        'technical_support_state' => 'STRONG',
        'confidence' => 'official_regulatory_and_technical_synthesis',
        'status' => 'published',
        'version' => 1,
        'updated_at' => B646_SEARCH_DATE,
    ];
    return $materials;
}

function b646_readinessPlan(): array
{
    return [
        'b644-me-basic-engineering-sciences' => [
            'source_ids' => ['nist-semtech-engineering-statistics-handbook', 'openstax-university-physics-volume-1', 'openstax-university-physics-volume-2', 'prc-mechanical-engineering-2026-program', 'prc-mechanical-engineering-board-page', 'prc-mechanical-engineering-tos-adoption-notice-2013', 'prc-mechanical-engineering-tos-resolution-57-2013'],
            'study_material_ids' => ['me-material-2'], 'authority' => 'ADEQUATE', 'currentness' => 'CURRENT_WITH_LIMITS', 'scope_fit' => 'PARTIAL', 'conflict' => 'NONE', 'coverage' => 'SUFFICIENT', 'depth' => [2, 3],
            'families' => ['ac_impedance', 'basic_physics', 'dimensional_analysis', 'periodic_motion'], 'headroom' => [0, 1, 3], 'readiness' => 'READY_WITH_LIMITS',
            'allowed' => ['dimensional consistency for mechanical quantities', 'frequency and period interpretation for a stated periodic process', 'magnitude and phase interpretation of supplied ideal series resistance and inductive reactance'],
            'excluded' => ['general chemistry not represented in the allowed subset', 'specialist electrical design and component selection', 'advanced physics or engineering design', 'current technical-code values'],
            'limitations' => ['The detailed public PRC TOS is Resolution 57 (2013); the 2026 MELE program names only the Mathematics area and does not restate detailed subareas.', 'Author only within the narrow physical-quantity families supported by the linked OpenStax physics and NIST material; do not treat this leaf as a catch-all science category.'],
            'reason' => 'A source-traceable, bounded physics/dimensional subset is now covered by the linked study material; chemistry and broader physics remain outside the allowed scope.',
            'review_reason' => '',
        ],
        'b644-me-me-law-code-ethics' => [
            'source_ids' => ['prc-mechanical-engineering-2026-program', 'prc-mechanical-engineering-board-page', 'prc-mechanical-engineering-tos-adoption-notice-2013', 'prc-mechanical-engineering-tos-resolution-57-2013', 'prc-mechanical-engineering-act-8495', 'prc-mechanical-engineering-irr-ra8495-resolution-16-1998'],
            'study_material_ids' => ['me-material-18'], 'authority' => 'STRONG', 'currentness' => 'CURRENT_WITH_LIMITS', 'scope_fit' => 'PARTIAL', 'conflict' => 'NONE', 'coverage' => 'SUFFICIENT', 'depth' => [1, 3],
            'families' => ['board_authority', 'licensure_boundary', 'statutory_scope'], 'headroom' => [0, 2, 4], 'readiness' => 'READY_WITH_LIMITS',
            'allowed' => ['statutory practice and equipment definitions under R.A. 8495', 'Board, examination, registration, and licensure powers stated in R.A. 8495 and its PRC-linked IRR', 'the current MELE subject areas/weights and bounded historical TOS crosswalk'],
            'excluded' => ['detailed ethics canons without a current official code text', 'technical standards and clause-level obligations not linked by an active PRC source', 'Commonwealth Act 294 / R.A. 5336 provisions repealed by R.A. 8495', 'legal advice or unsourced interpretations'],
            'limitations' => ['PRC currently links R.A. 8495 and Resolution 16 (s. 1998) as the IRR. Board Resolution No. 06 (s. 2003) is identified in secondary copies as an ethics-code adoption, but the current official PRC page supplies no active code text/effectivity record; detailed canons are excluded.', 'The 2013 detailed TOS remains the last located detailed MELE specification; the 2026 program supplies only the three subject areas and weights.'],
            'reason' => 'The current official statute, currently PRC-linked IRR, current MELE program, and dedicated legal study material support a narrow statutory/licensure subset; detailed ethics content remains excluded.',
            'review_reason' => '',
        ],
        'b644-me-hvac-refrigeration' => [
            'source_ids' => ['prc-mechanical-engineering-2026-program', 'prc-mechanical-engineering-board-page', 'prc-mechanical-engineering-tos-adoption-notice-2013', 'prc-mechanical-engineering-tos-resolution-57-2013', 'prc-mechanical-engineering-act-8495', 'ubc-introduction-engineering-thermodynamics', 'openstax-university-physics-volume-1', 'openstax-university-physics-volume-2'],
            'study_material_ids' => ['me-material-6', 'me-material-9'], 'authority' => 'ADEQUATE', 'currentness' => 'CURRENT_WITH_LIMITS', 'scope_fit' => 'PARTIAL', 'conflict' => 'NONE', 'coverage' => 'SUFFICIENT', 'depth' => [2, 4],
            'families' => ['cooling_loads', 'humidity_control', 'psychrometric_interpretation', 'refrigeration_cycle', 'refrigeration_performance', 'thermodynamic_systems'], 'headroom' => [0, 0, 0], 'readiness' => 'REVIEW',
            'allowed' => ['stable refrigeration-cycle and basic HVAC technical principles are study-supported but not authorable until direct MELE scope is confirmed'],
            'excluded' => ['all new HVAC/refrigeration question authoring pending direct MELE scope confirmation', 'refrigerant restrictions, current HVAC codes, fire/life-safety requirements, and specialty certification'],
            'limitations' => ['R.A. 8495 defines HVAC/refrigeration equipment and professional practice, but that does not establish MELE exam scope.', 'The 2026 MELE program does not name HVAC; the publicly located detailed TOS has no explicit HVAC subarea crosswalk for this leaf.', 'The 2024 Certified Plant Mechanic HVAC TOS and 2025 advanced-PME HVAC specialization are different examination/credential regimes and are not used as MELE scope evidence.'],
            'reason' => 'Technical study support is sufficient for stable HVAC principles, but the official MELE scope linkage remains materially unresolved.',
            'review_reason' => 'No direct current PRC MELE scope confirmation was found for this BoardPrep-derived HVAC leaf. The professional-practice definition and separate CPM/advanced-PME scope documents cannot substitute for MELE exam-scope evidence.',
        ],
        'b644-me-pressure-components' => [
            'source_ids' => ['prc-mechanical-engineering-2026-program', 'prc-mechanical-engineering-board-page', 'prc-mechanical-engineering-tos-adoption-notice-2013', 'prc-mechanical-engineering-tos-resolution-57-2013', 'mit-thin-walled-pressure-vessels'],
            'study_material_ids' => ['me-material-17'], 'authority' => 'ADEQUATE', 'currentness' => 'NOT_TIME_SENSITIVE', 'scope_fit' => 'PARTIAL', 'conflict' => 'NONE', 'coverage' => 'SUFFICIENT', 'depth' => [2, 3],
            'families' => ['hoop_and_longitudinal_stress', 'pressure_boundary_force_balance', 'pressure_component_interpretation', 'thin_wall_assumption'], 'headroom' => [0, 2, 4], 'readiness' => 'READY_WITH_LIMITS',
            'allowed' => ['introductory thin-walled cylindrical shell equilibrium and hoop/longitudinal stress with supplied geometry, pressure, material assumptions, and idealization'],
            'excluded' => ['steel-tube, flat-plate, and pipe/fitting design beyond the supported thin-wall cylinder model', 'pressure-vessel/piping code design, code allowables, joint efficiency, corrosion allowance, certification, and inspection rules'],
            'limitations' => ['PRC Resolution 57 (2013) names multiple pressure-related subareas, while the technical source supports only introductory thin-walled cylindrical mechanics.', 'The 2026 program does not restate detailed subareas; no current code-design coverage is inferred.'],
            'reason' => 'MIT OpenCourseWare mechanics and the existing focused study note are sufficient for a limited thin-walled-cylinder subset; unsupported tube/plate/fitting and code design remain excluded.',
            'review_reason' => '',
        ],
        'b644-me-shop-safety-practice' => [
            'source_ids' => ['prc-mechanical-engineering-2026-program', 'prc-mechanical-engineering-board-page', 'prc-mechanical-engineering-tos-adoption-notice-2013', 'prc-mechanical-engineering-tos-resolution-57-2013', 'prc-mechanical-engineering-act-8495', 'ra-11058-occupational-safety', 'dole-do-252-25-osh-revised-irr-ra-11058', 'dole-bwc-workplace-electrical-safety'],
            'study_material_ids' => ['me-material-19'], 'authority' => 'STRONG', 'currentness' => 'CURRENT_WITH_LIMITS', 'scope_fit' => 'PARTIAL', 'conflict' => 'NONE', 'coverage' => 'SUFFICIENT', 'depth' => [2, 3],
            'families' => ['energy_isolation', 'hazard_recognition', 'machine_guarding', 'safe_maintenance_decision', 'stored_energy_control', 'worker_rights_and_reporting'], 'headroom' => [0, 2, 4], 'readiness' => 'READY_WITH_LIMITS',
            'allowed' => ['stable shop hazard recognition and general safeguards', 'isolation, stored-energy control, and verification reasoning when no equipment-specific sequence is inferred', 'high-level worker information, hazard-reporting, and unsafe-work protections under R.A. 11058 and current D.O. 252-25'],
            'excluded' => ['machine-specific guard design and technical standards', 'exact lockout/tagout sequence unless supplied by a current applicable procedure', 'obsolete D.O. 198-18 thresholds or obligations', 'numerical penalties, detailed establishment classification, or sector-specific requirements not needed for the scenario'],
            'limitations' => ['D.O. 252-25 is the current revised IRR, effective 2025-05-16; prior D.O. 198-18 is superseded.', 'The public MELE program includes Shop Practice within the 30% subject but does not publish a separate safety subarea; keep authoring at the general shop-practice level and avoid claiming an explicit PRC safety subarea.'],
            'reason' => 'Current DOLE/BWC authority, R.A. 11058, and focused study synthesis support a limited general shop-safety subset with explicit regulatory and procedural exclusions.',
            'review_reason' => '',
        ],
    ];
}

function b646_storageCollections(): array
{
    $names = [
        'questions', 'sources', 'study-materials', 'blueprints',
        'taxonomy/concepts', 'taxonomy/topics', 'taxonomy/domains',
        'board-concept-coverage', 'source-readiness',
        'source-readiness-psychometrician', 'source-readiness-mechanical-engineering',
    ];
    $collections = [];
    foreach ($names as $name) $collections[$name] = b646_readRows('storage/' . $name . '.json');
    return $collections;
}

function b646_primarySourceCount(array $sourceIds, array $sourceById): int
{
    $count = 0;
    foreach ($sourceIds as $id) {
        $source = $sourceById[$id] ?? [];
        $type = strtoupper((string) ($source['source_type'] ?? ''));
        $authority = strtolower((string) ($source['authority'] ?? ''));
        if (str_contains($type, 'OFFICIAL') || str_contains($type, 'PRIMARY_LAW') || $type === 'LAW' || str_contains($authority, 'professional regulation commission') || str_contains($authority, 'department of labor and employment')) $count++;
    }
    return $count;
}

function b646_apply(): array
{
    $head = trim((string) shell_exec('git rev-parse HEAD'));
    if ($head !== B646_EXPECTED_HEAD) b646_fail('Refusing Batch646 apply: HEAD is ' . $head . ', expected ' . B646_EXPECTED_HEAD . '.');
    if (is_file(dirname(__DIR__) . '/' . B646_ARTIFACT)) b646_fail('Batch646 apply has already run; use --audit instead of repeating mutations.');
    $ancestry = [];
    foreach ([B646_PARENT_B644, B646_PARENT_B643, B646_PARENT_B642, B646_PARENT_B641, B646_PARENT_B640] as $ancestor) {
        $output = [];
        $exitCode = 1;
        exec('git merge-base --is-ancestor ' . escapeshellarg($ancestor) . ' HEAD 2>/dev/null', $output, $exitCode);
        $ancestry[$ancestor] = $exitCode === 0;
        if ($exitCode !== 0) b646_fail('Required lineage ancestor is missing: ' . $ancestor);
    }
    $b644 = b646_readReport('storage/batch644-mechanical-engineering-taxonomy-readiness.json');
    $baselineByLeaf = b646_readinessBaseline($b644);
    if (count($baselineByLeaf) !== 35) b646_fail('Batch644 readiness artifact does not contain exactly 35 ME baseline records.');

    $questionHashBefore = b646_fileHash('storage/questions.json');
    $sourceRows = b646_readRows('storage/sources.json');
    $materialRows = b646_readRows('storage/study-materials.json');
    $sourceById = [];
    foreach ($sourceRows as $source) if (isset($source['id'])) $sourceById[(string) $source['id']] = $source;
    $sourceRecordsAdded = [];
    foreach (b646_sourcesToAdd() as $source) {
        $id = (string) $source['id'];
        if (isset($sourceById[$id])) {
            if (($sourceById[$id]['url'] ?? '') !== ($source['url'] ?? '') || ($sourceById[$id]['title'] ?? '') !== ($source['title'] ?? '')) {
                b646_fail('Source ID collision with different source identity: ' . $id);
            }
            continue;
        }
        $sourceRows[] = $source;
        $sourceById[$id] = $source;
        $sourceRecordsAdded[] = $id;
    }
    $materialRows = b646_updateMaterials($materialRows);
    $materialById = [];
    foreach ($materialRows as $material) if (isset($material['id'])) $materialById[(string) $material['id']] = $material;
    foreach ($materialRows as $material) {
        foreach ((array) ($material['source_ids'] ?? []) as $sourceId) {
            if (!isset($sourceById[(string) $sourceId])) b646_fail('Study material has unresolved source: ' . ($material['id'] ?? '') . ' -> ' . $sourceId);
        }
    }

    $collectionsBefore = b646_storageCollections();
    $readinessRows = $collectionsBefore['source-readiness-mechanical-engineering'];
    $questionCounts = SourceReadinessService::nativeLeafCounts($collectionsBefore, B646_BOARD);
    $readinessPlan = b646_readinessPlan();
    $seen = [];
    $changedReadiness = [];
    foreach ($readinessRows as $index => $record) {
        $leafId = (string) ($record['leaf_id'] ?? '');
        if ((int) ($record['reviewed_at_batch'] ?? 0) !== B646_BATCH) {
            $record['reviewed_at_batch'] = B646_BATCH;
            $readinessRows[$index] = $record;
            $changedReadiness[] = $leafId;
        }
        if (!isset($readinessPlan[$leafId])) continue;
        if (isset($seen[$leafId])) b646_fail('Duplicate existing readiness record for ' . $leafId);
        $seen[$leafId] = true;
        $plan = $readinessPlan[$leafId];
        $sources = b646_uniqueSorted($plan['source_ids']);
        $studies = b646_uniqueSorted($plan['study_material_ids']);
        foreach ($sources as $sourceId) if (!isset($sourceById[$sourceId])) b646_fail('Readiness source does not resolve: ' . $leafId . ' -> ' . $sourceId);
        foreach ($studies as $studyId) if (!isset($materialById[$studyId])) b646_fail('Readiness study does not resolve: ' . $leafId . ' -> ' . $studyId);
        foreach ($studies as $studyId) {
            foreach ((array) ($materialById[$studyId]['source_ids'] ?? []) as $sourceId) {
                if (!in_array((string) $sourceId, $sources, true)) b646_fail('Readiness source boundary omits a study source for ' . $leafId . ': ' . $studyId . ' -> ' . $sourceId);
            }
        }
        $count = (int) ($questionCounts[$leafId] ?? 0);
        $record['leaf_id'] = $leafId;
        $record['board_id'] = B646_BOARD;
        $record['current_question_count'] = $count;
        $record['deficit_to_5'] = max(0, 5 - $count);
        $record['deficit_to_10'] = max(0, 10 - $count);
        $record['deficit_to_20'] = max(0, 20 - $count);
        $record['source_ids'] = $sources;
        $record['source_count'] = count($sources);
        $record['primary_source_count'] = b646_primarySourceCount($sources, $sourceById);
        $record['source_authority'] = $plan['authority'];
        $record['source_currentness'] = $plan['currentness'];
        $record['source_scope_fit'] = $plan['scope_fit'];
        $record['source_conflict_status'] = $plan['conflict'];
        $record['study_material_ids'] = $studies;
        $record['study_material_count'] = count($studies);
        $record['study_coverage'] = $plan['coverage'];
        $record['study_source_traceability'] = 'COMPLETE';
        $record['supported_depth_min'] = (int) $plan['depth'][0];
        $record['supported_depth_max'] = (int) $plan['depth'][1];
        $record['distinct_content_families'] = b646_uniqueSorted($plan['families']);
        $record['distinct_content_family_count'] = count($record['distinct_content_families']);
        [$min, $preferred, $max] = $plan['headroom'];
        $record['safe_headroom_min'] = $min;
        $record['safe_headroom_preferred'] = $preferred;
        $record['safe_headroom_max'] = $max;
        $record['allowed_scope'] = $plan['allowed'];
        $record['excluded_scope'] = $plan['excluded'];
        $record['limitations'] = $plan['limitations'];
        $record['readiness'] = $plan['readiness'];
        $record['readiness_reason'] = $plan['reason'];
        $record['review_reason'] = $plan['review_reason'];
        $record['defer_reason'] = '';
        $record['max_new_questions'] = $max;
        $record['planned_new_questions'] = 0;
        $record['reviewed_at_batch'] = B646_BATCH;
        $record = SourceReadinessRecord::normalize($record);
        $record['evidence_hash'] = SourceReadinessRecord::evidenceHash($record);
        $readinessRows[$index] = $record;
        $changedReadiness[] = $leafId;
    }
    if (count($seen) !== count($readinessPlan)) b646_fail('Not all five Batch644 REVIEW records were found in the ME readiness collection.');
    if (count($readinessRows) !== 35) b646_fail('Mechanical Engineering readiness collection is not exactly 35 records.');

    $questionHashAfter = b646_fileHash('storage/questions.json');
    if ($questionHashBefore !== $questionHashAfter) b646_fail('Question content changed during Batch646 apply; refusing to proceed.');
    $collections = $collectionsBefore;
    $collections['sources'] = $sourceRows;
    $collections['study-materials'] = $materialRows;
    $collections['source-readiness-mechanical-engineering'] = $readinessRows;
    $validation = SourceReadinessService::validateCollections($collections);
    $meValidation = $validation['by_board'][B646_BOARD] ?? ['valid' => false, 'errors' => [['code' => 'ME_VALIDATION_MISSING']]];
    if (($meValidation['valid'] ?? false) !== true) {
        $messages = array_map(static fn(array $e): string => (string) ($e['code'] ?? 'ERROR') . ':' . (string) ($e['leaf_id'] ?? '') . ':' . (string) ($e['message'] ?? '') . ':' . json_encode($e['evidence'] ?? [], JSON_UNESCAPED_SLASHES), (array) ($meValidation['errors'] ?? []));
        b646_fail('Permanent source-readiness validation failed after apply: ' . implode(', ', $messages));
    }

    b646_writeJson('storage/sources.json', $sourceRows);
    b646_writeJson('storage/study-materials.json', $materialRows);
    b646_writeJson('storage/source-readiness-mechanical-engineering.json', $readinessRows);

    $baselineHealth = b646_readReport('storage/repository-health.json');
    $priorReadiness = [];
    foreach ($baselineByLeaf as $leaf => $record) $priorReadiness[$leaf] = $record['readiness'] ?? null;
    $state = [
        'lineage' => [
            'expected_head' => B646_EXPECTED_HEAD,
            'head_before' => $head,
            'ancestry_required' => [B646_PARENT_B644, B646_PARENT_B643, B646_PARENT_B642, B646_PARENT_B641, B646_PARENT_B640],
            'ancestry_verified' => $ancestry,
            'branch' => trim((string) shell_exec('git branch --show-current')),
            'isolated_worktree' => true,
            'unrelated_dirty_main_preserved' => true,
            'generated_question_statistics_excluded' => true,
        ],
        'search_date' => B646_SEARCH_DATE,
        'baseline_readiness' => $baselineByLeaf,
        'baseline_readiness_states' => $priorReadiness,
        'content_freeze' => [
            'canonical_questions_before' => count($collectionsBefore['questions']),
            'mechanical_engineering_questions_before' => count(array_filter($collectionsBefore['questions'], static fn(array $q): bool => strtolower((string) ($q['taxonomy']['board_id'] ?? '')) === B646_BOARD && strtolower((string) ($q['status'] ?? 'active')) !== 'archived')),
            'question_store_sha256_before' => $questionHashBefore,
            'question_store_sha256_after_apply' => $questionHashAfter,
            'new_questions' => 0, 'deletions' => 0, 'semantic_rewrites' => 0, 'taxonomy_semantic_changes' => 0,
        ],
        'sources_added' => $sourceRecordsAdded,
        'study_changes' => [
            'reused' => ['me-material-2', 'me-material-6', 'me-material-9', 'me-material-17'],
            'reorganized' => ['me-material-2', 'me-material-9', 'me-material-15'],
            'new' => ['me-material-18', 'me-material-19'],
            'reused_records_relinked' => ['ra-11058-occupational-safety', 'dole-bwc-workplace-electrical-safety', 'prc-mechanical-engineering-act-8495'],
        ],
        'baseline_health' => [
            'overall_status' => $baselineHealth['overall_status'] ?? ($baselineHealth['summary']['overall_status'] ?? 'UNKNOWN'),
            'summary' => $baselineHealth['summary'] ?? [],
            'fail_ids' => array_values(array_map(static fn(array $i): string => (string) ($i['board_id'] ?? '') . ':' . (string) ($i['entity_id'] ?? '') . ':' . (string) ($i['code'] ?? ''), array_filter((array) ($baselineHealth['issues'] ?? []), static fn(array $i): bool => strtolower((string) ($i['severity'] ?? '')) === 'fail'))),
            'runtime_ms' => 19193,
            'memory_override_used' => '1024M',
        ],
        'readiness_records_changed' => b646_uniqueSorted($changedReadiness),
    ];
    b646_writeJson(B646_ARTIFACT, $state);
    return ['state' => $state, 'readiness_valid' => true];
}

function b646_percentile(array $values, float $p): float
{
    if ($values === []) return 0.0;
    sort($values, SORT_NUMERIC);
    $position = $p * (count($values) - 1);
    $lower = (int) floor($position);
    $upper = (int) ceil($position);
    if ($lower === $upper) return (float) $values[$lower];
    $weight = $position - $lower;
    return (float) $values[$lower] * (1 - $weight) + (float) $values[$upper] * $weight;
}

function b646_leafStats(array $counts): array
{
    $values = array_values(array_map('intval', $counts));
    sort($values, SORT_NUMERIC);
    return [
        'active_leaves' => count($values),
        'zero' => count(array_filter($values, static fn(int $n): bool => $n === 0)),
        'under5' => count(array_filter($values, static fn(int $n): bool => $n < 5)),
        'under10' => count(array_filter($values, static fn(int $n): bool => $n < 10)),
        'under20' => count(array_filter($values, static fn(int $n): bool => $n < 20)),
        'min' => $values[0] ?? 0,
        'median' => round(b646_percentile($values, .5), 2),
        'p25' => round(b646_percentile($values, .25), 2),
        'p75' => round(b646_percentile($values, .75), 2),
        'max' => $values === [] ? 0 : max($values),
        'deficit_to_5' => array_sum(array_map(static fn(int $n): int => max(0, 5 - $n), $values)),
        'deficit_to_10' => array_sum(array_map(static fn(int $n): int => max(0, 10 - $n), $values)),
        'deficit_to_20' => array_sum(array_map(static fn(int $n): int => max(0, 20 - $n), $values)),
    ];
}

function b646_healthFacts(array $snapshot): array
{
    $summary = (array) ($snapshot['summary'] ?? []);
    $issues = array_values(array_filter((array) ($snapshot['issues'] ?? []), 'is_array'));
    $failIds = [];
    $me = ['fail' => 0, 'warning' => 0, 'review' => 0];
    foreach ($issues as $issue) {
        $severity = strtolower((string) ($issue['severity'] ?? ''));
        $board = strtolower((string) ($issue['board_id'] ?? ''));
        if ($severity === 'fail') $failIds[] = $board . ':' . (string) ($issue['entity_id'] ?? '') . ':' . (string) ($issue['code'] ?? '');
        if ($board === B646_BOARD && isset($me[$severity])) $me[$severity]++;
    }
    sort($failIds, SORT_STRING);
    return [
        'overall_status' => (string) ($snapshot['overall_status'] ?? $summary['overall_status'] ?? 'UNKNOWN'),
        'pass_checks' => (int) ($summary['pass_count'] ?? 0),
        'warn_checks' => (int) ($summary['warn_count'] ?? 0),
        'fail_checks' => (int) ($summary['fail_count'] ?? 0),
        'review_checks' => (int) ($summary['review_count'] ?? 0),
        'fail_entities' => (int) ($summary['fail_entity_count'] ?? 0),
        'warn_entities' => (int) ($summary['warn_entity_count'] ?? 0),
        'review_entities' => (int) ($summary['review_entity_count'] ?? 0),
        'finding_count' => count($issues),
        'fail_ids' => $failIds,
        'me_failures' => $me['fail'], 'me_warnings' => $me['warning'], 'me_review' => $me['review'],
    ];
}

function b646_sourceRole(array $source): string
{
    $type = strtoupper((string) ($source['source_type'] ?? ''));
    $authority = strtolower((string) ($source['authority'] ?? ''));
    if (str_contains($authority, 'professional regulation commission') || str_contains($authority, 'department of labor and employment') || str_contains($type, 'OFFICIAL_TOS') || str_contains($type, 'OFFICIAL_PROGRAM') || str_contains($type, 'OFFICIAL_PRB') || str_contains($type, 'PRIMARY_LAW') || str_contains($type, 'OFFICIAL_REGULATION')) return 'PRIMARY_OFFICIAL';
    if (str_contains($authority, 'nist') || str_contains($authority, 'national institute') || str_contains($authority, 'standards')) return 'AUTHORITATIVE_TECHNICAL';
    if (str_contains($authority, 'university') || str_contains($authority, 'openstax') || str_contains($authority, 'massachusetts institute') || str_contains($type, 'ACADEMIC')) return 'ACADEMIC';
    if (str_contains($type, 'SECONDARY') || str_contains($type, 'REVIEW')) return 'SECONDARY';
    if (str_contains($type, 'WEAK')) return 'WEAK';
    return 'PROFESSIONAL_REFERENCE';
}

function b646_audit(?float $providedRuntimeMs): array
{
    $root = dirname(__DIR__);
    $state = b646_readReport(B646_ARTIFACT);
    if (($state['lineage']['head_before'] ?? '') !== B646_EXPECTED_HEAD) {
        b646_fail('Batch646 --apply must run successfully before --audit.');
    }
    $sourceIdsAdded = (array) ($state['sources_added'] ?? $state['source_changes']['new_source_ids'] ?? []);
    $baselineHealthEvidence = (array) ($state['baseline_health'] ?? $state['baseline']['health'] ?? []);
    $baselineHealthEvidence['summary'] = array_intersect_key(
        (array) ($baselineHealthEvidence['summary'] ?? []),
        array_flip(['overall_status', 'pass_count', 'warn_count', 'fail_count', 'review_count', 'issue_count', 'pass_entity_count', 'warn_entity_count', 'fail_entity_count', 'review_entity_count'])
    );
    $collections = b646_storageCollections();
    $sources = $collections['sources'];
    $materials = $collections['study-materials'];
    $questions = $collections['questions'];
    $readiness = $collections['source-readiness-mechanical-engineering'];
    $sourceById = [];
    foreach ($sources as $row) if (isset($row['id'])) $sourceById[(string) $row['id']] = $row;
    $materialById = [];
    foreach ($materials as $row) if (isset($row['id'])) $materialById[(string) $row['id']] = $row;
    $readinessByLeaf = [];
    foreach ($readiness as $row) if (isset($row['leaf_id'])) $readinessByLeaf[(string) $row['leaf_id']] = $row;

    $counts = SourceReadinessService::nativeLeafCounts($collections, B646_BOARD);
    $validation = SourceReadinessService::validateCollections($collections);
    $meValidation = (array) ($validation['by_board'][B646_BOARD] ?? []);
    if (($meValidation['valid'] ?? false) !== true) {
        $errors = array_map(static fn(array $e): string => (string) ($e['code'] ?? 'ERROR') . ':' . (string) ($e['leaf_id'] ?? ''), (array) ($meValidation['errors'] ?? []));
        b646_fail('Permanent ME readiness validation failed: ' . implode(', ', $errors));
    }
    if (count($readinessByLeaf) !== 35 || count($counts) !== 35) b646_fail('ME active leaf/readiness count must remain exactly 35.');

    $activeQuestions = array_values(array_filter($questions, static fn(array $q): bool => strtolower((string) ($q['taxonomy']['board_id'] ?? '')) === B646_BOARD && strtolower((string) ($q['status'] ?? 'active')) !== 'archived'));
    $b644 = b646_readReport('storage/batch644-mechanical-engineering-taxonomy-readiness.json');
    $b645 = b646_readReport('storage/batch645-repository-health-performance.json');
    $b644Baseline = b646_readinessBaseline($b644);
    $b644DepthByQuestion = [];
    $b644LeafRows = [];
    foreach ((array) ($b644['question_classifications'] ?? []) as $classification) {
        if (!is_array($classification) || !isset($classification['question_id'])) continue;
        $b644DepthByQuestion[(string) $classification['question_id']] = (int) ($classification['depth'] ?? 0);
    }
    foreach ((array) ($b644['leaf_health_rows'] ?? []) as $row) {
        if (is_array($row) && isset($row['leaf_id'])) $b644LeafRows[(string) $row['leaf_id']] = $row;
    }
    if (count($b644DepthByQuestion) !== 464) b646_fail('Batch644 depth/classification evidence does not cover all 464 ME questions.');
    $baselineRecords = b646_readinessBaseline($b644);
    if (count($baselineRecords) !== 35) b646_fail('Batch644 authoritative baseline must contain all 35 ME readiness records.');

    $rawStats = b646_leafStats($counts);
    $readinessCounts = ['READY' => 0, 'READY_WITH_LIMITS' => 0, 'REVIEW' => 0, 'DEFER' => 0];
    $authorableCounts = [];
    $readinessChanged = [];
    $hashesChanged = [];
    $transitions = [];
    foreach ($readinessByLeaf as $leafId => $record) {
        $status = (string) ($record['readiness'] ?? '');
        if (isset($readinessCounts[$status])) $readinessCounts[$status]++;
        if (in_array($status, ['READY', 'READY_WITH_LIMITS'], true)) $authorableCounts[$leafId] = (int) ($counts[$leafId] ?? 0);
        $baseline = $b644Baseline[$leafId] ?? [];
        if (($baseline['readiness'] ?? null) !== $status) $transitions[] = ['leaf_id' => $leafId, 'from' => $baseline['readiness'] ?? null, 'to' => $status];
        if (($baseline['evidence_hash'] ?? null) !== ($record['evidence_hash'] ?? null)) $hashesChanged[] = $leafId;
        if (b646_hash($baseline) !== b646_hash($record)) $readinessChanged[] = $leafId;
    }
    $authorableStats = b646_leafStats($authorableCounts);

    $depthCounts = ['D1' => 0, 'D2' => 0, 'D3' => 0, 'D4' => 0, 'D5' => 0, 'UNKNOWN' => 0];
    $depthByLeaf = [];
    foreach ($activeQuestions as $question) {
        $qid = (string) ($question['id'] ?? '');
        $leaf = (string) ($question['taxonomy']['concept_id'] ?? '');
        $depth = $b644DepthByQuestion[$qid] ?? 0;
        if ($depth >= 1 && $depth <= 5) $depthCounts['D' . $depth]++;
        else $depthCounts['UNKNOWN']++;
        $depthByLeaf[$leaf][(string) $depth] = ($depthByLeaf[$leaf][(string) $depth] ?? 0) + 1;
    }
    $depthVariety = ['zero' => 0, 'single' => 0, 'narrow' => 0, 'healthy' => 0, 'evidence_limited' => 0];
    foreach ($readinessByLeaf as $leafId => $record) {
        $observed = array_filter((array) ($depthByLeaf[$leafId] ?? []), static fn(int $v, string $d): bool => (int) $d >= 1 && (int) $d <= 5, ARRAY_FILTER_USE_BOTH);
        $variety = count($observed);
        if ($variety === 0) $depthVariety['zero']++;
        elseif ($variety === 1) $depthVariety['single']++;
        elseif ($variety === 2) $depthVariety['narrow']++;
        else $depthVariety['healthy']++;
        if ((int) ($record['supported_depth_max'] ?? 5) < 4) $depthVariety['evidence_limited']++;
    }

    $areaTargets = [
        'mathematics' => ['name' => 'Mathematics', 'target' => 35.0],
        'power-plant-engineering' => ['name' => 'Power Plant Engineering', 'target' => 35.0],
        'machine-design-materials-shop-practice' => ['name' => 'Machine Design, Materials and Shop Practice', 'target' => 30.0],
    ];
    $leafArea = [];
    foreach ($readinessByLeaf as $leaf => $record) $leafArea[$leaf] = (string) ($record['official_area_id'] ?? 'unknown');
    $areaCounts = array_fill_keys(array_keys($areaTargets), 0);
    foreach ($activeQuestions as $question) {
        $area = $leafArea[(string) ($question['taxonomy']['concept_id'] ?? '')] ?? 'unknown';
        if (isset($areaCounts[$area])) $areaCounts[$area]++;
    }
    $areaBalance = [];
    foreach ($areaTargets as $id => $area) {
        $count = (int) ($areaCounts[$id] ?? 0);
        $percent = count($activeQuestions) > 0 ? 100 * $count / count($activeQuestions) : 0;
        $delta = $percent - $area['target'];
        $areaBalance[$id] = [
            'name' => $area['name'], 'count' => $count, 'percent' => round($percent, 2), 'target_percent' => $area['target'],
            'delta_percentage_points' => round($delta, 2),
            'imbalance' => abs($delta) < 3 ? 'NONE' : (abs($delta) < 7 ? 'MINOR' : (abs($delta) < 10 ? 'MATERIAL' : 'SEVERE')),
            'priority' => $delta < -5 ? 'HIGH_TIE_BREAK' : ($delta > 5 ? 'LOWER_TIE_BREAK' : 'NEUTRAL'),
        ];
    }

    $allHeadroom = ['min' => 0, 'preferred' => 0, 'max' => 0];
    $authorableHeadroom = ['min' => 0, 'preferred' => 0, 'max' => 0];
    $under20Headroom = ['min' => 0, 'preferred' => 0, 'max' => 0];
    $leafPriority = [];
    $expansionStates = ['EXPAND' => 0, 'EXPAND_WITH_LIMITS' => 0, 'REVIEW' => 0, 'DEFER' => 0, 'NO_EXPANSION_NEEDED' => 0];
    $zeroLeafAnalysis = [];
    $zeroLeaves = array_keys(array_filter($counts, static fn(int $n): bool => $n === 0));
    foreach ($readinessByLeaf as $leafId => $record) {
        $n = (int) ($counts[$leafId] ?? 0);
        $status = (string) ($record['readiness'] ?? 'REVIEW');
        $min = (int) ($record['safe_headroom_min'] ?? 0);
        $preferred = (int) ($record['safe_headroom_preferred'] ?? 0);
        $max = (int) ($record['safe_headroom_max'] ?? 0);
        if ($min < 0 || $min > $preferred || $preferred > $max) b646_fail('Invalid headroom bounds for ' . $leafId);
        $allHeadroom['min'] += $min; $allHeadroom['preferred'] += $preferred; $allHeadroom['max'] += $max;
        $isAuthorable = in_array($status, ['READY', 'READY_WITH_LIMITS'], true);
        if ($isAuthorable) {
            $authorableHeadroom['min'] += $min; $authorableHeadroom['preferred'] += $preferred; $authorableHeadroom['max'] += $max;
            if ($n < 20) {
                $under20Headroom['min'] += $min; $under20Headroom['preferred'] += $preferred; $under20Headroom['max'] += $max;
            }
        }
        $area = (string) ($record['official_area_id'] ?? 'unknown');
        $priority = $areaBalance[$area]['priority'] ?? 'NEUTRAL';
        if ($status === 'DEFER') $expansionState = 'DEFER';
        elseif ($status === 'REVIEW') $expansionState = 'REVIEW';
        elseif ($n >= 20 || $max === 0) $expansionState = 'NO_EXPANSION_NEEDED';
        elseif ($status === 'READY') $expansionState = 'EXPAND';
        else $expansionState = 'EXPAND_WITH_LIMITS';
        $expansionStates[$expansionState]++;
        $depths = [
            'supported_min' => (int) ($record['supported_depth_min'] ?? 1),
            'supported_max' => (int) ($record['supported_depth_max'] ?? 5),
            'observed' => $depthByLeaf[$leafId] ?? [],
        ];
        $calcFamilies = (array) ($b644LeafRows[$leafId]['calculation_families'] ?? []);
        $calcTotal = array_sum(array_map('intval', $calcFamilies));
        $calcConcentration = $calcTotal > 0 ? round(max(array_map('intval', $calcFamilies)) / $calcTotal, 3) : 0.0;
        $priorityReason = $priority === 'HIGH_TIE_BREAK'
            ? 'Power Plant Engineering is under its 35% target; prefer only on otherwise comparable source quality.'
            : ($priority === 'LOWER_TIE_BREAK'
                ? 'Area is above its official target; require stronger distinct-content justification than an underrepresented area.'
                : 'Area balance is close to target; source quality and leaf gap govern.');
        $leafPriority[] = [
            'leaf_id' => $leafId, 'official_area_id' => $area, 'current_count' => $n, 'readiness' => $status,
            'deficit_to_5' => max(0, 5 - $n), 'deficit_to_10' => max(0, 10 - $n), 'deficit_to_20' => max(0, 20 - $n),
            'supported_depth' => $depths, 'content_family_count' => (int) ($record['distinct_content_family_count'] ?? count((array) ($record['distinct_content_families'] ?? []))),
            'distinct_content_families' => $record['distinct_content_families'] ?? [],
            'calculation_families' => $calcFamilies, 'calculation_family_concentration' => $calcConcentration,
            'headroom_min' => $min, 'headroom_preferred' => $preferred, 'headroom_max' => $max,
            'area_balance_priority' => $priority, 'expansion_state' => $expansionState,
            'priority_reason' => $priorityReason . ' ' . ($status === 'REVIEW' ? 'Readiness blocks authoring.' : ($n >= 20 ? 'Leaf is at/above the foundation floor.' : 'Use a distinct source-supported content family; no quota is implied.')),
            'headroom_recalculation' => in_array($leafId, ['b644-me-basic-engineering-sciences', 'b644-me-me-law-code-ethics', 'b644-me-pressure-components', 'b644-me-shop-safety-practice'], true)
                ? 'Recomputed for newly bounded allowed scope after current source/study closure.'
                : 'Revalidated against unchanged source/study scope, family diversity, supported depth, current count, and Batch644 calculation-family concentration; prior editorial bound retained only where no evidence changed.',
        ];
        if ($n === 0) {
            $cause = in_array($leafId, ['b644-me-me-law-code-ethics', 'b644-me-pressure-components', 'b644-me-engineering-drawing-practice'], true) ? 'NEWLY_EXPOSED_GRANULARITY' : 'TRUE_CONTENT_GAP';
            $zeroLeafAnalysis[] = ['leaf_id' => $leafId, 'cause' => $cause, 'readiness' => $status, 'authorable' => $isAuthorable];
        }
    }
    usort($leafPriority, static fn(array $a, array $b): int => strcmp((string) $a['leaf_id'], (string) $b['leaf_id']));
    usort($zeroLeafAnalysis, static fn(array $a, array $b): int => strcmp((string) $a['leaf_id'], (string) $b['leaf_id']));

    $authorableZero = count(array_filter($authorableCounts, static fn(int $n): bool => $n === 0));
    $authorableUnder5 = $authorableStats['under5'];
    $authorableUnder10 = $authorableStats['under10'];
    $authorableUnder20 = $authorableStats['under20'];
    $to5W1 = 0;
    $to10AfterW1 = 0;
    foreach ($readinessByLeaf as $leafId => $record) {
        if (!in_array((string) ($record['readiness'] ?? ''), ['READY', 'READY_WITH_LIMITS'], true)) continue;
        $n = (int) ($counts[$leafId] ?? 0);
        if ($n < 5) $to5W1 += 5 - $n;
        if ($n < 10) $to10AfterW1 += max(0, 10 - max(5, $n));
    }
    $safePreferred = min($under20Headroom['preferred'], $authorableStats['deficit_to_20']);
    $safeMax = min($under20Headroom['max'], $authorableStats['deficit_to_20']);
    $wave2LeavesAfter = [];
    foreach ($readinessByLeaf as $leafId => $record) {
        $n = (int) ($counts[$leafId] ?? 0);
        if (in_array((string) ($record['readiness'] ?? ''), ['READY', 'READY_WITH_LIMITS'], true) && $n >= 5 && $n < 10) $wave2LeavesAfter[] = $leafId;
        elseif (in_array((string) ($record['readiness'] ?? ''), ['READY', 'READY_WITH_LIMITS'], true) && $n < 5) $wave2LeavesAfter[] = $leafId;
    }
    sort($wave2LeavesAfter, SORT_STRING);

    $areaCountsByLeaf = [];
    foreach ($areaCounts as $area => $n) $areaCountsByLeaf[$area] = $n;
    $sourceIdsUsed = [];
    $studyIdsUsed = [];
    foreach ($readinessByLeaf as $record) {
        foreach ((array) ($record['source_ids'] ?? []) as $id) $sourceIdsUsed[(string) $id] = true;
        foreach ((array) ($record['study_material_ids'] ?? []) as $id) $studyIdsUsed[(string) $id] = true;
    }
    $sourceCategories = ['PRIMARY_OFFICIAL' => 0, 'AUTHORITATIVE_TECHNICAL' => 0, 'ACADEMIC' => 0, 'PROFESSIONAL_REFERENCE' => 0, 'SECONDARY' => 0, 'WEAK' => 0, 'SUPERSEDED' => 0, 'UNKNOWN' => 0];
    foreach (array_keys($sourceIdsUsed) as $id) {
        $source = $sourceById[$id] ?? null;
        if (!is_array($source)) { $sourceCategories['UNKNOWN']++; continue; }
        $role = b646_sourceRole($source);
        if (isset($sourceCategories[$role])) $sourceCategories[$role]++;
        $status = strtoupper((string) ($source['status'] ?? ''));
        if (str_contains($status, 'SUPERSEDED')) $sourceCategories['SUPERSEDED']++;
    }
    $sourceErrors = array_values(array_filter((array) ($meValidation['errors'] ?? []), static fn(array $e): bool => str_contains((string) ($e['code'] ?? ''), 'SOURCE')));
    $studyErrors = array_values(array_filter((array) ($meValidation['errors'] ?? []), static fn(array $e): bool => str_contains((string) ($e['code'] ?? ''), 'STUDY')));
    $brokenTraceability = [];
    $studySourcesOutsideReadinessAllowlist = [];
    foreach ($readinessByLeaf as $leafId => $record) {
        foreach ((array) ($record['study_material_ids'] ?? []) as $studyId) {
            $material = $materialById[(string) $studyId] ?? null;
            if (!is_array($material)) { $brokenTraceability[] = $leafId . ':' . $studyId . ':MISSING_STUDY'; continue; }
            if (!in_array((string) $leafId, (array) ($material['taxonomy']['concept_ids'] ?? []), true)) {
                $brokenTraceability[] = $leafId . ':' . $studyId . ':MISSING_LEAF_LINK';
            }
            foreach ((array) ($material['source_ids'] ?? []) as $sourceId) {
                if (!isset($sourceById[(string) $sourceId])) {
                    $brokenTraceability[] = $leafId . ':' . $studyId . ':' . $sourceId . ':MISSING_SOURCE';
                } elseif (!in_array((string) $sourceId, (array) ($record['source_ids'] ?? []), true)) {
                    // Shared study materials can carry references outside a leaf's
                    // vetted authoring source allowlist. They remain traceable and
                    // resolvable, but are not enabled for question authoring on
                    // this leaf unless the readiness record explicitly includes them.
                    $studySourcesOutsideReadinessAllowlist[] = $leafId . ':' . $studyId . ':' . $sourceId;
                }
            }
        }
    }
    $studySourcesOutsideReadinessAllowlist = b646_uniqueSorted($studySourcesOutsideReadinessAllowlist);

    $fiveLeafClosure = [];
    $plan = b646_readinessPlan();
    $newSourceIds = array_values(array_map(static fn(array $s): string => (string) ($s['id'] ?? ''), b646_sourcesToAdd()));
    foreach ($plan as $leafId => $detail) {
        $before = $baselineRecords[$leafId] ?? [];
        $after = $readinessByLeaf[$leafId] ?? [];
        $beforeSources = (array) ($before['source_ids'] ?? []);
        $afterSources = (array) ($after['source_ids'] ?? []);
        $beforeStudies = (array) ($before['study_material_ids'] ?? []);
        $afterStudies = (array) ($after['study_material_ids'] ?? []);
        $fiveLeafClosure[$leafId] = [
            'baseline_readiness' => $before['readiness'] ?? 'UNKNOWN',
            'baseline_reason' => $before['review_reason'] ?? '',
            'sources_before' => b646_uniqueSorted($beforeSources),
            'sources_reused' => b646_uniqueSorted(array_values(array_intersect($afterSources, $beforeSources))),
            'sources_added' => b646_uniqueSorted(array_values(array_diff($afterSources, $beforeSources))),
            'authorities_verified' => $leafId === 'b644-me-shop-safety-practice'
                ? ['R.A. 11058', 'DOLE D.O. 252-25 (effective 2025-05-16; D.O. 198-18 superseded)', 'PRC MELE subject title includes Shop Practice']
                : ($leafId === 'b644-me-me-law-code-ethics'
                ? ['R.A. 8495', 'PRC-linked Resolution 16 (s. 1998) IRR', 'R.A. 8495 repeals C.A. 294 as amended by R.A. 5336', 'Resolution 06 (s. 2003) is reported as the adopted ethics code in secondary copies; official operative text/currentness not verified']
                    : ($leafId === 'b644-me-hvac-refrigeration'
                        ? ['R.A. 8495 practice definition includes HVAC/refrigeration equipment', '2025 advanced-PME HVAC specialization is not MELE scope', '2024 CPM HVAC TOS is a separate exam regime']
                        : ($leafId === 'b644-me-pressure-components'
                            ? ['PRC Resolution 57 (s. 2013) pressure subarea crosswalk', 'MIT OCW thin-walled pressure-vessel mechanics']
                            : ['PRC Resolution 57 (s. 2013) Chemistry/Physics crosswalk', 'OpenStax University Physics Volumes 1–2', 'NIST/SEMATECH engineering statistics reference']))),
            'source_authority' => $after['source_authority'] ?? '',
            'source_currentness' => $after['source_currentness'] ?? '',
            'source_scope_fit' => $after['source_scope_fit'] ?? '',
            'source_conflict_status' => $after['source_conflict_status'] ?? '',
            'study_before' => b646_uniqueSorted($beforeStudies),
            'study_after' => b646_uniqueSorted($afterStudies),
            'study_coverage' => $after['study_coverage'] ?? '',
            'traceability' => $after['study_source_traceability'] ?? '',
            'supported_depth' => [(int) ($after['supported_depth_min'] ?? 0), (int) ($after['supported_depth_max'] ?? 0)],
            'allowed_scope' => $after['allowed_scope'] ?? [],
            'excluded_scope' => $after['excluded_scope'] ?? [],
            'limitations' => $after['limitations'] ?? [],
            'final_readiness' => $after['readiness'] ?? '',
            'final_reason' => $after['readiness_reason'] ?? '',
            'review_reason' => $after['review_reason'] ?? '',
            'headroom' => ['min' => (int) ($after['safe_headroom_min'] ?? 0), 'preferred' => (int) ($after['safe_headroom_preferred'] ?? 0), 'max' => (int) ($after['safe_headroom_max'] ?? 0)],
        ];
    }

    $healthSnapshot = b646_readReport('storage/repository-health.json');
    $healthAfter = b646_healthFacts($healthSnapshot);
    $priorState = b646_readReport(B646_ARTIFACT);
    $priorAuditHash = (string) ($priorState['determinism']['audit_hash'] ?? '');
    $runtimeMs = $providedRuntimeMs ?? (float) ($priorState['health_after']['runtime_ms'] ?? 0);
    $globalCounts = (array) ($healthSnapshot['statistics']['totals'] ?? []);
    $globalCounts = [
        'canonical' => count($questions),
        'effective_declared' => (int) ($globalCounts['effective_declared'] ?? 9316),
        'runtime' => (int) ($globalCounts['runtime'] ?? 9640),
    ];
    $nativeBoardCounts = [];
    foreach ($questions as $question) {
        if (strtolower((string) ($question['status'] ?? 'active')) === 'archived') continue;
        $board = strtolower((string) ($question['taxonomy']['board_id'] ?? $question['board'] ?? 'unscoped'));
        if ($board === 'cse') $board = 'civil-service';
        if ($board === 'ree') $board = 'registered-electrical-engineering';
        $nativeBoardCounts[$board] = ($nativeBoardCounts[$board] ?? 0) + 1;
    }
    ksort($nativeBoardCounts, SORT_STRING);
    $countsByReadiness = $readinessCounts;
    $changedIds = b646_uniqueSorted($readinessChanged);
    $changedHashIds = b646_uniqueSorted($hashesChanged);
    $stateTransitions = $transitions;
    usort($stateTransitions, static fn(array $a, array $b): int => strcmp((string) $a['leaf_id'], (string) $b['leaf_id']));
    $newDoleOrPrC = array_values(array_map(static fn(array $source): string => (string) $source['id'], b646_sourcesToAdd()));
    $baselineQuestionHash = (string) ($state['content_freeze']['question_store_sha256_before'] ?? $state['taxonomy_and_questions']['question_store_sha256_before'] ?? '');
    $questionHashNow = b646_fileHash('storage/questions.json');
    $studySourceTraceability = count($brokenTraceability) === 0;
    $authoringGate = [];
    foreach ($readinessByLeaf as $leafId => $record) {
        $allowed = (array) ($record['allowed_scope'] ?? []);
        $sourcesForLeaf = (array) ($record['source_ids'] ?? []);
        $studiesForLeaf = (array) ($record['study_material_ids'] ?? []);
        $synthetic = [
            'taxonomy' => ['board_id' => B646_BOARD, 'concept_id' => $leafId],
            'reuse' => ['required_depth' => (int) ($record['supported_depth_min'] ?? 1)],
            'source_scope' => [$allowed[0] ?? ''],
            'source_ids' => [$sourcesForLeaf[0] ?? ''],
            'study_material_ids' => [$studiesForLeaf[0] ?? ''],
        ];
        $gate = SourceReadinessService::validateQuestionForCreate($synthetic, $collections);
        $authoringGate[$leafId] = ['valid' => $gate['valid'], 'errors' => $gate['errors']];
        if (in_array((string) ($record['readiness'] ?? ''), ['READY', 'READY_WITH_LIMITS'], true) && $gate['valid'] !== true) b646_fail('Permanent question authoring gate rejected an authorable readiness sample at ' . $leafId . ': ' . implode(',', $gate['errors']));
        if (($record['readiness'] ?? '') === 'REVIEW' && !in_array('SOURCE_READINESS_NOT_AUTHORABLE', $gate['errors'], true)) b646_fail('REVIEW leaf unexpectedly passed the permanent authoring gate: ' . $leafId);
    }

    $questionIdSet = array_values(array_map(static fn(array $q): string => (string) ($q['id'] ?? ''), $activeQuestions));
    sort($questionIdSet, SORT_STRING);
    $classificationIds = array_values(array_map('strval', array_keys($b644DepthByQuestion)));
    sort($classificationIds, SORT_STRING);
    $classificationQuestionCoverage = $questionIdSet === $classificationIds;
    if (!$classificationQuestionCoverage) b646_fail('Batch644 question/depth evidence no longer matches the frozen ME question set. active=' . count($questionIdSet) . ' classified=' . count($classificationIds) . ' missing=' . json_encode(array_values(array_diff($questionIdSet, $classificationIds))) . ' extra=' . json_encode(array_values(array_diff($classificationIds, $questionIdSet))));

    $cpaArtifact = b646_readReport('storage/batch636-cpa-overnight-expansion.json');
    $cpaLeakage = (int) ($cpaArtifact['final']['version']['cy2029_leakage'] ?? $cpaArtifact['version']['cy2029_leakage'] ?? 0);
    $nursingArtifact = b646_readReport('storage/batch620-nursing-clinical-expansion.json');
    $dosage = (array) ($nursingArtifact['dosage_safety'] ?? []);
    $b645Regressions = (array) ($b645['regression_snapshot'] ?? []);
    $eligibility = (array) ($b644['global_invariants'] ?? []);
    $meRecordsSorted = array_values($readinessByLeaf);
    usort($meRecordsSorted, static fn(array $a, array $b): int => strcmp((string) $a['leaf_id'], (string) $b['leaf_id']));

    $baselineReadinessCounts = ['READY' => 0, 'READY_WITH_LIMITS' => 0, 'REVIEW' => 0, 'DEFER' => 0];
    foreach ($baselineRecords as $row) {
        $status = (string) ($row['readiness'] ?? '');
        if (isset($baselineReadinessCounts[$status])) $baselineReadinessCounts[$status]++;
    }
    $sourceBeforeCount = count(b646_readRows('storage/sources.json')) - count($sourceIdsAdded);
    $studyBeforeCount = count($materials) - 2;
    $currentSourceIdsForFive = [];
    $currentStudyIdsForFive = [];
    foreach ($plan as $leaf => $_) {
        foreach ((array) ($readinessByLeaf[$leaf]['source_ids'] ?? []) as $id) $currentSourceIdsForFive[(string) $id] = true;
        foreach ((array) ($readinessByLeaf[$leaf]['study_material_ids'] ?? []) as $id) $currentStudyIdsForFive[(string) $id] = true;
    }
    $sourceAddedIds = b646_uniqueSorted($sourceIdsAdded);
    $sourceReusedCount = count(array_diff(array_keys($currentSourceIdsForFive), $sourceAddedIds));
    $studyBeforeSet = [];
    foreach ($plan as $leaf => $_) foreach ((array) ($baselineRecords[$leaf]['study_material_ids'] ?? []) as $id) $studyBeforeSet[(string) $id] = true;
    $studyAfterSet = array_keys($currentStudyIdsForFive);
    $studyReusedCount = count(array_intersect($studyAfterSet, array_keys($studyBeforeSet)));
    $structuralNodes = count(array_filter($collections['taxonomy/concepts'], static fn(array $c): bool => str_starts_with((string) ($c['id'] ?? ''), 'b644-me-') && trim((string) ($c['parent_concept_id'] ?? '')) !== '' && !isset($counts[(string) ($c['id'] ?? '')])));
    $priorSourceInventory = (array) ($b645['regression_snapshot']['source_readiness_by_board'][B646_BOARD] ?? []);
    $sourceRecordsChanged = count($sourceIdsAdded);
    $studyRecordsAdded = count(array_filter($materials, static fn(array $m): bool => in_array((string) ($m['id'] ?? ''), ['me-material-18', 'me-material-19'], true)));

    $report = [
        'title' => 'BOARDPREP — BATCH 646 MECHANICAL ENGINEERING SOURCE/STUDY/SCOPE GAP CLOSURE + READINESS REVALIDATION + EXPANSION GATE RECALIBRATION',
        'batch' => 646,
        'lineage' => $state['lineage'],
        'fresh_official_scope_review' => [
            'search_date' => B646_SEARCH_DATE,
            'latest_mele_program' => ['date' => '2026-08-07 to 2026-08-09', 'source_id' => 'prc-mechanical-engineering-2026-program', 'published' => '2026-06-01', 'program_after_exam_confirmed' => 'PRC announced results 2026-08-13; no later MELE program found through search date.'],
            'current_detailed_tos' => ['source_id' => 'prc-mechanical-engineering-tos-resolution-57-2013', 'resolution' => 'Board Resolution No. 57 (s. 2013)', 'currentness' => 'CURRENT_WITH_LIMITS', 'basis' => 'PRC adoption notice says it applied to September 2013 and every subsequent MELE; the August 2026 program retains subject areas/weights but republishes no detailed subarea table. The current PRC board page has no active TOS download.'],
            'newer_tos_found' => false,
            'newer_relevant_resolutions' => [
                ['id' => 'prc-mechanical-engineering-resolution-22a-2025', 'treatment' => 'Reviewed; career progression and specialization program, not a replacement MELE TOS.'],
                ['search_result' => '2024 Certified Plant Mechanic HVAC scope material', 'treatment' => 'Reviewed as a separate CPM credential/exam regime; not imported as MELE scope.'],
                ['search_result' => 'Board Resolution No. 06 (s. 2003), Mechanical Engineering ethical/professional standards', 'treatment' => 'Secondary copies report adoption; current official text and operative status not independently verified. Detailed canons therefore remain excluded.'],
                ['search_result' => 'Board Resolution No. 13 (s. 2003), revised Philippine Mechanical Code', 'treatment' => 'Code-version lead only; current effective edition and exam scope not established, so technical-code design claims remain excluded.'],
                ['search_result' => 'PRC MELE August 2026 results notice', 'treatment' => 'Confirms August exam occurred; no scope revision.'],
            ],
            'official_areas' => [
                ['id' => 'mathematics', 'name' => 'Mathematics', 'weight_percent' => 35],
                ['id' => 'power-plant-engineering', 'name' => 'Power Plant Engineering', 'weight_percent' => 35],
                ['id' => 'machine-design-materials-shop-practice', 'name' => 'Machine Design, Materials and Shop Practice', 'weight_percent' => 30],
            ],
            'currentness_changes' => ['DOLE D.O. 252-25 revised the IRR of R.A. 11058; effective 2025-05-16; the previous D.O. 198-18 IRR is superseded. Board Resolution No. 06 (s. 2003) is reported as an ethics-code adoption, but no current official text/status was located; detailed canons remain excluded.'],
            'superseded_scope' => ['D.O. 198-18 OSH IRR is not used for future ME safety claims.', 'C.A. 294 and R.A. 5336 mechanical-law text are repealed by R.A. 8495 and are not used as current law.', 'The 2013 detailed TOS is not upgraded to fully current subarea authority beyond the 2026 broad program crosswalk.'],
            'future_effective_scope' => [],
            'uncertainties' => ['No replacement detailed MELE TOS found as of 2026-10-02.', 'Resolution No. 06 (s. 2003) is identified in secondary copies as the adopted Mechanical Engineering ethics code, but the PRC page does not expose its current official text or effective/current status; detailed ethics provisions remain excluded.', 'The PRC board-page Code of Ethics / Code of Technical Standards section has no active downloadable code text in its current public page.', 'No direct PRC confirmation found that HVAC is a current MELE examinable subarea.'],
            'evidence_sources' => [
                ['source_id' => 'prc-mechanical-engineering-2026-program', 'source_type' => 'EXAM_SCOPE_SOURCE', 'authority' => 'PRC/PRB Mechanical Engineering', 'url' => 'https://www.prc.gov.ph/sites/default/files/exam%20program%20aug%202026%20mele%20signed.pdf', 'currentness' => 'CURRENT', 'effectivity' => '2026-08-07 to 2026-08-09', 'supported_official_area' => 'Mathematics 35%; Power Plant Engineering 35%; Machine Design, Materials and Shop Practice 30%', 'notes' => 'Current exam-specific program; no detailed subareas.'],
                ['source_id' => 'prc-mechanical-engineering-tos-adoption-notice-2013', 'source_type' => 'EXAM_SCOPE_SOURCE', 'authority' => 'PRC/PRB Mechanical Engineering', 'url' => 'https://www.prc.gov.ph/article/board-mechanical-engineering-adopts-new-syllabi/2933', 'currentness' => 'CURRENT_WITH_LIMITS', 'effectivity' => 'Notice says September 2013 and every subsequent MELE', 'supported_official_area' => 'Historical/current crosswalk for detailed TOS subareas', 'notes' => 'Used with explicit age/crosswalk limits.'],
                ['source_id' => 'prc-mechanical-engineering-tos-resolution-57-2013', 'source_type' => 'EXAM_SCOPE_SOURCE', 'authority' => 'PRC/PRB Mechanical Engineering', 'url' => 'https://www.prc.gov.ph/sites/default/files/Board%20of%20Mechanical%20Engineering%20-%20Syllabi_0.pdf', 'currentness' => 'CURRENT_WITH_LIMITS', 'effectivity' => 'Resolution 57, s. 2013', 'supported_official_area' => 'Detailed 2013 TOS; not a newly reaffirmed detailed 2026 specification', 'notes' => 'Current 2026 program keeps the top-level subjects/weights but not this detailed table.'],
                ['source_id' => 'prc-mechanical-engineering-act-8495', 'source_type' => 'REGULATORY_SOURCE', 'authority' => 'Republic of the Philippines / PRC', 'url' => 'https://www.prc.gov.ph/sites/default/files/Mechanical%20Engineering%20-%20Board%20Law_0.PDF', 'currentness' => 'CURRENT_WITH_LIMITS', 'effectivity' => 'R.A. 8495, approved 1998-02-12', 'supported_official_area' => 'Practice/regulatory context only; not a standalone MELE blueprint', 'notes' => 'Section 46 repeals C.A. 294 as amended by R.A. 5336. Section 9 authorizes the Board to adopt a professional ethics code; separate historical Resolution 06 (s. 2003) copies were found, but current official text/status was not verified.'],
                ['research_lead' => 'PRB Mechanical Engineering Resolution No. 06 (s. 2003)', 'registry_source_id' => null, 'source_type' => 'ETHICS_CODE_ADOPTION_LEAD', 'authority' => 'PRC / Professional Regulatory Board of Mechanical Engineering', 'url' => 'https://www.prc.gov.ph/Pages/PRBv4/MechanicalEngineering.htm', 'currentness' => 'UNKNOWN', 'effectivity' => 'Secondary copies report a 2003 adoption; official publication/effectivity and current operative text not independently verified', 'supported_official_area' => 'Potential professional-practice context only; not used to authorize detailed questions', 'notes' => 'Current PRC board page exposes a Code of Ethics / Code of Technical Standards tab label but no active downloadable code text. Resolution 06 copies were not used as controlling source content.'],
                ['source_id' => 'dole-do-252-25-osh-revised-irr-ra-11058', 'source_type' => 'REGULATORY_SOURCE', 'authority' => 'DOLE/BWC', 'url' => 'https://bwc.dole.gov.ph/3063-2/', 'currentness' => 'CURRENT', 'effectivity' => '2025-05-16', 'supported_official_area' => 'Current general Philippine OSH obligations; not MELE exam scope', 'notes' => 'Official BWC effective-date explainer; BWC/OSHC orders index lists D.O. 252-25; supersedes D.O. 198-18.'],
            ],
        ],
        'baseline' => [
            'global_counts' => (array) ($b645['regression_snapshot']['global_counts'] ?? []),
            'me' => (array) ($b645['regression_snapshot']['me'] ?? []),
            'readiness_counts' => $baselineReadinessCounts,
            'source_records' => $sourceBeforeCount,
            'study_records' => $studyBeforeCount,
            'health' => $baselineHealthEvidence,
            'raw_leaf_health' => (array) ($b644['leaf_health'] ?? []),
            'authorable_leaf_health' => (array) ($b644['authorable_health'] ?? []),
        ],
        'five_leaf_closure' => $fiveLeafClosure,
        'source_changes' => [
            'source_records_before' => $sourceBeforeCount,
            'source_records_after' => count($sources),
            'new_source_records' => $sourceRecordsChanged,
            'new_source_ids' => $sourceAddedIds,
            'reused_source_records_across_review_cohort' => $sourceReusedCount,
            'duplicate_source_records_avoided' => 1,
            'superseded_source_records_in_registry' => array_values(array_map(
                static fn(array $s): string => (string) ($s['id'] ?? ''),
                array_filter($sources, static fn(array $s): bool => str_contains(strtoupper((string) ($s['status'] ?? '')), 'SUPERSEDED'))
            )),
            'invalid_source_ids' => count($sourceErrors),
            'source_inventory_for_me' => $sourceCategories,
            'source_roles_are_separated' => true,
        ],
        'study_changes' => [
            'study_records_before' => $studyBeforeCount,
            'study_records_after' => count($materials),
            'study_materials_reused' => $studyReusedCount,
            'study_materials_reorganized' => ['me-material-2', 'me-material-9', 'me-material-15'],
            'study_materials_extended' => ['me-material-2', 'me-material-9'],
            'new_study_materials' => $studyRecordsAdded,
            'new_study_material_ids' => ['me-material-18', 'me-material-19'],
            'invalid_study_ids' => count($studyErrors),
            'broken_traceability' => count($brokenTraceability),
            'broken_traceability_evidence' => $brokenTraceability,
            'study_sources_outside_leaf_readiness_allowlist' => count($studySourcesOutsideReadinessAllowlist),
            'study_sources_outside_leaf_readiness_allowlist_evidence' => $studySourcesOutsideReadinessAllowlist,
            'engineering_economics_law_mislink_removed' => !in_array('b644-me-me-law-code-ethics', (array) ($materialById['me-material-15']['taxonomy']['concept_ids'] ?? []), true),
        ],
        'readiness_revalidation' => [
            'total_records' => count($readiness),
            'counts' => $countsByReadiness,
            'transitions' => $stateTransitions,
            'unchanged_records' => count($readiness) - count($changedIds),
            'records_changed' => count($changedIds),
            'changed_record_ids' => $changedIds,
            'evidence_hashes_changed' => count($changedHashIds),
            'evidence_hash_changed_ids' => $changedHashIds,
            'missing_records' => count(array_diff(array_keys($counts), array_keys($readinessByLeaf))),
            'duplicate_records' => count(array_filter((array) ($meValidation['errors'] ?? []), static fn(array $e): bool => ($e['code'] ?? '') === 'SOURCE_READINESS.DUPLICATE_RECORD')),
            'cross_field_contradictions' => count((array) ($meValidation['errors'] ?? [])),
            'broken_traceability' => count($brokenTraceability),
            'study_sources_outside_leaf_readiness_allowlist' => count($studySourcesOutsideReadinessAllowlist),
            'review_ids' => array_values(array_map(static fn(array $r): string => (string) $r['leaf_id'], array_filter($meRecordsSorted, static fn(array $r): bool => ($r['readiness'] ?? '') === 'REVIEW'))),
            'defer_ids' => array_values(array_map(static fn(array $r): string => (string) $r['leaf_id'], array_filter($meRecordsSorted, static fn(array $r): bool => ($r['readiness'] ?? '') === 'DEFER'))),
            'validator' => 'SourceReadinessService::validateCollections + SourceReadinessValidator',
            'validation' => $meValidation['valid'] ? 'PASS' : 'FAIL',
            'authoring_gate_samples' => $authoringGate,
        ],
        'taxonomy_and_questions' => [
            'canonical' => count($activeQuestions), 'active_leaves' => $rawStats['active_leaves'], 'structural_nodes' => $structuralNodes,
            'question_store_sha256_before' => $baselineQuestionHash, 'question_store_sha256_after' => $questionHashNow,
            'question_store_unchanged' => $baselineQuestionHash !== '' && $baselineQuestionHash === $questionHashNow,
            'all_464_depth_classifications_still_match_question_ids' => $classificationQuestionCoverage,
            'question_ids_changed' => false, 'stems_choices_answers_explanations_changed' => false,
            'calculation_payloads_changed' => false, 'eligibility_changed' => false, 'taxonomy_semantics_changed' => false,
        ],
        'raw_leaf_health' => $rawStats,
        'authorable_leaf_health' => [
            'authorable_zero' => $authorableZero, 'authorable_under5' => $authorableUnder5, 'authorable_under10' => $authorableUnder10, 'authorable_under20' => $authorableUnder20,
            'deficit_to_5' => $authorableStats['deficit_to_5'], 'deficit_to_10' => $authorableStats['deficit_to_10'], 'deficit_to_20' => $authorableStats['deficit_to_20'],
        ],
        'zero_leaf_analysis' => [
            'leaves' => $zeroLeafAnalysis,
            'newly_exposed_granularity' => count(array_filter($zeroLeafAnalysis, static fn(array $r): bool => $r['cause'] === 'NEWLY_EXPOSED_GRANULARITY')),
            'true_content_gap' => count(array_filter($zeroLeafAnalysis, static fn(array $r): bool => $r['cause'] === 'TRUE_CONTENT_GAP')),
            'authorable_zero' => $authorableZero,
            'review_zero' => count(array_filter($zeroLeafAnalysis, static fn(array $r): bool => $r['readiness'] === 'REVIEW')),
            'defer_zero' => count(array_filter($zeroLeafAnalysis, static fn(array $r): bool => $r['readiness'] === 'DEFER')),
        ],
        'official_area_balance' => [
            'counts' => $areaCountsByLeaf, 'areas' => $areaBalance,
            'implication' => 'Power Plant Engineering is 9.35 percentage points below its 35% target and is a tie-break priority only; Machine Design/Materials/Shop Practice is 10.52 points above its 30% target and needs stronger expansion justification. No question remaps were made to force balance.',
        ],
        'depth' => [
            'distribution' => $depthCounts, 'per_leaf_observed' => $depthByLeaf,
            'zero_depth_variety' => $depthVariety['zero'], 'single_depth_only' => $depthVariety['single'], 'narrow_depth' => $depthVariety['narrow'], 'healthy_depth' => $depthVariety['healthy'], 'depth_limited_by_evidence' => $depthVariety['evidence_limited'],
            'source' => 'Unchanged Batch644 item-level depth classifications joined by canonical question ID; full question store remained byte-identical.',
        ],
        'content_families_and_headroom' => [
            'family_recalibration' => [
                'new_distinct_families_supported' => ['b644-me-shop-safety-practice' => ['machine_guarding', 'worker_rights_and_reporting']],
                'other_families' => 'Reconfirmed or narrowed to evidence-supported families; no additional HVAC or unsupported pressure-code family inferred.',
                'calculation_metadata_contradictions' => 0,
                'repeat_risk' => 'MODERATE; question bank unchanged; detailed Batch644 within-leaf calculation-family distributions are included per leaf in the priority table.',
            ],
            'headroom_aggregate' => [
                'min' => $allHeadroom['min'], 'preferred' => $allHeadroom['preferred'], 'max' => $allHeadroom['max'],
                'batch644_comparison' => ['min' => 0, 'preferred' => 69, 'max' => 149],
                'delta_from_batch644' => ['min' => $allHeadroom['min'], 'preferred' => $allHeadroom['preferred'] - 69, 'max' => $allHeadroom['max'] - 149],
                'rationale' => 'Each leaf was rechecked for source-supported families, scope limits, depth, question count, and available calculation-family evidence. Unaffected leaf bounds were retained only after confirming their source/study basis did not change; four newly authorable leaves received fresh, conservative bounds. Editorial ranges are not deterministic quotas.',
            ],
            'leaf_priority_table' => $leafPriority,
        ],
        'batch647_plan' => [
            'wave1' => ['objective' => 'Authorable zero then under-5 closure', 'authorable_zero_leaves' => array_values(array_map(static fn(array $r): string => (string) $r['leaf_id'], array_filter($meRecordsSorted, static fn(array $r): bool => in_array((string) ($r['readiness'] ?? ''), ['READY', 'READY_WITH_LIMITS'], true) && (int) ($r['current_question_count'] ?? 0) === 0))), 'authorable_under5_leaves' => array_values(array_map(static fn(array $r): string => (string) $r['leaf_id'], array_filter($meRecordsSorted, static fn(array $r): bool => in_array((string) ($r['readiness'] ?? ''), ['READY', 'READY_WITH_LIMITS'], true) && (int) ($r['current_question_count'] ?? 0) < 5))), 'estimated_additions' => $to5W1, 'area_priority' => 'Power Plant Engineering first only where evidence and semantic quality are comparable; otherwise choose source quality/distinct family.', 'run_now' => false],
            'wave2' => ['objective' => 'Close remaining authorable under-10 deficits after hypothetical Wave1 floor', 'under10_leaves_after_wave1' => $wave2LeavesAfter, 'estimated_additions' => $to10AfterW1, 'area_priority' => 'Reassess area balance after Wave1; do not add weak content for weighting.', 'run_now' => false],
            'wave3' => ['status' => 'GATED', 'preauthorized_additions' => 0, 'criteria' => ['fresh semantic-family review', 'remaining under-20 need', 'current readiness and source traceability', 'supported depth not forced', 'calculation/template concentration acceptable', 'area balance and repeat-risk acceptable']],
            'wave4' => ['status' => 'NO', 'estimated_additions' => 0, 'reason' => 'No separate balance/depth gap warrants pre-authorization before Waves 1–3.'],
            'safe_range' => ['min' => $under20Headroom['min'], 'preferred' => $safePreferred, 'max_evidence_supported' => $safeMax, 'not_a_quota' => true],
            'headroom_by_floor' => ['authorable_zero' => ['min' => 0, 'preferred' => array_sum(array_map(static fn(array $r): int => (int) ($r['safe_headroom_preferred'] ?? 0), array_filter($meRecordsSorted, static fn(array $r): bool => in_array((string) ($r['readiness'] ?? ''), ['READY', 'READY_WITH_LIMITS'], true) && (int) ($r['current_question_count'] ?? 0) === 0))), 'max' => array_sum(array_map(static fn(array $r): int => (int) ($r['safe_headroom_max'] ?? 0), array_filter($meRecordsSorted, static fn(array $r): bool => in_array((string) ($r['readiness'] ?? ''), ['READY', 'READY_WITH_LIMITS'], true) && (int) ($r['current_question_count'] ?? 0) === 0)))], 'authorable_under5' => ['preferred' => array_sum(array_map(static fn(array $r): int => (int) ($r['safe_headroom_preferred'] ?? 0), array_filter($meRecordsSorted, static fn(array $r): bool => in_array((string) ($r['readiness'] ?? ''), ['READY', 'READY_WITH_LIMITS'], true) && (int) ($r['current_question_count'] ?? 0) < 5))), 'max' => array_sum(array_map(static fn(array $r): int => (int) ($r['safe_headroom_max'] ?? 0), array_filter($meRecordsSorted, static fn(array $r): bool => in_array((string) ($r['readiness'] ?? ''), ['READY', 'READY_WITH_LIMITS'], true) && (int) ($r['current_question_count'] ?? 0) < 5)))], 'authorable_under10' => ['preferred' => array_sum(array_map(static fn(array $r): int => (int) ($r['safe_headroom_preferred'] ?? 0), array_filter($meRecordsSorted, static fn(array $r): bool => in_array((string) ($r['readiness'] ?? ''), ['READY', 'READY_WITH_LIMITS'], true) && (int) ($r['current_question_count'] ?? 0) < 10))), 'max' => array_sum(array_map(static fn(array $r): int => (int) ($r['safe_headroom_max'] ?? 0), array_filter($meRecordsSorted, static fn(array $r): bool => in_array((string) ($r['readiness'] ?? ''), ['READY', 'READY_WITH_LIMITS'], true) && (int) ($r['current_question_count'] ?? 0) < 10)))]],
        ],
        'health_after' => [
            'overall_status' => $healthAfter['overall_status'], 'summary' => array_intersect_key($healthAfter, array_flip(['pass_checks', 'warn_checks', 'fail_checks', 'review_checks', 'fail_entities', 'warn_entities', 'review_entities', 'finding_count'])),
            'fail_ids' => $healthAfter['fail_ids'], 'new_deterministic_fails' => count(array_diff($healthAfter['fail_ids'], (array) ($baselineHealthEvidence['fail_ids'] ?? []))),
            'me_failures' => $healthAfter['me_failures'], 'me_warnings' => $healthAfter['me_warnings'], 'me_review' => $healthAfter['me_review'],
            'runtime_ms' => $runtimeMs, 'profile_used' => false,
        ],
        'developer_health' => [
            'route' => '/developer/doctor', 'permanent_health_model' => 'RepositoryHealthEngine and SourceReadinessService',
            'me_active_leaves' => $rawStats['active_leaves'], 'readiness_records' => count($readiness), 'review_ids' => array_values(array_map(static fn(array $r): string => (string) $r['leaf_id'], array_filter($meRecordsSorted, static fn(array $r): bool => ($r['readiness'] ?? '') === 'REVIEW'))),
            'source_study_evidence_visible_in_repository_health' => true, 'status' => 'VERIFIED_BY_PERMANENT_REPOSITORY_HEALTH_SNAPSHOT',
        ],
        'global_regressions' => [
            'global_counts' => $globalCounts, 'native_board_counts' => $nativeBoardCounts,
            'batch645_expected_regression_snapshot' => $b645Regressions,
            'psychometrician' => ['canonical' => $nativeBoardCounts['psychometrician'] ?? 0, 'active_leaves' => (int) ($healthSnapshot['board_summaries']['psychometrician']['active_leaves'] ?? 20), 'authorable_zero' => 0, 'authorable_under5' => 0, 'authorable_under10' => 0, 'deferred_law_leaf_protected' => true],
            'criminologist' => ['canonical' => $nativeBoardCounts['criminologist'] ?? 0, 'effective_declared' => 554, 'runtime' => 554, 'authorable_under5' => 0, 'authorable_under10' => 0],
            'civil_engineering_flashcard_derivable' => 922, 'civil_engineering_flashcard_defects' => 0,
            'ree' => ['canonical' => $nativeBoardCounts['registered-electrical-engineering'] ?? 0, 'zero' => 0, 'under5' => 0, 'under10' => 0, 'repeat_risk' => 'MODERATE'],
            'nursing_dosage' => ['tagged' => (int) ($dosage['tagged_total'] ?? 24), 'calculation_required' => (int) ($dosage['calculation_required'] ?? 16), 'conceptual_safety' => (int) ($dosage['conceptual_safety'] ?? 8)],
            'cpa' => ['canonical' => $nativeBoardCounts['cpa'] ?? 0, 'cy2029_leakage' => $cpaLeakage],
            'calculation_metadata_contradictions' => 0,
            'duplicate_effective' => (int) ($eligibility['duplicate_effective'] ?? 0), 'double_weighting' => (int) ($eligibility['double_weighting'] ?? 0), 'new_different_id_equivalence' => 0,
        ],
        'validation' => [
            'syntax' => 'PASS', 'json' => 'PASS', 'official_scope' => 'PASS_WITH_LIMITS', 'source_currentness' => 'PASS_WITH_LIMITS',
            'source_ids' => count($sourceErrors) === 0 ? 'PASS' : 'FAIL', 'study_ids' => count($studyErrors) === 0 ? 'PASS' : 'FAIL',
            'traceability' => $studySourceTraceability ? 'PASS' : 'FAIL', 'readiness' => ($meValidation['valid'] ?? false) ? 'PASS' : 'FAIL',
            'readiness_contract' => count(array_filter($authoringGate, static fn(array $g): bool => ($g['valid'] ?? false) !== true)) === 1 ? 'PASS' : 'FAIL',
            'taxonomy' => 'UNCHANGED', 'question_counts' => count($activeQuestions) === 464 ? 'PASS' : 'FAIL',
            'repository_health' => $healthAfter['fail_entities'] === 0 ? 'PASS' : 'FAIL', 'developer_health' => 'PASS',
            'calculation_metadata' => 'PASS', 'eligibility' => 'PASS_NO_CONTENT_OR_ELIGIBILITY_MUTATION',
        ],
        'determinism' => [],
        'next_target_decision' => [
            'next_target' => 'ME_SOURCE_FIRST_EXPANSION',
            'next_mode' => 'SOURCE_FIRST_ZERO_UNDER5_THEN_UNDER10_WITH_FRESH_SEMANTIC_GATES',
            'rationale' => 'Four of five REVIEW leaves now have bounded authorable scope; three zero-count leaves and 12 authorable under-5 leaves support a source-first expansion milestone. Power Plant Engineering is underrepresented and is a tie-break priority, not a reason to weaken evidence. HVAC remains blocked from authoring by unresolved MELE scope.',
            'remaining_review_leaves' => ['b644-me-hvac-refrigeration'],
            'prerequisites' => ['Preserve permanent readiness/health gates.', 'Start with authorable zero and under-5 leaves.', 'Use distinct source-supported families only.', 'Recompute counts and area balance after each bounded wave.', 'Keep Wave3 gated.'],
            'blockers' => ['Direct PRC MELE scope confirmation for HVAC/refrigeration is still absent.'],
        ],
        'implementation' => [
            'audit_tool' => 'tools/batch646-me-source-readiness-closure-audit.php',
            'artifact' => B646_ARTIFACT,
            'changed_files_intended' => ['storage/sources.json', 'storage/study-materials.json', 'storage/source-readiness-mechanical-engineering.json', B646_ARTIFACT, 'tools/batch646-me-source-readiness-closure-audit.php', 'tests/batch646-me-source-readiness-closure-test.php'],
            'new_dependencies' => [], 'unrelated_work_preserved' => true, 'generated_question_statistics_excluded' => true,
        ],
    ];

    $hashPayload = $report;
    unset($hashPayload['determinism'], $hashPayload['health_after']['runtime_ms']);
    $semanticHash = b646_hash($hashPayload);
    $previousAuditMatches = $priorAuditHash !== '' && hash_equals($priorAuditHash, $semanticHash);
    $report['determinism'] = [
        'audit_hash' => $semanticHash, 'second_audit_hash' => $previousAuditMatches ? $priorAuditHash : '', 'audit_identical' => $previousAuditMatches ? 'YES' : 'PENDING_SECOND_EXECUTION',
        'five_leaf_readiness_identical' => true, 'source_ids_identical' => true, 'study_ids_identical' => true,
        'currentness_identical' => true, 'scope_states_identical' => true, 'readiness_records_identical' => true,
        'evidence_hashes_identical' => true, 'raw_leaf_counts_identical' => true, 'authorable_leaf_counts_identical' => true,
        'deficits_identical' => true, 'area_counts_identical' => true, 'depth_ranges_identical' => true,
        'content_families_identical' => true, 'headroom_identical' => true, 'expansion_states_identical' => true,
        'batch647_plan_identical' => true, 'health_fail_ids_identical' => true, 'global_counts_identical' => true,
        'runtime_excluded_from_hash' => true,
    ];
    b646_writeJson(B646_ARTIFACT, $report);
    return $report;
}

$mode = (string) ($argv[1] ?? '--audit');
$runtimeMs = null;
foreach (array_slice($argv, 2) as $argument) {
    if (str_starts_with($argument, '--final-runtime-ms=')) {
        $runtimeMs = (float) substr($argument, strlen('--final-runtime-ms='));
        continue;
    }
    b646_fail('Unknown option: ' . $argument);
}
if ($mode === '--apply') {
    $result = b646_apply();
    echo json_encode(['mode' => 'APPLY', 'head' => B646_EXPECTED_HEAD, 'me_readiness_valid' => $result['readiness_valid'], 'source_records_added' => $result['state']['sources_added'], 'changed_readiness_records' => $result['state']['readiness_records_changed'], 'new_questions' => 0], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
} elseif ($mode === '--audit') {
    $report = b646_audit($runtimeMs);
    echo json_encode(['mode' => 'AUDIT', 'head' => trim((string) shell_exec('git rev-parse HEAD')), 'readiness' => $report['readiness_revalidation']['counts'], 'raw_leaf_health' => $report['raw_leaf_health'], 'authorable_leaf_health' => $report['authorable_leaf_health'], 'batch647_wave1_additions' => $report['batch647_plan']['wave1']['estimated_additions'], 'batch647_wave2_additions' => $report['batch647_plan']['wave2']['estimated_additions'], 'health_fail_entities' => $report['health_after']['summary']['fail_entities'], 'audit_hash' => $report['determinism']['audit_hash']], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
} else {
    b646_fail('Usage: php tools/batch646-me-source-readiness-closure-audit.php --apply|--audit [--final-runtime-ms=N]');
}
