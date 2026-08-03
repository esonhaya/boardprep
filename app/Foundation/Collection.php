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

        return new self($items);

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

            static fn(array $item): bool =>
                ($item[$key] ?? null) === $value

        );

    }

    public function reject(
        callable $callback
    ): self {

        return $this->filter(

            static fn($item): bool =>
                !$callback($item)

        );

    }

    public function pluck(
        string $key
    ): self {

        return new self(

            array_map(

                static fn(array $item) =>
                    $item[$key] ?? null,

                $this->items

            )

        );

    }

    public function sortBy(
        string $key
    ): self {

        $items = $this->items;

        usort(

            $items,

            static fn(array $a, array $b): int =>

                ($a[$key] ?? "")
                <=>
                ($b[$key] ?? "")

        );

        return new self($items);

    }

    public function each(
        callable $callback
    ): self {

        foreach ($this->items as $key => $item) {

            $callback(
                $item,
                $key
            );

        }

        return $this;

    }

    public function first(): mixed
    {

        return $this->items[0] ?? null;

    }

    public function last(): mixed
    {

        return empty($this->items)
            ? null
            : $this->items[array_key_last($this->items)];

    }

    public function count(): int
    {

        return count($this->items);

    }

    public function isEmpty(): bool
    {

        return empty($this->items);

    }

    public function values(): self
    {

        return new self(

            array_values($this->items)

        );

    }

    public function toArray(): array
    {

        return $this->items;

    }
}
