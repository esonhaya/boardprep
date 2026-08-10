<?php

declare(strict_types=1);

final class ShortageRecoveryService
{
    public static function recover(
        SelectionResult $result,
        array $pool
    ): array {

        if (
            $result->fulfilled
        ) {
            return $result->questions;
        }

        $required =
            $result->request->questionCount;

        /*
         * Recovery intentionally widens the search scope.
         *
         * Current SelectionRequest supports:
         *   1. subject
         *   2. domain
         *
         * Concept/topic recovery will be introduced when those
         * dimensions become part of SelectionRequest.
         */

        foreach (
            [
                RecoveryScope::Domain,
                RecoveryScope::Subject,
            ]
            as $scope
        ) {

            $candidates =
                RecoveryCandidateService::candidates(
                    $pool,
                    $result->request,
                    $scope
                );

            if (
                count($candidates)
                >=
                $required
            ) {

                return array_slice(
                    $candidates,
                    0,
                    $required
                );
            }
        }

        /*
         * Nothing could satisfy the request.
         *
         * Preserve the original selection rather than inventing
         * questions or returning unrelated subject matter.
         */
        return $result->questions;
    }
}
