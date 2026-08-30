<?php
declare(strict_types=1);

final class ShortageRecoveryService
{
    public static function recover(
        SelectionResult $result,
        array $pool
    ): array {
        if ($result->fulfilled) {
            return $result->questions;
        }

        $required = $result->request->questionCount;

        $best = $result->questions;
        foreach (RecoveryScopePlan::forRequest($result->request) as $scope) {
            $candidates = RecoveryCandidateService::candidates(
                $pool,
                $result->request,
                $scope
            );

            if (count($candidates) >= $required) {
                return array_slice($candidates, 0, $required);
            }

            if (count($candidates) > count($best)) {
                $best = $candidates;
            }
        }

        return array_slice($best, 0, $required);
    }
}
