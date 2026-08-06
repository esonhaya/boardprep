<?php

declare(strict_types=1);

namespace Tools\Doctor\Engine;

use Tools\Doctor\Registry\CheckRegistry;

final class Doctor
{
    public static function checks(): array
    {
        return (new CheckRegistry())->all();
    }
}
