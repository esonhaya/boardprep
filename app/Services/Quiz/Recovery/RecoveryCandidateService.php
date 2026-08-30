<?php
declare(strict_types=1);

final class RecoveryCandidateService
{
    public static function candidates(
        array $questions,
        SelectionRequest $request,
        RecoveryScope $scope
    ): array {
        return RecoveryCandidateFilter::filter($questions, $request, $scope);
    }
}
