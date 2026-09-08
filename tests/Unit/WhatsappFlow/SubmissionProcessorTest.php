<?php

declare(strict_types=1);

namespace Tests\Unit\WhatsappFlow;

use App\Domains\WhatsappFlow\Services\SubmissionProcessorService;
use App\Enums\WhatsappFlowSubmitAction;
use App\Models\Contact;
use App\Models\WhatsappFlow;
use App\Models\WhatsappFlowSubmission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class SubmissionProcessorTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    private SubmissionProcessorService $processor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->processor = new SubmissionProcessorService();
    }

    protected function tearDown(): void
    {
        $this->tearDownTenant();
        parent::tearDown();
    }

    public function test_create_lead_action_creates_contact(): void
    {
        $flow = WhatsappFlow::factory()->create(['on_submit_action' => WhatsappFlowSubmitAction::CreateLead]);
        $submission = WhatsappFlowSubmission::factory()->for($flow, 'whatsappFlow')->create([
            'contact_phone' => '+919999999999',
            'form_data' => ['full_name' => 'John Doe', 'email' => 'john@test.com'],
        ]);

        $this->processor->processSubmission($submission, $flow);

        $this->assertDatabaseHas('contacts', [
            'phone' => '+919999999999',
            'name' => 'John Doe',
            'email' => 'john@test.com',
        ]);
        $submission->refresh();
        $this->assertSame('processed', $submission->status);
    }

    public function test_create_lead_updates_existing_contact(): void
    {
        Contact::query()->create([
            'phone' => '+919999999999',
            'name' => 'Old Name',
            'email' => 'old@test.com',
            'source' => 'manual',
        ]);

        $flow = WhatsappFlow::factory()->create(['on_submit_action' => WhatsappFlowSubmitAction::CreateLead]);
        $submission = WhatsappFlowSubmission::factory()->for($flow, 'whatsappFlow')->create([
            'contact_phone' => '+919999999999',
            'form_data' => ['full_name' => 'Updated Name', 'email' => 'updated@test.com'],
        ]);

        $this->processor->processSubmission($submission, $flow);

        $contact = Contact::query()->where('phone', '+919999999999')->first();
        $this->assertSame('Updated Name', $contact->name);
        $this->assertSame('updated@test.com', $contact->email);
        $this->assertDatabaseCount('contacts', 1);
    }

    public function test_update_contact_action_updates_existing(): void
    {
        Contact::query()->create([
            'phone' => '+919888888888',
            'name' => 'Old Name',
            'email' => 'old@test.com',
            'source' => 'manual',
        ]);

        $flow = WhatsappFlow::factory()->create(['on_submit_action' => WhatsappFlowSubmitAction::UpdateContact]);
        $submission = WhatsappFlowSubmission::factory()->for($flow, 'whatsappFlow')->create([
            'contact_phone' => '+919888888888',
            'form_data' => ['full_name' => 'New Name'],
        ]);

        $this->processor->processSubmission($submission, $flow);

        $contact = Contact::query()->where('phone', '+919888888888')->first();
        $this->assertSame('New Name', $contact->name);
    }

    public function test_update_contact_does_nothing_if_not_found(): void
    {
        $flow = WhatsappFlow::factory()->create(['on_submit_action' => WhatsappFlowSubmitAction::UpdateContact]);
        $submission = WhatsappFlowSubmission::factory()->for($flow, 'whatsappFlow')->create([
            'contact_phone' => '+917777777777',
            'form_data' => ['full_name' => 'Missing'],
        ]);

        $this->processor->processSubmission($submission, $flow);

        $this->assertDatabaseCount('contacts', 0);
    }

    public function test_webhook_action_sends_http_post(): void
    {
        Http::fake(['*' => Http::response(['ok' => true], 200)]);

        $flow = WhatsappFlow::factory()->create([
            'on_submit_action' => WhatsappFlowSubmitAction::Webhook,
            'on_submit_webhook_url' => 'https://webhook.test/receive',
        ]);
        $submission = WhatsappFlowSubmission::factory()->for($flow, 'whatsappFlow')->create([
            'contact_phone' => '+919666666666',
            'form_data' => ['name' => 'Test'],
        ]);

        $this->processor->processSubmission($submission, $flow);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://webhook.test/receive'
                && $request['event'] === 'whatsapp_flow_submission';
        });
    }

    public function test_none_action_marks_processed_without_side_effects(): void
    {
        $flow = WhatsappFlow::factory()->create(['on_submit_action' => WhatsappFlowSubmitAction::None]);
        $submission = WhatsappFlowSubmission::factory()->for($flow, 'whatsappFlow')->create();

        $this->processor->processSubmission($submission, $flow);

        $submission->refresh();
        $this->assertSame('processed', $submission->status);
        $this->assertDatabaseCount('contacts', 0);
    }

    public function test_null_action_marks_processed(): void
    {
        $flow = WhatsappFlow::factory()->create(['on_submit_action' => null]);
        $submission = WhatsappFlowSubmission::factory()->for($flow, 'whatsappFlow')->create();

        $this->processor->processSubmission($submission, $flow);

        $submission->refresh();
        $this->assertSame('processed', $submission->status);
    }
}
