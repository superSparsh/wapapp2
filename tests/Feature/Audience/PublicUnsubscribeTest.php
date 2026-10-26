<?php

declare(strict_types=1);

namespace Tests\Feature\Audience;

use App\Domains\Audience\Enums\ContactStatus;
use App\Domains\Audience\Support\UnsubscribeToken;
use App\Enums\ContactOptInStatus;
use App\Models\Contact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class PublicUnsubscribeTest extends TestCase
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

    public function test_guest_can_unsubscribe_from_the_public_link(): void
    {
        $contact = Contact::factory()->create([
            'status' => ContactStatus::Subscribed,
            'opt_in_status' => ContactOptInStatus::OptedIn,
        ]);
        $token = UnsubscribeToken::forContact($contact);

        $this->get(route('unsubscribe.show', ['token' => $token]))
            ->assertOk()
            ->assertSee('Confirm to stop promotional WhatsApp messages');

        $this->post(route('unsubscribe.store', ['token' => $token]))
            ->assertOk()
            ->assertSee('You are unsubscribed');

        $this->ensureTenant();
        $contact->refresh();

        $this->assertSame(ContactStatus::Unsubscribed, $contact->status);
        $this->assertSame(ContactOptInStatus::OptedOut, $contact->opt_in_status);
        $this->assertNotNull($contact->opted_out_at);
    }

    public function test_confirming_again_says_already_unsubscribed(): void
    {
        $contact = Contact::factory()->create([
            'status' => ContactStatus::Subscribed,
        ]);
        $token = UnsubscribeToken::forContact($contact);

        $this->post(route('unsubscribe.store', ['token' => $token]))->assertOk();
        $this->post(route('unsubscribe.store', ['token' => $token]))
            ->assertOk()
            ->assertSee('You are already unsubscribed');
    }

    public function test_old_numeric_link_does_not_change_the_contact(): void
    {
        $contact = Contact::factory()->create([
            'status' => ContactStatus::Subscribed,
        ]);

        $this->post(route('unsubscribe.store', ['token' => (string) $contact->id]))
            ->assertOk()
            ->assertSee('Reply STOP on WhatsApp');

        $this->ensureTenant();
        $contact->refresh();

        $this->assertSame(ContactStatus::Subscribed, $contact->status);
    }

    private function ensureTenant(): void
    {
        if (! tenancy()->initialized) {
            tenancy()->initialize($this->testTenant);
        }
    }
}
