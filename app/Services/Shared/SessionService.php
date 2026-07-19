<?php

class SessionService
{
    public static function get(
        string $key,
        $default = null
    )
    {
        return
            $_SESSION[$key]
            ?? $default;
    }

    public static function set(
        string $key,
        $value
    ): void
    {
        $_SESSION[$key] = $value;
    }

    public static function has(
        string $key
    ): bool
    {
        return isset(
            $_SESSION[$key]
        );
    }

    public static function remove(
        string $key
    ): void
    {
        unset(
            $_SESSION[$key]
        );
    }

    public static function clear(): void
    {
        session_unset();
    }

    public static function push(
        string $key,
        $value
    ): void
    {
        $items =
            self::get(
                $key,
                []
            );

        $items[] = $value;

        self::set(
            $key,
            $items
        );
    }
}
