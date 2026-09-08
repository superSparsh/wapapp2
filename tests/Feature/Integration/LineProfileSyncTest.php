<?php

declare(strict_types=1);

namespace Tests\Feature\Integration;

use App\Domains\Inbox\Contracts\OutboundMessageGateway;
use App\Domains\Integration\Services\LineProfileService;
use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
use App\Enums\MessageType;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\WhatsappLine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class LineProfileSyncTest extends TestCase
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

    public function test_sync_from_provider_updates_quality_and_tier(): void
    {
        config([
            'whatsapp.alibaba.access_key_id' => 'test-key',
            'whatsapp.alibaba.access_key_secret' => 'test-secret',
            'whatsapp.alibaba.endpoint' => 'cams.test.local',
        ]);

        $line = WhatsappLine::factory()->connected()->defaultLine()->create([
            'phone' => '919876543210',
            'waba_id' => 'WABA123',
            'alibaba_cust_space_id' => 'SPACE1',
            'quality_rating' => 'YELLOW',
            'messaging_limit_tier' => 'TIER_1K',
        ]);

        Http::fake([
            'https://cams.test.local/*' => Http::sequence()
                ->push([
                    'Code' => 'OK',
                    'Data' => ['CustSpaceId' => 'SPACE1'],
                ], 200)
                ->push([
                    'Code' => 'OK',
                    'PhoneNumbers' => [[
                        'phoneNumber' => '919876543210',
                        'qualityRating' => 'GREEN',
                        'messagingLimitTier' => 'TIER_10K',
                        'verifiedName' => 'Acme Biz',
                        'status' => 'CONNECTED',
                    ]],
                ], 200)
                ->push(['Code' => 'OK'], 200) // webhook
                ->push([
                    'Code' => 'OK',
                    'Data' => [
                        'businessId' => 'BID1',
                        'businessName' => 'Acme Corp',
                        'verificationStatus' => 'verified',
                        'vertical' => 'OTHER',
                    ],
                ], 200),
        ]);

        app(LineProfileService::class)->syncFromProvider($line);

        $line->refresh();
        $this->assertSame('GREEN', $line->quality_rating);
        $this->assertSame('TIER_10K', $line->messaging_limit_tier);
        $this->assertSame('Acme Biz', $line->display_name);
        $this->assertSame('Acme Corp', $line->metadata['business_name'] ?? null);
    }

    public function test_sync_fails_when_cams_not_configured(): void
    {
        config([
            'whatsapp.alibaba.access_key_id' => '',
            'whatsapp.alibaba.access_key_secret' => '',
        ]);

        $line = WhatsappLine::factory()->connected()->defaultLine()->create();

        $this->expectException(\RuntimeException::class);
        app(LineProfileService::class)->syncFromProvider($line);
    }

    public function test_profile_update_calls_modify_phone_business_profile(): void
    {
        config([
            'whatsapp.alibaba.access_key_id' => 'test-key',
            'whatsapp.alibaba.access_key_secret' => 'test-secret',
            'whatsapp.alibaba.endpoint' => 'cams.test.local',
        ]);

        Http::fake([
            'https://cams.test.local/*' => Http::response(['Code' => 'OK'], 200),
        ]);

        $line = WhatsappLine::factory()->connected()->defaultLine()->create([
            'phone' => '919876543210',
        ]);

        app(LineProfileService::class)->updateProfile($line, [
            'email' => 'hello@acme.test',
            'website' => 'https://acme.test',
            'address' => 'Delhi',
            'description' => 'We sell things',
            'about' => 'We sell things',
        ]);

        Http::assertSent(function ($request) {
            $url = $request->url();

            return str_contains($url, 'Action=ModifyPhoneBusinessProfile')
                || str_contains((string) $request->body(), 'ModifyPhoneBusinessProfile')
                || collect($request->data())->contains('ModifyPhoneBusinessProfile');
        });

        $line->refresh();
        $this->assertSame('hello@acme.test', $line->profile['email'] ?? null);
    }

    public function test_outbound_fails_when_cams_not_configured(): void
    {
        config([
            'whatsapp.outbound_driver' => 'alibaba',
            'whatsapp.alibaba.access_key_id' => '',
            'whatsapp.alibaba.access_key_secret' => '',
        ]);

        $line = WhatsappLine::factory()->connected()->create();
        $contact = Contact::factory()->create();
        $conversation = Conversation::factory()->create([
            'whatsapp_line_id' => $line->id,
            'contact_id' => $contact->id,
        ]);
        $message = Message::query()->create([
            'conversation_id' => $conversation->id,
            'body' => 'hello',
            'direction' => MessageDirection::Outbound,
            'message_type' => MessageType::Text,
            'status' => MessageStatus::Queued,
        ]);

        app(OutboundMessageGateway::class)->send($message->fresh());

        $message->refresh();
        $this->assertSame(MessageStatus::Failed, $message->status);
        $this->assertStringContainsString('not configured', (string) $message->failed_reason);
    }

    public function test_sync_endpoint_shows_success_flash(): void
    {
        config([
            'whatsapp.alibaba.access_key_id' => 'test-key',
            'whatsapp.alibaba.access_key_secret' => 'test-secret',
            'whatsapp.alibaba.endpoint' => 'cams.test.local',
        ]);

        WhatsappLine::query()->delete();
        WhatsappLine::factory()->connected()->defaultLine()->create([
            'phone' => '919876543210',
            'waba_id' => 'WABA123',
            'alibaba_cust_space_id' => 'SPACE1',
        ]);

        Http::fake([
            'https://cams.test.local/*' => Http::response([
                'Code' => 'OK',
                'Data' => ['CustSpaceId' => 'SPACE1'],
                'PhoneNumbers' => [],
            ], 200),
        ]);

        $this->actingAsTenantUser()
            ->post(route('profile.integration.sync'))
            ->assertRedirect(route('profile.integration'))
            ->assertSessionHas('status');
    }
}
