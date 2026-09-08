<?php

declare(strict_types=1);

namespace App\Shared\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Converts exceptions into the standardised JSON error response
 * defined in the WapApp 2.0 architecture proposal (§5.1).
 *
 * Response shape:
 * {
 *   "success": false,
 *   "error": {
 *     "code": "CAMP_AUDIENCE_EMPTY",
 *     "message": "Campaign cannot be sent without an audience.",
 *     "details": { "campaign_id": "camp_123" },
 *     "request_id": "req_abc123",
 *     "timestamp": "2026-07-27T10:30:00Z"
 *   }
 * }
 */
class ErrorResponse
{
    /**
     * Render a throwable into a JSON error response.
     */
    public static function render(Throwable $e, Request $request): ?JsonResponse
    {
        // Let Laravel handle authentication exceptions natively
        // (redirect to login for web, 401 for JSON)
        if ($e instanceof AuthenticationException) {
            return null;
        }

        // Only render JSON for API routes or AJAX requests
        if (! $request->is('api/*') && ! $request->expectsJson()) {
            return null;
        }

        // Only handle exceptions we have structured responses for
        if (! $e instanceof DomainException
            && ! $e instanceof ValidationException
            && ! $e instanceof HttpExceptionInterface
        ) {
            return null;
        }

        $payload = self::buildPayload($e, $request);
        $status = self::resolveHttpStatus($e);

        return new JsonResponse($payload, $status);
    }

    /**
     * Build the standardised error payload.
     */
    public static function buildPayload(Throwable $e, ?Request $request = null): array
    {
        $code = self::resolveErrorCode($e);
        $message = self::resolveMessage($e);
        $details = self::resolveDetails($e);

        $error = [
            'code' => $code?->value ?? 'SYS_INTERNAL_ERROR',
            'message' => $message,
        ];

        if (! empty($details)) {
            $error['details'] = $details;
        }

        if ($request && method_exists($request, 'attributes') && $request->attributes->has('request_id')) {
            $error['request_id'] = $request->attributes->get('request_id');
        }

        $error['timestamp'] = now()->toIso8601String();

        return [
            'success' => false,
            'error' => $error,
        ];
    }

    /**
     * Map exception to an ErrorCode.
     */
    private static function resolveErrorCode(Throwable $e): ?ErrorCode
    {
        if ($e instanceof DomainException) {
            return $e->getErrorCode();
        }

        if ($e instanceof ValidationException) {
            return ErrorCode::SYS_VALIDATION_FAILED;
        }

        if ($e instanceof HttpExceptionInterface) {
            return match ($e->getStatusCode()) {
                401 => ErrorCode::AUTH_UNAUTHENTICATED,
                403 => ErrorCode::AUTH_FORBIDDEN,
                404 => ErrorCode::SYS_NOT_FOUND,
                429 => ErrorCode::SYS_RATE_LIMITED,
                default => ErrorCode::SYS_INTERNAL_ERROR,
            };
        }

        return ErrorCode::SYS_INTERNAL_ERROR;
    }

    /**
     * Determine the user-facing message.
     */
    private static function resolveMessage(Throwable $e): string
    {
        if ($e instanceof DomainException) {
            return $e->getMessage();
        }

        if ($e instanceof ValidationException) {
            return 'The given data was invalid.';
        }

        if ($e instanceof HttpExceptionInterface) {
            return $e->getMessage() ?: 'HTTP Error '.$e->getStatusCode();
        }

        // Never leak internal error details in production
        if (app()->hasDebugModeEnabled()) {
            return $e->getMessage();
        }

        return 'An unexpected error occurred. Please try again later.';
    }

    /**
     * Extract extra details from the exception.
     */
    private static function resolveDetails(Throwable $e): array
    {
        if ($e instanceof DomainException) {
            return $e->getDetails();
        }

        if ($e instanceof ValidationException) {
            return ['errors' => $e->errors()];
        }

        return [];
    }

    /**
     * Resolve the HTTP status code.
     */
    private static function resolveHttpStatus(Throwable $e): int
    {
        if ($e instanceof DomainException) {
            return $e->getHttpStatus();
        }

        if ($e instanceof ValidationException) {
            return 422;
        }

        if ($e instanceof HttpExceptionInterface) {
            return $e->getStatusCode();
        }

        return 500;
    }
}
