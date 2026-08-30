<?php
declare(strict_types=1);

final class RecoveryScopePlan
{
    /**
     * @return list<RecoveryScope>
     */
    public static function forRequest(SelectionRequest $request): array
    {
        $scopes = [];

        if ($request->concept !== null) {
            $scopes[] = RecoveryScope::Concept;
        }

        if ($request->topic !== null) {
            $scopes[] = RecoveryScope::Topic;
        }

        if ($request->domain !== null) {
            $scopes[] = RecoveryScope::Domain;
        }

        $scopes[] = RecoveryScope::Subject;

        return $scopes;
    }
}
