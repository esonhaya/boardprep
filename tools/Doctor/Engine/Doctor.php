<?php

declare(strict_types=1);

namespace Tools\Doctor\Engine;

use Tools\Doctor\Contracts\CheckInterface;
use Tools\Doctor\Registry\CheckRegistry;

final class Doctor
{
    /**
     * @return CheckInterface[]
     */
    public static function checks(): array
    {
        return (new CheckRegistry())->fromDirectories([
            "./tools/Doctor/Project/Shared/Checks",
            "./tools/Doctor/Project/BoardPrep/Checks",
            "./tools/Doctor/Self/Checks",
        ]);
    }
}
