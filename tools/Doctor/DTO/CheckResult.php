<?php

declare(strict_types=1);

namespace Tools\Doctor\DTO;

final class CheckResult
{
    /**
     * @param string[] $details
     * @param string[] $recommendations
     */
    public function __construct(
        public string $title,
        public string $status,
        public string $summary = "",
        public array $details = [],
        public array $recommendations = [],
        public int $score = 100,
    ) {
    }
}
