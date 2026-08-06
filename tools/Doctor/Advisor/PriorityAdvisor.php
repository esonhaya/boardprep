<?php

declare(strict_types=1);

namespace Tools\Doctor\Advisor;

final class PriorityAdvisor
{
    public static function impact(
        string $title
    ): int {

        return match ($title) {

            "Largest Method" =>
                100,

            "Largest Service" =>
                95,

            "Largest Controller" =>
                90,

            "Layer Violations" =>
                90,

            "Circular Dependencies" =>
                90,

            "Dependency Coupling" =>
                80,

            "Dead Classes" =>
                60,

            "Unused Imports" =>
                40,

            "Empty Directories" =>
                20,

            "Domain Migration" =>
                15,

            default =>
                10,

        };

    }

    public static function label(
        int $impact
    ): string {

        return match (true) {

            $impact >= 90 =>
                "Critical",

            $impact >= 70 =>
                "High",

            $impact >= 40 =>
                "Medium",

            default =>
                "Low",

        };

    }
}
