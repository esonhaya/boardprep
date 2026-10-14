<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

$root = dirname(__DIR__, 2);
$read = static function (string $path) use ($root): array {
    return json_decode((string) file_get_contents($root . '/' . $path), true, 512, JSON_THROW_ON_ERROR);
};
$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException('Batch 677 source-first regression failed: ' . $message);
    }
};
$joinNotes = static function (array $material): string {
    $parts = [];
    foreach ((array) ($material['notes'] ?? []) as $note) {
        if (is_array($note) && array_is_list($note)) {
            $parts[] = implode(' ', array_map('strval', $note));
        } elseif (is_array($note)) {
            $parts[] = (string) ($note['heading'] ?? '') . ' ' . (string) ($note['body'] ?? '');
        }
    }
    return strtolower(implode(' ', $parts));
};

$questions = $read('storage/questions.json');
$questions = array_is_list($questions) ? $questions : (array) ($questions['questions'] ?? []);
$materials = $read('storage/study-materials.json');
$links = $read('storage/study-question-links.json');
$sources = $read('storage/sources.json');
$concepts = $read('storage/taxonomy/concepts.json');
$readiness = $read('storage/source-readiness-let.json');
$crosswalk = $read('storage/let-source-crosswalk-batch677.json');
$questionById = [];
$materialById = [];
$sourceById = [];
$conceptById = [];
$linkByQuestion = [];
foreach ($questions as $question) {
    $questionById[(string) ($question['id'] ?? '')] = $question;
}
foreach ($materials as $material) {
    $materialById[(string) ($material['id'] ?? '')] = $material;
}
foreach ($sources as $source) {
    $sourceById[(string) ($source['id'] ?? '')] = $source;
}
foreach ($concepts as $concept) {
    $conceptById[(string) ($concept['id'] ?? '')] = $concept;
}
foreach ($links as $link) {
    $linkByQuestion[(string) ($link['question_id'] ?? '')][] = $link;
}

$assert(count($questionById) === count($questions), 'canonical question IDs remain unique');
$assert(count($questions) === 8962, 'canonical question count remains stable');

$letQuestion = $questionById['135'] ?? [];
$letMaterial = $materialById['let-assessment-review'] ?? [];
$letLink = array_values(array_filter(
    $linkByQuestion['135'] ?? [],
    static fn(array $link): bool => ($link['id'] ?? '') === 'study-link-let-135'
))[0] ?? [];
$assert(($letQuestion['answer'] ?? '') === 'What they learned and what to improve', 'Q135 answer identity is unchanged');
$assert(str_contains((string) ($letQuestion['explanation'] ?? ''), 'learner B links evidence to a claim'), 'Q135 rationale interprets the table evidence');
$assert(($letLink['material_id'] ?? '') === 'let-assessment-review', 'Q135 links to assessment and feedback study material');
$assert(in_array('deped-classroom-assessment-do-8-2015', (array) ($letLink['source_ids'] ?? []), true)
    && in_array('deped-ppst-do-42-2017', (array) ($letLink['source_ids'] ?? []), true), 'Q135 link names its relevant primary guidance');
$assert(array_diff((array) ($letLink['source_ids'] ?? []), (array) ($letMaterial['source_ids'] ?? [])) === [], 'Q135 link sources are declared by its material');
$assert(str_contains($joinNotes($letMaterial), 'one feasible next step'), 'LET material explains evidence-based reflection');
$letConceptIds = (array) ($letMaterial['taxonomy']['concept_ids'] ?? []);
$assert(in_array((string) ($letQuestion['taxonomy']['concept_id'] ?? ''), $letConceptIds, true), 'LET study material names Q135’s exact concept');
$assert(isset($conceptById[(string) ($letQuestion['taxonomy']['concept_id'] ?? '')]), 'LET Q135 concept resolves in the canonical taxonomy');
$letReadiness = array_values(array_filter($readiness, static fn(array $row): bool => ($row['leaf_id'] ?? '') === 'let-learner-agency-self-regulation'))[0] ?? [];
$assert(in_array('let-assessment-review', (array) ($letReadiness['study_material_ids'] ?? []), true), 'LET readiness records the corrected study pathway');
$assert(hash_equals(\App\Services\RepositoryHealth\Readiness\SourceReadinessRecord::evidenceHash($letReadiness), (string) ($letReadiness['evidence_hash'] ?? '')), 'LET readiness evidence hash is current');
$crosswalkRow = array_values(array_filter($crosswalk['crosswalks'] ?? [], static fn(array $row): bool => ($row['leaf_id'] ?? '') === 'let-learner-agency-self-regulation'))[0] ?? [];
$crosswalkQ135 = array_values(array_filter($crosswalkRow['question_links'] ?? [], static fn(array $link): bool => (string) ($link['question_id'] ?? '') === '135'))[0] ?? [];
$assert(($crosswalk['batch'] ?? 0) === 677 && ($crosswalkQ135['material_id'] ?? '') === 'let-assessment-review', 'current B677 crosswalk records the corrected pathway');
$historicalCrosswalk = $read('storage/let-source-crosswalk-batch674.json');
$assert(($historicalCrosswalk['batch'] ?? 0) === 674, 'historical B674 crosswalk remains preserved');

$architecture = $materialById['architecture-4-2-foundation'] ?? [];
$architectureNotes = $joinNotes($architecture);
$assert(in_array('bp344-2024-irr', (array) ($architecture['source_ids'] ?? []), true), 'architecture route guidance cites the 2024 IRR');
$assert(str_contains($architectureNotes, 'continuous and unobstructed') && str_contains($architectureNotes, 'standard path'), 'architecture route note covers continuity and exclusion');
$assert(str_contains($architectureNotes, 'verify each dimension against the irr'), 'architecture note avoids unsupported dimensional claims');
$architectureQuestion = $questionById['8957'] ?? [];
$architectureLink = array_values(array_filter($linkByQuestion['8957'] ?? [], static fn(array $link): bool => ($link['material_id'] ?? '') === 'architecture-4-2-foundation'))[0] ?? [];
$assert(($architectureQuestion['answer'] ?? '') === 'Run the public entry to reception and consultation; connect the staff/service entry to a back-of-house route; and keep the fire exit connected to an unobstructed escape path.', 'Architecture Q8957 answer identity is unchanged');
$assert(str_contains(strtolower((string) ($architectureQuestion['explanation'] ?? '')), '2024 bp 344 irr'), 'Architecture Q8957 explains the source-backed route requirement');
$assert(in_array('b633-arch-site-circulation', (array) ($architecture['taxonomy']['concept_ids'] ?? []), true)
    && in_array('bp344-2024-irr', (array) ($architectureLink['source_ids'] ?? []), true), 'Architecture Q8957 links its exact concept and primary source');
$assert(isset($conceptById[(string) ($architectureQuestion['taxonomy']['concept_id'] ?? '')]), 'Architecture Q8957 concept resolves in the canonical taxonomy');

$hydro = $materialById['me-material-5'] ?? [];
$doe = $sourceById['doe-hydropower-how-it-works'] ?? [];
$hydroNotes = $joinNotes($hydro);
$assert(($doe['url'] ?? '') === 'https://www.energy.gov/cmei/water/how-hydropower-works', 'hydropower source resolves to the official DOE overview');
$assert(($doe['authority'] ?? '') === 'U.S. Department of Energy, Water Power Technologies Office', 'hydropower source identifies its publisher');
$assert(in_array('doe-hydropower-how-it-works', (array) ($hydro['source_ids'] ?? []), true), 'ME material cites the DOE overview');
$assert(str_contains($hydroNotes, 'flow and elevation change (head)') && str_contains($hydroNotes, 'penstock')
    && str_contains($hydroNotes, 'turbine drives a generator'), 'ME note teaches the source-backed conversion chain');
$assert(($linkByQuestion['8868'][0]['material_id'] ?? '') === 'me-material-5', 'hydropower question retains its exact study material link');
$hydroQuestion = $questionById['8869'] ?? [];
$hydroLink = array_values(array_filter($linkByQuestion['8869'] ?? [], static fn(array $link): bool => ($link['material_id'] ?? '') === 'me-material-5'))[0] ?? [];
$assert(($hydroQuestion['answer'] ?? '') === '60 m', 'ME Q8869 answer identity is unchanged');
$assert(str_contains((string) ($hydroQuestion['explanation'] ?? ''), '68 m - 8 m = 60 m')
    && str_contains(strtolower((string) ($hydroQuestion['explanation'] ?? '')), 'subtract the supplied loss once'), 'ME Q8869 explains net head and loss accounting');
$assert(in_array('b644-me-hydroelectric-plants', (array) ($hydro['taxonomy']['concept_ids'] ?? []), true)
    && in_array('doe-hydropower-how-it-works', (array) ($hydroLink['source_ids'] ?? []), true), 'ME Q8869 links its exact concept and DOE source');
$assert(isset($conceptById[(string) ($hydroQuestion['taxonomy']['concept_id'] ?? '')]), 'ME Q8869 concept resolves in the canonical taxonomy');

$nursing = $materialById['nle-pharmacology-safety'] ?? [];
$nursingNotes = $joinNotes($nursing);
$assert(in_array('who-medication-without-harm-5-moments', (array) ($nursing['source_ids'] ?? []), true), 'nursing material cites WHO medication-safety guidance');
$assert(str_contains($nursingNotes, 'patient and caregiver engagement tool') && str_contains($nursingNotes, 'does not replace those professional controls'), 'nursing note preserves the source boundary and clinical controls');
$nursingQuestion = $questionById['1517'] ?? [];
$nursingLink = array_values(array_filter($linkByQuestion['1517'] ?? [], static fn(array $link): bool => ($link['material_id'] ?? '') === 'nle-pharmacology-safety'))[0] ?? [];
$assert(($nursingQuestion['answer'] ?? '') === 'Hold administration and clarify the discrepancy using the required verification process', 'Nursing Q1517 answer identity is unchanged');
$assert(str_contains(strtolower((string) ($nursingQuestion['explanation'] ?? '')), 'who’s medication-safety tool')
    && str_contains(strtolower((string) ($nursingQuestion['explanation'] ?? '')), 'does not let the patient choose'), 'Nursing Q1517 explains patient partnership without transferring clinical control');
$assert(in_array('nle-np-iv-medication', (array) ($nursing['taxonomy']['concept_ids'] ?? []), true)
    && in_array('who-medication-without-harm-5-moments', (array) ($nursingLink['source_ids'] ?? []), true), 'Nursing Q1517 links its exact concept and WHO source');
$assert(isset($conceptById[(string) ($nursingQuestion['taxonomy']['concept_id'] ?? '')]), 'Nursing Q1517 concept resolves in the canonical taxonomy');

echo "[PASS] Batch 677 source-first improvements verified across LET, Architecture, Mechanical Engineering, and Nursing; exact concepts, primary links, explanations, and answer identities preserved.\n";
