<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$service = file_get_contents($root . '/app/Services/Question/QuestionQueryService.php');
$search = file_get_contents($root . '/app/Services/Question/QuestionSearchService.php');
if ($service === false || $search === false) {
    throw new RuntimeException('Unable to read question query production files.');
}

$questionsMethod = substr(
    $service,
    strpos($service, 'public static function getQuestions('),
    strpos($service, 'public static function getQuestionPage(') - strpos($service, 'public static function getQuestions(')
);
$pageMethod = substr(
    $service,
    strpos($service, 'public static function getQuestionPage('),
    strpos($service, 'private static function applyFilters(') - strpos($service, 'public static function getQuestionPage(')
);
if (substr_count($questionsMethod, '->all()') !== 1 || substr_count($pageMethod, '->all()') !== 1) {
    throw new RuntimeException('Each query entry point must use exactly one repository snapshot read.');
}
foreach (['QuestionQueryFilters', 'QuestionQueryPipeline'] as $name) {
    if (!str_contains($service, $name)) {
        throw new RuntimeException('QuestionQueryService missing ' . $name);
    }
}
if (!str_contains($search, 'QuestionQueryPipeline')) {
    throw new RuntimeException('QuestionSearchService does not share query pipeline.');
}

echo "[PASS] Question query entry points retain one repository snapshot each and delegate through the shared query pipeline.\n";
