<?php

declare(strict_types=1);

namespace App\Foundation;

class Collection
{
    private function __construct(
        private array $items
    ) {
    }

    public static function make(
        array $items
    ): self {

        return new self(
            $items
        );

    }

    public function all(): array
    {

        return $this->items;

    }

    public function map(
        callable $callback
    ): self {

        return new self(

            array_map(
                $callback,
                $this->items
            )

        );

    }

    public function filter(
        ?callable $callback = null
    ): self {

        return new self(

            array_values(

                array_filter(
                    $this->items,
                    $callback
                )

            )

        );

    }

    public function where(
        string $key,
        mixed $value
    ): self {

        return $this->filter(

            static fn(
                array $item
            ): bool =>

                ($item[$key] ?? null)
                ===
                $value

        );

    }

    public function pluck(
        string $key
    ): self {

        return new self(

            array_map(

                static fn(
                    array $item
                ) =>

                    $item[$key]
                    ?? null,

                $this->items

            )

        );

    }

    public function sortBy(
        string $key
    ): self {

        $items =
            $this->items;

        usort(

            $items,

            static function (
                array $left,
                array $right
            ) use (
                $key
            ): int {

                return ($left[$key] ?? "")
                    <=>
                    ($right[$key] ?? "");

            }

        );

        return new self(
            $items
        );

    }

    public function first(): mixed
    {

        return $this->items[0]
            ?? null;

    }

    public function count(): int
    {

        return count(
            $this->items
        );

    }

    public function isEmpty(): bool
    {

        return empty(
            $this->items
        );

    }

    public function values(): self
    {

        return new self(

            array_values(
                $this->items
            )

        );

    }
}
