#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();
putenv('APP_ENV=testing');
putenv('APP_DEBUG=false');
\App\Core\App::boot();
$storage = \App\Core\App::storage();
$lessons = $storage->all('study-lessons');
$index = \App\Services\Question\QuestionEligibilityService::index();
$questions = $storage->all('questions');
$practiceIds = [];
foreach ($questions as $question) {
    if (!is_array($question)) continue;
    $placement = $index->forExam($question, 'let');
    if (is_array($placement) && ($placement['subject_id'] ?? '') === 'values-education') $practiceIds[] = (string) $question['id'];
}
foreach ($lessons as &$lesson) {
    if (($lesson['id'] ?? '') !== 'let-values-ethical-reasoning-basics') continue;
    $lesson['title'] = 'Values Education: Foundations, Personhood, Transformative Education, and Work Ethics';
    $lesson['summary'] = 'Original instructional review of the four Values Education units in the current PRC TOS, with ethical analysis, culturally responsive dialogue, reflective action, and applied dilemmas.';
    $lesson['official_scope_source_ids'] = ['prc-values-tos-2025', 'prc-let-enhanced-tos-2025'];
    $lesson['instructional_source_ids'] = ['deped-matatag-values-curriculum', 'deped-esep-curriculum-guide', 'deped-ppst-do-42-2017'];
    $lesson['scope_note'] = 'The four units follow the published PRC Values Education secondary TOS. The MATATAG page is cited as the official curriculum-guide index; its linked PDF body was not inspected during this batch. This source-linked instructional guide is not an exam prediction.';
    $lesson['learning_objectives'] = [
        'Clarify a value through reasons, affected persons, context, and consequences.',
        'Use a repeatable ethical decision process when duties or values conflict.',
        'Facilitate respectful dialogue across difference while protecting dignity and participation.',
        'Apply civic and professional responsibilities proportionately and reflect on the result.',
    ];
    $lesson['sections'] = [
        ['heading'=>'Unit 1. Foundations of Values Education','body'=>'Values Education develops reasoned understanding and responsible action; it is not a list of slogans or a test of approved language. Clarify a value by identifying what is valued, why it matters, who is affected, and how context changes a choice. Distinguish a preference from a claim about dignity, fairness, duty, or the common good. A sound response gives reasons others can examine while recognizing that people may begin from different experiences.','items'=>['Name the value at stake and the evidence in the situation.','Ask who benefits, who bears a cost, and whose voice is missing.','Explain why a proposed action follows from the values and facts.']],
        ['heading'=>'Unit 2. Personhood Development and Reflective Agency','body'=>'Personhood includes agency, relationships, responsibilities, and the possibility of growth. Reflection examines an action and its effects without reducing a person to one mistake or assuming intention cancels harm. A learner can acknowledge what happened, listen to affected people, repair what is reasonably repairable, and plan a concrete future practice. Cultural concepts should be discussed in context; no learner should be made to represent an entire community.','items'=>['Separate behavior from a permanent judgment about character.','Listen to the affected person and consider a suitable repair.','Choose a specific practice that can be reviewed later.']],
        ['heading'=>'Unit 3. Transformative Education and Ethical Decision','body'=>'For a difficult case, establish facts and uncertainty; identify stakeholders, rights, duties, policies, and foreseeable consequences; then generate feasible actions and compare them against relevant principles. Select a proportionate option, explain what evidence would change the decision, and decide how the outcome will be reviewed. A process cannot guarantee one answer for every case, but it makes assumptions visible and reduces impulsive judgment.','items'=>['Facts: what is known, reported, and still uncertain?','Principles: which duty, right, fairness concern, or consequence applies?','Action: what is proportionate, authorized, and reviewable?']],
        ['heading'=>'Unit 4. Work Ethics, Community, and Public Responsibility','body'=>'Professional conduct includes competence, fairness, confidentiality, accountability, and responsible use of authority. A conflict of interest or request involving private information should be handled through the applicable rule and authorized process; good intentions alone do not remove a conflict. Community participation requires accurate information, accessible ways to contribute, and transparent use of input. Digital conduct also affects dignity and safety: verify before sharing, minimize exposure, and avoid amplifying harm.','items'=>['Identify the professional duty and applicable process.','Disclose or limit a conflict or data use as the governing rule requires.','Record the decision and communicate its basis to affected people.']],
        ['heading'=>'Worked dilemma: correcting a score','body'=>'A group discovers that a spreadsheet error raised its project score. Members worry that correcting it will lower a grade. Verify the calculation, identify the duty to report accurate work, and determine who can correct the official record. Discuss the error with the group, inform the teacher through the proper route, and ask for a recalculation. Waiting for discovery or changing the spreadsheet privately avoids accountability and leaves an unreliable result.','items'=>['Verify the error before acting.','Use the authorized correction route.','Accept that fair assessment may change the result.']],
        ['heading'=>'Review: test reasons, not slogans','body'=>'For any proposed action, ask whether the facts are accurate; the response respects dignity and fair participation; the duty is relevant; the action is proportionate and within authority; and follow-up can show whether it worked. Consider a plausible alternative carefully rather than dismissing it through a label. Ethical judgment is strengthened by evidence, transparent reasoning, and willingness to revise when facts change.','items'=>['State the controlling principle in plain language.','Explain why a plausible alternative is less appropriate in this case.','Name a limitation or fact that could change the decision.']],
    ];
    $lesson['worked_examples'] = [['heading'=>'Respond to a safety report','prompt'=>'A student reports a harmful post, asks for secrecy, and says the post identifies a peer who may be harassed.','steps'=>['Listen and clarify immediate risk without promising absolute secrecy.','Explain any necessary limited disclosure and the authorized safeguarding route.','Share only information needed for that purpose and check the affected learner’s support needs.'],'answer'=>'Protect trust while explaining its limits and acting through the proper safety process.','check'=>'Privacy, foreseeable harm, and professional responsibility are considered together.']];
    $lesson['common_misconceptions'] = ['One value overrides all others in every setting.','Respectful dialogue requires agreement or silence.','Good intention makes disclosure or unequal treatment harmless.','One action permanently defines a person’s character.','A majority vote replaces rights and duties that apply to a decision.'];
    $lesson['question_patterns'] = ['Resolve a conflict using facts, stakeholders, duties, and proportionality.','Distinguish values clarification from imposing a preferred answer.','Choose dialogue or repair that preserves agency and dignity.','Apply civic or professional duties to a realistic case.'];
    $lesson['practice_question_ids'] = array_values(array_unique(array_merge((array) ($lesson['practice_question_ids'] ?? []), $practiceIds)));
    $lesson['pdf_file'] = 'let-values-ethical-reasoning.pdf';
    $lesson['source_review'] = ['status'=>'SOURCE_CHECKED_HUMAN_REVIEW_PENDING','checked_at'=>gmdate('Y-m-d'),'note'=>'Official scope and linked source records were checked during authoring. Item-level academic review and owner approval remain pending. Original lesson text, not reproduced source material.'];
}
unset($lesson);
$storage->replace('study-lessons', $lessons);
printf("VALUES_LESSON_UPDATED=1\nPRACTICE_QUESTIONS=%d\n", count($practiceIds));
