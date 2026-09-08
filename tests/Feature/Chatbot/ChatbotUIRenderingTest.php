<?php

namespace Tests\Feature\Chatbot;

use App\Models\ChatbotFlow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class ChatbotUIRenderingTest extends TestCase
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

    public function test_chatbot_index_renders_scripts_and_modal(): void
    {
        ChatbotFlow::factory()->count(2)->active()->create();

        $response = $this->actingAsTenantUser()->get(route('chatbot.index'));
        $html = $response->getContent();

        // Write HTML FIRST so we can inspect even if assertions fail
        file_put_contents(base_path('storage/chatbot-debug.html'), $html);

        // 1. Modal exists
        $this->assertStringContainsString('modal-create-chatbot', $html, 'Create modal not found');

        // 2. Open button exists
        $this->assertStringContainsString('open-create-modal-btn', $html, 'Open create modal button not found');

        // 3. Script tag exists in the rendered HTML
        $this->assertStringContainsString('<script>', $html, 'Script tag not found');

        // 4. Toggle switch is rendered
        $this->assertStringContainsString('role="switch"', $html, 'Toggle switch not found');

        // 5. Toggle URL is rendered
        $this->assertStringContainsString('/toggle', $html, 'Toggle URL not found');

        // 6. CSRF meta tag exists
        $this->assertStringContainsString('csrf-token', $html, 'CSRF meta tag not found');

        // 7. DOMContentLoaded listener is in the script
        $this->assertStringContainsString("document.addEventListener('DOMContentLoaded'", $html, 'DOMContentLoaded listener not found');

        // 8. The openModal function exists
        $this->assertStringContainsString('function openModal()', $html, 'openModal function not found');

        // 9. The closeModal function exists
        $this->assertStringContainsString('function closeModal()', $html, 'closeModal function not found');

        // 10. Click listener on openBtn
        $this->assertStringContainsString("openBtn.addEventListener('click', openModal)", $html, 'Open button click listener not found');

        // 11. Form submit handler
        $this->assertStringContainsString("form.addEventListener('submit'", $html, 'Form submit handler not found');

        // 12. The route is properly resolved (not empty)
        $storeRoute = route('chatbot.store');
        $this->assertStringContainsString($storeRoute, $html, 'Store route not found in HTML');

        // 13. Check that the modal backdrop click handler exists
        $this->assertStringContainsString('e.target === modal', $html, 'Modal backdrop click handler not found');

        // 14. Check that toggle form uses PATCH method
        $this->assertStringContainsString('name="_method" value="PATCH"', $html, 'Toggle PATCH method not found');

        // 15. Check toggle submit type
        $this->assertStringContainsString('type="submit"', $html, 'Toggle submit type not found');

    }

    public function test_chatbot_builder_renders_scripts(): void
    {
        $flow = ChatbotFlow::factory()->active()->create(['name' => 'Test Bot']);

        $response = $this->actingAsTenantUser()->get(route('chatbot.edit', $flow));
        $html = $response->getContent();

        file_put_contents(base_path('storage/chatbot-builder-debug.html'), $html);

        $this->assertStringContainsString('builder-section', $html, 'Builder section not found');
        $this->assertStringContainsString('data-action="maximize"', $html, 'Maximize button not found');
        $this->assertStringContainsString('id="chatbot-react-root"', $html, 'React builder mount not found');
        $this->assertStringContainsString(route('chatbot.builder-data', $flow), $html, 'Builder data URL not found');
        $this->assertStringContainsString('flow-name-form', $html, 'Flow name form not found');
        $this->assertStringContainsString('builder-maximized', $html, 'Maximize handler CSS not found');
    }
}
