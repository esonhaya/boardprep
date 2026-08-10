<?php

declare(strict_types=1);

namespace Tools\Doctor\Diagnostics;

final class DiagnosticFindingFactory
{
    /**
     * @param array<string,mixed> $evidence
     */
    public static function warning(
        string $id,
        string $category,
        string $rule,
        string $title,
        string $message,
        ?string $file = null,
        ?string $symbol = null,
        ?string $recommendation = null,
        array $evidence = [],
    ): DiagnosticFinding {
        return self::make(
            severity: 'WARNING',
            id: $id,
            category: $category,
            rule: $rule,
            title: $title,
            message: $message,
            file: $file,
            symbol: $symbol,
            recommendation: $recommendation,
            evidence: $evidence,
        );
    }

    /**
     * @param array<string,mixed> $evidence
     */
    public static function error(
        string $id,
        string $category,
        string $rule,
        string $title,
        string $message,
        ?string $file = null,
        ?string $symbol = null,
        ?string $recommendation = null,
        array $evidence = [],
    ): DiagnosticFinding {
        return self::make(
            severity: 'ERROR',
            id: $id,
            category: $category,
            rule: $rule,
            title: $title,
            message: $message,
            file: $file,
            symbol: $symbol,
            recommendation: $recommendation,
            evidence: $evidence,
        );
    }

    /**
     * @param array<string,mixed> $evidence
     */
    public static function critical(
        string $id,
        string $category,
        string $rule,
        string $title,
        string $message,
        ?string $file = null,
        ?string $symbol = null,
        ?string $recommendation = null,
        array $evidence = [],
    ): DiagnosticFinding {
        return self::make(
            severity: 'CRITICAL',
            id: $id,
            category: $category,
            rule: $rule,
            title: $title,
            message: $message,
            file: $file,
            symbol: $symbol,
            recommendation: $recommendation,
            evidence: $evidence,
        );
    }

    /**
     * @param array<string,mixed> $evidence
     */
    public static function info(
        string $id,
        string $category,
        string $rule,
        string $title,
        string $message,
        ?string $file = null,
        ?string $symbol = null,
        ?string $recommendation = null,
        array $evidence = [],
    ): DiagnosticFinding {
        return self::make(
            severity: 'INFO',
            id: $id,
            category: $category,
            rule: $rule,
            title: $title,
            message: $message,
            file: $file,
            symbol: $symbol,
            recommendation: $recommendation,
            evidence: $evidence,
        );
    }

    /**
     * @param array<string,mixed> $evidence
     */
    private static function make(
        string $severity,
        string $id,
        string $category,
        string $rule,
        string $title,
        string $message,
        ?string $file,
        ?string $symbol,
        ?string $recommendation,
        array $evidence,
    ): DiagnosticFinding {
        return new DiagnosticFinding(
            id: $id,
            severity: $severity,
            category: $category,
            rule: $rule,
            title: $title,
            message: $message,
            file: $file,
            symbol: $symbol,
            recommendation: $recommendation,
            evidence: $evidence,
        );
    }
}
