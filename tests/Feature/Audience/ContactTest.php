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

    public function test_subscribers_index_without_list_redirects_to_audience_index(): void
    {
        $this->actingAsTenantUser()
            ->get(route('audience.subscribers'))
            ->assertRedirect(route('audience.index'));
    }

    public function test_subscribers_index_page_loads(): void
    {
        $list = MailList::factory()->create();

        $this->actingAsTenantUser()
            ->get(route('audience.subscribers', ['list' => $list->uuid]))
            ->assertOk()
            ->assertViewIs('audience.subscribers')
            ->assertDontSee('All Subscribers', false);
    }

    public function test_subscribers_index_shows_add_subscriber_when_empty(): void
    {
        $list = MailList::factory()->create();

        $this->actingAsTenantUser()
            ->get(route('audience.subscribers', ['list' => $list->uuid]))
            ->assertOk()
            ->assertViewIs('audience.subscribers')
            ->assertSee('Add New Subscribers')
            ->assertSee('New subscriber')
            ->assertSee('India (+91)')
            ->assertSee('Afghanistan (+93)')
            ->assertSee('United Arab Emirates (+971)');
    }

    public function test_subscribers_index_shows_contacts(): void
    {
        $list = MailList::factory()->create();
        Contact::factory()->count(5)->create(['mail_list_id' => $list->id]);

        $this->actingAsTenantUser()
            ->get(route('audience.subscribers', ['list' => $list->uuid]))
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
        $list = MailList::factory()->create();
        Contact::factory()->create([
            'name' => 'John Doe',
            'phone' => '919876543210',
            'mail_list_id' => $list->id,
        ]);
        Contact::factory()->create([
            'name' => 'Jane Smith',
            'phone' => '919876543211',
            'mail_list_id' => $list->id,
        ]);

        $this->actingAsTenantUser()
            ->get(route('audience.subscribers', ['list' => $list->uuid, 'search' => 'John']))
            ->assertOk()
            ->assertViewHas('contacts', function ($contacts) {
                return $contacts->total() === 1 && $contacts->first()->name === 'John Doe';
            });
    }

    public function test_subscribers_index_filters_by_status(): void
    {
        $list = MailList::factory()->create();
        Contact::factory()->count(3)->create([
            'status' => ContactStatus::Subscribed,
            'mail_list_id' => $list->id,
        ]);
        Contact::factory()->count(2)->create([
            'status' => ContactStatus::Unsubscribed,
            'mail_list_id' => $list->id,
        ]);

        $this->actingAsTenantUser()
            ->get(route('audience.subscribers', ['list' => $list->uuid, 'status' => 'subscribed']))
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
            ->get(route('audience.subscribers.detail', ['id' => $contact->uuid]))
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
            ->get(route('audience.subscribers.detail', ['id' => $contact->uuid]))
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
        $list = MailList::factory()->create();

        $this->actingAsTenantUser()
            ->post(route('audience.subscribers.store'), [
                'phone' => '919876543210',
                'mail_list_id' => $list->uuid,
            ])
            ->assertRedirect(route('audience.subscribers', ['list' => $list->uuid]));

        $contact = Contact::where('phone', '919876543210')->first();
        $this->assertEquals(ContactStatus::Subscribed, $contact->status);
        $this->assertEquals(ContactOptInStatus::OptedIn, $contact->opt_in_status);
    }

    public function test_store_prefixes_phone_with_legacy_country_dial_code(): void
    {
        $list = MailList::factory()->create();

        $this->actingAsTenantUser()
            ->post(route('audience.subscribers.store'), [
                'country_code' => '+91',
                'phone' => '9876543210',
                'name' => 'Dial Code Contact',
                'mail_list_id' => $list->uuid,
            ])
            ->assertRedirect(route('audience.subscribers', ['list' => $list->uuid]));

        $this->assertDatabaseHas('contacts', [
            'phone' => '919876543210',
            'country_code' => '+91',
            'name' => 'Dial Code Contact',
        ]);
    }

    public function test_store_without_list_redirects_to_audience_index(): void
    {
        $this->actingAsTenantUser()
            ->post(route('audience.subscribers.store'), [
                'phone' => '919876543210',
            ])
            ->assertRedirect(route('audience.index'));
    }

    public function test_store_allows_same_phone_on_different_list(): void
    {
        $listA = MailList::factory()->create();
        $listB = MailList::factory()->create();

        Contact::factory()->create([
            'phone' => '919876543299',
            'mail_list_id' => $listA->id,
        ]);

        $this->actingAsTenantUser()
            ->post(route('audience.subscribers.store'), [
                'phone' => '919876543299',
                'name' => 'On List B',
                'mail_list_id' => $listB->uuid,
            ])
            ->assertRedirect(route('audience.subscribers', ['list' => $listB->uuid]))
            ->assertSessionHas('status')
            ->assertSessionDoesntHaveErrors('phone');

        $this->assertSame(2, Contact::query()->where('phone', '919876543299')->count());
        $this->assertDatabaseHas('contacts', [
            'phone' => '919876543299',
            'mail_list_id' => $listB->id,
            'name' => 'On List B',
        ]);
    }

    public function test_store_rejects_active_duplicate_on_same_list(): void
    {
        $list = MailList::factory()->create();
        Contact::factory()->create([
            'phone' => '919876543288',
            'mail_list_id' => $list->id,
        ]);

        $this->actingAsTenantUser()
            ->from(route('audience.subscribers', ['list' => $list->uuid]))
            ->post(route('audience.subscribers.store'), [
                'phone' => '919876543288',
                'mail_list_id' => $list->uuid,
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('phone');
    }

    public function test_store_readds_soft_deleted_phone_on_same_list(): void
    {
        $list = MailList::factory()->create();
        $contact = Contact::factory()->create([
            'phone' => '919876543277',
            'name' => 'Old Name',
            'mail_list_id' => $list->id,
        ]);
        $contact->delete();

        $this->assertSoftDeleted('contacts', ['id' => $contact->id]);

        $this->actingAsTenantUser()
            ->post(route('audience.subscribers.store'), [
                'phone' => '919876543277',
                'name' => 'Restored Name',
                'mail_list_id' => $list->uuid,
            ])
            ->assertRedirect(route('audience.subscribers', ['list' => $list->uuid]))
            ->assertSessionHas('status')
            ->assertSessionDoesntHaveErrors('phone');

        $this->assertDatabaseHas('contacts', [
            'id' => $contact->id,
            'phone' => '919876543277',
            'mail_list_id' => $list->id,
            'name' => 'Restored Name',
            'deleted_at' => null,
        ]);
        $this->assertSame(1, Contact::query()->where('phone', '919876543277')->count());
    }

    public function test_purge_command_force_deletes_old_soft_deleted_contacts_only(): void
    {
        $old = Contact::factory()->create(['phone' => '919800000001']);
        $old->delete();
        Contact::onlyTrashed()->whereKey($old->id)->update([
            'deleted_at' => now()->subDays(31),
        ]);

        $recent = Contact::factory()->create(['phone' => '919800000002']);
        $recent->delete();
        Contact::onlyTrashed()->whereKey($recent->id)->update([
            'deleted_at' => now()->subDays(5),
        ]);

        $active = Contact::factory()->create(['phone' => '919800000003']);

        $this->artisan('audience:purge-soft-deleted-contacts', [
            '--days' => 30,
            '--tenants' => [$this->testTenant->id],
        ])->assertSuccessful();

        tenancy()->initialize($this->testTenant);

        $this->assertDatabaseMissing('contacts', ['id' => $old->id]);
        $this->assertSoftDeleted('contacts', ['id' => $recent->id]);
        $this->assertDatabaseHas('contacts', [
            'id' => $active->id,
            'deleted_at' => null,
        ]);
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
        $list = MailList::factory()->create();
        $contact = Contact::factory()->create([
            'name' => 'Old Name',
            'mail_list_id' => $list->id,
        ]);

        $this->actingAsTenantUser()
            ->put(route('audience.subscribers.update', $contact), [
                'phone' => $contact->phone,
                'name' => 'New Name',
                'list' => $list->uuid,
            ])
            ->assertRedirect(route('audience.subscribers', ['list' => $list->uuid]))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('contacts', ['id' => $contact->id, 'name' => 'New Name']);
    }

    public function test_update_can_sync_tags(): void
    {
        $list = MailList::factory()->create();
        $contact = Contact::factory()->create(['mail_list_id' => $list->id]);
        ContactTag::factory()->create(['contact_id' => $contact->id, 'name' => 'old-tag']);

        $this->actingAsTenantUser()
            ->put(route('audience.subscribers.update', $contact), [
                'phone' => $contact->phone,
                'tags' => ['new-tag-1', 'new-tag-2'],
                'list' => $list->uuid,
            ])
            ->assertRedirect(route('audience.subscribers', ['list' => $list->uuid]));

        $contact->refresh();
        $this->assertEquals(2, $contact->tags->count());
        $this->assertTrue($contact->tags->pluck('name')->contains('new-tag-1'));
        $this->assertFalse($contact->tags->pluck('name')->contains('old-tag'));
    }

    public function test_subscribers_index_filters_by_date_range(): void
    {
        $list = MailList::factory()->create();
        Contact::factory()->create([
            'created_at' => now()->subDays(10),
            'mail_list_id' => $list->id,
        ]);
        Contact::factory()->create([
            'created_at' => now()->subDay(),
            'mail_list_id' => $list->id,
        ]);

        $this->actingAsTenantUser()
            ->get(route('audience.subscribers', [
                'list' => $list->uuid,
                'date_from' => now()->subDays(2)->toDateString(),
                'date_to' => now()->toDateString(),
            ]))
            ->assertOk()
            ->assertViewHas('contacts', function ($contacts) {
                return $contacts->total() === 1;
            });
    }

    public function test_subscribers_index_filters_by_opt_in_delivery(): void
    {
        $list = MailList::factory()->create();
        Contact::factory()->create([
            'mail_list_id' => $list->id,
            'send_opt_in_message' => 'yes',
            'opt_in_message_delivery_status' => 'delivered',
        ]);
        Contact::factory()->create([
            'mail_list_id' => $list->id,
            'send_opt_in_message' => 'yes',
            'opt_in_message_delivery_status' => 'failed',
        ]);

        $this->actingAsTenantUser()
            ->get(route('audience.subscribers', ['list' => $list->uuid, 'opt_in' => 'delivered']))
            ->assertOk()
            ->assertViewHas('contacts', function ($contacts) {
                return $contacts->total() === 1
                    && $contacts->first()->opt_in_message_delivery_status === 'delivered';
            });
    }

    // ─── Delete ──────────────────────────────────────────────────────────────

    public function test_destroy_deletes_contact(): void
    {
        $list = MailList::factory()->create();
        $contact = Contact::factory()->create(['mail_list_id' => $list->id]);

        $this->actingAsTenantUser()
            ->delete(route('audience.subscribers.destroy', $contact))
            ->assertRedirect(route('audience.subscribers', ['list' => $list->uuid]))
            ->assertSessionHas('status');

        $this->assertSoftDeleted('contacts', ['id' => $contact->id]);
    }

    // ─── Subscribe / Unsubscribe ─────────────────────────────────────────────

    public function test_subscribers_page_renders_toggle_as_submit_button(): void
    {
        $list = MailList::factory()->create();
        $contact = Contact::factory()->create([
            'status' => ContactStatus::Subscribed,
            'mail_list_id' => $list->id,
        ]);

        $html = $this->actingAsTenantUser()
            ->get(route('audience.subscribers', ['list' => $list->uuid]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString(
            'aria-label="Unsubscribe contact"',
            $html,
            'Subscribe toggle should render with unsubscribe label when active',
        );
        $this->assertStringContainsString(
            'type="submit"',
            $html,
        );
        // Nested buttons break form submit — toggle must be the submit control.
        $this->assertDoesNotMatchRegularExpression(
            '/<button[^>]*type="submit"[^>]*>\s*<button/i',
            $html,
            'Toggle must not be wrapped in another button',
        );
        $this->assertStringContainsString(
            route('audience.subscribers.unsubscribe', absolute: false),
            $html,
        );
        $this->assertStringContainsString($contact->uuid, $html);
    }

    public function test_subscribe_changes_status(): void
    {
        $list = MailList::factory()->create();
        $contact = Contact::factory()->create([
            'status' => ContactStatus::Unsubscribed,
            'mail_list_id' => $list->id,
        ]);

        $this->actingAsTenantUser()
            ->post(route('audience.subscribers.subscribe'), [
                'ids' => [$contact->id],
                'list' => $list->uuid,
            ])
            ->assertRedirect(route('audience.subscribers', ['list' => $list->uuid]))
            ->assertSessionHas('status');

        $contact->refresh();
        $this->assertEquals(ContactStatus::Subscribed, $contact->status);
        $this->assertEquals(ContactOptInStatus::OptedIn, $contact->opt_in_status);
        $this->assertNotNull($contact->opted_in_at);
    }

    public function test_unsubscribe_changes_status(): void
    {
        $list = MailList::factory()->create();
        $contact = Contact::factory()->create([
            'status' => ContactStatus::Subscribed,
            'mail_list_id' => $list->id,
        ]);

        $this->actingAsTenantUser()
            ->post(route('audience.subscribers.unsubscribe'), [
                'ids' => [$contact->id],
                'list' => $list->uuid,
            ])
            ->assertRedirect(route('audience.subscribers', ['list' => $list->uuid]))
            ->assertSessionHas('status');

        $contact->refresh();
        $this->assertEquals(ContactStatus::Unsubscribed, $contact->status);
        $this->assertEquals(ContactOptInStatus::OptedOut, $contact->opt_in_status);
        $this->assertNotNull($contact->opted_out_at);
    }

    public function test_bulk_subscribe_multiple_contacts(): void
    {
        $list = MailList::factory()->create();
        $contacts = Contact::factory()->count(3)->create([
            'status' => ContactStatus::Unsubscribed,
            'mail_list_id' => $list->id,
        ]);

        $this->actingAsTenantUser()
            ->post(route('audience.subscribers.subscribe'), [
                'ids' => $contacts->pluck('id')->toArray(),
                'list' => $list->uuid,
            ])
            ->assertRedirect(route('audience.subscribers', ['list' => $list->uuid]))
            ->assertSessionHas('status', '3 contact(s) subscribed.');

        $this->assertEquals(3, Contact::where('status', ContactStatus::Subscribed)->count());
    }

    public function test_bulk_unsubscribe_multiple_contacts(): void
    {
        $list = MailList::factory()->create();
        $contacts = Contact::factory()->count(3)->create([
            'status' => ContactStatus::Subscribed,
            'mail_list_id' => $list->id,
        ]);

        $this->actingAsTenantUser()
            ->post(route('audience.subscribers.unsubscribe'), [
                'ids' => $contacts->pluck('id')->toArray(),
                'list' => $list->uuid,
            ])
            ->assertRedirect(route('audience.subscribers', ['list' => $list->uuid]))
            ->assertSessionHas('status', '3 contact(s) unsubscribed.');

        $this->assertEquals(3, Contact::where('status', ContactStatus::Unsubscribed)->count());
    }

    // ─── Bulk Delete ─────────────────────────────────────────────────────────

    public function test_bulk_delete_soft_deletes_contacts(): void
    {
        $list = MailList::factory()->create();
        $contacts = Contact::factory()->count(5)->create(['mail_list_id' => $list->id]);

        $this->actingAsTenantUser()
            ->post(route('audience.subscribers.bulk-delete'), [
                'ids' => $contacts->pluck('id')->toArray(),
                'list' => $list->uuid,
            ])
            ->assertRedirect(route('audience.subscribers', ['list' => $list->uuid]))
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
        $list = MailList::factory()->create();
        $csvContent = "country_code,phone_number,FIRST_NAME,LAST_NAME,existing_customer,send_opt_in_message\n91,919876543210,Test,User,yes,no\n";
        $file = UploadedFile::fake()->createWithContent('contacts.csv', $csvContent, 'text/csv');

        $this->actingAsTenantUser()
            ->post(route('audience.subscribers.import.store'), [
                'file' => $file,
                'mail_list_id' => $list->uuid,
            ])
            ->assertRedirect(route('audience.subscribers', ['list' => $list->uuid]))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('contacts', [
            'phone' => '919876543210',
            'mail_list_id' => $list->id,
        ]);
    }

    public function test_import_allows_same_phone_on_another_list(): void
    {
        Storage::fake('local');
        $listA = MailList::factory()->create();
        $listB = MailList::factory()->create();

        Contact::factory()->create([
            'phone' => '917018107871',
            'mail_list_id' => $listA->id,
        ]);

        $csvContent = "country_code,phone_number,FIRST_NAME,LAST_NAME,existing_customer,send_opt_in_message\n91,7018107871,Pet,Parent,yes,no\n";
        $file = UploadedFile::fake()->createWithContent('contacts.csv', $csvContent, 'text/csv');

        $this->actingAsTenantUser()
            ->post(route('audience.subscribers.import.store'), [
                'file' => $file,
                'mail_list_id' => $listB->uuid,
            ])
            ->assertRedirect(route('audience.subscribers', ['list' => $listB->uuid]))
            ->assertSessionHas('status');

        $this->assertSame(2, Contact::query()->where('phone', '917018107871')->count());
        $this->assertDatabaseHas('contacts', [
            'phone' => '917018107871',
            'mail_list_id' => $listB->id,
        ]);
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
