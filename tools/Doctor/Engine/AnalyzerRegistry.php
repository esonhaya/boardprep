<?php

declare(strict_types=1);

namespace Tools\Doctor\Engine;

use Tools\Doctor\Analyzers\ChangeImpactAnalyzer;
use Tools\Doctor\Analyzers\CyclomaticAnalyzer;
use Tools\Doctor\Analyzers\HotspotAnalyzer;
use Tools\Doctor\Analyzers\MaintainabilityAnalyzer;

final class AnalyzerRegistry
{
    public static function all(): array
    {
        return [

            new CyclomaticAnalyzer(),

            new MaintainabilityAnalyzer(),

            new HotspotAnalyzer(),

            new ChangeImpactAnalyzer(),

        ];
    }
}
