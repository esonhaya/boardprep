<?php

declare(strict_types=1);

namespace Tools\Doctor\Diagnostics;

use Countable;
use IteratorAggregate;
use Traversable;

/**
 * @implements IteratorAggregate<int,DiagnosticFinding>
 */
final class DiagnosticFindingCollection implements Countable, IteratorAggregate
{
    /**
     * @var DiagnosticFinding[]
     */
    private array $findings = [];

    public function add(
        DiagnosticFinding $finding
    ): void {
        $this->findings[] = $finding;
    }

    /**
     * @param DiagnosticFinding[] $findings
     */
    public function addMany(
        array $findings
    ): void {
        foreach ($findings as $finding) {
            $this->add($finding);
        }
    }

    public function count(): int
    {
        return count($this->findings);
    }

    /**
     * @return Traversable<int,DiagnosticFinding>
     */
    public function getIterator(): Traversable
    {
        yield from $this->findings;
    }

    /**
     * @return DiagnosticFinding[]
     */
    public function all(): array
    {
        return $this->findings;
    }

    /**
     * @return DiagnosticFinding[]
     */
    public function bySeverity(
        string $severity
    ): array {
        return array_values(
            array_filter(
                $this->findings,
                static fn (
                    DiagnosticFinding $finding
                ): bool => $finding->severity === $severity
            )
        );
    }

    /**
     * @return DiagnosticFinding[]
     */
    public function byCategory(
        string $category
    ): array {
        return array_values(
            array_filter(
                $this->findings,
                static fn (
                    DiagnosticFinding $finding
                ): bool => $finding->category === $category
            )
        );
    }

    public function hasSeverity(
        string $severity
    ): bool {
        return $this->bySeverity($severity) !== [];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function toArray(): array
    {
        return array_map(
            static fn (
                DiagnosticFinding $finding
            ): array => $finding->toArray(),
            $this->findings
        );
    }
}
