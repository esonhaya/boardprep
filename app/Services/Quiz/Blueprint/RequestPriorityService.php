<?php

declare(strict_types=1);

final class RequestPriorityService
{
    /**
     * @param SelectionRequest[] $requests
     * @return SelectionRequest[]
     */
    public static function sort(
        array $requests
    ): array {

        usort(
            $requests,
            static function (
                SelectionRequest $a,
                SelectionRequest $b
            ): int {

                return self::priority($b)
                    <=>
                    self::priority($a);

            }
        );

        return $requests;
    }

    private static function priority(
        SelectionRequest $request
    ): int {

        if ($request->domain !== null) {
            return 2;
        }

        return 1;
    }
}
