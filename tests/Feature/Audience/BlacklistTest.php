<?php

declare(strict_types=1);

namespace Tests\Feature\Audience;

use App\Domains\Audience\Models\Blacklist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class BlacklistTest extends TestCase
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

    // ─── Store ───────────────────────────────────────────────────────────────

    public function test_store_requires_phone_or_email(): void
    {
        $this->actingAsTenantUser()
            ->post(route('audience.blacklist.store'), [])
            ->assertSessionHasErrors(['phone', 'email']);
    }

    public function test_store_creates_blacklist_with_phone(): void
    {
        $this->actingAsTenantUser()
            ->post(route('audience.blacklist.store'), [
                'phone' => '919876543210',
                'reason' => 'Spam complaints',
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertDatabaseHas('blacklists', [
            'phone' => '919876543210',
            'reason' => 'Spam complaints',
        ]);
    }

    public function test_store_creates_blacklist_with_email(): void
    {
        $this->actingAsTenantUser()
            ->post(route('audience.blacklist.store'), [
                'email' => 'spam@example.com',
                'reason' => 'Abuse',
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertDatabaseHas('blacklists', [
            'email' => 'spam@example.com',
            'reason' => 'Abuse',
        ]);
    }

    public function test_store_creates_blacklist_with_both(): void
    {
        $this->actingAsTenantUser()
            ->post(route('audience.blacklist.store'), [
                'phone' => '919876543210',
                'email' => 'spam@example.com',
                'reason' => 'Multiple violations',
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertDatabaseHas('blacklists', [
            'phone' => '919876543210',
            'email' => 'spam@example.com',
        ]);
    }

    // ─── Delete ──────────────────────────────────────────────────────────────

    public function test_destroy_deletes_blacklist_entry(): void
    {
        $entry = Blacklist::factory()->create();

        $this->actingAsTenantUser()
            ->delete(route('audience.blacklist.destroy', $entry))
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertSoftDeleted('blacklists', ['id' => $entry->id]);
    }

    // ─── Import ──────────────────────────────────────────────────────────────

    public function test_import_requires_file(): void
    {
        $this->actingAsTenantUser()
            ->post(route('audience.blacklist.import'), [])
            ->assertSessionHasErrors('file');
    }

    public function test_import_accepts_csv_file(): void
    {
        Storage::fake('local');
        $file = UploadedFile::fake()->create('blacklist.csv', 100, 'text/csv');

        $this->actingAsTenantUser()
            ->post(route('audience.blacklist.import'), [
                'file' => $file,
            ])
            ->assertRedirect()
            ->assertSessionHas('status');
    }

    public function test_import_rejects_invalid_file_type(): void
    {
        Storage::fake('local');
        $file = UploadedFile::fake()->create('blacklist.pdf', 100, 'application/pdf');

        $this->actingAsTenantUser()
            ->post(route('audience.blacklist.import'), [
                'file' => $file,
            ])
            ->assertSessionHasErrors('file');
    }

    // ─── Model Methods ───────────────────────────────────────────────────────

    public function test_is_blacklisted_checks_phone(): void
    {
        Blacklist::factory()->create(['phone' => '919876543210']);

        $this->assertTrue(Blacklist::isBlacklisted('919876543210', null));
        $this->assertFalse(Blacklist::isBlacklisted('919876543211', null));
    }

    public function test_is_blacklisted_checks_email(): void
    {
        Blacklist::factory()->email()->create(['email' => 'spam@example.com']);

        $this->assertTrue(Blacklist::isBlacklisted(null, 'spam@example.com'));
        $this->assertFalse(Blacklist::isBlacklisted(null, 'legit@example.com'));
    }

    public function test_is_blacklisted_checks_both(): void
    {
        Blacklist::factory()->create([
            'phone' => '919876543210',
            'email' => 'spam@example.com',
        ]);

        $this->assertTrue(Blacklist::isBlacklisted('919876543210', 'spam@example.com'));
        $this->assertTrue(Blacklist::isBlacklisted('919876543210', null));
        $this->assertTrue(Blacklist::isBlacklisted(null, 'spam@example.com'));
        $this->assertFalse(Blacklist::isBlacklisted('919876543211', 'other@example.com'));
    }
}
