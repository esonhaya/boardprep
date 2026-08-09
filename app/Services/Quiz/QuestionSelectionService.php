<?php

declare(strict_types=1);

final class QuestionSelectionService
{
    public static function fulfillRequest(
        array $questions,
        SelectionRequest $request
    ): SelectionResult {

        $pool =
            array_values(
                array_filter(
                    $questions,
                    static function (
                        array $question
                    ) use (
                        $request
                    ): bool {

                        $taxonomy =
                            is_array(
                                $question['taxonomy'] ?? null
                            )
                                ? $question['taxonomy']
                                : [];

                        $subject =
                            $question['subject']
                            ?? $taxonomy['subject_id']
                            ?? null;

                        $domain =
                            $question['domain']
                            ?? $taxonomy['domain_id']
                            ?? null;

                        /*
                         * The current question bank uses "active".
                         * Older/generated records may use "approved".
                         * Both are valid selectable states.
                         */
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

                        return
                            (string) $subject
                            ===
                            (string) $request->subject

                            &&

                            (string) $domain
                            ===
                            (string) $request->domain;
                    }
                )
            );

        $selected =
            SelectionDeduplicator::unique(
                WeightedShuffleService::shuffle(
                    DifficultySelectionService::select(
                        $pool,
                        $request->difficultyDistribution,
                        $request->questionCount
                    )
                )
            );

        return new SelectionResult(
            questions:
                $selected,

            fulfilled:
                BlueprintQuotaValidator::validate(
                    $selected,
                    $request
                ),

            request:
                $request
        );
    }

    public static function select(
        array $questions,
        QuizSpecification $specification
    ): array {

        $selected =
            array_values(
                array_filter(
                    $questions,
                    static function (
                        array $question
                    ) use (
                        $specification
                    ): bool {

                        $taxonomy =
                            is_array(
                                $question['taxonomy'] ?? null
                            )
                                ? $question['taxonomy']
                                : [];

                        $subject =
                            $question['subject']
                            ?? $taxonomy['subject_id']
                            ?? null;

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

                        return
                            (string) $subject
                            ===
                            (string) $specification->subject;
                    }
                )
            );

        return array_slice(
            $selected,
            0,
            max(
                0,
                $specification->questionCount
            )
        );
    }
}
