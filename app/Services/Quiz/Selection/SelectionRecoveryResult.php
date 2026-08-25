<?php
declare(strict_types=1);

final class SelectionRecoveryResult
{
    public function __construct(
        public readonly array $questions,
        public readonly bool $recovered
    ) {}
}
