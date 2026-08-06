<?php

declare(strict_types=1);

final class BlueprintAllocationReconciler
{
    public static function reconcile(
        array $requests,
        int $target
    ): array {

        $allocated = 0;

        foreach ($requests as $request) {
            $allocated += $request->questionCount;
        }

        $difference = $target - $allocated;

        if ($difference === 0) {
            return $requests;
        }

        $count = count($requests);

        if ($count === 0) {
            return $requests;
        }

        $index = 0;

        while ($difference !== 0) {

            $request = $requests[$index];

            $questions =
                $request->questionCount;

            if ($difference > 0) {

                $questions++;
                $difference--;

            } elseif ($questions > 1) {

                $questions--;
                $difference++;

            }

            $requests[$index] =
                new SelectionRequest(

                    domain:
                        $request->domain,

                    topic:
                        $request->topic,

                    concept:
                        $request->concept,

                    difficultyDistribution:
                        $request->difficultyDistribution,

                    questionCount:
                        $questions

                );

            $index++;

            if ($index >= $count) {
                $index = 0;
            }

        }

        return $requests;

    }
}
