<?php

declare(strict_types=1);

namespace App\Domains\MobileApi\Http\Controllers;

use App\Domains\Auth\Exceptions\AccountInactiveException;
use App\Domains\Auth\Exceptions\InvalidCredentialsException;
use App\Domains\Auth\Exceptions\InvalidOtpException;
use App\Domains\Auth\Exceptions\OtpDeliveryException;
use App\Domains\Auth\Exceptions\OtpExpiredException;
use App\Domains\Auth\Exceptions\PhoneNotRegisteredException;
use App\Domains\MobileApi\Services\MobileAuthService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    public function __construct(
        private readonly MobileAuthService $auth,
    ) {}

    public function register(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:20'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
            'timezone' => ['required', 'string', 'max:64'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'administrative_access' => ['accepted'],
            'official_business_website' => ['accepted'],
            'whatsapp_commerce_policy' => ['accepted'],
            'existing_whatsapp_account' => ['accepted'],
            'whatsapp_business_terms_services' => ['accepted'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $result = $this->auth->register($validator->validated());
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Registration failed',
                'error' => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'User registered successfully',
            'data' => array_merge(
                ['user' => $this->auth->profilePayload($result['user'], $result['tenant_id'])],
                $result['tokens'],
            ),
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $result = $this->auth->loginWithPassword(
                (string) $request->input('email'),
                (string) $request->input('password'),
            );
        } catch (InvalidCredentialsException) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials',
            ], 401);
        } catch (AccountInactiveException) {
            return response()->json([
                'success' => false,
                'message' => 'Account is not activated. Please contact support.',
            ], 403);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Login failed',
                'error' => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'data' => array_merge(
                ['user' => $this->auth->profilePayload($result['user'], $result['tenant_id'])],
                $result['tokens'],
            ),
        ]);
    }

    public function sendOtp(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'phone' => ['required', 'string', 'max:20'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $this->auth->sendOtp((string) $request->input('phone'));
        } catch (PhoneNotRegisteredException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage() ?: 'This phone number is not registered',
            ], 404);
        } catch (OtpDeliveryException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 429);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Failed to send OTP',
                'error' => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'OTP sent successfully',
        ]);
    }

    public function otpLogin(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'phone' => ['required', 'string', 'max:20'],
            'otp' => ['required', 'string', 'size:6'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $result = $this->auth->loginWithOtp(
                (string) $request->input('phone'),
                (string) $request->input('otp'),
            );
        } catch (PhoneNotRegisteredException) {
            return response()->json([
                'success' => false,
                'message' => 'This phone number is not registered',
            ], 404);
        } catch (OtpExpiredException) {
            return response()->json([
                'success' => false,
                'message' => 'OTP has expired. Please request a new one.',
            ], 400);
        } catch (InvalidOtpException) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid OTP. Please try again.',
            ], 400);
        } catch (AccountInactiveException) {
            return response()->json([
                'success' => false,
                'message' => 'Your account is not activated. Please contact support.',
            ], 403);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'OTP login failed',
                'error' => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'OTP login successful',
            'data' => array_merge(
                ['user' => $this->auth->profilePayload($result['user'], $result['tenant_id'])],
                $result['tokens'],
            ),
        ]);
    }

    public function refresh(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'refresh_token' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $result = $this->auth->refresh((string) $request->input('refresh_token'));
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Token refresh failed',
                'error' => $e->getMessage(),
            ], 401);
        }

        return response()->json([
            'success' => true,
            'message' => 'Token refreshed successfully',
            'data' => array_merge(
                ['user' => $this->auth->profilePayload($result['user'], $result['tenant_id'])],
                $result['tokens'],
            ),
        ]);
    }

    public function logout(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully',
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $tenantId = (string) (tenant('id') ?? '');

        return response()->json([
            'success' => true,
            'data' => [
                'user' => $this->auth->profilePayload($user, $tenantId),
            ],
        ]);
    }
}
