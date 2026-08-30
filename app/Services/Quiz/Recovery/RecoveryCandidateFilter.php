<?php
declare(strict_types=1);

final class RecoveryCandidateFilter
{
    public static function filter(
        array $questions,
        SelectionRequest $request,
        RecoveryScope $scope
    ): array {
        return array_values(array_filter(
            $questions,
            static function (array $question) use ($request, $scope): bool {
                $context = RecoveryQuestionContextFactory::create($question);

                return RecoveryStatusPolicy::allows($context->status)
                    && RecoveryScopeMatcher::matches($context, $request, $scope);
            }
        ));
    }
}
