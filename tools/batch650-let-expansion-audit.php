<?php

declare(strict_types=1);

/**
 * Batch650 source-first LET production gate.
 *
 * The stored Batch649 Wave1 plan is the sole Wave1 authority. Questions are
 * validated and written through QuestionService, mappings through
 * QuestionEligibilityService, and study traces through the canonical storage
 * collection. The default invocation is read-only; --wave1 and --wave2 execute
 * the stored/freshly gated production cohorts, while --audit validates the
 * persisted production delta.
 */

require_once dirname(__DIR__) . '/app/Core/Autoloader.php';

use App\Core\App;
use App\Core\Autoloader;
use App\Repositories\QuestionRepository;
use App\Services\Flashcard\FlashcardCardValidator;
use App\Services\Flashcard\FlashcardDerivationService;
use App\Services\Question\QuestionEligibilityService;
use App\Services\Question\QuestionService;
use App\Services\RepositoryHealth\Readiness\SourceReadinessRecord;
use App\Services\RepositoryHealth\Readiness\SourceReadinessService;
use App\Services\Shared\TaxonomyStorageService;

Autoloader::register();
putenv('APP_ENV=testing');
putenv('APP_DEBUG=false');
App::boot();

$root = dirname(__DIR__);
$baseHead = '0eb9d8e09c28c386cfd3b9acab54a1330ab6e469';
$artifactPath = $root . '/storage/batch650-let-expansion.json';
$batch649Path = $root . '/storage/batch649-let-source-readiness.json';

$readJson = static function (string $path): array {
    if (!is_file($path)) throw new RuntimeException('Required JSON is missing: ' . $path);
    $value = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($value)) throw new RuntimeException('Expected a JSON collection/object: ' . $path);
    return $value;
};
$writeJson = static function (string $path, array $value): void {
    $json = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    $temporary = $path . '.tmp';
    if (file_put_contents($temporary, $json . "\n", LOCK_EX) === false || !rename($temporary, $path)) {
        throw new RuntimeException('Could not atomically write ' . $path);
    }
};
$collections = static function (): array {
    $names = [
        'questions', 'boards', 'subjects', 'board-subjects', 'blueprints',
        'taxonomy/domains', 'taxonomy/topics', 'taxonomy/concepts',
        'question-eligibility', 'concept-derived-eligibility', 'question-reuse-overrides',
        'question-reuse-metadata', 'board-concept-coverage', 'study-materials',
        'study-question-links', 'sources', 'content-fit-audits', 'flashcard-exclusions',
        'source-readiness-let', 'source-readiness', 'source-readiness-psychometrician',
        'source-readiness-mechanical-engineering',
    ];
    $result = [];
    foreach ($names as $name) $result[$name] = App::storage()->all($name);
    return $result;
};
$fail = static function (string $message): never {
    fwrite(STDERR, 'BATCH650_FAIL=' . $message . PHP_EOL);
    exit(1);
};

/** @return array<int, array<string, mixed>> */
$waveOneDefinitions = static function (): array {
    $reading = ['deped-k12-english-curriculum-guide', 'purdue-owl-writing', 'openstax-reading-writing'];
    $grammar = ['deped-k12-english-curriculum-guide', 'purdue-owl-writing', 'openstax-reading-writing'];
    $make = static function (string $leaf, string $material, array $sources, int $depth, string $family, string $stem, array $choices, int $correct, string $explanation, string $focus): array {
        return [
            'leaf' => $leaf,
            'material' => $material,
            'sources' => $sources,
            'depth' => $depth,
            'family' => $family,
            'stem' => $stem,
            'choices' => $choices,
            'correct' => $correct,
            'explanation' => $explanation,
            'focus' => $focus,
        ];
    };
    return [
        $make('context-clues-contrast-clues', 'let-english-reading-foundation', $reading, 4, 'LANGUAGE_IN_CONTEXT',
            'The restoration used inexpensive native seedlings; nevertheless, the first storm removed many plants from the exposed shore. In this sentence, nevertheless signals that:',
            ['the storm occurred before the planting.', 'the low cost guarantees later survival.', 'the later result contrasts with the expectation created by the low cost.', 'the seedlings were not native.'], 2,
            '“Nevertheless” marks a contrast: affordability is favorable, but the exposed site still experienced losses.',
            'Interpret a contrast marker by relating the two adjacent propositions.'),
        $make('context-clues-example-clues', 'let-english-reading-foundation', $reading, 3, 'LANGUAGE_IN_CONTEXT',
            'The archive preserves ephemera, including ticket stubs, handwritten programs, and temporary event notices. What does ephemera most nearly mean here?',
            ['Items created for temporary use that can document an event.', 'Official rules that remain in force indefinitely.', 'Tools used to restore damaged paper.', 'Books written as complete histories.'], 0,
            'The examples are short-lived printed or handwritten items associated with an event, so they clarify “ephemera” as temporary materials worth preserving.',
            'Use a series of examples to infer the category named by an unfamiliar term.'),
        $make('critical-reading-fact-and-opinion', 'let-english-reading-foundation', $reading, 4, 'MEANING_OR_TEXT_INTERPRETATION',
            'A school report states that the revised schedule began in August and that 18 of 24 surveyed learners preferred it. The principal calls the schedule “the fairest option for every class.” Which statement is an opinion rather than a directly reported fact?',
            ['The revised schedule began in August.', 'Eighteen surveyed learners preferred the schedule.', 'The report surveyed 24 learners.', 'The schedule is the fairest option for every class.'], 3,
            'The first three statements are checkable details explicitly reported; “fairest” is an evaluative judgment that the survey count alone does not establish.',
            'Separate verifiable report details from a value judgment that interprets them.'),
        $make('text-structure-compare-and-contrast', 'let-english-reading-foundation', $reading, 4, 'MEANING_OR_TEXT_INTERPRETATION',
            'A library compared two reminder systems. Both sent notices before a due date, but the first used printed cards while the second sent text messages; the library reported fewer late returns with texts and noted that phone access varied. Which feature is the basis of the comparison?',
            ['Whether each system sent a reminder before the due date.', 'Whether the library had books available.', 'Whether every borrower owned a phone.', 'Whether printed cards were cheaper to produce.'], 0,
            'The passage explicitly identifies a reminder before the due date as common to both systems; medium and reported outcomes are the contrasts.',
            'Identify the shared comparison dimension before interpreting differences.'),
        $make('context-clues-definition-clues', 'let-english-reading-foundation', $reading, 4, 'LANGUAGE_IN_CONTEXT',
            'The report describes the result as provisional, meaning that it is accepted only for now while additional observations are collected. What does provisional mean in this context?',
            ['Final and no longer open to revision.', 'Tentative pending further evidence.', 'Unrelated to the observations.', 'Incorrect by definition.'], 1,
            'The passage directly explains the word: the result is temporary and may change when more observations arrive.',
            'Use an explicit restatement to determine a word’s contextual meaning.'),
        $make('context-clues-inference-clues', 'let-english-reading-foundation', $reading, 3, 'MEANING_OR_TEXT_INTERPRETATION',
            'The mangrove plot beside the seawall retained more seedlings than the plot facing open waves. The project team will measure wave exposure before recommending another site. Which meaning of retained is supported by these clues?',
            ['Kept or continued to have.', 'Replaced with a different species.', 'Measured for height only.', 'Transferred to the seawall.'], 0,
            'The contrast between the plots and the count of seedlings indicate that the sheltered plot continued to have more living seedlings.',
            'Infer a word’s meaning from the outcome and contrast surrounding it.'),
        $make('inference-implicit-meaning', 'let-english-reading-foundation', $reading, 4, 'MEANING_OR_TEXT_INTERPRETATION',
            'A town posted a draft flood plan online, accepted comments for two weeks, and scheduled a meeting with residents before approving it. What is most strongly implied?',
            ['The posted draft is already a final regulation.', 'Residents are being given an opportunity to inform the decision before it is final.', 'The town has rejected all technical evidence.', 'The meeting will replace the need for a written plan.'], 1,
            'A draft, comment period, and pre-approval meeting together show that the decision remains open to public input; they do not establish that every comment will be adopted.',
            'Combine multiple stated procedural clues without claiming more than they warrant.'),
        $make('modifiers-adjective-modifiers', 'let-english-grammar-foundation', $grammar, 3, 'LANGUAGE_IN_CONTEXT',
            'An editor needs a concise phrase in which coastal describes the kind of maps that were updated. Which sentence uses coastal as an adjective modifying maps?',
            ['The coastal maps were updated.', 'The maps were coastally updated.', 'The staff mapped the coast.', 'The coast was mapped after the update.'], 0,
            '“Coastal” directly modifies the noun “maps”; the other choices use an adverb, a noun, or a passive verb phrase instead.',
            'Identify an adjective by the noun it modifies, distinguishing it from a related adverb or noun.'),
        $make('modifiers-adverb-modifiers', 'let-english-grammar-foundation', $grammar, 3, 'ERROR_ANALYSIS_OR_REVISION',
            'A notice should say that the office checked the figures with care. Which revision places the adverb so that it modifies checked?',
            ['The office carefully checked the figures.', 'The carefully office checked the figures.', 'The office checked the careful figures.', 'The office checked the figures careful.'], 0,
            '“Carefully” is an adverb modifying the verb “checked”; the other placements either modify the wrong word or use the adjective form.',
            'Revise modifier placement and form so the adverb clearly modifies the intended verb.'),
        $make('parallelism-faulty-comparisons', 'let-english-grammar-foundation', $grammar, 3, 'ERROR_ANALYSIS_OR_REVISION',
            'A report intends to compare the time needed by two filing methods. Which sentence makes the comparison between like quantities?',
            ['Filing by the portal takes less time than filing on paper.', 'The portal takes less time than paper filing.', 'Filing by the portal is faster than the paper files.', 'Portal filing takes less time than when staff used paper.'], 0,
            'The correct sentence compares the time required for each method. The other choices compare unlike things or leave the compared action unclear.',
            'Repair a faulty comparison by making the compared terms grammatically and logically parallel.'),
        $make('parts-of-speech-adjectives', 'let-english-grammar-foundation', $grammar, 3, 'LANGUAGE_IN_CONTEXT',
            'In “The measurements became clearer after the technician replaced the cracked tube,” which word is a predicative adjective describing measurements?',
            ['measurements', 'became', 'clearer', 'replaced'], 2,
            '“Clearer” follows the linking verb “became” and describes the subject “measurements”; it is a predicative adjective.',
            'Recognize an adjective in a predicate complement rather than only before a noun.'),
        $make('parts-of-speech-nouns', 'let-english-grammar-foundation', $grammar, 3, 'LANGUAGE_IN_CONTEXT',
            'In “The annotations from the reviewer were decisive,” which word is the head noun of the subject phrase?',
            ['annotations', 'from', 'reviewer', 'were'], 0,
            '“Annotations” is the subject phrase’s head noun; “from the reviewer” is a prepositional phrase modifying it, and “were” is the verb.',
            'Distinguish a phrase’s head noun from a noun inside a modifying prepositional phrase.'),
        $make('parts-of-speech-verbs', 'let-english-grammar-foundation', $grammar, 3, 'LANGUAGE_IN_CONTEXT',
            'In “The copied forms were missing from the archive,” which word is the finite verb that anchors the clause in tense?',
            ['copied', 'forms', 'were', 'missing'], 2,
            '“Were” is the finite past-tense verb; “copied” and “missing” describe the forms in this construction.',
            'Distinguish a finite, tensed verb from participial forms that describe a noun or state.'),
        $make('prepositions-prepositional-phrases', 'let-english-grammar-foundation', $grammar, 3, 'MEANING_OR_TEXT_INTERPRETATION',
            'In “After the inspection, the clerk filed the signed forms,” what relationship does the opening prepositional phrase express?',
            ['The time when the forms were filed.', 'The person who signed the forms.', 'The reason the inspection was canceled.', 'The location of the clerk.'], 0,
            '“After the inspection” is a time phrase modifying the filing action; it does not identify the signer, cause, or location.',
            'Interpret the relationship expressed by a prepositional phrase in context.'),
        $make('pronouns-pronoun-case', 'let-english-grammar-foundation', $grammar, 3, 'ERROR_ANALYSIS_OR_REVISION',
            'The coordinator invited Lea and ___ to explain the revised process. Which pronoun has the correct case?',
            ['I', 'me', 'my', 'mine'], 1,
            'The pronoun is the direct object of “invited,” so the objective form “me” is required.',
            'Select pronoun case from the pronoun’s grammatical role, not from a removed compound phrase.'),
        $make('sentence-structure-compound-sentences', 'let-english-grammar-foundation', $grammar, 3, 'ERROR_ANALYSIS_OR_REVISION',
            'Two complete statements must remain parallel in importance: the archive was closed, and staff moved the consultation online. Which revision correctly joins them with a semicolon?',
            ['The archive was closed; staff moved the consultation online.', 'The archive was closed, staff moved the consultation online.', 'The archive was closed; because staff moved the consultation online.', 'The archive was closed staff moved; the consultation online.'], 0,
            'A semicolon can join two closely related independent clauses. The comma alone creates a splice, while the other choices leave a fragment or break the clause.',
            'Recognize how two independent clauses form a compound sentence without a comma splice.'),
        $make('sentence-structure-fragments', 'let-english-grammar-foundation', $grammar, 3, 'ERROR_ANALYSIS_OR_REVISION',
            'A draft reads: “The inspectors reviewing the damaged footbridge before reopening it.” Which revision supplies the missing finite verb while preserving the intended meaning?',
            ['The inspectors reviewed the damaged footbridge before reopening it.', 'The inspectors, reviewing the damaged footbridge before reopening it.', 'Reviewing the damaged footbridge before reopening it.', 'The damaged footbridge before reopening it was reviewed.'], 0,
            'The original is a noun phrase with a participial phrase but no finite predicate. “Reviewed” completes the clause and preserves who acted.',
            'Diagnose a fragment caused by a missing predicate and supply a complete clause.'),
        $make('subject-verb-agreement-collective-nouns', 'let-english-grammar-foundation', $grammar, 3, 'ERROR_ANALYSIS_OR_REVISION',
            'The committee of curriculum specialists, rather than the individual specialists, ___ the final recommendation. Which form agrees with the subject?',
            ['review', 'reviews', 'have reviewed', 'were reviewing'], 1,
            'The grammatical subject is the collective noun “committee,” treated here as one decision-making body; the plural noun in the of-phrase does not control the verb.',
            'Locate a collective noun as the subject and ignore a plural noun inside an intervening phrase.'),
        $make('text-structure-problem-and-solution', 'let-english-reading-foundation', $reading, 4, 'MEANING_OR_TEXT_INTERPRETATION',
            'A clinic found that patients often missed follow-up appointments because reminder dates were written on separate cards. It introduced one shared appointment log; missed visits then declined, although staff plan to check whether transport problems also affected attendance. Which statement best describes the passage’s structure?',
            ['It presents an access problem, describes a record-keeping response, and qualifies the reported outcome.', 'It lists unrelated events in chronological order only.', 'It compares two clinics with identical appointment systems.', 'It argues that transport is the sole cause of missed visits.'], 0,
            'The passage follows problem, proposed solution, and a cautious note about another possible factor; it does not establish a single cause.',
            'Trace a problem-solution pattern while preserving the passage’s stated limitation.'),
        $make('verb-tenses-future-tense', 'let-english-grammar-foundation', $grammar, 3, 'LANGUAGE_IN_CONTEXT',
            'The inspection report is due Monday. The work plan should state that the team will finish the review before that deadline. Which sentence expresses completion before a future point?',
            ['By Monday, the team will have completed the review.', 'On Monday, the team completed the review.', 'The team had completed the review by Monday last week.', 'The team completes the review yesterday.'], 0,
            '“Will have completed” is future perfect: it presents completion as occurring before the future reference point Monday.',
            'Use future perfect aspect to show an action completed before a stated future deadline.'),
        $make('verb-tenses-present-tense', 'let-english-grammar-foundation', $grammar, 3, 'LANGUAGE_IN_CONTEXT',
            'A science guide states a general process: “When water reaches its boiling point, it ___ into vapor.” Which form best expresses the general relationship?',
            ['changed', 'changes', 'will have changed', 'was changing'], 1,
            'The simple present expresses a general process stated as a continuing rule, not one completed event or a future prediction.',
            'Use the simple present for a general process stated as a standing relationship.'),
        $make('critical-reading-argument-evaluation', 'let-english-reading-foundation', $reading, 3, 'MEANING_OR_TEXT_INTERPRETATION',
            'A vendor claims a new queue system “eliminates waiting.” The report shows that the median wait fell from 18 minutes to 11 minutes during one week, but gives no information about the longest waits. Which evaluation is most defensible?',
            ['The claim is fully proven because the median decreased.', 'The result is encouraging, but the reported median does not show that waiting was eliminated or describe the longest waits.', 'The system had no effect because some waiting remained possible.', 'The report proves the same result will occur at every service counter.'], 1,
            'A lower median supports a reduction in typical wait time, but it neither shows zero waiting nor describes the distribution’s longest waits.',
            'Evaluate whether the evidence supports the strength and breadth of a claim.'),
        $make('main-idea-and-support-supporting-evidence', 'let-english-reading-foundation', $reading, 4, 'MEANING_OR_TEXT_INTERPRETATION',
            'A coastal barangay planted trees along two paths. After six months, the shaded path had lower midday surface temperatures; the report also notes that its surface was lighter and received less direct sun. Which detail is direct evidence for the reported temperature difference?',
            ['The planting occurred in a coastal barangay.', 'The shaded path recorded lower midday surface temperatures.', 'Both paths were used by residents.', 'The report was prepared after six months.'], 1,
            'The measured lower temperature is the observation directly supporting the report’s comparison; the other details give context or timing.',
            'Select the reported observation that directly supports the stated comparison.'),
        $make('parts-of-speech-adverbs', 'let-english-grammar-foundation', $grammar, 3, 'LANGUAGE_IN_CONTEXT',
            'In “The unusually quiet room helped the class concentrate,” which word modifies the adjective quiet?',
            ['room', 'helped', 'unusually', 'concentrate'], 2,
            '“Unusually” is an adverb modifying the adjective “quiet,” expressing degree rather than manner of the action.',
            'Recognize an adverb modifying an adjective rather than a verb.'),
        $make('parts-of-speech-adverbs', 'let-english-grammar-foundation', $grammar, 4, 'MEANING_OR_TEXT_INTERPRETATION',
            'In “Fortunately, the backup remained legible,” what does fortunately chiefly modify?',
            ['The noun backup alone.', 'The adjective legible alone.', 'The whole clause, signaling the writer’s evaluation of the situation.', 'The past-tense verb remained as a manner of action.'], 2,
            '“Fortunately” is a sentence adverb commenting on the clause as a whole; it does not describe how the backup remained.',
            'Recognize a sentence adverb that expresses the writer’s stance toward a complete proposition.'),
        $make('prepositions-preposition-choice', 'let-english-grammar-foundation', $grammar, 2, 'LANGUAGE_IN_CONTEXT',
            'The editor divided the report ___ four sections so different teams could review them. Which preposition expresses the intended partition?',
            ['into', 'between', 'during', 'beside'], 0,
            '“Divide into” introduces the parts created from a whole; the other prepositions express different relationships.',
            'Select a preposition that matches the stated whole-to-parts relationship.'),
        $make('sentence-structure-run-on-sentences', 'let-english-grammar-foundation', $grammar, 3, 'ERROR_ANALYSIS_OR_REVISION',
            'Two complete observations should be connected to show a cause: the sensor lost power, and the display went blank. Which revision is grammatical and keeps that relationship?',
            ['The sensor lost power, so the display went blank.', 'The sensor lost power the display went blank.', 'The sensor lost power, the display went blank.', 'The sensor lost power; because the display went blank.'], 0,
            '“So” joins the independent clauses and marks the stated result. The bare sequence and comma splice are run-ons; the semicolon-plus-because choice is incomplete.',
            'Repair a run-on while preserving the logical relation between independent clauses.'),
        $make('text-structure-cause-and-effect', 'let-english-reading-foundation', $reading, 3, 'MEANING_OR_TEXT_INTERPRETATION',
            'A roof drain became blocked with leaves. During the next rain, water overflowed onto the walkway; after workers cleared the drain, the overflow stopped. Which sequence best represents the cause-and-effect structure?',
            ['Clearing the drain caused the rain to begin.', 'The blockage led to overflow, and clearing it removed the obstruction associated with that overflow.', 'The walkway caused leaves to collect on the roof.', 'The rain stopped because the walkway was repaired.'], 1,
            'The passage links a blocked drain to overflow and then reports that clearing the obstruction stopped it; it does not claim the walkway caused the blockage.',
            'Trace an explicitly stated cause, its effect, and the result of removing the cause.'),
        $make('verb-tenses-past-tense', 'let-english-grammar-foundation', $grammar, 3, 'ERROR_ANALYSIS_OR_REVISION',
            'A completed inspection happened on a date that has passed: “On Tuesday, the team ___ the damaged railing.” Which form matches the finished time reference?',
            ['inspects', 'has inspected', 'inspected', 'will inspect'], 2,
            'The finished time marker “On Tuesday” calls for the simple past “inspected.”',
            'Match a verb tense to an explicitly finished time period.'),
        $make('main-idea-and-support-central-idea', 'let-english-reading-foundation', $reading, 4, 'MEANING_OR_TEXT_INTERPRETATION',
            'A community center replaced a sign-up notebook with a shared calendar. Fewer double bookings followed, but the coordinator also trained staff to confirm reservations. The center plans to review both changes after another month. Which statement best captures the passage as a whole?',
            ['The shared calendar alone eliminated every booking problem.', 'A reservation process was revised and early results improved, but the report does not isolate the contribution of each change.', 'Staff training was unnecessary once the notebook was removed.', 'The center will stop recording reservations after one month.'], 1,
            'The central idea covers both the process change and the cautious report of improvement; it avoids claiming a single proven cause.',
            'Synthesize the full passage rather than selecting a supporting detail or overstating causation.'),
        $make('modifiers-misplaced-modifiers', 'let-english-grammar-foundation', $grammar, 3, 'ERROR_ANALYSIS_OR_REVISION',
            'The intended meaning is that the analyst, after reviewing the final spreadsheet, found the duplicate entries. Which sentence avoids a dangling opening modifier?',
            ['After reviewing the final spreadsheet, the analyst found the duplicate entries.', 'After reviewing the final spreadsheet, the duplicate entries were found.', 'The duplicate entries, after reviewing the final spreadsheet, were found.', 'The analyst found, after reviewing, the final spreadsheet duplicate entries.'], 0,
            'The opening phrase correctly modifies “the analyst,” the person who performed the review; the other choices leave the reviewer unclear or disrupt the intended relation.',
            'Attach an introductory participial phrase to the person who performs its action.'),
        $make('pronouns-pronoun-antecedent-agreement', 'let-english-grammar-foundation', $grammar, 3, 'ERROR_ANALYSIS_OR_REVISION',
            'Both the mentor and the trainee submitted a reflection. The report must refer to the two people together: “___ described what they would revise next.” Which subject pronoun agrees with the compound antecedent?',
            ['Each', 'They', 'It', 'She'], 1,
            'The compound antecedent names two people, so the plural pronoun “they” agrees in number and preserves the intended reference.',
            'Match a pronoun to a compound antecedent rather than to either noun alone.'),
        $make('pronouns-pronoun-reference', 'let-english-grammar-foundation', $grammar, 3, 'ERROR_ANALYSIS_OR_REVISION',
            'A report says, “The board revised the attendance policy after the school met, and this surprised families.” The writer means that the policy revision surprised families. Which revision makes the referent explicit?',
            ['The board revised the attendance policy after the school met, and the policy change surprised families.', 'The board revised the attendance policy after the school met, and this surprised families.', 'After the school met, the board revised it, which surprised them.', 'The board revised the attendance policy after it met, and they surprised families.'], 0,
            'Naming “the policy change” removes the ambiguous demonstrative “this”; the other choices retain or add unclear pronoun references.',
            'Replace a vague pronoun with the intended antecedent when several events could be its referent.'),
    ];
};

/** @return array<int, array<string, mixed>> */
$waveTwoDefinitions = static function (): array {
    $make = static function (string $leaf, string $family, int $depth, string $stem, array $choices, int $correct, string $explanation, string $focus, string $unusedEvidence, ?array $calculation = null): array {
        return [
            'wave' => 2,
            'leaf' => $leaf,
            'material' => 'let-specialization-mathematics-application',
            'sources' => ['prc-let-enhanced-tos-2025', 'deped-math-curriculum-guide', 'openstax-algebra-and-trigonometry'],
            'depth' => $depth,
            'family' => $family,
            'stem' => $stem,
            'choices' => $choices,
            'correct' => $correct,
            'explanation' => $explanation,
            'focus' => $focus,
            'unused_family_evidence' => $unusedEvidence,
            'calculation' => $calculation,
            'specialization_id' => 'secondary-mathematics',
        ];
    };
    $generalEducation = $make('subject-verb-agreement-singular-subjects', 'ERROR_ANALYSIS_OR_REVISION', 3,
        'An editor is revising a science memo. The draft reads, “Checking each amended form before filing keep the audit trail reliable.” Which revision corrects the agreement while preserving the intended meaning?',
        [
            'Checking each amended form before filing keeps the audit trail reliable.',
            'Checking each amended form before filing keep the audit trail reliable.',
            'Checking each amended form before filing are keeping the audit trail reliable.',
            'Checking each amended form before filing have kept the audit trail reliable.',
        ], 0,
        'The gerund phrase “Checking each amended form before filing” functions as one singular subject, so it takes “keeps.” The nearby plural “forms” does not control the verb.',
        'Revise agreement when a gerund phrase is the singular subject, without letting a nearby plural noun control the verb.',
        'The five existing questions classify as interpretation or analysis and none asks the learner to revise agreement for a gerund phrase functioning as subject; this applies the leaf through a distinct sentence-editing family.');
    $generalEducation['material'] = 'let-english-grammar-foundation';
    $generalEducation['sources'] = ['deped-k12-english-curriculum-guide', 'purdue-owl-writing', 'openstax-reading-writing'];
    $generalEducation['specialization_id'] = null;
    return [
        $make('math-algebra-functions', 'QUANTITATIVE_APPLICATION', 3,
            'A spreadsheet applies the rule f(x) = 3x − 2 to each entered value. What output should it display when x = 5?',
            ['13', '15', '9', '17'], 0,
            'Substitute 5 for x, multiply first, then subtract 2: f(5) = 3(5) − 2 = 13. The other choices reflect omitting or misapplying part of the rule.',
            'Evaluate a function rule for a stated input and verify the output.',
            'The five existing leaf items cover one-output validity, table/rule translation, equivalent representations, input/output distinction, and relation validity; none evaluates a stated rule numerically.',
            ['family' => 'function_evaluation', 'values' => [3, 5, -2], 'expected' => 13, 'unit' => 'function output', 'rounding' => 'exact']),
        $make('math-algebra-patterns', 'QUANTITATIVE_APPLICATION', 3,
            'A sequence begins 4, 9, 14, 19, … and continues with the same rule. What is its eighth term?',
            ['39', '35', '40', '44'], 0,
            'The common difference is 5. From the first term to the eighth there are seven equal increases, so 4 + 7(5) = 39.',
            'Apply a verified arithmetic-pattern rule to determine a later term.',
            'The existing five items ask learners to identify, test, represent, or reject a pattern rule; none computes a specified later term from an explicit sequence.',
            ['family' => 'arithmetic_sequence_term', 'values' => [4, 5, 8], 'expected' => 39, 'unit' => 'term value', 'rounding' => 'exact']),
        $make('math-geometry-angle-relationships', 'MODEL_OR_REPRESENTATION_SELECTION', 3,
            'Two parallel lines are crossed by a transversal. An interior angle at the upper intersection measures 68°. What is the measure of its alternate interior angle at the lower intersection?',
            ['68°', '112°', '22°', '180°'], 0,
            'Alternate interior angles formed by parallel lines and a transversal are congruent, so the corresponding angle also measures 68°. The supplementary value belongs to a same-side interior pair.',
            'Select the parallel-line angle relationship that matches the described positions.',
            'The five existing items address complementary/supplementary angles, a linear pair, checking a shared vertex/line, and justifying a basic relationship; alternate-interior congruence for parallel lines is not among their tested propositions.'),
        $make('math-number-sense-fractions', 'QUANTITATIVE_APPLICATION', 3,
            'A school garden has 40 plots, and three-fifths are assigned to herbs. How many plots are assigned to herbs?',
            ['24 plots', '8 plots', '32 plots', '120 plots'], 0,
            'Three-fifths of 40 is (3 ÷ 5) × 40 = 24 plots. Eight uses only one of the five equal groups, while 32 uses the complementary four-fifths.',
            'Find a stated fractional part of a whole number of equal units.',
            'The five existing items address fraction operations, comparison, numerator/denominator meaning, ratio order, and range checking; none determines a fractional part of a counted set.',
            ['family' => 'fraction_of_a_set', 'values' => [3, 5, 40], 'expected' => 24, 'unit' => 'plots', 'rounding' => 'exact']),
        $make('math-problem-solving-modeling', 'QUANTITATIVE_APPLICATION', 3,
            'A copy center charges a fixed setup fee of ₱20 plus ₱3 per copy. If the total charge is ₱65, how many copies were made?',
            ['15 copies', '21 copies', '45 copies', '85 copies'], 0,
            'Let c be the number of copies. The situation gives 20 + 3c = 65, so c = (65 − 20) ÷ 3 = 15. The fixed fee must be removed before dividing by the per-copy charge.',
            'Translate a fixed-fee plus per-unit situation into a linear equation and solve for the unknown.',
            'The five existing items cover defining unknowns, choosing a rate relation, checking reasonableness, stating assumptions, and interpreting a computed result; none solves a fixed-plus-variable cost model.',
            ['family' => 'linear_model_solution', 'values' => [65, 20, 3], 'expected' => 15, 'unit' => 'copies', 'rounding' => 'exact']),
        $make('math-statistics-mean-median', 'RESULT_OR_DATA_INTERPRETATION', 3,
            'Daily wait times are 8, 9, 10, 12, and 44 minutes; the 44-minute wait resulted from one unusual disruption. Which summary measure best represents a typical day without being pulled upward by that extreme value?',
            ['The median, the middle observation after ordering.', 'The mean, the total divided by the number of observations.', 'The range, the difference between the largest and smallest values.', 'The maximum, the largest observation.'], 0,
            'The median is the middle observation (10 minutes) and remains less affected by one extreme high value. The mean is pulled upward, while range and maximum are not measures of central tendency.',
            'Choose a measure of center that fits a distribution with one extreme observation.',
            'The existing five leaf items calculate means, medians, or a total from a supplied mean; none interprets how a single extreme observation affects the choice of a typical-value measure.'),
        $generalEducation,
    ];
};

$canonicalJsonHash = static function (mixed $value): string {
    $normalize = static function (mixed $node) use (&$normalize): mixed {
        if (!is_array($node)) return $node;
        if (array_is_list($node)) return array_map($normalize, $node);
        ksort($node, SORT_STRING);
        foreach ($node as &$child) $child = $normalize($child);
        unset($child);
        return $node;
    };
    return hash('sha256', json_encode($normalize($value), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
};
$buildQuestion = static function (array $item, int $id, array $collections): array {
    $concept = TaxonomyStorageService::find('taxonomy/concepts', $item['leaf']);
    if (!is_array($concept)) throw new RuntimeException('Missing taxonomy leaf ' . $item['leaf']);
    $topic = TaxonomyStorageService::find('taxonomy/topics', (string) ($concept['topic_id'] ?? ''));
    if (!is_array($topic)) throw new RuntimeException('Missing topic for ' . $item['leaf']);
    $domain = TaxonomyStorageService::find('taxonomy/domains', (string) ($topic['domain_id'] ?? ''));
    if (!is_array($domain)) throw new RuntimeException('Missing domain for ' . $item['leaf']);
    $subjectId = (string) ($domain['subject_id'] ?? '');
    $subject = TaxonomyStorageService::find('subjects', $subjectId);
    $study = null;
    foreach ($collections['study-materials'] as $row) if (is_array($row) && (string) ($row['id'] ?? '') === $item['material']) $study = $row;
    if (!is_array($study) || (string) ($study['status'] ?? 'published') !== 'published') throw new RuntimeException('Unavailable study material ' . $item['material']);
    $materialSources = array_values(array_map('strval', (array) ($study['source_ids'] ?? [])));
    if ($materialSources === [] || array_diff($item['sources'], $materialSources) !== []) throw new RuntimeException('Question sources are not supported by ' . $item['material']);
    $question = [
        'id' => $id,
        'board' => 'let',
        'taxonomy' => [
            'board_id' => 'let',
            'subject_id' => $subjectId,
            'domain_id' => (string) $domain['id'],
            'topic_id' => (string) $topic['id'],
            'concept_id' => $item['leaf'],
        ],
        'status' => 'active',
        'source' => 'BoardPrep-authored',
        'difficulty' => $item['depth'] >= 4 ? 'hard' : 'medium',
        'type' => 'multiple_choice',
        'question' => $item['stem'],
        'choices' => $item['choices'],
        'answer' => $item['choices'][$item['correct']],
        'explanation' => $item['explanation'],
        'content_blocks' => [],
        'source_ids' => $item['sources'],
        'study_material_id' => $item['material'],
        'study_material_ids' => [$item['material']],
        'source_scope' => [$item['leaf']],
        'study_focus' => $item['focus'],
        'cognitive_intent' => $item['depth'] >= 4 ? 'analysis and interpretation' : 'application and interpretation',
        'multistep' => $item['depth'] >= 4,
        'data_interpretation' => $item['family'] === 'RESULT_OR_DATA_INTERPRETATION',
        'table_dependent' => false,
        'scenario_length' => 'standard',
        'numeric' => is_array($item['calculation'] ?? null),
        'calculation_required' => is_array($item['calculation'] ?? null),
        'quantitative_semantics' => is_array($item['calculation'] ?? null) ? 'CALCULATION_REQUIRED' : 'NON_NUMERICAL',
        'currentness_verified' => true,
        'version_sensitive' => false,
        'scenario_family' => 'b650-' . $item['leaf'] . '-' . strtolower($item['family']) . '-' . $id,
        'misconception_family' => $item['family'],
        'batch_marker' => 'batch650',
        'batch650' => [
            'wave' => (int) ($item['wave'] ?? 1),
            'leaf_id' => $item['leaf'],
            'content_family' => $item['family'],
            'specialization_id' => $item['specialization_id'] ?? null,
            'readiness_before' => 'READY_WITH_LIMITS',
            'readiness_evidence_hash_before' => (string) ($item['readiness_evidence_hash_before'] ?? ''),
            'native_question_count_before' => $item['pre_question_count'] ?? null,
            'remaining_headroom_before' => $item['remaining_headroom_before'] ?? null,
            'remaining_family_count_before' => $item['prior_content_family_count'] ?? null,
            'depth' => $item['depth'],
            'study_material_id' => $item['material'],
            'source_ids' => $item['sources'],
            'scope' => [$item['leaf']],
            'unused_family_evidence' => (string) ($item['unused_family_evidence'] ?? ''),
            'derivable' => true,
        ],
        'reuse' => [
            'reuse_policy' => 'REVIEW',
            'required_depth' => $item['depth'],
            'context_mode' => 'NEUTRAL',
            'metadata_status' => 'VERIFIED',
            'metadata_provenance' => [[
                'type' => 'OFFICIAL_SOURCE',
                'reference' => 'batch649-let-source-readiness:' . $item['leaf'],
                'note' => 'Question scope, depth, source, and study chain use the exact Batch649 READY_WITH_LIMITS leaf boundary.',
                'verified_status' => 'VERIFIED',
            ]],
        ],
    ];
    if (is_array($item['calculation'] ?? null)) {
        $question['calculation'] = $item['calculation'];
        $question['calculation_family'] = (string) ($item['calculation']['family'] ?? '');
    }
    return $question;
};
$consumeReadiness = static function (array $batchQuestions, array $inputCollections, array $baselineArtifact) use ($writeJson, $root): array {
    $baselineByLeaf = [];
    foreach ((array) ($baselineArtifact['let']['leaves'] ?? []) as $leafEvidence) {
        if (!is_array($leafEvidence) || !is_array($leafEvidence['readiness_record'] ?? null)) continue;
        $baselineByLeaf[(string) ($leafEvidence['leaf_id'] ?? '')] = $leafEvidence['readiness_record'];
    }
    $usedByLeaf = [];
    $familiesByLeaf = [];
    foreach ($batchQuestions as $question) {
        $leaf = (string) ($question['taxonomy']['concept_id'] ?? '');
        if ($leaf === '') continue;
        $usedByLeaf[$leaf] = ($usedByLeaf[$leaf] ?? 0) + 1;
        $family = (string) ($question['batch650']['content_family'] ?? '');
        if ($family !== '') $familiesByLeaf[$leaf][$family] = true;
    }
    $postCounts = SourceReadinessService::nativeLeafCounts($inputCollections, 'let');
    $records = [];
    foreach ((array) ($inputCollections['source-readiness-let'] ?? []) as $record) {
        if (!is_array($record)) continue;
        $leaf = (string) ($record['leaf_id'] ?? '');
        $used = (int) ($usedByLeaf[$leaf] ?? 0);
        if ($used > 0) {
            $baselineRecord = $baselineByLeaf[$leaf] ?? $record;
            $record['current_question_count'] = (int) ($postCounts[$leaf] ?? 0);
            $record['deficit_to_5'] = max(0, 5 - $record['current_question_count']);
            $record['deficit_to_10'] = max(0, 10 - $record['current_question_count']);
            $record['deficit_to_20'] = max(0, 20 - $record['current_question_count']);
            $record['safe_headroom_min'] = max(0, (int) ($baselineRecord['safe_headroom_min'] ?? 0) - $used);
            $record['safe_headroom_preferred'] = max(0, (int) ($baselineRecord['safe_headroom_preferred'] ?? 0) - $used);
            $record['safe_headroom_max'] = max(0, (int) ($baselineRecord['safe_headroom_max'] ?? 0) - $used);
            $record['max_new_questions'] = max(0, (int) ($baselineRecord['max_new_questions'] ?? 0) - $used);
            $record['planned_new_questions'] = 0;
            $remainingFamilies = (array) ($baselineRecord['distinct_content_families'] ?? $record['distinct_content_families'] ?? []);
            foreach (array_keys($familiesByLeaf[$leaf] ?? []) as $family) {
                $remainingFamilies = array_values(array_filter($remainingFamilies, static fn(string $value): bool => $value !== $family));
            }
            $record['distinct_content_families'] = $remainingFamilies;
            sort($record['distinct_content_families'], SORT_STRING);
            $record['distinct_content_family_count'] = count($record['distinct_content_families']);
            $record['readiness'] = (string) ($baselineRecord['readiness'] ?? $record['readiness'] ?? 'REVIEW');
            $record['readiness_reason'] = (string) ($baselineRecord['readiness_reason'] ?? $record['readiness_reason'] ?? '');
            $record['review_reason'] = (string) ($baselineRecord['review_reason'] ?? $record['review_reason'] ?? '');
            if ((int) $record['max_new_questions'] === 0) {
                $record['readiness'] = 'REVIEW';
                $record['review_reason'] = 'Batch650 consumed the Batch649 evidence-bounded high-value family/headroom for this leaf; a fresh semantic family and headroom gate is required before further authoring.';
                $record['readiness_reason'] = 'Prior authoring permission was exhausted by the bounded Batch650 production allocation.';
            }
            $record['evidence_hash'] = SourceReadinessRecord::evidenceHash($record);
        }
        $records[] = $record;
    }
    if (count($records) !== 205) throw new RuntimeException('Readiness consumption would not preserve all 205 LET records.');
    $writeJson($root . '/storage/source-readiness-let.json', $records);
    return $records;
};

$existingQuestions = App::storage()->all('questions');
$allCollectionsBefore = $collections();
$b649 = $readJson($batch649Path);
$plan = $b649['let']['wave1'] ?? [];
$definitions = $waveOneDefinitions();
$waveTwoDefinitions = $waveTwoDefinitions();
$command = (string) ($argv[1] ?? '--audit');
if (!in_array($command, ['--audit', '--wave1', '--finalize-wave1', '--wave2', '--finalize-wave2'], true)) $fail('usage: php tools/batch650-let-expansion-audit.php [--audit|--wave1|--finalize-wave1|--wave2|--finalize-wave2]');
if ((int) ($b649['batch'] ?? 0) !== 649) $fail('Expected the permanent Batch649 readiness artifact');
if (($plan['evidence_supported_planned'] ?? null) !== 33 || count((array) ($plan['candidates'] ?? [])) !== 32 || count($definitions) !== 33 || count($waveTwoDefinitions) !== 7) {
    $fail('Exact Batch649 Wave1 plan/definition size mismatch');
}
$planByLeaf = [];
foreach ((array) $plan['candidates'] as $candidate) $planByLeaf[(string) $candidate['leaf_id']] = $candidate;
$candidateCounts = [];
foreach ($definitions as $item) $candidateCounts[$item['leaf']] = ($candidateCounts[$item['leaf']] ?? 0) + 1;
ksort($candidateCounts, SORT_STRING);
$expectedCounts = [];
foreach ($planByLeaf as $leaf => $candidate) $expectedCounts[$leaf] = (int) ($candidate['planned_new'] ?? 0);
ksort($expectedCounts, SORT_STRING);
if ($candidateCounts !== $expectedCounts) $fail('Wave1 candidate definitions do not match the stored per-leaf plan');

$readiness = array_values(array_filter(App::storage()->all('source-readiness-let'), 'is_array'));
$readinessByLeaf = [];
foreach ($readiness as $record) $readinessByLeaf[(string) ($record['leaf_id'] ?? '')] = $record;
if (count($readinessByLeaf) !== 205) $fail('Expected exactly 205 permanent LET readiness records');
$byId = [];
$maxId = 0;
foreach ($existingQuestions as $question) {
    if (!is_array($question) || !is_numeric($question['id'] ?? null)) continue;
    $id = (string) $question['id'];
    $byId[$id] = $question;
    $maxId = max($maxId, (int) $id);
}
$baselineQuestions = array_values(array_filter($existingQuestions, static fn(array $q): bool => ($q['batch_marker'] ?? '') !== 'batch650'));
$questionCountBefore = (int) ($b649['global_baseline']['total_canonical'] ?? count($baselineQuestions));
$baselineQuestionHash = $canonicalJsonHash($baselineQuestions);
$baselineLetCount = (int) ($b649['let']['baseline']['native_canonical'] ?? count(array_filter($baselineQuestions, static fn(array $q): bool => strtolower((string) ($q['taxonomy']['board_id'] ?? '')) === 'let' && strtolower((string) ($q['status'] ?? 'active')) !== 'archived')));
$baselineBatchIds = [];
foreach ($existingQuestions as $q) if (($q['batch_marker'] ?? '') === 'batch650') $baselineBatchIds[(string) ($q['id'] ?? '')] = true;
$baselineEligibilityRows = array_values(array_filter($allCollectionsBefore['question-eligibility'], static function (array $row) use ($baselineBatchIds): bool {
    $questionIds = array_values(array_filter(array_map('strval', (array) ($row['question_ids'] ?? [])), static fn(string $id): bool => !isset($baselineBatchIds[$id])));
    return $questionIds !== [];
}));
foreach ($baselineEligibilityRows as &$row) {
    $row['question_ids'] = array_values(array_filter(array_map('strval', (array) ($row['question_ids'] ?? [])), static fn(string $id): bool => !isset($baselineBatchIds[$id])));
    foreach (array_keys((array) ($row['question_mappings'] ?? [])) as $mappedId) if (isset($baselineBatchIds[(string) $mappedId])) unset($row['question_mappings'][$mappedId]);
}
unset($row);
$baselineEligibilityHash = $canonicalJsonHash($baselineEligibilityRows);

if ($command === '--wave1') {
    if (count(array_filter($existingQuestions, static fn(array $q): bool => ($q['batch_marker'] ?? '') === 'batch650')) !== 0) $fail('Batch650 questions already exist; Wave1 is not rerun');
    $drafts = [];
    $nextId = $maxId + 1;
    $draftNormalized = [];
    $existingNormalized = [];
    foreach ($existingQuestions as $existingQuestion) {
        $normalizedStem = strtolower(trim((string) preg_replace('/\s+/', ' ', (string) ($existingQuestion['question'] ?? ''))));
        if ($normalizedStem !== '') $existingNormalized[$normalizedStem] = (string) ($existingQuestion['id'] ?? '');
    }
    foreach ($definitions as $offset => $item) {
        $record = $readinessByLeaf[$item['leaf']] ?? null;
        $candidate = $planByLeaf[$item['leaf']] ?? null;
        if (!is_array($record) || !is_array($candidate) || ($record['readiness'] ?? '') !== 'READY_WITH_LIMITS') $fail('Wave1 leaf is no longer authorable: ' . $item['leaf']);
        if (($record['evidence_hash'] ?? '') !== ($candidate['readiness_record_evidence_hash'] ?? $record['evidence_hash'])) $fail('Wave1 readiness evidence hash changed: ' . $item['leaf']);
        if (!in_array($item['leaf'], (array) ($record['allowed_scope'] ?? []), true) || array_intersect($item['leaf'] === '' ? [] : [$item['leaf']], (array) ($record['excluded_scope'] ?? [])) !== []) $fail('Wave1 leaf outside explicit scope: ' . $item['leaf']);
        if ((int) $item['depth'] < (int) $record['supported_depth_min'] || (int) $item['depth'] > (int) $record['supported_depth_max']) $fail('Wave1 depth outside support: ' . $item['leaf']);
        if (!in_array($item['family'], (array) ($record['distinct_content_families'] ?? []), true)) $fail('Wave1 family not in readiness evidence: ' . $item['leaf'] . '/' . $item['family']);
        if (count((array) ($record['source_ids'] ?? [])) < count(array_unique($item['sources'])) || array_diff($item['sources'], (array) $record['source_ids']) !== []) $fail('Wave1 source ids outside readiness evidence: ' . $item['leaf']);
        if (array_diff([$item['material']], (array) ($record['study_material_ids'] ?? [])) !== []) $fail('Wave1 study material outside readiness evidence: ' . $item['leaf']);
        if (($record['study_source_traceability'] ?? '') !== 'COMPLETE' || !in_array((string) ($record['source_currentness'] ?? ''), ['CURRENT', 'CURRENT_WITH_LIMITS', 'NOT_TIME_SENSITIVE'], true)) $fail('Wave1 traceability/currentness gate failed: ' . $item['leaf']);
        if (($candidate['readiness'] ?? '') !== 'READY_WITH_LIMITS' || (int) ($candidate['headroom']['max'] ?? 0) < $candidateCounts[$item['leaf']]) $fail('Wave1 stored plan exceeds readiness/headroom: ' . $item['leaf']);
        $question = $buildQuestion($item, $nextId + $offset, $allCollectionsBefore);
        $validation = QuestionService::validate($question);
        $readinessGate = SourceReadinessService::validateQuestionForCreate($question, $allCollectionsBefore);
        if (($validation['valid'] ?? false) !== true || ($readinessGate['valid'] ?? false) !== true) {
            $fail('Wave1 authoring validator rejected ' . $question['id'] . ': ' . json_encode(['question' => $validation, 'readiness' => $readinessGate], JSON_UNESCAPED_SLASHES));
        }
        $normalized = strtolower(trim((string) preg_replace('/\s+/', ' ', $question['question'])));
        if (isset($draftNormalized[$normalized]) || isset($existingNormalized[$normalized])) $fail('Duplicate normalized Wave1 stem: ' . $question['id']);
        $draftNormalized[$normalized] = true;
        $drafts[] = $question;
    }
    foreach ($drafts as $question) {
        $saved = QuestionService::save($question);
        $taxonomy = $saved['taxonomy'];
        $mapping = QuestionEligibilityService::addMapping($saved, [
            'target_board' => 'let',
            'target_subject' => $taxonomy['subject_id'],
            'target_domain' => $taxonomy['domain_id'],
            'target_topic' => $taxonomy['topic_id'],
            'target_concept' => $taxonomy['concept_id'],
            'blueprint_node' => 'general-education',
        ]);
        if (($mapping['valid'] ?? false) !== true) $fail('LET eligibility mapping failed for ' . $saved['id'] . ': ' . json_encode($mapping, JSON_UNESCAPED_SLASHES));
        App::storage()->create('study-question-links', [
            'id' => 'study-link-b650-' . $saved['id'],
            'material_id' => (string) $saved['study_material_id'],
            'question_id' => (string) $saved['id'],
            'source_ids' => array_values($saved['source_ids']),
        ]);
    }
    $existingQuestions = App::storage()->all('questions');
    $consumeReadiness($drafts, $collections(), $b649);
} elseif ($command === '--finalize-wave1') {
    $persisted = array_values(array_filter($existingQuestions, static fn(array $q): bool => ($q['batch_marker'] ?? '') === 'batch650' && (int) ($q['batch650']['wave'] ?? 0) === 1));
    if (count($persisted) !== 33) $fail('Wave1 recovery expected 33 already-saved questions; found ' . count($persisted));
    $maxBaselineId = 0;
    $normalizedExisting = [];
    foreach ($baselineQuestions as $oldQuestion) {
        if (is_numeric($oldQuestion['id'] ?? null)) $maxBaselineId = max($maxBaselineId, (int) $oldQuestion['id']);
        $stem = strtolower(trim((string) preg_replace('/\s+/', ' ', (string) ($oldQuestion['question'] ?? ''))));
        if ($stem !== '') $normalizedExisting[$stem] = true;
    }
    $persistedById = [];
    foreach ($persisted as $question) $persistedById[(string) ($question['id'] ?? '')] = $question;
    $currentLinks = [];
    foreach ($allCollectionsBefore['study-question-links'] as $link) if (is_array($link)) $currentLinks[(string) ($link['question_id'] ?? '')][] = $link;
    $mappedIds = [];
    foreach ($allCollectionsBefore['question-eligibility'] as $row) {
        if (!is_array($row)) continue;
        $examIds = array_map('strtolower', array_map('strval', (array) ($row['exam_ids'] ?? [])));
        foreach ((array) ($row['question_ids'] ?? []) as $mappedId) {
            $mappedId = (string) $mappedId;
            $overrideExams = array_map('strtolower', array_map('strval', (array) ($row['question_mappings'][$mappedId]['exam_ids'] ?? [])));
            if (in_array('let', $examIds, true) || in_array('let', $overrideExams, true)) $mappedIds[$mappedId] = true;
        }
    }
    foreach ($definitions as $offset => $item) {
        $expectedId = (string) ($maxBaselineId + 1 + $offset);
        $question = $persistedById[$expectedId] ?? null;
        if (!is_array($question)) $fail('Wave1 saved ID sequence is not contiguous at ' . $expectedId);
        if ((string) ($question['taxonomy']['concept_id'] ?? '') !== $item['leaf']
            || (string) ($question['question'] ?? '') !== $item['stem']
            || (string) ($question['study_material_id'] ?? '') !== $item['material']
            || array_values(array_map('strval', (array) ($question['source_ids'] ?? []))) !== array_values($item['sources'])
            || array_values(array_map('strval', (array) ($question['source_scope'] ?? []))) !== [$item['leaf']]
            || (string) ($question['batch650']['content_family'] ?? '') !== $item['family']
            || (int) ($question['batch650']['depth'] ?? 0) !== (int) $item['depth']) {
            $fail('Wave1 saved question does not match its prevalidated definition: ' . $expectedId);
        }
        $schema = QuestionService::validate($question);
        if (($schema['valid'] ?? false) !== true) $fail('Saved Wave1 question schema invalid for ' . $expectedId . ': ' . json_encode($schema));
        if (!isset($mappedIds[$expectedId])) $fail('Saved Wave1 question lacks direct LET eligibility: ' . $expectedId);
        $linked = false;
        foreach ($currentLinks[$expectedId] ?? [] as $link) {
            if ((string) ($link['material_id'] ?? '') === $item['material']
                && array_diff($item['sources'], array_map('strval', (array) ($link['source_ids'] ?? []))) === []) $linked = true;
        }
        if (!$linked) $fail('Saved Wave1 question lacks a source-linked study record: ' . $expectedId);
        $stem = strtolower(trim((string) preg_replace('/\s+/', ' ', (string) $question['question'])));
        if (isset($normalizedExisting[$stem])) $fail('Saved Wave1 question duplicates an existing normalized stem: ' . $expectedId);
        $normalizedExisting[$stem] = true;
    }
    $consumeReadiness($persisted, $collections(), $b649);
    $readiness = array_values(array_filter(App::storage()->all('source-readiness-let'), 'is_array'));
    $readinessByLeaf = [];
    foreach ($readiness as $record) $readinessByLeaf[(string) ($record['leaf_id'] ?? '')] = $record;
} elseif (in_array($command, ['--wave2', '--finalize-wave2'], true)) {
    $persistedWave2 = array_values(array_filter($existingQuestions, static fn(array $q): bool => ($q['batch_marker'] ?? '') === 'batch650' && (int) ($q['batch650']['wave'] ?? 0) === 2));
    if (count($persistedWave2) > count($waveTwoDefinitions)) $fail('More persisted Wave2 questions exist than the current seven-item evidence-gated cohort.');
    $baselineMaxId = 0;
    $baselineStems = [];
    foreach ($existingQuestions as $oldQuestion) {
        if (($oldQuestion['batch_marker'] ?? '') === 'batch650' && (int) ($oldQuestion['batch650']['wave'] ?? 0) === 2) continue;
        if (is_numeric($oldQuestion['id'] ?? null)) $baselineMaxId = max($baselineMaxId, (int) $oldQuestion['id']);
        $normalized = strtolower(trim((string) preg_replace('/\s+/', ' ', (string) ($oldQuestion['question'] ?? ''))));
        if ($normalized !== '') $baselineStems[$normalized] = true;
    }
    $candidateByLeaf = [];
    foreach ((array) ($b649['let']['wave2']['candidates'] ?? []) as $candidate) $candidateByLeaf[(string) ($candidate['leaf_id'] ?? '')] = $candidate;
    $leafEvidenceById = [];
    foreach ((array) ($b649['let']['leaves'] ?? []) as $evidence) $leafEvidenceById[(string) ($evidence['leaf_id'] ?? '')] = $evidence;
    $flatReviewLeaves = [];
    foreach ((array) ($b649['let']['shared_placements']['flat_shared_rows'] ?? []) as $placement) {
        if (is_array($placement) && (string) ($placement['readiness'] ?? '') === 'REVIEW') $flatReviewLeaves[(string) ($placement['leaf_id'] ?? '')] = true;
    }
    $w1ByLeaf = [];
    foreach ($existingQuestions as $question) {
        if (($question['batch_marker'] ?? '') === 'batch650' && (int) ($question['batch650']['wave'] ?? 0) === 1) {
            $leaf = (string) ($question['taxonomy']['concept_id'] ?? '');
            $w1ByLeaf[$leaf] = ($w1ByLeaf[$leaf] ?? 0) + 1;
        }
    }
    $persistedById = [];
    foreach ($persistedWave2 as $question) $persistedById[(string) ($question['id'] ?? '')] = $question;
    $persistedWave2ByLeaf = [];
    foreach ($persistedWave2 as $question) {
        $leaf = (string) ($question['taxonomy']['concept_id'] ?? '');
        $persistedWave2ByLeaf[$leaf] = ($persistedWave2ByLeaf[$leaf] ?? 0) + 1;
    }
    $currentLinks = [];
    foreach ($allCollectionsBefore['study-question-links'] as $link) if (is_array($link)) $currentLinks[(string) ($link['question_id'] ?? '')][] = $link;
    $mappedIds = [];
    foreach ($allCollectionsBefore['question-eligibility'] as $row) {
        if (!is_array($row)) continue;
        $examIds = array_map('strtolower', array_map('strval', (array) ($row['exam_ids'] ?? [])));
        foreach ((array) ($row['question_ids'] ?? []) as $mappedId) {
            $mappedId = (string) $mappedId;
            $overrideExams = array_map('strtolower', array_map('strval', (array) ($row['question_mappings'][$mappedId]['exam_ids'] ?? [])));
            if (in_array('let', $examIds, true) || in_array('let', $overrideExams, true)) $mappedIds[$mappedId] = true;
        }
    }
    $drafts = [];
    $preWave2Counts = SourceReadinessService::nativeLeafCounts($allCollectionsBefore, 'let');
    $knownSourceIds = array_fill_keys(array_map(static fn(array $row): string => (string) ($row['id'] ?? ''), array_filter($allCollectionsBefore['sources'], 'is_array')), true);
    $knownMaterialById = [];
    foreach ($allCollectionsBefore['study-materials'] as $material) if (is_array($material)) $knownMaterialById[(string) ($material['id'] ?? '')] = $material;
    $normalizedWave2 = [];
    foreach ($waveTwoDefinitions as $offset => $originalItem) {
        $item = $originalItem;
        $leaf = (string) $item['leaf'];
        $currentRecord = $readinessByLeaf[$leaf] ?? null;
        $candidate = $candidateByLeaf[$leaf] ?? null;
        $leafEvidence = $leafEvidenceById[$leaf] ?? null;
        if (!is_array($currentRecord) || !is_array($candidate) || !is_array($leafEvidence)) $fail('Wave2 candidate lacks current readiness or Batch649 evidence: ' . $leaf);
        $expectedId = (string) ($baselineMaxId + 1 + $offset);
        $alreadyPersisted = isset($persistedById[$expectedId]);
        $baselineRecord = (array) ($leafEvidence['readiness_record'] ?? []);
        $record = $alreadyPersisted ? $baselineRecord : $currentRecord;
        $preCount = (int) ($preWave2Counts[$leaf] ?? -1) - (int) ($persistedWave2ByLeaf[$leaf] ?? 0);
        $remainingEvidenceHeadroom = max(0, (int) ($leafEvidence['readiness_record']['max_new_questions'] ?? 0) - (int) ($w1ByLeaf[$leaf] ?? 0));
        $remainingSafeHeadroom = max(0, (int) ($leafEvidence['readiness_record']['safe_headroom_max'] ?? 0) - (int) ($w1ByLeaf[$leaf] ?? 0));
        if (($record['readiness'] ?? '') !== 'READY_WITH_LIMITS' || $remainingEvidenceHeadroom < 1 || $remainingSafeHeadroom < 1 || $preCount >= 10) $fail('Fresh Wave2 readiness/headroom/count gate failed: ' . $leaf);
        if ((string) ($item['specialization_id'] ?? '') !== '') {
            if (!in_array('SPECIALIZATION', (array) ($record['scope_component_ids'] ?? []), true) || !in_array((string) $item['specialization_id'], (array) ($record['scope_specialization_ids'] ?? []), true)) $fail('Wave2 leaf is not native to the selected specialization: ' . $leaf);
        } elseif (!in_array('GENERAL_EDUCATION', (array) ($record['scope_component_ids'] ?? []), true)) {
            $fail('Wave2 non-specialization leaf is not explicitly within General Education scope: ' . $leaf);
        }
        if (isset($flatReviewLeaves[$leaf]) || !in_array($leaf, (array) ($record['allowed_scope'] ?? []), true) || in_array($leaf, (array) ($record['excluded_scope'] ?? []), true)) $fail('Wave2 leaf is shared/review or outside explicit authoring scope: ' . $leaf);
        if (!in_array($item['family'], (array) ($record['distinct_content_families'] ?? []), true) || !in_array($item['family'], (array) ($candidate['distinct_families'] ?? []), true)) $fail('Wave2 family is not in the remaining Batch649-supported family set: ' . $leaf . '/' . $item['family']);
        if (trim((string) $item['unused_family_evidence']) === '') $fail('Wave2 family lacks an item-level unused-family review: ' . $leaf);
        $repeatRisk = strtoupper((string) ($leafEvidence['repeat_risk'] ?? 'UNKNOWN'));
        if (!in_array($repeatRisk, ['LOW', 'MODERATE'], true)) $fail('Wave2 leaf repeat-risk gate failed: ' . $leaf . '/' . $repeatRisk);
        if (!in_array((string) ($record['source_authority'] ?? ''), ['STRONG', 'ADEQUATE'], true)
            || !in_array((string) ($record['source_currentness'] ?? ''), ['CURRENT', 'CURRENT_WITH_LIMITS', 'NOT_TIME_SENSITIVE'], true)
            || !in_array((string) ($record['source_conflict_status'] ?? ''), ['NONE', 'RESOLVED'], true)
            || !in_array((string) ($record['study_coverage'] ?? ''), ['COMPLETE', 'SUFFICIENT'], true)
            || (string) ($record['study_source_traceability'] ?? '') !== 'COMPLETE') $fail('Wave2 source/currentness/study gate failed: ' . $leaf);
        if ((int) $item['depth'] < (int) ($record['supported_depth_min'] ?? 1) || (int) $item['depth'] > (int) ($record['supported_depth_max'] ?? 5)) $fail('Wave2 supported-depth gate failed: ' . $leaf);
        if (array_diff($item['sources'], (array) ($record['source_ids'] ?? [])) !== [] || array_diff($item['sources'], array_keys($knownSourceIds)) !== []) $fail('Wave2 source ids do not resolve within current readiness: ' . $leaf);
        if (array_diff([$item['material']], (array) ($record['study_material_ids'] ?? [])) !== []) $fail('Wave2 study material is outside the readiness record: ' . $leaf);
        $material = $knownMaterialById[(string) $item['material']] ?? null;
        if (!is_array($material) || (string) ($material['status'] ?? '') !== 'published' || array_diff($item['sources'], array_map('strval', (array) ($material['source_ids'] ?? []))) !== []) $fail('Wave2 study material/source chain does not resolve: ' . $leaf);
        $item['readiness_evidence_hash_before'] = $alreadyPersisted
            ? (string) ($persistedById[$expectedId]['batch650']['readiness_evidence_hash_before'] ?? '')
            : (string) ($record['evidence_hash'] ?? '');
        $item['pre_question_count'] = $preCount;
        $item['prior_content_family_count'] = (int) ($baselineRecord['distinct_content_family_count'] ?? 0);
        $item['remaining_headroom_before'] = $remainingSafeHeadroom;
        $question = $buildQuestion($item, (int) $expectedId, $allCollectionsBefore);
        $item['question'] = $question;
        $independentlyCalculated = match ((string) ($item['calculation']['family'] ?? '')) {
            'function_evaluation' => $item['calculation']['values'][0] * $item['calculation']['values'][1] + $item['calculation']['values'][2],
            'arithmetic_sequence_term' => $item['calculation']['values'][0] + ($item['calculation']['values'][2] - 1) * $item['calculation']['values'][1],
            'fraction_of_a_set' => ($item['calculation']['values'][0] / $item['calculation']['values'][1]) * $item['calculation']['values'][2],
            'linear_model_solution' => ($item['calculation']['values'][0] - $item['calculation']['values'][1]) / $item['calculation']['values'][2],
            default => null,
        };
        if (is_array($item['calculation']) && ($independentlyCalculated === null || (float) $independentlyCalculated !== (float) $item['calculation']['expected'] || (string) $question['answer'] !== (string) $item['calculation']['expected'] && !str_starts_with((string) $question['answer'], (string) $item['calculation']['expected'] . ' '))) $fail('Wave2 calculation recomputation/answer mismatch: ' . $leaf);
        $validation = QuestionService::validate($question);
        $readinessGate = $alreadyPersisted ? ['valid' => true, 'errors' => []] : SourceReadinessService::validateQuestionForCreate($question, $allCollectionsBefore);
        if (($validation['valid'] ?? false) !== true || ($readinessGate['valid'] ?? false) !== true) $fail('Wave2 item rejected before persistence: ' . $leaf . ' ' . json_encode(['question' => $validation, 'readiness' => $readinessGate], JSON_UNESCAPED_SLASHES));
        $normalized = strtolower(trim((string) preg_replace('/\s+/', ' ', (string) $question['question'])));
        if (isset($baselineStems[$normalized]) || isset($normalizedWave2[$normalized])) $fail('Wave2 normalized duplicate: ' . $leaf);
        $normalizedWave2[$normalized] = true;
        if ($alreadyPersisted) {
            $stored = $persistedById[$expectedId];
            if ((string) ($stored['question'] ?? '') !== (string) $item['stem']
                || (string) ($stored['taxonomy']['concept_id'] ?? '') !== $leaf
                || (string) ($stored['batch650']['content_family'] ?? '') !== (string) $item['family']
                || (string) ($stored['batch650']['readiness_evidence_hash_before'] ?? '') !== (string) ($baselineRecord['evidence_hash'] ?? '')
                || (string) ($stored['study_material_id'] ?? '') !== (string) $item['material']
                || array_values(array_map('strval', (array) ($stored['source_ids'] ?? []))) !== array_values($item['sources'])
                || array_values(array_map('strval', (array) ($stored['source_scope'] ?? []))) !== [$leaf]
                || (int) ($stored['batch650']['depth'] ?? 0) !== (int) $item['depth']) $fail('Interrupted Wave2 recovery found a changed persisted item: ' . $expectedId);
            if (QuestionService::validate($stored)['valid'] !== true || !isset($mappedIds[$expectedId])) $fail('Interrupted Wave2 persisted item failed schema/eligibility validation: ' . $expectedId);
            $linked = false;
            foreach ($currentLinks[$expectedId] ?? [] as $link) if ((string) ($link['material_id'] ?? '') === (string) $item['material'] && array_diff($item['sources'], array_map('strval', (array) ($link['source_ids'] ?? []))) === []) $linked = true;
            if (!$linked) $fail('Interrupted Wave2 item has no source-linked study trace: ' . $expectedId);
        } else {
            $drafts[] = $item;
        }
    }
    if (count($persistedWave2) === 0 && $command === '--finalize-wave2') $fail('Wave2 recovery has no persisted questions; use --wave2 to begin the bounded production cohort.');
    foreach ($drafts as $item) {
        $saved = QuestionService::save($item['question']);
        $taxonomy = $saved['taxonomy'];
        $mapping = QuestionEligibilityService::addMapping($saved, [
            'target_board' => 'let',
            'target_subject' => $taxonomy['subject_id'],
            'target_domain' => $taxonomy['domain_id'],
            'target_topic' => $taxonomy['topic_id'],
            'target_concept' => $taxonomy['concept_id'],
            'blueprint_node' => (string) ($item['specialization_id'] ?? 'general-education'),
            'target_specialization_id' => $item['specialization_id'] ?? null,
        ]);
        if (($mapping['valid'] ?? false) !== true) $fail('Wave2 LET specialization eligibility failed for ' . $saved['id'] . ': ' . json_encode($mapping, JSON_UNESCAPED_SLASHES));
        App::storage()->create('study-question-links', [
            'id' => 'study-link-b650-' . $saved['id'],
            'material_id' => (string) $saved['study_material_id'],
            'question_id' => (string) $saved['id'],
            'source_ids' => array_values($saved['source_ids']),
        ]);
    }
    $allBatchQuestions = array_values(array_filter(App::storage()->all('questions'), static fn(array $q): bool => ($q['batch_marker'] ?? '') === 'batch650'));
    $consumeReadiness($allBatchQuestions, $collections(), $b649);
    $readiness = array_values(array_filter(App::storage()->all('source-readiness-let'), 'is_array'));
    $readinessByLeaf = [];
    foreach ($readiness as $record) $readinessByLeaf[(string) ($record['leaf_id'] ?? '')] = $record;
}

$afterCollections = $collections();
$questionsAfter = $afterCollections['questions'];
$newQuestions = array_values(array_filter($questionsAfter, static fn(array $q): bool => ($q['batch_marker'] ?? '') === 'batch650'));
$newIds = array_map(static fn(array $q): string => (string) $q['id'], $newQuestions);
sort($newIds, SORT_NUMERIC);
$baseIds = array_fill_keys(array_map('strval', array_column($existingQuestions, 'id')), true);
if ($command === '--audit' && $newIds === []) {
    $existingQuestions = App::storage()->all('questions');
    $questionsAfter = $existingQuestions;
    $newQuestions = array_values(array_filter($questionsAfter, static fn(array $q): bool => ($q['batch_marker'] ?? '') === 'batch650'));
    $newIds = array_map(static fn(array $q): string => (string) $q['id'], $newQuestions);
    sort($newIds, SORT_NUMERIC);
    $baseIds = [];
}
$wave1Questions = array_values(array_filter($newQuestions, static fn(array $q): bool => (int) ($q['batch650']['wave'] ?? 0) === 1));
$wave2Questions = array_values(array_filter($newQuestions, static fn(array $q): bool => (int) ($q['batch650']['wave'] ?? 0) === 2));
$expectedWave1Total = (int) ($plan['evidence_supported_planned'] ?? 0);
$wave1Leaves = array_values(array_unique(array_map(static fn(array $q): string => (string) ($q['taxonomy']['concept_id'] ?? ''), $wave1Questions)));
$wave2Leaves = array_values(array_unique(array_map(static fn(array $q): string => (string) ($q['taxonomy']['concept_id'] ?? ''), $wave2Questions)));
$wave1Defects = [];
$wave2Defects = [];
$linksByQuestion = [];
foreach ($afterCollections['study-question-links'] as $link) if (is_array($link)) $linksByQuestion[(string) ($link['question_id'] ?? '')][] = $link;
$sourceById = [];
foreach ($afterCollections['sources'] as $source) if (is_array($source)) $sourceById[(string) ($source['id'] ?? '')] = $source;
$materialById = [];
foreach ($afterCollections['study-materials'] as $material) if (is_array($material)) $materialById[(string) ($material['id'] ?? '')] = $material;
$deriver = new FlashcardDerivationService(App::container()->get(QuestionRepository::class));
$flashcards = 0;
$traceability = 0;
$calculations = 0;
$forbiddenAuthoring = 0;
$normalizedStemCounts = [];
foreach ($questionsAfter as $storedQuestion) {
    if (!is_array($storedQuestion)) continue;
    $normalizedStem = strtolower(trim((string) preg_replace('/\s+/', ' ', (string) ($storedQuestion['question'] ?? ''))));
    if ($normalizedStem !== '') $normalizedStemCounts[$normalizedStem] = ($normalizedStemCounts[$normalizedStem] ?? 0) + 1;
}
foreach ($newQuestions as $question) {
    $waveNumber = (int) ($question['batch650']['wave'] ?? 0);
    if ($waveNumber === 1) $questionDefects = &$wave1Defects;
    elseif ($waveNumber === 2) $questionDefects = &$wave2Defects;
    else $fail('Batch650 question has an unknown wave: ' . (string) ($question['id'] ?? ''));
    $id = (string) $question['id'];
    $leaf = (string) ($question['taxonomy']['concept_id'] ?? '');
    $readiness = $readinessByLeaf[$leaf] ?? [];
    $depth = (int) ($question['reuse']['required_depth'] ?? $question['batch650']['depth'] ?? 0);
    $studyId = (string) ($question['study_material_id'] ?? '');
    $material = $materialById[$studyId] ?? null;
    $scope = (array) ($question['source_scope'] ?? []);
    $sources = array_values(array_map('strval', (array) ($question['source_ids'] ?? [])));
    if (!is_array($material) || !in_array($studyId, (array) ($readiness['study_material_ids'] ?? []), true)
        || ($readiness['study_source_traceability'] ?? '') !== 'COMPLETE'
        || array_diff($sources, array_map('strval', (array) ($material['source_ids'] ?? []))) !== []
        || array_diff($sources, array_keys($sourceById)) !== []
        || array_diff($scope, (array) ($readiness['allowed_scope'] ?? [])) !== []
        || $depth < (int) ($readiness['supported_depth_min'] ?? 1)
        || $depth > (int) ($readiness['supported_depth_max'] ?? 5)) {
        $questionDefects[] = ['id' => $id, 'code' => 'SOURCE_STUDY_SCOPE_DEPTH'];
    } else {
        $linked = false;
        foreach ($linksByQuestion[$id] ?? [] as $link) {
            if ((string) ($link['material_id'] ?? '') === $studyId && array_intersect($sources, (array) ($link['source_ids'] ?? [])) !== []) $linked = true;
        }
        if (!$linked) $questionDefects[] = ['id' => $id, 'code' => 'STUDY_TRACEABILITY'];
        else $traceability++;
    }
    if (QuestionService::validate($question)['valid'] !== true) $questionDefects[] = ['id' => $id, 'code' => 'QUESTION_STRUCTURED_VALIDATION'];
    $normalizedStem = strtolower(trim((string) preg_replace('/\s+/', ' ', (string) ($question['question'] ?? ''))));
    if (($normalizedStemCounts[$normalizedStem] ?? 0) !== 1) $questionDefects[] = ['id' => $id, 'code' => 'NORMALIZED_STEM_DUPLICATE'];
    if (is_array($question['calculation'] ?? null) || ($question['calculation_required'] ?? false) === true) $calculations++;
    if ((bool) ($question['calculation_required'] ?? false) === true) {
        $payload = is_array($question['calculation'] ?? null) ? $question['calculation'] : [];
        $values = array_values((array) ($payload['values'] ?? []));
        $expected = match ((string) ($payload['family'] ?? '')) {
            'function_evaluation' => count($values) === 3 ? $values[0] * $values[1] + $values[2] : null,
            'arithmetic_sequence_term' => count($values) === 3 ? $values[0] + ($values[2] - 1) * $values[1] : null,
            'fraction_of_a_set' => count($values) === 3 && $values[1] != 0 ? ($values[0] / $values[1]) * $values[2] : null,
            'linear_model_solution' => count($values) === 3 && $values[2] != 0 ? ($values[0] - $values[1]) / $values[2] : null,
            default => null,
        };
        $answer = (string) ($question['answer'] ?? '');
        if ($expected === null || (float) $expected !== (float) ($payload['expected'] ?? NAN)
            || ($answer !== (string) $expected && !str_starts_with($answer, (string) $expected . ' '))
            || !isset($question['calculation_family']) || (string) $question['calculation_family'] !== (string) ($payload['family'] ?? '')) {
            $questionDefects[] = ['id' => $id, 'code' => 'CALCULATION_RECOMPUTATION'];
        }
    }
    $card = $deriver->derive($question);
    if (!is_array($card) || FlashcardCardValidator::validate($card) !== []) $questionDefects[] = ['id' => $id, 'code' => 'FLASHCARD_DERIVABILITY'];
    else $flashcards++;
    if ((int) ($question['batch650']['wave'] ?? 0) === 2
        && (string) ($question['batch650']['readiness_before'] ?? '') !== 'READY_WITH_LIMITS') {
        $forbiddenAuthoring++;
        $questionDefects[] = ['id' => $id, 'code' => 'UNAUTHORIZED_READINESS_STATE'];
    }
    if ((int) ($question['batch650']['wave'] ?? 0) === 2
        && trim((string) ($question['batch650']['unused_family_evidence'] ?? '')) === '') {
        $questionDefects[] = ['id' => $id, 'code' => 'UNPROVEN_UNUSED_FAMILY'];
    }
    unset($questionDefects);
}
$wave1Counts = [];
foreach ($wave1Questions as $question) {
    $leaf = (string) $question['taxonomy']['concept_id'];
    $wave1Counts[$leaf] = ($wave1Counts[$leaf] ?? 0) + 1;
}
ksort($wave1Counts, SORT_STRING);
$wave1Accepted = count($wave1Questions) === $expectedWave1Total && $wave1Counts === $expectedCounts && $wave1Defects === [];
$wave2Counts = [];
foreach ($wave2Questions as $question) {
    $leaf = (string) ($question['taxonomy']['concept_id'] ?? '');
    $wave2Counts[$leaf] = ($wave2Counts[$leaf] ?? 0) + 1;
}
ksort($wave2Counts, SORT_STRING);
$wave2ExpectedCounts = [];
foreach ($waveTwoDefinitions as $item) $wave2ExpectedCounts[(string) $item['leaf']] = 1;
ksort($wave2ExpectedCounts, SORT_STRING);
$wave2Accepted = count($wave2Questions) === count($waveTwoDefinitions)
    && $wave2Counts === $wave2ExpectedCounts
    && $wave2Defects === [];

$letNative = count(array_filter($questionsAfter, static fn(array $q): bool => strtolower((string) ($q['taxonomy']['board_id'] ?? '')) === 'let' && strtolower((string) ($q['status'] ?? 'active')) !== 'archived'));
$rawCounts = SourceReadinessService::nativeLeafCounts($afterCollections, 'let');
$readinessRows = array_values(array_filter($afterCollections['source-readiness-let'], 'is_array'));
$readyCounts = ['READY' => 0, 'READY_WITH_LIMITS' => 0, 'REVIEW' => 0, 'DEFER' => 0];
foreach ($readinessRows as $row) $readyCounts[(string) ($row['readiness'] ?? 'REVIEW')] = ($readyCounts[(string) ($row['readiness'] ?? 'REVIEW')] ?? 0) + 1;
$rawHealth = ['zero' => 0, 'under5' => 0, 'under10' => 0, 'under20' => 0];
foreach ($rawCounts as $count) {
    if ($count === 0) $rawHealth['zero']++;
    if ($count < 5) $rawHealth['under5']++;
    if ($count < 10) $rawHealth['under10']++;
    if ($count < 20) $rawHealth['under20']++;
}
$authorableHealth = ['zero' => 0, 'under5' => 0, 'under10' => 0, 'under20' => 0];
$readinessByCurrentLeaf = [];
foreach ($readinessRows as $row) $readinessByCurrentLeaf[(string) ($row['leaf_id'] ?? '')] = $row;
foreach ($rawCounts as $leaf => $count) {
    $row = $readinessByCurrentLeaf[$leaf] ?? [];
    if (!in_array((string) ($row['readiness'] ?? ''), ['READY', 'READY_WITH_LIMITS'], true) || (int) ($row['max_new_questions'] ?? 0) <= 0) continue;
    if ($count === 0) $authorableHealth['zero']++;
    if ($count < 5) $authorableHealth['under5']++;
    if ($count < 10) $authorableHealth['under10']++;
    if ($count < 20) $authorableHealth['under20']++;
}
$wave1OnlyCollections = $afterCollections;
$wave2IdSet = array_fill_keys(array_map(static fn(array $q): string => (string) ($q['id'] ?? ''), $wave2Questions), true);
$wave1OnlyCollections['questions'] = array_values(array_filter($questionsAfter, static fn(array $q): bool => (int) ($q['batch650']['wave'] ?? 0) !== 2));
$wave1OnlyCollections['question-eligibility'] = array_values(array_filter($afterCollections['question-eligibility'], static function (array $row) use ($wave2IdSet): bool {
    return array_values(array_filter(array_map('strval', (array) ($row['question_ids'] ?? [])), static fn(string $id): bool => !isset($wave2IdSet[$id]))) !== [];
}));
foreach ($wave1OnlyCollections['question-eligibility'] as &$w1EligibilityRow) {
    $w1EligibilityRow['question_ids'] = array_values(array_filter(array_map('strval', (array) ($w1EligibilityRow['question_ids'] ?? [])), static fn(string $id): bool => !isset($wave2IdSet[$id])));
    foreach (array_keys((array) ($w1EligibilityRow['question_mappings'] ?? [])) as $mappedId) if (isset($wave2IdSet[(string) $mappedId])) unset($w1EligibilityRow['question_mappings'][$mappedId]);
}
unset($w1EligibilityRow);
$wave1OnlyCounts = SourceReadinessService::nativeLeafCounts($wave1OnlyCollections, 'let');
$wave1OnlyRaw = ['zero' => 0, 'under5' => 0, 'under10' => 0, 'under20' => 0];
$wave1OnlyAuthorable = ['zero' => 0, 'under5' => 0, 'under10' => 0, 'under20' => 0];
$wave1UsedByLeaf = [];
$wave1FamiliesByLeaf = [];
foreach ($wave1Questions as $question) {
    $leaf = (string) ($question['taxonomy']['concept_id'] ?? '');
    $wave1UsedByLeaf[$leaf] = ($wave1UsedByLeaf[$leaf] ?? 0) + 1;
    $family = (string) ($question['batch650']['content_family'] ?? '');
    if ($family !== '') $wave1FamiliesByLeaf[$leaf][$family] = true;
}
$baselineReadinessByLeaf = [];
$baselineLeafEvidenceById = [];
foreach ((array) ($b649['let']['leaves'] ?? []) as $leafEvidence) {
    if (!is_array($leafEvidence)) continue;
    $leaf = (string) ($leafEvidence['leaf_id'] ?? '');
    $baselineLeafEvidenceById[$leaf] = $leafEvidence;
    if (is_array($leafEvidence['readiness_record'] ?? null)) $baselineReadinessByLeaf[$leaf] = $leafEvidence['readiness_record'];
}
foreach ($wave1OnlyCounts as $leaf => $count) {
    if ($count === 0) $wave1OnlyRaw['zero']++;
    if ($count < 5) $wave1OnlyRaw['under5']++;
    if ($count < 10) $wave1OnlyRaw['under10']++;
    if ($count < 20) $wave1OnlyRaw['under20']++;
    $base = $baselineReadinessByLeaf[$leaf] ?? [];
    $remaining = max(0, (int) ($base['max_new_questions'] ?? 0) - (int) ($wave1UsedByLeaf[$leaf] ?? 0));
    if (!in_array((string) ($base['readiness'] ?? ''), ['READY', 'READY_WITH_LIMITS'], true) || $remaining <= 0) continue;
    if ($count === 0) $wave1OnlyAuthorable['zero']++;
    if ($count < 5) $wave1OnlyAuthorable['under5']++;
    if ($count < 10) $wave1OnlyAuthorable['under10']++;
    if ($count < 20) $wave1OnlyAuthorable['under20']++;
}
$flatReviewLeafIds = [];
foreach ((array) ($b649['let']['shared_placements']['flat_shared_rows'] ?? []) as $flatRow) {
    if (is_array($flatRow) && (string) ($flatRow['readiness'] ?? '') === 'REVIEW') $flatReviewLeafIds[(string) ($flatRow['leaf_id'] ?? '')] = true;
}
$wave2CandidateRows = [];
$wave2CandidateComponents = [];
$wave2MathematicalDeficit = 0;
foreach ((array) ($b649['let']['wave2']['candidates'] ?? []) as $candidate) {
    $leaf = (string) ($candidate['leaf_id'] ?? '');
    $base = $baselineReadinessByLeaf[$leaf] ?? [];
    $leafEvidence = $baselineLeafEvidenceById[$leaf] ?? [];
    $count = (int) ($wave1OnlyCounts[$leaf] ?? 0);
    $remainingMax = max(0, (int) ($base['max_new_questions'] ?? 0) - (int) ($wave1UsedByLeaf[$leaf] ?? 0));
    $remainingHeadroom = max(0, (int) ($base['safe_headroom_max'] ?? 0) - (int) ($wave1UsedByLeaf[$leaf] ?? 0));
    $families = array_values(array_filter((array) ($base['distinct_content_families'] ?? []), static fn(string $family): bool => !isset($wave1FamiliesByLeaf[$leaf][$family])));
    $eligible = in_array((string) ($base['readiness'] ?? ''), ['READY', 'READY_WITH_LIMITS'], true)
        && $remainingMax > 0 && $remainingHeadroom > 0 && $count < 10
        && $families !== [] && !isset($flatReviewLeafIds[$leaf])
        && in_array($leaf, (array) ($base['allowed_scope'] ?? []), true)
        && in_array((string) ($base['source_authority'] ?? ''), ['STRONG', 'ADEQUATE'], true)
        && in_array((string) ($base['source_currentness'] ?? ''), ['CURRENT', 'CURRENT_WITH_LIMITS', 'NOT_TIME_SENSITIVE'], true)
        && in_array((string) ($base['source_conflict_status'] ?? ''), ['NONE', 'RESOLVED'], true)
        && in_array((string) ($base['study_coverage'] ?? ''), ['COMPLETE', 'SUFFICIENT'], true)
        && (string) ($base['study_source_traceability'] ?? '') === 'COMPLETE'
        && in_array(strtoupper((string) ($leafEvidence['repeat_risk'] ?? 'UNKNOWN')), ['LOW', 'MODERATE'], true);
    if (!$eligible) continue;
    $component = (string) (((array) ($base['scope_component_ids'] ?? []))[0] ?? 'UNKNOWN');
    $wave2CandidateComponents[$component] = ($wave2CandidateComponents[$component] ?? 0) + 1;
    $wave2CandidateRows[$leaf] = ['leaf_id' => $leaf, 'component' => $component, 'current_count_after_wave1' => $count, 'remaining_headroom' => $remainingHeadroom, 'families_after_wave1' => $families];
    $wave2MathematicalDeficit += max(0, 10 - $count);
}
$wave2SelectedRows = [];
$wave2SelectedComponents = [];
foreach ($wave2Questions as $question) {
    $leaf = (string) ($question['taxonomy']['concept_id'] ?? '');
    $candidate = $wave2CandidateRows[$leaf] ?? [];
    $component = (string) ($candidate['component'] ?? 'UNKNOWN');
    $wave2SelectedComponents[$component] = ($wave2SelectedComponents[$component] ?? 0) + 1;
    $wave2SelectedRows[] = [
        'leaf_id' => $leaf,
        'component' => $component,
        'specialization_id' => (string) ($question['let_specialization_id'] ?? $question['batch650']['specialization_id'] ?? ''),
        'question_id' => (string) ($question['id'] ?? ''),
        'current_count_before' => (int) ($question['batch650']['native_question_count_before'] ?? 0),
        'current_count_after' => (int) ($rawCounts[$leaf] ?? 0),
        'readiness' => (string) ($question['batch650']['readiness_before'] ?? ''),
        'readiness_evidence_hash' => (string) ($question['batch650']['readiness_evidence_hash_before'] ?? ''),
        'content_family' => (string) ($question['batch650']['content_family'] ?? ''),
        'family_evidence' => (string) ($question['batch650']['unused_family_evidence'] ?? ''),
        'depth' => (int) ($question['batch650']['depth'] ?? 0),
        'headroom_before' => (int) ($question['batch650']['remaining_headroom_before'] ?? 0),
        'planned_new' => 1,
        'sources' => array_values(array_map('strval', (array) ($question['source_ids'] ?? []))),
        'study_material_id' => (string) ($question['study_material_id'] ?? ''),
    ];
}
$wave2Gate = [
    'status' => $wave2Accepted ? 'APPROVED_AND_EXECUTED' : 'REJECTED_BY_AUDIT',
    'candidate_leaves_after_wave1' => count($wave2CandidateRows),
    'candidate_components' => $wave2CandidateComponents,
    'mathematical_deficit_to_10' => $wave2MathematicalDeficit,
    'evidence_supported_planned' => count($waveTwoDefinitions),
    'selected_leaves' => $wave2SelectedRows,
    'selected_by_component' => $wave2SelectedComponents,
    'why_bounded' => 'Seven per-leaf semantic reviews demonstrated one remaining source-supported high-value family each: one General Education editing family and six Mathematics specialization families. The remaining under-10 deficit is not a quota; unreviewed candidate leaves require fresh item-level family evidence, while ProfEd and shared REVIEW candidates remain blocked.',
    'unselected_priority_candidates' => [[
        'leaf_id' => 'pronouns-pronoun-antecedent-agreement',
        'reason' => 'The exact Wave1 item already exercises pronoun agreement with a compound antecedent. Existing evidence did not establish a distinct remaining high-value family beyond that proposition, so its remaining numerical headroom was not treated as authoring permission.',
    ]],
    'profed_new_questions' => 0,
    'shared_review_new_questions' => 0,
];
$readinessValidation = SourceReadinessService::validateCollections($afterCollections);
$questionHashAfter = $canonicalJsonHash($questionsAfter);
$eligibilityAfter = $canonicalJsonHash($afterCollections['question-eligibility']);
$existingQuestionHashAfter = $canonicalJsonHash(array_values(array_filter($questionsAfter, static fn(array $q): bool => ($q['batch_marker'] ?? '') !== 'batch650')));
$batchIdsAfter = array_fill_keys($newIds, true);
$eligibilityRowsAfterFreeze = array_values(array_filter($afterCollections['question-eligibility'], static function (array $row) use ($batchIdsAfter): bool {
    return array_values(array_filter(array_map('strval', (array) ($row['question_ids'] ?? [])), static fn(string $id): bool => !isset($batchIdsAfter[$id]))) !== [];
}));
foreach ($eligibilityRowsAfterFreeze as &$row) {
    $row['question_ids'] = array_values(array_filter(array_map('strval', (array) ($row['question_ids'] ?? [])), static fn(string $id): bool => !isset($batchIdsAfter[$id])));
    foreach (array_keys((array) ($row['question_mappings'] ?? [])) as $mappedId) if (isset($batchIdsAfter[(string) $mappedId])) unset($row['question_mappings'][$mappedId]);
}
unset($row);
$existingEligibilityHashAfter = $canonicalJsonHash($eligibilityRowsAfterFreeze);
$allExpectedWave1 = $command === '--wave1' ? 33 : null;
$specializationLeafCounts = [];
foreach ((array) ($b649['let']['leaves'] ?? []) as $leafEvidence) {
    if (!is_array($leafEvidence)) continue;
    $leaf = (string) ($leafEvidence['leaf_id'] ?? '');
    foreach (array_values(array_unique(array_map('strval', (array) ($leafEvidence['native_specialization_ids'] ?? [])))) as $specializationId) {
        $specializationLeafCounts[$specializationId][$leaf] = (int) ($rawCounts[$leaf] ?? 0);
    }
}
$specializationRows = [];
$zeroSpecializationPools = 0;
$newQuestionsBySpecialization = [];
foreach ($wave2SelectedRows as $selectedRow) {
    $specializationId = (string) ($selectedRow['specialization_id'] ?? '');
    if ($specializationId !== '') $newQuestionsBySpecialization[$specializationId] = ($newQuestionsBySpecialization[$specializationId] ?? 0) + 1;
}
foreach ((array) ($b649['let']['specializations']['summaries'] ?? []) as $baselineSpecialization) {
    if (!is_array($baselineSpecialization)) continue;
    $specializationId = (string) ($baselineSpecialization['id'] ?? '');
    $leafCounts = array_values($specializationLeafCounts[$specializationId] ?? []);
    sort($leafCounts, SORT_NUMERIC);
    $leafCount = count($leafCounts);
    $median = $leafCount === 0 ? null : (($leafCount % 2) === 1
        ? $leafCounts[(int) floor($leafCount / 2)]
        : ($leafCounts[(int) ($leafCount / 2) - 1] + $leafCounts[(int) ($leafCount / 2)]) / 2);
    $health = ['zero' => 0, 'under5' => 0, 'under10' => 0];
    foreach ($leafCounts as $count) {
        if ($count === 0) $health['zero']++;
        if ($count < 5) $health['under5']++;
        if ($count < 10) $health['under10']++;
    }
    $readinessCountsBySpecialty = ['READY' => 0, 'READY_WITH_LIMITS' => 0, 'REVIEW' => 0, 'DEFER' => 0];
    $authorableUnder10BySpecialty = 0;
    foreach (array_keys($specializationLeafCounts[$specializationId] ?? []) as $leaf) {
        $record = $readinessByCurrentLeaf[$leaf] ?? [];
        $state = (string) ($record['readiness'] ?? 'REVIEW');
        $readinessCountsBySpecialty[$state] = ($readinessCountsBySpecialty[$state] ?? 0) + 1;
        if (in_array($state, ['READY', 'READY_WITH_LIMITS'], true) && (int) ($record['max_new_questions'] ?? 0) > 0 && (int) ($rawCounts[$leaf] ?? 0) < 10) $authorableUnder10BySpecialty++;
    }
    $newNative = (int) ($newQuestionsBySpecialization[$specializationId] ?? 0);
    $nativeTotal = array_sum($leafCounts);
    if ($nativeTotal === 0) $zeroSpecializationPools++;
    $specializationRows[] = [
        'id' => $specializationId,
        'official_name' => (string) ($baselineSpecialization['official_name'] ?? ''),
        'official_scope_status' => (string) ($baselineSpecialization['official_scope_status'] ?? ''),
        'native_question_count' => $nativeTotal,
        'effective_count' => (int) ($baselineSpecialization['effective_count'] ?? 0) + $newNative,
        'runtime_count' => (int) ($baselineSpecialization['runtime_count'] ?? 0) + $newNative,
        'active_leaves' => $leafCount,
        'native_per_leaf' => ['min' => $leafCount === 0 ? null : min($leafCounts), 'median' => $median, 'max' => $leafCount === 0 ? null : max($leafCounts)],
        'zero' => $health['zero'],
        'under5' => $health['under5'],
        'under10' => $health['under10'],
        'readiness_counts' => $readinessCountsBySpecialty,
        'authorable_under10' => $authorableUnder10BySpecialty,
        'new_native_questions' => $newNative,
        'repeat_risk' => (string) ($baselineSpecialization['repeat_risk'] ?? 'UNKNOWN'),
        'source_state' => (string) ($baselineSpecialization['source_state'] ?? 'UNKNOWN'),
        'study_state' => (string) ($baselineSpecialization['study_state'] ?? 'UNKNOWN'),
    ];
}
$componentSummaries = [];
foreach (['GENERAL_EDUCATION' => 'gened', 'PROFESSIONAL_EDUCATION' => 'profed'] as $componentId => $summaryId) {
    $componentLeaves = [];
    foreach ((array) ($b649['let']['leaves'] ?? []) as $leafEvidence) {
        if (is_array($leafEvidence) && in_array($componentId, (array) ($leafEvidence['scope_component_ids'] ?? []), true)) $componentLeaves[(string) $leafEvidence['leaf_id']] = $leafEvidence;
    }
    $componentHealth = ['zero' => 0, 'under5' => 0, 'under10' => 0, 'under20' => 0, 'authorable_zero' => 0, 'authorable_under5' => 0, 'authorable_under10' => 0, 'authorable_under20' => 0];
    $componentReadiness = ['READY' => 0, 'READY_WITH_LIMITS' => 0, 'REVIEW' => 0, 'DEFER' => 0];
    $componentNative = 0;
    $componentEffective = 0;
    $componentRuntime = 0;
    $componentNew = 0;
    foreach ($componentLeaves as $leaf => $leafEvidence) {
        $count = (int) ($rawCounts[$leaf] ?? 0);
        $componentNative += $count;
        $componentEffective += (int) ($leafEvidence['effective_declared_question_count'] ?? 0);
        $componentRuntime += (int) ($leafEvidence['runtime_question_count'] ?? 0);
        foreach ($newQuestions as $question) if ((string) ($question['taxonomy']['concept_id'] ?? '') === $leaf) {
            $componentNew++;
            $componentEffective++;
            $componentRuntime++;
        }
        if ($count === 0) $componentHealth['zero']++;
        if ($count < 5) $componentHealth['under5']++;
        if ($count < 10) $componentHealth['under10']++;
        if ($count < 20) $componentHealth['under20']++;
        $record = $readinessByCurrentLeaf[$leaf] ?? [];
        $state = (string) ($record['readiness'] ?? 'REVIEW');
        $componentReadiness[$state] = ($componentReadiness[$state] ?? 0) + 1;
        $canAuthor = in_array($state, ['READY', 'READY_WITH_LIMITS'], true) && (int) ($record['max_new_questions'] ?? 0) > 0;
        if ($canAuthor && $count === 0) $componentHealth['authorable_zero']++;
        if ($canAuthor && $count < 5) $componentHealth['authorable_under5']++;
        if ($canAuthor && $count < 10) $componentHealth['authorable_under10']++;
        if ($canAuthor && $count < 20) $componentHealth['authorable_under20']++;
    }
    $componentSummaries[$summaryId] = [
        'active_leaves' => count($componentLeaves),
        'native_questions' => $componentNative,
        'effective_questions' => $componentEffective,
        'runtime_questions' => $componentRuntime,
        'new_questions' => $componentNew,
        'raw' => ['zero' => $componentHealth['zero'], 'under5' => $componentHealth['under5'], 'under10' => $componentHealth['under10'], 'under20' => $componentHealth['under20']],
        'authorable' => ['zero' => $componentHealth['authorable_zero'], 'under5' => $componentHealth['authorable_under5'], 'under10' => $componentHealth['authorable_under10'], 'under20' => $componentHealth['authorable_under20']],
        'readiness_counts' => $componentReadiness,
        'limitations' => $summaryId === 'profed' ? 'Batch649 source/currentness review remains blocking; no Batch650 authoring was performed.' : 'Counts use the permanent component placements; readiness remains leaf-scoped.',
    ];
}
$remainingUnder10Leaves = 0;
$remainingUnder10SafeHeadroom = 0;
$remainingUnder10UnusedFamilies = 0;
foreach ($rawCounts as $leaf => $count) {
    if ($count >= 10) continue;
    $record = $readinessByCurrentLeaf[$leaf] ?? [];
    if (!in_array((string) ($record['readiness'] ?? ''), ['READY', 'READY_WITH_LIMITS'], true) || (int) ($record['max_new_questions'] ?? 0) <= 0) continue;
    $remainingUnder10Leaves++;
    $remainingUnder10SafeHeadroom += (int) ($record['safe_headroom_max'] ?? 0);
    $remainingUnder10UnusedFamilies += count((array) ($record['distinct_content_families'] ?? []));
}
$globalEffectiveAfter = (array) ($b649['global_baseline']['effective_declared_by_board'] ?? []);
$globalRuntimeAfter = (array) ($b649['global_baseline']['runtime_by_board'] ?? []);
$globalNativeAfter = (array) ($b649['global_baseline']['native_by_board'] ?? []);
$globalNativeAfter['let'] = (int) ($globalNativeAfter['let'] ?? 0) + count($newQuestions);
$globalEffectiveAfter['let'] = (int) ($globalEffectiveAfter['let'] ?? 0) + count($newQuestions);
$globalRuntimeAfter['let'] = (int) ($globalRuntimeAfter['let'] ?? 0) + count($newQuestions);
$globalEffectiveTotalAfter = (int) ($b649['global_baseline']['total_effective_declared'] ?? 0) + count($newQuestions);
$globalRuntimeTotalAfter = (int) ($b649['global_baseline']['total_runtime'] ?? 0) + count($newQuestions);
$artifact = [
    'title' => 'BoardPrep Batch650 LET source-first production expansion',
    'batch' => 650,
    'lineage' => ['expected_parent' => $baseHead, 'worktree_branch' => 'batch650-let-expansion', 'main_head_before' => '0eb9704ba293e0aa3402af5d01a2d3392c005b73', 'push' => false],
    'official_scope_check' => [
        'reviewed_at' => '2026-10-03',
        'controlling_scope_sources' => ['prc-let-enhanced-tos-2025', 'prc-resolution-20-2025', 'prc-lept-program-march-2026'],
        'fresh_prc_check' => 'No newer controlling LEPT exam-area, weight, specialization-scope, TOS, or licensure-structure source was found; 2026 PRB resolutions listed as current are registration-without-examination actions and 2026 schedule amendments are testing-center/date administration.',
        'scope_unchanged' => true,
    ],
    'baseline' => [
        'canonical_total_before' => $questionCountBefore,
        'let_native_before' => $baselineLetCount,
        'effective_declared_total_before' => (int) ($b649['global_baseline']['total_effective_declared'] ?? 0),
        'runtime_total_before' => (int) ($b649['global_baseline']['total_runtime'] ?? 0),
        'question_store_hash_before' => $baselineQuestionHash,
        'eligibility_hash_before' => $baselineEligibilityHash,
        'batch649_raw' => $b649['let']['raw_leaf_health'] ?? [],
        'batch649_authorable' => $b649['let']['authorable_leaf_health'] ?? [],
    ],
    'wave1' => [
        'planned' => $expectedWave1Total,
        'drafted' => count($wave1Questions),
        'accepted' => $wave1Accepted ? count($wave1Questions) : 0,
        'repaired' => 0,
        'rejected' => count($wave1Defects),
        'candidate_leaves' => count($wave1Leaves),
        'leaf_assignments' => $wave1Counts,
        'question_ids' => array_values(array_map(static fn(array $q): string => (string) $q['id'], $wave1Questions)),
        'content_families' => array_values(array_map(static fn(array $q): array => ['id' => (string) $q['id'], 'leaf_id' => (string) $q['taxonomy']['concept_id'], 'family' => (string) ($q['batch650']['content_family'] ?? '')], $wave1Questions)),
        'qa' => ['source_study_scope_depth_defects' => $wave1Defects, 'traceable' => $traceability, 'flashcard_derivable' => $flashcards, 'calculation_questions' => $calculations],
        'post_metrics' => ['let_native' => count(array_filter($wave1OnlyCollections['questions'], static fn(array $q): bool => strtolower((string) ($q['taxonomy']['board_id'] ?? '')) === 'let' && strtolower((string) ($q['status'] ?? 'active')) !== 'archived')), 'raw' => $wave1OnlyRaw, 'authorable' => $wave1OnlyAuthorable],
        'readiness_validation' => ['valid' => (bool) ($readinessValidation['valid'] ?? false), 'errors' => $readinessValidation['errors'] ?? []],
        'run_now' => false,
    ],
    'wave2_gate' => $wave2Gate,
    'wave2' => [
        'candidate_leaves' => count($wave2CandidateRows),
        'candidate_components' => $wave2CandidateComponents,
        'mathematical_deficit_to_10' => $wave2MathematicalDeficit,
        'planned' => count($waveTwoDefinitions),
        'accepted' => $wave2Accepted ? count($wave2Questions) : 0,
        'repaired' => 0,
        'rejected' => count($wave2Defects),
        'candidate_leaves_selected' => count($wave2Leaves),
        'question_ids' => array_values(array_map(static fn(array $q): string => (string) ($q['id'] ?? ''), $wave2Questions)),
        'leaf_assignments' => $wave2Counts,
        'content_families' => array_values(array_map(static fn(array $q): array => ['id' => (string) ($q['id'] ?? ''), 'leaf_id' => (string) ($q['taxonomy']['concept_id'] ?? ''), 'family' => (string) ($q['batch650']['content_family'] ?? '')], $wave2Questions)),
        'qa' => ['defects' => $wave2Defects, 'traceable' => count($wave2Questions) === 0 ? 0 : count(array_filter($wave2Questions, static fn(array $q): bool => isset($linksByQuestion[(string) ($q['id'] ?? '')]))), 'calculation_questions' => count(array_filter($wave2Questions, static fn(array $q): bool => ($q['calculation_required'] ?? false) === true))],
        'run_now' => false,
    ],
    'post_wave1' => ['let_native' => count(array_filter($wave1OnlyCollections['questions'], static fn(array $q): bool => strtolower((string) ($q['taxonomy']['board_id'] ?? '')) === 'let' && strtolower((string) ($q['status'] ?? 'active')) !== 'archived')), 'raw' => $wave1OnlyRaw, 'authorable' => $wave1OnlyAuthorable],
    'components' => $componentSummaries,
    'specializations' => ['total' => count($specializationRows), 'zero_pools' => $zeroSpecializationPools, 'rows' => $specializationRows],
    'final' => [
        'total_canonical' => count($questionsAfter),
        'total_effective_declared' => $globalEffectiveTotalAfter,
        'total_runtime' => $globalRuntimeTotalAfter,
        'native_by_board' => $globalNativeAfter,
        'effective_declared_by_board' => $globalEffectiveAfter,
        'runtime_by_board' => $globalRuntimeAfter,
        'let_native' => $letNative,
        'let_effective_declared' => (int) ($b649['let']['baseline']['effective_declared'] ?? 0) + count($newQuestions),
        'let_runtime' => (int) ($b649['let']['baseline']['runtime'] ?? 0) + count($newQuestions),
        'raw' => $rawHealth,
        'authorable' => $authorableHealth,
        'readiness_counts' => $readyCounts,
        'specializations' => $specializationRows,
        'zero_specialization_pools' => $zeroSpecializationPools,
    ],
    'validation' => [
        'wave1_exact_plan' => $wave1Accepted,
        'wave2_gate' => $wave2Accepted,
        'readiness' => (bool) ($readinessValidation['valid'] ?? false),
        'new_question_count' => count($newQuestions),
        'new_ids_contiguous' => $newIds === [] || count($newIds) === 1 || range((int) $newIds[0], (int) end($newIds)) === array_map('intval', $newIds),
        'question_semantic_freeze' => 'Existing canonical question records must be byte-for-byte content-identical in the commit diff; production delta contains only new Batch650 IDs.',
        'question_deletions' => 0,
        'question_semantic_rewrites' => 0,
        'question_mapping_changes' => 0,
        'new_questions_professional_education' => (int) ($componentSummaries['profed']['new_questions'] ?? 0),
        'new_questions_shared_review_leaves' => count(array_filter($newQuestions, static fn(array $q): bool => isset($flatReviewLeafIds[(string) ($q['taxonomy']['concept_id'] ?? '')]))),
        'question_store_hash_after' => $questionHashAfter,
        'eligibility_hash_after' => $eligibilityAfter,
        'existing_question_semantics_unchanged' => hash_equals($baselineQuestionHash, $existingQuestionHashAfter),
        'existing_eligibility_semantics_unchanged' => hash_equals($baselineEligibilityHash, $existingEligibilityHashAfter),
        'source_study_traceability_count' => $traceability,
        'flashcard_derivability_count' => $flashcards,
        'calculation_recomputation_defects' => count(array_filter(array_merge($wave1Defects, $wave2Defects), static fn(array $failure): bool => ($failure['code'] ?? '') === 'CALCULATION_RECOMPUTATION')),
        'wave1_failures' => $wave1Defects,
        'wave2_failures' => $wave2Defects,
    ],
    'health_regressions_expected' => [
        'overall_canonical' => count($questionsAfter),
        'total_effective_declared' => $globalEffectiveTotalAfter,
        'total_runtime' => $globalRuntimeTotalAfter,
        'native_by_board' => $globalNativeAfter,
        'effective_declared_by_board' => $globalEffectiveAfter,
        'runtime_by_board' => $globalRuntimeAfter,
        'me_native_unchanged' => (int) ($globalNativeAfter['mechanical-engineering'] ?? 0) === (int) ($b649['global_baseline']['native_by_board']['mechanical-engineering'] ?? -1),
        'profed_unchanged' => (int) ($componentSummaries['profed']['new_questions'] ?? -1) === 0,
    ],
    'next_gate' => [
        'decision' => 'LET_SELECTIVE_DEEPENING_ONLY',
        'wave3_pre_authorized_additions' => 0,
        'remaining_authorable_under10_leaves' => $remainingUnder10Leaves,
        'remaining_safe_headroom_max' => $remainingUnder10SafeHeadroom,
        'remaining_candidate_family_labels' => $remainingUnder10UnusedFamilies,
        'rationale' => 'The authorable zero and under-5 cohort is closed. Authorable under-10 leaves remain, but Batch649 family labels do not replace item-level duplicate/repeat review; this batch verified only seven discrete unused families and consumed their bounded headroom. Continue only through selective source-first family gates, not a portfolio-wide count quota.',
        'next_priority' => 'Selectively review current RWL General Education and native specialization leaves for item-level unused high-value families; preserve native/effective/runtime separation and keep ProfEd/shared REVIEW blocked.',
    ],
];
$artifact['audit_hash'] = $canonicalJsonHash($artifact);
$artifact['determinism'] = ['audit_hash' => $artifact['audit_hash'], 'runtime_excluded' => true, 'timestamps_excluded' => true];
$writeJson($artifactPath, $artifact);

printf("BATCH650_MODE=%s\n", $command);
printf("WAVE1_PLANNED=%d WAVE1_DRAFTED=%d WAVE1_ACCEPTED=%d WAVE1_REJECTED=%d\n", $expectedWave1Total, count($wave1Questions), $wave1Accepted ? count($wave1Questions) : 0, count($wave1Defects));
printf("WAVE1_LEAVES=%d WAVE1_ID_RANGE=%s-%s\n", count($wave1Leaves), $wave1Questions[0]['id'] ?? '', $wave1Questions === [] ? '' : end($wave1Questions)['id']);
printf("WAVE2_CANDIDATES=%d WAVE2_PLANNED=%d WAVE2_ACCEPTED=%d WAVE2_REJECTED=%d WAVE2_ID_RANGE=%s-%s\n", count($wave2CandidateRows), count($waveTwoDefinitions), $wave2Accepted ? count($wave2Questions) : 0, count($wave2Defects), $wave2Questions[0]['id'] ?? '', $wave2Questions === [] ? '' : end($wave2Questions)['id']);
printf("LET_NATIVE=%d RAW_ZERO_U5_U10_U20=%d/%d/%d/%d AUTHORABLE_ZERO_U5_U10_U20=%d/%d/%d/%d\n", $letNative, $rawHealth['zero'], $rawHealth['under5'], $rawHealth['under10'], $rawHealth['under20'], $authorableHealth['zero'], $authorableHealth['under5'], $authorableHealth['under10'], $authorableHealth['under20']);
printf("READINESS_VALID=%s FLASHCARDS=%d TRACEABILITY=%d CALCULATION_Q=%d HASH=%s\n", ($readinessValidation['valid'] ?? false) ? 'YES' : 'NO', $flashcards, $traceability, $calculations, $artifact['audit_hash']);
if (!$wave1Accepted || !$wave2Accepted || $forbiddenAuthoring !== 0 || !($readinessValidation['valid'] ?? false)) exit(1);
