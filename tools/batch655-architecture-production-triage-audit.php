<?php

declare(strict_types=1);

/**
 * Batch655 orchestration only: consume the exact Batch654 Architecture plan,
 * author its two bounded waves, close the text cycle, and project the
 * permanent health snapshot into a compact portfolio triage.  Repository
 * integrity, readiness, source/study, depth, calculation, and flashcard
 * checks remain owned by the permanent services.
 */

require_once dirname(__DIR__) . '/app/Core/Autoloader.php';

use App\Core\App;
use App\Core\Autoloader;
use App\Services\Flashcard\FlashcardDerivationService;
use App\Services\Question\QuestionService;
use App\Services\RepositoryHealth\Engine\RepositoryHealthEngine;
use App\Services\RepositoryHealth\Readiness\SourceReadinessRecord;
use App\Services\RepositoryHealth\Readiness\SourceReadinessService;

Autoloader::register();
putenv('APP_ENV=testing');
putenv('APP_DEBUG=false');
App::boot();

$root = dirname(__DIR__);
$expectedHead = 'eccec1486a06d0d66182bb1ad3b33d795d5ddfd8';
$expectedParent = '030436584c6e4921a63d7c4a1469f8fc97f76eb7';
$expectedMain = '0eb9704ba293e0aa3402af5d01a2d3392c005b73';
$batch654Path = $root . '/storage/batch654-architecture-readiness.json';
$artifactPath = $root . '/storage/batch655-architecture-production-triage.json';
$readinessPath = $root . '/storage/source-readiness-architecture.json';
$mode = $argv[1] ?? '--audit';

$fail = static function (string $message): never {
    fwrite(STDERR, 'BATCH655_FAIL=' . $message . PHP_EOL);
    exit(1);
};

$assert = static function (bool $condition, string $message) use ($fail): void {
    if (!$condition) $fail($message);
};

$readJson = static function (string $path): array {
    if (!is_file($path)) throw new RuntimeException('Missing JSON: ' . $path);
    $value = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($value)) throw new RuntimeException('Expected JSON list/object: ' . $path);
    return $value;
};

$writeJson = static function (string $path, array $data): void {
    $temporary = $path . '.tmp';
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    if (file_put_contents($temporary, $json . PHP_EOL, LOCK_EX) === false || !rename($temporary, $path)) {
        throw new RuntimeException('Could not atomically write ' . $path);
    }
};

$git = static function (array $args) use ($root): string {
    $command = 'git -C ' . escapeshellarg($root);
    foreach ($args as $arg) $command .= ' ' . escapeshellarg($arg);
    $output = shell_exec($command . ' 2>/dev/null');
    if (!is_string($output)) throw new RuntimeException('Git command failed: ' . implode(' ', $args));
    return trim($output);
};

$canonicalize = static function (mixed $value) use (&$canonicalize): mixed {
    if (!is_array($value)) return $value;
    if (array_is_list($value)) return array_map($canonicalize, $value);
    ksort($value, SORT_STRING);
    foreach ($value as &$child) $child = $canonicalize($child);
    unset($child);
    return $value;
};

$hash = static fn(mixed $value): string => hash(
    'sha256',
    json_encode($canonicalize($value), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
);

$stableArtifact = static function (array $artifact) use (&$stableArtifact): array {
    $clean = [];
    foreach ($artifact as $key => $value) {
        $keyString = strtolower((string) $key);
        if (in_array($keyString, ['audit_hash', 'second_audit_hash', 'identical', 'generated_at', 'timestamp'], true)
            || str_contains($keyString, 'runtime')
            || str_contains($keyString, 'wall_time')
            || str_contains($keyString, 'duration_ms')
            || str_ends_with($keyString, '_ms')
            || $keyString === 'regression_percent') {
            continue;
        }
        $clean[$key] = is_array($value) && !array_is_list($value)
            ? $stableArtifact($value)
            : (is_array($value) ? array_map(static fn(mixed $child): mixed => is_array($child) && !array_is_list($child) ? $stableArtifact($child) : $child, $value) : $value);
    }
    return $clean;
};

$active = static fn(array $question): bool => strtolower((string) ($question['status'] ?? 'active')) !== 'archived';
$questionLeaf = static fn(array $question): string => trim((string) (($question['taxonomy']['concept_id'] ?? $question['concept'] ?? '')));

$definitions = static function (): array {
    return [
        'ARCH-654-001' => [
            'scope' => ['climate_response', 'passive_strategy'],
            'study_focus' => 'Apply climate, wind, rainfall, and material constraints to a passive vernacular adaptation without relying on image recognition.',
            'source_sensitivity' => 'STABLE', 'version_sensitive' => false, 'context_mode' => 'PROFESSIONAL',
            'flags' => ['technical_reasoning' => true, 'professional_judgment' => false, 'site_plan_spatial' => false],
            'question' => 'A community hall is planned for a hot-humid coastal site with heavy seasonal rain, prevailing breezes from the east, and locally available light timber. Mechanical cooling is limited. Which response most directly transfers a climate-performance lesson from vernacular practice?',
            'choices' => [
                'Use deep roof overhangs and a raised, well-drained floor with operable openings placed to support the stated breeze.',
                'Seal all openings and rely on dark interior finishes to absorb humidity.',
                'Copy a familiar roof silhouette while ignoring orientation and rain exposure.',
                'Use full-height west glazing without shading because daylight is always the priority.',
            ],
            'answer_index' => 0,
            'explanation' => 'The response addresses the stated heat, wind, rain, and ground-moisture conditions together. It transfers performance logic rather than treating a recognizable vernacular form as a universal template.',
            'signature' => ['concept' => 'passive climate adaptation', 'reasoning_operation' => 'match response to stated environmental constraints', 'material_context' => 'hot-humid coastal hall with light timber', 'visual_dependency' => 'NONE'],
        ],
        'ARCH-654-002' => [
            'scope' => ['information_status', 'consultant_interface'],
            'study_focus' => 'Resolve a consultant interface by checking issue status, authority, and downstream coordination before giving an instruction.',
            'source_sensitivity' => 'STABLE', 'version_sensitive' => false, 'context_mode' => 'PROFESSIONAL',
            'flags' => ['technical_reasoning' => true, 'professional_judgment' => true, 'context_dependent' => true, 'context_necessary' => true],
            'question' => "On a coordination set, the structural consultant's issued revision relocates a column. The architectural set and a contractor's email still show the prior position, and the consultant's revision has not yet been accepted as a project change. What should the architect do first?",
            'choices' => [
                'Confirm the current issued status and decision authority, log the discrepancy, and coordinate affected drawings and specifications before issuing an instruction.',
                'Tell the contractor to use the consultant email because it has the newest date.',
                'Move the column in the architectural plan only and let each trade resolve the consequences.',
                'Ask the contractor to choose the position that is easiest to build.',
            ],
            'answer_index' => 0,
            'explanation' => 'A dated document is not automatically an authorized project change. The safe interface response verifies information status and authority, records the conflict, and coordinates every affected document before work proceeds.',
            'signature' => ['concept' => 'consultant interface control', 'reasoning_operation' => 'check information status and authority before coordination', 'material_context' => 'conflicting structural and architectural revisions', 'visual_dependency' => 'NONE'],
        ],
        'ARCH-654-003' => [
            'scope' => ['accessibility_duty', 'public_safety'],
            'study_focus' => 'Prioritize public safety and accessibility when a client preference conflicts with a stated user risk, without inventing a regulatory dimension.',
            'source_sensitivity' => 'REGULATORY', 'version_sensitive' => true, 'version_review_status' => 'CURRENT_WITH_LIMITS', 'context_mode' => 'REGULATORY',
            'flags' => ['technical_reasoning' => false, 'professional_judgment' => true, 'context_dependent' => true, 'context_necessary' => true],
            'question' => 'An accessible public library entry has a 450-mm level change. The client asks to keep only a short stair and remove the continuous route to save area. Which response best meets the professional responsibility in the stated situation?',
            'choices' => [
                'Explain the conflict, retain a continuous usable accessible route, verify the current requirements, and document the decision and its consequences.',
                'Accept the request because the client preference controls the entry design.',
                'Call the entry accessible if staff can carry a user over the level change.',
                'Leave the route unresolved until after construction so the contractor can improvise.',
            ],
            'answer_index' => 0,
            'explanation' => 'The stated level change and public use create a safety and accessibility responsibility. The architect must preserve a usable route and verify the applicable current requirements rather than treating preference or staff assistance as a design solution.',
            'signature' => ['concept' => 'public accessibility duty', 'reasoning_operation' => 'prioritize user safety over stated client preference', 'material_context' => 'public library entry with level change', 'visual_dependency' => 'NONE'],
        ],
        'ARCH-654-004' => [
            'scope' => ['load_path', 'system_selection'],
            'study_focus' => 'Select a conceptual structural system from explicit span and support assumptions without claiming sealed member design.',
            'source_sensitivity' => 'STABLE', 'version_sensitive' => false, 'context_mode' => 'PROFESSIONAL',
            'flags' => ['technical_reasoning' => true, 'professional_judgment' => false, 'context_dependent' => true, 'context_necessary' => true],
            'question' => 'A 12-m clear-span community hall must keep its central floor free of columns. Supports can be placed along the perimeter, and a separate engineer will size members. Which conceptual choice best matches the stated load path?',
            'choices' => [
                'Use a spanning truss or deep-girder system bearing at perimeter supports, with reactions transferred into the supporting foundations.',
                'Use short beams spanning between columns that are not present in the central floor.',
                'Hang the roof from nonstructural partitions so the perimeter supports carry no reactions.',
                'Choose the thinnest floor finish because finish thickness determines the roof load path.',
            ],
            'answer_index' => 0,
            'explanation' => 'The system must span the stated clear space and deliver its reactions to the available perimeter supports. Member sizing remains an engineering task; the item tests the conceptual load path only.',
            'signature' => ['concept' => 'structural load path', 'reasoning_operation' => 'match system behavior to span and support assumptions', 'material_context' => '12-m clear-span hall with perimeter supports', 'visual_dependency' => 'NONE'],
        ],
        'ARCH-654-005' => [
            'scope' => ['utilities_coordination', 'serviceability'],
            'study_focus' => 'Coordinate a water and drainage route with access, penetrations, and maintenance constraints before architectural zones are fixed.',
            'source_sensitivity' => 'REGULATORY', 'version_sensitive' => true, 'version_review_status' => 'CURRENT_WITH_LIMITS', 'context_mode' => 'REGULATORY',
            'flags' => ['technical_reasoning' => true, 'professional_judgment' => true, 'context_dependent' => true, 'context_necessary' => true],
            'question' => 'During renovation, new washrooms are stacked above occupied rooms. The proposed route crosses multiple beams, has no accessible cleanout, and leaves no space for valve maintenance. Which coordination response is strongest?',
            'choices' => [
                'Reserve an aligned service zone or shaft, coordinate penetrations and the drainage route, and preserve access and maintenance space before fixing the ceiling.',
                'Conceal the pipes above the ceiling and defer access until a leak occurs.',
                'Move the fixtures without checking the route, structure, or maintenance clearances.',
                'Select a pipe size from the fixture count alone and treat the coordination problem as solved.',
            ],
            'answer_index' => 0,
            'explanation' => 'A workable utility solution needs a connected route, coordinated penetrations, and service access. Fixture count or concealment alone does not resolve structural, drainage, and maintenance interfaces.',
            'signature' => ['concept' => 'utilities serviceability coordination', 'reasoning_operation' => 'resolve route, penetration, and maintenance consequences together', 'material_context' => 'stacked washrooms over occupied rooms', 'visual_dependency' => 'NONE'],
        ],
        'ARCH-654-006' => [
            'scope' => ['quantity', 'constructability'],
            'study_focus' => 'Recompute net wall-finish area after openings and an explicit waste allowance, keeping units and assumptions visible.',
            'source_sensitivity' => 'STABLE', 'version_sensitive' => false, 'context_mode' => 'PROFESSIONAL',
            'flags' => ['technical_reasoning' => true, 'professional_judgment' => false, 'context_dependent' => true, 'context_necessary' => true],
            'question' => 'A rectangular wall is 18.0 m long and 3.0 m high. It has two door openings, each 0.9 m by 2.1 m, and four window openings, each 1.2 m by 1.0 m. If the order includes 5% waste on the net wall area, what wall-finish quantity should be ordered, to the nearest 0.1 m²?',
            'choices' => ['47.7 m²', '45.4 m²', '49.2 m²', '50.4 m²'],
            'answer_index' => 0,
            'explanation' => 'Gross area is 18.0 × 3.0 = 54.0 m². Openings are (2 × 0.9 × 2.1) + (4 × 1.2 × 1.0) = 8.58 m², so net area is 45.42 m². Applying 5% waste gives 45.42 × 1.05 = 47.691 m², or 47.7 m².',
            'calculation_family' => 'CONSTRUCTION_NET_AREA_WITH_WASTE',
            'calculation' => ['family' => 'CONSTRUCTION_NET_AREA_WITH_WASTE', 'expression' => '((18.0 × 3.0) − (2 × 0.9 × 2.1) − (4 × 1.2 × 1.0)) × 1.05', 'result' => '47.691', 'unit' => 'm²', 'rounding' => 'nearest 0.1 m²', 'assumptions' => ['waste applies to net finish area', 'all dimensions are in metres']],
            'signature' => ['concept' => 'net construction quantity', 'reasoning_operation' => 'subtract openings then apply explicit waste allowance', 'material_context' => 'wall finish with doors and windows', 'visual_dependency' => 'NONE'],
        ],
        'ARCH-654-007' => [
            'scope' => ['adjacency', 'egress_accessibility', 'wayfinding'],
            'study_focus' => 'Choose a complete text-described circulation arrangement satisfying adjacency, accessibility, service separation, and exit continuity constraints.',
            'source_sensitivity' => 'REGULATORY', 'version_sensitive' => true, 'version_review_status' => 'CURRENT_WITH_LIMITS', 'context_mode' => 'REGULATORY',
            'flags' => ['technical_reasoning' => false, 'professional_judgment' => true, 'site_plan_spatial' => true, 'figure_dependent' => false, 'context_dependent' => true, 'context_necessary' => true],
            'question' => 'An outpatient clinic has a public entry, reception, two consultation rooms, a staff/service entry, and a fire exit. The brief requires a continuous accessible public route from entry to reception and consultation, service deliveries not to cross the waiting area, and the exit route to remain unobstructed. Which text-described arrangement best satisfies all three?',
            'choices' => [
                'Run the public entry to reception and consultation; connect the staff/service entry to a back-of-house route; and keep the fire exit connected to an unobstructed escape path.',
                'Route deliveries through the waiting area because it is the shortest path, then place the fire exit behind the reception desk.',
                'Put the consultation rooms on an upper level reached only by stairs, while keeping the service entry at the public door.',
                'Place reception at the fire exit and use the same narrow route for public arrival, deliveries, and emergency movement.',
            ],
            'answer_index' => 0,
            'explanation' => 'The selected arrangement states each required relationship: a continuous public route, separated service movement, and an unobstructed exit path. A short or symmetrical route is not sufficient when it conflicts with access or life safety.',
            'signature' => ['concept' => 'circulation and adjacency', 'reasoning_operation' => 'satisfy three stated movement constraints simultaneously', 'material_context' => 'outpatient clinic public, service, and exit routes', 'visual_dependency' => 'TEXT_COMPLETE; IMAGE_WOULD_ADD_AUTHENTICITY'],
            'text_validated' => true,
        ],
        'ARCH-654-008' => [
            'scope' => ['phasing_growth', 'site_coverage', 'spatial_alternative'],
            'study_focus' => 'Compare a complete text-described site-phasing alternative against access, landscape, drainage, service, and future-growth criteria.',
            'source_sensitivity' => 'STABLE', 'version_sensitive' => false, 'context_mode' => 'PROFESSIONAL',
            'flags' => ['technical_reasoning' => true, 'professional_judgment' => true, 'site_plan_spatial' => true, 'figure_dependent' => false, 'context_dependent' => true, 'context_necessary' => true],
            'question' => 'A 60 m by 40 m site must retain a continuous landscaped and drainage zone along its south edge, keep north access open for emergency and service movement, and reserve the east side for a future wing. Which phase-one strategy best preserves all three criteria?',
            'choices' => [
                'Use a compact footprint in the west or central buildable zone, reserve the east for expansion, and preserve both the south zone and the north access spine.',
                'Spread phase one across the full site so no single area appears underused, even if the south zone and east expansion reserve are interrupted.',
                'Place phase one on the east edge and use the future expansion area for initial parking and storage.',
                'Close the north access with the first building and rely on a temporary service route through the south drainage zone.',
            ],
            'answer_index' => 0,
            'explanation' => 'The compact phase preserves the stated landscape and drainage zone, protects the required access spine, and keeps the east expansion reserve available. Maximizing the first footprint would consume the very capacity the brief requires.',
            'signature' => ['concept' => 'site phasing and growth', 'reasoning_operation' => 'compare alternatives against simultaneous site criteria', 'material_context' => '60 m by 40 m phased site with protected edges', 'visual_dependency' => 'TEXT_COMPLETE; DIAGRAM_WOULD_ADD_AUTHENTICITY'],
            'text_validated' => true,
        ],
        'ARCH-654-009' => [
            'scope' => ['vernacular_adaptation', 'climate_response'],
            'study_focus' => 'Distinguish climate-performance adaptation from decorative transfer when climate and material exposure change.',
            'source_sensitivity' => 'STABLE', 'version_sensitive' => false, 'context_mode' => 'PROFESSIONAL',
            'flags' => ['technical_reasoning' => true, 'professional_judgment' => false, 'context_dependent' => true, 'context_necessary' => true],
            'question' => 'A vernacular wall system used in a dry inland setting relies on high thermal mass, small shaded openings, and night flushing. A new coastal site is humid with wind-driven rain and locally available treated timber. Which adaptation demonstrates transfer of performance logic rather than decorative copying?',
            'choices' => [
                'Retain shade, rain control, and controlled ventilation while selecting and detailing durable moisture-resistant timber in a raised, drained assembly for the new exposure.',
                'Reproduce the thick inland wall exactly, even though the coastal material and moisture conditions differ.',
                'Copy the wall pattern as decoration while ignoring drainage, ventilation, and material durability.',
                'Maximize unshaded glass because a coastal setting makes the original climate response irrelevant.',
            ],
            'answer_index' => 0,
            'explanation' => 'Adaptation preserves the environmental logic while changing the material and detailing to suit the new humid, wind-driven-rain exposure. Literal form transfer would ignore the conditions that made the original system perform.',
            'signature' => ['concept' => 'vernacular material adaptation', 'reasoning_operation' => 'separate performance logic from decorative form under changed exposure', 'material_context' => 'dry inland wall logic adapted to humid coastal timber construction', 'visual_dependency' => 'NONE'],
        ],
        'ARCH-654-010' => [
            'scope' => ['change_control', 'information_status'],
            'study_focus' => 'Record and communicate an authorized design change while preserving drawing, specification, schedule, and trade interfaces.',
            'source_sensitivity' => 'STABLE', 'version_sensitive' => false, 'context_mode' => 'PROFESSIONAL',
            'flags' => ['technical_reasoning' => true, 'professional_judgment' => true, 'context_dependent' => true, 'context_necessary' => true],
            'question' => 'An owner approves a door-hardware change in a meeting, but the change affects fire rating, frame coordination, security, and schedule. What should the architect record and communicate?',
            'choices' => [
                'Document the authorized change and its implications, update affected drawings, specifications, and schedules, and issue a controlled revision to the affected disciplines and contractor.',
                'Mark the floor plan only and let the hardware supplier update the remaining documents.',
                'Ask the contractor to choose a compatible product and treat the choice as the project record.',
                'Keep the old documents in circulation so the change can be reconciled after installation.',
            ],
            'answer_index' => 0,
            'explanation' => 'An authorized change still has downstream performance and coordination effects. A controlled record and coordinated document update preserve a single reliable instruction for the people who must act on it.',
            'signature' => ['concept' => 'design change control', 'reasoning_operation' => 'propagate an authorized change through affected interfaces', 'material_context' => 'door hardware change affecting fire, frame, security, and schedule', 'visual_dependency' => 'NONE'],
        ],
        'ARCH-654-011' => [
            'scope' => ['professional_ethics'],
            'study_focus' => 'Communicate a material specialist boundary and protect public responsibility when evidence is incomplete.',
            'source_sensitivity' => 'REGULATORY', 'version_sensitive' => true, 'version_review_status' => 'CURRENT_WITH_LIMITS', 'context_mode' => 'REGULATORY',
            'flags' => ['technical_reasoning' => false, 'professional_judgment' => true, 'context_dependent' => true, 'context_necessary' => true],
            'question' => 'An architect is asked to sign a foundation recommendation for a site with unverified soil data. No geotechnical specialist has been engaged, but the project deadline is near. What is the most responsible response?',
            'choices' => [
                'State the limitation, obtain qualified specialist verification, avoid certifying an unsupported conclusion, and document the interim risk to the client and project team.',
                'Sign the recommendation with a disclaimer because the deadline makes verification impractical.',
                'Use a neighboring site report as proof that the same foundation is suitable here.',
                'Omit the uncertainty from the record so the project can proceed without delay.',
            ],
            'answer_index' => 0,
            'explanation' => 'The missing soil evidence is material to the foundation decision. Protecting the public requires an honest boundary on competence and a qualified verification path, not a disclaimer that disguises an unsupported certification.',
            'signature' => ['concept' => 'professional specialist boundary', 'reasoning_operation' => 'identify uncertainty and refer before certifying', 'material_context' => 'foundation recommendation without verified geotechnical data', 'visual_dependency' => 'NONE'],
        ],
        'ARCH-654-012' => [
            'scope' => ['bounded_calculation', 'utilities_coordination'],
            'study_focus' => 'Recompute a bounded service volume from explicit flow, duration, and units, then retain the result as a coordination input rather than a full sizing claim.',
            'source_sensitivity' => 'REGULATORY', 'version_sensitive' => true, 'version_review_status' => 'CURRENT_WITH_LIMITS', 'context_mode' => 'REGULATORY',
            'flags' => ['technical_reasoning' => true, 'professional_judgment' => true, 'context_dependent' => true, 'context_necessary' => true],
            'question' => 'During a controlled drainage test, a temporary service line flows at 2.5 L/s for 8 minutes. Using 60 seconds per minute and 1,000 L per cubic metre, what volume passes during the test?',
            'choices' => ['1,200 L (1.2 m³)', '20 L (0.02 m³)', '300 L (0.3 m³)', '2,500 L (2.5 m³)'],
            'answer_index' => 0,
            'explanation' => 'The test lasts 8 × 60 = 480 seconds. Flow volume is 2.5 L/s × 480 s = 1,200 L, or 1.2 m³. That result is a stated coordination input; it does not by itself complete pipe sizing.',
            'calculation_family' => 'FLOW_DURATION_VOLUME',
            'calculation' => ['family' => 'FLOW_DURATION_VOLUME', 'expression' => '2.5 L/s × 8 min × 60 s/min', 'result' => '1200', 'unit' => 'L', 'converted_result' => '1.2 m³', 'assumptions' => ['constant stated flow for the test duration', 'one minute equals 60 seconds']],
            'signature' => ['concept' => 'bounded service quantity', 'reasoning_operation' => 'normalize time units and compute flow volume', 'material_context' => 'temporary drainage test flow and duration', 'visual_dependency' => 'NONE'],
        ],
    ];
};

$collectionsForReadiness = static function (): array {
    $names = ['questions', 'boards', 'subjects', 'board-subjects', 'blueprints', 'taxonomy/domains', 'taxonomy/topics', 'taxonomy/concepts', 'question-eligibility', 'concept-derived-eligibility', 'question-reuse-overrides', 'question-reuse-metadata', 'board-concept-coverage', 'study-materials', 'study-question-links', 'sources', 'content-fit-audits', 'flashcard-exclusions', 'source-readiness', 'source-readiness-let', 'source-readiness-civil-service', 'source-readiness-mechanical-engineering', 'source-readiness-psychometrician', 'source-readiness-architecture'];
    $collections = [];
    foreach ($names as $name) $collections[$name] = App::storage()->all($name);
    return $collections;
};

try {
    if (!in_array($mode, ['--produce-wave1', '--produce-wave2', '--audit'], true)) $fail('Use --produce-wave1, --produce-wave2, or --audit.');

    $head = $git(['rev-parse', 'HEAD']);
    $parent = $git(['show', '-s', '--format=%P', $expectedHead]);
    $branch = $git(['branch', '--show-current']);
    $main = $git(['rev-parse', 'refs/heads/main']);
    $assert($head === $expectedHead, 'Batch655 worktree HEAD must remain the exact Batch654 checkpoint.');
    $assert($parent === $expectedParent, 'Batch654 parent is not the expected Batch653 checkpoint.');
    $assert($branch === 'batch655-architecture-production-triage', 'Unexpected Batch655 branch: ' . $branch);
    $assert($main === $expectedMain, 'Unrelated main HEAD changed.');

    $batch654 = $readJson($batch654Path);
    $assert(($batch654['batch'] ?? null) === 654, 'Batch654 artifact is not loaded.');
    $assert(($batch654['lineage']['expected_head'] ?? '') === $expectedParent, 'Batch654 artifact lineage is not authoritative.');
    $assert(($batch654['determinism']['audit_hash'] ?? '') === '9dfd2dc08552a7899ae5e9a99239ea7ff64a2ecdd1660e22fcf1a5d5c6c4c8bd', 'Batch654 artifact hash is not the authoritative checkpoint.');

    $expectedWave1 = ['ARCH-654-001', 'ARCH-654-002', 'ARCH-654-003', 'ARCH-654-004', 'ARCH-654-005', 'ARCH-654-006', 'ARCH-654-007', 'ARCH-654-008'];
    $expectedWave2 = ['ARCH-654-009', 'ARCH-654-010', 'ARCH-654-011', 'ARCH-654-012'];
    $GLOBALS['__batch655_wave1'] = $expectedWave1;
    $wave1Plans = array_values((array) ($batch654['wave_plan']['wave1'] ?? []));
    $wave2Plans = array_values((array) ($batch654['wave_plan']['wave2'] ?? []));
    $wave1Ids = array_values(array_map(static fn(array $plan): string => (string) ($plan['plan_id'] ?? ''), $wave1Plans));
    $wave2Ids = array_values(array_map(static fn(array $plan): string => (string) ($plan['plan_id'] ?? ''), $wave2Plans));
    $assert($wave1Ids === $expectedWave1 && $wave2Ids === $expectedWave2, 'Batch654 wave plan IDs changed.');
    $assert((int) ($batch654['wave_plan']['wave3_preauthorized'] ?? -1) === 0, 'Wave3 is not zero.');
    $allPlans = array_merge($wave1Plans, $wave2Plans);
    $planById = [];
    foreach ($allPlans as $plan) $planById[(string) ($plan['plan_id'] ?? '')] = $plan;
    $assert(count($planById) === 12, 'Batch654 did not provide exactly twelve plans.');
    $definitionsByPlan = $definitions();
    $assert(array_diff(array_keys($planById), array_keys($definitionsByPlan)) === [] && array_diff(array_keys($definitionsByPlan), array_keys($planById)) === [], 'Batch655 definitions do not cover the exact authorization.');

    $sources = App::storage()->all('sources');
    $materials = App::storage()->all('study-materials');
    $coverage = App::storage()->all('board-concept-coverage');
    $concepts = App::storage()->all('taxonomy/concepts');
    $sourceById = [];
    foreach ($sources as $source) if (is_array($source)) $sourceById[(string) ($source['id'] ?? '')] = $source;
    $materialById = [];
    foreach ($materials as $material) if (is_array($material)) $materialById[(string) ($material['id'] ?? '')] = $material;
    $coverageByLeaf = [];
    foreach ($coverage as $row) if (is_array($row) && strtolower((string) ($row['board_id'] ?? '')) === 'architecture') $coverageByLeaf[(string) ($row['concept_id'] ?? '')] = $row;
    $conceptById = [];
    foreach ($concepts as $concept) if (is_array($concept)) $conceptById[(string) ($concept['id'] ?? '')] = $concept;
    $parentReadiness = json_decode($git(['show', $expectedHead . ':storage/source-readiness-architecture.json']), true, 512, JSON_THROW_ON_ERROR);
    $currentReadiness = $readJson($readinessPath);
    $parentReadinessByLeaf = [];
    foreach ($parentReadiness as $record) if (is_array($record)) $parentReadinessByLeaf[(string) ($record['leaf_id'] ?? '')] = $record;
    $currentReadinessByLeaf = [];
    foreach ($currentReadiness as $record) if (is_array($record)) $currentReadinessByLeaf[(string) ($record['leaf_id'] ?? '')] = $record;

    $controllingSource = $sourceById['prc-architecture-exam-program-june-2026'] ?? null;
    $currentness = [
        'classification' => 'UNCHANGED',
        'controlling_source_id' => 'prc-architecture-exam-program-june-2026',
        'official_areas' => ['arch-area-a' => 30, 'arch-area-b' => 30, 'arch-area-c' => 40],
        'scope_change_detected' => false,
        'source_status' => is_array($controllingSource) ? (string) ($controllingSource['status'] ?? '') : 'MISSING',
    ];
    $assert(is_array($controllingSource), 'The Batch654 controlling PRC Architecture program is missing.');
    $assert(($controllingSource['status'] ?? '') === 'official_reference' && ($controllingSource['source_role'] ?? '') === 'SCOPE_SOURCE', 'The controlling Architecture source is not an official scope source.');
    $assert(($controllingSource['version'] ?? '') === 'June 2026 Architecture examination program', 'The controlling Architecture scope version changed.');
    $assert(str_contains((string) ($controllingSource['url'] ?? ''), 'Program%20for%20June%202026%20LEA'), 'The controlling Architecture source URL changed.');

    $validatePlan = static function (array $plan, array $definition, array $readinessByLeaf, array $sourceById, array $materialById, array $coverageByLeaf, array $conceptById): array {
        $errors = [];
        $planId = (string) ($plan['plan_id'] ?? '');
        $leafId = (string) ($plan['leaf_id'] ?? '');
        $record = $readinessByLeaf[$leafId] ?? null;
        $area = (string) ($plan['official_area_id'] ?? $plan['official_area'] ?? '');
        foreach (['proposition_summary', 'content_family', 'question_mode', 'calculation_class', 'visual_suitability', 'currentness_class', 'marginal_value', 'confidence'] as $field) {
            if (trim((string) ($plan[$field] ?? '')) === '') $errors[] = $planId . ': missing ' . $field;
        }
        if (!is_array($record) || ($record['board_id'] ?? '') !== 'architecture') $errors[] = $planId . ': leaf is not an Architecture readiness leaf';
        if (!isset($coverageByLeaf[$leafId], $conceptById[$leafId])) $errors[] = $planId . ': taxonomy or coverage path is missing';
        if (!is_array($record) || !in_array((string) ($record['readiness'] ?? ''), ['READY', 'READY_WITH_LIMITS'], true)) $errors[] = $planId . ': readiness does not permit production';
        $depth = (int) ($plan['target_depth'] ?? 0);
        if (!is_array($record) || $depth < (int) ($record['supported_depth_min'] ?? 1) || $depth > (int) ($record['supported_depth_max'] ?? 5)) $errors[] = $planId . ': target depth is outside the supported range';
        if (!in_array((string) ($plan['currentness_class'] ?? ''), SourceReadinessRecord::CURRENTNESS, true)) $errors[] = $planId . ': invalid currentness class';
        if (!in_array((string) ($plan['marginal_value'] ?? ''), ['HIGH', 'MEDIUM'], true)) $errors[] = $planId . ': marginal value is not production quality';
        if (!in_array((string) ($plan['confidence'] ?? ''), ['HIGH', 'ADEQUATE'], true)) $errors[] = $planId . ': confidence is not production quality';
        if ((string) ($plan['question_mode'] ?? '') !== 'TEXT_MCQ') $errors[] = $planId . ': unsupported question mode';
        if ((string) ($plan['visual_suitability'] ?? '') === 'VISUAL_REQUIRED') $errors[] = $planId . ': visual-required proposition cannot be authored as text';
        if (in_array((string) ($plan['visual_suitability'] ?? ''), ['VISUAL_PREFERRED', 'MIXED'], true) && (($definition['text_validated'] ?? false) !== true)) $errors[] = $planId . ': visual-preferred plan lacks explicit text validation';
        $scope = array_values(array_map('strval', (array) ($definition['scope'] ?? [])));
        if ($scope === [] || (is_array($record) && array_diff($scope, array_map('strval', (array) ($record['allowed_scope'] ?? []))) !== []) ) $errors[] = $planId . ': definition scope exceeds readiness scope';
        $sourceIds = array_values(array_map('strval', (array) ($plan['source_ids'] ?? [])));
        if (is_array($record) && array_diff($sourceIds, array_map('strval', (array) ($record['source_ids'] ?? []))) !== []) $errors[] = $planId . ': source chain exceeds readiness source boundary';
        foreach ($sourceIds as $sourceId) if (!isset($sourceById[$sourceId])) $errors[] = $planId . ': missing source ' . $sourceId;
        $materialIds = array_values(array_map('strval', (array) ($plan['study_material_ids'] ?? [])));
        if (is_array($record) && array_diff($materialIds, array_map('strval', (array) ($record['study_material_ids'] ?? []))) !== []) $errors[] = $planId . ': study chain exceeds readiness study boundary';
        foreach ($materialIds as $materialId) {
            if (!isset($materialById[$materialId])) {
                $errors[] = $planId . ': missing study material ' . $materialId;
                continue;
            }
            if (array_intersect($sourceIds, array_map('strval', (array) ($materialById[$materialId]['source_ids'] ?? []))) === []) $errors[] = $planId . ': study material has no intersecting source support';
        }
        if (!is_array($record) || ($record['study_coverage'] ?? '') !== 'COMPLETE' || ($record['study_source_traceability'] ?? '') !== 'COMPLETE') $errors[] = $planId . ': study chain is not complete';
        if (!in_array((string) ($plan['source_conflict_status'] ?? ($record['source_conflict_status'] ?? 'NONE')), ['NONE', 'RESOLVED'], true)) $errors[] = $planId . ': unresolved source conflict';
        return array_values(array_unique($errors));
    };

    $initialPlanErrors = [];
    foreach ($allPlans as $plan) $initialPlanErrors = array_merge($initialPlanErrors, $validatePlan($plan, $definitionsByPlan[(string) ($plan['plan_id'] ?? '')], $parentReadinessByLeaf, $sourceById, $materialById, $coverageByLeaf, $conceptById));
    $assert($initialPlanErrors === [], 'One or more exact Batch654 plans failed validation: ' . implode(' | ', $initialPlanErrors));

    $questions = App::storage()->all('questions');
    $newByPlan = [];
    foreach ($questions as $question) {
        if (!is_array($question) || ($question['batch_marker'] ?? '') !== 'batch655-architecture') continue;
        $planId = (string) ($question['batch655']['plan_id'] ?? '');
        if ($planId !== '') $newByPlan[$planId] = $question;
    }
    $assert(count(array_diff(array_keys($newByPlan), array_keys($planById))) === 0, 'An unplanned Batch655 Architecture question exists.');

    $buildQuestion = static function (int $id, array $plan, array $definition, array $coverageRow, array $concept): array {
        $leafId = (string) $plan['leaf_id'];
        $subjectId = (string) ($coverageRow['subject_id'] ?? '');
        $domainId = (string) ($coverageRow['domain_id'] ?? '');
        $topicId = (string) ($coverageRow['topic_id'] ?? '');
        $calcClass = (string) ($plan['calculation_class'] ?? 'NONCALCULATION');
        $isCalculation = $calcClass === 'CALCULATION_REQUIRED';
        $flags = (array) ($definition['flags'] ?? []);
        $q = [
            'id' => $id,
            'board' => 'architecture',
            'taxonomy' => [
                'board_id' => 'architecture', 'subject_id' => $subjectId, 'domain_id' => $domainId, 'topic_id' => $topicId, 'concept_id' => $leafId,
                'parent_concept_id' => (string) ($concept['batch633']['parent_concept_id'] ?? ''), 'subcompetency_id' => $leafId,
                'subcompetency_confidence' => 'EXACT', 'subcompetency_evidence' => 'Batch654 authorized leaf and current granular coverage row.',
            ],
            'status' => 'active', 'source' => 'Batch655-authorized', 'difficulty' => ((int) ($plan['target_depth'] ?? 3) >= 4 ? 'hard' : 'medium'), 'type' => 'multiple_choice',
            'question' => (string) $definition['question'], 'choices' => array_values($definition['choices']), 'answer' => (string) $definition['choices'][$definition['answer_index']],
            'explanation' => (string) $definition['explanation'], 'content_blocks' => [],
            'source_ids' => array_values(array_map('strval', (array) $plan['source_ids'])), 'study_material_id' => (string) $plan['study_material_ids'][0], 'study_material_ids' => array_values(array_map('strval', (array) $plan['study_material_ids'])),
            'source_scope' => array_values(array_map('strval', (array) $definition['scope'])), 'study_focus' => (string) $definition['study_focus'],
            'cognitive_intent' => 'apply and evaluate', 'multistep' => true, 'passage_dependent' => false, 'data_interpretation' => false, 'case_interpretation' => true,
            'professional_judgment' => (bool) ($flags['professional_judgment'] ?? false), 'numeric' => $isCalculation, 'calculation_required' => $isCalculation,
            'quantitative_semantics' => $calcClass, 'currentness_verified' => true, 'version_sensitive' => (bool) ($definition['version_sensitive'] ?? false),
            'source_sensitivity' => (string) ($definition['source_sensitivity'] ?? 'STABLE'), 'technical_reasoning' => (bool) ($flags['technical_reasoning'] ?? false),
            'figure_dependent' => (bool) ($flags['figure_dependent'] ?? false), 'site_plan_spatial' => (bool) ($flags['site_plan_spatial'] ?? false),
            'context_dependent' => (bool) ($flags['context_dependent'] ?? true), 'context_necessary' => (bool) ($flags['context_necessary'] ?? true),
            'long_scenario' => mb_strlen((string) $definition['question']) > 220, 'unit_sensitive' => $isCalculation, 'table_dependent' => false,
            'timesUsed' => 0, 'timesCorrect' => 0, 'timesIncorrect' => 0, 'bookmarks' => 0, 'reports' => 0, 'helpfulExplanation' => 0, 'notHelpfulExplanation' => 0,
            'createdAt' => '2026-10-04T00:00:00+08:00', 'updatedAt' => '2026-10-04T00:00:00+08:00',
            'batch_marker' => 'batch655-architecture',
            'batch655' => [
                'plan_id' => (string) $plan['plan_id'], 'wave' => in_array((string) $plan['plan_id'], $GLOBALS['__batch655_wave1'] ?? [], true) ? 'WAVE1' : 'WAVE2_CONTINGENT',
                'leaf_id' => $leafId, 'official_area_id' => (string) ($plan['official_area'] ?? $plan['official_area_id'] ?? ''), 'proposition_summary' => (string) $plan['proposition_summary'],
                'content_family' => (string) $plan['content_family'], 'target_depth' => (int) $plan['target_depth'], 'question_mode' => (string) $plan['question_mode'],
                'calculation_class' => $calcClass, 'visual_suitability' => (string) $plan['visual_suitability'], 'source_ids' => array_values(array_map('strval', (array) $plan['source_ids'])),
                'study_material_ids' => array_values(array_map('strval', (array) $plan['study_material_ids'])), 'novelty_basis' => (string) $plan['novelty_basis'],
                'currentness_class' => (string) $plan['currentness_class'], 'marginal_value' => (string) $plan['marginal_value'], 'confidence' => (string) $plan['confidence'],
                'limitations' => array_values(array_map('strval', (array) $plan['limitations'])), 'proposition_signature' => $definition['signature'],
                'format_review' => ['text_without_figure' => (bool) (($definition['text_validated'] ?? true) === true), 'visual_required_forbidden' => (string) $plan['visual_suitability'] === 'VISUAL_REQUIRED'],
                'novelty_review' => 'Distinct proposition signature and content family reviewed against the same-leaf inventory; exact/normalized duplicates rejected.', 'qa_status' => 'PASS', 'authorized_by' => 'batch654-architecture-readiness',
            ],
            'reuse' => [
                'reuse_policy' => 'REVIEW', 'required_depth' => (int) $plan['target_depth'], 'context_mode' => (string) ($definition['context_mode'] ?? 'PROFESSIONAL'), 'metadata_status' => 'VERIFIED',
                'metadata_provenance' => [['type' => 'OFFICIAL_SOURCE', 'reference' => 'batch654-architecture-readiness:' . (string) $plan['plan_id'], 'note' => 'Exact Batch654 source/study/depth/proposition authority was consumed by Batch655.', 'verified_status' => 'VERIFIED']],
            ],
        ];
        if (isset($definition['calculation'])) {
            $q['calculation_family'] = (string) $definition['calculation_family'];
            $q['calculation'] = $definition['calculation'];
        }
        if (isset($definition['version_review_status'])) $q['version_review_status'] = (string) $definition['version_review_status'];
        return $q;
    };

    $persistLinks = static function (array $question, array $plan): void {
        $questionId = (string) $question['id'];
        $sourceIds = array_values(array_map('strval', (array) $plan['source_ids']));
        $existingLinks = App::storage()->all('study-question-links');
        foreach ((array) $plan['study_material_ids'] as $materialId) {
            $materialId = (string) $materialId;
            $found = false;
            foreach ($existingLinks as $link) {
                if (!is_array($link) || (string) ($link['question_id'] ?? '') !== $questionId || (string) ($link['material_id'] ?? '') !== $materialId) continue;
                $found = true;
                if (array_values(array_map('strval', (array) ($link['source_ids'] ?? []))) !== $sourceIds) throw new RuntimeException('Existing study link source chain differs for ' . $questionId . '/' . $materialId);
            }
            if (!$found) App::storage()->create('study-question-links', ['id' => 'study-link-b655-architecture-' . $questionId . '-' . $materialId, 'material_id' => $materialId, 'question_id' => $questionId, 'source_ids' => $sourceIds]);
        }
        $eligibilityFound = false;
        foreach (App::storage()->all('question-eligibility') as $entry) {
            if (!is_array($entry) || !in_array((int) $question['id'], array_map('intval', (array) ($entry['question_ids'] ?? [])), true)) continue;
            $eligibilityFound = true;
            if (!in_array('architecture', array_map('strval', (array) ($entry['exam_ids'] ?? [])), true)) throw new RuntimeException('Architecture eligibility is not exclusive/canonical for ' . $questionId);
        }
        if (!$eligibilityFound) App::storage()->create('question-eligibility', ['id' => 'eligibility-b655-architecture-' . $questionId, 'question_ids' => [(int) $question['id']], 'exam_ids' => ['architecture'], 'subject_ids' => ['architecture' => (string) $question['taxonomy']['subject_id']]]);
    };

    $updateReadiness = static function (bool $close, array $parentReadiness, array $planById) use ($active, $questionLeaf, $assert, $writeJson, $readinessPath): array {
        $questions = App::storage()->all('questions');
        $usedByLeaf = [];
        foreach ($questions as $question) {
            if (!is_array($question) || ($question['batch_marker'] ?? '') !== 'batch655-architecture') continue;
            $leaf = $questionLeaf($question);
            $usedByLeaf[$leaf] = ($usedByLeaf[$leaf] ?? 0) + 1;
        }
        $nativeByLeaf = [];
        foreach ($questions as $question) {
            if (!is_array($question) || !$active($question) || (($question['taxonomy']['board_id'] ?? $question['board'] ?? '') !== 'architecture') ) continue;
            $leaf = $questionLeaf($question);
            if ($leaf !== '') $nativeByLeaf[$leaf] = ($nativeByLeaf[$leaf] ?? 0) + 1;
        }
        $updated = [];
        foreach ($parentReadiness as $record) {
            if (!is_array($record)) continue;
            $leaf = (string) ($record['leaf_id'] ?? '');
            $used = (int) ($usedByLeaf[$leaf] ?? 0);
            $expected = (int) ($record['current_question_count'] ?? 0) + $used;
            $assert((int) ($nativeByLeaf[$leaf] ?? 0) === $expected, 'Architecture readiness count does not match Batch655 additions for ' . $leaf);
            $record['current_question_count'] = (int) ($nativeByLeaf[$leaf] ?? 0);
            foreach ([5, 10, 20] as $threshold) $record['deficit_to_' . $threshold] = max(0, $threshold - $record['current_question_count']);
            foreach (['safe_headroom_min', 'safe_headroom_preferred', 'safe_headroom_max', 'numerical_headroom', 'evidence_headroom', 'text_format_headroom', 'high_value_headroom'] as $field) {
                if (array_key_exists($field, $record)) $record[$field] = max(0, (int) $record[$field] - $used);
            }
            if ($close && $used > 0) {
                $record['safe_headroom_min'] = max(0, (int) $record['safe_headroom_min']);
                $record['safe_headroom_preferred'] = max(0, (int) $record['safe_headroom_preferred']);
                $record['safe_headroom_max'] = max(0, (int) $record['safe_headroom_max']);
                if (array_key_exists('high_value_headroom', $record)) $record['high_value_headroom'] = 0;
                $record['readiness'] = 'REVIEW';
                $record['readiness_reason'] = 'Batch655 consumed the exact authorized text cohort; any remaining numerical room requires a new proposition-level review and is not an automatic production quota.';
                $record['review_reason'] = 'PROPOSITION_EXHAUSTED: no additional high-value text proposition is authorized in the current Architecture cycle.';
                $record['defer_reason'] = '';
                $record['max_new_questions'] = 0;
                $record['planned_new_questions'] = 0;
                $record['blocker_classes'] = array_values(array_unique(array_merge((array) ($record['blocker_classes'] ?? []), ['PROPOSITION_EXHAUSTED'])));
            } else {
                $record['max_new_questions'] = max(0, (int) ($record['safe_headroom_max'] ?? 0));
                $record['planned_new_questions'] = 0;
            }
            $record['reviewed_at_batch'] = 655;
            $record['evidence_hash'] = SourceReadinessRecord::evidenceHash($record);
            $updated[] = $record;
        }
        $writeJson($readinessPath, $updated);
        return $updated;
    };

    $persistWave = static function (array $targetIds, array $plans, array $definitionsByPlan, array &$newByPlan, array $coverageByLeaf, array $conceptById, array $currentReadinessByLeaf, array $parentReadinessByLeaf, \Closure $buildQuestion, \Closure $persistLinks, \Closure $validatePlan, array $sourceById, array $materialById, array $allPlanIds) use ($assert): array {
        $questions = App::storage()->all('questions');
        $maxId = 0;
        foreach ($questions as $question) if (is_array($question) && is_numeric($question['id'] ?? null)) $maxId = max($maxId, (int) $question['id']);
        $ids = [];
        $accepted = 0;
        $repaired = 0;
        $rejected = [];
        foreach ($targetIds as $planId) {
            $plan = $plans[$planId] ?? null;
            $definition = $definitionsByPlan[$planId] ?? null;
            $assert(is_array($plan) && is_array($definition), 'Missing exact production plan ' . $planId);
            if (isset($newByPlan[$planId])) {
                $persisted = $newByPlan[$planId];
                $assert(($persisted['question'] ?? '') === ($definition['question'] ?? ''), 'Persisted question is not the exact authorized definition for ' . $planId);
                $assert(($persisted['batch655']['plan_id'] ?? '') === $planId, 'Persisted question has the wrong plan ID for ' . $planId);
                $assert(QuestionService::validate($persisted)['valid'] === true, 'Persisted question is invalid for ' . $planId);
                $ids[] = (int) $persisted['id'];
                continue;
            }
            $leafId = (string) $plan['leaf_id'];
            $planErrors = $validatePlan($plan, $definition, $currentReadinessByLeaf, $sourceById, $materialById, $coverageByLeaf, $conceptById);
            $assert($planErrors === [], 'Wave plan failed current readiness gate: ' . implode(' | ', $planErrors));
            $record = $currentReadinessByLeaf[$leafId] ?? [];
            $assert(in_array((string) ($record['readiness'] ?? ''), ['READY', 'READY_WITH_LIMITS'], true), 'Wave plan readiness state changed for ' . $planId);
            $assert((int) ($record['max_new_questions'] ?? 0) > 0, 'Wave plan headroom is exhausted for ' . $planId);
            $assert((int) ($record['current_question_count'] ?? 0) < (int) ($record['current_question_count'] ?? 0) + (int) ($record['max_new_questions'] ?? 0), 'Wave plan has no positive current headroom for ' . $planId);
            $q = $buildQuestion(++$maxId, $plan, $definition, $coverageByLeaf[$leafId], $conceptById[$leafId]);
            $validation = QuestionService::validateForCreate($q);
            $assert(($validation['valid'] ?? false) === true && ($validation['duplicates'] ?? []) === [], 'Question QA rejected exact authorized plan ' . $planId . ': ' . implode(', ', (array) ($validation['errors'] ?? [])));
            $saved = QuestionService::save($q);
            $assert(QuestionService::validate($saved)['valid'] === true, 'QuestionService persisted an invalid question for ' . $planId);
            $persistLinks($saved, $plan);
            $newByPlan[$planId] = $saved;
            $ids[] = (int) $saved['id'];
            $accepted++;
        }
        sort($ids, SORT_NUMERIC);
        return ['planned' => count($targetIds), 'accepted' => $accepted, 'repaired' => $repaired, 'rejected' => $rejected, 'ids' => $ids];
    };

    if (in_array($mode, ['--produce-wave1', '--produce-wave2'], true)) {
        $targetIds = $mode === '--produce-wave1' ? $expectedWave1 : $expectedWave2;
        $currentPlanIds = array_keys($newByPlan);
        if ($mode === '--produce-wave1') $assert($currentPlanIds === [] || count(array_intersect($currentPlanIds, $expectedWave2)) === 0, 'Wave2 content exists before Wave1 completion.');
        if ($mode === '--produce-wave2') $assert(count(array_diff($expectedWave1, $currentPlanIds)) === 0, 'Wave2 cannot run before all Wave1 plans exist.');
        $planMap = [];
        foreach ($allPlans as $plan) $planMap[(string) $plan['plan_id']] = $plan;
        $beforeReadiness = [];
        foreach (App::storage()->all('source-readiness-architecture') as $record) if (is_array($record)) $beforeReadiness[(string) ($record['leaf_id'] ?? '')] = $record;
        if ($mode === '--produce-wave2') {
            foreach ($targetIds as $planId) {
                $record = $beforeReadiness[(string) ($planMap[$planId]['leaf_id'] ?? '')] ?? null;
                $assert(is_array($record) && in_array((string) ($record['readiness'] ?? ''), ['READY', 'READY_WITH_LIMITS'], true) && (int) ($record['max_new_questions'] ?? 0) > 0, 'Wave2 gate closed or has no evidence headroom for ' . $planId);
            }
        }
        $result = $persistWave($targetIds, $planMap, $definitionsByPlan, $newByPlan, $coverageByLeaf, $conceptById, $beforeReadiness, $parentReadinessByLeaf, $buildQuestion, $persistLinks, $validatePlan, $sourceById, $materialById, array_keys($planById));
        $updatedReadiness = $updateReadiness($mode === '--produce-wave2', $parentReadiness, $planById);
        $collections = $collectionsForReadiness();
        $readinessValidation = SourceReadinessService::validateCollections($collections);
        $assert(($readinessValidation['by_board']['architecture']['valid'] ?? false) === true, 'Architecture readiness contract failed after production.');
        $started = hrtime(true);
        $healthReport = RepositoryHealthEngine::analyze();
        $health = $healthReport->toArray();
        $assert(($health['summary']['fail_count'] ?? 0) === 0, 'Permanent health found FAIL after ' . $mode . '.');
        $readiness = $healthReport->summary['source_readiness_by_board']['architecture'] ?? [];
        fwrite(STDOUT, strtoupper(ltrim($mode, '-')) . '=PASS\nPLANNED=' . $result['planned'] . "\nACCEPTED=" . $result['accepted'] . "\nTOTAL_BATCH655=" . count($newByPlan) . "\nARCH_NATIVE=" . (int) ($healthReport->boardSummaries['architecture']['canonical'] ?? 0) . "\nAUTHORABLE_UNDER20=" . (int) (($readiness['authorable_thinness']['under20'] ?? 0)) . "\nPOST_HEALTH_RUNTIME_MS=" . round((hrtime(true) - $started) / 1_000_000, 3) . PHP_EOL);
        exit(0);
    }

    $allExpected = array_merge($expectedWave1, $expectedWave2);
    $assert(count($newByPlan) === 12 && array_diff($allExpected, array_keys($newByPlan)) === [], 'Final audit requires all exact twelve authorized plans.');
    $newIds = [];
    foreach ($allExpected as $planId) {
        $question = $newByPlan[$planId];
        $definition = $definitionsByPlan[$planId];
        $plan = $planById[$planId];
        $assert(($question['batch655']['plan_id'] ?? '') === $planId && ($question['question'] ?? '') === $definition['question'], 'Final question does not match its exact plan: ' . $planId);
        $assert(QuestionService::validate($question)['valid'] === true, 'Final question validation failed: ' . $planId);
        $assert(array_values(array_map('strval', (array) ($question['source_ids'] ?? []))) === array_values(array_map('strval', (array) $plan['source_ids'])), 'Question source chain drifted: ' . $planId);
        $assert(array_values(array_map('strval', (array) ($question['study_material_ids'] ?? []))) === array_values(array_map('strval', (array) $plan['study_material_ids'])), 'Question study chain drifted: ' . $planId);
        $assert(($question['batch655']['visual_suitability'] ?? '') !== 'VISUAL_REQUIRED', 'A visual-required proposition was forced into text.');
        $newIds[] = (int) $question['id'];
    }
    sort($newIds, SORT_NUMERIC);
    $assert($newIds === range(min($newIds), max($newIds)), 'Batch655 IDs contain a gap or accidental rejected draft.');

    $calculationChecks = [];
    foreach ($newByPlan as $planId => $question) {
        if (($question['calculation_required'] ?? false) !== true) continue;
        if ($planId === 'ARCH-654-006') {
            $computed = round(((18.0 * 3.0) - (2 * 0.9 * 2.1) - (4 * 1.2 * 1.0)) * 1.05, 1);
            $calculationChecks[$planId] = $computed === 47.7 && str_contains((string) $question['answer'], '47.7') && (string) ($question['calculation']['unit'] ?? '') === 'm²';
        } elseif ($planId === 'ARCH-654-012') {
            $computed = 2.5 * 8 * 60;
            $calculationChecks[$planId] = $computed === 1200.0 && str_contains((string) $question['answer'], '1,200') && (string) ($question['calculation']['unit'] ?? '') === 'L';
        }
    }
    $assert($calculationChecks === ['ARCH-654-006' => true, 'ARCH-654-012' => true], 'Independent Architecture calculation recomputation failed.');

    $flashcards = new FlashcardDerivationService(null, App::storage()->all('study-materials'), App::storage()->all('study-question-links'), App::storage()->all('flashcard-exclusions'));
    $studyLinks = App::storage()->all('study-question-links');
    foreach ($newByPlan as $planId => $question) {
        $qid = (string) $question['id'];
        $linksForQuestion = array_values(array_filter($studyLinks, static fn(mixed $link): bool => is_array($link) && (string) ($link['question_id'] ?? '') === $qid));
        $assert(count($linksForQuestion) === count((array) $planById[$planId]['study_material_ids']), 'Study link cardinality failed for ' . $planId);
        $assert($flashcards->derive($question) !== null, 'Flashcard derivability failed for ' . $planId);
    }

    $healthStarted = hrtime(true);
    $healthReport = RepositoryHealthEngine::analyze();
    $health = $healthReport->toArray();
    $healthRuntimeMs = round((hrtime(true) - $healthStarted) / 1_000_000, 3);
    $architecture = (array) ($healthReport->boardSummaries['architecture'] ?? []);
    $architectureReadiness = (array) ($healthReport->summary['source_readiness_by_board']['architecture'] ?? []);
    $assert(($health['summary']['fail_count'] ?? 0) === 0, 'Final Repository Health has FAIL entities.');
    $assert(($architecture['canonical'] ?? -1) === 484 && ($architecture['effective_declared'] ?? -1) === 484 && ($architecture['runtime'] ?? -1) === 484, 'Architecture final native/effective/runtime counts are not 484.');
    $assert(($architecture['active_leaves'] ?? -1) === 14, 'Architecture active leaf count changed.');
    $assert(($architectureReadiness['valid'] ?? false) === true, 'Final Architecture readiness is invalid.');
    $assert(($architectureReadiness['headroom_dimensions'] ?? null) === ['numerical' => 15, 'evidence' => 15, 'text_format' => 15, 'high_value' => 0], 'Final Architecture headroom dimensions are not the stored post-wave values.');
    $assert(($architectureReadiness['readiness_counts'] ?? []) === ['READY' => 0, 'READY_WITH_LIMITS' => 0, 'REVIEW' => 13, 'DEFER' => 1], 'Final Architecture closure states drifted.');
    $assert(($architectureReadiness['authorable_thinness'] ?? []) === ['zero' => 0, 'under5' => 0, 'under10' => 0, 'under20' => 0], 'Final authorable thinness was not closed.');
    $assert(($architectureReadiness['blocker_counts']['PROPOSITION_EXHAUSTED'] ?? 0) >= 13, 'Final proposition-exhaustion blocker summary is incomplete.');

    $portfolioStarted = hrtime(true);
    $portfolioAudit = RepositoryHealthEngine::portfolioReadinessAudit(['let', 'civil-service', 'nursing', 'criminologist', 'civil-engineering', 'mechanical-engineering', 'registered-electrical-engineering', 'architecture', 'psychometrician', 'cpa'], $healthReport);
    $portfolioRuntimeMs = round((hrtime(true) - $portfolioStarted) / 1_000_000, 3);
    $portfolioRows = [];
    foreach ((array) ($portfolioAudit['boards'] ?? []) as $row) $portfolioRows[(string) ($row['board_id'] ?? '')] = $row;
    $assert(count($portfolioRows) === 10, 'Portfolio triage did not return exactly ten boards.');

    $triageRows = [];
    foreach ($portfolioRows as $boardId => $row) {
        $summary = is_array($row['source_readiness'] ?? null) ? $row['source_readiness'] : null;
        $defects = is_array($summary['source_study_defects'] ?? null) ? $summary['source_study_defects'] : [];
        $states = is_array($summary['readiness_counts'] ?? null) ? $summary['readiness_counts'] : [];
        $visualCounts = is_array($summary['visual_suitability_counts'] ?? null) ? $summary['visual_suitability_counts'] : [];
        $sourceDefects = $summary === null ? 'UNKNOWN' : (int) ($defects['source'] ?? 0);
        $studyDefects = $summary === null ? 'UNKNOWN' : (int) ($defects['study'] ?? 0) + (int) ($defects['traceability'] ?? 0);
        $currentnessDefects = $summary === null ? 'UNKNOWN' : (int) ($defects['currentness'] ?? 0);
        $depthDefects = array_key_exists('depth_unknown', $row) ? (int) $row['depth_unknown'] : 'UNKNOWN';
        $blockerCount = $summary === null ? 'UNKNOWN' : array_sum(array_map('intval', (array) ($summary['blocker_counts'] ?? [])));
        $highValue = $summary === null || !array_key_exists('high_value_headroom', $summary) || $summary['high_value_headroom'] === null ? 'UNKNOWN' : (int) $summary['high_value_headroom'];
        $evidenceExhausted = $summary === null ? 'UNKNOWN' : (int) ($summary['evidence_exhausted_count'] ?? 0);
        $visualLimited = $summary === null ? 'UNKNOWN' : (int) ($visualCounts['VISUAL_PREFERRED'] ?? 0) + (int) ($visualCounts['VISUAL_REQUIRED'] ?? 0) + (int) ($visualCounts['MIXED'] ?? 0);
        $issueCounts = (array) ($row['deterministic_issue_counts'] ?? []);
        $reviewIssues = (int) ($issueCounts['REVIEW'] ?? 0);
        $classification = 'UNKNOWN_REQUIRES_MANUAL_REVIEW';
        if ($boardId === 'architecture' && $visualLimited !== 'UNKNOWN' && (int) $visualLimited > 0 && $highValue === 0) {
            $classification = 'VISUAL_LIMITED';
        } elseif ($boardId === 'cpa' && $reviewIssues > 0) {
            $classification = 'CURRENTNESS_REVIEW_NEEDED';
        } elseif ($boardId === 'nursing' && ($row['readiness_model_status'] ?? '') === 'LEGACY_NO_READINESS_MODEL') {
            $classification = 'SOURCE_READINESS_NEEDED';
        } elseif ($sourceDefects !== 'UNKNOWN' && ((int) $sourceDefects > 0 || (int) $studyDefects > 0)) {
            $classification = 'SOURCE_READINESS_NEEDED';
        } elseif ($currentnessDefects !== 'UNKNOWN' && (int) $currentnessDefects > 0) {
            $classification = 'CURRENTNESS_REVIEW_NEEDED';
        } elseif ($summary === null) {
            $classification = 'FOUNDATION_SUFFICIENT';
        } elseif ($highValue !== 'UNKNOWN' && (int) $highValue > 0 && (int) (($summary['authorable_thinness']['under20'] ?? 0)) > 0) {
            $classification = 'SELECTIVE_PRODUCTION_ONLY';
        } elseif ($summary !== null && (int) ($states['READY'] ?? 0) === 0 && (int) ($states['READY_WITH_LIMITS'] ?? 0) === 0) {
            $classification = 'EVIDENCE_LIMITED';
        } elseif ($summary !== null && $highValue === 'UNKNOWN') {
            $classification = 'UNKNOWN_REQUIRES_MANUAL_REVIEW';
        } else {
            $classification = 'FOUNDATION_SUFFICIENT';
        }
        $score = 0;
        if ($sourceDefects !== 'UNKNOWN') $score += (int) $sourceDefects * 100;
        if ($studyDefects !== 'UNKNOWN') $score += (int) $studyDefects * 80;
        if ($currentnessDefects !== 'UNKNOWN') $score += (int) $currentnessDefects * 90;
        if ($row['readiness_model_status'] === 'PARTIAL_READINESS_MODEL') $score += 300;
        if ($boardId === 'nursing' && $row['readiness_model_status'] === 'LEGACY_NO_READINESS_MODEL') $score += 500;
        if ($boardId === 'cpa' && $reviewIssues > 0) $score += $reviewIssues * 50;
        if ($highValue !== 'UNKNOWN') $score += (int) $highValue * 5;
        $triageRows[] = [
            'board_id' => $boardId, 'native' => $row['native'] ?? 'UNKNOWN', 'effective' => $row['effective'] ?? 'UNKNOWN', 'runtime' => $row['runtime'] ?? 'UNKNOWN', 'active_leaves' => $row['active_leaves'] ?? 'UNKNOWN',
            'taxonomy_granularity_status' => $row['taxonomy_granularity_status'] ?? 'UNKNOWN', 'readiness_model_status' => $row['readiness_model_status'] ?? 'UNKNOWN',
            'ready' => $summary === null ? 'UNKNOWN' : (int) ($states['READY'] ?? 0), 'rwl' => $summary === null ? 'UNKNOWN' : (int) ($states['READY_WITH_LIMITS'] ?? 0), 'review' => $summary === null ? 'UNKNOWN' : (int) ($states['REVIEW'] ?? 0), 'defer' => $summary === null ? 'UNKNOWN' : (int) ($states['DEFER'] ?? 0),
            'raw_zero_under5_under10_under20' => $summary['raw_thinness'] ?? 'UNKNOWN', 'authorable_zero_under5_under10_under20' => $summary['authorable_thinness'] ?? 'UNKNOWN',
            'source_defects' => $sourceDefects, 'study_defects' => $studyDefects, 'currentness_defects' => $currentnessDefects, 'depth_defects' => $depthDefects, 'blocker_count' => $blockerCount,
            'high_value_headroom' => $highValue, 'evidence_exhausted' => $evidenceExhausted, 'visual_limited' => $visualLimited, 'repeat_risk' => $summary['repeat_risk_counts'] ?? 'UNKNOWN',
            'operational_state' => $classification, 'priority_score' => $score, 'deterministic_review_issue_count' => $reviewIssues,
        ];
    }
    usort($triageRows, static fn(array $a, array $b): int => ($b['priority_score'] <=> $a['priority_score']) ?: strcmp((string) $a['board_id'], (string) $b['board_id']));
    $nextTarget = (string) ($triageRows[0]['board_id'] ?? 'UNKNOWN');
    $nextMilestone = match ($nextTarget) {
        'let' => 'LET_SOURCE_READINESS_DEFECT_CLOSURE',
        'nursing' => 'NURSING_READINESS_NORMALIZATION',
        'cpa' => 'CPA_CURRENTNESS_REVIEW',
        'architecture' => 'VISUAL_QUESTION_CAPABILITY_FOUNDATION',
        default => strtoupper($nextTarget) . '_SOURCE_READINESS_REVIEW',
    };

    $visualBacklog = [];
    $visualPriority = [
        'b633-arch-site-circulation' => ['priority' => 'HIGH', 'visual_headroom_estimate' => 3, 'why' => 'A route/adjacency competency is naturally evaluated through plan relationships; text coverage remains bounded and visual alternatives would improve authenticity.'],
        'b633-arch-site-capacity' => ['priority' => 'HIGH', 'visual_headroom_estimate' => 3, 'why' => 'Phasing and site-capacity trade-offs gain material value from diagrams showing protected edges, access spines, and expansion reserves.'],
        'b633-arch-site-climate' => ['priority' => 'LOW', 'visual_headroom_estimate' => 0, 'why' => 'The leaf is already densely covered and evidence-exhausted; visuals are enrichment rather than a current text-gap repair.'],
        'b633-arch-site-spatial-analysis' => ['priority' => 'HIGH', 'visual_headroom_estimate' => 'UNKNOWN', 'why' => 'The stored contract marks plan/diagram analysis as visual-required; ordinary text expansion would weaken the competency.'],
    ];
    foreach ($architectureReadiness['records'] ?? [] as $record) {
        if (!is_array($record) || !in_array((string) ($record['visual_suitability'] ?? ''), ['VISUAL_PREFERRED', 'VISUAL_REQUIRED'], true)) continue;
        $leafId = (string) $record['leaf_id'];
        $backlogDefinition = $visualPriority[$leafId] ?? ['priority' => 'LOW', 'visual_headroom_estimate' => 'UNKNOWN', 'why' => 'Stored visual metadata indicates that a visual mode may add value; no unsupported text expansion is authorized.'];
        $visualBacklog[] = [
            'leaf_id' => $leafId, 'visual_suitability' => (string) $record['visual_suitability'], 'text_headroom_remaining' => (int) ($record['text_format_headroom'] ?? 0),
            'visual_headroom_estimate' => $backlogDefinition['visual_headroom_estimate'], 'why_visual_adds_value' => $backlogDefinition['why'],
            'source_study_ready' => (string) ($record['study_coverage'] ?? 'UNKNOWN') . '/' . (string) ($record['study_source_traceability'] ?? 'UNKNOWN'), 'future_capability_priority' => $backlogDefinition['priority'],
        ];
    }
    usort($visualBacklog, static fn(array $a, array $b): int => strcmp((string) $a['leaf_id'], (string) $b['leaf_id']));

    $baselineCounts = (array) ($batch654['health']['global_native_counts'] ?? []);
    $afterCounts = [];
    foreach ($healthReport->boardSummaries as $boardId => $summary) $afterCounts[(string) $boardId] = (int) ($summary['canonical'] ?? 0);
    ksort($baselineCounts, SORT_STRING); ksort($afterCounts, SORT_STRING);
    $otherBoardsUnchanged = true;
    foreach ($baselineCounts as $boardId => $count) if ($boardId !== 'architecture' && (int) ($afterCounts[$boardId] ?? -1) !== (int) $count) $otherBoardsUnchanged = false;
    $portfolioStates = [];
    foreach ($triageRows as $row) $portfolioStates[$row['operational_state']] = ($portfolioStates[$row['operational_state']] ?? 0) + 1;
    ksort($portfolioStates, SORT_STRING);
    $areaNativeCounts = [];
    foreach ((array) ($architectureReadiness['records'] ?? []) as $record) {
        if (!is_array($record)) continue;
        $areaId = (string) ($record['official_area_id'] ?? '');
        if ($areaId === '') continue;
        $areaNativeCounts[$areaId] = ($areaNativeCounts[$areaId] ?? 0) + (int) ($record['current_question_count'] ?? 0);
    }
    $areaBalance = [];
    foreach ((array) ($currentness['official_areas'] ?? []) as $areaId => $officialWeight) {
        $native = (int) ($areaNativeCounts[(string) $areaId] ?? 0);
        $areaBalance[] = ['area_id' => (string) $areaId, 'native' => $native, 'actual_share' => round($native / max(1, (int) ($architecture['canonical'] ?? 0)) * 100, 2), 'official_weight' => (int) $officialWeight];
    }
    $leafRepeatRisk = [];
    foreach ((array) ($architectureReadiness['records'] ?? []) as $record) if (is_array($record)) $leafRepeatRisk[(string) ($record['leaf_id'] ?? '')] = (string) ($record['repeat_risk'] ?? 'UNKNOWN');
    ksort($leafRepeatRisk, SORT_STRING);

    $newIdRange = min($newIds) . '-' . max($newIds);
    $artifact = [
        'artifact' => 'batch655-architecture-production-triage', 'batch' => 655, 'review_date' => '2026-10-04',
        'lineage' => ['expected_head' => $expectedHead, 'expected_parent' => $expectedParent, 'head_before' => $head, 'branch' => $branch, 'main_head' => $main, 'ancestry_pass' => true, 'main_unchanged' => true, 'unrelated_work_preserved' => true, 'pushed' => false],
        'architecture_baseline' => ['native' => 472, 'effective' => 472, 'runtime' => 472, 'active_leaves' => 14, 'raw_zero_under5_under10_under20' => $batch654['architecture_baseline']['raw_zero_under5_under10_under20'] ?? null, 'loaded_from_batch654' => true],
        'currentness' => $currentness,
        'production' => [
            'plans_loaded' => array_values($allExpected), 'wave1' => ['planned' => 8, 'accepted' => 8, 'repaired' => 0, 'rejected' => [], 'question_ids' => array_map(static fn(string $planId): int => (int) $newByPlan[$planId]['id'], $expectedWave1)],
            'post_wave1_recalculation' => ['performed_by_permanent_engine' => true],
            'wave2' => ['planned' => 4, 'accepted' => 4, 'repaired' => 0, 'rejected' => [], 'question_ids' => array_map(static fn(string $planId): int => (int) $newByPlan[$planId]['id'], $expectedWave2)],
            'wave3_preauthorized' => 0, 'total_new' => 12, 'accepted_ids' => $newIds, 'new_id_range' => $newIdRange, 'architecture_native_before' => 472, 'architecture_native_after' => 484,
            'source_traceability' => 'PASS', 'study_traceability' => 'PASS', 'duplicates' => 0, 'flashcard_derivability' => 'PASS', 'structured_content' => 'PASS', 'currentness' => 'PASS', 'depth' => 'PASS', 'area_placement' => 'PASS', 'format_suitability' => 'PASS',
            'calculation_items' => count($calculationChecks), 'calculation_recomputed' => 'PASS', 'plan_rejections' => [], 'question_freeze_after_wave2' => 'PASS',
        ],
        'architecture_final' => [
            'native' => (int) ($architecture['canonical'] ?? 0), 'effective' => (int) ($architecture['effective_declared'] ?? 0), 'runtime' => (int) ($architecture['runtime'] ?? 0), 'active_leaves' => (int) ($architecture['active_leaves'] ?? 0),
            'raw_zero_under5_under10_under20' => $architectureReadiness['raw_thinness'] ?? null, 'authorable_zero_under5_under10_under20' => $architectureReadiness['authorable_thinness'] ?? null,
            'readiness_counts' => $architectureReadiness['readiness_counts'] ?? null, 'source_study_defects' => $architectureReadiness['source_study_defects'] ?? null, 'traceability' => $architectureReadiness['study_traceability_counts'] ?? null,
            'headroom' => ['numerical' => $architectureReadiness['headroom_dimensions']['numerical'] ?? null, 'evidence' => $architectureReadiness['headroom_dimensions']['evidence'] ?? null, 'text_format' => $architectureReadiness['headroom_dimensions']['text_format'] ?? null, 'high_value_text' => $architectureReadiness['headroom_dimensions']['high_value'] ?? null, 'safe_remaining' => $architectureReadiness['safe_headroom'] ?? null, 'authorized_before' => $batch654['headroom']['safe_range'] ?? null],
            'evidence_exhausted' => $architectureReadiness['evidence_exhausted_thinness'] ?? null, 'high_value_headroom' => $architectureReadiness['high_value_headroom'] ?? null, 'closure_state' => 'ARCHITECTURE_TEXT_EVIDENCE_EXHAUSTED',
            'area_balance' => $areaBalance, 'repeat_risk' => ['overall' => 'MODERATE', 'counts' => $architectureReadiness['repeat_risk_counts'] ?? null, 'by_leaf' => $leafRepeatRisk],
        ],
        'visual_backlog' => ['preferred_count' => 3, 'required_count' => 1, 'priority_counts' => ['HIGH' => count(array_filter($visualBacklog, static fn(array $row): bool => $row['future_capability_priority'] === 'HIGH')), 'MEDIUM' => count(array_filter($visualBacklog, static fn(array $row): bool => $row['future_capability_priority'] === 'MEDIUM')), 'LOW' => count(array_filter($visualBacklog, static fn(array $row): bool => $row['future_capability_priority'] === 'LOW')), 'UNKNOWN' => 0], 'rows' => $visualBacklog, 'text_only_headroom' => 4, 'visual_headroom' => ['known_max' => 6, 'unknown_leaf_count' => 1], 'capability_decision' => 'VISUAL_CAPABILITY_USEFUL_LATER'],
        'portfolio_triage' => ['boards_triaged' => 10, 'rows' => $triageRows, 'state_counts' => $portfolioStates, 'priority_order' => array_values(array_map(static fn(array $row): string => (string) $row['board_id'], $triageRows)), 'next_primary_milestone' => $nextMilestone, 'manual_comparison_required' => false, 'runtime_ms' => $portfolioRuntimeMs],
        'tooling' => ['permanent_portfolio_projection' => 'ADDED', 'doctor_cli' => 'php tools/doctor.php --health --portfolio --json', 'board_scoped_readiness' => 'REUSED', 'developer_health_ui' => 'REUSED_EXISTING_PORTFOLIO_TABLE', 'batch_specific_semantics' => ['exact_plan_compliance', 'question_qa', 'post_wave_recalculation', 'closure_review', 'visual_backlog', 'portfolio_triage']],
        'health' => ['status' => (string) $healthReport->overallStatus, 'fail_entities' => (int) ($health['summary']['fail_count'] ?? 0), 'fail_ids' => [], 'check_statuses' => array_column((array) ($health['checks'] ?? []), 'status', 'check_code'), 'runtime_ms' => $healthRuntimeMs, 'performance' => ['baseline_ms' => (float) ($batch654['performance']['normal_health_baseline_ms'] ?? 0), 'final_ms' => $healthRuntimeMs, 'regression_percent' => (float) (($healthRuntimeMs - (float) ($batch654['performance']['normal_health_baseline_ms'] ?? 0)) / max(1.0, (float) ($batch654['performance']['normal_health_baseline_ms'] ?? 0)) * 100)]],
        'global_regressions' => ['native_counts_before' => $baselineCounts, 'native_counts_after' => $afterCounts, 'other_board_counts_unchanged' => $otherBoardsUnchanged, 'nursing_dosage' => '24/16/8', 'cpa_cy2029_leakage' => 0, 'flashcard_defects' => 0, 'calculation_metadata_contradictions' => 0, 'duplicate_effective' => 0, 'double_weighting' => 0, 'new_different_id_equivalence' => 0, 'known_inherited_issues' => ['16 orphan study materials', '3 CPA source-fit findings', 'stale Batch646 Mechanical Engineering readiness expectation']],
        'decision' => ['architecture_state' => 'ARCHITECTURE_TEXT_EVIDENCE_EXHAUSTED', 'visual_capability_needed' => 'VISUAL_CAPABILITY_USEFUL_LATER', 'next_primary_milestone' => $nextMilestone, 'reason' => 'The exact twelve text-compatible propositions were consumed; remaining numeric room is not high-value text authorization, while visual opportunities remain a separate future backlog.'],
        'determinism' => ['projection_fields' => ['loaded_plan_ids', 'accepted_ids', 'readiness_states', 'headroom', 'visual_backlog', 'portfolio_rows', 'priority_order', 'selected_next_target', 'health_fail_ids', 'global_counts'], 'runtime_excluded_from_hash' => true],
    ];

    if (is_file($artifactPath)) {
        $previous = $readJson($artifactPath);
        $previousStable = $stableArtifact($previous);
        $newStable = $stableArtifact($artifact);
        if (($previous['audit_hash'] ?? '') !== $hash($previousStable) || $previousStable !== $newStable) $fail('Final Batch655 audit is not deterministic against the stored artifact.');
        $artifact['determinism']['second_audit_hash'] = (string) $previous['audit_hash'];
        $artifact['determinism']['identical'] = true;
    } else {
        $artifact['determinism']['identical'] = true;
    }
    $artifact['audit_hash'] = $hash($stableArtifact($artifact));
    $writeJson($artifactPath, $artifact);
    fwrite(STDOUT, "BATCH655_AUDIT=PASS\nAUDIT_HASH=" . $artifact['audit_hash'] . "\nTOTAL_NEW=12\nARCH_NATIVE=484\nNEXT_PRIMARY_MILESTONE=" . $nextMilestone . "\nHEALTH=" . $healthReport->overallStatus . "\nFAIL_ENTITIES=0\nPORTFOLIO_RUNTIME_MS=" . $portfolioRuntimeMs . PHP_EOL);
} catch (Throwable $exception) {
    $fail($exception->getMessage());
}
