<?php

declare(strict_types=1);

namespace App\Domains\Api\Services;

use App\Domains\Campaigns\Services\CampaignService;
use App\Domains\Templates\Enums\TemplateStatus;
use App\Domains\Templates\Support\CamsTemplateIdentity;
use App\Models\Campaign;
use App\Models\MailList;
use App\Models\Template;
use App\Models\WhatsappLine;
use App\Support\PhoneNormalizer;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Partner API campaign create / schedule / send-now.
 */
class PartnerCampaignService
{
    public function __construct(
        private readonly CampaignService $campaigns,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function createAndDispatch(array $input): Campaign
    {
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            throw ValidationException::withMessages(['name' => 'Campaign name is required.']);
        }

        $template = $this->resolveTemplate((string) ($input['template_uid'] ?? ''));
        $list = $this->resolveList((string) ($input['list_uid'] ?? ''));
        $line = $this->resolveLine((string) ($input['phone_number'] ?? ''));

        $variables = $input['variables_array']
            ?? $input['template_variables']
            ?? $input['variables']
            ?? null;
        if (is_string($variables)) {
            $decoded = json_decode($variables, true);
            $variables = is_array($decoded) ? $decoded : null;
        }
        if (! is_array($variables)) {
            $variables = null;
        }

        $scheduledAt = $this->resolveSchedule($input['schedule_datetime'] ?? null);

        $campaign = $this->campaigns->create([
            'name' => $name,
            'audience_id' => $list->id,
            'whatsapp_line_id' => $line->id,
            'template_id' => $template->id,
            'template_variables' => $variables,
            'scheduled_at' => $scheduledAt?->toDateTimeString(),
        ]);

        // Send immediately when no future schedule was provided.
        if ($scheduledAt === null || $scheduledAt->lessThanOrEqualTo(now())) {
            return $this->campaigns->launch($campaign);
        }

        return $this->campaigns->schedule($campaign->fresh() ?? $campaign);
    }

    private function resolveTemplate(string $uid): Template
    {
        $uid = trim($uid);
        if ($uid === '') {
            throw ValidationException::withMessages(['template_uid' => 'template_uid is required.']);
        }

        $template = Template::query()->where('uuid', $uid)->first();
        if (! $template instanceof Template) {
            throw ValidationException::withMessages(['template_uid' => 'Template not found.']);
        }

        if ($template->status !== TemplateStatus::Approved) {
            throw ValidationException::withMessages(['template_uid' => 'Template must be approved.']);
        }

        $code = $template->whatsappCode();
        if ($code === null || ! CamsTemplateIdentity::isProviderCode($code)) {
            throw ValidationException::withMessages([
                'template_uid' => 'Template is missing a valid WhatsApp template code.',
            ]);
        }

        return $template;
    }

    private function resolveList(string $uid): MailList
    {
        $uid = trim($uid);
        if ($uid === '') {
            throw ValidationException::withMessages(['list_uid' => 'list_uid is required.']);
        }

        $list = MailList::query()->where('uuid', $uid)->first();
        if (! $list instanceof MailList) {
            throw ValidationException::withMessages(['list_uid' => 'Audience list not found.']);
        }

        return $list;
    }

    private function resolveLine(string $phone): WhatsappLine
    {
        $phone = trim($phone);
        if ($phone === '') {
            $line = WhatsappLine::query()->orderByDesc('is_default')->orderBy('id')->first();
            if (! $line instanceof WhatsappLine) {
                throw ValidationException::withMessages([
                    'phone_number' => 'No WhatsApp number is configured for this account.',
                ]);
            }

            return $line;
        }

        $variants = PhoneNormalizer::lookupVariants($phone);
        $line = WhatsappLine::query()->whereIn('phone', $variants)->first();
        if (! $line instanceof WhatsappLine) {
            throw ValidationException::withMessages([
                'phone_number' => 'Sender WhatsApp number is not registered on this account.',
            ]);
        }

        return $line;
    }

    private function resolveSchedule(mixed $value): ?Carbon
    {
        if ($value === null || $value === '' || $value === 'null') {
            return null;
        }

        try {
            return Carbon::parse((string) $value, 'Asia/Kolkata');
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                'schedule_datetime' => 'Invalid schedule_datetime. Use a valid date/time string.',
            ]);
        }
    }
}
