<?php

class Clock
{
    /**
     * Current UTC timestamp.
     */
    public static function now(): string
    {
        return gmdate('c');
    }
}
