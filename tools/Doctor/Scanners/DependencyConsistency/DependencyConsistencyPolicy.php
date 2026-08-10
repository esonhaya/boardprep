<?php

declare(strict_types=1);

namespace Tools\Doctor\Scanners\DependencyConsistency;

/**
 * Project-independent configuration for static cross-file API analysis.
 *
 * The scanner should know HOW to inspect PHP.
 * This policy decides WHAT belongs to the analysis.
 */
final class DependencyConsistencyPolicy
{
    /**
     * @param list<string> $roots
     * @param list<string> $knownExternalTypes
     */
    public function __construct(
        public readonly array $roots = [
            'app/',
            'routes/',
        ],
        public readonly array $knownExternalTypes = [],
        public readonly int $maxIssues = 40,
    ) {
    }

    public function includes(string $path): bool
    {
        $path = str_replace('\\', '/', $path);

        foreach ($this->roots as $root) {
            $root = rtrim(
                str_replace('\\', '/', $root),
                '/'
            ) . '/';

            if (
                str_starts_with($path, $root)
                || str_starts_with($path, './' . $root)
            ) {
                return true;
            }
        }

        return false;
    }

    public function isKnownExternalType(string $type): bool
    {
        $type = ltrim($type, '\\');

        if ($type === '') {
            return true;
        }

        $builtins = [
            'array',
            'string',
            'int',
            'float',
            'bool',
            'mixed',
            'object',
            'callable',
            'iterable',
            'void',
            'never',
            'null',
            'true',
            'false',
            'self',
            'static',
            'parent',

            'stdClass',
            'Exception',
            'Error',
            'Throwable',
            'RuntimeException',
            'LogicException',
            'InvalidArgumentException',
            'UnexpectedValueException',
            'OutOfBoundsException',
            'PDO',
            'PDOException',
            'DateTime',
            'DateTimeImmutable',
            'DateTimeInterface',
            'JsonException',
            'Closure',
            'Generator',
            'Traversable',
            'Iterator',
            'Countable',
        ];

        $normalizedBuiltins =
            array_map(
                'strtolower',
                $builtins
            );

        if (
            in_array(
                strtolower($type),
                $normalizedBuiltins,
                true
            )
        ) {
            return true;
        }

        return
            in_array(
                $type,
                $this->knownExternalTypes,
                true
            )
            || class_exists($type, false)
            || interface_exists($type, false)
            || trait_exists($type, false);
    }
}
