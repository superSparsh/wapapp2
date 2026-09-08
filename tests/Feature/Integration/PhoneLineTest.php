<?php

declare(strict_types=1);

namespace Tests\Feature\Integration;

use App\Domains\Integration\Services\PhoneLineService;
use App\Models\WhatsappLine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

/**
 * Tests for the Manage Phone Numbers feature.
 *
 * Setup: testLine is the default (is_default=true).
 *        Additional secondary lines are created per-test.
 */
class PhoneLineTest extends TestCase
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

    // ─── Page accessibility ───────────────────────────────────────────────────

    public function test_owner_can_view_phone_lines_page(): void
    {
        $this->actingAsTenantUser()
            ->get(route('profile.phone-lines.index'))
            ->assertOk()
            ->assertViewIs('profile.phone-lines');
    }

    public function test_unauthenticated_user_is_redirected(): void
    {
        $this->get(route('profile.phone-lines.index'))
            ->assertRedirect();
    }

    public function test_page_shows_empty_state_when_no_secondary_lines(): void
    {
        // testLine is default; no secondary lines exist
        $this->actingAsTenantUser()
            ->get(route('profile.phone-lines.index'))
            ->assertOk()
            ->assertSee('No additional phone numbers found');
    }

    public function test_page_lists_secondary_lines(): void
    {
        $secondary = WhatsappLine::factory()->create(['display_name' => 'Sales Line']);

        $this->actingAsTenantUser()
            ->get(route('profile.phone-lines.index'))
            ->assertOk()
            ->assertSee('Sales Line');
    }

    public function test_default_line_not_shown_on_manage_page(): void
    {
        // testLine is default and should NOT appear in secondary listing
        $this->actingAsTenantUser()
            ->get(route('profile.phone-lines.index'))
            ->assertOk()
            ->assertDontSee($this->testLine->display_name);
    }

    // ─── Set Password ─────────────────────────────────────────────────────────

    public function test_owner_can_set_line_password(): void
    {
        $line = WhatsappLine::factory()->create();

        $this->actingAsTenantUser()
            ->post(route('profile.phone-lines.password'), [
                'line_id'               => $line->id,
                'password'              => 'MySecret88',
                'password_confirmation' => 'MySecret88',
            ])
            ->assertRedirect(route('profile.phone-lines.index'))
            ->assertSessionHas('success');

        $line->refresh();
        $this->assertTrue($line->hasLinePassword());
        $this->assertTrue(Hash::check('MySecret88', (string) $line->line_password));
    }

    public function test_set_password_requires_confirmation(): void
    {
        $line = WhatsappLine::factory()->create();

        $this->actingAsTenantUser()
            ->post(route('profile.phone-lines.password'), [
                'line_id'               => $line->id,
                'password'              => 'MySecret88',
                'password_confirmation' => 'WrongConfirm',
            ])
            ->assertSessionHasErrors('password');
    }

    public function test_set_password_requires_minimum_8_chars(): void
    {
        $line = WhatsappLine::factory()->create();

        $this->actingAsTenantUser()
            ->post(route('profile.phone-lines.password'), [
                'line_id'               => $line->id,
                'password'              => 'short',
                'password_confirmation' => 'short',
            ])
            ->assertSessionHasErrors('password');
    }

    public function test_cannot_set_password_for_default_line(): void
    {
        $this->actingAsTenantUser()
            ->post(route('profile.phone-lines.password'), [
                'line_id'               => $this->testLine->id,
                'password'              => 'MySecret88',
                'password_confirmation' => 'MySecret88',
            ])
            ->assertRedirect(route('profile.phone-lines.index'))
            ->assertSessionHas('error');
    }

    // ─── Login As Number ──────────────────────────────────────────────────────

    public function test_owner_can_login_as_a_connected_secondary_line(): void
    {
        $line = WhatsappLine::factory()->connected()->withPassword('password123')->create();

        $this->actingAsTenantUser()
            ->post(route('profile.phone-lines.login-as'), [
                'line_id'  => $line->id,
                'password' => 'password123',
            ])
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('success');

        $this->assertTrue(PhoneLineService::isLocked());
        $this->assertSame($line->id, session(PhoneLineService::SESSION_LINE_ID));
    }

    public function test_login_as_fails_with_wrong_password(): void
    {
        $line = WhatsappLine::factory()->connected()->withPassword('password123')->create();

        $this->actingAsTenantUser()
            ->post(route('profile.phone-lines.login-as'), [
                'line_id'  => $line->id,
                'password' => 'wrongpassword',
            ])
            ->assertRedirect(route('profile.phone-lines.index'))
            ->assertSessionHas('error');

        $this->assertFalse(PhoneLineService::isLocked());
    }

    public function test_login_as_fails_when_no_password_set(): void
    {
        $line = WhatsappLine::factory()->connected()->create(); // no password

        $this->actingAsTenantUser()
            ->post(route('profile.phone-lines.login-as'), [
                'line_id'  => $line->id,
                'password' => 'password123',
            ])
            ->assertRedirect(route('profile.phone-lines.index'))
            ->assertSessionHas('error');
    }

    public function test_login_as_fails_when_line_not_connected(): void
    {
        $line = WhatsappLine::factory()->withPassword('password123')->create(); // no waba_id

        $this->actingAsTenantUser()
            ->post(route('profile.phone-lines.login-as'), [
                'line_id'  => $line->id,
                'password' => 'password123',
            ])
            ->assertRedirect(route('profile.phone-lines.index'))
            ->assertSessionHas('error');
    }

    // ─── Exit Context ─────────────────────────────────────────────────────────

    public function test_owner_can_exit_line_context(): void
    {
        $line = WhatsappLine::factory()->connected()->withPassword('password123')->create();

        $response = $this->actingAsTenantUser();

        // First lock the session
        session([
            PhoneLineService::SESSION_LOCKED  => true,
            PhoneLineService::SESSION_LINE_ID => $line->id,
        ]);

        $response
            ->post(route('profile.phone-lines.exit-context'))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('success');

        $this->assertFalse(PhoneLineService::isLocked());
        $this->assertNull(session(PhoneLineService::SESSION_LINE_ID));
    }

    // ─── Set Default ──────────────────────────────────────────────────────────

    public function test_owner_can_set_a_secondary_line_as_default(): void
    {
        $secondary = WhatsappLine::factory()->connected()->create();

        $this->actingAsTenantUser()
            ->post(route('profile.phone-lines.set-default'), [
                'line_id' => $secondary->id,
            ])
            ->assertRedirect(route('profile.phone-lines.index'))
            ->assertSessionHas('success');

        $secondary->refresh();
        $this->testLine->refresh();

        $this->assertTrue($secondary->is_default);
        $this->assertFalse($this->testLine->is_default);
    }

    public function test_set_default_fails_for_not_connected_line(): void
    {
        $secondary = WhatsappLine::factory()->create(); // no waba_id

        $this->actingAsTenantUser()
            ->post(route('profile.phone-lines.set-default'), [
                'line_id' => $secondary->id,
            ])
            ->assertRedirect(route('profile.phone-lines.index'))
            ->assertSessionHas('error');

        $secondary->refresh();
        $this->assertFalse($secondary->is_default);
    }

    public function test_set_default_fails_when_already_default(): void
    {
        // testLine is already default
        $this->actingAsTenantUser()
            ->post(route('profile.phone-lines.set-default'), [
                'line_id' => $this->testLine->id,
            ])
            ->assertRedirect(route('profile.phone-lines.index'))
            ->assertSessionHas('error');
    }

    public function test_set_default_fails_in_locked_mode(): void
    {
        $secondary = WhatsappLine::factory()->connected()->create();

        session([PhoneLineService::SESSION_LOCKED => true]);

        $this->actingAsTenantUser()
            ->post(route('profile.phone-lines.set-default'), [
                'line_id' => $secondary->id,
            ])
            ->assertRedirect(route('profile.phone-lines.index'))
            ->assertSessionHas('error');

        $secondary->refresh();
        $this->assertFalse($secondary->is_default);
    }

    // ─── PhoneLineService unit tests ──────────────────────────────────────────

    public function test_service_secondary_lines_excludes_default(): void
    {
        WhatsappLine::factory()->count(3)->create();

        $service = app(PhoneLineService::class);
        $lines   = $service->secondaryLines();

        // 3 secondary + testLine (default) = 4 total; secondary only = 3
        $this->assertCount(3, $lines);
        $this->assertTrue($lines->every(fn ($l) => ! $l->is_default));
    }

    public function test_service_set_default_is_atomic(): void
    {
        $a = WhatsappLine::factory()->connected()->create();
        $b = WhatsappLine::factory()->connected()->create();

        $service = app(PhoneLineService::class);
        $service->setAsDefault($a);

        $a->refresh();
        $b->refresh();
        $this->testLine->refresh();

        $this->assertTrue($a->is_default);
        $this->assertFalse($b->is_default);
        $this->assertFalse($this->testLine->is_default);
    }

    // ─── Model helpers ────────────────────────────────────────────────────────

    public function test_whatsapp_line_has_line_password_returns_false_when_null(): void
    {
        $line = WhatsappLine::factory()->create();
        $this->assertFalse($line->hasLinePassword());
    }

    public function test_whatsapp_line_has_line_password_returns_true_when_set(): void
    {
        $line = WhatsappLine::factory()->withPassword()->create();
        $this->assertTrue($line->hasLinePassword());
    }

    public function test_whatsapp_line_check_line_password_correct(): void
    {
        $line = WhatsappLine::factory()->withPassword('securepass1')->create();
        $this->assertTrue($line->checkLinePassword('securepass1'));
    }

    public function test_whatsapp_line_check_line_password_wrong(): void
    {
        $line = WhatsappLine::factory()->withPassword('securepass1')->create();
        $this->assertFalse($line->checkLinePassword('wrongpass'));
    }

    public function test_whatsapp_line_is_connected_true_when_waba_set(): void
    {
        $line = WhatsappLine::factory()->connected()->create();
        $this->assertTrue($line->isConnected());
    }

    public function test_whatsapp_line_is_connected_false_when_no_waba(): void
    {
        $line = WhatsappLine::factory()->create();
        $this->assertFalse($line->isConnected());
    }

    public function test_whatsapp_line_display_phone_formats_indian_number(): void
    {
        $line = WhatsappLine::factory()->make(['phone' => '919876543210']);
        $this->assertSame('+91 98765 43210', $line->displayPhone());
    }

    // ─── Line Login page (public) ─────────────────────────────────────────────

    public function test_line_login_page_is_publicly_accessible(): void
    {
        $this->get(route('line.login'))
            ->assertOk()
            ->assertViewIs('auth.line-login');
    }

    public function test_line_login_succeeds_with_correct_phone_and_password(): void
    {
        $line = WhatsappLine::factory()->connected()->withPassword('linepass99')->create([
            'is_default' => false,
            'phone'      => '919876543210',
        ]);

        $this->post(route('line.login.submit'), [
            'phone'    => '919876543210',
            'password' => 'linepass99',
        ])->assertRedirect(route('dashboard'));

        $this->assertTrue(PhoneLineService::isLocked());
    }

    public function test_line_login_fails_with_wrong_password(): void
    {
        WhatsappLine::factory()->connected()->withPassword('linepass99')->create([
            'is_default' => false,
            'phone'      => '919876543210',
        ]);

        $this->post(route('line.login.submit'), [
            'phone'    => '919876543210',
            'password' => 'wrongpass',
        ])->assertSessionHasErrors('phone');

        $this->assertFalse(PhoneLineService::isLocked());
    }

    public function test_line_login_fails_with_unknown_phone(): void
    {
        $this->post(route('line.login.submit'), [
            'phone'    => '9900000000',
            'password' => 'anypass',
        ])->assertSessionHasErrors('phone');
    }

    public function test_line_login_fails_when_no_password_set(): void
    {
        WhatsappLine::factory()->connected()->create([
            'is_default' => false,
            'phone'      => '919876543210',
        ]);

        $this->post(route('line.login.submit'), [
            'phone'    => '919876543210',
            'password' => 'somepass',
        ])->assertSessionHasErrors('phone');
    }

    public function test_phone_lines_page_shows_open_login_link_button(): void
    {
        $this->actingAsTenantUser()
            ->get(route('profile.phone-lines.index'))
            ->assertSee('Open Login Link');
    }

    public function test_phone_lines_page_shows_share_link_banner(): void
    {
        $url = route('line.login');

        $this->actingAsTenantUser()
            ->get(route('profile.phone-lines.index'))
            ->assertSee($url);
    }

    public function test_phone_lines_page_shows_password_set_badge(): void
    {
        WhatsappLine::factory()->connected()->withPassword()->create(['is_default' => false]);

        $this->actingAsTenantUser()
            ->get(route('profile.phone-lines.index'))
            ->assertSee('Password set');
    }

    public function test_phone_lines_page_shows_no_password_badge(): void
    {
        WhatsappLine::factory()->connected()->create(['is_default' => false]);

        $this->actingAsTenantUser()
            ->get(route('profile.phone-lines.index'))
            ->assertSee('No password');
    }

    public function test_phone_lines_page_shows_disabled_open_inbox_when_no_password(): void
    {
        WhatsappLine::factory()->connected()->create(['is_default' => false]);

        $this->actingAsTenantUser()
            ->get(route('profile.phone-lines.index'))
            ->assertSee('Set a Number Access password above first');
    }
}
