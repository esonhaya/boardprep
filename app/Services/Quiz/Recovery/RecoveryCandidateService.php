<?php

declare(strict_types=1);

final class RecoveryCandidateService
{
    public static function candidates(
        array $questions,
        SelectionRequest $request,
        RecoveryScope $scope
    ): array {

        return array_values(

            array_filter(

                $questions,

                static function (
                    array $question
                ) use (
                    $request,
                    $scope
                ): bool {

                    return match ($scope) {

                        RecoveryScope::Concept =>

                            ($question["concept"] ?? null)
                            ===
                            $request->concept,

                        RecoveryScope::Topic =>

                            ($question["topic"] ?? null)
                            ===
                            $request->topic,

                        RecoveryScope::Domain =>

                            ($question["domain"] ?? null)
                            ===
                            $request->domain,

                        RecoveryScope::Subject =>

                            true,

                    };

                }

            )

        );

    }
}
