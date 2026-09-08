<?php

declare(strict_types=1);

namespace Tests\Feature\Audience;

use App\Domains\Audience\Enums\SegmentConditionType;
use App\Domains\Audience\Models\Segment;
use App\Enums\RecordStatus;
use App\Models\Contact;
use App\Models\MailList;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class SegmentTest extends TestCase
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

    public function test_segments_index_requires_auth(): void
    {
        $this->get(route('audience.segments'))->assertRedirect();
    }

    public function test_segments_index_page_loads(): void
    {
        $this->actingAsTenantUser()
            ->get(route('audience.segments'))
            ->assertOk()
            ->assertViewIs('audience.segments')
            ->assertViewHas('segments');
    }

    public function test_segments_index_shows_segments(): void
    {
        $list = MailList::factory()->create();
        Segment::factory()->count(3)->create(['mail_list_id' => $list->id]);

        $this->actingAsTenantUser()
            ->get(route('audience.segments'))
            ->assertOk()
            ->assertViewHas('segments', function ($segments) {
                return $segments->total() === 3;
            });
    }

    public function test_segments_index_filters_by_mail_list(): void
    {
        $list1 = MailList::factory()->create();
        $list2 = MailList::factory()->create();
        Segment::factory()->count(2)->create(['mail_list_id' => $list1->id]);
        Segment::factory()->count(3)->create(['mail_list_id' => $list2->id]);

        $this->actingAsTenantUser()
            ->get(route('audience.segments', ['list' => $list1->uuid]))
            ->assertOk()
            ->assertViewHas('segments', function ($segments) {
                return $segments->total() === 2;
            });
    }

    public function test_segments_index_search_filters(): void
    {
        $list = MailList::factory()->create();
        Segment::factory()->create(['mail_list_id' => $list->id, 'name' => 'VIP Customers']);
        Segment::factory()->create(['mail_list_id' => $list->id, 'name' => 'Regular Users']);

        $this->actingAsTenantUser()
            ->get(route('audience.segments', ['search' => 'VIP']))
            ->assertOk()
            ->assertViewHas('segments', function ($segments) {
                return $segments->total() === 1 && $segments->first()->name === 'VIP Customers';
            });
    }

    // ─── Store ───────────────────────────────────────────────────────────────

    public function test_store_requires_name(): void
    {
        $this->actingAsTenantUser()
            ->post(route('audience.segments.store'), [])
            ->assertSessionHasErrors('name');
    }

    public function test_store_creates_segment(): void
    {
        $list = MailList::factory()->create();

        $this->actingAsTenantUser()
            ->post(route('audience.segments.store'), [
                'name' => 'Test Segment',
                'mail_list_id' => $list->uuid,
                'conditions' => [
                    ['field' => 'status', 'type' => 'equals', 'value' => 'subscribed'],
                ],
            ])
            ->assertRedirect(route('audience.segments', ['list' => $list->uuid]))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('segments', [
            'name' => 'Test Segment',
            'mail_list_id' => $list->id,
        ]);
    }

    public function test_store_recalculates_contact_count(): void
    {
        $list = MailList::factory()->create();
        Contact::factory()->count(5)->create([
            'mail_list_id' => $list->id,
            'status' => \App\Domains\Audience\Enums\ContactStatus::Subscribed,
        ]);

        $this->actingAsTenantUser()
            ->post(route('audience.segments.store'), [
                'name' => 'Subscribed Segment',
                'mail_list_id' => $list->uuid,
                'conditions' => [
                    ['field' => 'status', 'type' => 'equals', 'value' => 'subscribed'],
                ],
            ])
            ->assertRedirect(route('audience.segments', ['list' => $list->uuid]));

        $segment = Segment::where('name', 'Subscribed Segment')->first();
        $this->assertEquals(5, $segment->contact_count);
    }

    // ─── Update ──────────────────────────────────────────────────────────────

    public function test_update_requires_name(): void
    {
        $segment = Segment::factory()->create();

        $this->actingAsTenantUser()
            ->put(route('audience.segments.update', $segment), ['name' => ''])
            ->assertSessionHasErrors('name');
    }

    public function test_update_modifies_segment(): void
    {
        $segment = Segment::factory()->create(['name' => 'Old Name']);

        $this->actingAsTenantUser()
            ->put(route('audience.segments.update', $segment), [
                'name' => 'New Name',
                'conditions' => $segment->conditions,
            ])
            ->assertRedirect(route('audience.segments'))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('segments', ['id' => $segment->id, 'name' => 'New Name']);
    }

    public function test_update_recalculates_count(): void
    {
        $list = MailList::factory()->create();
        $segment = Segment::factory()->create([
            'mail_list_id' => $list->id,
            'contact_count' => 0,
        ]);

        Contact::factory()->count(3)->create([
            'mail_list_id' => $list->id,
            'status' => \App\Domains\Audience\Enums\ContactStatus::Subscribed,
        ]);

        $this->actingAsTenantUser()
            ->put(route('audience.segments.update', $segment), [
                'name' => $segment->name,
                'conditions' => [
                    ['field' => 'status', 'type' => 'equals', 'value' => 'subscribed'],
                ],
            ])
            ->assertRedirect(route('audience.segments'));

        $segment->refresh();
        $this->assertEquals(3, $segment->contact_count);
    }

    // ─── Delete ──────────────────────────────────────────────────────────────

    public function test_destroy_deletes_segment(): void
    {
        $segment = Segment::factory()->create();

        $this->actingAsTenantUser()
            ->delete(route('audience.segments.destroy', $segment))
            ->assertRedirect(route('audience.segments'))
            ->assertSessionHas('status');

        $this->assertSoftDeleted('segments', ['id' => $segment->id]);
    }

    // ─── Segment Conditions ──────────────────────────────────────────────────

    public function test_segment_conditions_with_equals(): void
    {
        $list = MailList::factory()->create();
        Contact::factory()->count(3)->create([
            'mail_list_id' => $list->id,
            'status' => \App\Domains\Audience\Enums\ContactStatus::Subscribed,
        ]);
        Contact::factory()->count(2)->create([
            'mail_list_id' => $list->id,
            'status' => \App\Domains\Audience\Enums\ContactStatus::Unsubscribed,
        ]);

        $segment = Segment::factory()->create([
            'mail_list_id' => $list->id,
            'conditions' => [
                ['field' => 'status', 'type' => 'equals', 'value' => 'subscribed'],
            ],
        ]);

        $service = app(\App\Domains\Audience\Services\SegmentService::class);
        $service->recalculateCount($segment);
        $segment->refresh();

        $this->assertEquals(3, $segment->contact_count);
    }

    public function test_segment_conditions_with_not_equals(): void
    {
        $list = MailList::factory()->create();
        Contact::factory()->count(3)->create([
            'mail_list_id' => $list->id,
            'status' => \App\Domains\Audience\Enums\ContactStatus::Subscribed,
        ]);
        Contact::factory()->count(2)->create([
            'mail_list_id' => $list->id,
            'status' => \App\Domains\Audience\Enums\ContactStatus::Unsubscribed,
        ]);

        $segment = Segment::factory()->create([
            'mail_list_id' => $list->id,
            'conditions' => [
                ['field' => 'status', 'type' => 'not_equals', 'value' => 'subscribed'],
            ],
        ]);

        $service = app(\App\Domains\Audience\Services\SegmentService::class);
        $service->recalculateCount($segment);
        $segment->refresh();

        $this->assertEquals(2, $segment->contact_count);
    }

    public function test_segment_conditions_with_contains(): void
    {
        $list = MailList::factory()->create();
        Contact::factory()->create(['mail_list_id' => $list->id, 'name' => 'John Doe']);
        Contact::factory()->create(['mail_list_id' => $list->id, 'name' => 'Jane Smith']);
        Contact::factory()->create(['mail_list_id' => $list->id, 'name' => 'Bob Johnson']);

        $segment = Segment::factory()->create([
            'mail_list_id' => $list->id,
            'conditions' => [
                ['field' => 'name', 'type' => 'contains', 'value' => 'John'],
            ],
        ]);

        $service = app(\App\Domains\Audience\Services\SegmentService::class);
        $service->recalculateCount($segment);
        $segment->refresh();

        $this->assertEquals(2, $segment->contact_count);
    }
}
