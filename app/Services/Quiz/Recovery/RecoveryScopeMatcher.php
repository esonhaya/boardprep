<?php
declare(strict_types=1);

final class RecoveryScopeMatcher
{
    public static function matches(
        RecoveryQuestionContext $question,
        SelectionRequest $request,
        RecoveryScope $scope
    ): bool {
        if ((string)$question->subject !== (string)$request->subject) {
            return false;
        }

        return match ($scope) {
            RecoveryScope::Concept =>
                self::matchesOptional($question->domain, $request->domain)
                && self::matchesOptional($question->topic, $request->topic)
                && self::matchesRequired($question->concept, $request->concept),

            RecoveryScope::Topic =>
                self::matchesOptional($question->domain, $request->domain)
                && self::matchesRequired($question->topic, $request->topic),

            RecoveryScope::Domain =>
                self::matchesOptional($question->domain, $request->domain),

            RecoveryScope::Subject => true,
        };
    }

    private static function matchesOptional(?string $actual, ?string $expected): bool
    {
        return $expected === null || (string)$actual === (string)$expected;
    }

    private static function matchesRequired(?string $actual, ?string $expected): bool
    {
        return $expected !== null && (string)$actual === (string)$expected;
    }
}
