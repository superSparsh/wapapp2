<?php

declare(strict_types=1);

namespace App\Domains\Admin\Services;

use App\Domains\Admin\Support\ErrorModuleResolver;
use App\Enums\PlatformErrorType;
use App\Models\PlatformErrorLog;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

/**
 * Persists module-tagged errors for the admin Errors hub.
 * Never throws — logging must not break the request/job.
 */
class ModuleErrorRecorder
{
    private const MESSAGE_LIMIT = 2000;

    private const CONTEXT_STACK_LIMIT = 4000;

    public function __construct(
        private readonly ErrorModuleResolver $resolver,
    ) {}

    /**
     * @param  array<string, mixed>  $context
     */
    public function record(
        PlatformErrorType|string $type,
        string $message,
        ?string $module = null,
        ?string $source = null,
        ?string $tenantId = null,
        array $context = [],
    ): void {
        try {
            if (! $this->tableReady()) {
                return;
            }

            $typeEnum = $type instanceof PlatformErrorType
                ? $type
                : PlatformErrorType::tryFrom($type) ?? PlatformErrorType::Exception;

            $resolvedModule = $module;
            if ($resolvedModule === null || ! $this->resolver->isValidModule($resolvedModule)) {
                $resolvedModule = $this->resolver->fromClass($source) !== 'other'
                    ? $this->resolver->fromClass($source)
                    : 'other';
            }

            if (! $this->resolver->isValidModule($resolvedModule)) {
                $resolvedModule = 'other';
            }

            if ($tenantId === null) {
                $tenantId = $this->currentTenantId();
            }

            PlatformErrorLog::query()->create([
                'module' => $resolvedModule,
                'type' => $typeEnum,
                'tenant_id' => $tenantId !== null && $tenantId !== '' ? Str::limit($tenantId, 64, '') : null,
                'source' => $source !== null ? Str::limit($source, 255, '') : null,
                'message' => Str::limit($message, self::MESSAGE_LIMIT, '…'),
                'context' => $this->sanitizeContext($context),
                'occurred_at' => now(),
            ]);
        } catch (Throwable $e) {
            Log::warning('ModuleErrorRecorder failed', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function recordException(Throwable $e, ?string $module = null, ?string $source = null): void
    {
        if ($this->shouldSkipException($e)) {
            return;
        }

        $source ??= $e::class;
        $module ??= $this->resolver->fromClass($this->guessSourceClass($e));

        $this->record(
            type: PlatformErrorType::Exception,
            message: $e->getMessage() !== '' ? $e->getMessage() : $e::class,
            module: $module,
            source: $source,
            context: [
                'exception' => $e::class,
                'file' => $e->getFile().':'.$e->getLine(),
                'trace' => Str::limit($e->getTraceAsString(), self::CONTEXT_STACK_LIMIT, '…'),
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function recordApi(
        string $message,
        ?string $module = null,
        ?string $source = null,
        array $context = [],
    ): void {
        $this->record(
            type: PlatformErrorType::Api,
            message: $message,
            module: $module,
            source: $source,
            context: $context,
        );
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function recordJob(
        string $message,
        ?string $module = null,
        ?string $source = null,
        array $context = [],
    ): void {
        $this->record(
            type: PlatformErrorType::Job,
            message: $message,
            module: $module,
            source: $source,
            context: $context,
        );
    }

    private function tableReady(): bool
    {
        try {
            return Schema::connection(
                (string) config('tenancy.database.central_connection', config('database.default'))
            )->hasTable('platform_error_logs');
        } catch (Throwable) {
            return false;
        }
    }

    private function currentTenantId(): ?string
    {
        try {
            if (function_exists('tenant') && tenant()) {
                $id = tenant('id');

                return is_scalar($id) ? (string) $id : null;
            }
        } catch (Throwable) {
            //
        }

        $fromSession = session('auth.tenant_id') ?? session('tenant_id');

        return is_scalar($fromSession) ? (string) $fromSession : null;
    }

    private function shouldSkipException(Throwable $e): bool
    {
        $class = $e::class;

        $skip = [
            \Illuminate\Validation\ValidationException::class,
            \Illuminate\Auth\AuthenticationException::class,
            \Illuminate\Auth\Access\AuthorizationException::class,
            \Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class,
            \Symfony\Component\HttpKernel\Exception\HttpException::class,
            \Illuminate\Session\TokenMismatchException::class,
            \Illuminate\Routing\Exceptions\InvalidSignatureException::class,
        ];

        foreach ($skip as $skipClass) {
            if ($e instanceof $skipClass) {
                // Keep 5xx HttpExceptions
                if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) {
                    return $e->getStatusCode() < 500;
                }

                return true;
            }
        }

        return false;
    }

    private function guessSourceClass(Throwable $e): string
    {
        foreach ($e->getTrace() as $frame) {
            $class = $frame['class'] ?? null;
            if (is_string($class) && str_starts_with($class, 'App\\Domains\\')) {
                return $class;
            }
        }

        return $e::class;
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function sanitizeContext(array $context): array
    {
        $clean = [];
        foreach ($context as $key => $value) {
            if (! is_string($key)) {
                continue;
            }
            if (is_scalar($value) || $value === null) {
                $clean[$key] = is_string($value) ? Str::limit($value, 1000, '…') : $value;
            } elseif (is_array($value)) {
                $clean[$key] = Str::limit(json_encode($value) ?: '', 1000, '…');
            } else {
                $clean[$key] = Str::limit((string) $value, 500, '…');
            }
        }

        return $clean;
    }
}
