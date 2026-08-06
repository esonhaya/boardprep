<?php

declare(strict_types=1);

namespace Tools\Doctor\Advisor;

final class EffortAdvisor
{
    public static function label(
        string $title
    ): string {

        return match ($title) {

            "Largest Method" =>
                "Small",

            "Largest Service" =>
                "Medium",

            "Largest Controller" =>
                "Medium",

            "Layer Violations" =>
                "Large",

            "Dead Classes" =>
                "Small",

            "Unused Imports" =>
                "Very Small",

            "Empty Directories" =>
                "Very Small",

            "Domain Migration" =>
                "Large",

            default =>
                "Unknown",

        };

    }
}
