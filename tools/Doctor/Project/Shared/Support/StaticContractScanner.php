<?php

declare(strict_types=1);

namespace Tools\Doctor\Project\Shared\Support;

final class StaticContractScanner
{
    /**
     * @return array<int,array<string,mixed>>
     */
    public static function staticCalls(
        string $file,
        string $source
    ): array {
        $tokens = token_get_all($source);
        $calls = [];
        $count = count($tokens);

        for ($i = 0; $i < $count; $i++) {
            if (!is_array($tokens[$i])) {
                continue;
            }

            /*
             * PHP 8 introduces dedicated name tokens for qualified
             * names. Plain T_STRING is still required for aliases,
             * self, static, and parent.
             */
            if (!self::isClassNameToken($tokens[$i])) {
                continue;
            }

            $class = self::tokenText($tokens[$i]);

            $j = self::nextMeaningful(
                $tokens,
                $i + 1
            );

            if (
                $j === null
                || self::tokenText($tokens[$j]) !== '::'
            ) {
                continue;
            }

            /*
             * Avoid interpreting declaration syntax as a static call.
             */
            $previous = self::previousMeaningful(
                $tokens,
                $i - 1
            );

            if (
                $previous !== null
                && is_array($tokens[$previous])
                && in_array(
                    $tokens[$previous][0],
                    [
                        T_CLASS,
                        T_INTERFACE,
                        T_TRAIT,
                        T_ENUM,
                    ],
                    true
                )
            ) {
                continue;
            }

            $j = self::nextMeaningful(
                $tokens,
                $j + 1
            );

            if (
                $j === null
                || !is_array($tokens[$j])
                || !self::isMethodNameToken($tokens[$j])
            ) {
                continue;
            }

            $method = self::tokenText($tokens[$j]);

            $open = self::nextMeaningful(
                $tokens,
                $j + 1
            );

            if (
                $open === null
                || self::tokenText($tokens[$open]) !== '('
            ) {
                continue;
            }

            [$arguments, $end] =
                self::collectArguments(
                    $tokens,
                    $open
                );

            $calls[] = [
                'file' => $file,
                'class' => $class,
                'method' => $method,
                'arguments' => $arguments,
                'line' =>
                    is_array($tokens[$i])
                        ? $tokens[$i][2]
                        : 0,
            ];

            $i = $end;
        }

        return $calls;
    }

    /**
     * @return array{0:int,1:int}
     */
    private static function collectArguments(
        array $tokens,
        int $open
    ): array {
        $depth = 0;
        $commas = 0;
        $hasContent = false;
        $count = count($tokens);

        for ($i = $open; $i < $count; $i++) {
            $text = self::tokenText($tokens[$i]);

            if (
                $text === '('
                || $text === '['
                || $text === '{'
            ) {
                $depth++;
                continue;
            }

            if (
                $text === ')'
                || $text === ']'
                || $text === '}'
            ) {
                $depth--;

                if (
                    $depth === 0
                    && $text === ')'
                ) {
                    return [
                        $hasContent
                            ? $commas + 1
                            : 0,
                        $i,
                    ];
                }

                continue;
            }

            if (
                $depth === 1
                && $text === ','
            ) {
                /*
                 * A trailing comma is syntax, not another argument.
                 *
                 * Example:
                 *
                 * self::make(
                 *     severity: 'WARNING',
                 *     evidence: $evidence,
                 * );
                 *
                 * The final comma must not increase the argument count.
                 */
                $next = self::nextMeaningful(
                    $tokens,
                    $i + 1
                );

                if (
                    $next !== null
                    && self::tokenText($tokens[$next]) === ')'
                ) {
                    continue;
                }

                $commas++;
                continue;
            }

            if (
                $depth === 1
                && is_array($tokens[$i])
                && !in_array(
                    $tokens[$i][0],
                    [
                        T_WHITESPACE,
                        T_COMMENT,
                        T_DOC_COMMENT,
                    ],
                    true
                )
            ) {
                $hasContent = true;
            }
        }

        return [0, $open];
    }

    /**
     * @param array<int,mixed> $tokens
     */
    private static function nextMeaningful(
        array $tokens,
        int $start
    ): ?int {
        $count = count($tokens);

        for ($i = $start; $i < $count; $i++) {
            if (!self::ignorable($tokens[$i])) {
                return $i;
            }
        }

        return null;
    }

    /**
     * @param array<int,mixed> $tokens
     */
    private static function previousMeaningful(
        array $tokens,
        int $start
    ): ?int {
        for ($i = $start; $i >= 0; $i--) {
            if (!self::ignorable($tokens[$i])) {
                return $i;
            }
        }

        return null;
    }

    /**
     * @param array<int,mixed> $token
     */
    private static function isClassNameToken(
        array $token
    ): bool {
        return in_array(
            $token[0],
            [
                T_STRING,
                T_NAME_QUALIFIED,
                T_NAME_FULLY_QUALIFIED,
                T_NAME_RELATIVE,
            ],
            true
        );
    }

    /**
     * @param array<int,mixed> $token
     */
    private static function isMethodNameToken(
        array $token
    ): bool {
        return $token[0] === T_STRING;
    }

    public static function methodExists(
        string $class,
        string $method
    ): bool {
        return class_exists($class)
            && method_exists($class, $method);
    }

    /**
     * Resolve a method against the project's source map without
     * requiring the application runtime to be booted.
     *
     * @param array<string,string> $classMap
     */
    public static function methodExistsInProject(
        string $class,
        string $method,
        array $classMap
    ): bool {
        $visited = [];

        return self::methodExistsInProjectRecursive(
            $class,
            $method,
            $classMap,
            $visited
        );
    }

    /**
     * @param array<string,string> $classMap
     * @param array<string,bool> $visited
     */
    private static function methodExistsInProjectRecursive(
        string $class,
        string $method,
        array $classMap,
        array &$visited
    ): bool {
        if ($class === '') {
            return false;
        }

        if (isset($visited[$class])) {
            return false;
        }

        $visited[$class] = true;

        /*
         * Runtime reflection is useful when the class is already loaded,
         * but source resolution remains authoritative for Doctor scans.
         */
        if (
            class_exists($class)
            && method_exists($class, $method)
        ) {
            return true;
        }

        $file = $classMap[$class] ?? null;

        if (
            $file === null
            || !is_file($file)
        ) {
            return false;
        }

        $source = @file_get_contents($file);

        if ($source === false) {
            return false;
        }

        $declaration =
            self::classDeclaration(
                $source,
                $class
            );

        if (
            $declaration !== null
            && in_array(
                $method,
                $declaration['methods'],
                true
            )
        ) {
            return true;
        }

        if (
            $declaration === null
            || $declaration['parent'] === null
        ) {
            return false;
        }

        $namespace =
            self::namespace($source);

        $imports =
            self::imports($source);

        $parent =
            self::resolveClass(
                $declaration['parent'],
                $namespace,
                $imports,
                $class,
                $classMap
            );

        if ($parent === null) {
            return false;
        }

        return self::methodExistsInProjectRecursive(
            $parent,
            $method,
            $classMap,
            $visited
        );
    }

    private static function classNamesMatch(
        string $declaredName,
        string $requestedClass
    ): bool {
        $requestedClass = ltrim(
            trim($requestedClass),
            '\\'
        );

        if ($requestedClass === '') {
            return false;
        }

        $parts = explode(
            '\\',
            $requestedClass
        );

        $shortName = end($parts);

        return $declaredName === $requestedClass
            || $declaredName === $shortName;
    }

    /**
     * @return array{
     *     name:string,
     *     parent:?string,
     *     methods:string[]
     * }|null
     */
    public static function classDeclaration(
        string $source,
        ?string $requestedClass = null
    ): ?array {
        $tokens = token_get_all($source);
        $count = count($tokens);

        for ($i = 0; $i < $count; $i++) {
            if (
                !is_array($tokens[$i])
                || !in_array(
                    $tokens[$i][0],
                    [
                        T_CLASS,
                        T_INTERFACE,
                        T_TRAIT,
                    ],
                    true
                )
            ) {
                continue;
            }

            $previous =
                self::previousMeaningful(
                    $tokens,
                    $i - 1
                );

            /*
             * Anonymous classes:
             *
             * new class extends Foo {}
             *
             * are not project class declarations.
             */
            if (
                $previous !== null
                && self::tokenText($tokens[$previous]) === 'new'
            ) {
                continue;
            }

            $nameIndex =
                self::nextMeaningful(
                    $tokens,
                    $i + 1
                );

            if (
                $nameIndex === null
                || !is_array($tokens[$nameIndex])
                || $tokens[$nameIndex][0] !== T_STRING
            ) {
                continue;
            }

            $name =
                self::tokenText(
                    $tokens[$nameIndex]
                );

            /*
             * A source file may legally contain multiple classes.
             * When the caller knows the requested class, skip unrelated
             * declarations instead of returning the first class in the file.
             */
            if (
                $requestedClass !== null
                && !self::classNamesMatch(
                    $name,
                    $requestedClass
                )
            ) {
                continue;
            }

            $parent = null;
            $methods = [];

            $j =
                self::nextMeaningful(
                    $tokens,
                    $nameIndex + 1
                );

            while (
                $j !== null
                && $j < $count
            ) {
                $text =
                    self::tokenText(
                        $tokens[$j]
                    );

                if ($text === '{') {
                    break;
                }

                if (
                    is_array($tokens[$j])
                    && $tokens[$j][0] === T_EXTENDS
                ) {
                    $parentIndex =
                        self::nextMeaningful(
                            $tokens,
                            $j + 1
                        );

                    if (
                        $parentIndex !== null
                    ) {
                        $parts = [];

                        $k = $parentIndex;

                        while ($k < $count) {
                            $part =
                                self::tokenText(
                                    $tokens[$k]
                                );

                            if (
                                $part === '{'
                                || $part === 'implements'
                            ) {
                                break;
                            }

                            if (
                                $part === ','
                                || self::ignorable(
                                    $tokens[$k]
                                )
                            ) {
                                break;
                            }

                            $parts[] = $part;
                            $k++;
                        }

                        $parent =
                            trim(
                                implode('', $parts)
                            );
                    }
                }

                $j =
                    self::nextMeaningful(
                        $tokens,
                        $j + 1
                    );
            }

            if ($j === null) {
                return [
                    'name' => $name,
                    'parent' => $parent,
                    'methods' => [],
                ];
            }

            $depth = 0;

            for (
                $k = $j;
                $k < $count;
                $k++
            ) {
                $token = $tokens[$k];
                $text =
                    self::tokenText($token);

                if ($text === '{') {
                    $depth++;
                    continue;
                }

                if ($text === '}') {
                    $depth--;

                    if ($depth === 0) {
                        break;
                    }

                    continue;
                }

                if (
                    $depth !== 1
                    || !is_array($token)
                    || $token[0] !== T_FUNCTION
                ) {
                    continue;
                }

                $methodIndex =
                    self::nextMeaningful(
                        $tokens,
                        $k + 1
                    );

                /*
                 * PHP permits an optional ampersand:
                 *
                 * function &foo()
                 */
                if (
                    $methodIndex !== null
                    && self::tokenText(
                        $tokens[$methodIndex]
                    ) === '&'
                ) {
                    $methodIndex =
                        self::nextMeaningful(
                            $tokens,
                            $methodIndex + 1
                        );
                }

                if (
                    $methodIndex !== null
                    && is_array(
                        $tokens[$methodIndex]
                    )
                    && $tokens[$methodIndex][0]
                        === T_STRING
                ) {
                    $methods[] =
                        self::tokenText(
                            $tokens[$methodIndex]
                        );
                }
            }

            return [
                'name' => $name,
                'parent' => $parent,
                'methods' => array_values(
                    array_unique($methods)
                ),
            ];
        }

        return null;
    }

    /**
     * Return source-level parameter information for a project method.
     *
     * @return array{
     *     required:int,
     *     maximum:int
     * }|null
     */
    public static function methodSignatureInProject(
        string $class,
        string $method,
        array $classMap
    ): ?array {
        $visited = [];

        return self::methodSignatureRecursive(
            $class,
            $method,
            $classMap,
            $visited
        );
    }

    /**
     * @param array<string,string> $classMap
     * @param array<string,bool> $visited
     * @return array{
     *     required:int,
     *     maximum:int
     * }|null
     */
    private static function methodSignatureRecursive(
        string $class,
        string $method,
        array $classMap,
        array &$visited
    ): ?array {
        if (
            $class === ''
            || isset($visited[$class])
        ) {
            return null;
        }

        $visited[$class] = true;

        if (
            class_exists($class)
            && method_exists($class, $method)
        ) {
            $reflection =
                new \ReflectionMethod(
                    $class,
                    $method
                );

            return [
                'required' =>
                    $reflection
                        ->getNumberOfRequiredParameters(),
                'maximum' =>
                    $reflection
                        ->getNumberOfParameters(),
            ];
        }

        $file = $classMap[$class] ?? null;

        if (
            $file === null
            || !is_file($file)
        ) {
            return null;
        }

        $source =
            @file_get_contents($file);

        if ($source === false) {
            return null;
        }

        $signature =
            self::methodSignatureFromSource(
                $source,
                $method
            );

        if ($signature !== null) {
            return $signature;
        }

        $declaration =
            self::classDeclaration(
                $source,
                $class
            );

        if (
            $declaration === null
            || $declaration['parent'] === null
        ) {
            return null;
        }

        $parent =
            self::resolveClass(
                $declaration['parent'],
                self::namespace($source),
                self::imports($source),
                $class,
                $classMap
            );

        if ($parent === null) {
            return null;
        }

        return self::methodSignatureRecursive(
            $parent,
            $method,
            $classMap,
            $visited
        );
    }

    /**
     * @return array{
     *     required:int,
     *     maximum:int
     * }|null
     */
    private static function methodSignatureFromSource(
        string $source,
        string $method
    ): ?array {
        $tokens = token_get_all($source);
        $count = count($tokens);

        for ($i = 0; $i < $count; $i++) {
            if (
                !is_array($tokens[$i])
                || $tokens[$i][0] !== T_FUNCTION
            ) {
                continue;
            }

            $nameIndex =
                self::nextMeaningful(
                    $tokens,
                    $i + 1
                );

            if (
                $nameIndex !== null
                && self::tokenText(
                    $tokens[$nameIndex]
                ) === '&'
            ) {
                $nameIndex =
                    self::nextMeaningful(
                        $tokens,
                        $nameIndex + 1
                    );
            }

            if (
                $nameIndex === null
                || !is_array(
                    $tokens[$nameIndex]
                )
                || $tokens[$nameIndex][0] !== T_STRING
                || self::tokenText(
                    $tokens[$nameIndex]
                ) !== $method
            ) {
                continue;
            }

            $open =
                self::nextMeaningful(
                    $tokens,
                    $nameIndex + 1
                );

            if (
                $open === null
                || self::tokenText(
                    $tokens[$open]
                ) !== '('
            ) {
                continue;
            }

            return self::parameterSignature(
                $tokens,
                $open
            );
        }

        return null;
    }

    /**
     * @return array{
     *     required:int,
     *     maximum:int
     * }
     */
    private static function parameterSignature(
        array $tokens,
        int $open
    ): array {
        $depth = 0;
        $maximum = 0;
        $required = 0;
        $hasParameter = false;
        $parameterOptional = false;
        $count = count($tokens);

        for ($i = $open; $i < $count; $i++) {
            $text =
                self::tokenText(
                    $tokens[$i]
                );

            if (
                $text === '('
                || $text === '['
                || $text === '{'
            ) {
                $depth++;
                continue;
            }

            if (
                $text === ')'
                || $text === ']'
                || $text === '}'
            ) {
                if (
                    $text === ')'
                    && $depth === 1
                ) {
                    if ($hasParameter) {
                        $maximum++;

                        if (!$parameterOptional) {
                            $required++;
                        }
                    }

                    return [
                        'required' => $required,
                        'maximum' => $maximum,
                    ];
                }

                $depth--;
                continue;
            }

            if (
                $depth !== 1
            ) {
                continue;
            }

            if ($text === ',') {
                if ($hasParameter) {
                    $maximum++;

                    if (!$parameterOptional) {
                        $required++;
                    }
                }

                $hasParameter = false;
                $parameterOptional = false;
                continue;
            }

            if (
                self::ignorable(
                    $tokens[$i]
                )
            ) {
                continue;
            }

            /*
             * '=' at parameter depth indicates a default value.
             */
            if ($text === '=') {
                $parameterOptional = true;
                continue;
            }

            $hasParameter = true;
        }

        return [
            'required' => $required,
            'maximum' => $maximum,
        ];
    }

    /**
     * Resolve a class against the project's source map.
     *
     * @param array<string,string> $classMap
     * @param array<string,bool> $visited
     */
    public static function resolveClass(
        string $class,
        string $namespace,
        array $imports,
        ?string $declaredClass = null,
        array $classMap = []
    ): ?string {
        $class = trim($class);

        if ($class === '') {
            return null;
        }

        if (
            in_array(
                strtolower($class),
                ['self', 'static'],
                true
            )
        ) {
            return $declaredClass;
        }

        if (
            strtolower($class) === 'parent'
        ) {
            if ($declaredClass !== null) {
                $parent = self::resolveParentClass(
                    $declaredClass,
                    $classMap
                );

                if ($parent !== null) {
                    return $parent;
                }
            }

            return null;
        }

        if (str_starts_with($class, '\\')) {
            return ltrim($class, '\\');
        }

        if (isset($imports[$class])) {
            return $imports[$class];
        }

        /*
         * A qualified class name is already namespace-qualified.
         */
        if (str_contains($class, '\\')) {
            $candidate = ltrim($class, '\\');

            if (
                isset($classMap[$candidate])
                || class_exists($candidate)
            ) {
                return $candidate;
            }

            return $candidate;
        }

        if ($namespace !== '') {
            $candidate =
                $namespace . '\\' . $class;

            if (
                isset($classMap[$candidate])
                || class_exists($candidate)
            ) {
                return $candidate;
            }
        }

        if (
            isset($classMap[$class])
            || class_exists($class)
        ) {
            return $class;
        }

        return null;
    }

    private static function resolveParentClass(
        string $class,
        array $classMap
    ): ?string {
        if (class_exists($class)) {
            $parent = get_parent_class($class);

            if ($parent !== false) {
                return $parent;
            }
        }

        /*
         * The canonical class map gives us the file, but not the parent
         * directly. If reflection cannot resolve it, leave parent
         * resolution to the dedicated inheritance-aware checks rather
         * than inventing a relationship.
         */
        return null;
    }

    public static function namespace(
        string $source
    ): string {
        $tokens = token_get_all($source);
        $count = count($tokens);

        for ($i = 0; $i < $count; $i++) {
            if (
                !is_array($tokens[$i])
                || $tokens[$i][0] !== T_NAMESPACE
            ) {
                continue;
            }

            $parts = [];
            $i++;

            while ($i < $count) {
                $token = $tokens[$i];

                if (
                    self::tokenText($token) === ';'
                    || self::tokenText($token) === '{'
                ) {
                    break;
                }

                if (
                    self::ignorable($token)
                ) {
                    $i++;
                    continue;
                }

                $parts[] =
                    self::tokenText($token);

                $i++;
            }

            return trim(
                implode('', $parts),
                "\\ \t\n\r\0\x0B"
            );
        }

        return '';
    }

    /**
     * @return array<string,string>
     */
    public static function imports(
        string $source
    ): array {
        $imports = [];

        $tokens = token_get_all($source);
        $count = count($tokens);

        for ($i = 0; $i < $count; $i++) {
            if (
                !is_array($tokens[$i])
                || $tokens[$i][0] !== T_USE
            ) {
                continue;
            }

            /*
             * Ignore closure uses:
             *
             * function () use ($foo) {}
             */
            $previous = self::previousMeaningful(
                $tokens,
                $i - 1
            );

            if (
                $previous !== null
                && self::tokenText($tokens[$previous]) === ')'
            ) {
                continue;
            }

            $parts = [];
            $i++;

            while ($i < $count) {
                $token = $tokens[$i];
                $text = self::tokenText($token);

                if ($text === ';') {
                    break;
                }

                if (self::ignorable($token)) {
                    $i++;
                    continue;
                }

                $parts[] = $text;
                $i++;
            }

            $import = trim(
                implode('', $parts)
            );

            if (
                $import === ''
                || str_starts_with(
                    strtolower($import),
                    'function '
                )
                || str_starts_with(
                    strtolower($import),
                    'const '
                )
            ) {
                continue;
            }

            /*
             * This scanner intentionally handles ordinary class imports.
             * Grouped imports are expanded conservatively.
             */
            if (
                str_contains($import, '{')
                && str_contains($import, '}')
            ) {
                if (
                    preg_match(
                        '/^(.+)\\\\\{(.+)\}$/',
                        $import,
                        $match
                    )
                ) {
                    $prefix =
                        trim(
                            $match[1],
                            "\\ \t\n\r"
                        );

                    foreach (
                        explode(',', $match[2])
                        as $entry
                    ) {
                        self::addImport(
                            $imports,
                            $prefix,
                            trim($entry)
                        );
                    }
                }

                continue;
            }

            self::addImport(
                $imports,
                '',
                $import
            );
        }

        return $imports;
    }

    /**
     * @param array<string,string> $imports
     */
    private static function addImport(
        array &$imports,
        string $prefix,
        string $entry
    ): void {
        $entry = trim($entry);

        if ($entry === '') {
            return;
        }

        $parts = preg_split(
            '/\s+as\s+/i',
            $entry
        );

        $target = trim(
            ($prefix !== ''
                ? $prefix . '\\'
                : '')
            . $parts[0],
            '\\'
        );

        $alias =
            isset($parts[1])
                ? trim($parts[1])
                : basename(
                    str_replace(
                        '\\',
                        '/',
                        $target
                    )
                );

        if ($target !== '') {
            $imports[$alias] = $target;
        }
    }

    public static function argumentCount(
        string $arguments
    ): int {
        $arguments = trim($arguments);

        if ($arguments === '') {
            return 0;
        }

        $tokens = token_get_all(
            '<?php ' . $arguments
        );

        $depth = 0;
        $commas = 0;
        $hasContent = false;
        $count = count($tokens);

        for ($i = 0; $i < $count; $i++) {
            $text = self::tokenText($tokens[$i]);

            if (
                $text === '('
                || $text === '['
                || $text === '{'
            ) {
                $depth++;
                continue;
            }

            if (
                $text === ')'
                || $text === ']'
                || $text === '}'
            ) {
                $depth--;
                continue;
            }

            if (
                $depth === 0
                && $text === ','
            ) {
                $commas++;
                continue;
            }

            if (
                $depth === 0
                && is_array($tokens[$i])
                && !self::ignorable($tokens[$i])
            ) {
                $hasContent = true;
            }
        }

        return $hasContent
            ? $commas + 1
            : 0;
    }

    /**
     * @param mixed $token
     */
    private static function ignorable(
        mixed $token
    ): bool {
        return is_array($token)
            && in_array(
                $token[0],
                [
                    T_WHITESPACE,
                    T_COMMENT,
                    T_DOC_COMMENT,
                ],
                true
            );
    }

    /**
     * @param mixed $token
     */
    private static function tokenText(
        mixed $token
    ): string {
        return is_array($token)
            ? $token[1]
            : (string) $token;
    }
}
