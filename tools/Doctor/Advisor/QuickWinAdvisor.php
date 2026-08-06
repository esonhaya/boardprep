<?php

declare(strict_types=1);

namespace Tools\Doctor\Advisor;

final class QuickWinAdvisor
{
    /**
     * @return array<int,string>
     */
    public static function fromWarnings(
        array $checks
    ): array {

        $wins = [];

        foreach ($checks as $check) {

            switch ($check->title) {

                case "Unused Imports":
                    $wins[] =
                        "Remove unused imports.";
                    break;

                case "Empty Directories":
                    $wins[] =
                        "Delete or populate empty directories.";
                    break;

                case "Largest Method":
                    $wins[] =
                        "Extract one helper method from the largest method.";
                    break;

                case "Largest Service":
                    $wins[] =
                        "Split one responsibility from the largest service.";
                    break;

            }

        }

        return array_values(
            array_unique($wins)
        );
    }
}
