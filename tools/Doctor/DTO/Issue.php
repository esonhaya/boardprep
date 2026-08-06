<?php

declare(strict_types=1);

namespace Tools\Doctor\DTO;

final class Issue
{
    public function __construct(
        public readonly string $severity,
        public readonly string $category,
        public readonly string $rule,
        public readonly string $file,
        public readonly string $message,
        public readonly ?string $suggestion = null,
    ) {
    }
}
