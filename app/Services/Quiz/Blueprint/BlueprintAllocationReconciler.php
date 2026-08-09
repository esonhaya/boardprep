<?php

declare(strict_types=1);

final class BlueprintAllocationReconciler
{
    public static function reconcile(
        array $requests,
        int $target
    ): array {

        if ($target < 0) {
            throw new InvalidArgumentException(
                'Allocation target cannot be negative.'
            );
        }

        $allocated = 0;

        foreach ($requests as $request) {
            $allocated += $request->questionCount;
        }

        $difference = $target - $allocated;

        if ($difference === 0 || empty($requests)) {
            return $requests;
        }

        $count = count($requests);
        $index = 0;

        while ($difference > 0) {

            $request = $requests[$index];

            $requests[$index] =
                new SelectionRequest(
                    subject:
                        $request->subject,

                    domain:
                        $request->domain,

                    difficultyDistribution:
                        $request->difficultyDistribution,

                    questionCount:
                        $request->questionCount + 1
                );

            $difference--;
            $index++;

            if ($index >= $count) {
                $index = 0;
            }
        }

        while ($difference < 0) {

            $index = 0;
            $changed = false;

            while ($index < $count && $difference < 0) {

                $request = $requests[$index];

                if ($request->questionCount > 0) {

                    $requests[$index] =
                        new SelectionRequest(
                            subject:
                                $request->subject,

                            domain:
                                $request->domain,

                            difficultyDistribution:
                                $request->difficultyDistribution,

                            questionCount:
                                $request->questionCount - 1
                        );

                    $difference++;
                    $changed = true;
                }

                $index++;
            }

            if (!$changed) {
                throw new InvalidArgumentException(
                    'Allocation target cannot be reconciled with the available requests.'
                );
            }
        }

        return $requests;
    }
}
