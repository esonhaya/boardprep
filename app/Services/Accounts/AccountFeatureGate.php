<?php

declare(strict_types=1);

namespace App\Services\Accounts;

use App\Core\App;
use App\Core\Environment;

final class AccountFeatureGate
{
    public static function enabled(): bool
    {
        if (App::config('environment', 'production') === 'production') {
            return false;
        }

        return strtolower(trim((string) Environment::get('BOARDPREP_ACCOUNTS_ENABLED', 'false'))) === 'true';
    }
}
