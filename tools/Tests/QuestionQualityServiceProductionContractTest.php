<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$service = (string) file_get_contents($root . '/app/Services/Question/QuestionQualityService.php');
$cache = (string) file_get_contents($root . '/app/Services/Question/Quality/QuestionQualityReportCacheService.php');
$presenter = (string) file_get_contents($root . '/app/Services/Question/Quality/QuestionQualityReportPresenter.php');

foreach ([
    'QuestionQualityReportCacheService::currentReport()' => $service,
    'QuestionQualityReportPresenter::present(' => $service,
    'RepositoryHealthEngine::analyze()' => $cache,
    'RepositoryHealthReportHydrator::hydrate(' => $cache,
    'QuestionQualityIssueGrouper::group(' => $presenter,
] as $contract => $source) {
    if (!str_contains($source, $contract)) {
        throw new RuntimeException('missing production delegation: ' . $contract);
    }
}

if (str_contains($service, 'switch (') || substr_count($service, 'case ') > 0) {
    throw new RuntimeException('legacy inline issue switch remains');
}

echo "[PASS] QuestionQualityService production delegation, provenance-validated caching, and report presentation verified.\n";
