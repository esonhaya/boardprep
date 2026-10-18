#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();
putenv('APP_ENV=testing');
putenv('APP_DEBUG=false');
\App\Core\App::boot();
$storage = \App\Core\App::storage();
$questions = $storage->all('questions');
$eligibility = \App\Services\Question\QuestionEligibilityService::index();
$lessons = $storage->all('study-lessons');
$materials = $storage->all('study-materials');
$links = $storage->all('study-question-links');
$sources = [];
foreach ($storage->all('sources') as $source) if (is_array($source)) $sources[(string) ($source['id'] ?? '')] = $source;

$specs = [
    'architecture-planning-and-practice' => [
        'lesson_id'=>'architecture-planning-and-practice-decisions',
        'material_id'=>'architecture-planning-and-practice-decisions',
        'title'=>'Architectural Practice, Coordination, and Public Responsibility',
        'pdf'=>'architecture-planning-and-practice-decisions.pdf',
        'scope_sources'=>['prc-architecture-syllabi','ra-9266-architecture-act','ra-9266-architecture-irr'],
        'instruction_sources'=>['pd1096-national-building-code','bp344-2024-irr'],
        'scope_note'=>'The PRC architecture syllabus includes principles of planning and architectural practice. Republic Act 9266 describes the statutory scope of architecture services. This lesson teaches bounded project reasoning and source checking; it is not a legal opinion, current-code determination, or exam prediction.',
        'objectives'=>[
            'Relate a project task to the documented service scope, role, and decision record.',
            'Trace a coordination issue from its source through responsible review and a controlled revision.',
            'Use current official safety and accessibility references when a decision affects the public.',
        ],
        'sections'=>[
            ['id'=>'practice-scope-and-services','heading'=>'1. Practice scope and service responsibilities','body'=>'Architectural practice joins site and planning work, design, documentation, coordination, and construction-related services. Read the engagement as a defined professional service: identify the client, deliverables, responsible signatory, review boundaries, and decisions that require another qualified discipline. Republic Act 9266 is a primary Philippine reference for regulated scope; its text should be checked directly, together with current implementing rules, before a regulatory conclusion is made. In a project case, separate what the architect is responsible for from what a consultant, contractor, approving office, or client controls. A clear service record helps prevent a drawing or verbal instruction from silently expanding the assignment. It does not remove duties imposed by applicable law or professional standards.','related_concept_ids'=>['b633-arch-practice-scope']],
            ['id'=>'project-coordination-and-records','heading'=>'2. Coordinate decisions across project information','body'=>'A coordination problem often appears as conflicting information across drawings, specifications, site observations, and consultant inputs. First identify the exact location and revision of each record. State the discrepancy without assigning cause, identify which discipline or decision-maker owns the next review, and preserve the response in a dated issue log. The resolved direction must be carried into the controlled documents and communicated to affected participants. A meeting note alone is not proof that every drawing was updated. Check that the current set has a common revision status, that superseded information is identifiable, and that unresolved items remain visible. Escalate a conflict that affects safety, compliance, cost, or scope instead of filling the gap with an unsupported assumption.','related_concept_ids'=>['b633-arch-practice-coordination']],
            ['id'=>'public-responsibility-and-access','heading'=>'3. Public responsibility, safety, and access','body'=>'Professional decisions can affect people who did not commission the project: occupants, neighbors, workers, and visitors. Identify who may be excluded or exposed to risk by the proposed arrangement, then consult the current official requirements and responsible specialists. For accessibility, verify the applicable law, rules, building use, route, and project conditions rather than relying on a single remembered dimension. For safety, communicate unresolved hazards and assign a responsible review before work proceeds. A useful decision note records the evidence considered, the people affected, the applicable source checked, the responsible reviewer, and the action taken. An architect can explain design reasoning but should not present an unverified code interpretation as a final determination.','related_concept_ids'=>['b633-arch-practice-public-responsibility']],
            ['id'=>'practice-worked-coordination-case','heading'=>'4. Worked case: a conflicting service route','body'=>'A reflected ceiling plan shows a service route passing through a structural member, while the latest structural sheet shows the member in that location. The project team should preserve both drawing references and revisions, describe the physical conflict, and issue it to the responsible architecture and engineering reviewers. Do not move the route on an uncontrolled sheet or assume that the smaller system can be shifted without checking access and maintenance. Once the authorized design direction is recorded, update the coordinated sheets, identify the superseded revision, and notify the affected site team. The check is complete only when the resolution is visible in the current coordinated set.','related_concept_ids'=>[]],
            ['id'=>'practice-review-and-transfer','heading'=>'5. Review: a defensible project decision','body'=>'Before closing a practice problem, ask: What is the exact issue? Which source and revision establish it? Who has authority and competence to decide? What public or project risk follows from delay? Where will the decision be recorded? Which controlled documents must change? Use these questions to distinguish a traceable professional decision from an informal assumption.','related_concept_ids'=>[]],
        ],
        'worked_examples'=>[
            ['heading'=>'Resolve a document conflict','prompt'=>'An architectural plan and a consultant schedule give different locations for a building service. The site team asks which one to follow.','steps'=>['Record both document names, revision dates, and the exact location.','Send the issue to the responsible design reviewers and identify any safety or access consequence.','Record the approved direction, revise the coordinated set, and notify affected participants.'],'answer'=>'Hold the conflicting work at the affected location until the responsible review is documented, then coordinate the controlled revision.','check'=>'The current drawings and the issue log should show the same resolved location; an untracked verbal instruction is not enough.'],
            ['heading'=>'Check a service boundary','prompt'=>'A client asks for an extra site and permit deliverable that is not listed in the signed scope.','steps'=>['Identify the requested deliverable and its decision deadline.','Check the written service agreement, statutory obligations, and which qualified professional is responsible.','Document any agreed scope change and its effects on fee, schedule, coordination, and approvals.'],'answer'=>'Clarify responsibility and document an authorized scope change before treating the extra deliverable as included.','check'=>'A scope record does not override duties set by applicable law or professional standards.'],
        ],
        'misconceptions'=>[
            'A verbal decision automatically updates all controlled drawings.',
            'A coordination issue belongs only to the discipline whose drawing first showed it.',
            'A remembered code value can substitute for checking the current official rule and project context.',
            'A written service agreement removes every statutory or public-safety duty.',
        ],
        'patterns'=>[
            'Identify the responsible scope, record, and reviewer in a project scenario.',
            'Trace a coordination discrepancy through controlled resolution and communication.',
            'Choose what must be verified before making a safety or accessibility claim.',
        ],
        'concept_sections'=>[
            'b633-arch-practice-scope'=>'practice-scope-and-services',
            'b633-arch-practice-coordination'=>'project-coordination-and-records',
            'b633-arch-practice-public-responsibility'=>'public-responsibility-and-access',
        ],
        'concept_sources'=>[
            'b633-arch-practice-scope'=>['prc-architecture-syllabi','ra-9266-architecture-act','ra-9266-architecture-irr'],
            'b633-arch-practice-coordination'=>['prc-architecture-syllabi','ra-9266-architecture-act','mit-4-111-intro-architecture'],
            'b633-arch-practice-public-responsibility'=>['prc-architecture-syllabi','ra-9266-architecture-act','bp344-2024-irr','pd1096-national-building-code'],
        ],
    ],
    'architecture-history-and-theory' => [
        'lesson_id'=>'architecture-history-theory-and-conservation',
        'material_id'=>'architecture-history-theory-and-conservation',
        'title'=>'Architectural Precedent, Context, and Conservation',
        'pdf'=>'architecture-history-theory-and-conservation.pdf',
        'scope_sources'=>['prc-architecture-syllabi','prc-architecture-exam-program-june-2026'],
        'instruction_sources'=>['mit-4-111-intro-architecture','nps-preservation-briefs','ra-9266-architecture-act'],
        'scope_note'=>'The PRC architecture syllabus includes history and theory of architecture, including the influence of environmental, historical, and sociocultural factors. Republic Act 9266 includes conservation and restoration in its architecture-practice scope. The National Park Service references here are instructional conservation and documentation guidance, not Philippine law.',
        'objectives'=>[
            'Compare architectural precedents using form, use, time, place, and source evidence.',
            'Explain how climate and sociocultural context may influence building form without treating one feature as universal proof.',
            'Plan a documented conservation response that distinguishes significance, observed condition, and proposed intervention.',
        ],
        'sections'=>[
            ['id'=>'precedent-and-context','heading'=>'1. Read precedent as evidence','body'=>'A precedent is useful when its features are connected to a particular time, place, use, and body of evidence. Begin with a reliable plan, section, photograph, written account, or measured survey. Describe what can be observed before assigning a style or influence. Compare spatial organization, materials, structure, circulation, and intended use, then ask which historical or social conditions help explain the differences. Similar forms do not prove direct borrowing, and a label does not explain how a building worked. Keep observations, source claims, and interpretation distinct. This method supports critical comparison rather than memorizing isolated names and dates.','related_concept_ids'=>['b633-arch-history-theory-precedent']],
            ['id'=>'vernacular-context-and-climate','heading'=>'2. Vernacular form, climate, and use','body'=>'Buildings respond to weather, materials, construction knowledge, household or community practice, and available resources. Test a climate explanation against multiple pieces of evidence: orientation, openings, shading, rain protection, material behavior, local conditions, and the way people use the space. A feature may serve more than one purpose, and similar forms can arise in different places for different reasons. Avoid treating “vernacular” as a single timeless style or assigning one environmental cause without evidence. Describe the site and climate conditions, compare examples, and state the limits of the inference. This approach connects architectural form with environmental and sociocultural context while respecting local variation.','related_concept_ids'=>['b633-arch-history-vernacular-climate']],
            ['id'=>'conservation-and-documentation','heading'=>'3. Conservation begins with significance and condition','body'=>'Before proposing a repair or alteration, establish what is significant, what is physically observed, and what evidence is still missing. A photograph or survey documents condition at a point in time; it does not by itself diagnose the cause of deterioration. Record material, location, extent, prior alterations, and safety concerns. Consult appropriate specialists and the relevant current Philippine authority when a project may be regulated. Conservation guidance emphasizes understanding and documenting a historic property before choosing treatment. For examination reasoning, distinguish stabilizing an immediate risk, repairing a diagnosed problem, rehabilitating use, restoring a documented earlier state, and reconstructing missing fabric; do not assume they are interchangeable.','related_concept_ids'=>['b633-arch-history-conservation']],
            ['id'=>'historical-worked-comparison','heading'=>'4. Worked comparison: form and climate','body'=>'Two coastal buildings both use broad roof overhangs. One has deep verandas and cross-ventilation; the other has a protected service gallery and a different orientation. The shared roof feature alone does not prove a common historical source or identical climate strategy. Compare each site’s sun and rain exposure, program, construction, chronology, and use evidence. The defensible conclusion may be that the feature has a plausible environmental role in each case while the broader design responds to different conditions. Keep the claim proportional to the evidence.','related_concept_ids'=>[]],
            ['id'=>'conservation-worked-case','heading'=>'5. Worked case: a historic building leak','body'=>'A historic public building leaks at one roof junction, and a proposal would replace the entire roof profile. First document the location, weather conditions, materials, visible damage, and any immediate risk to occupants. Review available drawings and repair history; have an appropriately qualified specialist investigate the cause. Compare a targeted repair with broader alternatives against significance, performance, safety, and documented evidence. If intervention is justified, state what will change, why, and how the work will be recorded. Do not infer that age alone proves the original roof should be replaced.','related_concept_ids'=>[]],
        ],
        'worked_examples'=>[
            ['heading'=>'Compare two precedents','prompt'=>'Two buildings share a courtyard form but differ in date, climate, material, and community use. What can a comparison reasonably establish?','steps'=>['Describe the courtyard and its relationship to rooms, circulation, and surrounding space in each case.','Check chronology, local environmental conditions, construction evidence, and documented use.','State which similarities are observable and which explanations remain interpretations.'],'answer'=>'The comparison can identify shared spatial features and evidence-supported differences; it cannot establish direct influence from form alone.','check'=>'A defensible interpretation names its sources and avoids turning a visual resemblance into a historical fact.'],
            ['heading'=>'Choose a conservation next step','prompt'=>'A measured drawing shows a crack in historic masonry, but no record explains when it formed or whether it is active.','steps'=>['Record location, dimensions, material, and conditions with dated images or survey notes.','Check earlier documentation and obtain appropriate structural or conservation assessment if safety or stability may be affected.','Compare intervention options after significance, cause, and risk are understood.'],'answer'=>'Document and assess the condition before selecting a repair, while addressing any immediate safety risk through qualified review.','check'=>'A visible symptom is not a diagnosis; treatment should follow evidence about cause and significance.'],
        ],
        'misconceptions'=>[
            'A recognizable style label explains the building’s historical development.',
            'One feature proves a single climate cause or direct cultural influence.',
            'A photograph documents the cause of deterioration rather than the observed condition.',
            'Replacing old material is automatically safer or more historically accurate.',
        ],
        'patterns'=>[
            'Interpret a precedent with chronology, site, use, material, and source evidence.',
            'Compare environmental responses without treating a feature as universal proof.',
            'Select a documented and proportionate next step for a conservation case.',
        ],
        'concept_sections'=>[
            'b633-arch-history-theory-precedent'=>'precedent-and-context',
            'b633-arch-history-vernacular-climate'=>'vernacular-context-and-climate',
            'b633-arch-history-conservation'=>'conservation-and-documentation',
        ],
        'concept_sources'=>[
            'b633-arch-history-theory-precedent'=>['prc-architecture-syllabi','mit-4-111-intro-architecture'],
            'b633-arch-history-vernacular-climate'=>['prc-architecture-syllabi','mit-4-111-intro-architecture'],
            'b633-arch-history-conservation'=>['prc-architecture-syllabi','ra-9266-architecture-act','nps-preservation-briefs'],
        ],
    ],
];

$qIndex = [];
foreach ($questions as $index => $question) if (is_array($question)) $qIndex[(string) ($question['id'] ?? '')] = $index;
$lessonAt = [];
foreach ($lessons as $index => $lesson) if (is_array($lesson)) $lessonAt[(string) ($lesson['id'] ?? '')] = $index;
$materialAt = [];
foreach ($materials as $index => $material) if (is_array($material)) $materialAt[(string) ($material['id'] ?? '')] = $index;
$linkAt = [];
foreach ($links as $index => $link) if (is_array($link)) $linkAt[(string) ($link['id'] ?? '')] = $index;
$createdLessons = 0;
$newLinks = 0;

foreach ($specs as $subjectId => $spec) {
    foreach (array_merge($spec['scope_sources'], $spec['instruction_sources']) as $sourceId) {
        if (!isset($sources[$sourceId])) throw new RuntimeException('Required architecture lesson source is not registered: ' . $sourceId);
    }
    $practiceIds = [];
    $sectionsByConcept = $spec['concept_sections'];
    foreach ($questions as $questionIndex => $question) {
        if (!is_array($question) || strtolower((string) ($question['status'] ?? 'active')) === 'archived') continue;
        $placement = $eligibility->forExam($question, 'architecture');
        if (!is_array($placement) || (string) ($placement['subject_id'] ?? '') !== $subjectId) continue;
        $conceptId = (string) ($question['taxonomy']['concept_id'] ?? '');
        if (!isset($sectionsByConcept[$conceptId])) throw new RuntimeException('No architecture lesson section for ' . $subjectId . ' question ' . (string) $question['id']);
        $question['study_material_id'] = $spec['material_id'];
        $question['study_section_id'] = $sectionsByConcept[$conceptId];
        $questions[$questionIndex] = $question;
        $questionId = (string) $question['id'];
        $practiceIds[] = $questionId;
        $linkId = 'batch694-architecture-' . $subjectId . '-' . $questionId;
        $linkRecord = [
            'id'=>$linkId,
            'material_id'=>$spec['material_id'],
            'question_id'=>$questionId,
            'source_ids'=>$spec['concept_sources'][$conceptId],
            'section_id'=>$sectionsByConcept[$conceptId],
        ];
        if (isset($linkAt[$linkId])) $links[$linkAt[$linkId]] = $linkRecord;
        else { $linkAt[$linkId] = count($links); $links[] = $linkRecord; $newLinks++; }
    }
    $practiceIds = array_values(array_unique($practiceIds));
    if ($practiceIds === []) throw new RuntimeException('No active eligible architecture questions resolved for ' . $subjectId);
    $firstQuestion = $questions[$qIndex[$practiceIds[0]]];
    $taxonomy = (array) ($firstQuestion['taxonomy'] ?? []);
    $lesson = [
        'id'=>$spec['lesson_id'], 'title'=>$spec['title'], 'material_id'=>$spec['material_id'], 'status'=>'published', 'version'=>1,
        'board_id'=>'architecture','subject_id'=>$subjectId,
        'official_scope_source_ids'=>$spec['scope_sources'],'instructional_source_ids'=>$spec['instruction_sources'],
        'scope_note'=>$spec['scope_note'],'learning_objectives'=>$spec['objectives'],'sections'=>$spec['sections'],
        'worked_examples'=>$spec['worked_examples'],'common_misconceptions'=>$spec['misconceptions'],'question_patterns'=>$spec['patterns'],
        'practice_question_ids'=>$practiceIds,'pdf_file'=>$spec['pdf'],
        'taxonomy'=>['subject_id'=>$subjectId,'domain_id'=>(string) ($taxonomy['domain_id'] ?? ''),'topic_id'=>(string) ($taxonomy['topic_id'] ?? ''),'concept_id'=>(string) ($taxonomy['concept_id'] ?? '')],
        'source_review'=>['status'=>'SOURCE_CHECKED_HUMAN_REVIEW_PENDING','checked_at'=>gmdate('Y-m-d'),'note'=>'The PRC architecture syllabus and registered profession-specific sources were checked for scope. Lesson prose is original. Human academic and owner review remain pending; the lesson is not legal advice or an exam prediction.'],
        'batch_marker'=>'batch694',
    ];
    if (isset($lessonAt[$spec['lesson_id']])) $lessons[$lessonAt[$spec['lesson_id']]] = $lesson;
    else { $lessonAt[$spec['lesson_id']] = count($lessons); $lessons[] = $lesson; $createdLessons++; }
    $material = [
        'id'=>$spec['material_id'],'title'=>$spec['title'],
        'summary'=>'Original source-first study lesson for the current architecture licensure syllabus, with evidence-based worked examples and linked practice questions.',
        'notes'=>[['Scope',$spec['scope_note']],['Study method','Read the section, work the example, then state which evidence and professional decision rule support the answer.'],['Review status','Source checked; human academic and owner review remain pending.']],
        'source_ids'=>array_values(array_unique(array_merge($spec['scope_sources'],$spec['instruction_sources']))),
        'exams'=>['architecture'],'exam_focus'=>['architecture'=>['priority'=>'medium','rationale'=>'Instructional practice aligned to the registered architecture syllabus; no exam-frequency claim.','official_scope_status'=>'source-linked study support','focus_topics'=>array_column($spec['sections'],'heading')]],
        'taxonomy'=>['subject_id'=>$subjectId,'domain_id'=>(string) ($taxonomy['domain_id'] ?? ''),'topic_id'=>(string) ($taxonomy['topic_id'] ?? ''),'concept_id'=>(string) ($taxonomy['concept_id'] ?? '')],
        'status'=>'published','version'=>1,'updated_at'=>gmdate('Y-m-d'),'batch_marker'=>'batch694',
    ];
    if (isset($materialAt[$spec['material_id']])) $materials[$materialAt[$spec['material_id']]] = $material;
    else { $materialAt[$spec['material_id']] = count($materials); $materials[] = $material; }
}

if (($argv[1] ?? '') === '--dry-run') {
    printf("ARCHITECTURE_STUDY_LESSONS=DRY_RUN_PASS CREATED=%d LINKS=%d\n", $createdLessons, $newLinks);
    exit(0);
}
$storage->replace('questions', $questions);
$storage->replace('study-lessons', $lessons);
$storage->replace('study-materials', $materials);
$storage->replace('study-question-links', $links);
printf("ARCHITECTURE_STUDY_LESSONS=APPLIED CREATED=%d LINKS=%d\n", $createdLessons, $newLinks);
