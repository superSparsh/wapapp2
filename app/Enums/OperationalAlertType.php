<?php

declare(strict_types=1);

namespace App\Enums;

enum OperationalAlertType: string
{
    case PlanExpiration = 'plan_expiration';
    case InboxNewMessage = 'inbox_new_message';
    case LowWallet = 'low_wallet';
    case WalletCreditRequested = 'wallet_credit_requested';
    case WalletCreditCompleted = 'wallet_credit_completed';
    case PhoneQualityChanged = 'phone_quality_changed';
    case AccountPurged = 'account_purged';
    case ReadinessSubmitted = 'readiness_submitted';
    case ReadinessEligible = 'readiness_eligible';
    case ReadinessNotEligible = 'readiness_not_eligible';
    case CloudBillUploaded = 'cloud_bill_uploaded';
    case WhatsappHealthDigest = 'whatsapp_health_digest';
    case AccountExpirationReport = 'account_expiration_report';
    case DeveloperError = 'developer_error';
    case CalendlyEvent = 'calendly_event';
    case GoogleCalendarEvent = 'google_calendar_event';

    public function label(): string
    {
        return match ($this) {
            self::PlanExpiration => 'Plan expiration reminder',
            self::InboxNewMessage => 'New inbox message',
            self::LowWallet => 'Low wallet balance',
            self::WalletCreditRequested => 'Wallet credit requested',
            self::WalletCreditCompleted => 'Wallet credit completed',
            self::PhoneQualityChanged => 'Phone quality / tier change',
            self::AccountPurged => 'Account data purged',
            self::ReadinessSubmitted => 'Customer readiness submitted',
            self::ReadinessEligible => 'Customer readiness eligible',
            self::ReadinessNotEligible => 'Customer readiness not eligible',
            self::CloudBillUploaded => 'Cloud bill uploaded',
            self::WhatsappHealthDigest => 'WhatsApp health digest',
            self::AccountExpirationReport => 'Account expiration report',
            self::DeveloperError => 'Developer error alert',
            self::CalendlyEvent => 'Calendly booking alert',
            self::GoogleCalendarEvent => 'Google Calendar booking alert',
        };
    }
}
