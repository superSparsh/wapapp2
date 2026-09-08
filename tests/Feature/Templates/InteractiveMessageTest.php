<?php

namespace Tests\Feature\Templates;

use App\Models\InteractiveMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class InteractiveMessageTest extends TestCase
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

    public function test_owner_can_view_free_templates_tab(): void
    {
        InteractiveMessage::query()->create([
            'name' => 'Welcome Buttons',
            'type' => 'button',
            'content' => ['body' => 'Hello', 'footer' => '', 'buttons' => []],
            'whatsapp_line_id' => $this->testLine->id,
        ]);

        $this->actingAsTenantUser()
            ->get(route('templates.index', ['tab' => 'free']))
            ->assertOk()
            ->assertSee('Welcome Buttons')
            ->assertSee('Free Template Messages');
    }

    public function test_owner_can_create_free_template_message(): void
    {
        $this->actingAsTenantUser()
            ->post(route('templates.free.store'), [
                'name' => 'Order Status',
                'type' => 'button',
                'body' => 'Your order is ready',
                'footer' => 'Thanks',
                'buttons' => [
                    ['text' => 'Track Order', 'type' => 'quick_reply', 'url' => ''],
                ],
            ])
            ->assertRedirect(route('templates.index', ['tab' => 'free']));

        $this->assertDatabaseHas('interactive_messages', [
            'name' => 'Order Status',
            'type' => 'button',
        ]);
    }
}
