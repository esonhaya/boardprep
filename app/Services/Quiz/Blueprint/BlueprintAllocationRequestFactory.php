<?php

declare(strict_types=1);

final class BlueprintAllocationRequestFactory
{
    public static function withQuestionCount(
        SelectionRequest $request,
        int $questionCount
    ): SelectionRequest {
        return new SelectionRequest(
            subject: $request->subject,
            domain: $request->domain,
            difficultyDistribution: $request->difficultyDistribution,
            questionCount: $questionCount,
            topic: $request->topic,
            concept: $request->concept
        );
    }
}
