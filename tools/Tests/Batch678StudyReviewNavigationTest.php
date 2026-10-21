<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

$root = dirname(__DIR__, 2);
$storage = sys_get_temp_dir() . '/boardprep-batch678-review-' . bin2hex(random_bytes(6));
if (!mkdir($storage, 0700, true)) {
    throw new RuntimeException('Unable to create the isolated Batch 678 review fixture.');
}

foreach (['sources.json', 'study-materials.json', 'study-question-links.json'] as $file) {
    if (!copy($root . '/storage/' . $file, $storage . '/' . $file)) {
        throw new RuntimeException('Unable to seed the isolated Batch 678 review fixture.');
    }
}

putenv('APP_ENV=production');
putenv('APP_DEBUG=false');
putenv('APP_TIMEZONE=UTC');
putenv('APP_STORAGE_PATH=' . $storage);
putenv('DB_DRIVER=json');
\App\Core\App::boot();

$cases = [
    '1181' => [
        'link_id' => 'study-link-ce-1181',
        'material_id' => 'ce-489--onstruction-anagement-and-uantity',
        'source_id' => 'fhwa-construction-quantity-review-appendix-d',
    ],
    '1365' => [
        'link_id' => 'link-cpa-1365-depth',
        'material_id' => 'cpa-material-17',
        'source_id' => 'bir-efps-form-1701-q',
    ],
    '1629' => [
        'link_id' => 'ree-link-1629',
        'material_id' => 'ree-material-electronics',
        'source_id' => 'openstax-college-physics-magnetic-force-conductor',
    ],
    '6811' => [
        'link_id' => 'study-link-b632-6811',
        'material_id' => 'cse-numerical-foundation',
        'source_id' => 'openstax-prealgebra-distributive-property-7-3',
    ],
];

try {
    $allSources = array_fill_keys(array_map(
        static fn(mixed $source): string => is_array($source) ? (string) ($source['id'] ?? '') : '',
        \App\Core\App::storage()->all('sources')
    ), true);
    $allMaterials = [];
    foreach (\App\Core\App::storage()->all('study-materials') as $material) {
        if (is_array($material)) {
            $allMaterials[(string) ($material['id'] ?? '')] = $material;
        }
    }
    $allLinks = [];
    foreach (\App\Core\App::storage()->all('study-question-links') as $link) {
        if (is_array($link)) {
            $allLinks[(string) ($link['id'] ?? '')] = $link;
        }
    }

    foreach ($cases as $questionId => $case) {
        $questionId = (string) $questionId;
        $link = $allLinks[$case['link_id']] ?? null;
        $material = $allMaterials[$case['material_id']] ?? null;
        if (!is_array($link) || (string) ($link['question_id'] ?? '') !== $questionId) {
            throw new RuntimeException("Question {$questionId} does not have its expected exact study link.");
        }
        if (!is_array($material) || !in_array($case['source_id'], $material['source_ids'] ?? [], true)) {
            throw new RuntimeException("Question {$questionId} material does not include its source record.");
        }
        if (!in_array($case['source_id'], $link['source_ids'] ?? [], true) || !isset($allSources[$case['source_id']])) {
            throw new RuntimeException("Question {$questionId} study link does not resolve its exact source record.");
        }

        $reference = \App\Services\Study\StudyLibraryService::forQuestion($questionId)[0] ?? null;
        if (!is_array($reference)
            || ($reference['material_id'] ?? '') !== $case['material_id']
            || trim((string) ($reference['review_guidance'] ?? '')) === '') {
            throw new RuntimeException("Question {$questionId} has no actionable post-submission review guidance.");
        }
    }

    $references = \App\Services\Study\StudyLibraryService::forQuestions(['6811']);
    $reference = $references['6811'][0] ?? null;
    $sourceUrl = is_array($reference) ? (string) (($reference['source_references'][0]['url'] ?? '')) : '';
    if (!is_array($reference) || $sourceUrl === '' || ($reference['lesson_id'] ?? '') !== '' || ($reference['section_id'] ?? '') !== '') {
        throw new RuntimeException('The source-only question was presented as an exact lesson mapping or lost its registered source.');
    }
    $sourceLink = 'href="' . htmlspecialchars($sourceUrl, ENT_QUOTES, 'UTF-8') . '"';
    ob_start();
    \App\Core\View::render('quiz/result', [
        'summary' => ['score' => 0, 'total' => 1, 'percentage' => 0],
        'review' => [[
            'question' => [
                'id' => 6811,
                'question' => 'A value is 2.5 times 36. What is the product?',
                'answer' => '90',
                'explanation' => '2.5 × 36 = 90.',
            ],
            'userAnswer' => '96',
            'correctAnswer' => '90',
            'correct' => false,
            'answered' => true,
        ]],
        'studyReferencesByQuestion' => $references,
    ]);
    $html = (string) ob_get_clean();
    if (!str_contains($html, $sourceLink)
        || str_contains($html, 'href="/study-library?material=cse-numerical-foundation')
        || !str_contains($html, 'no exact lesson section is mapped to this question')
        || !str_contains($html, 'Use the distributive property')
        || !str_contains($html, 'quiz-review-guidance')) {
        throw new RuntimeException('Submitted answer review did not preserve the question explanation while distinguishing the registered source from an exact lesson mapping.');
    }

    ob_start();
    \App\Core\View::render('quiz/result', [
        'summary' => ['score' => 0, 'total' => 1, 'percentage' => 0],
        'review' => [[
            'question' => [
                'id' => 6811,
                'question' => 'A value is 2.5 times 36. What is the product?',
                'answer' => '90',
                'explanation' => '2.5 × 36 = 90.',
            ],
            'userAnswer' => '96',
            'correctAnswer' => '90',
            'correct' => false,
            'answered' => true,
        ]],
        'studyReferencesByQuestion' => $references,
        'historical' => true,
        'completedAt' => '2026-10-14 10:00:00',
        'historicalContext' => ['board' => 'civil-service', 'mode' => 'practice'],
    ]);
    $historicalHtml = (string) ob_get_clean();
    if (!str_contains($historicalHtml, $sourceLink)
        || str_contains($historicalHtml, 'href="/study-library?material=cse-numerical-foundation')
        || !str_contains($historicalHtml, 'no exact lesson section is mapped to this question')
        || !str_contains($historicalHtml, 'Use the distributive property')
        || !str_contains($historicalHtml, 'Historical Quiz Result')) {
        throw new RuntimeException('Historical answer review did not distinguish source-only evidence from an exact lesson mapping.');
    }

    echo "[PASS] Four source-backed question links resolve to exact materials, and current and historical reviews display actionable guidance.\n";
} finally {
    foreach (glob($storage . '/*') ?: [] as $file) {
        if (is_file($file)) {
            @unlink($file);
        }
    }
    @rmdir($storage);
    putenv('APP_ENV');
    putenv('APP_DEBUG');
    putenv('APP_TIMEZONE');
    putenv('APP_STORAGE_PATH');
    putenv('DB_DRIVER');
}
