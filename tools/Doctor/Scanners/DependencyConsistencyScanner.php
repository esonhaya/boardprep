<?php

declare(strict_types=1);

namespace Tools\Doctor\Scanners;

use Tools\Doctor\Scanners\DependencyConsistency\DependencyConsistencyPolicy;

final class DependencyConsistencyScanner
{
    /**
     * Static API contract scanner for the application layer.
     * It deliberately avoids executing application code.
     *
     * @return array{issues:array<int,array<string,mixed>>, symbols:array<string,array<string,mixed>>}
     */
    public static function scan(
        array $files,
        ?DependencyConsistencyPolicy $policy = null
    ): array {
        $policy ??= new DependencyConsistencyPolicy();

        $files = array_values(array_filter(
            $files,
            static function (array|string $file) use ($policy): bool {
                $path = is_array($file)
                    ? (string) ($file['path'] ?? '')
                    : $file;

                return $policy->includes($path);
            }
        ));

        $symbols = self::collectSymbols($files);
        $issues = [];

        foreach ($symbols as $symbol) {
            $issues = array_merge(
                $issues,
                self::checkInheritanceContracts($symbol, $symbols),
                self::checkReferences($symbol, $symbols)
            );
        }

        $issues = array_merge($issues, self::checkDuplicateSymbols($symbols));

        usort($issues, static function (array $a, array $b): int {
            return [$a['file'], $a['line'], $a['message']]
                <=> [$b['file'], $b['line'], $b['message']];
        });

        return [
            'issues' => $issues,
            'symbols' => $symbols,
        ];
    }

    private static function collectSymbols(array $files): array
    {
        $symbols = [];

        foreach ($files as $file) {
            $path = is_array($file) ? (string) ($file['path'] ?? '') : (string) $file;
            if ($path === '' || !is_file($path)) {
                continue;
            }

            $code = file_get_contents($path);
            if ($code === false || $code === '') {
                continue;
            }

            $tokens = token_get_all($code);
            $namespace = '';
            $imports = [];
            $classes = self::parseDeclarations($tokens, $path, $namespace, $imports);

            foreach ($classes as $class) {
                $fqcn = self::fqcn($class['namespace'], $class['name']);
                $class['fqcn'] = $fqcn;
                $class['imports'] = $imports;
                $class['code'] = $code;
                $class['tokens'] = $tokens;
                $symbols[$fqcn] = $class;
            }
        }

        return $symbols;
    }

    private static function parseDeclarations(array $tokens, string $file, string &$namespace, array &$imports): array
    {
        $classes = [];
        $namespace = '';
        $imports = [];
        $count = count($tokens);

        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];
            if (!is_array($token)) {
                continue;
            }

            if ($token[0] === T_NAMESPACE) {
                [$name, $end] = self::readNameStatement($tokens, $i + 1);
                $namespace = trim($name, '\\');
                $i = $end;
                continue;
            }

            if ($token[0] === T_USE) {
                [$importsFound, $end] = self::readImports($tokens, $i + 1);
                foreach ($importsFound as $alias => $target) {
                    $imports[strtolower($alias)] = $target;
                }
                $i = $end;
                continue;
            }

            if (!in_array($token[0], array_filter([T_CLASS, T_INTERFACE, T_TRAIT, defined('T_ENUM') ? T_ENUM : null], static fn($v) => $v !== null), true)) {
                continue;
            }

            if ($token[0] === T_CLASS && self::isAnonymousClass($tokens, $i)) {
                continue;
            }

            $kind = match ($token[0]) {
                T_INTERFACE => 'interface',
                T_TRAIT => 'trait',
                defined('T_ENUM') ? T_ENUM : -1 => 'enum',
                default => 'class',
            };

            $nameIndex = self::nextToken($tokens, $i + 1, T_STRING);
            if ($nameIndex === null) {
                continue;
            }

            $name = $tokens[$nameIndex][1];
            $headerEnd = self::findToken($tokens, $nameIndex + 1, '{');
            if ($headerEnd === null) {
                continue;
            }

            $header = self::tokensText($tokens, $nameIndex + 1, $headerEnd - 1);
            $extends = null;
            $implements = [];

            if (preg_match('/\bextends\s+([^\s{]+(?:\s*,\s*[^\s{]+)*)/i', $header, $m)) {
                $extends = trim($m[1]);
            }
            if (preg_match('/\bimplements\s+([^\{]+)/i', $header, $m)) {
                $implements = array_values(array_filter(array_map('trim', preg_split('/,/', trim($m[1])) ?: [])));
            }

            $close = self::matchingBrace($tokens, $headerEnd);
            if ($close === null) {
                continue;
            }

            $body = array_slice($tokens, $headerEnd + 1, $close - $headerEnd - 1);
            [$methods, $properties] = self::parseMembers($body, $tokens[$nameIndex][2] ?? 1);

            $classes[] = [
                'name' => $name,
                'namespace' => $namespace,
                'kind' => $kind,
                'extends' => $extends,
                'implements' => $implements,
                'methods' => $methods,
                'properties' => $properties,
                'file' => $file,
                'line' => (int) ($tokens[$nameIndex][2] ?? 1),
            ];

            $i = $close;
        }

        return $classes;
    }

    private static function parseMembers(array $tokens, int $baseLine): array
    {
        $methods = [];
        $properties = [];
        $depth = 0;
        $interpolationDepth = 0;
        $count = count($tokens);
        $pending = [
            'visibility' => 'public',
            'static' => false,
            'abstract' => false,
            'final' => false,
        ];

        for ($i = 0; $i < $count; $i++) {
            $t = $tokens[$i];

            if ($t === '{') {
                if ($interpolationDepth > 0) {
                    $interpolationDepth++;
                } else {
                    $depth++;
                }
                continue;
            }

            if (is_array($t) && $t[0] === T_CURLY_OPEN) {
                $interpolationDepth++;
                continue;
            }

            if ($t === '}') {
                if ($interpolationDepth > 0) {
                    $interpolationDepth--;
                } else {
                    $depth--;
                }
                continue;
            }

            if ($depth !== 0 || $interpolationDepth !== 0 || !is_array($t)) {
                continue;
            }

            if (self::applyMemberModifier($t, $pending)) continue;

            if ($t[0] === T_FUNCTION) {
                $method = self::parseMemberMethod($tokens, $i, $baseLine, $pending);
                if ($method === null) continue;

                $methods[$method['name']] = $method;
                $pending = self::defaultMemberModifiers();
                continue;
            }

            if ($t[0] === T_VARIABLE) {
                self::registerMemberProperty($properties, $tokens, $i, $baseLine, $pending);
            }
        }

        return [$methods, $properties];
    }

    private static function defaultMemberModifiers(): array
    {
        return [
            'visibility' => 'public',
            'static' => false,
            'abstract' => false,
            'final' => false,
        ];
    }

    private static function applyMemberModifier(array $token, array &$pending): bool
    {
        if ($token[0] === T_PUBLIC || $token[0] === T_PROTECTED || $token[0] === T_PRIVATE) {
            $pending['visibility'] = strtolower($token[1]);
            return true;
        }
        if ($token[0] === T_STATIC) {
            $pending['static'] = true;
            return true;
        }
        if ($token[0] === T_ABSTRACT) {
            $pending['abstract'] = true;
            return true;
        }
        if ($token[0] === T_FINAL) {
            $pending['final'] = true;
            return true;
        }
        return defined('T_READONLY') && $token[0] === T_READONLY;
    }

    private static function parseMemberMethod(
        array $tokens,
        int $index,
        int $baseLine,
        array $pending
    ): ?array {
        $nameIndex = self::nextToken($tokens, $index + 1, T_STRING);
        if ($nameIndex === null) return null;

        $open = self::findToken($tokens, $nameIndex + 1, '(');
        if ($open === null) return null;

        $close = self::matchingParen($tokens, $open);
        if ($close === null) return null;

        $name = $tokens[$nameIndex][1];

        return [
            'name' => $name,
            'visibility' => $pending['visibility'],
            'static' => $pending['static'],
            'abstract' => $pending['abstract'],
            'final' => $pending['final'],
            'params' => self::parseParameters(array_slice($tokens, $open + 1, $close - $open - 1)),
            'returnType' => self::parseReturnType($tokens, $close + 1),
            'line' => (int) ($tokens[$nameIndex][2] ?? $baseLine),
        ];
    }

    private static function registerMemberProperty(
        array &$properties,
        array $tokens,
        int $index,
        int $baseLine,
        array $pending
    ): void {
        $name = substr($tokens[$index][1], 1);

        $properties[$name] = [
            'name' => $name,
            'visibility' => $pending['visibility'],
            'static' => $pending['static'],
            'type' => self::propertyTypeBefore($tokens, $index),
            'line' => (int) ($tokens[$index][2] ?? $baseLine),
        ];
    }

    private static function propertyTypeBefore(array $tokens, int $variableIndex): ?string
    {
        $parts = [];
        for ($i = $variableIndex - 1; $i >= 0; $i--) {
            $t = $tokens[$i];
            if ($t === ';' || $t === '{' || $t === '}') break;
            if (is_array($t) && in_array($t[0], [T_WHITESPACE, T_PUBLIC, T_PROTECTED, T_PRIVATE, T_STATIC, T_READONLY], true)) continue;
            if (is_array($t) && self::isTypeToken($t[0])) {
                array_unshift($parts, $t[1]);
                continue;
            }
            if ($t === '?' || $t === '|' || $t === '\\' || $t === '&') {
                array_unshift($parts, $t);
                continue;
            }
            break;
        }
        $type = trim(implode('', $parts));
        return $type === '' ? null : self::normalizeTypeText($type);
    }

    private static function parseParameters(array $tokens): array
    {
        if ($tokens === []) return [];

        $params = [];
        foreach (self::splitParameterSegments($tokens) as $segment) {
            $parameter = self::parseParameterSegment($segment);
            if ($parameter !== null) $params[] = $parameter;
        }

        return $params;
    }

    private static function splitParameterSegments(array $tokens): array
    {
        $segments = [];
        $current = [];
        $depth = 0;

        foreach ($tokens as $token) {
            if (in_array($token, ['(', '[', '{'], true)) $depth++;
            if (in_array($token, [')', ']', '}'], true)) $depth--;

            if ($token === ',' && $depth === 0) {
                $segments[] = $current;
                $current = [];
                continue;
            }

            $current[] = $token;
        }

        if ($current !== []) $segments[] = $current;
        return $segments;
    }

    private static function parseParameterSegment(array $segment): ?array
    {
        $typeParts = [];
        $name = null;
        $variadic = false;
        $optional = false;
        $seenVariable = false;

        foreach ($segment as $token) {
            if (is_array($token)) {
                if ($token[0] === T_ELLIPSIS) {
                    $variadic = true;
                    continue;
                }
                if ($token[0] === T_VARIABLE) {
                    $name = substr($token[1], 1);
                    $seenVariable = true;
                    continue;
                }
                if (!$seenVariable && self::isTypeToken($token[0])) {
                    $typeParts[] = $token[1];
                }
                continue;
            }

            if ($token === '=' && $seenVariable) $optional = true;
            if (!$seenVariable && in_array($token, ['?', '|', '&', '\\'], true)) {
                $typeParts[] = $token;
            }
        }

        if ($name === null) return null;

        return [
            'name' => $name,
            'type' => self::normalizeTypeText(implode('', $typeParts)) ?: null,
            'variadic' => $variadic,
            'optional' => $optional || $variadic,
        ];
    }

    private static function parseReturnType(array $tokens, int $start): ?string
    {
        $parts = '';
        for ($i = $start, $count = count($tokens); $i < $count; $i++) {
            $t = $tokens[$i];
            if ($t === '{' || $t === ';') break;
            if (is_array($t) && $t[0] === T_WHITESPACE) continue;
            $parts .= is_array($t) ? $t[1] : $t;
        }
        $parts = trim($parts);
        if (!str_starts_with($parts, ':')) return null;
        return self::normalizeTypeText(trim(substr($parts, 1)));
    }

    private static function checkReferences(array $symbol, array $symbols): array
    {
        $issues = [];
        $tokens = $symbol['tokens'] ?? token_get_all((string) ($symbol['code'] ?? ''));
        $variables = [];

        for ($i = 0, $count = count($tokens); $i < $count; $i++) {
            $token = $tokens[$i];
            if (!is_array($token)) continue;

            if ($token[0] === T_FUNCTION) {
                self::collectReferenceMethodVariables($tokens, $i, $variables);
                continue;
            }
            if ($token[0] === T_USE) {
                self::collectReferenceUseVariables($tokens, $i, $variables);
                continue;
            }
            if ($token[0] === T_VARIABLE) {
                self::checkObjectReference($issues, $symbol, $symbols, $tokens, $i, $variables);
                continue;
            }
            if ($token[0] === T_DOUBLE_COLON) {
                self::checkStaticReference($issues, $symbol, $symbols, $tokens, $i);
                continue;
            }
            if ($token[0] === T_NEW) {
                self::checkNewReference($issues, $symbol, $symbols, $tokens, $i);
            }
        }

        return $issues;
    }

    private static function collectReferenceMethodVariables(array $tokens, int &$index, array &$variables): void
    {
        $name = self::nextToken($tokens, $index + 1, T_STRING);
        $open = $name === null ? null : self::findToken($tokens, $name + 1, '(');
        if ($open === null) return;
        $close = self::matchingParen($tokens, $open);
        if ($close === null) return;

        foreach (self::parseParameterTypesWithVariables(
            array_slice($tokens, $open + 1, $close - $open - 1)
        ) as $name => $type) {
            $variables[$name] = $type;
        }
    }

    private static function collectReferenceUseVariables(array $tokens, int &$index, array &$variables): void
    {
        $open = self::nextMeaningful($tokens, $index + 1);
        if ($open === null || ($tokens[$open] ?? null) !== '(') return;
        $close = self::matchingParen($tokens, $open);
        if ($close === null) return;

        foreach (self::parseUseVariables(array_slice($tokens, $open + 1, $close - $open - 1)) as $name) {
            if (!isset($variables[$name])) $variables[$name] = null;
        }
        $index = $close;
    }

    private static function checkObjectReference(
        array &$issues, array $symbol, array $symbols, array $tokens, int $index, array &$variables
    ): void {
        $name = substr($tokens[$index][1], 1);
        if ($name === 'this') $variables['this'] = $symbol['fqcn'];

        $next = self::nextMeaningful($tokens, $index + 1);
        if ($next === null || !is_array($tokens[$next]) || $tokens[$next][0] !== T_OBJECT_OPERATOR) return;

        $member = self::nextMeaningful($tokens, $next + 1);
        if ($member === null || !is_array($tokens[$member]) || $tokens[$member][0] !== T_STRING) return;

        $type = $variables[$name] ?? null;
        if ($type === null) return;
        $target = $name === 'this' ? $type : self::resolveType($type, $symbol);
        if ($target === null) return;

        if (!isset($symbols[$target])) {
            if (!self::isKnownExternalType($target)) {
                $issues[] = self::issue($symbol, $tokens[$member][2] ?? 1,
                    sprintf('Referenced type %s cannot be resolved', $target));
            }
            return;
        }

        $memberName = $tokens[$member][1];
        $after = self::nextMeaningful($tokens, $member + 1);
        if ($after !== null && ($tokens[$after] ?? null) === '(') {
            self::validateMethodCall($issues, $symbol, $symbols, $target, $memberName, false, $after, $tokens);
        } else {
            self::validateProperty($issues, $symbol, $symbols, $target, $memberName, $tokens[$member][2] ?? 1);
        }
    }

    private static function checkStaticReference(
        array &$issues, array $symbol, array $symbols, array $tokens, int $index
    ): void {
        $nameIndex = $index - 1;
        while ($nameIndex >= 0 && is_array($tokens[$nameIndex]) && $tokens[$nameIndex][0] === T_WHITESPACE) $nameIndex--;
        if ($nameIndex < 0 || !is_array($tokens[$nameIndex])) return;

        $types = array_filter([
            T_STRING, T_NAME_QUALIFIED ?? null, T_NAME_FULLY_QUALIFIED ?? null,
            defined('T_NAME_RELATIVE') ? T_NAME_RELATIVE : null
        ], static fn($v) => $v !== null);
        if (!in_array($tokens[$nameIndex][0], $types, true)) return;

        $className = $tokens[$nameIndex][1];
        if ($className === 'self' || $className === 'static') {
            $target = $symbol['fqcn'];
        } elseif ($className === 'parent') {
            $target = self::resolveClassName($symbol['extends'] ?? '', $symbol, $symbols);
        } else {
            $target = self::resolveClassName($className, $symbol, $symbols);
        }
        if ($target === null) return;

        if (!isset($symbols[$target])) {
            if (!self::isKnownExternalType($target)) {
                $issues[] = self::issue($symbol, self::tokenLine($tokens, $index),
                    sprintf('Referenced class %s cannot be resolved', $target));
            }
            return;
        }

        $member = self::nextMeaningful($tokens, $index + 1);
        if ($member === null || !is_array($tokens[$member]) || $tokens[$member][0] !== T_STRING) return;
        $after = self::nextMeaningful($tokens, $member + 1);
        if ($after !== null && ($tokens[$after] ?? null) === '(') {
            self::validateMethodCall($issues, $symbol, $symbols, $target, $tokens[$member][1], true, $after, $tokens);
        }
    }

    private static function checkNewReference(
        array &$issues, array $symbol, array $symbols, array $tokens, int $index
    ): void {
        $nameIndex = self::nextNameToken($tokens, $index + 1);
        if ($nameIndex === null) return;

        $target = self::resolveClassName(
            self::tokensName($tokens, $index + 1, $nameIndex), $symbol, $symbols
        );
        if ($target === null) return;

        if (!isset($symbols[$target])) {
            if (!self::isKnownExternalType($target)) {
                $issues[] = self::issue($symbol, self::tokenLine($tokens, $nameIndex),
                    sprintf('Referenced class %s cannot be resolved', $target));
            }
            return;
        }

        $open = self::nextMeaningful($tokens, $nameIndex + 1);
        if ($open !== null && ($tokens[$open] ?? null) === '(') {
            self::validateMethodCall($issues, $symbol, $symbols, $target, '__construct', false, $open, $tokens);
        }
    }

    private static function validateMethodCall(array &$issues, array $source, array $symbols, string $target, string $method, bool $static, int $open, array $tokens): void
    {
        $definition = self::findMethod($target, $method, $symbols);
        if ($definition === null) {
            self::reportMissingMethod($issues, $source, $symbols, $target, $method, $open, $tokens);
            return;
        }

        self::validateMethodStaticness($issues, $source, $target, $method, $static, $definition, $open, $tokens);
        self::validateMethodArguments($issues, $source, $target, $method, $definition, $open, $tokens);
    }

    private static function reportMissingMethod(
        array &$issues,
        array $source,
        array $symbols,
        string $target,
        string $method,
        int $open,
        array $tokens
    ): void {
        if ($method === '__construct' && empty($symbols[$target]['methods'])) return;

        $issues[] = self::issue(
            $source,
            self::tokenLine($tokens, $open),
            sprintf('%s::%s() does not exist', $target, $method)
        );
    }

    private static function validateMethodStaticness(
        array &$issues,
        array $source,
        string $target,
        string $method,
        bool $static,
        array $definition,
        int $open,
        array $tokens
    ): void {
        if ($static && !$definition['static'] && $method !== '__construct') {
            $issues[] = self::issue(
                $source,
                self::tokenLine($tokens, $open),
                sprintf('%s::%s() is an instance method, not static', $target, $method)
            );
        }
    }

    private static function validateMethodArguments(
        array &$issues,
        array $source,
        string $target,
        string $method,
        array $definition,
        int $open,
        array $tokens
    ): void {
        $close = self::matchingParen($tokens, $open);
        if ($close === null) return;

        $args = self::argumentCount(array_slice($tokens, $open + 1, $close - $open - 1));
        $params = $definition['params'];
        $required = count(array_filter(
            $params,
            static fn(array $p): bool => !$p['optional'] && !$p['variadic']
        ));
        $maximum = count(array_filter(
            $params,
            static fn(array $p): bool => !$p['variadic']
        ));
        $hasVariadic = (bool) array_filter(
            $params,
            static fn(array $p): bool => $p['variadic']
        );

        if ($args < $required || (!$hasVariadic && $args > $maximum)) {
            $expected = $hasVariadic ? $required . '+' : $required . '-' . $maximum;
            $issues[] = self::issue(
                $source,
                self::tokenLine($tokens, $open),
                sprintf(
                    '%s::%s() receives %d argument(s); expected %s',
                    $target,
                    $method,
                    $args,
                    $expected
                )
            );
        }
    }

    private static function validateProperty(array &$issues, array $source, array $symbols, string $target, string $property, int $line): void
    {
        if (self::findProperty($target, $property, $symbols) === null) {
            $issues[] = self::issue($source, $line, sprintf('%s::$%s does not exist', $target, $property));
        }
    }

    private static function checkInheritanceContracts(array $symbol, array $symbols): array
    {
        $issues = [];
        $parents = [];
        if (!empty($symbol['extends'])) {
            $parent = self::resolveClassName($symbol['extends'], $symbol, $symbols);
            if ($parent !== null && isset($symbols[$parent])) $parents[] = $parent;
        }
        foreach ($symbol['implements'] as $interface) {
            $resolved = self::resolveClassName($interface, $symbol, $symbols);
            if ($resolved !== null && isset($symbols[$resolved])) $parents[] = $resolved;
        }

        foreach ($parents as $parent) {
            foreach (self::allMethods($parent, $symbols) as $name => $required) {
                $child = $symbol['methods'][$name] ?? null;
                if ($symbol['kind'] === 'class' && $required['abstract'] && $child === null) {
                    $issues[] = self::issue($symbol, $symbol['line'], sprintf('%s must implement %s::%s()', $symbol['fqcn'], $parent, $name));
                    continue;
                }
                if ($child === null) continue;
                if ($required['static'] !== $child['static']) {
                    $issues[] = self::issue($symbol, $child['line'], sprintf('%s::%s() does not match inherited static/instance contract', $symbol['fqcn'], $name));
                }
                if (self::visibilityRank($child['visibility']) < self::visibilityRank($required['visibility'])) {
                    $issues[] = self::issue($symbol, $child['line'], sprintf('%s::%s() reduces visibility of inherited method', $symbol['fqcn'], $name));
                }
                $requiredCount = count($required['params']);
                $childCount = count($child['params']);
                if ($childCount < $requiredCount) {
                    $issues[] = self::issue($symbol, $child['line'], sprintf('%s::%s() declares %d parameter(s); inherited contract has %d', $symbol['fqcn'], $name, $childCount, $requiredCount));
                }
                if (($required['returnType'] ?? null) !== null && ($child['returnType'] ?? null) !== null && strtolower($required['returnType']) !== strtolower($child['returnType'])) {
                    $issues[] = self::issue($symbol, $child['line'], sprintf('%s::%s() return type %s differs from inherited %s', $symbol['fqcn'], $name, $child['returnType'], $required['returnType']));
                }
            }
        }

        return $issues;
    }

    private static function checkDuplicateSymbols(array $symbols): array
    {
        $byName = [];
        foreach ($symbols as $fqcn => $symbol) {
            $byName[$fqcn][] = $symbol['file'];
        }
        $issues = [];
        foreach ($byName as $fqcn => $files) {
            if (count(array_unique($files)) <= 1) continue;
            foreach (array_unique($files) as $file) {
                $issues[] = [
                    'file' => $file,
                    'line' => 1,
                    'severity' => 'ERROR',
                    'message' => sprintf('Duplicate class symbol %s also declared in %s', $fqcn, implode(', ', array_diff($files, [$file]))),
                ];
            }
        }
        return $issues;
    }

    private static function allMethods(string $fqcn, array $symbols, array &$seen = []): array
    {
        if (isset($seen[$fqcn])) return [];
        $seen[$fqcn] = true;
        if (!isset($symbols[$fqcn])) return [];
        $symbol = $symbols[$fqcn];
        $methods = $symbol['methods'];
        if (!empty($symbol['extends'])) {
            $parent = self::resolveClassName($symbol['extends'], $symbol, $symbols);
            if ($parent !== null) $methods = array_merge(self::allMethods($parent, $symbols, $seen), $methods);
        }
        return $methods;
    }

    private static function findMethod(string $fqcn, string $method, array $symbols, array &$seen = []): ?array
    {
        $methodLower = strtolower($method);
        foreach (self::allMethods($fqcn, $symbols, $seen) as $name => $definition) {
            if (strtolower($name) === $methodLower) return $definition;
        }

        // PHP supplies an implicit zero-argument constructor when a class has
        // neither its own constructor nor an inherited one. For classes extending
        // external parents (for example Exception), the inherited signature is not
        // statically known here, so leave it unknown rather than inventing a contract.
        if ($methodLower === '__construct' && isset($symbols[$fqcn])) {
            $parent = $symbols[$fqcn]['extends'] ?? null;
            if ($parent !== null && self::hasKnownExternalParent($fqcn, $symbols)) {
                return null;
            }

            return [
                'name' => '__construct',
                'visibility' => 'public',
                'static' => false,
                'abstract' => false,
                'final' => false,
                'params' => [],
                'returnType' => null,
                'line' => (int) ($symbols[$fqcn]['line'] ?? 1),
            ];
        }

        if (isset($symbols[$fqcn]) && ($symbols[$fqcn]['kind'] ?? '') === 'enum') {
            if ($methodLower === 'cases') {
                return [
                    'name' => 'cases', 'visibility' => 'public', 'static' => true,
                    'abstract' => false, 'final' => false, 'params' => [],
                    'returnType' => 'array', 'line' => (int) ($symbols[$fqcn]['line'] ?? 1),
                ];
            }
            if (in_array($methodLower, ['from', 'tryfrom'], true)) {
                return [
                    'name' => $methodLower, 'visibility' => 'public', 'static' => true,
                    'abstract' => false, 'final' => false,
                    'params' => [['name' => 'value', 'type' => 'mixed', 'variadic' => false, 'optional' => false]],
                    'returnType' => $methodLower === 'tryfrom' ? '?' . ($symbols[$fqcn]['name'] ?? '') : ($symbols[$fqcn]['name'] ?? ''),
                    'line' => (int) ($symbols[$fqcn]['line'] ?? 1),
                ];
            }
        }

        return null;
    }

    private static function findProperty(string $fqcn, string $property, array $symbols, array &$seen = []): ?array
    {
        if (isset($seen[$fqcn])) return null;
        $seen[$fqcn] = true;
        if (!isset($symbols[$fqcn])) return null;
        if (isset($symbols[$fqcn]['properties'][$property])) return $symbols[$fqcn]['properties'][$property];
        $parent = $symbols[$fqcn]['extends'] ?? null;
        if ($parent !== null) {
            $resolved = self::resolveClassName($parent, $symbols[$fqcn], $symbols);
            if ($resolved !== null) return self::findProperty($resolved, $property, $symbols, $seen);
        }
        return null;
    }

    private static function parseUseVariables(array $tokens): array
    {
        $names = [];
        foreach ($tokens as $token) {
            if (is_array($token) && $token[0] === T_VARIABLE) {
                $names[] = substr($token[1], 1);
            }
        }
        return $names;
    }

    private static function parseParameterTypesWithVariables(array $tokens): array
    {
        $result = [];
        $type = null;
        foreach ($tokens as $t) {
            if (is_array($t)) {
                if (self::isTypeToken($t[0])) { $type = self::normalizeTypeText($t[1]); continue; }
                if ($t[0] === T_VARIABLE) { $result[substr($t[1], 1)] = $type; $type = null; }
            }
        }
        return array_filter($result, static fn($v) => $v !== null);
    }

    private static function hasKnownExternalParent(string $fqcn, array $symbols, array &$seen = []): bool
    {
        if (isset($seen[$fqcn]) || !isset($symbols[$fqcn])) return false;
        $seen[$fqcn] = true;
        $parent = $symbols[$fqcn]['extends'] ?? null;
        if ($parent === null) return false;
        $resolved = self::resolveClassName((string) $parent, $symbols[$fqcn], $symbols);
        if ($resolved !== null && self::isKnownExternalType($resolved)) return true;
        return $resolved !== null && isset($symbols[$resolved])
            ? self::hasKnownExternalParent($resolved, $symbols, $seen)
            : false;
    }

    private static function isKnownExternalType(string $type): bool
    {
        $type = ltrim($type, '\\');
        $builtins = [
            'array', 'string', 'int', 'float', 'bool', 'mixed', 'object', 'callable',
            'iterable', 'void', 'never', 'null', 'true', 'false', 'self', 'static', 'parent',
            'stdClass', 'Exception', 'Error', 'Throwable', 'RuntimeException', 'LogicException',
            'InvalidArgumentException', 'UnexpectedValueException', 'OutOfBoundsException',
            'PDO', 'PDOException', 'DateTime', 'DateTimeImmutable', 'DateTimeInterface',
            'JsonException', 'Closure', 'Generator', 'Traversable', 'Iterator', 'Countable',
        ];
        if (in_array($type, $builtins, true) || in_array(strtolower($type), array_map('strtolower', $builtins), true)) {
            return true;
        }
        return class_exists($type, false) || interface_exists($type, false) || trait_exists($type, false);
    }

    private static function resolveClassName(string $raw, array $source, array $symbols): ?string
    {
        $raw = trim($raw, " \t\r\n\\");
        if ($raw === '') return null;
        if (in_array(strtolower($raw), ['self', 'static', 'parent'], true)) return null;
        $raw = preg_replace('/\s+/', '', $raw) ?? $raw;

        if (str_starts_with($raw, '\\')) return ltrim($raw, '\\');

        $imports = $source['imports'] ?? [];
        $parts = explode('\\', $raw);
        $first = strtolower($parts[0]);
        if (isset($imports[$first])) {
            $suffix = count($parts) > 1 ? '\\' . implode('\\', array_slice($parts, 1)) : '';
            return rtrim($imports[$first], '\\') . $suffix;
        }

        $namespace = $source['namespace'] ?? '';
        $candidate = self::fqcn($namespace, $raw);
        if (isset($symbols[$candidate])) return $candidate;
        if (isset($symbols[$raw])) return $raw;
        if (self::isKnownExternalType($raw)) return $raw;

        foreach ($symbols as $fqcn => $_) {
            if (strcasecmp($fqcn, $raw) === 0 || strcasecmp(substr($fqcn, strrpos($fqcn, '\\') + 1), $raw) === 0) return $fqcn;
        }

        return $candidate;
    }

    private static function resolveType(?string $type, array $source): ?string
    {
        if ($type === null) return null;
        $type = trim($type, " \t\r\n|?");
        if ($type === '' || in_array(strtolower($type), ['mixed','array','string','int','float','bool','object','callable','iterable','void','never','null','false','true'], true)) return null;

        $parts = preg_split('/[|&]/', $type) ?: [];
        $type = trim((string) ($parts[0] ?? ''), '\\');
        if ($type === '') return null;

        $imports = $source['imports'] ?? [];
        $nameParts = explode('\\', $type);
        $first = strtolower($nameParts[0]);
        if (isset($imports[$first])) {
            $suffix = count($nameParts) > 1 ? '\\' . implode('\\', array_slice($nameParts, 1)) : '';
            return rtrim($imports[$first], '\\') . $suffix;
        }

        if (self::isKnownExternalType($type)) return $type;
        return self::fqcn($source['namespace'] ?? '', $type);
    }

    private static function fqcn(string $namespace, string $name): string
    {
        return $namespace === '' ? ltrim($name, '\\') : trim($namespace, '\\') . '\\' . ltrim($name, '\\');
    }

    private static function tokenLine(array $tokens, int $index): int
    {
        for ($i = $index; $i >= 0; $i--) {
            if (is_array($tokens[$i]) && isset($tokens[$i][2])) {
                return max(1, (int) $tokens[$i][2]);
            }
        }
        return 1;
    }

    private static function issue(array $source, int $line, string $message): array
    {
        return [
            'file' => (string) ($source['file'] ?? ''),
            'line' => max(1, $line),
            'severity' => 'ERROR',
            'message' => $message,
        ];
    }

    private static function argumentCount(array $tokens): int
    {
        if ($tokens === []) {
            return 0;
        }

        $depth = 0;
        $count = 0;
        $hasValue = false;

        foreach ($tokens as $t) {
            if ($t === '(' || $t === '[' || $t === '{') {
                $depth++;
                $hasValue = true;
                continue;
            }

            if ($t === ')' || $t === ']' || $t === '}') {
                $depth--;
                continue;
            }

            if ($depth === 0 && $t === ',') {
                if ($hasValue) {
                    $count++;
                    $hasValue = false;
                }
                continue;
            }

            if (is_array($t)) {
                if (in_array(
                    $t[0],
                    [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT],
                    true
                )) {
                    continue;
                }
            }

            $hasValue = true;
        }

        if ($hasValue) {
            $count++;
        }

        return $count;
    }

    private static function visibilityRank(string $visibility): int
    {
        return match ($visibility) { 'private' => 0, 'protected' => 1, default => 2 };
    }

    private static function isTypeToken(int $id): bool
    {
        return in_array($id, array_filter([
            T_STRING, T_NAME_QUALIFIED ?? null, T_NAME_FULLY_QUALIFIED ?? null,
            defined('T_NAME_RELATIVE') ? T_NAME_RELATIVE : null,
            defined('T_NULL') ? T_NULL : null,
        ], static fn($v) => $v !== null), true);
    }

    private static function normalizeTypeText(string $text): string
    {
        return trim(preg_replace('/\s+/', '', $text) ?? $text);
    }

    private static function nextToken(array $tokens, int $start, int $type): ?int
    {
        for ($i = $start, $count = count($tokens); $i < $count; $i++) {
            if (is_array($tokens[$i]) && $tokens[$i][0] === T_WHITESPACE) continue;
            if (is_array($tokens[$i]) && $tokens[$i][0] === $type) return $i;
            return null;
        }
        return null;
    }

    private static function nextMeaningful(array $tokens, int $start): ?int
    {
        for ($i = $start, $count = count($tokens); $i < $count; $i++) {
            if (is_array($tokens[$i]) && in_array($tokens[$i][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) continue;
            return $i;
        }
        return null;
    }

    private static function nextNameToken(array $tokens, int $start): ?int
    {
        for ($i = $start, $count = count($tokens); $i < $count; $i++) {
            $t = $tokens[$i];
            if (is_array($t) && in_array($t[0], [T_WHITESPACE, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, defined('T_NAME_RELATIVE') ? T_NAME_RELATIVE : -1], true)) {
                if (in_array($t[0], [T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, defined('T_NAME_RELATIVE') ? T_NAME_RELATIVE : -1], true)) return $i;
                continue;
            }
            if (is_array($t) && $t[0] === T_STRING) return $i;
            return null;
        }
        return null;
    }

    private static function findToken(array $tokens, int $start, string $needle): ?int
    {
        for ($i = $start, $count = count($tokens); $i < $count; $i++) if ($tokens[$i] === $needle) return $i;
        return null;
    }

    private static function matchingBrace(array $tokens, int $open): ?int
    {
        return self::matching($tokens, $open, '{', '}');
    }
    private static function matchingParen(array $tokens, int $open): ?int
    {
        return self::matching($tokens, $open, '(', ')');
    }
    private static function matching(array $tokens, int $open, string $left, string $right): ?int
    {
        $depth = 0;
        $interpolationDepth = 0;

        for ($i = $open, $count = count($tokens); $i < $count; $i++) {
            $token = $tokens[$i];

            if ($left === '{' && is_array($token) && $token[0] === T_CURLY_OPEN) {
                $interpolationDepth++;
                continue;
            }

            if ($left === '{' && $token === '}' && $interpolationDepth > 0) {
                $interpolationDepth--;
                continue;
            }

            if ($token === $left) {
                $depth++;
                continue;
            }

            if ($token === $right) {
                $depth--;
                if ($depth === 0) return $i;
            }
        }

        return null;
    }

    private static function tokensText(array $tokens, int $start, int $end): string
    {
        $text = '';
        for ($i = $start; $i <= $end; $i++) $text .= is_array($tokens[$i]) ? $tokens[$i][1] : $tokens[$i];
        return $text;
    }

    private static function tokensName(array $tokens, int $start, int $end): string
    {
        return trim(self::tokensText($tokens, $start, $end));
    }

    private static function readNameStatement(array $tokens, int $start): array
    {
        $name = '';
        for ($i = $start, $count = count($tokens); $i < $count; $i++) {
            if ($tokens[$i] === ';' || $tokens[$i] === '{') return [$name, $i];
            if (is_array($tokens[$i]) && $tokens[$i][0] === T_WHITESPACE) continue;
            $name .= is_array($tokens[$i]) ? $tokens[$i][1] : $tokens[$i];
        }
        return [$name, $start];
    }

    private static function readImports(array $tokens, int $start): array
    {
        $text = '';
        $end = $start;
        for ($i = $start, $count = count($tokens); $i < $count; $i++) {
            $end = $i;
            if ($tokens[$i] === ';') break;
            $text .= is_array($tokens[$i]) ? $tokens[$i][1] : $tokens[$i];
        }
        $imports = [];
        foreach (preg_split('/,/', trim($text)) ?: [] as $part) {
            $part = trim($part);
            if ($part === '' || str_contains($part, '{')) continue;
            if (preg_match('/^(.+?)(?:\s+as\s+([A-Za-z_][A-Za-z0-9_]*))?$/i', $part, $m)) {
                $target = trim($m[1], " \t\r\n\\");
                $alias = $m[2] ?? substr($target, strrpos($target, '\\') + 1);
                $imports[$alias] = $target;
            }
        }
        return [$imports, $end];
    }

    private static function isAnonymousClass(array $tokens, int $index): bool
    {
        $prev = self::nextMeaningfulBackward($tokens, $index - 1);
        return $prev !== null && is_array($tokens[$prev]) && $tokens[$prev][0] === T_NEW;
    }

    private static function nextMeaningfulBackward(array $tokens, int $start): ?int
    {
        for ($i = $start; $i >= 0; $i--) {
            if (is_array($tokens[$i]) && in_array($tokens[$i][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) continue;
            return $i;
        }
        return null;
    }
}
