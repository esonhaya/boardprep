<?php

declare(strict_types=1);

namespace Tools\Doctor\Advisor;

final class FixRecommendationAdvisor
{
    /**
     * @return array<int,string>
     */
    public static function for(
        string $title
    ): array {

        return match ($title) {

            "Largest Controller" => [

                "Extract application logic into Services.",
                "Keep Controllers focused on HTTP concerns.",

            ],

            "Largest Service" => [

                "Split the Service into smaller collaborators.",

            ],

            "Largest Method" => [

                "Extract private helper methods.",
                "Reduce branching where possible.",

            ],

            "Layer Violations" => [

                "Inject Services instead of Repositories.",
                "Keep Controllers independent of persistence.",

            ],

            "Dead Classes" => [

                "Verify classes are unused before removing them.",

            ],

            default => [],

        };

    }
}
