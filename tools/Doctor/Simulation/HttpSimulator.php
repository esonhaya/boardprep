<?php

declare(strict_types=1);

namespace Tools\Doctor\Simulation;

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

        $payload = base64_encode(
            serialize([
                'method' => strtoupper($method),
                'path' => $path,
                'query' => $query,
                'post' => $post,
                'server' => $server,
                'cookies' => $cookies,
            ])
        );

        $bootstrap = <<<'PHP'
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

$_SERVER['HTTP_HOST'] =
    $_SERVER['HTTP_HOST'] ?? 'localhost';

$_SERVER['SERVER_NAME'] =
    $_SERVER['SERVER_NAME'] ?? 'localhost';

$_SERVER['SERVER_PORT'] =
    $_SERVER['SERVER_PORT'] ?? '80';

$_SERVER['HTTPS'] =
    $_SERVER['HTTPS'] ?? '';

foreach (
    $payload['server'] ?? []
    as $key => $value
) {
    $_SERVER[$key] = $value;
}

$headers = [];

set_error_handler(
    static function (
        int $severity,
        string $message,
        string $file,
        int $line
    ) {
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
    static function (
        \Throwable $exception
    ) {
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

        $command =
            escapeshellarg(PHP_BINARY)
            . ' -d display_errors=0'
            . ' -d log_errors=0'
            . ' -r '
            . escapeshellarg($bootstrap)
            . ' '
            . escapeshellarg($this->entryPoint);

        $start = microtime(true);

        $stdoutFile =
            tempnam(
                sys_get_temp_dir(),
                'boardprep_stdout_'
            );

        $stderrFile =
            tempnam(
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

        $environment = $_ENV;

        $environment[
            'BOARDPREP_SIMULATION'
        ] = $payload;

        $environment[
            'BOARDPREP_SIMULATED'
        ] = '1';

        $processCommand =
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

        $output = [];
        $exitCode = 0;

        exec(
            $processCommand,
            $output,
            $exitCode
        );

        $stdout =
            is_file($stdoutFile)
                ? (string) file_get_contents($stdoutFile)
                : '';

        $stderr =
            is_file($stderrFile)
                ? (string) file_get_contents($stderrFile)
                : '';

        @unlink($stdoutFile);
        @unlink($stderrFile);

        $duration =
            microtime(true) - $start;

        $headers =
            $this->extractHeaders($stdout);

        $status =
            $this->extractStatus($headers);

        $location =
            $this->extractLocation($headers);

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
        array $headers
    ): int {

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
