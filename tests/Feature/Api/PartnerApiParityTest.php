<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Enums\TenantUserAccountType;
use App\Models\MailList;
use App\Models\Template;
use App\Models\TenantUserAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class PartnerApiParityTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    private string $apiToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();

        $this->apiToken = Str::random(60);
        $this->testUser->forceFill(['api_token' => $this->apiToken])->save();

        tenancy()->central(function (): void {
            TenantUserAccess::query()->create([
                'email' => strtolower((string) $this->testUser->email),
                'phone' => $this->testUser->phone,
                'tenant_id' => $this->testTenant->id,
                'account_type' => TenantUserAccountType::Owner,
                'is_active' => true,
                'api_token' => $this->apiToken,
            ]);
        });
    }

    protected function tearDown(): void
    {
        $this->tearDownTenant();
        parent::tearDown();
    }

    public function test_lists_crud_and_subscription_templates_business_stats(): void
    {
        $create = $this->post('/api/v1/lists', [
            'api_token' => $this->apiToken,
            'name' => 'API List',
        ], ['Accept' => 'application/json'])->assertCreated();

        $listUid = (string) $create->json('data.uid');
        $this->assertNotSame('', $listUid);

        $this->get('/api/v1/lists/'.$listUid.'?api_token='.$this->apiToken, ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.uid', $listUid);

        $subscriber = $this->post('/api/v1/subscribers', [
            'api_token' => $this->apiToken,
            'list_uid' => $listUid,
            'whatsapp_number' => '919811122233',
            'first_name' => 'Api',
            'last_name' => 'User',
        ], ['Accept' => 'application/json'])->assertCreated();

        $subUid = (string) $subscriber->json('data.uid');
        $this->get('/api/v1/subscribers/'.$subUid.'?api_token='.$this->apiToken, ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.uid', $subUid);

        $this->patch('/api/v1/subscribers/'.$subUid, [
            'api_token' => $this->apiToken,
            'first_name' => 'Updated',
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.first_name', 'Updated');

        $template = Template::factory()->create([
            'code' => '1125253687146348599',
            'name' => 'Partner Welcome',
            'payload' => array_replace_recursive(Template::defaultPayload(), [
                'body' => ['text' => 'Hello {{name}}'],
                'header' => ['type' => 'text', 'text' => 'Welcome'],
                'footer' => ['text' => 'Thanks'],
                'buttons' => [
                    ['text' => 'Shop', 'type' => 'url', 'url' => 'https://example.com'],
                    ['text' => 'Call us', 'type' => 'phone', 'url' => '919999999999'],
                ],
            ]),
        ]);

        $list = $this->get('/api/v1/templates?api_token='.$this->apiToken, ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonFragment([
                'uid' => $template->uuid,
                'name' => 'Partner Welcome',
                'template_category' => 'Marketing',
                'body' => 'Hello {{name}}',
                'header_type' => 'text',
                'header_description' => 'Welcome',
                'footer_description' => 'Thanks',
                'button_description' => 'Shop',
                'button_link' => 'https://example.com',
                'phone_button_description' => 'Call us',
                'phone_button_link' => '919999999999',
                'status' => 'Approved',
            ]);

        $this->assertIsArray($list->json());
        $this->assertArrayNotHasKey('data', $list->json());
        $this->assertArrayNotHasKey('meta', $list->json());

        $this->get('/api/v1/templates/'.$template->uuid.'?api_token='.$this->apiToken, ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('template.uid', $template->uuid)
            ->assertJsonMissingPath('data.uid');

        $this->post('/api/v1/variables', [
            'api_token' => $this->apiToken,
            'name' => 'offer_code',
            'type' => 'Dynamic',
            'variable_type' => 'String',
            'value' => 'SAVE20',
        ], ['Accept' => 'application/json'])->assertCreated();

        $this->get('/api/v1/variables?api_token='.$this->apiToken, ['Accept' => 'application/json'])
            ->assertOk();

        $this->get('/api/v1/subscription-details?api_token='.$this->apiToken, ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->get('/api/v1/business-details-and-phones?api_token='.$this->apiToken, ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonStructure(['data' => ['phones']]);

        $this->get('/api/v1/business-conversations/statistics?api_token='.$this->apiToken, ['Accept' => 'application/json'])
            ->assertOk();

        $this->get('/api/v1/service-conversations/statistics?api_token='.$this->apiToken, ['Accept' => 'application/json'])
            ->assertOk();

        $this->get('/api/v1/wallet-transactions?api_token='.$this->apiToken, ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonStructure(['current_wallet_amount', 'wallet_transactions']);

        $login = $this->post('/api/v1/login-token', [
            'api_token' => $this->apiToken,
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonStructure(['token', 'url']);

        $this->assertNotSame('', (string) $login->json('token'));

        $this->delete('/api/v1/subscribers/'.$subUid.'?api_token='.$this->apiToken, [], ['Accept' => 'application/json'])
            ->assertOk();

        $this->delete('/api/v1/lists/'.$listUid.'?api_token='.$this->apiToken, [], ['Accept' => 'application/json'])
            ->assertOk();

        $this->assertNull(MailList::query()->where('uuid', $listUid)->first());
    }
}
