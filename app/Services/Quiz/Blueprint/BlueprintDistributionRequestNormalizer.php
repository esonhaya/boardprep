<?php

declare(strict_types=1);

final class BlueprintDistributionRequestNormalizer
{
    /**
     * @param array<int,array<string,mixed>> $requests
     * @return array<int,array<string,mixed>>
     */
    public static function normalize(array $requests): array
    {
        return array_values(array_filter(
            array_map(
                static function (array $request): array {
                    $request["questionCount"] = max(
                        0,
                        (int) ($request["questionCount"] ?? $request["count"] ?? 0)
                    );
                    $request["subject"] = trim((string) ($request["subject"] ?? ""));
                    $request["domain"] = isset($request["domain"])
                        ? trim((string) $request["domain"])
                        : null;
                    return $request;
                },
                $requests
            ),
            static fn(array $request): bool =>
                $request["questionCount"] > 0 && $request["subject"] !== ""
        ));
    }
}
