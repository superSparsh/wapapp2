<?php

declare(strict_types=1);

namespace App\Shared\Exceptions;

/**
 * Centralised error code registry.
 *
 * Every code follows the pattern: {DOMAIN}_{DESCRIPTION}.
 * These codes appear in API JSON error responses so that front-end
 * and external consumers can react programmatically.
 */
enum ErrorCode: string
{
    // ── Authentication ──
    case AUTH_INVALID_CREDENTIALS = 'AUTH_INVALID_CREDENTIALS';
    case AUTH_TOKEN_EXPIRED = 'AUTH_TOKEN_EXPIRED';
    case AUTH_UNAUTHENTICATED = 'AUTH_UNAUTHENTICATED';
    case AUTH_FORBIDDEN = 'AUTH_FORBIDDEN';
    case AUTH_TWO_FACTOR_REQUIRED = 'AUTH_TWO_FACTOR_REQUIRED';
    case AUTH_TWO_FACTOR_INVALID = 'AUTH_TWO_FACTOR_INVALID';
    case AUTH_EMAIL_NOT_VERIFIED = 'AUTH_EMAIL_NOT_VERIFIED';
    case AUTH_ACCOUNT_INACTIVE = 'AUTH_ACCOUNT_INACTIVE';

    // ── Tenancy ──
    case TENANT_NOT_FOUND = 'TENANT_NOT_FOUND';
    case TENANT_SUSPENDED = 'TENANT_SUSPENDED';
    case TENANT_LIMIT_EXCEEDED = 'TENANT_LIMIT_EXCEEDED';
    case TENANT_PROVISIONING_FAILED = 'TENANT_PROVISIONING_FAILED';

    // ── Messaging ──
    case MSG_SEND_FAILED = 'MSG_SEND_FAILED';
    case MSG_TEMPLATE_NOT_APPROVED = 'MSG_TEMPLATE_NOT_APPROVED';
    case MSG_MEDIA_TOO_LARGE = 'MSG_MEDIA_TOO_LARGE';
    case MSG_RATE_LIMITED = 'MSG_RATE_LIMITED';
    case MSG_INVALID_RECIPIENT = 'MSG_INVALID_RECIPIENT';

    // ── Campaigns ──
    case CAMP_AUDIENCE_EMPTY = 'CAMP_AUDIENCE_EMPTY';
    case CAMP_ALREADY_RUNNING = 'CAMP_ALREADY_RUNNING';
    case CAMP_NOT_FOUND = 'CAMP_NOT_FOUND';
    case CAMP_TEMPLATE_MISSING = 'CAMP_TEMPLATE_MISSING';
    case CAMP_INSUFFICIENT_BALANCE = 'CAMP_INSUFFICIENT_BALANCE';

    // ── Wallet / Billing ──
    case WALLET_INSUFFICIENT_BALANCE = 'WALLET_INSUFFICIENT_BALANCE';
    case WALLET_TRANSACTION_FAILED = 'WALLET_TRANSACTION_FAILED';
    case WALLET_TOPUP_MINIMUM = 'WALLET_TOPUP_MINIMUM';

    // ── Webhooks ──
    case WEBHOOK_INVALID_SIGNATURE = 'WEBHOOK_INVALID_SIGNATURE';
    case WEBHOOK_DUPLICATE = 'WEBHOOK_DUPLICATE';
    case WEBHOOK_DELIVERY_FAILED = 'WEBHOOK_DELIVERY_FAILED';

    // ── WhatsApp Flows ──
    case FLOW_NOT_FOUND = 'FLOW_NOT_FOUND';
    case FLOW_ALREADY_PUBLISHED = 'FLOW_ALREADY_PUBLISHED';
    case FLOW_META_SYNC_FAILED = 'FLOW_META_SYNC_FAILED';

    // ── Chatbot ──
    case CHATBOT_FLOW_INVALID = 'CHATBOT_FLOW_INVALID';
    case CHATBOT_NODE_NOT_FOUND = 'CHATBOT_NODE_NOT_FOUND';

    // ── System / General ──
    case SYS_SERVICE_UNAVAILABLE = 'SYS_SERVICE_UNAVAILABLE';
    case SYS_RATE_LIMITED = 'SYS_RATE_LIMITED';
    case SYS_VALIDATION_FAILED = 'SYS_VALIDATION_FAILED';
    case SYS_NOT_FOUND = 'SYS_NOT_FOUND';
    case SYS_INTERNAL_ERROR = 'SYS_INTERNAL_ERROR';
    case SYS_CIRCUIT_OPEN = 'SYS_CIRCUIT_OPEN';

    /**
     * Default HTTP status code for this error.
     */
    public function defaultHttpStatus(): int
    {
        return match ($this) {
            self::AUTH_INVALID_CREDENTIALS,
            self::AUTH_TWO_FACTOR_INVALID => 401,

            self::AUTH_UNAUTHENTICATED,
            self::AUTH_TOKEN_EXPIRED => 401,

            self::AUTH_FORBIDDEN,
            self::AUTH_ACCOUNT_INACTIVE,
            self::TENANT_SUSPENDED => 403,

            self::AUTH_TWO_FACTOR_REQUIRED,
            self::AUTH_EMAIL_NOT_VERIFIED => 403,

            self::TENANT_NOT_FOUND,
            self::CAMP_NOT_FOUND,
            self::FLOW_NOT_FOUND,
            self::CHATBOT_NODE_NOT_FOUND,
            self::SYS_NOT_FOUND => 404,

            self::SYS_VALIDATION_FAILED => 422,

            self::CAMP_AUDIENCE_EMPTY,
            self::TENANT_LIMIT_EXCEEDED,
            self::CAMP_INSUFFICIENT_BALANCE,
            self::WALLET_INSUFFICIENT_BALANCE,
            self::WALLET_TOPUP_MINIMUM => 422,

            self::MSG_RATE_LIMITED,
            self::SYS_RATE_LIMITED => 429,

            self::CAMP_ALREADY_RUNNING,
            self::FLOW_ALREADY_PUBLISHED,
            self::WEBHOOK_DUPLICATE => 409,

            self::SYS_CIRCUIT_OPEN,
            self::SYS_SERVICE_UNAVAILABLE,
            self::MSG_SEND_FAILED,
            self::MSG_TEMPLATE_NOT_APPROVED,
            self::CAMP_TEMPLATE_MISSING,
            self::FLOW_META_SYNC_FAILED,
            self::WEBHOOK_DELIVERY_FAILED,
            self::WALLET_TRANSACTION_FAILED,
            self::TENANT_PROVISIONING_FAILED => 503,

            self::MSG_MEDIA_TOO_LARGE => 413,
            self::MSG_INVALID_RECIPIENT,
            self::CHATBOT_FLOW_INVALID,
            self::WEBHOOK_INVALID_SIGNATURE => 400,

            self::SYS_INTERNAL_ERROR => 500,
        };
    }
}
