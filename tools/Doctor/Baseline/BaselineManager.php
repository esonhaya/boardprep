<?php

declare(strict_types=1);

namespace Tools\Doctor\Baseline;

use Tools\Doctor\DTO\DoctorResult;
use Tools\Doctor\Snapshot\ProjectSnapshot;

final class BaselineManager
{
    public function __construct(
        private readonly BaselineRepository $repository =
            new BaselineRepository()
    ) {
    }

    public function capture(
        DoctorResult $result,
        ProjectSnapshot $snapshot
    ): void {

        $this->repository->save([

            "createdAt" => date(DATE_ATOM),

            "health" =>
                $result->health(),

            "pass" =>
                $result->passCount(),

            "warning" =>
                $result->warningCount(),

            "fail" =>
                $result->failCount(),

            "phpFiles" =>
                $snapshot->phpFileCount(),

            "classes" =>
                count(
                    $snapshot->classes
                ),

            "largestController" =>
                $snapshot->largestFile(
                    "/Controllers/"
                ),

            "largestService" =>
                $snapshot->largestFile(
                    "/Services/"
                ),

        ]);

    }

    public function baseline(): array
    {
        return $this->repository->load();
    }

    public function exists(): bool
    {
        return $this->repository->exists();
    }
}
