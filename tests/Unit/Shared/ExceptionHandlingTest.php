<?php

declare(strict_types=1);

namespace Tests\Unit\Shared;

use App\Shared\Exceptions\DomainException;
use App\Shared\Exceptions\ErrorCode;
use App\Shared\Exceptions\ErrorResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

class ExceptionHandlingTest extends TestCase
{
    // ── ErrorCode enum ──

    public function test_error_codes_have_default_http_statuses(): void
    {
        $this->assertSame(401, ErrorCode::AUTH_INVALID_CREDENTIALS->defaultHttpStatus());
        $this->assertSame(403, ErrorCode::AUTH_FORBIDDEN->defaultHttpStatus());
        $this->assertSame(404, ErrorCode::SYS_NOT_FOUND->defaultHttpStatus());
        $this->assertSame(422, ErrorCode::SYS_VALIDATION_FAILED->defaultHttpStatus());
        $this->assertSame(429, ErrorCode::SYS_RATE_LIMITED->defaultHttpStatus());
        $this->assertSame(503, ErrorCode::SYS_CIRCUIT_OPEN->defaultHttpStatus());
        $this->assertSame(500, ErrorCode::SYS_INTERNAL_ERROR->defaultHttpStatus());
        $this->assertSame(409, ErrorCode::CAMP_ALREADY_RUNNING->defaultHttpStatus());
        $this->assertSame(422, ErrorCode::WALLET_INSUFFICIENT_BALANCE->defaultHttpStatus());
    }

    public function test_all_error_codes_have_string_values(): void
    {
        foreach (ErrorCode::cases() as $code) {
            $this->assertNotEmpty($code->value);
            $this->assertSame($code->name, $code->value);
        }
    }

    // ── DomainException ──

    public function test_domain_exception_carries_error_code(): void
    {
        $e = DomainException::withCode(ErrorCode::CAMP_AUDIENCE_EMPTY, 'No contacts.');

        $this->assertSame(ErrorCode::CAMP_AUDIENCE_EMPTY, $e->getErrorCode());
        $this->assertSame('No contacts.', $e->getMessage());
        $this->assertSame(422, $e->getHttpStatus());
        $this->assertSame([], $e->getDetails());
    }

    public function test_domain_exception_with_details(): void
    {
        $e = DomainException::withCode(
            ErrorCode::MSG_SEND_FAILED,
            'Delivery failed.',
            ['phone' => '+919999999999'],
        );

        $this->assertSame(['phone' => '+919999999999'], $e->getDetails());
        $this->assertSame(503, $e->getHttpStatus());
    }

    public function test_domain_exception_defaults_to_error_code_message(): void
    {
        $e = DomainException::withCode(ErrorCode::TENANT_SUSPENDED);

        $this->assertSame('TENANT_SUSPENDED', $e->getMessage());
        $this->assertSame(403, $e->getHttpStatus());
    }

    // ── ErrorResponse ──

    public function test_error_response_renders_domain_exception_as_json(): void
    {
        $request = Request::create('/api/test', 'GET');
        $exception = DomainException::withCode(
            ErrorCode::CAMP_AUDIENCE_EMPTY,
            'Audience has no contacts.',
            ['campaign_id' => 'camp_123'],
        );

        $response = ErrorResponse::render($exception, $request);

        $this->assertNotNull($response);
        $this->assertSame(422, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertSame('CAMP_AUDIENCE_EMPTY', $data['error']['code']);
        $this->assertSame('Audience has no contacts.', $data['error']['message']);
        $this->assertSame(['campaign_id' => 'camp_123'], $data['error']['details']);
        $this->assertArrayHasKey('timestamp', $data['error']);
    }

    public function test_error_response_returns_null_for_non_json_requests(): void
    {
        $request = Request::create('/dashboard', 'GET');
        $exception = DomainException::withCode(ErrorCode::SYS_INTERNAL_ERROR);

        $response = ErrorResponse::render($exception, $request);

        $this->assertNull($response);
    }

    public function test_error_response_returns_null_for_authentication_exception(): void
    {
        $request = Request::create('/api/test', 'GET');
        $exception = new AuthenticationException('Unauthenticated.');

        $response = ErrorResponse::render($exception, $request);

        $this->assertNull($response);
    }

    public function test_error_response_returns_null_for_unknown_exceptions(): void
    {
        $request = Request::create('/api/test', 'GET');
        $exception = new \RuntimeException('Something broke');

        $response = ErrorResponse::render($exception, $request);

        $this->assertNull($response);
    }

    public function test_error_response_renders_validation_exception(): void
    {
        $request = Request::create('/api/test', 'POST');
        $validator = validator([], ['name' => 'required']);
        $validator->fails();
        $exception = new ValidationException($validator);

        $response = ErrorResponse::render($exception, $request);

        $this->assertNotNull($response);
        $this->assertSame(422, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertSame('SYS_VALIDATION_FAILED', $data['error']['code']);
        $this->assertArrayHasKey('errors', $data['error']['details']);
    }

    public function test_error_response_renders_http_exception(): void
    {
        $request = Request::create('/api/test', 'GET');
        $exception = new NotFoundHttpException('Resource not found.');

        $response = ErrorResponse::render($exception, $request);

        $this->assertNotNull($response);
        $this->assertSame(404, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertSame('SYS_NOT_FOUND', $data['error']['code']);
    }

    public function test_error_response_hides_internal_errors_in_production(): void
    {
        // Simulate non-debug mode
        config(['app.debug' => false]);

        $request = Request::create('/api/test', 'GET');
        $exception = new \RuntimeException('SQLSTATE[HY000]: password leaked');

        $response = ErrorResponse::render($exception, $request);

        // Should return null (we don't handle unknown exceptions)
        $this->assertNull($response);
    }
}
