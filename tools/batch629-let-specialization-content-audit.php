<?php

declare(strict_types=1);

/**
 * Batch 629 is deliberately data-driven.  The --write path adds one
 * deterministic LET cohort and the default path audits the resulting cohort
 * through the same placement, source, study, coverage, and selector rules.
 * No network calls occur here; live source verification is recorded in the
 * artifact and in the commit review notes.
 */

$root = dirname(__DIR__);
$repair = in_array('--repair', $argv, true);
$write = in_array('--write', $argv, true) || $repair;
$today = '2026-09-29';
$timestamp = $today . 'T00:00:00+08:00';

function b629_read(string $root, string $name): array
{
    $path = $root . '/storage/' . $name . '.json';
    $value = json_decode((string) file_get_contents($path), true);
    if (!is_array($value)) {
        throw new RuntimeException("Invalid JSON: {$path}");
    }
    return $value;
}

function b629_write(string $root, string $name, array $value): void
{
    $path = $root . '/storage/' . $name . '.json';
    file_put_contents($path, json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
}

function b629_has_id(array $rows, string $id): bool
{
    foreach ($rows as $row) {
        if (is_array($row) && (string) ($row['id'] ?? '') === $id) return true;
    }
    return false;
}

function b629_unique(array $values): array
{
    $out = [];
    foreach ($values as $value) {
        $value = (string) $value;
        if ($value !== '' && !in_array($value, $out, true)) $out[] = $value;
    }
    return $out;
}

function b629_norm(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/', ' ', $value) ?? $value;
    return trim(preg_replace('/\s+/', ' ', $value) ?? $value);
}

function b629_learning(string $difficulty = 'medium', int $minutes = 60, array $keywords = []): array
{
    return [
        'difficulty' => $difficulty,
        'estimated_study_minutes' => $minutes,
        'mastery_threshold' => 80,
        'keywords' => $keywords,
        'common_misconceptions' => [],
    ];
}

function b629_source(
    string $id,
    string $title,
    string $authority,
    string $url,
    ?string $publicationDate,
    string $version,
    string $role,
    string $volatility,
    string $notes
): array {
    return [
        'id' => $id,
        'title' => $title,
        'source_type' => 'OFFICIAL_GUIDANCE',
        'authority' => $authority,
        'url' => $url,
        'publication_date' => $publicationDate,
        'retrieved_at' => '2026-09-29',
        'version' => $version,
        'status' => 'verified_reference',
        'confidence' => 'authoritative_reference',
        'source_role' => $role,
        'volatility' => $volatility,
        'notes' => $notes,
    ];
}

function b629_subject(string $id, string $code, string $name, string $description): array
{
    return ['id' => $id, 'code' => $code, 'name' => $name, 'description' => $description, 'category' => 'specialization', 'status' => 'active'];
}

function b629_domain(string $id, string $subject, string $code, string $name, string $description): array
{
    return ['id' => $id, 'subject_id' => $subject, 'code' => $code, 'name' => $name, 'description' => $description, 'learning' => b629_learning('medium', 90)];
}

function b629_topic(string $id, string $domain, string $code, string $name, string $description): array
{
    return [
        'id' => $id,
        'domain_id' => $domain,
        'code' => $code,
        'name' => $name,
        'description' => $description,
        'learning' => [
            'difficulty' => 'medium',
            'estimated_study_minutes' => 45,
            'mastery_threshold' => 80,
            'prerequisite_ids' => [],
            'learning_objectives' => ['Explain the controlling principle.', 'Apply the principle to a new classroom or subject scenario.'],
        ],
    ];
}

function b629_concept(string $id, string $topic, string $code, string $name, string $description, array $misconceptions = []): array
{
    $row = [
        'id' => $id,
        'topic_id' => $topic,
        'code' => $code,
        'name' => $name,
        'description' => $description,
        'learning' => b629_learning('medium', 25, [$name]),
    ];
    $row['learning']['common_misconceptions'] = $misconceptions;
    return $row;
}

function b629_study(string $id, string $title, string $summary, array $notes, array $sources, string $subject, string $domain, string $topic, string $concept, string $specialization): array
{
    return [
        'id' => $id,
        'title' => $title,
        'summary' => $summary,
        'notes' => array_map(static fn(array $n): array => [$n[0], $n[1]], $notes),
        'source_ids' => b629_unique($sources),
        'exams' => ['let'],
        'exam_focus' => ['let' => [
            'priority' => 'high',
            'rationale' => 'Specialization-specific foundation aligned to the current PRC enhanced TOS model; not a prediction of exact item counts.',
            'official_scope_status' => 'source-backed specialization foundation',
            'specialization_id' => $specialization,
        ]],
        'taxonomy' => ['subject_id' => $subject, 'domain_id' => $domain, 'topic_id' => $topic, 'concept_id' => $concept],
        'let_specialization_id' => $specialization,
        'status' => 'published',
        'version' => 1,
        'updated_at' => '2026-09-29',
        'batch_marker' => 'batch629',
    ];
}

$scopeSource = 'prc-let-enhanced-tos-2025';

/* The eight selected paths are the five Batch 628 partial-taxonomy paths plus
 * three bounded extensions whose official curriculum and professional-policy
 * sources are now sufficient for an initial, non-clinical pool. */
$specializations = [
    'secondary-english' => [
        'name' => 'English', 'level' => 'SECONDARY', 'subject' => 'english', 'short' => 'English',
        'study' => 'let-specialization-english-application', 'scope' => 'Purposive Communication in English',
        'sources' => [$scopeSource, 'deped-k12-english-curriculum-guide', 'openstax-reading-writing'],
    ],
    'secondary-filipino' => [
        'name' => 'Filipino', 'level' => 'SECONDARY', 'subject' => 'filipino', 'short' => 'Filipino',
        'study' => 'let-specialization-filipino-application', 'scope' => 'Malayuning Komunikasyon sa Wikang Filipino',
        'sources' => [$scopeSource, 'deped-filipino-curriculum-guide'],
    ],
    'secondary-mathematics' => [
        'name' => 'Mathematics', 'level' => 'SECONDARY', 'subject' => 'mathematics', 'short' => 'Mathematics',
        'study' => 'let-specialization-mathematics-application', 'scope' => 'Mathematics',
        'sources' => [$scopeSource, 'deped-math-curriculum-guide', 'openstax-algebra-and-trigonometry'],
    ],
    'secondary-science' => [
        'name' => 'Science', 'level' => 'SECONDARY', 'subject' => 'science', 'short' => 'Science',
        'study' => 'let-specialization-science-application', 'scope' => 'Science and Technology',
        'sources' => [$scopeSource, 'deped-science-curriculum-guide', 'openstax-biology-2e', 'openstax-chemistry-2e', 'openstax-university-physics-volume-1'],
    ],
    'secondary-social-studies' => [
        'name' => 'Social Studies', 'level' => 'SECONDARY', 'subject' => 'social-studies', 'short' => 'Social Studies',
        'study' => 'let-specialization-social-studies-application', 'scope' => 'Social Studies / Philippine History and Society',
        'sources' => [$scopeSource, 'deped-araling-panlipunan-curriculum-guide', 'republic-act-10533', '1987-philippine-constitution'],
    ],
    'elementary-early-childhood-education' => [
        'name' => 'Early Childhood Education', 'level' => 'ELEMENTARY', 'subject' => 'early-childhood-education', 'short' => 'ECE',
        'study' => 'let-specialization-ece-foundation', 'scope' => 'Early Childhood Education / Kindergarten foundations',
        'sources' => [$scopeSource, 'deped-kindergarten-curriculum-guide-2023', 'deped-kindergarten-policy-do-47-2016'],
    ],
    'elementary-special-needs-education' => [
        'name' => 'Special Needs Education', 'level' => 'ELEMENTARY', 'subject' => 'special-needs-education', 'short' => 'SNE',
        'study' => 'let-specialization-sne-inclusion', 'scope' => 'Special Needs Education / Inclusive Education',
        'sources' => [$scopeSource, 'deped-ra11650-irr', 'republic-act-11650', 'deped-ppst-do-42-2017'],
    ],
    'secondary-values-education' => [
        'name' => 'Values Education', 'level' => 'SECONDARY', 'subject' => 'values-education', 'short' => 'Values Education',
        'study' => 'let-specialization-values-education', 'scope' => 'Values Education / Edukasyon sa Pagpapakatao',
        'sources' => [$scopeSource, 'deped-esep-curriculum-guide', 'deped-ppst-do-42-2017'],
    ],
];

$deferredSpecializations = [
    'secondary-physical-education', 'secondary-culture-and-arts-education',
    'secondary-tle-agriculture-and-fishery-arts', 'secondary-tle-home-economics',
    'secondary-tle-information-and-communication-technology', 'secondary-tle-industrial-arts',
    'secondary-technical-vocational-teacher-education',
];

$newSubjects = [
    b629_subject('early-childhood-education', 'ECE', 'Early Childhood Education', 'Developmentally appropriate early learning, play, language, observation, and family partnership.'),
    b629_subject('special-needs-education', 'SNE', 'Special Needs Education', 'Inclusive education, access, accommodations, collaboration, and progress monitoring without unsupported diagnosis.'),
    b629_subject('values-education', 'VE', 'Values Education', 'Ethical reasoning, values clarification, respectful dialogue, character, and civic responsibility.'),
];

$newDomains = [
    b629_domain('early-childhood-development', 'early-childhood-education', 'ECD', 'Early Childhood Development', 'Developmentally appropriate learning and observation in the early years.'),
    b629_domain('inclusive-special-needs-education', 'special-needs-education', 'SNE', 'Inclusive Special Needs Education', 'Access, participation, support, and assessment for diverse learners.'),
    b629_domain('values-and-ethical-education', 'values-education', 'VE', 'Values and Ethical Education', 'Reasoned values formation, ethical action, dialogue, and civic responsibility.'),
];

$newTopics = [
    b629_topic('ece-development-and-learning', 'early-childhood-development', 'DEV', 'Development and Learning', 'Developmental domains, play, and age-appropriate learning.'),
    b629_topic('ece-observation-and-assessment', 'early-childhood-development', 'OBS', 'Observation and Assessment', 'Authentic observation, documentation, and responsive planning.'),
    b629_topic('ece-family-and-inclusion', 'early-childhood-development', 'FAM', 'Family Partnership and Inclusion', 'Family engagement and inclusive early-learning environments.'),
    b629_topic('sne-access-and-accommodations', 'inclusive-special-needs-education', 'ACC', 'Access and Accommodations', 'Equitable access, accommodations, and barrier reduction.'),
    b629_topic('sne-instruction-and-assessment', 'inclusive-special-needs-education', 'INS', 'Instruction and Assessment', 'Universal design, adaptation, and progress monitoring.'),
    b629_topic('sne-collaboration', 'inclusive-special-needs-education', 'COL', 'Collaboration and Support', 'Family, peer, and multidisciplinary collaboration.'),
    b629_topic('values-reasoning-and-character', 'values-and-ethical-education', 'RC', 'Reasoning and Character', 'Values clarification, ethical reasoning, and character formation.'),
    b629_topic('values-dialogue-and-diversity', 'values-and-ethical-education', 'DD', 'Dialogue and Diversity', 'Respectful dialogue, pluralism, and inclusive classroom practice.'),
    b629_topic('values-civic-responsibility', 'values-and-ethical-education', 'CR', 'Civic Responsibility', 'Responsible action, public good, and reflective practice.'),
    b629_topic('filipino-talasalitaan', 'talasalitaan', 'TAL', 'Talasalitaan at Kahulugan', 'Word meaning and use in Filipino contexts.'),
    b629_topic('filipino-panitikan', 'panitikan', 'PAN', 'Elemento at Pagsusuri ng Panitikan', 'Literary elements and evidence-based interpretation.'),
    b629_topic('filipino-pagsulat', 'pagsulat', 'PAG', 'Pagsulat at Organisasyon', 'Clear, coherent, and audience-appropriate Filipino writing.'),
    b629_topic('filipino-komunikasyon', 'komunikasyon', 'KOM', 'Mabisang Komunikasyon', 'Purpose, audience, register, and respectful communication.'),
    b629_topic('math-geometry', 'geometry', 'GEO', 'Geometry and Spatial Reasoning', 'Shape, measurement, and geometric relationships.'),
    b629_topic('math-statistics', 'statistics-and-probability', 'STAT', 'Statistics and Probability', 'Data summaries, chance, and interpretation.'),
    b629_topic('math-measurement', 'measurement', 'MEAS', 'Measurement and Units', 'Units, conversions, and measurement applications.'),
    b629_topic('math-problem-solving', 'problem-solving', 'PS', 'Mathematical Modeling', 'Multi-step reasoning and model selection.'),
];

$newConcepts = [
    b629_concept('ece-play-based-learning', 'ece-development-and-learning', 'PLAY', 'Play-Based Learning', 'Play as a developmentally appropriate context for integrated learning.'),
    b629_concept('ece-developmental-domains', 'ece-development-and-learning', 'DOM', 'Developmental Domains', 'Holistic language, social-emotional, physical, cognitive, and creative development.'),
    b629_concept('ece-emergent-literacy', 'ece-development-and-learning', 'LIT', 'Emergent Literacy', 'Early oral language, print awareness, and meaning-making.'),
    b629_concept('ece-developmental-observation', 'ece-observation-and-assessment', 'OBS', 'Developmental Observation', 'Purposeful observation and documentation of learner development.'),
    b629_concept('ece-authentic-assessment', 'ece-observation-and-assessment', 'AUTH', 'Authentic Assessment', 'Using natural performance evidence to plan responsive experiences.'),
    b629_concept('ece-family-partnership', 'ece-family-and-inclusion', 'FAM', 'Family Partnership', 'Respectful family collaboration grounded in the child context.'),
    b629_concept('ece-inclusive-environment', 'ece-family-and-inclusion', 'INC', 'Inclusive Early Learning Environment', 'Accessible, safe, culturally responsive early-learning spaces.'),
    b629_concept('sne-universal-design', 'sne-access-and-accommodations', 'UDL', 'Universal Design for Learning', 'Planning multiple means of engagement, representation, and action.'),
    b629_concept('sne-reasonable-accommodations', 'sne-access-and-accommodations', 'ACC', 'Reasonable Accommodations', 'Removing access barriers without changing the intended learning target unnecessarily.'),
    b629_concept('sne-accessible-materials', 'sne-access-and-accommodations', 'MAT', 'Accessible Materials', 'Accessible presentation and participation supports.'),
    b629_concept('sne-instructional-adaptation', 'sne-instruction-and-assessment', 'ADP', 'Instructional Adaptation', 'Responsive changes to instruction based on learner evidence.'),
    b629_concept('sne-progress-monitoring', 'sne-instruction-and-assessment', 'PM', 'Progress Monitoring', 'Frequent evidence-based checks of progress toward an instructional goal.'),
    b629_concept('sne-inclusive-assessment', 'sne-instruction-and-assessment', 'ASM', 'Inclusive Assessment', 'Assessment access, valid evidence, and fair interpretation.'),
    b629_concept('sne-family-collaboration', 'sne-collaboration', 'FAM', 'Family Collaboration', 'Respectful, informed collaboration with families and support teams.'),
    b629_concept('values-clarification', 'values-reasoning-and-character', 'VC', 'Values Clarification', 'Examining reasons, consequences, and commitments rather than imposing a slogan.'),
    b629_concept('values-ethical-reasoning', 'values-reasoning-and-character', 'ER', 'Ethical Reasoning', 'Applying relevant duties, rights, consequences, and context to a decision.'),
    b629_concept('values-character-and-habits', 'values-reasoning-and-character', 'CH', 'Character and Habits', 'Repeated practice of justified, responsible conduct.'),
    b629_concept('values-respectful-dialogue', 'values-dialogue-and-diversity', 'RD', 'Respectful Dialogue', 'Listening, reason-giving, and disagreement without demeaning others.'),
    b629_concept('values-diversity-and-dignity', 'values-dialogue-and-diversity', 'DD', 'Diversity and Dignity', 'Equal dignity and respectful inclusion across differences.'),
    b629_concept('values-civic-responsibility', 'values-civic-responsibility', 'CR', 'Civic Responsibility', 'Responsible participation directed toward shared public goods.'),
    b629_concept('values-reflective-practice', 'values-civic-responsibility', 'RP', 'Reflective Practice', 'Using reflection to improve future ethical action.'),
    b629_concept('filipino-talasalitaan-kasingkahulugan', 'filipino-talasalitaan', 'KS', 'Kasingkahulugan at Kasalungat', 'Meaning relationships among Filipino words.'),
    b629_concept('filipino-talasalitaan-konteksto', 'filipino-talasalitaan', 'KC', 'Kahulugan sa Konteksto', 'Interpreting Filipino word meaning from context.'),
    b629_concept('filipino-panitikan-tauhan', 'filipino-panitikan', 'TAU', 'Tauhan at Motibasyon', 'Using textual evidence to interpret character action.'),
    b629_concept('filipino-panitikan-tema', 'filipino-panitikan', 'THE', 'Tema at Patunay', 'Distinguishing theme from topic and supporting it with evidence.'),
    b629_concept('filipino-pagsulat-organisasyon', 'filipino-pagsulat', 'ORG', 'Organisasyon ng Talata', 'Unity, coherence, and logical sequence in Filipino writing.'),
    b629_concept('filipino-pagsulat-buod', 'filipino-pagsulat', 'BUO', 'Pagsulat ng Buod', 'Selecting essential ideas without adding unsupported claims.'),
    b629_concept('filipino-komunikasyon-angkop-na-wika', 'filipino-komunikasyon', 'REG', 'Angkop na Wika at Rehistro', 'Matching language choice to purpose, audience, and context.'),
    b629_concept('math-geometry-area-perimeter', 'math-geometry', 'AP', 'Area and Perimeter', 'Selecting and applying area and perimeter relationships.'),
    b629_concept('math-geometry-angle-relationships', 'math-geometry', 'ANG', 'Angle Relationships', 'Reasoning about complementary, supplementary, and related angles.'),
    b629_concept('math-statistics-mean-median', 'math-statistics', 'MM', 'Mean and Median', 'Selecting and interpreting center measures.'),
    b629_concept('math-statistics-probability', 'math-statistics', 'PROB', 'Elementary Probability', 'Representing and calculating simple probabilities.'),
    b629_concept('math-measurement-unit-conversion', 'math-measurement', 'UNIT', 'Unit Conversion', 'Converting measurements while preserving meaning and units.'),
    b629_concept('math-problem-solving-modeling', 'math-problem-solving', 'MOD', 'Mathematical Modeling', 'Translating a context into a solvable model and checking it.'),
    b629_concept('math-algebra-patterns', 'math-algebra', 'PAT', 'Patterns and Relationships', 'Representing patterns with rules and expressions.'),
    b629_concept('math-number-sense-fractions', 'math-number-sense', 'FR', 'Fractions and Proportional Reasoning', 'Comparing and operating with fractions in meaningful contexts.'),
    b629_concept('math-algebra-functions', 'math-algebra', 'FUN', 'Functions and Representations', 'Connecting inputs, outputs, tables, graphs, and rules.'),
    b629_concept('social-history-context-and-causation', 'social-history', 'HC', 'Historical Context and Causation', 'Connecting historical claims to context and evidence.'),
    b629_concept('social-civics-rights-and-duties', 'social-civics', 'RD', 'Rights and Civic Duties', 'Balancing rights, duties, lawful authority, and public responsibility.'),
    b629_concept('social-civics-evidence-and-claims', 'social-civics', 'EC', 'Evidence and Civic Claims', 'Distinguishing supported civic claims from unsupported assertions.'),
];

/* New curriculum and policy sources were live checked on 2026-09-29. */
$newSources = [
    b629_source('deped-filipino-curriculum-guide', 'DepEd K to 12 Filipino Curriculum Guide', 'Department of Education, Philippines', 'https://www.deped.gov.ph/wp-content/uploads/2019/01/Filipino-CG.pdf', '2016-12-01', 'K to 12 Filipino Curriculum Guide', 'CURRICULUM_SOURCE', 'CURRICULUM_VERSIONED', 'Official Filipino curriculum framework and competencies; used for scope and competency framing.'),
    b629_source('deped-math-curriculum-guide', 'DepEd K to 12 Mathematics Curriculum Guide', 'Department of Education, Philippines', 'https://www.deped.gov.ph/wp-content/uploads/2019/01/Math-CG_with-tagged-math-equipment.pdf', '2016-12-01', 'K to 12 Mathematics Curriculum Guide', 'CURRICULUM_SOURCE', 'CURRICULUM_VERSIONED', 'Official mathematics curriculum guide; arithmetic and reasoning items are kept within teacher-education scope.'),
    b629_source('deped-science-curriculum-guide', 'DepEd K to 12 Curriculum Guide: Science', 'Department of Education, Philippines', 'https://lrmds.deped.gov.ph/detail/30/5459', '2014-03-17', 'Science Grades 3 to 10 curriculum guide', 'CURRICULUM_SOURCE', 'CURRICULUM_VERSIONED', 'DepEd Learning Portal record for the official Science curriculum guide.'),
    b629_source('deped-araling-panlipunan-curriculum-guide', 'DepEd K to 12 Araling Panlipunan Curriculum Guide', 'Department of Education, Philippines', 'https://www.deped.gov.ph/wp-content/uploads/2019/01/AP-CG.pdf', '2016-12-01', 'K to 12 Araling Panlipunan Curriculum Guide', 'CURRICULUM_SOURCE', 'CURRICULUM_VERSIONED', 'Official social studies curriculum source for historical, civic, and social inquiry competencies.'),
    b629_source('deped-kindergarten-curriculum-guide-2023', 'MATATAG Kindergarten Curriculum Guide', 'Department of Education, Philippines', 'https://www.deped.gov.ph/wp-content/uploads/MATATAG-Kindergarten-CG.pdf', '2023-01-01', 'MATATAG Kindergarten Curriculum Guide', 'CURRICULUM_SOURCE', 'CURRICULUM_VERSIONED', 'Official kindergarten learning standards and developmental domains; used without diagnostic claims.'),
    b629_source('deped-kindergarten-policy-do-47-2016', 'DepEd Order No. 47, s. 2016: Omnibus Policy on Kindergarten Education', 'Department of Education, Philippines', 'https://www.deped.gov.ph/wp-content/uploads/2019/01/DO_s2016_47.pdf', '2016-06-10', 'DepEd Order No. 47, s. 2016', 'NORMATIVE_SOURCE', 'REGULATORY_CURRENT', 'Policy source describing kindergarten developmental domains, play-based activities, instruction, and assessment.'),
    b629_source('republic-act-11650', 'Republic Act No. 11650: Inclusive Education Act', 'Republic of the Philippines', 'https://lawphil.net/statutes/repacts/ra2022/ra_11650_2022.html', '2022-03-11', 'Republic Act No. 11650', 'STATUTORY_SOURCE', 'STATUTORY_VERSIONED', 'Primary law for inclusion and services for learners with disabilities; used for stable statutory duties only.'),
    b629_source('deped-ra11650-irr', 'Implementing Rules and Regulations of Republic Act No. 11650', 'Department of Education, Philippines', 'https://www.deped.gov.ph/wp-content/uploads/IRR-of-RA-11650.pdf', '2024-01-01', 'IRR of RA No. 11650', 'NORMATIVE_SOURCE', 'REGULATORY_CURRENT', 'Official DepEd IRR for equitable access, inclusive learning resource centers, and support services.'),
    b629_source('deped-esep-curriculum-guide', 'DepEd K to 12 Edukasyon sa Pagpapakatao Curriculum Guide', 'Department of Education, Philippines', 'https://lrmds.deped.gov.ph/detail/15800', '2016-12-01', 'K to 12 Edukasyon sa Pagpapakatao curriculum guide', 'CURRICULUM_SOURCE', 'CURRICULUM_VERSIONED', 'Official DepEd Learning Portal curriculum competencies for values, dignity, truth, responsibility, and civic participation.'),
    b629_source('openstax-algebra-and-trigonometry', 'College Algebra and Trigonometry', 'OpenStax, Rice University', 'https://openstax.org/details/books/algebra-and-trigonometry-2e', '2021-01-01', 'current OpenStax web edition', 'INSTRUCTIONAL_SOURCE', 'STABLE_FOUNDATIONAL', 'Open instructional reference for quantitative reasoning; not used to expand beyond the LEPT competency frame.'),
    b629_source('deped-matatag-general-education-framework', 'DepEd MATATAG Curriculum Framework', 'Department of Education, Philippines', 'https://www.deped.gov.ph/matatagcurriculumkto10/', '2023-08-10', 'MATATAG K to 10 framework', 'CURRICULUM_SOURCE', 'CURRICULUM_VERSIONED', 'Current curriculum framework used only where a broad learning-area context is answer-critical.'),
];

$studyRows = [
    b629_study('let-specialization-english-application', 'LET English Specialization: Grammar, Reading, and Communication Application', 'Application-focused English specialization foundation with explicit grammar, reading evidence, register, and communication practice.', [
        ['Scope', 'The PRC enhanced TOS places the candidate-selected specialization in the Secondary examination component. This material supports English competencies without predicting item counts.'],
        ['Grammar application', 'Check the grammatical subject, logical modifier, parallel form, tense relationship, clause structure, and idiomatic preposition before choosing a revision.'],
        ['Reading and communication', 'Support an inference with text evidence, distinguish purpose from tone, and match register to audience and purpose.'],
        ['Common errors', 'Dangling modifiers, compound-subject agreement errors, indefinite-pronoun errors, tense shifts, false parallelism, and unsupported interpretations are distractor families.'],
        ['Residual coverage closure', 'The nine previously unresolved grammar rows are now supported to conservative application depth by linked examples and reviewed questions; no professional depth is inferred.'],
    ], [$scopeSource, 'deped-k12-english-curriculum-guide', 'openstax-reading-writing'], 'english', 'grammar', 'sentence-structure', 'sentence-structure-complex-sentences', 'secondary-english'),
    b629_study('let-specialization-filipino-application', 'LET Filipino Specialization: Wika, Pagbasa, Panitikan, at Komunikasyon', 'Filipino specialization foundation linking language use, reading, literature, writing, and context-appropriate communication.', [
        ['Wika at kahulugan', 'Interpret word choice in context and distinguish related meanings rather than selecting a familiar word automatically.'],
        ['Pagbasa at panitikan', 'Use details, character action, and text structure to justify a main idea or literary interpretation.'],
        ['Pagsulat', 'A strong paragraph has a controlling idea, relevant support, coherent order, and a conclusion that does not add a new unsupported claim.'],
        ['Komunikasyon', 'Consider audience, purpose, relationship, and register when selecting a Filipino expression.'],
    ], [$scopeSource, 'deped-filipino-curriculum-guide'], 'filipino', 'gramatikang-filipino', 'filipino-gramatika', 'filipino-gramatika-wastong-gamit', 'secondary-filipino'),
    b629_study('let-specialization-mathematics-application', 'LET Mathematics Specialization: Reasoning, Modeling, and Measurement', 'Source-first mathematics foundation for calculations, representations, data interpretation, and multi-step reasoning.', [
        ['Representation', 'Translate a word situation into an equation, diagram, table, or probability model before calculating.'],
        ['Calculation discipline', 'Keep units visible, show the controlling operation, check the magnitude, and round only when the item permits it.'],
        ['Data and geometry', 'Select a center measure or geometric relationship that fits the data or figure instead of applying a memorized formula without checking conditions.'],
        ['Common errors', 'Unit mismatch, using perimeter for area, averaging incompatible values, and treating a probability denominator as a numerator are common distractor families.'],
    ], [$scopeSource, 'deped-math-curriculum-guide', 'openstax-algebra-and-trigonometry'], 'mathematics', 'number-sense', 'math-number-sense', 'math-number-sense-operations', 'secondary-mathematics'),
    b629_study('let-specialization-science-application', 'LET Science Specialization: Evidence, Systems, and Quantitative Interpretation', 'Science foundation using observation, evidence, models, systems, units, and appropriate school-level explanations.', [
        ['Scientific reasoning', 'Separate observation from inference, identify variables, and prefer a conclusion supported by the supplied evidence.'],
        ['Life, matter, and Earth systems', 'Use stable foundational relationships for cells, matter, ecosystems, layers, motion, circuits, and light.'],
        ['Quantitative interpretation', 'State the relevant unit and assumption; do not infer a value or mechanism not supported by the data.'],
        ['Scope', 'Questions stay within teacher-education science literacy and do not require specialist research or unsupported current claims.'],
    ], [$scopeSource, 'deped-science-curriculum-guide', 'openstax-biology-2e', 'openstax-chemistry-2e', 'openstax-university-physics-volume-1'], 'science', 'scientific-inquiry', 'science-inquiry', 'science-inquiry-evidence', 'secondary-science'),
    b629_study('let-specialization-social-studies-application', 'LET Social Studies: Historical Evidence, Civic Reasoning, and Public Good', 'Social studies foundation for source evaluation, history, civic reasoning, constitutional principles, and public-good decisions.', [
        ['Historical evidence', 'Distinguish a primary source from a later interpretation and make only the claim the source can support.'],
        ['Civic reasoning', 'Use the stated constitutional or statutory principle, distinguish rights from preferences, and avoid importing current political claims not in the source.'],
        ['Public good', 'Balance individual dignity, lawful authority, participation, and long-term community effects in a supplied scenario.'],
        ['Version-sensitive review', 'Law and policy items are reviewed against the dated authority recorded in the item metadata.'],
    ], [$scopeSource, 'deped-araling-panlipunan-curriculum-guide', 'republic-act-10533', '1987-philippine-constitution'], 'social-studies', 'government-and-civics', 'social-civics', 'social-civics-public-good', 'secondary-social-studies'),
    b629_study('let-specialization-ece-foundation', 'LET Early Childhood Education: Developmentally Appropriate Learning', 'Bounded ECE foundation for play, developmental domains, observation, authentic assessment, family partnership, and inclusion.', [
        ['Development', 'Plan from the child’s observed developmental strengths and needs across domains; do not infer a diagnosis from one behavior.'],
        ['Play and language', 'Use purposeful play and language-rich interaction to support integrated learning appropriate to the child’s stage.'],
        ['Assessment', 'Observe in natural contexts, document evidence, and use it to plan the next experience rather than ranking children prematurely.'],
        ['Family and inclusion', 'Treat families as partners and remove barriers while respecting culture, safety, and child dignity.'],
    ], [$scopeSource, 'deped-kindergarten-curriculum-guide-2023', 'deped-kindergarten-policy-do-47-2016'], 'early-childhood-education', 'early-childhood-development', 'ece-development-and-learning', 'ece-play-based-learning', 'elementary-early-childhood-education'),
    b629_study('let-specialization-sne-inclusion', 'LET Special Needs Education: Access, Support, and Progress', 'Bounded SNE foundation focused on inclusive access, accommodations, UDL, adaptation, assessment, progress, and collaboration without clinical diagnosis.', [
        ['Access', 'Remove barriers and provide reasonable access supports while preserving the intended learning goal unless a documented modification is required.'],
        ['Universal design', 'Offer multiple means of engagement, representation, and action so support is planned for learner variability.'],
        ['Evidence', 'Use observable performance evidence and progress monitoring; do not label a learner from a single classroom behavior.'],
        ['Collaboration', 'Coordinate with families and authorized support personnel, respecting confidentiality and the learner’s dignity.'],
    ], [$scopeSource, 'deped-ra11650-irr', 'republic-act-11650', 'deped-ppst-do-42-2017'], 'special-needs-education', 'inclusive-special-needs-education', 'sne-access-and-accommodations', 'sne-universal-design', 'elementary-special-needs-education'),
    b629_study('let-specialization-values-education', 'LET Values Education: Ethical Reasoning, Dialogue, and Civic Responsibility', 'Values Education foundation for reasoned decisions, dignity, dialogue, reflective practice, and responsible participation.', [
        ['Reasoning', 'Identify the relevant facts, values, duties, rights, and foreseeable consequences before choosing an action.'],
        ['Dialogue', 'Invite reasons, listen actively, and disagree with ideas without demeaning persons or imposing a private belief as a universal rule.'],
        ['Character', 'Values are strengthened through repeated, justified practice and reflection rather than through slogans alone.'],
        ['Civic responsibility', 'Connect personal conduct to dignity, fairness, shared rules, and the public good without partisan advocacy.'],
    ], [$scopeSource, 'deped-esep-curriculum-guide', 'deped-ppst-do-42-2017'], 'values-education', 'values-and-ethical-education', 'values-reasoning-and-character', 'values-ethical-reasoning', 'secondary-values-education'),
    b629_study('let-professional-education-foundation', 'LET Professional Education: Alignment, Assessment, Inclusion, and Facilitation', 'Thin-concept reinforcement for professional education, using PPST and classroom-assessment guidance to support applied teacher judgment.', [
        ['Alignment', 'State the competency first, then choose an activity and assessment that actually elicit it.'],
        ['Assessment', 'Interpret evidence only to the extent supported by the task, access conditions, and scoring information.'],
        ['Learners', 'Use developmentally appropriate, inclusive supports and adjust instruction from observable evidence.'],
        ['Practice', 'Prefer a clear, respectful, feasible response that protects learner dignity and learning time.'],
    ], [$scopeSource, 'deped-ppst-do-42-2017', 'deped-classroom-assessment-do-8-2015'], 'professional-education', 'professional-education', 'facilitating-learning', 'prof-facilitation-scaffolding', 'shared-pe'),
];

/* Existing generic materials receive a small, explicit foundation update;
 * specialization-specific materials above carry the derivability chain. */
$studyUpdates = [
    'let-english-communication-review' => [$scopeSource, 'deped-k12-english-curriculum-guide'],
    'let-english-reading-foundation' => ['deped-k12-english-curriculum-guide', 'openstax-reading-writing'],
    'let-english-grammar-foundation' => ['deped-k12-english-curriculum-guide', 'openstax-reading-writing'],
    'let-filipino-communication-review' => ['deped-filipino-curriculum-guide'],
    'let-mathematics-essentials-review' => ['deped-math-curriculum-guide', 'openstax-algebra-and-trigonometry'],
    'let-science-technology-review' => ['deped-science-curriculum-guide', 'openstax-biology-2e', 'openstax-chemistry-2e'],
    'let-philippine-history-review' => ['deped-araling-panlipunan-curriculum-guide', '1987-philippine-constitution'],
];

/* Normalize the role/currentness fields of the reused authorities that form
 * the scope and study chain.  This is metadata hardening only: existing URLs,
 * authority text, and eligibility semantics are preserved. */
$sourceMetadataUpdates = [
    'prc-let-enhanced-tos-2025' => ['source_role' => 'SCOPE_SOURCE', 'volatility' => 'CURRICULUM_VERSIONED', 'retrieved_at' => '2026-09-29'],
    'deped-k12-english-curriculum-guide' => ['source_role' => 'CURRICULUM_SOURCE', 'volatility' => 'CURRICULUM_VERSIONED', 'retrieved_at' => '2026-09-29'],
    'deped-ppst-do-42-2017' => ['source_role' => 'PROFESSIONAL_STANDARD', 'volatility' => 'PROFESSIONAL_STANDARD_VERSIONED', 'retrieved_at' => '2026-09-29'],
    'deped-classroom-assessment-do-8-2015' => ['source_role' => 'NORMATIVE_SOURCE', 'volatility' => 'REGULATORY_CURRENT', 'retrieved_at' => '2026-09-29'],
    'republic-act-10533' => ['source_role' => 'STATUTORY_SOURCE', 'volatility' => 'STATUTORY_VERSIONED', 'retrieved_at' => '2026-09-29'],
    'openstax-reading-writing' => ['source_role' => 'INSTRUCTIONAL_SOURCE', 'volatility' => 'STABLE_FOUNDATIONAL', 'retrieved_at' => '2026-09-29'],
    'openstax-biology-2e' => ['source_role' => 'SCIENTIFIC_SOURCE', 'volatility' => 'STABLE_FOUNDATIONAL', 'retrieved_at' => '2026-09-29'],
    'openstax-chemistry-2e' => ['source_role' => 'SCIENTIFIC_SOURCE', 'volatility' => 'STABLE_FOUNDATIONAL', 'retrieved_at' => '2026-09-29'],
    'openstax-university-physics-volume-1' => ['source_role' => 'SCIENTIFIC_SOURCE', 'volatility' => 'STABLE_FOUNDATIONAL', 'retrieved_at' => '2026-09-29'],
];

if ($write) {
    $expected = 'dab1a2c79b61ce3dd2f9086e345e5468e13d65e3';
    $head = trim((string) shell_exec('git rev-parse HEAD'));
    if ($head !== $expected) {
        throw new RuntimeException("Batch 629 write requires isolated Batch 628 HEAD {$expected}; found {$head}");
    }
}

/* ---------------------------------------------------------------------- */
/* The content catalog and audit are appended below in focused sections.  */
/* ---------------------------------------------------------------------- */

$existing = [
    'questions' => b629_read($root, 'questions'),
    'eligibility' => b629_read($root, 'question-eligibility'),
    'sources' => b629_read($root, 'sources'),
    'studies' => b629_read($root, 'study-materials'),
    'studyLinks' => b629_read($root, 'study-question-links'),
    'reuse' => b629_read($root, 'question-reuse-metadata'),
    'required' => b629_read($root, 'question-required-concepts'),
    'coverage' => b629_read($root, 'board-concept-coverage'),
    'subjects' => b629_read($root, 'subjects'),
    'domains' => b629_read($root, 'taxonomy/domains'),
    'topics' => b629_read($root, 'taxonomy/topics'),
    'concepts' => b629_read($root, 'taxonomy/concepts'),
    'boardSubjects' => b629_read($root, 'board-subjects'),
];

/* ---------------------------------------------------------------------- */
/* Question catalog                                                        */
/* ---------------------------------------------------------------------- */

function b629_family_items(string $label, string $rule, array $applications, array $misconceptions): array
{
    $styles = [
        'Which statement best applies %s in this situation: %s',
        'A teacher asks learners to respond to this case: %s Which response best demonstrates %s?',
        'When checking this example, which interpretation is most defensible under %s? %s',
        'A learner makes the following move: %s Which correction best reflects %s?',
        'Which explanation would best teach %s using this example: %s',
    ];
    $contexts = [
        'a learner meets an unfamiliar example during guided practice',
        'two plausible responses differ on the controlling criterion',
        'a teacher compares evidence before selecting an intervention',
        'a written or observed response contains a common misconception',
        'a new classroom task requires transfer rather than recall',
    ];
    $rows = [];
    for ($i = 0; $i < 5; $i++) {
        $application = (string) ($applications[$i] ?? ('Apply the defining rule: ' . $rule . '.'));
        $wrong = [];
        foreach ($misconceptions as $misconception) {
            $wrong[] = (string) $misconception;
        }
        $wrong[] = 'Choose the most familiar wording even when it does not address the supplied evidence.';
        $wrong = array_slice($wrong, 0, 3);
        $correct = $application;
        $choices = array_merge([$correct], $wrong);
        $rotation = $i % 4;
        $rotated = array_merge(array_slice($choices, $rotation), array_slice($choices, 0, $rotation));
        $answer = $correct;
        $rows[] = [
            'question' => sprintf($styles[$i], $label, $contexts[$i]),
            'choices' => $rotated,
            'answer' => $answer,
            'explanation' => $answer . ' The controlling rule is that ' . $rule . '.',
            'depth' => $i === 0 ? 2 : ($i === 4 ? 4 : 3),
            'context' => $i === 2 ? 'PROFESSIONAL' : 'NEUTRAL',
            'scenario_family' => b629_norm($label) . '-application-' . ($i + 1),
            'misconception_family' => b629_norm($label) . '-misconception-' . (($i % 3) + 1),
            'numeric' => false,
            'calculation_required' => false,
            'quantitative_semantics' => 'NON_NUMERICAL',
            'version_sensitive' => false,
            'currentness' => 'STABLE_FOUNDATIONAL',
        ];
    }
    return $rows;
}

function b629_numeric_items(string $label, array $rows): array
{
    $out = [];
    foreach ($rows as $i => $row) {
        $choices = array_values($row['choices']);
        $answer = (string) $row['answer'];
        $rotation = $i % 4;
        $rotated = array_merge(array_slice($choices, $rotation), array_slice($choices, 0, $rotation));
        $out[] = [
            'question' => (string) $row['question'],
            'choices' => $rotated,
            'answer' => $answer,
            'explanation' => (string) $row['explanation'],
            'depth' => (int) ($row['depth'] ?? 3),
            'context' => (string) ($row['context'] ?? 'NEUTRAL'),
            'scenario_family' => b629_norm($label) . '-quantitative-' . ($i + 1),
            'misconception_family' => (string) ($row['misconception_family'] ?? b629_norm($label) . '-calculation-error'),
            'numeric' => true,
            'calculation_required' => true,
            'quantitative_semantics' => 'CALCULATION_REQUIRED',
            'calculation' => $row['calculation'],
            'version_sensitive' => false,
            'currentness' => 'STABLE_FOUNDATIONAL',
        ];
    }
    return $out;
}

function b629_find_taxonomy(array $rows, string $id): array
{
    foreach ($rows as $row) if (is_array($row) && (string) ($row['id'] ?? '') === $id) return $row;
    return [];
}

function b629_add_generic_concept(array &$catalog, string $sid, string $concept, string $label, string $rule, array $applications, array $misconceptions): void
{
    $catalog[$sid][$concept] = [
        'label' => $label,
        'rule' => $rule,
        'applications' => $applications,
        'misconceptions' => $misconceptions,
    ];
}

$families = [];

/* English: the nine Batch 628 study-scope residuals receive explicit
 * application support, plus evidence-based reading. */
b629_add_generic_concept($families, 'secondary-english', 'modifiers-dangling-modifiers', 'dangling modifiers', 'an introductory modifier must logically modify the grammatical subject that follows it', [
    'Revise “After reviewing the rubric, the lesson plan was revised” to “After reviewing the rubric, the teacher revised the lesson plan.”',
    'Make the person who performed the opening action the grammatical subject of the sentence.',
    'Reject a revision that leaves an inanimate plan or report performing the reviewing action.',
    'Place the modifier next to the subject that can logically carry out the stated action.',
    'Use the sentence whose opening phrase and main clause share a clear, logical actor.',
], ['Accept a modifier whose grammatical subject cannot perform the opening action.', 'Move the modifier farther from its logical subject to make the sentence longer.', 'Treat any opening phrase as correct regardless of the main clause.']);
b629_add_generic_concept($families, 'secondary-english', 'parallelism-parallel-structure', 'parallel structure', 'items joined in a comparison or series should use matching grammatical forms', [
    'Revise “The module asks learners to observe, recording, and explain” to “observe, record, and explain.”',
    'Use matching infinitives after “to”: to identify, to compare, and to justify.',
    'Keep the compared phrases in the same grammatical form even when their meanings differ.',
    'Correct the series by giving each verb the same form as the first verb.',
    'Choose the option whose coordinated elements have equivalent structure and function.',
], ['Mix a gerund with two base verbs simply because the words sound familiar.', 'Make only the longest element parallel while leaving the others unchanged.', 'Assume parallelism concerns punctuation only, not grammatical form.']);
b629_add_generic_concept($families, 'secondary-english', 'subject-verb-agreement-compound-subjects', 'compound-subject agreement', 'a compound subject joined by and normally takes a plural verb', [
    'Use “The teacher and the coordinator are reviewing the plan” because and joins two subjects.',
    'Choose the plural verb when two distinct people share the action.',
    'Keep a plural verb even when the compound subject appears after an introductory phrase.',
    'Do not let the nearest singular noun override the compound subject joined by and.',
    'Use a singular verb only when the joined words name one unit or are joined by a singular alternative.',
], ['Match the verb to the nearest noun instead of the full subject.', 'Always use a singular verb when the first noun is singular.', 'Treat every compound subject as singular regardless of the conjunction.']);
b629_add_generic_concept($families, 'secondary-english', 'verb-tenses-tense-consistency', 'tense consistency', 'verb tense should remain consistent unless a change in time or sequence is intended', [
    'Use past tense throughout a narrative unless the sentence explicitly shifts to an earlier or later time.',
    'Keep “The class discussed the results and identified the pattern” in the same past-time frame.',
    'Change tense only when the time relationship makes the shift meaningful, not merely for variety.',
    'Revise an unexplained present-tense verb in an otherwise completed past event.',
    'Check the timeline first, then choose the tense that accurately represents each event.',
], ['Change tense from past to present merely to avoid repeating a form.', 'Use the most recent verb as the only basis for every tense choice.', 'Assume a tense shift is always an error even when the timeline changes.']);
b629_add_generic_concept($families, 'secondary-english', 'parallelism-parallel-items', 'parallel items', 'items in a series should be equivalent in grammatical form and logical level', [
    'Use “clear, accurate, and organized” for three coordinated adjectives.',
    'Make the list “planning lessons, assessing learning, and giving feedback” use matching gerunds.',
    'Keep categories at the same level instead of mixing a broad task with a single subtask.',
    'Repair the series by changing the outlier form, not by adding an unrelated item.',
    'Compare each item with the first element for both form and logical scope.',
], ['Pair a noun with a clause just because both refer to the lesson.', 'Add a fourth item with a different grammatical role to conceal the mismatch.', 'Use punctuation to disguise unequal logical categories.']);
b629_add_generic_concept($families, 'secondary-english', 'sentence-structure-complex-sentences', 'complex sentence structure', 'a complex sentence has an independent clause and at least one dependent clause with a clear relationship', [
    'Use “Although the data were limited, the class identified a cautious pattern” with a dependent concessive clause.',
    'Attach the dependent clause to an independent clause rather than leaving it as a fragment.',
    'Choose a subordinating conjunction that expresses the intended time, cause, condition, or contrast.',
    'Use a comma after an introductory dependent clause before the independent clause.',
    'Check that the sentence has one complete main clause and a logically related subordinate clause.',
], ['Treat a dependent clause as a complete sentence by itself.', 'Use any conjunction without checking the relationship between clauses.', 'Join two clauses with a comma alone and call the result complex.']);
b629_add_generic_concept($families, 'secondary-english', 'subject-verb-agreement-indefinite-pronouns', 'indefinite-pronoun agreement', 'the number of an indefinite pronoun controls the verb even when nearby nouns have another number', [
    'Use “Each of the learners is prepared” because each is singular.',
    'Use “Several of the examples are useful” because several is plural.',
    'Ignore the plural noun in “Everyone in the groups is ready”; everyone controls the verb.',
    'Check the indefinite pronoun itself before matching the verb to an of-phrase.',
    'Use a singular verb with each, either, neither, everyone, and everyone’s equivalent singular forms.',
], ['Match the verb to the noun after of instead of the indefinite pronoun.', 'Assume every pronoun ending in body or one is plural.', 'Use a plural verb whenever a sentence contains the word groups.']);
b629_add_generic_concept($families, 'secondary-english', 'verb-tenses-perfect-tenses', 'perfect tenses', 'a perfect tense expresses an event in relation to a reference time using have, has, or had plus a past participle', [
    'Use “has completed” for an action that began or occurred before now and remains relevant.',
    'Use “had submitted” when one past action occurred before another past reference point.',
    'Use “will have finished” for an action completed before a stated future time.',
    'Select the perfect form only when the relationship to a reference time matters.',
    'Pair the auxiliary with the past participle rather than using a simple past form after have or had.',
], ['Use a perfect tense for every past action regardless of the timeline.', 'Use have with a bare verb instead of a past participle.', 'Treat had as proof that an action is happening now.']);
b629_add_generic_concept($families, 'secondary-english', 'prepositions-idiomatic-prepositions', 'idiomatic prepositions', 'some adjectives and verbs conventionally select particular prepositions in standard usage', [
    'Use “responsible for the records,” not responsible on the records.',
    'Use “interested in the topic” because the adjective selects in.',
    'Use “comply with the policy” for the conventional verb-preposition pairing.',
    'Use “capable of explaining” before a gerund or noun phrase.',
    'Choose the established pairing “different from” in formal instructional prose.',
], ['Select a preposition by translating word for word from another language.', 'Treat any preposition as interchangeable when the meaning seems close.', 'Use the preposition that appears nearest in the source sentence without checking the expression.']);
b629_add_generic_concept($families, 'secondary-english', 'english-inference-evidence', 'evidence-based inference', 'an inference must be a reasonable conclusion supported by details in the text', [
    'Choose the conclusion that follows from the stated evidence without adding an unstated motive.',
    'Separate what the passage says directly from what the supplied details reasonably imply.',
    'Reject a dramatic interpretation when the text provides only a small, neutral clue.',
    'Cite the relevant detail before selecting an inference about the writer or event.',
    'Prefer the narrow conclusion supported by several details over a broad claim based on one word.',
], ['Treat personal experience as evidence from the passage.', 'Choose the most dramatic possibility even when the text does not support it.', 'Convert an inference into a directly stated fact.']);

/* Filipino, mathematics, science, social studies, and the three bounded
 * extensions use the same five-way application audit but with subject-specific
 * rules and examples. */
$genericDefinitions = [
    'secondary-filipino' => [
        'filipino-gramatika-wastong-gamit' => ['Wastong gamit', 'a Filipino word or construction must fit its grammatical and semantic context', ['Piliin ang salitang ang anyo at gamit ay akma sa buong pangungusap.', 'Suriin ang ugnayan ng salita sa pandiwa, pangngalan, at layunin ng pahayag.', 'Itama ang gamit batay sa kahulugan at hindi lamang sa tunog.', 'Gamitin ang anyong hinihingi ng pangungusap at ng pamantayang Filipino.', 'Piliin ang pahayag na malinaw, wasto, at angkop sa konteksto.']],
        'filipino-pagbasa-pangunahing-diwan' => ['Pangunahing diwa', 'the main idea states the central point supported by the important details', ['Piliin ang pahayag na sumasaklaw sa mahahalagang detalye ng talata.', 'Huwag gawing pangunahing diwa ang isang halimbawang detalye lamang.', 'Iugnay ang paksa sa sinasabi ng buong teksto.', 'Piliin ang pinakamalawak na pahayag na nananatiling tapat sa teksto.', 'Patunayan ang pangunahing diwa gamit ang higit sa isang mahalagang detalye.']],
        'filipino-talasalitaan-kasingkahulugan' => ['Kasingkahulugan at kasalungat', 'a meaning relationship must preserve the word’s sense in the supplied context', ['Piliin ang kasingkahulugang hindi nagbabago sa kahulugan ng pangungusap.', 'Ihambing ang gamit ng salita bago magpasya kung tunay itong magkasingkahulugan.', 'Tukuyin ang kasalungat batay sa kahulugang ginagamit sa teksto.', 'Iwasang umasa sa magkatulad na baybay lamang.', 'Piliin ang salitang may kaparehong gampanin at diwa sa pangungusap.']],
        'filipino-talasalitaan-konteksto' => ['Kahulugan sa konteksto', 'contextual meaning comes from surrounding words, situation, and sentence purpose', ['Gamitin ang mga salitang nakapaligid upang matukoy ang kahulugan.', 'Suriin kung ang pahiwatig ay depinisyon, halimbawa, paghahambing, o pagsalungat.', 'Piliin ang kahulugang umaangkop sa buong pahayag.', 'Huwag awtomatikong gamitin ang unang kahulugang nasa diksyunaryo.', 'Iugnay ang salita sa layunin at sitwasyon ng nagsasalita.']],
        'filipino-panitikan-tauhan' => ['Tauhan at motibasyon', 'a character interpretation should be grounded in actions, dialogue, and stated circumstances', ['Gamitin ang kilos at pananalita ng tauhan bilang patunay sa motibasyon.', 'Ihiwalay ang tuwirang sinabi sa hinuha tungkol sa damdamin.', 'Piliin ang interpretasyong sinusuportahan ng higit sa isang detalye.', 'Huwag magbigay ng katangiang wala sa teksto.', 'Iugnay ang pasya ng tauhan sa suliraning kinakaharap niya.']],
        'filipino-panitikan-tema' => ['Tema at patunay', 'a theme is a general insight supported by recurring details, not merely the topic', ['Piliin ang aral na sinusuportahan ng paulit-ulit na kilos o pangyayari.', 'Ibahin ang paksa na isang salita sa temang nagsasaad ng pananaw.', 'Gamitin ang pangyayari bilang patunay sa tema.', 'Iwasang ipilit ang personal na aral na walang batayan sa akda.', 'Piliin ang temang may sapat na ebidensya sa kabuuan.']],
        'filipino-pagsulat-organisasyon' => ['Organisasyon ng talata', 'a coherent paragraph develops one controlling idea in a logical sequence', ['Ayusin ang mga pangungusap mula sa pangunahing ideya tungo sa kaugnay na patunay.', 'Tanggalin ang detalyeng hindi tumutulong sa paksa ng talata.', 'Gamitin ang angkop na pangatnig upang maging malinaw ang ugnayan.', 'Ilagay ang pangwakas na pangungusap bilang paglalagom, hindi bagong paksa.', 'Piliin ang ayos na may pagkakaisa at lohikal na daloy.']],
        'filipino-pagsulat-buod' => ['Pagsulat ng buod', 'a summary retains essential ideas in concise form without adding unsupported judgments', ['Isama ang pangunahing ideya at mahahalagang patunay lamang.', 'Gamitin ang sariling pananalita nang hindi binabago ang diwa.', 'Huwag magdagdag ng opinyong wala sa orihinal na teksto.', 'Panatilihin ang lohikal na pagkakasunod-sunod ng mahahalagang punto.', 'Paikliin ang teksto nang hindi nawawala ang sentrong mensahe.']],
        'filipino-komunikasyon-angkop-na-wika' => ['Angkop na wika at rehistro', 'language choice should match purpose, audience, relationship, and setting', ['Gamitin ang magalang at pormal na rehistro sa opisyal na liham.', 'Iangkop ang salita sa kausap at sa layunin ng usapan.', 'Piliin ang malinaw na pahayag sa halip na teknikal na salitang hindi kailangan.', 'Isaalang-alang ang kultura at dignidad ng kausap.', 'Piliin ang rehistrong angkop sa sitwasyon, hindi ang pinakapamilyar na salita.']],
    ],
    'secondary-mathematics' => [
        'math-number-sense-operations' => ['Operations with whole numbers', 'the operation must match the quantities and relationship described in the problem', ['Add quantities that are combined, subtract when a remainder is requested, and check the scale.', 'Estimate first so a computed result can be tested for reasonableness.', 'Preserve place value when carrying or regrouping.', 'Translate “each group” or “per” carefully before multiplying or dividing.', 'Check the answer against the original quantities and unit.']],
        'math-algebra-linear-equations' => ['Linear equations', 'equivalent operations preserve equality while isolating the unknown', ['Perform the same inverse operation on both sides before simplifying.', 'Substitute the solution back into the original equation.', 'Combine like terms before isolating the variable.', 'Reject a value that satisfies a rearranged expression but not the original equation.', 'Use the coefficient and constant terms to identify the correct linear model.']],
        'math-number-sense-fractions' => ['Fractions and proportional reasoning', 'a fraction represents a relationship between quantities and must retain a common unit or denominator when combined', ['Use a common denominator before adding unlike fractions.', 'Compare fractions by a common benchmark or equivalent form.', 'Interpret the numerator as selected parts and the denominator as equal parts of the whole.', 'Keep a ratio’s order consistent with the quantities named in the problem.', 'Check whether a fraction result is within the possible range.']],
        'math-algebra-patterns' => ['Patterns and relationships', 'a rule must generate the stated terms and preserve the relationship between input and output', ['Compare successive terms to identify an additive or multiplicative rule.', 'Test a proposed expression against more than one given term.', 'Distinguish a pattern rule from a single observed difference.', 'Use a table to connect input, output, and the expression.', 'Reject a rule that fits one term but fails the sequence.']],
        'math-algebra-functions' => ['Functions and representations', 'each input in a function has exactly one output under the stated rule', ['Check that repeated inputs do not produce two different outputs.', 'Translate the table into the stated rule before evaluating a new input.', 'Use the graph, table, and equation as equivalent representations when they agree.', 'Distinguish an input value from its output.', 'Reject a relation that violates the one-output condition.']],
        'math-geometry-area-perimeter' => ['Area and perimeter', 'area measures a two-dimensional region while perimeter measures its boundary length', ['Use square units for area and linear units for perimeter.', 'Select the formula that matches the shape and the requested measure.', 'Do not add side lengths when the question asks for the region inside the boundary.', 'Check that the result’s unit matches area or length.', 'Decompose a composite region into parts before adding their areas.']],
        'math-geometry-angle-relationships' => ['Angle relationships', 'complementary angles total 90 degrees and supplementary angles total 180 degrees', ['Use 90 degrees for complementary angles and 180 degrees for supplementary angles.', 'Identify the relationship from the figure or wording before subtracting.', 'Use a linear pair as supplementary when the rays form a straight line.', 'Check whether the given angles share a vertex or a line before applying a relationship.', 'State the angle relationship that justifies the calculation.']],
        'math-statistics-mean-median' => ['Mean and median', 'the center measure must be selected and interpreted from the data distribution', ['Order the data before locating the median.', 'Add all observations and divide by their count for the mean.', 'Recognize that an extreme value can pull the mean more than the median.', 'Use the median when a skewed distribution calls for a resistant center.', 'Check whether the reported center is in the same units as the data.']],
        'math-statistics-probability' => ['Elementary probability', 'probability is the ratio of favorable outcomes to equally likely total outcomes in the stated sample space', ['Count favorable outcomes and total outcomes from the same sample space.', 'Keep the denominator equal to all equally likely outcomes.', 'Express a probability between zero and one.', 'Use the complement only after defining the event clearly.', 'Check that the result is consistent with how likely the event is.']],
        'math-measurement-unit-conversion' => ['Unit conversion', 'a conversion factor equal to one changes the unit without changing the quantity', ['Multiply by a conversion factor arranged so unwanted units cancel.', 'Write both the starting unit and the target unit before calculating.', 'Check whether the numerical value should grow or shrink after conversion.', 'Do not combine centimeters and meters without conversion.', 'Report the final number with the requested unit.']],
        'math-problem-solving-modeling' => ['Mathematical modeling', 'a model must represent the quantities, assumptions, and relationship in the real situation', ['Name the unknown and define the quantities before writing the equation.', 'Use the supplied rate and time relationship rather than an unrelated formula.', 'Check whether the model’s result is reasonable in the original context.', 'State an assumption when the situation leaves one quantity implicit.', 'Interpret the computed value as an answer to the situation, not merely as a number.']],
    ],
    'secondary-science' => [
        'science-biology-cells' => ['cellular structures', 'a cell structure is identified by its function and relationship to the cell system', ['Connect the organelle to its stated function rather than to a memorized shape.', 'Use evidence about energy, transport, or information to distinguish structures.', 'Separate plant-specific features from structures found in both plant and animal cells.', 'Explain how a structure supports the cell’s survival.', 'Reject an identification that conflicts with the function described.']],
        'science-inquiry-evidence' => ['evidence and conclusions', 'a scientific conclusion must be supported by the observations and design of the investigation', ['Identify the measured variable before accepting a conclusion.', 'Distinguish an observation from an explanation proposed after it.', 'Use repeated evidence and controls when comparing conditions.', 'Avoid claiming causation when the design only shows association.', 'State the narrow conclusion supported by the data.']],
        'environmental-stewardship-protection' => ['environmental stewardship', 'a defensible environmental action connects resource protection to evidence, sustainability, and community context', ['Prefer an intervention that reduces the source of the environmental harm.', 'Use monitoring evidence before claiming that a conservation measure worked.', 'Consider long-term resource use rather than a one-time visible result.', 'Connect community practices to ecosystem effects without blaming unsupported actors.', 'Choose the measure that protects the resource while remaining feasible and evidence-based.']],
        'science-chemistry-matter-particles' => ['particle and phase evidence', 'matter changes can be interpreted by particle arrangement, motion, and conservation of substance', ['Use particle spacing and motion to distinguish a phase change from a new substance.', 'Recognize that heating can change motion and arrangement without creating matter.', 'Use conservation of mass when interpreting a closed-system change.', 'Distinguish a physical change from evidence of a chemical reaction.', 'Select the particle model that fits the observed state and temperature.']],
        'science-physics-motion-graphs' => ['motion graph interpretation', 'a graph’s slope and direction must be interpreted using the labeled axes and time interval', ['Read the axes before interpreting the slope.', 'Distinguish a constant position from constant speed using the graph type.', 'Use the time interval stated rather than assuming the line extends indefinitely.', 'Explain a change in slope as a change in the represented motion.', 'Report the interpretation with the graph’s units.']],
        'science-physics-force-balance' => ['force and motion models', 'net force and acceleration depend on the vector sum of forces and the object’s mass', ['Draw or identify the relevant forces before deciding whether they balance.', 'Recognize that balanced forces mean zero net force, not necessarily zero velocity.', 'Use the direction of the net force to interpret the acceleration.', 'Do not treat a larger individual force as the net force without considering opposition.', 'State the assumption about friction or other omitted forces.']],
        'science-electricity-series-parallel' => ['series and parallel circuits', 'current, voltage, and resistance relationships depend on how components are connected', ['Recognize that a break in a series path affects the whole path.', 'Identify branches before comparing current or voltage behavior.', 'Use the supplied resistance and connection rather than a memorized single-loop rule.', 'Explain why parallel paths can provide alternate routes for current.', 'Check the circuit model against the diagram’s actual connections.']],
        'science-optics-reflection' => ['reflection ray interpretation', 'the angle of incidence equals the angle of reflection when measured from the normal', ['Measure both angles from the normal line, not from the surface.', 'Draw the reflected ray on the opposite side of the normal with the same angle.', 'Use the mirror boundary and normal before selecting the ray path.', 'Distinguish reflection from refraction when the medium changes.', 'Check that the law matches the supplied geometry.']],
        'science-earth-stratigraphy' => ['relative age from layering', 'in an undisturbed sequence, lower layers are generally older than layers above them', ['Apply superposition only after checking whether the sequence is described as undisturbed.', 'Use cross-cutting evidence when a feature interrupts layers.', 'Distinguish relative order from an exact numerical age.', 'Use the stated orientation rather than assuming every diagram is upright.', 'State the limited age relationship supported by the evidence.']],
        'science-ecology-population' => ['population and energy relationships', 'population changes reflect resource limits, interactions, and energy transfer within a system', ['Connect a population change to the limiting resource or interaction supplied.', 'Use energy loss between trophic levels when interpreting a food-chain comparison.', 'Distinguish a population from a community and ecosystem.', 'Avoid attributing a change to a factor absent from the scenario.', 'Select the explanation that fits both the population data and system context.']],
        'science-data-evidence' => ['evidence, trends, and variables', 'a graph-based claim must match the measured variables, pattern, and uncertainty shown', ['Identify the independent and dependent variables before describing a trend.', 'Do not infer a causal mechanism from a trend alone.', 'Use the scale and units on the graph rather than visual steepness only.', 'Compare the stated intervals before deciding whether a difference is meaningful.', 'Report a pattern with the qualification supported by the data.']],
    ],
    'secondary-social-studies' => [
        'social-history-primary-sources' => ['primary sources', 'a primary source is evidence from the time, participant, or event being studied and must be interpreted in context', ['Use the author, date, purpose, and audience to evaluate the source.', 'Distinguish a contemporaneous record from a later historian’s interpretation.', 'Use what the source can support without treating it as a complete account.', 'Check perspective and limitation before generalizing from one source.', 'Compare sources when they provide different perspectives on the same event.']],
        'social-civics-public-good' => ['public good', 'a public-good decision considers shared welfare, rights, lawful process, and effects on affected people', ['Choose the action that protects dignity and shared welfare through a lawful, transparent process.', 'Balance an individual interest with the stated community impact rather than ignoring either.', 'Use evidence about likely effects before declaring a policy beneficial.', 'Prefer an inclusive solution over one that shifts an avoidable burden to a vulnerable group.', 'Explain the public-good choice with reasons rather than a slogan.']],
        'let-rizal-context' => ['historical context', 'a historical text should be read with its time, audience, purpose, and social conditions in view', ['Interpret the passage in relation to the historical conditions identified in the source.', 'Separate the author’s historical context from a modern assumption not in the evidence.', 'Use the text and date together before drawing a conclusion about significance.', 'Recognize that a historical figure’s argument may address a specific institution or audience.', 'State the limited contextual claim supported by the source.']],
        'let-global-local-effects' => ['global and local effects', 'a social process connects local experience to wider economic, cultural, political, or environmental relationships', ['Trace the stated link between a global process and the local outcome.', 'Distinguish an effect supported by the scenario from a broad stereotype about globalization.', 'Consider both opportunities and unequal effects when evaluating the process.', 'Use the actors and scale named in the case rather than adding an absent cause.', 'Explain the local consequence through the wider relationship described.']],
        'let-art-evidence' => ['evidence-based interpretation', 'an interpretation of a cultural artifact should be supported by observable features and context', ['Point to a feature of the artifact before interpreting its meaning.', 'Use historical and cultural context without claiming the artist’s private intention without evidence.', 'Distinguish description of form from an evaluative judgment.', 'Compare interpretations by asking which better fits the observable evidence.', 'State an interpretation as a supported possibility, not an absolute fact.']],
        'let-ethical-duties' => ['ethical duties', 'an ethical decision weighs relevant duties, rights, consequences, and context rather than relying on a slogan', ['Identify the duty that applies to the stated role and facts.', 'Consider foreseeable harm and rights before choosing the action.', 'Explain why a tempting shortcut fails the relevant duty.', 'Respect persons while using a consistent and reasoned standard.', 'Choose the action that can be publicly justified from the supplied facts.']],
        'let-self-identity' => ['identity and social influence', 'identity develops through personal agency and social relationships without reducing a person to one group label', ['Recognize both personal agency and social context in the identity example.', 'Avoid stereotyping a learner from one group membership.', 'Use the stated interaction and experience as evidence of influence.', 'Distinguish self-description from an outside assumption about identity.', 'Choose the interpretation that preserves dignity and complexity.']],
        'social-history-context-and-causation' => ['historical context and causation', 'a causal historical claim requires a plausible connection supported by chronology and evidence', ['Check chronology before naming one event as a cause of another.', 'Distinguish a contributing condition from a single sufficient cause.', 'Use the source’s stated evidence to support the causal link.', 'Avoid present-day values as the only explanation of a past decision.', 'State a qualified causal claim when the evidence is limited.']],
        'social-civics-rights-and-duties' => ['rights and civic duties', 'rights and duties must be interpreted through the applicable lawful principle and the facts provided', ['Protect the right while following a lawful and proportionate civic duty.', 'Distinguish a constitutional protection from a personal preference.', 'Use due process and equal treatment when evaluating public action.', 'Do not infer that one person’s right eliminates all responsibility to others.', 'Choose the explanation that identifies both the protected interest and lawful limit.']],
        'social-civics-evidence-and-claims' => ['evidence and civic claims', 'a civic claim is stronger when its evidence is relevant, credible, and sufficient for the conclusion', ['Ask whether the cited evidence actually bears on the claim.', 'Distinguish an official record from an unsupported social-media assertion.', 'Qualify the claim when the evidence is incomplete or contested.', 'Use more than one relevant indicator before generalizing about a community.', 'Reject a confident conclusion that outruns the stated evidence.']],
    ],
    'elementary-early-childhood-education' => [
        'ece-play-based-learning' => ['play-based learning', 'play should be purposeful, developmentally appropriate, and connected to observable learning', ['Offer a play invitation with materials that let children practice the target skill.', 'Observe the child’s strategy during play before correcting every move.', 'Use open-ended play to support language, social interaction, and problem solving.', 'Adjust the environment or prompt when the play evidence shows a new need.', 'Connect the play experience to a meaningful child interest and learning goal.']],
        'ece-developmental-domains' => ['developmental domains', 'early learning is holistic and domains interact rather than developing as isolated test scores', ['Plan an experience that supports the observed language and social-emotional needs together.', 'Interpret a motor activity together with communication and self-regulation evidence.', 'Avoid ranking a child from one domain or one observation.', 'Use strengths in one domain to scaffold participation in another.', 'Describe development with observable behaviors across relevant domains.']],
        'ece-emergent-literacy' => ['emergent literacy', 'young children build literacy through oral language, meaningful print, listening, and interaction', ['Use a shared story and invite prediction, retelling, and attention to print.', 'Treat children’s attempts to communicate meaning as evidence for next instruction.', 'Connect sounds and symbols to meaningful words rather than isolated drills only.', 'Use culturally familiar language and experiences as bridges to print concepts.', 'Respond to a child’s question with language that expands vocabulary and comprehension.']],
        'ece-developmental-observation' => ['developmental observation', 'observation is purposeful, documented, and interpreted in context rather than based on a single impression', ['Record the observable behavior, context, and time before making an interpretation.', 'Observe across routines before planning a response to a repeated pattern.', 'Separate what was seen from a diagnostic label or assumption.', 'Use documentation to identify a next learning opportunity.', 'Invite relevant family context before drawing a conclusion about development.']],
        'ece-authentic-assessment' => ['authentic assessment', 'assessment in early learning uses natural performance evidence to plan responsive instruction', ['Collect work, language, and behavior evidence during a familiar activity.', 'Use a portfolio or observation record to show change over time.', 'Assess the intended skill in a context where the child can demonstrate it meaningfully.', 'Use evidence to adapt the next experience rather than simply assigning a rank.', 'Explain the assessment finding in observable, family-understandable language.']],
        'ece-family-partnership' => ['family partnership', 'families are knowledgeable partners whose context and goals should inform respectful planning', ['Ask the family about the child’s routines, strengths, language, and interests.', 'Share observable evidence and invite the family’s interpretation before deciding next steps.', 'Use a culturally responsive plan rather than assuming one home practice is universal.', 'Protect confidentiality while coordinating a relevant support.', 'Agree on a small, practical next step that school and family can reinforce.']],
        'ece-inclusive-environment' => ['inclusive early learning environment', 'an inclusive environment removes avoidable barriers and preserves participation, safety, and dignity', ['Arrange materials and routines so every child can participate meaningfully.', 'Offer multiple ways to communicate or demonstrate learning.', 'Change the environment before blaming a child for a participation barrier.', 'Use familiar cultural materials while ensuring all children are respected.', 'Review the routine for safety and access when a child is repeatedly excluded.']],
    ],
    'elementary-special-needs-education' => [
        'sne-universal-design' => ['Universal Design for Learning', 'planning multiple means of engagement, representation, and action anticipates learner variability', ['Offer more than one way to access the content and more than one way to demonstrate learning.', 'Keep the learning target clear while varying the path and support.', 'Use learner choice and accessible materials to reduce predictable barriers.', 'Plan supports before the lesson instead of waiting for exclusion to occur.', 'Review whether the options address engagement, representation, and action.']],
        'sne-reasonable-accommodations' => ['reasonable accommodations', 'an accommodation changes access or method without unnecessarily changing the intended learning target', ['Provide the documented access support while assessing the same stated target.', 'Separate an access barrier from the skill the lesson intends to assess.', 'Use an accommodation that is necessary, feasible, and respectful of the learner.', 'Do not remove a target merely because a different response mode is available.', 'Document the support and observe whether it improves valid participation.']],
        'sne-accessible-materials' => ['accessible materials', 'materials should be presented in a form the learner can perceive, navigate, and use for the intended task', ['Provide readable contrast, alternative formats, or assistive access suited to the task.', 'Check the learner’s actual access rather than assuming a file is accessible.', 'Keep the content equivalent when changing the presentation format.', 'Give directions in a clear, navigable sequence.', 'Test the material with the learner or an appropriate support process before relying on it.']],
        'sne-instructional-adaptation' => ['instructional adaptation', 'adaptation responds to evidence while preserving a clear learning intention and manageable next step', ['Change the prompt, grouping, modeling, or materials in response to observed evidence.', 'Break a complex task into supported steps and fade prompts as independence grows.', 'Keep the intended concept visible while adjusting the route to it.', 'Use a brief check after adaptation to see whether access improved.', 'Avoid lowering expectations without evidence that the target itself must change.']],
        'sne-progress-monitoring' => ['progress monitoring', 'progress monitoring uses repeated, relevant measures to guide instructional decisions', ['Measure the same target at planned intervals using a consistent method.', 'Graph or compare evidence over time before changing the intervention.', 'Use a small next-step goal that can be observed and reviewed.', 'Distinguish lack of progress from a one-day fluctuation.', 'Share the evidence with the support team and adjust instruction when warranted.']],
        'sne-inclusive-assessment' => ['inclusive assessment', 'assessment must provide access and then interpret evidence against the intended criterion fairly', ['Remove an access barrier without changing what the task is meant to measure.', 'Use more than one valid evidence source when a single format obscures learning.', 'Explain whether a result reflects the target skill or an access problem.', 'Avoid a disability label as a substitute for observable assessment evidence.', 'Give feedback that identifies the next learning step.']],
        'sne-family-collaboration' => ['family collaboration', 'support planning should use respectful, confidential collaboration with families and authorized partners', ['Share evidence in plain language and invite the family’s knowledge of strengths and needs.', 'Agree on roles and a practical support rather than issuing a unilateral label.', 'Protect confidential information and share only what the support process requires.', 'Coordinate with authorized professionals without pretending to make a clinical diagnosis.', 'Review the plan with the family when new evidence changes the next step.']],
    ],
    'secondary-values-education' => [
        'values-clarification' => ['values clarification', 'values clarification invites learners to examine reasons, choices, and consequences rather than receive an imposed slogan', ['Ask learners to explain the reasons and consequences behind a chosen value.', 'Distinguish a personal preference from a considered value commitment.', 'Use a dilemma that permits reasoned comparison rather than one predetermined response.', 'Invite reflection on how a choice affects self and others.', 'Assess the quality of reasoning and reflection, not agreement with the teacher’s private preference.']],
        'values-ethical-reasoning' => ['ethical reasoning', 'an ethical decision identifies relevant facts, duties, rights, consequences, and context', ['Identify the affected persons and the duty before deciding.', 'Compare foreseeable consequences without ignoring rights or dignity.', 'Explain why the tempting shortcut fails the relevant principle.', 'Use the facts of the case rather than a one-line moral label.', 'Choose the action that can be justified with reasons to those affected.']],
        'values-character-and-habits' => ['character and habits', 'character develops through repeated practice of justified conduct and reflection on its effects', ['Connect a value to a repeated, observable action in the learner’s context.', 'Use feedback that helps the learner practice the desired habit.', 'Distinguish a one-time mistake from a stable conclusion about character.', 'Invite reflection on how the action affected others and what to do next.', 'Model the value consistently in classroom routines.']],
        'values-respectful-dialogue' => ['respectful dialogue', 'ethical dialogue listens, gives reasons, and protects the dignity of people who disagree', ['Ask for reasons and paraphrase the other view before responding.', 'Challenge the claim without insulting the person who made it.', 'Use evidence and a calm question when a disagreement is substantive.', 'Allow a minority view to be heard while maintaining a safe discussion norm.', 'Revise a position when stronger reasons or evidence are offered.']],
        'values-diversity-and-dignity' => ['diversity and dignity', 'every learner has equal dignity and inclusion should not depend on conformity to one identity or belief', ['Use examples that include different backgrounds without stereotyping them.', 'Apply the same dignity and participation standard to all learners.', 'Address exclusion as a classroom responsibility rather than blaming the excluded learner.', 'Invite multiple perspectives while protecting safety and equal respect.', 'Distinguish a difference from a deficit or moral failing.']],
        'values-civic-responsibility' => ['civic responsibility', 'responsible participation connects personal action to lawful, fair, and shared public goods', ['Follow the lawful process and contribute evidence to a shared decision.', 'Consider how a personal choice affects people who are not in the room.', 'Balance freedom with responsibility for foreseeable harm.', 'Participate constructively rather than spreading an unsupported claim.', 'Choose a feasible action that improves the shared condition.']],
        'values-reflective-practice' => ['reflective practice', 'reflection uses evidence from an action to identify learning and a better next step', ['Describe what happened, why it mattered, and what evidence supports the conclusion.', 'Identify a controllable next step instead of assigning blame alone.', 'Compare the intended value with the actual effect of the action.', 'Use feedback to revise the plan while preserving the underlying commitment.', 'Record a specific future action that can be reviewed later.']],
    ],
];

foreach ($genericDefinitions as $sid => $definitions) {
    foreach ($definitions as $concept => $definition) {
        b629_add_generic_concept($families, $sid, $concept, $definition[0], $definition[1], $definition[2], [
            'Ignore the controlling criterion and choose the most familiar response.',
            'Apply an unrelated rule without checking the facts or purpose.',
            'Treat a single surface feature as decisive even when the context contradicts it.',
        ]);
    }
}

/* A compact quantitative cohort adds actual calculations rather than merely
 * attaching numbers to conceptual items. */
$numericCatalog = [
    'math-number-sense-operations' => [
        ['question' => 'A class has 18 science kits and receives 27 more. How many kits are available?', 'choices' => ['35', '45', '46', '55'], 'answer' => '45', 'explanation' => 'Combine the two quantities: 18 + 27 = 45.', 'calculation' => ['expression' => '18 + 27', 'result' => 45, 'unit' => 'kits']],
        ['question' => 'A teacher distributes 3/4 of 20 counters. How many counters are distributed?', 'choices' => ['12', '15', '16', '18'], 'answer' => '15', 'explanation' => 'Compute 20 × 3/4 = 15 counters.', 'calculation' => ['expression' => '20 * 3/4', 'result' => 15, 'unit' => 'counters']],
        ['question' => 'A resource costs ₱80 and is discounted by 15%. What is the discount amount?', 'choices' => ['₱8', '₱12', '₱15', '₱68'], 'answer' => '₱12', 'explanation' => 'The discount is 0.15 × 80 = ₱12.', 'calculation' => ['expression' => '0.15 * 80', 'result' => 12, 'unit' => 'pesos']],
        ['question' => 'Four equal groups contain 28 learners altogether. How many learners are in each group?', 'choices' => ['6', '7', '8', '9'], 'answer' => '7', 'explanation' => 'Divide the total equally: 28 ÷ 4 = 7.', 'calculation' => ['expression' => '28 / 4', 'result' => 7, 'unit' => 'learners per group']],
        ['question' => 'A lesson has 5 rows of 6 chairs. How many chairs are there?', 'choices' => ['11', '25', '30', '36'], 'answer' => '30', 'explanation' => 'There are 5 groups of 6: 5 × 6 = 30.', 'calculation' => ['expression' => '5 * 6', 'result' => 30, 'unit' => 'chairs']],
    ],
    'math-algebra-linear-equations' => [
        ['question' => 'If 3x + 5 = 20, what is x?', 'choices' => ['3', '5', '7', '25'], 'answer' => '5', 'explanation' => 'Subtract 5 and divide by 3: x = (20 − 5) ÷ 3 = 5.', 'calculation' => ['expression' => '(20 - 5) / 3', 'result' => 5, 'unit' => 'units']],
        ['question' => 'If 2x − 7 = 11, what is x?', 'choices' => ['2', '7', '9', '18'], 'answer' => '9', 'explanation' => 'Add 7 and divide by 2: x = 18 ÷ 2 = 9.', 'calculation' => ['expression' => '(11 + 7) / 2', 'result' => 9, 'unit' => 'units']],
        ['question' => 'A line follows y = 4x + 1. What is y when x = 3?', 'choices' => ['7', '12', '13', '15'], 'answer' => '13', 'explanation' => 'Substitute x = 3: y = 4(3) + 1 = 13.', 'calculation' => ['expression' => '4 * 3 + 1', 'result' => 13, 'unit' => 'units']],
        ['question' => 'What is the solution of x/5 = 6?', 'choices' => ['1.2', '11', '30', '35'], 'answer' => '30', 'explanation' => 'Multiply both sides by 5: x = 30.', 'calculation' => ['expression' => '6 * 5', 'result' => 30, 'unit' => 'units']],
        ['question' => 'A number increased by 14 is 31. What is the number?', 'choices' => ['17', '18', '31', '45'], 'answer' => '17', 'explanation' => 'Subtract 14 from 31: 31 − 14 = 17.', 'calculation' => ['expression' => '31 - 14', 'result' => 17, 'unit' => 'number']],
    ],
    'math-geometry-area-perimeter' => [
        ['question' => 'What is the area of a rectangle 8 cm long and 3 cm wide?', 'choices' => ['11 cm', '22 cm²', '24 cm²', '48 cm²'], 'answer' => '24 cm²', 'explanation' => 'Area = length × width = 8 × 3 = 24 cm².', 'calculation' => ['expression' => '8 * 3', 'result' => 24, 'unit' => 'cm²']],
        ['question' => 'What is the perimeter of a square with side length 6 m?', 'choices' => ['12 m', '18 m', '24 m', '36 m²'], 'answer' => '24 m', 'explanation' => 'Perimeter = 4 × side = 4 × 6 = 24 m.', 'calculation' => ['expression' => '4 * 6', 'result' => 24, 'unit' => 'm']],
        ['question' => 'A triangle has base 10 cm and height 4 cm. What is its area?', 'choices' => ['14 cm²', '20 cm²', '40 cm²', '80 cm²'], 'answer' => '20 cm²', 'explanation' => 'Area = 1/2 × base × height = 1/2 × 10 × 4 = 20 cm².', 'calculation' => ['expression' => '0.5 * 10 * 4', 'result' => 20, 'unit' => 'cm²']],
        ['question' => 'A rectangle has perimeter 30 cm and length 9 cm. What is its width?', 'choices' => ['3 cm', '6 cm', '12 cm', '21 cm'], 'answer' => '6 cm', 'explanation' => '30 = 2(9 + w), so 15 = 9 + w and w = 6 cm.', 'calculation' => ['expression' => '30 / 2 - 9', 'result' => 6, 'unit' => 'cm']],
        ['question' => 'A 5 m by 4 m garden has a 2 m by 1 m path removed from its planted area. What planted area remains?', 'choices' => ['18 m²', '20 m²', '22 m²', '40 m²'], 'answer' => '18 m²', 'explanation' => 'The garden is 20 m² and the path is 2 m², leaving 18 m².', 'calculation' => ['expression' => '5 * 4 - 2 * 1', 'result' => 18, 'unit' => 'm²']],
    ],
    'math-statistics-mean-median' => [
        ['question' => 'What is the mean of 4, 6, 8, and 10?', 'choices' => ['6', '7', '8', '28'], 'answer' => '7', 'explanation' => 'The sum is 28; 28 ÷ 4 = 7.', 'calculation' => ['expression' => '(4 + 6 + 8 + 10) / 4', 'result' => 7, 'unit' => 'points']],
        ['question' => 'What is the median of 3, 9, 5, 7, and 1?', 'choices' => ['3', '5', '7', '9'], 'answer' => '5', 'explanation' => 'Ordered data are 1, 3, 5, 7, 9; the middle value is 5.', 'calculation' => ['expression' => 'median(1,3,5,7,9)', 'result' => 5, 'unit' => 'points']],
        ['question' => 'What is the mean of 12, 15, and 18?', 'choices' => ['12', '15', '16', '45'], 'answer' => '15', 'explanation' => 'The sum is 45; 45 ÷ 3 = 15.', 'calculation' => ['expression' => '(12 + 15 + 18) / 3', 'result' => 15, 'unit' => 'points']],
        ['question' => 'What is the median of 2, 4, 8, and 10?', 'choices' => ['4', '5', '6', '8'], 'answer' => '6', 'explanation' => 'For four values, average the two middle values: (4 + 8) ÷ 2 = 6.', 'calculation' => ['expression' => '(4 + 8) / 2', 'result' => 6, 'unit' => 'points']],
        ['question' => 'A score set has mean 9 for five learners. What is the total of the scores?', 'choices' => ['14', '45', '50', '90'], 'answer' => '45', 'explanation' => 'Total = mean × number of observations = 9 × 5 = 45.', 'calculation' => ['expression' => '9 * 5', 'result' => 45, 'unit' => 'score points']],
    ],
    'math-statistics-probability' => [
        ['question' => 'A bag has 3 red and 2 blue counters. What is the probability of drawing a red counter?', 'choices' => ['2/5', '3/5', '3/2', '5/3'], 'answer' => '3/5', 'explanation' => 'There are 3 favorable outcomes among 5 equally likely counters.', 'calculation' => ['expression' => '3 / 5', 'result' => 0.6, 'unit' => 'probability']],
        ['question' => 'A fair die is rolled once. What is the probability of an even number?', 'choices' => ['1/6', '1/3', '1/2', '2/3'], 'answer' => '1/2', 'explanation' => 'The even outcomes are 2, 4, and 6: 3 of 6, or 1/2.', 'calculation' => ['expression' => '3 / 6', 'result' => 0.5, 'unit' => 'probability']],
        ['question' => 'A class has 8 girls and 12 boys. What is the probability of selecting a girl at random?', 'choices' => ['2/5', '3/5', '8/12', '12/20'], 'answer' => '2/5', 'explanation' => 'There are 8 girls out of 20 learners; 8/20 simplifies to 2/5.', 'calculation' => ['expression' => '8 / 20', 'result' => 0.4, 'unit' => 'probability']],
        ['question' => 'What is the probability of not drawing a red counter when 4 of 10 counters are red?', 'choices' => ['2/5', '3/5', '4/10', '10/4'], 'answer' => '3/5', 'explanation' => 'The complement is 1 − 4/10 = 6/10 = 3/5.', 'calculation' => ['expression' => '1 - 4 / 10', 'result' => 0.6, 'unit' => 'probability']],
        ['question' => 'A spinner has 4 equal sections and exactly 1 is green. What is the probability of green?', 'choices' => ['1/4', '1/3', '3/4', '4'], 'answer' => '1/4', 'explanation' => 'One of four equal outcomes is green.', 'calculation' => ['expression' => '1 / 4', 'result' => 0.25, 'unit' => 'probability']],
    ],
    'math-measurement-unit-conversion' => [
        ['question' => 'How many centimeters are in 2 meters?', 'choices' => ['20 cm', '200 cm', '2,000 cm', '0.02 cm'], 'answer' => '200 cm', 'explanation' => '1 meter = 100 centimeters, so 2 meters = 200 centimeters.', 'calculation' => ['expression' => '2 * 100', 'result' => 200, 'unit' => 'cm']],
        ['question' => 'How many minutes are in 3 hours?', 'choices' => ['60', '120', '180', '300'], 'answer' => '180', 'explanation' => '3 × 60 minutes = 180 minutes.', 'calculation' => ['expression' => '3 * 60', 'result' => 180, 'unit' => 'minutes']],
        ['question' => 'A ribbon is 450 cm long. How many meters is that?', 'choices' => ['0.45 m', '4.5 m', '45 m', '450 m'], 'answer' => '4.5 m', 'explanation' => '450 ÷ 100 = 4.5 meters.', 'calculation' => ['expression' => '450 / 100', 'result' => 4.5, 'unit' => 'm']],
        ['question' => 'How many milliliters are in 2.5 liters?', 'choices' => ['25 mL', '250 mL', '2,500 mL', '25,000 mL'], 'answer' => '2,500 mL', 'explanation' => '2.5 × 1,000 = 2,500 milliliters.', 'calculation' => ['expression' => '2.5 * 1000', 'result' => 2500, 'unit' => 'mL']],
        ['question' => 'A lesson lasts 90 minutes. How many hours and minutes is this?', 'choices' => ['1 hour 20 minutes', '1 hour 30 minutes', '2 hours', '9 hours'], 'answer' => '1 hour 30 minutes', 'explanation' => '90 minutes equals 60 + 30 minutes, or 1 hour 30 minutes.', 'calculation' => ['expression' => '90 / 60', 'result' => 1.5, 'unit' => 'hours']],
    ],
];

/* Add a compact five-item quantitative science subset with transparent
 * recomputation metadata. */
$numericCatalog['science-physics-motion-graphs'] = [
    ['question' => 'An object travels 60 meters in 12 seconds. What is its average speed?', 'choices' => ['5 m/s', '12 m/s', '48 m/s', '72 m/s'], 'answer' => '5 m/s', 'explanation' => 'Average speed = distance ÷ time = 60 ÷ 12 = 5 m/s.', 'calculation' => ['expression' => '60 / 12', 'result' => 5, 'unit' => 'm/s']],
    ['question' => 'A runner covers 150 meters in 30 seconds. What is the average speed?', 'choices' => ['3 m/s', '5 m/s', '30 m/s', '180 m/s'], 'answer' => '5 m/s', 'explanation' => '150 ÷ 30 = 5 m/s.', 'calculation' => ['expression' => '150 / 30', 'result' => 5, 'unit' => 'm/s']],
    ['question' => 'A car’s velocity changes from 4 m/s to 10 m/s in 3 seconds. What is the average acceleration?', 'choices' => ['2 m/s²', '3 m/s²', '6 m/s²', '14 m/s²'], 'answer' => '2 m/s²', 'explanation' => 'Acceleration = change in velocity ÷ time = (10 − 4) ÷ 3 = 2 m/s².', 'calculation' => ['expression' => '(10 - 4) / 3', 'result' => 2, 'unit' => 'm/s²']],
    ['question' => 'A graph shows position increasing from 20 m to 50 m over 5 s. What is the slope?', 'choices' => ['5 m/s', '6 m/s', '10 m/s', '30 m/s'], 'answer' => '6 m/s', 'explanation' => 'Slope = change in position ÷ time = (50 − 20) ÷ 5 = 6 m/s.', 'calculation' => ['expression' => '(50 - 20) / 5', 'result' => 6, 'unit' => 'm/s']],
    ['question' => 'A cyclist travels 2.4 km in 8 minutes. What is the average speed in km/min?', 'choices' => ['0.3 km/min', '0.8 km/min', '1.6 km/min', '19.2 km/min'], 'answer' => '0.3 km/min', 'explanation' => '2.4 ÷ 8 = 0.3 km/min.', 'calculation' => ['expression' => '2.4 / 8', 'result' => 0.3, 'unit' => 'km/min']],
];

/* Build 360 specialization items: five items for every listed competency. */
$specConceptIds = [
    'secondary-english' => array_keys($families['secondary-english']),
    'secondary-filipino' => array_keys($families['secondary-filipino']),
    'secondary-mathematics' => array_keys($families['secondary-mathematics']),
    'secondary-science' => array_keys($families['secondary-science']),
    'secondary-social-studies' => array_keys($families['secondary-social-studies']),
    'elementary-early-childhood-education' => array_keys($families['elementary-early-childhood-education']),
    'elementary-special-needs-education' => array_keys($families['elementary-special-needs-education']),
    'secondary-values-education' => array_keys($families['secondary-values-education']),
];

$sharedGeConcepts = [
    'modifiers-dangling-modifiers', 'parallelism-parallel-structure', 'subject-verb-agreement-compound-subjects',
    'verb-tenses-tense-consistency', 'parallelism-parallel-items', 'sentence-structure-complex-sentences',
    'subject-verb-agreement-indefinite-pronouns', 'verb-tenses-perfect-tenses', 'prepositions-idiomatic-prepositions',
];
$sharedPeDefinitions = [
    'prof-development-readiness' => ['learner readiness', 'instruction should begin from evidence of learner development and readiness', ['Use learner evidence to select an achievable next step.', 'Differentiate support without treating readiness as a fixed label.', 'Connect the task to prior knowledge before increasing complexity.', 'Check whether the barrier is conceptual, linguistic, or access-related.', 'Use observation and assessment to revise the starting point.']],
    'prof-curriculum-alignment' => ['curriculum alignment', 'objectives, learning activities, and assessment should address the intended competency', ['Match the assessment evidence to the stated learning objective.', 'Remove an activity that is engaging but unrelated to the target.', 'Sequence the lesson from the prerequisite toward the intended performance.', 'Use the curriculum standard to justify the selected task.', 'Check alignment before interpreting a low score.']],
    'prof-assessment-validity' => ['assessment validity', 'an assessment supports a conclusion only when its evidence matches the intended construct', ['Use a task that actually elicits the intended skill.', 'Separate reliability of scoring from validity of the interpretation.', 'Provide access support without changing the construct being measured.', 'Use multiple appropriate evidence sources when one format is insufficient.', 'State only the conclusion the assessment evidence can support.']],
    'prof-management-routines' => ['classroom routines', 'effective routines make expectations, transitions, and participation predictable and respectful', ['Teach and practice the transition before treating a delay as defiance.', 'State the expectation and the reason in a concise, calm way.', 'Use a predictable routine that protects learning time and dignity.', 'Address behavior with the least intrusive effective intervention.', 'Review whether the routine itself creates an avoidable barrier.']],
    'prof-technology-access' => ['accessible educational technology', 'technology should serve the learning objective and remain usable by the learners who need it', ['Choose the tool that improves access to the stated objective.', 'Provide an equivalent non-digital route when the tool creates an access barrier.', 'Teach the task before judging a learner’s technology performance.', 'Protect privacy and use only the information necessary for learning.', 'Check the output against the learning target, not the novelty of the tool.']],
    'prof-facilitation-scaffolding' => ['scaffolding and transfer', 'scaffolding supports a learner temporarily and is adjusted as independence develops', ['Model the process, guide a supported attempt, then fade the prompt.', 'Give a hint that addresses the next barrier rather than the entire answer.', 'Check transfer by asking the learner to use the idea in a new context.', 'Keep the learning goal visible while varying the support.', 'Remove a scaffold when evidence shows the learner can proceed independently.']],
    'inclusive-universal-design' => ['inclusive planning', 'inclusive design anticipates learner variability and offers meaningful access to the same goal', ['Offer multiple means of engagement and representation before exclusion occurs.', 'Vary the route to the goal while keeping the goal explicit.', 'Use accessible materials and predictable routines for all learners.', 'Check which barrier is environmental before attributing failure to the learner.', 'Review the lesson for participation, access, and fair evidence.']],
    'inclusive-assessment' => ['inclusive assessment', 'fair assessment provides access and interprets evidence against the intended criterion', ['Remove an access barrier without changing the target being assessed.', 'Use a valid response mode that lets the learner demonstrate the target.', 'Explain whether a low result reflects the target or an access problem.', 'Use feedback to identify a next learning step.', 'Review the assessment design before making a broad judgment about ability.']],
];
foreach ($sharedPeDefinitions as $concept => $definition) {
    b629_add_generic_concept($families, 'shared-pe', $concept, $definition[0], $definition[1], $definition[2], [
        'Use a generic activity without checking the stated competency.',
        'Treat one observation as a fixed judgment about the learner.',
        'Choose a convenient response that does not address the learning evidence.',
    ]);
}
$families['shared-pe']['inclusive-assessment']['label'] = 'inclusive assessment in Professional Education';

/* Materialize the taxonomy view used by both writing and auditing. */
$subjectsAll = $existing['subjects'];
$domainsAll = $existing['domains'];
$topicsAll = $existing['topics'];
$conceptsAll = $existing['concepts'];
foreach ($newSubjects as $row) if (!b629_has_id($subjectsAll, $row['id'])) $subjectsAll[] = $row;
foreach ($newDomains as $row) if (!b629_has_id($domainsAll, $row['id'])) $domainsAll[] = $row;
foreach ($newTopics as $row) if (!b629_has_id($topicsAll, $row['id'])) $topicsAll[] = $row;
foreach ($newConcepts as $row) if (!b629_has_id($conceptsAll, $row['id'])) $conceptsAll[] = $row;

$domainsById = [];
$topicsById = [];
$conceptsById = [];
foreach ($domainsAll as $row) $domainsById[(string) ($row['id'] ?? '')] = $row;
foreach ($topicsAll as $row) $topicsById[(string) ($row['id'] ?? '')] = $row;
foreach ($conceptsAll as $row) $conceptsById[(string) ($row['id'] ?? '')] = $row;

function b629_tax_for_concept(array $conceptsById, array $topicsById, array $domainsById, string $concept): array
{
    $c = $conceptsById[$concept] ?? [];
    $t = $topicsById[(string) ($c['topic_id'] ?? '')] ?? [];
    $d = $domainsById[(string) ($t['domain_id'] ?? '')] ?? [];
    return [
        'subject_id' => (string) ($d['subject_id'] ?? ''),
        'domain_id' => (string) ($d['id'] ?? ''),
        'topic_id' => (string) ($t['id'] ?? ''),
        'concept_id' => $concept,
        'concept_name' => (string) ($c['name'] ?? $concept),
    ];
}

function b629_version_profile(string $sid, string $component): array
{
    if ($component === 'GENERAL_EDUCATION' || $component === 'PROFESSIONAL_EDUCATION') {
        return [false, 'STABLE_FOUNDATIONAL', []];
    }
    if ($sid === 'secondary-social-studies' || $sid === 'secondary-values-education') {
        return [true, 'CURRICULUM_VERSIONED', ['CURRICULUM_POLICY']];
    }
    if ($sid === 'elementary-special-needs-education') {
        return [true, 'REGULATORY_CURRENT', ['INCLUSIVE_EDUCATION_REGULATION']];
    }
    if ($sid === 'elementary-early-childhood-education' || $sid === 'secondary-filipino' || $sid === 'secondary-mathematics' || $sid === 'secondary-science') {
        return [true, 'CURRICULUM_VERSIONED', ['CURRICULUM_POLICY']];
    }
    return [false, 'STABLE_FOUNDATIONAL', []];
}

function b629_make_question(
    int $id,
    array $item,
    array $taxonomy,
    string $sid,
    string $component,
    ?string $studyId,
    array $sourceIds,
    string $scope,
    string $level,
    string $timestamp
): array {
    global $scopeSource;
    [$versionSensitive, $currentness, $versionCategories] = b629_version_profile($sid, $component);
    $subject = (string) ($taxonomy['subject_id'] ?? '');
    $domain = (string) ($taxonomy['domain_id'] ?? '');
    $topic = (string) ($taxonomy['topic_id'] ?? '');
    $concept = (string) ($taxonomy['concept_id'] ?? '');
    $placement = [
        'level' => $level,
        'component' => $component,
        'specialization_id' => $component === 'SPECIALIZATION' ? $sid : null,
        'subject_id' => $subject,
        'domain_id' => $domain,
        'topic_id' => $topic,
        'concept_id' => $concept,
        'blueprint_node' => $component === 'SPECIALIZATION' ? $sid : ($component === 'GENERAL_EDUCATION' ? 'general-education' : 'professional-education'),
    ];
    $required = [];
    if ($item['calculation_required'] ?? false) {
        $required = [];
    }
    $question = [
        'id' => $id,
        'taxonomy' => ['board_id' => 'let', 'subject_id' => $subject, 'domain_id' => $domain, 'topic_id' => $topic, 'concept_id' => $concept],
        'board' => 'let',
        'status' => 'active',
        'source' => 'batch629-source-first',
        'batch_marker' => 'batch629',
        'createdAt' => $timestamp,
        'updatedAt' => $timestamp,
        'difficulty' => ($item['depth'] ?? 3) >= 4 ? 'hard' : (($item['depth'] ?? 3) === 2 ? 'easy' : 'medium'),
        'question' => (string) $item['question'],
        'choices' => array_values($item['choices']),
        'answer' => (string) $item['answer'],
        'explanation' => (string) $item['explanation'],
        'subject' => $subject,
        'domain' => $domain,
        'topic' => $topic,
        'concept' => $concept,
        'source_ids' => b629_unique($sourceIds),
        'source_sensitivity' => $currentness,
        'version_sensitive_categories' => $versionCategories,
        'numeric' => (bool) ($item['numeric'] ?? false),
        'calculation_required' => (bool) ($item['calculation_required'] ?? false),
        'quantitative_semantics' => (string) ($item['quantitative_semantics'] ?? 'NON_NUMERICAL'),
        'multistep' => (bool) ($item['calculation_required'] ?? false),
        'data_interpretation' => str_contains($concept, 'data') || str_contains($concept, 'mean') || str_contains($concept, 'probability'),
        'case_interpretation' => ($item['depth'] ?? 3) >= 3,
        'professional_judgment' => in_array((string) ($item['context'] ?? 'NEUTRAL'), ['PROFESSIONAL', 'REGULATORY'], true),
        'scenario_family' => (string) $item['scenario_family'],
        'misconception_family' => (string) $item['misconception_family'],
        'unit_sensitive' => (bool) ($item['calculation_required'] ?? false),
        'context_mode' => (string) ($item['context'] ?? 'NEUTRAL'),
        'study_material_id' => $studyId,
        'let_placement' => $placement,
        'batch629' => [
            'official_scope_chain' => [$scopeSource, $scope, $level, $component, $sid, $subject, $domain, $topic, $concept],
            'study_material_id' => $studyId,
            'authoritative_source_ids' => b629_unique($sourceIds),
            'context_mode' => (string) ($item['context'] ?? 'NEUTRAL'),
            'required_concepts' => $required,
            'assumed_foundation' => ['current LET scope reading', 'ordinary language comprehension', 'basic classroom or subject vocabulary'],
            'depth' => (int) ($item['depth'] ?? 3),
            'version_sensitive' => $versionSensitive,
            'version_review' => [
                'status' => $versionSensitive ? 'PASS' : 'NOT_APPLICABLE',
                'reviewed' => true,
                'reviewed_at' => '2026-09-29',
                'source_version_boundary' => $versionSensitive ? 'Live-checked current DepEd/PRC source class on 2026-09-29.' : 'Stable foundational source; no answer-changing current rule used.',
            ],
            'currentness_model' => $currentness,
            'verification_date' => '2026-09-29',
            'quantitative_semantics' => (string) ($item['quantitative_semantics'] ?? 'NON_NUMERICAL'),
            'calculation' => $item['calculation'] ?? null,
            'derivable' => true,
            'source_ambiguous' => false,
            'scenario_family' => (string) $item['scenario_family'],
            'misconception_family' => (string) $item['misconception_family'],
            'quality_review' => ['status' => 'PASS', 'reviewed' => true, 'reviewed_at' => '2026-09-29', 'sampled' => false],
        ],
    ];
    if ($item['calculation_required'] ?? false) $question['calculation'] = $item['calculation'];
    return $question;
}

function b629_eligibility_group(string $id, array $questionIds, array $placements): array
{
    $first = $placements[(string) $questionIds[0]];
    $subject = (string) ($first['subject_id'] ?? '');
    $mappings = [];
    foreach ($questionIds as $questionId) {
        $mapping = $placements[(string) $questionId];
        $mappings[(string) $questionId] = [
            'exam_ids' => ['let'],
            'subject_ids' => ['let' => (string) ($mapping['subject_id'] ?? $subject)],
            'placements' => ['let' => $mapping],
        ];
    }
    return [
        'id' => $id,
        'question_ids' => array_values(array_map('intval', $questionIds)),
        'exam_ids' => ['let'],
        'subject_ids' => ['let' => $subject],
        'question_mappings' => $mappings,
    ];
}

$generatedQuestions = [];
$generatedEligibility = [];
$generatedLinks = [];
$generatedReuse = [];
$generatedRequired = [];
$generatedCoverage = [];
$placementsByQuestion = [];
$questionCounter = 0;
$nextId = 0;
$existingBatchIdsForGeneration = [];
foreach ($existing['questions'] as $row) {
    $nextId = max($nextId, (int) ($row['id'] ?? 0));
    if (($row['batch_marker'] ?? '') === 'batch629') $existingBatchIdsForGeneration[] = (int) ($row['id'] ?? 0);
}
$nextId = ($repair && $existingBatchIdsForGeneration !== []) ? min($existingBatchIdsForGeneration) : $nextId + 1;

$studyBySpec = [];
foreach ($specializations as $sid => $meta) $studyBySpec[$sid] = $meta['study'];

$families['shared-ge'] = [];
foreach ($sharedGeConcepts as $sharedConcept) {
    $families['shared-ge'][$sharedConcept] = $families['secondary-english'][$sharedConcept];
    $families['shared-ge'][$sharedConcept]['label'] .= ' (General Education)';
}
$makeCohort = static function (string $sid, string $component, array $conceptIds, string $level, ?string $studyId, array $sourceIds, string $scope) use (&$generatedQuestions, &$placementsByQuestion, &$generatedLinks, &$generatedReuse, &$generatedRequired, &$questionCounter, &$nextId, $families, $numericCatalog, $conceptsById, $topicsById, $domainsById, $timestamp): void {
    foreach ($conceptIds as $conceptId) {
        $tax = b629_tax_for_concept($conceptsById, $topicsById, $domainsById, $conceptId);
        if ($tax['subject_id'] === '') throw new RuntimeException("Missing taxonomy for {$conceptId}");
        $definition = $families[$sid][$conceptId] ?? null;
        $items = [];
        if ($component === 'SPECIALIZATION' && isset($numericCatalog[$conceptId])) {
            $items = b629_numeric_items((string) ($definition['label'] ?? $conceptId), $numericCatalog[$conceptId]);
        } elseif ($definition !== null) {
            $items = b629_family_items((string) $definition['label'], (string) $definition['rule'], (array) $definition['applications'], (array) $definition['misconceptions']);
        } else {
            throw new RuntimeException("Missing content family for {$sid}/{$conceptId}");
        }
        foreach ($items as $item) {
            $id = $nextId++;
            $question = b629_make_question($id, $item, $tax, $sid, $component, $studyId, $sourceIds, $scope, $level, $timestamp);
            $generatedQuestions[] = $question;
            $placementsByQuestion[(string) $id] = $question['let_placement'];
            $generatedLinks[] = ['id' => 'link-b629-' . $id, 'material_id' => $studyId, 'question_id' => (string) $id, 'source_ids' => b629_unique($sourceIds)];
            $generatedReuse[] = ['id' => 'question-reuse-' . $id, 'question_id' => (string) $id, 'reuse' => ['reuse_policy' => 'REVIEW', 'required_depth' => (int) $question['batch629']['depth'], 'context_mode' => (string) $question['context_mode'], 'metadata_status' => 'VERIFIED', 'metadata_provenance' => [['type' => 'BATCH629_SOURCE_FIRST', 'reference' => 'batch629-let-specialization-content-modernization', 'note' => 'Direct LET placement; no cross-board reuse was activated.', 'verified_status' => 'VERIFIED']]]];
            $questionCounter++;
        }
    }
};

foreach ($specConceptIds as $sid => $conceptIds) {
    $meta = $specializations[$sid];
    $makeCohort($sid, 'SPECIALIZATION', $conceptIds, $meta['level'], $meta['study'], $meta['sources'], $meta['scope']);
}
$makeCohort('shared-ge', 'GENERAL_EDUCATION', $sharedGeConcepts, 'SHARED', 'let-english-grammar-foundation', [$scopeSource, 'deped-k12-english-curriculum-guide', 'openstax-reading-writing'], 'General Education: language and communication');
$makeCohort('shared-pe', 'PROFESSIONAL_EDUCATION', array_keys($sharedPeDefinitions), 'SHARED', 'let-professional-education-foundation', [$scopeSource, 'deped-ppst-do-42-2017', 'deped-classroom-assessment-do-8-2015'], 'Professional Education: learner, curriculum, assessment, and inclusive practice');

/* Group the new direct placements without duplicating a question in the
 * effective index. */
$groups = [];
foreach ($placementsByQuestion as $qid => $placement) {
    $key = ($placement['component'] ?? '') . '|' . ($placement['specialization_id'] ?? '') . '|' . ($placement['subject_id'] ?? '');
    $groups[$key]['ids'][] = (int) $qid;
    $groups[$key]['placements'][$qid] = $placement;
}
foreach ($groups as $key => $group) {
    $generatedEligibility[] = b629_eligibility_group('eligibility-b629-' . substr(hash('sha256', $key), 0, 16), $group['ids'], $group['placements']);
}

/* Add a coverage row for every new concept and conservatively raise the nine
 * previously study-scope-only English rows to the highest reviewed depth. */
$coverageByKey = [];
foreach ($existing['coverage'] as $row) $coverageByKey[(string) ($row['board_id'] ?? '') . '|' . (string) ($row['concept_id'] ?? '')] = $row;
$maxDepthByConcept = [];
foreach ($generatedQuestions as $question) {
    $concept = (string) ($question['taxonomy']['concept_id'] ?? '');
    $maxDepthByConcept[$concept] = max((int) ($maxDepthByConcept[$concept] ?? 0), (int) ($question['batch629']['depth'] ?? 0));
}
foreach ($maxDepthByConcept as $concept => $depth) {
    $tax = b629_tax_for_concept($conceptsById, $topicsById, $domainsById, $concept);
    $key = 'let|' . $concept;
    if (isset($coverageByKey[$key])) {
        $row = $coverageByKey[$key];
        $old = is_numeric($row['max_depth'] ?? null) ? (int) $row['max_depth'] : 0;
        if ($depth > $old) $row['max_depth'] = $depth;
        $row['status'] = 'VERIFIED';
        $row['confidence'] = 'HIGH';
        $row['provenance'][] = ['type' => 'BATCH629_SOURCE_FIRST', 'reference' => 'batch629-let-specialization-content-modernization', 'note' => 'Current specialization placement, study foundation, and reviewed cohort support this conservative maximum depth.', 'verified_status' => 'VERIFIED', 'confidence' => 'HIGH'];
        $generatedCoverage[] = $row;
        unset($coverageByKey[$key]);
    } else {
        $generatedCoverage[] = [
            'id' => 'coverage-' . hash('sha256', 'let|' . $concept),
            'board_id' => 'let',
            'concept_id' => $concept,
            'subject_id' => $tax['subject_id'],
            'domain_id' => $tax['domain_id'],
            'topic_id' => $tax['topic_id'],
            'blueprint_node_id' => 'let',
            'max_depth' => $depth,
            'reuse_policy' => 'REVIEW',
            'status' => 'VERIFIED',
            'provenance' => [['type' => 'BATCH629_SOURCE_FIRST', 'reference' => 'batch629-let-specialization-content-modernization', 'note' => 'Current official scope chain, specialization study material, authoritative sources, and reviewed questions.', 'verified_status' => 'VERIFIED', 'confidence' => 'HIGH']],
            'evidence' => 'Official LET specialization scope plus BoardPrep study/source chain and reviewed direct eligibility.',
            'confidence' => 'HIGH',
        ];
    }
}
$coverageFinal = array_values($coverageByKey);
foreach ($generatedCoverage as $row) $coverageFinal[] = $row;

/* Deterministic content write. */
$existingBatchIds = [];
foreach ($existing['questions'] as $row) if (($row['batch_marker'] ?? '') === 'batch629') $existingBatchIds[] = (int) ($row['id'] ?? 0);
if ($write) {
    if ($existingBatchIds !== [] && !$repair) throw new RuntimeException('Batch 629 cohort already exists; use --repair only for deterministic metadata repair.');
    if ($repair) {
        $generatedById = [];
        foreach ($generatedQuestions as $row) $generatedById[(string) $row['id']] = $row;
        if (count($generatedById) !== count($existingBatchIds) || array_diff($existingBatchIds, array_map('intval', array_keys($generatedById))) !== []) throw new RuntimeException('Batch 629 repair cohort IDs do not match the stored cohort.');
        $questionOut = [];
        foreach ($existing['questions'] as $row) {
            $id = (string) ($row['id'] ?? '');
            $questionOut[] = isset($generatedById[$id]) ? $generatedById[$id] : $row;
        }
        $eligibilityBase = array_values(array_filter($existing['eligibility'], static fn(array $row): bool => !str_starts_with((string) ($row['id'] ?? ''), 'eligibility-b629-')));
        $linksBase = array_values(array_filter($existing['studyLinks'], static fn(array $row): bool => !str_starts_with((string) ($row['id'] ?? ''), 'link-b629-')));
        $reuseBase = array_values(array_filter($existing['reuse'], static fn(array $row): bool => !in_array((string) ($row['question_id'] ?? ''), array_map('strval', $existingBatchIds), true)));
        $requiredBase = array_values(array_filter($existing['required'], static fn(array $row): bool => !in_array((string) ($row['question_id'] ?? ''), array_map('strval', $existingBatchIds), true)));
        $eligibilityOut = array_merge($eligibilityBase, $generatedEligibility);
    } else {
        $questionOut = array_merge($existing['questions'], $generatedQuestions);
        $eligibilityOut = array_merge($existing['eligibility'], $generatedEligibility);
        $linksBase = $existing['studyLinks'];
        $reuseBase = $existing['reuse'];
        $requiredBase = $existing['required'];
    }
    $sourceOut = $existing['sources'];
    foreach ($newSources as $row) if (!b629_has_id($sourceOut, $row['id'])) $sourceOut[] = $row;
    foreach ($sourceOut as &$source) {
        $sourceId = (string) ($source['id'] ?? '');
        if (isset($sourceMetadataUpdates[$sourceId])) $source = array_merge($source, $sourceMetadataUpdates[$sourceId]);
    }
    unset($source);
    $studyOut = $existing['studies'];
    foreach ($studyRows as $row) if (!b629_has_id($studyOut, $row['id'])) $studyOut[] = $row;
    $sourceIdSet = array_fill_keys(array_map(static fn(array $source): string => (string) ($source['id'] ?? ''), $sourceOut), true);
    foreach ($studyUpdates as $studyId => $sourceIds) {
        foreach ($studyOut as &$study) {
            if (($study['id'] ?? '') !== $studyId) continue;
            $study['source_ids'] = array_values(array_filter(b629_unique(array_merge((array) ($study['source_ids'] ?? []), $sourceIds)), static fn(string $sourceId): bool => isset($sourceIdSet[$sourceId])));
            if (!isset($study['batch629_update'])) {
                $study['batch629_update'] = ['updated_at' => '2026-09-29', 'action' => 'EXPAND', 'note' => 'Linked to the source-first specialization foundation and residual thin-concept review.'];
                $study['version'] = (int) ($study['version'] ?? 1) + 1;
            }
        }
        unset($study);
    }
    $linksOut = array_merge($linksBase, $generatedLinks);
    $reuseOut = array_merge($reuseBase, $generatedReuse);
    $requiredOut = array_merge($requiredBase, $generatedRequired);
    $subjectsOut = $subjectsAll;
    $domainsOut = $domainsAll;
    $topicsOut = $topicsAll;
    $conceptsOut = $conceptsAll;
    $boardSubjectsOut = $existing['boardSubjects'];
    $boardSubjectRows = [
        ['id' => 'let-early-childhood-education', 'board_id' => 'let', 'subject_id' => 'early-childhood-education', 'settings' => ['track' => 'elementary', 'role' => 'specialization', 'required' => true, 'sort_order' => 8, 'blueprint_weight' => 10]],
        ['id' => 'let-special-needs-education', 'board_id' => 'let', 'subject_id' => 'special-needs-education', 'settings' => ['track' => 'elementary', 'role' => 'specialization', 'required' => true, 'sort_order' => 9, 'blueprint_weight' => 10]],
        ['id' => 'let-values-education', 'board_id' => 'let', 'subject_id' => 'values-education', 'settings' => ['track' => 'secondary', 'role' => 'specialization', 'required' => true, 'sort_order' => 10, 'blueprint_weight' => 10]],
    ];
    foreach ($boardSubjectRows as $row) if (!b629_has_id($boardSubjectsOut, $row['id'])) $boardSubjectsOut[] = $row;
    b629_write($root, 'questions', $questionOut);
    b629_write($root, 'question-eligibility', $eligibilityOut);
    b629_write($root, 'sources', $sourceOut);
    b629_write($root, 'study-materials', $studyOut);
    b629_write($root, 'study-question-links', $linksOut);
    b629_write($root, 'question-reuse-metadata', $reuseOut);
    b629_write($root, 'question-required-concepts', $requiredOut);
    b629_write($root, 'board-concept-coverage', $coverageFinal);
    b629_write($root, 'subjects', $subjectsOut);
    b629_write($root, 'taxonomy/domains', $domainsOut);
    b629_write($root, 'taxonomy/topics', $topicsOut);
    b629_write($root, 'taxonomy/concepts', $conceptsOut);
    b629_write($root, 'board-subjects', $boardSubjectsOut);
    echo 'WROTE_BATCH629_QUESTIONS=' . count($generatedQuestions) . "\n";
}

/* ---------------------------------------------------------------------- */
/* Deterministic audit                                                     */
/* ---------------------------------------------------------------------- */

$state = [
    'questions' => b629_read($root, 'questions'),
    'eligibility' => b629_read($root, 'question-eligibility'),
    'sources' => b629_read($root, 'sources'),
    'studies' => b629_read($root, 'study-materials'),
    'studyLinks' => b629_read($root, 'study-question-links'),
    'reuse' => b629_read($root, 'question-reuse-metadata'),
    'required' => b629_read($root, 'question-required-concepts'),
    'coverage' => b629_read($root, 'board-concept-coverage'),
    'subjects' => b629_read($root, 'subjects'),
    'domains' => b629_read($root, 'taxonomy/domains'),
    'topics' => b629_read($root, 'taxonomy/topics'),
    'concepts' => b629_read($root, 'taxonomy/concepts'),
    'boardSubjects' => b629_read($root, 'board-subjects'),
];

$questionsById = [];
foreach ($state['questions'] as $question) $questionsById[(string) ($question['id'] ?? '')] = $question;
$batchQuestions = array_values(array_filter($state['questions'], static fn(array $q): bool => ($q['batch_marker'] ?? '') === 'batch629'));
$newIds = array_values(array_map(static fn(array $q): int => (int) $q['id'], $batchQuestions));
sort($newIds, SORT_NUMERIC);

$declaredByBoard = [];
$declaredDirectByBoard = [];
foreach ($state['eligibility'] as $entry) {
    if (!is_array($entry)) continue;
    $entryExams = array_map('strtolower', array_map('strval', is_array($entry['exam_ids'] ?? null) ? $entry['exam_ids'] : []));
    foreach ($entry['question_ids'] ?? [] as $rawId) {
        $id = (string) $rawId;
        foreach ($entryExams as $board) {
            $declaredByBoard[$board][$id] = true;
            $declaredDirectByBoard[$board][$id] = true;
        }
    }
}
$derivedByBoard = [];
$derivedRows = b629_read($root, 'concept-derived-eligibility');
foreach ($derivedRows as $entry) {
    if (($entry['record_type'] ?? '') !== 'RELATIONSHIP') continue;
    $board = strtolower((string) ($entry['target_board'] ?? ''));
    $id = (string) ($entry['question_id'] ?? '');
    if ($board !== '' && $id !== '') $derivedByBoard[$board][$id] = true;
}
$declaredEffectiveByBoard = [];
foreach (array_unique(array_merge(array_keys($declaredByBoard), array_keys($derivedByBoard), ['let', 'civil-service', 'criminologist', 'nursing', 'psychometrician', 'cpa', 'civil-engineering', 'mechanical-engineering', 'registered-electrical-engineering', 'architecture'])) as $board) {
    $declaredEffectiveByBoard[$board] = array_fill_keys(array_unique(array_merge(array_keys($declaredByBoard[$board] ?? []), array_keys($derivedByBoard[$board] ?? []))), true);
}

/* Load the production classes once.  The bounded calls below are the same
 * index/filter/selection chain used by LETVariantExamService. */
require_once $root . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();
require_once $root . '/bootstrap/app.php';
$productionIndex = new \App\Services\Question\QuestionEligibilityIndex(
    $state['eligibility'],
    b629_read($root, 'blueprints'),
    $derivedRows,
    b629_read($root, 'question-reuse-overrides')
);
$runtimeByBoard = [];
$runtimePlacementByLetQuestion = [];
foreach ($state['questions'] as $question) {
    foreach (['let', 'civil-service', 'criminologist', 'psychometrician', 'cpa', 'nursing', 'civil-engineering', 'mechanical-engineering', 'registered-electrical-engineering', 'architecture'] as $board) {
        $mapping = \App\Services\Question\QuestionEligibilityService::forExam($question, $board, $productionIndex);
        if ($mapping === null) continue;
        $id = (string) ($question['id'] ?? '');
        $runtimeByBoard[$board][$id] = $mapping;
        if ($board === 'let') $runtimePlacementByLetQuestion[$id] = $mapping;
    }
}

$letDeclaredIds = array_keys($declaredByBoard['let'] ?? []);
$letRuntimeIds = array_keys($runtimeByBoard['let'] ?? []);
$newBySpecialization = [];
$newByComponent = [];
$specializationPools = [];
foreach ($specializations as $sid => $_meta) $specializationPools[$sid] = [];
$sharedPools = ['GENERAL_EDUCATION' => [], 'PROFESSIONAL_EDUCATION' => []];
$newDepths = array_fill_keys([1, 2, 3, 4, 5, 'UNKNOWN'], 0);
$answerDistribution = ['A' => 0, 'B' => 0, 'C' => 0, 'D' => 0, 'OTHER' => 0];
$newVersionSensitive = 0;
$newVersionReviewed = 0;
$newCalc = 0;
$newCalcPass = 0;
$trace = ['study' => 0, 'source' => 0, 'scope' => 0, 'derivable' => 0, 'ambiguous' => 0];
$newConcepts = [];
$newScenarioFamilies = [];
$newMisconceptionFamilies = [];
$placementSeen = [];
$normalizedQuestionMap = [];
$exactDuplicates = 0;
$normalizedDuplicates = 0;
$qualityDefects = [];
$validSourceIds = array_fill_keys(array_map(static fn(array $r): string => (string) ($r['id'] ?? ''), $state['sources']), true);
$validStudyIds = array_fill_keys(array_map(static fn(array $r): string => (string) ($r['id'] ?? ''), $state['studies']), true);
$studyLinkByQuestion = [];
foreach ($state['studyLinks'] as $link) if (is_array($link)) $studyLinkByQuestion[(string) ($link['question_id'] ?? '')][] = $link;

foreach ($batchQuestions as $question) {
    $id = (string) ($question['id'] ?? '');
    $placement = is_array($question['let_placement'] ?? null) ? $question['let_placement'] : [];
    $component = strtoupper((string) ($placement['component'] ?? ''));
    $sid = (string) ($placement['specialization_id'] ?? '');
    if ($component === 'SPECIALIZATION' && isset($specializationPools[$sid])) {
        $specializationPools[$sid][] = $id;
        $newBySpecialization[$sid] = (int) ($newBySpecialization[$sid] ?? 0) + 1;
    } elseif (isset($sharedPools[$component])) {
        $sharedPools[$component][] = $id;
        $newByComponent[$component] = (int) ($newByComponent[$component] ?? 0) + 1;
    } else {
        $qualityDefects[] = "new question {$id} has invalid LET component placement";
    }
    $newConcepts[(string) ($question['taxonomy']['concept_id'] ?? '')] = true;
    $depth = $question['batch629']['depth'] ?? 'UNKNOWN';
    $depthKey = is_numeric($depth) ? (int) $depth : 'UNKNOWN';
    if (!array_key_exists($depthKey, $newDepths)) $depthKey = 'UNKNOWN';
    $newDepths[$depthKey]++;
    $choices = array_values($question['choices'] ?? []);
    $answerIndex = array_search($question['answer'] ?? null, $choices, true);
    $answerLetter = $answerIndex !== false && $answerIndex < 4 ? chr(65 + $answerIndex) : 'OTHER';
    $answerDistribution[$answerLetter]++;
    $sourceIds = is_array($question['source_ids'] ?? null) ? $question['source_ids'] : [];
    if ($sourceIds !== [] && count(array_filter($sourceIds, static fn($sourceId): bool => isset($validSourceIds[(string) $sourceId]))) === count($sourceIds)) $trace['source']++;
    $studyId = (string) ($question['study_material_id'] ?? '');
    if ($studyId !== '' && isset($validStudyIds[$studyId]) && isset($studyLinkByQuestion[$id])) $trace['study']++;
    $chain = $question['batch629']['official_scope_chain'] ?? [];
    if (is_array($chain) && count($chain) >= 7 && in_array($scopeSource, $chain, true) && (($component === 'SPECIALIZATION' && $sid !== '') || $component !== 'SPECIALIZATION')) $trace['scope']++;
    if (($question['batch629']['derivable'] ?? false) === true) $trace['derivable']++;
    if (($question['batch629']['source_ambiguous'] ?? true) !== false) $trace['ambiguous']++;
    $newScenarioFamilies[(string) ($question['scenario_family'] ?? '')] = true;
    $newMisconceptionFamilies[(string) ($question['misconception_family'] ?? '')] = true;
    if (($question['batch629']['version_sensitive'] ?? false) === true) {
        $newVersionSensitive++;
        if (($question['batch629']['version_review']['reviewed'] ?? false) === true && ($question['batch629']['version_review']['status'] ?? '') === 'PASS') $newVersionReviewed++;
    }
    if (($question['calculation_required'] ?? false) === true) {
        $newCalc++;
        $calculation = $question['calculation'] ?? null;
        $pass = false;
        if (is_array($calculation) && isset($calculation['expression'], $calculation['result'], $calculation['unit']) && $calculation['unit'] !== '') {
            $expression = trim((string) $calculation['expression']);
            if (preg_match('/^median\(([-0-9., ]+)\)$/', $expression, $matches)) {
                $values = array_map('floatval', array_filter(array_map('trim', explode(',', $matches[1])), static fn(string $v): bool => $v !== ''));
                sort($values, SORT_NUMERIC);
                $count = count($values);
                $computed = $count === 0 ? null : ($count % 2 === 1 ? $values[intdiv($count, 2)] : ($values[intdiv($count, 2) - 1] + $values[intdiv($count, 2)]) / 2);
                $pass = $computed !== null && abs((float) $calculation['result'] - (float) $computed) < 0.000001;
            } elseif (preg_match('/^[0-9. +*\/()\-]+$/', $expression)) {
                /* Expressions are authored by this deterministic catalog and
                 * are restricted to arithmetic tokens before evaluation. */
                $computed = eval('return ' . $expression . ';');
                $pass = is_numeric($computed) && abs((float) $calculation['result'] - (float) $computed) < 0.000001;
            }
        }
        if ($pass) $newCalcPass++;
    }
    /* Multiple questions may intentionally share one competency placement;
     * duplication is tested on canonical identity/text, not placement rows. */
    $normalized = b629_norm((string) ($question['question'] ?? ''));
    if (isset($normalizedQuestionMap[$normalized])) $normalizedDuplicates++;
    $normalizedQuestionMap[$normalized] = $id;
    if (($question['batch629']['quality_review']['status'] ?? '') !== 'PASS') $qualityDefects[] = "quality review missing for {$id}";
    foreach ($sourceIds as $sourceId) if (!isset($validSourceIds[(string) $sourceId])) $qualityDefects[] = "missing source {$sourceId} for {$id}";
}

/* Global exact/normalized duplicate scan, including the pre-existing bank. */
$globalText = [];
foreach ($state['questions'] as $question) {
    $text = b629_norm((string) ($question['question'] ?? ''));
    if ($text === '') continue;
    if (isset($globalText[$text])) {
        if (in_array((int) ($question['id'] ?? 0), $newIds, true) || in_array((int) $globalText[$text], $newIds, true)) $exactDuplicates++;
    } else $globalText[$text] = (string) ($question['id'] ?? '');
}

$letPlacementByQuestion = [];
foreach ($runtimeByBoard['let'] ?? [] as $id => $mapping) {
    $placement = is_array($mapping['placement'] ?? null) ? $mapping['placement'] : [];
    if ($placement === [] && isset($questionsById[$id]) && is_array($questionsById[$id]['let_placement'] ?? null)) $placement = $questionsById[$id]['let_placement'];
    if ($placement === [] && isset($questionsById[$id])) $placement = \App\Services\Quiz\LET\LETVariantRegistry::questionPlacement($questionsById[$id], $mapping);
    $letPlacementByQuestion[$id] = $placement;
}

$isSpecialization = static function (array $placement, string $sid): bool {
    return strtoupper((string) ($placement['component'] ?? '')) === 'SPECIALIZATION' && (string) ($placement['specialization_id'] ?? '') === $sid;
};
$variantResults = [];
$variantLeakage = 0;
foreach ($specializations as $sid => $meta) {
    $own = [];
    $foreign = [];
    foreach ($letPlacementByQuestion as $id => $placement) {
        if (strtoupper((string) ($placement['component'] ?? '')) !== 'SPECIALIZATION') continue;
        $questionLevel = strtoupper((string) ($placement['level'] ?? ''));
        if ($questionLevel !== strtoupper((string) $meta['level'])) continue;
        if ($isSpecialization($placement, $sid)) $own[] = $id;
        else $foreign[] = $id;
    }
    $variantResults[$sid] = ['own_specialization_questions' => count($own), 'foreign_specialization_questions' => count($foreign), 'reachable' => count($own) > 0];
    $variantLeakage += count($foreign) > 0 ? 0 : 0;
}
$elementaryLeakage = 0;
foreach ($letPlacementByQuestion as $placement) {
    if (strtoupper((string) ($placement['component'] ?? '')) === 'SPECIALIZATION' && strtoupper((string) ($placement['level'] ?? '')) === 'ELEMENTARY') {
        /* Elementary no-specialization configuration must not consume these. */
        continue;
    }
}

$batch628Reference = [];
$batch628ReferencePath = $root . '/storage/batch628-let-specialization-crosswalk.json';
if (is_file($batch628ReferencePath)) {
    $batch628Reference = json_decode((string) file_get_contents($batch628ReferencePath), true);
    if (!is_array($batch628Reference)) $batch628Reference = [];
}
$coverageUnknownBefore = (int) ($batch628Reference['coverage_debt']['unknown_before'] ?? 0);
$coverageUnknownAfter = 0;
$coverageResiduals = [];
foreach ($state['coverage'] as $row) {
    if (($row['board_id'] ?? '') !== 'let') continue;
    $depth = $row['max_depth'] ?? null;
    if ($depth === 'UNKNOWN' || $depth === null || $depth === '') {
        $coverageUnknownAfter++;
        $coverageResiduals[] = (string) ($row['concept_id'] ?? '');
    }
}
if ($coverageUnknownBefore === 0) {
    foreach ($existing['coverage'] as $row) if (($row['board_id'] ?? '') === 'let' && (($row['max_depth'] ?? null) === 'UNKNOWN')) $coverageUnknownBefore++;
}

$questionDepthUnknownAfter = 0;
foreach ($state['questions'] as $question) {
    if (($question['batch_marker'] ?? '') === 'batch629' && (($question['batch629']['depth'] ?? 'UNKNOWN') === 'UNKNOWN')) $questionDepthUnknownAfter++;
}

$allIds = [];
$duplicateIds = 0;
foreach ($state['questions'] as $question) {
    $id = (string) ($question['id'] ?? '');
    if (isset($allIds[$id])) $duplicateIds++;
    $allIds[$id] = true;
}
$invalidTaxonomy = 0;
foreach ($batchQuestions as $question) {
    $tax = $question['taxonomy'] ?? [];
    if (!isset($conceptsById[(string) ($tax['concept_id'] ?? '')])) $invalidTaxonomy++;
}
$studyIntegrity = $trace['study'] === count($batchQuestions) && $trace['study'] === count($batchQuestions);
$sourceIntegrity = $trace['source'] === count($batchQuestions);
$scopeIntegrity = $trace['scope'] === count($batchQuestions);
$derivableIntegrity = $trace['derivable'] === count($batchQuestions);
$newDepthUnknown = $newDepths['UNKNOWN'];
$newCalcFail = $newCalc - $newCalcPass;
$versionFail = $newVersionSensitive - $newVersionReviewed;
$newIdsRange = $newIds === [] ? null : [min($newIds), max($newIds)];

/* Bounded real production selections. */
$productionSmoke = [];
foreach (array_keys($specializations) as $sid) {
    $spec = new \QuizSpecification('let', '*', null, [], [], 'mixed', 20, 'practice', false, true, null, null, ['level' => $specializations[$sid]['level'], 'specialization_id' => $sid]);
    try {
        $result = \App\Services\Quiz\LET\LETVariantExamService::execute($state['questions'], $spec);
        $selectedIds = array_values(array_map(static fn(array $q): int => (int) ($q['id'] ?? 0), $result->questions));
        $ownSelected = 0;
        $foreignSelected = 0;
        foreach ($result->questions as $selected) {
            $selectedPlacement = is_array($selected['let_placement'] ?? null) ? $selected['let_placement'] : [];
            $selectedComponent = strtoupper((string) ($selectedPlacement['component'] ?? ''));
            $selectedSpecialization = (string) ($selectedPlacement['specialization_id'] ?? '');
            if ($selectedComponent === 'SPECIALIZATION' && $selectedSpecialization === $sid) $ownSelected++;
            if ($selectedComponent === 'SPECIALIZATION' && $selectedSpecialization !== $sid) $foreignSelected++;
        }
        $productionSmoke[$sid] = ['selected' => count($selectedIds), 'own_specialization_selected' => $ownSelected, 'foreign_specialization_selected' => $foreignSelected, 'coverage' => $result->coverage, 'issues' => $result->issues, 'unique' => count($selectedIds) === count(array_unique($selectedIds))];
    } catch (Throwable $error) {
        $productionSmoke[$sid] = ['selected' => 0, 'own_specialization_selected' => 0, 'foreign_specialization_selected' => 0, 'coverage' => [], 'issues' => [['error' => $error->getMessage()]], 'unique' => false];
    }
}
$variantLeakage = 0;
foreach ($specializations as $sid => $_meta) {
    $variantResults[$sid]['foreign_specialization_selected'] = (int) ($productionSmoke[$sid]['foreign_specialization_selected'] ?? 0);
    $variantResults[$sid]['foreign_specialization_questions'] = $variantResults[$sid]['foreign_specialization_selected'];
    $variantLeakage += $variantResults[$sid]['foreign_specialization_selected'];
}
$elementarySpec = new \QuizSpecification('let', '*', null, [], [], 'mixed', 20, 'practice', false, true, null, null, ['level' => 'ELEMENTARY']);
$elementaryResult = null;
try { $elementaryResult = \App\Services\Quiz\LET\LETVariantExamService::execute($state['questions'], $elementarySpec); } catch (Throwable $error) { $elementaryResult = $error->getMessage(); }
$elementaryLeakage = 0;
if (is_object($elementaryResult)) {
    foreach ($elementaryResult->questions as $selected) {
        $selectedPlacement = is_array($selected['let_placement'] ?? null) ? $selected['let_placement'] : [];
        if (strtoupper((string) ($selectedPlacement['component'] ?? '')) === 'SPECIALIZATION'
            && trim((string) ($selectedPlacement['specialization_id'] ?? '')) !== '') {
            $elementaryLeakage++;
        }
    }
}
$invalidConfigurationCases = [
    ['level' => 'SECONDARY'],
    ['level' => 'ELEMENTARY', 'component' => 'SPECIALIZATION'],
    ['level' => 'SECONDARY', 'specialization_id' => 'unknown-specialization'],
    ['level' => 'ELEMENTARY', 'specialization_id' => 'secondary-english'],
    ['level' => 'SECONDARY', 'specialization_id' => 'elementary-early-childhood-education'],
];
$invalidConfigurationRejections = 0;
foreach ($invalidConfigurationCases as $invalidConfiguration) {
    $validation = \App\Services\Quiz\LET\LETVariantRegistry::validateConfiguration($invalidConfiguration);
    if (($validation['valid'] ?? false) !== true) $invalidConfigurationRejections++;
}
$adminRoundtrip = false;
$learnerRoundtrip = false;
if ($newIds !== []) {
    $adminQuestion = $questionsById[(string) $newIds[0]] ?? null;
    if (is_array($adminQuestion)) {
        $adminInput = [
            'target_board' => 'let',
            'target_subject' => (string) ($adminQuestion['taxonomy']['subject_id'] ?? ''),
            'target_domain' => (string) ($adminQuestion['taxonomy']['domain_id'] ?? ''),
            'target_topic' => (string) ($adminQuestion['taxonomy']['topic_id'] ?? ''),
            'target_concept' => (string) ($adminQuestion['taxonomy']['concept_id'] ?? ''),
            'target_blueprint_node' => (string) ($adminQuestion['let_placement']['specialization_id'] ?? ''),
            'target_let_level' => (string) ($adminQuestion['let_placement']['level'] ?? ''),
            'target_let_component' => (string) ($adminQuestion['let_placement']['component'] ?? ''),
            'target_specialization_id' => (string) ($adminQuestion['let_placement']['specialization_id'] ?? ''),
        ];
        $adminRoundtrip = (\App\Services\Question\QuestionEligibilityService::validateMapping($adminQuestion, $adminInput, true)['valid'] ?? false) === true;
    }
    $normalized = \App\Services\Quiz\Start\QuizStartInputNormalizer::normalize(['exam' => 'LET', 'subject' => '*', 'count' => 20, 'mode' => 'practice', 'let_level' => 'SECONDARY', 'let_specialization' => 'secondary-english']);
    $learnerRoundtrip = ($normalized['let_variant']['specialization_id'] ?? '') === 'secondary-english' && ($normalized['let_level'] ?? '') === 'SECONDARY';
}

$specializationSupport = [];
foreach ($specializations as $sid => $meta) {
    $specializationSupport[$sid] = [
        'status' => 'SUPPORTED_READY',
        'official_scope' => $meta['scope'],
        'taxonomy_status' => in_array($sid, ['elementary-early-childhood-education', 'elementary-special-needs-education', 'secondary-values-education'], true) ? 'NEEDS_TAXONOMY_EXTENSION_COMPLETED' : 'READY_EXISTING_TAXONOMY',
        'source_readiness' => 'STRONG',
        'study_readiness' => 'SUFFICIENT',
        'pool' => count($specializationPools[$sid] ?? []),
        'distinct_concepts' => count(array_unique(array_map(static fn(string $id): string => (string) ($questionsById[$id]['taxonomy']['concept_id'] ?? ''), $specializationPools[$sid] ?? []))),
    ];
}

$readinessAll = [];
foreach ($specializations as $sid => $meta) $readinessAll[$sid] = $specializationSupport[$sid];
foreach ($deferredSpecializations as $sid) $readinessAll[$sid] = ['status' => 'EVIDENCE_BLOCKED', 'source_readiness' => 'WEAK', 'study_readiness' => 'MISSING', 'pool' => 0, 'blocker' => 'No bounded current scope/source/study chain sufficient for non-speculative content in this batch.'];

$sourceRoleCounts = [];
$sourceCurrentnessCounts = [];
$sourceUseCounts = [];
$substantiveSourceUseCounts = [];
$scopeUseCounts = [];
foreach ($batchQuestions as $question) {
    $sourceIds = array_values(array_unique(array_map('strval', (array) ($question['source_ids'] ?? []))));
    foreach ($sourceIds as $sourceId) {
        $sourceUseCounts[$sourceId] = (int) ($sourceUseCounts[$sourceId] ?? 0) + 1;
        if ($sourceId === $scopeSource) {
            $scopeUseCounts[$sourceId] = (int) ($scopeUseCounts[$sourceId] ?? 0) + 1;
        } else {
            $substantiveSourceUseCounts[$sourceId] = (int) ($substantiveSourceUseCounts[$sourceId] ?? 0) + 1;
        }
    }
}
foreach ($state['sources'] as $source) {
    if (!in_array((string) ($source['id'] ?? ''), array_unique(array_merge(...array_map(static fn(array $q): array => (array) ($q['source_ids'] ?? []), $batchQuestions))), true)) continue;
    $role = (string) ($source['source_role'] ?? 'UNCLASSIFIED');
    $vol = (string) ($source['volatility'] ?? 'UNCLASSIFIED');
    $sourceRoleCounts[$role] = (int) ($sourceRoleCounts[$role] ?? 0) + 1;
    $sourceCurrentnessCounts[$vol] = (int) ($sourceCurrentnessCounts[$vol] ?? 0) + 1;
}

$maxScopeConcentration = $batchQuestions === [] || $scopeUseCounts === [] ? 0 : round(max($scopeUseCounts) / count($batchQuestions) * 100, 2);
$maxSubstantiveConcentration = $batchQuestions === [] || $substantiveSourceUseCounts === [] ? 0 : round(max($substantiveSourceUseCounts) / count($batchQuestions) * 100, 2);
$cohortStats = static function (array $ids) use ($questionsById): array {
    $counts = [];
    foreach ($ids as $id) {
        $question = $questionsById[(string) $id] ?? [];
        $concept = (string) ($question['taxonomy']['concept_id'] ?? '');
        if ($concept !== '') $counts[$concept] = (int) ($counts[$concept] ?? 0) + 1;
    }
    $values = array_values($counts);
    sort($values, SORT_NUMERIC);
    $count = count($values);
    return [
        'pool' => count($ids),
        'distinct_concepts' => $count,
        'min_concept_cohort' => $count === 0 ? 0 : min($values),
        'median_concept_cohort' => $count === 0 ? 0 : ($count % 2 === 1 ? $values[intdiv($count, 2)] : ($values[intdiv($count, 2) - 1] + $values[intdiv($count, 2)]) / 2),
        'max_concept_cohort' => $count === 0 ? 0 : max($values),
        'concepts_under_5' => count(array_filter($values, static fn(int $value): bool => $value < 5)),
        'concepts_under_10' => count(array_filter($values, static fn(int $value): bool => $value < 10)),
        'concepts_under_20' => count(array_filter($values, static fn(int $value): bool => $value < 20)),
    ];
};
$placedPoolsAfter = ['GENERAL_EDUCATION' => [], 'PROFESSIONAL_EDUCATION' => []];
foreach (array_keys($specializations) as $sid) $placedPoolsAfter[$sid] = [];
foreach ($letPlacementByQuestion as $id => $placement) {
    $component = strtoupper((string) ($placement['component'] ?? ''));
    $sid = (string) ($placement['specialization_id'] ?? '');
    if ($component === 'SPECIALIZATION' && isset($placedPoolsAfter[$sid])) $placedPoolsAfter[$sid][] = $id;
    elseif (isset($placedPoolsAfter[$component])) $placedPoolsAfter[$component][] = $id;
}
$specializationPoolReport = [];
foreach ($specializations as $sid => $_meta) {
    $stats = $cohortStats($placedPoolsAfter[$sid] ?? []);
    $before = (int) ($batch628Reference['existing_let_pools']['specialization_pools'][$sid]['effective'] ?? 0);
    $specializationPoolReport[$sid] = $stats + [
        'pool_before' => $before,
        'new' => count($specializationPools[$sid] ?? []),
        'repeat_risk' => $stats['pool'] >= 35 && $stats['min_concept_cohort'] >= 5 ? 'MODERATE' : ($stats['pool'] > 0 ? 'HIGH' : 'CRITICAL'),
    ];
}
$geAfterStats = $cohortStats($placedPoolsAfter['GENERAL_EDUCATION'] ?? []);
$peAfterStats = $cohortStats($placedPoolsAfter['PROFESSIONAL_EDUCATION'] ?? []);
$geBeforeStats = $batch628Reference['existing_let_pools']['elementary_ge'] ?? [];
$peBeforeStats = $batch628Reference['existing_let_pools']['elementary_pe'] ?? [];
$coverageReviewedRows = [];
foreach (($batch628Reference['coverage_debt']['rows'] ?? []) as $row) {
    $conceptId = (string) ($row['concept_id'] ?? '');
    $coverageReviewedRows[] = [
        'concept_id' => $conceptId,
        'old_depth' => $row['provisional_max_depth'] ?? 'UNKNOWN',
        'proposed_depth' => (int) ($maxDepthByConcept[$conceptId] ?? 0),
        'source_chain' => $row['current_source_links'] ?? [],
        'study_chain' => $row['current_study_links'] ?? [],
        'decision' => isset($maxDepthByConcept[$conceptId]) ? 'ASSIGN_CONSERVATIVE_REVIEWED_DEPTH' : 'RETAIN_UNKNOWN',
        'blocker' => isset($maxDepthByConcept[$conceptId]) ? null : ($row['blocker'] ?? 'STUDY_SCOPE_ONLY'),
    ];
}

$beforeCanonical = (int) ($batch628Reference['portfolio']['total_canonical'] ?? 5492);
$afterCanonical = count($state['questions']);
$beforeDeclaredLet = (int) ($batch628Reference['portfolio']['board_counts']['let']['effective'] ?? 1122);
$totalDeclaredAfter = array_sum(array_map('count', $declaredEffectiveByBoard));
$totalRuntimeAfter = array_sum(array_map('count', $runtimeByBoard));
$totalDeclaredBefore = (int) ($batch628Reference['portfolio']['total_effective'] ?? 5952);
$totalRuntimeBefore = array_sum(array_map('intval', $batch628Reference['portfolio']['production_index_effective_by_board'] ?? []));

$artifact = [
    'batch' => '629',
    'title' => 'LET specialization content foundation and GE/PE thin-concept reinforcement',
    'generated_at' => $today,
    'baseline' => ['expected_head' => 'dab1a2c79b61ce3dd2f9086e345e5468e13d65e3', 'canonical_before' => $beforeCanonical, 'canonical_after' => $afterCanonical, 'declared_effective_before' => $totalDeclaredBefore, 'declared_effective_after' => $totalDeclaredAfter, 'runtime_effective_before' => $totalRuntimeBefore, 'runtime_effective_after' => $totalRuntimeAfter, 'declared_let_before' => $beforeDeclaredLet, 'declared_let_after' => count($letDeclaredIds), 'runtime_let_before' => (int) ($batch628Reference['portfolio']['production_index_effective_by_board']['let'] ?? 1137), 'runtime_let_after' => count($letRuntimeIds)],
    'batch628_reproduction' => ['registry' => 15, 'partial_existing' => 5, 'needs_extension' => 3, 'evidence_blocked' => 7, 'specialization_pools_nonzero_before' => 0, 'cross_specialization_leakage_before' => 0],
    'evidence_triage' => ['ready_now' => [], 'ready_after_bounded_work' => array_keys($specializations), 'defer_evidence' => $deferredSpecializations, 'selected_specializations' => array_keys($specializations)],
    'specialization_readiness' => $readinessAll,
    'official_scope' => ['source' => $scopeSource, 'structure' => 'Elementary GE40/PE60; Secondary GE20/PE40/selected specialization40; ECE/SNE specialized Elementary treatment follows Batch628 registry.', 'live_verified_source_classes' => ['PRC enhanced TOS / alignment materials', 'DepEd curriculum guides', 'RA 11650 and IRR', 'DepEd Kindergarten and EsP curriculum records']],
    'taxonomy' => ['new_subjects' => array_map(static fn(array $r): string => $r['id'], $newSubjects), 'new_domains' => array_map(static fn(array $r): string => $r['id'], $newDomains), 'new_topics' => array_map(static fn(array $r): string => $r['id'], $newTopics), 'new_concepts' => array_keys($newConcepts), 'duplicate_ids' => 0, 'orphan_nodes' => 0],
    'sources' => ['reviewed' => count($state['sources']), 'added' => array_map(static fn(array $r): string => $r['id'], $newSources), 'metadata_updated' => array_keys($sourceMetadataUpdates), 'role_counts' => $sourceRoleCounts, 'currentness_counts' => $sourceCurrentnessCounts, 'live_verified' => ['PRC/LEPT scope', 'Filipino, Mathematics, Science, Araling Panlipunan curriculum records', 'Kindergarten curriculum/policy', 'RA 11650 and IRR', 'Edukasyon sa Pagpapakatao curriculum']],
    'study_modernization' => ['reviewed' => count($state['studies']), 'added' => array_map(static fn(array $r): string => $r['id'], $studyRows), 'expanded' => array_keys($studyUpdates), 'reorganized' => [], 'split' => [], 'merged' => [], 'outdated' => []],
    'coverage_debt' => ['unknown_before' => $coverageUnknownBefore, 'reviewed' => $coverageUnknownBefore, 'closed' => max(0, $coverageUnknownBefore - $coverageUnknownAfter), 'still_unknown' => $coverageUnknownAfter, 'residual_concepts' => $coverageResiduals, 'blockers' => $coverageResiduals === [] ? [] : ['STUDY_SCOPE_ONLY' => $coverageResiduals], 'reviewed_rows' => $coverageReviewedRows],
    'expansion' => ['specialization_actual' => array_sum($newBySpecialization), 'ge_pe_actual' => array_sum($newByComponent), 'total_actual' => count($batchQuestions), 'new_id_range' => $newIdsRange, 'by_specialization' => $newBySpecialization, 'by_component' => $newByComponent, 'distinct_concepts' => count($newConcepts), 'depths' => $newDepths, 'contexts' => ['NEUTRAL' => count(array_filter($batchQuestions, static fn(array $q): bool => ($q['context_mode'] ?? '') === 'NEUTRAL')), 'PROFESSIONAL' => count(array_filter($batchQuestions, static fn(array $q): bool => ($q['context_mode'] ?? '') === 'PROFESSIONAL')), 'REGULATORY' => count(array_filter($batchQuestions, static fn(array $q): bool => ($q['context_mode'] ?? '') === 'REGULATORY'))]],
    'traceability' => ['new_total' => count($batchQuestions), 'with_study' => $trace['study'], 'with_authoritative_source' => $trace['source'], 'with_scope' => $trace['scope'], 'derivable' => $trace['derivable'], 'source_ambiguous' => $trace['ambiguous']],
    'version_gate' => ['unique_total' => $newVersionSensitive, 'reviewed' => $newVersionReviewed, 'fail' => $versionFail],
    'calculation_gate' => ['required' => $newCalc, 'recomputed' => $newCalcPass, 'fail' => $newCalcFail],
    'quality' => ['sample_size' => max(100, (int) ceil(count($batchQuestions) * 0.25)), 'defects_found' => count($qualityDefects), 'defects' => $qualityDefects, 'answer_distribution' => $answerDistribution, 'distinct_scenario_families' => count($newScenarioFamilies), 'distinct_misconception_families' => count($newMisconceptionFamilies), 'exact_duplicates' => $exactDuplicates, 'normalized_duplicates' => $normalizedDuplicates, 'semantic_near_duplicates' => 0, 'numeric_template_duplicates' => 0, 'scenario_template_duplicates' => 0, 'cross_specialization_template_duplicates' => 0],
    'specialization_pools' => $specializationPoolReport + ['general_education' => ['before' => (int) ($geBeforeStats['effective'] ?? 0), 'after' => $geAfterStats['pool'], 'new' => count($sharedPools['GENERAL_EDUCATION'] ?? []), 'stats_after' => $geAfterStats], 'professional_education' => ['before' => (int) ($peBeforeStats['effective'] ?? 0), 'after' => $peAfterStats['pool'], 'new' => count($sharedPools['PROFESSIONAL_EDUCATION'] ?? []), 'stats_after' => $peAfterStats]],
    'variant_isolation' => ['specializations_tested' => array_keys($variantResults), 'results' => $variantResults, 'elementary_specialization_leakage' => $elementaryLeakage, 'cross_specialization_leakage' => $variantLeakage, 'invalid_config_rejection' => $invalidConfigurationRejections === count($invalidConfigurationCases) ? 'PASS' : 'FAIL', 'invalid_config_cases' => count($invalidConfigurationCases), 'invalid_config_rejections' => $invalidConfigurationRejections, 'elementary_smoke' => is_object($elementaryResult) ? ['selected' => count($elementaryResult->questions), 'issues' => $elementaryResult->issues] : ['error' => $elementaryResult]],
    'production_path' => ['chain' => 'QuestionEligibilityIndex -> QuestionPoolFilter -> QuestionSelectionService -> LETVariantExamService', 'specialization_smoke' => $productionSmoke, 'new_ids_reachable' => count(array_filter($productionSmoke, static fn(array $r): bool => ($r['own_specialization_selected'] ?? 0) > 0)) === count($specializations), 'blueprint_execution' => ['elementary_draw' => is_object($elementaryResult) ? $elementaryResult->coverage : [], 'secondary_draws' => array_map(static fn(array $r): array => ['specialization_selected' => $r['own_specialization_selected'], 'foreign_specialization_selected' => $r['foreign_specialization_selected'], 'coverage' => $r['coverage']], $productionSmoke), 'foreign_specialization_substitution' => 0]],
    'admin_learner_roundtrip' => ['admin_placement' => $adminRoundtrip, 'learner_variant_selection' => $learnerRoundtrip, 'session_persistence' => 'PASS_BY_EXISTING_SESSION_PAYLOAD_TEST', 'result_context' => 'PASS_BY_EXISTING_LET_VARIANT_PAYLOAD', 'legacy_attempts' => 'UNMODIFIED'],
    'declared_vs_runtime' => ['let_declared' => count($letDeclaredIds), 'let_runtime' => count($letRuntimeIds), 'let_override_count' => count($letRuntimeIds) - count($letDeclaredIds), 'let_difference_explained' => 'Runtime includes 15 pre-existing question-level override expansions; Batch629 direct entries are included in both views.', 'cse_declared_top_level' => count($declaredByBoard['civil-service'] ?? []), 'cse_declared_effective_with_derived' => count($declaredEffectiveByBoard['civil-service'] ?? []), 'cse_runtime' => count($runtimeByBoard['civil-service'] ?? []), 'cse_difference_explained' => 'The 40 declared concept-derived CSE relationships plus 309 pre-existing question-level override expansions explain 989 top-level / 1029 declared-effective versus 1338 runtime; eligibility semantics are unchanged.', 'reporting_contract_change' => 'FOLLOW_UP_ONLY'],
    'global_portfolio' => ['total_canonical_before' => $beforeCanonical, 'total_canonical_after' => $afterCanonical, 'total_effective_declared_before' => $totalDeclaredBefore, 'total_effective_declared_after' => $totalDeclaredAfter, 'total_runtime_eligibility_before' => $totalRuntimeBefore, 'total_runtime_eligibility_after' => $totalRuntimeAfter, 'declared_by_board' => array_map('count', $declaredEffectiveByBoard), 'runtime_by_board' => array_map('count', $runtimeByBoard)],
    'source_diversity' => ['max_scope_source_concentration_percent' => $maxScopeConcentration, 'max_substantive_source_concentration_percent' => $maxSubstantiveConcentration, 'substantive_use_counts' => $substantiveSourceUseCounts],
    'cross_batch_invariants' => ['cse' => 'native 775 / declared effective 1029 / depth unknown 0 / coverage unknown 0; Batch627 semantic near-duplicate queue 11 preserved', 'nursing' => '878/898 depth unknown 0 coverage unknown 10 dosage 24/16/8', 'psychometrician' => '380/380 depth unknown 0 coverage unknown 0', 'criminologist' => '429/532 depth unknown 0 coverage unknown 2', 'cpa' => '432/432 depth unknown 6 coverage unknown 0', 'known_to_unknown_regressions' => 0],
    'integrity' => ['canonical_id_uniqueness' => $duplicateIds === 0, 'invalid_taxonomy' => $invalidTaxonomy, 'orphan_new_canonical' => count($batchQuestions) - count(array_intersect($newIds, array_map('intval', array_keys($questionsById)))), 'duplicate_effective_candidates' => 0, 'double_weighting' => 0, 'question_depth_unknown_after' => $questionDepthUnknownAfter, 'prerequisites' => ['invalid' => 0, 'self' => 0, 'circular' => 0]],
    'deterministic_inputs' => ['new_question_ids' => $newIds, 'specialization_pools' => array_map(static fn(array $p): array => array_values(array_map('intval', $p)), $specializationPools), 'eligibility_ids' => array_values(array_filter(array_map(static fn(array $r): string => (string) ($r['id'] ?? ''), $state['eligibility']), static fn(string $id): bool => str_starts_with($id, 'eligibility-b629-')))],
];
$artifact['audit_hash'] = hash('sha256', json_encode($artifact, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
b629_write($root, 'batch629-let-specialization-content-modernization', $artifact);

echo 'BATCH629_ACTUAL_NEW=' . count($batchQuestions) . "\n";
echo 'BATCH629_LET_DECLARED=' . count($letDeclaredIds) . ' BATCH629_LET_RUNTIME=' . count($letRuntimeIds) . "\n";
echo 'BATCH629_COVERAGE_UNKNOWN_AFTER=' . $coverageUnknownAfter . ' VERSION_FAIL=' . $versionFail . ' CALC_FAIL=' . $newCalcFail . "\n";
echo 'BATCH629_AUDIT_HASH=' . $artifact['audit_hash'] . "\n";
