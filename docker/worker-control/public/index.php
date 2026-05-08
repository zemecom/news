<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Symfony\Component\Process\Process;

require dirname(__DIR__, 3).'/vendor/autoload.php';

$app = require dirname(__DIR__, 3).'/bootstrap/app.php';
$app->make(ConsoleKernel::class)->bootstrap();

$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$path = trim((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/');

if ($path === 'healthz' && $method === 'GET') {
    workerControlJson(200, [
        'status' => 'ok',
    ]);
}

$token = (string) env('WORKER_SUPERVISOR_TOKEN', '');
$authorizationHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

if ($token !== '' && $authorizationHeader !== 'Bearer '.$token) {
    workerControlJson(401, [
        'error' => 'Unauthorized.',
    ]);
}

$segments = $path === '' ? [] : explode('/', $path);
$runtimes = config('workers.runtimes', []);

if ($segments === ['v1', 'runtimes'] && $method === 'GET') {
    $items = [];

    foreach ($runtimes as $runtime => $runtimeConfig) {
        if (! is_array($runtimeConfig)) {
            continue;
        }

        $items[] = workerControlStatus($runtime, $runtimeConfig);
    }

    workerControlJson(200, [
        'runtimes' => $items,
    ]);
}

if (count($segments) < 3 || $segments[0] !== 'v1' || $segments[1] !== 'runtimes') {
    workerControlJson(404, [
        'error' => 'Route not found.',
    ]);
}

$runtime = (string) $segments[2];
$runtimeConfig = $runtimes[$runtime] ?? null;

if (! is_array($runtimeConfig)) {
    workerControlJson(404, [
        'error' => sprintf('Runtime "%s" is not configured.', $runtime),
    ]);
}

if (count($segments) === 3 && $method === 'GET') {
    workerControlJson(200, workerControlStatus($runtime, $runtimeConfig));
}

if (($segments[3] ?? null) === 'start' && $method === 'POST') {
    workerControlJson(...workerControlActionResponse($runtime, $runtimeConfig, 'start'));
}

if (($segments[3] ?? null) === 'stop' && $method === 'POST') {
    workerControlJson(...workerControlActionResponse($runtime, $runtimeConfig, 'stop'));
}

if (($segments[3] ?? null) === 'restart' && $method === 'POST') {
    workerControlJson(...workerControlActionResponse($runtime, $runtimeConfig, 'restart'));
}

if (($segments[3] ?? null) === 'logs' && $method === 'GET') {
    $lines = max(1, (int) ($_GET['lines'] ?? config('workers.supervisor.log_tail_lines', 50)));
    $service = (string) ($runtimeConfig['service'] ?? $runtime);
    [$success, $output, $error] = workerControlRun([
        'docker',
        'compose',
        '--project-directory',
        base_path(),
        'logs',
        '--tail',
        (string) $lines,
        $service,
    ]);

    workerControlJson($success ? 200 : 500, [
        'runtime' => $runtime,
        'lines' => $success ? array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $output) ?: []), static fn (string $line): bool => $line !== '')) : [],
        'error' => $success ? null : $error,
    ]);
}

workerControlJson(404, [
    'error' => 'Route not found.',
]);

/**
 * @param  array<string, mixed>  $runtimeConfig
 * @return array<string, mixed>
 */
function workerControlStatus(string $runtime, array $runtimeConfig): array
{
    $service = (string) ($runtimeConfig['service'] ?? $runtime);
    [$containerIdSuccess, $containerId, $containerIdError] = workerControlRun([
        'docker',
        'compose',
        '--project-directory',
        base_path(),
        'ps',
        '-aq',
        $service,
    ]);

    if (! $containerIdSuccess) {
        return [
            'runtime' => $runtime,
            'service' => $service,
            'state' => 'unavailable',
            'container_present' => false,
            'memory_bytes' => null,
            'cpu_percent' => null,
            'uptime_seconds' => null,
            'restart_count' => null,
            'started_at' => null,
            'error' => $containerIdError,
        ];
    }

    $containerId = trim($containerId);

    if ($containerId === '') {
        return [
            'runtime' => $runtime,
            'service' => $service,
            'state' => 'stopped',
            'container_present' => false,
            'memory_bytes' => null,
            'cpu_percent' => null,
            'uptime_seconds' => null,
            'restart_count' => null,
            'started_at' => null,
            'error' => null,
        ];
    }

    [$inspectSuccess, $inspectOutput, $inspectError] = workerControlRun([
        'docker',
        'inspect',
        $containerId,
    ]);

    if (! $inspectSuccess) {
        return [
            'runtime' => $runtime,
            'service' => $service,
            'state' => 'unavailable',
            'container_present' => true,
            'memory_bytes' => null,
            'cpu_percent' => null,
            'uptime_seconds' => null,
            'restart_count' => null,
            'started_at' => null,
            'error' => $inspectError,
        ];
    }

    $decodedInspect = json_decode($inspectOutput, true);
    $container = is_array($decodedInspect) && is_array($decodedInspect[0] ?? null) ? $decodedInspect[0] : [];
    $state = is_array($container['State'] ?? null) ? $container['State'] : [];
    $startedAt = is_string($state['StartedAt'] ?? null) ? $state['StartedAt'] : null;
    $running = (bool) ($state['Running'] ?? false);
    $restartCount = isset($container['RestartCount']) ? (int) $container['RestartCount'] : null;

    $memoryBytes = null;
    $cpuPercent = null;

    if ($running) {
        [$statsSuccess, $statsOutput] = workerControlRun([
            'docker',
            'stats',
            '--no-stream',
            '--format',
            '{{json .}}',
            $containerId,
        ]);

        if ($statsSuccess) {
            $decodedStats = json_decode($statsOutput, true);

            if (is_array($decodedStats)) {
                $memoryBytes = workerControlParseBytes(is_string($decodedStats['MemUsage'] ?? null) ? $decodedStats['MemUsage'] : null);
                $cpuPercent = workerControlParseCpu(is_string($decodedStats['CPUPerc'] ?? null) ? $decodedStats['CPUPerc'] : null);
            }
        }
    }

    return [
        'runtime' => $runtime,
        'service' => $service,
        'state' => $running ? 'running' : 'stopped',
        'container_present' => true,
        'memory_bytes' => $memoryBytes,
        'cpu_percent' => $cpuPercent,
        'uptime_seconds' => $startedAt !== null ? CarbonImmutable::parse($startedAt)->diffInSeconds(now()) : null,
        'restart_count' => $restartCount,
        'started_at' => $startedAt,
        'error' => null,
    ];
}

/**
 * @param  array<string, mixed>  $runtimeConfig
 * @return array{0:int,1:array<string, mixed>}
 */
function workerControlActionResponse(string $runtime, array $runtimeConfig, string $action): array
{
    $service = (string) ($runtimeConfig['service'] ?? $runtime);
    $baseArgs = ['docker', 'compose', '--project-directory', base_path()];

    $args = match ($action) {
        'start' => [...$baseArgs, '--profile', 'queue', 'up', '-d', '--no-deps', $service],
        'stop' => [...$baseArgs, 'stop', $service],
        'restart' => [...$baseArgs, 'restart', $service],
        default => throw new InvalidArgumentException('Unsupported action.'),
    };

    [$success, $output, $error] = workerControlRun($args);

    return [
        $success ? 200 : 500,
        [
            'success' => $success,
            'runtime' => $runtime,
            'message' => $success
                ? sprintf('Runtime %s completed for %s.', $action, $runtime)
                : $error,
            'output' => $success ? trim($output) : null,
        ],
    ];
}

/**
 * @param  list<string>  $command
 * @return array{0:bool,1:string,2:?string}
 */
function workerControlRun(array $command): array
{
    $process = new Process($command, base_path());
    $process->setTimeout(15);
    $process->run();

    return [
        $process->isSuccessful(),
        trim($process->getOutput()),
        $process->isSuccessful() ? null : trim($process->getErrorOutput() ?: $process->getOutput()),
    ];
}

function workerControlParseBytes(?string $memUsage): ?int
{
    if ($memUsage === null || $memUsage === '') {
        return null;
    }

    $parts = explode('/', $memUsage);
    $value = trim($parts[0] ?? '');

    if ($value === '' || ! preg_match('/^([0-9.]+)\s*([KMGTP]?i?B)$/i', $value, $matches)) {
        return null;
    }

    $number = (float) $matches[1];
    $unit = strtoupper($matches[2]);

    return (int) round($number * match ($unit) {
        'B' => 1,
        'KB', 'KIB' => 1024,
        'MB', 'MIB' => 1024 ** 2,
        'GB', 'GIB' => 1024 ** 3,
        'TB', 'TIB' => 1024 ** 4,
        'PB', 'PIB' => 1024 ** 5,
        default => 1,
    });
}

function workerControlParseCpu(?string $cpu): ?float
{
    if ($cpu === null || $cpu === '') {
        return null;
    }

    return is_numeric(str_replace('%', '', $cpu))
        ? (float) str_replace('%', '', $cpu)
        : null;
}

/**
 * @param  array<string, mixed>  $payload
 */
function workerControlJson(int $status, array $payload): never
{
    http_response_code($status);
    header('Content-Type: application/json');

    echo json_encode($payload, JSON_THROW_ON_ERROR);
    exit;
}
