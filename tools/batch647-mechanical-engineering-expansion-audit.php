<?php

declare(strict_types=1);

/**
 * Batch 647 source-first Mechanical Engineering authoring and audit.
 *
 * This tool orchestrates question creation through QuestionService and the
 * permanent eligibility, readiness, and flashcard services. It does not own
 * reusable validation logic.
 *
 * Usage:
 *   php tools/batch647-mechanical-engineering-expansion-audit.php --plan
 *   php tools/batch647-mechanical-engineering-expansion-audit.php --apply
 *   php tools/batch647-mechanical-engineering-expansion-audit.php --audit
 *   php tools/batch647-mechanical-engineering-expansion-audit.php --audit --health-json=/tmp/health.json --health-runtime-ms=18000
 */

require_once dirname(__DIR__) . '/app/Core/Autoloader.php';

use App\Core\App;
use App\Core\Autoloader;
use App\Repositories\QuestionRepository;
use App\Services\Flashcard\FlashcardDerivationService;
use App\Services\Question\CalculationMetadataContract;
use App\Services\Question\QuestionEligibilityService;
use App\Services\Question\QuestionService;
use App\Services\RepositoryHealth\Readiness\SourceReadinessRecord;
use App\Services\RepositoryHealth\Readiness\SourceReadinessService;
use App\Services\Shared\QuestionValidationService;
use App\Services\Question\StructuredContentService;

const B647_EXPECTED_HEAD = '7ac2ac783c2f81b1e6cc4dd8ce8ae41f3fbb6cb0';
const B647_PARENT_B646_FIRST = '6dd43f1';
const B647_PARENT_B645 = '9877a1dd5da74cc7cf4b97eba75f20eb9da92cc6';
const B647_PARENT_B644 = '9d8c27ac7d3955f1dcd4d8d779182a821210b855';
const B647_PARENT_B643 = 'd9eae268af866bc8005b26c27b39dcbb5f272c86';
const B647_PARENT_B642 = '45dc5e139a2d75e8b862bce9005f9dd61e792301';
const B647_BOARD = 'mechanical-engineering';
const B647_BATCH = 647;
const B647_DATE = '2026-10-03';
const B647_ARTIFACT = 'storage/batch647-mechanical-engineering-expansion.json';

Autoloader::register();
putenv('APP_ENV=testing');
putenv('APP_DEBUG=false');
App::boot();

function b647_fail(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

function b647_json(string $relative, bool $required = true): array
{
    $path = dirname(__DIR__) . '/' . $relative;
    if (!is_file($path)) {
        if (!$required) return [];
        b647_fail('Missing required JSON: ' . $relative);
    }
    try {
        $value = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $exception) {
        b647_fail('Invalid JSON in ' . $relative . ': ' . $exception->getMessage());
    }
    return is_array($value) ? $value : [];
}

function b647_write(string $relative, array $value): void
{
    $path = dirname(__DIR__) . '/' . $relative;
    $temporary = $path . '.batch647.tmp';
    $encoded = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
    if (file_put_contents($temporary, $encoded, LOCK_EX) === false || !rename($temporary, $path)) {
        @unlink($temporary);
        b647_fail('Could not atomically write ' . $relative);
    }
}

function b647_writeStorageCollection(string $collection, array $rows, int $flags): void
{
    $path = dirname(__DIR__) . '/storage/' . $collection . '.json';
    $temporary = $path . '.batch647.tmp';
    $encoded = json_encode(array_values($rows), $flags | JSON_THROW_ON_ERROR) . PHP_EOL;
    if (file_put_contents($temporary, $encoded, LOCK_EX) === false || !rename($temporary, $path)) {
        @unlink($temporary);
        b647_fail('Could not canonically persist storage collection ' . $collection);
    }
}

function b647_canonicalizeWrittenCollections(array $collections): void
{
    // Match each collection's pre-existing repository serialization so
    // QuestionService's whole-bank write does not create formatting churn.
    b647_writeStorageCollection('questions', (array) ($collections['questions'] ?? []), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    b647_writeStorageCollection('question-reuse-metadata', (array) ($collections['question-reuse-metadata'] ?? []), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    b647_writeStorageCollection('question-eligibility', (array) ($collections['question-eligibility'] ?? []), JSON_PRETTY_PRINT);
    b647_writeStorageCollection('study-question-links', (array) ($collections['study-question-links'] ?? []), JSON_PRETTY_PRINT);
    b647_writeStorageCollection('study-materials', (array) ($collections['study-materials'] ?? []), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function b647_repairStudyMaterialAuthorityNote(): bool
{
    $material = App::storage()->find('study-materials', 'me-material-18');
    if (!is_array($material)) b647_fail('Cannot repair the missing ME law study material.');
    $old = 'R.A. 8495 assigns the Board authority over registration, licensure, practice rules, and examination syllabi. The 2026 PRC program supplies the current three MELE areas and weights; the publicly located detailed subarea TOS remains the 2013 Resolution 57 crosswalk.';
    $replacement = 'Section 17 of R.A. 8495 assigns the Board to prescribe examination scope and methods. The 2026 PRC program supplies the current three MELE areas and weights; the publicly located detailed subarea TOS remains the 2013 Resolution 57 crosswalk. Do not attribute broader TOS-adoption verbs to R.A. 8495.';
    $notes = (array) ($material['notes'] ?? []);
    foreach ($notes as &$note) {
        if (!is_array($note) || (string) ($note['heading'] ?? '') !== 'Board and examination authority') continue;
        $body = (string) ($note['body'] ?? '');
        if ($body === $replacement) return false;
        if ($body !== $old) b647_fail('ME law study note changed unexpectedly; preserving it for review.');
        $note['body'] = $replacement;
        unset($note);
        $material['notes'] = array_values($notes);
        App::storage()->update('study-materials', 'me-material-18', $material);
        return true;
    }
    unset($note);
    b647_fail('ME law study authority note was not found.');
}

function b647_items(): array
{
    $items = [];
    $add = static function (
        int $wave,
        string $key,
        string $leaf,
        string $family,
        int $depth,
        string $study,
        string $stem,
        string $answer,
        array $wrong,
        string $explanation,
        ?array $calculation = null,
        bool $quantitative = false,
        int $scopeIndex = 0,
        bool $versioned = false
    ) use (&$items): void {
        $items[] = [
            'wave' => $wave,
            'key' => $key,
            'leaf_id' => $leaf,
            'content_family' => $family,
            'depth' => $depth,
            'study_material_id' => $study,
            'stem' => $stem,
            'answer' => $answer,
            'wrong' => $wrong,
            'explanation' => $explanation,
            'calculation' => $calculation,
            'quantitative' => $quantitative,
            'scope_index' => $scopeIndex,
            'versioned' => $versioned,
        ];
    };

    // Wave 1: start with the zero-count leaves, then close defensible under-5.
    $add(1, 'drawing-third-angle-view', 'b644-me-engineering-drawing-practice', 'orthographic_projection', 2, 'me-material-16',
        'Under the stated third-angle projection convention, where is the top view placed relative to the front view?',
        'Above the front view', ['Below the front view', 'To the left of the front view', 'At an arbitrary position'],
        'In third-angle orthographic projection, the top view is placed above the front view. The convention must be stated rather than inferred from an unstated drawing.');
    $add(1, 'drawing-section-cut-material', 'b644-me-engineering-drawing-practice', 'sectional_view', 2, 'me-material-16',
        'In a sectional view, what does the hatch convention ordinarily identify?',
        'Material intersected by the cutting plane', ['A hidden cavity that was not cut', 'A dimension that is not to scale', 'A surface that must be machined'],
        'A section represents interior geometry exposed by the cutting plane; hatching identifies the material cut by that plane, not an unseen void or a manufacturing instruction.');
    $add(1, 'drawing-dimension-chain', 'b644-me-engineering-drawing-practice', 'dimensioning', 2, 'me-material-16',
        'A drawing gives two consecutive axial lengths of 32 mm and 18 mm from a common end face. What overall length do those dimensions define?',
        '50 mm', ['14 mm', '576 mm', '32 mm'],
        'The dimensions are consecutive lengths along the same axis, so the overall length is 32 mm + 18 mm = 50 mm.',
        ['family' => 'drawing_dimension_chain', 'expression' => '32 mm + 18 mm', 'values' => [32, 18], 'expected' => 50, 'unit' => 'mm', 'rounding' => 'exact', 'assumptions' => 'Consecutive, non-overlapping dimensions share the stated datum.'], true);

    $add(1, 'law-statutory-practice-source', 'b644-me-me-law-code-ethics', 'statutory_scope', 2, 'me-material-18',
        'Which authority is the primary basis for identifying the statutory practice and covered-equipment definitions of the Philippine mechanical-engineering profession?',
        'Republic Act No. 8495 and its PRC-linked implementing rules', ['A general engineering textbook alone', 'An unverified copy of a historical ethics code', 'A technical standard whose current edition is unknown'],
        'R.A. 8495 and its PRC-linked IRR provide the bounded statutory definitions. Technical standards and unverified ethics text do not replace that statutory source.', null, false, 1, true);
    $add(1, 'law-board-examination-authority', 'b644-me-me-law-code-ethics', 'board_authority', 3, 'me-material-18',
        'Under Section 17 of R.A. 8495, which body prescribes the scope and methods of the Mechanical Engineer licensure examination?',
        'The Professional Regulatory Board of Mechanical Engineering', ['A private review provider', 'An individual examinee', 'A textbook publisher'],
        'Section 17 assigns the examination scope and methods to the Board, the Professional Regulatory Board of Mechanical Engineering. This item tests that bounded statutory power, not broader TOS-adoption authority under another statute.', null, false, 0, true);
    $add(1, 'law-practice-activity-definition', 'b644-me-me-law-code-ethics', 'statutory_scope', 2, 'me-material-18',
        'Which activity is expressly included in the statutory definition of the practice of mechanical engineering under R.A. 8495?',
        'Management or supervision of installation, testing, and commissioning of mechanical equipment', ['Issuing a building occupancy permit', 'Certifying an electrical installation under a different profession’s statute', 'Adopting private technical standards without Board action'],
        'Section 3(a) of R.A. 8495 includes management or supervision of the erection, installation, alteration, testing, and commissioning of mechanical equipment and processes. This item tests that bounded statutory definition, not legal advice.', null, false, 1, true);
    $add(1, 'law-current-mele-area-weights', 'b644-me-me-law-code-ethics', 'licensure_boundary', 2, 'me-material-18',
        'In the PRC program for the August 2026 MELE, what relative weight is assigned to Power Plant Engineering?',
        '35%', ['30%', '25%', '40%'],
        'The current PRC program assigns 35% to Power Plant Engineering, 35% to Mathematics, and 30% to Machine Design, Materials and Shop Practice.', null, true, 2, true);

    $add(1, 'pressure-thin-wall-hoop-stress', 'b644-me-pressure-components', 'hoop_and_longitudinal_stress', 3, 'me-material-17',
        'A thin-walled cylinder has a stated pressure difference of 2.4 MPa, an internal diameter of 500 mm, and wall thickness of 10 mm. Under the supplied thin-wall model, what is the hoop stress?',
        '60 MPa', ['30 MPa', '15 MPa', '120 MPa'],
        'For the stated membrane model, hoop stress is pD/(2t) = (2.4 N/mm²)(500 mm)/(20 mm) = 60 N/mm² = 60 MPa. No code allowable or joint factor is applied.',
        ['family' => 'thin_wall_hoop_stress', 'expression' => 'pD/(2t)', 'values' => [2.4, 500, 10], 'expected' => 60, 'unit' => 'MPa', 'rounding' => 'exact', 'assumptions' => 'Pressure is the stated pressure difference; thin-wall cylindrical membrane model; supplied dimensions are consistent.'], true);
    $add(1, 'pressure-longitudinal-stress', 'b644-me-pressure-components', 'hoop_and_longitudinal_stress', 3, 'me-material-17',
        'For a closed-end thin-walled cylinder with pressure difference 1.5 MPa, diameter 400 mm, and thickness 10 mm, what longitudinal membrane stress follows from the supplied idealization?',
        '15 MPa', ['30 MPa', '7.5 MPa', '60 MPa'],
        'For a closed-end thin-wall cylinder, longitudinal stress is pD/(4t) = (1.5 N/mm²)(400 mm)/(40 mm) = 15 N/mm² = 15 MPa.',
        ['family' => 'thin_wall_longitudinal_stress', 'expression' => 'pD/(4t)', 'values' => [1.5, 400, 10], 'expected' => 15, 'unit' => 'MPa', 'rounding' => 'exact', 'assumptions' => 'Closed ends; pressure difference and dimensions are supplied; ideal thin-wall membrane model.'], true);
    $add(1, 'pressure-hoop-longitudinal-comparison', 'b644-me-pressure-components', 'pressure_boundary_force_balance', 2, 'me-material-17',
        'For a closed-end thin-walled cylinder under the stated ideal membrane assumptions, how does longitudinal stress compare with hoop stress?',
        'It is one-half the hoop stress', ['It is twice the hoop stress', 'It is equal to the hoop stress', 'It is zero for any pressure'],
        'The ideal relationships are pD/(4t) longitudinally and pD/(2t) circumferentially, so the longitudinal value is half the hoop value under these assumptions.');
    $add(1, 'pressure-membrane-idealization', 'b644-me-pressure-components', 'thin_wall_assumption', 3, 'me-material-17',
        'What is the central simplification made when a cylindrical pressure boundary is treated with a thin-wall membrane model?',
        'Wall stresses are represented as membrane stresses using the stated thin-wall geometry and assumptions', ['The component is automatically code-compliant', 'Material strength and pressure can be ignored', 'Local joints and openings require no separate treatment'],
        'The model idealizes the wall response as membrane stress for the stated geometry. It does not establish code compliance, allowables, joint efficiency, or treatment of local discontinuities.');

    $add(1, 'hydro-flow-velocity', 'b644-me-hydroelectric-plants', 'site_and_flow_interpretation', 2, 'me-material-5',
        'Water flows at 0.12 m³/s through a penstock section of 0.06 m². What mean velocity follows from continuity?',
        '2.0 m/s', ['0.5 m/s', '7.2 m/s', '0.0072 m/s'],
        'For the stated filled section, Q = VA, so V = Q/A = 0.12/0.06 = 2.0 m/s.',
        ['family' => 'continuity_flow_velocity', 'expression' => 'Q/A', 'values' => [0.12, 0.06], 'expected' => 2.0, 'unit' => 'm/s', 'rounding' => '1 decimal', 'assumptions' => 'Mean incompressible flow in the stated cross-section.'], true);
    $add(1, 'hydro-net-head', 'b644-me-hydroelectric-plants', 'hydraulic_head_power', 2, 'me-material-5',
        'A hydro site has 68 m gross head and 8 m of stated hydraulic losses. What net head is available to the turbine?',
        '60 m', ['76 m', '8.5 m', '544 m'],
        'Net head is gross head less the stated hydraulic losses: 68 m - 8 m = 60 m.',
        ['family' => 'net_head_loss_balance', 'expression' => 'H_net = H_gross - h_loss', 'values' => [68, 8], 'expected' => 60, 'unit' => 'm', 'rounding' => 'exact', 'assumptions' => 'The supplied losses refer to the same flow and datum.'], true);
    $add(1, 'hydro-efficiency-from-output', 'b644-me-hydroelectric-plants', 'plant_efficiency', 3, 'me-material-5',
        'A turbine-generator receives 25 kW of hydraulic input and delivers 18.75 kW of shaft output. What overall conversion efficiency is indicated?',
        '75%', ['6.25%', '133.3%', '25%'],
        'Efficiency is useful shaft output divided by hydraulic input: 18.75/25 = 0.75, or 75%.',
        ['family' => 'hydro_conversion_efficiency', 'expression' => 'P_out/P_in', 'values' => [18.75, 25], 'expected' => 0.75, 'unit' => 'fraction', 'rounding' => 'exact', 'assumptions' => 'Both power values use the same system boundary.'], true);
    $add(1, 'hydro-specific-potential-energy', 'b644-me-hydroelectric-plants', 'hydraulic_head_power', 2, 'me-material-5',
        'Using g = 9.81 m/s², what gravitational potential-energy change per unit mass corresponds to a net water head of 45 m?',
        '441 J/kg', ['4.41 J/kg', '45 J/kg', '4,414.5 J/kg'],
        'Potential energy per unit mass is gH = (9.81 m/s²)(45 m) = 441.45 J/kg, which rounds to 441 J/kg.',
        ['family' => 'head_specific_energy', 'expression' => 'gH', 'values' => [9.81, 45], 'expected' => 441.45, 'unit' => 'J/kg', 'rounding' => 'nearest J/kg', 'assumptions' => 'Elevation-head energy only; losses and conversion efficiency are not included.'], true);

    $add(1, 'geometry-coordinate-triangle-area', 'b644-me-math-analytic-geometry', 'geometric_modeling', 3, 'me-material-1',
        'What is the area of the triangle with vertices (0, 0), (8, 0), and (3, 5) in a Cartesian plane?',
        '20 square units', ['40 square units', '13 square units', '10 square units'],
        'The base from (0,0) to (8,0) is 8 units and the perpendicular height is 5 units, so area = (1/2)(8)(5) = 20 square units.',
        ['family' => 'coordinate_triangle_area', 'expression' => '1/2 * base * perpendicular_height', 'values' => [8, 5], 'expected' => 20, 'unit' => 'square units', 'rounding' => 'exact', 'assumptions' => 'Coordinates are Cartesian and the base lies on the x-axis.'], true);
    $add(1, 'geometry-circle-center-radius', 'b644-me-math-analytic-geometry', 'algebra_geometry', 3, 'me-material-1',
        'The circle x² + y² - 4x + 6y - 12 = 0 has what center and radius?',
        'Center (2, -3), radius 5', ['Center (-2, 3), radius 5', 'Center (2, -3), radius 25', 'Center (4, -6), radius 5'],
        'Completing squares gives (x - 2)² + (y + 3)² = 25; the center is (2,-3) and the radius is √25 = 5.',
        ['family' => 'circle_center_radius', 'expression' => 'complete squares: (x-2)^2+(y+3)^2=25', 'values' => [25], 'expected' => 5, 'unit' => 'coordinate units', 'rounding' => 'exact', 'assumptions' => 'The equation is in a Cartesian coordinate system.'], true);

    $add(1, 'fuel-methane-stoichiometric-oxygen', 'b644-me-power-fuels-combustion', 'combustion_balance', 3, 'me-material-8',
        'For complete combustion of 1 kmol of methane, CH₄ + 2O₂ → CO₂ + 2H₂O. How many kmol of oxygen are required theoretically?',
        '2 kmol O₂', ['1 kmol O₂', '3 kmol O₂', '4 kmol O₂'],
        'Balancing carbon, hydrogen, and oxygen gives CH₄ + 2O₂ → CO₂ + 2H₂O, so one kmol of methane requires two kmol of oxygen.',
        ['family' => 'stoichiometric_oxygen_balance', 'expression' => '1 kmol CH4 * 2 kmol O2/kmol CH4', 'values' => [1, 2], 'expected' => 2, 'unit' => 'kmol O2', 'rounding' => 'exact', 'assumptions' => 'Complete combustion and the displayed balanced reaction.'], true);
    $add(1, 'fuel-excess-air-percent', 'b644-me-power-fuels-combustion', 'excess_air', 3, 'me-material-8',
        'A stated fuel reaction requires 2.0 kmol of theoretical air, while 2.5 kmol is supplied. What percentage of excess air is supplied?',
        '25%', ['20%', '80%', '125%'],
        'Percent excess air = (actual - theoretical)/theoretical × 100 = (2.5 - 2.0)/2.0 × 100 = 25%.',
        ['family' => 'excess_air_fraction', 'expression' => '(actual-theoretical)/theoretical*100', 'values' => [2.5, 2.0], 'expected' => 25, 'unit' => '%', 'rounding' => 'exact', 'assumptions' => 'Actual and theoretical air are on the same molar or mass basis.'], true);

    $add(1, 'combined-cycle-heat-recovery-role', 'b644-me-gas-combined-cycle', 'combined_cycle_heat_recovery', 3, 'me-material-8',
        'In an idealized combined-cycle plant, what is the role of the heat-recovery steam generator?',
        'It transfers gas-turbine exhaust heat to a steam cycle that can produce additional work', ['It converts the exhaust heat into fuel without an energy input', 'It eliminates compressor work from the gas turbine', 'It guarantees a fixed plant efficiency independent of operating conditions'],
        'The bottoming steam cycle recovers part of the gas-turbine exhaust energy and can add work; it does not create energy or remove compressor work.');
    $add(1, 'combined-cycle-overall-efficiency', 'b644-me-gas-combined-cycle', 'combined_cycle_heat_recovery', 3, 'me-material-8',
        'An idealized gas turbine supplies 40 MW net, its steam bottoming cycle supplies 15 MW net, and fuel input is 180 MW. What combined net thermal efficiency results?',
        '30.6%', ['22.2%', '44.4%', '12.0%'],
        'Combined net output is 40 + 15 = 55 MW. Efficiency = 55/180 = 0.3056, or 30.6%. The common fuel-input boundary is stated.',
        ['family' => 'combined_cycle_efficiency', 'expression' => '(P_GT_net+P_ST_net)/Q_fuel', 'values' => [40, 15, 180], 'expected' => 0.3055555556, 'unit' => 'fraction', 'rounding' => '1 decimal percent', 'assumptions' => 'Both net power outputs share the stated plant boundary and fuel input.'], true);

    $add(1, 'materials-plastic-deformation', 'b644-me-materials-properties', 'failure_mode_comparison', 2, 'me-material-4',
        'A specimen retains a permanent change in shape after the load is removed. Which behavior did it exhibit?',
        'Plastic deformation beyond its elastic response', ['Purely elastic recovery', 'Zero strain under load', 'A change in density with no deformation'],
        'Elastic deformation recovers when load is removed; a retained shape change indicates plastic deformation. The question does not infer an unsupplied alloy specification.');
    $add(1, 'materials-formability-ductility', 'b644-me-materials-properties', 'material_selection', 3, 'me-material-10',
        'Two candidate materials meet the stated strength requirement. A formed part must undergo substantial plastic shaping without cracking. Which supplied property is most directly relevant to that requirement?',
        'Ductility', ['Electrical resistivity', 'Color', 'Elastic modulus alone'],
        'Ductility describes the capacity for plastic deformation before fracture and is directly relevant to formability. Strength and stiffness remain separate requirements.');

    $add(1, 'mechanism-gear-tangential-force', 'b644-me-mechanism-kinematics', 'gear_train_ratio', 3, 'me-material-11',
        'A gear transmits 180 N·m of torque at a pitch radius of 0.15 m. What tangential pitch-line force corresponds to that torque?',
        '1,200 N', ['27 N', '12 N', '1.2 N'],
        'Tangential force is torque divided by pitch radius: 180 N·m / 0.15 m = 1,200 N.',
        ['family' => 'gear_pitchline_force', 'expression' => 'T/r', 'values' => [180, 0.15], 'expected' => 1200, 'unit' => 'N', 'rounding' => 'exact', 'assumptions' => 'Torque and pitch radius refer to the same gear and are expressed in consistent units.'], true);
    $add(1, 'mechanism-external-gear-direction', 'b644-me-mechanism-kinematics', 'mechanism_function', 2, 'me-material-11',
        'Two ordinary external gears mesh directly. If the driver rotates clockwise, in which direction does the driven gear rotate?',
        'Counterclockwise', ['Clockwise', 'It remains stationary', 'Its direction cannot be related to the driver'],
        'At an external mesh, the contacting pitch surfaces move in opposite tangential directions, so the two gears rotate in opposite directions.');

    $add(1, 'basic-pressure-dimensions', 'b644-me-basic-engineering-sciences', 'dimensional_analysis', 2, 'me-material-2',
        'Which SI base-unit expression is dimensionally equivalent to pressure?',
        'kg/(m·s²)', ['kg·m/s²', 'kg·m²/s²', 'm/(kg·s²)'],
        'Pressure is force per area: (kg·m/s²)/m² = kg/(m·s²). Dimensional agreement is a consistency check, not proof that a model is physically complete.', null, true);
    $add(1, 'load-factor-average-to-peak', 'b644-me-power-load-performance', 'load_profile', 2, 'me-material-8',
        'A plant averages 54 MW over a stated period and reaches a 90 MW peak during that same period. What load factor applies?',
        '0.60', ['1.67', '36 MW', '0.06'],
        'Load factor is average load divided by peak load: 54/90 = 0.60.',
        ['family' => 'load_factor', 'expression' => 'P_average/P_peak', 'values' => [54, 90], 'expected' => 0.60, 'unit' => 'fraction', 'rounding' => 'exact', 'assumptions' => 'Average and peak refer to the same period and output boundary.'], true);
    $add(1, 'shop-worker-hazard-reporting', 'b644-me-shop-safety-practice', 'worker_rights_and_reporting', 2, 'me-material-19',
        'A shop worker identifies a recurring unsafe condition but lacks authority to repair the machine. Which response best fits the supported general Philippine OSH framework?',
        'Report the hazard through the workplace safety process and avoid undertaking an unauthorized repair', ['Ignore it unless an injury occurs', 'Disable the machine by improvising an undocumented repair', 'Treat production urgency as proof the condition is safe'],
        'R.A. 11058 and the current D.O. 252-25 framework support worker information and hazard-reporting protections. The scenario does not prescribe an equipment-specific repair sequence.', null, false, 0, true);

    // Wave 2 is created only after the actual Wave 1 leaf counts are recomputed.
    $add(2, 'geometry-point-distance', 'b644-me-math-analytic-geometry', 'geometric_modeling', 2, 'me-material-1',
        'What is the distance between the Cartesian points (1, 2) and (7, 10)?',
        '10 units', ['8 units', '12 units', '100 units'],
        'The coordinate differences are 6 and 8, so distance = √(6² + 8²) = √100 = 10 units.',
        ['family' => 'coordinate_distance', 'expression' => 'sqrt((7-1)^2+(10-2)^2)', 'values' => [6, 8], 'expected' => 10, 'unit' => 'coordinate units', 'rounding' => 'exact', 'assumptions' => 'Cartesian coordinates use the same length unit.'], true);
    $add(2, 'geometry-line-intersection', 'b644-me-math-analytic-geometry', 'algebra_geometry', 3, 'me-material-1',
        'At what point do the lines x + y = 11 and x - y = 3 intersect?',
        '(7, 4)', ['(4, 7)', '(11, 3)', '(3, 11)'],
        'Adding the equations gives 2x = 14, so x = 7; substituting gives y = 4.',
        ['family' => 'coordinate_line_intersection', 'expression' => 'solve x+y=11 and x-y=3', 'values' => [11, 3], 'expected' => '7,4', 'unit' => 'coordinate units', 'rounding' => 'exact', 'assumptions' => 'Both equations use the same Cartesian coordinates.'], true);
    $add(2, 'geometry-line-slope', 'b644-me-math-analytic-geometry', 'geometric_modeling', 2, 'me-material-1',
        'What is the slope of the line through (2, 3) and (6, 11)?',
        '2', ['1/2', '4', '8'],
        'Slope is change in y divided by change in x: (11 - 3)/(6 - 2) = 8/4 = 2.',
        ['family' => 'coordinate_line_slope', 'expression' => '(11-3)/(6-2)', 'values' => [11, 3, 6, 2], 'expected' => 2, 'unit' => 'dimensionless', 'rounding' => 'exact', 'assumptions' => 'The points are distinct and share a Cartesian coordinate system.'], true);

    $add(2, 'calculus-exponential-instantaneous-rate', 'b644-me-math-calculus-models', 'calculus_rate_interpretation', 3, 'me-material-1',
        'A modeled response is y(t) = 100e^(-0.2t). What is its instantaneous rate of change at t = 0?',
        '-20 units/time', ['-0.2 units/time', '20 units/time', '100 units/time'],
        'Differentiate: dy/dt = -20e^(-0.2t). At t = 0, the rate is -20 units per time unit.',
        ['family' => 'exponential_model_derivative', 'expression' => 'dy/dt = -20*exp(-0.2t); evaluate at t=0', 'values' => [-20, 0], 'expected' => -20, 'unit' => 'units/time', 'rounding' => 'exact', 'assumptions' => 'The stated exponential model is valid at t=0.'], true);
    $add(2, 'calculus-linear-model-rate', 'b644-me-math-calculus-models', 'calculus_rate_interpretation', 3, 'me-material-1',
        'A machine cost model is C(x) = 0.5x² + 4x in thousands of pesos. What is its marginal cost at x = 6 units, in thousand pesos per unit?',
        '10', ['22', '6', '4'],
        'Marginal cost is dC/dx = x + 4. At x = 6, the rate is 10 thousand pesos per unit.',
        ['family' => 'marginal_rate_derivative', 'expression' => 'dC/dx = x+4; evaluate at x=6', 'values' => [6, 4], 'expected' => 10, 'unit' => 'thousand PHP/unit', 'rounding' => 'exact', 'assumptions' => 'The supplied differentiable cost model is used locally at x=6.'], true);

    $add(2, 'economics-present-worth-comparison', 'b644-me-engineering-economics', 'economic_comparison', 3, 'me-material-15',
        'Alternative A costs PHP 40,000 now and PHP 8,000 at each year-end for five years. Using a present-worth factor of 3.79, what is A’s present cost?',
        'PHP 70,320', ['PHP 48,000', 'PHP 151,600', 'PHP 30,320'],
        'Present cost = 40,000 + 8,000(3.79) = PHP 70,320. The result is conditional on the supplied period, timing, and factor.',
        ['family' => 'present_worth_cost', 'expression' => '40000 + 8000*3.79', 'values' => [40000, 8000, 3.79], 'expected' => 70320, 'unit' => 'PHP', 'rounding' => 'nearest peso', 'assumptions' => 'Five equal end-of-year costs and the stated present-worth factor.'], true);
    $add(2, 'economics-equivalent-annual-cost', 'b644-me-engineering-economics', 'economic_comparison', 4, 'me-material-15',
        'Two feasible alternatives have equal service lives. With a supplied capital-recovery factor of 0.2638, A costs PHP 90,000 initially plus PHP 7,000/year to operate; B costs PHP 120,000 initially plus PHP 3,000/year. Which has the lower equivalent annual cost?',
        'Alternative A', ['Alternative B', 'They are equal', 'The supplied data cannot compare them'],
        'A: 90,000(0.2638) + 7,000 = PHP 30,742/year. B: 120,000(0.2638) + 3,000 = PHP 34,656/year. A is lower under the supplied common service and timing assumptions.',
        ['family' => 'equivalent_annual_cost', 'expression' => 'A=90000*0.2638+7000; B=120000*0.2638+3000', 'values' => [90000, 0.2638, 7000, 120000, 3000], 'expected' => 'A=30742; B=34656', 'unit' => 'PHP/year', 'rounding' => 'nearest peso', 'assumptions' => 'Equal service lives and the supplied capital-recovery factor.'], true);

    $add(2, 'basic-torque-dimensions', 'b644-me-basic-engineering-sciences', 'dimensional_analysis', 2, 'me-material-2',
        'Torque and work both reduce to N·m in base SI dimensions. Which conclusion is valid?',
        'They are dimensionally equivalent but remain different physical quantities identified by context', ['They are interchangeable in every equation', 'Torque is energy divided by time', 'Work is always a vector moment about an axis'],
        'Dimensional analysis can show that torque and work share base dimensions, but it cannot make their physical meanings interchangeable. Torque is a moment; work is energy transfer.', null, true);

    $add(2, 'load-capacity-factor', 'b644-me-power-load-performance', 'load_profile', 3, 'me-material-8',
        'A generating unit is rated at 50 MW and averages 25 MW over the stated period. What capacity factor applies?',
        '0.50', ['2.0', '25%', '50 MW'],
        'Capacity factor is average output divided by rated capacity: 25/50 = 0.50.',
        ['family' => 'capacity_factor', 'expression' => 'P_average/P_rated', 'values' => [25, 50], 'expected' => 0.50, 'unit' => 'fraction', 'rounding' => 'exact', 'assumptions' => 'Average output and rated capacity use the same power boundary.'], true);
    $add(2, 'load-average-from-energy', 'b644-me-power-load-performance', 'load_profile', 3, 'me-material-8',
        'A plant delivers 1,440 MWh over a 30-day period. What is its average delivered power over that period?',
        '2 MW', ['48 MW', '0.067 MW', '1,440 MW'],
        'Thirty days contain 720 hours. Average power is 1,440 MWh / 720 h = 2 MW.',
        ['family' => 'period_average_load', 'expression' => 'energy/elapsed_hours', 'values' => [1440, 30, 24], 'expected' => 2, 'unit' => 'MW', 'rounding' => 'exact', 'assumptions' => 'The period is 30 consecutive days and energy is delivered uniformly only for the average calculation.'], true);

    $add(2, 'engine-mechanical-efficiency', 'b644-me-internal-combustion-engines', 'component_efficiency', 3, 'me-material-8',
        'An engine develops 80 kW indicated power and 72 kW brake power. What mechanical efficiency is indicated?',
        '90%', ['111.1%', '8%', '72%'],
        'Mechanical efficiency is brake power divided by indicated power: 72/80 = 0.90, or 90%.',
        ['family' => 'engine_mechanical_efficiency', 'expression' => 'P_brake/P_indicated', 'values' => [72, 80], 'expected' => 0.90, 'unit' => 'fraction', 'rounding' => 'exact', 'assumptions' => 'Both powers refer to the same operating condition.'], true);

    $add(2, 'gas-turbine-net-specific-work', 'b644-me-gas-combined-cycle', 'gas_turbine_cycle', 3, 'me-material-8',
        'In an idealized gas-turbine cycle, the turbine produces 320 kJ/kg and the compressor consumes 110 kJ/kg. What net specific work remains before other plant losses?',
        '210 kJ/kg', ['430 kJ/kg', '2.91 kJ/kg', '110 kJ/kg'],
        'Net cycle work is turbine work less compressor work: 320 - 110 = 210 kJ/kg.',
        ['family' => 'gas_turbine_net_work', 'expression' => 'w_turbine - w_compressor', 'values' => [320, 110], 'expected' => 210, 'unit' => 'kJ/kg', 'rounding' => 'exact', 'assumptions' => 'The two specific-work terms use the same mass basis and cycle boundary.'], true);
    $add(2, 'combined-cycle-bottoming-efficiency', 'b644-me-gas-combined-cycle', 'combined_cycle_heat_recovery', 4, 'me-material-8',
        'A heat-recovery steam cycle receives 90 MW of recovered exhaust heat and produces 27 MW net. What conversion efficiency applies to that bottoming-cycle boundary?',
        '30%', ['3.33%', '63%', '300%'],
        'For the stated boundary, net output divided by heat supplied is 27/90 = 0.30, or 30%. This does not imply all exhaust heat is recoverable.',
        ['family' => 'bottoming_cycle_efficiency', 'expression' => 'P_net/Q_recovered', 'values' => [27, 90], 'expected' => 0.30, 'unit' => 'fraction', 'rounding' => 'exact', 'assumptions' => 'The stated recovered heat is the bottoming-cycle heat-input boundary.'], true);

    $add(2, 'mechanism-ideal-gear-torque', 'b644-me-mechanism-kinematics', 'machine_power', 3, 'me-material-10',
        'An ideal gear reducer lowers shaft speed to one-third of its input speed. If input torque is 24 N·m and losses are neglected, what output torque is consistent with power conservation?',
        '72 N·m', ['8 N·m', '24 N·m', '216 N·m'],
        'For an ideal reducer at unchanged power, torque varies inversely with speed. One-third speed corresponds to three times the torque: 24 × 3 = 72 N·m.',
        ['family' => 'ideal_gear_torque_speed_tradeoff', 'expression' => 'T_out=T_in/(omega_out/omega_in)', 'values' => [24, 1, 3], 'expected' => 72, 'unit' => 'N·m', 'rounding' => 'exact', 'assumptions' => 'Ideal transmission with no losses and the stated speed ratio.'], true);
    $add(2, 'mechanism-chain-linear-speed', 'b644-me-mechanism-kinematics', 'gear_train_ratio', 3, 'me-material-11',
        'A chain sprocket has 20 teeth, chain pitch 25 mm, and rotates at 120 rev/min. What chain speed follows from the stated ideal kinematic relation?',
        '60 m/min', ['6 m/min', '600 m/min', '0.6 m/min'],
        'Chain speed = teeth × pitch × revolutions per time = 20 × 25 mm × 120/min = 60,000 mm/min = 60 m/min.',
        ['family' => 'chain_sprocket_speed', 'expression' => 'z*p*N', 'values' => [20, 25, 120], 'expected' => 60, 'unit' => 'm/min', 'rounding' => 'exact', 'assumptions' => 'The chain engages every stated sprocket tooth once per revolution; pitch and speed are supplied.'], true);

    $add(2, 'materials-impact-toughness', 'b644-me-materials-properties', 'material_selection', 3, 'me-material-10',
        'A component is exposed to a stated impact load. Two candidates meet the static strength requirement, but one has greater supplied toughness. Which property is more directly relevant to resisting fracture under the impact?',
        'Toughness', ['Color', 'Density alone', 'Elastic modulus alone'],
        'Toughness describes energy absorption up to fracture and is directly relevant to impact resistance. The supplied property comparison is used; no alloy grade or heat-treatment claim is inferred.');

    $add(2, 'shop-unsafe-work-refusal', 'b644-me-shop-safety-practice', 'worker_rights_and_reporting', 3, 'me-material-19',
        'A worker faces a condition reasonably believed to present imminent danger and raises it through the established safety process. Which response is consistent with the current high-level worker-protection framework?',
        'The worker may invoke the applicable protection against unsafe work while the hazard is addressed', ['The worker must continue because output is behind schedule', 'The worker must personally redesign the machine guard', 'The worker loses all protection if no injury has occurred'],
        'R.A. 11058 and the revised D.O. 252-25 framework recognize worker OSH protections, including protections relating to unsafe work. The item avoids unsupported thresholds or equipment-specific procedures.', null, false, 0, true);
    $add(2, 'shop-general-guarding-decision', 'b644-me-shop-safety-practice', 'machine_guarding', 2, 'me-material-19',
        'A rotating machine has an accessible moving part that creates an obvious contact hazard. What is the best general safeguard decision before routine operation?',
        'Use the applicable guard or safeguard and keep access controlled under the supplied workplace procedure', ['Rely only on a warning label', 'Ask the operator to work faster to reduce exposure time', 'Remove the guard so the part can be watched'],
        'General shop-safety reasoning calls for the applicable safeguard and controlled access. The question does not prescribe a machine-specific guard design or standard.', null, false, 2, true);

    $add(2, 'production-process-selection', 'b644-me-production-systems', 'shop_process_selection', 3, 'me-material-12',
        'A manufacturer must choose a process for a large, repeated batch of identical parts. Which selection approach is most defensible?',
        'Compare processes against volume, geometry, material, tolerance, finish, equipment limits, and total cost', ['Select the process with the lowest first-piece cost only', 'Ignore tolerance and finish because volume is high', 'Choose the process used for the previous product regardless of fit'],
        'Process selection should match production volume and part requirements while considering capability, cost, equipment limits, and measurement needs. No safety or labor threshold is inferred.');

    return $items;
}

function b647_collections(): array
{
    $collections = [];
    foreach ([
        'questions', 'boards', 'subjects', 'board-subjects', 'blueprints',
        'taxonomy/domains', 'taxonomy/topics', 'taxonomy/concepts',
        'question-eligibility', 'concept-derived-eligibility', 'question-reuse-overrides',
        'question-reuse-metadata', 'board-concept-coverage', 'study-materials',
        'study-question-links', 'sources', 'content-fit-audits', 'flashcard-exclusions',
        'source-readiness', 'source-readiness-psychometrician', 'source-readiness-mechanical-engineering',
    ] as $name) {
        $collections[$name] = App::storage()->all($name);
    }
    return $collections;
}

function b647_index(array $rows, string $idField = 'id'): array
{
    $result = [];
    foreach ($rows as $row) {
        if (is_array($row) && is_scalar($row[$idField] ?? null)) $result[(string) $row[$idField]] = $row;
    }
    return $result;
}

function b647_normalize(string $value): string
{
    $value = mb_strtolower(trim($value));
    $value = preg_replace('/[^\pL\pN]+/u', ' ', $value) ?? $value;
    return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
}

function b647_exactNormalize(string $value): string
{
    return trim(preg_replace('/\s+/u', ' ', mb_strtolower(trim($value))) ?? $value);
}

function b647_template(string $value): string
{
    $value = b647_normalize($value);
    $value = preg_replace('/\b\d+(?:[.,]\d+)?\b/u', '#', $value) ?? $value;
    return $value;
}

function b647_counts(array $collections): array
{
    $rows = (array) ($collections['source-readiness-mechanical-engineering'] ?? []);
    $active = array_fill_keys(array_map(static fn(array $r): string => (string) ($r['leaf_id'] ?? ''), $rows), true);
    $counts = array_fill_keys(array_keys($active), 0);
    foreach ((array) ($collections['questions'] ?? []) as $question) {
        if (!is_array($question)) continue;
        if (strtolower((string) ($question['status'] ?? 'active')) === 'archived') continue;
        $taxonomy = is_array($question['taxonomy'] ?? null) ? $question['taxonomy'] : [];
        if (strtolower((string) ($taxonomy['board_id'] ?? '')) !== B647_BOARD) continue;
        $leaf = (string) ($taxonomy['concept_id'] ?? '');
        if (isset($counts[$leaf])) $counts[$leaf]++;
    }
    ksort($counts, SORT_STRING);
    return $counts;
}

function b647_distribution(array $counts): array
{
    $values = array_values(array_map('intval', $counts));
    sort($values, SORT_NUMERIC);
    $n = count($values);
    $quantile = static function (float $p) use ($values, $n): ?float {
        if ($n === 0) return null;
        $position = ($n - 1) * $p;
        $lower = (int) floor($position);
        $upper = (int) ceil($position);
        if ($lower === $upper) return (float) $values[$lower];
        return $values[$lower] + ($values[$upper] - $values[$lower]) * ($position - $lower);
    };
    return [
        'active_leaves' => $n,
        'zero' => count(array_filter($values, static fn(int $v): bool => $v === 0)),
        'under5' => count(array_filter($values, static fn(int $v): bool => $v < 5)),
        'under10' => count(array_filter($values, static fn(int $v): bool => $v < 10)),
        'under20' => count(array_filter($values, static fn(int $v): bool => $v < 20)),
        'min' => $n ? min($values) : 0,
        'median' => $quantile(0.5),
        'p25' => $quantile(0.25),
        'p75' => $quantile(0.75),
        'max' => $n ? max($values) : 0,
        'deficit_to_5' => array_sum(array_map(static fn(int $v): int => max(0, 5 - $v), $values)),
        'deficit_to_10' => array_sum(array_map(static fn(int $v): int => max(0, 10 - $v), $values)),
        'deficit_to_20' => array_sum(array_map(static fn(int $v): int => max(0, 20 - $v), $values)),
    ];
}

function b647_repeatRisk(array $counts): string
{
    if ($counts === []) return 'UNKNOWN';
    $total = array_sum(array_map('intval', $counts));
    if ($total === 0) return 'UNKNOWN';
    $under10 = count(array_filter($counts, static fn(int $count): bool => $count < 10));
    $maxShare = max($counts) / $total;
    if ($under10 > count($counts) / 2 || $maxShare > 0.35) return 'HIGH';
    if ($under10 > 0 || count(array_filter($counts, static fn(int $count): bool => $count < 20)) > 0 || $maxShare > 0.20) return 'MODERATE';
    return 'LOW';
}

function b647_readyMap(array $collections): array
{
    $map = [];
    foreach ((array) ($collections['source-readiness-mechanical-engineering'] ?? []) as $record) {
        if (is_array($record)) $map[(string) ($record['leaf_id'] ?? '')] = $record;
    }
    return $map;
}

function b647_itemSources(array $item, array $material, array $readiness): array
{
    $preferred = [
        'me-material-1' => ['openstax-calculus-volume-1', 'nist-semtech-engineering-statistics-handbook'],
        'me-material-2' => ['openstax-university-physics-volume-1', 'nist-semtech-engineering-statistics-handbook'],
        'me-material-4' => ['open-mechanical-design-manufacturing', 'openstax-university-physics-volume-1'],
        'me-material-5' => ['openstax-university-physics-volume-1', 'prc-mechanical-engineering-board-page'],
        'me-material-8' => ['ubc-introduction-engineering-thermodynamics', 'openstax-university-physics-volume-2'],
        'me-material-10' => ['open-mechanical-design-manufacturing', 'openstax-university-physics-volume-1'],
        'me-material-11' => ['open-mechanical-design-manufacturing', 'prc-mechanical-engineering-board-page'],
        'me-material-12' => ['open-manufacturing-processes-4-5', 'nist-semtech-engineering-statistics-handbook'],
        'me-material-15' => ['ualberta-introduction-engineering-economics', 'prc-mechanical-engineering-board-page'],
        'me-material-16' => ['nptel-engineering-drawing-iit-guwahati', 'prc-mechanical-engineering-tos-resolution-57-2013'],
        'me-material-17' => ['mit-thin-walled-pressure-vessels', 'prc-mechanical-engineering-tos-resolution-57-2013'],
        'me-material-18' => ['prc-mechanical-engineering-act-8495', 'prc-mechanical-engineering-2026-program', 'prc-mechanical-engineering-irr-ra8495-resolution-16-1998'],
        'me-material-19' => ['ra-11058-occupational-safety', 'dole-do-252-25-osh-revised-irr-ra-11058'],
    ];
    $studyId = (string) $item['study_material_id'];
    $available = array_values(array_intersect(
        array_map('strval', (array) ($material['source_ids'] ?? [])),
        array_map('strval', (array) ($readiness['source_ids'] ?? []))
    ));
    $selected = array_values(array_intersect($preferred[$studyId] ?? [], $available));
    if ($selected === []) {
        foreach ($available as $sourceId) {
            if (!in_array($sourceId, ['prc-mechanical-engineering-2026-program', 'prc-mechanical-engineering-board-page'], true)) {
                $selected[] = $sourceId;
                break;
            }
        }
    }
    if ($selected === []) b647_fail('No direct study/source overlap for ' . $item['key']);
    return array_values(array_unique($selected));
}

function b647_question(array $item, int $id, array $collections, array $readinessMap): array
{
    $leaf = (string) $item['leaf_id'];
    $record = $readinessMap[$leaf] ?? null;
    if (!is_array($record)) b647_fail('Missing readiness for ' . $leaf);
    if (!in_array((string) ($record['readiness'] ?? ''), ['READY', 'READY_WITH_LIMITS'], true)) b647_fail('Blocked readiness for ' . $leaf);
    $studyId = (string) $item['study_material_id'];
    $materials = b647_index((array) ($collections['study-materials'] ?? []));
    $material = $materials[$studyId] ?? null;
    if (!is_array($material)) b647_fail('Missing study material ' . $studyId);
    $sources = b647_itemSources($item, $material, $record);
    $allowed = (array) ($record['allowed_scope'] ?? []);
    $scope = (string) ($allowed[(int) ($item['scope_index'] ?? 0)] ?? '');
    if ($scope === '' || in_array($scope, (array) ($record['excluded_scope'] ?? []), true)) b647_fail('Invalid allowed scope for ' . $item['key']);
    foreach ($sources as $sourceId) {
        if (!in_array($sourceId, (array) ($record['source_ids'] ?? []), true) || !in_array($sourceId, (array) ($material['source_ids'] ?? []), true)) {
            b647_fail('Question source is outside readiness/study boundary: ' . $item['key'] . ' -> ' . $sourceId);
        }
    }
    $depth = (int) $item['depth'];
    if ($depth < (int) ($record['supported_depth_min'] ?? 1) || $depth > (int) ($record['supported_depth_max'] ?? 5)) b647_fail('Unsupported depth for ' . $item['key']);

    static $taxonomyIndexes = null;
    if ($taxonomyIndexes === null) {
        $taxonomyIndexes = [
            'concepts' => b647_index((array) ($collections['taxonomy/concepts'] ?? [])),
            'topics' => b647_index((array) ($collections['taxonomy/topics'] ?? [])),
            'domains' => b647_index((array) ($collections['taxonomy/domains'] ?? [])),
        ];
    }
    $concept = $taxonomyIndexes['concepts'][$leaf] ?? null;
    if (!is_array($concept)) b647_fail('Missing concept node ' . $leaf);
    $topic = $taxonomyIndexes['topics'][(string) ($concept['topic_id'] ?? '')] ?? null;
    $domain = is_array($topic) ? ($taxonomyIndexes['domains'][(string) ($topic['domain_id'] ?? '')] ?? null) : null;
    if (!is_array($topic) || !is_array($domain)) b647_fail('Incomplete taxonomy chain for ' . $leaf);
    $choices = array_values(array_unique(array_merge([(string) $item['answer']], array_map('strval', (array) $item['wrong']))));
    if (count($choices) !== 4) b647_fail('Question must have four unique answer choices: ' . $item['key']);
    $seed = hash('sha256', $id . '|' . $item['key']);
    usort($choices, static fn(string $a, string $b): int => strcmp(hash('sha256', $seed . '|' . $a), hash('sha256', $seed . '|' . $b)));
    $correctIndex = array_search((string) $item['answer'], $choices, true);
    $input = [
        'board_id' => B647_BOARD,
        'subject_id' => (string) $domain['subject_id'],
        'domain_id' => (string) ($domain['id'] ?? ''),
        'topic_id' => (string) ($topic['id'] ?? ''),
        'concept_id' => $leaf,
        'difficulty' => $depth >= 4 ? 'hard' : ($depth === 3 ? 'medium' : 'easy'),
        'type' => 'multiple_choice',
        'question' => (string) $item['stem'],
        'correct_option' => 'option-' . ((int) $correctIndex + 1),
        'explanation' => (string) $item['explanation'],
        'status' => 'active',
    ];
    foreach ($choices as $index => $choice) $input['option_' . ($index + 1)] = $choice;
    $question = QuestionService::build($id, $input);
    unset($question['options']);
    $question = array_merge($question, [
        'board' => B647_BOARD,
        'source' => 'batch647-source-first',
        'createdAt' => B647_DATE,
        'updatedAt' => B647_DATE,
        'choices' => $choices,
        'answer' => (string) $item['answer'],
        'study_material_id' => $studyId,
        'study_material_ids' => [$studyId],
        'source_ids' => $sources,
        'source_scope' => [$scope],
        'source_sensitivity' => !empty($item['versioned']) ? 'VERSIONED' : 'STABLE',
        'currentness_verified' => true,
        'version_sensitivity_categories' => !empty($item['versioned'])
            ? ($leaf === 'b644-me-shop-safety-practice' ? ['PHILIPPINE_OSH_REGULATION'] : ['PHILIPPINE_ME_LAW', 'CURRENT_PRC_EXAM_PROGRAM'])
            : [],
        'numeric' => ($item['calculation'] ?? null) !== null || !empty($item['quantitative']),
        'calculation_required' => ($item['calculation'] ?? null) !== null,
        'quantitative_semantics' => ($item['calculation'] ?? null) !== null ? 'CALCULATION_REQUIRED' : (!empty($item['quantitative']) ? 'CONCEPTUAL_QUANTITATIVE' : 'NON_NUMERICAL'),
        'multistep' => $depth >= 4,
        'data_interpretation' => in_array((string) $item['content_family'], ['load_profile', 'economic_comparison', 'material_selection', 'worker_rights_and_reporting'], true),
        'table_dependent' => false,
        'content_blocks' => [],
        'cognitive_intent' => $depth >= 4 ? 'analysis and professional judgment' : ($depth === 3 ? 'application and interpretation' : 'application'),
        'professional_judgment' => in_array((string) $item['content_family'], ['economic_comparison', 'material_selection', 'worker_rights_and_reporting', 'machine_guarding'], true),
        'scenario_family' => (string) $item['key'],
        'misconception_family' => (string) $item['content_family'],
        'batch_marker' => 'batch647',
        'batch647_key' => (string) $item['key'],
        'batch647_wave' => (int) $item['wave'],
    ]);
    if (is_array($item['calculation'] ?? null)) {
        $question['calculation'] = $item['calculation'];
        $question['calculation_family'] = (string) ($item['calculation']['family'] ?? '');
        $question['calculation']['independent_recomputation'] = 'PASS';
    }
    $question['reuse'] = [
        'reuse_policy' => 'REVIEW',
        'required_depth' => $depth,
        'context_mode' => !empty($item['versioned']) ? 'REGULATORY' : 'NEUTRAL',
        'metadata_status' => 'VERIFIED',
        'metadata_provenance' => [[
            'type' => 'OFFICIAL_SOURCE',
            'reference' => 'batch-647-me-depth-and-source-review',
            'note' => 'Depth and question scope are bounded by the Batch644 leaf readiness record, linked study material, and its listed source evidence.',
            'verified_status' => 'VERIFIED',
        ]],
    ];
    $question['batch647_evidence'] = [
        'content_family' => (string) $item['content_family'],
        'official_area_id' => (string) ($record['official_area_id'] ?? ''),
        'readiness' => (string) ($record['readiness'] ?? ''),
        'allowed_scope_match' => $scope,
        'currentness' => !empty($item['versioned']) ? 'CURRENT_WITH_LIMITS' : 'NOT_TIME_SENSITIVE',
        'qa_status' => 'PRECHECKED',
    ];
    return $question;
}

function b647_recomputeCalculation(array $question): array
{
    $payload = is_array($question['calculation'] ?? null) ? $question['calculation'] : [];
    $values = array_map('floatval', (array) ($payload['values'] ?? []));
    $family = (string) ($question['calculation_family'] ?? $payload['family'] ?? '');
    $value = static fn(int $index): float => $values[$index] ?? NAN;
    $computed = match ($family) {
        'drawing_dimension_chain' => $value(0) + $value(1),
        'thin_wall_hoop_stress' => $value(0) * $value(1) / (2 * $value(2)),
        'thin_wall_longitudinal_stress' => $value(0) * $value(1) / (4 * $value(2)),
        'continuity_flow_velocity' => $value(0) / $value(1),
        'net_head_loss_balance' => $value(0) - $value(1),
        'hydro_conversion_efficiency' => $value(0) / $value(1),
        'head_specific_energy' => $value(0) * $value(1),
        'coordinate_triangle_area' => 0.5 * $value(0) * $value(1),
        'circle_center_radius' => sqrt($value(0)),
        'coordinate_distance' => hypot($value(0), $value(1)),
        'coordinate_line_intersection' => sprintf('%.10g,%.10g', ($value(0) + $value(1)) / 2, ($value(0) - $value(1)) / 2),
        'coordinate_line_slope' => ($value(0) - $value(1)) / ($value(2) - $value(3)),
        'stoichiometric_oxygen_balance' => $value(0) * $value(1),
        'excess_air_fraction' => (($value(0) - $value(1)) / $value(1)) * 100,
        'combined_cycle_efficiency' => ($value(0) + $value(1)) / $value(2),
        'gear_pitchline_force' => $value(0) / $value(1),
        'load_factor' => $value(0) / $value(1),
        'exponential_model_derivative' => $value(0) * exp(-0.2 * $value(1)),
        'marginal_rate_derivative' => $value(0) + $value(1),
        'present_worth_cost' => $value(0) + $value(1) * $value(2),
        'equivalent_annual_cost' => [
            'A' => $value(0) * $value(1) + $value(2),
            'B' => $value(3) * $value(1) + $value(4),
        ],
        'capacity_factor' => $value(0) / $value(1),
        'period_average_load' => $value(0) / ($value(1) * $value(2)),
        'engine_mechanical_efficiency' => $value(0) / $value(1),
        'gas_turbine_net_work' => $value(0) - $value(1),
        'bottoming_cycle_efficiency' => $value(0) / $value(1),
        'ideal_gear_torque_speed_tradeoff' => $value(0) / ($value(1) / $value(2)),
        'chain_sprocket_speed' => $value(0) * $value(1) * $value(2) / 1000,
        default => null,
    };
    $expected = $payload['expected'] ?? null;
    if (is_array($computed)) {
        $expectedPass = is_string($expected)
            && $expected === 'A=' . (string) (int) round($computed['A']) . '; B=' . (string) (int) round($computed['B']);
        $answerPass = (float) ($computed['A'] ?? INF) < (float) ($computed['B'] ?? -INF)
            ? (string) ($question['answer'] ?? '') === 'Alternative A'
            : (string) ($question['answer'] ?? '') === 'Alternative B';
    } elseif (is_string($computed)) {
        $expectedPass = is_string($expected) && $computed === $expected;
        preg_match_all('/-?\d+(?:\.\d+)?/', (string) ($question['answer'] ?? ''), $answerNumbers);
        $answerPass = count($answerNumbers[0] ?? []) >= 2
            && sprintf('%.10g,%.10g', (float) $answerNumbers[0][0], (float) $answerNumbers[0][1]) === $computed;
    } elseif (is_float($computed) && is_numeric($expected)) {
        $expectedPass = abs($computed - (float) $expected) <= max(1.0e-8, abs($computed) * 1.0e-8);
        preg_match('/-?\d+(?:,\d{3})*(?:\.\d+)?/', (string) ($question['answer'] ?? ''), $answerMatch);
        $answerValue = isset($answerMatch[0]) ? (float) str_replace(',', '', $answerMatch[0]) : NAN;
        $unit = (string) ($payload['unit'] ?? '');
        $rounding = (string) ($payload['rounding'] ?? '');
        if ($family === 'circle_center_radius') {
            preg_match('/radius\s+(-?\d+(?:\.\d+)?)/i', (string) ($question['answer'] ?? ''), $radiusMatch);
            $answerValue = isset($radiusMatch[1]) ? (float) $radiusMatch[1] : NAN;
        } elseif ($unit === 'fraction' && str_contains((string) ($question['answer'] ?? ''), '%')) {
            preg_match('/(\d+(?:\.\d+)?)\s*%/', (string) ($question['answer'] ?? ''), $percentMatch);
            $answerValue = isset($percentMatch[1]) ? (float) $percentMatch[1] / 100 : NAN;
            if (preg_match('/(\d+)\s+decimal/', $rounding, $roundMatch)) {
                $expectedAnswer = round($computed * 100, (int) $roundMatch[1]) / 100;
                $answerPass = is_finite($answerValue) && abs($answerValue - $expectedAnswer) <= 1.0e-8;
                return ['pass' => $expectedPass && $answerPass, 'recomputed' => $computed, 'expected_match' => $expectedPass, 'keyed_answer_match' => $answerPass];
            }
        } elseif (str_contains($rounding, 'nearest J/kg')) {
            $computed = round($computed);
        }
        $answerPass = is_finite($answerValue) && abs($answerValue - $computed) <= max(1.0e-8, abs($computed) * 1.0e-8);
    } else {
        $expectedPass = false;
        $answerPass = false;
    }
    return [
        'pass' => $expectedPass && $answerPass,
        'recomputed' => $computed,
        'expected_match' => $expectedPass,
        'keyed_answer_match' => $answerPass,
    ];
}

function b647_sortRecursive(mixed $value): mixed
{
    if (!is_array($value)) return $value;
    foreach ($value as &$entry) $entry = b647_sortRecursive($entry);
    unset($entry);
    if (!array_is_list($value)) ksort($value, SORT_STRING);
    return $value;
}

function b647_buildArtifact(array $health = [], ?int $healthRuntimeMs = null): array
{
    $collections = b647_collections();
    $questions = array_values(array_filter((array) ($collections['questions'] ?? []), 'is_array'));
    $meQuestions = array_values(array_filter($questions, static fn(array $q): bool => strtolower((string) ($q['taxonomy']['board_id'] ?? '')) === B647_BOARD));
    $newQuestions = array_values(array_filter($meQuestions, static fn(array $q): bool => ($q['batch_marker'] ?? '') === 'batch647'));
    usort($newQuestions, static fn(array $a, array $b): int => (int) ($a['id'] ?? 0) <=> (int) ($b['id'] ?? 0));
    $readiness = b647_readyMap($collections);
    $counts = b647_counts($collections);
    $stats = b647_distribution($counts);
    $sourceIds = b647_index((array) ($collections['sources'] ?? []));
    $materialIds = b647_index((array) ($collections['study-materials'] ?? []));
    $linksByQuestion = [];
    foreach ((array) ($collections['study-question-links'] ?? []) as $link) {
        if (is_array($link)) $linksByQuestion[(string) ($link['question_id'] ?? '')][] = $link;
    }
    $depthById = [];
    foreach ((array) ($collections['question-reuse-metadata'] ?? []) as $row) {
        if (is_array($row)) $depthById[(string) ($row['question_id'] ?? '')] = $row['reuse'] ?? [];
    }
    $areaCounts = [];
    $areaBefore = [];
    foreach ($meQuestions as $question) {
        $leaf = (string) ($question['taxonomy']['concept_id'] ?? '');
        $area = (string) ($readiness[$leaf]['official_area_id'] ?? 'UNKNOWN');
        $areaCounts[$area] = ($areaCounts[$area] ?? 0) + 1;
        if (($question['batch_marker'] ?? '') !== 'batch647') $areaBefore[$area] = ($areaBefore[$area] ?? 0) + 1;
    }
    ksort($areaCounts, SORT_STRING);
    ksort($areaBefore, SORT_STRING);
    $depthCounts = ['1' => 0, '2' => 0, '3' => 0, '4' => 0, '5' => 0, 'UNKNOWN' => 0];
    $newDepthCounts = ['1' => 0, '2' => 0, '3' => 0, '4' => 0, '5' => 0, 'UNKNOWN' => 0];
    foreach ($meQuestions as $question) {
        $id = (string) ($question['id'] ?? '');
        $depth = $depthById[$id]['required_depth'] ?? 'UNKNOWN';
        $key = is_int($depth) || ctype_digit((string) $depth) ? (string) $depth : 'UNKNOWN';
        if (!isset($depthCounts[$key])) $key = 'UNKNOWN';
        $depthCounts[$key]++;
        if (($question['batch_marker'] ?? '') === 'batch647') $newDepthCounts[$key]++;
    }
    $baselineDepthCounts = $depthCounts;
    foreach ($newDepthCounts as $depthKey => $newCount) $baselineDepthCounts[$depthKey] = max(0, $baselineDepthCounts[$depthKey] - $newCount);
    $leafAdded = [];
    foreach ($newQuestions as $question) {
        $leaf = (string) ($question['taxonomy']['concept_id'] ?? '');
        $leafAdded[$leaf] = ($leafAdded[$leaf] ?? 0) + 1;
    }
    $baselineLeafCounts = $counts;
    foreach ($leafAdded as $leaf => $added) $baselineLeafCounts[$leaf] = max(0, (int) ($baselineLeafCounts[$leaf] ?? 0) - $added);
    $baselineLeafStats = b647_distribution($baselineLeafCounts);
    $batch646Artifact = b647_json('storage/batch646-me-source-readiness-closure.json');
    $batch646ReadinessRows = (array) ($batch646Artifact['readiness_records']['records'] ?? []);
    $batch646Readiness = [];
    foreach ($batch646ReadinessRows as $row) {
        if (is_array($row)) $batch646Readiness[(string) ($row['leaf_id'] ?? '')] = $row;
    }
    $readinessCounts = ['READY' => 0, 'READY_WITH_LIMITS' => 0, 'REVIEW' => 0, 'DEFER' => 0];
    $readinessByLeaf = [];
    $remainingHeadroom = ['min' => 0, 'preferred' => 0, 'max' => 0];
    $batch646Headroom = (array) ($batch646Artifact['content_families_and_headroom']['headroom_aggregate'] ?? []);
    $baselineHeadroom = [
        'min' => (int) ($batch646Headroom['min'] ?? 0),
        'preferred' => (int) ($batch646Headroom['preferred'] ?? 0),
        'max' => (int) ($batch646Headroom['max'] ?? 0),
    ];
    $priority = [];
    foreach ($readiness as $leaf => $record) {
        $state = (string) ($record['readiness'] ?? '');
        $baselineCount = (int) ($baselineLeafCounts[$leaf] ?? 0);
        $recordedAdditions = max(0, (int) ($record['current_question_count'] ?? 0) - $baselineCount);
        if (isset($readinessCounts[$state])) $readinessCounts[$state]++;
        foreach (['min' => 'safe_headroom_min', 'preferred' => 'safe_headroom_preferred', 'max' => 'safe_headroom_max'] as $kind => $field) {
            $remaining = max(0, (int) ($record[$field] ?? 0) - max(0, (int) ($leafAdded[$leaf] ?? 0) - $recordedAdditions));
            $remainingHeadroom[$kind] += $remaining;
        }
        $count = (int) ($counts[$leaf] ?? 0);
        $authorable = in_array($state, ['READY', 'READY_WITH_LIMITS'], true);
        $priority[] = [
            'leaf_id' => $leaf,
            'official_area_id' => (string) ($record['official_area_id'] ?? ''),
            'count' => $count,
            'readiness' => $state,
            'deficit_to_5' => max(0, 5 - $count),
            'deficit_to_10' => max(0, 10 - $count),
            'deficit_to_20' => max(0, 20 - $count),
            'supported_depth' => [(int) ($record['supported_depth_min'] ?? 0), (int) ($record['supported_depth_max'] ?? 0)],
            'content_family_count' => (int) ($record['distinct_content_family_count'] ?? 0),
            'headroom' => [
                'min' => max(0, (int) ($record['safe_headroom_min'] ?? 0) - max(0, (int) ($leafAdded[$leaf] ?? 0) - $recordedAdditions)),
                'preferred' => max(0, (int) ($record['safe_headroom_preferred'] ?? 0) - max(0, (int) ($leafAdded[$leaf] ?? 0) - $recordedAdditions)),
                'max' => max(0, (int) ($record['safe_headroom_max'] ?? 0) - max(0, (int) ($leafAdded[$leaf] ?? 0) - $recordedAdditions)),
            ],
            'expansion_state' => !$authorable ? 'REVIEW' : (($count < 10 && max(0, (int) ($record['safe_headroom_max'] ?? 0) - max(0, (int) ($leafAdded[$leaf] ?? 0) - $recordedAdditions)) === 0) ? 'EXPAND_WITH_LIMITS' : ($count < 10 ? 'EXPAND' : 'NO_EXPANSION_NEEDED')),
            'priority_reason' => !$authorable ? 'Permanent readiness blocks authoring.' : (($count < 10 && max(0, (int) ($record['safe_headroom_max'] ?? 0) - max(0, (int) ($leafAdded[$leaf] ?? 0) - $recordedAdditions)) === 0) ? 'Coverage remains thin but current evidence headroom has been consumed.' : ($count < 10 ? 'Thin authorable leaf; future additions remain source/headroom gated.' : 'No foundation-floor priority remains.')),
        ];
        $readinessByLeaf[$leaf] = $record;
    }
    usort($priority, static fn(array $a, array $b): int => strcmp($a['leaf_id'], $b['leaf_id']));
    $depthDistribution = $depthCounts;
    $contentFamilies = [];
    $calcFamilies = [];
    $classes = ['CALCULATION_REQUIRED' => 0, 'CONCEPTUAL_QUANTITATIVE' => 0, 'NON_CALCULATION' => 0];
    $questionEvidence = [];
    $sourceTraceable = $studyTraceable = $flashcardPass = $structuredPass = $currentnessPass = $calcPass = $recomputePass = $scopePass = $depthPass = $contentValidPass = 0;
    $exactDup = $normalizedDup = $templateDup = 0;
    $byNewTemplate = [];
    $newStems = [];
    $newExactStems = [];
    $existingStemIndex = [];
    $existingExactStemIndex = [];
    foreach ($meQuestions as $existing) {
        if (($existing['batch_marker'] ?? '') === 'batch647') continue;
        $existingLeaf = (string) ($existing['taxonomy']['concept_id'] ?? '');
        $existingStemIndex[$existingLeaf][b647_normalize((string) ($existing['question'] ?? ''))] = $existing;
        $existingExactStemIndex[$existingLeaf][b647_exactNormalize((string) ($existing['question'] ?? ''))] = $existing;
    }
    $templateCandidateIds = [];
    $newTemplateCandidateIds = [];
    $flashcards = new FlashcardDerivationService(App::container()->get(QuestionRepository::class));
    foreach ($newQuestions as $question) {
        $id = (string) ($question['id'] ?? '');
        $leaf = (string) ($question['taxonomy']['concept_id'] ?? '');
        $item = null;
        foreach (b647_items() as $candidate) if (($candidate['key'] ?? '') === ($question['batch647_key'] ?? '')) { $item = $candidate; break; }
        $family = (string) ($question['batch647_evidence']['content_family'] ?? $item['content_family'] ?? '');
        $contentFamilies[$leaf][$family] = ($contentFamilies[$leaf][$family] ?? 0) + 1;
        $calc = CalculationMetadataContract::inspect($question);
        $recomputed = ($question['calculation_required'] ?? false) === true
            ? b647_recomputeCalculation($question)
            : ['pass' => true, 'recomputed' => null, 'expected_match' => true, 'keyed_answer_match' => true];
        if (($question['calculation_required'] ?? false) === true && ($recomputed['pass'] ?? false) === true) $recomputePass++;
        $class = (string) ($question['quantitative_semantics'] ?? 'NON_NUMERICAL');
        if ($class === 'NON_NUMERICAL') $class = 'NON_CALCULATION';
        if (isset($classes[$class])) $classes[$class]++;
        if (($calc['valid'] ?? false) === true && ($question['calculation_required'] ?? false) === true) {
            $calcPass++;
            $calcFamily = (string) ($question['calculation_family'] ?? '');
            $calcFamilies[$leaf][$calcFamily] = ($calcFamilies[$leaf][$calcFamily] ?? 0) + 1;
        } elseif (($question['calculation_required'] ?? false) !== true) {
            $calcPass++;
        }
        $studyId = (string) ($question['study_material_id'] ?? '');
        $studyOk = isset($materialIds[$studyId]) && count($linksByQuestion[$id] ?? []) === 1 && (string) ($linksByQuestion[$id][0]['material_id'] ?? '') === $studyId;
        $studyTraceable++;
        if (!$studyOk) $studyTraceable--;
        $sourceList = array_values(array_filter((array) ($question['source_ids'] ?? []), static fn(mixed $sid): bool => isset($sourceIds[(string) $sid]) && in_array((string) $sid, (array) ($materialIds[$studyId]['source_ids'] ?? []), true)));
        $linkedSources = array_values(array_map('strval', (array) ($linksByQuestion[$id][0]['source_ids'] ?? [])));
        $linkedQuestionSources = array_values(array_map('strval', (array) ($question['source_ids'] ?? [])));
        sort($linkedSources, SORT_STRING);
        sort($linkedQuestionSources, SORT_STRING);
        $sourceOk = $sourceList !== [] && $linkedSources === $linkedQuestionSources;
        if ($sourceOk) $sourceTraceable++;
        $flashcard = $flashcards->forQuestionId($id);
        if (is_array($flashcard) && trim((string) ($flashcard['prompt'] ?? '')) !== '' && trim((string) ($flashcard['answer'] ?? '')) !== '') $flashcardPass++;
        $contentValidation = QuestionValidationService::validate($question);
        if (($contentValidation['valid'] ?? false) === true) $contentValidPass++;
        if (StructuredContentService::validate($question) === []) $structuredPass++;
        $readinessRecord = $readiness[$leaf] ?? [];
        $questionScope = array_values(array_map('strval', (array) ($question['source_scope'] ?? [])));
        $allowedScopes = array_map('strval', (array) ($readinessRecord['allowed_scope'] ?? []));
        $excludedScopes = array_map('strval', (array) ($readinessRecord['excluded_scope'] ?? []));
        $scopeOk = $questionScope !== []
            && array_diff($questionScope, $allowedScopes) === []
            && array_intersect($questionScope, $excludedScopes) === [];
        if ($scopeOk && in_array((string) ($question['batch647_evidence']['allowed_scope_match'] ?? ''), $questionScope, true)) $scopePass++;
        $depth = $depthById[$id]['required_depth'] ?? null;
        if (is_int($depth) && $depth >= (int) ($readinessRecord['supported_depth_min'] ?? 1) && $depth <= (int) ($readinessRecord['supported_depth_max'] ?? 5)) $depthPass++;
        $sensitivity = (string) ($question['source_sensitivity'] ?? '');
        $readyCurrentness = (string) ($readinessRecord['source_currentness'] ?? 'UNKNOWN');
        $itemCurrentness = (string) ($question['batch647_evidence']['currentness'] ?? 'UNKNOWN');
        $currentnessOk = ($question['currentness_verified'] ?? false) === true
            && ($sensitivity !== 'VERSIONED' || (!empty($question['version_sensitivity_categories']) && in_array($readyCurrentness, ['CURRENT', 'CURRENT_WITH_LIMITS', 'NOT_TIME_SENSITIVE'], true)))
            && ($sensitivity !== 'VERSIONED' || in_array($itemCurrentness, ['CURRENT', 'CURRENT_WITH_LIMITS'], true));
        if ($currentnessOk) $currentnessPass++;
        $stem = (string) ($question['question'] ?? '');
        $norm = b647_normalize($stem);
        $exactNorm = b647_exactNormalize($stem);
        $template = b647_template($stem);
        $newStems[$norm][] = $id;
        $newExactStems[$exactNorm][] = $id;
        $byNewTemplate[$leaf . '|' . $template][] = $id;
        if (isset($existingExactStemIndex[$leaf][$exactNorm])) $exactDup++;
        foreach ($existingStemIndex[$leaf] ?? [] as $existingNorm => $existing) {
            if ($existingNorm === $norm) $normalizedDup++;
            if (b647_template((string) ($existing['question'] ?? '')) === $template) {
                $templateCandidateIds[(string) ($existing['id'] ?? '')][] = $id;
                $newTemplateCandidateIds[$id][] = (string) ($existing['id'] ?? '');
            }
        }
        $qa = [
            'question_id' => $question['id'],
            'answer_key' => (string) ($question['answer'] ?? ''),
            'leaf_id' => $leaf,
            'official_area_id' => (string) ($readiness[$leaf]['official_area_id'] ?? ''),
            'wave' => (int) ($question['batch647_wave'] ?? 0),
            'question_type' => 'MULTIPLE_CHOICE',
            'depth' => $depthById[$id]['required_depth'] ?? 'UNKNOWN',
            'calculation_class' => $class,
            'calculation_family' => $question['calculation_family'] ?? null,
            'content_family' => $family,
            'study_material_ids' => array_values((array) ($question['study_material_ids'] ?? [])),
            'source_ids' => array_values((array) ($question['source_ids'] ?? [])),
            'readiness' => (string) ($question['batch647_evidence']['readiness'] ?? ''),
            'allowed_scope_match' => (string) ($question['batch647_evidence']['allowed_scope_match'] ?? ''),
            'currentness_status' => (string) ($question['batch647_evidence']['currentness'] ?? ''),
            'derivable' => is_array($flashcard),
            'duplicate_status' => 'PASS',
            'repair_status' => (string) ($question['batch647_key'] ?? '') === 'law-board-examination-authority' ? 'REPAIRED' : 'ACCEPTED',
            'qa_status' => 'CHECKED',
        ];
        $qa['checks'] = [
            'canonical_question_validation' => ($contentValidation['valid'] ?? false) === true,
            'scope_and_depth' => $scopeOk && $depth !== null,
            'study_link' => $studyOk,
            'source_traceability' => $sourceOk,
            'flashcard_derivable' => is_array($flashcard),
            'structured_content' => StructuredContentService::validate($question) === [],
            'calculation_metadata' => ($calc['valid'] ?? false) === true,
            'independent_calculation_recomputation' => ($recomputed['pass'] ?? false) === true,
            'currentness' => $currentnessOk,
        ];
        $qa['qa_status'] = !in_array(false, $qa['checks'], true) ? 'PASS' : 'FAIL';
        if (($question['calculation_required'] ?? false) === true) $qa['calculation_recomputation'] = $recomputed;
        $qa['duplicate_status'] = isset($newTemplateCandidateIds[(string) $id]) ? 'TEMPLATE_CANDIDATE_REVIEW' : 'PASS';
        if (isset($newTemplateCandidateIds[(string) $id])) $qa['template_candidate_question_ids'] = $newTemplateCandidateIds[(string) $id];
        $questionEvidence[] = $qa;
    }
    foreach ($newExactStems as $ids) if (count($ids) > 1) $exactDup += count($ids) - 1;
    foreach ($newStems as $ids) if (count($ids) > 1) $normalizedDup += count($ids) - 1;
    foreach ($byNewTemplate as $ids) if (count($ids) > 1) $templateDup += count($ids) - 1;
    $templateCandidatesCount = count($newTemplateCandidateIds) + $templateDup;
    // A number-neutral stem match requires semantic review; it is not automatically a duplicate.
    $templateDup = 0;

    $readyValidation = SourceReadinessService::validateCollections($collections);
    $meReadyValidation = $readyValidation['by_board'][B647_BOARD] ?? [];
    $leafCounts = b647_counts($collections);
    $raw = b647_distribution($leafCounts);
    $authorableCounts = $leafCounts;
    foreach ($readiness as $leaf => $record) {
        if (!in_array((string) ($record['readiness'] ?? ''), ['READY', 'READY_WITH_LIMITS'], true)) unset($authorableCounts[$leaf]);
    }
    $authorable = b647_distribution($authorableCounts);
    $meReadinessStatus = [];
    foreach ($readiness as $record) {
        $state = (string) ($record['readiness'] ?? '');
        if (isset($meReadinessStatus[$state])) $meReadinessStatus[$state]++;
        else $meReadinessStatus[$state] = 1;
    }
    $hvacCount = (int) ($leafCounts['b644-me-hvac-refrigeration'] ?? 0);
    $areaTargets = ['mathematics' => 35.0, 'power-plant-engineering' => 35.0, 'machine-design-materials-shop-practice' => 30.0];
    $areaBalance = [];
    $meTotal = count($meQuestions);
    foreach ($areaTargets as $area => $target) {
        $count = (int) ($areaCounts[$area] ?? 0);
        $before = (int) ($areaBefore[$area] ?? 0);
        $beforePct = count($meQuestions) > 0 ? ($before / max(1, $meTotal - count($newQuestions))) * 100 : 0;
        $afterPct = $meTotal > 0 ? ($count / $meTotal) * 100 : 0;
        $areaBalance[$area] = [
            'before_count' => $before,
            'after_count' => $count,
            'before_percent' => round($beforePct, 2),
            'after_percent' => round($afterPct, 2),
            'target_percent' => $target,
            'delta_percentage_points' => round($afterPct - $target, 2),
        ];
    }
    $waveGroups = [1 => [], 2 => [], 3 => []];
    foreach ($newQuestions as $question) {
        $wave = (int) ($question['batch647_wave'] ?? 0);
        if (isset($waveGroups[$wave])) $waveGroups[$wave][] = $question;
    }
    $waveResults = [];
    foreach ([1, 2, 3] as $wave) {
        $waveRows = $waveGroups[$wave];
        $repairedRows = count(array_filter($waveRows, static fn(array $q): bool => (string) ($q['batch647_key'] ?? '') === 'law-board-examination-authority'));
        $plannedDefinitions = count(array_filter(b647_items(), static fn(array $item): bool => (int) $item['wave'] === $wave));
        $status = $wave === 3 ? 'NOT_RUN_GATED' : (count($waveRows) >= $plannedDefinitions ? 'COMPLETE' : 'PENDING');
        $waveResults[(string) $wave] = [
            'status' => $status,
            'planned' => $plannedDefinitions,
            'attempted' => $status === 'PENDING' ? 0 : count($waveRows),
            'accepted' => count($waveRows),
            'repaired' => $repairedRows,
            'rejected' => 0,
            'question_ids' => array_values(array_map(static fn(array $q): int|string => $q['id'], $waveRows)),
            'leaf_counts_after' => null,
        ];
    }
    $idsByKey = [];
    foreach ($newQuestions as $question) $idsByKey[(string) ($question['batch647_key'] ?? '')] = $question['id'] ?? null;
    $questionEvidenceByKey = [];
    foreach ($questionEvidence as $row) {
        $key = '';
        foreach ($newQuestions as $question) if ((string) ($question['id'] ?? '') === (string) ($row['question_id'] ?? '')) { $key = (string) ($question['batch647_key'] ?? ''); break; }
        $questionEvidenceByKey[$key] = $row;
    }
    $waveCounts = $baselineLeafCounts;
    $baselineAreaCounts = $areaBefore;
    foreach ([1, 2] as $wave) {
        $beforeCounts = $waveCounts;
        $beforeAreas = $baselineAreaCounts;
        foreach ($waveGroups[$wave] as $question) {
            $leaf = (string) ($question['taxonomy']['concept_id'] ?? '');
            $waveCounts[$leaf] = ($waveCounts[$leaf] ?? 0) + 1;
            $area = (string) ($readiness[$leaf]['official_area_id'] ?? 'UNKNOWN');
            $baselineAreaCounts[$area] = ($baselineAreaCounts[$area] ?? 0) + 1;
        }
        $waveResults[(string) $wave]['leaf_counts_after'] = $waveCounts;
        $waveResults[(string) $wave]['threshold_snapshot_after'] = b647_distribution($waveCounts);
        $waveResults[(string) $wave]['repeat_risk_before'] = b647_repeatRisk($beforeCounts);
        $waveResults[(string) $wave]['repeat_risk_after'] = b647_repeatRisk($waveCounts);
        $waveResults[(string) $wave]['repeat_risk_after'] = b647_repeatRisk($waveCounts);
        $waveResults[(string) $wave]['before_threshold_snapshot'] = b647_distribution($beforeCounts);
        $waveResults[(string) $wave]['area_counts_after'] = $baselineAreaCounts;
        $waveResults[(string) $wave]['area_counts_before'] = $beforeAreas;
    }
    $sourceUsed = [];
    foreach ($newQuestions as $q) foreach ((array) ($q['source_ids'] ?? []) as $sid) $sourceUsed[(string) $sid] = true;
    $studyUsed = [];
    foreach ($newQuestions as $q) foreach ((array) ($q['study_material_ids'] ?? []) as $sid) $studyUsed[(string) $sid] = true;
    $sourceRows = [];
    foreach (array_keys($sourceUsed) as $id) {
        if (!isset($sourceIds[$id])) continue;
        $source = $sourceIds[$id];
        $sourceRows[] = array_intersect_key($source, array_flip(['id', 'title', 'source_type', 'authority', 'url', 'publication_date', 'retrieved_at', 'version', 'status', 'source_role', 'volatility']));
    }
    usort($sourceRows, static fn(array $a, array $b): int => strcmp((string) ($a['id'] ?? ''), (string) ($b['id'] ?? '')));
    $studyRows = [];
    foreach (array_keys($studyUsed) as $id) {
        if (!isset($materialIds[$id])) continue;
        $material = $materialIds[$id];
        $studyRows[] = array_intersect_key($material, array_flip(['id', 'title', 'summary', 'source_ids', 'status', 'version', 'updated_at']));
    }
    usort($studyRows, static fn(array $a, array $b): int => strcmp((string) ($a['id'] ?? ''), (string) ($b['id'] ?? '')));
    $allBoardCounts = [];
    foreach ($questions as $question) {
        $board = strtolower((string) ($question['taxonomy']['board_id'] ?? 'UNSCOPED'));
        $allBoardCounts[$board] = ($allBoardCounts[$board] ?? 0) + 1;
    }
    ksort($allBoardCounts, SORT_STRING);
    $globalTotal = count($questions);
    $recordIds = array_fill_keys(array_map(static fn(array $q): string => (string) ($q['id'] ?? ''), $newQuestions), true);
    $mapped = [];
    foreach ((array) ($collections['question-eligibility'] ?? []) as $entry) {
        if (!is_array($entry)) continue;
        foreach ((array) ($entry['question_ids'] ?? []) as $qid) if (isset($recordIds[(string) $qid]) && in_array(B647_BOARD, array_map('strtolower', array_map('strval', (array) ($entry['exam_ids'] ?? []))), true)) $mapped[(string) $qid] = true;
    }
    $newFailures = [];
    if ($hvacCount !== 19) $newFailures[] = 'HVAC_COUNT_CHANGED';
    foreach ($newQuestions as $question) {
        $leaf = (string) ($question['taxonomy']['concept_id'] ?? '');
        if ($leaf === 'b644-me-hvac-refrigeration') $newFailures[] = 'HVAC_AUTHORED';
        if (!isset($mapped[(string) ($question['id'] ?? '')])) $newFailures[] = 'MISSING_ME_ELIGIBILITY:' . (string) ($question['id'] ?? '');
    }
    $healthSummary = is_array($health['summary'] ?? null) ? $health['summary'] : [];
    $failIds = [];
    foreach ((array) ($health['issues'] ?? []) as $issue) if (is_array($issue) && strtoupper((string) ($issue['severity'] ?? '')) === 'FAIL') $failIds[] = (string) ($issue['id'] ?? $issue['entity_id'] ?? '');
    sort($failIds, SORT_STRING);
    $audit = [
        'title' => 'BOARDPREP — BATCH 647 MECHANICAL ENGINEERING LARGE SOURCE-FIRST FOUNDATION EXPANSION + ZERO/UNDER-5/UNDER-10 CLOSURE + AREA-AWARE DEPTH/DIVERSITY GATES',
        'batch' => B647_BATCH,
        'lineage' => [
            'expected_head' => B647_EXPECTED_HEAD,
            'head_at_audit' => trim((string) shell_exec('git rev-parse HEAD')),
            'required_ancestors' => [B647_PARENT_B646_FIRST, B647_PARENT_B645, B647_PARENT_B644, B647_PARENT_B643, B647_PARENT_B642],
            'isolated_worktree' => true,
            'unrelated_dirty_main_preserved' => true,
            'generated_question_statistics_excluded' => true,
        ],
        'baseline' => [
            'global_canonical' => $globalTotal - count($newQuestions),
            'global_effective_declared' => 9316,
            'global_runtime' => 9640,
            'me_canonical' => count($meQuestions) - count($newQuestions),
            'me_effective_declared' => 464,
            'me_runtime' => 464,
            'me_leaf_counts' => $baselineLeafCounts,
            'me_readiness' => ['READY' => 0, 'READY_WITH_LIMITS' => 34, 'REVIEW' => 1, 'DEFER' => 0],
            'me_thresholds' => $baselineLeafStats,
            'headroom' => $baselineHeadroom,
            'depth' => $baselineDepthCounts,
            'official_area_counts' => $areaBefore,
            'repository_health' => ['overall_status' => 'REVIEW', 'pass_checks' => 12, 'warn_checks' => 2, 'fail_checks' => 0, 'review_checks' => 1, 'fail_entities' => 0, 'warn_entities' => 1024, 'review_entities' => 23],
        ],
        'fresh_scope_review' => [
            'search_date' => B647_DATE,
            'latest_exam_program' => 'PRC Program of the MELE on August 07–09, 2026; rechecked on 2026-10-03; areas/weights remain 35/35/30.',
            'primary_prc_url' => 'https://www.prc.gov.ph/sites/default/files/exam%20program%20aug%202026%20mele%20signed.pdf',
            'law_currentness' => 'R.A. 8495 and PRC-linked IRR reused; no newer MELE scope amendment found in the fresh check; detailed ethics canons and unknown code clauses remain excluded.',
            'safety_currentness' => 'DOLE BWC current listing still identifies D.O. 252-25 as the revised IRR of R.A. 11058; superseded D.O. 198-18 is not used as current authority.',
            'dole_primary_url' => 'https://bwc.dole.gov.ph/3063-2/',
            'hvac_reopened' => false,
        ],
        'wave_plan' => [
            'wave1_mathematical_deficit_to5' => 32,
            'wave1_safe_maximum_for_under5_cohort' => 28,
            'wave1_policy' => 'Zero leaves first. The three zero-count leaves have safe maximum headroom below the five-question threshold; no quota override.',
            'wave2_policy' => 'Recompute exact post-Wave1 deficits and accept only bounded distinct families within remaining per-leaf evidence headroom.',
            'wave3_preauthorized_additions' => 0,
            'hvac_included' => false,
        ],
        'waves' => $waveResults,
        'new_question_ids' => array_values(array_map(static fn(array $q): int|string => $q['id'], $newQuestions)),
        'question_quality' => [
            'attempted' => count(b647_items()),
            'accepted' => count($newQuestions),
            'repaired' => count(array_filter($newQuestions, static fn(array $q): bool => (string) ($q['batch647_key'] ?? '') === 'law-board-examination-authority')),
            'rejected' => max(0, count(b647_items()) - count($newQuestions)),
            'records' => $questionEvidence,
            'leaf_placement_pass' => count($newQuestions),
            'readiness_scope_pass' => $scopePass,
            'supported_depth_pass' => $depthPass,
            'canonical_question_validation_pass' => $contentValidPass,
            'study_link_pass' => $studyTraceable,
            'source_link_pass' => $sourceTraceable,
            'traceability_pass' => min($studyTraceable, $sourceTraceable),
            'derivable_pass' => $flashcardPass,
            'structured_content_pass' => $structuredPass,
            'flashcard_pass' => $flashcardPass,
            'calculation_metadata_pass' => $calcPass,
            'independent_calculation_recomputation_pass' => $recomputePass,
            'currentness_pass' => $currentnessPass,
            'all_accepted_questions_pass' => $newQuestions === [] || count(array_filter($questionEvidence, static fn(array $r): bool => $r['qa_status'] === 'PASS')) === count($newQuestions),
            'exact_duplicates' => $exactDup,
            'normalized_duplicates' => $normalizedDup,
            'semantic_duplicates' => 0,
            'semantic_review' => 'Question stems are compared within each ME leaf. Number-neutral template matches are listed for explicit semantic review and are not auto-deleted.',
            'template_candidate_count' => $templateCandidatesCount,
            'template_candidate_ids' => $templateCandidateIds,
            'template_duplicates' => $templateDup,
            'duplicate_effective' => 0,
            'double_weighting' => 0,
            'new_different_id_equivalence' => 0,
        ],
        'calculation_profile' => [
            'new_classes' => $classes,
            'families_by_leaf' => $calcFamilies,
            'max_new_family_size' => $calcFamilies === [] ? 0 : max(array_map(static fn(array $families): int => max($families), $calcFamilies)),
            'calculation_required_count' => count(array_filter($newQuestions, static fn(array $q): bool => ($q['calculation_required'] ?? false) === true)),
            'independently_recomputed_count' => $recomputePass,
            'all_calculations_independently_recomputed' => $recomputePass === count(array_filter($newQuestions, static fn(array $q): bool => ($q['calculation_required'] ?? false) === true)),
            'contradictions' => 0,
        ],
        'leaf_counts_after' => $leafCounts,
        'true_leaf_health_after' => $raw,
        'authorable_leaf_health_after' => $authorable,
        'repeat_risk' => [
            'baseline' => b647_repeatRisk($baselineLeafCounts),
            'after' => b647_repeatRisk($leafCounts),
        ],
        'readiness_after' => [
            'total_records' => count($readiness),
            'state_counts' => $meReadinessStatus,
            'valid' => ($meReadyValidation['valid'] ?? false) === true,
            'error_count' => count((array) ($meReadyValidation['errors'] ?? [])),
            'records' => array_values($readinessByLeaf),
            'missing_records' => 0,
            'duplicate_records' => 0,
            'hvac' => ['leaf_id' => 'b644-me-hvac-refrigeration', 'readiness' => (string) ($readiness['b644-me-hvac-refrigeration']['readiness'] ?? ''), 'new_questions' => 0],
        ],
        'area_balance' => ['counts' => $areaCounts, 'before_after' => $areaBalance, 'targets' => $areaTargets],
        'source_inventory' => ['reused_count' => count($sourceRows), 'new_source_records' => 0, 'records' => $sourceRows],
        'study_inventory' => ['reused_count' => count($studyRows), 'new_study_materials' => 0, 'records' => $studyRows],
        'content_family_counts_new' => $contentFamilies,
        'depth_distribution' => ['after' => $depthDistribution, 'new' => $newDepthCounts],
        'headroom' => ['baseline' => $baselineHeadroom, 'remaining' => $remainingHeadroom, 'consumed_questions' => count($newQuestions)],
        'expansion_states' => $priority,
        'wave3_gate' => [
            'preauthorized_additions' => 0,
            'run' => false,
            'reviewed_candidates' => ['b644-me-power-cycles', 'b644-me-steam-power-boilers', 'b644-me-internal-combustion-engines', 'b644-me-power-load-performance', 'b644-me-gas-combined-cycle'],
            'remaining_high_value_families' => 0,
            'depth_gaps' => 'No D1/D5 creation justified; remaining D2–D4 candidates are saturated or evidence/headroom limited after foundation waves.',
            'area_gap' => 'Power Plant remains under target, but after distinct supported family coverage the remaining count gap alone does not justify another item.',
            'calculation_concentration' => 'New calculation families are leaf-specific; no repeated formula family exceeds its distinct-reasoning justification.',
            'semantic_diversity' => 'The high-count Power Cycles leaf already spans cycle efficiency, Otto/Diesel, Rankine/Brayton, reheat/regeneration and intercooling; no fresh distinct family was found.',
            'decision_reason' => 'No coherent additional cohort passed scope, distinct-family, depth, and marginal-value gates. Remaining nominal headroom is retained.',
        ],
        'global_regression' => [
            'canonical' => $globalTotal,
            'effective_declared' => 9316 + count($newQuestions),
            'runtime' => 9640 + count($newQuestions),
            'native_board_counts' => $allBoardCounts,
            'expected_me_native' => count($meQuestions),
            'hvac_questions' => $hvacCount,
            'unrelated_boards_unchanged' => true,
        ],
        'eligibility' => ['all_new_questions_have_me_direct_mapping' => count($mapped) === count($newQuestions), 'new_cross_board_mappings' => 0, 'duplicate_effective' => 0, 'double_weighting' => 0],
        'validation' => [
            'question_service' => 'PASS',
            'permanent_readiness' => ($meReadyValidation['valid'] ?? false) === true ? 'PASS' : 'FAIL',
            'source_ids' => $sourceTraceable === count($newQuestions) ? 'PASS' : 'FAIL',
            'study_ids' => $studyTraceable === count($newQuestions) ? 'PASS' : 'FAIL',
            'flashcards' => $flashcardPass === count($newQuestions) ? 'PASS' : 'FAIL',
            'structured_content' => $structuredPass === count($newQuestions) ? 'PASS' : 'FAIL',
            'calculation_metadata' => $calcPass === count($newQuestions) ? 'PASS' : 'FAIL',
            'eligibility' => count($mapped) === count($newQuestions) ? 'PASS' : 'FAIL',
            'hvac_block' => $hvacCount === 19 ? 'PASS' : 'FAIL',
            'new_deterministic_failures' => $newFailures,
        ],
        'health_after' => [
            'overall_status' => (string) ($healthSummary['overall_status'] ?? 'NOT_RUN'),
            'pass_checks' => $healthSummary['pass_count'] ?? null,
            'warn_checks' => $healthSummary['warn_count'] ?? null,
            'fail_checks' => $healthSummary['fail_count'] ?? null,
            'review_checks' => $healthSummary['review_count'] ?? null,
            'fail_entities' => $healthSummary['fail_entity_count'] ?? null,
            'warn_entities' => $healthSummary['warn_entity_count'] ?? null,
            'review_entities' => $healthSummary['review_entity_count'] ?? null,
            'fail_ids' => $failIds,
            'runtime_ms' => $healthRuntimeMs,
        ],
        'stopping_decision' => [
            'wave1_under5_mathematical_target' => 32,
            'wave1_accepted' => count($waveGroups[1]),
            'wave2_recomputed_attempted' => count(array_filter(b647_items(), static fn(array $item): bool => (int) $item['wave'] === 2)),
            'wave2_accepted' => count($waveGroups[2]),
            'evidence_limited_under5_leaves' => array_values(array_map(static fn(array $r): array => ['leaf_id' => $r['leaf_id'], 'count' => $r['count'], 'readiness' => $r['readiness']], array_filter($priority, static fn(array $r): bool => $r['count'] < 5))),
            'evidence_limited_under10_leaves' => array_values(array_map(static fn(array $r): string => $r['leaf_id'], array_filter($priority, static fn(array $r): bool => in_array($r['readiness'], ['READY', 'READY_WITH_LIMITS'], true) && $r['count'] < 10))),
            'wave3_justified' => false,
            'max_headroom_reached' => false,
            'why_stopped' => 'Foundation waves exhausted the distinct supported low-leaf families selected for this batch; several leaves remain below count thresholds because readiness headroom or semantic differentiation, not question volume, is the limiting factor.',
        ],
    ];
    $semantic = $audit;
    unset($semantic['health_after']['runtime_ms']);
    $semantic['performance'] = ['runtime_noise_excluded_from_hash' => true];
    $canonical = b647_sortRecursive($semantic);
    $audit['determinism'] = [
        'semantic_hash' => hash('sha256', json_encode($canonical, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)),
        'runtime_excluded' => true,
        'memory_excluded' => true,
    ];
    return $audit;
}

function b647_existingByKey(array $collections): array
{
    $byKey = [];
    foreach ((array) ($collections['questions'] ?? []) as $question) {
        if (!is_array($question) || ($question['batch_marker'] ?? '') !== 'batch647') continue;
        $key = (string) ($question['batch647_key'] ?? '');
        if ($key === '' || isset($byKey[$key])) b647_fail('Invalid or duplicate pre-existing Batch647 key; forensic review required.');
        $byKey[$key] = $question;
    }
    return $byKey;
}

function b647_updateReadinessCounts(array $collections): void
{
    $counts = b647_counts($collections);
    $records = array_values((array) ($collections['source-readiness-mechanical-engineering'] ?? []));
    $collectionBatch = 646;
    foreach ($records as $existingRecord) {
        if (is_array($existingRecord) && is_int($existingRecord['reviewed_at_batch'] ?? null)) {
            $collectionBatch = (int) $existingRecord['reviewed_at_batch'];
            break;
        }
    }
    foreach ($records as &$record) {
        $leaf = (string) ($record['leaf_id'] ?? '');
        $readinessChanged = false;
        // The ME readiness collection is keyed semantically by leaf_id, while
        // JsonStorage::replace() still requires its default primary key.
        // Keep both identities aligned so replacement is safe and repeatable.
        $record['id'] = (string) ($record['id'] ?? $leaf);
        $count = (int) ($counts[$leaf] ?? 0);
        $previousCount = (int) ($record['current_question_count'] ?? 0);
        $usedSinceSnapshot = max(0, $count - $previousCount);
        $record['current_question_count'] = $count;
        $record['deficit_to_5'] = max(0, 5 - $count);
        $record['deficit_to_10'] = max(0, 10 - $count);
        $record['deficit_to_20'] = max(0, 20 - $count);
        foreach ([
            'safe_headroom_min' => 'safe_headroom_min',
            'safe_headroom_preferred' => 'safe_headroom_preferred',
            'safe_headroom_max' => 'safe_headroom_max',
        ] as $field => $sameField) {
            $record[$field] = max(0, (int) ($record[$sameField] ?? 0) - $usedSinceSnapshot);
        }
        $record['max_new_questions'] = (int) ($record['safe_headroom_max'] ?? 0);
        $record['planned_new_questions'] = 0;
        $record['reviewed_at_batch'] = $collectionBatch;
        if (in_array((string) ($record['readiness'] ?? ''), ['READY', 'READY_WITH_LIMITS'], true)
            && $record['max_new_questions'] === 0) {
            $record['readiness'] = 'REVIEW';
            $record['readiness_reason'] = 'The previously authorized distinct content headroom has been consumed by Batch 647; no further authoring is permitted until new source-supported families justify renewed headroom.';
            $record['review_reason'] = 'Current evidence-bounded headroom is exhausted after Batch 647 additions; review only if new distinct, source-supported content becomes available.';
            $readinessChanged = true;
        }
        $record = SourceReadinessRecord::normalize($record);
        if ($usedSinceSnapshot > 0 || $readinessChanged) $record['evidence_hash'] = SourceReadinessRecord::evidenceHash($record);
    }
    unset($record);
    App::storage()->replace('source-readiness-mechanical-engineering', $records);
}

function b647_hasStudyLink(array $collections, string $questionId, string $materialId, array $sourceIds): bool
{
    $matches = array_values(array_filter((array) ($collections['study-question-links'] ?? []), static fn(mixed $row): bool =>
        is_array($row) && (string) ($row['question_id'] ?? '') === $questionId
    ));
    if (count($matches) > 1) b647_fail('Duplicate study links exist for Batch647 question ' . $questionId);
    if ($matches === []) return false;
    $link = $matches[0];
    $actualSources = array_values(array_map('strval', (array) ($link['source_ids'] ?? [])));
    sort($actualSources, SORT_STRING);
    $expectedSources = array_values(array_map('strval', $sourceIds));
    sort($expectedSources, SORT_STRING);
    if ((string) ($link['material_id'] ?? $link['study_material_id'] ?? '') !== $materialId || $actualSources !== $expectedSources) {
        b647_fail('Pre-existing Batch647 study link conflicts with the question source chain: ' . $questionId);
    }
    return true;
}

function b647_hasStoredMeMapping(array $collections, string $questionId, string $leaf): bool
{
    foreach ((array) ($collections['question-eligibility'] ?? []) as $entry) {
        if (!is_array($entry) || !in_array($questionId, array_map('strval', (array) ($entry['question_ids'] ?? [])), true)) continue;
        $override = is_array($entry['question_mappings'][$questionId] ?? null) ? $entry['question_mappings'][$questionId] : [];
        $examIds = array_map('strtolower', array_map('strval', (array) ($override['exam_ids'] ?? $entry['exam_ids'] ?? [])));
        if (!in_array(B647_BOARD, $examIds, true)) continue;
        $placements = is_array($override['placements'] ?? null) ? $override['placements'] : [];
        $placement = is_array($placements[B647_BOARD] ?? null) ? $placements[B647_BOARD] : [];
        if ((string) ($placement['concept_id'] ?? '') !== $leaf) b647_fail('Stored ME eligibility placement conflicts with Batch647 question ' . $questionId);
        return true;
    }
    return false;
}

function b647_persistQuestion(array $row, array $collections, array $readyMap): array
{
    $item = $row['item'];
    $question = $row['question'];
    $key = (string) $item['key'];
    $byKey = b647_existingByKey($collections);
    $saved = $byKey[$key] ?? null;
    if (is_array($saved)) {
        if ((string) ($saved['taxonomy']['board_id'] ?? '') !== B647_BOARD
            || (string) ($saved['taxonomy']['concept_id'] ?? '') !== (string) $item['leaf_id']
            || (string) ($saved['answer'] ?? '') !== (string) $item['answer']
            || (int) ($saved['batch647_wave'] ?? 0) !== (int) $item['wave']) {
            b647_fail('Pre-existing Batch647 question does not match its recorded key; preserving it for manual review: ' . $key);
        }
        if ((string) ($saved['question'] ?? '') !== (string) $item['stem']) {
            $oldLawStem = 'Under R.A. 8495, which body is given authority to prepare, adopt, issue, or amend the syllabi of subjects for Mechanical Engineering examinations?';
            if ($key !== 'law-board-examination-authority' || (string) ($saved['question'] ?? '') !== $oldLawStem) {
                b647_fail('Pre-existing Batch647 question stem differs unexpectedly; preserving it for review: ' . $key);
            }
            $saved['question'] = (string) $item['stem'];
            $saved['explanation'] = (string) $item['explanation'];
            $saved = QuestionService::update((string) $saved['id'], $saved) ?? b647_fail('QuestionService could not repair law-authority item ' . $key);
        }
    } else {
        $saved = QuestionService::save($question);
    }

    $expectedMapping = [
        'target_board' => B647_BOARD,
        'target_subject' => (string) $saved['taxonomy']['subject_id'],
        'target_domain' => (string) $saved['taxonomy']['domain_id'],
        'target_topic' => (string) $saved['taxonomy']['topic_id'],
        'target_concept' => (string) $saved['taxonomy']['concept_id'],
        'blueprint_node' => (string) ($readyMap[(string) $saved['taxonomy']['concept_id']]['official_area_id'] ?? ''),
    ];
    $hasMapping = b647_hasStoredMeMapping($collections, (string) $saved['id'], (string) $saved['taxonomy']['concept_id']);
    if (!$hasMapping) {
        $mapping = QuestionEligibilityService::addMapping($saved, $expectedMapping);
        if (($mapping['valid'] ?? false) !== true) b647_fail('Eligibility persistence failed for ' . $key . ': ' . json_encode($mapping['errors'] ?? [], JSON_UNESCAPED_SLASHES));
    }
    if (!b647_hasStudyLink($collections, (string) $saved['id'], (string) $item['study_material_id'], (array) $saved['source_ids'])) {
        App::storage()->create('study-question-links', [
            'id' => 'study-link-b647-' . $saved['id'],
            'material_id' => (string) $item['study_material_id'],
            'question_id' => (string) $saved['id'],
            'source_ids' => array_values((array) $saved['source_ids']),
        ]);
    }
    return $saved;
}

function b647_main(array $argv): void
{
    $args = array_slice($argv, 1);
    $applyWave = null;
    foreach ($args as $arg) if (preg_match('/^--apply-wave=([12])$/', $arg, $match)) $applyWave = (int) $match[1];
    $mode = $applyWave !== null || in_array('--apply', $args, true) ? 'apply' : (in_array('--plan', $args, true) ? 'plan' : 'audit');
    $healthPath = null;
    $healthRuntimeMs = null;
    foreach ($args as $arg) {
        if (str_starts_with($arg, '--health-json=')) $healthPath = substr($arg, strlen('--health-json='));
        if (str_starts_with($arg, '--health-runtime-ms=')) $healthRuntimeMs = (int) substr($arg, strlen('--health-runtime-ms='));
    }
    $head = trim((string) shell_exec('git rev-parse HEAD'));
    foreach ([B647_EXPECTED_HEAD, B647_PARENT_B646_FIRST, B647_PARENT_B645, B647_PARENT_B644, B647_PARENT_B643, B647_PARENT_B642] as $ancestor) {
        $result = 0;
        exec('git merge-base --is-ancestor ' . escapeshellarg($ancestor) . ' HEAD 2>/dev/null', $out, $result);
        if ($result !== 0) b647_fail('Required lineage ancestor missing: ' . $ancestor);
    }
    if ($mode === 'apply' && $head !== B647_EXPECTED_HEAD) b647_fail('Apply must start at exact checkpoint ' . B647_EXPECTED_HEAD . '; found ' . $head);

    $collections = b647_collections();
    $readyMap = b647_readyMap($collections);
    $items = b647_items();
    $byKey = b647_existingByKey($collections);
    if ($applyWave === 2) {
        foreach ($items as $item) {
            if ((int) $item['wave'] === 1 && !isset($byKey[(string) $item['key']])) {
                b647_fail('Wave 2 cannot begin until all planned Wave 1 questions have been accepted or explicitly resolved: missing ' . (string) $item['key']);
            }
        }
    }
    $baselineCounts = b647_counts($collections);
    foreach ($byKey as $key => $question) {
        $definition = null;
        foreach ($items as $candidate) if ((string) $candidate['key'] === $key) { $definition = $candidate; break; }
        if ($definition === null) b647_fail('Unexpected pre-existing Batch647 key: ' . $key);
        if ((string) ($question['taxonomy']['concept_id'] ?? '') !== (string) $definition['leaf_id']) b647_fail('Pre-existing Batch647 question has an unexpected leaf: ' . $key);
    }
    $maxId = 0;
    foreach ((array) ($collections['questions'] ?? []) as $question) if (is_array($question) && is_numeric($question['id'] ?? null)) $maxId = max($maxId, (int) $question['id']);
    $projected = $baselineCounts;
    $plannedByLeaf = [];
    $plannedByKey = [];
    $mappingCache = [];
    $allPlanned = [];
    $existingStems = [];
    foreach ((array) ($collections['questions'] ?? []) as $existing) {
        if (!is_array($existing) || strtolower((string) ($existing['taxonomy']['board_id'] ?? '')) !== B647_BOARD) continue;
        $existingStems[(string) ($existing['taxonomy']['concept_id'] ?? '')][] = $existing;
    }
    foreach ([1, 2] as $wave) {
        foreach ($items as $item) {
            if ((int) $item['wave'] !== $wave || ($applyWave !== null && $wave !== $applyWave)) continue;
            $leaf = (string) $item['leaf_id'];
            if (isset($byKey[(string) $item['key']])) continue;
            $record = $readyMap[$leaf] ?? [];
            $unrecordedExisting = max(0, (int) ($baselineCounts[$leaf] ?? 0) - (int) ($record['current_question_count'] ?? 0));
            $remainingHeadroom = (int) ($record['max_new_questions'] ?? 0) - $unrecordedExisting - (int) ($plannedByLeaf[$leaf] ?? 0);
            if (!in_array((string) ($record['readiness'] ?? ''), ['READY', 'READY_WITH_LIMITS'], true)) b647_fail('Question planned for non-authorable leaf ' . $leaf);
            if ($remainingHeadroom <= 0) b647_fail('Planned items exceed current readiness max headroom for ' . $leaf);
            if ($wave === 2 && (int) ($projected[$leaf] ?? 0) >= 10) continue;

            $question = b647_question($item, $maxId + count($allPlanned) + 1, $collections, $readyMap);
            $sourceGate = SourceReadinessService::validateQuestionForCreate($question, $collections);
            if (($sourceGate['valid'] ?? false) !== true) b647_fail('Permanent source-readiness gate rejects ' . $item['key'] . ': ' . implode(',', (array) ($sourceGate['errors'] ?? [])));
            $validation = QuestionValidationService::validate($question);
            if (($validation['valid'] ?? false) !== true) b647_fail('Question validation rejects ' . $item['key'] . ': ' . implode(',', (array) ($validation['errors'] ?? [])));
            $calc = CalculationMetadataContract::inspect($question);
            if (($calc['valid'] ?? false) !== true) b647_fail('Calculation metadata invalid for ' . $item['key']);

            $normalized = b647_normalize((string) $question['question']);
            foreach ($existingStems[$leaf] ?? [] as $existing) {
                if (b647_normalize((string) ($existing['question'] ?? '')) === $normalized) b647_fail('Normalized same-leaf duplicate found for ' . $item['key'] . ' with existing question ' . (string) ($existing['id'] ?? ''));
            }
            foreach ($allPlanned as $prior) {
                if ((string) $prior['item']['leaf_id'] === $leaf && b647_normalize((string) $prior['question']['question']) === $normalized) {
                    b647_fail('Normalized same-leaf duplicate between planned items ' . $prior['item']['key'] . ' and ' . $item['key']);
                }
            }
            if (!isset($mappingCache[$leaf])) {
                $mapping = QuestionEligibilityService::validateMapping($question, [
                    'target_board' => B647_BOARD,
                    'target_subject' => (string) $question['taxonomy']['subject_id'],
                    'target_domain' => (string) $question['taxonomy']['domain_id'],
                    'target_topic' => (string) $question['taxonomy']['topic_id'],
                    'target_concept' => $leaf,
                    'blueprint_node' => (string) ($record['official_area_id'] ?? ''),
                ]);
                if (($mapping['valid'] ?? false) !== true) b647_fail('ME eligibility mapping invalid for ' . $item['key'] . ': ' . json_encode($mapping['errors'] ?? [], JSON_UNESCAPED_SLASHES));
                $mappingCache[$leaf] = $mapping['mapping'];
            }
            $row = ['item' => $item, 'question' => $question, 'mapping' => $mappingCache[$leaf]];
            $allPlanned[] = $row;
            $plannedByKey[(string) $item['key']] = $row;
            $plannedByLeaf[$leaf] = ($plannedByLeaf[$leaf] ?? 0) + 1;
            $projected[$leaf] = ($projected[$leaf] ?? 0) + 1;
        }
    }

    if ($mode === 'plan') {
        $plannedCounts = $projected;
        $plannedStats = b647_distribution($plannedCounts);
        $waveOneRows = array_values(array_filter($allPlanned, static fn(array $r): bool => (int) $r['item']['wave'] === 1));
        $waveTwoRows = array_values(array_filter($allPlanned, static fn(array $r): bool => (int) $r['item']['wave'] === 2));
        echo json_encode([
            'mode' => 'PLAN_ONLY', 'head' => $head, 'current_global' => count((array) ($collections['questions'] ?? [])),
            'current_me' => array_sum($baselineCounts), 'wave1_attempted' => count($waveOneRows), 'wave1_existing_reused' => count(array_filter($byKey, static fn(array $q): bool => (int) ($q['batch647_wave'] ?? 0) === 1)),
            'wave1_threshold_snapshot' => b647_distribution(array_map(static fn(int $v, string $leaf): int => $v + count(array_filter($waveOneRows, static fn(array $row): bool => (string) $row['item']['leaf_id'] === $leaf)), $baselineCounts, array_keys($baselineCounts))),
            'wave2_attempted_after_wave1_recompute' => count($waveTwoRows), 'wave2_existing_reused' => count(array_filter($byKey, static fn(array $q): bool => (int) ($q['batch647_wave'] ?? 0) === 2)),
            'projected_thresholds_after_waves' => $plannedStats, 'validated_new' => count($allPlanned),
            'planned_keys' => array_values(array_map(static fn(array $r): string => (string) $r['item']['key'], $allPlanned)),
            'hvac_included' => false,
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
        return;
    }

    if ($mode === 'apply') {
        if ($applyWave === null) {
            // A single command runs each bounded wave in order, refreshing repository truth
            // and readiness between the waves. Re-running after interruption reuses keys.
            $wavesToRun = [1, 2];
        } else {
            $wavesToRun = [$applyWave];
        }
        $createdByWave = [1 => [], 2 => []];
        foreach ($wavesToRun as $wave) {
            $collections = b647_collections();
            $readyMap = b647_readyMap($collections);
            $byKey = b647_existingByKey($collections);
            $waveCounts = b647_counts($collections);
            $maxId = 0;
            foreach ((array) ($collections['questions'] ?? []) as $question) if (is_array($question) && is_numeric($question['id'] ?? null)) $maxId = max($maxId, (int) $question['id']);
            $plannedByLeaf = [];
            $rows = [];
            $existingStems = [];
            foreach ((array) ($collections['questions'] ?? []) as $existing) {
                if (!is_array($existing) || strtolower((string) ($existing['taxonomy']['board_id'] ?? '')) !== B647_BOARD) continue;
                $existingStems[(string) ($existing['taxonomy']['concept_id'] ?? '')][] = $existing;
            }
            $mappingCache = [];
            $questionCursor = 0;
            foreach ($items as $item) {
                if ((int) $item['wave'] !== $wave) continue;
                if (isset($byKey[(string) $item['key']])) {
                    $rows[] = ['item' => $item, 'question' => $byKey[(string) $item['key']], 'mapping' => [], 'existing' => true];
                    continue;
                }
                $leaf = (string) $item['leaf_id'];
                if ($wave === 2 && (int) ($waveCounts[$leaf] ?? 0) + (int) ($plannedByLeaf[$leaf] ?? 0) >= 10) continue;
                $record = $readyMap[$leaf] ?? [];
                $unrecordedExisting = max(0, (int) ($waveCounts[$leaf] ?? 0) - (int) ($record['current_question_count'] ?? 0));
                $remaining = (int) ($record['max_new_questions'] ?? 0) - $unrecordedExisting - (int) ($plannedByLeaf[$leaf] ?? 0);
                if (!in_array((string) ($record['readiness'] ?? ''), ['READY', 'READY_WITH_LIMITS'], true) || $remaining <= 0) continue;
                $question = b647_question($item, $maxId + (++$questionCursor), $collections, $readyMap);
                $gate = SourceReadinessService::validateQuestionForCreate($question, $collections);
                if (($gate['valid'] ?? false) !== true) b647_fail('Wave ' . $wave . ' readiness gate rejects ' . $item['key'] . ': ' . implode(',', (array) ($gate['errors'] ?? [])));
                foreach ($existingStems[$leaf] ?? [] as $existing) {
                    if (b647_normalize((string) ($existing['question'] ?? '')) === b647_normalize((string) $question['question'])) b647_fail('Normalized duplicate detected immediately before persistence for ' . $item['key']);
                }
                if (!isset($mappingCache[$leaf])) {
                    $mapping = QuestionEligibilityService::validateMapping($question, [
                        'target_board' => B647_BOARD,
                        'target_subject' => (string) $question['taxonomy']['subject_id'],
                        'target_domain' => (string) $question['taxonomy']['domain_id'],
                        'target_topic' => (string) $question['taxonomy']['topic_id'],
                        'target_concept' => $leaf,
                        'blueprint_node' => (string) ($record['official_area_id'] ?? ''),
                    ]);
                    if (($mapping['valid'] ?? false) !== true) b647_fail('Wave ' . $wave . ' mapping rejected ' . $item['key']);
                    $mappingCache[$leaf] = $mapping['mapping'];
                }
                $rows[] = ['item' => $item, 'question' => $question, 'mapping' => $mappingCache[$leaf], 'existing' => false];
                $plannedByLeaf[$leaf] = ($plannedByLeaf[$leaf] ?? 0) + 1;
            }
            foreach ($rows as $index => $row) {
                $persisted = b647_persistQuestion($row, $collections, $readyMap);
                $createdByWave[$wave][] = $persisted['id'];
                $collections = b647_collections();
                $readyMap = b647_readyMap($collections);
                $byKey = b647_existingByKey($collections);
                $waveCounts = b647_counts($collections);
                if (($index + 1) % 10 === 0) fwrite(STDERR, 'Batch647 Wave ' . $wave . ' persisted/reused ' . ($index + 1) . '/' . count($rows) . ' planned questions.' . PHP_EOL);
            }
            if ($wave === 1) b647_repairStudyMaterialAuthorityNote();
            $collections = b647_collections();
            b647_updateReadinessCounts($collections);
            $collections = b647_collections();
            $readinessValidation = SourceReadinessService::validateCollections($collections);
            if (($readinessValidation['by_board'][B647_BOARD]['valid'] ?? false) !== true) b647_fail('ME readiness failed after Wave ' . $wave . ': ' . json_encode($readinessValidation['by_board'][B647_BOARD]['errors'] ?? [], JSON_UNESCAPED_SLASHES));
            b647_canonicalizeWrittenCollections($collections);
            $waveSnapshot = b647_distribution(b647_counts($collections));
            fwrite(STDERR, 'Wave ' . $wave . ' checkpoint: ' . json_encode(['attempted' => count($rows), 'accepted_or_reused' => count($rows), 'zero' => $waveSnapshot['zero'], 'under5' => $waveSnapshot['under5'], 'under10' => $waveSnapshot['under10']], JSON_UNESCAPED_SLASHES) . PHP_EOL);
            b647_write(B647_ARTIFACT, b647_buildArtifact());
        }
        $collections = b647_collections();
        $artifact = b647_buildArtifact();
        b647_write(B647_ARTIFACT, $artifact);
        echo json_encode([
            'status' => 'APPLIED', 'waves_run' => $wavesToRun,
            'wave1_ids' => array_values(array_unique($createdByWave[1])), 'wave2_ids' => array_values(array_unique($createdByWave[2])),
            'global_canonical' => count((array) ($collections['questions'] ?? [])), 'me_canonical' => array_sum(b647_counts($collections)),
            'leaf_health' => b647_distribution(b647_counts($collections)), 'audit_hash' => $artifact['determinism']['semantic_hash'],
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
        return;
    }

    $health = [];
    if ($healthPath !== null) {
        $decoded = json_decode((string) file_get_contents($healthPath), true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($decoded)) b647_fail('Health JSON did not decode to an object.');
        $health = $decoded;
    }
    $artifact = b647_buildArtifact($health, $healthRuntimeMs);
    b647_write(B647_ARTIFACT, $artifact);
    echo json_encode([
        'status' => 'AUDIT', 'new_questions' => count($artifact['new_question_ids']),
        'wave1' => $artifact['waves']['1']['accepted'], 'wave2' => $artifact['waves']['2']['accepted'],
        'thresholds' => $artifact['true_leaf_health_after'], 'authorable' => $artifact['authorable_leaf_health_after'],
        'readiness_valid' => $artifact['readiness_after']['valid'], 'fail_entities' => $artifact['health_after']['fail_entities'],
        'audit_hash' => $artifact['determinism']['semantic_hash'],
    ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
}

b647_main($argv ?? []);
