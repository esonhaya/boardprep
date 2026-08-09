<?php

declare(strict_types=1);

final class RequestExecutionPlanService
{
    /**
     * @param SelectionRequest[] $requests
     * @return SelectionRequest[]
     */
    public static function build(
        array $requests
    ): array {

        return RequestPriorityService::sort(
            array_values(
                array_filter(
                    $requests,
                    static fn(mixed $request): bool =>
                        $request instanceof SelectionRequest
                )
            )
        );

    }
}
