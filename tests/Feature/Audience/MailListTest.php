<?php

declare(strict_types=1);

namespace Tests\Feature\Audience;

use App\Domains\Audience\Enums\ContactStatus;
use App\Domains\Audience\Models\ListField;
use App\Enums\ContactOptInStatus;
use App\Enums\RecordStatus;
use App\Models\Contact;
use App\Models\MailList;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class MailListTest extends TestCase
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

    public function test_audience_index_requires_auth(): void
    {
        $this->get(route('audience.index'))->assertRedirect();
    }

    public function test_audience_index_page_loads(): void
    {
        $this->actingAsTenantUser()
            ->get(route('audience.index'))
            ->assertOk()
            ->assertViewIs('audience.index')
            ->assertViewHas('lists');
    }

    public function test_audience_index_shows_mail_lists(): void
    {
        $lists = MailList::factory()->count(3)->create();

        $this->actingAsTenantUser()
            ->get(route('audience.index'))
            ->assertOk()
            ->assertViewHas('lists', function ($lists) {
                return $lists->total() === 3;
            });
    }

    public function test_audience_index_search_filters_lists(): void
    {
        MailList::factory()->create(['name' => 'Alpha List']);
        MailList::factory()->create(['name' => 'Beta List']);
        MailList::factory()->create(['name' => 'Gamma List']);

        $this->actingAsTenantUser()
            ->get(route('audience.index', ['search' => 'Alpha']))
            ->assertOk()
            ->assertViewHas('lists', function ($lists) {
                return $lists->total() === 1 && $lists->first()->name === 'Alpha List';
            });
    }

    // ─── Store ───────────────────────────────────────────────────────────────

    public function test_store_requires_name(): void
    {
        $this->actingAsTenantUser()
            ->post(route('audience.lists.store'), [])
            ->assertSessionHasErrors('name');
    }

    public function test_store_creates_mail_list(): void
    {
        $this->actingAsTenantUser()
            ->post(route('audience.lists.store'), [
                'name' => 'Test List',
                'description' => 'Test description',
            ])
            ->assertRedirect(route('audience.index'))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('mail_lists', [
            'name' => 'Test List',
            'description' => 'Test description',
        ]);
    }

    public function test_store_rejects_duplicate_name(): void
    {
        MailList::factory()->create(['name' => 'Duplicate']);

        $this->actingAsTenantUser()
            ->post(route('audience.lists.store'), ['name' => 'Duplicate'])
            ->assertSessionHasErrors('name');
    }

    // ─── Update ──────────────────────────────────────────────────────────────

    public function test_update_requires_name(): void
    {
        $list = MailList::factory()->create();

        $this->actingAsTenantUser()
            ->put(route('audience.lists.update', $list), ['name' => ''])
            ->assertSessionHasErrors('name');
    }

    public function test_update_modifies_mail_list(): void
    {
        $list = MailList::factory()->create(['name' => 'Old Name']);

        $this->actingAsTenantUser()
            ->put(route('audience.lists.update', $list), ['name' => 'New Name'])
            ->assertRedirect(route('audience.index'))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('mail_lists', ['id' => $list->id, 'name' => 'New Name']);
    }

    public function test_update_can_change_status(): void
    {
        $list = MailList::factory()->create(['status' => RecordStatus::Active]);

        $this->actingAsTenantUser()
            ->put(route('audience.lists.update', $list), [
                'name' => $list->name,
                'status' => 'inactive',
            ])
            ->assertRedirect(route('audience.index'));

        $list->refresh();
        $this->assertEquals(RecordStatus::Inactive, $list->status);
    }

    // ─── Delete ──────────────────────────────────────────────────────────────

    public function test_destroy_deletes_mail_list(): void
    {
        $list = MailList::factory()->create();

        $this->actingAsTenantUser()
            ->delete(route('audience.lists.destroy', $list))
            ->assertRedirect(route('audience.index'))
            ->assertSessionHas('status');

        $this->assertSoftDeleted('mail_lists', ['id' => $list->id]);
    }

    // ─── Overview ────────────────────────────────────────────────────────────

    public function test_overview_page_loads(): void
    {
        $this->actingAsTenantUser()
            ->get(route('audience.overview'))
            ->assertOk()
            ->assertViewIs('audience.overview')
            ->assertViewHas('stats');
    }

    public function test_overview_with_specific_list(): void
    {
        $list = MailList::factory()->create();

        $this->actingAsTenantUser()
            ->get(route('audience.overview', ['list' => $list->id]))
            ->assertOk()
            ->assertViewHas('mailList', function ($mailList) use ($list) {
                return $mailList->id === $list->id;
            });
    }

    public function test_overview_stats_are_accurate(): void
    {
        $list = MailList::factory()->create();
        Contact::factory()->count(5)->create([
            'mail_list_id' => $list->id,
            'status' => ContactStatus::Subscribed,
        ]);
        Contact::factory()->count(2)->create([
            'mail_list_id' => $list->id,
            'status' => ContactStatus::Unsubscribed,
        ]);

        $this->actingAsTenantUser()
            ->get(route('audience.overview', ['list' => $list->id]))
            ->assertOk()
            ->assertViewHas('stats', function ($stats) {
                return $stats['subscriber_count'] === 7
                    && $stats['subscribed_count'] === 5
                    && $stats['unsubscribed_count'] === 2;
            });
    }

    // ─── Settings ────────────────────────────────────────────────────────────

    public function test_settings_page_loads(): void
    {
        $list = MailList::factory()->create();

        $this->actingAsTenantUser()
            ->get(route('audience.settings', ['list' => $list->id]))
            ->assertOk()
            ->assertViewIs('audience.settings')
            ->assertViewHas('mailList');
    }

    public function test_settings_without_list_shows_message(): void
    {
        $this->actingAsTenantUser()
            ->get(route('audience.settings'))
            ->assertOk()
            ->assertViewIs('audience.settings')
            ->assertViewHas('mailList', null);
    }

    // ─── Chart Endpoints ─────────────────────────────────────────────────────

    public function test_growth_chart_returns_json(): void
    {
        $list = MailList::factory()->create();

        $this->actingAsTenantUser()
            ->get(route('audience.lists.growth-chart', $list))
            ->assertOk()
            ->assertJsonStructure(['columns', 'total', 'unsubscribed']);
    }

    public function test_statistics_chart_returns_json(): void
    {
        $list = MailList::factory()->create();

        $this->actingAsTenantUser()
            ->get(route('audience.lists.statistics-chart', $list))
            ->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_forms_page_stays_on_audience_and_does_not_redirect_to_form_builder(): void
    {
        $list = MailList::factory()->create(['name' => 'Leads']);

        $this->actingAsTenantUser()
            ->get(route('audience.forms', ['list' => $list->id]))
            ->assertOk()
            ->assertViewIs('audience.forms')
            ->assertSee('Embedded form')
            ->assertSee('Copy / paste onto your site');
    }

    public function test_embedded_form_settings_are_saved_on_mail_list(): void
    {
        $list = MailList::factory()->create(['name' => 'Leads']);

        $this->actingAsTenantUser()
            ->post(route('audience.forms.update', ['list' => $list->id]), [
                'list' => $list->id,
                'form_title' => 'Subscribe to our WhatsApp list',
                'redirect_url' => 'https://example.com/thanks',
                'custom_css' => '.x { color: red; }',
                'show_required_only' => '1',
                'include_js' => '0',
                'include_css' => '1',
                'show_invisible_fields' => '1',
            ])
            ->assertRedirect(route('audience.forms', ['list' => $list->id]));

        $list->refresh();
        $options = $list->embedded_form_options;
        $this->assertSame('Subscribe to our WhatsApp list', $options['form_title']);
        $this->assertTrue((bool) $options['show_required_only']);
        $this->assertFalse((bool) $options['include_js']);
        $this->assertTrue((bool) $options['include_css']);
        $this->assertStringContainsString('.x { color: red; }', (string) $options['custom_css']);
    }

    public function test_forms_settings_can_be_saved_via_ajax_like_legacy_embedded_form(): void
    {
        $list = MailList::factory()->create(['name' => 'Leads']);
        ListField::query()->create([
            'mail_list_id' => $list->id,
            'label' => 'WhatsApp Number',
            'type' => ListField::TYPE_TEXT,
            'tag' => 'phone_number',
            'required' => true,
            'visible' => true,
            'sort_order' => 1,
        ]);

        $this->actingAsTenantUser()
            ->postJson(route('audience.forms.update', ['list' => $list->id]), [
                'list' => $list->id,
                'form_title' => 'Join list',
                'redirect_url' => '',
                'custom_css' => '',
                'show_required_only' => '0',
                'include_js' => '1',
                'include_css' => '1',
                'show_invisible_fields' => '0',
            ])
            ->assertOk()
            ->assertJsonStructure(['status', 'embed_code', 'preview_url'])
            ->assertJsonFragment(['status' => 'success']);

        $embed = (string) $this->actingAsTenantUser()
            ->postJson(route('audience.forms.update', ['list' => $list->id]), [
                'list' => $list->id,
                'form_title' => 'Join list',
                'include_js' => '1',
                'include_css' => '1',
            ])->json('embed_code');

        $this->assertStringContainsString('country_code', $embed);
        $this->assertStringContainsString('phone_number', $embed);
        $this->assertStringContainsString('FIRST_NAME', $embed);
        $this->assertStringContainsString('LAST_NAME', $embed);
        $this->assertStringContainsString('embedded-form-subscribe-captcha', $embed);
        $this->assertStringContainsString('/core/css/embedded.css', $embed);
        $this->assertStringContainsString('/core/css/app.css', $embed);
        $this->assertStringContainsString('/core/js/jquery-3.6.0.min.js', $embed);
        $this->assertStringContainsString('/core/validate/jquery.validate.min.js', $embed);
        $this->assertStringContainsString('/core/js/functions.js', $embed);
        $this->assertStringContainsString('initJs', $embed);
        $this->assertStringNotContainsString('wapapp-form-', $embed);
    }

    public function test_public_embedded_form_preview_renders_list_fields(): void
    {
        $list = MailList::factory()->create(['name' => 'Leads']);
        $list->forceFill([
            'embedded_form_options' => [
                'form_title' => 'Subscribe now',
                'include_css' => true,
                'include_js' => true,
            ],
        ])->save();

        $this->get(route('public.list.embedded-form.preview', [
            'tenant' => $this->testTenant->id,
            'list' => $list->uuid,
        ]))
            ->assertOk()
            ->assertSee('Subscribe now')
            ->assertSee('Country Code')
            ->assertSee('WHATSAPP NUMBER')
            ->assertSee('First name')
            ->assertSee('Last name')
            ->assertSee('country_code', false)
            ->assertSee('phone_number', false);
    }

    public function test_list_fields_page_loads_with_options_without_deleted_at_error(): void
    {
        $list = MailList::factory()->create();
        $field = ListField::query()->create([
            'mail_list_id' => $list->id,
            'label' => 'City',
            'type' => ListField::TYPE_DROPDOWN,
            'required' => false,
            'visible' => true,
            'sort_order' => 1,
        ]);
        $field->options()->create([
            'label' => 'Delhi',
            'value' => 'delhi',
            'sort_order' => 0,
        ]);

        $this->actingAsTenantUser()
            ->get(route('audience.list-fields', ['list' => $list->id]))
            ->assertOk()
            ->assertSee('Manage list fields')
            ->assertSee('City');
    }
}
