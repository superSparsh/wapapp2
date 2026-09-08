<?php

namespace Tests\Feature\Inbox;

use App\Enums\ConversationResponseType;
use App\Models\Contact;
use App\Models\Conversation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class InboxAddContactTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
    }

    protected function tearDown(): void
    {
        $this->tearDownTenant();
        parent::tearDown();
    }

    public function test_user_can_add_new_inbox_contact(): void
    {
        $this->actingAsTenantUser()
            ->postJson(route('inbox.api.contacts.store'), [
                'name' => 'New Customer',
                'country_code' => '91',
                'phone' => '9876543210',
                'response_type' => ConversationResponseType::Human->value,
            ])
            ->assertCreated()
            ->assertJsonPath('ok', true);

        $this->assertDatabaseHas('contacts', [
            'phone' => '919876543210',
            'name' => 'New Customer',
        ]);

        $this->assertDatabaseHas('conversations', [
            'contact_phone' => '919876543210',
            'contact_name' => 'New Customer',
            'response_type' => ConversationResponseType::Human->value,
        ]);
    }

    public function test_duplicate_contact_is_rejected(): void
    {
        $contact = Contact::factory()->create(['phone' => '919876543210']);
        Conversation::factory()->create([
            'whatsapp_line_id' => $this->testLine->id,
            'contact_id' => $contact->id,
            'contact_phone' => $contact->phone,
            'line_phone' => $this->testLine->phone,
            'last_message_at' => now(),
        ]);

        $this->actingAsTenantUser()
            ->postJson(route('inbox.api.contacts.store'), [
                'name' => 'Duplicate',
                'country_code' => '91',
                'phone' => '9876543210',
                'response_type' => ConversationResponseType::Human->value,
            ])
            ->assertStatus(422);
    }
}
