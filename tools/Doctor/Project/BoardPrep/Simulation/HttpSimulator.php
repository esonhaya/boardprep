<?php

declare(strict_types=1);

namespace Tools\Doctor\Project\BoardPrep\Simulation;

final class HttpSimulator
{
    public function __construct(
        private string $entryPoint
    ) {
    }

    /**
     * Execute a simulated HTTP request against the application entry point.
     *
     * @param array<string,string|int|float|bool|null> $query
     * @param array<string,string|int|float|bool|null> $post
     * @param array<string,string> $server
     * @param array<string,string> $cookies
     *
     * @return array{
     *     exitCode:int,
     *     output:string,
     *     stdout:string,
     *     stderr:string,
     *     duration:float,
     *     status:int,
     *     headers:array<int,string>,
     *     location:string|null,
     *     success:bool
     * }
     */
    public function request(
        string $method = 'GET',
        string $path = '/',
        array $query = [],
        array $post = [],
        array $server = [],
        array $cookies = []
    ): array {
        $payload = $this->createPayload(
            $method,
            $path,
            $query,
            $post,
            $server,
            $cookies
        );

        $bootstrap = $this->createBootstrap();

        $command = $this->createCommand($bootstrap);

        $start = microtime(true);

        [$stdout, $stderr, $exitCode] =
            $this->execute(
                $payload,
                $command
            );

        $duration =
            microtime(true) - $start;

        return $this->buildResponse(
            $stdout,
            $stderr,
            $exitCode,
            $duration
        );
    }

    /**
     * @param array<string,string|int|float|bool|null> $query
     * @param array<string,string|int|float|bool|null> $post
     * @param array<string,string> $server
     * @param array<string,string> $cookies
     */
    private function createPayload(
        string $method,
        string $path,
        array $query,
        array $post,
        array $server,
        array $cookies
    ): string {
        return base64_encode(
            serialize([
                'method' => strtoupper($method),
                'path' => $path,
                'query' => $query,
                'post' => $post,
                'server' => $server,
                'cookies' => $cookies,
            ])
        );
    }

    private function createBootstrap(): string
    {
        return
            $this->bootstrapRequestSetup()
            . $this->bootstrapSessionSetup()
            . $this->bootstrapErrorHandling()
            . $this->bootstrapExecution();
    }

    private function bootstrapRequestSetup(): string
    {
        return <<<'PHP'
declare(strict_types=1);

$payload = unserialize(
    base64_decode(
        getenv('BOARDPREP_SIMULATION')
    )
);

$_GET = $payload['query'] ?? [];
$_POST = $payload['post'] ?? [];
$_COOKIE = $payload['cookies'] ?? [];

$_SERVER['REQUEST_METHOD'] =
    $payload['method'] ?? 'GET';

$_SERVER['REQUEST_URI'] =
    $payload['path'] ?? '/';

$_SERVER['QUERY_STRING'] =
    http_build_query($_GET);

$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['SERVER_NAME'] = 'localhost';
$_SERVER['SERVER_PORT'] = '80';
$_SERVER['HTTPS'] = '';

foreach (
    $payload['server'] ?? []
    as $key => $value
) {
    $_SERVER[$key] = $value;
}
PHP;
    }

    private function bootstrapSessionSetup(): string
    {
        return <<<'PHP'

$sessionDirectory =
    dirname($argv[1], 2)
    . '/storage/doctor/simulation-sessions';

if (!is_dir($sessionDirectory)) {
    mkdir($sessionDirectory, 0777, true);
}

ini_set('session.use_cookies', '0');
ini_set('session.use_strict_mode', '0');
ini_set('session.cache_limiter', '');

session_save_path($sessionDirectory);

if (
    isset($_COOKIE['PHPSESSID'])
    && is_string($_COOKIE['PHPSESSID'])
    && $_COOKIE['PHPSESSID'] !== ''
) {
    session_id($_COOKIE['PHPSESSID']);
}
PHP;
    }

    private function bootstrapErrorHandling(): string
    {
        return <<<'PHP'

register_shutdown_function(
    static function (): void {
        $status = http_response_code();

        if ($status === false) {
            $status = 200;
        }

        fwrite(
            STDERR,
            '__BOARDPREP_HTTP_STATUS__'
            . $status
            . PHP_EOL
        );
    }
);

set_error_handler(
    static function (
        int $severity,
        string $message,
        string $file,
        int $line
    ): bool {
        throw new \ErrorException(
            $message,
            0,
            $severity,
            $file,
            $line
        );
    }
);

set_exception_handler(
    static function (\Throwable $exception): void {
        fwrite(
            STDERR,
            get_class($exception)
            . ': '
            . $exception->getMessage()
            . PHP_EOL
        );

        exit(1);
    }
);
PHP;
    }

    private function bootstrapExecution(): string
    {
        return <<<'PHP'

ob_start();

try {
    require $argv[1];

    $output = ob_get_clean();

    echo $output;
} catch (\Throwable $exception) {
    if (ob_get_level() > 0) {
        ob_end_clean();
    }

    fwrite(
        STDERR,
        get_class($exception)
        . ': '
        . $exception->getMessage()
        . PHP_EOL
    );

    exit(1);
}
PHP;
    }

    private function createCommand(
        string $bootstrap
    ): string {
        return
            escapeshellarg(PHP_BINARY)
            . ' -d display_errors=0'
            . ' -d log_errors=0'
            . ' -r '
            . escapeshellarg($bootstrap)
            . ' '
            . escapeshellarg($this->entryPoint);
    }

    /**
     * @return array{0:string,1:string,2:int}
     */
    private function execute(
        string $payload,
        string $command
    ): array {
        $stdoutFile = tempnam(
            sys_get_temp_dir(),
            'boardprep_stdout_'
        );

        $stderrFile = tempnam(
            sys_get_temp_dir(),
            'boardprep_stderr_'
        );

        if (
            $stdoutFile === false
            || $stderrFile === false
        ) {
            throw new \RuntimeException(
                'Unable to create simulation output files.'
            );
        }

        try {
            $processCommand =
                $this->createProcessCommand(
                    $payload,
                    $command,
                    $stdoutFile,
                    $stderrFile
                );

            $output = [];
            $exitCode = 0;

            exec(
                $processCommand,
                $output,
                $exitCode
            );

            $stdout =
                is_file($stdoutFile)
                    ? (string) file_get_contents(
                        $stdoutFile
                    )
                    : '';

            $stderr =
                is_file($stderrFile)
                    ? (string) file_get_contents(
                        $stderrFile
                    )
                    : '';

            return [
                $stdout,
                $stderr,
                $exitCode,
            ];

        } finally {
            @unlink($stdoutFile);
            @unlink($stderrFile);
        }
    }

    private function createProcessCommand(
        string $payload,
        string $command,
        string $stdoutFile,
        string $stderrFile
    ): string {
        return
            'env '
            . escapeshellarg(
                'BOARDPREP_SIMULATION=' . $payload
            )
            . ' '
            . escapeshellarg(
                'BOARDPREP_SIMULATED=1'
            )
            . ' '
            . $command
            . ' > '
            . escapeshellarg($stdoutFile)
            . ' 2> '
            . escapeshellarg($stderrFile);
    }

    /**
     * @return array{
     *     exitCode:int,
     *     output:string,
     *     stdout:string,
     *     stderr:string,
     *     duration:float,
     *     status:int,
     *     headers:array<int,string>,
     *     location:string|null,
     *     success:bool
     * }
     */
    private function buildResponse(
        string $stdout,
        string $stderr,
        int $exitCode,
        float $duration
    ): array {
        $headers =
            $this->extractHeaders($stdout);

        $status =
            $this->extractStatus(
                $headers,
                $stderr
            );

        $location =
            $this->extractSimulatedLocation($stderr)
            ?? $this->extractLocation($headers);

        return [
            'exitCode' => $exitCode,
            'output' => $stdout,
            'stdout' => $stdout,
            'stderr' => $stderr,
            'duration' => $duration,
            'status' => $status,
            'headers' => $headers,
            'location' => $location,
            'success' =>
                $exitCode === 0
                && $status < 400,
        ];
    }

    /**
     * @param array<int,string> $headers
     */
    private function extractStatus(
        array $headers,
        string $stderr = ''
    ): int {
        if (
            preg_match(
                '/__BOARDPREP_HTTP_STATUS__(\d{3})/',
                $stderr,
                $matches
            )
        ) {
            return (int) $matches[1];
        }

        foreach ($headers as $header) {
            if (
                preg_match(
                    '/^HTTP\/\S+\s+(\d{3})/i',
                    $header,
                    $matches
                )
            ) {
                return (int) $matches[1];
            }
        }

        return 200;
    }

    private function extractSimulatedLocation(
        string $stderr
    ): ?string {
        if (
            preg_match(
                '/__BOARDPREP_HTTP_LOCATION__(.*)/',
                $stderr,
                $matches
            )
        ) {
            return trim($matches[1]);
        }

        return null;
    }

    /**
     * @param array<int,string> $headers
     */
    private function extractLocation(
        array $headers
    ): ?string {
        foreach ($headers as $header) {
            if (
                preg_match(
                    '/^Location:\s*(.+)$/i',
                    $header,
                    $matches
                )
            ) {
                return trim($matches[1]);
            }
        }

        return null;
    }

    /**
     * @return array<int,string>
     */
    private function extractHeaders(
        string $output
    ): array {
        $headers = [];

        foreach (
            preg_split(
                '/\R/',
                $output
            ) ?: []
            as $line
        ) {
            $line = trim($line);

            if (
                preg_match(
                    '/^(HTTP\/\S+\s+\d{3}|[A-Za-z0-9\-]+:)/',
                    $line
                )
            ) {
                $headers[] = $line;
            }
        }

        return $headers;
    }
}
