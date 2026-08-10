<?php

declare(strict_types=1);

namespace Tools\Doctor\Advisor;

use Tools\Doctor\Diagnostics\DiagnosticFinding;

final class QuickWinAdvisor
{
    /**
     * @param DiagnosticFinding[] $findings
     *
     * @return array<int,string>
     */
    public static function fromFindings(
        array $findings
    ): array {
        $wins = [];

        foreach ($findings as $finding) {
            $win = match ($finding->id) {
                'import.unused' =>
                    'Remove unused imports.',

                'directory.empty' =>
                    'Delete or populate empty directories.',

                'method.large' =>
                    'Extract one helper method from the largest method.',

                'service.large' =>
                    'Split one responsibility from the largest service.',

                'class.dead' =>
                    'Verify potentially unused classes before removing them.',

                default => null,
            };

            if ($win !== null) {
                $wins[] = $win;
            }
        }

        return array_values(
            array_unique($wins)
        );
    }

    /**
     * Legacy title-based compatibility path.
     *
     * @param array<int,object> $checks
     *
     * @return array<int,string>
     */
    public static function fromWarnings(
        array $checks
    ): array {
        $wins = [];

        foreach ($checks as $check) {
            $win = match ($check->title) {
                'Unused Imports' =>
                    'Remove unused imports.',

                'Empty Directories' =>
                    'Delete or populate empty directories.',

                'Largest Method' =>
                    'Extract one helper method from the largest method.',

                'Largest Service' =>
                    'Split one responsibility from the largest service.',

                default => null,
            };

            if ($win !== null) {
                $wins[] = $win;
            }
        }

        return array_values(
            array_unique($wins)
        );
    }
}
