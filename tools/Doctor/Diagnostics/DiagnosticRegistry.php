<?php

declare(strict_types=1);

namespace Tools\Doctor\Diagnostics;

final class DiagnosticRegistry
{
    /**
     * @var array<string,array<string,mixed>>
     */
    private const DEFINITIONS = [

        'method.large' => [
            'category' => 'complexity',
            'impact' => 100,
            'effort' => 'Small',
            'label' => 'Critical',
        ],

        'service.large' => [
            'category' => 'complexity',
            'impact' => 95,
            'effort' => 'Medium',
            'label' => 'Critical',
        ],

        'controller.large' => [
            'category' => 'architecture',
            'impact' => 90,
            'effort' => 'Medium',
            'label' => 'Critical',
        ],

        'architecture.layer_violation' => [
            'category' => 'architecture',
            'impact' => 90,
            'effort' => 'Large',
            'label' => 'Critical',
        ],

        'dependency.circular' => [
            'category' => 'dependency',
            'impact' => 90,
            'effort' => 'Large',
            'label' => 'Critical',
        ],

        'dependency.coupled' => [
            'category' => 'dependency',
            'impact' => 80,
            'effort' => 'Medium',
            'label' => 'High',
        ],

        'class.dead' => [
            'category' => 'cleanup',
            'impact' => 60,
            'effort' => 'Small',
            'label' => 'Medium',
        ],

        'import.unused' => [
            'category' => 'cleanup',
            'impact' => 40,
            'effort' => 'Very Small',
            'label' => 'Medium',
        ],

        'directory.empty' => [
            'category' => 'cleanup',
            'impact' => 20,
            'effort' => 'Very Small',
            'label' => 'Low',
        ],

        'domain.migration' => [
            'category' => 'architecture',
            'impact' => 15,
            'effort' => 'Large',
            'label' => 'Low',
        ],
    ];

    public static function has(
        string $id
    ): bool {
        return isset(self::DEFINITIONS[$id]);
    }

    /**
     * @return array<string,mixed>|null
     */
    public static function get(
        string $id
    ): ?array {
        return self::DEFINITIONS[$id] ?? null;
    }

    public static function category(
        string $id
    ): string {
        return self::DEFINITIONS[$id]['category'] ?? 'unknown';
    }

    public static function impact(
        string $id
    ): int {
        return self::DEFINITIONS[$id]['impact'] ?? 10;
    }

    public static function effort(
        string $id
    ): string {
        return self::DEFINITIONS[$id]['effort'] ?? 'Unknown';
    }

    public static function label(
        string $id
    ): string {
        return self::DEFINITIONS[$id]['label'] ?? 'Low';
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    public static function all(): array
    {
        return self::DEFINITIONS;
    }
}
