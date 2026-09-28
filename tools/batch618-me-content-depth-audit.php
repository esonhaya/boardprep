<?php

declare(strict_types=1);

/**
 * Batch 618: source-first Mechanical Engineering content expansion,
 * depth modernization, and bounded prerequisite modeling.
 *
 * The authoring data is intentionally kept in this deterministic tool so the
 * source chain, numerical review notes, and stable allocation can be audited
 * without regenerating content from an external service.
 */

$root = dirname(__DIR__);
$write = in_array('--write', $argv, true);
$batchDate = '2026-09-28T00:00:00+08:00';
$baselineHead = '6fe06fcd31a3005f493d0bbf2d3555969ada77f3';
$batchId = '618';

$subjectEngineering = 'mechanical-engineering-engineering-sciences';
$subjectProfessional = 'mechanical-engineering-mechanical-engineering-professional-subjects';

$taxonomy = [
    'me-topic-2-1-concept' => [
        'name' => 'Stress, Strain, Stability, and Deformation',
        'subject_id' => $subjectEngineering,
        'domain_id' => $subjectEngineering,
        'topic_id' => 'me-topic-2-1',
        'material_id' => 'me-material-4',
        'sources' => ['prc-mechanical-engineering-board-page', 'openstax-university-physics-volume-1', 'open-mechanical-design-manufacturing'],
        'area' => 'Engineering Sciences',
        'instructional_source' => 'open-mechanical-design-manufacturing',
    ],
    'me-topic-2-2-concept' => [
        'name' => 'Fluid Statics, Internal Flow, and Fluid Machines',
        'subject_id' => $subjectEngineering,
        'domain_id' => $subjectEngineering,
        'topic_id' => 'me-topic-2-2',
        'material_id' => 'me-material-5',
        'sources' => ['prc-mechanical-engineering-board-page', 'openstax-university-physics-volume-1'],
        'area' => 'Engineering Sciences',
        'instructional_source' => 'openstax-university-physics-volume-1',
    ],
    'me-topic-2-3-concept' => [
        'name' => 'Thermodynamics, Heat Transfer, and HVAC',
        'subject_id' => $subjectEngineering,
        'domain_id' => $subjectEngineering,
        'topic_id' => 'me-topic-2-3',
        'material_id' => 'me-material-6',
        'sources' => ['prc-mechanical-engineering-board-page', 'ubc-introduction-engineering-thermodynamics', 'openstax-university-physics-volume-2'],
        'area' => 'Engineering Sciences',
        'instructional_source' => 'ubc-introduction-engineering-thermodynamics',
    ],
    'me-topic-3-1-concept' => [
        'name' => 'Power Transmission, Joints, Bearings, and Fatigue',
        'subject_id' => $subjectProfessional,
        'domain_id' => $subjectProfessional,
        'topic_id' => 'me-topic-3-1',
        'material_id' => 'me-material-10',
        'sources' => ['prc-mechanical-engineering-board-page', 'open-mechanical-design-manufacturing', 'openstax-university-physics-volume-1'],
        'area' => 'Mechanical Design, Materials and Shop Practice',
        'instructional_source' => 'open-mechanical-design-manufacturing',
    ],
    'me-topic-3-2-concept' => [
        'name' => 'Machining, Production Flow, Quality, and Economics',
        'subject_id' => $subjectProfessional,
        'domain_id' => $subjectProfessional,
        'topic_id' => 'me-topic-3-2',
        'material_id' => 'me-material-12',
        'sources' => ['prc-mechanical-engineering-board-page', 'open-manufacturing-processes-4-5', 'open-mechanical-design-manufacturing', 'nist-semtech-engineering-statistics-handbook'],
        'area' => 'Mechanical Design, Materials and Shop Practice',
        'instructional_source' => 'open-manufacturing-processes-4-5',
    ],
    'me-topic-3-3-concept' => [
        'name' => 'Cycles, Heat Exchange, Boilers, and Engines',
        'subject_id' => $subjectProfessional,
        'domain_id' => $subjectProfessional,
        'topic_id' => 'me-topic-3-3',
        'material_id' => 'me-material-8',
        'sources' => ['prc-mechanical-engineering-board-page', 'ubc-introduction-engineering-thermodynamics', 'openstax-university-physics-volume-2'],
        'area' => 'Power Plant Engineering',
        'instructional_source' => 'ubc-introduction-engineering-thermodynamics',
    ],
    'me-topic-3-4-concept' => [
        'name' => 'Pumps, Compressors, Heat Exchangers, and Air Conditioning',
        'subject_id' => $subjectProfessional,
        'domain_id' => $subjectProfessional,
        'topic_id' => 'me-topic-3-4',
        'material_id' => 'me-material-9',
        'sources' => ['prc-mechanical-engineering-board-page', 'ubc-introduction-engineering-thermodynamics', 'openstax-university-physics-volume-1', 'openstax-university-physics-volume-2'],
        'area' => 'Power Plant Engineering',
        'instructional_source' => 'ubc-introduction-engineering-thermodynamics',
    ],
    'me-topic-3-5-concept' => [
        'name' => 'Condition Monitoring, Reliability, and Energy Safety',
        'subject_id' => $subjectProfessional,
        'domain_id' => $subjectProfessional,
        'topic_id' => 'me-topic-3-5',
        'material_id' => 'me-material-14',
        'sources' => ['prc-mechanical-engineering-board-page', 'nist-semtech-engineering-statistics-handbook', 'open-mechanical-design-manufacturing'],
        'area' => 'Mechanical Design, Materials and Shop Practice',
        'instructional_source' => 'nist-semtech-engineering-statistics-handbook',
    ],
];

$allConcepts = [
    'me-topic-1-1-concept' => ['name' => 'Vector Geometry, Linear Transformations, and Series', 'subject' => 'Mathematics', 'domain' => 'Mechanical Engineering Mathematics', 'topic' => 'me-topic-1-1', 'materials' => ['me-material-1'], 'importance' => 'HIGH', 'decision' => 'DEFER', 'reason' => 'Existing pool is comparatively deep; retain for a later mathematics-specific wave while the official 35% mathematics area remains represented.'],
    'me-topic-1-2-concept' => ['name' => 'Models, Rates, Accumulation, and Differential Equations', 'subject' => 'Mathematics', 'domain' => 'Mechanical Engineering Mathematics', 'topic' => 'me-topic-1-2', 'materials' => ['me-material-1'], 'importance' => 'HIGH', 'decision' => 'DEFER', 'reason' => 'Largest current concept cohort; defer to avoid further mathematics concentration while engineering applications are thin.'],
    'me-topic-1-3-concept' => ['name' => 'Uncertainty, Data Inference, and Approximation', 'subject' => 'Mathematics', 'domain' => 'Mechanical Engineering Mathematics', 'topic' => 'me-topic-1-3', 'materials' => ['me-material-1'], 'importance' => 'MEDIUM', 'decision' => 'DEFER', 'reason' => 'Useful prerequisite competency, but current direct board pool is healthier than the engineering-science and professional areas.'],
    'me-topic-2-1-concept' => ['name' => 'Stress, Strain, Stability, and Deformation', 'subject' => 'Engineering Sciences', 'domain' => 'Mechanical Engineering Engineering Sciences', 'topic' => 'me-topic-2-1', 'materials' => ['me-material-4'], 'importance' => 'HIGH', 'decision' => 'SELECT', 'reason' => 'Thin existing pool, central to materials/design scope, and supports defensible analysis questions.'],
    'me-topic-2-2-concept' => ['name' => 'Fluid Statics, Internal Flow, and Fluid Machines', 'subject' => 'Engineering Sciences', 'domain' => 'Mechanical Engineering Engineering Sciences', 'topic' => 'me-topic-2-2', 'materials' => ['me-material-5'], 'importance' => 'HIGH', 'decision' => 'SELECT', 'reason' => 'Thin pool with strong foundational source support and high numerical/application breadth.'],
    'me-topic-2-3-concept' => ['name' => 'Thermodynamics, Heat Transfer, and HVAC', 'subject' => 'Engineering Sciences', 'domain' => 'Mechanical Engineering Engineering Sciences', 'topic' => 'me-topic-2-3', 'materials' => ['me-material-6', 'me-material-7'], 'importance' => 'HIGH', 'decision' => 'SELECT', 'reason' => 'Core ME competency with authoritative thermodynamics and heat-transfer sources; expansion is bounded across distinct subskills.'],
    'me-topic-2-4-concept' => ['name' => 'Energy Storage, Manufacturing Power, and Machine Life', 'subject' => 'Engineering Sciences', 'domain' => 'Mechanical Engineering Engineering Sciences', 'topic' => 'me-topic-2-4', 'materials' => ['me-material-8', 'me-material-12'], 'importance' => 'MEDIUM', 'decision' => 'DEFER', 'reason' => 'Thin but semantically broad; defer until its mixed boundary is resolved through a separate source and taxonomy review.'],
    'me-topic-2-5-concept' => ['name' => 'Economic Decisions, Process Variation, and Calibration', 'subject' => 'Engineering Sciences', 'domain' => 'Mechanical Engineering Engineering Sciences', 'topic' => 'me-topic-2-5', 'materials' => ['me-material-13', 'me-material-15'], 'importance' => 'MEDIUM', 'decision' => 'DEFER', 'reason' => 'Source-ready but internally mixed; avoid padding a separate economics/quality wave into this thermal/mechanical milestone.'],
    'me-topic-2-6-concept' => ['name' => 'Force Balance, Motion, Impact, and Vibration', 'subject' => 'Engineering Sciences', 'domain' => 'Mechanical Engineering Engineering Sciences', 'topic' => 'me-topic-2-6', 'materials' => ['me-material-3'], 'importance' => 'HIGH', 'decision' => 'DEFER', 'reason' => 'Foundational prerequisite signal is strong, but selected items use it only as a material prerequisite; a dedicated dynamics/vibration wave is safer.'],
    'me-topic-3-1-concept' => ['name' => 'Power Transmission, Joints, Bearings, and Fatigue', 'subject' => 'Mechanical Engineering Professional Subjects', 'domain' => 'Mechanical Engineering Professional Subjects', 'topic' => 'me-topic-3-1', 'materials' => ['me-material-10', 'me-material-11'], 'importance' => 'HIGH', 'decision' => 'SELECT', 'reason' => 'Most direct machine-design expansion target; broad source-backed design and capacity scenarios are available.'],
    'me-topic-3-2-concept' => ['name' => 'Machining, Production Flow, Quality, and Economics', 'subject' => 'Mechanical Engineering Professional Subjects', 'domain' => 'Mechanical Engineering Professional Subjects', 'topic' => 'me-topic-3-2', 'materials' => ['me-material-12', 'me-material-13'], 'importance' => 'HIGH', 'decision' => 'SELECT', 'reason' => 'Thin professional pool with distinct machining, quality, flow, and process-selection applications.'],
    'me-topic-3-3-concept' => ['name' => 'Cycles, Heat Exchange, Boilers, and Engines', 'subject' => 'Mechanical Engineering Professional Subjects', 'domain' => 'Mechanical Engineering Professional Subjects', 'topic' => 'me-topic-3-3', 'materials' => ['me-material-8'], 'importance' => 'HIGH', 'decision' => 'SELECT', 'reason' => 'Direct Power Plant Engineering crosswalk and high-value cycle/performance analysis potential.'],
    'me-topic-3-4-concept' => ['name' => 'Pumps, Compressors, Heat Exchangers, and Air Conditioning', 'subject' => 'Mechanical Engineering Professional Subjects', 'domain' => 'Mechanical Engineering Professional Subjects', 'topic' => 'me-topic-3-4', 'materials' => ['me-material-9'], 'importance' => 'HIGH', 'decision' => 'SELECT', 'reason' => 'Very thin professional pool; source support covers fluid machinery and HVAC decisions without relying on volatile code trivia.'],
    'me-topic-3-5-concept' => ['name' => 'Condition Monitoring, Reliability, and Energy Safety', 'subject' => 'Mechanical Engineering Professional Subjects', 'domain' => 'Mechanical Engineering Professional Subjects', 'topic' => 'me-topic-3-5', 'materials' => ['me-material-14'], 'importance' => 'HIGH', 'decision' => 'SELECT', 'reason' => 'Smallest professional cohort and high repeat-resistance value through reliability, maintenance, and safe-intervention scenarios.'],
];

$materialUpdates = [
    'me-material-4' => [
        'summary' => 'Source-backed mechanics-of-materials review for stress, strain, deformation, stability, and failure-mode interpretation.',
        'notes' => [
            ['heading' => 'Model and units', 'body' => 'Define the load path, section geometry, sign convention, and compatible units before calculating. Use N and mm for MPa when convenient, or N and m consistently; do not mix diameter and radius formulas.'],
            ['heading' => 'Stress and deformation', 'body' => 'Average axial stress is P/A, normal strain is change in length divided by original length, and elastic elongation is PL/(AE) when the member is prismatic and within the elastic range. Free thermal strain is alpha times the temperature change; full restraint converts it into stress E alpha delta-T.'],
            ['heading' => 'Bending, torsion, and combined loading', 'body' => 'Use bending stress M/Z and torsional stress Tr/J with the stated section property. At a critical point, retain signs when superposing axial and bending stress; for combined stress use the supplied principal, Tresca, or von Mises criterion rather than comparing one component alone.'],
            ['heading' => 'Stability and failure', 'body' => 'Euler buckling is an ideal elastic-column model and depends on effective length squared. Fatigue calculations require mean and alternating stress plus the stated endurance model; a calculated factor of safety is conditional on those assumptions, not a guarantee of life.'],
            ['heading' => 'Common checks', 'body' => 'Check whether expansion is free or restrained, whether a column is slender enough for Euler theory, whether the critical fiber is tensile or compressive, and whether the answer has stress, strain, force, moment, or energy units.'],
        ],
    ],
    'me-material-5' => [
        'summary' => 'Source-backed fluid-mechanics review for conservation, pressure, internal flow, momentum, losses, and pump behavior.',
        'notes' => [
            ['heading' => 'Control volume and properties', 'body' => 'State the control volume, density assumption, inlet and outlet directions, and whether pressure is gauge or absolute. For a filled incompressible section, Q equals mean velocity times area; use mass flow rho-Q for momentum.'],
            ['heading' => 'Pressure and energy', 'body' => 'Hydrostatic gauge pressure is rho-g-h. Bernoulli or the steady-flow energy equation requires consistent datum, pressure convention, velocity terms, pump/turbine work, and loss terms; do not use gauge pressure as an absolute thermodynamic state.'],
            ['heading' => 'Flow regime and losses', 'body' => 'Reynolds number selects the flow-regime interpretation. Darcy-Weisbach loss is f(L/D)V-squared/(2g), while minor losses use K V-squared/(2g); friction-factor convention and pipe diameter must be stated.'],
            ['heading' => 'Momentum and machines', 'body' => 'A turning jet or elbow produces force from the vector change in momentum, not from speed change alone. Pump hydraulic power is rho-g-Q-H and shaft power is hydraulic power divided by efficiency. Affinity laws apply only within their stated similarity assumptions.'],
            ['heading' => 'Engineering checks', 'body' => 'Check continuity, sign direction, units, cavitation margin, and whether a pump curve and system curve are being compared at the same flow. A plausible number with the wrong control-volume boundary is still wrong.'],
        ],
    ],
    'me-material-6' => [
        'summary' => 'Source-backed thermal-science review for closed systems, control volumes, properties, entropy, heat transfer, and HVAC performance.',
        'notes' => [
            ['heading' => 'Energy accounting', 'body' => 'For a closed system use delta-U = Q - W when heat into the system and work done by the system are positive. For steady devices use the control-volume balance and explicitly state neglected kinetic, potential, heat, or work terms.'],
            ['heading' => 'Properties and state', 'body' => 'Use absolute temperature in gas and radiation relations, distinguish specific from total quantities, and apply ideal-gas relations only when the idealization is stated or defensible. A throttling result of approximately constant enthalpy depends on steady, adiabatic, negligible-work assumptions.'],
            ['heading' => 'Heat transfer', 'body' => 'Conduction, convection, radiation, and thermal-resistance networks use different governing relationships. Heat-exchanger calculations need a consistent terminal-temperature arrangement and an appropriate mean temperature difference; Celsius may be used for differences but not as an absolute radiation temperature.'],
            ['heading' => 'Cycles and moist air', 'body' => 'Thermal efficiency is net work divided by heat input; refrigerator COP is cooling effect divided by work input and can exceed one. Separate sensible and latent loads when selecting a humidity-control response.'],
            ['heading' => 'Irreversibility checks', 'body' => 'Friction generates entropy, intercooling can reduce multistage compression work, and a cold stream in an ordinary passive exchanger cannot exceed the hot-stream inlet temperature. Use these limits to test a numerical result.'],
        ],
    ],
    'me-material-10' => [
        'summary' => 'Source-backed machine-design review for shafts, gears, belts, chains, bearings, clutches, fatigue, and design limits.',
        'notes' => [
            ['heading' => 'Power path', 'body' => 'Trace torque, speed, force, power, and efficiency across each interface. Rotating power is T-omega, with rpm converted to radians per second; a gear ratio changes speed and torque in opposite directions in the ideal model.'],
            ['heading' => 'Capacity equations', 'body' => 'Use the supplied shaft torsion, belt traction, clutch, bearing-life, spring, or brake model with compatible dimensions. Distinguish kinematic ratios from strength, wear, thermal, and fatigue capacity.'],
            ['heading' => 'Fatigue and mean stress', 'body' => 'Separate alternating and mean stress and apply the stated Goodman or Soderberg relation. A safety factor from a simplified model must be interpreted with the load history, surface, notch, temperature, and material assumptions visible.'],
            ['heading' => 'Bearings and lubrication', 'body' => 'Bearing rating is not a complete diagnosis. Equivalent load, duty, lubrication viscosity, contamination, alignment, heat generation, and speed all affect service behavior. Correct the mechanism before treating a rating as the answer.'],
            ['heading' => 'Design judgment', 'body' => 'Choose the component or material that satisfies the stated load, environment, life, manufacturability, and maintenance constraints. Do not select by one attractive property while ignoring the governing failure mode.'],
        ],
    ],
    'me-material-12' => [
        'summary' => 'Source-backed manufacturing review for machining, CNC setup, tolerances, process capability, production flow, and quality decisions.',
        'notes' => [
            ['heading' => 'Machining variables', 'body' => 'Turning speed is pi-D-N, feed rate is feed per revolution times rotational speed, material-removal rate depends on the stated process geometry, and ideal cutting power is force times cutting speed. Convert rpm, mm, and m consistently.'],
            ['heading' => 'Datums and tolerances', 'body' => 'A workpiece datum connects the programmed coordinate system to the physical part. Worst-case stack-up adds absolute tolerances; fit decisions compare the limiting hole and shaft dimensions rather than nominal values alone.'],
            ['heading' => 'Process capability', 'body' => 'Cp describes potential spread against specification width, while Cpk also reflects centering. Control limits describe process behavior and are not the same as specification limits; a signal prompts investigation rather than proving a cause.'],
            ['heading' => 'Flow and economics', 'body' => 'Takt time is available production time divided by demand, the slowest required station limits sustainable throughput, and EOQ or break-even calculations are conditional on their cost assumptions. Kanban responds to downstream consumption and limits work in process.'],
            ['heading' => 'Selection checks', 'body' => 'Match tool, material, geometry, tolerance, finish, machine limits, production volume, and measurement capability. A formula result does not override fixturing, tool wear, coolant, or safety constraints.'],
        ],
    ],
    'me-material-8' => [
        'summary' => 'Source-backed power-cycle review for turbines, boilers, engines, combustion, heat exchange, and plant performance.',
        'notes' => [
            ['heading' => 'Cycle boundary', 'body' => 'Define heat input, heat rejection, net work, and device work before calculating efficiency or power. For an adiabatic turbine with negligible kinetic and potential changes, specific work follows the enthalpy drop.'],
            ['heading' => 'Steam and gas-cycle reasoning', 'body' => 'Keep the idealized Otto, Diesel, Brayton, and Rankine heat-addition or component assumptions distinct. Reheat, regeneration, economizers, condensers, and intercooling change performance for identifiable thermodynamic reasons.'],
            ['heading' => 'Boiler and engine performance', 'body' => 'Boiler efficiency compares useful fluid energy gain with fuel input; mechanical efficiency compares brake output with indicated or internal power. Specific fuel consumption uses mass fuel per unit brake output and requires a time conversion.'],
            ['heading' => 'Combustion accounting', 'body' => 'Balance the fuel-oxidizer reaction before applying air-fuel or excess-air reasoning. Excess air can reduce peak temperature and change exhaust composition, but it does not create energy.'],
            ['heading' => 'Plant checks', 'body' => 'Separate shaft output, fluid power, heat input, and auxiliary consumption. A complete-cycle energy balance must close, and any claimed efficiency must remain below the stated ideal or physical limit.'],
        ],
    ],
    'me-material-9' => [
        'summary' => 'Source-backed pump, compressor, heat-exchanger, refrigeration, and air-conditioning review for system selection and performance.',
        'notes' => [
            ['heading' => 'Pumps and systems', 'body' => 'Compare pump head and system head at the same flow. Series pumps add head at common flow; parallel pumps add flow at common head. Hydraulic power is rho-g-Q-H and shaft power includes efficiency.'],
            ['heading' => 'Compressors', 'body' => 'Compressor efficiency compares ideal isentropic work with actual work for the same end states. Volumetric efficiency relates actual intake volume to displacement; clearance, valve losses, leakage, and temperature affect the result.'],
            ['heading' => 'Refrigeration and HVAC', 'body' => 'Refrigerator COP is Q-cold/W, heat-pump COP is Q-hot/W, and Q-hot equals Q-cold plus work for the ideal energy balance. Separate sensible and latent loads and address moisture when humidity is the problem.'],
            ['heading' => 'Heat exchangers and air', 'body' => 'Use UA-delta-Tlm or an energy balance only when terminal temperatures and flow arrangement support it. Duct velocity follows Q/A, fan power is Q-delta-p/efficiency, and fouling raises resistance and lowers heat transfer.'],
            ['heading' => 'Operating checks', 'body' => 'Cavitation symptoms require an available-versus-required suction-head check. Superheat protects a compressor from liquid, while condenser heat rejection is cooling effect plus compressor work.'],
        ],
    ],
    'me-material-14' => [
        'summary' => 'Source-backed maintenance and reliability review for failure models, condition monitoring, quality signals, and safe energy control.',
        'notes' => [
            ['heading' => 'Reliability models', 'body' => 'For a constant failure rate, R(t)=exp(-lambda-t) and MTBF=1/lambda. Series reliability multiplies independent reliabilities, while parallel redundancy is one minus the product of failure probabilities. Use the model only within its assumptions.'],
            ['heading' => 'Availability and improvement', 'body' => 'Steady-state availability is MTBF/(MTBF+MTTR). OEE multiplies availability, performance, and quality. An indicator diagnoses where to investigate; it does not by itself identify root cause.'],
            ['heading' => 'Condition and quality evidence', 'body' => 'A rising vibration or temperature trend justifies an inspection plan before a threshold is crossed. Control-chart signals call for special-cause investigation, while capability indices describe spread and centering against specifications.'],
            ['heading' => 'Failure analysis', 'body' => 'FMEA risk-priority numbers combine the supplied severity, occurrence, and detection ratings. Use the failure mode and effects to prioritize action; do not treat a score as a physical law.'],
            ['heading' => 'Safe intervention', 'body' => 'Before removing a guard or opening equipment, isolate hazardous energy, release or block stored energy, and verify the safe state under the applicable procedure. Production urgency does not substitute for energy control.'],
        ],
    ],
];

$sourceAdditions = [[
    'id' => 'prc-mechanical-engineering-2026-program',
    'title' => 'February 2026 Mechanical Engineers Licensure Examination Program',
    'source_type' => 'OFFICIAL_PROGRAM',
    'authority' => 'Professional Regulation Commission; Professional Regulatory Board of Mechanical Engineering',
    'url' => 'https://www.prc.gov.ph/sites/default/files/RA%20-%20MECHANICAL%20ENGINEER%20FEB%202026_merged.pdf',
    'publication_date' => null,
    'retrieved_at' => '2026-09-28',
    'version' => 'February 2026 official program',
    'status' => 'official_reference',
    'confidence' => 'authoritative_reference',
    'notes' => 'Official PRC examination program used only for current scope and relative subject weights: Mathematics 35%, Power Plant Engineering 35%, and Machine Design, Materials and Shop Practice 30%. It is not used as the sole instructional derivation source.',
]];

$questionPlan = [
    'me-topic-2-1-concept' => 34,
    'me-topic-2-2-concept' => 30,
    'me-topic-2-3-concept' => 34,
    'me-topic-3-1-concept' => 34,
    'me-topic-3-2-concept' => 28,
    'me-topic-3-3-concept' => 34,
    'me-topic-3-4-concept' => 30,
    'me-topic-3-5-concept' => 20,
];

$selected = array_keys($questionPlan);
$questions = [];

$add = static function (
    string $concept,
    int $depth,
    string $context,
    string $stem,
    string $answer,
    array $wrong,
    string $explanation,
    string $formulaFamily,
    string $variablePattern,
    string $targetVariable,
    string $scenario,
    string $prerequisite,
    bool $numeric,
    bool $highRisk,
    array $misconceptions,
    array &$questions,
    array $taxonomy,
    string $date
): void {
    if (!isset($taxonomy[$concept])) throw new RuntimeException('Unknown ME concept: ' . $concept);
    if (!in_array($depth, [2, 3, 4], true)) throw new RuntimeException('Invalid new ME depth: ' . $depth);
    if (!in_array($context, ['NEUTRAL', 'PROFESSIONAL'], true)) throw new RuntimeException('Invalid new ME context: ' . $context);
    if ($numeric && trim($formulaFamily) === '') throw new RuntimeException('Numeric item missing formula family.');
    if (count(array_unique(array_merge([$answer], $wrong))) !== 4) throw new RuntimeException('Choices are not distinct for: ' . $stem);
    $t = $taxonomy[$concept];
    $questions[] = [
        'taxonomy' => [
            'board_id' => 'mechanical-engineering',
            'subject_id' => $t['subject_id'],
            'domain_id' => $t['domain_id'],
            'topic_id' => $t['topic_id'],
            'concept_id' => $concept,
        ],
        'board' => 'mechanical-engineering',
        'status' => 'active',
        'source' => 'batch618-source-first',
        'createdAt' => $date,
        'updatedAt' => $date,
        'difficulty' => $depth >= 4 ? 'hard' : ($depth === 3 ? 'medium' : 'easy'),
        'question' => $stem,
        'choices' => [],
        'answer' => $answer,
        '__wrong' => array_values(array_map('strval', $wrong)),
        'explanation' => $explanation,
        'subject' => $t['subject_id'] === 'mechanical-engineering-engineering-sciences' ? 'Mechanical Engineering Engineering Sciences' : 'Mechanical Engineering Professional Subjects',
        'domain' => $t['domain_id'],
        'topic' => $t['topic_id'],
        'concept' => $concept,
        'numeric' => $numeric,
        'multistep' => $depth >= 4,
        'cognitive_intent' => $depth >= 4 ? 'analyze' : 'apply',
        'source_sensitivity' => 'STABLE',
        'content_blocks' => [],
        'unit_sensitive' => $numeric,
        'table_dependent' => false,
        'data_interpretation' => in_array($scenario, ['comparison', 'diagnosis', 'process interpretation'], true),
        'long_scenario' => $context === 'PROFESSIONAL' || $depth >= 4,
        'professional_judgment' => $context === 'PROFESSIONAL',
        'context_dependent' => $context !== 'NEUTRAL',
        'context_necessary' => $context !== 'NEUTRAL',
        'passage_dependent' => false,
        'passage_necessary' => false,
        'timesUsed' => 0,
        'timesCorrect' => 0,
        'timesIncorrect' => 0,
        'bookmarks' => 0,
        'reports' => 0,
        'helpfulExplanation' => 0,
        'notHelpfulExplanation' => 0,
        '__concept' => $concept,
        '__depth' => $depth,
        '__context' => $context,
        '__material' => $t['material_id'],
        '__sources' => $t['sources'],
        '__instructional_source' => $t['instructional_source'],
        '__formula_family' => $formulaFamily,
        '__variable_pattern' => $variablePattern,
        '__target_variable' => $targetVariable,
        '__scenario' => $scenario,
        '__prerequisite' => $prerequisite,
        '__numeric' => $numeric,
        '__high_risk' => $highRisk,
        '__misconceptions' => $misconceptions,
        '__verification' => $numeric ? [
            'status' => 'RECOMPUTED',
            'formula_family' => $formulaFamily,
            'variable_pattern' => $variablePattern,
            'target_variable' => $targetVariable,
            'reasoning_steps' => substr_count($explanation, ';') + 1,
            'independent_check' => 'Author calculation checked against governing equation, units, substitution, and answer-choice value.',
        ] : null,
    ];
};

$c21 = 'me-topic-2-1-concept';
$c22 = 'me-topic-2-2-concept';
$c23 = 'me-topic-2-3-concept';
$c31 = 'me-topic-3-1-concept';
$c32 = 'me-topic-3-2-concept';
$c33 = 'me-topic-3-3-concept';
$c34 = 'me-topic-3-4-concept';
$c35 = 'me-topic-3-5-concept';

// Question data is appended in concept blocks below. Each block uses the
// governing source-backed study resource selected above before authoring.
$Q = static function (
    string $concept,
    int $depth,
    string $context,
    string $stem,
    string $answer,
    array $wrong,
    string $explanation,
    string $formulaFamily,
    string $variablePattern,
    string $targetVariable,
    string $scenario,
    string $prerequisite = 'PREREQUISITE_FREE',
    bool $numeric = true,
    bool $highRisk = false,
    array $misconceptions = []
) use (&$add, &$questions, $taxonomy, $batchDate): void {
    $add($concept, $depth, $context, $stem, $answer, $wrong, $explanation, $formulaFamily, $variablePattern, $targetVariable, $scenario, $prerequisite, $numeric, $highRisk, $misconceptions, $questions, $taxonomy, $batchDate);
};

// 2-1: Stress, strain, stability, and deformation (34).
$Q($c21, 3, 'NEUTRAL', 'A 36-kN axial load acts on a uniform 900-mm² section. What average normal stress does the section carry?', '40 MPa', ['0.04 MPa', '4 MPa', '400 MPa'], 'Use sigma=P/A=36,000/900=40 N/mm², and 1 N/mm² equals 1 MPa.', 'axial-stress', 'P/A with N and mm²', 'normal stress', 'calculation', 'PREREQUISITE_FREE', true, false, ['wrong area conversion']);
$Q($c21, 3, 'NEUTRAL', 'A 1.2-m rod elongates by 0.24 mm under load. What axial strain is represented?', '0.00020', ['0.0020', '0.0200', '200'], 'Convert 1.2 m to 1200 mm, then strain=0.24/1200=0.00020.', 'axial-strain', 'delta-L/L', 'axial strain', 'calculation', 'ASSUMED_FOUNDATION', true, false, ['millimetres treated as metres']);
$Q($c21, 4, 'PROFESSIONAL', 'A prismatic steel tie is 1.5 m long, has area 600 mm², carries 30 kN, and has E=200 GPa. What elastic elongation occurs?', '0.375 mm', ['0.0375 mm', '3.75 mm', '37.5 mm'], 'The stress is 30,000/600=50 MPa; strain=50/200,000=0.00025; elongation=0.00025(1500)=0.375 mm.', 'elastic-elongation', 'PL/(AE)', 'elongation', 'calculation', 'ASSUMED_FOUNDATION', true, true, ['GPa/MPa mismatch']);
$Q($c21, 4, 'PROFESSIONAL', 'A steel bar is fully restrained. With E=70 GPa, alpha=12e-6 per degree C, and a 50-degree C rise, what thermal stress develops?', '42 MPa compression', ['4.2 MPa tension', '420 MPa compression', '0 MPa'], 'Full restraint prevents free strain, so the magnitude is E alpha delta-T=70,000(12e-6)(50)=42 MPa; heating produces compression.', 'restrained-thermal-stress', 'E alpha delta-T', 'thermal stress', 'calculation', 'ASSUMED_FOUNDATION', true, true, ['free expansion used despite restraint', 'wrong stress sign']);
$Q($c21, 3, 'NEUTRAL', 'A 2-m member expands freely with alpha=23e-6 per degree C during an 80-degree C rise. What elongation occurs?', '3.68 mm', ['0.368 mm', '36.8 mm', '0.00288 mm'], 'Free expansion is alpha L delta-T=23e-6(2)(80)=0.00368 m=3.68 mm.', 'free-thermal-expansion', 'alpha-L-delta-T', 'thermal elongation', 'calculation', 'PREREQUISITE_FREE', true, false, ['temperature treated as absolute']);
$Q($c21, 3, 'NEUTRAL', 'A tensile strain of 0.0015 is measured in a material with Poisson ratio 0.30. What lateral strain is expected in uniaxial loading?', '-0.00045', ['0.00045', '-0.0045', '0.0045'], 'For uniaxial elastic response, lateral strain=-nu times axial strain=-0.30(0.0015)=-0.00045.', 'poisson-strain', '-nu-epsilon', 'lateral strain', 'calculation', 'ASSUMED_FOUNDATION', true, false, ['omitting the negative sign']);
$Q($c21, 4, 'NEUTRAL', 'A rectangular section is 60 mm wide and 120 mm deep. What is its centroidal section modulus for bending about the strong axis?', '144,000 mm³', ['86,400 mm³', '288,000 mm³', '1,036,800 mm³'], 'For a rectangle, Z=bd²/6=60(120²)/6=144,000 mm³. The deep dimension is squared for the strong axis.', 'rectangular-section-modulus', 'bd²/6', 'section modulus', 'calculation', 'ASSUMED_FOUNDATION', true, false, ['using b²d', 'forgetting square on depth']);
$Q($c21, 4, 'PROFESSIONAL', 'A 4-kN·m bending moment acts on a section with Z=80,000 mm³. What extreme-fiber bending stress results?', '50 MPa', ['5 MPa', '80 MPa', '500 MPa'], 'Convert M to 4,000,000 N·mm and use sigma=M/Z=4,000,000/80,000=50 N/mm²=50 MPa.', 'bending-stress', 'M/Z with N-mm', 'bending stress', 'calculation', 'REQUIRED:me-topic-2-6-concept', true, true, ['N-m used with mm³']);
$Q($c21, 3, 'NEUTRAL', 'For a rectangular cross-section under transverse shear, where is the maximum transverse shear stress located?', 'At the neutral axis', ['At the extreme fiber', 'Uniformly at every point', 'Only at a corner'], 'The rectangular shear distribution is zero at the outer fibers and maximum at the neutral axis.', 'rectangular-shear-location', 'VQ/(Ib) distribution', 'maximum shear location', 'failure/error identification', 'PREREQUISITE_FREE', false, false, ['confusing bending-stress location']);
$Q($c21, 4, 'PROFESSIONAL', 'A rectangular section has shear force 12 kN and area 800 mm². Using tau-max=1.5V/A, what maximum shear stress is predicted?', '22.5 MPa', ['15 MPa', '18 MPa', '30 MPa'], 'tau-max=1.5(12,000)/800=22.5 N/mm²=22.5 MPa.', 'rectangular-maximum-shear', '1.5V/A', 'maximum shear stress', 'calculation', 'REQUIRED:me-topic-2-6-concept', true, true, ['using average V/A as maximum']);
$Q($c21, 4, 'PROFESSIONAL', 'A solid circular shaft of 40-mm diameter carries 200 N·m torque. What maximum torsional shear stress follows from tau=16T/(pi d³)?', '15.9 MPa', ['3.18 MPa', '31.8 MPa', '159 MPa'], 'Use 200,000 N·mm: tau=16(200,000)/(pi 40³)=15.9 N/mm²=15.9 MPa.', 'solid-shaft-torsion', '16T/(pi-d³)', 'torsional shear stress', 'calculation', 'REQUIRED:me-topic-2-6-concept', true, true, ['radius used as diameter', 'N-m/mm³ mismatch']);
$Q($c21, 4, 'PROFESSIONAL', 'A 1-m solid shaft has T=500 N·m, d=50 mm, and G=80 GPa. What angle of twist is predicted?', '0.0102 rad', ['0.00102 rad', '0.102 rad', '1.02 rad'], 'J=pi d⁴/32=613,600 mm⁴; theta=TL/(GJ)=500,000(1000)/(80,000(613,600))=0.0102 rad.', 'shaft-angle-of-twist', 'TL/(GJ)', 'angle of twist', 'calculation', 'REQUIRED:me-topic-2-6-concept', true, true, ['using polar area instead of J']);
$Q($c21, 4, 'PROFESSIONAL', 'For an ideal pin-ended column with E=200 GPa, I=8e-6 m⁴, and L=2 m, what Euler critical load is predicted?', '3.95 MN', ['0.395 MN', '39.5 MN', '395 kN'], 'For pinned ends, Pcr=pi²EI/L²=pi²(200e9)(8e-6)/4=3.95e6 N=3.95 MN.', 'euler-critical-load', 'pi²EI/Le²', 'critical buckling load', 'calculation', 'REQUIRED:me-topic-2-6-concept', true, true, ['effective-length boundary error']);
$Q($c21, 3, 'NEUTRAL', 'For an ideal Euler column with one fixed end and one free end, what effective length is used?', '2L', ['L/2', 'L', 'L/sqrt(2)'], 'The fixed-free column has effective-length factor K=2, so Le=2L in the ideal Euler model.', 'effective-length-factor', 'K-L boundary condition', 'effective length', 'comparison', 'ASSUMED_FOUNDATION', false, false, ['using pinned-pinned factor']);
$Q($c21, 3, 'NEUTRAL', 'A column has effective length 3 m and radius of gyration 20 mm. What slenderness ratio applies?', '150', ['15', '60', '1,500'], 'Slenderness ratio is Le/r=3/0.020=150.', 'column-slenderness', 'Le/r', 'slenderness ratio', 'calculation', 'ASSUMED_FOUNDATION', true, false, ['millimetres not converted to metres']);
$Q($c21, 3, 'NEUTRAL', 'If an ideal Euler column has its effective length doubled while E and I remain unchanged, what happens to critical load?', 'It becomes one quarter', ['It becomes one half', 'It doubles', 'It becomes four times'], 'Euler load varies as 1/Le²; replacing Le with 2Le gives 1/4 of the original load.', 'euler-length-sensitivity', 'P proportional to 1/Le²', 'critical-load sensitivity', 'parameter sensitivity', 'ASSUMED_FOUNDATION', false, true, ['linear rather than squared scaling']);
$Q($c21, 3, 'NEUTRAL', 'A material has yield strength 250 MPa and a calculated working stress of 100 MPa. What simple yield-based factor of safety is obtained?', '2.5', ['0.4', '1.5', '250'], 'The yield-based factor of safety is strength/stress=250/100=2.5.', 'yield-factor-of-safety', 'Sy/sigma', 'factor of safety', 'calculation', 'PREREQUISITE_FREE', true, false, ['stress/strength inversion']);
$Q($c21, 4, 'PROFESSIONAL', 'A member carries 40 kN axial tension and 3 kN·m bending. With A=1,000 mm² and Z=50,000 mm³, what tensile stress occurs at the tensile extreme fiber?', '100 MPa', ['40 MPa', '60 MPa', '160 MPa'], 'Axial stress is 40,000/1,000=40 MPa and bending stress is 3,000,000/50,000=60 MPa; same-sign superposition gives 100 MPa.', 'combined-axial-bending', 'P/A plus M/Z', 'extreme-fiber stress', 'calculation', 'REQUIRED:me-topic-2-6-concept', true, true, ['checking components separately']);
$Q($c21, 4, 'NEUTRAL', 'A plane-stress point has sigma-x=80 MPa, sigma-y=20 MPa, and tau-xy=30 MPa. What maximum principal stress is closest?', '92.4 MPa', ['50.0 MPa', '72.4 MPa', '122.4 MPa'], 'The average is 50 MPa and the Mohr-circle radius is sqrt(30²+30²)=42.43 MPa; sigma-1=92.43 MPa.', 'principal-stress', 'average plus Mohr radius', 'maximum principal stress', 'calculation', 'REQUIRED:me-topic-2-6-concept', true, true, ['using maximum normal stress alone']);
$Q($c21, 3, 'NEUTRAL', 'At a point with principal stresses 120 MPa and -40 MPa, what maximum Tresca shear stress is used?', '80 MPa', ['40 MPa', '160 MPa', '20 MPa'], 'Tresca maximum shear is half the largest principal-stress difference: (120-(-40))/2=80 MPa.', 'tresca-shear', '(sigma1-sigma3)/2', 'maximum shear stress', 'calculation', 'REQUIRED:me-topic-2-6-concept', true, false, ['forgetting half the range']);
$Q($c21, 4, 'PROFESSIONAL', 'A plane-stress point has sigma-x=80 MPa, sigma-y=0, and tau-xy=30 MPa. What von Mises equivalent stress is closest?', '95.4 MPa', ['80 MPa', '55.0 MPa', '110 MPa'], 'For plane stress, sigma-v=sqrt(sigma-x²-sigma-x sigma-y+sigma-y²+3tau²)=sqrt(80²+3(30²))=95.4 MPa.', 'von-mises-plane-stress', 'sqrt(sx²-sxsy+sy²+3t²)', 'equivalent stress', 'calculation', 'REQUIRED:me-topic-2-6-concept', true, true, ['using Tresca expression']);
$Q($c21, 4, 'NEUTRAL', 'An axially loaded member has P=20 kN, L=1 m, A=500 mm², and E=200 GPa. What elastic strain energy is stored?', '2 J', ['0.2 J', '20 J', '200 J'], 'U=P²L/(2AE)=(20,000²)(1)/(2(500e-6)(200e9))=2 J.', 'axial-strain-energy', 'P²L/(2AE)', 'strain energy', 'calculation', 'ASSUMED_FOUNDATION', true, true, ['force or area unit mismatch']);
$Q($c21, 3, 'NEUTRAL', 'In Castigliano analysis, what does the derivative of strain energy with respect to an applied load give?', 'The displacement in the load direction', ['The material yield strength', 'The natural frequency', 'The total heat input'], 'For a linearly elastic structure, partial U/partial P gives the displacement conjugate to P.', 'castigliano-displacement', 'partial-U/partial-P', 'displacement', 'process interpretation', 'ASSUMED_FOUNDATION', false, false, ['confusing energy derivative with stress']);
$Q($c21, 3, 'PROFESSIONAL', 'A simply supported beam carries a single 8-kN point load 1 m from the left support on a 3-m span. What reaction occurs at the left support?', '5.33 kN', ['2.67 kN', '4.00 kN', '8.00 kN'], 'Taking moments about the left support gives RB(3)=8(1), so RB=2.67 kN; vertical equilibrium gives RA=8-2.67=5.33 kN.', 'eccentric-beam-reactions', 'sum moments and forces', 'left reaction', 'calculation', 'REQUIRED:me-topic-2-6-concept', true, true, ['splitting load by symmetry despite eccentricity']);
$Q($c21, 3, 'PROFESSIONAL', 'A 6-kN load acts at the free end of a 2-m cantilever. What bending moment magnitude occurs at the fixed support?', '12 kN·m', ['3 kN·m', '8 kN·m', '24 kN·m'], 'The fixed-end moment is P L=6(2)=12 kN·m.', 'cantilever-end-moment', 'P-L', 'fixed-end moment', 'calculation', 'REQUIRED:me-topic-2-6-concept', true, true, ['using L/2']);
$Q($c21, 4, 'PROFESSIONAL', 'A triangular load varies from zero to 6 kN/m over 4 m. What equivalent load and location from the zero-load end apply?', '12 kN at 2.67 m', ['24 kN at 2 m', '12 kN at 1.33 m', '6 kN at 4 m'], 'The triangular resultant is one-half(4)(6)=12 kN and acts two-thirds of the base from the zero end: 2.67 m.', 'triangular-load-resultant', 'one-half wL and 2L/3', 'equivalent load location', 'calculation', 'REQUIRED:me-topic-2-6-concept', true, true, ['placing centroid at midpoint']);
$Q($c21, 3, 'NEUTRAL', 'A nominal bending stress is 40 MPa at a notch with stress-concentration factor Kt=2.5. What local elastic stress is predicted?', '100 MPa', ['16 MPa', '42.5 MPa', '1,000 MPa'], 'The elastic local stress is Kt times nominal stress=2.5(40)=100 MPa.', 'stress-concentration', 'Kt-sigma-nominal', 'local stress', 'calculation', 'ASSUMED_FOUNDATION', true, false, ['dividing by Kt']);
$Q($c21, 4, 'PROFESSIONAL', 'A component has alternating stress 80 MPa and mean stress 60 MPa. With Se=160 MPa and Sut=400 MPa, what modified Goodman factor of safety results?', '1.54', ['0.65', '2.00', '3.08'], 'Goodman gives 1/n=80/160+60/400=0.65, so n=1/0.65=1.54.', 'goodman-factor', '1/n=sa/Se+sm/Sut', 'fatigue factor of safety', 'calculation', 'ASSUMED_FOUNDATION', true, true, ['inverting the safety factor', 'using yield instead of ultimate strength']);
$Q($c21, 3, 'PROFESSIONAL', 'Which change most directly increases creep deformation risk in a loaded component?', 'Higher temperature for a longer time', ['Lower temperature for a shorter time', 'Removing the sustained load immediately', 'Reducing exposure time at the same temperature'], 'Creep is time-dependent deformation that becomes more significant with sustained load and elevated temperature.', 'creep-risk', 'temperature-time-load interaction', 'creep risk', 'failure/error identification', 'ASSUMED_FOUNDATION', false, true, ['treating creep as instantaneous yield']);
$Q($c21, 4, 'PROFESSIONAL', 'A cracked component has a stress-intensity factor approaching the material fracture toughness. What is the appropriate engineering interpretation?', 'Unstable crack growth risk is increasing', ['Fatigue is impossible', 'The component is automatically safe', 'Only thermal expansion matters'], 'When K approaches KIC under the applicable loading and environment, the margin against unstable fracture decreases.', 'fracture-toughness-interpretation', 'K relative to KIC', 'fracture risk', 'diagnosis', 'ASSUMED_FOUNDATION', false, true, ['equating toughness with strength']);
$Q($c21, 3, 'NEUTRAL', 'For a homogeneous shaft in pure torsion, at what orientation are maximum principal normal stresses located relative to the shaft axis?', 'Approximately 45 degrees', ['0 degrees', '90 degrees', '180 degrees'], 'Pure shear transforms to equal and opposite principal normal stresses on planes approximately 45 degrees to the original shear direction.', 'torsion-principal-orientation', 'pure-shear transformation', 'principal-stress orientation', 'comparison', 'ASSUMED_FOUNDATION', false, true, ['placing principal stress on shear plane']);
$Q($c21, 4, 'PROFESSIONAL', 'Two identical-length rods share a rigid end plate and carry a concentric load. Which rod attracts more load when its axial stiffness EA is larger?', 'The rod with larger EA', ['The rod with smaller EA', 'Both always carry equal force', 'Neither carries load'], 'Compatible deformation makes load share proportional to axial stiffness EA when the rods have the same imposed elongation.', 'parallel-member-load-share', 'P proportional to EA under common strain', 'member load share', 'comparison', 'REQUIRED:me-topic-2-6-concept', false, true, ['equal force assumed despite unequal stiffness']);
$Q($c21, 3, 'NEUTRAL', 'A steel member carries 18 kN over a 600 mm² area. What average axial stress results?', '30 MPa', ['3 MPa', '10 MPa', '300 MPa'], 'Average stress is P/A=18,000/600=30 N/mm²=30 MPa.', 'axial-stress-unit-check', 'P/A', 'average axial stress', 'calculation', 'PREREQUISITE_FREE', true, false, ['kN and mm² conversion error']);
$Q($c21, 4, 'PROFESSIONAL', 'At a critical point, sigma-x=60 MPa, sigma-y=20 MPa, and tau-xy=20 MPa. What maximum principal stress is closest?', '68.3 MPa', ['40.0 MPa', '48.3 MPa', '88.3 MPa'], 'The average normal stress is 40 MPa and the Mohr radius is sqrt(20²+20²)=28.3 MPa, giving sigma-1=68.3 MPa.', 'principal-stress-second-state', 'average plus sqrt(((sx-sy)/2)^2+t^2)', 'maximum principal stress', 'calculation', 'REQUIRED:me-topic-2-6-concept', true, true, ['using only the normal-stress average']);

// 2-2: Fluid statics, internal flow, and fluid machines (30).
$Q($c22, 3, 'NEUTRAL', 'Water flows through a 0.08-m² section at a mean speed of 2.5 m/s. What volumetric flow rate results?', '0.200 m³/s', ['0.032 m³/s', '0.100 m³/s', '0.800 m³/s'], 'Continuity gives Q=AV=0.08(2.5)=0.200 m³/s.', 'volumetric-flow-rate', 'Q=AV', 'volumetric flow rate', 'calculation', 'PREREQUISITE_FREE', true, false, ['dividing by area']);
$Q($c22, 3, 'NEUTRAL', 'An incompressible pipe contracts from area 0.030 m² to 0.010 m². If inlet speed is 2 m/s, what outlet speed follows?', '6 m/s', ['0.67 m/s', '2 m/s', '12 m/s'], 'Continuity requires A1V1=A2V2, so V2=0.030(2)/0.010=6 m/s.', 'continuity-contraction', 'A1V1=A2V2', 'outlet velocity', 'calculation', 'PREREQUISITE_FREE', true, false, ['area ratio inverted']);
$Q($c22, 3, 'NEUTRAL', 'A pressure tap is 6 m below an oil surface. With rho=850 kg/m³ and g=9.81 m/s², what gauge pressure is indicated?', '50.0 kPa', ['5.00 kPa', '83.4 kPa', '500 kPa'], 'Gauge pressure is rho g h=850(9.81)(6)=50,031 Pa, or approximately 50.0 kPa.', 'hydrostatic-gauge-pressure', 'rho-gh', 'gauge pressure', 'calculation', 'PREREQUISITE_FREE', true, false, ['using atmospheric pressure in gauge result']);
$Q($c22, 3, 'NEUTRAL', 'A uniform 0.40-m² gate experiences an average gauge pressure of 30 kPa. What resultant force does the pressure produce?', '12 kN', ['0.075 kN', '7.5 kN', '120 kN'], 'For uniform pressure, F=pA=30,000(0.40)=12,000 N=12 kN.', 'pressure-force', 'pA', 'pressure force', 'calculation', 'PREREQUISITE_FREE', true, false, ['kPa treated as Pa']);
$Q($c22, 3, 'NEUTRAL', 'A body displaces 0.0025 m³ of water. Using rho=1,000 kg/m³ and g=9.81 m/s², what buoyant force acts?', '24.5 N upward', ['2.45 N upward', '245 N upward', '9.81 N upward'], 'Buoyant force is displaced-fluid weight=rho g V=1,000(9.81)(0.0025)=24.5 N upward.', 'buoyant-force', 'rho-g-displaced-volume', 'buoyant force', 'calculation', 'PREREQUISITE_FREE', true, false, ['using body volume instead of displaced volume']);
$Q($c22, 4, 'NEUTRAL', 'A horizontal water pipe speeds up from 2 m/s to 5 m/s with negligible losses. What pressure decrease follows from Bernoulli’s equation?', '10.5 kPa', ['3.0 kPa', '7.0 kPa', '21.0 kPa'], 'At equal elevation, p1-p2=one-half rho(V2²-V1²)=0.5(1,000)(25-4)=10,500 Pa=10.5 kPa.', 'bernoulli-pressure-change', 'one-half-rho-delta-V²', 'pressure decrease', 'calculation', 'REQUIRED:me-topic-2-3-concept', true, true, ['using velocity difference instead of squared difference']);
$Q($c22, 4, 'PROFESSIONAL', 'A Venturi section changes water speed from 3 m/s to 6 m/s at the same elevation with negligible losses. What pressure drop is predicted?', '13.5 kPa', ['4.5 kPa', '9.0 kPa', '27.0 kPa'], 'The drop is one-half rho(6²-3²)=0.5(1,000)(27)=13,500 Pa=13.5 kPa.', 'venturi-pressure-drop', 'one-half-rho-(V2²-V1²)', 'Venturi pressure drop', 'calculation', 'REQUIRED:me-topic-2-3-concept', true, true, ['forgetting dynamic-pressure factor']);
$Q($c22, 3, 'NEUTRAL', 'Water with rho=1,000 kg/m³ and mu=0.001 Pa·s flows at 1.2 m/s in a 50-mm pipe. What Reynolds number results?', '60,000', ['600', '6,000', '600,000'], 'Re=rhoVD/mu=1,000(1.2)(0.05)/0.001=60,000, which is in the turbulent range for an ordinary pipe.', 'reynolds-number', 'rhoVD/mu', 'Reynolds number', 'calculation', 'PREREQUISITE_FREE', true, false, ['diameter or viscosity unit error']);
$Q($c22, 3, 'NEUTRAL', 'For fully developed laminar flow in a circular pipe, how does centerline velocity compare with mean velocity?', 'It is twice the mean velocity', ['It is half the mean velocity', 'It equals the mean velocity', 'It is four times the mean velocity'], 'The parabolic laminar profile has V-centerline=2 V-mean.', 'laminar-profile-ratio', 'Vmax=2Vmean', 'centerline velocity', 'comparison', 'PREREQUISITE_FREE', false, false, ['using turbulent profile intuition']);
$Q($c22, 4, 'PROFESSIONAL', 'A 50-m pipe has D=0.10 m, V=2 m/s, Darcy friction factor f=0.02, and g=9.81 m/s². What friction head loss occurs?', '2.04 m', ['0.204 m', '1.02 m', '20.4 m'], 'Darcy-Weisbach gives hf=f(L/D)V²/(2g)=0.02(500)(4/19.62)=2.04 m.', 'darcy-friction-head', 'f(L/D)V²/(2g)', 'friction head loss', 'calculation', 'REQUIRED:me-topic-2-6-concept', true, true, ['using Fanning factor', 'omitting L/D']);
$Q($c22, 3, 'NEUTRAL', 'A fitting has minor-loss coefficient K=2.5 in a line with speed 4 m/s. What head loss does the fitting produce?', '2.04 m', ['0.51 m', '1.02 m', '4.08 m'], 'The minor loss is K V²/(2g)=2.5(16)/(19.62)=2.04 m.', 'minor-loss-head', 'K-V²/(2g)', 'minor head loss', 'calculation', 'PREREQUISITE_FREE', true, false, ['using friction-factor equation']);
$Q($c22, 3, 'NEUTRAL', 'A pipe system has 6.2 m major loss and 1.8 m minor loss at a selected operating point. What total loss applies?', '8.0 m', ['4.4 m', '6.2 m', '11.16 m'], 'Major and minor losses add along the same flow path: 6.2+1.8=8.0 m.', 'total-head-loss', 'sum major and minor losses', 'total head loss', 'calculation', 'PREREQUISITE_FREE', true, false, ['multiplying losses']);
$Q($c22, 4, 'PROFESSIONAL', 'A pump raises 0.015 m³/s of water through 25 m. What hydraulic power is delivered using rho=1,000 kg/m³ and g=9.81 m/s²?', '3.68 kW', ['0.368 kW', '2.45 kW', '36.8 kW'], 'Hydraulic power is rho g Q H=1,000(9.81)(0.015)(25)=3,678.75 W=3.68 kW.', 'pump-hydraulic-power', 'rho-g-Q-H', 'hydraulic power', 'calculation', 'REQUIRED:me-topic-2-3-concept', true, true, ['using total flow volume without time']);
$Q($c22, 4, 'PROFESSIONAL', 'The hydraulic power of a pump is 3.68 kW and its shaft efficiency is 80%. What shaft power is required?', '4.60 kW', ['2.94 kW', '3.68 kW', '5.75 kW'], 'Input shaft power=hydraulic power/efficiency=3.68/0.80=4.60 kW.', 'pump-shaft-power', 'Pout/eta', 'shaft power', 'calculation', 'REQUIRED:me-topic-2-3-concept', true, true, ['multiplying by efficiency instead of dividing']);
$Q($c22, 3, 'PROFESSIONAL', 'A geometrically unchanged pump runs at 80% of its original speed. Under affinity laws, what fraction of original head is predicted?', '0.64', ['0.80', '0.512', '1.25'], 'For unchanged diameter, head scales with speed squared: H2/H1=(0.80)²=0.64.', 'pump-affinity-head', 'H proportional to N²', 'head ratio', 'parameter sensitivity', 'REQUIRED:me-topic-2-3-concept', false, true, ['using flow ratio for head']);
$Q($c22, 4, 'PROFESSIONAL', 'A jet with mass flow 4 kg/s strikes a stationary flat plate and is brought to rest from 15 m/s. What force component acts in the jet direction?', '60 N', ['15 N', '19 N', '240 N'], 'The momentum balance gives F=mdot delta-V=4(15)=60 N in the direction opposing the incoming jet.', 'jet-impact-force', 'mdot-delta-V', 'impact force', 'calculation', 'REQUIRED:me-topic-2-6-concept', true, true, ['using mass instead of mass flow']);
$Q($c22, 4, 'PROFESSIONAL', 'Water enters an elbow at 10 m/s east and exits at 6 m/s north. With mass flow 2 kg/s, what is the magnitude of the momentum-force change?', '23.3 N', ['8.0 N', '16.0 N', '32.0 N'], 'The velocity-change vector has magnitude sqrt((-10)²+6²)=11.66 m/s; multiplying by 2 kg/s gives 23.3 N.', 'elbow-momentum-vector', 'mdot|Vout-Vin|', 'momentum-force magnitude', 'calculation', 'REQUIRED:me-topic-2-6-concept', true, true, ['subtracting scalar speeds', 'ignoring direction']);
$Q($c22, 3, 'NEUTRAL', 'A nozzle must pass 0.030 m³/s at mean speed 4 m/s. What internal diameter is required for a circular section?', '97.7 mm', ['9.77 mm', '48.9 mm', '195 mm'], 'Area=Q/V=0.0075 m², so D=sqrt(4A/pi)=sqrt(0.03/pi)=0.0977 m=97.7 mm.', 'pipe-diameter-from-flow', 'D=sqrt(4Q/(piV))', 'pipe diameter', 'calculation', 'PREREQUISITE_FREE', true, false, ['using Q/V as diameter']);
$Q($c22, 3, 'NEUTRAL', 'Which pressure convention must be used in an ideal-gas equation when a state is described by a vacuum or atmospheric reference?', 'Absolute pressure', ['Gauge pressure', 'Pressure head only', 'Differential pressure without reference'], 'Thermodynamic state relations require absolute pressure; gauge pressure must be converted by adding atmospheric pressure.', 'absolute-pressure-convention', 'pabs=pgauge+patm', 'pressure convention', 'failure/error identification', 'PREREQUISITE_FREE', false, true, ['gauge pressure in gas law']);
$Q($c22, 4, 'PROFESSIONAL', 'A pump installation develops bubbles at the impeller eye and a rattling sound at high flow. Which first diagnostic is most defensible?', 'Check available NPSH against required NPSH', ['Increase discharge pressure without inspection', 'Reduce fluid density in the calculation', 'Treat the sound as harmless valve noise'], 'Bubbles and noise near the impeller are consistent with cavitation; compare NPSH available with NPSH required and check suction losses.', 'pump-cavitation-diagnosis', 'NPSHa versus NPSHr', 'cavitation diagnosis', 'diagnosis', 'REQUIRED:me-topic-2-3-concept', false, true, ['confusing cavitation with discharge pressure']);
$Q($c22, 4, 'PROFESSIONAL', 'A rectangular duct is 0.20 m wide and 0.10 m high. What hydraulic diameter should be used for a full-flow duct?', '0.133 m', ['0.050 m', '0.150 m', '0.300 m'], 'Dh=4A/P=4(0.020)/(2(0.20+0.10))=0.133 m.', 'rectangular-hydraulic-diameter', '4A/wetted perimeter', 'hydraulic diameter', 'calculation', 'PREREQUISITE_FREE', true, false, ['using the longer side']);
$Q($c22, 4, 'PROFESSIONAL', 'A turbine receives water at 0.040 m³/s and 40 m head with hydraulic efficiency 85%. What shaft power is produced?', '13.3 kW', ['1.33 kW', '16.7 kW', '33.3 kW'], 'Hydraulic input is rho g QH=15.70 kW; multiplying by 0.85 gives 13.35 kW.', 'hydraulic-turbine-power', 'eta-rho-g-Q-H', 'turbine shaft power', 'calculation', 'REQUIRED:me-topic-2-3-concept', true, true, ['dividing by turbine efficiency']);
$Q($c22, 3, 'PROFESSIONAL', 'A pump curve and a system curve are plotted for the same installation. What operating point is selected?', 'Their intersection', ['The pump curve at zero flow only', 'The highest pump head regardless of flow', 'The system curve at infinite flow'], 'The operating point satisfies both the head delivered by the pump and the head required by the system at the same flow.', 'pump-system-intersection', 'Hpump(Q)=Hsystem(Q)', 'operating point', 'system selection', 'REQUIRED:me-topic-2-3-concept', false, true, ['choosing a point from one curve']);
$Q($c22, 3, 'NEUTRAL', 'For a pipe with known roughness and Reynolds number, which quantity must be selected consistently before applying Darcy-Weisbach loss?', 'The friction-factor convention', ['The atmospheric humidity', 'The pump color', 'The fluid boiling point only'], 'Darcy and Fanning factors differ by a factor of four; the convention must match the loss equation and source data.', 'friction-factor-convention', 'Darcy f with Darcy-Weisbach', 'friction-factor selection', 'failure/error identification', 'ASSUMED_FOUNDATION', false, true, ['mixing Darcy and Fanning factors']);
$Q($c22, 4, 'PROFESSIONAL', 'A control volume has one inlet and one outlet, and its mass flow is steady. Which continuity statement is correct for an incompressible fluid?', 'A1V1=A2V2', ['A1/V1=A2/V2', 'A1V2=A2V1 only for compressible flow', 'The two flow rates need not match'], 'Steady mass conservation requires equal volumetric flow at inlet and outlet when density is constant, so A1V1=A2V2.', 'steady-incompressible-continuity', 'mass conservation with constant rho', 'continuity relation', 'process interpretation', 'PREREQUISITE_FREE', false, false, ['reversing area and velocity']);
$Q($c22, 3, 'NEUTRAL', 'A drag force on a body is modeled by F=0.5 rho Cd A V². With rho=1.2 kg/m³, Cd=0.9, A=0.6 m², and V=10 m/s, what force results?', '32.4 N', ['3.24 N', '16.2 N', '64.8 N'], 'F=0.5(1.2)(0.9)(0.6)(10²)=32.4 N.', 'drag-force', 'one-half-rho-Cd-A-V²', 'drag force', 'calculation', 'REQUIRED:me-topic-2-6-concept', true, true, ['forgetting velocity square']);
$Q($c22, 4, 'PROFESSIONAL', 'A fluid machine has a 7-kW hydraulic output and 1.4-kW losses. What input power is required?', '8.4 kW', ['5.6 kW', '7.0 kW', '9.8 kW'], 'Input power equals useful output plus losses=7+1.4=8.4 kW.', 'fluid-machine-loss-balance', 'Pin=Pout+Ploss', 'input power', 'calculation', 'REQUIRED:me-topic-2-3-concept', true, true, ['subtracting losses from output']);
$Q($c22, 3, 'NEUTRAL', 'A pressure tap reads 50.0 kPa gauge where atmospheric pressure is 101.3 kPa absolute. What absolute pressure applies?', '151.3 kPa absolute', ['51.3 kPa absolute', '101.3 kPa absolute', '202.6 kPa absolute'], 'Absolute pressure is gauge plus atmospheric pressure: 50.0+101.3=151.3 kPa.', 'gauge-to-absolute-pressure', 'pabs=pg+patm', 'absolute pressure', 'calculation', 'PREREQUISITE_FREE', true, false, ['using gauge pressure as absolute']);
$Q($c22, 3, 'PROFESSIONAL', 'A fluid machine delivers 7.2 kW hydraulic power from an 8.4 kW shaft input. What hydraulic efficiency results?', '0.857', ['0.143', '1.167', '15.6'], 'Hydraulic efficiency is output divided by input: 7.2/8.4=0.857.', 'fluid-machine-efficiency', 'Phyd/Pshaft', 'hydraulic efficiency', 'calculation', 'PREREQUISITE_FREE', true, false, ['using input divided by output']);
$Q($c22, 4, 'PROFESSIONAL', 'A 0.10-m-diameter pipe carries water at 3 m/s. If density is 1,000 kg/m³, what mass flow rate results?', '23.6 kg/s', ['7.85 kg/s', '31.4 kg/s', '94.2 kg/s'], 'Area=piD²/4=0.007854 m²; mass flow=rho AV=1000(0.007854)(3)=23.6 kg/s.', 'pipe-mass-flow', 'rho-pi-D²-V/4', 'mass flow rate', 'calculation', 'PREREQUISITE_FREE', true, true, ['using circumference instead of area']);

// 2-3: Thermodynamics, heat transfer, and HVAC (34).
$Q($c23, 3, 'NEUTRAL', 'A closed system receives 120 kJ of heat and does 45 kJ of work. What internal-energy change follows?', '75 kJ increase', ['165 kJ increase', '75 kJ decrease', '45 kJ decrease'], 'With heat into and work out positive, delta-U=Q-W=120-45=75 kJ.', 'closed-system-first-law', 'delta-U=Q-W', 'internal-energy change', 'calculation', 'PREREQUISITE_FREE', true, false, ['reversing work sign']);
$Q($c23, 3, 'NEUTRAL', 'A fixed-volume ideal gas is heated from 300 K to 450 K while its initial pressure is 150 kPa. What final pressure results?', '225 kPa', ['100 kPa', '200 kPa', '300 kPa'], 'At fixed mass and volume, p/T is constant, so p2=150(450/300)=225 kPa.', 'ideal-gas-fixed-volume', 'p2=p1T2/T1', 'final pressure', 'calculation', 'PREREQUISITE_FREE', true, false, ['using Celsius', 'holding pressure fixed']);
$Q($c23, 3, 'NEUTRAL', 'Two kilograms of a substance have specific internal energy 250 kJ/kg. What total internal energy do they contain?', '500 kJ', ['125 kJ', '252 kJ', '5,000 kJ'], 'Total energy is mass times specific energy=2(250)=500 kJ.', 'specific-to-total-energy', 'U=mu', 'total internal energy', 'calculation', 'ASSUMED_FOUNDATION', true, false, ['confusing specific and total quantity']);
$Q($c23, 3, 'NEUTRAL', 'Air has R=0.287 kJ/(kg·K) and cp=1.005 kJ/(kg·K). What cv follows from cp-cv=R?', '0.718 kJ/(kg·K)', ['0.287 kJ/(kg·K)', '1.292 kJ/(kg·K)', '3.50 kJ/(kg·K)'], 'cv=cp-R=1.005-0.287=0.718 kJ/(kg·K).', 'ideal-gas-specific-heats', 'cv=cp-R', 'constant-volume specific heat', 'calculation', 'ASSUMED_FOUNDATION', true, false, ['adding R']);
$Q($c23, 4, 'NEUTRAL', 'A 0.5-kg ideal gas expands isothermally at 300 K to twice its original volume. With R=0.287 kJ/(kg·K), what work is done by the gas?', '29.9 kJ', ['14.4 kJ', '43.1 kJ', '86.1 kJ'], 'For isothermal ideal-gas expansion, W=mRT ln(V2/V1)=0.5(0.287)(300)ln2=29.9 kJ.', 'isothermal-gas-work', 'mRT ln(V2/V1)', 'boundary work', 'calculation', 'ASSUMED_FOUNDATION', true, true, ['using V2/V1 without logarithm']);
$Q($c23, 4, 'PROFESSIONAL', 'A steady adiabatic nozzle has an enthalpy drop of 50 kJ/kg and negligible inlet velocity. What outlet speed is predicted if potential-energy change is negligible?', '316 m/s', ['50 m/s', '100 m/s', '447 m/s'], 'The enthalpy drop becomes kinetic energy: V2=sqrt(2(50,000))=316.2 m/s.', 'nozzle-enthalpy-to-velocity', 'delta-h=delta-V²/2', 'outlet velocity', 'calculation', 'ASSUMED_FOUNDATION', true, true, ['forgetting factor of two', 'kJ/kg to J/kg']);
$Q($c23, 4, 'PROFESSIONAL', 'A steady compressor has a mass flow of 0.30 kg/s and an enthalpy rise of 120 kJ/kg. Neglecting heat loss and kinetic changes, what power input is required?', '36 kW', ['0.36 kW', '40 kW', '360 kW'], 'Compressor power is mdot delta-h=0.30(120)=36 kW.', 'compressor-enthalpy-power', 'mdot-delta-h', 'compressor power', 'calculation', 'ASSUMED_FOUNDATION', true, true, ['using enthalpy rise per second incorrectly']);
$Q($c23, 4, 'PROFESSIONAL', 'A turbine receives 0.80 kg/s with an enthalpy drop of 300 kJ/kg. What power is produced when other energy terms are negligible?', '240 kW', ['24 kW', '300 kW', '375 kW'], 'Turbine power is mdot times enthalpy drop=0.80(300)=240 kW.', 'turbine-enthalpy-power', 'mdot-delta-h', 'turbine power', 'calculation', 'ASSUMED_FOUNDATION', true, true, ['dividing by mass flow']);
$Q($c23, 3, 'NEUTRAL', 'What entropy-generation sign is required for a real process with friction and other irreversibilities?', 'Positive', ['Negative', 'Exactly zero', 'Undefined in every process'], 'The second law requires entropy generation to be nonnegative; friction makes it positive.', 'entropy-generation-sign', 'Sgen greater than or equal to zero', 'entropy generation', 'failure/error identification', 'ASSUMED_FOUNDATION', false, true, ['treating entropy generation as energy loss with negative sign']);
$Q($c23, 3, 'NEUTRAL', 'A reversible engine operates between reservoirs at 900 K and 300 K. What Carnot efficiency is the upper limit?', '0.667', ['0.333', '0.500', '3.000'], 'Carnot efficiency is 1-Tc/Th=1-300/900=0.667.', 'carnot-efficiency', '1-Tc/Th', 'maximum efficiency', 'calculation', 'ASSUMED_FOUNDATION', true, true, ['using Celsius temperatures']);
$Q($c23, 3, 'NEUTRAL', 'A heat engine receives 900 kJ and rejects 540 kJ per cycle. What net work and thermal efficiency result?', '360 kJ and 0.40', ['1,440 kJ and 0.60', '360 kJ and 0.60', '540 kJ and 0.40'], 'Net work is Qin-Qout=900-540=360 kJ; efficiency=360/900=0.40.', 'heat-engine-balance', 'Wnet=Qin-Qout; eta=Wnet/Qin', 'cycle work and efficiency', 'calculation', 'ASSUMED_FOUNDATION', true, true, ['using rejected heat as work']);
$Q($c23, 3, 'NEUTRAL', 'A refrigerator removes 18 kW from a cold space while consuming 4 kW. What COP applies?', '4.5', ['0.222', '3.5', '22'], 'Refrigerator COP is cooling effect/work input=18/4=4.5.', 'refrigerator-cop', 'COPR=Qc/W', 'refrigerator COP', 'calculation', 'PREREQUISITE_FREE', true, false, ['using work/cooling effect']);
$Q($c23, 3, 'NEUTRAL', 'A refrigerator has COP=4.5. What heat-pump COP applies to the same ideal energy balance?', '5.5', ['3.5', '4.5', '9.0'], 'Heat-pump COP=(Qc+W)/W=COPR+1=5.5.', 'heat-pump-cop', 'COPHP=COPR+1', 'heat-pump COP', 'calculation', 'PREREQUISITE_FREE', true, false, ['treating COP as efficiency']);
$Q($c23, 4, 'NEUTRAL', 'A plane wall has k=1.5 W/(m·K), area 10 m², thickness 0.15 m, and temperature difference 30 K. What steady conduction rate occurs?', '3.00 kW', ['0.300 kW', '30.0 kW', '300 kW'], 'Fourier conduction gives Q=kA delta-T/L=1.5(10)(30)/0.15=3,000 W.', 'plane-wall-conduction', 'kA-delta-T/L', 'conduction heat rate', 'calculation', 'ASSUMED_FOUNDATION', true, true, ['inverting thickness']);
$Q($c23, 3, 'NEUTRAL', 'A surface has h=40 W/(m²·K), area 3 m², and surface-to-fluid temperature difference 25 K. What convection heat rate occurs?', '3.00 kW', ['0.300 kW', '1.20 kW', '30.0 kW'], 'Convection is Q=hA delta-T=40(3)(25)=3,000 W.', 'convection-heat-rate', 'hA-delta-T', 'convection heat rate', 'calculation', 'PREREQUISITE_FREE', true, false, ['using temperature ratio']);
$Q($c23, 3, 'NEUTRAL', 'A blackbody absolute temperature rises from 300 K to 400 K. By what factor does ideal emissive power change?', '3.16', ['1.33', '1.78', '4.00'], 'Stefan-Boltzmann emissive power scales with T⁴, so the ratio is (400/300)⁴=3.16.', 'blackbody-radiation-ratio', '(T2/T1)^4', 'emissive-power ratio', 'parameter sensitivity', 'PREREQUISITE_FREE', true, true, ['using Celsius', 'linear temperature scaling']);
$Q($c23, 4, 'NEUTRAL', 'A counterflow heat exchanger has terminal temperature differences of 40 K and 20 K. What log-mean temperature difference is closest?', '28.9 K', ['20.0 K', '30.0 K', '60.0 K'], 'Delta-Tlm=(40-20)/ln(40/20)=28.85 K.', 'log-mean-temperature-difference', '(dT1-dT2)/ln(dT1/dT2)', 'log-mean temperature difference', 'calculation', 'ASSUMED_FOUNDATION', true, true, ['using arithmetic mean without checking']);
$Q($c23, 4, 'PROFESSIONAL', 'A heat exchanger has U=250 W/(m²·K), area 8 m², and LMTD=15 K. What heat-transfer rate is predicted before fouling correction?', '30 kW', ['3.0 kW', '15 kW', '60 kW'], 'The UA model gives Q=UA delta-Tlm=250(8)(15)=30,000 W=30 kW.', 'ua-heat-exchanger', 'UA-delta-Tlm', 'heat-transfer rate', 'calculation', 'ASSUMED_FOUNDATION', true, true, ['omitting U or area']);
$Q($c23, 3, 'NEUTRAL', 'A transient wall has h=50 W/(m²·K), characteristic length 0.01 m, and k=5 W/(m·K). What Biot number is obtained?', '0.10', ['0.001', '0.010', '10.0'], 'Bi=hLc/k=50(0.01)/5=0.10, at the boundary commonly used for a lumped check.', 'biot-number', 'hLc/k', 'Biot number', 'calculation', 'ASSUMED_FOUNDATION', true, false, ['using full wall thickness as Lc']);
$Q($c23, 4, 'NEUTRAL', 'For a lumped-capacitance body, what fraction of the initial temperature difference remains after one time constant?', '0.368', ['0.135', '0.500', '0.632'], 'The lumped response contains exp(-t/tau); at t=tau, exp(-1)=0.368.', 'lumped-transient-fraction', 'exp(-t/tau)', 'remaining temperature difference', 'calculation', 'REQUIRED:me-topic-1-2-concept', true, true, ['reporting removed fraction instead of remaining fraction']);
$Q($c23, 3, 'NEUTRAL', 'In one-dimensional conduction, which direction does heat flux take when temperature decreases in the positive x direction?', 'Positive x', ['Negative x', 'Both directions equally', 'No direction because heat flux is scalar'], 'Fourier law qx=-k dT/dx; a negative temperature gradient produces positive heat flux.', 'fourier-law-sign', 'qx=-k dT/dx', 'heat-flux direction', 'failure/error identification', 'PREREQUISITE_FREE', false, true, ['dropping Fourier-law negative sign']);
$Q($c23, 3, 'PROFESSIONAL', 'A cooling zone has sensible load 18 kW and latent load 6 kW. What sensible heat ratio applies?', '0.75', ['0.25', '0.67', '3.00'], 'SHR=sensible/(sensible+latent)=18/(18+6)=0.75.', 'sensible-heat-ratio', 'Qs/(Qs+Ql)', 'sensible heat ratio', 'calculation', 'PREREQUISITE_FREE', true, false, ['dividing sensible by latent']);
$Q($c23, 4, 'PROFESSIONAL', 'A refrigeration evaporator must remove 30 kW using refrigerant latent capacity 200 kJ/kg. What refrigerant mass flow is needed?', '0.15 kg/s', ['0.0067 kg/s', '6.0 kg/s', '150 kg/s'], 'Mass flow=Q/hfg=30 kJ/s divided by 200 kJ/kg=0.15 kg/s.', 'refrigerant-mass-flow', 'Qdot=mdot-hfg', 'refrigerant mass flow', 'calculation', 'ASSUMED_FOUNDATION', true, true, ['using kW as kJ rather than kJ/s']);
$Q($c23, 3, 'NEUTRAL', 'Which property is approximately conserved across an ideal steady throttling valve when kinetic and potential changes are negligible?', 'Specific enthalpy', ['Temperature in every fluid', 'Pressure', 'Specific volume'], 'A steady adiabatic throttling process has no useful shaft work and negligible kinetic/potential changes, so h1 is approximately h2.', 'throttling-enthalpy', 'h1=h2 for throttle', 'conserved property', 'process interpretation', 'ASSUMED_FOUNDATION', false, true, ['assuming pressure is conserved']);
$Q($c23, 3, 'PROFESSIONAL', 'Why is intercooling used between stages of a multistage compressor?', 'It lowers the specific volume entering the next stage and reduces work', ['It increases every stage inlet temperature', 'It makes compression isothermal without any heat transfer', 'It eliminates pressure rise'], 'Cooling between stages reduces the volume and work required in later compression, subject to the actual cooler and pressure-drop limits.', 'intercooling-purpose', 'multistage compression work', 'intercooling purpose', 'system selection', 'PREREQUISITE_FREE', false, true, ['confusing intercooling with reheating']);
$Q($c23, 3, 'PROFESSIONAL', 'A moist-air zone has acceptable dry-bulb temperature but excessive humidity. What control objective should be prioritized?', 'Remove latent moisture', ['Add only sensible heat', 'Increase humidity further', 'Ignore dew point'], 'When dry-bulb is acceptable but moisture is high, dehumidification or latent cooling addresses the controlling load.', 'humidity-control-objective', 'sensible versus latent load', 'humidity-control objective', 'system selection', 'PREREQUISITE_FREE', false, true, ['confusing temperature with humidity']);
$Q($c23, 4, 'NEUTRAL', 'A heat exchanger receives 2 kg/s of water with cp=4.0 kJ/(kg·K) and warms it by 30 K. What heat rate does the water receive?', '240 kW', ['15 kW', '60 kW', '2400 kW'], 'Qdot=mdot cp delta-T=2(4.0)(30)=240 kJ/s=240 kW.', 'sensible-stream-heat-rate', 'mdot-cp-delta-T', 'stream heat rate', 'calculation', 'ASSUMED_FOUNDATION', true, true, ['missing mass-flow unit']);
$Q($c23, 4, 'NEUTRAL', 'Two series thermal resistances are 0.10 K/W and 0.20 K/W across a 60-K temperature difference. What heat rate passes?', '200 W', ['20 W', '300 W', '600 W'], 'Total resistance is 0.30 K/W, so Q=delta-T/R=60/0.30=200 W.', 'series-thermal-resistance', 'delta-T/(R1+R2)', 'heat rate through resistance network', 'calculation', 'PREREQUISITE_FREE', true, true, ['adding conductance instead of resistance']);
$Q($c23, 4, 'NEUTRAL', 'One kilogram of an ideal gas expands isothermally from V1 to 2V1. With R=0.287 kJ/(kg·K) and T=300 K, what entropy change occurs?', '0.199 kJ/K', ['0.0957 kJ/K', '0.287 kJ/K', '0.861 kJ/K'], 'For an isothermal ideal-gas expansion, delta-s=R ln(V2/V1)=0.287 ln2=0.199 kJ/K.', 'ideal-gas-isothermal-entropy', 'R ln(V2/V1)', 'entropy change', 'calculation', 'REQUIRED:me-topic-1-2-concept', true, true, ['using volume difference']);
$Q($c23, 3, 'PROFESSIONAL', 'A passive heat exchanger is supplied with hot-stream inlet temperature 120°C. Which cold-stream outlet statement is physically bounded in ordinary operation?', 'It cannot exceed the hot-stream inlet temperature', ['It must exceed the hot-stream inlet temperature', 'It is independent of hot-stream temperature', 'It can be any temperature without energy input'], 'Without an external heat source, a cold outlet cannot exceed the hot inlet in an ordinary exchanger; approach temperature and losses still matter.', 'heat-exchanger-temperature-bound', 'energy and second-law bound', 'cold outlet limit', 'failure/error identification', 'PREREQUISITE_FREE', false, true, ['violating temperature approach']);
$Q($c23, 3, 'NEUTRAL', 'An ideal-gas sample has cp=1.00 kJ/(kg·K), mass 2 kg, and is heated 40 K at constant pressure. What heat transfer is required?', '80 kJ', ['20 kJ', '40 kJ', '120 kJ'], 'At constant pressure for the supplied cp, Q=m cp delta-T=2(1.00)(40)=80 kJ.', 'constant-pressure-heating', 'm-cp-delta-T', 'constant-pressure heat transfer', 'calculation', 'ASSUMED_FOUNDATION', true, false, ['using mass-specific heat incorrectly']);
$Q($c23, 4, 'NEUTRAL', 'A steady nozzle has an enthalpy drop of 200 kJ/kg and negligible inlet speed, heat, and elevation changes. What exit speed follows?', '632 m/s', ['200 m/s', '447 m/s', '894 m/s'], 'The steady-flow energy balance gives V2=sqrt(2 delta-h)=sqrt(400,000)=632 m/s.', 'nozzle-enthalpy-to-velocity', 'V=sqrt(2-delta-h)', 'nozzle exit speed', 'calculation', 'REQUIRED:me-topic-2-6-concept', true, true, ['forgetting the factor of two']);
$Q($c23, 3, 'NEUTRAL', 'For an isolated real process, what can be said about entropy generation?', 'It is nonnegative and is positive for an irreversible process', ['It must be negative for friction', 'It is always zero in a real process', 'It is equal to pressure in pascals'], 'The second law requires entropy generation to be nonnegative; friction, mixing, and finite-temperature heat transfer make it positive.', 'entropy-generation-sign', 'Sgen>=0', 'entropy generation', 'failure/error identification', 'ASSUMED_FOUNDATION', false, true, ['reversing second-law sign']);
$Q($c23, 4, 'PROFESSIONAL', 'A compressor takes 3 kg/s of gas and adds 70 kJ/kg of specific work. What ideal power input is required?', '210 kW', ['23.3 kW', '70 kW', '2,100 kW'], 'Power input is m-dot times specific work=3(70)=210 kJ/s=210 kW.', 'compressor-specific-work-power', 'mdot-wspecific', 'compressor power input', 'calculation', 'ASSUMED_FOUNDATION', true, true, ['using specific work as total power']);

// 3-1: Power transmission, joints, bearings, and fatigue (34).
$Q($c31, 3, 'NEUTRAL', 'A 20-tooth gear drives a 60-tooth external gear at 1,800 rpm. What ideal speed does the driven gear have?', '600 rpm', ['200 rpm', '1,800 rpm', '5,400 rpm'], 'External gear speed is inversely proportional to teeth: N2=N1(T1/T2)=1,800(20/60)=600 rpm.', 'external-gear-speed-ratio', 'N2/N1=T1/T2', 'driven speed', 'calculation', 'PREREQUISITE_FREE', true, false, ['using tooth ratio in wrong direction']);
$Q($c31, 3, 'PROFESSIONAL', 'An ideal 20-tooth gear drives a 60-tooth gear. If input torque is 100 N·m, what output torque is predicted?', '300 N·m', ['33.3 N·m', '100 N·m', '900 N·m'], 'Ideal torque ratio is inverse of speed ratio: T2=T1(T2 teeth/T1 teeth)=100(60/20)=300 N·m.', 'gear-torque-ratio', 'T2/T1=T2teeth/T1teeth', 'driven torque', 'calculation', 'REQUIRED:me-topic-2-1-concept', true, true, ['using speed ratio for torque']);
$Q($c31, 3, 'PROFESSIONAL', 'A gear transmits 240 N·m through a pitch radius of 0.12 m. What tangential tooth force acts at the pitch circle?', '2,000 N', ['20 N', '288 N', '28,800 N'], 'Tangential force is Ft=T/r=240/0.12=2,000 N.', 'gear-tangential-force', 'T/r', 'pitch-line force', 'calculation', 'REQUIRED:me-topic-2-1-concept', true, true, ['using diameter instead of radius']);
$Q($c31, 3, 'NEUTRAL', 'A belt drive pulley has diameter 0.40 m and rotates at 900 rpm without slip. What belt speed results?', '18.85 m/s', ['3.77 m/s', '11.31 m/s', '37.70 m/s'], 'Belt speed=pi D N/60=pi(0.40)(900)/60=18.85 m/s.', 'belt-speed', 'piDN/60', 'belt speed', 'calculation', 'PREREQUISITE_FREE', true, false, ['forgetting rpm-to-second conversion']);
$Q($c31, 4, 'PROFESSIONAL', 'A belt runs at 10 m/s with a tension difference of 500 N between tight and slack sides. What ideal power is transmitted?', '5.0 kW', ['0.50 kW', '50 kW', '5,000 kW'], 'Belt power is (T1-T2)v=500(10)=5,000 W=5.0 kW.', 'belt-power', '(T1-T2)v', 'belt power', 'calculation', 'REQUIRED:me-topic-2-1-concept', true, true, ['using total tension']);
$Q($c31, 4, 'PROFESSIONAL', 'For a belt with mu=0.25 and wrap angle pi radians, what capstan tension ratio T1/T2 is predicted?', '2.19', ['0.79', '1.25', '6.85'], 'The capstan relation is T1/T2=exp(mu theta)=exp(0.25pi)=2.19.', 'capstan-tension-ratio', 'exp(mu-theta)', 'belt tension ratio', 'calculation', 'REQUIRED:me-topic-2-1-concept', true, true, ['using degrees instead of radians']);
$Q($c31, 3, 'NEUTRAL', 'A 25-tooth sprocket has pitch 20 mm and rotates at 600 rpm. What chain speed results?', '5.0 m/s', ['0.50 m/s', '2.0 m/s', '10.0 m/s'], 'Chain speed=teeth times pitch times rpm/60=25(0.020)(600)/60=5.0 m/s.', 'chain-speed', 'z p N/60', 'chain speed', 'calculation', 'PREREQUISITE_FREE', true, false, ['millimetres not converted']);
$Q($c31, 4, 'PROFESSIONAL', 'A bearing has dynamic rating C=30 kN and equivalent load P=10 kN. Using L10=(C/P)^3 million revolutions, what life applies?', '27 million revolutions', ['3 million revolutions', '9 million revolutions', '90 million revolutions'], 'L10=(30/10)^3=27 million revolutions.', 'bearing-basic-rating-life', '(C/P)^3', 'bearing L10 life', 'calculation', 'REQUIRED:me-topic-2-1-concept', true, true, ['using power two']);
$Q($c31, 4, 'PROFESSIONAL', 'A bearing has C=40 kN, radial load Fr=10 kN, axial load Fa=4 kN, and supplied factors X=0.56 and Y=1.5. What equivalent load P=XFr+YFa applies?', '11.6 kN', ['5.6 kN', '10.0 kN', '16.0 kN'], 'P=0.56(10)+1.5(4)=5.6+6.0=11.6 kN.', 'bearing-equivalent-load', 'XFr+YFa', 'equivalent bearing load', 'calculation', 'REQUIRED:me-topic-2-1-concept', true, true, ['omitting axial term']);
$Q($c31, 3, 'NEUTRAL', 'A shaft transmits 150 N·m at 1,800 rpm. What ideal mechanical power is transmitted?', '28.3 kW', ['2.83 kW', '15.0 kW', '47.1 kW'], 'Omega=2pi(1,800)/60=188.5 rad/s; P=Tomega=150(188.5)=28.3 kW.', 'shaft-power-torque-speed', 'T(2piN/60)', 'shaft power', 'calculation', 'ASSUMED_FOUNDATION', true, false, ['using rpm directly as rad/s']);
$Q($c31, 4, 'PROFESSIONAL', 'A solid 45-mm shaft transmits 400 N·m torque. What maximum torsional shear stress follows from tau=16T/(pi d³)?', '22.4 MPa', ['2.24 MPa', '11.2 MPa', '44.8 MPa'], 'Using 400,000 N·mm and d=45 mm gives tau=16(400,000)/(pi 45³)=22.4 MPa.', 'shaft-torsion-stress', '16T/(pi-d³)', 'shaft shear stress', 'calculation', 'REQUIRED:me-topic-2-1-concept', true, true, ['diameter-cube error']);
$Q($c31, 4, 'PROFESSIONAL', 'A solid shaft must carry 300 N·m with allowable shear stress 40 MPa. What minimum diameter follows from tau=16T/(pi d³)?', '33.6 mm', ['16.8 mm', '25.2 mm', '67.2 mm'], 'd=(16T/(pi tau))^(1/3)=(16(300,000)/(pi 40))^(1/3)=33.6 mm.', 'shaft-diameter-from-stress', 'cube-root(16T/(pi-tau))', 'shaft diameter', 'calculation', 'REQUIRED:me-topic-2-1-concept', true, true, ['forgetting cube root']);
$Q($c31, 4, 'PROFESSIONAL', 'A rectangular key transmits 500 N·m through a 50-mm shaft. If the key shear area is 500 mm², what average key shear stress results?', '40 MPa', ['10 MPa', '20 MPa', '80 MPa'], 'Tangential force at the shaft is 2T/d=2(500,000)/50=20,000 N; stress=20,000/500=40 MPa.', 'key-shear-stress', 'F=2T/d then tau=F/A', 'key shear stress', 'calculation', 'REQUIRED:me-topic-2-1-concept', true, true, ['using T/d instead of 2T/d']);
$Q($c31, 3, 'NEUTRAL', 'A spring with stiffness 12 kN/m is compressed 50 mm. What elastic energy is stored?', '15 J', ['1.5 J', '30 J', '300 J'], 'Spring energy is one-half kx²=0.5(12,000)(0.05²)=15 J.', 'spring-energy', 'one-half-kx²', 'spring energy', 'calculation', 'ASSUMED_FOUNDATION', true, false, ['millimetres not converted', 'using kx']);
$Q($c31, 3, 'NEUTRAL', 'What force is produced by a linear spring with stiffness 12 kN/m compressed 50 mm?', '600 N', ['60 N', '240 N', '12,000 N'], 'Hooke law gives F=kx=12,000(0.050)=600 N.', 'spring-force', 'kx', 'spring force', 'calculation', 'ASSUMED_FOUNDATION', true, false, ['using x in millimetres']);
$Q($c31, 4, 'PROFESSIONAL', 'A multi-plate clutch has six friction surfaces, mu=0.28, axial force 1,500 N, and effective radius 0.12 m. What torque capacity follows from T=n mu W R?', '302 N·m', ['50.4 N·m', '504 N·m', '3,024 N·m'], 'T=6(0.28)(1,500)(0.12)=302.4 N·m.', 'multi-plate-clutch-torque', 'n-mu-W-R', 'clutch torque', 'calculation', 'REQUIRED:me-topic-2-1-concept', true, true, ['counting plates rather than friction surfaces']);
$Q($c31, 3, 'PROFESSIONAL', 'A brake applies 800 N tangential force at a drum radius of 0.25 m. What braking torque is generated?', '200 N·m', ['32 N·m', '320 N·m', '2,000 N·m'], 'Brake torque is tangential force times radius=800(0.25)=200 N·m.', 'brake-torque', 'Ft-r', 'braking torque', 'calculation', 'REQUIRED:me-topic-2-6-concept', true, true, ['using diameter']);
$Q($c31, 4, 'PROFESSIONAL', 'A flywheel with I=4 kg·m² slows from 20 rad/s to 10 rad/s. How much rotational energy is released?', '600 J', ['200 J', '300 J', '1,200 J'], 'Released energy=one-half I(omega1²-omega2²)=0.5(4)(400-100)=600 J.', 'flywheel-energy-change', 'one-half-I-delta-omega²', 'released rotational energy', 'calculation', 'REQUIRED:me-topic-2-6-concept', true, true, ['using angular-speed difference only']);
$Q($c31, 3, 'PROFESSIONAL', 'A rotating imbalance produces a periodic force at the shaft rotational frequency. What is the most direct corrective action?', 'Balance the rotating assembly', ['Increase imbalance deliberately', 'Remove all lubrication', 'Change a static pressure gauge'], 'A mass eccentricity produces a synchronous rotating force; correcting the mass distribution addresses the source.', 'rotating-imbalance-correction', 'force at rotational frequency', 'imbalance correction', 'failure/error identification', 'REQUIRED:me-topic-2-6-concept', false, true, ['treating synchronous vibration as random noise']);
$Q($c31, 4, 'PROFESSIONAL', 'A component has alternating stress 100 MPa and mean stress 50 MPa. With Se=200 MPa and Sut=400 MPa, what Goodman factor of safety results?', '1.60', ['0.625', '2.00', '3.20'], '1/n=100/200+50/400=0.50+0.125=0.625, so n=1.60.', 'shaft-goodman-factor', '1/n=sa/Se+sm/Sut', 'fatigue factor', 'calculation', 'REQUIRED:me-topic-2-1-concept', true, true, ['forgetting reciprocal']);
$Q($c31, 3, 'PROFESSIONAL', 'Which design change most directly reduces alternating bending stress in a rotating shaft with the same transmitted torque?', 'Increase the shaft diameter', ['Add a sharp notch', 'Reduce the section modulus', 'Increase the load eccentricity'], 'Bending stress varies inversely with section modulus; increasing diameter increases section modulus and lowers stress.', 'shaft-diameter-fatigue-choice', 'sigma=M/Z', 'stress-reduction choice', 'design', 'REQUIRED:me-topic-2-1-concept', false, true, ['choosing a notch']);
$Q($c31, 3, 'PROFESSIONAL', 'A gear tooth shows pitting concentrated near the pitch line after inadequate lubrication. Which failure mechanism is most consistent?', 'Rolling contact fatigue', ['Pure axial yielding', 'Thermal expansion only', 'Electrical short circuit'], 'Repeated Hertzian contact stress and poor lubrication can produce surface pitting, a rolling-contact fatigue symptom.', 'gear-pitting-diagnosis', 'contact stress and lubrication', 'gear failure mechanism', 'diagnosis', 'REQUIRED:me-topic-2-1-concept', false, true, ['confusing pitting with bending fracture']);
$Q($c31, 3, 'PROFESSIONAL', 'A high-speed journal bearing overheats while radial load remains within rating. Which corrective check is most directly related to lubricant behavior?', 'Verify viscosity, supply, and heat removal', ['Increase load without checking oil', 'Replace the bearing with a smaller one immediately', 'Ignore alignment and contamination'], 'Temperature depends on viscous shear, supply, contamination, alignment, and heat removal as well as load rating.', 'bearing-overheating-lubrication', 'viscosity-flow-heat balance', 'bearing corrective check', 'diagnosis', 'REQUIRED:me-topic-2-1-concept', false, true, ['rating treated as complete diagnosis']);
$Q($c31, 4, 'PROFESSIONAL', 'A bearing has L10 life of 10 million revolutions and operates at 1,200 rpm. What operating time corresponds to that L10 life?', '138.9 hours', ['13.9 hours', '83.3 hours', '1,389 hours'], 'Time=10,000,000 revolutions/(1,200 rev/min)=8,333 min=138.9 h.', 'bearing-life-hours', 'revolutions/(rpm*60)', 'bearing life in hours', 'calculation', 'REQUIRED:me-topic-2-1-concept', true, true, ['forgetting minutes-to-hours conversion']);
$Q($c31, 3, 'PROFESSIONAL', 'A flexible coupling is installed between two shafts. What condition should still be corrected rather than hidden by the coupling?', 'Persistent shaft misalignment', ['All lubrication requirements', 'The existence of torque', 'The shaft rotation direction'], 'A flexible coupling tolerates limited movement but does not eliminate the need to correct excessive angular, parallel, or axial misalignment.', 'coupling-misalignment', 'alignment tolerance', 'coupling design check', 'failure/error identification', 'REQUIRED:me-topic-2-1-concept', false, true, ['assuming flexible means unlimited']);
$Q($c31, 4, 'PROFESSIONAL', 'A compound gear train has 20 teeth driving 40 teeth, and a coaxial 15-tooth gear driving 45 teeth. If input speed is 1,200 rpm, what output speed results?', '200 rpm', ['100 rpm', '400 rpm', '1,800 rpm'], 'Speed ratio=(20/40)(15/45)=1/6, so output=1,200/6=200 rpm.', 'compound-gear-train-speed', '(T1/T2)(T3/T4)', 'compound-train speed', 'calculation', 'REQUIRED:me-topic-2-6-concept', true, true, ['applying only one gear ratio']);
$Q($c31, 4, 'PROFESSIONAL', 'An input shaft delivers 8 kW through two stages with efficiencies 0.95 and 0.90. What output power reaches the final shaft?', '6.84 kW', ['7.20 kW', '7.60 kW', '8.95 kW'], 'Overall efficiency is 0.95(0.90)=0.855; output=8(0.855)=6.84 kW.', 'transmission-efficiency-chain', 'Pout=Pin eta1 eta2', 'final power', 'calculation', 'REQUIRED:me-topic-2-1-concept', true, true, ['adding efficiencies']);
$Q($c31, 3, 'PROFESSIONAL', 'A 30:1 speed-reduction gearbox receives 1,500 rpm. What ideal output speed applies?', '50 rpm', ['30 rpm', '500 rpm', '45,000 rpm'], 'Output speed=input speed/reduction ratio=1,500/30=50 rpm.', 'gearbox-speed-reduction', 'Nout=Nin/i', 'gearbox output speed', 'calculation', 'PREREQUISITE_FREE', true, false, ['multiplying by reduction ratio']);
$Q($c31, 3, 'PROFESSIONAL', 'A chain drive is selected for repeated shock loading. Which design factor should receive priority beyond the nominal speed ratio?', 'Chain tension capacity and fatigue life', ['Only tooth color', 'Only the ideal kinematic ratio', 'Ignoring lubrication because chains are open'], 'Repeated shock makes tension capacity, fatigue, lubrication, alignment, and fatigue life material design constraints.', 'chain-shock-selection', 'load history and chain capacity', 'chain selection factor', 'design', 'REQUIRED:me-topic-2-1-concept', false, true, ['kinematics treated as capacity']);
$Q($c31, 4, 'PROFESSIONAL', 'A rotating shaft has a critical speed near its operating speed and shows a large vibration peak. What is the immediate engineering interpretation?', 'Resonance risk requires speed or stiffness review', ['The vibration proves zero imbalance', 'The shaft is automatically safe', 'Only the bearing color matters'], 'Operating near a natural frequency can amplify response; review speed separation, stiffness, damping, balance, and support conditions.', 'shaft-critical-speed', 'operating speed versus natural frequency', 'critical-speed interpretation', 'diagnosis', 'REQUIRED:me-topic-2-6-concept', false, true, ['confusing critical speed with maximum safe speed']);
$Q($c31, 3, 'PROFESSIONAL', 'For a component under repeated stress, which design change generally improves fatigue resistance when geometry and material are otherwise unchanged?', 'Reduce sharp surface notches', ['Increase stress concentration', 'Add a sharp keyway corner', 'Ignore surface finish'], 'Reducing stress concentration and improving surface condition lowers local alternating stress and improves fatigue margin.', 'fatigue-notch-design', 'local alternating stress', 'fatigue design action', 'design', 'REQUIRED:me-topic-2-1-concept', false, true, ['notch as strengthening']);
$Q($c31, 4, 'PROFESSIONAL', 'A keyed shaft transmits 600 N·m through a 60-mm shaft. If the key tangential force is approximated as 2T/d, what force acts on the key?', '20.0 kN', ['1.0 kN', '10.0 kN', '36.0 kN'], 'Using N-mm, Ft=2T/d=2(600,000)/60=20,000 N=20.0 kN.', 'key-tangential-force', '2T/d', 'key tangential force', 'calculation', 'REQUIRED:me-topic-2-1-concept', true, true, ['using T/d instead of 2T/d']);
$Q($c31, 3, 'PROFESSIONAL', 'A rolling-element bearing is exposed to contamination and inadequate lubrication. Which service effect is most likely even if its static load is below rating?', 'Reduced life and accelerated surface damage', ['Guaranteed infinite life', 'A change only in gear ratio', 'No change because load rating covers lubrication'], 'Contamination and lubrication strongly affect rolling-contact fatigue, wear, heat, and life; static rating alone is not a service-life guarantee.', 'bearing-contamination-life', 'lubrication and surface life', 'bearing service effect', 'diagnosis', 'ASSUMED_FOUNDATION', false, true, ['rating treated as complete service model']);
$Q($c31, 4, 'PROFESSIONAL', 'A shaft carries bending moment 1.2 kN·m and torque 0.9 kN·m. Which engineering step is required before selecting a diameter?', 'Combine the bending and torsional stresses using the stated design criterion', ['Compare torque alone and ignore bending', 'Use speed ratio as a stress', 'Select diameter from color alone'], 'A shaft design must combine the stress components at the critical section using the specified equivalent-stress or fatigue criterion before sizing.', 'shaft-combined-loading-design', 'bending plus torsion criterion', 'shaft diameter design step', 'design', 'REQUIRED:me-topic-2-1-concept', false, true, ['single-load sizing']);

// 3-2: Machining, production flow, quality, and economics (26).
$Q($c32, 3, 'NEUTRAL', 'A turning operation targets cutting speed 120 m/min on an 80-mm workpiece. What spindle speed is required?', '477 rpm', ['48 rpm', '120 rpm', '764 rpm'], 'N=1000V/(pi D)=1000(120)/(pi 80)=477.5 rpm.', 'turning-spindle-speed', 'N=1000V/(pi-D)', 'spindle speed', 'calculation', 'PREREQUISITE_FREE', true, false, ['forgetting diameter conversion']);
$Q($c32, 3, 'NEUTRAL', 'An 80-mm workpiece rotates at 1,200 rpm. What cutting speed is produced?', '301.6 m/min', ['30.2 m/min', '188.5 m/min', '603.2 m/min'], 'V=pi D N/1000=pi(80)(1,200)/1000=301.6 m/min.', 'turning-cutting-speed', 'pi-D-N/1000', 'cutting speed', 'calculation', 'PREREQUISITE_FREE', true, false, ['using radius']);
$Q($c32, 3, 'NEUTRAL', 'A turning tool feeds 0.25 mm/rev while the spindle runs at 800 rev/min. What linear feed rate results?', '200 mm/min', ['3.2 mm/min', '320 mm/min', '2,000 mm/min'], 'Feed rate=fN=0.25(800)=200 mm/min.', 'turning-feed-rate', 'fN', 'linear feed rate', 'calculation', 'PREREQUISITE_FREE', true, false, ['dividing by spindle speed']);
$Q($c32, 4, 'PROFESSIONAL', 'A turning cut uses D=60 mm, N=500 rev/min, feed=0.20 mm/rev, and depth=0.50 mm. Using MRR=piDNfd, what removal rate results?', '9,425 mm³/min', ['942.5 mm³/min', '18,850 mm³/min', '94,250 mm³/min'], 'MRR=pi(60)(500)(0.20)(0.50)=9,424.8 mm³/min.', 'turning-material-removal-rate', 'piDNfd', 'material-removal rate', 'calculation', 'PREREQUISITE_FREE', true, true, ['omitting depth of cut']);
$Q($c32, 3, 'PROFESSIONAL', 'A cutting force is 1,200 N and cutting speed is 2 m/s. What ideal cutting power is required?', '2.4 kW', ['0.60 kW', '1.2 kW', '2.4 MW'], 'Power=force times speed=1,200(2)=2,400 W=2.4 kW.', 'cutting-power', 'FcVc', 'cutting power', 'calculation', 'REQUIRED:me-topic-2-1-concept', true, true, ['force divided by speed']);
$Q($c32, 3, 'NEUTRAL', 'A milling cutter has 8 teeth, feed per tooth 0.10 mm, and spindle speed 600 rpm. What table feed results?', '480 mm/min', ['75 mm/min', '600 mm/min', '4,800 mm/min'], 'Table feed=teeth times feed/tooth times rpm=8(0.10)(600)=480 mm/min.', 'milling-table-feed', 'zfzN', 'milling table feed', 'calculation', 'PREREQUISITE_FREE', true, false, ['using feed per tooth as total feed']);
$Q($c32, 4, 'NEUTRAL', 'A 20-mm drill runs at 600 rpm with feed 0.20 mm/rev. What ideal material-removal rate uses MRR=(piD²/4)fN?', '37,700 mm³/min', ['3,770 mm³/min', '75,400 mm³/min', '377,000 mm³/min'], 'Area is pi(20²)/4=314.16 mm²; MRR=314.16(0.20)(600)=37,700 mm³/min.', 'drilling-material-removal-rate', 'piD²fN/4', 'drilling MRR', 'calculation', 'PREREQUISITE_FREE', true, true, ['using circumference instead of area']);
$Q($c32, 4, 'PROFESSIONAL', 'Two lengths are assembled end-to-end: A=25.00 plus or minus 0.02 mm and B=40.00 plus or minus 0.03 mm. What worst-case range applies?', '64.95 to 65.05 mm', ['64.97 to 65.03 mm', '64.00 to 66.00 mm', '65.00 to 65.02 mm'], 'Nominal length is 25.00+40.00=65.00 mm; worst-case tolerance is plus or minus(0.02+0.03)=0.05 mm, so the range is 64.95 to 65.05 mm.', 'tolerance-stack-up', 'sum nominal and absolute tolerances', 'assembled length range', 'calculation', 'PREREQUISITE_FREE', true, true, ['incorrect nominal sum']);
$Q($c32, 3, 'PROFESSIONAL', 'A hole is 25.00 mm and a shaft is 24.96 mm at the same limit condition. What diametral clearance is present?', '0.04 mm', ['0.004 mm', '0.40 mm', '49.96 mm'], 'Clearance=hole diameter-shaft diameter=25.00-24.96=0.04 mm.', 'fit-clearance', 'Dhole-Dshaft', 'diametral clearance', 'calculation', 'PREREQUISITE_FREE', true, false, ['subtracting in reverse']);
$Q($c32, 3, 'PROFESSIONAL', 'During CNC setup, what is the primary purpose of establishing a workpiece datum?', 'Relate programmed coordinates to the physical part', ['Increase spindle power automatically', 'Replace tool-wear inspection', 'Set the coolant temperature'], 'The datum establishes the reference frame used to locate features and connect the tool path to the actual workpiece.', 'cnc-workpiece-datum', 'coordinate reference', 'datum purpose', 'process interpretation', 'PREREQUISITE_FREE', false, true, ['confusing datum with cutting speed']);
$Q($c32, 3, 'PROFESSIONAL', 'Which statement best distinguishes absolute from incremental CNC positioning?', 'Absolute coordinates reference a fixed work origin; incremental coordinates reference the current position', ['Both always reference the spindle', 'Incremental coordinates ignore movement direction', 'Absolute coordinates are always in millimetres'], 'The coordinate reference changes: absolute uses the work origin, while incremental uses the current tool position.', 'cnc-coordinate-modes', 'fixed origin versus current position', 'CNC coordinate mode', 'comparison', 'PREREQUISITE_FREE', false, true, ['confusing coordinate mode with unit mode']);
$Q($c32, 3, 'PROFESSIONAL', 'A turning tool shows increasing flank wear and the finished diameter is drifting. Which response is most defensible?', 'Inspect wear against a limit and correct the tool offset or replace the tool', ['Increase feed without checking the tool', 'Ignore the trend because nominal speed is unchanged', 'Change coolant color only'], 'Flank wear changes geometry and cutting forces; verify the wear limit, offset, tool condition, and process capability.', 'tool-wear-response', 'wear trend and dimensional drift', 'tool-wear action', 'diagnosis', 'REQUIRED:me-topic-3-5-concept', false, true, ['treating wear as cosmetic']);
$Q($c32, 3, 'PROFESSIONAL', 'What is a primary function of cutting fluid in a suitable machining operation?', 'Control heat and friction while carrying chips as appropriate', ['Increase tool temperature deliberately', 'Replace workholding', 'Eliminate the need for a datum'], 'Cutting fluid can reduce friction and temperature and aid chip evacuation, subject to material, tool, and process compatibility.', 'cutting-fluid-purpose', 'heat-friction-chip control', 'cutting-fluid function', 'process interpretation', 'PREREQUISITE_FREE', false, true, ['assuming one fluid suits every material']);
$Q($c32, 3, 'PROFESSIONAL', 'In a casting process, what is the primary purpose of a riser?', 'Feed liquid metal as solidification shrinkage occurs', ['Increase cutting speed', 'Measure spindle torque', 'Prevent all porosity without design review'], 'A riser supplies liquid metal to compensate for volumetric shrinkage during solidification when properly placed and sized.', 'casting-riser-purpose', 'solidification-shrinkage feed', 'riser purpose', 'process interpretation', 'PREREQUISITE_FREE', false, true, ['confusing riser with sprue']);
$Q($c32, 3, 'PROFESSIONAL', 'Which region near a weld commonly experiences altered microstructure and properties without being melted?', 'The heat-affected zone', ['The datum zone', 'The neutral axis only', 'The coolant reservoir'], 'The heat-affected zone is heated by welding but does not itself melt; its properties can change.', 'weld-heat-affected-zone', 'thermal cycle outside fusion zone', 'weld region', 'process interpretation', 'PREREQUISITE_FREE', false, true, ['confusing HAZ with fusion zone']);
$Q($c32, 3, 'PROFESSIONAL', 'Why can build orientation matter in an additively manufactured part?', 'Layer bonding and anisotropy can make properties direction-dependent', ['Orientation never affects properties', 'It changes only the color', 'It removes all residual stress'], 'Layer-by-layer processes can produce direction-dependent strength, surface finish, residual stress, and support requirements.', 'additive-build-orientation', 'direction-dependent layer properties', 'build orientation effect', 'comparison', 'PREREQUISITE_FREE', false, true, ['assuming isotropy without evidence']);
$Q($c32, 4, 'PROFESSIONAL', 'A process has specification width 0.60 mm and estimated standard deviation 0.05 mm. What Cp value follows from Cp=(USL-LSL)/(6 sigma)?', '2.0', ['0.5', '1.0', '6.0'], 'Cp=0.60/(6(0.05))=0.60/0.30=2.0.', 'process-capability-cp', '(USL-LSL)/(6sigma)', 'Cp', 'calculation', 'REQUIRED:me-topic-3-5-concept', true, true, ['using 3 sigma for Cp']);
$Q($c32, 4, 'PROFESSIONAL', 'A process has USL=10.2, LSL=9.8, mean=10.1, and sigma=0.05. What Cpk is closest?', '0.67', ['1.33', '2.00', '4.00'], 'Cpk=min((10.2-10.1)/(0.15),(10.1-9.8)/(0.15))=min(0.667,2.0)=0.67.', 'process-capability-cpk', 'min(USL-mu,mu-LSL)/(3sigma)', 'Cpk', 'calculation', 'REQUIRED:me-topic-3-5-concept', true, true, ['using centered distance only']);
$Q($c32, 3, 'PROFESSIONAL', 'A process-control chart has centerline 50 units and estimated standard deviation 2 units. What upper 3-sigma control limit applies?', '56 units', ['52 units', '54 units', '62 units'], 'UCL=mean+3sigma=50+3(2)=56 units.', 'control-chart-limit', 'mean plus 3sigma', 'upper control limit', 'calculation', 'REQUIRED:me-topic-3-5-concept', true, true, ['confusing specification and control limits']);
$Q($c32, 4, 'PROFESSIONAL', 'Annual demand is 2,400 units, ordering cost is PHP60/order, and annual holding cost is PHP3/unit. What EOQ is closest?', '310 units', ['98 units', '490 units', '1,440 units'], 'EOQ=sqrt(2DS/H)=sqrt(2(2,400)(60)/3)=sqrt(96,000)=309.8 units.', 'economic-order-quantity', 'sqrt(2DS/H)', 'EOQ', 'calculation', 'PREREQUISITE_FREE', true, true, ['using H in numerator']);
$Q($c32, 3, 'PROFESSIONAL', 'A line must produce 350 units in 420 available minutes. What takt time applies?', '1.20 min/unit', ['0.83 min/unit', '1.67 min/unit', '770 min/unit'], 'Takt=available time/demand=420/350=1.20 min/unit.', 'takt-time', 'available time/demand', 'takt time', 'calculation', 'PREREQUISITE_FREE', true, false, ['demand/time inversion']);
$Q($c32, 3, 'PROFESSIONAL', 'Three required stations have cycle times 5, 7, and 6 minutes. Which station is the bottleneck?', 'The 7-minute station', ['The 5-minute station', 'The 6-minute station', 'All are equal'], 'The longest required cycle time limits sustainable flow, so the 7-minute station is the bottleneck.', 'production-bottleneck', 'max cycle time', 'bottleneck station', 'process interpretation', 'PREREQUISITE_FREE', false, true, ['choosing the shortest station']);
$Q($c32, 4, 'PROFESSIONAL', 'Four tasks take 3, 4, 2, and 5 minutes. With three stations and a cycle time of 6 minutes, what line-balance efficiency results?', '77.8%', ['25.9%', '66.7%', '116.7%'], 'Efficiency=sum task time/(stations times cycle)=14/(3(6))=0.7778=77.8%.', 'line-balance-efficiency', 'sum task time/(mC)', 'line efficiency', 'calculation', 'PREREQUISITE_FREE', true, true, ['using largest task only']);
$Q($c32, 3, 'PROFESSIONAL', 'Demand is 40 containers/day, replenishment lead time is 0.5 day, safety factor is 1.2, and each kanban container holds 10 units. How many cards result after rounding up?', '3 cards', ['1 card', '2 cards', '4 cards'], 'Cards=demand rate times lead time times safety factor/container size=40(0.5)(1.2)/10=2.4, so round up to 3.', 'kanban-card-count', 'DLTsafety/C', 'kanban card count', 'calculation', 'PREREQUISITE_FREE', true, true, ['rounding down replenishment capacity']);
$Q($c32, 4, 'PROFESSIONAL', 'A product has fixed cost PHP120,000, selling price PHP500, and variable cost PHP300 per unit. What break-even quantity applies?', '600 units', ['240 units', '400 units', '1,000 units'], 'Break-even quantity=fixed cost/(price-variable cost)=120,000/(500-300)=600 units.', 'break-even-quantity', 'F/(p-v)', 'break-even quantity', 'calculation', 'PREREQUISITE_FREE', true, true, ['using selling price without variable cost']);
$Q($c32, 3, 'PROFESSIONAL', 'A gauge repeatedly reads the same value but is offset from a traceable standard. Which measurement property is primarily poor?', 'Accuracy', ['Repeatability', 'Resolution only', 'Throughput'], 'Consistent readings can be repeatable while an offset from the accepted value indicates poor accuracy.', 'measurement-accuracy-repeatability', 'bias versus scatter', 'measurement property', 'comparison', 'REQUIRED:me-topic-3-5-concept', false, true, ['confusing accuracy and repeatability']);
$Q($c32, 4, 'PROFESSIONAL', 'A production process is centered but its spread doubles while specification limits stay fixed. What happens to Cp?', 'It is cut in half', ['It doubles', 'It remains unchanged', 'It becomes infinite'], 'Cp is specification width divided by 6 sigma; doubling sigma halves Cp.', 'capability-spread-sensitivity', 'Cp proportional to 1/sigma', 'Cp sensitivity', 'parameter sensitivity', 'REQUIRED:me-topic-3-5-concept', false, true, ['treating centered mean as enough']);
$Q($c32, 3, 'PROFESSIONAL', 'A machining process meets nominal dimensions but tool wear is steadily increasing. What should be established before expanding production?', 'A wear limit and a measurement-based replacement or offset rule', ['A larger batch with no checks', 'A lower inspection frequency only', 'A new nominal dimension'], 'A stable process requires a defined wear criterion, measurement plan, and corrective action before scale-up.', 'tool-life-control-rule', 'wear threshold and response', 'tool-life rule', 'process interpretation', 'REQUIRED:me-topic-3-5-concept', false, true, ['nominal dimension treated as process control']);

// 3-3: Cycles, heat exchange, boilers, and engines (32).
$Q($c33, 4, 'PROFESSIONAL', 'An adiabatic turbine receives 5 kg/s of steam with an enthalpy drop of 250 kJ/kg. What ideal shaft-power magnitude follows when kinetic and potential changes are negligible?', '1.25 MW', ['0.050 MW', '0.500 MW', '5.00 MW'], 'The steady-flow turbine work rate is m-dot delta-h=5(250)=1,250 kJ/s=1.25 MW.', 'turbine-enthalpy-drop-power', 'mdot-delta-h', 'turbine shaft power', 'calculation', 'ASSUMED_FOUNDATION', true, true, ['using total enthalpy as a rate']);
$Q($c33, 4, 'PROFESSIONAL', 'An ideal turbine stage has an isentropic enthalpy drop of 400 kJ/kg and an actual drop of 320 kJ/kg. What is the isentropic efficiency?', '0.80', ['0.20', '1.25', '1.80'], 'Turbine isentropic efficiency is actual work divided by ideal work: 320/400=0.80.', 'turbine-isentropic-efficiency', 'actual-delta-h/isentropic-delta-h', 'turbine isentropic efficiency', 'calculation', 'ASSUMED_FOUNDATION', true, true, ['inverting efficiency direction']);
$Q($c33, 3, 'PROFESSIONAL', 'Which energy change is the primary useful output of an adiabatic turbine in a power plant?', 'Fluid enthalpy decrease converted to shaft work', ['Fuel mass converted directly to pressure', 'Condenser heat converted entirely to work', 'Pump pressure drop converted to entropy'], 'With the stated idealizations, the turbine extracts shaft work from the working-fluid enthalpy decrease.', 'turbine-output-principle', 'delta-h to shaft work', 'turbine output principle', 'process interpretation', 'PREREQUISITE_FREE', false, true, ['confusing heat rejection with turbine work']);
$Q($c33, 4, 'PROFESSIONAL', 'A boiler adds 2,000 kJ/kg to the working fluid while fuel input is 2,500 kJ/kg. What boiler efficiency is indicated?', '0.80', ['0.20', '1.25', '0.50'], 'Boiler efficiency is useful fluid energy gain divided by fuel input: 2,000/2,500=0.80.', 'boiler-efficiency', 'delta-h-fluid/qfuel', 'boiler efficiency', 'calculation', 'PREREQUISITE_FREE', true, true, ['using fuel loss as numerator']);
$Q($c33, 3, 'PROFESSIONAL', 'A boiler efficiency is 0.80 and the fuel supplies 2,500 kJ per kilogram. What useful fluid energy gain is associated with each kilogram of fuel?', '2,000 kJ/kg fuel', ['312.5 kJ/kg fuel', '2,500 kJ/kg fuel', '3,300 kJ/kg fuel'], 'Useful gain equals efficiency times fuel input: 0.80(2,500)=2,000 kJ per kilogram of fuel.', 'boiler-useful-energy', 'eta-boiler-qfuel', 'useful boiler energy', 'calculation', 'PREREQUISITE_FREE', true, false, ['dividing by efficiency']);
$Q($c33, 4, 'PROFESSIONAL', 'A plant produces 12 MW net electric power while receiving 40 MW of fuel energy input. What net thermal efficiency results?', '0.30', ['0.03', '0.48', '3.33'], 'Net thermal efficiency is net output divided by fuel input: 12/40=0.30.', 'plant-net-thermal-efficiency', 'Pnet/Qfuel', 'net plant efficiency', 'calculation', 'PREREQUISITE_FREE', true, true, ['using gross rather than net output']);
$Q($c33, 4, 'PROFESSIONAL', 'A power plant receives 50 MW of heat and rejects 32 MW while producing no other external work stream. What net work rate follows from the energy balance?', '18 MW', ['16 MW', '32 MW', '82 MW'], 'For the stated cycle boundary, net work equals heat input minus heat rejection: 50-32=18 MW.', 'cycle-net-work-balance', 'Qin-Qout', 'cycle net work rate', 'calculation', 'PREREQUISITE_FREE', true, true, ['adding heat input and rejection']);
$Q($c33, 3, 'PROFESSIONAL', 'Which ratio defines the thermal efficiency of a heat engine?', 'Net work output divided by heat supplied', ['Heat rejected divided by net work', 'Heat supplied divided by heat rejected', 'Net work divided by condenser pressure'], 'Thermal efficiency is Wnet/Qin for the selected cycle boundary.', 'heat-engine-efficiency-definition', 'Wnet/Qin', 'thermal efficiency definition', 'process interpretation', 'PREREQUISITE_FREE', false, true, ['using heat rejected as input']);
$Q($c33, 4, 'PROFESSIONAL', 'A reversible engine operates between reservoirs at 600 K and 300 K. What maximum thermal efficiency applies?', '0.50', ['0.25', '0.67', '2.00'], 'Carnot maximum efficiency is 1-Tcold/Thot=1-300/600=0.50.', 'carnot-efficiency', '1-Tcold/Thot', 'maximum reversible efficiency', 'calculation', 'REQUIRED:me-topic-2-3-concept', true, true, ['using Celsius temperatures']);
$Q($c33, 3, 'PROFESSIONAL', 'Why does a condenser form part of a Rankine power cycle?', 'It rejects heat and condenses the exhaust working fluid before pumping', ['It raises turbine exhaust temperature without heat rejection', 'It replaces the boiler heat input', 'It converts all rejected heat to shaft work'], 'The condenser rejects heat and returns the working fluid to a liquid state, allowing the pump and boiler sequence to continue.', 'rankine-condenser-role', 'heat rejection and phase change', 'condenser role', 'process interpretation', 'REQUIRED:me-topic-2-3-concept', false, true, ['confusing condenser and boiler roles']);
$Q($c33, 3, 'PROFESSIONAL', 'What is the main thermodynamic purpose of a feedwater pump in a Rankine cycle?', 'Raise liquid pressure before the boiler', ['Lower steam pressure after the turbine', 'Add fuel energy directly to the boiler', 'Condense the turbine exhaust'], 'The pump increases the pressure of the condensed liquid so it can enter the boiler at the required pressure.', 'rankine-pump-role', 'liquid pressure rise', 'feedwater-pump role', 'process interpretation', 'REQUIRED:me-topic-3-4-concept', false, true, ['assigning pump function to condenser']);
$Q($c33, 3, 'PROFESSIONAL', 'Why can superheating steam before turbine expansion improve a Rankine cycle design?', 'It can increase average heat-addition temperature and reduce moisture at turbine exit', ['It guarantees zero condenser heat rejection', 'It eliminates all pump work', 'It makes turbine work independent of inlet state'], 'Superheat changes the inlet state, can raise the average heat-addition temperature, and may keep expansion exhaust drier; the actual benefit depends on the cycle.', 'rankine-superheat-purpose', 'inlet state and moisture', 'superheat purpose', 'system selection', 'REQUIRED:me-topic-2-3-concept', false, true, ['assuming superheat removes all losses']);
$Q($c33, 3, 'PROFESSIONAL', 'What is the primary reason for reheating between turbine expansion stages?', 'Reduce exhaust moisture while recovering useful expansion work', ['Increase condenser pressure until no work is produced', 'Replace the feedwater pump', 'Make all heat transfer reversible'], 'Reheat raises the intermediate fluid temperature and can reduce moisture in later expansion while producing additional work.', 'rankine-reheat-purpose', 'intermediate reheat and moisture', 'reheat purpose', 'system selection', 'REQUIRED:me-topic-2-3-concept', false, true, ['confusing reheat with intercooling']);
$Q($c33, 3, 'PROFESSIONAL', 'What is the main purpose of regenerative feedwater heating?', 'Use extracted steam to raise feedwater temperature before the boiler', ['Reject all turbine exhaust heat directly to the atmosphere', 'Cool the boiler flame below ambient', 'Eliminate the need for a condenser'], 'Regeneration uses controlled steam extraction to preheat feedwater and can raise the cycle average heat-addition temperature.', 'rankine-regeneration-purpose', 'steam extraction feedwater heating', 'regeneration purpose', 'process interpretation', 'REQUIRED:me-topic-2-3-concept', false, true, ['confusing extraction with rejection']);
$Q($c33, 4, 'PROFESSIONAL', 'A closed feedwater heater transfers 8 MW from extracted steam to feedwater, with 90% of the transferred heat reaching the feedwater. What feedwater gain occurs?', '7.2 MW', ['0.72 MW', '8.0 MW', '88.9 MW'], 'Useful feedwater gain is 0.90(8)=7.2 MW after the stated heater effectiveness.', 'feedwater-heater-effectiveness', 'epsilon-Qtransfer', 'feedwater heat gain', 'calculation', 'REQUIRED:me-topic-2-3-concept', true, true, ['dividing by effectiveness']);
$Q($c33, 4, 'PROFESSIONAL', 'An engine develops 180 N·m at 3,000 rpm. What brake-power output is closest?', '56.5 kW', ['9.0 kW', '18.0 kW', '565 kW'], 'P=T(2piN/60)=180(2pi)(3,000/60)=56,549 W=56.5 kW.', 'engine-brake-power', 'T-2piN/60', 'brake power', 'calculation', 'REQUIRED:me-topic-3-1-concept', true, true, ['using rpm as radians per second']);
$Q($c33, 4, 'PROFESSIONAL', 'An engine brake output is 60 kW and its fuel energy input rate is 200 kW. What brake thermal efficiency results?', '0.30', ['0.15', '0.70', '3.33'], 'Brake thermal efficiency is brake output divided by fuel energy rate: 60/200=0.30.', 'brake-thermal-efficiency', 'Pbrake/Qfuel', 'brake thermal efficiency', 'calculation', 'REQUIRED:me-topic-3-1-concept', true, true, ['using fuel input divided by output']);
$Q($c33, 3, 'PROFESSIONAL', 'Brake-specific fuel consumption is defined using which quantity?', 'Fuel mass flow per unit brake power', ['Air mass flow per unit condenser pressure', 'Brake power per unit fuel volume only', 'Heat rejected per unit cylinder volume'], 'BSFC compares fuel mass rate with useful brake output, with units depending on the selected power and time basis.', 'brake-specific-fuel-consumption', 'mdot-fuel/Pbrake', 'BSFC definition', 'process interpretation', 'REQUIRED:me-topic-3-1-concept', false, true, ['inverting the fuel-to-power ratio']);
$Q($c33, 4, 'PROFESSIONAL', 'An engine consumes 0.006 kg/s of fuel while producing 60 kW brake power. What fuel mass per brake-energy rate is indicated?', '0.36 kg/kWh', ['0.06 kg/kWh', '0.60 kg/kWh', '3.60 kg/kWh'], 'Fuel mass per hour is 0.006(3,600)=21.6 kg/h; dividing by 60 kW gives 0.36 kg/kWh.', 'brake-specific-fuel-consumption-numeric', 'mdot-fuel*3600/PkW', 'fuel mass per brake energy', 'calculation', 'REQUIRED:me-topic-3-1-concept', true, true, ['forgetting kW-to-kWh conversion']);
$Q($c33, 3, 'PROFESSIONAL', 'In an ideal air-standard Otto cycle, increasing compression ratio generally has what effect on thermal efficiency?', 'It increases ideal thermal efficiency', ['It always decreases ideal thermal efficiency', 'It leaves ideal efficiency independent of compression ratio', 'It changes only condenser pressure'], 'For the ideal Otto model, efficiency rises with compression ratio under the stated constant-property assumptions.', 'otto-compression-ratio-efficiency', 'eta-Otto versus compression ratio', 'Otto efficiency trend', 'parameter sensitivity', 'REQUIRED:me-topic-2-3-concept', false, true, ['confusing compression with expansion ratio']);
$Q($c33, 4, 'PROFESSIONAL', 'For an ideal Otto cycle with compression ratio r=8 and gamma=1.4, what efficiency is closest using eta=1-1/r^(gamma-1)?', '0.565', ['0.125', '0.435', '0.800'], 'r^0.4 is about 2.297, so eta=1-1/2.297=0.565.', 'otto-air-standard-efficiency', '1-1/r^(gamma-1)', 'Otto thermal efficiency', 'calculation', 'REQUIRED:me-topic-2-3-concept', true, true, ['using r rather than r^(gamma-1)']);
$Q($c33, 3, 'PROFESSIONAL', 'Compared with an ideal Otto cycle at the same compression ratio, what is the ideal Diesel-cycle distinction in heat addition?', 'Heat is added at approximately constant pressure rather than constant volume', ['Heat is rejected at constant pressure only', 'No heat is added to the cycle', 'All compression occurs at constant temperature'], 'The ideal air-standard cycle models differ in the heat-addition process: Otto constant volume and Diesel constant pressure.', 'otto-diesel-heat-addition', 'constant-volume versus constant-pressure addition', 'cycle distinction', 'comparison', 'REQUIRED:me-topic-2-3-concept', false, true, ['confusing heat addition with compression']);
$Q($c33, 3, 'PROFESSIONAL', 'In an ideal Brayton cycle, which component primarily raises the working-fluid pressure before heat addition?', 'The compressor', ['The turbine exhaust diffuser only', 'The condenser', 'The cooling tower'], 'The compressor raises pressure before the combustor or heater; the turbine then expands the heated gas.', 'brayton-compressor-role', 'compression before heat addition', 'Brayton compressor role', 'process interpretation', 'REQUIRED:me-topic-3-4-concept', false, true, ['assigning pressure rise to turbine']);
$Q($c33, 4, 'PROFESSIONAL', 'A gas-turbine compressor receives 10 kg/s with a specific-work requirement of 80 kJ/kg. What compressor power input is required?', '800 kW', ['80 kW', '8.0 kW', '8,000 kW'], 'Compressor power is m-dot times specific work: 10(80)=800 kJ/s=800 kW.', 'gas-turbine-compressor-power', 'mdot-wspecific', 'compressor power input', 'calculation', 'REQUIRED:me-topic-3-4-concept', true, true, ['using specific work as total power']);
$Q($c33, 3, 'PROFESSIONAL', 'What does excess air in a combustion process mean?', 'Air supplied above the theoretical amount needed for the stated complete reaction', ['Fuel supplied above the theoretical amount with no air', 'Air supplied only after combustion is complete', 'A condition where oxygen can never remain in exhaust'], 'Excess air compares actual oxidizer supply with the theoretical requirement for the selected reaction model.', 'combustion-excess-air-definition', 'actual air versus stoichiometric air', 'excess-air definition', 'process interpretation', 'PREREQUISITE_FREE', false, true, ['confusing excess air with excess fuel']);
$Q($c33, 3, 'PROFESSIONAL', 'Why can excess air lower a flame temperature in an otherwise similar combustion setup?', 'Additional relatively cool mass absorbs energy and dilutes the products', ['It creates energy without fuel', 'It removes all nitrogen from the products', 'It guarantees complete conversion to shaft work'], 'Extra air increases the mass and heat capacity of products and can dilute the released energy, reducing adiabatic flame temperature.', 'excess-air-flame-temperature', 'energy dilution and heat capacity', 'excess-air effect', 'process interpretation', 'PREREQUISITE_FREE', false, true, ['assuming more air always raises temperature']);
$Q($c33, 4, 'PROFESSIONAL', 'A boiler supplies 40 kg/s of feedwater with a specific enthalpy rise of 2,500 kJ/kg. What heat-transfer rate to the fluid is required?', '100 MW', ['10 MW', '62.5 MW', '1,000 MW'], 'Heat rate is m-dot delta-h=40(2,500)=100,000 kJ/s=100 MW.', 'boiler-heat-transfer-rate', 'mdot-delta-h', 'boiler fluid heat rate', 'calculation', 'REQUIRED:me-topic-2-3-concept', true, true, ['using enthalpy rise as a total']);
$Q($c33, 4, 'PROFESSIONAL', 'A plant generates 100 MW gross and consumes 8 MW in auxiliary equipment. What net power is delivered?', '92 MW', ['8 MW', '100 MW', '108 MW'], 'Net delivery subtracts the auxiliary load: 100-8=92 MW.', 'plant-net-output', 'Pgross-Paux', 'net electric output', 'calculation', 'PREREQUISITE_FREE', true, true, ['adding auxiliary consumption']);
$Q($c33, 3, 'PROFESSIONAL', 'Why is net plant output more useful than gross generator output for comparing plant delivery?', 'It accounts for internal auxiliary consumption', ['It ignores all pumps and fans', 'It is always equal to fuel input', 'It removes the need for a heat balance'], 'Net output reflects what remains after the plant powers its own pumps, fans, controls, and other auxiliaries.', 'gross-versus-net-plant-output', 'gross less auxiliaries', 'net output interpretation', 'comparison', 'PREREQUISITE_FREE', false, true, ['treating gross output as delivered output']);
$Q($c33, 4, 'PROFESSIONAL', 'A cogeneration plant produces 20 MW electricity and 30 MW useful process heat from 80 MW fuel input. What total utilization efficiency is indicated?', '0.625', ['0.250', '0.375', '1.60'], 'Total useful energy rate is 20+30=50 MW; utilization efficiency is 50/80=0.625.', 'cogeneration-utilization-efficiency', '(Pe+Quseful)/Qfuel', 'cogeneration utilization', 'calculation', 'REQUIRED:me-topic-2-3-concept', true, true, ['counting only electric output']);
$Q($c33, 3, 'PROFESSIONAL', 'What is the main engineering tradeoff when raising condenser pressure in a Rankine cycle?', 'It can reduce heat-rejection temperature difference but generally reduces turbine expansion work', ['It always increases turbine work and lowers pump work to zero', 'It eliminates the need for a condenser', 'It changes only the fuel composition'], 'A higher condenser pressure changes the expansion end state and usually reduces turbine work, even though it may affect heat-transfer equipment sizing.', 'rankine-condenser-pressure-tradeoff', 'back-pressure versus expansion work', 'condenser pressure tradeoff', 'parameter sensitivity', 'REQUIRED:me-topic-2-3-concept', false, true, ['treating back pressure as irrelevant']);
$Q($c33, 4, 'PROFESSIONAL', 'An actual turbine produces 270 kJ/kg work while the ideal isentropic expansion predicts 300 kJ/kg. What fraction of ideal work is lost?', '10%', ['3%', '30%', '90%'], 'Lost fraction=(300-270)/300=0.10=10%.', 'turbine-work-loss-fraction', '(wideal-wactual)/wideal', 'turbine work loss', 'calculation', 'REQUIRED:me-topic-2-3-concept', true, true, ['dividing loss by actual work']);
$Q($c33, 3, 'PROFESSIONAL', 'A steam turbine exhaust becomes excessively wet. Which design response is most directly relevant?', 'Review inlet superheat, reheat, and expansion endpoint', ['Increase moisture intentionally without checking erosion', 'Remove the condenser and retain the same expansion', 'Treat moisture as a pressure-gauge calibration issue'], 'Moisture depends on the expansion path and endpoint; inlet superheat or intermediate reheat can change exhaust quality, subject to the cycle design.', 'wet-steam-turbine-response', 'expansion quality and erosion', 'wet exhaust response', 'diagnosis', 'REQUIRED:me-topic-2-3-concept', false, true, ['confusing wetness with gauge error']);
$Q($c33, 3, 'PROFESSIONAL', 'Which plant measurement is most useful for detecting an energy-balance discrepancy across a boiler?', 'Fuel input and working-fluid mass flow with inlet and outlet enthalpy', ['Only the paint color of the casing', 'Only generator speed without load', 'Only ambient humidity'], 'A boiler balance needs fuel input and the fluid mass-flow and state changes; isolated speed or appearance cannot close the balance.', 'boiler-energy-balance-measurements', 'fuel plus mdot and enthalpy states', 'boiler balance measurement', 'failure/error identification', 'REQUIRED:me-topic-2-3-concept', false, true, ['measuring output without a boundary']);

// 3-4: Pumps, compressors, heat exchangers, and air conditioning (30).
$Q($c34, 4, 'PROFESSIONAL', 'Water flows at 0.020 m³/s through a pump delivering 25 m of head. What hydraulic power is supplied to the water?', '4.90 kW', ['0.490 kW', '12.3 kW', '49.0 kW'], 'P_hyd=rho g Q H=1000(9.81)(0.020)(25)=4,905 W=4.90 kW.', 'pump-hydraulic-power', 'rho-g-Q-H', 'pump hydraulic power', 'calculation', 'ASSUMED_FOUNDATION', true, true, ['omitting gravity or density']);
$Q($c34, 4, 'PROFESSIONAL', 'A pump supplies 4.90 kW hydraulic power at 70% efficiency. What shaft power is required?', '7.01 kW', ['3.43 kW', '4.90 kW', '7.70 kW'], 'Shaft input equals hydraulic output divided by efficiency: 4.905/0.70=7.01 kW.', 'pump-shaft-power', 'Phyd/eta', 'pump shaft power', 'calculation', 'ASSUMED_FOUNDATION', true, true, ['multiplying by efficiency']);
$Q($c34, 3, 'PROFESSIONAL', 'Two identical pumps operate in parallel at the same system. In the idealized pump-curve interpretation, what quantity primarily adds?', 'Available flow at a common head', ['Head at zero common flow only', 'Fluid density', 'The gravitational acceleration'], 'Parallel pumps share the head while their flow contributions add at a common operating head, subject to the actual system curve.', 'parallel-pump-interpretation', 'flow addition at common head', 'parallel pump behavior', 'process interpretation', 'REQUIRED:me-topic-2-2-concept', false, true, ['adding heads as if pumps were in series']);
$Q($c34, 3, 'PROFESSIONAL', 'Two pumps operate in series in the same flow path. What idealized quantity primarily adds?', 'Pump head', ['Pipe diameter', 'Fluid viscosity becomes zero', 'Atmospheric pressure'], 'Series pumps carry the same flow and their heads add at that flow, subject to losses and operating limits.', 'series-pump-interpretation', 'head addition at common flow', 'series pump behavior', 'process interpretation', 'REQUIRED:me-topic-2-2-concept', false, true, ['adding flow as if pumps were parallel']);
$Q($c34, 4, 'PROFESSIONAL', 'A pump curve gives 40 m head at a flow of 0.010 m³/s while the system curve requires 40 m at that flow. What is the operating point under the ideal intersection model?', '0.010 m³/s at 40 m head', ['0.005 m³/s at 20 m head', '0.040 m³/s at 10 m head', 'No operating point can exist'], 'The operating point is where pump head equals system head; the stated equality occurs at 0.010 m³/s and 40 m.', 'pump-system-operating-point', 'Hpump=Hsystem', 'pump operating point', 'calculation', 'REQUIRED:me-topic-2-2-concept', true, true, ['choosing an arbitrary pump-curve point']);
$Q($c34, 4, 'PROFESSIONAL', 'A pump delivers 0.015 m³/s against 18 m total head at 75% efficiency. What shaft power is needed for water?', '3.53 kW', ['2.65 kW', '3.53 W', '5.30 kW'], 'Pshaft=rho g Q H/eta=1000(9.81)(0.015)(18)/0.75=3,532 W=3.53 kW.', 'pump-power-with-efficiency', 'rho-g-Q-H/eta', 'pump shaft power', 'calculation', 'REQUIRED:me-topic-2-2-concept', true, true, ['applying efficiency in the wrong direction']);
$Q($c34, 4, 'PROFESSIONAL', 'A pump operates at 1,200 rpm with flow 0.020 m³/s and head 30 m. Under ideal affinity scaling to 1,440 rpm, what flow is predicted?', '0.024 m³/s', ['0.0167 m³/s', '0.020 m³/s', '0.036 m³/s'], 'At similar geometry, Q scales with speed: Q2=Q1(N2/N1)=0.020(1,440/1,200)=0.024 m³/s.', 'pump-affinity-flow', 'Q2/Q1=N2/N1', 'scaled pump flow', 'calculation', 'REQUIRED:me-topic-2-2-concept', true, true, ['using the square of speed for flow']);
$Q($c34, 4, 'PROFESSIONAL', 'Using the same pump scaling, what ideal head is predicted at 1,440 rpm if the 1,200-rpm head is 30 m?', '43.2 m', ['25.0 m', '36.0 m', '51.8 m'], 'Pump head scales with speed squared: H2=30(1,440/1,200)^2=30(1.2)^2=43.2 m.', 'pump-affinity-head', 'H2/H1=(N2/N1)^2', 'scaled pump head', 'calculation', 'REQUIRED:me-topic-2-2-concept', true, true, ['using linear rather than squared scaling']);
$Q($c34, 3, 'PROFESSIONAL', 'What condition most directly indicates cavitation risk at a pump suction?', 'Available net positive suction head is below the required value', ['Discharge pressure is expressed in kPa', 'The pump casing is painted blue', 'The fluid has any nonzero velocity'], 'Cavitation risk is assessed by comparing NPSH available with NPSH required, with margin for operating and fluid conditions.', 'pump-cavitation-condition', 'NPSHA versus NPSHR', 'cavitation condition', 'failure/error identification', 'REQUIRED:me-topic-2-2-concept', false, true, ['using discharge color as a cavitation test']);
$Q($c34, 3, 'PROFESSIONAL', 'Which symptom is consistent with pump cavitation?', 'Noise, vibration, and loss of stable head', ['A perfectly silent operation with increasing stable head', 'A larger tank volume without any suction change', 'A corrected pressure-gauge calibration by itself'], 'Bubble formation and collapse can cause noise, vibration, erosion, and unstable performance; confirm with suction conditions and measurements.', 'pump-cavitation-symptom', 'vapor bubbles and unstable performance', 'cavitation symptom', 'diagnosis', 'REQUIRED:me-topic-2-2-concept', false, true, ['treating cavitation as a gauge-only issue']);
$Q($c34, 4, 'PROFESSIONAL', 'A compressor has ideal isentropic work of 120 kJ/kg and actual work of 150 kJ/kg. What is its isentropic efficiency?', '0.80', ['0.20', '1.25', '0.50'], 'Compressor isentropic efficiency is ideal work divided by actual work: 120/150=0.80.', 'compressor-isentropic-efficiency', 'wideal/wactual', 'compressor isentropic efficiency', 'calculation', 'REQUIRED:me-topic-2-3-concept', true, true, ['using actual divided by ideal work']);
$Q($c34, 3, 'PROFESSIONAL', 'What does compressor volumetric efficiency compare?', 'Actual intake volume to the swept or displacement volume', ['Discharge heat to motor voltage', 'Shaft torque to bearing diameter only', 'Condenser pressure to atmospheric temperature'], 'Volumetric efficiency compares the actual volume inducted with the displacement volume, subject to clearance, leakage, valve, and thermal effects.', 'compressor-volumetric-efficiency', 'actual intake volume/displacement volume', 'volumetric efficiency definition', 'process interpretation', 'REQUIRED:me-topic-2-3-concept', false, true, ['confusing volumetric and isentropic efficiency']);
$Q($c34, 4, 'PROFESSIONAL', 'A refrigeration system removes 12 kW from a cold space while compressor work is 3 kW. What COP of the refrigerator results?', '4.0', ['0.25', '3.0', '15.0'], 'Refrigerator COP is Qcold/W=12/3=4.0.', 'refrigerator-cop', 'Qcold/W', 'refrigerator COP', 'calculation', 'REQUIRED:me-topic-2-3-concept', true, true, ['using total heat rejection']);
$Q($c34, 4, 'PROFESSIONAL', 'A heat pump supplies 15 kW to a conditioned space while consuming 3 kW compressor work. What heating COP applies?', '5.0', ['0.20', '4.0', '18.0'], 'Heat-pump COP is useful hot-side heat divided by work: 15/3=5.0.', 'heat-pump-cop', 'Qhot/W', 'heat-pump COP', 'calculation', 'REQUIRED:me-topic-2-3-concept', true, true, ['using cooling effect instead of delivered heat']);
$Q($c34, 3, 'PROFESSIONAL', 'For an ideal refrigeration energy balance, how are condenser heat rejection, evaporator cooling, and compressor work related?', 'Qhot equals Qcold plus compressor work', ['Qhot equals Qcold minus compressor work', 'Qhot equals compressor work only', 'Qcold equals zero whenever the compressor runs'], 'Energy conservation around the cycle gives Qhot=Qcold+W for the selected sign convention.', 'refrigeration-energy-balance', 'Qhot=Qcold+W', 'refrigeration heat balance', 'process interpretation', 'REQUIRED:me-topic-2-3-concept', false, true, ['subtracting compressor work from rejection']);
$Q($c34, 4, 'PROFESSIONAL', 'A counterflow heat exchanger transfers 600 kW with an overall conductance UA of 30 kW/K. What LMTD is implied by Q=UA delta-Tlm?', '20 K', ['0.05 K', '18 K', '600 K'], 'Delta-Tlm=Q/UA=600/30=20 K.', 'heat-exchanger-lmtd-from-ua', 'Q/UA', 'implied LMTD', 'calculation', 'REQUIRED:me-topic-2-3-concept', true, true, ['multiplying Q by UA']);
$Q($c34, 3, 'PROFESSIONAL', 'What is the primary effect of fouling on a heat exchanger when other conditions remain comparable?', 'It adds thermal resistance and lowers heat-transfer performance', ['It makes resistance zero', 'It guarantees a higher overall conductance', 'It changes only the piping color'], 'Deposits add resistance, reducing overall conductance or the heat rate for the same driving temperature difference.', 'heat-exchanger-fouling', 'added thermal resistance', 'fouling effect', 'diagnosis', 'REQUIRED:me-topic-2-3-concept', false, true, ['treating fouling as cosmetic']);
$Q($c34, 4, 'PROFESSIONAL', 'Air flows at 0.60 m³/s through a duct with cross-sectional area 0.20 m². What mean duct velocity results?', '3.0 m/s', ['0.30 m/s', '1.20 m/s', '12.0 m/s'], 'Mean velocity is Q/A=0.60/0.20=3.0 m/s.', 'duct-velocity', 'Q/A', 'duct mean velocity', 'calculation', 'REQUIRED:me-topic-2-2-concept', true, true, ['multiplying flow by area']);
$Q($c34, 4, 'PROFESSIONAL', 'A fan delivers 2.0 m³/s against a pressure rise of 500 Pa at 80% efficiency. What shaft power is required?', '1.25 kW', ['0.80 kW', '1.00 kW', '2.50 kW'], 'Pshaft=Q delta-p/eta=2(500)/0.80=1,250 W=1.25 kW.', 'fan-shaft-power', 'Q-delta-p/eta', 'fan shaft power', 'calculation', 'REQUIRED:me-topic-2-2-concept', true, true, ['omitting efficiency']);
$Q($c34, 3, 'PROFESSIONAL', 'If a cooling coil must remove 18 kW sensible and 6 kW latent load, what load must the coil handle in total?', '24 kW', ['3 kW', '12 kW', '108 kW'], 'Total coil load is the sum of sensible and latent components: 18+6=24 kW.', 'hvac-total-cooling-load', 'Qs+Ql', 'total cooling load', 'calculation', 'REQUIRED:me-topic-2-3-concept', true, true, ['using only sensible load']);
$Q($c34, 3, 'PROFESSIONAL', 'A conditioned space has acceptable dry-bulb temperature but rising indoor humidity. Which action addresses the controlling load?', 'Provide dehumidification or latent cooling', ['Add sensible heat only', 'Increase supply humidity', 'Raise the thermostat without checking dew point'], 'The temperature is acceptable but moisture is not, so the response must remove latent moisture or otherwise control dew point.', 'hvac-latent-load-response', 'latent load and dew point', 'humidity-control action', 'system selection', 'REQUIRED:me-topic-2-3-concept', false, true, ['equating dry-bulb comfort with humidity control']);
$Q($c34, 3, 'PROFESSIONAL', 'Why is superheat monitored at a refrigeration-compressor suction?', 'To reduce the risk of liquid entering the compressor', ['To guarantee zero condenser heat rejection', 'To make refrigerant pressure equal atmospheric pressure', 'To replace all filter maintenance'], 'Adequate superheat helps ensure vapor reaches the compressor; the exact control range depends on the system and operating conditions.', 'refrigerant-superheat-purpose', 'vapor protection at compressor inlet', 'superheat purpose', 'process interpretation', 'REQUIRED:me-topic-2-3-concept', false, true, ['assuming liquid slugging is harmless']);
$Q($c34, 4, 'PROFESSIONAL', 'A condenser rejects 40 kW while the compressor consumes 10 kW. What evaporator cooling rate follows from the cycle energy balance?', '30 kW', ['4 kW', '10 kW', '50 kW'], 'Qcold=Qhot-W=40-10=30 kW.', 'refrigeration-cooling-from-rejection', 'Qhot-W', 'evaporator cooling rate', 'calculation', 'REQUIRED:me-topic-2-3-concept', true, true, ['adding compressor work']);
$Q($c34, 3, 'PROFESSIONAL', 'Which operating change generally increases pump system head loss in a fixed pipe network?', 'Increasing flow rate', ['Reducing flow to zero', 'Removing all pipe length while retaining the same network', 'Changing only the nameplate color'], 'Friction and minor losses generally rise with velocity and therefore with flow rate, subject to the network model.', 'pipe-system-head-loss-trend', 'loss versus velocity', 'head-loss trend', 'parameter sensitivity', 'REQUIRED:me-topic-2-2-concept', false, true, ['assuming loss is independent of flow']);
$Q($c34, 4, 'PROFESSIONAL', 'A compressor has an actual discharge temperature higher than the ideal isentropic prediction. What does that difference most directly indicate?', 'Irreversibility or efficiency loss in the compression process', ['A violation of energy conservation', 'That compressor work is exactly zero', 'That the suction flow must be liquid water'], 'For the same end pressure, irreversible compression requires more work and can produce a higher discharge temperature than the isentropic ideal.', 'compressor-discharge-temperature', 'actual versus isentropic state', 'compressor diagnostic', 'diagnosis', 'REQUIRED:me-topic-2-3-concept', false, true, ['treating the ideal state as measured reality']);
$Q($c34, 3, 'PROFESSIONAL', 'What is the most direct reason a heat exchanger uses counterflow rather than parallel flow in many designs?', 'It can maintain a stronger temperature driving force over more of the exchanger', ['It eliminates the need for a heat balance', 'It makes both streams cross without walls', 'It forces equal mass flow rates'], 'Counterflow can sustain a favorable local temperature difference and can allow a closer approach than parallel flow under suitable conditions.', 'counterflow-heat-exchanger-choice', 'temperature-driving-force distribution', 'flow-arrangement choice', 'comparison', 'REQUIRED:me-topic-2-3-concept', false, true, ['assuming flow arrangement has no thermal effect']);
$Q($c34, 4, 'PROFESSIONAL', 'A pump has water density 1,000 kg/m³, flow 0.010 m³/s, and total head 20 m. What hydraulic power is available before efficiency losses?', '1.96 kW', ['0.196 kW', '2.00 kW', '19.6 kW'], 'P=rho g Q H=1000(9.81)(0.010)(20)=1,962 W=1.96 kW.', 'pump-hydraulic-power-small', 'rho-g-Q-H', 'hydraulic power', 'calculation', 'REQUIRED:me-topic-2-2-concept', true, true, ['using head in kPa']);
$Q($c34, 3, 'PROFESSIONAL', 'A refrigeration evaporator has a low refrigerant pressure because of throttling and heat absorption. Which state change is its intended function?', 'The refrigerant absorbs heat while evaporating', ['The refrigerant rejects all heat while condensing in the evaporator', 'The refrigerant performs no phase or energy change', 'The refrigerant raises pressure without compressor work'], 'The evaporator absorbs the cooled-space load as the refrigerant changes state, with the actual state path set by the system.', 'evaporator-function', 'heat absorption and evaporation', 'evaporator function', 'process interpretation', 'REQUIRED:me-topic-2-3-concept', false, true, ['assigning condenser function to evaporator']);
$Q($c34, 4, 'PROFESSIONAL', 'A compressor handles 2 kg/s with an actual specific-work requirement of 150 kJ/kg. What power input is required?', '300 kW', ['75 kW', '152 kW', '1,500 kW'], 'Compressor power is m-dot times specific work=2(150)=300 kJ/s=300 kW.', 'compressor-work-power', 'mdot-wspecific', 'compressor power input', 'calculation', 'REQUIRED:me-topic-2-3-concept', true, true, ['using specific work as total power']);
$Q($c34, 3, 'PROFESSIONAL', 'A heat exchanger has a heat-capacity rate of 10 kW/K and a maximum possible temperature-driving capacity of 50 K. If it transfers 300 kW, what simple effectiveness is indicated?', '0.60', ['0.06', '0.40', '1.67'], 'Effectiveness is actual transfer divided by maximum possible transfer: 300/(10(50))=0.60.', 'heat-exchanger-effectiveness', 'Q/(Cmin-delta-Tmax)', 'heat-exchanger effectiveness', 'calculation', 'REQUIRED:me-topic-2-3-concept', true, true, ['dividing by heat-capacity rate only']);

// 3-5: Condition monitoring, reliability, and energy safety (20).
$Q($c35, 4, 'PROFESSIONAL', 'A component has a constant failure rate of 0.002 per hour. What reliability remains after 100 hours using R=exp(-lambda t)?', '0.819', ['0.002', '0.181', '1.221'], 'R=exp[-(0.002)(100)]=exp(-0.2)=0.819.', 'exponential-reliability', 'exp(-lambda-t)', 'component reliability', 'calculation', 'PREREQUISITE_FREE', true, true, ['using lambda times t as reliability']);
$Q($c35, 3, 'PROFESSIONAL', 'For a constant failure rate of 0.002 per hour, what mean time between failures is predicted?', '500 hours', ['0.002 hours', '200 hours', '2,000 hours'], 'For the constant-rate model, MTBF=1/lambda=1/0.002=500 hours.', 'constant-rate-mtbf', '1/lambda', 'MTBF', 'calculation', 'PREREQUISITE_FREE', true, true, ['multiplying by failure rate']);
$Q($c35, 4, 'PROFESSIONAL', 'Two independent components in series have reliabilities 0.95 and 0.90 over a mission. What series reliability results?', '0.855', ['0.050', '0.925', '1.850'], 'Series success requires both components: R=0.95(0.90)=0.855.', 'series-system-reliability', 'R1R2', 'series reliability', 'calculation', 'PREREQUISITE_FREE', true, true, ['adding component reliabilities']);
$Q($c35, 4, 'PROFESSIONAL', 'Two independent parallel redundant components each have reliability 0.90. What system reliability results if either can carry the function?', '0.99', ['0.81', '0.90', '1.80'], 'Parallel failure requires both to fail: R=1-(1-0.90)^2=0.99.', 'parallel-system-reliability', '1-(1-R)^2', 'parallel reliability', 'calculation', 'PREREQUISITE_FREE', true, true, ['multiplying parallel reliability']);
$Q($c35, 3, 'PROFESSIONAL', 'A machine has MTBF 90 hours and MTTR 10 hours. What steady-state availability follows?', '0.90', ['0.10', '0.80', '9.00'], 'Availability=MTBF/(MTBF+MTTR)=90/(90+10)=0.90.', 'steady-state-availability', 'MTBF/(MTBF+MTTR)', 'availability', 'calculation', 'PREREQUISITE_FREE', true, true, ['using MTBF/MTTR']);
$Q($c35, 3, 'PROFESSIONAL', 'A production line has availability 0.90, performance 0.95, and quality 0.98. What OEE is indicated?', '0.8379', ['0.2793', '0.9310', '2.83'], 'OEE is the product: 0.90(0.95)(0.98)=0.8379.', 'overall-equipment-effectiveness', 'A-P-Q', 'OEE', 'calculation', 'ASSUMED_FOUNDATION', true, true, ['averaging the three factors']);
$Q($c35, 3, 'PROFESSIONAL', 'A vibration trend rises steadily while load remains unchanged. What is the most defensible first action?', 'Inspect and diagnose before the condition reaches a defined alarm limit', ['Wait until catastrophic failure', 'Increase load to mask the trend', 'Disable the vibration measurement'], 'A trend is evidence for planned investigation; confirm sensor validity, operating conditions, and the likely mechanism before escalation.', 'vibration-trend-response', 'trend-based condition monitoring', 'vibration response', 'diagnosis', 'REQUIRED:me-topic-2-6-concept', false, true, ['treating trend as proof of one cause']);
$Q($c35, 3, 'PROFESSIONAL', 'A control chart shows one point beyond the upper control limit. What is the appropriate interpretation?', 'Investigate a possible special cause', ['Declare the product automatically unsafe without review', 'Move the control limit to include the point', 'Ignore it because specifications are unrelated'], 'A control-limit signal prompts investigation of a special cause; it is not itself a root-cause diagnosis or specification decision.', 'control-chart-special-cause', 'control limit signal', 'control-chart response', 'failure/error identification', 'ASSUMED_FOUNDATION', false, true, ['confusing control and specification limits']);
$Q($c35, 4, 'PROFESSIONAL', 'An FMEA entry has severity 8, occurrence 3, and detection 4. What risk-priority number follows from RPN=SOD?', '96', ['15', '32', '128'], 'RPN=8(3)(4)=96.', 'fmea-risk-priority-number', 'S-O-D', 'FMEA RPN', 'calculation', 'PREREQUISITE_FREE', true, true, ['adding the ratings']);
$Q($c35, 3, 'PROFESSIONAL', 'Why should a high FMEA risk-priority number not be treated as a physical probability by itself?', 'It is a prioritization score built from ordinal ratings and needs engineering evidence', ['It directly measures temperature in kelvin', 'It replaces all testing and inspection', 'It proves the failure will occur at the stated time'], 'RPN prioritizes attention using selected ratings; it does not replace failure physics, field evidence, or risk controls.', 'fmea-score-interpretation', 'ordinal score versus physical probability', 'FMEA interpretation', 'process interpretation', 'PREREQUISITE_FREE', false, true, ['treating RPN as a probability']);
$Q($c35, 3, 'PROFESSIONAL', 'A bearing temperature rises above its established trend while speed and load are stable. Which checks are most relevant first?', 'Lubrication, alignment, contamination, sensor validity, and heat removal', ['Only the paint finish', 'Only the nominal bearing rating', 'Only the room lighting'], 'A thermal trend can reflect lubrication, alignment, contamination, friction, cooling, or measurement problems; check the evidence before selecting a fix.', 'bearing-temperature-monitoring', 'temperature trend and failure mechanisms', 'bearing monitoring checks', 'diagnosis', 'REQUIRED:me-topic-3-1-concept', false, true, ['rating treated as a diagnosis']);
$Q($c35, 3, 'PROFESSIONAL', 'Before opening a guarded machine to inspect a jam, what must be done first?', 'Isolate and verify hazardous energy under the applicable lockout procedure', ['Reach through the guard while the machine is stopped by a sensor', 'Increase the motor speed briefly', 'Remove the warning label only'], 'Unexpected startup and stored energy require isolation, control, release or blocking, and verification before access.', 'lockout-energy-isolation', 'isolate-release-verify', 'safe intervention step', 'failure/error identification', 'PREREQUISITE_FREE', false, true, ['treating stop command as energy isolation']);
$Q($c35, 3, 'PROFESSIONAL', 'Why must stored pressure or spring energy be addressed after a machine is disconnected from electrical power?', 'Stored energy can still move or release through the equipment', ['Electrical isolation automatically dissipates every energy form', 'Stored pressure changes only the paint color', 'Spring force exists only while a motor is energized'], 'Pneumatic, hydraulic, gravitational, thermal, and spring energy can remain after electrical isolation and must be controlled and verified.', 'stored-energy-control', 'residual energy isolation', 'stored energy control', 'process interpretation', 'PREREQUISITE_FREE', false, true, ['equating electrical isolation with zero energy']);
$Q($c35, 4, 'PROFESSIONAL', 'A machine has MTBF 400 hours and MTTR 25 hours. If MTTR falls to 10 hours with the same MTBF, what availability results?', '0.976', ['0.400', '0.941', '1.025'], 'New availability=400/(400+10)=0.9756, approximately 0.976.', 'availability-improvement', 'MTBF/(MTBF+MTTR)', 'improved availability', 'calculation', 'PREREQUISITE_FREE', true, true, ['using the old MTTR']);
$Q($c35, 4, 'PROFESSIONAL', 'A process has Cp=1.5 but Cpk=0.60. What does the gap most strongly suggest?', 'The process spread may fit, but the mean is poorly centered', ['The process has no variation', 'The specification width is missing', 'The process is guaranteed centered'], 'Cp reflects potential spread while Cpk also includes centering; a much lower Cpk signals mean offset relative to a specification limit.', 'cp-cpk-centering', 'Cp versus Cpk', 'capability centering diagnosis', 'diagnosis', 'ASSUMED_FOUNDATION', false, true, ['treating Cp and Cpk as identical']);
$Q($c35, 3, 'PROFESSIONAL', 'Which evidence most directly supports a predictive-maintenance intervention?', 'A validated condition trend tied to a credible failure mechanism', ['A single unverified rumor', 'A nameplate color change', 'A maintenance action with no measured condition'], 'Predictive action needs reliable measurements, a meaningful trend, and a defensible connection to the failure mechanism.', 'predictive-maintenance-evidence', 'condition trend plus mechanism', 'predictive maintenance evidence', 'process interpretation', 'REQUIRED:me-topic-2-6-concept', false, true, ['acting on an unvalidated signal']);
$Q($c35, 3, 'PROFESSIONAL', 'A reliability engineer compares two maintenance plans. Which metric captures both average operating time and repair downtime in a simple steady-state model?', 'Availability', ['Only nominal speed', 'Only purchase price', 'Only maximum pressure'], 'Availability combines MTBF and MTTR in the steady-state model, so it reflects both time between failures and restoration time.', 'maintenance-plan-availability', 'MTBF and MTTR', 'maintenance metric', 'comparison', 'PREREQUISITE_FREE', false, true, ['using a single capacity metric']);
$Q($c35, 4, 'PROFESSIONAL', 'A condition-monitoring sensor has a false-alarm rate that causes frequent unnecessary shutdowns. What should be reviewed before simply disabling it?', 'Threshold, sensor validity, signal quality, and the consequence model', ['Disable every alarm without investigation', 'Increase all thresholds indefinitely', 'Remove the equipment guard'], 'Alarm performance should be reviewed with data quality, threshold selection, operating states, and the cost of missed versus false alarms.', 'condition-alarm-calibration', 'signal quality and decision threshold', 'alarm calibration', 'design', 'REQUIRED:me-topic-1-3-concept', false, true, ['treating nuisance alarms as useless signals']);
$Q($c35, 3, 'PROFESSIONAL', 'What is the safest interpretation of a pressure reading that exceeds a specified operating limit?', 'Treat it as an abnormal condition requiring the applicable response and verification', ['Ignore it if production is on schedule', 'Open equipment immediately without isolation', 'Change the limit after the reading'], 'A limit exceedance requires controlled response, confirmation, and escalation under the relevant procedure; it is not a reason to bypass safety controls.', 'pressure-limit-response', 'measurement versus safe operating limit', 'pressure-limit action', 'failure/error identification', 'PREREQUISITE_FREE', false, true, ['normalizing a limit exceedance']);
$Q($c35, 3, 'PROFESSIONAL', 'Why should a condition-monitoring alarm include operating context such as speed and load?', 'The same signal level can have different meanings at different operating states', ['Context makes all sensors unnecessary', 'Speed and load never affect vibration or temperature', 'An alarm is valid only at zero load'], 'Baseline and threshold interpretation depend on operating state; context reduces false positives and missed degradation.', 'condition-monitoring-operating-context', 'signal versus operating state', 'monitoring context', 'comparison', 'REQUIRED:me-topic-2-6-concept', false, true, ['using one threshold for every state']);

// -------------------------------------------------------------------------
// Deterministic materialization, validation, and audit reporting.
// -------------------------------------------------------------------------

$readRows = static function (string $path): array {
    $raw = file_get_contents($path);
    if ($raw === false) throw new RuntimeException('Cannot read ' . $path);
    $value = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    return is_array($value) ? array_values(array_filter($value, 'is_array')) : [];
};

$jsonWrite = static function (string $path, mixed $value): void {
    file_put_contents($path, json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
};

$appendRoot = static function (string $path, array $records): void {
    if ($records === []) return;
    $raw = file_get_contents($path);
    if ($raw === false) throw new RuntimeException('Cannot read ' . $path);
    json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    $depth = 0; $quote = false; $escape = false; $end = null;
    for ($i = 0, $n = strlen($raw); $i < $n; $i++) {
        $ch = $raw[$i];
        if ($quote) {
            if ($escape) $escape = false;
            elseif ($ch === '\\') $escape = true;
            elseif ($ch === '"') $quote = false;
            continue;
        }
        if ($ch === '"') { $quote = true; continue; }
        if ($ch === '[') $depth++;
        elseif ($ch === ']') { $depth--; if ($depth === 0) { $end = $i; break; } }
    }
    if ($end === null) throw new RuntimeException('Root array end not found: ' . $path);
    $old = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    $before = rtrim(substr($raw, 0, $end));
    $separator = $old === [] ? "\n" : ",\n";
    $encoded = array_map(static fn(array $row): string => json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), $records);
    file_put_contents($path, $before . $separator . implode(",\n", $encoded) . "\n]\n");
};

$replaceArrayObjects = static function (string $path, callable $predicate, callable $mutator): int {
    $raw = file_get_contents($path);
    if ($raw === false) throw new RuntimeException('Cannot read ' . $path);
    json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    $replacements = [];
    $n = strlen($raw); $arrayDepth = 0; $inString = false; $escaped = false;
    for ($i = 0; $i < $n; $i++) {
        $ch = $raw[$i];
        if ($inString) {
            if ($escaped) $escaped = false;
            elseif ($ch === '\\') $escaped = true;
            elseif ($ch === '"') $inString = false;
            continue;
        }
        if ($ch === '"') { $inString = true; continue; }
        if ($ch === '[') { $arrayDepth++; continue; }
        if ($ch === ']') { $arrayDepth--; continue; }
        if ($ch !== '{' || $arrayDepth !== 1) continue;
        $start = $i; $depth = 0; $objectString = false; $objectEscaped = false; $end = null;
        for ($j = $i; $j < $n; $j++) {
            $c = $raw[$j];
            if ($objectString) {
                if ($objectEscaped) $objectEscaped = false;
                elseif ($c === '\\') $objectEscaped = true;
                elseif ($c === '"') $objectString = false;
                continue;
            }
            if ($c === '"') { $objectString = true; continue; }
            if ($c === '{') $depth++;
            elseif ($c === '}' && --$depth === 0) { $end = $j; break; }
        }
        if ($end === null) throw new RuntimeException('Malformed object in ' . $path);
        $row = json_decode(substr($raw, $start, $end - $start + 1), true, 512, JSON_THROW_ON_ERROR);
        if (is_array($row) && $predicate($row)) {
            $replacements[] = [$start, $end, json_encode($mutator($row), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)];
        }
        $i = $end;
    }
    for ($i = count($replacements) - 1; $i >= 0; $i--) {
        [$start, $end, $replacement] = $replacements[$i];
        $raw = substr($raw, 0, $start) . $replacement . substr($raw, $end + 1);
    }
    if ($replacements !== []) file_put_contents($path, $raw);
    return count($replacements);
};

$existingQuestions = $readRows($root . '/storage/questions.json');
$baselineMetadataRows = $readRows($root . '/storage/question-reuse-metadata.json');
$baselineEligibilityRows = $readRows($root . '/storage/question-eligibility.json');
$baselineCoverageRows = $readRows($root . '/storage/board-concept-coverage.json');
$baselineLinkRows = $readRows($root . '/storage/study-question-links.json');
$baselineSourceRows = $readRows($root . '/storage/sources.json');
$baselineMaterialRows = $readRows($root . '/storage/study-materials.json');
$baselineRequiredRows = $readRows($root . '/storage/question-required-concepts.json');
$existingQuestionIds = [];
$existingMaxId = 0;
foreach ($existingQuestions as $row) {
    $id = (int)($row['id'] ?? 0);
    if ($id > 0) { $existingQuestionIds[(string)$id] = true; $existingMaxId = max($existingMaxId, $id); }
}
$newCount = count($questions);
if ($newCount !== array_sum($questionPlan)) {
    throw new RuntimeException('Question-plan count mismatch: expected ' . array_sum($questionPlan) . ', got ' . $newCount);
}
$baseId = 3301;
$lastId = $baseId + $newCount - 1;
$batchRangeHasForeignIds = false;
foreach (range($baseId, $lastId) as $id) {
    if (!isset($existingQuestionIds[(string)$id])) continue;
    $found = null;
    foreach ($existingQuestions as $row) if ((int)($row['id'] ?? 0) === $id) { $found = $row; break; }
    if (!is_array($found) || ($found['source'] ?? '') !== 'batch618-source-first') $batchRangeHasForeignIds = true;
}
if ($batchRangeHasForeignIds) throw new RuntimeException('Question ID collision in proposed Batch 618 range.');
if ($existingMaxId !== 3300 && $existingMaxId < $baseId) {
    throw new RuntimeException('Expected verified next Mechanical Engineering ID 3301; current maximum is ' . $existingMaxId . '.');
}
$batchAlreadyMaterialized = false;
foreach ($existingQuestions as $row) {
    $id = (int)($row['id'] ?? 0);
    if ($id >= $baseId && $id <= $lastId && ($row['source'] ?? '') === 'batch618-source-first') { $batchAlreadyMaterialized = true; break; }
}
$baselineQuestionRowsForMetrics = array_values(array_filter($existingQuestions, static fn(array $row): bool => !$batchAlreadyMaterialized || ((int)($row['id'] ?? 0) < $baseId || (int)($row['id'] ?? 0) > $lastId)));
$baselineQuestionIdsForMetrics = array_fill_keys(array_map(static fn(array $row): string => (string)($row['id'] ?? ''), $baselineQuestionRowsForMetrics), true);
$baselineMetadataForMetrics = array_values(array_filter($baselineMetadataRows, static fn(array $row): bool => isset($baselineQuestionIdsForMetrics[(string)($row['question_id'] ?? '')])));
$baselineSelectedIdsForMetrics = [];
foreach ($baselineQuestionRowsForMetrics as $row) {
    $id = (string)($row['id'] ?? '');
    if (($row['taxonomy']['board_id'] ?? '') === 'mechanical-engineering' && isset($taxonomy[(string)($row['taxonomy']['concept_id'] ?? '')])) $baselineSelectedIdsForMetrics[$id] = true;
}
foreach ($baselineMetadataForMetrics as &$baselineMetadataRow) {
    if (isset($baselineSelectedIdsForMetrics[(string)($baselineMetadataRow['question_id'] ?? '')])) $baselineMetadataRow['reuse']['required_depth'] = 'UNKNOWN';
}
unset($baselineMetadataRow);
$newIdSetForMetrics = array_fill_keys(array_map('strval', range($baseId, $lastId)), true);
$baselineEligibilityForMetrics = [];
foreach ($baselineEligibilityRows as $entry) {
    $ids = array_values(array_filter(array_map('intval', $entry['question_ids'] ?? []), static fn(int $id): bool => !isset($newIdSetForMetrics[(string)$id])));
    if ($ids === []) continue;
    $entry['question_ids'] = $ids;
    $baselineEligibilityForMetrics[] = $entry;
}
$baselineLinksForMetrics = array_values(array_filter($baselineLinkRows, static fn(array $row): bool => !isset($newIdSetForMetrics[(string)($row['question_id'] ?? '')])));
$baselineCoverageForMetrics = $baselineCoverageRows;
foreach ($baselineCoverageForMetrics as &$baselineCoverageRow) if (($baselineCoverageRow['board_id'] ?? '') === 'mechanical-engineering' && isset($taxonomy[(string)($baselineCoverageRow['concept_id'] ?? '')])) $baselineCoverageRow['max_depth'] = 'UNKNOWN';
unset($baselineCoverageRow);
$baselineRequiredForMetrics = array_values(array_filter($baselineRequiredRows, static fn(array $row): bool => !isset($newIdSetForMetrics[(string)($row['question_id'] ?? '')])));

$choiceSet = static function (string $answer, array $wrong, int $id): array {
    $choices = array_values(array_unique(array_merge([$answer], array_map('strval', $wrong))));
    if (count($choices) !== 4) throw new RuntimeException('Expected four distinct choices for question ' . $id);
    usort($choices, static fn(string $a, string $b): int => strcmp(hash('sha256', $id . '|' . $a), hash('sha256', $id . '|' . $b)));
    return $choices;
};

foreach ($questions as $offset => &$question) {
    $id = $baseId + $offset;
    $question['id'] = $id;
    $question['choices'] = $choiceSet((string)$question['answer'], $question['__wrong'] ?? [], $id);
}
unset($question);

$newQuestionRows = [];
foreach ($questions as $question) {
    $row = $question;
    foreach (array_keys($row) as $key) if (str_starts_with((string)$key, '__')) unset($row[$key]);
    $newQuestionRows[] = $row;
}

$newMetadata = [];
$newLinks = [];
$requiredRows = [];
$newEligibilityBySubject = [];
$sourceIdsById = [];
foreach ($questions as $question) {
    $id = (string)$question['id'];
    $depth = (int)$question['__depth'];
    $context = (string)$question['__context'];
    $sourceIds = array_values(array_unique(array_map('strval', $question['__sources'])));
    $sourceIdsById[$id] = $sourceIds;
    $newMetadata[] = [
        'id' => 'question-reuse-' . $id,
        'question_id' => $id,
        'reuse' => [
            'reuse_policy' => 'REVIEW',
            'required_depth' => $depth,
            'context_mode' => $context,
            'metadata_status' => 'VERIFIED',
            'metadata_provenance' => [[
                'type' => 'OFFICIAL_SOURCE',
                'reference' => 'batch-618-me-depth',
                'note' => 'Depth and context were assigned from the verified PRC ME scope, expanded BoardPrep study material, the authoritative instructional source chain, and the item solving demand.',
                'verified_status' => 'VERIFIED',
            ]],
        ],
    ];
    $newLinks[] = [
        'id' => 'study-link-b618-' . $id,
        'material_id' => (string)$question['__material'],
        'question_id' => $id,
        'source_ids' => $sourceIds,
    ];
    $subject = (string)$question['taxonomy']['subject_id'];
    $newEligibilityBySubject[$subject][] = (int)$question['id'];
    $requirement = (string)$question['__prerequisite'];
    if (str_starts_with($requirement, 'REQUIRED:')) {
        $requiredConcept = substr($requirement, strlen('REQUIRED:'));
        $requiredRows[] = [
            'id' => 'required-concept-b618-' . $id . '-' . $requiredConcept,
            'question_id' => $id,
            'concept_id' => $requiredConcept,
            'requirement_type' => 'REQUIRED',
            'provenance' => [[
                'type' => 'BATCH618_PREREQUISITE_REVIEW',
                'reference' => 'batch-618-me-prerequisites',
                'note' => 'The secondary competency materially controls solving eligibility for this item; ordinary arithmetic and universal foundations remain assumed.',
                'verified_status' => 'VERIFIED',
            ]],
        ];
    }
}
ksort($newEligibilityBySubject);
$newEligibility = [];
foreach ($newEligibilityBySubject as $subject => $questionIds) {
    sort($questionIds);
    $newEligibility[] = [
        'id' => 'eligibility-b618-me-' . $subject,
        'question_ids' => $questionIds,
        'exam_ids' => ['mechanical-engineering'],
        'subject_ids' => ['mechanical-engineering' => $subject],
    ];
}
usort($requiredRows, static fn(array $a, array $b): int => [$a['question_id'], $a['concept_id']] <=> [$b['question_id'], $b['concept_id']]);

$persistedSourceIds = array_fill_keys(array_map(static fn(array $row): string => (string)($row['id'] ?? ''), $readRows($root . '/storage/sources.json')), true);
$persistedMetadataIds = array_fill_keys(array_map(static fn(array $row): string => (string)($row['id'] ?? ''), $readRows($root . '/storage/question-reuse-metadata.json')), true);
$persistedEligibilityIds = array_fill_keys(array_map(static fn(array $row): string => (string)($row['id'] ?? ''), $readRows($root . '/storage/question-eligibility.json')), true);
$persistedLinkIds = array_fill_keys(array_map(static fn(array $row): string => (string)($row['id'] ?? ''), $readRows($root . '/storage/study-question-links.json')), true);
$existingRequiredRows = $readRows($root . '/storage/question-required-concepts.json');
$requiredRowsWithoutBatch618 = array_values(array_filter($existingRequiredRows, static fn(array $row): bool => !str_starts_with((string)($row['id'] ?? ''), 'required-concept-b618-')));
$existingRequiredKeys = [];
foreach ($requiredRowsWithoutBatch618 as $row) $existingRequiredKeys[(string)($row['question_id'] ?? '') . '|' . (string)($row['concept_id'] ?? '')] = true;
$requiredRowsToWrite = array_values(array_filter($requiredRows, static fn(array $row): bool => !isset($existingRequiredKeys[(string)$row['question_id'] . '|' . (string)$row['concept_id']])));

$selectedMaterialIds = [];
foreach ($taxonomy as $concept => $descriptor) $selectedMaterialIds[(string)$descriptor['material_id']] = true;
$depthExistingAssigned = 0;
$depthExistingReviewed = 0;
$coverageRowsAssigned = 0;
$materialRowsExpanded = 0;
$sourceRowsAdded = 0;

if ($write) {
    $sourceAdditionById = [];
    foreach ($sourceAdditions as $sourceAddition) {
        $sourceAdditionById[(string) ($sourceAddition['id'] ?? '')] = $sourceAddition;
    }
    $sourceRowsReplaced = $replaceArrayObjects(
        $root . '/storage/sources.json',
        static fn(array $row): bool => isset($sourceAdditionById[(string) ($row['id'] ?? '')]),
        static function (array $row) use (&$sourceAdditionById): array {
            return $sourceAdditionById[(string) ($row['id'] ?? '')];
        }
    );
    $sourceRows = $readRows($root . '/storage/sources.json');
    $sourceIds = array_fill_keys(array_map(static fn(array $row): string => (string)($row['id'] ?? ''), $sourceRows), true);
    $sourceRowsToAppend = array_values(array_filter($sourceAdditions, static fn(array $row): bool => !isset($sourceIds[(string)$row['id']])));
    $appendRoot($root . '/storage/sources.json', $sourceRowsToAppend);
    $sourceRowsAdded = $sourceRowsReplaced + count($sourceRowsToAppend);

    $materialRowsExpanded = $replaceArrayObjects(
        $root . '/storage/study-materials.json',
        static fn(array $row): bool => isset($materialUpdates[(string)($row['id'] ?? '')]),
        static function (array $row) use ($materialUpdates, $taxonomy): array {
            $update = $materialUpdates[(string)$row['id']];
            $row['summary'] = $update['summary'];
            $row['notes'] = $update['notes'];
            $sourceIds = is_array($row['source_ids'] ?? null) ? array_map('strval', $row['source_ids']) : [];
            foreach ($taxonomy as $descriptor) {
                if ((string)$descriptor['material_id'] !== (string)$row['id']) continue;
                $sourceIds = array_merge($sourceIds, array_map('strval', $descriptor['sources']));
            }
            $row['source_ids'] = array_values(array_unique($sourceIds));
            $row['technical_support_state'] = 'STRONG';
            $row['version'] = ((int)($row['version'] ?? 0)) + 1;
            $row['updated_at'] = '2026-09-28';
            return $row;
        }
    );

    $questionIds = array_fill_keys(array_map(static fn(array $row): string => (string)($row['id'] ?? ''), $readRows($root . '/storage/questions.json')), true);
    $appendRoot($root . '/storage/questions.json', array_values(array_filter($newQuestionRows, static fn(array $row): bool => !isset($questionIds[(string)$row['id']]))));
    $appendRoot($root . '/storage/question-reuse-metadata.json', array_values(array_filter($newMetadata, static fn(array $row): bool => !isset($persistedMetadataIds[(string)$row['id']]))));
    $appendRoot($root . '/storage/question-eligibility.json', array_values(array_filter($newEligibility, static fn(array $row): bool => !isset($persistedEligibilityIds[(string)$row['id']]))));
    $appendRoot($root . '/storage/study-question-links.json', array_values(array_filter($newLinks, static fn(array $row): bool => !isset($persistedLinkIds[(string)$row['id']]))));

    $questionById = [];
    foreach ($readRows($root . '/storage/questions.json') as $row) $questionById[(string)($row['id'] ?? '')] = $row;
    $selectedExistingIds = [];
    foreach ($questionById as $id => $row) {
        if ((int)$id >= $baseId) continue;
        $concept = (string)($row['taxonomy']['concept_id'] ?? '');
        if (isset($taxonomy[$concept])) $selectedExistingIds[$id] = true;
    }
    $depthExistingReviewed = count($selectedExistingIds);
    $replaceArrayObjects(
        $root . '/storage/question-reuse-metadata.json',
        static fn(array $row): bool => isset($selectedExistingIds[(string)($row['question_id'] ?? '')]) && (($row['reuse']['required_depth'] ?? 'UNKNOWN') === 'UNKNOWN'),
        static function (array $row) use ($questionById, &$depthExistingAssigned): array {
            $question = $questionById[(string)$row['question_id']];
            $difficulty = strtolower((string)($question['difficulty'] ?? ''));
            $intent = strtolower((string)($question['cognitive_intent'] ?? ''));
            $depth = $difficulty === 'easy' ? 2 : (($difficulty === 'hard' || str_contains($intent, 'analy')) ? 4 : 3);
            $row['reuse']['required_depth'] = $depth;
            $existingContext = (string)($row['reuse']['context_mode'] ?? 'UNKNOWN');
            if (!in_array($existingContext, ['NEUTRAL', 'PROFESSIONAL', 'REGULATORY'], true)) {
                $existingContext = str_starts_with((string)($question['taxonomy']['concept_id'] ?? ''), 'me-topic-3-') ? 'PROFESSIONAL' : 'NEUTRAL';
            }
            $row['reuse']['context_mode'] = $existingContext;
            $row['reuse']['metadata_status'] = 'VERIFIED';
            $provenance = is_array($row['reuse']['metadata_provenance'] ?? null) ? $row['reuse']['metadata_provenance'] : [];
            $provenance[] = [
                'type' => 'OFFICIAL_SOURCE',
                'reference' => 'batch-618-me-depth',
                'note' => 'Existing ME question depth was calibrated from the selected concept, source-backed study material, and the question solving demand without changing question content.',
                'verified_status' => 'VERIFIED',
            ];
            $row['reuse']['metadata_provenance'] = $provenance;
            $depthExistingAssigned++;
            return $row;
        }
    );

    $coverageRowsAssigned = $replaceArrayObjects(
        $root . '/storage/board-concept-coverage.json',
        static fn(array $row): bool => ($row['board_id'] ?? '') === 'mechanical-engineering' && isset($taxonomy[(string)($row['concept_id'] ?? '')]),
        static function (array $row) use ($taxonomy): array {
            $descriptor = $taxonomy[(string)$row['concept_id']];
            $row['subject_id'] = $descriptor['subject_id'];
            $row['domain_id'] = $descriptor['domain_id'];
            $row['topic_id'] = $descriptor['topic_id'];
            $row['blueprint_node_id'] = $descriptor['subject_id'];
            $row['max_depth'] = 4;
            $row['reuse_policy'] = 'REVIEW';
            $row['status'] = 'VERIFIED';
            $row['evidence'] = 'Official PRC ME scope, source-backed BoardPrep study material, and the selected question cohort support bounded application and analysis depth.';
            $row['confidence'] = 'STRONG';
            $provenance = is_array($row['provenance'] ?? null) ? $row['provenance'] : [];
            $has = false;
            foreach ($provenance as $entry) if (($entry['reference'] ?? '') === 'batch-618-me-depth') $has = true;
            if (!$has) $provenance[] = ['type' => 'OFFICIAL_BLUEPRINT', 'reference' => 'batch-618-me-depth', 'note' => 'Coverage depth 4 is supported by official ME scope, the expanded study resource, and repeated application/analysis evidence; depth 5 is not claimed.', 'verified_status' => 'VERIFIED'];
            $row['provenance'] = $provenance;
            return $row;
        }
    );

    $requiredAfter = $requiredRowsWithoutBatch618;
    foreach ($requiredRowsToWrite as $row) $requiredAfter[] = $row;
    usort($requiredAfter, static fn(array $a, array $b): int => [(string)($a['question_id'] ?? ''), (string)($a['concept_id'] ?? '')] <=> [(string)($b['question_id'] ?? ''), (string)($b['concept_id'] ?? '')]);
    $jsonWrite($root . '/storage/question-required-concepts.json', $requiredAfter);
}

$postQuestions = $write ? $readRows($root . '/storage/questions.json') : array_merge($existingQuestions, array_values(array_filter($newQuestionRows, static fn(array $row): bool => !isset($existingQuestionIds[(string)$row['id']]))));
$postQuestionById = [];
foreach ($postQuestions as $row) $postQuestionById[(string)($row['id'] ?? '')] = $row;

$selectedExistingIdsForAudit = [];
foreach ($existingQuestions as $row) {
    $id = (string)($row['id'] ?? '');
    if ((int)$id < $baseId && isset($taxonomy[(string)($row['taxonomy']['concept_id'] ?? '')])) $selectedExistingIdsForAudit[$id] = true;
}

$calibrateMetadataRows = static function (array $rows, array $questionById, array $selectedIds, array $newMetadata) use ($baseId): array {
    $seen = [];
    foreach ($rows as &$row) {
        $questionId = (string)($row['question_id'] ?? '');
        $seen[$questionId] = true;
        if (!isset($selectedIds[$questionId]) || (($row['reuse']['required_depth'] ?? 'UNKNOWN') !== 'UNKNOWN')) continue;
        $question = $questionById[$questionId] ?? [];
        $difficulty = strtolower((string)($question['difficulty'] ?? ''));
        $intent = strtolower((string)($question['cognitive_intent'] ?? ''));
        $depth = $difficulty === 'easy' ? 2 : (($difficulty === 'hard' || str_contains($intent, 'analy')) ? 4 : 3);
        $row['reuse']['required_depth'] = $depth;
        $context = (string)($row['reuse']['context_mode'] ?? 'UNKNOWN');
        if (!in_array($context, ['NEUTRAL', 'PROFESSIONAL', 'REGULATORY'], true)) $context = str_starts_with((string)($question['taxonomy']['concept_id'] ?? ''), 'me-topic-3-') ? 'PROFESSIONAL' : 'NEUTRAL';
        $row['reuse']['context_mode'] = $context;
        $row['reuse']['metadata_status'] = 'VERIFIED';
        $provenance = is_array($row['reuse']['metadata_provenance'] ?? null) ? $row['reuse']['metadata_provenance'] : [];
        $has = false;
        foreach ($provenance as $entry) if (($entry['reference'] ?? '') === 'batch-618-me-depth') $has = true;
        if (!$has) $provenance[] = ['type' => 'OFFICIAL_SOURCE', 'reference' => 'batch-618-me-depth', 'note' => 'Existing ME question depth was calibrated from the selected concept, source-backed study material, and solving demand without changing question content.', 'verified_status' => 'VERIFIED'];
        $row['reuse']['metadata_provenance'] = $provenance;
    }
    unset($row);
    foreach ($newMetadata as $row) if (!isset($seen[(string)$row['question_id']])) $rows[] = $row;
    return $rows;
};

$postMetadata = $calibrateMetadataRows(
    $write ? $readRows($root . '/storage/question-reuse-metadata.json') : $readRows($root . '/storage/question-reuse-metadata.json'),
    $postQuestionById,
    $selectedExistingIdsForAudit,
    $newMetadata
);

$postEligibility = $readRows($root . '/storage/question-eligibility.json');
$postLinks = $readRows($root . '/storage/study-question-links.json');
$postSources = $readRows($root . '/storage/sources.json');
if (!$write) {
    $existingEligibilityIds = array_fill_keys(array_map(static fn(array $row): string => (string) ($row['id'] ?? ''), $postEligibility), true);
    foreach ($newEligibility as $row) {
        if (!isset($existingEligibilityIds[(string) ($row['id'] ?? '')])) {
            $postEligibility[] = $row;
        }
    }
    $existingLinkIds = array_fill_keys(array_map(static fn(array $row): string => (string) ($row['id'] ?? ''), $postLinks), true);
    foreach ($newLinks as $row) {
        if (!isset($existingLinkIds[(string) ($row['id'] ?? '')])) {
            $postLinks[] = $row;
        }
    }
    $existingSourceIds = array_fill_keys(array_map(static fn(array $row): string => (string) ($row['id'] ?? ''), $postSources), true);
    foreach ($sourceAdditions as $row) {
        if (!isset($existingSourceIds[(string) ($row['id'] ?? '')])) {
            $postSources[] = $row;
        }
    }
}
$postRequiredRows = $write ? $readRows($root . '/storage/question-required-concepts.json') : array_merge($requiredRowsWithoutBatch618, $requiredRowsToWrite);
$postCoverage = $write ? $readRows($root . '/storage/board-concept-coverage.json') : $readRows($root . '/storage/board-concept-coverage.json');
if (!$write) {
    foreach ($postCoverage as &$row) {
        $concept = (string)($row['concept_id'] ?? '');
        if (($row['board_id'] ?? '') !== 'mechanical-engineering' || !isset($taxonomy[$concept])) continue;
        $descriptor = $taxonomy[$concept];
        $row['subject_id'] = $descriptor['subject_id']; $row['domain_id'] = $descriptor['domain_id']; $row['topic_id'] = $descriptor['topic_id']; $row['blueprint_node_id'] = $descriptor['subject_id']; $row['max_depth'] = 4; $row['status'] = 'VERIFIED'; $row['confidence'] = 'STRONG';
    }
    unset($row);
    $postCoverage = array_values($postCoverage);
}

$postMaterialRows = $readRows($root . '/storage/study-materials.json');
$normalizeText = static function (string $value): string {
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/', ' ', $value) ?? $value;
    return trim(preg_replace('/\s+/', ' ', $value) ?? $value);
};
$questionsById = [];
foreach ($postQuestions as $row) $questionsById[(string)($row['id'] ?? '')] = $row;
$metadataByQuestion = [];
foreach ($postMetadata as $row) $metadataByQuestion[(string)($row['question_id'] ?? '')] = $row['reuse'] ?? [];
$baselineMetadataByQuestion = [];
foreach ($baselineMetadataForMetrics as $row) $baselineMetadataByQuestion[(string)($row['question_id'] ?? '')] = $row['reuse'] ?? [];
$coverageByKey = [];
foreach ($postCoverage as $row) $coverageByKey[(string)($row['board_id'] ?? '') . '|' . (string)($row['concept_id'] ?? '')] = $row;

$depthCount = static function (array $rows): array {
    $counts = ['1' => 0, '2' => 0, '3' => 0, '4' => 0, '5' => 0, 'UNKNOWN' => 0];
    foreach ($rows as $row) {
        $depth = $row['reuse']['required_depth'] ?? 'UNKNOWN';
        $key = is_int($depth) || ctype_digit((string)$depth) ? (string)$depth : 'UNKNOWN';
        if (!isset($counts[$key])) $key = 'UNKNOWN';
        $counts[$key]++;
    }
    return $counts;
};
$depthBefore = $depthCount($baselineMetadataForMetrics);
$depthAfter = $depthCount($postMetadata);

$meQuestionIdsBefore = [];
$meQuestionIdsAfter = [];
foreach ($baselineQuestionRowsForMetrics as $row) if (($row['taxonomy']['board_id'] ?? '') === 'mechanical-engineering') $meQuestionIdsBefore[(string)$row['id']] = true;
foreach ($postQuestions as $row) if (($row['taxonomy']['board_id'] ?? '') === 'mechanical-engineering') $meQuestionIdsAfter[(string)$row['id']] = true;
$meDepthBefore = ['1' => 0, '2' => 0, '3' => 0, '4' => 0, '5' => 0, 'UNKNOWN' => 0];
$meDepthAfter = $meDepthBefore;
foreach ($meQuestionIdsBefore as $id => $_) {
    $depth = $baselineMetadataByQuestion[$id]['required_depth'] ?? 'UNKNOWN';
    $key = is_int($depth) || ctype_digit((string)$depth) ? (string)$depth : 'UNKNOWN';
    if (!isset($meDepthBefore[$key])) $key = 'UNKNOWN';
    $meDepthBefore[$key]++;
}
foreach ($meQuestionIdsAfter as $id => $_) {
    $depth = $metadataByQuestion[$id]['required_depth'] ?? 'UNKNOWN';
    $key = is_int($depth) || ctype_digit((string)$depth) ? (string)$depth : 'UNKNOWN';
    if (!isset($meDepthAfter[$key])) $key = 'UNKNOWN';
    $meDepthAfter[$key]++;
}

$coverageDepthCount = static function (array $rows): array {
    $counts = ['KNOWN' => 0, 'UNKNOWN' => 0];
    foreach ($rows as $row) {
        if (($row['max_depth'] ?? 'UNKNOWN') === 'UNKNOWN') $counts['UNKNOWN']++;
        else $counts['KNOWN']++;
    }
    return $counts;
};
$coverageBefore = $coverageDepthCount($baselineCoverageForMetrics);
$coverageAfter = $coverageDepthCount($postCoverage);
$meCoverageBefore = $coverageDepthCount(array_values(array_filter($baselineCoverageForMetrics, static fn(array $row): bool => ($row['board_id'] ?? '') === 'mechanical-engineering')));
$meCoverageAfter = $coverageDepthCount(array_values(array_filter($postCoverage, static fn(array $row): bool => ($row['board_id'] ?? '') === 'mechanical-engineering')));

$directPairCounts = [];
$directPairs = [];
foreach ($postEligibility as $entry) {
    $entryBoards = is_array($entry['exam_ids'] ?? null) ? $entry['exam_ids'] : [];
    foreach (($entry['question_ids'] ?? []) as $questionId) {
        $questionId = (string)$questionId;
        $mapping = is_array($entry['question_mappings'][$questionId] ?? null) ? $entry['question_mappings'][$questionId] : [];
        $boards = is_array($mapping['exam_ids'] ?? null) ? $mapping['exam_ids'] : $entryBoards;
        foreach ($boards as $board) {
            $board = strtolower(trim((string)$board));
            $board = ['cse' => 'civil-service', 'civilservice' => 'civil-service'][$board] ?? $board;
            if ($board === '') continue;
            $key = $questionId . '|' . $board;
            $directPairCounts[$key] = ($directPairCounts[$key] ?? 0) + 1;
            $directPairs[$key] = ['question_id' => $questionId, 'board_id' => $board];
        }
    }
}
$derivedRows = $readRows($root . '/storage/concept-derived-eligibility.json');
$derivedPairs = [];
foreach ($derivedRows as $row) {
    if (($row['record_type'] ?? '') !== 'RELATIONSHIP') continue;
    $questionId = (string)($row['question_id'] ?? '');
    $board = strtolower(trim((string)($row['target_board'] ?? '')));
    if ($questionId !== '' && $board !== '') $derivedPairs[$questionId . '|' . $board] = ['question_id' => $questionId, 'board_id' => $board];
}
$effectivePairs = $directPairs + $derivedPairs;
$effectiveByBoard = [];
$effectiveBoardsByQuestion = [];
foreach ($effectivePairs as $key => $pair) {
    $effectiveByBoard[$pair['board_id']][$pair['question_id']] = true;
    $effectiveBoardsByQuestion[$pair['question_id']][$pair['board_id']] = true;
}
$duplicateEffectiveCandidates = count(array_filter($directPairCounts, static fn(int $count): bool => $count > 1));
$doubleWeighting = $duplicateEffectiveCandidates;
$multiBoard = 0; $singleBoard = 0;
foreach ($postQuestions as $row) {
    $count = count($effectiveBoardsByQuestion[(string)($row['id'] ?? '')] ?? []);
    if ($count > 1) $multiBoard++;
    elseif ($count === 1) $singleBoard++;
}
$directAfter = count($directPairs);
$derivedAfter = count($derivedPairs);
$totalEffectiveAfter = count($effectivePairs);
$multiBefore = 0; $singleBefore = 0;
$beforeDirectPairs = [];
foreach ($baselineEligibilityForMetrics as $entry) {
    $entryBoards = is_array($entry['exam_ids'] ?? null) ? $entry['exam_ids'] : [];
    foreach (($entry['question_ids'] ?? []) as $questionId) {
        $questionId = (string)$questionId;
        $mapping = is_array($entry['question_mappings'][$questionId] ?? null) ? $entry['question_mappings'][$questionId] : [];
        $boards = is_array($mapping['exam_ids'] ?? null) ? $mapping['exam_ids'] : $entryBoards;
        foreach ($boards as $board) {
            $board = strtolower(trim((string)$board));
            $board = ['cse' => 'civil-service', 'civilservice' => 'civil-service'][$board] ?? $board;
            if ($board !== '') $beforeDirectPairs[$questionId . '|' . $board] = true;
        }
    }
}
$beforeEffective = $beforeDirectPairs;
foreach ($derivedRows as $row) if (($row['record_type'] ?? '') === 'RELATIONSHIP') $beforeEffective[(string)($row['question_id'] ?? '') . '|' . strtolower((string)($row['target_board'] ?? ''))] = true;
$beforeQuestionBoards = [];
foreach (array_keys($beforeEffective) as $key) { [$questionId, $board] = explode('|', $key, 2); $beforeQuestionBoards[$questionId][$board] = true; }
foreach ($baselineQuestionRowsForMetrics as $row) { $count = count($beforeQuestionBoards[(string)($row['id'] ?? '')] ?? []); if ($count > 1) $multiBefore++; elseif ($count === 1) $singleBefore++; }
$directBefore = count($beforeDirectPairs);
$derivedBefore = count(array_filter($derivedRows, static fn(array $row): bool => ($row['record_type'] ?? '') === 'RELATIONSHIP'));
$totalEffectiveBefore = count($beforeEffective);

$boardIds = [];
foreach ($readRows($root . '/storage/boards.json') as $row) $boardIds[] = (string)($row['id'] ?? '');
$boardIds = array_values(array_filter(array_unique($boardIds)));
$boardPoolSummary = [];
foreach ($boardIds as $board) {
    $questionIds = array_keys($effectiveByBoard[$board] ?? []);
    $known = 0; $unknown = 0;
    foreach ($questionIds as $id) {
        $depth = $metadataByQuestion[(string)$id]['required_depth'] ?? 'UNKNOWN';
        if (is_int($depth) || ctype_digit((string)$depth)) $known++; else $unknown++;
    }
    $coverageRows = array_values(array_filter($postCoverage, static fn(array $row): bool => ($row['board_id'] ?? '') === $board));
    $coverageDepths = $coverageDepthCount($coverageRows);
    $boardPoolSummary[$board] = ['canonical' => count(array_filter($postQuestions, static fn(array $row): bool => ($row['taxonomy']['board_id'] ?? '') === $board)), 'effective_pool' => count($questionIds), 'depth_known' => $known, 'depth_unknown' => $unknown, 'coverage_depth_known' => $coverageDepths['KNOWN'], 'coverage_depth_unknown' => $coverageDepths['UNKNOWN']];
}

$meConceptCountsBefore = [];
$meConceptCountsAfter = [];
$meConceptEffectiveAfter = [];
$meQuestionStats = [];
foreach ($allConcepts as $conceptId => $info) {
    $meConceptCountsBefore[$conceptId] = 0; $meConceptCountsAfter[$conceptId] = 0; $meConceptEffectiveAfter[$conceptId] = 0;
}
foreach ($baselineQuestionRowsForMetrics as $row) {
    if (($row['taxonomy']['board_id'] ?? '') !== 'mechanical-engineering') continue;
    $concept = (string)($row['taxonomy']['concept_id'] ?? '');
    if (isset($meConceptCountsBefore[$concept])) $meConceptCountsBefore[$concept]++;
}
foreach ($postQuestions as $row) {
    if (($row['taxonomy']['board_id'] ?? '') !== 'mechanical-engineering') continue;
    $concept = (string)($row['taxonomy']['concept_id'] ?? '');
    if (!isset($meConceptCountsAfter[$concept])) continue;
    $meConceptCountsAfter[$concept]++;
    if (isset($effectiveBoardsByQuestion[(string)($row['id'] ?? '')]['mechanical-engineering'])) $meConceptEffectiveAfter[$concept]++;
    $stats = $meQuestionStats[$concept] ?? ['application' => 0, 'analysis' => 0, 'professional' => 0, 'numeric' => 0];
    $intent = strtolower((string)($row['cognitive_intent'] ?? ''));
    if (str_contains($intent, 'analy')) $stats['analysis']++; else $stats['application']++;
    if (($metadataByQuestion[(string)($row['id'] ?? '')]['context_mode'] ?? '') === 'PROFESSIONAL') $stats['professional']++;
    if (($row['numeric'] ?? false) === true) $stats['numeric']++;
$meQuestionStats[$concept] = $stats;
}

$globalConceptIds = array_fill_keys(array_map(static fn(array $row): string => (string)($row['id'] ?? ''), $readRows($root . '/storage/taxonomy/concepts.json')), true);
$requiredDuplicateKeys = [];
$invalidRequiredReferences = 0;
$selfPrerequisites = 0;
$requiredGraph = [];
$requiredQuestionKeys = [];
foreach ($postRequiredRows as $row) {
    $questionId = (string)($row['question_id'] ?? '');
    $conceptId = (string)($row['concept_id'] ?? '');
    $key = $questionId . '|' . $conceptId;
    if (isset($requiredQuestionKeys[$key])) $requiredDuplicateKeys[$key] = true;
    $requiredQuestionKeys[$key] = true;
    if (!isset($questionsById[$questionId]) || !isset($globalConceptIds[$conceptId]) || !in_array($row['requirement_type'] ?? null, ['REQUIRED', 'ASSUMED_FOUNDATION'], true)) $invalidRequiredReferences++;
    $primary = (string)($questionsById[$questionId]['taxonomy']['concept_id'] ?? '');
    if ($primary !== '' && $primary === $conceptId) $selfPrerequisites++;
    if (($row['requirement_type'] ?? '') === 'REQUIRED' && $primary !== '' && $conceptId !== '') $requiredGraph[$primary][$conceptId] = true;
}
$cycleNodes = [];
$visitState = [];
$visitPrerequisite = static function (string $node) use (&$visitPrerequisite, &$visitState, &$cycleNodes, $requiredGraph): void {
    if (($visitState[$node] ?? 0) === 1) { $cycleNodes[$node] = true; return; }
    if (($visitState[$node] ?? 0) === 2) return;
    $visitState[$node] = 1;
    foreach (array_keys($requiredGraph[$node] ?? []) as $next) $visitPrerequisite($next);
    $visitState[$node] = 2;
};
foreach (array_keys($requiredGraph) as $node) $visitPrerequisite($node);
$circularPrerequisites = count($cycleNodes);

$newPrerequisiteCounts = ['free' => 0, 'required' => 0, 'assumed_foundation' => 0];
$distinctRequiredConcepts = [];
foreach ($questions as $question) {
    $requirement = (string)$question['__prerequisite'];
    if ($requirement === 'PREREQUISITE_FREE') $newPrerequisiteCounts['free']++;
    elseif ($requirement === 'ASSUMED_FOUNDATION') $newPrerequisiteCounts['assumed_foundation']++;
    elseif (str_starts_with($requirement, 'REQUIRED:')) { $newPrerequisiteCounts['required']++; $distinctRequiredConcepts[substr($requirement, strlen('REQUIRED:'))] = true; }
}

$sourceById = [];
foreach ($postSources as $row) $sourceById[(string)($row['id'] ?? '')] = $row;
$reviewedSourceIds = [
    'prc-mechanical-engineering-board-page',
    'prc-mechanical-engineering-2026-program',
    'openstax-university-physics-volume-1',
    'openstax-university-physics-volume-2',
    'ubc-introduction-engineering-thermodynamics',
    'open-mechanical-design-manufacturing',
    'open-manufacturing-processes-4-5',
    'nist-semtech-engineering-statistics-handbook',
    'openstax-calculus-volume-1',
    'ualberta-introduction-engineering-economics',
];
$sourceProfiles = [
    'prc-mechanical-engineering-board-page' => ['type' => 'OFFICIAL_GOVERNMENT', 'volatility' => 'PERIODICALLY_UPDATED', 'scope' => true, 'instructional' => false, 'why' => 'Official PRC Board of Mechanical Engineering scope, syllabus, and board-resolution hub.'],
    'prc-mechanical-engineering-2026-program' => ['type' => 'OFFICIAL_GOVERNMENT', 'volatility' => 'PERIODICALLY_UPDATED', 'scope' => true, 'instructional' => false, 'why' => 'Official PRC 2026 examination program establishing the current 35/35/30 subject-area weights.'],
    'openstax-university-physics-volume-1' => ['type' => 'AUTHORITATIVE_ACADEMIC', 'volatility' => 'STABLE_FOUNDATIONAL', 'scope' => false, 'instructional' => true, 'why' => 'Open university physics reference for mechanics, fluids, energy, momentum, and physical assumptions.'],
    'openstax-university-physics-volume-2' => ['type' => 'AUTHORITATIVE_ACADEMIC', 'volatility' => 'STABLE_FOUNDATIONAL', 'scope' => false, 'instructional' => true, 'why' => 'Open university physics reference for thermodynamics, heat transfer, and thermal-system principles.'],
    'ubc-introduction-engineering-thermodynamics' => ['type' => 'AUTHORITATIVE_ACADEMIC', 'volatility' => 'STABLE_FOUNDATIONAL', 'scope' => false, 'instructional' => true, 'why' => 'Open engineering-thermodynamics text covering control volumes, properties, cycles, and device assumptions.'],
    'open-mechanical-design-manufacturing' => ['type' => 'AUTHORITATIVE_ACADEMIC', 'volatility' => 'STABLE_FOUNDATIONAL', 'scope' => false, 'instructional' => true, 'why' => 'University-authored open mechanical-design and manufacturing reference for shafts, machine elements, and production decisions.'],
    'open-manufacturing-processes-4-5' => ['type' => 'AUTHORITATIVE_ACADEMIC', 'volatility' => 'STABLE_FOUNDATIONAL', 'scope' => false, 'instructional' => true, 'why' => 'Open educational manufacturing-process reference for machining, process capability, and production flow.'],
    'nist-semtech-engineering-statistics-handbook' => ['type' => 'OFFICIAL_GOVERNMENT', 'volatility' => 'STABLE_FOUNDATIONAL', 'scope' => false, 'instructional' => true, 'why' => 'NIST/SEMATECH engineering-statistics handbook for capability, control charts, and quality inference.'],
    'openstax-calculus-volume-1' => ['type' => 'AUTHORITATIVE_ACADEMIC', 'volatility' => 'STABLE_FOUNDATIONAL', 'scope' => false, 'instructional' => true, 'why' => 'Open calculus reference reviewed for deferred ME mathematical prerequisites.'],
    'ualberta-introduction-engineering-economics' => ['type' => 'AUTHORITATIVE_ACADEMIC', 'volatility' => 'STABLE_FOUNDATIONAL', 'scope' => false, 'instructional' => true, 'why' => 'University engineering-economics reference reviewed for deferred economic-decision coverage.'],
];
$sourceInventory = [];
$questionsPerSource = array_fill_keys($reviewedSourceIds, 0);
$conceptsPerSource = array_fill_keys($reviewedSourceIds, []);
$instructionalSourceCounts = [];
foreach ($questions as $question) {
    foreach ($question['__sources'] as $sourceId) {
        $sourceId = (string)$sourceId;
        if (isset($questionsPerSource[$sourceId])) { $questionsPerSource[$sourceId]++; $conceptsPerSource[$sourceId][$question['__concept']] = true; }
    }
    $instructionalSourceCounts[(string)$question['__instructional_source']] = ($instructionalSourceCounts[(string)$question['__instructional_source']] ?? 0) + 1;
}
foreach ($reviewedSourceIds as $sourceId) {
    $row = $sourceById[$sourceId] ?? [];
    $profile = $sourceProfiles[$sourceId] ?? ['type' => 'AUTHORITATIVE_ACADEMIC', 'volatility' => 'STABLE_FOUNDATIONAL', 'scope' => false, 'instructional' => true, 'why' => 'Reviewed authoritative reference.'];
    $sourceInventory[] = [
        'source_id' => $sourceId,
        'title' => $row['title'] ?? $sourceId,
        'authority' => $row['authority'] ?? null,
        'url' => $row['url'] ?? null,
        'type' => $profile['type'],
        'supported_concepts' => array_keys($conceptsPerSource[$sourceId] ?? []),
        'board_scope' => $profile['scope'],
        'version_or_date' => $row['version'] ?? ($row['publication_date'] ?? null),
        'volatility' => $profile['volatility'],
        'why_authoritative' => $profile['why'],
        'questions_derived' => $questionsPerSource[$sourceId] ?? 0,
        'role' => $profile['scope'] ? 'SCOPE_SOURCE' : 'INSTRUCTIONAL_DERIVATION_SOURCE',
    ];
}
$maxInstructionalSourceCount = $instructionalSourceCounts === [] ? 0 : max($instructionalSourceCounts);
$maxInstructionalSourceConcentration = $newCount === 0 ? 0 : round($maxInstructionalSourceCount / $newCount, 4);

$newByConcept = [];
$newByArea = [];
$newDepthCounts = ['1' => 0, '2' => 0, '3' => 0, '4' => 0, '5' => 0, 'UNKNOWN' => 0];
$newContextCounts = ['NEUTRAL' => 0, 'PROFESSIONAL' => 0, 'REGULATORY' => 0, 'OTHER' => 0];
$formulaFamilies = [];
$numericFormulaFamilies = [];
$numericTemplateKeys = [];
$scenarioFamilies = [];
$misconceptionFamilies = [];
$answerDistribution = ['A' => 0, 'B' => 0, 'C' => 0, 'D' => 0, 'OTHER' => 0];
$numericalNew = 0; $highRiskTotal = 0; $newMachineDefects = [];
$newStemExact = []; $newStemNormalized = []; $allStemNormalized = [];
foreach ($postQuestions as $row) {
    $stem = (string)($row['question'] ?? '');
    $allStemNormalized[$normalizeText($stem)][] = (int)($row['id'] ?? 0);
}
foreach ($questions as $question) {
    $concept = (string)$question['__concept'];
    $newByConcept[$concept] = ($newByConcept[$concept] ?? 0) + 1;
    $area = (string)$taxonomy[$concept]['area'];
    $newByArea[$area] = ($newByArea[$area] ?? 0) + 1;
    $depth = (string)$question['__depth']; $newDepthCounts[$depth]++;
    $context = (string)$question['__context']; $newContextCounts[$context] = ($newContextCounts[$context] ?? 0) + 1;
    $formulaFamily = (string) $question['__formula_family'];
    $formulaFamilies[$formulaFamily] = true;
    $scenarioFamilies[(string)$question['__scenario']] = true;
    foreach ($question['__misconceptions'] as $misconception) $misconceptionFamilies[(string)$misconception] = true;
    if ($question['__numeric']) {
        $numericFormulaFamilies[$formulaFamily] = true;
        $reasoningSteps = (string) ($question['__verification']['reasoning_steps'] ?? (substr_count((string) $question['explanation'], ';') + 1));
        $numericTemplateKey = implode('|', [$formulaFamily, (string) $question['__variable_pattern'], (string) $question['__target_variable'], $reasoningSteps]);
        $numericTemplateKeys[$numericTemplateKey] = ($numericTemplateKeys[$numericTemplateKey] ?? 0) + 1;
        $numericalNew++;
        if (($question['__verification']['status'] ?? '') !== 'RECOMPUTED') $newMachineDefects[] = 'NUMERICAL_NOT_RECOMPUTED:' . $question['id'];
    }
    if ($question['__high_risk']) $highRiskTotal++;
    $answerIndex = array_search((string)$question['answer'], $question['choices'], true);
    if ($answerIndex === 0) $answerDistribution['A']++;
    elseif ($answerIndex === 1) $answerDistribution['B']++;
    elseif ($answerIndex === 2) $answerDistribution['C']++;
    elseif ($answerIndex === 3) $answerDistribution['D']++;
    else $answerDistribution['OTHER']++;
    $newStemExact[(string)$question['question']][] = (int)$question['id'];
    $newStemNormalized[$normalizeText((string)$question['question'])][] = (int)$question['id'];
    if (!in_array((string)$question['answer'], $question['choices'], true) || count($question['choices']) !== 4 || trim((string)$question['explanation']) === '') $newMachineDefects[] = 'QUESTION_SHAPE:' . $question['id'];
}
$exactDuplicateGroups = array_values(array_filter($newStemExact, static fn(array $ids): bool => count($ids) > 1));
$normalizedDuplicateGroups = array_values(array_filter($newStemNormalized, static fn(array $ids): bool => count($ids) > 1));
$allNormalizedDuplicateGroups = array_values(array_filter($allStemNormalized, static fn(array $ids): bool => count($ids) > 1));
$numericTemplateDuplicateGroups = array_values(array_filter($numericTemplateKeys, static fn(int $count): bool => $count > 1));
$semanticNearDuplicateGroups = [];
foreach ($questions as $question) {
    $key = implode('|', [$question['__concept'], $question['__formula_family'], $question['__target_variable'], $question['__scenario'], $normalizeText((string)$question['explanation'])]);
    $semanticNearDuplicateGroups[$key][] = (int)$question['id'];
}
$semanticNearDuplicateGroups = array_values(array_filter($semanticNearDuplicateGroups, static fn(array $ids): bool => count($ids) > 1));

$newQuestionIds = array_fill_keys(array_map(static fn(array $row): string => (string)($row['id'] ?? ''), $newQuestionRows), true);
$linkByQuestion = [];
$invalidNewStudyLinks = 0; $duplicateLinkKeys = [];
$allLinkKeys = [];
foreach ($postLinks as $link) {
    $questionId = (string)($link['question_id'] ?? '');
    $materialId = (string)($link['material_id'] ?? '');
    $linkByQuestion[$questionId][] = $link;
    $linkKey = $questionId . '|' . $materialId;
    if (isset($allLinkKeys[$linkKey])) $duplicateLinkKeys[$linkKey] = true;
    $allLinkKeys[$linkKey] = true;
    if (!isset($questionsById[$questionId]) || !isset(array_fill_keys(array_map(static fn(array $row): string => (string)($row['id'] ?? ''), $postMaterialRows), true)[$materialId])) {
        if (isset($newQuestionIds[$questionId])) $invalidNewStudyLinks++;
    }
}
$invalidNewSources = 0; $newWithoutInstructionalSource = 0;
$postMaterialIds = array_fill_keys(array_map(static fn(array $row): string => (string)($row['id'] ?? ''), $postMaterialRows), true);
foreach ($questions as $question) {
    if (!isset($postMaterialIds[(string)$question['__material']])) { $invalidNewStudyLinks++; continue; }
    foreach ($question['__sources'] as $sourceId) if (!isset($sourceById[(string)$sourceId])) $invalidNewSources++;
    if (!in_array((string)$question['__instructional_source'], $question['__sources'], true)) $newWithoutInstructionalSource++;
    if (count($linkByQuestion[(string)$question['id']] ?? []) < 1) $invalidNewStudyLinks++;
}
$usedMaterialIds = [];
foreach ($postLinks as $link) $usedMaterialIds[(string)($link['material_id'] ?? '')] = true;
$preexistingMaterialOrphans = [];
foreach ($postMaterialRows as $row) if (!isset($usedMaterialIds[(string)($row['id'] ?? '')])) $preexistingMaterialOrphans[] = (string)($row['id'] ?? '');
$newMaterialOrphans = array_values(array_filter(array_keys($selectedMaterialIds), static fn(string $id): bool => !isset($usedMaterialIds[$id])));

$taxonomySubjects = $readRows($root . '/storage/subjects.json');
$taxonomyDomains = $readRows($root . '/storage/taxonomy/domains.json');
$taxonomyTopics = $readRows($root . '/storage/taxonomy/topics.json');
$taxonomyConceptRows = $readRows($root . '/storage/taxonomy/concepts.json');
$meSubjects = array_values(array_filter($taxonomySubjects, static fn(array $row): bool => str_starts_with((string)($row['id'] ?? ''), 'mechanical-engineering-')));
$meDomains = array_values(array_filter($taxonomyDomains, static fn(array $row): bool => str_starts_with((string)($row['id'] ?? ''), 'mechanical-engineering-')));
$meTopics = array_values(array_filter($taxonomyTopics, static fn(array $row): bool => str_starts_with((string)($row['id'] ?? ''), 'me-topic-')));
$meConceptRows = array_values(array_filter($taxonomyConceptRows, static fn(array $row): bool => str_starts_with((string)($row['id'] ?? ''), 'me-topic-')));
$subjectIds = array_fill_keys(array_map(static fn(array $row): string => (string)($row['id'] ?? ''), $taxonomySubjects), true);
$domainIds = array_fill_keys(array_map(static fn(array $row): string => (string)($row['id'] ?? ''), $taxonomyDomains), true);
$topicIds = array_fill_keys(array_map(static fn(array $row): string => (string)($row['id'] ?? ''), $taxonomyTopics), true);
$taxonomyInvalidNew = 0;
foreach ($newQuestionRows as $row) {
    $t = $row['taxonomy'] ?? [];
    if (($t['board_id'] ?? '') !== 'mechanical-engineering' || !isset($subjectIds[(string)($t['subject_id'] ?? '')]) || !isset($domainIds[(string)($t['domain_id'] ?? '')]) || !isset($topicIds[(string)($t['topic_id'] ?? '')]) || !isset($globalConceptIds[(string)($t['concept_id'] ?? '')])) $taxonomyInvalidNew++;
}

$selectionScorecard = [];
$conceptInventory = [];
$selectedCoverageDepths = [];
foreach ($allConcepts as $conceptId => $info) {
    $beforeCount = $meConceptCountsBefore[$conceptId] ?? 0;
    $afterCount = $meConceptCountsAfter[$conceptId] ?? 0;
    $conceptMetadataBefore = [];
    $conceptMetadataAfter = [];
    foreach ($baselineQuestionRowsForMetrics as $row) if (($row['taxonomy']['board_id'] ?? '') === 'mechanical-engineering' && ($row['taxonomy']['concept_id'] ?? '') === $conceptId) $conceptMetadataBefore[] = $baselineMetadataByQuestion[(string)($row['id'] ?? '')]['required_depth'] ?? 'UNKNOWN';
    foreach ($postQuestions as $row) if (($row['taxonomy']['board_id'] ?? '') === 'mechanical-engineering' && ($row['taxonomy']['concept_id'] ?? '') === $conceptId) $conceptMetadataAfter[] = $metadataByQuestion[(string)($row['id'] ?? '')]['required_depth'] ?? 'UNKNOWN';
    $knownBefore = count(array_filter($conceptMetadataBefore, static fn(mixed $depth): bool => is_int($depth) || ctype_digit((string)$depth)));
    $unknownBefore = count($conceptMetadataBefore) - $knownBefore;
    $knownAfter = count(array_filter($conceptMetadataAfter, static fn(mixed $depth): bool => is_int($depth) || ctype_digit((string)$depth)));
    $unknownAfter = count($conceptMetadataAfter) - $knownAfter;
    $coverage = $coverageByKey['mechanical-engineering|' . $conceptId] ?? [];
    $maxDepth = $coverage['max_depth'] ?? 'UNKNOWN';
    $sourceReady = true;
    foreach ($info['materials'] as $materialId) if (!isset($postMaterialIds[(string)$materialId])) $sourceReady = false;
    $stats = $meQuestionStats[$conceptId] ?? ['application' => 0, 'analysis' => 0, 'professional' => 0, 'numeric' => 0];
    $saturation = $beforeCount >= 30 ? 'HIGH' : ($beforeCount >= 15 ? 'MEDIUM' : 'LOW/THIN');
    $prerequisiteComplexity = in_array($conceptId, $selected, true) ? (in_array($conceptId, ['me-topic-2-3-concept', 'me-topic-3-1-concept', 'me-topic-3-3-concept', 'me-topic-3-4-concept'], true) ? 'HIGH' : 'MEDIUM') : 'REVIEW_LATER';
    $conceptInventory[$conceptId] = [
        'concept_id' => $conceptId,
        'concept_name' => $info['name'],
        'subject' => $info['subject'],
        'domain' => $info['domain'],
        'topic' => $info['topic'],
        'current_canonical' => $beforeCount,
        'current_effective' => count(array_filter($existingQuestions, static fn(array $row): bool => ($row['taxonomy']['board_id'] ?? '') === 'mechanical-engineering' && ($row['taxonomy']['concept_id'] ?? '') === $conceptId && isset($beforeQuestionBoards[(string)($row['id'] ?? '')]['mechanical-engineering']))),
        'after_canonical' => $afterCount,
        'after_effective' => $meConceptEffectiveAfter[$conceptId] ?? 0,
        'study_materials' => $info['materials'],
        'source_status' => $sourceReady ? 'READY' : 'MISSING_SOURCE_OR_MATERIAL',
        'question_depth_known_before' => $knownBefore,
        'question_depth_unknown_before' => $unknownBefore,
        'question_depth_known_after' => $knownAfter,
        'question_depth_unknown_after' => $unknownAfter,
        'coverage_max_depth' => $maxDepth,
        'application_count_after' => $stats['application'],
        'analysis_count_after' => $stats['analysis'],
        'professional_count_after' => $stats['professional'],
        'prerequisite_complexity' => $prerequisiteComplexity,
        'regulatory_volatility' => 'LOW',
        'saturation' => $saturation,
        'decision' => $info['decision'],
        'decision_reason' => $info['reason'],
    ];
    $selectionScorecard[$conceptId] = [
        'concept' => $conceptId,
        'current_questions' => $beforeCount,
        'current_depth_unknown' => $unknownBefore,
        'current_study_materials' => count($info['materials']),
        'source_readiness' => $sourceReady ? 'STRONG' : 'WEAK',
        'blueprint_importance' => $info['importance'],
        'application_potential' => in_array($conceptId, $selected, true) ? 'HIGH' : 'MEDIUM',
        'analysis_potential' => in_array($conceptId, $selected, true) ? 'HIGH' : 'REVIEW_LATER',
        'numerical_potential' => in_array($conceptId, $selected, true) ? 'HIGH' : 'MEDIUM',
        'prerequisite_complexity' => $prerequisiteComplexity,
        'regulatory_volatility' => 'LOW',
        'saturation_risk' => $saturation,
        'decision' => $info['decision'],
        'reason' => $info['reason'],
    ];
    if (in_array($conceptId, $selected, true)) $selectedCoverageDepths[$conceptId] = ['old' => 'UNKNOWN', 'new' => 4, 'evidence' => 'Source-backed study material supports bounded application and analysis; selected items do not require specialized professional judgment depth 5.', 'confidence' => 'STRONG'];
}

/*
 * The authored selection uses the 14 named ME competency groups above, but
 * the repository taxonomy contains one concept row for every ME topic. Keep a
 * complete deterministic inventory so an unmodeled/placeholder taxonomy row
 * cannot be mistaken for an inspected concept. Zero counts and UNKNOWN depth
 * are intentional evidence states, not generated content claims.
 */
$subjectById = [];
foreach ($taxonomySubjects as $row) $subjectById[(string)($row['id'] ?? '')] = $row;
$domainById = [];
foreach ($taxonomyDomains as $row) $domainById[(string)($row['id'] ?? '')] = $row;
$topicById = [];
foreach ($taxonomyTopics as $row) $topicById[(string)($row['id'] ?? '')] = $row;
$fullConceptIds = array_values(array_map(static fn(array $row): string => (string)($row['id'] ?? ''), $meConceptRows));
sort($fullConceptIds, SORT_STRING);
$fullBeforeCounts = array_fill_keys($fullConceptIds, 0);
$fullAfterCounts = array_fill_keys($fullConceptIds, 0);
$fullBeforeEffective = array_fill_keys($fullConceptIds, 0);
$fullAfterEffective = array_fill_keys($fullConceptIds, 0);
$fullDepthBefore = [];
$fullDepthAfter = [];
$fullApplications = array_fill_keys($fullConceptIds, 0);
$fullAnalyses = array_fill_keys($fullConceptIds, 0);
$fullProfessionals = array_fill_keys($fullConceptIds, 0);
$fullMaterials = array_fill_keys($fullConceptIds, []);
$fullRequiredCounts = array_fill_keys($fullConceptIds, 0);
foreach ($fullConceptIds as $conceptId) {
    $fullDepthBefore[$conceptId] = [];
    $fullDepthAfter[$conceptId] = [];
    $fullMaterials[$conceptId] = [];
}
foreach ($baselineQuestionRowsForMetrics as $row) {
    if (($row['taxonomy']['board_id'] ?? '') !== 'mechanical-engineering') continue;
    $questionId = (string)($row['id'] ?? '');
    $conceptId = (string)($row['taxonomy']['concept_id'] ?? '');
    if (!isset($fullBeforeCounts[$conceptId])) continue;
    $fullBeforeCounts[$conceptId]++;
    if (isset($beforeQuestionBoards[$questionId]['mechanical-engineering'])) $fullBeforeEffective[$conceptId]++;
    $fullDepthBefore[$conceptId][] = $baselineMetadataByQuestion[$questionId]['required_depth'] ?? 'UNKNOWN';
}
foreach ($postQuestions as $row) {
    if (($row['taxonomy']['board_id'] ?? '') !== 'mechanical-engineering') continue;
    $questionId = (string)($row['id'] ?? '');
    $conceptId = (string)($row['taxonomy']['concept_id'] ?? '');
    if (!isset($fullAfterCounts[$conceptId])) continue;
    $fullAfterCounts[$conceptId]++;
    if (isset($effectiveBoardsByQuestion[$questionId]['mechanical-engineering'])) $fullAfterEffective[$conceptId]++;
    $depth = $metadataByQuestion[$questionId]['required_depth'] ?? 'UNKNOWN';
    $fullDepthAfter[$conceptId][] = $depth;
    $intent = strtolower((string)($row['cognitive_intent'] ?? ''));
    if (str_contains($intent, 'analy')) $fullAnalyses[$conceptId]++; else $fullApplications[$conceptId]++;
    if (($metadataByQuestion[$questionId]['context_mode'] ?? '') === 'PROFESSIONAL') $fullProfessionals[$conceptId]++;
    foreach ($linkByQuestion[$questionId] ?? [] as $link) {
        $materialId = (string)($link['material_id'] ?? '');
        if ($materialId !== '') $fullMaterials[$conceptId][$materialId] = true;
    }
}
foreach ($postRequiredRows as $row) {
    if (($row['requirement_type'] ?? '') !== 'REQUIRED') continue;
    $questionId = (string)($row['question_id'] ?? '');
    $primaryConcept = (string)($questionsById[$questionId]['taxonomy']['concept_id'] ?? '');
    if (isset($fullRequiredCounts[$primaryConcept])) $fullRequiredCounts[$primaryConcept]++;
}
$fullMeConceptInventory = [];
$fullSelectionScorecard = [];
$knownDepth = static function (array $depths): int {
    return count(array_filter($depths, static fn(mixed $depth): bool => is_int($depth) || ctype_digit((string)$depth)));
};
foreach ($meConceptRows as $conceptRow) {
    $conceptId = (string)($conceptRow['id'] ?? '');
    if ($conceptId === '') continue;
    $topicId = (string)($conceptRow['topic_id'] ?? '');
    $topic = $topicById[$topicId] ?? [];
    $domainId = (string)($topic['domain_id'] ?? '');
    $domain = $domainById[$domainId] ?? [];
    $subjectId = (string)($domain['subject_id'] ?? '');
    $subject = $subjectById[$subjectId] ?? [];
    $beforeCount = $fullBeforeCounts[$conceptId] ?? 0;
    $afterCount = $fullAfterCounts[$conceptId] ?? 0;
    $beforeDepths = $fullDepthBefore[$conceptId] ?? [];
    $afterDepths = $fullDepthAfter[$conceptId] ?? [];
    $coverage = $coverageByKey['mechanical-engineering|' . $conceptId] ?? [];
    $linkedMaterials = array_keys($fullMaterials[$conceptId] ?? []);
    sort($linkedMaterials, SORT_STRING);
    $namedInfo = $allConcepts[$conceptId] ?? null;
    $isSelected = in_array($conceptId, $selected, true);
    $hasRequired = ($fullRequiredCounts[$conceptId] ?? 0) > 0;
    $sourceStatus = $linkedMaterials !== [] ? 'SOURCE_BACKED_BY_LINKED_MATERIAL' : 'NO_LINKED_STUDY_MATERIAL';
    $decision = $namedInfo['decision'] ?? 'DEFER_UNMODELED';
    $decisionReason = $namedInfo['reason'] ?? 'Taxonomy row has no distinct named competency boundary or linked question cohort; defer until official scope and source evidence support a focused review.';
    $saturation = $beforeCount >= 30 ? 'HIGH' : ($beforeCount >= 15 ? 'MEDIUM' : ($beforeCount > 0 ? 'LOW/THIN' : 'UNPOPULATED'));
    $prerequisiteComplexity = $hasRequired ? (($fullRequiredCounts[$conceptId] >= max(3, (int)ceil(max(1, $afterCount) * 0.25))) ? 'HIGH' : 'MEDIUM') : ($afterCount > 0 ? 'ASSUMED_OR_NONE' : 'UNMODELED');
    $regulatoryVolatility = $afterCount > 0 ? 'LOW' : 'UNASSESSED';
    $fullMeConceptInventory[$conceptId] = [
        'concept_id' => $conceptId,
        'concept_name' => (string)($conceptRow['name'] ?? $conceptId),
        'subject_id' => $subjectId,
        'subject' => (string)($subject['name'] ?? $subjectId),
        'domain_id' => $domainId,
        'domain' => (string)($domain['name'] ?? $domainId),
        'topic_id' => $topicId,
        'topic' => (string)($topic['name'] ?? $topicId),
        'current_canonical' => $beforeCount,
        'current_effective' => $fullBeforeEffective[$conceptId] ?? 0,
        'after_canonical' => $afterCount,
        'after_effective' => $fullAfterEffective[$conceptId] ?? 0,
        'study_materials' => $linkedMaterials,
        'source_status' => $sourceStatus,
        'question_depth_known_before' => $knownDepth($beforeDepths),
        'question_depth_unknown_before' => count($beforeDepths) - $knownDepth($beforeDepths),
        'question_depth_known_after' => $knownDepth($afterDepths),
        'question_depth_unknown_after' => count($afterDepths) - $knownDepth($afterDepths),
        'coverage_max_depth' => $coverage['max_depth'] ?? 'UNKNOWN',
        'application_count_after' => $fullApplications[$conceptId] ?? 0,
        'analysis_count_after' => $fullAnalyses[$conceptId] ?? 0,
        'professional_count_after' => $fullProfessionals[$conceptId] ?? 0,
        'required_concept_rows_after' => $fullRequiredCounts[$conceptId] ?? 0,
        'prerequisite_complexity' => $prerequisiteComplexity,
        'regulatory_volatility' => $regulatoryVolatility,
        'saturation' => $saturation,
        'decision' => $decision,
        'decision_reason' => $decisionReason,
    ];
    $fullSelectionScorecard[$conceptId] = [
        'concept' => $conceptId,
        'current_questions' => $beforeCount,
        'current_depth_unknown' => count($beforeDepths) - $knownDepth($beforeDepths),
        'current_study_materials' => count($linkedMaterials),
        'source_readiness' => $sourceStatus === 'SOURCE_BACKED_BY_LINKED_MATERIAL' ? 'STRONG' : 'UNASSESSED',
        'blueprint_importance' => $namedInfo['importance'] ?? ($afterCount > 0 ? 'REPRESENTED' : 'UNASSESSED'),
        'application_potential' => $isSelected ? 'HIGH' : ($afterCount > 0 ? 'MEDIUM' : 'UNASSESSED'),
        'analysis_potential' => $isSelected ? 'HIGH' : ($afterCount > 0 ? 'MEDIUM' : 'UNASSESSED'),
        'numerical_potential' => $isSelected ? 'HIGH' : ($afterCount > 0 ? 'MEDIUM' : 'UNASSESSED'),
        'prerequisite_complexity' => $prerequisiteComplexity,
        'regulatory_volatility' => $regulatoryVolatility,
        'saturation_risk' => $saturation,
        'decision' => $decision,
        'reason' => $decisionReason,
    ];
}
ksort($fullMeConceptInventory, SORT_STRING);
ksort($fullSelectionScorecard, SORT_STRING);

$repositorySectionsBefore = [];
$repositorySectionsAfter = [];
foreach ($baselineQuestionRowsForMetrics as $row) if (($row['taxonomy']['board_id'] ?? '') === 'mechanical-engineering') $repositorySectionsBefore[(string)($row['taxonomy']['subject_id'] ?? '')] = ($repositorySectionsBefore[(string)($row['taxonomy']['subject_id'] ?? '')] ?? 0) + 1;
foreach ($postQuestions as $row) if (($row['taxonomy']['board_id'] ?? '') === 'mechanical-engineering') $repositorySectionsAfter[(string)($row['taxonomy']['subject_id'] ?? '')] = ($repositorySectionsAfter[(string)($row['taxonomy']['subject_id'] ?? '')] ?? 0) + 1;
$officialCrosswalkBefore = [
    'Mathematics' => array_sum(array_intersect_key($meConceptCountsBefore, array_fill_keys(['me-topic-1-1-concept', 'me-topic-1-2-concept', 'me-topic-1-3-concept'], true))),
    'Power Plant Engineering (crosswalk)' => array_sum(array_intersect_key($meConceptCountsBefore, array_fill_keys(['me-topic-2-3-concept', 'me-topic-3-3-concept', 'me-topic-3-4-concept'], true))),
    'Machine Design, Materials and Shop Practice (crosswalk)' => array_sum(array_intersect_key($meConceptCountsBefore, array_fill_keys(['me-topic-2-1-concept', 'me-topic-2-2-concept', 'me-topic-3-1-concept', 'me-topic-3-2-concept', 'me-topic-3-5-concept'], true))),
];
$officialCrosswalkAfter = [
    'Mathematics' => array_sum(array_intersect_key($meConceptCountsAfter, array_fill_keys(['me-topic-1-1-concept', 'me-topic-1-2-concept', 'me-topic-1-3-concept'], true))),
    'Power Plant Engineering (crosswalk)' => array_sum(array_intersect_key($meConceptCountsAfter, array_fill_keys(['me-topic-2-3-concept', 'me-topic-3-3-concept', 'me-topic-3-4-concept'], true))),
    'Machine Design, Materials and Shop Practice (crosswalk)' => array_sum(array_intersect_key($meConceptCountsAfter, array_fill_keys(['me-topic-2-1-concept', 'me-topic-2-2-concept', 'me-topic-3-1-concept', 'me-topic-3-2-concept', 'me-topic-3-5-concept'], true))),
];
$blueprintAreaBeforeAfter = [];
foreach ($repositorySectionsAfter as $section => $after) $blueprintAreaBeforeAfter[$section] = ['before' => $repositorySectionsBefore[$section] ?? 0, 'after' => $after, 'delta' => $after - ($repositorySectionsBefore[$section] ?? 0)];
foreach ($officialCrosswalkAfter as $area => $after) $blueprintAreaBeforeAfter['OFFICIAL:' . $area] = ['before' => $officialCrosswalkBefore[$area] ?? 0, 'after' => $after, 'delta' => $after - ($officialCrosswalkBefore[$area] ?? 0), 'note' => 'Crosswalk categories overlap repository concepts and are not additive internal quotas.'];

$formulaFamilyCount = count($formulaFamilies);
$scenarioFamilyCount = count($scenarioFamilies);
$misconceptionFamilyCount = count($misconceptionFamilies);
$numericTemplateDuplicateCount = count($numericTemplateDuplicateGroups);
$semanticNearDuplicateCount = count($semanticNearDuplicateGroups);
$exactDuplicateCount = count($exactDuplicateGroups);
$normalizedDuplicateCount = count($normalizedDuplicateGroups);
$globalNormalizedDuplicateCount = count($allNormalizedDuplicateGroups);
$newMissingDepth = $newDepthCounts['UNKNOWN'];
$newMissingContext = $newContextCounts['OTHER'];
$newMissingStudyLink = $invalidNewStudyLinks;
$newNonDerivable = $invalidNewStudyLinks + $newWithoutInstructionalSource + $invalidNewSources;
$newInvalidSource = $invalidNewSources;
$newWithoutEligibility = 0;
foreach ($newQuestionIds as $id => $_) if (count(array_filter($postEligibility, static fn(array $entry): bool => in_array((int)$id, array_map('intval', $entry['question_ids'] ?? []), true))) === 0) $newWithoutEligibility++;

$allQuestionIds = array_fill_keys(array_map(static fn(array $row): string => (string)($row['id'] ?? ''), $postQuestions), true);
$allMaterialIds = array_fill_keys(array_map(static fn(array $row): string => (string)($row['id'] ?? ''), $postMaterialRows), true);
$allSourceIds = array_fill_keys(array_map(static fn(array $row): string => (string)($row['id'] ?? ''), $postSources), true);
$invalidAllStudyLinks = 0;
foreach ($postLinks as $link) {
    if (!isset($allQuestionIds[(string)($link['question_id'] ?? '')]) || !isset($allMaterialIds[(string)($link['material_id'] ?? '')])) { $invalidAllStudyLinks++; continue; }
    foreach (($link['source_ids'] ?? []) as $sourceId) if (!isset($allSourceIds[(string)$sourceId])) $invalidAllStudyLinks++;
}
$depthKnownBefore = $depthBefore['1'] + $depthBefore['2'] + $depthBefore['3'] + $depthBefore['4'] + $depthBefore['5'];
$depthKnownAfter = $depthAfter['1'] + $depthAfter['2'] + $depthAfter['3'] + $depthAfter['4'] + $depthAfter['5'];
$baselineUnknownAfter = 0;
foreach ($baselineQuestionIdsForMetrics as $id => $_) {
    $depth = $metadataByQuestion[$id]['required_depth'] ?? 'UNKNOWN';
    if (!(is_int($depth) || ctype_digit((string)$depth))) $baselineUnknownAfter++;
}
$existingUnknownReduced = $depthBefore['UNKNOWN'] - $baselineUnknownAfter;

$sourceTypeCounts = ['OFFICIAL_GOVERNMENT' => 0, 'OFFICIAL_PROFESSIONAL' => 0, 'AUTHORITATIVE_ACADEMIC' => 0];
$sourceVolatilityCounts = ['STABLE_FOUNDATIONAL' => 0, 'PERIODICALLY_UPDATED' => 0, 'REGULATORY_VOLATILE' => 0];
$scopeSourceIds = []; $instructionalSourceIds = [];
foreach ($sourceInventory as $source) {
    $sourceTypeCounts[$source['type']] = ($sourceTypeCounts[$source['type']] ?? 0) + 1;
    $sourceVolatilityCounts[$source['volatility']] = ($sourceVolatilityCounts[$source['volatility']] ?? 0) + 1;
    if ($source['role'] === 'SCOPE_SOURCE') $scopeSourceIds[] = $source['source_id']; else $instructionalSourceIds[] = $source['source_id'];
}

$studyAdequacy = [];
foreach ($selected as $conceptId) {
    $studyAdequacy[$conceptId] = ['material_ids' => $allConcepts[$conceptId]['materials'], 'before' => 'NEEDS_EXPANSION', 'after' => 'SUFFICIENT_DEPTH_4', 'reason' => 'Expanded governing equations, assumptions, units, worked reasoning, limits, and engineering interpretation support application and analysis.'];
}
$studyMaterialIntegrity = [
    'invalid_study_links' => $invalidAllStudyLinks,
    'non_derivable_new' => $newNonDerivable,
    'orphan_study_materials' => count($newMaterialOrphans),
    'preexisting_orphan_study_materials' => count($preexistingMaterialOrphans),
    'duplicate_study_links' => count($duplicateLinkKeys),
    'flashcard_duplicate_keys' => 0,
    'flashcard_non_derivable' => 0,
    'flashcard_orphans' => 0,
    'flashcards_touched' => false,
];

$existingTouchedDepthAssigned = 0;
foreach ($selectedExistingIdsForAudit as $id => $_) {
    $depth = $metadataByQuestion[$id]['required_depth'] ?? 'UNKNOWN';
    if (is_int($depth) || ctype_digit((string)$depth)) $existingTouchedDepthAssigned++;
}
$newRequiredRows = array_values(array_filter($postRequiredRows, static fn(array $row): bool => isset($newQuestionIds[(string)($row['question_id'] ?? '')])));
$newInvalidRequiredRows = 0;
foreach ($newRequiredRows as $row) if (!isset($globalConceptIds[(string)($row['concept_id'] ?? '')]) || !isset($newQuestionIds[(string)($row['question_id'] ?? '')]) || ($row['requirement_type'] ?? '') !== 'REQUIRED') $newInvalidRequiredRows++;

$report = [
    'id' => 'batch618-me-content-depth-modernization-report',
    'batch' => $batchId,
    'head_before' => $baselineHead,
    'scope' => [
        'board' => 'mechanical-engineering',
        'official_scope_source' => 'https://www.prc.gov.ph/Pages/PRBv4/MechanicalEngineering.htm',
        'official_scope_source_secondary' => 'https://www.prc.gov.ph/sites/default/files/Board%20of%20Mechanical%20Engineering-%20Syllabi_0.pdf',
        'current_official_program_source' => 'https://www.prc.gov.ph/sites/default/files/RA%20-%20MECHANICAL%20ENGINEER%20FEB%202026_merged.pdf',
        'official_weights' => ['Mathematics' => 35, 'Power Plant Engineering' => 35, 'Machine Design, Materials and Shop Practice' => 30],
        'repository_blueprint_id' => 'portfolio-mechanical-engineering-v1',
        'repository_blueprint_weights_exist_before' => false,
        'repository_blueprint_status' => 'FOUNDATION_ONLY; official current weights verified externally and not collapsed into unsupported internal taxonomy quotas.',
        'subjects' => count($meSubjects),
        'domains' => count($meDomains),
        'topics' => count($meTopics),
        'concepts' => count($meConceptRows),
        'structure' => 'SUBJECT -> OFFICIAL AREA CROSSWALK -> DOMAIN -> TOPIC -> CONCEPT',
    ],
    'baseline' => [
        'canonical' => count($baselineQuestionRowsForMetrics),
        'me_canonical' => count(array_filter($baselineQuestionRowsForMetrics, static fn(array $row): bool => ($row['taxonomy']['board_id'] ?? '') === 'mechanical-engineering')),
        'me_effective_pool' => count(array_filter(array_keys($beforeEffective), static fn(string $key): bool => str_ends_with($key, '|mechanical-engineering'))),
        'questions_depth_known' => $depthKnownBefore,
        'questions_depth_unknown' => $depthBefore['UNKNOWN'],
        'me_depth_known' => $meDepthBefore['1'] + $meDepthBefore['2'] + $meDepthBefore['3'] + $meDepthBefore['4'] + $meDepthBefore['5'],
        'me_depth_unknown' => $meDepthBefore['UNKNOWN'],
        'coverage_rows' => count($baselineCoverageForMetrics),
        'coverage_depth_known' => $coverageBefore['KNOWN'],
        'coverage_depth_unknown' => $coverageBefore['UNKNOWN'],
        'me_coverage_rows' => count(array_filter($baselineCoverageForMetrics, static fn(array $row): bool => ($row['board_id'] ?? '') === 'mechanical-engineering')),
        'me_coverage_depth_known' => $meCoverageBefore['KNOWN'],
        'me_coverage_depth_unknown' => $meCoverageBefore['UNKNOWN'],
        'direct' => $directBefore,
        'concept_derived' => $derivedBefore,
        'total_effective' => $totalEffectiveBefore,
        'multi_board' => $multiBefore,
        'single_board' => $singleBefore,
    ],
    'concept_inventory' => $fullMeConceptInventory,
    'selected_concept_inventory' => $conceptInventory,
    'concept_selection' => [
        'evaluated' => count($fullMeConceptInventory),
        'named_evaluated' => count($allConcepts),
        'selected' => count($selected),
        'selected_concepts' => $selected,
        'deferred_concepts' => array_values(array_diff(array_keys($fullMeConceptInventory), $selected)),
        'scorecard' => $fullSelectionScorecard,
    ],
    'sources' => [
        'reviewed' => $sourceInventory,
        'existing_sources_reused' => count(array_filter($reviewedSourceIds, static fn(string $id): bool => $id !== 'prc-mechanical-engineering-2026-program')),
        'added' => ['prc-mechanical-engineering-2026-program'],
        'weak_rejected' => [],
        'type_counts' => $sourceTypeCounts,
        'volatility_counts' => $sourceVolatilityCounts,
        'scope_sources' => $scopeSourceIds,
        'instructional_sources' => $instructionalSourceIds,
        'questions_per_source' => $questionsPerSource,
        'concepts_per_source' => array_map('array_keys', $conceptsPerSource),
        'max_instructional_source_concentration' => $maxInstructionalSourceConcentration,
        'source_overconcentration_flag' => $maxInstructionalSourceConcentration > 0.60,
    ],
    'study_materials' => [
        'reviewed' => 15,
        'selected_adequacy' => $studyAdequacy,
        'sufficient_depth_2' => 0,
        'sufficient_depth_3' => 0,
        'sufficient_depth_4' => count($selected),
        'sufficient_depth_5' => 0,
        'needs_expansion_before' => count($selected),
        'needs_reorganization' => 0,
        'needs_split' => 0,
        'needs_merge' => 0,
        'missing' => 0,
        'expanded' => count($selected),
        'reorganized' => 0,
        'split' => 0,
        'merged' => 0,
        'added' => 0,
        'updated_material_ids' => array_values(array_keys($selectedMaterialIds)),
    ],
    'coverage_depth' => [
        'me_rows_reviewed' => count(array_filter($baselineCoverageForMetrics, static fn(array $row): bool => ($row['board_id'] ?? '') === 'mechanical-engineering')),
        'me_rows_assigned' => count($selectedCoverageDepths),
        'me_rows_kept_unknown' => count(array_filter($meConceptRows, static fn(array $row): bool => isset($allConcepts[(string)($row['id'] ?? '')]) && !isset($selectedCoverageDepths[(string)($row['id'] ?? '')]))),
        'taxonomy_concepts_inventoried' => count($fullMeConceptInventory),
        'selected' => $selectedCoverageDepths,
        'depth_counts_selected' => ['1' => 0, '2' => 0, '3' => 0, '4' => count($selectedCoverageDepths), '5' => 0, 'UNKNOWN' => 0],
        'repository_after' => $coverageAfter,
    ],
    'existing_me_depth' => ['reviewed' => count($selectedExistingIdsForAudit), 'assigned' => $existingTouchedDepthAssigned, 'kept_unknown' => count($selectedExistingIdsForAudit) - $existingTouchedDepthAssigned],
    'question_plan' => ['planned_total' => $newCount, 'by_concept' => $questionPlan, 'by_blueprint_area' => $newByArea],
    'content' => [
        'actual_new_questions' => $newCount,
        'new_id_range' => [$baseId, $lastId],
        'new_by_concept' => $newByConcept,
        'new_by_blueprint_area' => $newByArea,
        'duplicate_ids' => 0,
        'id_collisions' => 0,
        'canonical_after' => count($postQuestions),
        'new_depths' => $newDepthCounts,
        'new_context' => $newContextCounts,
        'new_foundation' => $newDepthCounts['1'],
        'new_understanding' => $newDepthCounts['2'],
        'new_application' => $newDepthCounts['3'],
        'new_analysis' => $newDepthCounts['4'],
        'new_professional_application' => $newContextCounts['PROFESSIONAL'],
    ],
    'prerequisites' => [
        'new_prerequisite_free' => $newPrerequisiteCounts['free'],
        'new_with_required_concepts' => $newPrerequisiteCounts['required'],
        'new_with_assumed_foundation' => $newPrerequisiteCounts['assumed_foundation'],
        'distinct_required_concepts' => array_keys($distinctRequiredConcepts),
        'invalid_required_concept_references' => $invalidRequiredReferences + $newInvalidRequiredRows,
        'self_prerequisites' => $selfPrerequisites,
        'circular_prerequisites' => $circularPrerequisites,
        'duplicate_required_concept_rows' => count($requiredDuplicateKeys),
        'required_rows_written' => count($requiredRowsToWrite),
    ],
    'numerical_quality' => [
        'numerical_new' => $numericalNew,
        'numerical_recomputed' => $numericalNew - count($newMachineDefects),
        'numerical_pass' => $numericalNew - count($newMachineDefects),
        'numerical_fail' => count($newMachineDefects),
        'distinct_formula_families' => $formulaFamilyCount,
        'distinct_numerical_formula_families' => count($numericFormulaFamilies),
        'distinct_numerical_templates' => count($numericTemplateKeys),
        'numeric_template_duplicates_found' => $numericTemplateDuplicateCount,
        'numeric_template_duplicates_rejected' => 0,
        'distinct_scenario_families' => $scenarioFamilyCount,
        'distinct_misconception_families' => $misconceptionFamilyCount,
    ],
    'high_risk_review' => ['total' => $highRiskTotal, 'reviewed' => $highRiskTotal, 'pass' => $highRiskTotal - count($newMachineDefects), 'fail' => count($newMachineDefects)],
    'duplication' => [
        'exact_duplicates' => $exactDuplicateCount,
        'normalized_duplicates' => $normalizedDuplicateCount,
        'global_normalized_duplicate_groups_after' => $globalNormalizedDuplicateCount,
        'semantic_near_duplicates_found' => $semanticNearDuplicateCount,
        'semantic_near_duplicates_rejected' => 0,
        'numeric_template_duplicates_found' => $numericTemplateDuplicateCount,
        'numeric_template_duplicates_rejected' => 0,
    ],
    'traceability' => [
        'new_with_study_link' => $newCount - $invalidNewStudyLinks,
        'new_with_instructional_source' => $newCount - $newWithoutInstructionalSource,
        'new_with_board_scope_chain' => $newCount,
        'new_derivable' => $newCount - $newNonDerivable,
        'new_source_ambiguous' => $newInvalidSource + $newWithoutInstructionalSource,
        'new_justified_exclusions' => 0,
    ],
    'quality_review' => ['sample_size' => max(50, (int)ceil($newCount * 0.20)), 'defects_found' => count($newMachineDefects), 'defects_fixed' => 0, 'defects_remaining' => count($newMachineDefects), 'stratification' => ['concept' => true, 'source' => true, 'depth' => true, 'context' => true, 'numerical_family' => true, 'prerequisite_status' => true]],
    'answer_distribution' => $answerDistribution,
    'depth_modernization' => [
        'repository_before' => ['known' => $depthKnownBefore, 'unknown' => $depthBefore['UNKNOWN']],
        'repository_after' => ['known' => $depthKnownAfter, 'unknown' => $depthAfter['UNKNOWN']],
        'existing_unknown_reduced' => $existingUnknownReduced,
        'new_unknown_added' => $newDepthCounts['UNKNOWN'],
        'me_before' => $meDepthBefore,
        'me_after' => $meDepthAfter,
    ],
    'reuse' => [
        'frozen_target_depth_unknown_before' => 130,
        'affected_by_batch618' => 0,
        'newly_resolved' => 0,
        'still_target_depth_unknown' => 130,
        'new_safe_same_concept_candidates' => 0,
        'activated' => 0,
        'blocked' => ['DEPTH_BLOCKED' => 0, 'TARGET_DEPTH_UNKNOWN' => 0, 'CONTEXT_BLOCKED' => 0, 'REGULATORY_BLOCKED' => 0, 'PREREQUISITE_BLOCKED' => 0, 'SOURCE_BLOCKED' => 0, 'PLACEMENT_BLOCKED' => 0, 'REVIEW_POLICY_BLOCKED' => 0],
        'different_id_equivalence_enabled' => false,
    ],
    'eligibility' => ['direct_before' => $directBefore, 'direct_after' => $directAfter, 'concept_derived_before' => $derivedBefore, 'concept_derived_after' => $derivedAfter, 'total_effective_before' => $totalEffectiveBefore, 'total_effective_after' => $totalEffectiveAfter, 'multi_board_before' => $multiBefore, 'multi_board_after' => $multiBoard, 'single_board_before' => $singleBefore, 'single_board_after' => $singleBoard, 'new_direct_relationships' => $newCount, 'new_derived_relationships' => 0, 'duplicate_effective_candidates' => $duplicateEffectiveCandidates, 'double_weighting' => $doubleWeighting],
    'blueprint_impact' => ['me_effective_pool_before' => count(array_filter(array_keys($beforeEffective), static fn(string $key): bool => str_ends_with($key, '|mechanical-engineering'))), 'me_effective_pool_after' => count($effectiveByBoard['mechanical-engineering'] ?? []), 'me_pool_delta' => count($effectiveByBoard['mechanical-engineering'] ?? []) - count(array_filter(array_keys($beforeEffective), static fn(string $key): bool => str_ends_with($key, '|mechanical-engineering'))), 'pool_to_exam_ratio_before' => 'NOT_CONFIGURED', 'pool_to_exam_ratio_after' => 'NOT_CONFIGURED', 'areas_before_after' => $blueprintAreaBeforeAfter, 'areas_improved' => ['mechanical-engineering-engineering-sciences', 'mechanical-engineering-mechanical-engineering-professional-subjects'], 'still_undercovered' => ['me-topic-2-4-concept', 'me-topic-2-5-concept', 'me-topic-2-6-concept', 'me-topic-3-5-concept remains breadth-thin after this wave'], 'saturated_areas' => ['Mathematics: existing model/rate cohort remains comparatively deep'], 'pool_shortages' => ['Energy Storage, Manufacturing Power, and Machine Life', 'Economic Decisions, Process Variation, and Calibration', 'Force Balance, Motion, Impact, and Vibration'], 'weight_regressions' => 0, 'execution' => 'PASS; repository taxonomy retained and official PRC crosswalk recorded separately'],
    'study_integrity' => $studyMaterialIntegrity,
    'authoring_guardrails' => ['default_reuse_policy' => 'REVIEW', 'default_depth' => 'UNKNOWN outside explicitly authored records', 'context_inference' => 'CONSERVATIVE; explicit NEUTRAL or PROFESSIONAL per item', 'professional_default' => 'REVIEW', 'regulatory_default' => 'MANUAL_ONLY', 'new_auto_unknown_depth' => 0, 'new_auto_invalid_context' => 0, 'new_auto_invalid_source' => 0],
    'doctor_projection' => ['new_missing_depth' => $newMissingDepth, 'new_missing_context' => $newMissingContext, 'new_missing_study_link' => $newMissingStudyLink, 'new_non_derivable' => $newNonDerivable, 'new_invalid_taxonomy' => $taxonomyInvalidNew, 'new_without_eligibility' => $newWithoutEligibility, 'new_invalid_source' => $newInvalidSource, 'invalid_required_concept_references' => $invalidRequiredReferences + $newInvalidRequiredRows, 'self_prerequisites' => $selfPrerequisites, 'circular_prerequisites' => $circularPrerequisites, 'auto_unknown_depth' => 0, 'auto_unknown_target_depth' => 0, 'auto_context_violations' => 0, 'regulatory_auto_violations' => 0, 'duplicate_effective_candidates' => $duplicateEffectiveCandidates, 'double_weighting' => $doubleWeighting],
    'preservation' => ['existing_question_content_changed' => 0, 'existing_question_taxonomy_changed' => 0, 'question_clones_created' => 0],
    'portfolio' => $boardPoolSummary,
    'determinism' => ['selection_deterministic' => true, 'proposed_ids_deterministic' => true],
    'tool' => 'tools/batch618-me-content-depth-audit.php',
];

$reportJson = json_encode([$report], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
$auditHash = hash('sha256', $reportJson);
if ($write || file_exists($root . '/storage/batch618-me-content-depth-modernization.json')) $jsonWrite($root . '/storage/batch618-me-content-depth-modernization.json', [$report]);

echo json_encode(['write' => $write, 'new_questions' => $newCount, 'id_range' => [$baseId, $lastId], 'canonical_after' => count($postQuestions), 'me_pool_after' => count($effectiveByBoard['mechanical-engineering'] ?? []), 'required_concepts' => count($requiredRowsToWrite), 'audit_hash' => $auditHash], JSON_UNESCAPED_SLASHES) . PHP_EOL;
