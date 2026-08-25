<?php
declare(strict_types=1);
final class SelectionFulfillmentFactory
{
    public static function create(array $selected, SelectionRequest $request): SelectionResult
    {
        return new SelectionResult(questions:$selected, fulfilled:BlueprintQuotaValidator::validate($selected,$request), request:$request);
    }
}
