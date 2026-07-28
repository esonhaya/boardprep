<?php

declare(strict_types=1);

namespace App\Core;

use Closure;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;
use RuntimeException;
use Throwable;

class Container
{
    /**
     * @var array<string, Closure(self): mixed>
     */
    private array $bindings = [];

    /**
     * @var array<string, mixed>
     */
    private array $instances = [];

    /**
     * @var array<string, bool>
     */
    private array $resolving = [];

    public function bind(
        string $abstract,
        Closure $factory
    ): void {
        $this->bindings[$abstract] = $factory;
    }

    public function singleton(
        string $abstract,
        Closure $factory
    ): void {
        $this->bindings[$abstract] = function (
            self $container
        ) use (
            $abstract,
            $factory
        ) {
            if (!array_key_exists(
                $abstract,
                $this->instances
            )) {
                $this->instances[$abstract] = $factory(
                    $container
                );
            }

            return $this->instances[$abstract];
        };
    }

    public function has(
        string $abstract
    ): bool {
        return isset($this->bindings[$abstract])
            || class_exists($abstract);
    }

    public function get(
        string $abstract
    ): mixed {
        return $this->resolve($abstract);
    }

    private function resolve(
        string $abstract
    ): mixed {
        if (array_key_exists(
            $abstract,
            $this->instances
        )) {
            return $this->instances[$abstract];
        }

        if (isset($this->bindings[$abstract])) {
            return $this->bindings[$abstract]($this);
        }

        if (!class_exists($abstract)) {
            throw new RuntimeException(
                sprintf(
                    'Container binding [%s] does not exist.',
                    $abstract
                )
            );
        }

        return $this->build($abstract);
    }

    private function build(
        string $class
    ): object {
        if (isset($this->resolving[$class])) {
            throw new RuntimeException(
                sprintf(
                    'Circular dependency detected while resolving [%s].',
                    $class
                )
            );
        }

        $this->resolving[$class] = true;

        try {
            $reflection = new ReflectionClass($class);

            if (
                !$reflection->isInstantiable()
            ) {
                throw new RuntimeException(
                    sprintf(
                        'Class [%s] is not instantiable.',
                        $class
                    )
                );
            }

            $constructor = $reflection->getConstructor();

            if ($constructor === null) {
                return $reflection->newInstance();
            }

            $dependencies = [];

            foreach (
                $constructor->getParameters()
                as $parameter
            ) {
                $dependencies[] =
                    $this->resolveParameter(
                        $parameter
                    );
            }

            return $reflection->newInstanceArgs(
                $dependencies
            );
        } catch (Throwable $exception) {
            throw $exception;
        } finally {
            unset(
                $this->resolving[$class]
            );
        }
    }

    private function resolveParameter(
        ReflectionParameter $parameter
    ): mixed {
        $type = $parameter->getType();

        if (
            $type instanceof ReflectionNamedType
            && !$type->isBuiltin()
        ) {
            return $this->resolve(
                $type->getName()
            );
        }

        if (
            $parameter->isDefaultValueAvailable()
        ) {
            return $parameter->getDefaultValue();
        }

        throw new RuntimeException(
            sprintf(
                'Unable to resolve parameter [$%s].',
                $parameter->getName()
            )
        );
    }
}
