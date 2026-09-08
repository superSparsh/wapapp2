<?php

declare(strict_types=1);

namespace Tests\Feature\Audience;

use App\Domains\Audience\Enums\ContactStatus;
use App\Domains\Audience\Models\ContactTag;
use App\Enums\ContactOptInStatus;
use App\Models\Contact;
use App\Models\MailList;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class ContactTest extends TestCase
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

    // ─── Index / Listing ─────────────────────────────────────────────────────

    public function test_subscribers_index_requires_auth(): void
    {
        $this->get(route('audience.subscribers'))->assertRedirect();
    }

    public function test_subscribers_index_page_loads(): void
    {
        $this->actingAsTenantUser()
            ->get(route('audience.subscribers'))
            ->assertOk();
    }

    public function test_subscribers_index_shows_add_subscriber_when_empty(): void
    {
        $this->actingAsTenantUser()
            ->get(route('audience.subscribers'))
            ->assertOk()
            ->assertViewIs('audience.subscribers')
            ->assertSee('Add New Subscribers')
            ->assertSee('New subscriber');
    }

    public function test_subscribers_index_shows_contacts(): void
    {
        Contact::factory()->count(5)->create();

        $this->actingAsTenantUser()
            ->get(route('audience.subscribers'))
            ->assertOk()
            ->assertViewIs('audience.subscribers')
            ->assertViewHas('contacts', function ($contacts) {
                return $contacts->total() === 5;
            });
    }

    public function test_subscribers_index_filters_by_mail_list(): void
    {
        $list1 = MailList::factory()->create();
        $list2 = MailList::factory()->create();
        Contact::factory()->count(3)->create(['mail_list_id' => $list1->id]);
        Contact::factory()->count(2)->create(['mail_list_id' => $list2->id]);

        $this->actingAsTenantUser()
            ->get(route('audience.subscribers', ['list' => $list1->uuid]))
            ->assertOk()
            ->assertViewHas('contacts', function ($contacts) {
                return $contacts->total() === 3;
            });
    }

    public function test_subscribers_index_search_filters_contacts(): void
    {
        Contact::factory()->create(['name' => 'John Doe', 'phone' => '919876543210']);
        Contact::factory()->create(['name' => 'Jane Smith', 'phone' => '919876543211']);

        $this->actingAsTenantUser()
            ->get(route('audience.subscribers', ['search' => 'John']))
            ->assertOk()
            ->assertViewHas('contacts', function ($contacts) {
                return $contacts->total() === 1 && $contacts->first()->name === 'John Doe';
            });
    }

    public function test_subscribers_index_filters_by_status(): void
    {
        Contact::factory()->count(3)->create(['status' => ContactStatus::Subscribed]);
        Contact::factory()->count(2)->create(['status' => ContactStatus::Unsubscribed]);

        $this->actingAsTenantUser()
            ->get(route('audience.subscribers', ['status' => 'subscribed']))
            ->assertOk()
            ->assertViewHas('contacts', function ($contacts) {
                return $contacts->total() === 3;
            });
    }

    // ─── Detail ──────────────────────────────────────────────────────────────

    public function test_contact_detail_page_loads(): void
    {
        $contact = Contact::factory()->create();

        $this->actingAsTenantUser()
            ->get(route('audience.subscribers.detail', ['id' => $contact->id]))
            ->assertOk()
            ->assertViewIs('audience.subscribers-detail')
            ->assertViewHas('contact', function ($c) use ($contact) {
                return $c->id === $contact->id;
            });
    }

    public function test_contact_detail_shows_tags_and_mail_list(): void
    {
        $list = MailList::factory()->create();
        $contact = Contact::factory()->create(['mail_list_id' => $list->id]);
        ContactTag::factory()->count(3)->create(['contact_id' => $contact->id]);

        $this->actingAsTenantUser()
            ->get(route('audience.subscribers.detail', ['id' => $contact->id]))
            ->assertOk()
            ->assertViewHas('contact', function ($c) {
                return $c->tags->count() === 3 && $c->mailList !== null;
            });
    }

    // ─── Store ───────────────────────────────────────────────────────────────

    public function test_store_requires_phone(): void
    {
        $this->actingAsTenantUser()
            ->post(route('audience.subscribers.store'), [])
            ->assertSessionHasErrors('phone');
    }

    public function test_store_creates_contact(): void
    {
        $list = MailList::factory()->create();

        $this->actingAsTenantUser()
            ->post(route('audience.subscribers.store'), [
                'phone' => '919876543210',
                'name' => 'Test Contact',
                'email' => 'test@example.com',
                'mail_list_id' => $list->uuid,
                'tags' => ['vip', 'new'],
            ])
            ->assertRedirect(route('audience.subscribers', ['list' => $list->uuid]))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('contacts', [
            'phone' => '919876543210',
            'name' => 'Test Contact',
            'email' => 'test@example.com',
        ]);

        $contact = Contact::where('phone', '919876543210')->first();
        $this->assertEquals(2, $contact->tags->count());
    }

    public function test_store_sets_default_status(): void
    {
        $this->actingAsTenantUser()
            ->post(route('audience.subscribers.store'), [
                'phone' => '919876543210',
            ])
            ->assertRedirect(route('audience.subscribers'));

        $contact = Contact::where('phone', '919876543210')->first();
        $this->assertEquals(ContactStatus::Subscribed, $contact->status);
        $this->assertEquals(ContactOptInStatus::OptedIn, $contact->opt_in_status);
    }

    // ─── Update ──────────────────────────────────────────────────────────────

    public function test_update_requires_phone(): void
    {
        $contact = Contact::factory()->create();

        $this->actingAsTenantUser()
            ->put(route('audience.subscribers.update', $contact), ['phone' => ''])
            ->assertSessionHasErrors('phone');
    }

    public function test_update_modifies_contact(): void
    {
        $contact = Contact::factory()->create(['name' => 'Old Name']);

        $this->actingAsTenantUser()
            ->put(route('audience.subscribers.update', $contact), [
                'phone' => $contact->phone,
                'name' => 'New Name',
            ])
            ->assertRedirect(route('audience.subscribers'))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('contacts', ['id' => $contact->id, 'name' => 'New Name']);
    }

    public function test_update_can_sync_tags(): void
    {
        $contact = Contact::factory()->create();
        ContactTag::factory()->create(['contact_id' => $contact->id, 'name' => 'old-tag']);

        $this->actingAsTenantUser()
            ->put(route('audience.subscribers.update', $contact), [
                'phone' => $contact->phone,
                'tags' => ['new-tag-1', 'new-tag-2'],
            ])
            ->assertRedirect(route('audience.subscribers'));

        $contact->refresh();
        $this->assertEquals(2, $contact->tags->count());
        $this->assertTrue($contact->tags->pluck('name')->contains('new-tag-1'));
        $this->assertFalse($contact->tags->pluck('name')->contains('old-tag'));
    }

    // ─── Delete ──────────────────────────────────────────────────────────────

    public function test_destroy_deletes_contact(): void
    {
        $contact = Contact::factory()->create();

        $this->actingAsTenantUser()
            ->delete(route('audience.subscribers.destroy', $contact))
            ->assertRedirect(route('audience.subscribers'))
            ->assertSessionHas('status');

        $this->assertSoftDeleted('contacts', ['id' => $contact->id]);
    }

    // ─── Subscribe / Unsubscribe ─────────────────────────────────────────────

    public function test_subscribe_changes_status(): void
    {
        $contact = Contact::factory()->create(['status' => ContactStatus::Unsubscribed]);

        $this->actingAsTenantUser()
            ->post(route('audience.subscribers.subscribe'), [
                'ids' => [$contact->id],
            ])
            ->assertRedirect(route('audience.subscribers'))
            ->assertSessionHas('status');

        $contact->refresh();
        $this->assertEquals(ContactStatus::Subscribed, $contact->status);
        $this->assertEquals(ContactOptInStatus::OptedIn, $contact->opt_in_status);
        $this->assertNotNull($contact->opted_in_at);
    }

    public function test_unsubscribe_changes_status(): void
    {
        $contact = Contact::factory()->create(['status' => ContactStatus::Subscribed]);

        $this->actingAsTenantUser()
            ->post(route('audience.subscribers.unsubscribe'), [
                'ids' => [$contact->id],
            ])
            ->assertRedirect(route('audience.subscribers'))
            ->assertSessionHas('status');

        $contact->refresh();
        $this->assertEquals(ContactStatus::Unsubscribed, $contact->status);
        $this->assertEquals(ContactOptInStatus::OptedOut, $contact->opt_in_status);
        $this->assertNotNull($contact->opted_out_at);
    }

    public function test_bulk_subscribe_multiple_contacts(): void
    {
        $contacts = Contact::factory()->count(3)->create(['status' => ContactStatus::Unsubscribed]);

        $this->actingAsTenantUser()
            ->post(route('audience.subscribers.subscribe'), [
                'ids' => $contacts->pluck('id')->toArray(),
            ])
            ->assertRedirect(route('audience.subscribers'))
            ->assertSessionHas('status', '3 contact(s) subscribed.');

        $this->assertEquals(3, Contact::where('status', ContactStatus::Subscribed)->count());
    }

    public function test_bulk_unsubscribe_multiple_contacts(): void
    {
        $contacts = Contact::factory()->count(3)->create(['status' => ContactStatus::Subscribed]);

        $this->actingAsTenantUser()
            ->post(route('audience.subscribers.unsubscribe'), [
                'ids' => $contacts->pluck('id')->toArray(),
            ])
            ->assertRedirect(route('audience.subscribers'))
            ->assertSessionHas('status', '3 contact(s) unsubscribed.');

        $this->assertEquals(3, Contact::where('status', ContactStatus::Unsubscribed)->count());
    }

    // ─── Bulk Delete ─────────────────────────────────────────────────────────

    public function test_bulk_delete_soft_deletes_contacts(): void
    {
        $contacts = Contact::factory()->count(5)->create();

        $this->actingAsTenantUser()
            ->post(route('audience.subscribers.bulk-delete'), [
                'ids' => $contacts->pluck('id')->toArray(),
            ])
            ->assertRedirect(route('audience.subscribers'))
            ->assertSessionHas('status', '5 contact(s) deleted.');

        $this->assertEquals(5, Contact::onlyTrashed()->count());
        $this->assertEquals(0, Contact::count());
    }

    // ─── Import ──────────────────────────────────────────────────────────────

    public function test_import_page_loads(): void
    {
        MailList::factory()->count(3)->create();

        $this->actingAsTenantUser()
            ->get(route('audience.subscribers.import'))
            ->assertOk()
            ->assertViewIs('audience.subscribers-import')
            ->assertViewHas('mailLists');
    }

    public function test_import_requires_file(): void
    {
        $this->actingAsTenantUser()
            ->post(route('audience.subscribers.import.store'), [])
            ->assertSessionHasErrors('file');
    }

    public function test_import_accepts_csv_file(): void
    {
        Storage::fake('local');
        $csvContent = "phone,name,email\n919876543210,Test User,test@example.com\n";
        $file = UploadedFile::fake()->createWithContent('contacts.csv', $csvContent, 'text/csv');

        $this->actingAsTenantUser()
            ->post(route('audience.subscribers.import.store'), [
                'file' => $file,
            ])
            ->assertRedirect(route('audience.subscribers'))
            ->assertSessionHas('status');
    }

    public function test_import_rejects_invalid_file_type(): void
    {
        Storage::fake('local');
        $file = UploadedFile::fake()->create('contacts.pdf', 100, 'application/pdf');

        $this->actingAsTenantUser()
            ->post(route('audience.subscribers.import.store'), [
                'file' => $file,
            ])
            ->assertSessionHasErrors('file');
    }

    // ─── Export ──────────────────────────────────────────────────────────────

    public function test_export_requires_auth(): void
    {
        $this->post(route('audience.subscribers.export'))->assertRedirect();
    }

    public function test_export_returns_csv_download(): void
    {
        Contact::factory()->count(3)->create();

        $this->actingAsTenantUser()
            ->post(route('audience.subscribers.export'))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_export_can_filter_by_mail_list(): void
    {
        $list = MailList::factory()->create();
        Contact::factory()->count(3)->create(['mail_list_id' => $list->id]);
        Contact::factory()->count(2)->create();

        $this->actingAsTenantUser()
            ->post(route('audience.subscribers.export'), [
                'mail_list_id' => $list->uuid,
            ])
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }
}
