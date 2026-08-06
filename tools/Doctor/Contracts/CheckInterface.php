<?php

declare(strict_types=1);

namespace Tools\Doctor\Contracts;

use Tools\Doctor\DTO\CheckResult;

interface CheckInterface
{
    public function run(): CheckResult;

    public function category(): string;

    public function priority(): int;
}
