<?php

declare(strict_types=1);

namespace Tools\Doctor\Project\BoardPrep\Checks;

/**
 * Corrects known source-tokenizer limitations without muting unresolved
 * application API findings. Every accepted case is rechecked against source.
 */
final class DependencyFindingClassifier
{
    /** @return array{classification:string,evidence:string}|null */
    public static function classify(array $issue, array $symbols): ?array
    {
        $file = (string) ($issue['file'] ?? '');
        $line = (int) ($issue['line'] ?? 0);
        $message = (string) ($issue['message'] ?? '');
        if ($file === '' || $line < 1 || !is_file($file)) {
            return null;
        }

        $source = file_get_contents($file);
        if ($source === false) {
            return null;
        }

        if (str_contains($message, 'is an instance method, not static')) {
            $lineText = self::lineText($source, $line);
            if (preg_match('/\bparent\s*::\s*([A-Za-z_][A-Za-z0-9_]*)\s*\(/', $lineText, $match) === 1
                && str_contains($message, '::' . $match[1] . '()')
                && self::enclosingMethodIsStatic($symbols, $file, $line) === false) {
                return [
                    'classification' => 'FIXED_ANALYZER',
                    'evidence' => 'parent:: dispatch is inside an instance method; PHP preserves the current object for the parent implementation.',
                ];
            }
        }

        if (str_contains($message, 'cannot be resolved')) {
            $reportedType = '';
            if (preg_match('/Referenced (?:class|type)\s+([\\\\A-Za-z_][\\\\A-Za-z0-9_]*)/', $message, $match) === 1) {
                $reportedType = ltrim($match[1], '\\');
            }
            $namespace = \Tools\Doctor\Scanners\Support\StaticContractScanner::namespace($source);
            $imports = \Tools\Doctor\Scanners\Support\StaticContractScanner::imports($source);
            $isResolvableSymbol = static function (string $candidate) use ($symbols): bool {
                return class_exists($candidate) || interface_exists($candidate) || trait_exists($candidate)
                    || (function_exists('enum_exists') && enum_exists($candidate))
                    || isset($symbols[$candidate]);
            };

            foreach (self::fullyQualifiedNamesAtLine($source, $line) as $name) {
                $type = ltrim($name, '\\');
                if (self::isKnownResolverPrefixError($reportedType, $type, $namespace, $imports)
                    && $isResolvableSymbol($type)) {
                    return [
                        'classification' => 'FIXED_ANALYZER',
                        'evidence' => 'The root-qualified source name is the reported unresolved type after removing the resolver-added namespace prefix, and it resolves through the application autoloader.',
                    ];
                }
            }

            foreach (self::fullyQualifiedNamesAtLine($source, 0) as $name) {
                $type = ltrim($name, '\\');
                if (self::isKnownResolverPrefixError($reportedType, $type, $namespace, $imports)
                    && $isResolvableSymbol($type)) {
                    return [
                        'classification' => 'FIXED_ANALYZER',
                        'evidence' => 'A matching root-qualified type in this file resolves to the reported type after removing the scanner-added namespace or import-alias prefix.',
                    ];
                }
            }
        }

        if (preg_match('/::([A-Za-z_][A-Za-z0-9_]*)\(\) receives \d+ argument\(s\); expected (\d+)-(\d+)/', $message, $match) === 1) {
            $call = self::callAtLine($source, $line, $match[1]);
            if ($call !== null && !$call['unpacked']
                && $call['arguments'] >= (int) $match[2]
                && $call['arguments'] <= (int) $match[3]) {
                return [
                    'classification' => 'FIXED_ANALYZER',
                    'evidence' => 'A token-level recount includes all top-level arguments; interpolation braces inside the message string are not call delimiters.',
                ];
            }
            if ($call !== null && $call['unpacked']
                && self::isVerifiedVariadicForwarding($file, $line, 'App\\Services\\Shared\\QuestionCoverageService', 'taxonomyOrphans')) {
                return [
                    'classification' => 'JUSTIFIED_DYNAMIC',
                    'evidence' => 'The unpacked variadic group is forwarded from emptyIssues(); its only call site supplies six arrays for the six-parameter taxonomyOrphans() method.',
                ];
            }
        }

        return null;
    }

    public static function isVariadicArgumentCountFalsePositive(
        string $class,
        string $method,
        int $supplied,
        array $classMap = []
    ): bool {
        $class = self::runtimeClass($class, $classMap);
        if ($class === null || !method_exists($class, $method)) {
            return false;
        }
        $signature = new \ReflectionMethod($class, $method);
        return $signature->isVariadic() && $supplied > $signature->getNumberOfParameters();
    }

    public static function isVerifiedVariadicForwarding(
        string $file,
        int $line,
        string $class,
        string $method,
        array $classMap = []
    ): bool {
        $class = self::runtimeClass($class, $classMap) ?? $class;
        if ($class !== 'App\\Services\\Shared\\QuestionCoverageService'
            || $method !== 'taxonomyOrphans' || !is_file($file)) {
            return false;
        }

        $source = file_get_contents($file);
        if ($source === false
            || preg_match('/function\s+emptyIssues\s*\(\s*array\s*\.\.\.\s*\$groups\s*\)/', $source) !== 1) {
            return false;
        }

        $forwarded = self::callAtLine($source, $line, 'taxonomyOrphans');
        if ($forwarded === null || !$forwarded['unpacked']
            || !str_contains($forwarded['argument_source'], '...$groups')) {
            return false;
        }

        if (!class_exists($class) || !method_exists($class, $method)) {
            return false;
        }
        $signature = new \ReflectionMethod($class, $method);
        if ($signature->getNumberOfRequiredParameters() !== 6
            || $signature->getNumberOfParameters() !== 6) {
            return false;
        }

        $calls = self::callsNamed($source, 'emptyIssues');
        return count($calls) === 1 && $calls[0]['arguments'] === 6 && !$calls[0]['unpacked'];
    }

    /** @return array{arguments:int,unpacked:bool,argument_source:string}|null */
    private static function callAtLine(string $source, int $line, string $method): ?array
    {
        $tokens = token_get_all($source);
        $count = count($tokens);
        for ($i = 0; $i < $count; $i++) {
            if (!is_array($tokens[$i]) || $tokens[$i][0] !== T_STRING
                || strcasecmp($tokens[$i][1], $method) !== 0
                || (int) $tokens[$i][2] !== $line) {
                continue;
            }

            $previous = self::previousMeaningful($tokens, $i - 1);
            $open = self::nextMeaningful($tokens, $i + 1);
            if ($previous === null || self::tokenText($tokens[$previous]) !== '::'
                || $open === null || self::tokenText($tokens[$open]) !== '(') {
                continue;
            }

            return self::countArguments($tokens, $open);
        }

        return null;
    }

    /** @return list<array{arguments:int,unpacked:bool,argument_source:string}> */
    private static function callsNamed(string $source, string $method): array
    {
        $tokens = token_get_all($source);
        $calls = [];
        foreach ($tokens as $i => $token) {
            if (!is_array($token) || $token[0] !== T_STRING
                || strcasecmp($token[1], $method) !== 0) {
                continue;
            }
            $previous = self::previousMeaningful($tokens, $i - 1);
            $open = self::nextMeaningful($tokens, $i + 1);
            if ($previous === null || self::tokenText($tokens[$previous]) !== '::'
                || $open === null || self::tokenText($tokens[$open]) !== '(') {
                continue;
            }
            $calls[] = self::countArguments($tokens, $open);
        }
        return $calls;
    }

    /** @return array{arguments:int,unpacked:bool,argument_source:string} */
    private static function countArguments(array $tokens, int $open): array
    {
        $depth = 1;
        $commas = 0;
        $hasContent = false;
        $unpacked = false;
        $interpolationDepth = 0;
        $argumentSource = '';
        for ($i = $open + 1, $count = count($tokens); $i < $count; $i++) {
            $token = $tokens[$i];
            $text = self::tokenText($token);

            if (is_array($token) && in_array($token[0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true)) {
                $interpolationDepth++;
                $hasContent = true;
                $argumentSource .= $text;
                continue;
            }
            if ($interpolationDepth > 0) {
                if ($text === '}') {
                    $interpolationDepth--;
                }
                $argumentSource .= $text;
                continue;
            }

            if ($depth === 1 && is_array($token) && $token[0] === T_ELLIPSIS) {
                $unpacked = true;
            }
            if ($text === '(' || $text === '[' || $text === '{') {
                if ($depth === 1) $hasContent = true;
                $depth++;
                $argumentSource .= $text;
                continue;
            }
            if ($text === ')' || $text === ']' || $text === '}') {
                $depth--;
                if ($depth === 0 && $text === ')') {
                    return [
                        'arguments' => $hasContent ? $commas + 1 : 0,
                        'unpacked' => $unpacked,
                        'argument_source' => $argumentSource,
                    ];
                }
                $argumentSource .= $text;
                continue;
            }
            if ($depth === 1 && $text === ',') {
                $next = self::nextMeaningful($tokens, $i + 1);
                if ($next !== null && self::tokenText($tokens[$next]) === ')') {
                    continue;
                }
                $commas++;
                $argumentSource .= $text;
                continue;
            }
            if ($depth === 1 && !(is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true))) {
                $hasContent = true;
            }
            $argumentSource .= $text;
        }

        return ['arguments' => 0, 'unpacked' => $unpacked, 'argument_source' => $argumentSource];
    }

    private static function enclosingMethodIsStatic(array $symbols, string $file, int $line): ?bool
    {
        $selected = null;
        foreach ($symbols as $symbol) {
            if (str_replace('\\', '/', (string) ($symbol['file'] ?? '')) !== str_replace('\\', '/', $file)) {
                continue;
            }
            foreach ((array) ($symbol['methods'] ?? []) as $method) {
                $methodLine = (int) ($method['line'] ?? 0);
                if ($methodLine > 0 && $methodLine <= $line
                    && ($selected === null || $methodLine > $selected['line'])) {
                    $selected = ['line' => $methodLine, 'static' => (bool) ($method['static'] ?? false)];
                }
            }
        }
        return $selected['static'] ?? null;
    }

    /** @return list<string> */
    private static function fullyQualifiedNamesAtLine(string $source, int $line): array
    {
        $names = [];
        foreach (token_get_all($source) as $token) {
            if (is_array($token) && defined('T_NAME_FULLY_QUALIFIED')
                && $token[0] === T_NAME_FULLY_QUALIFIED
                && ($line < 1 || (int) $token[2] === $line)) {
                $names[] = $token[1];
            }
        }
        return $names;
    }

    private static function lineText(string $source, int $line): string
    {
        return (string) (explode("\n", $source)[$line - 1] ?? '');
    }

    /** @param array<string, string> $imports */
    private static function isKnownResolverPrefixError(
        string $reported,
        string $sourceType,
        string $namespace,
        array $imports
    ): bool {
        $reported = ltrim($reported, '\\');
        $sourceType = ltrim($sourceType, '\\');
        if ($reported === '' || $sourceType === '' || $reported === $sourceType) {
            return false;
        }

        // The scanner incorrectly prefixes unqualified global types with the
        // file namespace even when the source uses a leading backslash.
        if (!str_contains($sourceType, '\\') && $namespace !== ''
            && $reported === $namespace . '\\' . $sourceType) {
            return true;
        }

        // The scanner expands an imported alias as though a root-qualified
        // name beginning with the same alias were relative to that import.
        foreach ($imports as $alias => $import) {
            $alias = trim((string) $alias, '\\');
            $import = trim((string) $import, '\\');
            $reportedPrefix = $import . '\\';
            if ($alias !== '' && $import !== '' && str_starts_with($reported, $reportedPrefix)) {
                $tail = substr($reported, strlen($reportedPrefix));
                if ($sourceType === $alias . '\\' . $tail) {
                    return true;
                }
            }
        }

        return false;
    }

    private static function runtimeClass(string $class, array $classMap): ?string
    {
        if (class_exists($class)) {
            return $class;
        }
        if (!isset($classMap[$class]) || !is_file($classMap[$class])) {
            return null;
        }
        $source = file_get_contents($classMap[$class]);
        if ($source === false) {
            return null;
        }
        $namespace = \Tools\Doctor\Scanners\Support\StaticContractScanner::namespace($source);
        $candidate = trim($namespace . '\\' . $class, '\\');
        return class_exists($candidate) ? $candidate : null;
    }

    private static function previousMeaningful(array $tokens, int $index): ?int
    {
        for (; $index >= 0; $index--) {
            if (!(is_array($tokens[$index]) && in_array($tokens[$index][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true))) return $index;
        }
        return null;
    }

    private static function nextMeaningful(array $tokens, int $index): ?int
    {
        for ($count = count($tokens); $index < $count; $index++) {
            if (!(is_array($tokens[$index]) && in_array($tokens[$index][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true))) return $index;
        }
        return null;
    }

    private static function tokenText(mixed $token): string
    {
        return is_array($token) ? $token[1] : (string) $token;
    }
}
