<?php

declare(strict_types=1);

namespace App\Domains\Admin\Services;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Redis;
use RuntimeException;
use Throwable;

class ServerOpsService
{
    /**
     * @return array<string, list<array{key: string, label: string, description: string, danger: bool, type: string}>>
     */
    public function catalogByGroup(): array
    {
        $grouped = [];

        foreach ($this->commands() as $key => $command) {
            $group = (string) ($command['group'] ?? 'Other');
            $grouped[$group][] = [
                'key' => $key,
                'label' => (string) ($command['label'] ?? $key),
                'description' => (string) ($command['description'] ?? ''),
                'danger' => (bool) ($command['danger'] ?? false),
                'type' => (string) ($command['type'] ?? 'artisan'),
            ];
        }

        return $grouped;
    }

    /**
     * @return array{ok: bool, output: string, key: string, label: string}
     */
    public function run(string $key): array
    {
        $commands = $this->commands();
        if (! isset($commands[$key]) || ! is_array($commands[$key])) {
            throw new RuntimeException('Unknown or disallowed ops command.');
        }

        $command = $commands[$key];
        $label = (string) ($command['label'] ?? $key);
        $type = (string) ($command['type'] ?? 'artisan');

        $result = match ($type) {
            'artisan' => $this->runArtisan((string) ($command['command'] ?? '')),
            'redis' => $this->runRedis((string) ($command['command'] ?? 'ping')),
            'shell' => $this->runShell($command),
            default => ['ok' => false, 'output' => "Unsupported ops type [{$type}]."],
        };

        return [
            'ok' => $result['ok'],
            'output' => $result['output'],
            'key' => $key,
            'label' => $label,
        ];
    }

    /**
     * Lightweight probes for the queues dashboard header.
     *
     * @return array{horizon: array{ok: bool, output: string, status: string}, redis: array{ok: bool, output: string}}
     */
    public function probes(): array
    {
        $horizon = $this->run('horizon_status');
        $output = mb_strtolower($horizon['output']);
        $status = 'unknown';
        if (str_contains($output, 'running')) {
            $status = 'running';
        } elseif (str_contains($output, 'inactive') || str_contains($output, 'not running') || str_contains($output, 'stopped')) {
            $status = 'inactive';
        } elseif (! $horizon['ok']) {
            $status = 'unavailable';
        }

        $redis = $this->runRedis('ping');

        return [
            'horizon' => [
                'ok' => $horizon['ok'],
                'output' => $horizon['output'],
                'status' => $status,
            ],
            'redis' => $redis,
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function commands(): array
    {
        $commands = config('admin_ops.commands', []);

        return is_array($commands) ? $commands : [];
    }

    /**
     * @return array{ok: bool, output: string}
     */
    private function runArtisan(string $command): array
    {
        if ($command === '') {
            return ['ok' => false, 'output' => 'Empty artisan command.'];
        }

        try {
            $exit = Artisan::call($command);
            $output = trim(Artisan::output());

            return [
                'ok' => $exit === 0,
                'output' => $output !== '' ? $output : ($exit === 0 ? "{$command} completed." : "{$command} failed (exit {$exit})."),
            ];
        } catch (Throwable $e) {
            return ['ok' => false, 'output' => $e->getMessage()];
        }
    }

    /**
     * @return array{ok: bool, output: string}
     */
    private function runRedis(string $action): array
    {
        try {
            if ($action === 'ping') {
                $pong = Redis::connection()->ping();
                $text = is_string($pong) ? $pong : (is_bool($pong) && $pong ? 'PONG' : json_encode($pong));

                return ['ok' => true, 'output' => (string) $text];
            }

            return ['ok' => false, 'output' => "Unsupported redis action [{$action}]."];
        } catch (Throwable $e) {
            return ['ok' => false, 'output' => $e->getMessage()];
        }
    }

    /**
     * @param  array<string, mixed>  $command
     * @return array{ok: bool, output: string}
     */
    private function runShell(array $command): array
    {
        $argv = $command['argv'] ?? null;
        if (! is_array($argv) || $argv === []) {
            return ['ok' => false, 'output' => 'Empty shell argv.'];
        }

        $resolved = [];
        foreach ($argv as $part) {
            $resolved[] = $this->resolveToken((string) $part);
        }

        $needsSudo = (bool) ($command['sudo'] ?? false) && (bool) config('admin_ops.use_sudo', true);
        if ($needsSudo) {
            $sudo = (string) config('admin_ops.sudo_bin', '/usr/bin/sudo');
            // -n: never prompt (fails clearly if sudoers missing)
            array_unshift($resolved, $sudo, '-n');
        }

        // Safety: every argv token must be a simple path/flag/word — no shell metacharacters.
        foreach ($resolved as $token) {
            if ($token === '' || preg_match('/[\s;&|<>`$\\\\]/', $token)) {
                return ['ok' => false, 'output' => 'Blocked unsafe shell token.'];
            }
        }

        try {
            $timeout = max(5, (int) config('admin_ops.timeout', 45));
            $result = Process::timeout($timeout)->run($resolved);
            $output = trim($result->output()."\n".$result->errorOutput());

            return [
                'ok' => $result->successful(),
                'output' => $output !== '' ? $output : ($result->successful()
                    ? 'Command completed with no output.'
                    : 'Command failed (exit '.$result->exitCode().').'),
            ];
        } catch (Throwable $e) {
            return ['ok' => false, 'output' => $e->getMessage()];
        }
    }

    private function resolveToken(string $token): string
    {
        return match ($token) {
            '{supervisorctl}' => (string) config('admin_ops.supervisorctl_bin', '/usr/bin/supervisorctl'),
            '{supervisor_program}' => (string) config('admin_ops.supervisor_program', 'horizon'),
            '{systemctl}' => (string) config('admin_ops.systemctl_bin', '/bin/systemctl'),
            '{apache_service}' => (string) config('admin_ops.apache_service', 'apache2'),
            '{apachectl}' => (string) config('admin_ops.apachectl_bin', '/usr/sbin/apache2ctl'),
            '{redis_cli}' => (string) config('admin_ops.redis_cli_bin', '/usr/bin/redis-cli'),
            default => $token,
        };
    }
}
