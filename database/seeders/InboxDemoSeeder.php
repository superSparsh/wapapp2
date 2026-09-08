<?php

namespace Database\Seeders;

use App\Enums\ContactOptInStatus;
use App\Domains\Inbox\Services\InboxMessageService;
use App\Enums\ConversationStatus;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\WhatsappLine;
use Illuminate\Database\Seeder;

class InboxDemoSeeder extends Seeder
{
    public function run(): void
    {
        $line = WhatsappLine::query()->where('is_default', true)->first()
            ?? WhatsappLine::query()->first();

        if ($line === null) {
            return;
        }

        $messageService = app(InboxMessageService::class);

        $demoContacts = [
            ['name' => 'Shan Khan', 'phone' => '918217312289'],
            ['name' => 'Priya Sharma', 'phone' => '919876543210'],
            ['name' => 'Rahul Verma', 'phone' => '919988776655'],
            ['name' => 'Aisha Patel', 'phone' => '917700112233'],
        ];

        foreach ($demoContacts as $index => $demo) {
            $contact = Contact::query()->firstOrCreate(
                ['phone' => $demo['phone']],
                ['name' => $demo['name'], 'opt_in_status' => ContactOptInStatus::OptedIn, 'opted_in_at' => now()],
            );

            $conversation = Conversation::query()->firstOrCreate(
                [
                    'whatsapp_line_id' => $line->id,
                    'contact_phone' => $contact->phone,
                ],
                [
                    'contact_id' => $contact->id,
                    'line_phone' => $line->phone,
                    'contact_name' => $contact->name,
                    'status' => ConversationStatus::Open,
                    'response_type' => 'human_response',
                    'unread_count' => $index === 0 ? 2 : 0,
                    'last_message_at' => now()->subMinutes($index * 15),
                ],
            );

            if ($conversation->messages()->exists()) {
                continue;
            }

            $messageService->recordInbound(
                $conversation,
                'Hi, I wanted to know more about your services.',
                'demo-in-'.$conversation->id.'-1',
            );

            if ($index === 0) {
                $messageService->recordInbound(
                    $conversation,
                    'Are you available for a quick call today?',
                    'demo-in-'.$conversation->id.'-2',
                );
            }

            $messageService->sendText(
                $conversation,
                'Thanks for reaching out! How can we help you today?',
            );
        }
    }
}
