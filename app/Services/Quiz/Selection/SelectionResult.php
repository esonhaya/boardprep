<?php

declare(strict_types=1);

final class SelectionResult
{
    public function __construct(

        public readonly array $questions,

        public readonly bool $fulfilled,

        public readonly SelectionRequest $request,

    ) {
    }
}
