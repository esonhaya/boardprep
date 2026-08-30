<?php

declare(strict_types=1);

namespace App\Services\Quiz\Result\Attempt;

final class AttemptRecordFactory
{
    public static function create(
        array $sessionContext,
        array $questionSet,
        array $scoreSummary
    ): array {
        return array_merge(
            [
                "id" => "attempt-" . bin2hex(random_bytes(8)),
            ],
            $sessionContext,
            $questionSet,
            $scoreSummary,
            [
                "completed" => true,
                "completed_at" => date("c"),
            ]
        );
    }
}
