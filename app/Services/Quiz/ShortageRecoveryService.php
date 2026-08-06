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

        foreach (

            RecoveryScope::cases()

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
                $result->request->questionCount

            ) {

                return array_slice(

                    $candidates,

                    0,

                    $result->request->questionCount

                );

            }

        }

        return $result->questions;

    }
}
