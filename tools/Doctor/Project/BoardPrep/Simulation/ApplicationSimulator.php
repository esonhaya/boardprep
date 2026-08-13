<?php

declare(strict_types=1);

namespace Tools\Doctor\Project\BoardPrep\Simulation;

use Tools\Doctor\Project\BoardPrep\Simulation\Assertions\SimulationAssertions;
use Throwable;

final class ApplicationSimulator
{
    private ?SimulationResponse $response = null;

    private SimulationResult $result;

    private SimulationContext $context;

    private array $server = [];

    private array $session = [];

    private array $cookies = [];

    private array $requestData = [];

    private string $method = 'GET';

    private string $uri = '/';

    public function __construct(
        private readonly ?string $entryPoint = null
    ) {
        $this->result = new SimulationResult();
        $this->context = new SimulationContext();

        $this->cookies['PHPSESSID'] =
            bin2hex(random_bytes(16));
    }

    public function get(
        string $uri
    ): static {
        return $this->request(
            'GET',
            $uri
        );
    }

    public function post(
        string $uri,
        array $data = []
    ): static {
        return $this->request(
            'POST',
            $uri,
            $data
        );
    }

    public function request(
        string $method,
        string $uri,
        array $data = []
    ): static {
        $this->method = strtoupper($method);
        $this->uri = $uri;
        $this->requestData = $data;

        $this->server = [
            'REQUEST_METHOD' => $this->method,
            'REQUEST_URI' => $uri,
            'QUERY_STRING' => parse_url(
                $uri,
                PHP_URL_QUERY
            ) ?? '',
        ];

        $this->response = null;

        return $this;
    }

    public function execute(): static
    {
        $entryPoint =
            $this->entryPoint
            ?? dirname(__DIR__, 5) . '/public/index.php';

        if (!is_file($entryPoint)) {
            throw new \RuntimeException(
                "Application entry point not found: {$entryPoint}"
            );
        }

        $parts = parse_url($this->uri);

        $path =
            is_string($parts['path'] ?? null)
                ? $parts['path']
                : '/';

        $query = [];

        if (isset($parts['query'])) {
            parse_str(
                (string) $parts['query'],
                $query
            );
        }

        $simulator =
            new HttpSimulator(
                $entryPoint
            );

        $result =
            $simulator->request(
                method: $this->method,
                path: $path,
                query: $query,
                post: $this->requestData,
                server: $this->server,
                cookies: $this->cookies
            );

        $this->response =
            new SimulationResponse(
                status: $result['status'],
                body: $result['stdout'],
                headers: $result['headers']
            );

        $this->context->set(
            'http',
            $result
        );

        return $this;
    }

    public function response(
        SimulationResponse $response
    ): static {
        $this->response = $response;

        return $this;
    }

    public function assertStatus(
        int $expected
    ): static {
        $this->requireResponse();

        try {
            SimulationAssertions::status(
                $this->response,
                $expected
            );

            $this->record(
                "HTTP status is {$expected}",
                true
            );
        } catch (Throwable $exception) {
            $this->record(
                "HTTP status is {$expected}",
                false,
                $exception->getMessage()
            );
        }

        return $this;
    }

    public function assertSuccessful(): static
    {
        $this->requireResponse();

        try {
            SimulationAssertions::successful(
                $this->response
            );

            $this->record(
                'Response is successful',
                true
            );
        } catch (Throwable $exception) {
            $this->record(
                'Response is successful',
                false,
                $exception->getMessage()
            );
        }

        return $this;
    }

    public function assertContains(
        string $needle
    ): static {
        $this->requireResponse();

        try {
            SimulationAssertions::contains(
                $this->response,
                $needle
            );

            $this->record(
                "Response contains \"{$needle}\"",
                true
            );
        } catch (Throwable $exception) {
            $this->record(
                "Response contains \"{$needle}\"",
                false,
                $exception->getMessage()
            );
        }

        return $this;
    }

    public function assertNotContains(
        string $needle
    ): static {
        $this->requireResponse();

        try {
            SimulationAssertions::notContains(
                $this->response,
                $needle
            );

            $this->record(
                "Response does not contain \"{$needle}\"",
                true
            );
        } catch (Throwable $exception) {
            $this->record(
                "Response does not contain \"{$needle}\"",
                false,
                $exception->getMessage()
            );
        }

        return $this;
    }

    public function context(): SimulationContext
    {
        return $this->context;
    }

    public function session(): array
    {
        return $this->session;
    }

    public function setSession(
        string $key,
        mixed $value
    ): static {
        $this->session[$key] = $value;

        return $this;
    }

    public function getSession(
        string $key,
        mixed $default = null
    ): mixed {
        return $this->session[$key] ?? $default;
    }

    public function requestData(): array
    {
        return $this->requestData;
    }

    public function server(): array
    {
        return $this->server;
    }

    public function responseData(): ?SimulationResponse
    {
        return $this->response;
    }

    public function result(): SimulationResult
    {
        return $this->result;
    }

    public function passed(): bool
    {
        return $this->result->passed();
    }

    public function reset(): static
    {
        $this->response = null;
        $this->result = new SimulationResult();
        $this->context->clear();
        $this->server = [];
        $this->requestData = [];
        $this->method = 'GET';
        $this->uri = '/';

        return $this;
    }

    private function requireResponse(): void
    {
        if ($this->response === null) {
            throw new \LogicException(
                'No simulation response is available. Execute a request first.'
            );
        }
    }

    private function record(
        string $description,
        bool $passed,
        ?string $failure = null
    ): void {
        $this->result->record(
            $description,
            $passed,
            $failure
        );
    }
}
