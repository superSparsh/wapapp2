<?php

declare(strict_types=1);

namespace App\Enums;

enum WhatsappFlowSubmitAction: string
{
    case CreateLead = 'create_lead';
    case UpdateContact = 'update_contact';
    case Webhook = 'webhook';
    case None = 'none';

    public function label(): string
    {
        return match ($this) {
            self::CreateLead => 'Create Lead',
            self::UpdateContact => 'Update Contact',
            self::Webhook => 'Send to Webhook',
            self::None => 'Do Nothing',
        };
    }
}
