<?php

declare(strict_types=1);

namespace Tools\Doctor\Advisor;

use Tools\Doctor\Diagnostics\DiagnosticFinding;

final class FixRecommendationAdvisor
{
    /**
     * @return array<int,string>
     */
    public static function forFinding(
        DiagnosticFinding $finding
    ): array {
        return match ($finding->id) {
            'controller.large' => [
                'Extract application logic into Services.',
                'Keep Controllers focused on HTTP concerns.',
            ],

            'service.large' => [
                'Split the Service into smaller collaborators.',
            ],

            'method.large' => [
                'Extract private helper methods.',
                'Reduce branching where possible.',
            ],

            'architecture.layer_violation' => [
                'Inject Services instead of Repositories.',
                'Keep Controllers independent of persistence.',
            ],

            'dependency.circular' => [
                'Break the dependency cycle by introducing narrower collaborators.',
            ],

            'dependency.coupled' => [
                'Review the highest-coupled component.',
                'Prefer narrower collaborators when possible.',
            ],

            'class.dead' => [
                'Verify the class is unused before removing it.',
            ],

            'import.unused' => [
                'Remove the unused import.',
            ],

            'directory.empty' => [
                'Delete or populate the empty directory.',
            ],

            'domain.migration' => [
                'Continue implementing the Domain layer.',
                'Replace placeholder directories as features are added.',
            ],

            default => [],
        };
    }

    /**
     * @return array<int,string>
     */
    public static function for(
        string $title
    ): array {
        return match ($title) {
            'Largest Controller' => [
                'Extract application logic into Services.',
                'Keep Controllers focused on HTTP concerns.',
            ],

            'Largest Service' => [
                'Split the Service into smaller collaborators.',
            ],

            'Largest Method' => [
                'Extract private helper methods.',
                'Reduce branching where possible.',
            ],

            'Layer Violations' => [
                'Inject Services instead of Repositories.',
                'Keep Controllers independent of persistence.',
            ],

            'Dead Classes' => [
                'Verify classes are unused before removing them.',
            ],

            default => [],
        };
    }
}
