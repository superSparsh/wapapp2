<?php

declare(strict_types=1);

namespace App\Support;

final class BuiltinVariableCatalog
{
    /** @var array<string, string> */
    private const VARIABLES = [
        'first_name' => 'First name from incoming message',
        'last_name' => 'Last name from incoming message',
        'full_name' => 'Full name from incoming message',
        'display_name' => 'Display name from incoming message',
        'phone_number' => 'Sender phone number',
        'recipient_number' => 'Recipient phone number',
        'message_text' => 'Message content',
        'message_id' => 'Unique message ID',
        'timestamp' => 'Message timestamp',
        'message_type' => 'Type of message (TEXT, IMAGE, etc.)',
        'subscriber_first_name' => 'Subscriber first name from database',
        'subscriber_last_name' => 'Subscriber last name from database',
        'subscriber_full_name' => 'Subscriber full name from database',
        'subscriber_uid' => 'Subscriber unique ID',
        'subscriber_status' => 'Subscriber status',
        'subscriber_created_at' => 'Subscriber creation date',
        'subscriber_updated_at' => 'Subscriber last update date',
        'current_date' => 'Current date (Y-m-d)',
        'current_time' => 'Current time (H:i:s)',
        'current_datetime' => 'Current date and time',
        'current_day' => 'Current day name',
        'current_month' => 'Current month name',
        'current_year' => 'Current year',
        'current_timestamp' => 'Current Unix timestamp',
        'timezone' => 'Current timezone',
    ];

    /** @return list<array{name: string, display_name: string, description: string, syntax: string, category: string, type: string}> */
    public function all(): array
    {
        $items = [];

        foreach (self::VARIABLES as $name => $description) {
            $items[] = [
                'name' => $name,
                'display_name' => ucwords(str_replace('_', ' ', $name)),
                'description' => $description,
                'syntax' => '$('.$name.')',
                'category' => $this->categoryFor($name),
                'type' => 'built-in',
            ];
        }

        return $items;
    }

    private function categoryFor(string $name): string
    {
        if (str_starts_with($name, 'subscriber_')) {
            return 'subscriber';
        }

        if (str_starts_with($name, 'current_') || $name === 'timezone') {
            return 'datetime';
        }

        return 'message';
    }
}
