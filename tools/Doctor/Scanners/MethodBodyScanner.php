<?php

declare(strict_types=1);

namespace Tools\Doctor\Scanners;

final class MethodBodyScanner
{
    /**
     * @return array<int,array{
     *     hash:string,
     *     body:string
     * }>
     */
    public static function scan(
        string $contents
    ): array {

        preg_match_all(

            '/function\s+[A-Za-z0-9_]+\s*\([^)]*\)\s*(?::\s*[^{]+)?\{([\s\S]*?)^\}/m',

            $contents,

            $matches,

            PREG_SET_ORDER

        );

        $methods = [];

        foreach ($matches as $match) {

            $body =
                trim($match[1]);

            $normalized =
                preg_replace(
                    '/\s+/',
                    '',
                    $body
                );

            $methods[] = [

                "hash" =>
                    md5(
                        $normalized
                    ),

                "body" =>
                    $body,

            ];

        }

        return $methods;

    }
}
