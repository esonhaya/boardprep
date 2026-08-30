<?php
declare(strict_types=1);

final class RecoveryScopeMatcher
{
    public static function matches(
        RecoveryQuestionContext $question,
        SelectionRequest $request,
        RecoveryScope $scope
    ): bool {
        if (!self::same($question->subject, $request->subject)) {
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
        return $expected === null || self::same($actual, $expected);
    }

    private static function matchesRequired(?string $actual, ?string $expected): bool
    {
        return $expected !== null && self::same($actual, $expected);
    }

    private static function same(?string $actual, ?string $expected): bool
    {
        return $actual !== null
            && $expected !== null
            && strcasecmp(trim($actual), trim($expected)) === 0;
    }
}
