<?php
declare(strict_types=1);

final class RecoveryStatusPolicy
{
    public static function allows(string $status): bool
    {
        return in_array(strtolower($status), ['active', 'approved'], true);
    }
}
