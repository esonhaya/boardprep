<?php

declare(strict_types=1);

namespace Tools\Doctor\Engine;

use Tools\Doctor\Context\DoctorContext;
use Tools\Doctor\Context\DoctorSelfContext;
use Tools\Doctor\DTO\DoctorResult;
use Tools\Doctor\Metrics\DoctorMetricsPipeline;
use Tools\Doctor\Metrics\MetricRegistry;
use Tools\Doctor\Metrics\MetricsPipeline;
use Tools\Doctor\Output\JsonReportWriter;
use Tools\Doctor\Registry\CheckRegistry;
use Tools\Doctor\Snapshot\DoctorSnapshotBuilder;
use Tools\Doctor\Snapshot\ProjectSnapshotBuilder;
use App\Core\Environment;
use App\Services\RepositoryHealth\Engine\RepositoryHealthEvidenceService;

final class DoctorRunner
{
    public function run(): DoctorResult
    {
        $projectRoot =
            dirname(__DIR__, 3);

        chdir($projectRoot);
        Environment::load($projectRoot . '/.env');

        MetricRegistry::reset();

        $projectSnapshot =
            (new ProjectSnapshotBuilder())
                ->build();

        (new MetricsPipeline())
            ->analyze(
                $projectSnapshot
            );

        DoctorContext::setSnapshot(
            $projectSnapshot
        );

        $doctorSnapshot =
            (new DoctorSnapshotBuilder())
                ->build();

        (new DoctorMetricsPipeline())
            ->analyze(
                $doctorSnapshot
            );

        DoctorSelfContext::setSnapshot(
            $doctorSnapshot
        );

        $result = new DoctorResult();

        $checks =
            (new CheckRegistry())
                ->fromDirectories([
                    $projectRoot . "/tools/Doctor/Project/Shared/Checks",
                    $projectRoot . "/tools/Doctor/Project/BoardPrep/Checks",
                    $projectRoot . "/tools/Doctor/Self/Checks",
                ]);

        foreach ($checks as $check) {
            $checkResult =
                $check->run();

            MetricRegistry::set(
                strtolower(
                    str_replace(
                        " ",
                        "-",
                        $checkResult->title
                    )
                ),
                $checkResult
            );

            $result->add(
                $checkResult
            );
        }

        $storagePath = trim((string) (Environment::get('APP_STORAGE_PATH', '') ?? ''));
        if ($storagePath === '') {
            $storagePath = $projectRoot . '/storage';
        }
        $reportPath = rtrim($storagePath, DIRECTORY_SEPARATOR) . '/doctor-report.json';
        (new JsonReportWriter())->write($result, $reportPath);

        $reportContents = file_get_contents($reportPath);
        $report = $reportContents === false ? null : json_decode($reportContents, true);
        if (!is_array($report)) {
            throw new \RuntimeException('Doctor report writer did not produce a valid saved report.');
        }
        $report = RepositoryHealthEvidenceService::preparePersistedSnapshot($report, $projectRoot);
        $temporaryPath = $reportPath . '.tmp-' . bin2hex(random_bytes(4));
        if (file_put_contents(
            $temporaryPath,
            json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL,
            LOCK_EX
        ) === false || !rename($temporaryPath, $reportPath)) {
            @unlink($temporaryPath);
            throw new \RuntimeException('Unable to publish the current Doctor report.');
        }

        return $result;
    }
}
