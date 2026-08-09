<?php

declare(strict_types=1);

final class RequestExecutionPlan
{
    /**
     * @param SelectionRequest[] $requests
     */
    public function __construct(
        public readonly array $requests
    ) {
    }

    public function count(): int
    {
        return count($this->requests);
    }

    public function totalQuestions(): int
    {
        $total = 0;

        foreach ($this->requests as $request) {
            $total += $request->questionCount;
        }

        return $total;
    }
}
