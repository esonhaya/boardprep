<?php

declare(strict_types=1);

$path = 'tools/Doctor/Scanners/DependencyConsistencyScanner.php';

$text = file_get_contents($path);

if ($text === false) {
    fwrite(STDERR, "[STOP] Unable to read {$path}.\n");
    exit(1);
}

$start = strpos(
    $text,
    '    private static function checkObjectReference('
);

$end = strpos(
    $text,
    '    private static function checkStaticReference(',
    $start
);

if ($start === false || $end === false) {
    fwrite(
        STDERR,
        "[STOP] checkObjectReference boundaries not found.\n"
    );
    exit(1);
}

$new = <<<'SOURCE'
    private static function checkObjectReference(
        array &$issues,
        array $symbol,
        array $symbols,
        array $tokens,
        int $index,
        array $variables
    ): void {
        $name = substr($tokens[$index][1], 1);

        if ($name === 'this') {
            $variables['this'] = $symbol['fqcn'];
        }

        $member = self::objectReferenceMember(
            $tokens,
            $index
        );

        if ($member === null) {
            return;
        }

        $type = $variables[$name] ?? null;

        if ($type === null) {
            return;
        }

        $target = $name === 'this'
            ? $type
            : self::resolveType($type, $symbol);

        if ($target === null) {
            return;
        }

        if (!self::validateObjectReferenceTarget(
            $issues,
            $symbol,
            $symbols,
            $target,
            $tokens,
            $member
        )) {
            return;
        }

        $memberName = $tokens[$member][1];
        $after = self::nextMeaningful($tokens, $member + 1);

        if ($after !== null && ($tokens[$after] ?? null) === '(') {
            self::validateMethodCall(
                $issues,
                $symbol,
                $symbols,
                $target,
                $memberName,
                false,
                $after,
                $tokens
            );

            return;
        }

        self::validateProperty(
            $issues,
            $symbol,
            $symbols,
            $target,
            $memberName,
            $tokens[$member][2] ?? 1
        );
    }

    private static function objectReferenceMember(
        array $tokens,
        int $index
    ): ?int {
        $next = self::nextMeaningful(
            $tokens,
            $index + 1
        );

        if (
            $next === null
            || !is_array($tokens[$next])
            || $tokens[$next][0] !== T_OBJECT_OPERATOR
        ) {
            return null;
        }

        $member = self::nextMeaningful(
            $tokens,
            $next + 1
        );

        if (
            $member === null
            || !is_array($tokens[$member])
            || $tokens[$member][0] !== T_STRING
        ) {
            return null;
        }

        return $member;
    }

    private static function validateObjectReferenceTarget(
        array &$issues,
        array $symbol,
        array $symbols,
        string $target,
        array $tokens,
        int $member
    ): bool {
        if (isset($symbols[$target])) {
            return true;
        }

        if (self::isKnownExternalType($target)) {
            return false;
        }

        $issues[] = self::issue(
            $symbol,
            $tokens[$member][2] ?? 1,
            sprintf(
                'Referenced type %s cannot be resolved',
                $target
            )
        );

        return false;
    }

SOURCE;

$text =
    substr($text, 0, $start)
    . $new
    . substr($text, $end);

if (file_put_contents($path, $text) === false) {
    fwrite(STDERR, "[STOP] Unable to write {$path}.\n");
    exit(1);
}

echo "[PASS] checkObjectReference() refactored using reusable tool.\n";
