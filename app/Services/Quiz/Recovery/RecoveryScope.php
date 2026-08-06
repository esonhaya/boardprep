<?php

declare(strict_types=1);

enum RecoveryScope: string
{
    case Concept = 'concept';
    case Topic = 'topic';
    case Domain = 'domain';
    case Subject = 'subject';
}
