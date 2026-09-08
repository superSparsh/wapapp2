<?php

declare(strict_types=1);

namespace Tests\Unit\Campaigns;

use App\Domains\Audience\Enums\ContactStatus;
use App\Domains\Audience\Models\ListField;
use App\Domains\Campaigns\Services\CampaignTemplateParamsResolver;
use App\Domains\Campaigns\Services\CampaignVariableGridService;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Contact;
use App\Models\MailList;
use App\Models\Template;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class CampaignVariableGridServiceTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    private CampaignVariableGridService $grid;

    private CampaignTemplateParamsResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->grid = app(CampaignVariableGridService::class);
        $this->resolver = app(CampaignTemplateParamsResolver::class);
    }

    protected function tearDown(): void
    {
        $this->tearDownTenant();
        parent::tearDown();
    }

    public function test_auto_fill_uses_contact_name_and_custom_fields(): void
    {
        $list = MailList::factory()->create();
        ListField::query()->create([
            'mail_list_id' => $list->id,
            'label' => 'Company',
            'tag' => 'company',
            'type' => ListField::TYPE_TEXT,
            'sort_order' => 1,
        ]);

        $contact = Contact::factory()->create([
            'mail_list_id' => $list->id,
            'name' => 'Ada Lovelace',
            'phone' => '919876543210',
            'status' => ContactStatus::Subscribed,
            'custom_fields' => ['company' => 'Analytical Engines'],
        ]);

        $index = $this->resolver->listFieldIndex($list->id);
        $auto = $this->resolver->autoFill(
            $contact,
            $contact->phone,
            ['full_name', 'first_name', 'last_name', 'company', 'unsub'],
            $index,
        );

        $this->assertSame('Ada Lovelace', $auto['values']['full_name']);
        $this->assertSame('Ada', $auto['values']['first_name']);
        $this->assertSame('Lovelace', $auto['values']['last_name']);
        $this->assertSame('Analytical Engines', $auto['values']['company']);
        $this->assertSame('contact', $auto['sources']['first_name']);
        $this->assertSame('list', $auto['sources']['company']);
        $this->assertArrayNotHasKey('unsub', $auto['values']);
    }

    public function test_send_merge_prefers_recipient_values_over_defaults(): void
    {
        $list = MailList::factory()->create();
        $contact = Contact::factory()->create([
            'mail_list_id' => $list->id,
            'name' => 'Ada Lovelace',
            'status' => ContactStatus::Subscribed,
        ]);
        $campaign = Campaign::factory()->create([
            'audience_id' => $list->id,
            'template_variables' => ['company' => 'Campaign Default'],
        ]);
        $recipient = CampaignRecipient::factory()->create([
            'campaign_id' => $campaign->id,
            'contact_id' => $contact->id,
            'contact_phone' => $contact->phone,
            'variable_values' => ['company' => 'Per Recipient', 'first_name' => 'Override'],
        ]);

        $params = $this->resolver->forRecipient($campaign, $recipient);

        $this->assertSame('Override', $params['first_name']);
        $this->assertSame('Per Recipient', $params['company']);
        $this->assertSame((string) $contact->id, $params['unsub']);
        $this->assertArrayNotHasKey('cams_group_id', $params);
    }

    public function test_csv_rejects_name_headers_and_applies_custom_columns(): void
    {
        $list = MailList::factory()->create();
        $contact = Contact::factory()->create([
            'mail_list_id' => $list->id,
            'phone' => '919111111111',
            'status' => ContactStatus::Subscribed,
        ]);

        $payload = Template::defaultPayload();
        $payload['body']['text'] = 'Hi $(first_name), code $(offer_code)';
        $template = Template::factory()->create([
            'payload' => $payload,
            'body_preview' => 'Hi $(first_name), code $(offer_code)',
        ]);

        $campaign = Campaign::factory()->create([
            'audience_id' => $list->id,
            'template_id' => $template->id,
        ]);
        $recipient = CampaignRecipient::factory()->create([
            'campaign_id' => $campaign->id,
            'contact_id' => $contact->id,
            'contact_phone' => $contact->phone,
        ]);

        $bad = UploadedFile::fake()->createWithContent(
            'bad.csv',
            "whatsapp_number,first_name,offer_code\n919111111111,Ada,SAVE10\n",
        );
        $badResult = $this->grid->applyCsv($campaign, $template, $bad);
        $this->assertSame('CSV headers must not contain first_name, last_name, or full_name.', $badResult['error'] ?? null);

        $good = UploadedFile::fake()->createWithContent(
            'good.csv',
            "whatsapp_number,offer_code\n919111111111,SAVE10\n",
        );
        $goodResult = $this->grid->applyCsv($campaign, $template, $good);
        $this->assertSame(1, $goodResult['updated']);
        $this->assertSame('SAVE10', $recipient->fresh()->variable_values['offer_code'] ?? null);
    }

    public function test_variable_names_exclude_unsub_and_put_full_name_first(): void
    {
        $payload = Template::defaultPayload();
        $payload['body']['text'] = 'Hi $(company) $(full_name) $(unsub)';
        $template = Template::factory()->create([
            'payload' => $payload,
            'body_preview' => 'Hi $(company) $(full_name) $(unsub)',
        ]);

        $names = $this->grid->variableNames($template);

        $this->assertSame('full_name', $names[0]);
        $this->assertContains('company', $names);
        $this->assertNotContains('unsub', $names);
    }
}
