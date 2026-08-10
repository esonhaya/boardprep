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

                    $taxonomy =
                        is_array(
                            $question['taxonomy'] ?? null
                        )
                            ? $question['taxonomy']
                            : [];

                    $status =
                        strtolower(
                            (string) (
                                $question['status']
                                ?? 'active'
                            )
                        );

                    if (
                        !in_array(
                            $status,
                            [
                                'active',
                                'approved',
                            ],
                            true
                        )
                    ) {
                        return false;
                    }

                    $subject =
                        $question['subject']
                        ?? $taxonomy['subject_id']
                        ?? null;

                    $domain =
                        $question['domain']
                        ?? $taxonomy['domain_id']
                        ?? null;

                    /*
                     * SelectionRequest currently carries only
                     * subject/domain constraints.
                     *
                     * Concept/topic recovery will be supported
                     * once those dimensions become part of the
                     * request contract. Do not access nonexistent
                     * request properties here.
                     */
                    return match ($scope) {

                        RecoveryScope::Concept,
                        RecoveryScope::Topic =>
                            false,

                        RecoveryScope::Domain =>
                            (string) $subject
                            ===
                            (string) $request->subject
                            &&
                            (
                                $request->domain === null
                                ||
                                (string) $domain
                                ===
                                (string) $request->domain
                            ),

                        RecoveryScope::Subject =>
                            (string) $subject
                            ===
                            (string) $request->subject,

                    };

                }
            )
        );
    }
}
