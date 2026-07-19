<?php

class BaseController
{
    protected static function render(
        string $view,
        array $data = []
    ): void
    {
        View::render(
            $view,
            $data
        );
    }

    protected static function session(
        string $key,
        $default = null
    )
    {
        return SessionService::get(
            $key,
            $default
        );
    }

    protected static function setSession(
        string $key,
        $value
    ): void
    {
        SessionService::set(
            $key,
            $value
        );
    }

    protected static function removeSession(
        string $key
    ): void
    {
        SessionService::remove(
            $key
        );
    }
}
