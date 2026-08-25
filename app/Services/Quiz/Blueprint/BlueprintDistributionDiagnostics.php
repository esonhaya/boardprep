<?php

declare(strict_types=1);

final class BlueprintDistributionDiagnostics
{
    /**
     * @param array<int,array<string,mixed>> $requests
     * @return array{request_count:int,total_questions:int,subjects:array<int,string>}
     */
    public static function summarize(array $requests): array
    {
        $subjects = [];

        foreach ($requests as $request) {
            $subject = trim((string) ($request["subject"] ?? ""));
            if ($subject !== "") {
                $subjects[$subject] = true;
            }
        }

        return [
            "request_count" => count($requests),
            "total_questions" => array_sum(array_map(
                static fn(array $request): int =>
                    (int) ($request["questionCount"] ?? $request["count"] ?? 0),
                $requests
            )),
            "subjects" => array_keys($subjects),
        ];
    }
}
