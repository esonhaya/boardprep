<?php
declare(strict_types=1);

final class RecoveryQuestionContext
{
    public function __construct(
        public readonly string $status,
        public readonly ?string $subject,
        public readonly ?string $domain,
        public readonly ?string $topic,
        public readonly ?string $concept
    ) {
    }
}
