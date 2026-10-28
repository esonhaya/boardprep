<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

$iterations = max(3, min(25, (int) ($argv[1] ?? 9)));
$profiles = [];
foreach ([150, 1200, 5000] as $poolSize) {
    $pool = [];
    for ($index = 0; $index < $poolSize; $index++) {
        $topic = 'topic-' . ($index % 40);
        $difficulty = ['easy', 'medium', 'hard'][$index % 3];
        $pool[] = [
            'id' => 'synthetic-' . $index,
            'difficulty' => $difficulty,
            'subject' => 'English',
            'topic' => $topic,
            'taxonomy' => ['subject_id' => 'english', 'topic_id' => $topic],
        ];
    }
    $weakTopics = [];
    for ($index = 0; $index < 40; $index++) {
        $weakTopics[] = [
            'board' => 'LET',
            'subject' => 'English',
            'topic' => 'topic-' . $index,
            'priority' => 40 - $index,
        ];
    }
    $exposure = [];
    for ($index = 0; $index < min($poolSize, 900); $index += 2) {
        $exposure['synthetic-' . $index] = [
            'count' => 1 + ($index % 4),
            'last_attempt_index' => $index % 5,
        ];
    }
    $context = ['weak_topics' => $weakTopics, 'question_exposure' => $exposure];
    $request = new SelectionRequest(
        '*', null, ['easy' => 10, 'medium' => 10, 'hard' => 10], 30,
        exam: 'LET', adaptiveContext: $context
    );
    $selected = array_slice($pool, 0, 30);
    $timings = [];
    $output = [];
    for ($iteration = 0; $iteration < $iterations + 1; $iteration++) {
        $start = hrtime(true);
        $output = \AdaptiveQuestionSelectionService::apply($selected, $pool, $request, $context);
        $elapsed = (hrtime(true) - $start) / 1_000_000;
        if ($iteration > 0) {
            $timings[] = $elapsed;
        }
    }
    sort($timings);
    $profiles[(string) $poolSize] = [
        'pool_size' => $poolSize,
        'warm_ms_median' => $timings[intdiv(count($timings), 2)],
        'warm_ms_min' => $timings[0],
        'warm_ms_max' => $timings[count($timings) - 1],
        'selected_count' => count($output),
        'selected_ids' => array_column($output, 'id'),
    ];
}

echo json_encode([
    'iterations' => $iterations,
    'profiles' => $profiles,
    'peak_memory_bytes' => memory_get_peak_usage(true),
    'storage_operations' => 0,
    'fixture' => 'Synthetic 150/1,200/5,000 candidate pools, 40 bounded LET weak-topic rows, 30 difficulty slots, and up to 450 exposure rows; no application storage access.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
