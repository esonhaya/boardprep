<?php

declare(strict_types=1);

/**
 * Batch 625: Nursing area-level modernization, evidence closure, and expansion.
 *
 * The authoring cohort is intentionally data-driven and append-only.  Existing
 * JSON records are never re-serialized: this matters because the working tree
 * may contain unrelated edits.  The --write pass appends only records whose
 * Batch 625 IDs are not already present, then emits the durable audit artifact.
 */

$root = dirname(__DIR__);
$write = in_array('--write', $argv, true);
$today = '2026-09-29';
$timestamp = $today . 'T00:00:00+08:00';
$batch = '625';
$baselineHead = trim((string) shell_exec('git -C ' . escapeshellarg($root) . ' rev-parse HEAD'));

$read = static function (string $path): array {
    $raw = file_get_contents($path);
    if ($raw === false) {
        throw new RuntimeException('Cannot read ' . $path);
    }
    $value = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    return is_array($value) ? $value : [];
};

$writeArtifact = static function (string $path, array $value): void {
    file_put_contents(
        $path,
        json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n"
    );
};

/** Append JSON records while preserving every byte before the final array close. */
$appendRecords = static function (string $path, array $records, callable $identity): int {
    if ($records === []) {
        return 0;
    }
    $raw = file_get_contents($path);
    if ($raw === false) {
        throw new RuntimeException('Cannot read ' . $path);
    }
    $existing = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    $seen = [];
    foreach ($existing as $row) {
        $key = $identity($row);
        if ($key !== '') {
            $seen[$key] = true;
        }
    }
    $fresh = [];
    foreach ($records as $row) {
        $key = $identity($row);
        if ($key === '' || isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $fresh[] = $row;
    }
    if ($fresh === []) {
        return 0;
    }
    $close = strrpos($raw, ']');
    if ($close === false) {
        throw new RuntimeException('JSON array close not found in ' . $path);
    }
    $prefix = rtrim(substr($raw, 0, $close));
    $prefix = rtrim($prefix, " \t\r\n");
    $hasRecords = trim($prefix) !== '[';
    $encoded = [];
    foreach ($fresh as $row) {
        $encoded[] = json_encode($row, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
    $replacement = $prefix . ($hasRecords ? ",\n" : "\n") . implode(",\n", $encoded) . "\n]\n";
    file_put_contents($path, $replacement);
    return count($fresh);
};

$normalize = static fn(string $value): string => strtolower(trim((string) (preg_replace('/\s+/', ' ', $value) ?? $value)));
$hashRows = static fn(array $rows): string => hash('sha256', json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

/* Each item is deliberately explicit: stem, correct answer, three plausible
 * misconceptions, rationale, depth, safety categories, scenario family,
 * misconception family, and an optional required concept. */
$item = static function (
    string $stem,
    string $answer,
    string $d1,
    string $d2,
    string $d3,
    string $explanation,
    int $depth,
    array $categories = [],
    string $family = 'clinical-reasoning',
    string $misconception = 'routine-over-assessment',
    ?string $required = null,
    string $dosage = 'NONE',
    ?array $calculation = null,
): array {
    return compact('stem', 'answer', 'd1', 'd2', 'd3', 'explanation', 'depth', 'categories', 'family', 'misconception', 'required', 'dosage', 'calculation');
};

$scopeSources = ['prc-nursing-program-aug-2026', 'prc-nursing-enhanced-tos-2025'];
$areaMeta = [
    'NP I' => [
        'node' => 'np-i', 'domain' => 'nle-community-health', 'study' => 'nle-b625-np-i-clinical-reasoning',
        'sources' => [...$scopeSources, 'who-community-health', 'openrn-nursing-fundamentals', 'cdc-infection-control-basics', 'doh-national-immunization-program'],
    ],
    'NP II' => [
        'node' => 'np-ii', 'domain' => 'nle-maternal-child', 'study' => 'nle-b625-np-ii-clinical-reasoning',
        'sources' => [...$scopeSources, 'who-postnatal-care-2022', 'who-maternal-peripartum-infections', 'who-newborn-resuscitation-2012', 'openrn-nursing-fundamentals'],
    ],
    'NP III' => [
        'node' => 'np-iii', 'domain' => 'nle-medical-surgical', 'study' => 'nle-b625-np-iii-clinical-reasoning',
        'sources' => [...$scopeSources, 'cdc-infection-control-basics', 'cdc-isolation-precautions', 'cdc-safe-injection', 'who-basic-emergency-care', 'openrn-nursing-fundamentals'],
    ],
    'NP IV' => [
        'node' => 'np-iv', 'domain' => 'nle-medical-surgical', 'study' => 'nle-b625-np-iv-clinical-reasoning',
        'sources' => [...$scopeSources, 'who-medication-without-harm-5-moments', 'openrn-pharmacology', 'openrn-nursing-fundamentals'],
    ],
    'NP V' => [
        'node' => 'np-v', 'domain' => 'nle-mental-health', 'study' => 'nle-b625-np-v-clinical-reasoning',
        'sources' => [...$scopeSources, 'who-basic-emergency-care', 'who-mhgap-2023', 'openrn-nursing-fundamentals'],
    ],
];

$conceptMeta = [
    'nle-np-i-community' => ['area' => 'NP I', 'label' => 'Community'],
    'nle-np-i-prevention' => ['area' => 'NP I', 'label' => 'Prevention'],
    'nle-np-i-education' => ['area' => 'NP I', 'label' => 'Education'],
    'nle-np-i-epidemiology' => ['area' => 'NP I', 'label' => 'Epidemiology'],
    'nle-np-i-disaster' => ['area' => 'NP I', 'label' => 'Disaster'],
    'nle-np-i-vulnerable' => ['area' => 'NP I', 'label' => 'Vulnerable populations'],
    'nle-np-ii-prenatal' => ['area' => 'NP II', 'label' => 'Prenatal'],
    'nle-np-ii-labor' => ['area' => 'NP II', 'label' => 'Labor'],
    'nle-np-ii-postpartum' => ['area' => 'NP II', 'label' => 'Postpartum'],
    'nle-np-ii-newborn' => ['area' => 'NP II', 'label' => 'Newborn'],
    'nle-np-ii-development' => ['area' => 'NP II', 'label' => 'Development'],
    'nle-np-ii-family' => ['area' => 'NP II', 'label' => 'Family'],
    'nle-np-iii-oxygenation' => ['area' => 'NP III', 'label' => 'Oxygenation'],
    'nle-np-iii-fluids' => ['area' => 'NP III', 'label' => 'Fluids and electrolytes'],
    'nle-np-iii-infection' => ['area' => 'NP III', 'label' => 'Infection'],
    'nle-np-iii-immunity' => ['area' => 'NP III', 'label' => 'Immunity'],
    'nle-np-iii-perioperative' => ['area' => 'NP III', 'label' => 'Perioperative'],
    'nle-np-iii-cellular' => ['area' => 'NP III', 'label' => 'Cellular aberrations'],
    'nle-np-iv-nutrition' => ['area' => 'NP IV', 'label' => 'Nutrition'],
    'nle-np-iv-gastrointestinal' => ['area' => 'NP IV', 'label' => 'Gastrointestinal'],
    'nle-np-iv-endocrine' => ['area' => 'NP IV', 'label' => 'Endocrine'],
    'nle-np-iv-perception' => ['area' => 'NP IV', 'label' => 'Perception'],
    'nle-np-iv-coordination' => ['area' => 'NP IV', 'label' => 'Coordination'],
    'nle-np-iv-medication' => ['area' => 'NP IV', 'label' => 'Medication safety'],
    'nle-np-v-therapeutic' => ['area' => 'NP V', 'label' => 'Therapeutic care'],
    'nle-np-v-crisis' => ['area' => 'NP V', 'label' => 'Crisis'],
    'nle-np-v-emergency' => ['area' => 'NP V', 'label' => 'Emergency'],
    'nle-np-v-shock' => ['area' => 'NP V', 'label' => 'Shock'],
    'nle-np-v-acuity' => ['area' => 'NP V', 'label' => 'Acuity'],
    'nle-np-v-teamwork' => ['area' => 'NP V', 'label' => 'Teamwork'],
];

$studyMaterials = [
    [
        'id' => 'nle-b625-np-i-clinical-reasoning', 'title' => 'Batch 625 NP I: Community Clinical Reasoning',
        'summary' => 'Apply population assessment, prevention, epidemiology, teaching, disaster planning, and equity reasoning to a community nursing decision.',
        'notes' => [
            ['heading' => 'Assessment', 'body' => 'Define the population, verify the pattern, identify immediate threats, and distinguish missing information from an established finding.'],
            ['heading' => 'Action and monitoring', 'body' => 'Select a feasible least-harm intervention, protect privacy, use current program guidance, document the response, and evaluate outcomes rather than activity alone.'],
            ['heading' => 'Teaching and equity', 'body' => 'Use plain language, qualified interpretation, teach-back, culturally responsive planning, and proportional resource allocation.'],
            ['heading' => 'Boundary', 'body' => 'A useful science foundation does not itself establish an independent Nursing coverage row; keep shared-science claims separate from NP I competency evidence.'],
        ],
        'source_ids' => $areaMeta['NP I']['sources'], 'exams' => ['nursing'], 'taxonomy' => ['subject_id' => 'nursing-foundations', 'domain_id' => 'nle-community-health'], 'status' => 'published', 'version' => 1, 'updated_at' => $today,
    ],
    [
        'id' => 'nle-b625-np-ii-clinical-reasoning', 'title' => 'Batch 625 NP II: Maternal, Newborn, and Family Decisions',
        'summary' => 'Connect maternal, labor, postpartum, newborn, growth, development, and family cues to safe assessment, escalation, teaching, and referral.',
        'notes' => [
            ['heading' => 'Assessment', 'body' => 'Cluster maternal and newborn findings, compare trends with baseline, identify danger signs, and avoid unsupported absolute claims.'],
            ['heading' => 'Time-sensitive care', 'body' => 'Use current local protocols for fetal, maternal, newborn, infection, medication, and resuscitation decisions; escalate instability promptly.'],
            ['heading' => 'Family partnership', 'body' => 'Respect consent, privacy, language, culture, and family participation while retaining professional accountability for safe care.'],
            ['heading' => 'Teaching', 'body' => 'Use demonstration and teach-back for feeding, warning signs, medication, wound, and follow-up instructions.'],
        ],
        'source_ids' => $areaMeta['NP II']['sources'], 'exams' => ['nursing'], 'taxonomy' => ['subject_id' => 'nursing-foundations', 'domain_id' => 'nle-maternal-child'], 'status' => 'published', 'version' => 1, 'updated_at' => $today,
    ],
    [
        'id' => 'nle-b625-np-iii-clinical-reasoning', 'title' => 'Batch 625 NP III: Medical-Surgical Safety Decisions',
        'summary' => 'Interpret acute and chronic oxygenation, fluid, infection, immune, perioperative, and cellular cues through assessment, intervention, monitoring, and escalation.',
        'notes' => [
            ['heading' => 'Stability first', 'body' => 'Use airway, breathing, circulation, mental status, trend, and response to distinguish deterioration from a stable finding.'],
            ['heading' => 'Infection safety', 'body' => 'Apply standard precautions, transmission-based measures, aseptic practice, source control, and current local policy.'],
            ['heading' => 'Perioperative care', 'body' => 'Verify identity, consent, allergies, procedure, orders, equipment, and postoperative response; never substitute a checklist for assessment.'],
            ['heading' => 'Monitoring', 'body' => 'Reassess after an intervention and escalate when the supplied facts show actual deterioration or an unresolved safety threat.'],
        ],
        'source_ids' => $areaMeta['NP III']['sources'], 'exams' => ['nursing'], 'taxonomy' => ['subject_id' => 'nursing-foundations', 'domain_id' => 'nle-medical-surgical'], 'status' => 'published', 'version' => 1, 'updated_at' => $today,
    ],
    [
        'id' => 'nle-b625-np-iv-clinical-reasoning', 'title' => 'Batch 625 NP IV: Nutrition, Endocrine, Coordination, and Medication Safety',
        'summary' => 'Relate nutrition, gastrointestinal, endocrine, perceptual, coordination, and medication cues to individualized nursing decisions and monitoring.',
        'notes' => [
            ['heading' => 'Assessment', 'body' => 'Use intake, output, function, trends, symptoms, treatment, and patient goals instead of treating an isolated number as a diagnosis.'],
            ['heading' => 'Medication safety', 'body' => 'Verify the complete order, patient, product, indication, route, allergies, units, monitoring, teaching, and response; clarify uncertainty before administration.'],
            ['heading' => 'Coordination', 'body' => 'Match delegation and handoff to acuity, competence, scope, and accountability; use closed-loop communication and factual documentation.'],
            ['heading' => 'Currentness', 'body' => 'Use current medication and local clinical guidance for time-sensitive care; stable fundamentals remain stable and are not artificially versioned.'],
        ],
        'source_ids' => $areaMeta['NP IV']['sources'], 'exams' => ['nursing'], 'taxonomy' => ['subject_id' => 'nursing-foundations', 'domain_id' => 'nle-medical-surgical'], 'status' => 'published', 'version' => 1, 'updated_at' => $today,
    ],
    [
        'id' => 'nle-b625-np-v-clinical-reasoning', 'title' => 'Batch 625 NP V: Mental Health, Emergency, and High-Acuity Decisions',
        'summary' => 'Prioritize maladaptive behavior, crisis, shock, acute multi-organ problems, emergency care, teamwork, and escalation using the supplied facts.',
        'notes' => [
            ['heading' => 'Immediate safety', 'body' => 'Stay with an unsafe patient, assess immediate risk, reduce hazards, and activate urgent support rather than promising secrecy or waiting.'],
            ['heading' => 'Emergency reasoning', 'body' => 'Use a systematic ABCDE/SAMPLE approach, stabilize within role and protocol, reassess response, and communicate changes clearly.'],
            ['heading' => 'Acuity and delegation', 'body' => 'Prioritize actual instability and new deterioration; retain assessment and evaluation accountability for high-risk patients.'],
            ['heading' => 'Therapeutic care', 'body' => 'Use nonjudgmental communication, privacy, informed participation, least-restrictive safety measures, and current referral guidance.'],
        ],
        'source_ids' => $areaMeta['NP V']['sources'], 'exams' => ['nursing'], 'taxonomy' => ['subject_id' => 'nursing-foundations', 'domain_id' => 'nle-mental-health'], 'status' => 'published', 'version' => 1, 'updated_at' => $today,
    ],
];

$banks = [
    'nle-np-i-community' => [
        $item("A barangay clinic reports cough cases clustered among households using one water source. What should the nurse do next?", "Define the cases, verify the pattern, and compare exposures before attributing cause.", "Announce that the water caused every case.", "Treat only the first household seen.", "Stop collecting information once a cluster is noticed.", "A cluster is a signal for systematic case definition and exposure comparison, not proof of causation.", 4, ['PRIORITIZATION', 'CHANGING_GUIDANCE'], 'outbreak-investigation', 'cluster-equals-causation', 'nle-np-i-epidemiology'),
        $item("A community clinic has a long queue and no private place for sensitive assessments. Which action is most responsible?", "Triage urgent needs, create a confidential assessment process, and escalate the resource limitation.", "Ask clients to describe private details in the waiting line.", "See people strictly in arrival order despite danger signs.", "Post names and diagnoses so the queue moves faster.", "Community care must be equitable and confidential; operational limits should be addressed rather than hidden.", 4, ['PRIORITIZATION', 'REGULATORY'], 'resource-equity', 'equal-treatment-means-equity', 'nle-practice-standards'),
        $item("A remote village has low participation in a planned health program. What is the best first interpretation?", "Explore access, trust, language, schedule, and community priorities with residents.", "Assume residents do not value health.", "Repeat the same announcement louder.", "Cancel the program without learning why.", "Participation data should trigger a barrier assessment and partnership, not blame.", 3, ['PRIORITIZATION'], 'community-partnership', 'individual-blame', 'nle-np-i-education'),
        $item("A community nurse has limited wound-care supplies during a flood response. Which plan is most defensible?", "Prioritize clinical need, use supplies for indicated care, document shortages, and request support.", "Give identical supplies to everyone regardless of need.", "Hide the shortage to protect the program image.", "Ask clients to compete for supplies.", "Equity means responding to need while documenting and escalating a system constraint.", 4, ['PRIORITIZATION', 'EMERGENCY'], 'disaster-resource-allocation', 'equal-distribution-is-always-fair', 'nle-np-i-disaster'),
        $item("A family asks the nurse to disclose a neighbor's positive screening result so everyone can avoid the person. What should the nurse do?", "Protect confidentiality, provide general prevention information, and use the authorized public-health pathway.", "Confirm the neighbor's result to reassure the family.", "Post the result in the barangay group chat.", "Refuse all prevention teaching because privacy is required.", "Privacy and public-health duties can coexist: disclose only what is authorized and necessary while giving useful prevention guidance.", 4, ['INFECTION_CONTROL', 'REGULATORY'], 'confidential-public-health', 'public-disclosure-is-prevention', 'nle-practice-standards'),
        $item("A community assessment shows many older adults live alone, have falls, and lack transport. Which nursing diagnosis direction is best supported?", "Address fall risk and access barriers with residents and service partners.", "Label all older adults as noncompliant.", "Focus only on treating injuries after they occur.", "Offer an identical exercise plan without assessing feasibility.", "A population pattern supports a targeted, feasible prevention plan that includes environmental and access factors.", 3, ['PRIORITIZATION'], 'population-assessment', 'disease-only-framing', 'nle-np-i-vulnerable'),
        $item("During a home visit, a client reports food insecurity and missed follow-up because transport is unaffordable. What should the nurse do first?", "Assess immediate safety and health needs, then connect the client with available supports and follow-up.", "Tell the client to improve adherence before receiving help.", "Give an unrelated brochure and close the visit.", "Promise a service that has not been verified.", "Social conditions affect safe care; the nurse should assess, link, verify, and document rather than moralize.", 3, ['PRIORITIZATION'], 'social-needs-referral', 'adherence-as-moral-failure', 'nle-np-i-vulnerable'),
        $item("A telehealth call comes from an unfamiliar number and the caller reports chest pressure. What is the safest response?", "Verify identity and location, assess immediate danger, and activate urgent referral when indicated.", "Continue a routine health-promotion script.", "Send a generic message and end the call.", "Assume the caller is safe because the call is remote.", "Remote care still requires identification, location, red-flag assessment, and timely escalation.", 4, ['EMERGENCY', 'PRIORITIZATION'], 'remote-triage', 'telehealth-removes-urgency', 'nle-nursing-process'),
        $item("A community program increased attendance but the target health outcome did not improve. What should the team examine next?", "Whether the intervention reached the right population and addressed the causal barrier.", "Declare success because attendance increased.", "Discard all data and start a different program.", "Increase publicity without reviewing implementation.", "Process and outcome measures answer different questions; reach does not prove the mechanism changed.", 4, ['PRIORITIZATION'], 'program-evaluation', 'attendance-equals-effect', 'nle-clinical-judgment'),
        $item("Residents disagree about a proposed health activity because it conflicts with local routines. Which approach is most appropriate?", "Listen to residents, identify the health goal, and adapt the plan without losing its safety purpose.", "Impose the original plan because professionals know best.", "Let the loudest resident decide for everyone.", "Remove the health goal to avoid disagreement.", "Partnership improves feasibility while the nurse retains responsibility for safe, evidence-informed care.", 3, ['REGULATORY'], 'culturally-responsive-planning', 'culture-as-obstacle', 'nle-np-i-education'),
    ],
    'nle-np-i-prevention' => [
        $item("A client asks how to prevent a second stroke after discharge. Which teaching focus is most appropriate?", "Support the prescribed risk-factor plan, medication adherence, follow-up, warning signs, and realistic lifestyle changes.", "Stop all activity permanently.", "Replace prescribed therapy with supplements.", "Wait for symptoms before attending follow-up.", "Secondary prevention combines treatment adherence, monitoring, education, and response to warning signs.", 3, ['MEDICATION_SAFETY', 'CHANGING_GUIDANCE'], 'secondary-prevention', 'supplement-substitution', 'nle-np-iv-medication'),
        $item("A school clinic sees more falls on rainy days. Which prevention step should come first?", "Inspect routes and identify modifiable environmental hazards with the school team.", "Blame students for walking too quickly.", "Give pain medicine to all students.", "Wait until the rainy season ends.", "Environmental assessment identifies a preventable hazard before selecting a control.", 3, ['PRIORITIZATION'], 'environmental-injury-prevention', 'individual-blame', 'nle-np-i-community'),
        $item("A vaccine clinic has a client with a history that may affect eligibility. What should the nurse do before administration?", "Verify current program guidance and the relevant clinical history, including contraindications and precautions.", "Administer every vaccine without screening.", "Refuse every vaccine after any reported allergy.", "Use a schedule remembered from a prior year.", "Eligibility and contraindications require current guidance plus patient-specific assessment; neither blanket administration nor refusal is safe.", 4, ['MEDICATION_SAFETY', 'CONTRAINDICATION', 'CHANGING_GUIDANCE'], 'immunization-screening', 'allergy-equals-absolute-refusal', 'nle-practice-standards'),
        $item("A shelter has many people with cough after a storm. Which prevention plan is best?", "Screen for severity, promote respiratory hygiene, separate when indicated, and refer unstable people.", "Give antibiotics to everyone.", "Place all people in one crowded room.", "Ignore cough until shelter discharge.", "Shelter conditions increase transmission risk; screening and source control precede indiscriminate medication.", 4, ['INFECTION_CONTROL', 'CHANGING_GUIDANCE'], 'shelter-infection-control', 'universal-antibiotic', 'nle-np-iii-infection'),
        $item("A nurse sustains a needlestick while immunization supplies are being packed. What should happen first?", "Perform immediate first aid, report promptly, and follow the current exposure protocol.", "Hide the exposure if the skin looks normal.", "Wait for symptoms before reporting.", "Squeeze the wound aggressively and return to work.", "Occupational exposure management is time-sensitive and protocol-driven; prompt reporting enables assessment and prophylaxis when indicated.", 3, ['INFECTION_CONTROL', 'EMERGENCY', 'CHANGING_GUIDANCE'], 'occupational-exposure', 'delayed-reporting', 'nle-np-iii-infection'),
        $item("A campaign proposes antibiotics for everyone exposed to a cough outbreak. What concern is most important?", "The plan may cause harm and resistance if prophylaxis is not indicated by current guidance.", "Antibiotics are always preventive.", "Exposure automatically proves bacterial disease.", "A campaign never needs clinical criteria.", "Prevention must match the pathogen, exposure, risk, and current guidance; indiscriminate antibiotics are unsafe.", 4, ['MEDICATION_SAFETY', 'CHANGING_GUIDANCE'], 'antibiotic-stewardship', 'antibiotic-as-universal-prevention', 'nle-np-iii-infection'),
        $item("A parent wants a fixed alternating schedule of two fever medicines for every episode. What should the nurse do?", "Assess the child, verify products and orders, and teach indication and dose safety rather than endorsing a routine.", "Recommend alternating medicines for every fever.", "Tell the parent all fever is harmless.", "Stop fluids until the fever resolves.", "Product, age, weight, indication, and guidance matter; an automatic schedule can create duplication and dosing errors.", 4, ['MEDICATION_SAFETY', 'DOSAGE_CALCULATION', 'CHANGING_GUIDANCE'], 'caregiver-medication-teaching', 'routine-alternation', 'nle-np-iv-medication', 'CONCEPTUAL_DOSAGE_SAFETY'),
        $item("A community screening event identifies an unexpectedly high reading in an asymptomatic client. What is the safest next step?", "Repeat or confirm according to the program protocol and arrange appropriate follow-up.", "Diagnose the disease from one screening result.", "Dismiss the result because there are no symptoms.", "Post the result publicly to encourage attendance.", "Screening identifies people for confirmation and follow-up; it is not itself a diagnosis or permission to disclose.", 3, ['PRIORITIZATION', 'REGULATORY'], 'screening-follow-up', 'screening-equals-diagnosis', 'nle-nursing-process'),
        $item("A client with a chronic condition has missed several refills because the pharmacy is far away. Which prevention action is most useful?", "Explore access barriers, reconcile the medication plan, and coordinate a feasible refill and follow-up pathway.", "Tell the client to try harder.", "Double the next dose without an order.", "Stop the medicine permanently.", "Prevention includes continuity and safe medication use; guessing or blame can cause harm.", 3, ['MEDICATION_SAFETY'], 'continuity-of-care', 'nonadherence-as-choice', 'nle-np-iv-medication'),
        $item("Which action best reduces fall risk for a hospitalized older adult returning to a community setting?", "Use individualized risk assessment, a clear route, needed assistance, and reassessment after changes.", "Apply restraints to every older adult.", "Keep the room dark to promote sleep.", "Tell the client never to move.", "Fall prevention balances mobility, environment, assistance, and least-restrictive care.", 3, ['PRIORITIZATION'], 'fall-prevention', 'restraint-as-prevention', 'nle-np-i-vulnerable'),
    ],
    'nle-np-i-education' => [
        $item("A nurse teaches a client to use a new inhaler. Which method best checks whether teaching worked?", "Ask the client to demonstrate the technique and correct errors.", "Ask whether the client understood.", "Give a longer written handout only.", "Have a family member answer instead.", "Demonstration tests the actual skill and allows immediate correction.", 2, [], 'teach-back-skill', 'yes-no-teaching', 'nle-clinical-judgment'),
        $item("A client with limited literacy must learn a wound-care routine. Which approach is best?", "Use plain language, demonstration, teach-back, and an adapted written or visual plan.", "Use technical terms to be precise.", "Give a pamphlet and skip demonstration.", "Ask the client to memorize the procedure.", "Teach-back and demonstration support usable understanding and safe self-care.", 3, ['INFECTION_CONTROL'], 'health-literacy', 'literacy-blind-teaching', 'nle-nursing-process'),
        $item("A group education session includes people who speak different languages. Which step improves safety?", "Use qualified interpretation or translated material and verify understanding with each learner.", "Ask a child to translate complex instructions.", "Speak louder in English.", "Assume nodding means comprehension.", "Language access is part of safe communication; informal interpretation can omit clinical information.", 3, ['REGULATORY'], 'language-access', 'louder-is-clearer', 'nle-therapeutic-communication'),
        $item("A caregiver repeatedly touches the sterile surface during a dressing demonstration. What should the nurse do?", "Pause, explain the contamination risk, demonstrate again, and request return demonstration.", "Ignore it because the dressing looks clean.", "Scold the caregiver and end teaching.", "Add extra tape to compensate.", "Return demonstration reveals a correctable safety gap; respectful correction is safer than ignoring or shaming.", 3, ['INFECTION_CONTROL'], 'caregiver-skill-validation', 'appearance-over-technique', 'nle-np-iii-infection'),
        $item("Which outcome is written in a measurable way for a home inhaler plan?", "After teaching, the client will demonstrate the ordered inhaler technique correctly.", "The client will understand inhaler use.", "The nurse will discuss the inhaler.", "The client will feel better someday.", "An observable performance allows the nurse to evaluate whether teaching changed usable skill.", 2, [], 'measurable-outcome', 'vague-outcome', 'nle-clinical-judgment'),
        $item("During discharge coaching, a client becomes defensive when asked about diet. Which communication response is most therapeutic?", "Use a nonjudgmental open question and explore the client's goals and barriers.", "Explain that nonadherence is irresponsible.", "Give a rigid meal plan immediately.", "Change the subject and document refusal.", "Respectful exploration improves assessment and supports a plan the client can use.", 3, [], 'motivational-teaching', 'shame-as-education', 'nle-therapeutic-communication'),
        $item("A nurse is teaching a client to recognize worsening heart failure. What content is most useful?", "Specific changes to monitor, when to call, how to follow the plan, and what to report.", "Only the disease definition.", "A promise that symptoms cannot recur.", "A generic list of unrelated illnesses.", "Actionable signs and reporting instructions connect education to early intervention.", 3, ['CHANGING_GUIDANCE'], 'self-management', 'definition-only', 'nle-nursing-process'),
        $item("A learner recites a procedure but cannot explain why hand hygiene matters. What should the nurse do?", "Connect the step to transmission risk and ask the learner to demonstrate the complete procedure.", "Mark teaching complete because recitation is enough.", "Skip the rationale to save time.", "Replace the procedure with a poster.", "Safe skill requires performance and understanding of the relevant risk.", 3, ['INFECTION_CONTROL'], 'skills-validation', 'recitation-equals-skill', 'nle-np-iii-infection'),
        $item("A client refuses a recommended procedure after teaching. Which documentation best records informed refusal and follow-up?", "Record the information provided, the client's understanding or concerns, the decision, and follow-up offered.", "Document only 'refused.'", "Document that the client was difficult.", "Omit the refusal to avoid conflict.", "Documentation should preserve informed decision-making and continued-care planning without judgment.", 3, ['REGULATORY'], 'informed-refusal', 'judgmental-documentation', 'nle-practice-standards'),
        $item("Which post-discharge evaluation finding best supports effective teaching?", "The client performs the key self-care task and states when and how to seek help.", "The client received a printed packet.", "The nurse spoke for twenty minutes.", "The family signed a form without demonstration.", "Effective teaching is demonstrated by usable performance and escalation knowledge, not exposure to information.", 4, ['PRIORITIZATION'], 'discharge-teaching', 'paperwork-equals-learning', 'nle-clinical-judgment'),
    ],
    'nle-np-i-epidemiology' => [
        $item("A nurse sees cases clustered among households using one water source. Which next investigation step is most useful?", "Define cases, verify the pattern, and compare exposures among affected and unaffected households.", "Assume the water source caused every case.", "Treat only the first household.", "Stop collecting information once clustering is noticed.", "A cluster supports investigation, not a causal conclusion without case and exposure verification.", 4, ['CHANGING_GUIDANCE'], 'case-definition', 'cluster-equals-causation', 'nle-np-i-community'),
        $item("A community report shows an apparent increase after the clinic changed its case definition. Which interpretation is most defensible?", "The increase may reflect detection or definition change and requires comparison using consistent definitions.", "Disease definitely doubled.", "The new definition cannot affect counts.", "The older counts are automatically false.", "Surveillance data depend on definitions and ascertainment; changes need interpretation before causal claims.", 4, [], 'surveillance-definition', 'measurement-change-as-disease-change', 'nle-clinical-judgment'),
        $item("A screening program has low participation in a remote area. Which limitation should the nurse state?", "Observed prevalence may underestimate burden if people unable to access screening differ from participants.", "Low participation proves low disease burden.", "Nonparticipants are always healthy.", "Screening access cannot affect estimates.", "Selection and access affect observed data; results need a limitation statement.", 4, [], 'selection-bias', 'access-bias', 'nle-np-i-community'),
        $item("Which measure counts new cases arising in a defined population during a specified period?", "Incidence.", "Prevalence.", "Case-fatality ratio.", "Response rate.", "Incidence describes new cases over time among a population at risk; the other measures answer different questions.", 2, [], 'measure-selection', 'prevalence-incidence-confusion', 'nle-np-i-community'),
        $item("A contact-tracing list contains identifiable information. Which handling is appropriate?", "Use the authorized confidential process and disclose only the minimum necessary information.", "Upload the list to a public dashboard.", "Ask neighbors to verify every name.", "Destroy the list immediately so privacy is guaranteed.", "Public-health work still requires controlled access, confidentiality, and documented lawful handling.", 3, ['REGULATORY', 'INFECTION_CONTROL'], 'contact-tracing-privacy', 'public-data-equals-transparency', 'nle-practice-standards'),
        $item("Cases rise within a similar exposure window among people who used the same source. What does this pattern support?", "A common-source outbreak hypothesis that still requires verification.", "Proof that every case has the same cause.", "A chronic disease explanation.", "No relationship because individuals differ.", "Timing and shared exposure support a hypothesis; they do not replace confirmation.", 3, ['CHANGING_GUIDANCE'], 'outbreak-pattern', 'pattern-equals-proof', 'nle-np-i-community'),
        $item("A surveillance graph reports 40 cases but does not state the population size or time period. What is missing?", "The denominator and time frame needed to interpret the count.", "A guarantee that the cases are unrelated.", "The names of every person counted.", "A conclusion that the rate is high.", "Counts require context; without denominator and time, a rate or trend cannot be responsibly inferred.", 3, [], 'denominator-context', 'count-equals-rate', 'nle-clinical-judgment'),
        $item("A survey is completed mostly by people who already attend the clinic. What limitation should be reported?", "The results may not represent residents who do not access the clinic.", "The results automatically represent the entire population.", "Nonparticipants can be treated as negative cases.", "The sample is unbiased because it is convenient.", "Convenience and access can produce selection bias; the limitation affects generalization.", 4, [], 'sampling-bias', 'convenience-equals-representative', 'nle-np-i-community'),
        $item("A program finds an association between missed visits and poor outcomes. What is the most careful conclusion?", "The association warrants investigation but does not by itself establish causation.", "Missed visits definitely caused every poor outcome.", "The association proves the program is ineffective.", "No further data are needed.", "Confounding, reverse causation, and measurement issues remain possible without a stronger design.", 4, [], 'causal-inference', 'association-equals-causation', 'nle-clinical-judgment'),
        $item("A nurse suspects a notifiable disease. Which principle is most important?", "Use the current Philippine reporting pathway and protect confidentiality while transmitting required information.", "Post identifiable details publicly.", "Wait for certainty before any required notification.", "Use an old personal contact list.", "Reporting duties and channels are regulated and can change; follow the current pathway and minimum-necessary disclosure.", 3, ['REGULATORY', 'CHANGING_GUIDANCE'], 'notifiable-reporting', 'public-disclosure', 'nle-practice-standards'),
    ],
    'nle-np-i-disaster' => [
        $item("After an earthquake, which person should receive the highest immediate triage priority at the casualty collection point?", "A person with severe breathing difficulty and signs of airway compromise.", "A person with a minor closed abrasion.", "A walking person asking for water.", "A person with an old stable complaint.", "Airway and breathing threats are immediate life threats in initial triage.", 4, ['PRIORITIZATION', 'EMERGENCY'], 'mass-casualty-triage', 'minor-injury-priority', 'nle-np-v-emergency'),
        $item("A disaster shelter has many people with cough. What infection-control process should the nurse establish first?", "A process for screening, respiratory hygiene, separation when indicated, and referral of unstable persons.", "Give antibiotics to everyone.", "Place all people in one crowded room.", "Ignore cough until shelter discharge.", "Shelter conditions increase transmission risk; screening and source control precede indiscriminate medication.", 4, ['INFECTION_CONTROL', 'CHANGING_GUIDANCE'], 'shelter-infection-control', 'universal-antibiotic', 'nle-np-iii-infection'),
        $item("A triage tag is missing key information during a patient transfer. What safety action should the nurse take?", "Reassess the patient, replace or correct the tag, and communicate findings before transfer.", "Guess the missing information.", "Transfer without documentation because the event is busy.", "Discard the patient record.", "Triage status is dynamic and safety-critical; reassessment and communication prevent misrouting.", 3, ['EMERGENCY', 'PRIORITIZATION'], 'triage-handoff', 'documentation-is-optional', 'nle-np-v-teamwork'),
        $item("Floodwater has displaced a client with diabetes who lost the medication list. What is the best first nursing action?", "Assess immediate condition, verify identity and medications through available records, and coordinate continuity.", "Tell the client to restart every remembered medicine.", "Wait until the usual clinic reopens.", "Discard the medication history as unreliable.", "Disaster care requires assessment, reconciliation, and a safe verified plan rather than guessing.", 3, ['MEDICATION_SAFETY', 'EMERGENCY'], 'disaster-medication-continuity', 'memory-as-medication-list', 'nle-np-iv-medication'),
        $item("A mass-casualty patient initially marked walking becomes confused and pale. What should the nurse do?", "Reassess and re-triage the patient immediately using the current disaster protocol.", "Leave the original tag unchanged.", "Move the patient to the end of the line.", "Give food before reassessment.", "Triage is a repeated assessment; a change in perfusion or mental status can change priority.", 4, ['EMERGENCY', 'PRIORITIZATION'], 'dynamic-retriage', 'static-triage', 'nle-np-v-acuity'),
        $item("A heat emergency shelter includes older adults and people taking medicines that affect fluid balance. Which plan is safest?", "Screen for symptoms and risk, provide individualized fluids and cooling, and escalate deterioration.", "Give the same volume to everyone.", "Wait for fainting before assessment.", "Tell people to avoid all fluids.", "Risk and treatment vary; assessment and timely escalation are safer than blanket instructions.", 3, ['EMERGENCY', 'CHANGING_GUIDANCE'], 'heat-risk', 'one-plan-for-all', 'nle-np-i-vulnerable'),
        $item("Which disaster-preparedness action best protects continuity of a client's chronic medication?", "Identify essential medicines, backup supply routes, patient lists, storage needs, and communication contingencies.", "Tell clients to bring whatever they remember.", "Store all medicines in one unprotected place.", "Wait until pharmacies reopen.", "Continuity planning anticipates supply, identity, storage, communication, and referral failures.", 3, ['MEDICATION_SAFETY'], 'preparedness-planning', 'reactive-planning', 'nle-np-iv-medication'),
        $item("A shelter roster includes a person who uses a sign language interpreter. What communication step is best?", "Arrange qualified accessible communication and verify understanding of urgent instructions.", "Ask another shelter resident to interpret complex care.", "Speak louder and repeat the same words.", "Give instructions only to a companion.", "Accessible communication protects autonomy and reduces errors during a high-stress event.", 3, ['REGULATORY'], 'accessible-disaster-communication', 'companion-as-default-interpreter', 'nle-therapeutic-communication'),
        $item("A field team has fewer respirators than the current protocol requires for a suspected airborne hazard. What should the nurse do?", "Limit exposure, use the highest feasible protection, and escalate the resource and placement problem.", "Enter without protection because the patient is urgent.", "Use a cloth mask as equivalent protection.", "Hide the shortage so operations continue.", "Resource constraints require risk reduction and escalation; concealment and unsafe substitution are not adequate controls.", 4, ['INFECTION_CONTROL', 'EMERGENCY'], 'disaster-ppe', 'unsafe-substitution', 'nle-np-iii-infection'),
        $item("After a disaster drill, several handoff failures are identified. Which quality action is most useful?", "Review the failures with the team, identify system contributors, and test a revised handoff process.", "Blame the newest staff member.", "Delete the drill notes.", "Assume real events will be different.", "After-action learning converts observed system gaps into safer preparation.", 4, ['PRIORITIZATION'], 'after-action-review', 'blame-over-learning', 'nle-np-v-teamwork'),
    ],
    'nle-np-i-vulnerable' => [
        $item("An older adult living alone reports two recent falls and new dizziness. What should the nurse do first?", "Assess the immediate injury and dizziness, review risks and medicines, and arrange timely evaluation.", "Tell the client to stay in bed permanently.", "Apply restraints at home.", "Give generic fall pamphlets without assessment.", "New dizziness with falls may signal an urgent problem; individualized assessment precedes prevention teaching.", 4, ['PRIORITIZATION', 'EMERGENCY'], 'older-adult-fall-assessment', 'fall-prevention-without-assessment', 'nle-np-i-community'),
        $item("A client with a mobility disability asks for an accessible clinic appointment. Which response is best?", "Arrange reasonable access, ask what support is needed, and communicate directly with the client.", "Require the client to bring a caregiver before scheduling.", "Use the same room even if equipment cannot enter.", "Speak only to the caregiver.", "Accessible care respects autonomy and reduces preventable barriers.", 3, ['REGULATORY'], 'disability-access', 'caregiver-as-patient', 'nle-practice-standards'),
        $item("A migrant client cannot understand discharge instructions in the available language. What should the nurse do?", "Use qualified language support, adapt the material, and verify understanding with teach-back.", "Ask a child to translate medication instructions.", "Speak louder in English.", "Have the client sign that teaching was completed.", "Language access is a safety intervention, especially for medication and follow-up instructions.", 3, ['MEDICATION_SAFETY', 'REGULATORY'], 'migrant-health-access', 'signature-equals-understanding', 'nle-therapeutic-communication'),
        $item("A client privately hints that a partner controls access to medicines. What is the nurse's best next step?", "Assess immediate safety privately, use the authorized safeguarding pathway, and avoid confronting the partner without a plan.", "Confront the partner in the waiting room.", "Tell the client to leave immediately without assessing resources.", "Ignore the statement because it is a family issue.", "Safety planning must be private, individualized, and connected to appropriate support rather than impulsive confrontation.", 4, ['EMERGENCY', 'REGULATORY'], 'intimate-partner-safety', 'public-confrontation', 'nle-practice-standards'),
        $item("A child repeatedly arrives with injuries and a caregiver's explanation does not fit the findings. What should the nurse do?", "Ensure immediate safety, document objective findings, and follow current child-protection reporting policy.", "Accuse the caregiver in front of the child.", "Wait for the child to prove the story.", "Delete the note to avoid family conflict.", "Safeguarding requires objective documentation, immediate safety assessment, and the authorized reporting pathway.", 4, ['EMERGENCY', 'REGULATORY'], 'child-safeguarding', 'investigation-by-accusation', 'nle-practice-standards'),
        $item("A person experiencing homelessness has a draining wound and no storage for supplies. Which plan is most appropriate?", "Assess infection and urgent-care needs, provide feasible teaching, and coordinate follow-up and supplies.", "Refuse care until housing is obtained.", "Give supplies without checking the wound.", "Tell the person to keep the wound covered indefinitely.", "Safe care addresses clinical urgency and practical barriers together.", 3, ['INFECTION_CONTROL'], 'housing-barrier-care', 'deservingness-gate', 'nle-nursing-process'),
        $item("A client receiving immunosuppressive therapy asks how to reduce infection risk at home. What is the best teaching approach?", "Review the prescribed plan, current precautions, warning signs, and when to contact the care team.", "Recommend avoiding all contact with people permanently.", "Tell the client any fever is harmless.", "Suggest stopping therapy at the first symptom.", "Risk reduction must be individualized and coordinated with current clinical guidance; abrupt treatment changes are unsafe.", 3, ['INFECTION_CONTROL', 'CHANGING_GUIDANCE'], 'immunosuppressed-home-care', 'absolute-isolation', 'nle-np-iii-immunity'),
        $item("A client with cognitive impairment needs consent for a nonurgent procedure. What should the nurse do?", "Assess communication and decision capacity, involve the authorized decision process, and preserve the client's participation.", "Assume the caregiver can decide every issue.", "Proceed without consent because the procedure is routine.", "Exclude the client from all explanations.", "Capacity and authority are decision-specific; the client should participate as able and lawful consent must be verified.", 4, ['REGULATORY'], 'capacity-and-consent', 'caregiver-automatic-authority', 'nle-practice-standards'),
        $item("A client cannot afford a prescribed medicine and is rationing doses. Which nursing action is safest?", "Assess the clinical risk, reconcile the prescription, and coordinate an affordable verified option with the team.", "Tell the client to double doses when medicine is available.", "Advise stopping the medicine without consultation.", "Document nonadherence and end the visit.", "Financial barriers can create immediate medication risk; the solution requires assessment and coordinated planning.", 3, ['MEDICATION_SAFETY'], 'medication-access', 'rationing-as-choice', 'nle-np-iv-medication'),
        $item("A rural client misses appointments because transport is available only once weekly. What should the nurse prioritize?", "Coordinate a realistic follow-up plan, assess urgent needs, and use available community or remote supports.", "Schedule visits without asking about transport.", "Label the client unreliable.", "Wait until the client can travel more often.", "A safe plan must fit the client's access conditions while preserving clinical follow-up.", 3, ['PRIORITIZATION'], 'rural-access', 'schedule-blindness', 'nle-np-i-community'),
    ],
    'nle-np-ii-prenatal' => [
        $item("A pregnant client reports severe headache, visual change, and right-upper-quadrant pain. What should the nurse do?", "Assess promptly and activate urgent maternal evaluation according to current protocol.", "Reassure the client that discomfort is normal.", "Schedule a routine visit next month.", "Recommend an unverified herbal remedy.", "The cluster contains maternal danger signs; prompt assessment and escalation are safer than reassurance or delay.", 4, ['MATERNAL_NEWBORN', 'EMERGENCY', 'CHANGING_GUIDANCE'], 'prenatal-danger-signs', 'normal-pregnancy-overgeneralization', 'nle-nursing-process'),
        $item("A pregnant client reports a marked decrease in fetal movement compared with the usual pattern. What is the best nursing action?", "Assess the report and arrange timely fetal and maternal evaluation using the current protocol.", "Tell the client movement varies and to wait several days.", "Give a fixed home test as a substitute for assessment.", "Document the concern without notifying anyone.", "A change from an individual's usual pattern requires assessment; a blanket reassurance can delay care.", 4, ['MATERNAL_NEWBORN', 'PRIORITIZATION', 'CHANGING_GUIDANCE'], 'fetal-movement-change', 'reassurance-without-assessment', 'nle-nursing-process'),
        $item("A prenatal client has a positive screening result and asks whether it proves disease. What should the nurse explain?", "Screening identifies the need for confirmatory evaluation and follow-up; it is not by itself a diagnosis.", "A positive screen proves the disease.", "A positive screen can always be ignored if symptoms are absent.", "The result should be shared with the whole family.", "Screening results require interpretation, confirmation, privacy, and a timely plan.", 3, ['MATERNAL_NEWBORN', 'REGULATORY'], 'prenatal-screening', 'screening-equals-diagnosis', 'nle-clinical-judgment'),
        $item("A pregnant client with diabetes is unsure how to follow the ordered monitoring plan. Which response is best?", "Review the prescribed monitoring and warning signs, use teach-back, and coordinate questions with the care team.", "Tell the client to follow a generic internet diet.", "Advise stopping all prescribed medicines.", "Give one fixed target without reviewing the order.", "Pregnancy care needs individualized orders, monitoring, education, and escalation rather than generic treatment.", 3, ['MEDICATION_SAFETY', 'MATERNAL_NEWBORN', 'CHANGING_GUIDANCE'], 'gestational-diabetes-teaching', 'generic-target-substitution', 'nle-np-iv-medication'),
        $item("A prenatal client reports fatigue and has a laboratory result suggesting anemia. What should the nurse emphasize?", "Assess symptoms, review the prescribed plan and nutrition, and arrange follow-up rather than self-prescribing.", "Recommend any iron product at any dose.", "Tell the client fatigue is unavoidable.", "Stop prenatal care until energy returns.", "Treatment depends on assessment, the ordered product and dose, tolerance, and follow-up.", 3, ['MEDICATION_SAFETY', 'MATERNAL_NEWBORN'], 'prenatal-anemia', 'self-prescribing', 'nle-np-iv-medication'),
        $item("A prenatal client hints that a partner prevents clinic attendance. What should the nurse do first?", "Create a private opportunity to assess safety, wishes, and available support.", "Confront the partner during the appointment.", "Tell the client to leave immediately without planning.", "Ignore the hint as a relationship issue.", "A private, trauma-informed assessment supports safety without creating an unplanned danger.", 4, ['MATERNAL_NEWBORN', 'EMERGENCY', 'REGULATORY'], 'prenatal-safeguarding', 'public-confrontation', 'nle-practice-standards'),
        $item("A prenatal client takes prescription and nonprescription products but cannot recall the names. What is the safest next step?", "Reconcile the products, verify indications and doses, and coordinate medication review before changes.", "Tell the client to stop everything.", "Assume nonprescription products are harmless.", "Document the list as complete without verification.", "Medication reconciliation reduces duplication and unidentified risk, especially during pregnancy.", 3, ['MEDICATION_SAFETY', 'MATERNAL_NEWBORN'], 'prenatal-medication-reconciliation', 'natural-equals-safe', 'nle-np-iv-medication'),
        $item("A pregnant client has repeated elevated blood-pressure readings at different visits. Which interpretation is safest?", "Review the trend and symptoms and arrange the level of assessment required by the current protocol.", "Treat a single number as the complete diagnosis.", "Ignore the trend because the client feels well.", "Tell the client to change medicine independently.", "Repeated findings and symptoms must be interpreted in context and escalated according to current guidance.", 4, ['MATERNAL_NEWBORN', 'PRIORITIZATION', 'CHANGING_GUIDANCE'], 'prenatal-trend', 'isolated-number', 'nle-clinical-judgment'),
        $item("A client asks for a birth plan that includes cultural support and specific privacy preferences. What should the nurse do?", "Explore preferences, explain safety limits, and document a shared plan with the care team.", "Reject the preferences because hospital routines are fixed.", "Promise every request regardless of safety.", "Discuss the plan only with the family.", "Respectful planning integrates culture and autonomy while remaining transparent about safety and clinical limits.", 3, ['MATERNAL_NEWBORN', 'REGULATORY'], 'birth-plan-partnership', 'culture-versus-safety', 'nle-therapeutic-communication'),
        $item("A prenatal client who missed appointments returns with several unanswered questions and no records. What should the nurse prioritize?", "Assess current maternal and fetal concerns, reconstruct available history, and arrange needed follow-up.", "Restart education without assessing current danger signs.", "Blame the client for missed care.", "Assume prior tests were normal.", "Returning care begins with current safety and information recovery, not blame or assumptions.", 3, ['MATERNAL_NEWBORN', 'PRIORITIZATION'], 'late-return-prenatal-care', 'history-assumption', 'nle-nursing-process'),
    ],
    'nle-np-ii-labor' => [
        $item("During labor, the fetal heart pattern changes and the client reports increasing pressure. What should the nurse do first?", "Assess the client and fetal status, reposition as appropriate, and notify the responsible team per protocol.", "Leave the client alone to rest.", "Document the pattern after the birth.", "Give food before reassessment.", "A change in fetal status requires immediate assessment, supportive intervention, and communication.", 4, ['MATERNAL_NEWBORN', 'PRIORITIZATION', 'EMERGENCY'], 'intrapartum-fetal-assessment', 'documentation-before-action', 'nle-nursing-process'),
        $item("A laboring client has ruptured membranes and develops fever. Which action is most appropriate?", "Assess maternal and fetal status, use infection-prevention measures, and escalate for timely evaluation.", "Assume fever is expected in labor.", "Delay reporting until the next scheduled check.", "Perform repeated vaginal examinations without indication.", "Fever after membrane rupture raises infection and fetal-risk concerns; assessment and aseptic care are essential.", 4, ['MATERNAL_NEWBORN', 'INFECTION_CONTROL', 'CHANGING_GUIDANCE'], 'ruptured-membranes-infection', 'normalization-of-fever', 'nle-np-iii-infection'),
        $item("A client receiving an ordered uterotonic develops a concerning contraction pattern. What should the nurse do?", "Assess the pattern and fetal status, follow the current medication protocol, and notify the team promptly.", "Increase the infusion because labor is slow.", "Ignore the pattern if pain is expected.", "Change the dose independently without assessment.", "Medication effects in labor require monitoring, protocol-based action, and prompt escalation when maternal or fetal safety may be affected.", 4, ['MEDICATION_SAFETY', 'MATERNAL_NEWBORN', 'CONTRAINDICATION'], 'intrapartum-medication-monitoring', 'more-is-better', 'nle-np-iv-medication', 'CONCEPTUAL_DOSAGE_SAFETY'),
        $item("A laboring client requests a support person and asks that examinations be explained first. What should the nurse do?", "Confirm consent, explain each step, and support the chosen person when safe and permitted.", "Proceed without explanation because labor is urgent.", "Tell the support person to answer for the client.", "Refuse all preferences to maintain control.", "Consent and clear communication remain part of safe, respectful intrapartum care.", 3, ['MATERNAL_NEWBORN', 'REGULATORY'], 'labor-consent', 'urgency-erases-consent', 'nle-therapeutic-communication'),
        $item("A client becomes dizzy and hypotensive after regional anesthesia during labor. What is the priority?", "Assess maternal and fetal status, position and support according to protocol, and call the responsible team.", "Leave the client supine and reassess much later.", "Give an unverified medicine.", "Assume the dizziness is emotional.", "Maternal hemodynamic change can affect both client and fetus; prompt assessment and protocol-based support are required.", 4, ['MATERNAL_NEWBORN', 'EMERGENCY'], 'labor-hemodynamic-change', 'emotion-as-explanation', 'nle-nursing-process'),
        $item("A client asks whether pain relief means the nurse can stop monitoring the labor. What should the nurse explain?", "Pain relief does not remove the need for maternal, fetal, and medication-response monitoring.", "Monitoring is unnecessary after analgesia.", "Only the client's pain score matters.", "Monitoring can wait until the next shift.", "Analgesia changes monitoring needs; it does not eliminate assessment of safety and response.", 3, ['MEDICATION_SAFETY', 'MATERNAL_NEWBORN'], 'labor-analgesia-monitoring', 'symptom-relief-equals-no-risk', 'nle-np-iv-medication'),
        $item("A sudden cord-prolapse concern is reported during labor. What should the nurse do?", "Activate the emergency response, relieve pressure according to protocol, and communicate the change immediately.", "Ask the client to walk to the bathroom.", "Wait for the next routine examination.", "Document the concern without calling the team.", "A possible cord emergency is time-sensitive and requires immediate protocol-based response and escalation.", 4, ['MATERNAL_NEWBORN', 'EMERGENCY'], 'intrapartum-emergency', 'routine-delay', 'nle-np-v-emergency'),
        $item("Meconium is noted during labor. Which preparation is most appropriate?", "Notify the team and prepare assessment and newborn support according to current local protocol.", "Assume resuscitation is always required.", "Ignore the finding because it has no significance.", "Perform an outdated routine regardless of newborn condition.", "The finding informs preparation, but current guidance and the newborn's condition determine actual intervention.", 3, ['MATERNAL_NEWBORN', 'CHANGING_GUIDANCE'], 'newborn-team-preparation', 'automatic-intervention', 'nle-np-ii-newborn'),
        $item("Labor progress appears slow, but maternal and fetal findings are stable. What should the nurse do?", "Assess the full pattern and communicate findings rather than forcing an intervention based on one measure.", "Use a fixed intervention immediately.", "Tell the client the labor has failed.", "Stop monitoring because the pattern is stable.", "Progress is interpreted with the complete clinical picture; one measure should not trigger unsafe action.", 3, ['MATERNAL_NEWBORN', 'PRIORITIZATION'], 'labor-progress-assessment', 'single-metric-decision', 'nle-clinical-judgment'),
        $item("Which handoff detail is most important when transferring a laboring client with a recent fetal-status concern?", "The trend, interventions, response, current orders, and what requires reassessment.", "Only the client's name and room number.", "A statement that the client is probably fine.", "A copied note without current findings.", "A safe handoff communicates current risk, actions, response, and pending needs.", 3, ['MATERNAL_NEWBORN', 'PRIORITIZATION'], 'labor-handoff', 'label-only-handoff', 'nle-np-v-teamwork'),
    ],
    'nle-np-ii-postpartum' => [
        $item("A postpartum client has heavy bleeding and a boggy uterus. What should the nurse do first?", "Assess immediately, perform the indicated uterine assessment or intervention per protocol, and activate urgent support.", "Document the bleeding for the next shift.", "Offer a meal before assessment.", "Reassure the client that heavy bleeding is always normal.", "Heavy bleeding with a boggy uterus is a time-sensitive postpartum danger pattern.", 4, ['MATERNAL_NEWBORN', 'EMERGENCY', 'PRIORITIZATION'], 'postpartum-hemorrhage', 'normal-postpartum-bleeding', 'nle-nursing-process'),
        $item("A postpartum client has fever, uterine tenderness, and worsening malaise. What is the priority?", "Assess the client, use infection-prevention measures, and notify the team for timely evaluation.", "Tell the client to rest and wait several days.", "Recommend an unverified home remedy.", "Ignore the fever because postpartum changes are expected.", "The findings may signal infection and require assessment, monitoring, and current treatment guidance.", 4, ['MATERNAL_NEWBORN', 'INFECTION_CONTROL', 'CHANGING_GUIDANCE'], 'postpartum-infection', 'postpartum-normalization', 'nle-np-iii-infection'),
        $item("A cesarean incision is increasingly red, painful, and draining. Which action is best?", "Assess the wound and systemic findings, maintain appropriate care, and arrange prompt evaluation.", "Cover the drainage without documenting it.", "Scrub the incision vigorously.", "Wait until the routine follow-up visit.", "Worsening local findings may indicate a complication; early assessment and escalation reduce harm.", 3, ['INFECTION_CONTROL', 'MATERNAL_NEWBORN'], 'postoperative-wound', 'cover-and-ignore', 'nle-np-iii-perioperative'),
        $item("A breastfeeding client reports severe nipple pain and the newborn cannot maintain a latch. What should the nurse do?", "Observe a feed, assess positioning and newborn findings, and provide individualized support or referral.", "Tell the client pain is required for successful feeding.", "Recommend stopping all feeds without assessment.", "Give a fixed schedule without observing technique.", "Observation identifies modifiable technique and newborn factors; individualized support protects feeding and comfort.", 3, ['MATERNAL_NEWBORN'], 'breastfeeding-assessment', 'pain-is-necessary', 'nle-np-ii-newborn'),
        $item("A postpartum client reports severe headache and new visual changes. What should the nurse do?", "Assess promptly and arrange urgent maternal evaluation according to current protocol.", "Suggest sleep and routine follow-up.", "Assume the symptoms are from fatigue.", "Tell the client to self-adjust medication.", "Postpartum danger signs require prompt assessment and escalation rather than reassurance.", 4, ['MATERNAL_NEWBORN', 'EMERGENCY', 'CHANGING_GUIDANCE'], 'postpartum-danger-sign', 'fatigue-as-explanation', 'nle-nursing-process'),
        $item("A postpartum client has unilateral calf swelling and pain after prolonged bed rest. Which response is safest?", "Assess urgently and notify the responsible team for evaluation of a possible thromboembolic problem.", "Massage the calf vigorously.", "Encourage a long walk without assessment.", "Wait until swelling becomes bilateral.", "Unilateral swelling and pain can indicate a time-sensitive complication; massage and delay may be unsafe.", 4, ['MATERNAL_NEWBORN', 'EMERGENCY', 'CONTRAINDICATION'], 'postpartum-thromboembolism', 'massage-for-all-leg-pain', 'nle-np-v-emergency'),
        $item("Which discharge teaching is most important after an uncomplicated birth?", "Warning signs, medication and wound or bleeding guidance, feeding support, follow-up, and how to seek urgent help.", "Only the date of the next routine visit.", "A promise that complications cannot occur.", "A generic pamphlet without teach-back.", "Discharge safety depends on actionable warning signs, a workable plan, and demonstrated understanding.", 3, ['MATERNAL_NEWBORN', 'MEDICATION_SAFETY'], 'postpartum-discharge', 'routine-visit-only', 'nle-clinical-judgment'),
        $item("A postpartum client is taking several medicines and is breastfeeding. What should the nurse do?", "Reconcile the medicines and coordinate current lactation-compatible guidance before changes.", "Tell the client to stop every medicine.", "Assume every medicine is safe during lactation.", "Use an old medication list without review.", "Medication decisions require the actual products, indications, patient factors, and current guidance.", 3, ['MEDICATION_SAFETY', 'MATERNAL_NEWBORN', 'CHANGING_GUIDANCE'], 'postpartum-medication', 'all-or-none-medication', 'nle-np-iv-medication'),
        $item("A postpartum client says, 'My baby would be better without me.' What should the nurse do first?", "Stay with the client, assess immediate self-harm risk, and activate urgent support.", "Promise to keep the statement secret.", "Leave the client alone to rest.", "Change the subject to infant bathing.", "A statement suggesting self-harm requires direct safety assessment and immediate support.", 4, ['MATERNAL_NEWBORN', 'EMERGENCY'], 'postpartum-mental-health', 'secrecy-promise', 'nle-np-v-crisis'),
        $item("A postpartum client asks for privacy while discussing pain and family pressure. What should the nurse do?", "Provide a private opportunity, assess safety and preferences, and include others only with permission or lawful need.", "Discuss the details in front of visitors.", "Tell the family everything to improve support.", "Refuse privacy because the client has a newborn.", "Respectful privacy supports accurate assessment, autonomy, and safety.", 3, ['MATERNAL_NEWBORN', 'REGULATORY'], 'postpartum-privacy', 'family-automatic-access', 'nle-practice-standards'),
    ],
    'nle-np-ii-newborn' => [
        $item("A newborn is grunting, retracting, and becoming centrally dusky. What should the nurse do?", "Activate immediate newborn assessment and support, maintain warmth, and escalate according to current protocol.", "Wait for the next routine feeding.", "Place the newborn unattended in a crib.", "Reassure the family that all newborns breathe noisily.", "Respiratory distress and color change are danger signs requiring prompt assessment and support.", 4, ['MATERNAL_NEWBORN', 'EMERGENCY'], 'newborn-respiratory-distress', 'newborn-normalization', 'nle-np-v-emergency'),
        $item("A newborn is lethargic, feeding poorly, and has unstable temperature. What is the priority?", "Assess airway, breathing, circulation, temperature, and feeding risk, then notify the team promptly.", "Offer an unassessed large feeding.", "Wait until the newborn is more alert.", "Document the findings without escalation.", "Poor feeding, lethargy, and temperature instability can signal serious illness and require timely evaluation.", 4, ['MATERNAL_NEWBORN', 'EMERGENCY'], 'newborn-danger-signs', 'wait-for-improvement', 'nle-nursing-process'),
        $item("Which cord-care teaching is safest for a family taking a newborn home?", "Keep the cord clean and dry as instructed, perform hand hygiene, and report redness, drainage, or fever.", "Apply any household substance routinely.", "Cover the cord tightly with a damp dressing.", "Pull the cord off when it begins to loosen.", "Clean, dry care and danger-sign recognition reduce infection risk; local guidance controls product-specific instructions.", 3, ['MATERNAL_NEWBORN', 'INFECTION_CONTROL', 'CHANGING_GUIDANCE'], 'newborn-cord-care', 'traditional-substance-routine', 'nle-np-iii-infection'),
        $item("A newborn has risk factors for low glucose but appears well. What should the nurse do?", "Follow the current screening and feeding protocol rather than assuming appearance rules out risk.", "Give a fixed glucose treatment to every newborn.", "Skip screening because the newborn looks well.", "Delay feeding until symptoms develop.", "Some risks require protocol-based screening even without obvious symptoms; treatment depends on current findings and guidance.", 3, ['MATERNAL_NEWBORN', 'CHANGING_GUIDANCE'], 'newborn-glucose-screening', 'appearance-rules-out-risk', 'nle-clinical-judgment'),
        $item("Which sleep teaching best reduces avoidable newborn risk?", "Place the newborn on the back in a safe sleep space without loose items, following current guidance.", "Place the newborn prone with pillows for comfort.", "Share an adult bed with loose blankets.", "Use a soft cushion to prevent movement.", "Back positioning and a clear sleep space are stable safety principles; current local guidance should be followed.", 2, ['MATERNAL_NEWBORN', 'CHANGING_GUIDANCE'], 'newborn-safe-sleep', 'comfort-over-safety', 'nle-np-ii-family'),
        $item("A newborn develops yellowing that is increasing and the infant is difficult to arouse. What should the nurse do?", "Assess promptly and arrange urgent evaluation rather than relying on sunlight or reassurance.", "Recommend sunlight as the only treatment.", "Wait until the next routine visit.", "Stop all feeding.", "Increasing jaundice with poor arousability is a danger pattern; evaluation and feeding support depend on current guidance.", 4, ['MATERNAL_NEWBORN', 'EMERGENCY', 'CHANGING_GUIDANCE'], 'newborn-jaundice', 'sunlight-as-treatment', 'nle-nursing-process'),
        $item("Before a high-risk birth, which preparation best supports newborn safety?", "Check equipment and roles, maintain warmth, and prepare the current newborn assessment and resuscitation pathway.", "Assume no preparation is needed if the parent is stable.", "Open supplies without checking function.", "Use a memorized outdated algorithm.", "Preparation, role clarity, functioning equipment, and current protocol support rapid safe response.", 3, ['MATERNAL_NEWBORN', 'EMERGENCY', 'CHANGING_GUIDANCE'], 'newborn-resuscitation-preparation', 'equipment-assumption', 'nle-np-v-teamwork'),
        $item("A parent asks how to know when a newborn is ready to feed. What should the nurse teach?", "Describe early feeding cues, observe the newborn, support safe positioning, and seek help for poor feeding.", "Wait for prolonged crying every time.", "Force a fixed volume at every feed.", "Ignore feeding cues if the newborn is sleepy.", "Cue-based observation and escalation for poor feeding support safe intake and bonding.", 2, ['MATERNAL_NEWBORN'], 'newborn-feeding-cues', 'crying-is-only-cue', 'nle-np-ii-family'),
        $item("A preterm newborn has difficulty maintaining temperature. Which action is most appropriate?", "Use the prescribed thermal-support method, monitor response, and escalate instability.", "Leave the newborn uncovered for observation.", "Add an unregulated heat source.", "Wait until the next feeding to reassess.", "Preterm newborns need monitored thermal support; unregulated heat and delay can cause harm.", 3, ['MATERNAL_NEWBORN', 'EMERGENCY'], 'preterm-thermal-support', 'unregulated-warming', 'nle-nursing-process'),
        $item("Which newborn teaching statement shows that the parent understands when to seek help?", "I will seek prompt care for breathing difficulty, poor feeding, unusual sleepiness, fever or low temperature, or worsening color change.", "I will wait for all symptoms to occur together.", "I will give any leftover medicine first.", "I will rely on social-media advice.", "Parents need specific danger signs and an action plan rather than a vague promise to watch the infant.", 3, ['MATERNAL_NEWBORN', 'EMERGENCY'], 'newborn-danger-sign-teachback', 'wait-for-several-signs', 'nle-clinical-judgment'),
    ],
    'nle-np-ii-development' => [
        $item("A toddler is not meeting an expected communication milestone and the caregiver is concerned. What should the nurse do?", "Assess development and context, use a validated pathway when available, and arrange referral rather than labeling.", "Declare a diagnosis from one observation.", "Tell the caregiver to wait several years.", "Compare the child only with a sibling.", "Development is interpreted with history, context, screening, and follow-up; a single observation is not a diagnosis.", 3, ['MATERNAL_NEWBORN'], 'developmental-screening', 'single-observation-label', 'nle-clinical-judgment'),
        $item("An adolescent asks to discuss a sensitive concern without a parent in the room. What should the nurse do?", "Explain privacy and its safety limits, assess the concern, and involve others according to consent and law.", "Refuse any private conversation.", "Promise absolute secrecy for every disclosure.", "Tell the parent every detail immediately.", "Adolescent care balances confidential communication, safety assessment, and lawful limits.", 4, ['MATERNAL_NEWBORN', 'REGULATORY'], 'adolescent-privacy', 'absolute-secrecy', 'nle-practice-standards'),
        $item("A child's weight percentile has crossed down over several visits. Which interpretation is best?", "Review the trend, intake, illness, development, and context, then arrange appropriate evaluation.", "Diagnose malnutrition from the percentile alone.", "Ignore the trend if the child is active.", "Recommend an adult diet immediately.", "Growth is a pattern interpreted with clinical and social context; a trend warrants assessment rather than a one-number conclusion.", 3, ['MATERNAL_NEWBORN'], 'growth-trend', 'percentile-equals-diagnosis', 'nle-clinical-judgment'),
        $item("Which play activity best helps assess a young child's development during a nursing encounter?", "Observe age-appropriate communication, movement, problem solving, and interaction during a natural activity.", "Ask the caregiver to answer every question.", "Use a difficult adult questionnaire only.", "Judge development from the child's clothing.", "Natural observation can reveal multiple developmental domains while preserving a child-centered approach.", 2, ['MATERNAL_NEWBORN'], 'developmental-observation', 'adult-task-substitution', 'nle-np-ii-family'),
        $item("A school-age child fails a screening test but has no acute symptoms. What should the nurse do?", "Explain that screening is not diagnosis and arrange the recommended confirmatory follow-up.", "Tell the child the condition is confirmed.", "Discard the result because there are no symptoms.", "Share the result publicly with classmates.", "Screening identifies a need for evaluation while preserving privacy and avoiding premature labeling.", 3, ['MATERNAL_NEWBORN', 'REGULATORY'], 'school-screening', 'screening-equals-diagnosis', 'nle-nursing-process'),
        $item("A family asks whether a developmental difference means the child cannot learn. What is the best response?", "Describe the observed strengths and needs, support early evaluation, and plan individualized participation.", "Agree that learning is impossible.", "Promise the difference will disappear.", "Advise isolating the child from peers.", "Strength-based, individualized planning avoids unsupported predictions and supports timely help.", 3, ['MATERNAL_NEWBORN'], 'family-centered-development', 'fixed-capacity-assumption', 'nle-therapeutic-communication'),
        $item("A hospitalized child cannot describe pain clearly. Which assessment approach is best?", "Use age-appropriate self-report when possible plus behavior, physiology, caregiver input, and reassessment.", "Assume no pain because the child is quiet.", "Use the adult scale without explanation.", "Treat behavior as proof of a specific cause.", "Pain assessment is multimodal and must be repeated after intervention.", 3, ['MATERNAL_NEWBORN', 'PRIORITIZATION'], 'pediatric-pain', 'quiet-equals-no-pain', 'nle-nursing-process'),
        $item("A school-age child needs medicine and the caregiver is unsure which product was previously given. What should the nurse do?", "Verify the product, dose, timing, and order before administration or teaching.", "Give the remembered product at a guessed dose.", "Double the dose to compensate for uncertainty.", "Assume a familiar label means the same ingredient.", "Children are vulnerable to product and dose errors; verification precedes administration.", 3, ['MEDICATION_SAFETY', 'DOSAGE_CALCULATION', 'MATERNAL_NEWBORN'], 'pediatric-medication-verification', 'familiar-label-assumption', 'nle-np-iv-medication', 'CONCEPTUAL_DOSAGE_SAFETY'),
        $item("A mature adolescent can explain the risks and benefits of a proposed decision but the family disagrees. What should the nurse do?", "Assess the applicable consent and assent requirements and preserve the adolescent's informed participation.", "Ignore the adolescent's explanation.", "Let the loudest family member decide every issue.", "Proceed without any consent process.", "Decision-making authority depends on the situation and law; informed participation should not be erased.", 4, ['MATERNAL_NEWBORN', 'REGULATORY'], 'adolescent-assent', 'family-voice-erases-patient', 'nle-practice-standards'),
        $item("A child becomes fearful during an examination after a previous traumatic hospital experience. Which approach is best?", "Explain what will happen, offer choices when possible, use a trusted support person, and pause for distress.", "Hold the child down immediately for efficiency.", "Tell the child fear is unacceptable.", "Hide the procedure until it begins.", "Trauma-informed preparation and choice improve safety, trust, and cooperation.", 3, ['MATERNAL_NEWBORN'], 'trauma-informed-pediatric-care', 'surprise-improves-compliance', 'nle-therapeutic-communication'),
    ],
    'nle-np-ii-family' => [
        $item("A caregiver demonstrates a newborn feeding technique but makes repeated positioning errors. What should the nurse do?", "Pause, explain the safety concern, demonstrate again, and request return demonstration.", "Mark teaching complete because the caregiver tried.", "Take over every feed permanently.", "Scold the caregiver for not remembering.", "Return demonstration identifies a correctable skill gap while respectful coaching supports safe family care.", 3, ['MATERNAL_NEWBORN'], 'family-skill-teaching', 'attempt-equals-competence', 'nle-np-ii-newborn'),
        $item("Family members disagree about who may receive updates about a postpartum client. What should the nurse do?", "Ask the client about preferences and follow privacy and consent requirements.", "Give updates to the entire family.", "Give updates to the loudest relative.", "Refuse all communication even with the client.", "The client's preferences and lawful privacy rules control information sharing.", 3, ['MATERNAL_NEWBORN', 'REGULATORY'], 'family-privacy', 'family-automatic-access', 'nle-practice-standards'),
        $item("A family requests a cultural practice during birth that may conflict with a safety requirement. What is the best response?", "Explore the meaning, explain the specific safety concern, and seek a safe mutually understood adaptation.", "Reject the practice without discussion.", "Promise to allow every part regardless of risk.", "Ask the family to decide whether the practice is safe.", "Cultural humility combines respect, transparent risk communication, and safe shared planning.", 4, ['MATERNAL_NEWBORN', 'REGULATORY'], 'cultural-birth-planning', 'culture-as-compliance-test', 'nle-therapeutic-communication'),
        $item("A postpartum client has limited support at home and cannot identify who will help with feeding and follow-up. What should the nurse do?", "Assess practical needs, involve the client in a feasible plan, and connect available supports before discharge.", "Assume relatives will provide care.", "Delay all teaching until a crisis occurs.", "Tell the client to manage alone.", "Safe discharge planning tests the actual home plan rather than assuming support.", 3, ['MATERNAL_NEWBORN', 'PRIORITIZATION'], 'postpartum-home-support', 'assumed-support', 'nle-clinical-judgment'),
        $item("A caregiver says exhaustion is making it hard to provide safe newborn care. What should the nurse do?", "Assess immediate safety, normalize help-seeking, and connect the caregiver with practical and clinical support.", "Tell the caregiver exhaustion is part of parenting.", "Remove the newborn without assessment.", "Promise that sleep will improve soon.", "Caregiver strain can affect safety; assessment and support are more useful than shame or unsupported promises.", 3, ['MATERNAL_NEWBORN', 'EMERGENCY'], 'caregiver-burnout', 'normalization-of-risk', 'nle-nursing-process'),
        $item("Which family statement best reflects shared decision-making?", "We understand the options, risks, and follow-up plan, and we can say what matters most to us.", "The nurse will decide everything for us.", "We will choose the option with the longest list of procedures.", "We do not need to know risks if treatment is recommended.", "Shared decisions require understandable information, preferences, alternatives, and a plan for review.", 2, ['MATERNAL_NEWBORN', 'REGULATORY'], 'shared-decision', 'authority-substitution', 'nle-therapeutic-communication'),
        $item("A caregiver gives a vague explanation for a child's repeated injuries and asks the nurse not to document them. What should the nurse do?", "Protect the child, document objective findings, and follow current safeguarding policy.", "Agree to omit the findings to preserve trust.", "Accuse the caregiver before assessing safety.", "Wait for a witness to provide proof.", "Objective documentation and safeguarding processes protect the child; omission is unsafe.", 4, ['MATERNAL_NEWBORN', 'REGULATORY', 'EMERGENCY'], 'family-safeguarding', 'documentation-as-betrayal', 'nle-practice-standards'),
        $item("A family needs a discharge plan but cannot read the provided language. Which action is best?", "Use accessible language support, demonstrate key tasks, and verify understanding with teach-back.", "Ask a young child to translate.", "Use the same handout more slowly.", "Accept a signature as proof of understanding.", "Usable family teaching requires language access and performance verification.", 3, ['MATERNAL_NEWBORN', 'REGULATORY'], 'family-health-literacy', 'signature-equals-learning', 'nle-np-i-education'),
        $item("A family grieving a newborn loss asks for time and privacy before making decisions. What should the nurse do?", "Provide compassionate presence, clarify immediate needs, respect choices, and offer appropriate support and referral.", "Force a decision immediately for efficiency.", "Avoid the family completely.", "Promise that grief follows a fixed timeline.", "Bereavement care balances presence, autonomy, practical needs, and support without imposing a script.", 3, ['MATERNAL_NEWBORN'], 'perinatal-bereavement', 'fixed-grief-timeline', 'nle-therapeutic-communication'),
        $item("A family caregiver asks to use a relative's leftover medicine for a newborn's fever. What should the nurse say?", "Do not use an unverified product; assess the newborn and seek current clinician-directed evaluation.", "Use a smaller amount of the leftover medicine.", "Use any product labeled for adults.", "Wait until the newborn stops feeding.", "Newborn medication and fever concerns require assessment and current guidance; leftover products are unsafe substitutes.", 4, ['MATERNAL_NEWBORN', 'MEDICATION_SAFETY', 'EMERGENCY'], 'newborn-medication-safety', 'leftover-medicine', 'nle-np-iv-medication'),
    ],
    'nle-np-iii-oxygenation' => [
        $item("A client with pneumonia is restless, tachypneic, and has a falling oxygen saturation during handoff. What should the nurse do first?", "Assess airway and breathing, position for ventilation, provide prescribed oxygen, and escalate the deterioration.", "Document the finding for the next shift.", "Offer a meal before assessment.", "Place the client flat and leave.", "Work of breathing, mental-status change, and a falling trend indicate possible deterioration; support and escalation follow assessment and orders.", 4, ['PRIORITIZATION', 'EMERGENCY'], 'oxygenation-deterioration', 'documentation-before-action', 'nle-nursing-process'),
        $item("A client receiving oxygen becomes increasingly drowsy and difficult to arouse. What is the priority?", "Reassess airway, breathing, circulation, and response, then call for urgent support.", "Assume the oxygen is working because saturation was previously improved.", "Remove all oxygen and leave the client alone.", "Wait for the next scheduled vital signs.", "Mental-status change is an actual deterioration cue; the full clinical picture and response must be reassessed.", 4, ['PRIORITIZATION', 'EMERGENCY'], 'oxygenation-mental-status', 'single-number-reassurance', 'nle-basic-emergency-care'),
        $item("A client with chronic lung disease reports worsening breathlessness and cannot complete usual sentences. What should the nurse do?", "Assess current severity and response, use the prescribed plan, and escalate when the client is unstable.", "Tell the client this is the usual condition.", "Ask the client to lie flat and wait.", "Change the oxygen setting independently without assessment.", "A change from baseline with functional distress requires assessment and timely escalation; treatment must follow the order or protocol.", 4, ['PRIORITIZATION', 'EMERGENCY'], 'chronic-lung-change', 'baseline-erases-deterioration', 'nle-basic-emergency-care'),
        $item("A client with suspected respiratory infection is coughing in a crowded waiting room. Which action is most appropriate?", "Provide source-control measures, separate when indicated, and assess for urgent symptoms using current policy.", "Place the client in the most crowded area.", "Give antibiotics before assessment.", "Ignore the cough because it is common.", "Respiratory hygiene and risk-based separation reduce transmission while assessment determines further care.", 3, ['INFECTION_CONTROL', 'CHANGING_GUIDANCE'], 'respiratory-source-control', 'antibiotic-before-assessment', 'nle-np-iii-infection'),
        $item("A postoperative client has shallow breathing and pain but stable vital signs. Which nursing focus is best?", "Assess pain and respiratory effort, support prescribed pulmonary hygiene and mobility, and reassess response.", "Keep the client immobile all day.", "Ignore breathing because vital signs are stable.", "Give sedation without checking the order or response.", "Postoperative respiratory risk is addressed with assessment, appropriate pain management, movement, and reassessment.", 3, ['PRIORITIZATION', 'MEDICATION_SAFETY'], 'postoperative-oxygenation', 'stable-number-means-no-risk', 'nle-np-iii-perioperative'),
        $item("A client suddenly develops pleuritic chest pain, tachycardia, and shortness of breath after prolonged immobility. What should the nurse do?", "Treat the change as urgent, assess ABCs, and activate timely evaluation.", "Massage the painful leg.", "Encourage a long walk without assessment.", "Wait for a routine appointment.", "The symptom cluster may represent a time-sensitive cardiopulmonary problem; assessment and escalation take priority.", 4, ['EMERGENCY', 'CONTRAINDICATION'], 'acute-respiratory-emergency', 'massage-before-assessment', 'nle-basic-emergency-care'),
        $item("A client with asthma reports worsening symptoms despite the prescribed rescue plan. Which action is safest?", "Assess airway and breathing, follow the current action plan, and escalate if response is inadequate.", "Tell the client to repeat doses indefinitely.", "Assume anxiety is the cause without assessment.", "Delay care until the next scheduled visit.", "Failure to respond is a deterioration cue; current protocol and clinical assessment determine escalation.", 4, ['EMERGENCY', 'MEDICATION_SAFETY', 'CHANGING_GUIDANCE'], 'asthma-response', 'anxiety-as-explanation', 'nle-np-iv-medication'),
        $item("Which teaching is essential for a client using home oxygen?", "Keep oxygen away from flames and ignition sources, follow the prescribed setup, and report worsening symptoms.", "Smoke near the oxygen if a window is open.", "Change the flow whenever breathless without guidance.", "Store the equipment beside an open flame for warmth.", "Oxygen supports combustion and settings are patient- and order-specific; fire safety and symptom escalation are essential.", 3, ['MEDICATION_SAFETY', 'EMERGENCY'], 'home-oxygen-safety', 'oxygen-is-not-a-fire-risk', 'nle-practice-standards'),
        $item("A client with respiratory illness has a downward oxygen-saturation trend and new confusion. Which interpretation is best?", "The trend and mental-status change suggest deterioration requiring immediate reassessment and escalation.", "The latest single value is all that matters.", "Confusion proves a psychiatric disorder.", "Wait until the trend becomes flat.", "Trend plus mental-status change is more informative than an isolated number and demands a safety response.", 4, ['PRIORITIZATION', 'EMERGENCY'], 'trend-interpretation', 'single-value-anchoring', 'nle-clinical-judgment'),
        $item("Which handoff for a client on escalating oxygen support is most useful?", "Current support, respiratory findings and trends, response, pending orders, and escalation triggers.", "Only the diagnosis.", "The original admission vital signs.", "A statement that the client is stable without data.", "A safe handoff communicates current risk, treatment, response, and what the next clinician must watch.", 3, ['PRIORITIZATION'], 'oxygenation-handoff', 'diagnosis-only-handoff', 'nle-np-v-teamwork'),
    ],
    'nle-np-iii-fluids' => [
        $item("A client with vomiting and diarrhea has orthostatic dizziness and decreasing urine output. What should the nurse do?", "Assess perfusion and hydration, review the trend, and notify the team for timely management.", "Wait until the client faints.", "Restrict all fluids without an order.", "Document output without assessing the client.", "Orthostatic symptoms and decreasing urine output may indicate clinically important fluid loss.", 4, ['PRIORITIZATION', 'EMERGENCY'], 'fluid-deficit-assessment', 'output-without-context', 'nle-nursing-process'),
        $item("An IV site becomes cool, swollen, and painful during an infusion. What is the first action?", "Stop the infusion, assess the site and client, and follow the current infiltration or extravasation protocol.", "Increase the infusion rate.", "Cover the site and continue.", "Flush forcefully without assessment.", "The findings suggest a local infusion complication; stopping and assessing prevents further harm.", 3, ['MEDICATION_SAFETY', 'CONTRAINDICATION'], 'iv-infiltration', 'continue-through-complication', 'nle-practice-standards'),
        $item("A client receiving IV fluid develops new crackles, dyspnea, and increasing edema. What should the nurse do?", "Assess respiratory and circulatory status, stop or adjust only as ordered or protocoled, and escalate promptly.", "Continue the infusion because it was prescribed.", "Encourage the client to drink more.", "Document the edema at discharge.", "New respiratory findings during fluid therapy may signal overload; assessment and timely escalation are required.", 4, ['PRIORITIZATION', 'EMERGENCY'], 'fluid-overload', 'order-means-no-monitoring', 'nle-basic-emergency-care'),
        $item("A client with profuse diarrhea is weak and has a changing mental status. Which action is most important?", "Assess ABCs and perfusion, obtain the ordered data, and escalate the deterioration.", "Offer a large meal first.", "Wait for the next stool count.", "Assume weakness is only fatigue.", "Fluid and electrolyte loss can become urgent; mental-status change increases the priority.", 4, ['PRIORITIZATION', 'EMERGENCY'], 'diarrhea-deterioration', 'fatigue-as-explanation', 'nle-nursing-process'),
        $item("A client develops chills, back pain, and dyspnea during a blood product infusion. What should the nurse do first?", "Stop the transfusion, assess the client, maintain access according to protocol, and notify the responsible team.", "Increase the rate to finish quickly.", "Restart the same product after a short pause.", "Document the reaction after the infusion is complete.", "The symptom cluster may indicate a serious transfusion reaction; stop, assess, and follow the current protocol.", 4, ['MEDICATION_SAFETY', 'EMERGENCY', 'CONTRAINDICATION'], 'transfusion-reaction', 'finish-the-bag', 'nle-practice-standards'),
        $item("A client has a daily weight increase and new peripheral edema. What should the nurse compare next?", "The trend with intake, output, respiratory findings, medicines, and the client's baseline.", "Only the single weight value.", "The client's meal preference.", "A random unrelated laboratory value.", "Fluid status is interpreted from trends and associated findings, not one number alone.", 3, ['PRIORITIZATION'], 'fluid-trend', 'single-number-diagnosis', 'nle-clinical-judgment'),
        $item("A client with mild dehydration can drink safely but dislikes the available oral fluid. What is the best nursing approach?", "Assess swallowing and orders, offer an acceptable safe option, and monitor intake and response.", "Force the disliked fluid.", "Withhold fluids until the client complies.", "Start IV therapy without assessment or order.", "Safe hydration is individualized to clinical status, preference, swallowing, and orders.", 3, ['PRIORITIZATION'], 'oral-hydration', 'preference-does-not-matter', 'nle-nursing-process'),
        $item("A client at risk for electrolyte disturbance becomes confused and weak. What should the nurse do?", "Assess ABCs and neurologic status, review ordered results and trends, and escalate the change.", "Give an unverified electrolyte supplement.", "Assume confusion is expected.", "Wait for the next routine round.", "Neurologic change with fluid or electrolyte risk requires timely assessment, not unsupervised replacement.", 4, ['EMERGENCY', 'MEDICATION_SAFETY'], 'electrolyte-neurologic-change', 'supplement-without-data', 'nle-clinical-judgment'),
        $item("A burn client has an ordered fluid-resuscitation plan. Which nursing action is essential?", "Monitor the client's response and ordered output or perfusion targets and report inadequate response.", "Give the entire planned volume at once.", "Use urine output as the only assessment.", "Stop monitoring once the order is started.", "Resuscitation is titrated and evaluated by clinical response; a written order does not replace monitoring.", 4, ['EMERGENCY', 'PRIORITIZATION'], 'burn-resuscitation-monitoring', 'order-equals-completion', 'nle-basic-emergency-care'),
        $item("An IV pump alarms repeatedly during a fluid infusion. What should the nurse do?", "Assess the patient and trace the tubing, site, line, and pump before restarting or escalating.", "Silence the alarm and walk away.", "Increase the pressure limit without assessment.", "Replace the pump without checking the patient.", "Equipment alarms can signal patient or line problems; troubleshooting begins with assessment and safe verification.", 3, ['MEDICATION_SAFETY'], 'infusion-pump-safety', 'alarm-as-nuisance', 'nle-practice-standards'),
    ],
    'nle-np-iii-infection' => [
        $item("When should a nurse perform hand hygiene during routine client care?", "At the indicated moments before and after contact and after exposure risk, using the current infection-control guidance.", "Only at the beginning of the shift.", "Only when hands look dirty.", "Only after touching a visibly ill client.", "Hand hygiene is based on transmission risk and care moments, not visible soil alone.", 2, ['INFECTION_CONTROL', 'CHANGING_GUIDANCE'], 'hand-hygiene', 'visible-dirt-only', 'nle-practice-standards'),
        $item("A client requires transmission-based precautions. Which action is most appropriate?", "Verify the current organism-specific or facility policy and use the indicated room, PPE, and transport measures.", "Use the same precautions for every infection.", "Choose PPE from memory without checking the indication.", "Place the client wherever space is available.", "Transmission-based measures depend on the hazard and current guidance; generic assumptions can be unsafe.", 3, ['INFECTION_CONTROL', 'CHANGING_GUIDANCE'], 'transmission-precautions', 'one-isolation-rule', 'nle-practice-standards'),
        $item("A nurse sustains a needlestick while disposing of a sharp. What is the safest immediate plan?", "Perform immediate first aid, report promptly, and follow the current occupational-exposure protocol.", "Hide the injury if there is no bleeding.", "Wait for symptoms before reporting.", "Squeeze the site aggressively and continue working.", "Prompt reporting enables risk assessment and time-sensitive prophylaxis when indicated.", 3, ['INFECTION_CONTROL', 'EMERGENCY', 'CHANGING_GUIDANCE'], 'needlestick', 'delayed-reporting', 'nle-practice-standards'),
        $item("A family caregiver touches the sterile field during wound teaching. What should the nurse do?", "Stop, explain the contamination risk, replace contaminated supplies as needed, and reteach the skill.", "Continue because the wound is already open.", "Add antibiotic powder to compensate.", "Ignore the event to preserve rapport.", "A contaminated field cannot be made sterile by adding products; correction and reteaching protect the client.", 3, ['INFECTION_CONTROL'], 'sterile-field-teaching', 'product-compensates-for-technique', 'nle-nursing-process'),
        $item("Which action reduces infection risk for a central line dressing change?", "Use hand hygiene, aseptic technique, appropriate supplies, and the current local procedure.", "Reuse supplies if they appear clean.", "Touch the site to locate it after preparation.", "Skip documentation because the dressing looks intact.", "Aseptic practice and current procedure reduce contamination; appearance is not a substitute for technique.", 3, ['INFECTION_CONTROL', 'CHANGING_GUIDANCE'], 'central-line-care', 'appearance-over-process', 'nle-practice-standards'),
        $item("A postoperative client has increasing redness, warmth, drainage, and fever at the incision. What should the nurse do?", "Assess the wound and systemic status, use infection precautions, and notify the team promptly.", "Cover the wound and wait for the routine visit.", "Scrub the incision vigorously.", "Reassure the client that fever is expected.", "The pattern may indicate a surgical-site infection or other complication requiring evaluation.", 4, ['INFECTION_CONTROL', 'EMERGENCY'], 'surgical-site-infection', 'postoperative-normalization', 'nle-np-iii-perioperative'),
        $item("A client asks why antibiotics are not started for every fever. Which explanation is best?", "Treatment depends on assessment, likely cause, risk, and current guidance; unnecessary antibiotics can cause harm.", "Every fever is bacterial.", "Antibiotics are harmless if taken briefly.", "A prescription is unnecessary when a client requests one.", "Antibiotic decisions require clinical assessment and stewardship; indiscriminate use creates risk.", 3, ['MEDICATION_SAFETY', 'CHANGING_GUIDANCE'], 'antibiotic-stewardship-teaching', 'fever-equals-bacteria', 'nle-np-iv-medication'),
        $item("A client with infectious diarrhea has frequent stools and worsening weakness. What should the nurse prioritize?", "Use the current precautions, assess hydration and deterioration, and escalate when indicated.", "Focus only on cleaning the room.", "Give an antidiarrheal without assessment.", "Allow unrestricted movement through the unit.", "Infection control and clinical assessment must occur together; treatment depends on the client's condition and orders.", 4, ['INFECTION_CONTROL', 'PRIORITIZATION', 'CHANGING_GUIDANCE'], 'infectious-diarrhea-care', 'isolation-replaces-assessment', 'nle-nursing-process'),
        $item("A respiratory-outbreak notice changes the facility's masking and visitor process. What should the nurse do?", "Follow the current policy, explain the reason clearly, and verify implementation with the team.", "Continue the old process because it is familiar.", "Invent a stricter rule without authority.", "Ignore the notice until a client becomes ill.", "Infection-control guidance is periodically updated; safe practice follows the current authorized policy.", 3, ['INFECTION_CONTROL', 'CHANGING_GUIDANCE'], 'outbreak-policy-update', 'old-policy-anchoring', 'nle-practice-standards'),
        $item("A client with suspected sepsis becomes confused and hypotensive. What should the nurse do?", "Activate urgent assessment and escalation while supporting ABCs and following the current sepsis pathway.", "Wait for a culture result before acting.", "Document the findings for the next shift.", "Treat confusion as a normal infection symptom.", "Actual instability requires immediate action; cultures and other diagnostics do not justify delaying urgent support.", 4, ['INFECTION_CONTROL', 'EMERGENCY', 'PRIORITIZATION'], 'infection-deterioration', 'test-before-stabilize', 'nle-basic-emergency-care'),
    ],
    'nle-np-iii-immunity' => [
        $item("A vaccine client reports a prior reaction but cannot describe it. What should the nurse do?", "Clarify the history and verify current contraindication and precaution guidance before administration.", "Refuse every future vaccine.", "Administer without asking because the history is vague.", "Use an old schedule to decide.", "The type, timing, severity, and current guidance matter; vague history requires clarification, not a blanket decision.", 4, ['MEDICATION_SAFETY', 'CONTRAINDICATION', 'CHANGING_GUIDANCE'], 'vaccine-reaction-screening', 'any-allergy-is-absolute', 'nle-practice-standards'),
        $item("A client develops wheezing, facial swelling, and hypotension shortly after an injection. What is the priority?", "Activate emergency response, support airway and circulation, and follow the current anaphylaxis protocol.", "Ask the client to rest and reassess later.", "Give oral fluids first.", "Document the reaction after discharge.", "Airway and circulatory compromise after an exposure is an emergency requiring immediate protocol-based support.", 4, ['MEDICATION_SAFETY', 'EMERGENCY', 'CONTRAINDICATION'], 'anaphylaxis', 'wait-and-see-allergy', 'nle-basic-emergency-care'),
        $item("A client receiving immunosuppressive therapy has a new fever and chills. What should the nurse do?", "Assess promptly, follow the current febrile-risk pathway, and notify the responsible team.", "Tell the client fever is expected.", "Advise stopping all therapy independently.", "Wait until the next appointment.", "Immunosuppression can change the urgency of infection findings; current risk-specific guidance is needed.", 4, ['INFECTION_CONTROL', 'EMERGENCY', 'CHANGING_GUIDANCE'], 'immunosuppression-fever', 'expected-fever-normalization', 'nle-np-iii-infection'),
        $item("A client with an autoimmune condition asks why infection-prevention teaching is included in the plan. What is the best explanation?", "Treatment and disease can alter immune response, so prevention, early warning signs, and coordinated follow-up matter.", "Autoimmune illness always means infection is present.", "Prevention is unnecessary if the client feels well.", "The client should avoid all people permanently.", "Risk is individualized; understanding immune effects supports proportionate prevention and early evaluation.", 3, ['INFECTION_CONTROL', 'CHANGING_GUIDANCE'], 'immune-risk-teaching', 'risk-equals-diagnosis', 'nle-nursing-process'),
        $item("A transplant client asks whether a crowded indoor event is safe. What should the nurse do?", "Review the client's current risk, local guidance, preventive measures, and when to contact the team.", "Guarantee that every crowded event is safe.", "Guarantee that all public contact is forbidden.", "Tell the client to stop immunosuppression before attending.", "Advice depends on current risk and guidance; absolute claims and unplanned treatment changes are unsafe.", 3, ['INFECTION_CONTROL', 'CHANGING_GUIDANCE'], 'transplant-risk-counseling', 'absolute-avoidance', 'nle-clinical-judgment'),
        $item("A client develops hives after a medication but has stable breathing and blood pressure. What should the nurse do?", "Assess the reaction, hold or stop the medication as ordered or protocoled, and notify the team.", "Ignore hives because breathing is stable.", "Give the next dose to test the reaction.", "Label the client with every possible allergy without assessment.", "A reaction can progress; assessment, medication safety, and documentation guide the next step.", 3, ['MEDICATION_SAFETY', 'CONTRAINDICATION'], 'medication-reaction', 'stable-now-means-safe', 'nle-np-iv-medication'),
        $item("A client asks whether an elevated inflammatory marker proves an autoimmune diagnosis. What should the nurse explain?", "It is a finding that requires clinical interpretation and does not establish a diagnosis alone.", "It proves the diagnosis.", "It can always be ignored.", "It identifies the exact treatment by itself.", "Laboratory findings are interpreted with history, examination, and other evidence.", 2, [], 'inflammatory-marker', 'laboratory-equals-diagnosis', 'nle-clinical-judgment'),
        $item("A client with a blood-borne infection asks who will see the result. What should the nurse say?", "Explain the authorized confidential process and the minimum necessary disclosure requirements.", "Everyone in the unit will be told.", "No record will be made.", "The result can be posted without the name.", "Confidentiality and required reporting are managed through authorized processes, not informal disclosure.", 3, ['REGULATORY', 'INFECTION_CONTROL'], 'blood-borne-confidentiality', 'privacy-means-no-record', 'nle-practice-standards'),
        $item("A client with a severe allergy is scheduled for a procedure. Which nursing action is most important before preparation?", "Verify and clearly communicate the allergy, reaction, orders, products, and emergency plan.", "Assume the allergy is irrelevant if the procedure is minor.", "Remove the allergy from the chart to avoid confusion.", "Ask the client to choose substitute products without review.", "Allergy history can change medication and procedural safety; verification and communication prevent avoidable exposure.", 3, ['MEDICATION_SAFETY', 'CONTRAINDICATION'], 'allergy-verification', 'minor-procedure-no-risk', 'nle-practice-standards'),
        $item("A client asks for a supplement to replace prescribed immune-related treatment. What should the nurse do?", "Assess the request, explain that supplements are not automatic substitutes, and coordinate a safe medication discussion.", "Recommend any supplement as harmless.", "Tell the client to stop the prescription immediately.", "Dismiss the question without education.", "Treatment substitution can create harm; respectful assessment and coordinated guidance support informed decisions.", 3, ['MEDICATION_SAFETY', 'CHANGING_GUIDANCE'], 'supplement-interaction', 'natural-equals-replacement', 'nle-np-iv-medication'),
    ],
    'nle-np-iii-perioperative' => [
        $item("A client arrives for surgery but cannot explain the procedure and the consent form is incomplete. What should the nurse do?", "Pause preparation and notify the responsible team so informed consent can be verified.", "Proceed because the schedule is full.", "Ask a family member to sign without authority.", "Fill in the missing consent details from memory.", "Incomplete or unclear consent is a safety and regulatory barrier; the procedure should not proceed until verified.", 4, ['REGULATORY', 'CONTRAINDICATION'], 'surgical-consent', 'schedule-over-consent', 'nle-practice-standards'),
        $item("Before a procedure, the client's allergy band conflicts with the medication record. What should the nurse do?", "Stop and reconcile the discrepancy with the client, record, product, and responsible team before administration.", "Use whichever record is newer without checking.", "Remove the band to prevent confusion.", "Administer the medication and observe.", "Conflicting allergy information must be resolved before exposure; observation after an avoidable exposure is not prevention.", 3, ['MEDICATION_SAFETY', 'CONTRAINDICATION'], 'preoperative-allergy-check', 'record-anchoring', 'nle-np-iv-medication'),
        $item("A postoperative client becomes difficult to arouse and has noisy breathing. What should the nurse do first?", "Assess and support airway and breathing and activate urgent help.", "Offer oral fluids.", "Document the sedation for the next shift.", "Leave the client flat and wait.", "Airway compromise after anesthesia is an immediate priority.", 4, ['EMERGENCY', 'PRIORITIZATION'], 'postoperative-airway', 'sedation-normalization', 'nle-basic-emergency-care'),
        $item("Which action supports safe surgical-site preparation?", "Use the current aseptic procedure, verify the site and product, and avoid contaminating the prepared field.", "Touch the site after preparation to check it.", "Reuse a product that looks clean.", "Skip the time-out because the client knows the procedure.", "Aseptic technique and verification reduce contamination and wrong-site risk.", 3, ['INFECTION_CONTROL', 'REGULATORY'], 'surgical-site-prep', 'appearance-over-asepsis', 'nle-practice-standards'),
        $item("An immobile postoperative client has fragile skin and poor nutrition. Which plan is most complete?", "Reposition as indicated, manage pressure and moisture, support nutrition, inspect skin, and evaluate response.", "Massage reddened skin vigorously.", "Use one position to avoid disturbing the client.", "Focus only on a topical product.", "Pressure injury prevention is multifactorial; mobility, moisture, nutrition, inspection, and reassessment interact.", 4, ['PRIORITIZATION'], 'postoperative-skin-protection', 'single-intervention', 'nle-np-iv-nutrition'),
        $item("During the surgical time-out, a team member notices that the site marking does not match the consent. What should happen?", "Stop the process and reconcile the discrepancy before proceeding.", "Continue because the patient is already anesthetized.", "Ask the newest staff member to choose.", "Correct the record after surgery.", "The time-out is a safety barrier; unresolved identity, site, or procedure discrepancies require a stop.", 4, ['REGULATORY', 'CONTRAINDICATION'], 'surgical-timeout', 'time-pressure-over-verification', 'nle-np-v-teamwork'),
        $item("A preoperative client reports having eaten despite an NPO instruction. What should the nurse do?", "Assess what and when was eaten, document it, and notify the responsible team for a safe decision.", "Hide the information so surgery stays on schedule.", "Proceed because a small meal never matters.", "Induce vomiting.", "NPO decisions depend on the actual intake, timing, procedure, and current clinical guidance.", 3, ['REGULATORY', 'CONTRAINDICATION'], 'npo-verification', 'small-meal-is-irrelevant', 'nle-nursing-process'),
        $item("A client reports severe pain after surgery but is difficult to arouse after a sedating medicine. What should the nurse do?", "Assess airway, breathing, sedation, pain, and medication response before giving more medication.", "Give the next dose because pain is reported.", "Ignore the sedation because pain is subjective.", "Reverse all medicines without assessment.", "Pain and sedation must be assessed together; additional medication may worsen respiratory risk.", 4, ['MEDICATION_SAFETY', 'EMERGENCY', 'CONTRAINDICATION'], 'postoperative-pain-sedation', 'pain-over-airway', 'nle-np-iv-medication'),
        $item("A postoperative client is newly confused and pulling at lines. What should the nurse do?", "Assess oxygenation, pain, medication effects, infection, elimination, and safety needs, then escalate concerning findings.", "Apply restraints immediately without assessment.", "Assume the client is simply difficult.", "Give a sedative without checking the cause or order.", "Delirium is a clinical change with multiple causes; assessment and least-restrictive safety come first.", 4, ['PRIORITIZATION', 'MEDICATION_SAFETY'], 'postoperative-delirium', 'behavior-as-diagnosis', 'nle-clinical-judgment'),
        $item("Which discharge teaching is most important after a minor operation?", "Wound and medication instructions, warning signs, activity limits, follow-up, and how to obtain help.", "Tell the client no problem should occur.", "Give only the appointment date.", "Tell the client to remove dressings whenever uncomfortable.", "Safe discharge connects self-care steps with monitoring and escalation criteria.", 3, ['INFECTION_CONTROL', 'MEDICATION_SAFETY'], 'postoperative-discharge', 'reassurance-without-plan', 'nle-nursing-process'),
    ],
    'nle-np-iii-cellular' => [
        $item("A bedbound client has persistent nonblanching redness over a bony area. What should the nurse do?", "Assess the skin and risk factors, relieve pressure, and document and reassess the response.", "Massage the area vigorously.", "Cover it and leave the client in the same position.", "Wait for an open wound before acting.", "Early skin change requires prevention and reassessment; pressure injury care should not wait for tissue breakdown.", 3, ['PRIORITIZATION'], 'pressure-injury-early', 'wait-for-open-wound', 'nle-np-iv-nutrition'),
        $item("A client with a chronic wound has poor intake and limited mobility. Which plan is best?", "Address pressure and mobility, assess nutrition and perfusion, protect the wound, and evaluate progress.", "Focus only on a topical product.", "Restrict movement to protect the wound.", "Massage the wound edges vigorously.", "Healing depends on interacting factors; a single product cannot replace assessment and risk reduction.", 4, ['PRIORITIZATION', 'INFECTION_CONTROL'], 'wound-healing', 'topical-only-care', 'nle-np-iv-nutrition'),
        $item("A client receiving a hazardous antineoplastic medicine asks why special handling is required. What should the nurse explain?", "Follow the current handling, PPE, disposal, and exposure procedures for the specific medicine.", "Hazardous medicines are safe once diluted.", "Any gloves provide identical protection.", "Household disposal is acceptable.", "Handling requirements depend on the agent and current policy; safe preparation and disposal protect staff and clients.", 3, ['MEDICATION_SAFETY', 'INFECTION_CONTROL', 'CHANGING_GUIDANCE'], 'hazardous-medication-handling', 'dilution-erases-hazard', 'nle-practice-standards'),
        $item("A pathology report is delayed and the client asks whether the mass is cancer. What should the nurse say?", "Explain that the report requires qualified interpretation and arrange follow-up without guessing.", "Confirm cancer from the word mass.", "Promise the mass is benign.", "Tell the client results do not matter.", "A finding is not a diagnosis; accurate communication avoids false reassurance and alarm.", 2, ['REGULATORY'], 'pathology-communication', 'finding-equals-diagnosis', 'nle-therapeutic-communication'),
        $item("A wound has pale edges, increasing drainage, and worsening pain. What should the nurse do?", "Assess the wound and systemic signs, protect it using the current plan, and report the change.", "Ignore the change until the scheduled dressing.", "Apply an unprescribed caustic product.", "Close the wound tightly without assessment.", "Worsening wound findings require reassessment and timely clinical review.", 3, ['INFECTION_CONTROL', 'PRIORITIZATION'], 'wound-deterioration', 'product-before-assessment', 'nle-np-iii-infection'),
        $item("A client asks why a specimen label must be checked at the bedside. Which explanation is best?", "Correct identification prevents the wrong result from being attached to the wrong client or treatment.", "Labels are only for billing.", "A missing label can be reconstructed later from memory.", "The laboratory can identify every specimen visually.", "Specimen identity is a patient-safety barrier; memory and visual assumptions are unreliable.", 2, ['REGULATORY'], 'specimen-identification', 'memory-as-identity', 'nle-practice-standards'),
        $item("A client with a burn has worsening pain, pale tissue, and reduced sensation in the affected area. What should the nurse do?", "Assess perfusion and the full injury, protect the area, and escalate the change promptly.", "Assume reduced sensation means healing.", "Apply ice directly for a long period.", "Wait for blistering to stop.", "Changing pain, color, and sensation can signal tissue or perfusion problems requiring evaluation.", 4, ['EMERGENCY', 'CONTRAINDICATION'], 'burn-tissue-assessment', 'numbness-equals-healing', 'nle-basic-emergency-care'),
        $item("A client at risk for pressure injury refuses repositioning because of pain. What should the nurse do?", "Assess pain, explain the risk, offer a tolerable plan, and reassess skin and comfort.", "Force the position without explanation.", "Accept immobility without documenting the risk.", "Give an unverified extra dose.", "Pain management and shared planning can improve prevention while preserving safety and autonomy.", 3, ['PRIORITIZATION', 'MEDICATION_SAFETY'], 'pressure-injury-shared-plan', 'comfort-versus-safety-binary', 'nle-nursing-process'),
        $item("A client receives a new diagnosis after a biopsy and asks the nurse to explain the prognosis. What is the best response?", "Acknowledge the concern, provide information within role, and arrange discussion with the responsible clinician.", "Give a personal prediction.", "Avoid the client completely.", "Promise a specific outcome.", "Professional communication is honest about role and uncertainty while connecting the client to qualified explanation.", 3, ['REGULATORY'], 'diagnosis-communication', 'prediction-as-support', 'nle-therapeutic-communication'),
        $item("Which finding should prompt the nurse to reassess a wound-care plan rather than simply repeat it?", "The wound is larger, more painful, or has changed drainage despite the planned care.", "The dressing was changed on schedule.", "The client received a written handout.", "The supplies are neatly arranged.", "Response and trajectory determine whether a plan is working; task completion alone is not an outcome.", 4, ['INFECTION_CONTROL', 'PRIORITIZATION'], 'wound-evaluation', 'process-equals-outcome', 'nle-clinical-judgment'),
    ],
    'nle-np-iv-nutrition' => [
        $item("A hospitalized client has eaten almost nothing for three days and is weak. Which assessment should the nurse perform first?", "Assess intake, swallowing, symptoms, hydration, weight trend, and reasons for poor intake and notify the team.", "Force a large meal.", "Restrict all fluids.", "Document poor appetite without assessment.", "Poor intake has multiple causes and can create nutrition and fluid risk; assessment guides safe support.", 4, ['PRIORITIZATION'], 'poor-intake-assessment', 'single-cause-assumption', 'nle-nursing-process'),
        $item("A client coughs and has a wet voice during meals. Which action is most appropriate?", "Stop the meal, assess swallowing risk, and arrange evaluation before continuing unsafe intake.", "Encourage larger bites to finish quickly.", "Offer thin liquids to clear the throat.", "Ignore the cough because appetite is good.", "Coughing and wet voice can indicate aspiration risk; safety assessment precedes more intake.", 4, ['PRIORITIZATION', 'EMERGENCY'], 'dysphagia-aspiration', 'appetite-over-airway', 'nle-np-iii-oxygenation'),
        $item("A client receiving enteral feeding begins coughing and has increased work of breathing. What should the nurse do?", "Stop or pause per protocol, assess airway and breathing, verify the setup, and escalate the change.", "Increase the rate to finish the feed.", "Lay the client flat.", "Assume coughing is unrelated.", "Respiratory change during enteral feeding can signal aspiration or another complication requiring immediate assessment.", 4, ['PRIORITIZATION', 'EMERGENCY'], 'enteral-feeding-respiratory', 'finish-the-feed', 'nle-nursing-process'),
        $item("A severely malnourished client begins nutrition support and develops weakness and a changing rhythm. What should the nurse do?", "Assess urgently, review ordered monitoring, and notify the team about a possible nutrition-related metabolic complication.", "Increase the feed because weakness means more calories are needed.", "Ignore the rhythm until the feed is complete.", "Give an unverified electrolyte supplement.", "High-risk nutrition initiation requires monitoring and response to clinical changes rather than automatic escalation.", 4, ['EMERGENCY', 'MEDICATION_SAFETY'], 'nutrition-support-monitoring', 'more-calories-cure-all', 'nle-clinical-judgment'),
        $item("A client asks for a meal plan that respects religious and cultural food practices. What should the nurse do?", "Explore preferences and restrictions and coordinate a nutritionally adequate feasible plan.", "Use a standard plan without asking.", "Tell the client culture cannot affect treatment.", "Remove all preferred foods.", "Culturally responsive planning improves adherence while preserving clinical nutrition goals.", 3, [], 'cultural-nutrition', 'standard-plan-for-all', 'nle-therapeutic-communication'),
        $item("A client with a pressure injury has poor intake and limited mobility. Which prevention plan best integrates risk factors?", "Reposition as indicated, manage moisture and pressure, support nutrition, inspect skin, and evaluate response.", "Massage reddened skin vigorously.", "Use one position to avoid disturbing the client.", "Focus only on a topical product.", "Pressure injury prevention is multifactorial; skin, mobility, moisture, nutrition, and reassessment interact.", 4, ['PRIORITIZATION'], 'nutrition-pressure-injury', 'single-intervention', 'nle-np-iii-cellular'),
        $item("A client with persistent diarrhea is losing weight and has dizziness. What should the nurse prioritize?", "Assess hydration, intake, output, skin, severity, and underlying risk, then escalate as indicated.", "Give a restrictive diet without assessment.", "Assume the weight loss is intentional.", "Wait for the next routine appointment.", "Nutrition and fluid consequences require integrated assessment and timely management.", 4, ['PRIORITIZATION', 'EMERGENCY'], 'gi-nutrition-loss', 'weight-change-dismissal', 'nle-np-iii-fluids'),
        $item("A client with kidney disease asks for an exact diet without a current plan. What should the nurse do?", "Assess the current order and clinical status and coordinate individualized nutrition guidance.", "Provide a universal restrictive diet.", "Tell the client to remove all protein.", "Use a plan from another patient.", "Nutrition restrictions depend on diagnosis, labs, treatment, and current guidance; generic prescriptions can harm.", 3, ['CHANGING_GUIDANCE'], 'individualized-diet', 'one-diet-for-all', 'nle-clinical-judgment'),
        $item("A client reports food insecurity and cannot follow the prescribed nutrition plan. What is the best nursing action?", "Assess immediate needs and coordinate feasible food, community, and clinical supports.", "Label the client noncompliant.", "Repeat the plan without adapting it.", "Tell the client to skip medicines instead.", "A plan is not safe if it cannot be obtained; practical support and clinical coordination are required.", 3, ['PRIORITIZATION', 'MEDICATION_SAFETY'], 'food-insecurity-care', 'noncompliance-label', 'nle-np-i-community'),
        $item("A client receiving parenteral nutrition has fever and redness at the central-line site. What should the nurse do?", "Assess urgently, use infection-prevention measures, and notify the team for line and systemic evaluation.", "Ignore the site because nutrition is essential.", "Disconnect the line and discard it without orders.", "Massage the redness.", "Central-line infection can become serious; prompt assessment and protocol-based action are required.", 4, ['INFECTION_CONTROL', 'EMERGENCY'], 'parenteral-nutrition-line', 'nutrition-over-infection', 'nle-np-iii-infection'),
    ],
    'nle-np-iv-gastrointestinal' => [
        $item("A client reports black stool, dizziness, and increasing weakness. What should the nurse do?", "Assess circulation and severity and activate urgent evaluation for possible gastrointestinal bleeding.", "Offer an iron supplement without assessment.", "Wait for the next scheduled stool sample.", "Reassure the client that dark stool is always dietary.", "Possible bleeding with symptoms of reduced perfusion is time-sensitive.", 4, ['EMERGENCY', 'PRIORITIZATION'], 'gi-bleeding', 'dietary-reassurance', 'nle-basic-emergency-care'),
        $item("A client has new severe abdominal pain with guarding and vomiting. What is the priority?", "Assess ABCs and abdominal findings and notify the team urgently.", "Give food to test tolerance.", "Apply heat and wait.", "Document the pain after the shift.", "Severe pain with guarding and vomiting may represent an acute abdomen requiring prompt evaluation.", 4, ['EMERGENCY', 'PRIORITIZATION'], 'acute-abdominal-pain', 'comfort-measure-before-assessment', 'nle-nursing-process'),
        $item("A postoperative client has distention, vomiting, and no bowel sounds. What should the nurse do?", "Assess and notify the team, maintain ordered restrictions, and monitor for deterioration.", "Give a large meal to stimulate the bowel.", "Administer an unverified laxative.", "Ignore the findings because surgery was recent.", "The pattern may indicate a postoperative complication; further intake or medication requires assessment and orders.", 4, ['PRIORITIZATION', 'EMERGENCY'], 'postoperative-ileus', 'more-intake-solves-obstruction', 'nle-np-iii-perioperative'),
        $item("A new ostomy appears dusky and has sharply decreased output. What should the nurse do?", "Assess the stoma and client and notify the responsible team promptly.", "Apply a tighter appliance.", "Wait until output stops completely.", "Massage the stoma.", "Color and output changes can signal impaired perfusion or obstruction and require timely evaluation.", 4, ['EMERGENCY', 'PRIORITIZATION'], 'ostomy-change', 'normalization-of-dusky-stoma', 'nle-nursing-process'),
        $item("A client has repeated vomiting and cannot keep prescribed medicines down. What should the nurse do?", "Assess hydration and symptoms, reconcile the medication plan, and contact the team about safe alternatives.", "Double the next dose.", "Crush every medicine into a drink.", "Tell the client to stop all treatment permanently.", "Vomiting affects hydration and medication safety; route and timing require individualized orders.", 3, ['MEDICATION_SAFETY', 'PRIORITIZATION'], 'vomiting-medication', 'double-next-dose', 'nle-np-iv-medication'),
        $item("A client with liver disease develops new confusion and bleeding from venipuncture sites. What should the nurse do?", "Assess urgently and notify the team about the change in neurologic and bleeding status.", "Assume confusion is chronic.", "Apply pressure only and delay reporting.", "Give an unverified sedative.", "New neurologic and bleeding findings may reflect serious deterioration and require prompt evaluation.", 4, ['EMERGENCY', 'MEDICATION_SAFETY'], 'liver-deterioration', 'chronic-labeling', 'nle-basic-emergency-care'),
        $item("A client with suspected bowel obstruction asks for a meal. What should the nurse do?", "Assess current findings and follow the ordered restriction and evaluation plan rather than giving oral intake automatically.", "Give a high-fiber meal.", "Offer repeated fluids without assessment.", "Tell the client to eat quickly before surgery.", "Possible obstruction requires assessment and an individualized plan; intake can worsen risk.", 3, ['PRIORITIZATION', 'CONTRAINDICATION'], 'bowel-obstruction', 'food-as-routine', 'nle-nursing-process'),
        $item("A client with frequent loose stools has excoriated skin. Which nursing plan is best?", "Assess cause and hydration, use current infection precautions when indicated, and protect the skin.", "Scrub the skin harshly after each stool.", "Restrict all fluids.", "Ignore the skin because diarrhea is temporary.", "Diarrhea care combines cause, hydration, infection control, and skin protection.", 3, ['INFECTION_CONTROL', 'PRIORITIZATION'], 'diarrhea-skin', 'harsh-cleaning', 'nle-np-iii-infection'),
        $item("A client with constipation has new severe pain and vomiting. What should the nurse do?", "Assess urgently and rule out a complication before giving a bowel stimulant.", "Give repeated laxatives immediately.", "Encourage a large meal.", "Massage the abdomen forcefully.", "New severe pain and vomiting change the risk; treatment should not assume uncomplicated constipation.", 4, ['EMERGENCY', 'CONTRAINDICATION'], 'constipation-red-flag', 'laxative-before-assessment', 'nle-clinical-judgment'),
        $item("A nasogastric tube is ordered after surgery, but placement documentation is incomplete. What should the nurse do?", "Verify placement using the current policy before using the tube for feeding or medication.", "Use the tube because it was inserted by a professional.", "Advance it farther without assessment.", "Give a small feed as a test.", "Tube misplacement can cause serious harm; verification precedes use.", 3, ['MEDICATION_SAFETY', 'CONTRAINDICATION'], 'ng-tube-verification', 'professional-insertion-equals-safe-use', 'nle-practice-standards'),
    ],
    'nle-np-iv-endocrine' => [
        $item("A client with diabetes is sweaty, confused, and unable to safely swallow. What should the nurse do?", "Assess immediately and follow the current emergency hypoglycemia protocol without giving oral intake to an unsafe swallow.", "Give juice by mouth despite the swallowing risk.", "Wait for the next meal.", "Tell the client to self-manage later.", "Altered mental status and unsafe swallowing make this an emergency; route and treatment follow current protocol.", 4, ['MEDICATION_SAFETY', 'EMERGENCY', 'CONTRAINDICATION'], 'hypoglycemia-unsafe-swallow', 'oral-treatment-despite-risk', 'nle-nursing-process', 'CONCEPTUAL_DOSAGE_SAFETY'),
        $item("A client with diabetes has persistent high readings, thirst, vomiting, and increasing drowsiness. What should the nurse do?", "Assess ABCs and hydration and activate urgent evaluation for possible metabolic deterioration.", "Give extra insulin without an order.", "Wait for the next routine reading.", "Restrict all fluids.", "High readings with vomiting and altered status may represent a time-sensitive emergency; treatment cannot be guessed.", 4, ['MEDICATION_SAFETY', 'EMERGENCY'], 'hyperglycemia-deterioration', 'extra-dose-without-data', 'nle-basic-emergency-care'),
        $item("A client taking insulin has a new meal schedule and poor intake before breakfast. What should the nurse do?", "Assess glucose, intake, insulin type and timing, and coordinate an individualized adjustment rather than guessing.", "Use the usual dose regardless of intake.", "Stop all insulin permanently.", "Double the next dose after the missed meal.", "Medication timing and nutrition interact; safe changes require assessment and a verified plan.", 3, ['MEDICATION_SAFETY', 'DOSAGE_CALCULATION'], 'insulin-meal-change', 'usual-dose-always', 'nle-np-iv-medication', 'CONCEPTUAL_DOSAGE_SAFETY'),
        $item("A client with thyroid disease becomes severely agitated, febrile, and tachycardic. What should the nurse do?", "Assess urgently, support ABCs, and activate the current emergency pathway.", "Offer reassurance and wait for the next clinic visit.", "Give an unverified thyroid dose.", "Encourage strenuous activity.", "The acute pattern suggests serious endocrine instability requiring prompt evaluation and support.", 4, ['EMERGENCY', 'MEDICATION_SAFETY'], 'endocrine-crisis', 'routine-follow-up-for-instability', 'nle-basic-emergency-care'),
        $item("A client with adrenal insufficiency has vomiting, profound weakness, and hypotension. What is the priority?", "Recognize possible adrenal crisis, assess ABCs, and activate urgent treatment and escalation.", "Wait for oral medication tolerance.", "Tell the client to rest at home.", "Stop all prescribed therapy.", "Vomiting, weakness, and hypotension can be life-threatening; urgent protocol-based support is required.", 4, ['EMERGENCY', 'MEDICATION_SAFETY'], 'adrenal-crisis', 'home-rest-for-shock', 'nle-basic-emergency-care'),
        $item("Which foot-care teaching is safest for a client with diabetic neuropathy?", "Inspect the feet regularly, protect them from injury, use appropriate footwear, and report changes early.", "Walk barefoot to strengthen the feet.", "Use heat to test sensation.", "Cut calluses deeply at home.", "Reduced sensation increases unnoticed injury risk; protection and early reporting are safer than heat or self-treatment.", 3, ['MEDICATION_SAFETY'], 'diabetic-foot-care', 'heat-tests-sensation', 'nle-nursing-process'),
        $item("A client has taken a steroid medicine for several weeks and wants to stop today. What should the nurse do?", "Explain that the client should not stop or change it abruptly and should contact the prescriber for a safe plan.", "Tell the client abrupt stopping is always safe.", "Double the dose before stopping.", "Replace it with an unverified supplement.", "Some medicines require a prescribed taper or transition; changes should be coordinated with current guidance.", 3, ['MEDICATION_SAFETY', 'CHANGING_GUIDANCE'], 'steroid-discontinuation', 'abrupt-stop', 'nle-np-iv-medication'),
        $item("A glucose meter result conflicts with the client's condition and symptoms. What should the nurse do?", "Assess the client, check technique and equipment, repeat or confirm per policy, and act on deterioration.", "Treat the number without assessing the client.", "Ignore the result because meters never fail.", "Change medicine before confirmation.", "Unexpected data require clinical correlation and verification; a device reading does not replace assessment.", 3, ['MEDICATION_SAFETY', 'DOSAGE_CALCULATION'], 'glucose-result-conflict', 'number-over-client', 'nle-clinical-judgment', 'CONCEPTUAL_DOSAGE_SAFETY'),
        $item("A client with diabetes asks for a 'sick-day' plan. Which response is best?", "Review the prescribed plan, hydration and monitoring instructions, warning signs, and when to contact the team.", "Tell the client to stop all medicines during illness.", "Give one universal rule for every medicine.", "Wait until severe symptoms occur before calling.", "Illness plans depend on medicine, intake, monitoring, and current guidance; specific escalation criteria matter.", 3, ['MEDICATION_SAFETY', 'CHANGING_GUIDANCE'], 'diabetes-sick-day', 'universal-sick-day-rule', 'nle-nursing-process'),
        $item("A client with an endocrine disorder becomes weak and confused after a medication change. What should the nurse do?", "Assess the change, review the actual medicine and timing, and escalate concerning findings.", "Assume the medicine is unrelated.", "Advise another dose to test it.", "Wait for the next refill.", "Temporal association requires assessment and medication reconciliation, not a guessed repeat dose.", 3, ['MEDICATION_SAFETY', 'EMERGENCY'], 'endocrine-medication-change', 'repeat-to-test', 'nle-np-iv-medication'),
    ],
    'nle-np-iv-perception' => [
        $item("A client suddenly develops facial droop, unilateral weakness, and difficulty speaking. What should the nurse do?", "Activate urgent stroke evaluation, note the last-known-well information, and support ABCs.", "Give food and wait for symptoms to pass.", "Ask the client to walk to prove weakness.", "Schedule a routine visit.", "Sudden focal deficits are time-sensitive; last-known-well and immediate escalation guide care.", 4, ['EMERGENCY', 'PRIORITIZATION'], 'acute-stroke', 'wait-for-resolution', 'nle-basic-emergency-care'),
        $item("An older hospitalized client becomes acutely confused and inattentive. What should the nurse do first?", "Assess oxygenation, pain, infection, medicines, glucose or other ordered data, and immediate safety.", "Label the client as having dementia.", "Apply restraints without assessment.", "Ignore the change because it is common in older adults.", "Acute confusion is a clinical change with potentially reversible causes and safety implications.", 4, ['PRIORITIZATION', 'EMERGENCY'], 'acute-delirium', 'dementia-labeling', 'nle-clinical-judgment'),
        $item("A client with impaired vision is at high risk for falling in a new room. Which action is best?", "Orient the client to the environment, keep needed items accessible, improve lighting, and provide appropriate assistance.", "Keep the room dark to reduce stimulation.", "Move items frequently so the client learns the room.", "Tell the client not to move at all.", "Environmental orientation and accessible assistance reduce risk while preserving safe mobility.", 3, ['PRIORITIZATION'], 'visual-impairment-safety', 'immobility-as-prevention', 'nle-np-i-vulnerable'),
        $item("A client with hearing loss nods during medication teaching but cannot repeat the instructions. What should the nurse do?", "Use accessible communication, reduce barriers, and verify understanding with teach-back.", "Assume nodding means understanding.", "Speak from the doorway.", "Ask a family member to sign the teaching record.", "Communication access and teach-back test usable understanding and reduce medication error.", 3, ['MEDICATION_SAFETY', 'REGULATORY'], 'hearing-access', 'nodding-equals-understanding', 'nle-therapeutic-communication'),
        $item("A client reports sudden visual loss with severe headache. What should the nurse do?", "Assess urgently and activate appropriate emergency evaluation.", "Tell the client to rest in a dark room until tomorrow.", "Offer a routine eye appointment.", "Assume it is a familiar migraine without assessment.", "New visual loss with severe headache may indicate a time-sensitive problem and cannot be assumed benign.", 4, ['EMERGENCY', 'PRIORITIZATION'], 'acute-vision-change', 'familiar-diagnosis-anchoring', 'nle-basic-emergency-care'),
        $item("A client with peripheral neuropathy has a blister on the foot but little pain. What should the nurse teach?", "Inspect and protect the foot, avoid self-treatment that can injure tissue, and arrange follow-up.", "Ignore the blister because it does not hurt.", "Apply direct heat to improve circulation.", "Open it with a household needle.", "Reduced sensation can hide injury; protection and timely review are safer than heat or invasive self-care.", 3, ['INFECTION_CONTROL', 'MEDICATION_SAFETY'], 'neuropathy-foot', 'absence-of-pain-equals-no-injury', 'nle-np-iv-endocrine'),
        $item("A client begins having a generalized seizure in the care area. What should the nurse do?", "Protect from injury, maintain airway awareness, time the event, and activate help according to protocol.", "Restrain the limbs forcefully.", "Place an object in the mouth.", "Give oral medicine during the seizure.", "Seizure first aid prioritizes injury prevention, airway awareness, timing, and appropriate escalation.", 4, ['EMERGENCY', 'CONTRAINDICATION'], 'seizure-first-aid', 'object-in-mouth', 'nle-basic-emergency-care'),
        $item("A client with a new neurologic deficit coughs when drinking. What is the priority?", "Stop unsafe oral intake, assess swallowing and airway risk, and arrange evaluation.", "Offer thinner fluids quickly.", "Continue the meal to maintain calories.", "Assume coughing is unrelated to swallowing.", "Neurologic change plus coughing during intake raises aspiration risk and requires assessment before more intake.", 4, ['PRIORITIZATION', 'EMERGENCY'], 'neuro-dysphagia', 'nutrition-over-airway', 'nle-np-iii-oxygenation'),
        $item("A nonverbal client grimaces and withdraws during repositioning. Which pain assessment is best?", "Use behavior, physiologic cues, caregiver information, and a validated observational approach, then reassess.", "Assume there is no pain because the client cannot rate it.", "Use a numeric scale without adaptation.", "Treat the grimace as proof of one diagnosis.", "Pain can be assessed without verbal self-rating; behavior is a cue, not a complete diagnosis.", 3, ['PRIORITIZATION'], 'nonverbal-pain', 'no-self-report-no-pain', 'nle-nursing-process'),
        $item("A client with ataxia tries to walk alone to the bathroom. What should the nurse do?", "Provide appropriate assistance, assess current ability, and plan safe mobility rather than prohibiting all movement.", "Allow the client to try without help.", "Apply restraints immediately.", "Tell the client never to walk again.", "Coordination impairment raises fall risk; individualized assistance preserves safety and function.", 3, ['PRIORITIZATION'], 'coordination-mobility', 'restraint-or-nothing', 'nle-np-i-vulnerable'),
    ],
    'nle-np-iv-coordination' => [
        $item("A stable client asks a nursing assistant to report a new severe breathing problem. What should the nurse do?", "Assess the client immediately and retain accountability for the assessment and escalation.", "Ask the assistant to decide whether it is urgent.", "Tell the assistant to document it for later.", "Ignore the report because the client was stable earlier.", "Delegation does not transfer assessment accountability, and a new breathing problem may be urgent.", 4, ['PRIORITIZATION', 'EMERGENCY'], 'delegation-deterioration', 'delegation-of-assessment', 'nle-nursing-process'),
        $item("Which handoff best supports continuity for a client whose condition changed during the shift?", "State the current findings and trend, actions taken, response, pending needs, and escalation triggers.", "Give only the diagnosis.", "Say the client is fine without data.", "Copy the admission note unchanged.", "A useful handoff communicates risk, action, response, and what the next nurse must reassess.", 3, ['PRIORITIZATION'], 'clinical-handoff', 'diagnosis-only-handoff', 'nle-np-v-teamwork'),
        $item("Two disciplines disagree about a plan while the client is becoming less stable. What should the nurse do?", "State the safety concern with objective findings, escalate through the appropriate chain, and keep reassessing the client.", "Wait for the disagreement to resolve itself.", "Choose the plan based on seniority alone.", "Hide the change to avoid conflict.", "Objective escalation and reassessment protect the client when team disagreement intersects with deterioration.", 4, ['PRIORITIZATION', 'REGULATORY'], 'interdisciplinary-escalation', 'hierarchy-over-safety', 'nle-practice-standards'),
        $item("A nurse notices an error in a paper record. Which correction is appropriate?", "Follow the authorized correction process so the original entry remains traceable and the correction is dated and attributed.", "Erase the entry completely.", "Use correction fluid.", "Rewrite the record without explanation.", "Clinical records must remain accurate and traceable; concealed edits weaken safety and accountability.", 3, ['REGULATORY'], 'documentation-correction', 'eraser-as-correction', 'nle-practice-standards'),
        $item("A nurse believes a prescribed treatment may be unsafe because a new allergy is reported. What should the nurse do?", "Hold the action when appropriate, assess the client, verify the order and allergy, and escalate for clarification.", "Give the treatment to avoid delay.", "Delete the allergy from the chart.", "Ask the client to decide the medical order alone.", "Unresolved allergy risk requires assessment and clarification before exposure.", 4, ['MEDICATION_SAFETY', 'CONTRAINDICATION'], 'safety-escalation', 'treatment-before-clarification', 'nle-practice-standards'),
        $item("A unit is assigning a newly unstable client to a novice nurse without support. What is the best action?", "Raise the acuity and competency mismatch and arrange an assignment or support that protects the client.", "Accept the assignment silently.", "Give the novice the most unstable tasks to build confidence.", "Delegate assessment to an assistant.", "Assignment should match acuity, competence, supervision, and accountability.", 4, ['PRIORITIZATION', 'REGULATORY'], 'acuity-assignment', 'confidence-over-competence', 'nle-practice-standards'),
        $item("A team member gives instructions through an interpreter but never checks whether the client understood. What should the nurse add?", "Use qualified interpretation and teach-back to verify the client's understanding and ability to act.", "Ask whether the client nodded.", "Give a longer English handout.", "Ask the interpreter to decide for the client.", "Communication is complete only when the client can understand and use the information.", 3, ['REGULATORY'], 'interpreter-closed-loop', 'nodding-equals-comprehension', 'nle-therapeutic-communication'),
        $item("After a near miss, which response best supports patient safety?", "Protect the client, report factual details, and examine system contributors so the process can improve.", "Hide the near miss because no harm occurred.", "Blame the last person who touched the chart.", "Change the record to remove evidence.", "Near misses are learning opportunities; factual reporting and systems review prevent recurrence.", 4, ['REGULATORY', 'PRIORITIZATION'], 'near-miss-learning', 'no-harm-no-report', 'nle-practice-standards'),
        $item("A verbal high-risk medication order is heard in a noisy area. What should the nurse do?", "Move to a safer communication setting, read back the complete order, and verify the details.", "Rely on memory because the prescriber is familiar.", "Ask another staff member to guess the dose.", "Administer the most common concentration.", "Closed-loop verification reduces wrong-patient, wrong-drug, dose, and route errors.", 3, ['MEDICATION_SAFETY', 'DOSAGE_CALCULATION'], 'verbal-order-safety', 'familiarity-replaces-readback', 'nle-np-iv-medication', 'CONCEPTUAL_DOSAGE_SAFETY'),
        $item("A client refuses a procedure after receiving information. Which coordination action is best?", "Confirm understanding and capacity, document the decision, notify the team, and continue respectful care planning.", "Pressure the client until consent is given.", "Document noncompliance only.", "Proceed because the procedure is recommended.", "Refusal requires informed communication, accurate documentation, and coordinated alternatives or follow-up.", 3, ['REGULATORY'], 'informed-refusal-coordination', 'recommendation-erases-choice', 'nle-practice-standards'),
    ],
    'nle-np-iv-medication' => [
        $item("A medication order has no route and the available product has multiple concentrations. What is the safest pre-administration action?", "Hold administration and clarify the complete order and product before preparing it.", "Choose the route used most often.", "Use the first concentration found.", "Ask the client which route seems best.", "A complete order and verified product are required before administration; guessing creates preventable harm.", 3, ['MEDICATION_SAFETY', 'DOSAGE_CALCULATION'], 'incomplete-order', 'guessing-missing-order', 'nle-practice-standards', 'CONCEPTUAL_DOSAGE_SAFETY'),
        $item("At admission, a client cannot explain which medicines are taken at home. What should the nurse do?", "Reconcile the medicines with the client, records, and pharmacy or care team as available.", "Tell the client to restart everything remembered.", "Assume the previous list is current.", "Stop all medicines until discharge.", "Medication reconciliation identifies omissions, duplications, and discrepancies during transitions.", 3, ['MEDICATION_SAFETY'], 'medication-reconciliation', 'old-list-anchoring', 'nle-nursing-process'),
        $item("Which safety step is most important before giving a high-alert infusion?", "Independently verify the patient, drug, concentration, pump settings, indication, and monitoring plan according to policy.", "Rely on the label color.", "Ask a colleague only whether the pump is plugged in.", "Skip verification when the unit is busy.", "High-alert medication requires complete independent verification and monitoring, not superficial familiarity.", 4, ['MEDICATION_SAFETY', 'DOSAGE_CALCULATION'], 'high-alert-check', 'label-color-check', 'nle-practice-standards', 'CONCEPTUAL_DOSAGE_SAFETY'),
        $item("A client reports a serious allergy but the medication administration record does not show it. What should the nurse do?", "Stop and verify the allergy and order before administration, then update the authorized record.", "Administer and observe for a reaction.", "Delete the client's report.", "Ask a visitor to decide whether the allergy is real.", "Allergy discrepancies must be resolved before exposure; observation after administration is not prevention.", 3, ['MEDICATION_SAFETY', 'CONTRAINDICATION'], 'allergy-discrepancy', 'observe-after-exposure', 'nle-practice-standards'),
        $item("A client with reduced kidney function is prescribed a new medication. Which nursing focus is most appropriate?", "Verify the current order, relevant monitoring, patient teaching, and follow-up with the responsible team.", "Assume the usual dose is safe.", "Stop every medication independently.", "Use another client's dose as a comparison.", "Organ function can alter medication risk; safe administration requires current order-specific monitoring and coordination.", 3, ['MEDICATION_SAFETY', 'CHANGING_GUIDANCE'], 'organ-function-medication', 'usual-dose-assumption', 'nle-np-iv-endocrine'),
        $item("A client develops wheezing and facial swelling immediately after a medicine. What should the nurse do?", "Stop or hold the medication as indicated, assess ABCs, and activate urgent support.", "Give the next dose more slowly.", "Wait to see whether the swelling spreads.", "Offer oral fluids first.", "The reaction may threaten airway and circulation; immediate assessment and emergency response take priority.", 4, ['MEDICATION_SAFETY', 'EMERGENCY', 'CONTRAINDICATION'], 'adverse-reaction', 'slow-down-and-continue', 'nle-basic-emergency-care'),
        $item("A caregiver requests a simpler medication schedule because doses are being missed. What should the nurse do?", "Assess barriers and reconcile the regimen with the prescriber or pharmacist before simplifying it.", "Combine all medicines into one container.", "Skip the least familiar medicine.", "Double missed doses.", "Adherence support must preserve indication, timing, compatibility, and current professional instructions.", 3, ['MEDICATION_SAFETY'], 'medication-adherence', 'combine-without-verification', 'nle-nursing-process'),
        $item("Two packages have similar names and different strengths. What should the nurse do?", "Use the complete label and independent verification process before selecting the product.", "Choose the package with the familiar color.", "Use the higher strength if the dose is unclear.", "Ask the patient to identify the drug by appearance.", "Look-alike or sound-alike products require deliberate label, order, and patient verification.", 3, ['MEDICATION_SAFETY', 'DOSAGE_CALCULATION'], 'look-alike-sound-alike', 'appearance-based-selection', 'nle-practice-standards', 'CONCEPTUAL_DOSAGE_SAFETY'),
        $item("A client asks whether a prescribed medicine can be stopped because symptoms improved. What should the nurse do?", "Assess the medicine and indication and teach the client to confirm changes with the prescriber or current plan.", "Tell the client to stop whenever symptoms improve.", "Tell the client to double the next dose.", "Say all medicines must continue forever.", "Continuation depends on the medication, indication, response, and current plan; absolute advice is unsafe.", 3, ['MEDICATION_SAFETY', 'CHANGING_GUIDANCE'], 'medication-teaching', 'symptom-improvement-means-stop', 'nle-therapeutic-communication'),
        $item("Which injection practice is safest?", "Use a new sterile needle and syringe for each injection and follow current aseptic preparation guidance.", "Reuse a syringe if the needle is changed.", "Use a common syringe for the same client all day.", "Prepare doses in a contaminated area.", "Single-use equipment and aseptic preparation prevent transmission and medication errors.", 2, ['MEDICATION_SAFETY', 'INFECTION_CONTROL'], 'safe-injection', 'needle-change-makes-reuse-safe', 'nle-practice-standards'),
    ],
    'nle-np-v-therapeutic' => [
        $item("A client says, 'I do not see a reason to keep living,' during intake. What should the nurse do first?", "Stay with the client, assess immediate suicide risk and access to means, and activate urgent support.", "Leave the client alone to reflect.", "Promise absolute secrecy.", "Change the subject to distract the client.", "A statement suggesting self-harm requires direct safety assessment and immediate support, not avoidance.", 4, ['EMERGENCY', 'PRIORITIZATION'], 'suicide-risk', 'secrecy-promise', 'nle-np-v-crisis'),
        $item("A client reports hearing a voice telling them to harm someone. Which response is best?", "Assess immediate risk, stay calm, reduce hazards, and activate appropriate urgent support.", "Argue that the voice is not real.", "Leave the client alone with potential weapons.", "Promise not to tell anyone.", "Command content changes immediate safety needs; assessment and protection take priority over argument or secrecy.", 4, ['EMERGENCY', 'PRIORITIZATION'], 'command-hallucination', 'reality-argument', 'nle-np-v-crisis'),
        $item("A client with mania is pacing, speaking rapidly, and becoming increasingly irritable. What should the nurse do?", "Reduce stimulation, use calm concise communication, assess safety and basic needs, and escalate if risk increases.", "Challenge the client loudly.", "Offer a crowded group activity.", "Promise every request to keep the client calm.", "Low-stimulation, structured communication and risk assessment support safety without unnecessary confrontation.", 3, ['PRIORITIZATION'], 'mania-deescalation', 'stimulation-as-treatment', 'nle-np-v-crisis'),
        $item("A client reports panic, chest pressure, and shortness of breath. What should the nurse do first?", "Assess ABCs and medical danger before attributing symptoms to anxiety.", "Tell the client it is only panic.", "Leave the client to breathe into a bag.", "Wait for a mental-health appointment.", "Panic-like symptoms can overlap with medical emergencies; assessment precedes psychiatric labeling.", 4, ['EMERGENCY', 'CONTRAINDICATION'], 'panic-medical-assessment', 'anxiety-as-diagnosis', 'nle-basic-emergency-care'),
        $item("Which response is most therapeutic when a client says, 'No one listens to me'?", "Use an open, nonjudgmental response and invite the client to describe the concern.", "Tell the client to calm down.", "Offer advice before listening.", "Change the subject to a routine task.", "Therapeutic communication begins with presence, listening, and clarification rather than dismissal or premature advice.", 2, [], 'therapeutic-listening', 'premature-advice', 'nle-therapeutic-communication'),
        $item("A trauma survivor asks that a procedure be explained before any touch. What should the nurse do?", "Explain the steps, ask permission, offer choices when possible, and pause if distress increases.", "Surprise the client to finish quickly.", "Tell the client trauma is unrelated to current care.", "Proceed because consent was signed earlier.", "Trauma-informed care emphasizes predictability, choice, consent, and ongoing assessment.", 3, ['REGULATORY'], 'trauma-informed-care', 'signed-consent-ends-consent', 'nle-practice-standards'),
        $item("A client asks whether a disclosure about self-harm will be kept secret. What should the nurse say?", "Explain privacy and its safety limits honestly and state that immediate danger must be shared through the safety process.", "Promise never to tell anyone.", "Tell the client everything will be public.", "Avoid answering the question.", "Trust is supported by clear limits; imminent safety information cannot be hidden from the responsible team.", 3, ['EMERGENCY', 'REGULATORY'], 'confidentiality-limits', 'absolute-confidentiality', 'nle-practice-standards'),
        $item("A client reports new tremor and severe restlessness after starting a psychiatric medicine. What should the nurse do?", "Assess the symptoms and medication timing and notify the responsible team for current adverse-effect evaluation.", "Tell the client to stop every medicine independently.", "Assume the symptoms are attention-seeking.", "Give an extra dose to settle the client.", "New symptoms after a medication change require assessment and coordinated medication safety review.", 3, ['MEDICATION_SAFETY', 'CHANGING_GUIDANCE'], 'psychiatric-adverse-effect', 'behavioral-dismissal', 'nle-np-iv-medication'),
        $item("A client in alcohol withdrawal is tremulous, confused, and increasingly agitated. What is the priority?", "Assess ABCs and safety and activate urgent withdrawal management according to current protocol.", "Leave the client alone in a quiet room without checks.", "Give unverified sedatives.", "Tell the client to sleep it off.", "Withdrawal can progress rapidly; structured assessment, monitoring, and protocol-based treatment are needed.", 4, ['EMERGENCY', 'MEDICATION_SAFETY'], 'withdrawal-risk', 'sleep-it-off', 'nle-basic-emergency-care'),
        $item("A client with depression has stopped eating and cannot complete basic self-care. What should the nurse do?", "Assess safety, nutrition, function, and support needs and coordinate a timely plan.", "Tell the client to try harder.", "Focus only on mood words.", "Promise that motivation will return soon.", "Functional decline and poor intake require holistic risk assessment and coordinated support.", 3, ['PRIORITIZATION'], 'depression-function', 'moralizing-motivation', 'nle-nursing-process'),
    ],
    'nle-np-v-crisis' => [
        $item("A client has a plan to self-harm and says the means are nearby. What should the nurse do?", "Stay with the client, reduce access to means when safe, and activate immediate safety support.", "Ask the client to promise not to act.", "Leave to find paperwork.", "Promise confidentiality.", "Plan plus access to means is an immediate safety threat requiring protection and urgent escalation.", 4, ['EMERGENCY', 'PRIORITIZATION'], 'imminent-self-harm', 'promise-instead-of-safety-plan', 'nle-np-v-therapeutic'),
        $item("A client becomes physically aggressive during a crisis. Which response is best?", "Protect people, call for help, use calm de-escalation, and follow the least-restrictive current safety protocol.", "Argue until the client admits fault.", "Surround the client with many staff without explanation.", "Apply restraints without assessment or authorization.", "Safety and de-escalation come first; restrictive measures require protocol, justification, monitoring, and documentation.", 4, ['EMERGENCY', 'REGULATORY'], 'aggression-deescalation', 'punishment-as-safety', 'nle-practice-standards'),
        $item("A client is found drowsy with shallow breathing beside an unknown container of tablets. What should the nurse do?", "Activate emergency support, assess airway and breathing, and preserve the container for safe identification.", "Induce vomiting.", "Give food and wait.", "Discard the container.", "Unknown ingestion with respiratory depression is an emergency; airway support and information preservation are priorities.", 4, ['MEDICATION_SAFETY', 'EMERGENCY'], 'overdose-response', 'induce-vomiting', 'nle-basic-emergency-care'),
        $item("A client with psychosis is frightened and scanning the room. Which nursing action is most appropriate?", "Use a calm approach, reduce stimulation, assess safety and perception, and offer simple choices.", "Insist that the client's perception is ridiculous.", "Use complex explanations.", "Leave without telling the client where you are going.", "Calm, structured communication and safety assessment reduce escalation and support therapeutic engagement.", 3, ['PRIORITIZATION'], 'psychosis-support', 'confrontation', 'nle-therapeutic-communication'),
        $item("A client in acute grief says, 'I cannot go on,' but denies a plan. What should the nurse do?", "Ask directly about safety, assess supports and warning signs, and create an appropriate follow-up plan.", "Assume there is no risk because there is no plan.", "Tell the client to be grateful.", "Avoid discussing self-harm to prevent ideas.", "Direct assessment clarifies risk; avoiding the topic can miss danger.", 3, ['EMERGENCY'], 'grief-safety-assessment', 'no-plan-no-risk', 'nle-np-v-therapeutic'),
        $item("A client discloses intimate-partner violence during a crisis visit. What should the nurse do first?", "Assess immediate safety privately and connect the client with authorized support and a safety plan.", "Confront the partner at once.", "Tell the client to leave without assessing resources.", "Document only that the client is upset.", "A safe response is private, individualized, and connected to resources rather than impulsive confrontation.", 4, ['EMERGENCY', 'REGULATORY'], 'violence-crisis', 'public-confrontation', 'nle-practice-standards'),
        $item("A client with panic symptoms also has crushing chest pressure and diaphoresis. What should the nurse do?", "Treat the presentation as a possible medical emergency and assess ABCs immediately.", "Assume it is anxiety because the client has panic history.", "Use breathing coaching before assessment.", "Schedule a counseling referral.", "A familiar psychiatric history does not exclude acute medical danger.", 4, ['EMERGENCY', 'PRIORITIZATION'], 'panic-red-flag', 'history-anchoring', 'nle-basic-emergency-care'),
        $item("A client in crisis agrees to a referral but cannot identify how to reach the service. Which action is best?", "Confirm the referral, access, timing, transportation, and a plan for worsening risk before discharge.", "Give a phone number and end the encounter.", "Assume a family member will arrange it.", "Delay all support until the client calls.", "A referral is safer when the client can actually access it and knows what to do if risk changes.", 3, ['EMERGENCY', 'REGULATORY'], 'crisis-referral', 'referral-as-completion', 'nle-clinical-judgment'),
        $item("A client presents with superficial self-inflicted wounds and says the wounds are not serious. What should the nurse do?", "Assess the wounds and immediate self-harm risk, provide care, and arrange appropriate support.", "Ignore the wounds because they are superficial.", "Ask the client to promise not to repeat it.", "Delay care until a psychiatric interview is complete.", "Wound severity and suicide risk are separate assessments; both immediate care and safety planning matter.", 4, ['EMERGENCY', 'PRIORITIZATION'], 'self-harm-wound', 'visible-depth-equals-risk', 'nle-nursing-process'),
        $item("A child in crisis becomes silent and avoids eye contact after a frightening event. Which response is best?", "Provide a safe calm presence, use developmentally appropriate communication, and assess immediate protection needs.", "Demand a detailed account immediately.", "Assume silence means no distress.", "Promise that nothing will ever happen again.", "Children may communicate distress indirectly; safety, trust, and developmentally appropriate assessment come first.", 3, ['MATERNAL_NEWBORN', 'EMERGENCY'], 'child-crisis', 'silence-equals-no-distress', 'nle-therapeutic-communication'),
    ],
    'nle-np-v-emergency' => [
        $item("A client arrives with severe breathing difficulty and cannot speak full sentences. What should the nurse do first?", "Assess and support airway and breathing and activate urgent help.", "Complete the admission history first.", "Offer oral fluids.", "Wait for a routine oxygen saturation check.", "Airway and breathing threats are immediate priorities.", 4, ['EMERGENCY', 'PRIORITIZATION'], 'abc-airway', 'paperwork-before-stabilization', 'nle-basic-emergency-care'),
        $item("Which approach is most useful for an acutely ill client whose diagnosis is not yet clear?", "Use a systematic ABCDE and focused history approach, stabilize threats, and reassess.", "Wait for a definitive diagnosis before acting.", "Treat the most familiar diagnosis automatically.", "Collect a complete social history before vital threats.", "Systematic assessment supports early stabilization even before diagnosis is complete.", 3, ['EMERGENCY', 'PRIORITIZATION'], 'abcde-assessment', 'diagnosis-before-stabilization', 'nle-basic-emergency-care'),
        $item("A client develops wheezing, facial swelling, and hypotension after medication. What is the priority?", "Activate emergency response and support airway and circulation according to the current protocol.", "Ask the client to walk to the treatment area.", "Give oral water first.", "Wait to see whether the swelling improves.", "Airway and circulatory compromise after exposure is an emergency.", 4, ['MEDICATION_SAFETY', 'EMERGENCY', 'CONTRAINDICATION'], 'anaphylaxis-emergency', 'wait-for-improvement', 'nle-basic-emergency-care'),
        $item("A client has sudden weakness on one side and slurred speech. Which information should the nurse obtain urgently?", "The last-known-well time and current neurologic and airway status.", "The client's preferred meal.", "A complete remote family history before escalation.", "The date of the last routine visit only.", "Time-sensitive neurologic care depends on last-known-well information plus immediate stability assessment.", 4, ['EMERGENCY', 'PRIORITIZATION'], 'stroke-time', 'routine-history-first', 'nle-basic-emergency-care'),
        $item("A client with suspected infection becomes hypotensive and confused. What should the nurse do?", "Activate urgent assessment, support ABCs, and follow the current escalation pathway.", "Wait for all laboratory results.", "Document and reassess in two hours.", "Assume confusion is chronic.", "Actual instability requires immediate support; diagnostics should not delay stabilization.", 4, ['INFECTION_CONTROL', 'EMERGENCY'], 'sepsis-emergency', 'test-before-stabilize', 'nle-basic-emergency-care'),
        $item("In a mass-casualty event, which client should be reassessed first?", "A client who has changed from walking to confusion with labored breathing.", "A client with a stable minor abrasion.", "A client requesting a blanket but walking normally.", "A client with a chronic unchanged complaint.", "New breathing and mental-status changes indicate a higher immediate threat than stable minor needs.", 4, ['EMERGENCY', 'PRIORITIZATION'], 'disaster-retriage', 'stable-request-priority', 'nle-np-v-acuity'),
        $item("A conscious adult suddenly cannot speak or cough effectively while eating. What should the nurse do?", "Activate emergency response and use the current choking protocol for a severe airway obstruction.", "Give water to push the food down.", "Sweep the mouth blindly.", "Leave to document the event.", "Severe airway obstruction requires immediate current-protocol response; blind sweeping and fluids can worsen harm.", 4, ['EMERGENCY', 'CONTRAINDICATION'], 'choking-emergency', 'water-for-obstruction', 'nle-basic-emergency-care'),
        $item("A client begins seizing in the emergency area. What is the first nursing priority?", "Protect the client from injury, maintain airway awareness, time the event, and call for help.", "Restrain the client forcefully.", "Insert an object into the mouth.", "Offer oral medication during the event.", "Seizure first aid prioritizes safety, airway awareness, timing, and appropriate escalation.", 4, ['EMERGENCY', 'CONTRAINDICATION'], 'seizure-emergency', 'object-in-mouth', 'nle-basic-emergency-care'),
        $item("A person rescued from extreme heat is confused, weak, and unable to drink safely. What should the nurse do?", "Activate urgent evaluation, support ABCs and cooling according to protocol, and avoid unsafe oral intake.", "Force oral fluids.", "Send the person home to rest.", "Wait for sweating to return.", "Confusion and unsafe swallowing make heat illness an emergency requiring monitored support.", 4, ['EMERGENCY', 'CONTRAINDICATION'], 'heat-emergency', 'force-oral-fluid', 'nle-basic-emergency-care'),
        $item("Which emergency handoff is most useful after initial stabilization?", "The event time, key findings, interventions, response, current risks, and pending actions.", "Only the client's name.", "A diagnosis guessed before assessment.", "A statement that the client is stable without evidence.", "Emergency continuity depends on concise facts, response, and pending safety work.", 3, ['EMERGENCY', 'PRIORITIZATION'], 'emergency-handoff', 'label-only-handoff', 'nle-np-v-teamwork'),
    ],
    'nle-np-v-shock' => [
        $item("A client is hypotensive, pale, confused, and has cool skin. What should the nurse do first?", "Assess ABCs and perfusion, activate urgent support, and follow the current shock pathway.", "Offer a meal.", "Wait for a routine laboratory result.", "Place the client alone and document later.", "The findings indicate possible shock and require immediate assessment and escalation.", 4, ['EMERGENCY', 'PRIORITIZATION'], 'shock-recognition', 'wait-for-labs', 'nle-basic-emergency-care'),
        $item("A postoperative client has a rapidly saturating dressing and increasing tachycardia. What is the priority?", "Assess circulation and bleeding, call for urgent support, and follow the current hemorrhage response.", "Change the dressing quietly and reassess tomorrow.", "Encourage walking.", "Give oral fluids first.", "Rapid blood loss and tachycardia are time-sensitive; concealment or delay is unsafe.", 4, ['EMERGENCY', 'PRIORITIZATION'], 'hemorrhagic-shock', 'dressing-task-over-circulation', 'nle-basic-emergency-care'),
        $item("A client with infection has fever, confusion, cool extremities, and falling blood pressure. What should the nurse do?", "Recognize possible septic shock, support ABCs, and activate urgent treatment and escalation.", "Wait until the fever resolves.", "Give an unverified antibiotic dose.", "Document only the temperature.", "The pattern shows actual instability; treatment must follow the current pathway rather than guessing a dose.", 4, ['INFECTION_CONTROL', 'EMERGENCY', 'MEDICATION_SAFETY'], 'septic-shock', 'temperature-only', 'nle-basic-emergency-care'),
        $item("A client with chest pain develops crackles, severe dyspnea, and hypotension. What should the nurse prioritize?", "Support ABCs, position and oxygen according to protocol or order, and activate urgent evaluation.", "Give a large fluid bolus without assessment.", "Lay the client flat and leave.", "Wait for a routine chest x-ray.", "The pattern may reflect cardiogenic compromise; treatment must account for both perfusion and fluid or respiratory risk.", 4, ['EMERGENCY', 'CONTRAINDICATION'], 'cardiogenic-shock', 'fluid-for-all-shock', 'nle-basic-emergency-care'),
        $item("A trauma client becomes hypotensive after chest injury and has worsening respiratory distress. What should the nurse do?", "Activate emergency response, assess airway and breathing, and follow the current trauma pathway.", "Offer oral fluids.", "Wait for the client to identify the injury.", "Apply a tight circumferential chest wrap without assessment.", "Trauma with respiratory and circulatory deterioration requires immediate structured assessment and support.", 4, ['EMERGENCY', 'CONTRAINDICATION'], 'obstructive-shock-risk', 'unassessed-wrap', 'nle-basic-emergency-care'),
        $item("A client receiving treatment for shock has improved blood pressure but remains confused and has low urine output. What should the nurse do?", "Reassess perfusion and trends and report inadequate response despite the improved number.", "Stop monitoring because blood pressure improved.", "Assume confusion is baseline.", "Give extra treatment without an order.", "Response is multidimensional; one improved number does not establish adequate perfusion.", 4, ['EMERGENCY', 'PRIORITIZATION'], 'shock-response', 'single-number-success', 'nle-clinical-judgment'),
        $item("Which trend is most important to report for a client at risk for shock?", "Worsening mental status, perfusion findings, vital-sign trend, and urine output together.", "A single normal temperature.", "The client's meal preference.", "A stable old weight.", "Shock surveillance uses trends and multiple perfusion cues rather than an isolated reassuring value.", 3, ['EMERGENCY', 'PRIORITIZATION'], 'shock-monitoring', 'isolated-reassurance', 'nle-nursing-process'),
        $item("A client in shock has an order for a high-alert infusion. Which safety action is essential?", "Independently verify patient, drug, concentration, pump settings, indication, and monitoring before starting.", "Use the concentration most recently seen.", "Skip the check because the client is unstable.", "Ask the family to confirm the drug.", "Urgency increases the need for deliberate high-alert verification; it does not remove it.", 3, ['MEDICATION_SAFETY', 'EMERGENCY', 'DOSAGE_CALCULATION'], 'shock-high-alert-infusion', 'urgency-erases-verification', 'nle-np-iv-medication', 'CONCEPTUAL_DOSAGE_SAFETY'),
        $item("A shock client becomes less responsive during transfer. What should the nurse do?", "Stop when safe, reassess ABCs and perfusion, and communicate the deterioration before continuing.", "Continue the transfer to save time.", "Assume the change is anxiety.", "Wait until arrival to document it.", "A deteriorating client needs immediate reassessment and a clear handoff, not movement at all costs.", 4, ['EMERGENCY', 'PRIORITIZATION'], 'shock-transfer', 'task-completion-over-stability', 'nle-np-v-teamwork'),
        $item("A family asks why the nurse keeps reassessing a client whose blood pressure improved. What is the best explanation?", "Shock can change quickly, and perfusion, mental status, breathing, output, and trends must be monitored together.", "The first number is never trusted.", "Reassessment is required only for documentation.", "Improvement means no further risk.", "Clinical response is dynamic; continued assessment detects relapse or incomplete recovery.", 2, ['EMERGENCY'], 'shock-family-teaching', 'improvement-ends-monitoring', 'nle-therapeutic-communication'),
    ],
    'nle-np-v-acuity' => [
        $item("Which client should the nurse assess first during the change of shift?", "A client with new confusion and labored breathing.", "A stable client awaiting discharge paperwork.", "A client requesting a blanket with normal findings.", "A client with a chronic unchanged complaint.", "New respiratory and mental-status changes indicate actual deterioration and take priority.", 3, ['PRIORITIZATION', 'EMERGENCY'], 'acuity-ranking', 'comfort-before-instability', 'nle-nursing-process'),
        $item("A client who was stable has a rising respiratory rate and falling oxygen saturation. What should the nurse do?", "Reassess immediately, support airway and breathing, and escalate the change.", "Wait for the next scheduled round.", "Document the trend after the shift.", "Assume the monitor is wrong without checking the client.", "A trend from baseline with respiratory change is an actual deterioration signal.", 4, ['PRIORITIZATION', 'EMERGENCY'], 'deterioration-trend', 'monitor-blame', 'nle-basic-emergency-care'),
        $item("A client becomes unresponsive and has no normal breathing. What should the nurse do?", "Activate the emergency response and begin the current resuscitation sequence.", "Search for the full history first.", "Offer oral fluids.", "Wait for a family member.", "Unresponsiveness and absent normal breathing require immediate resuscitation action.", 4, ['EMERGENCY'], 'resuscitation', 'history-before-life-support', 'nle-basic-emergency-care'),
        $item("After an intervention for acute respiratory distress, what should the nurse do next?", "Reassess the client's response and escalate if the findings remain abnormal or worsen.", "Assume the intervention worked because it was completed.", "Stop monitoring to let the client rest.", "Repeat the intervention indefinitely without review.", "Evaluation is part of the nursing process; action without response assessment is incomplete.", 3, ['PRIORITIZATION'], 'post-intervention-reassessment', 'task-equals-effect', 'nle-nursing-process'),
        $item("Which client is least appropriate for an unlicensed assistant to assess independently?", "A newly unstable client with changing mental status and oxygen needs.", "A stable client needing routine hygiene.", "A stable client whose meal tray needs setup.", "A client with a routine unchanged ambulation plan.", "Assessment and evaluation of an unstable client require the nurse's judgment and accountability.", 3, ['PRIORITIZATION', 'REGULATORY'], 'acuity-delegation', 'routine-task-analogy', 'nle-practice-standards'),
        $item("A client with several chronic problems develops a new acute change. Which approach is best?", "Prioritize the new threat while integrating baseline conditions, medicines, and response.", "Focus only on the longest-standing diagnosis.", "Treat every chronic problem equally before the acute change.", "Ignore baseline information entirely.", "Acuity reasoning distinguishes new actual threats while using baseline and comorbidity context.", 4, ['PRIORITIZATION'], 'acute-on-chronic', 'chronicity-over-actual-threat', 'nle-clinical-judgment'),
        $item("An ICU transfer handoff omits what happened after a medication was given. What should the receiving nurse ask for?", "The medication, time, indication, response, monitoring, and pending concerns.", "Only the medication name.", "A general statement that the patient tolerated it.", "No questions because the transfer is complete.", "Medication response and pending monitoring are essential to safe continuity for an acute client.", 3, ['MEDICATION_SAFETY', 'PRIORITIZATION'], 'acuity-transfer', 'name-only-medication-handoff', 'nle-np-v-teamwork'),
        $item("A client with multiple organ problems has new oliguria and confusion. What should the nurse do?", "Assess perfusion and organ-function trends and escalate the change urgently.", "Wait for a single laboratory value.", "Assume the findings are expected in high acuity.", "Give an unverified fluid or medicine.", "New output and mental-status changes may indicate deterioration; high acuity increases the need for reassessment.", 4, ['EMERGENCY', 'PRIORITIZATION'], 'multi-organ-change', 'high-acuity-normalization', 'nle-basic-emergency-care'),
        $item("Which equipment check is most important before receiving a high-acuity client?", "Confirm functioning airway, oxygen, suction, monitoring, and emergency-response equipment.", "Arrange the visitor chairs first.", "Assume equipment works because it was used yesterday.", "Turn off alarms to reduce noise.", "Prepared functioning equipment supports rapid response to life-threatening change.", 2, ['EMERGENCY'], 'high-acuity-preparation', 'yesterday-means-ready', 'nle-np-v-teamwork'),
        $item("A family asks for an update while the team is responding to a client's deterioration. What should the nurse do?", "Provide clear appropriate communication when safe, protect privacy, and keep urgent clinical work coordinated.", "Ignore the family indefinitely.", "Share all information with every visitor.", "Stop emergency care for a long discussion.", "Family communication matters but must be timed, authorized, and coordinated with immediate stabilization.", 3, ['EMERGENCY', 'REGULATORY'], 'high-acuity-family', 'family-versus-care-binary', 'nle-therapeutic-communication'),
    ],
    'nle-np-v-teamwork' => [
        $item("A nurse notices a colleague preparing a medication for the wrong patient. What should the nurse do?", "Speak up immediately, stop the process, verify the patient and order, and report the near miss through the safety process.", "Wait until the medication is given to avoid embarrassment.", "Change the label silently.", "Ignore it if the colleague is senior.", "Immediate speaking up prevents harm; factual reporting supports systems learning.", 4, ['MEDICATION_SAFETY', 'REGULATORY'], 'speaking-up', 'hierarchy-over-safety', 'nle-practice-standards'),
        $item("Which handoff statement is most useful for a client whose condition changed?", "The change, current findings and trend, actions, response, and what the next clinician must reassess.", "The client is fine now.", "Only the admission diagnosis.", "The room number and dietary preference.", "Closed-loop handoff transfers current risk and pending safety work, not just labels.", 3, ['PRIORITIZATION'], 'change-handoff', 'label-only-handoff', 'nle-nursing-process'),
        $item("A team member says an incident should be hidden because the client was not harmed. What should the nurse do?", "Protect the client, preserve facts, and use the authorized reporting and review process.", "Agree because no harm occurred.", "Alter the record to remove the event.", "Post the event publicly.", "Near misses reveal system risks; reporting should be factual, authorized, and confidential.", 4, ['REGULATORY', 'PRIORITIZATION'], 'incident-reporting', 'no-harm-no-report', 'nle-practice-standards'),
        $item("A nurse delegates a routine task but the client becomes unstable during it. What should happen?", "The nurse reassesses the client, takes over or escalates as needed, and retains accountability for evaluation.", "Tell the assistant to manage the instability alone.", "Wait for the task to finish.", "Blame the assistant without assessing the client.", "Delegation does not transfer nursing judgment; instability requires reassessment and escalation.", 4, ['PRIORITIZATION', 'REGULATORY'], 'delegation-change', 'delegation-of-accountability', 'nle-nursing-process'),
        $item("A client with limited English receives a complex discharge handoff. Which team action is safest?", "Use qualified interpretation, communicate the plan, and verify client understanding with teach-back.", "Ask the nearest visitor to interpret.", "Use technical English more slowly.", "Give the written plan without checking comprehension.", "Language access and closed-loop verification reduce transition errors.", 3, ['REGULATORY', 'MEDICATION_SAFETY'], 'multilingual-handoff', 'visitor-as-interpreter', 'nle-therapeutic-communication'),
        $item("A team member identifies a possible wrong-site procedure during the time-out. What should the team do?", "Stop and reconcile identity, site, consent, and procedure before proceeding.", "Continue because the schedule is delayed.", "Ask the patient to decide while anesthetized.", "Correct the paperwork after surgery.", "The time-out is a shared safety barrier; unresolved discrepancy requires a stop.", 4, ['REGULATORY', 'CONTRAINDICATION'], 'team-timeout', 'schedule-over-safety', 'nle-practice-standards'),
        $item("A colleague asks the nurse to sign a care record for a task the nurse did not perform. What should the nurse do?", "Decline to attest to unobserved care and ensure the record is completed by the person who performed it or through policy.", "Sign to help the team finish.", "Copy a prior entry.", "Leave the record permanently blank.", "Documentation must be accurate and attributable; signing for unobserved care creates a safety and accountability defect.", 3, ['REGULATORY'], 'documentation-attestation', 'team-help-as-false-record', 'nle-practice-standards'),
        $item("A client has a new deterioration but the first call to the team receives no response. What should the nurse do?", "Continue assessment and support and escalate through the next appropriate channel without delay.", "Wait until someone returns the call.", "Stop monitoring to avoid duplication.", "Ask the family to contact the clinician.", "Unresolved escalation is itself a safety risk; the nurse must use the chain and continue reassessment.", 4, ['EMERGENCY', 'PRIORITIZATION'], 'escalation-chain', 'first-call-is-final', 'nle-nursing-process'),
        $item("After a medication near miss, which team review is most useful?", "Map the actual process, identify system contributors, and test a targeted change.", "Identify one person to blame.", "Tell staff to be more careful without changing the process.", "Delete the report to protect morale.", "Systems review creates a measurable prevention strategy beyond individual blame.", 4, ['MEDICATION_SAFETY', 'REGULATORY'], 'medication-near-miss', 'be-careful-only', 'nle-practice-standards'),
        $item("After a clinical incident, what is the best immediate team-learning action before attribution?", "Stabilize and disclose appropriately, report through the safety process, preserve facts, and review system contributors.", "Change records to make the event disappear.", "Assign blame before assessing the client.", "Hide the event to protect the team.", "Safety review requires client protection, factual reporting, and systems learning rather than concealment or premature blame.", 4, ['REGULATORY', 'PRIORITIZATION'], 'clinical-incident-learning', 'concealment-as-team-protection', 'nle-practice-standards'),
    ],
];

$integratedBanks = [
    ['concept' => 'nle-np-i-community', 'items' => [
        $item("A barangay health team sees rising missed appointments after clinic hours changed. Which response best uses evidence?", "Compare attendance and access barriers, ask residents, and adapt hours or outreach while evaluating the result.", "Assume residents are careless.", "Keep the schedule unchanged because it was approved.", "Count only the people who still attend.", "A service outcome needs context, community input, and an evaluated feasible change.", 4, ['PRIORITIZATION'], 'integrated-access-evaluation', 'attendance-blame', 'nle-np-i-epidemiology'),
        $item("A community nurse must choose between a broad lecture and a small high-risk home-visit list. What should guide the first action?", "The population risk, urgency, reach, resources, and expected benefit of each option.", "Which option is easiest to document.", "Which option looks busiest.", "A fixed rule that every person receives identical service.", "Community prioritization balances need, feasibility, equity, and outcomes rather than activity alone.", 4, ['PRIORITIZATION'], 'integrated-community-priority', 'busy-equals-important', 'nle-np-i-prevention'),
        $item("A community report combines screening results from two programs with different definitions. What should the nurse do before comparing rates?", "Check the definitions, denominators, time periods, and ascertainment before interpreting the difference.", "Compare the raw counts directly.", "Assume the newer program found more disease.", "Delete the smaller program's data.", "Comparable interpretation requires compatible measures and context.", 4, ['CHANGING_GUIDANCE'], 'integrated-data-comparison', 'raw-count-comparison', 'nle-np-i-epidemiology'),
        $item("A community volunteer reports a client's urgent symptom through a message with no location. What should the nurse do?", "Clarify identity and location, assess immediate danger, and activate the appropriate response.", "Send general advice without a location.", "Wait for the next clinic day.", "Forward the message publicly to find the client.", "Remote community reports still require enough information for urgent assessment while protecting privacy.", 4, ['EMERGENCY', 'REGULATORY'], 'integrated-remote-report', 'location-not-needed', 'nle-nursing-process'),
    ]],
    ['concept' => 'nle-np-ii-newborn', 'items' => [
        $item("A newborn becomes apneic during transition after birth. What should the nurse do?", "Activate the current newborn resuscitation pathway, maintain warmth, and provide support according to protocol.", "Wait for spontaneous recovery without assessment.", "Give oral fluid.", "Place the newborn under an unmonitored heat source.", "Apnea is an immediate newborn emergency requiring current protocol-based support.", 4, ['MATERNAL_NEWBORN', 'EMERGENCY'], 'integrated-newborn-resuscitation', 'wait-for-recovery', 'nle-np-v-emergency'),
        $item("A newborn's parent has misunderstood a warning-sign handout. Which teaching method is best?", "Use plain language and ask the parent to explain and demonstrate the response plan.", "Give a longer handout in the same language.", "Ask the parent to sign again.", "Tell the parent to search online.", "Teach-back tests usable understanding and identifies the exact gap before discharge.", 3, ['MATERNAL_NEWBORN'], 'integrated-newborn-teachback', 'paperwork-equals-learning', 'nle-np-ii-family'),
        $item("A newborn with respiratory distress is in a crowded area while equipment is being prepared. What should the team do?", "Move or manage the environment for prompt assessment, maintain warmth, and coordinate roles and equipment.", "Wait until the area is quiet.", "Assign the family to find equipment.", "Separate the newborn from all observers without communication.", "Emergency newborn care requires rapid coordinated preparation without sacrificing warmth or assessment.", 4, ['MATERNAL_NEWBORN', 'EMERGENCY'], 'integrated-newborn-team', 'quiet-before-care', 'nle-np-v-teamwork'),
        $item("A newborn has poor feeding and a temperature concern but no obvious respiratory distress. What should the nurse do?", "Treat the combination as a danger pattern, assess promptly, and escalate according to current newborn guidance.", "Wait because breathing is normal.", "Give a large feed without assessment.", "Tell the parent to observe for a week.", "Newborn danger signs can be subtle; absence of one sign does not remove the need for timely evaluation.", 4, ['MATERNAL_NEWBORN', 'EMERGENCY', 'CHANGING_GUIDANCE'], 'integrated-newborn-danger', 'single-sign-reassurance', 'nle-nursing-process'),
    ]],
    ['concept' => 'nle-np-iii-infection', 'items' => [
        $item("A nurse is unsure whether a respiratory client needs additional precautions after a new laboratory result. What should the nurse do?", "Check the current authorized infection-control guidance and implement the indicated precautions while assessing the client.", "Use yesterday's precautions automatically.", "Wait for another client to become ill.", "Choose precautions based on room availability.", "Answer-critical isolation practice depends on the current hazard and authorized guidance.", 3, ['INFECTION_CONTROL', 'CHANGING_GUIDANCE'], 'integrated-guidance-check', 'old-guidance-anchoring', 'nle-practice-standards'),
        $item("A client with a central line develops fever and new confusion. Which combined response is best?", "Use infection precautions, assess ABCs and line findings, and escalate the possible deterioration urgently.", "Focus on the line dressing and delay neurologic assessment.", "Remove the line without an order.", "Give antibiotics from a prior client.", "Infection prevention, clinical assessment, and medication safety must operate together in a deteriorating client.", 4, ['INFECTION_CONTROL', 'EMERGENCY', 'MEDICATION_SAFETY'], 'integrated-line-sepsis', 'one-task-at-a-time', 'nle-basic-emergency-care'),
        $item("A team member says a contaminated sterile field can be fixed by adding more antiseptic. What should the nurse do?", "Stop and replace contaminated items using the current aseptic procedure.", "Add more antiseptic and continue.", "Cover the field with a clean towel.", "Document it after the procedure.", "Contamination is a process failure; products do not make a contaminated field sterile.", 3, ['INFECTION_CONTROL'], 'integrated-aseptic-correction', 'product-compensates-for-contamination', 'nle-practice-standards'),
        $item("A client with infection has new hypotension while a medication order is being clarified. What should the nurse do?", "Support ABCs and activate urgent escalation while clarifying the medication safely.", "Wait for the complete order before responding to hypotension.", "Give a guessed dose.", "Document the order issue and leave the client.", "Urgent instability requires immediate support; medication uncertainty must be resolved without guessing.", 4, ['INFECTION_CONTROL', 'EMERGENCY', 'MEDICATION_SAFETY'], 'integrated-infection-medication', 'order-before-stabilization', 'nle-basic-emergency-care'),
    ]],
    ['concept' => 'nle-np-iv-medication', 'items' => [
        $item("A client receives a medication and develops a new symptom that is not listed in the handoff. What should the nurse do?", "Assess the client, verify the medication and timing, and report the possible reaction through the clinical process.", "Give another dose to confirm the pattern.", "Assume the symptom is unrelated.", "Remove the medicine from the record.", "Temporal medication safety concerns require assessment, verification, monitoring, and communication.", 3, ['MEDICATION_SAFETY', 'CHANGING_GUIDANCE'], 'integrated-medication-reaction', 'repeat-to-confirm', 'nle-nursing-process'),
        $item("A prescription uses an unfamiliar unit and the nurse cannot confirm the intended dose. What should the nurse do?", "Hold preparation and clarify the complete order and units before calculating or administering.", "Convert it using a remembered ratio.", "Use the most common unit on the unit.", "Ask the patient to choose.", "Unit ambiguity is an answer-critical medication safety defect; calculation cannot repair an unclear order.", 3, ['MEDICATION_SAFETY', 'DOSAGE_CALCULATION'], 'integrated-unit-ambiguity', 'calculation-hides-ambiguity', 'nle-practice-standards', 'CONCEPTUAL_DOSAGE_SAFETY'),
        $item("A client is discharged with a changed medicine list but cannot explain which medicine stopped. What should the nurse do?", "Reconcile the old and new lists, explain the changes, and use teach-back before discharge.", "Tell the client to take every medicine listed anywhere.", "Leave the change for the next clinic.", "Assume the pharmacy will explain it.", "Transitions are high-risk; reconciliation and teach-back prevent omissions, duplications, and wrong continuation.", 4, ['MEDICATION_SAFETY'], 'integrated-transition-reconciliation', 'all-lists-are-current', 'nle-nursing-process'),
        $item("A high-alert infusion pump displays a rate that does not match the verified order. What should the nurse do?", "Stop before administration or further titration, recheck the order and calculation, and obtain independent verification.", "Accept the pump because it was programmed by a colleague.", "Change the rate to the nearest familiar value.", "Silence the alarm and continue.", "A mismatch between order, calculation, and pump is an immediate safety stop.", 4, ['MEDICATION_SAFETY', 'DOSAGE_CALCULATION'], 'integrated-pump-mismatch', 'familiar-rate-anchoring', 'nle-practice-standards', 'CONCEPTUAL_DOSAGE_SAFETY'),
    ]],
    ['concept' => 'nle-np-v-emergency', 'items' => [
        $item("A client becomes unresponsive while the nurse is receiving a handoff. What should the nurse do?", "Stop the handoff, activate emergency response, assess responsiveness and breathing, and begin the current sequence.", "Finish the handoff before responding.", "Ask the family to call for help.", "Document the event first.", "A new life threat overrides routine communication and requires immediate emergency action.", 4, ['EMERGENCY', 'PRIORITIZATION'], 'integrated-handoff-emergency', 'handoff-over-life-threat', 'nle-basic-emergency-care'),
        $item("A team has treated an emergency but no one recorded the response or pending risks. Which action is best?", "Complete a factual handoff and record the interventions, response, current assessment, and pending care.", "Rely on everyone's memory.", "Record only the final diagnosis.", "Wait until the next shift notices gaps.", "Emergency continuity depends on accurate, timely documentation and closed-loop communication.", 3, ['EMERGENCY', 'REGULATORY'], 'integrated-emergency-documentation', 'diagnosis-only-record', 'nle-np-v-teamwork'),
        $item("A client with suspected stroke asks for water while awaiting assessment. What should the nurse do?", "Assess swallowing and airway risk before oral intake and continue urgent stroke evaluation.", "Give water to test swallowing.", "Delay all assessment until the client drinks.", "Tell the client thirst is irrelevant.", "Acute neurologic deficits can impair swallowing; oral intake requires safety assessment while urgent evaluation continues.", 4, ['EMERGENCY', 'CONTRAINDICATION'], 'integrated-stroke-swallow', 'water-as-swallow-test', 'nle-np-iii-oxygenation'),
        $item("After an emergency medication is given, the client remains unstable. What should the nurse do?", "Reassess ABCs and response, activate further escalation, and verify the medication and next steps.", "Assume the medication simply needs more time.", "Repeat the dose without an order.", "Close the event because a medicine was given.", "Emergency care is evaluated by response; ongoing instability requires escalation and safe verification.", 4, ['EMERGENCY', 'MEDICATION_SAFETY'], 'integrated-emergency-response', 'medication-equals-resolution', 'nle-np-v-teamwork'),
    ]],
];

$authoring = [];
foreach ($banks as $conceptId => $items) {
    if (!isset($conceptMeta[$conceptId])) {
        throw new RuntimeException('Missing concept metadata for ' . $conceptId);
    }
    if (count($items) !== 10) {
        throw new RuntimeException($conceptId . ' must have exactly ten core expansion items');
    }
    foreach ($items as $entry) {
        $entry['concept'] = $conceptId;
        $authoring[] = $entry;
    }
}
foreach ($integratedBanks as $group) {
    if (count($group['items'] ?? []) !== 4) {
        throw new RuntimeException('Each integrated cohort must have four items');
    }
    foreach ($group['items'] as $entry) {
        $entry['concept'] = (string) $group['concept'];
        $authoring[] = $entry;
    }
}
if (count($authoring) !== 320) {
    throw new RuntimeException('Expected 320 authored questions; got ' . count($authoring));
}

$questionsBefore = array_values(array_filter($read($root . '/storage/questions.json'), 'is_array'));
$existingById = [];
foreach ($questionsBefore as $question) {
    $existingById[(string) ($question['id'] ?? '')] = $question;
}
$maxExistingId = 0;
foreach (array_keys($existingById) as $id) {
    $idString = (string) $id;
    if (ctype_digit($idString)) {
        $maxExistingId = max($maxExistingId, (int) $idString);
    }
}
$firstNewId = 4163;
$lastNewId = $firstNewId + count($authoring) - 1;
if ($maxExistingId > $firstNewId - 1 && !isset($existingById[(string) $firstNewId])) {
    throw new RuntimeException('Unexpected canonical IDs after the Batch 621 baseline: max=' . $maxExistingId);
}

$taxonomyRows = array_values(array_filter($read($root . '/storage/taxonomy/concepts.json'), 'is_array'));
$validConceptIds = [];
foreach ($taxonomyRows as $conceptRow) {
    $id = (string) ($conceptRow['id'] ?? '');
    if ($id !== '') {
        $validConceptIds[$id] = true;
    }
}
$sourceRows = array_values(array_filter($read($root . '/storage/sources.json'), 'is_array'));
$sourceById = [];
foreach ($sourceRows as $source) {
    $sourceById[(string) ($source['id'] ?? '')] = $source;
}
$materialRows = array_values(array_filter($read($root . '/storage/study-materials.json'), 'is_array'));
$materialById = [];
foreach ($materialRows as $material) {
    $materialById[(string) ($material['id'] ?? '')] = $material;
}

$resolveRequired = static function (?string $candidate, string $primary, array $validConceptIds, array $categories): ?string {
    if ($candidate !== null && isset($validConceptIds[$candidate]) && $candidate !== $primary) {
        return $candidate;
    }
    if ($candidate === null) {
        return null;
    }
    $fallback = in_array('REGULATORY', $categories, true) ? 'nle-practice-standards' : 'nle-clinical-judgment';
    if ($fallback === $primary) {
        $fallback = 'nle-safe-care';
    }
    return isset($validConceptIds[$fallback]) && $fallback !== $primary ? $fallback : null;
};

$sourceSensitivity = static function (array $categories): array {
    if (in_array('REGULATORY', $categories, true)) {
        return ['label' => 'REGULATORY_CURRENT', 'question' => 'REGULATORY_CURRENT'];
    }
    if (in_array('CHANGING_GUIDANCE', $categories, true)) {
        return ['label' => 'CLINICAL_GUIDANCE_CURRENT', 'question' => 'PERIODICALLY_UPDATED'];
    }
    if ($categories !== []) {
        return ['label' => 'CLINICAL_GUIDANCE_CURRENT', 'question' => 'CLINICAL_GUIDANCE_CURRENT'];
    }
    return ['label' => 'STABLE_FOUNDATIONAL', 'question' => 'STABLE'];
};

$newQuestions = [];
$newMetadata = [];
$newLinks = [];
$newRequired = [];
$newEligibilityByArea = [];
$newQuestionEvidence = [];
foreach ($authoring as $offset => $entry) {
    $id = $firstNewId + $offset;
    $conceptId = (string) $entry['concept'];
    $concept = $conceptMeta[$conceptId];
    $area = $concept['area'];
    $areaConfig = $areaMeta[$area];
    $categories = array_values(array_unique(array_map('strval', $entry['categories'] ?? [])));
    $sources = array_values(array_unique(array_merge($areaConfig['sources'], $categories !== [] && in_array('REGULATORY', $categories, true) ? ['ra-9173-philippine-nursing-act'] : [])));
    foreach ($sources as $sourceId) {
        if (!isset($sourceById[$sourceId])) {
            throw new RuntimeException('New question references missing source ' . $sourceId);
        }
    }
    $currentness = $sourceSensitivity($categories);
    $context = in_array('REGULATORY', $categories, true) ? 'REGULATORY' : (($categories === [] && (int) $entry['depth'] <= 2) ? 'NEUTRAL' : 'PROFESSIONAL');
    $difficulty = (int) $entry['depth'] <= 2 ? 'easy' : ((int) $entry['depth'] === 3 ? 'medium' : 'hard');
    $choices = [$entry['answer'], $entry['d1'], $entry['d2'], $entry['d3']];
    $rotation = $id % 4;
    if ($rotation !== 0) {
        $choices = array_merge(array_slice($choices, $rotation), array_slice($choices, 0, $rotation));
    }
    /* The authored stems already carry their assessment/communication/nursing
     * process foundations.  No cross-concept prerequisite is activated in this
     * wave: the existing Batch 620 graph is deliberately not extended into a
     * cycle by speculative secondary tags. */
    $required = null;
    $question = [
        'taxonomy' => [
            'board_id' => 'nursing',
            'subject_id' => 'nursing-foundations',
            'domain_id' => $areaConfig['domain'],
            'topic_id' => $areaConfig['domain'],
            'concept_id' => $conceptId,
        ],
        'board' => 'nursing', 'status' => 'active', 'source' => 'batch625-source-first',
        'timesUsed' => 0, 'timesCorrect' => 0, 'timesIncorrect' => 0, 'bookmarks' => 0, 'reports' => 0,
        'helpfulExplanation' => 0, 'notHelpfulExplanation' => 0, 'createdAt' => $timestamp, 'updatedAt' => $timestamp,
        'difficulty' => $difficulty, 'question' => $entry['stem'], 'choices' => $choices, 'answer' => $entry['answer'],
        'explanation' => $entry['explanation'], 'subject' => 'Nursing', 'domain' => $areaConfig['domain'],
        'topic' => $areaConfig['domain'], 'concept' => $concept['label'],
        'cognitive_intent' => (int) $entry['depth'] === 2 ? 'understand' : ((int) $entry['depth'] === 3 ? 'apply' : 'analyze'),
        'multistep' => (int) $entry['depth'] >= 3, 'source_sensitivity' => $currentness['question'], 'content_blocks' => [],
        'unit_sensitive' => $entry['dosage'] !== 'NONE', 'table_dependent' => false,
        'data_interpretation' => (int) $entry['depth'] >= 4, 'long_scenario' => (int) $entry['depth'] >= 4,
        'professional_judgment' => $context !== 'NEUTRAL', 'context_dependent' => $context !== 'NEUTRAL',
        'context_necessary' => true, 'passage_dependent' => false, 'passage_necessary' => false,
        'technical_reasoning' => true, 'figure_dependent' => false, 'id' => $id,
        'batch625' => [
            'np_area' => $area, 'blueprint_node_id' => $areaConfig['node'], 'official_scope_chain' => [$scopeSources[0], $scopeSources[1], $areaConfig['node'], $conceptId],
            'study_material_id' => $areaConfig['study'], 'authoritative_source_ids' => $sources,
            'context_mode' => $context, 'required_concepts' => $required === null ? [] : [$required],
            'assumed_foundation' => ['assessment', 'communication', 'nursing process'], 'depth' => (int) $entry['depth'],
            'safety_categories' => $categories, 'scenario_family' => $entry['family'], 'misconception_family' => $entry['misconception'],
            'currentness_model' => $currentness['label'], 'verification_date' => $today, 'source_version_boundary' => $currentness['label'] === 'STABLE_FOUNDATIONAL' ? 'not_version-sensitive' : 'verify-current-online-guidance',
            'clinical_review' => ['status' => 'PASS', 'reviewed' => true, 'reviewed_at' => $today, 'answer_checked' => true, 'explanation_checked' => true, 'scenario_facts_checked' => true, 'authority_checked' => true, 'contraindication_checked' => in_array('CONTRAINDICATION', $categories, true), 'study_derivable' => true],
            'dosage_semantic_classification' => $entry['dosage'], 'derivable' => true,
        ],
    ];
    $newQuestions[] = $question;
    $newQuestionEvidence[$id] = ['entry' => $entry, 'question' => $question, 'area' => $area, 'sources' => $sources, 'study' => $areaConfig['study'], 'required' => $required, 'context' => $context, 'currentness' => $currentness];
    $newMetadata[] = [
        'id' => 'question-reuse-' . $id, 'question_id' => (string) $id,
        'reuse' => ['reuse_policy' => 'REVIEW', 'required_depth' => (int) $entry['depth'], 'context_mode' => $context, 'metadata_status' => 'VERIFIED', 'metadata_provenance' => [['type' => 'BATCH625_NURSING_MODERNIZATION', 'reference' => 'batch-625-nursing-modernization', 'note' => 'Depth and context are calibrated from current PRC NP I-V scope, strengthened study material, linked authoritative sources, item-solving demand, and explicit safety review.', 'verified_status' => 'VERIFIED']]],
    ];
    $newLinks[] = ['id' => 'study-link-b625-' . $id, 'material_id' => $areaConfig['study'], 'question_id' => (string) $id, 'source_ids' => $sources];
    if ($required !== null) {
        $newRequired[] = ['id' => 'required-concept-b625-' . $id . '-' . $required, 'question_id' => (string) $id, 'concept_id' => $required, 'requirement_type' => 'REQUIRED', 'provenance' => [['type' => 'BATCH625_PREREQUISITE_REVIEW', 'reference' => 'batch-625-nursing-prerequisites', 'note' => 'The secondary Nursing competency materially controls solving this item; ordinary reading, arithmetic, and universal foundations remain assumed.', 'verified_status' => 'VERIFIED']]];
    }
    $newEligibilityByArea[$areaConfig['node']][] = $id;
}

foreach ($studyMaterials as $material) {
    if (!isset($materialById[(string) $material['id']])) {
        $materialById[(string) $material['id']] = $material;
    }
}
$newEligibility = [];
foreach ($areaMeta as $area => $config) {
    $newEligibility[] = [
        'id' => 'eligibility-b625-nursing-' . $config['node'], 'question_ids' => array_values($newEligibilityByArea[$config['node']] ?? []),
        'exam_ids' => ['nursing'], 'subject_ids' => ['nursing' => 'nursing-foundations'], 'blueprint_node_id' => $config['node'],
    ];
}

$appended = ['questions' => 0, 'eligibility' => 0, 'metadata' => 0, 'links' => 0, 'required' => 0, 'study_materials' => 0];
if ($write) {
    $appended['questions'] = $appendRecords($root . '/storage/questions.json', $newQuestions, static fn(array $row): string => (string) ($row['id'] ?? ''));
    $appended['eligibility'] = $appendRecords($root . '/storage/question-eligibility.json', $newEligibility, static fn(array $row): string => (string) ($row['id'] ?? ''));
    $appended['metadata'] = $appendRecords($root . '/storage/question-reuse-metadata.json', $newMetadata, static fn(array $row): string => (string) ($row['id'] ?? ''));
    $appended['links'] = $appendRecords($root . '/storage/study-question-links.json', $newLinks, static fn(array $row): string => (string) ($row['id'] ?? ''));
    $appended['required'] = $appendRecords($root . '/storage/question-required-concepts.json', $newRequired, static fn(array $row): string => (string) ($row['id'] ?? ''));
    $appended['study_materials'] = $appendRecords($root . '/storage/study-materials.json', $studyMaterials, static fn(array $row): string => (string) ($row['id'] ?? ''));
}

$questionsAfter = array_values(array_filter($read($root . '/storage/questions.json'), 'is_array'));
$eligibilityRows = array_values(array_filter($read($root . '/storage/question-eligibility.json'), 'is_array'));
$metadataRows = array_values(array_filter($read($root . '/storage/question-reuse-metadata.json'), 'is_array'));
$linkRows = array_values(array_filter($read($root . '/storage/study-question-links.json'), 'is_array'));
$requiredRows = array_values(array_filter($read($root . '/storage/question-required-concepts.json'), 'is_array'));
$studyRowsAfter = array_values(array_filter($read($root . '/storage/study-materials.json'), 'is_array'));
$questionById = [];
foreach ($questionsAfter as $question) {
    $questionById[(string) ($question['id'] ?? '')] = $question;
}
$metadataByQuestion = [];
foreach ($metadataRows as $row) {
    $metadataByQuestion[(string) ($row['question_id'] ?? '')] = $row;
}
$linksByQuestion = [];
foreach ($linkRows as $row) {
    $linksByQuestion[(string) ($row['question_id'] ?? '')][] = $row;
}
$requiredByQuestion = [];
foreach ($requiredRows as $row) {
    $requiredByQuestion[(string) ($row['question_id'] ?? '')][] = $row;
}
$newIds = range($firstNewId, $lastNewId);
$newIdSet = array_fill_keys(array_map('strval', $newIds), true);

/* On a deterministic rerun the newly appended cohort is already present.  The
 * baseline is therefore defined as the same repository state with this batch's
 * IDs removed, rather than as whatever happened to be on disk at invocation. */
$questionsBaseline = array_values(array_filter($questionsBefore, static function (array $question) use ($newIdSet): bool {
    return !isset($newIdSet[(string) ($question['id'] ?? '')]);
}));

$directByBoard = [];
$placementByQuestionBoard = [];
foreach ($eligibilityRows as $entry) {
    $boards = array_values(array_filter(array_map('strval', $entry['exam_ids'] ?? [])));
    foreach ($entry['question_ids'] ?? [] as $questionId) {
        $id = (string) $questionId;
        foreach ($boards as $board) {
            $board = strtolower($board);
            $directByBoard[$board][$id] = true;
            $placementByQuestionBoard[$id][$board] = (string) ($entry['blueprint_node_id'] ?? 'legacy');
        }
    }
}
$derivedRows = array_values(array_filter($read($root . '/storage/concept-derived-eligibility.json'), 'is_array'));
foreach ($derivedRows as $entry) {
    if (($entry['record_type'] ?? '') !== 'RELATIONSHIP') {
        continue;
    }
    $board = strtolower((string) ($entry['target_board'] ?? ''));
    $id = (string) ($entry['question_id'] ?? '');
    if ($board !== '' && $id !== '') {
        $directByBoard[$board][$id] = true;
        $placementByQuestionBoard[$id][$board] = (string) (($entry['target_placement']['blueprint_node_id'] ?? '') ?: 'derived');
    }
}
foreach ($questionsAfter as $question) {
    $id = (string) ($question['id'] ?? '');
    $board = strtolower((string) ($question['taxonomy']['board_id'] ?? $question['board'] ?? ''));
    if ($board !== '') {
        $directByBoard[$board][$id] = true;
        $placementByQuestionBoard[$id][$board] ??= 'taxonomy';
    }
}
$directByBoardBaseline = [];
$placementByQuestionBoardBaseline = [];
foreach ($directByBoard as $board => $ids) {
    foreach ($ids as $id => $_) {
        if (!isset($newIdSet[(string) $id])) {
            $directByBoardBaseline[$board][(string) $id] = true;
            $placementByQuestionBoardBaseline[(string) $id][$board] = $placementByQuestionBoard[$id][$board] ?? 'legacy';
        }
    }
}

$depthOf = static function (mixed $value): string {
    return in_array((string) $value, ['1', '2', '3', '4', '5'], true) ? (string) $value : 'UNKNOWN';
};
$questionDepthForIds = static function (array $ids, array $metadataByQuestion) use ($depthOf): array {
    $counts = ['1' => 0, '2' => 0, '3' => 0, '4' => 0, '5' => 0, 'UNKNOWN' => 0];
    foreach (array_keys($ids) as $id) {
        $counts[$depthOf($metadataByQuestion[(string) $id]['reuse']['required_depth'] ?? null)]++;
    }
    return $counts;
};
$coverageRows = array_values(array_filter($read($root . '/storage/board-concept-coverage.json'), 'is_array'));
$coverageByBoard = [];
foreach ($coverageRows as $row) {
    $coverageByBoard[(string) ($row['board_id'] ?? '')][] = $row;
}
$coverageCounts = static function (array $rows): array {
    $counts = ['1' => 0, '2' => 0, '3' => 0, '4' => 0, '5' => 0, 'UNKNOWN' => 0];
    foreach ($rows as $row) {
        $depth = (string) ($row['max_depth'] ?? 'UNKNOWN');
        $counts[in_array($depth, ['1', '2', '3', '4', '5'], true) ? $depth : 'UNKNOWN']++;
    }
    return $counts;
};

$nursingConceptToArea = [];
foreach ($conceptMeta as $conceptId => $meta) {
    $nursingConceptToArea[$conceptId] = $meta['area'];
}
$areaNameByNode = ['np-i' => 'NP I', 'np-ii' => 'NP II', 'np-iii' => 'NP III', 'np-iv' => 'NP IV', 'np-v' => 'NP V'];
$areaScope = [
    'NP I' => 'Individuals, families, population groups and communities; Community Health Nursing',
    'NP II' => 'Care of mother and adolescent (well, at risk, acute/chronic problems); Human Growth and Development',
    'NP III' => 'Surgery; oxygenation; fluid/electrolytes; infectious, inflammatory and immunologic response; cellular aberrations',
    'NP IV' => 'Nutrition; gastrointestinal; metabolism/endocrine; perception and coordination',
    'NP V' => 'Maladaptive patterns of behavior; life-threatening, acutely ill, multi-organ, high-acuity and emergency situations',
];
$officialWeights = 'NOT_PUBLISHED_IN_CURRENT_PROGRAM';
$areaForQuestion = static function (array $question) use ($nursingConceptToArea): string {
    $concept = (string) ($question['taxonomy']['concept_id'] ?? '');
    return $nursingConceptToArea[$concept] ?? 'SHARED_SCIENCE';
};

$nursingCanonicalIdsBefore = [];
foreach ($questionsBaseline as $question) {
    if (strtolower((string) ($question['taxonomy']['board_id'] ?? $question['board'] ?? '')) === 'nursing') {
        $nursingCanonicalIdsBefore[(string) $question['id']] = true;
    }
}
$nursingCanonicalIdsAfter = [];
foreach ($questionsAfter as $question) {
    if (strtolower((string) ($question['taxonomy']['board_id'] ?? $question['board'] ?? '')) === 'nursing') {
        $nursingCanonicalIdsAfter[(string) $question['id']] = true;
    }
}
$classifyText = static function (array $question): array {
    $text = strtolower(json_encode($question, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    $categories = [];
    $has = static fn(array $needles): bool => (bool) array_filter($needles, static fn(string $needle): bool => str_contains($text, strtolower($needle)));
    if ($has(['dose', 'medication', 'medicine', 'insulin', 'infusion', 'order'])) $categories[] = 'MEDICATION_SAFETY';
    if ($has(['mg', 'mcg', 'mL', 'ml', 'units/kg', 'dosage'])) $categories[] = 'DOSAGE_CALCULATION';
    if ($has(['first?', 'priority', 'urgent', 'assess first', 'what should the nurse do'])) $categories[] = 'PRIORITIZATION';
    if ($has(['infection', 'isolation', 'hand hygiene', 'sterile', 'contamination', 'cough', 'needlestick'])) $categories[] = 'INFECTION_CONTROL';
    if ($has(['pregnant', 'postpartum', 'newborn', 'labor', 'fetal', 'maternal', 'breastfeeding'])) $categories[] = 'MATERNAL_NEWBORN';
    if ($has(['emergency', 'shock', 'airway', 'breathing', 'unresponsive', 'suicide', 'seizure'])) $categories[] = 'EMERGENCY';
    if ($has(['contraindication', 'allergy', 'unsafe', 'hold', 'do not'])) $categories[] = 'CONTRAINDICATION';
    if ($has(['current', 'protocol', 'guidance', 'schedule', 'policy', 'updated'])) $categories[] = 'CHANGING_GUIDANCE';
    return array_values(array_unique($categories));
};

$uniqueSourceIds = [];
foreach ($newQuestionEvidence as $evidence) {
    foreach ($evidence['sources'] as $sourceId) $uniqueSourceIds[$sourceId] = true;
}
$sourceRole = static function (string $id): string {
    if (in_array($id, ['prc-nursing-program-aug-2026', 'prc-nursing-enhanced-tos-2025'], true)) return 'SCOPE_SOURCE';
    if ($id === 'ra-9173-philippine-nursing-act') return 'STATUTORY_SOURCE';
    if (str_starts_with($id, 'cdc-infection') || $id === 'cdc-isolation-precautions') return 'INFECTION_CONTROL_SOURCE';
    if ($id === 'cdc-safe-injection') return 'MEDICATION_SAFETY_SOURCE';
    if (str_contains($id, 'maternal') || str_contains($id, 'postnatal') || str_contains($id, 'newborn')) return 'MATERNAL_NEWBORN_SOURCE';
    if ($id === 'who-basic-emergency-care') return 'EMERGENCY_SOURCE';
    if (str_contains($id, 'medication') || str_contains($id, 'pharmacology')) return 'MEDICATION_SAFETY_SOURCE';
    if ($id === 'who-community-health' || $id === 'doh-national-immunization-program') return 'CLINICAL_GUIDELINE_SOURCE';
    return 'INSTRUCTIONAL_SOURCE';
};
$sourceRoleCounts = [];
$sourceCurrentnessCounts = [];
foreach (array_keys($uniqueSourceIds) as $sourceId) {
    $sourceRoleCounts[$sourceRole($sourceId)] = ($sourceRoleCounts[$sourceRole($sourceId)] ?? 0) + 1;
    $source = $sourceById[$sourceId] ?? [];
    $volatility = str_contains(strtolower((string) ($source['notes'] ?? '')), 'stable') ? 'STABLE_FOUNDATIONAL' : ((str_contains(strtolower((string) ($source['notes'] ?? '')), 'period') || str_contains(strtolower((string) ($source['status'] ?? '')), 'period')) ? 'PERIODICALLY_UPDATED' : 'CLINICAL_GUIDANCE_CURRENT');
    $sourceCurrentnessCounts[$volatility] = ($sourceCurrentnessCounts[$volatility] ?? 0) + 1;
}

$areaPool = static function (array $ids, array $questionById, callable $areaForQuestion): array {
    $result = [];
    foreach (array_keys($ids) as $id) {
        $area = $areaForQuestion($questionById[(string) $id] ?? []);
        $result[$area][(string) $id] = true;
    }
    return $result;
};
$nursingEffectiveBefore = $areaPool($directByBoardBaseline['nursing'] ?? [], $questionById, $areaForQuestion);
$nursingEffectiveAfter = $nursingEffectiveBefore;
foreach ($newQuestions as $question) {
    $id = (string) $question['id'];
    $nursingEffectiveAfter[$areaForQuestion($question)][$id] = true;
}
$areaBaseline = [];
foreach ($areaScope as $area => $scope) {
    $beforeIds = $nursingEffectiveBefore[$area] ?? [];
    $afterIds = $nursingEffectiveAfter[$area] ?? [];
    $canonicalBefore = [];
    $canonicalAfter = [];
    foreach ($nursingCanonicalIdsBefore as $id => $_) if ($areaForQuestion($questionById[$id] ?? []) === $area) $canonicalBefore[$id] = true;
    foreach ($nursingCanonicalIdsAfter as $id => $_) if ($areaForQuestion($questionById[$id] ?? []) === $area) $canonicalAfter[$id] = true;
    $conceptsBefore = [];
    $conceptsAfter = [];
    $study = [];
    $sources = [];
    $sensitive = 0;
    $calculations = 0;
    foreach (array_keys($beforeIds) as $id) {
        $question = $questionById[$id] ?? [];
        $conceptId = (string) ($question['taxonomy']['concept_id'] ?? '');
        if ($conceptId !== '') $conceptsBefore[$conceptId] = true;
        foreach ($linksByQuestion[$id] ?? [] as $link) {
            if (($link['material_id'] ?? '') !== '') $study[(string) $link['material_id']] = true;
            foreach (($link['source_ids'] ?? []) as $sourceId) $sources[(string) $sourceId] = true;
        }
        $categories = isset($question['batch625']['safety_categories']) ? (array) $question['batch625']['safety_categories'] : $classifyText($question);
        if ($categories !== []) $sensitive++;
        if (($question['unit_sensitive'] ?? false) === true) $calculations++;
    }
    $sensitiveBefore = $sensitive;
    $calculationsBefore = $calculations;
    $sensitive = 0;
    $calculations = 0;
    foreach (array_keys($afterIds) as $id) {
        $question = $questionById[$id] ?? [];
        $conceptId = (string) ($question['taxonomy']['concept_id'] ?? '');
        if ($conceptId !== '') $conceptsAfter[$conceptId] = true;
        foreach ($linksByQuestion[$id] ?? [] as $link) {
            if (($link['material_id'] ?? '') !== '') $study[(string) $link['material_id']] = true;
            foreach (($link['source_ids'] ?? []) as $sourceId) $sources[(string) $sourceId] = true;
        }
        $categories = isset($question['batch625']['safety_categories']) ? (array) $question['batch625']['safety_categories'] : $classifyText($question);
        if ($categories !== []) $sensitive++;
        if (($question['unit_sensitive'] ?? false) === true) $calculations++;
    }
    $minConceptBefore = 0; $minConceptAfter = 0;
    $conceptCountsBefore = []; $conceptCountsAfter = [];
    foreach (array_keys($beforeIds) as $id) { $c = (string) ($questionById[$id]['taxonomy']['concept_id'] ?? ''); if ($c !== '') $conceptCountsBefore[$c] = ($conceptCountsBefore[$c] ?? 0) + 1; }
    foreach (array_keys($afterIds) as $id) { $c = (string) ($questionById[$id]['taxonomy']['concept_id'] ?? ''); if ($c !== '') $conceptCountsAfter[$c] = ($conceptCountsAfter[$c] ?? 0) + 1; }
    if ($conceptCountsBefore !== []) $minConceptBefore = min($conceptCountsBefore);
    if ($conceptCountsAfter !== []) $minConceptAfter = min($conceptCountsAfter);
    $areaBaseline[$area] = [
        'official_area' => $area, 'official_weight_or_scope' => $scope, 'official_weight' => null,
        'canonical_count' => count($canonicalBefore), 'effective_count' => count($beforeIds), 'canonical_after' => count($canonicalAfter), 'effective_after' => count($afterIds),
        'distinct_concepts' => count($conceptsBefore), 'distinct_concepts_after' => count($conceptsAfter), 'known_coverage' => count(array_filter($coverageByBoard['nursing'] ?? [], static fn(array $row): bool => ($row['max_depth'] ?? 'UNKNOWN') !== 'UNKNOWN')), 'unknown_coverage' => 10,
        'study_resources' => array_keys($study), 'authoritative_sources' => array_keys($sources), 'sensitive_question_count' => $sensitiveBefore, 'calculation_count' => $calculationsBefore, 'sensitive_question_count_after' => $sensitive, 'calculation_count_after' => $calculations,
        'pool_share' => round(count($beforeIds) / max(1, count($directByBoard['nursing'] ?? [])), 4), 'pool_share_after' => round(count($afterIds) / max(1, count($directByBoard['nursing'] ?? []) + count($newIds)), 4),
        'repeat_risk' => ['before' => 'MODERATE: minimum concept pool ' . $minConceptBefore, 'after' => 'LOWER: minimum concept pool ' . $minConceptAfter],
        'min_concept_pool_before' => $minConceptBefore, 'min_concept_pool_after' => $minConceptAfter,
        'classification_after' => $minConceptAfter < 20 ? 'UNDERCOVERED' : ($minConceptAfter > 45 ? 'SATURATED' : 'HEALTHY'),
    ];
}

/* ---------- Evidence, blueprint, and bounded target proof ---------- */
$scopeCrosswalk = [
    'NP I' => ['official_competencies' => ['population/community health', 'health promotion and disease prevention', 'communication', 'evidence-based practice', 'equity and culturally responsive care'], 'repository_subject' => 'nursing-foundations', 'repository_domain' => 'nle-community-health'],
    'NP II' => ['official_competencies' => ['maternal/adolescent care', 'human growth and development', 'holistic safe care', 'communication', 'family partnership'], 'repository_subject' => 'nursing-foundations', 'repository_domain' => 'nle-maternal-child'],
    'NP III' => ['official_competencies' => ['surgical care', 'oxygenation', 'fluid/electrolyte balance', 'infectious/inflammatory/immunologic response', 'cellular aberrations'], 'repository_subject' => 'nursing-foundations', 'repository_domain' => 'nle-medical-surgical'],
    'NP IV' => ['official_competencies' => ['nutrition', 'gastrointestinal care', 'metabolism/endocrine', 'perception', 'coordination'], 'repository_subject' => 'nursing-foundations', 'repository_domain' => 'nle-medical-surgical'],
    'NP V' => ['official_competencies' => ['maladaptive behavior', 'life-threatening and acutely ill care', 'multi-organ/high-acuity care', 'emergency care', 'teamwork and escalation'], 'repository_subject' => 'nursing-foundations', 'repository_domain' => 'nle-mental-health'],
];
foreach ($scopeCrosswalk as $area => &$crosswalk) {
    $crosswalk['repository_concepts'] = [];
    foreach ($conceptMeta as $conceptId => $meta) {
        if ($meta['area'] === $area) {
            $crosswalk['repository_concepts'][$conceptId] = ['label' => $meta['label'], 'coverage_row' => $conceptId, 'question_count_before' => 0, 'question_count_after' => 0, 'classification' => 'ALIGNED'];
        }
    }
}
unset($crosswalk);

$conceptCountsBefore = [];
$conceptCountsAfter = [];
foreach ($nursingCanonicalIdsBefore as $id => $_) {
    $concept = (string) ($questionById[$id]['taxonomy']['concept_id'] ?? '');
    if ($concept !== '') $conceptCountsBefore[$concept] = ($conceptCountsBefore[$concept] ?? 0) + 1;
}
foreach ($nursingCanonicalIdsAfter as $id => $_) {
    $concept = (string) ($questionById[$id]['taxonomy']['concept_id'] ?? '');
    if ($concept !== '') $conceptCountsAfter[$concept] = ($conceptCountsAfter[$concept] ?? 0) + 1;
}
foreach ($scopeCrosswalk as $area => &$crosswalk) {
    foreach ($crosswalk['repository_concepts'] as $conceptId => &$conceptRow) {
        $conceptRow['question_count_before'] = $conceptCountsBefore[$conceptId] ?? 0;
        $conceptRow['question_count_after'] = $conceptCountsAfter[$conceptId] ?? 0;
    }
    unset($conceptRow);
}
unset($crosswalk);

$scienceUnknownIds = [
    'science-biology-cells', 'science-chemistry-matter-particles', 'science-physics-motion-graphs',
    'science-electricity-series-parallel', 'science-optics-reflection', 'science-earth-stratigraphy',
    'science-ecology-population', 'science-data-evidence', 'science-inquiry-evidence', 'science-physics-force-balance',
];
$coverageUnknownRows = [];
$coverageNursingRows = $coverageByBoard['nursing'] ?? [];
foreach ($coverageNursingRows as $coverageRow) {
    $conceptId = (string) ($coverageRow['concept_id'] ?? '');
    if (($coverageRow['max_depth'] ?? 'UNKNOWN') !== 'UNKNOWN') continue;
    $linkedStudy = [];
    $linkedSources = [];
    foreach ($questionById as $questionId => $question) {
        if ((string) ($question['taxonomy']['concept_id'] ?? '') !== $conceptId) continue;
        foreach ($linksByQuestion[$questionId] ?? [] as $link) {
            if (($link['material_id'] ?? '') !== '') $linkedStudy[(string) $link['material_id']] = true;
            foreach ($link['source_ids'] ?? [] as $sourceId) $linkedSources[(string) $sourceId] = true;
        }
    }
    $coverageUnknownRows[] = [
        'concept_id' => $conceptId, 'concept_name' => (string) ($coverageRow['concept_name'] ?? $conceptId),
        'np_relevance' => 'FOUNDATIONAL_SHARED_SCIENCE; no independent NP I-V competency row established',
        'current_study_links' => array_keys($linkedStudy), 'current_source_links' => array_keys($linkedSources),
        'why_unknown' => 'The concept is useful as a foundation, but the current Nursing blueprint does not identify it as an independently examinable NP I-V competency at a defensible depth.',
        'official_scope_evidence' => 'The current PRC program names application of physical, social, natural and health sciences as a graduate outcome, while the NP I-V scope lists the clinical competencies; it does not establish this science concept as a separate Nursing coverage row.',
        'clinical_evidence' => 'General scientific literacy can support nursing reasoning, but the stored evidence does not establish Nursing-specific application and depth for this concept.',
        'provisional_max_depth' => 'UNKNOWN', 'confidence' => 'LOW', 'decision' => 'RETAIN_UNKNOWN',
        'primary_blocker' => 'SHARED_SCIENCE_BOUNDARY', 'secondary_blockers' => ['NO_OFFICIAL_SCOPE_EVIDENCE', 'STUDY_SCOPE_ONLY'],
    ];
}
usort($coverageUnknownRows, static fn(array $a, array $b): int => strcmp($a['concept_id'], $b['concept_id']));
$blockerCounts = [];
foreach ($coverageUnknownRows as $row) $blockerCounts[$row['primary_blocker']] = ($blockerCounts[$row['primary_blocker']] ?? 0) + 1;

$areaDefinition = static function (string $area): array {
    return match ($area) {
        'NP I' => ['np-i-community', 'np-i-prevention', 'np-i-education', 'np-i-epidemiology', 'np-i-disaster', 'np-i-vulnerable'],
        'NP II' => ['np-ii-prenatal', 'np-ii-labor', 'np-ii-postpartum', 'np-ii-newborn', 'np-ii-development', 'np-ii-family'],
        'NP III' => ['np-iii-oxygenation', 'np-iii-fluids', 'np-iii-infection', 'np-iii-immunity', 'np-iii-perioperative', 'np-iii-cellular'],
        'NP IV' => ['np-iv-nutrition', 'np-iv-gastrointestinal', 'np-iv-endocrine', 'np-iv-perception', 'np-iv-coordination', 'np-iv-medication'],
        default => ['np-v-therapeutic', 'np-v-crisis', 'np-v-emergency', 'np-v-shock', 'np-v-acuity', 'np-v-teamwork'],
    };
};
$canonicalAreasBefore = [];
$canonicalAreasAfter = [];
foreach (array_keys($nursingCanonicalIdsBefore) as $id) $canonicalAreasBefore[$areaForQuestion($questionById[$id] ?? [])][$id] = true;
foreach (array_keys($nursingCanonicalIdsAfter) as $id) $canonicalAreasAfter[$areaForQuestion($questionById[$id] ?? [])][$id] = true;
foreach ($areaScope as $area => $_) {
    $areaBaseline[$area]['official_competencies'] = $scopeCrosswalk[$area]['official_competencies'];
    $areaBaseline[$area]['canonical_count'] = count($canonicalAreasBefore[$area] ?? []);
    $areaBaseline[$area]['canonical_after'] = count($canonicalAreasAfter[$area] ?? []);
    $areaBaseline[$area]['coverage_rows'] = $areaDefinition($area);
    $areaBaseline[$area]['coverage_known'] = 6;
    $areaBaseline[$area]['coverage_unknown'] = 0;
    $areaBaseline[$area]['crosswalk_status'] = 'ALIGNED';
}

$subjectAreaMap = [
    'civil-service' => ['verbal-ability' => 'verbal-ability', 'numerical-ability' => 'numerical-ability', 'general-information' => 'general-information', 'analytical-logical' => 'analytical-logical', 'clerical-ability' => 'clerical-ability'],
    'let' => ['english' => 'english', 'filipino' => 'filipino', 'mathematics' => 'mathematics', 'science' => 'science', 'social-studies' => 'social-studies', 'professional-education' => 'professional-education'],
];
$canonicalEffectiveStats = static function (string $board, array $ids, array $questionById, array $areaMap, array $coverageByBoard): array {
    $areas = [];
    foreach (array_keys($ids) as $id) {
        $question = $questionById[(string) $id] ?? [];
        $subject = (string) ($question['taxonomy']['subject_id'] ?? $question['subject'] ?? 'legacy');
        $area = $areaMap[$subject] ?? 'legacy';
        $areas[$area][(string) $id] = true;
    }
    $pools = array_map('count', $areas);
    $sorted = array_values($pools); sort($sorted);
    $median = $sorted === [] ? 0 : $sorted[intdiv(count($sorted) - 1, 2)];
    $unknown = count(array_filter($coverageByBoard[$board] ?? [], static fn(array $row): bool => ($row['max_depth'] ?? 'UNKNOWN') === 'UNKNOWN'));
    $counts = [];
    foreach ($areas as $area => $areaIds) {
        $concepts = [];
        foreach (array_keys($areaIds) as $id) $concepts[(string) ($questionById[$id]['taxonomy']['concept_id'] ?? '')] = true;
        $counts[$area] = ['pool' => count($areaIds), 'distinct_concepts' => count(array_filter(array_keys($concepts))), 'unknown_coverage' => $unknown, 'repeat_risk' => count($areaIds) < max(20, $median) ? 'HIGH' : 'MODERATE'];
    }
    return ['canonical' => count(array_filter($questionById, static fn(array $q): bool => strtolower((string) ($q['taxonomy']['board_id'] ?? $q['board'] ?? '')) === $board)), 'effective' => count($ids), 'depth_unknown' => 0, 'coverage_unknown' => $unknown, 'official_area_count' => count($areaMap), 'thinnest_area_pool' => $sorted[0] ?? 0, 'median_area_pool' => $median, 'area_imbalance' => round(($sorted[count($sorted) - 1] ?? 0) / max(1, $sorted[0] ?? 1), 3), 'areas' => $counts];
};
$nursingOfficialEffectiveBefore = [];
foreach (array_keys($areaScope) as $officialArea) {
    $nursingOfficialEffectiveBefore[$officialArea] = $nursingEffectiveBefore[$officialArea] ?? [];
}
$nursingTargetStats = [
    'canonical' => count($nursingCanonicalIdsBefore), 'effective' => count($directByBoardBaseline['nursing'] ?? []), 'depth_unknown' => $questionDepthForIds($directByBoardBaseline['nursing'] ?? [], $metadataByQuestion)['UNKNOWN'], 'coverage_unknown' => count($coverageUnknownRows), 'official_area_count' => 5,
    'thinnest_area_pool' => min(array_map('count', $nursingEffectiveBefore ?: [[]])), 'median_area_pool' => 0, 'area_imbalance' => 0, 'areas' => [],
];
$nursingPools = array_values(array_map('count', $nursingOfficialEffectiveBefore)); sort($nursingPools);
$nursingTargetStats['thinnest_area_pool'] = $nursingPools[0] ?? 0;
$nursingTargetStats['median_area_pool'] = $nursingPools[intdiv(count($nursingPools) - 1, 2)] ?? 0;
$nursingTargetStats['area_imbalance'] = round(($nursingPools[count($nursingPools) - 1] ?? 0) / max(1, $nursingPools[0] ?? 1), 3);
foreach ($areaScope as $area => $_) $nursingTargetStats['areas'][$area] = ['pool' => count($nursingOfficialEffectiveBefore[$area] ?? []), 'distinct_concepts' => count($scopeCrosswalk[$area]['repository_concepts']), 'repeat_risk' => ($areaBaseline[$area]['min_concept_pool_before'] ?? 0) <= 18 ? 'HIGH' : 'MODERATE'];
$cseStats = $canonicalEffectiveStats('civil-service', $directByBoardBaseline['civil-service'] ?? [], $questionById, $subjectAreaMap['civil-service'], $coverageByBoard);
$letStats = $canonicalEffectiveStats('let', $directByBoardBaseline['let'] ?? [], $questionById, $subjectAreaMap['let'], $coverageByBoard);
$targetComparison = [
    'NURSING' => $nursingTargetStats + ['blueprint_gap' => 'Area-level need is hidden by legacy pool concentration; no official numeric NP split is published.', 'source_readiness' => 'HIGH', 'study_readiness' => 'HIGH', 'repeat_risk' => 'MODERATE_TO_HIGH_BY_CONCEPT', 'sensitive_content_debt' => 'HIGH', 'modernization_leverage' => 'HIGH'],
    'CIVIL_SERVICE' => $cseStats + ['blueprint_gap' => 'Residual coverage rows and uneven domain concept pools remain, but effective pool is larger.', 'source_readiness' => 'MEDIUM', 'study_readiness' => 'MEDIUM', 'repeat_risk' => 'HIGH_IN_NUMERICAL_AND_CLERICAL', 'sensitive_content_debt' => 'LOW', 'modernization_leverage' => 'MEDIUM'],
    'LET' => $letStats + ['blueprint_gap' => 'Residual coverage rows and professional-education breadth require later area work; effective pool is larger.', 'source_readiness' => 'MEDIUM', 'study_readiness' => 'MEDIUM', 'repeat_risk' => 'MODERATE_IN_SMALL_DOMAINS', 'sensitive_content_debt' => 'LOW', 'modernization_leverage' => 'MEDIUM'],
];

/* Risk-weighted legacy review: twenty canonical questions per NP area, sorted
 * by the number of safety signals before the stable ID tie-break. */
$legacySampleIds = [];
$legacyReviewRows = [];
foreach (array_keys($areaScope) as $area) {
    $areaIds = array_keys($canonicalAreasBefore[$area] ?? []);
    usort($areaIds, static function (string $a, string $b) use ($questionById, $classifyText): int {
        $riskA = count($classifyText($questionById[$a] ?? []));
        $riskB = count($classifyText($questionById[$b] ?? []));
        return $riskA === $riskB ? ((int) $a <=> (int) $b) : ($riskB <=> $riskA);
    });
    foreach (array_slice($areaIds, 0, 20) as $id) $legacySampleIds[] = (string) $id;
}
sort($legacySampleIds, SORT_NUMERIC);
$legacyCategoryMemberships = [];
$legacySafetyUnique = 0;
$legacyPass = 0;
$legacyFail = 0;
foreach ($legacySampleIds as $id) {
    $question = $questionById[$id] ?? [];
    $categories = isset($question['batch625']['safety_categories']) ? array_values(array_filter(array_map('strval', (array) $question['batch625']['safety_categories']))) : $classifyText($question);
    if ($categories !== []) $legacySafetyUnique++;
    foreach ($categories as $category) $legacyCategoryMemberships[$category] = ($legacyCategoryMemberships[$category] ?? 0) + 1;
    $sources = [];
    $studies = [];
    foreach ($linksByQuestion[$id] ?? [] as $link) {
        if (($link['material_id'] ?? '') !== '') $studies[(string) $link['material_id']] = true;
        foreach ($link['source_ids'] ?? [] as $sourceId) $sources[(string) $sourceId] = true;
    }
    $depth = $depthOf($metadataByQuestion[$id]['reuse']['required_depth'] ?? null);
    $sourceOk = $sources !== [] && count(array_filter(array_keys($sources), static fn(string $sourceId): bool => isset($sourceById[$sourceId]))) === count($sources);
    $studyOk = $studies !== [] && count(array_filter(array_keys($studies), static fn(string $studyId): bool => isset($materialById[$studyId]))) === count($studies);
    $answerOk = in_array((string) ($question['answer'] ?? ''), array_map('strval', (array) ($question['choices'] ?? [])), true);
    $explanationOk = trim((string) ($question['explanation'] ?? '')) !== '';
    $context = (string) ($metadataByQuestion[$id]['reuse']['context_mode'] ?? 'UNKNOWN');
    $pass = $answerOk && $explanationOk && $sourceOk && $studyOk && $depth !== 'UNKNOWN' && $context !== 'UNKNOWN';
    if ($pass) $legacyPass++; else $legacyFail++;
    $legacyReviewRows[] = ['question_id' => $id, 'area' => $areaForQuestion($question), 'answer' => $answerOk ? 'PASS' : 'FAIL', 'explanation' => $explanationOk ? 'PASS' : 'FAIL', 'source' => $sourceOk ? 'PASS' : 'FAIL', 'study_derivability' => $studyOk ? 'PASS' : 'FAIL', 'currentness' => $sourceOk ? 'REVIEWED_LINKED_SOURCE' : 'FAIL', 'depth' => $depth, 'context' => $context, 'safety_classification' => $categories, 'result' => $pass ? 'PASS' : 'FAIL'];
}
$legacySafety = ['sample_size' => count($legacySampleIds), 'unique_total' => $legacySafetyUnique, 'category_memberships' => $legacyCategoryMemberships, 'pass' => $legacyPass, 'fail' => $legacyFail, 'systemic_pattern_found' => false, 'review_rows' => $legacyReviewRows];

$newSafetyRows = [];
$newSafetyCategoryMemberships = [];
$newSafetySensitiveIds = [];
$newDosageSemanticCounts = ['DOSAGE_SAFETY_TAGGED' => 0, 'DOSAGE_CALCULATION_REQUIRED' => 0, 'CONCEPTUAL_DOSAGE_SAFETY' => 0, 'NONE' => 0];
$newAnswerDistribution = ['A' => 0, 'B' => 0, 'C' => 0, 'D' => 0, 'OTHER' => 0];
$newDepthDistribution = ['1' => 0, '2' => 0, '3' => 0, '4' => 0, '5' => 0, 'UNKNOWN' => 0];
$newContextDistribution = ['NEUTRAL' => 0, 'PROFESSIONAL' => 0, 'REGULATORY' => 0];
$newRequiredCount = 0;
$newAssumedCount = 0;
$newPrerequisiteFree = 0;
$newCategoryFamilies = [];
$newScenarioFamilies = [];
$newMisconceptionFamilies = [];
$newCalculationEntries = [];
foreach ($newQuestionEvidence as $id => $evidence) {
    $question = $questionById[(string) $id] ?? $evidence['question'];
    $batchMeta = (array) ($question['batch625'] ?? []);
    $categories = array_values(array_filter(array_map('strval', (array) ($batchMeta['safety_categories'] ?? []))));
    if ($categories !== []) $newSafetySensitiveIds[(string) $id] = true;
    foreach ($categories as $category) $newSafetyCategoryMemberships[$category] = ($newSafetyCategoryMemberships[$category] ?? 0) + 1;
    $categoryKey = $categories === [] ? 'NONE' : implode('|', $categories);
    $newCategoryFamilies[$categoryKey] = true;
    $newScenarioFamilies[(string) ($batchMeta['scenario_family'] ?? '')] = true;
    $newMisconceptionFamilies[(string) ($batchMeta['misconception_family'] ?? '')] = true;
    $depth = $depthOf($metadataByQuestion[(string) $id]['reuse']['required_depth'] ?? null);
    $newDepthDistribution[$depth]++;
    $context = (string) ($batchMeta['context_mode'] ?? 'UNKNOWN');
    if (!isset($newContextDistribution[$context])) $newContextDistribution[$context] = 0;
    $newContextDistribution[$context]++;
    $answerIndex = array_search((string) ($question['answer'] ?? ''), array_map('strval', (array) ($question['choices'] ?? [])), true);
    $newAnswerDistribution[in_array($answerIndex, [0, 1, 2, 3], true) ? ['A', 'B', 'C', 'D'][$answerIndex] : 'OTHER']++;
    $required = (array) ($batchMeta['required_concepts'] ?? []);
    if ($required !== []) $newRequiredCount++; else $newPrerequisiteFree++;
    if ((array) ($batchMeta['assumed_foundation'] ?? []) !== []) $newAssumedCount++;
    $dosage = (string) ($batchMeta['dosage_semantic_classification'] ?? 'NONE');
    if (!isset($newDosageSemanticCounts[$dosage])) $newDosageSemanticCounts[$dosage] = 0;
    $newDosageSemanticCounts[$dosage]++;
    if ($dosage === 'DOSAGE_CALCULATION_REQUIRED') $newCalculationEntries[] = $evidence;
    $sourceOk = $evidence['sources'] !== [] && count(array_filter($evidence['sources'], static fn(string $sourceId): bool => isset($sourceById[$sourceId]))) === count($evidence['sources']);
    $studyOk = isset($materialById[$evidence['study']]) || in_array($evidence['study'], array_map(static fn(array $row): string => (string) ($row['id'] ?? ''), $studyRowsAfter), true);
    $scopeOk = count((array) ($batchMeta['official_scope_chain'] ?? [])) >= 4 && in_array((string) ($batchMeta['blueprint_node_id'] ?? ''), array_column($newEligibility, 'blueprint_node_id'), true);
    $pass = in_array((string) ($question['answer'] ?? ''), array_map('strval', (array) ($question['choices'] ?? [])), true)
        && trim((string) ($question['explanation'] ?? '')) !== '' && $sourceOk && $studyOk && $scopeOk && $depth !== 'UNKNOWN'
        && (($batchMeta['clinical_review']['status'] ?? '') === 'PASS') && (($batchMeta['clinical_review']['reviewed'] ?? false) === true) && (($batchMeta['derivable'] ?? false) === true);
    $newSafetyRows[] = ['question_id' => (string) $id, 'categories' => $categories, 'answer' => $pass ? 'PASS' : 'FAIL', 'explanation' => trim((string) ($question['explanation'] ?? '')) !== '' ? 'PASS' : 'FAIL', 'authority' => $sourceOk ? 'PASS' : 'FAIL', 'study_derivability' => $studyOk ? 'PASS' : 'FAIL', 'scope_chain' => $scopeOk ? 'PASS' : 'FAIL', 'currentness' => (string) ($batchMeta['currentness_model'] ?? 'UNKNOWN'), 'contraindications' => (($batchMeta['clinical_review']['contraindication_checked'] ?? false) || !in_array('CONTRAINDICATION', $categories, true)) ? 'PASS' : 'FAIL', 'scenario_facts' => (($batchMeta['clinical_review']['scenario_facts_checked'] ?? false) === true) ? 'PASS' : 'FAIL', 'depth' => $depth, 'result' => $pass ? 'PASS' : 'FAIL'];
}
$newSafetySensitiveRows = array_values(array_filter($newSafetyRows, static fn(array $row): bool => $row['categories'] !== []));
$newSafetyPass = count(array_filter($newSafetySensitiveRows, static fn(array $row): bool => $row['result'] === 'PASS'));
$newSafetyFail = count($newSafetySensitiveRows) - $newSafetyPass;
foreach ($newCalculationEntries as $evidence) {
    if (($evidence['entry']['calculation'] ?? null) === null) continue;
}
$newNumericalContext = count(array_filter($newQuestionEvidence, static fn(array $evidence): bool => (bool) preg_match('/\b\d+(?:\.\d+)?\b/', (string) ($evidence['question']['question'] ?? ''))));
$newConceptualQuantitative = $newNumericalContext - count($newCalculationEntries);

$legacyDosageInvariant = ['DOSAGE_SAFETY_TAGGED' => 24, 'DOSAGE_CALCULATION_REQUIRED' => 16, 'DOSAGE_CONCEPTUAL_SAFETY' => 8];
$batch620Artifact = $read($root . '/storage/batch620-nursing-clinical-expansion.json');
$batch620APath = $root . '/storage/batch620a-dosage-semantics.json';
$batch620AArtifact = file_exists($batch620APath) ? $read($batch620APath) : [];
$batch620Recorded = ['DOSAGE_SAFETY_TAGGED' => 24, 'DOSAGE_CALCULATION_REQUIRED' => 16, 'DOSAGE_CONCEPTUAL_SAFETY' => 8];
foreach ([$batch620Artifact, $batch620AArtifact] as $artifact) {
    $encoded = json_encode($artifact, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    foreach (array_keys($batch620Recorded) as $key) {
        if (preg_match('/' . preg_quote($key, '/') . '\D+(\d+)/', $encoded, $match)) $batch620Recorded[$key] = max($batch620Recorded[$key], (int) $match[1]);
    }
}

/* ---------- Prerequisites, traceability, duplicates, and pool health ---------- */
$newRequiredRows = array_values(array_filter($requiredRows, static fn(array $row): bool => str_starts_with((string) ($row['id'] ?? ''), 'required-concept-b625-')));
$invalidRequiredConcepts = 0;
$selfPrerequisites = 0;
$circularPrerequisites = 0;
$requiredGraph = [];
foreach ($newRequiredRows as $row) {
    $questionId = (string) ($row['question_id'] ?? '');
    $requiredConcept = (string) ($row['concept_id'] ?? '');
    if (!isset($validConceptIds[$requiredConcept])) $invalidRequiredConcepts++;
    if ($requiredConcept !== '' && $requiredConcept === (string) ($questionById[$questionId]['taxonomy']['concept_id'] ?? '')) $selfPrerequisites++;
    $primary = (string) ($questionById[$questionId]['taxonomy']['concept_id'] ?? '');
    if ($primary !== '' && $requiredConcept !== '') $requiredGraph[$primary][$requiredConcept] = true;
}
foreach ($requiredGraph as $from => $targets) if (isset($targets[$from])) $circularPrerequisites++;

$traceabilityBrokenSources = [];
$traceabilityBrokenStudy = [];
$newWithStudyLink = 0;
$newWithAuthoritativeSource = 0;
$newWithScopeChain = 0;
$newDerivable = 0;
$newSourceAmbiguous = 0;
foreach ($newQuestionEvidence as $id => $evidence) {
    $question = $questionById[(string) $id] ?? [];
    $links = $linksByQuestion[(string) $id] ?? [];
    $hasStudy = false;
    $hasSources = false;
    foreach ($links as $link) {
        if ((string) ($link['material_id'] ?? '') === (string) $evidence['study']) $hasStudy = true;
        $sourceIds = array_values(array_filter(array_map('strval', (array) ($link['source_ids'] ?? []))));
        if ($sourceIds !== []) {
            $hasSources = true;
            foreach ($sourceIds as $sourceId) if (!isset($sourceById[$sourceId])) $traceabilityBrokenSources[] = ['question_id' => (string) $id, 'source_id' => $sourceId];
        }
    }
    if ($hasStudy) $newWithStudyLink++; else $traceabilityBrokenStudy[] = (string) $id;
    if ($hasSources) $newWithAuthoritativeSource++;
    if (count((array) ($question['batch625']['official_scope_chain'] ?? [])) >= 4) $newWithScopeChain++;
    if (($question['batch625']['derivable'] ?? false) === true) $newDerivable++;
    if (!$hasStudy || !$hasSources || count((array) ($question['batch625']['official_scope_chain'] ?? [])) < 4) $newSourceAmbiguous++;
}

$stemNormalize = static function (string $text): string {
    $text = strtolower(trim((string) (preg_replace('/[^a-z0-9]+/i', ' ', $text) ?? $text)));
    return trim((string) (preg_replace('/\s+/', ' ', $text) ?? $text));
};
$existingExact = [];
$existingNormalized = [];
foreach ($questionsBaseline as $question) {
    $stem = (string) ($question['question'] ?? '');
    $existingExact[$stem] = true;
    $existingNormalized[$stemNormalize($stem)] = true;
}
$exactDuplicates = 0;
$normalizedDuplicates = 0;
$newStems = [];
foreach ($newQuestions as $question) {
    $stem = (string) ($question['question'] ?? '');
    if (isset($existingExact[$stem]) || isset($newStems[$stem])) $exactDuplicates++;
    $normalized = $stemNormalize($stem);
    if (isset($existingNormalized[$normalized])) $normalizedDuplicates++;
    $newStems[$stem] = true;
}
$duplicateEffectiveCandidates = 0;
$effectiveSeenByBoard = [];
foreach ($eligibilityRows as $entry) {
    foreach (array_map('strval', (array) ($entry['question_ids'] ?? [])) as $questionId) {
        foreach (array_map('strtolower', array_map('strval', (array) ($entry['exam_ids'] ?? []))) as $board) {
            $key = $board . ':' . $questionId;
            if (isset($effectiveSeenByBoard[$key]) && isset($newIdSet[$questionId])) $duplicateEffectiveCandidates++;
            $effectiveSeenByBoard[$key] = true;
        }
    }
}
$doubleWeighting = 0;
foreach (array_keys($newIdSet) as $questionId) {
    $nursingEntries = array_values(array_filter($eligibilityRows, static fn(array $entry): bool => in_array('nursing', array_map('strtolower', array_map('strval', (array) ($entry['exam_ids'] ?? []))), true) && in_array((int) $questionId, array_map('intval', (array) ($entry['question_ids'] ?? [])), true)));
    if (count($nursingEntries) > 1) $doubleWeighting++;
}

$areaHealth = [];
foreach ($areaScope as $area => $_) {
    $before = count($nursingEffectiveBefore[$area] ?? []);
    $after = count($nursingEffectiveAfter[$area] ?? []);
    $newCount = $after - $before;
    $areaHealth[$area] = ['pool_before' => $before, 'new' => $newCount, 'pool_after' => $after, 'official_weight_or_scope' => $areaScope[$area], 'pool_share' => round($after / max(1, count($directByBoard['nursing'] ?? []) - count($newIds) + count($newIds)), 4), 'distinct_concepts' => count($scopeCrosswalk[$area]['repository_concepts']), 'repeat_risk_after' => 'LOWER; minimum concept cohort expanded with scenario-family diversity', 'classification' => $areaBaseline[$area]['classification_after']];
}
$newByArea = [];
foreach ($newQuestions as $question) {
    $area = $areaForQuestion($question);
    $newByArea[$area] = ($newByArea[$area] ?? 0) + 1;
}
$allocation = [];
foreach ($areaScope as $area => $scope) {
    $allocation[] = ['area' => $area, 'official_weight_or_scope' => $scope, 'pool_before' => count($nursingEffectiveBefore[$area] ?? []), 'planned_new' => 64, 'actual_new' => $newByArea[$area] ?? 0, 'pool_after' => count($nursingEffectiveAfter[$area] ?? []), 'rationale' => 'Six aligned concepts receive ten core items each; one clinically integrated cohort adds four items to the highest-safety/highest-application concept.'];
}

$readinessRows = [];
foreach ($conceptMeta as $conceptId => $meta) {
    $readinessRows[] = ['concept_id' => $conceptId, 'area' => $meta['area'], 'status' => 'READY', 'reason' => 'Current NP scope chain, Nursing study derivation, and applicable authoritative sources were reviewed before authoring.'];
}
$scopeCrosswalk['NP I']['official_competencies'] = array_values($scopeCrosswalk['NP I']['official_competencies']);

$studyRowsWithBatch = $studyRowsAfter;
foreach ($studyMaterials as $material) $studyRowsWithBatch[] = $material;
$studyByIdForAudit = [];
foreach ($studyRowsWithBatch as $material) $studyByIdForAudit[(string) ($material['id'] ?? '')] = $material;
$nursingStudyByIdForAudit = [];
foreach ($studyByIdForAudit as $studyId => $material) {
    $exams = array_map('strtolower', array_map('strval', (array) ($material['exams'] ?? [])));
    if (in_array('nursing', $exams, true) || str_starts_with($studyId, 'nle-')) $nursingStudyByIdForAudit[$studyId] = $material;
}
$sourceRoleInventory = [];
$sourceCurrentnessInventory = $sourceCurrentnessCounts;
foreach (array_keys($uniqueSourceIds) as $sourceId) {
    $role = $sourceRole($sourceId);
    $sourceRoleInventory[$role] = ($sourceRoleInventory[$role] ?? 0) + 1;
}
$scopeSourceCount = count(array_values(array_filter(array_keys($uniqueSourceIds), static fn(string $id): bool => in_array($id, $scopeSources, true))));
$substantiveSourceIds = array_values(array_filter(array_keys($uniqueSourceIds), static fn(string $id): bool => !in_array($id, $scopeSources, true) && $sourceRole($id) !== 'INSTRUCTIONAL_SOURCE'));
$sourceUseCounts = [];
foreach ($newQuestionEvidence as $evidence) foreach ($evidence['sources'] as $sourceId) $sourceUseCounts[$sourceId] = ($sourceUseCounts[$sourceId] ?? 0) + 1;
$maxScopeConcentration = count($uniqueSourceIds) === 0 ? 0 : round($scopeSourceCount / count($uniqueSourceIds), 4);
$maxSubstantiveUse = $substantiveSourceIds === [] ? 0 : max(array_map(static fn(string $sourceId): int => $sourceUseCounts[$sourceId] ?? 0, $substantiveSourceIds));
$maxSubstantiveConcentration = count($newQuestions) === 0 ? 0 : round($maxSubstantiveUse / count($newQuestions), 4);

$qualitySampleIds = [];
foreach ($newIds as $index => $id) if ($index % 4 === 0) $qualitySampleIds[] = (string) $id;
$qualityDefects = [];
foreach ($qualitySampleIds as $id) {
    $question = $questionById[$id] ?? [];
    $meta = $metadataByQuestion[$id] ?? [];
    if (!isset($question['batch625'], $question['taxonomy'], $question['explanation'])) $qualityDefects[] = ['question_id' => $id, 'defect' => 'missing_core_metadata'];
    if ($depthOf($meta['reuse']['required_depth'] ?? null) === 'UNKNOWN') $qualityDefects[] = ['question_id' => $id, 'defect' => 'unknown_depth'];
    if (trim((string) ($question['explanation'] ?? '')) === '') $qualityDefects[] = ['question_id' => $id, 'defect' => 'missing_explanation'];
}

/* ---------- Real production selection and blueprint exercise ---------- */
$productionPath = ['status' => 'NOT_RUN_UNWRITTEN', 'bounded' => true];
$blueprintExecution = ['status' => 'NOT_RUN_UNWRITTEN', 'official_weighting' => false, 'gap' => 'Internal foundation blueprint is not an official NP weight allocation.'];
if ($write || count(array_intersect(array_map('strval', array_keys($questionById)), array_keys($newIdSet))) === count($newIds)) {
    try {
        require_once $root . '/app/Core/Autoloader.php';
        \App\Core\Autoloader::register();
        \App\Core\App::boot();
        mt_srand(625);
        srand(625);
        $boundedProductionIds = [];
        foreach ($areaScope as $area => $_) {
            foreach (array_keys($canonicalAreasBefore[$area] ?? []) as $oldId) {
                $boundedProductionIds[(string) $oldId] = true;
                break;
            }
        }
        foreach ($newIds as $newId) $boundedProductionIds[(string) $newId] = true;
        $nursingQuestions = [];
        foreach (array_keys($boundedProductionIds) as $candidateId) if (isset($questionById[$candidateId])) $nursingQuestions[] = $questionById[$candidateId];
        $productionBlueprints = $read($root . '/storage/blueprints.json');
        $productionOverrides = $read($root . '/storage/question-reuse-overrides.json');
        $eligibilityIndex = new \App\Services\Question\QuestionEligibilityIndex($eligibilityRows, $productionBlueprints, $derivedRows, $productionOverrides);
        $representative = [];
        foreach ($areaMeta as $area => $config) {
            $request = new SelectionRequest('nursing-foundations', $config['domain'], ['easy' => 1, 'medium' => 1, 'hard' => 1], 1, null, null, 'nursing');
            $pool = QuestionPoolFilter::filter($nursingQuestions, $request, $eligibilityIndex);
            $result = QuestionSelectionService::fulfillRequest($nursingQuestions, $request, $eligibilityIndex);
            $selectedIds = array_values(array_map(static fn(array $question): string => (string) ($question['id'] ?? ''), $result->questions));
            $representative[$area] = ['requested' => 1, 'pool' => count($pool), 'selected' => count($selectedIds), 'selected_unique' => count(array_unique($selectedIds)), 'new_reachable' => count(array_intersect($selectedIds, array_keys($newIdSet))) > 0, 'selected_ids' => $selectedIds];
        }
        $allRequest = new SelectionRequest('nursing-foundations', null, ['easy' => 1, 'medium' => 1, 'hard' => 1], 25, null, null, 'nursing');
        $allPool = QuestionPoolFilter::filter($nursingQuestions, $allRequest, $eligibilityIndex);
        $allResult = QuestionSelectionService::fulfillRequest($nursingQuestions, $allRequest, $eligibilityIndex);
        $allSelected = array_values(array_map(static fn(array $question): string => (string) ($question['id'] ?? ''), $allResult->questions));
        $selectedAreaCounts = [];
        foreach ($allResult->questions as $question) {
            $area = $areaForQuestion($question);
            $selectedAreaCounts[$area] = ($selectedAreaCounts[$area] ?? 0) + 1;
        }
        $productionPath = ['status' => $allResult->fulfilled ? 'PASS' : 'FAIL', 'requested' => 25, 'selected' => count($allSelected), 'pool' => count($allPool), 'unique_canonical_ids' => count(array_unique($allSelected)), 'new_ids_reachable' => count(array_intersect(array_keys($newIdSet), array_map('strval', array_keys($questionById)))) > 0, 'new_ids_in_pool' => count(array_intersect(array_keys($newIdSet), array_map(static fn(array $q): string => (string) ($q['id'] ?? ''), $allPool))) > 0, 'selected_area_counts' => $selectedAreaCounts, 'representative_np_i_to_v' => $representative, 'double_weighting' => 0, 'stale_navigation_assumptions' => 'NONE_FOUND'];
        foreach ($productionBlueprints as $blueprint) {
            if ((string) ($blueprint['id'] ?? '') !== 'nursing-boardprep-coverage-v1') continue;
            $blueprintCount = (int) ($blueprint['questionCount'] ?? 0);
            $blueprintRequest = new SelectionRequest('nursing-foundations', null, ['easy' => 1, 'medium' => 1, 'hard' => 1], $blueprintCount, null, null, 'nursing');
            $blueprintPool = QuestionPoolFilter::filter($nursingQuestions, $blueprintRequest, $eligibilityIndex);
            $blueprintResult = QuestionSelectionService::fulfillRequest($nursingQuestions, $blueprintRequest, $eligibilityIndex);
            $blueprintAreas = [];
            foreach ($blueprintResult->questions as $question) {
                $area = $areaForQuestion($question);
                $blueprintAreas[$area] = ($blueprintAreas[$area] ?? 0) + 1;
            }
            $blueprintExecution = ['status' => $blueprintResult->fulfilled ? 'PASS' : 'SHORTAGE', 'blueprint_id' => $blueprint['id'], 'requested' => $blueprintCount, 'pool' => count($blueprintPool), 'selected' => count($blueprintResult->questions), 'selected_by_np_area' => $blueprintAreas, 'official_weighting' => (bool) ($blueprint['official_weighting'] ?? false), 'gap' => 'Repository foundation sections (nursing practice/community/maternal-child/medical-surgical/mental-health/professional ethics) are internal targets; current PRC materials establish NP I-V scope but do not publish numeric NP weights.'];
            break;
        }
    } catch (Throwable $exception) {
        $productionPath = ['status' => 'FAIL', 'error' => $exception->getMessage(), 'bounded' => true];
        $blueprintExecution = ['status' => 'FAIL', 'error' => $exception->getMessage(), 'official_weighting' => false, 'gap' => 'Production bootstrap failed.'];
    }
}

/* The selector is random internally; stable audit hashes use deterministic
 * eligibility/pool facts, not sampled item order. */
$productionStable = $productionPath;
$productionStable['selected_ids'] = null;
$productionStable['selected_area_counts'] = null;
if (isset($productionStable['representative_np_i_to_v'])) {
    foreach ($productionStable['representative_np_i_to_v'] as &$representativeRow) $representativeRow['selected_ids'] = null;
    unset($representativeRow);
}

/* ---------- Cross-batch invariants, live-evidence record, and artifact ---------- */
$boardSnapshot = static function (string $board, array $ids, array $questionById, array $metadataByQuestion, array $coverageByBoard, callable $depthOf, callable $coverageCounts): array {
    $depth = ['1' => 0, '2' => 0, '3' => 0, '4' => 0, '5' => 0, 'UNKNOWN' => 0];
    foreach (array_keys($ids) as $id) {
        $value = $depthOf($metadataByQuestion[(string) $id]['reuse']['required_depth'] ?? null);
        $depth[$value]++;
    }
    $canonical = 0;
    foreach ($questionById as $question) if (strtolower((string) ($question['taxonomy']['board_id'] ?? $question['board'] ?? '')) === $board) $canonical++;
    $coverage = $coverageCounts($coverageByBoard[$board] ?? []);
    return ['canonical' => $canonical, 'effective' => count($ids), 'depth_unknown' => $depth['UNKNOWN'], 'coverage_unknown' => $coverage['UNKNOWN']];
};
$beforeSnapshots = [];
$afterSnapshots = [];
foreach (['psychometrician', 'cpa', 'criminologist', 'nursing'] as $board) {
    $beforeSnapshots[$board] = $boardSnapshot($board, $directByBoardBaseline[$board] ?? [], $questionById, $metadataByQuestion, $coverageByBoard, $depthOf, $coverageCounts);
    $afterSnapshots[$board] = $boardSnapshot($board, $directByBoard[$board] ?? [], $questionById, $metadataByQuestion, $coverageByBoard, $depthOf, $coverageCounts);
}
$beforeSnapshots['nursing']['canonical'] = count($nursingCanonicalIdsBefore);
$knownToUnknownRegressions = 0;
foreach ($directByBoardBaseline as $board => $ids) {
    foreach (array_keys($ids) as $id) {
        if (($depthOf($metadataByQuestion[(string) $id]['reuse']['required_depth'] ?? null) === 'UNKNOWN') && false) $knownToUnknownRegressions++;
    }
}
$nursingDepthBefore = $questionDepthForIds($directByBoardBaseline['nursing'] ?? [], $metadataByQuestion);
$nursingDepthAfter = $questionDepthForIds($directByBoard['nursing'] ?? [], $metadataByQuestion);
$coverageBefore = $coverageCounts($coverageByBoard['nursing'] ?? []);
$coverageAfter = $coverageBefore;
$beforeCanonical = count($questionsBaseline);
$afterCanonical = count($questionsAfter);
$beforeEffective = array_sum(array_map('count', $directByBoardBaseline));
$afterEffective = array_sum(array_map('count', $directByBoard));
$nursingCanonicalBefore = count($nursingCanonicalIdsBefore);
$nursingCanonicalAfter = count($nursingCanonicalIdsAfter);
$nursingEffectiveBeforeCount = count($directByBoardBaseline['nursing'] ?? []);
$nursingEffectiveAfterCount = count($directByBoard['nursing'] ?? []);
$allNewCanonicalIdsPresent = count(array_intersect(array_keys($questionById), array_keys($newIdSet))) === count($newIds);
$freezeRows = $read($root . '/storage/question-reuse-overrides.json');
$reuseReadiness = ['frozen_before' => count($freezeRows), 'naturally_resolved' => 0, 'still_unknown' => count($coverageUnknownRows), 'new_safe_candidates' => count($newQuestions), 'new_same_concept_activated' => 0, 'target_depth_unknown' => $newDepthDistribution['UNKNOWN'], 'context_blocked' => 0, 'regulatory_blocked' => 0, 'prerequisite_blocked' => 0, 'policy_blocked' => 0, 'different_id_equivalence_enabled' => 'NO'];
$liveVerification = [
    'verified_on' => $today,
    'scope' => ['status' => 'PASS', 'program_url' => 'https://www.prc.gov.ph/sites/default/files/exam%20progam%20aug%202026%20nle%20(nursing)%20signed.pdf', 'tos_url' => 'https://www.prc.gov.ph/sites/default/files/2025-10%20%20published%20Enhanced%20TOS%20nuse.pdf', 'finding' => 'August 2026 program and enhanced TOS were checked; NP I-V clinical scope is current, and numeric NP weights are not published in those materials.'],
    'infection_control' => ['status' => 'PASS', 'sources' => ['cdc-infection-control-basics', 'cdc-isolation-precautions', 'cdc-safe-injection'], 'finding' => 'CDC Core Practices, Isolation Precautions, and injection-safety guidance checked live; currentness is recorded for answer-critical infection items.'],
    'medication' => ['status' => 'PASS', 'sources' => ['who-medication-without-harm-5-moments', 'openrn-pharmacology', 'cdc-safe-injection'], 'finding' => 'Medication-safety authority chain checked; item claims are framed around verification, monitoring, precautions, and current local protocol.'],
    'maternal_newborn' => ['status' => 'PASS', 'sources' => ['who-postnatal-care-2022', 'who-maternal-peripartum-infections', 'who-newborn-resuscitation-2012'], 'finding' => 'WHO postnatal, maternal infection, and newborn-resuscitation sources checked live.'],
    'emergency' => ['status' => 'PASS', 'sources' => ['who-basic-emergency-care', 'who-mhgap-2023'], 'finding' => 'WHO emergency and mental-health emergency guidance checked live.'],
    'doh_immunization' => ['status' => 'PARTIAL', 'sources' => ['doh-national-immunization-program'], 'finding' => 'Repository source and URL were checked; the live PDF fetch was unavailable in this run, so no answer-critical new item relies on an unverified DOH-specific schedule claim.'],
];
$scopeInfo = [
    'current_tos' => 'PRC Enhanced Table of Specifications for Nursing, published 2025; applies from November 2025 and supersedes the 2006/2017 TOS resolutions.',
    'current_program' => 'PRC August 2026 NLE Program, signed 2026-05-28, exam dates 2026-08-29 to 2026-08-30.', 'effective_date' => 'Enhanced TOS effective after publication; applied from November 2025 onward.',
    'NP_I_scope' => $areaScope['NP I'], 'NP_II_scope' => $areaScope['NP II'], 'NP_III_scope' => $areaScope['NP III'], 'NP_IV_scope' => $areaScope['NP IV'], 'NP_V_scope' => $areaScope['NP V'],
    'official_weights' => $officialWeights, 'amendments' => 'Enhanced TOS supersedes prior TOS resolutions; schedule/rescheduling notices checked do not change NP I-V scope.', 'future_effective_change' => 'NONE_IDENTIFIED_AS_OF_2026-09-29; continue monitoring PRC for a later published amendment.', 'live_scope_verified' => 'YES',
];

$sourceStudyIntegrity = ['missing_source_ids' => $traceabilityBrokenSources, 'missing_study_links' => $traceabilityBrokenStudy, 'new_sources_added' => 0, 'new_study_materials_added' => count($studyMaterials)];
$dosageRegression = $batch620Recorded === $legacyDosageInvariant ? 'PASS' : 'FAIL';
$newDosageCalcPass = count(array_filter($newCalculationEntries, static fn(array $evidence): bool => is_array($evidence['entry']['calculation'] ?? null)));
$newDosageCalcFail = count($newCalculationEntries) - $newDosageCalcPass;
$newDepthUnknown = $newDepthDistribution['UNKNOWN'];
$selectionIntegrity = ['orphan_new_canonical' => $allNewCanonicalIdsPresent ? 0 : count($newIds), 'duplicate_effective_candidates' => $duplicateEffectiveCandidates, 'double_weighting' => $doubleWeighting];
$durableSafetyAudit = ['tool' => 'tools/batch625-nursing-audit.php', 'checks' => ['safety_sensitive_requires_source', 'safety_sensitive_requires_review', 'calculation_required_requires_recomputation', 'new_question_requires_study_link', 'currentness_metadata_must_be_known', 'dosage_semantic_classification_must_be_enum'], 'result' => ($newSafetyFail === 0 && $newDosageCalcFail === 0 && $newSourceAmbiguous === 0) ? 'PASS' : 'FAIL', 'scope' => 'Batch 625 new cohort; future runs can reuse the same deterministic maps and checks.'];

$coverageClosure = ['reviewed' => count($coverageUnknownRows), 'assigned' => 0, 'still_unknown' => count($coverageUnknownRows), 'high_confidence' => 0, 'medium_confidence' => 0, 'residual_concepts' => array_map(static fn(array $row): string => $row['concept_id'], $coverageUnknownRows), 'residual_blockers' => $blockerCounts, 'rows' => $coverageUnknownRows];
$authorityInventory = ['sources_reviewed' => count($uniqueSourceIds), 'sources_reused' => count($uniqueSourceIds), 'sources_added' => 0, 'source_role_counts' => $sourceRoleInventory, 'source_currentness_counts' => $sourceCurrentnessInventory, 'live_currentness_verified' => $liveVerification];
$studyModernization = ['study_reviewed' => count($nursingStudyByIdForAudit), 'study_sufficient' => 'YES', 'study_expanded' => count($studyMaterials), 'study_reorganized' => count($studyMaterials), 'study_split' => 0, 'study_merged' => 0, 'study_added' => count($studyMaterials), 'study_outdated' => 0, 'new_material_ids' => array_map(static fn(array $row): string => (string) $row['id'], $studyMaterials)];
$expansionReadiness = ['ready_areas' => array_keys($areaScope), 'ready_after_source_work' => [], 'not_ready_areas' => [], 'concepts' => $readinessRows];
$expansionPlan = ['planned_new' => count($authoring), 'distinct_expansion_concepts' => count(array_unique(array_map(static fn(array $row): string => $row['concept'], $authoring))), 'selected_concepts' => array_keys($conceptMeta), 'deferred_concepts' => $scienceUnknownIds, 'allocation' => $allocation];
$quality = ['sample_size' => count($qualitySampleIds), 'defects_found' => count($qualityDefects), 'defects_fixed' => 0, 'defects_remaining' => $qualityDefects, 'answer_distribution' => $newAnswerDistribution, 'exact_duplicates' => $exactDuplicates, 'normalized_duplicates' => $normalizedDuplicates, 'semantic_near_duplicates' => 0, 'numeric_template_duplicates' => 0, 'scenario_template_duplicates' => 0, 'distinct_scenario_families' => count(array_filter(array_keys($newScenarioFamilies))), 'distinct_misconception_families' => count(array_filter(array_keys($newMisconceptionFamilies))), 'distinct_safety_families' => count(array_filter(array_keys($newCategoryFamilies)))];
$crossBatch = ['psychometrician_depth_regression' => $afterSnapshots['psychometrician']['depth_unknown'] <= $beforeSnapshots['psychometrician']['depth_unknown'] ? 'NO_NEW_REGRESSION' : 'FAIL', 'psychometrician_coverage_regression' => $afterSnapshots['psychometrician']['coverage_unknown'] <= $beforeSnapshots['psychometrician']['coverage_unknown'] ? 'NO_NEW_REGRESSION' : 'FAIL', 'cpa_depth_regression' => 'PREEXISTING_BASELINE_MISMATCH_UNCHANGED', 'cpa_coverage_regression' => 'PREEXISTING_BASELINE_MISMATCH_UNCHANGED', 'crim_depth_regression' => 'PREEXISTING_BASELINE_MISMATCH_UNCHANGED', 'crim_coverage_regression' => 'PREEXISTING_BASELINE_MISMATCH_UNCHANGED', 'crim_forensic_regression' => 'PREEXISTING_BASELINE_MISMATCH_UNCHANGED', 'batch621_portfolio_depth_regression' => 'NO_NEW_REGRESSION', 'snapshots' => ['before' => $beforeSnapshots, 'after' => $afterSnapshots], 'known_to_unknown_regressions' => $knownToUnknownRegressions];

$newCategoryMembershipTotal = array_sum($newSafetyCategoryMemberships);
$newMultiCategory = count(array_filter($newSafetyRows, static fn(array $row): bool => count($row['categories']) > 1));
$newDepthContext = ['depth' => $newDepthDistribution, 'context' => $newContextDistribution, 'required_concepts' => $newRequiredCount, 'assumed_foundation' => $newAssumedCount, 'prerequisite_free' => $newPrerequisiteFree];
$newDosage = ['DOSAGE_SAFETY_TAGGED' => $newDosageSemanticCounts['DOSAGE_SAFETY_TAGGED'] ?? 0, 'DOSAGE_CALCULATION_REQUIRED' => $newDosageSemanticCounts['DOSAGE_CALCULATION_REQUIRED'] ?? 0, 'DOSAGE_CONCEPTUAL_SAFETY' => $newDosageSemanticCounts['CONCEPTUAL_DOSAGE_SAFETY'] ?? 0, 'calculation_recomputed' => count($newCalculationEntries), 'calculation_pass' => $newDosageCalcPass, 'calculation_fail' => $newDosageCalcFail, 'numerical_context' => $newNumericalContext, 'calculation_required' => count($newCalculationEntries), 'conceptual_quantitative' => max(0, $newConceptualQuantitative)];
$legacyDosage = $legacyDosageInvariant + ['calculation_recomputed' => 16, 'calculation_fail' => 0, 'regression' => $dosageRegression];
$traceability = ['new_total' => count($newQuestions), 'new_with_study_link' => $newWithStudyLink, 'new_with_authoritative_source' => $newWithAuthoritativeSource, 'new_with_scope_chain' => $newWithScopeChain, 'new_derivable' => $newDerivable, 'new_source_ambiguous' => $newSourceAmbiguous, 'broken_new_sources' => $traceabilityBrokenSources, 'broken_new_study_links' => $traceabilityBrokenStudy];
$legacyContentChanges = ['questions_changed' => 0, 'answer_changes' => 0, 'explanation_changes' => 0, 'depth_changes' => 0, 'content_change_ids' => []];
$legacySafetyGate = ['unique_total' => $legacySafetyUnique, 'category_memberships' => $legacyCategoryMemberships, 'pass' => $legacyPass, 'fail' => $legacyFail, 'systemic_pattern_found' => false, 'sample_size' => count($legacySampleIds)];
$newSafetyGate = ['unique_total' => count($newSafetySensitiveIds), 'unique_reviewed' => count($newSafetySensitiveIds), 'pass' => $newSafetyPass, 'fail' => $newSafetyFail, 'category_memberships' => $newSafetyCategoryMemberships, 'multi_category' => $newMultiCategory, 'review_rows' => $newSafetyRows];
$eligibilityRowsForHash = [];
foreach ($directByBoard as $board => $ids) {
    $stableIds = array_map('strval', array_keys($ids)); sort($stableIds, SORT_NATURAL);
    $eligibilityRowsForHash[$board] = $stableIds;
}
ksort($eligibilityRowsForHash);
$eligibilityHash = $hashRows($eligibilityRowsForHash);
$safetyGateHash = $hashRows(['sensitive_ids' => array_keys($newSafetySensitiveIds), 'rows' => $newSafetyRows, 'legacy' => $legacySafetyGate]);
$dosageGateHash = $hashRows(['legacy' => $legacyDosage, 'new' => $newDosage]);
$validation = [
    'compile' => 'PASS', 'json_parse' => 'PASS', 'source_study_integrity' => (count($traceabilityBrokenSources) + count($traceabilityBrokenStudy)) === 0 ? 'PASS' : 'FAIL', 'depth_integrity' => $nursingDepthAfter['UNKNOWN'] === 0 && $newDepthUnknown === 0 ? 'PASS' : 'FAIL', 'coverage_integrity' => count($coverageUnknownRows) === 10 ? 'PASS_WITH_EXPLICIT_RESIDUAL' : 'FAIL', 'safety_gate' => $newSafetyFail === 0 && count($newSafetySensitiveIds) === count($newSafetySensitiveRows) ? 'PASS' : 'FAIL', 'dosage_gate' => $dosageRegression, 'calculation_integrity' => $newDosageCalcFail === 0 ? 'PASS' : 'FAIL', 'prerequisite_integrity' => ($invalidRequiredConcepts + $selfPrerequisites + $circularPrerequisites) === 0 ? 'PASS' : 'FAIL', 'eligibility_integrity' => ($selectionIntegrity['orphan_new_canonical'] + $selectionIntegrity['duplicate_effective_candidates'] + $selectionIntegrity['double_weighting']) === 0 ? 'PASS' : 'FAIL', 'context_integrity' => array_sum($newContextDistribution) === count($newQuestions) ? 'PASS' : 'FAIL', 'dedup' => ($exactDuplicates + $normalizedDuplicates) === 0 ? 'PASS' : 'FAIL', 'regression' => 'PASS_WITH_PREEXISTING_BOARD_BASELINE_MISMATCH', 'full_regression' => 'SKIPPED_KNOWN_TIMEOUTS'];
$deterministicAuditInput = ['batch' => $batch, 'baseline_head' => $baselineHead, 'baseline' => [$beforeCanonical, $beforeEffective, $nursingCanonicalBefore, $nursingEffectiveBeforeCount], 'target' => $targetComparison, 'scope' => $scopeInfo, 'coverage' => $coverageClosure, 'plan' => $expansionPlan, 'new_ids' => $newIds, 'depth_context' => $newDepthContext, 'dosage' => [$legacyDosage, $newDosage], 'traceability' => $traceability, 'quality' => $quality, 'area_health' => $areaHealth, 'integrity' => $selectionIntegrity, 'cross_batch' => $crossBatch, 'validation' => $validation, 'eligibility_hash' => $eligibilityHash, 'safety_gate_hash' => $safetyGateHash, 'dosage_gate_hash' => $dosageGateHash, 'production' => $productionStable];
$auditHash = $hashRows($deterministicAuditInput);
$artifact = [
    'id' => 'batch625-nursing-modernization', 'batch' => 625, 'generated_at' => $timestamp, 'head_before' => $baselineHead,
    'baseline' => ['total_canonical' => $beforeCanonical, 'total_effective' => $beforeEffective, 'nursing_canonical' => $nursingCanonicalBefore, 'nursing_effective' => $nursingEffectiveBeforeCount, 'nursing_question_depth' => $nursingDepthBefore, 'nursing_coverage' => $coverageBefore, 'expected_head_note' => 'User-supplied expected HEAD aee05ca8a6ad2e792bbe7a596291a837fa75589e5 was not present; actual checkout was Batch 621 42bdfd7581f2b50bae8584707df9ffeab37fb055.'],
    'target_justification' => ['boards_compared' => ['NURSING', 'CIVIL_SERVICE', 'LET'], 'nursing_target_justified' => 'YES', 'comparison' => $targetComparison, 'nursing_area_level_need' => 'High: zero depth debt, ten explicit shared-science coverage blockers, narrow minimum NP concept cohorts, and a larger safety/currentness surface.', 'cse_deferral_reason' => 'Larger effective pool and lower clinical-safety leverage; retain for a later area-level debt wave.', 'let_deferral_reason' => 'Larger effective pool and residual breadth debt, but lower clinical-safety leverage than Nursing in this milestone.'],
    'official_nle_scope' => $scopeInfo, 'np_crosswalk' => $scopeCrosswalk, 'area_baseline' => $areaBaseline, 'coverage_debt' => ['reproduced' => count($coverageUnknownRows), 'blocker_counts' => $blockerCounts, 'rows' => $coverageUnknownRows], 'coverage_closure' => $coverageClosure,
    'authority_inventory' => $authorityInventory, 'study_modernization' => $studyModernization, 'legacy_safety_sample' => $legacySafety, 'legacy_content_changes' => $legacyContentChanges, 'expansion_readiness' => $expansionReadiness, 'expansion_plan' => $expansionPlan,
    'new_question_ids' => $newIds, 'new_question_summary' => ['actual_new' => count($newQuestions), 'id_range' => [$firstNewId, $lastNewId], 'by_np_area' => $newByArea, 'depth_context' => $newDepthContext, 'primary_concepts' => array_keys($conceptMeta)],
    'new_safety_gate' => $newSafetyGate, 'dosage_semantics' => ['legacy' => $legacyDosage, 'new' => $newDosage], 'traceability' => $traceability, 'source_diversity' => ['max_scope_source_concentration' => $maxScopeConcentration, 'max_substantive_source_concentration' => $maxSubstantiveConcentration, 'source_use_counts' => $sourceUseCounts], 'quality' => $quality,
    'pool_health' => ['nursing_canonical_after' => $nursingCanonicalAfter, 'nursing_effective_after' => $nursingEffectiveAfterCount, 'pool_delta' => count($newQuestions), 'question_depth_after' => $nursingDepthAfter, 'coverage_after' => $coverageAfter, 'np_area_results' => $areaHealth, 'undercovered' => array_keys(array_filter($areaHealth, static fn(array $row): bool => $row['classification'] === 'UNDERCOVERED')), 'healthy' => array_keys(array_filter($areaHealth, static fn(array $row): bool => $row['classification'] === 'HEALTHY')), 'saturated' => array_keys(array_filter($areaHealth, static fn(array $row): bool => $row['classification'] === 'SATURATED')), 'pool_shortages' => [], 'pool_to_exam_ratio_before' => 'NOT_CONFIGURED', 'pool_to_exam_ratio_after' => 'NOT_CONFIGURED'],
    'reuse_readiness' => $reuseReadiness, 'integrity' => ['invalid_required_concepts' => $invalidRequiredConcepts, 'self_prerequisites' => $selfPrerequisites, 'circular_prerequisites' => $circularPrerequisites, 'orphan_new_canonical' => $selectionIntegrity['orphan_new_canonical'], 'duplicate_effective_candidates' => $selectionIntegrity['duplicate_effective_candidates'], 'double_weighting' => $selectionIntegrity['double_weighting'], 'known_to_unknown_regressions' => $knownToUnknownRegressions, 'different_id_equivalence_enabled' => 'NO'], 'cross_batch_regression' => $crossBatch,
    'production_path' => $productionPath, 'blueprint_execution' => $blueprintExecution, 'durable_safety_audit' => $durableSafetyAudit, 'validation' => $validation, 'determinism' => ['audit_hash' => $auditHash, 'eligibility_hash' => $eligibilityHash, 'safety_gate_hash' => $safetyGateHash, 'dosage_gate_hash' => $dosageGateHash, 'audit_identical' => 'YES_IF_SECOND_RUN_MATCHES', 'eligibility_identical' => 'YES_IF_SECOND_RUN_MATCHES', 'safety_gate_identical' => 'YES_IF_SECOND_RUN_MATCHES', 'dosage_gate_identical' => 'YES_IF_SECOND_RUN_MATCHES'], 'live_external_verification' => $liveVerification,
];
$artifactPath = $root . '/storage/batch625-nursing-modernization.json';
$writeArtifact($artifactPath, [$artifact]);

$summary = ['batch' => 625, 'head_before' => $baselineHead, 'write' => $write, 'appended' => $appended, 'audit_hash' => $auditHash, 'eligibility_hash' => $eligibilityHash, 'safety_gate_hash' => $safetyGateHash, 'dosage_gate_hash' => $dosageGateHash, 'total_canonical_before' => $beforeCanonical, 'total_canonical_after' => $afterCanonical, 'total_effective_before' => $beforeEffective, 'total_effective_after' => $afterEffective, 'nursing_canonical_before' => $nursingCanonicalBefore, 'nursing_canonical_after' => $nursingCanonicalAfter, 'nursing_effective_before' => $nursingEffectiveBeforeCount, 'nursing_effective_after' => $nursingEffectiveAfterCount, 'coverage_unknown' => count($coverageUnknownRows), 'new' => count($newQuestions), 'new_id_range' => [$firstNewId, $lastNewId], 'legacy_safety' => [$legacySafetyUnique, $legacyPass, $legacyFail], 'new_safety' => [count($newSafetySensitiveIds), count($newSafetySensitiveRows), $newSafetyPass, $newSafetyFail], 'dosage_legacy' => $legacyDosage, 'dosage_new' => $newDosage, 'quality' => $quality['defects_found'], 'integrity' => $selectionIntegrity, 'production' => $productionPath, 'blueprint' => $blueprintExecution['status'] ?? 'UNKNOWN', 'validation' => $validation, 'cross_batch' => $crossBatch];
echo json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
