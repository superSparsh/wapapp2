<?php

declare(strict_types=1);

namespace App\Domains\LegacyMigration\Importers;

use App\Domains\LegacyMigration\DTO\LegacyCustomerSnapshot;
use App\Domains\LegacyMigration\Support\LegacyConnection;
use App\Domains\LegacyMigration\Support\LegacyTemplateCategoryMapper;
use App\Domains\LegacyMigration\Support\MigrationIdMap;
use App\Domains\LegacyMigration\Support\MigrationReport;
use App\Domains\Templates\Enums\TemplateSource;
use App\Domains\Templates\Enums\TemplateStatus;
use App\Domains\Templates\Services\TemplateRegistryService;
use App\Models\Template;
use App\Models\Tenant;
use Illuminate\Support\Str;

final class TemplateImporter implements LegacyImporter
{
    public function __construct(
        private readonly LegacyConnection $legacy,
        private readonly TemplateRegistryService $registry,
    ) {}

    public function key(): string
    {
        return 'templates';
    }

    public function import(
        LegacyCustomerSnapshot $customer,
        Tenant $tenant,
        MigrationIdMap $ids,
        MigrationReport $report,
        bool $dryRun = false,
    ): void {
        if (! $this->legacy->tableExists('new_templates')) {
            return;
        }

        $rows = $this->legacyTemplatesQuery($customer->id)->get();

        foreach ($rows as $row) {
            $legacyId = (int) $row->id;
            // CAMS TemplateCode lives in new_templates.template_code — never invent from name.
            $code = $this->resolveTemplateCode($row);
            if ($code === null) {
                $report->warn(sprintf(
                    'Skipped new_templates#%d (%s): missing template_code for customer_id=%d.',
                    $legacyId,
                    trim((string) ($row->template_name ?? 'untitled')),
                    $customer->id,
                ));
                $report->bump($this->key(), 'skipped');

                continue;
            }

            $name = trim((string) ($row->template_name ?? 'Untitled Template'));
            $lineId = isset($row->new_contact_id) && $row->new_contact_id
                ? $ids->getInt('line', (int) $row->new_contact_id)
                : null;

            $body = (string) ($row->actual_body ?? $row->body ?? '');
            $status = $this->mapStatus($row->status ?? null);
            $category = LegacyTemplateCategoryMapper::fromLegacyRow($row);
            $language = $this->mapLanguage($row->language ?? $row->lang ?? null);
            $payload = $this->buildWizardPayload($row, $name, $body, $category, $language, $status, $code);

            if ($dryRun) {
                $exists = $this->findExisting($legacyId, $code, $lineId, $name, $language) !== null;
                $report->bump($this->key(), $exists ? 'updated' : 'created');

                continue;
            }

            $template = $this->findExisting($legacyId, $code, $lineId, $name, $language);

            $attributes = [
                'code' => $code,
                'name' => $name,
                'language' => $language,
                'category' => $category,
                'status' => $status,
                // Legacy DB import is local catalog data — not a live CAMS pull.
                'source' => TemplateSource::Local,
                'whatsapp_line_id' => $lineId,
                'team_member_name' => $row->team_member_name ?? null,
                'payload' => $payload,
                'body_preview' => Str::limit(strip_tags($body), 240),
                'synced_at' => null,
            ];

            if ($template !== null) {
                $template->forceFill($attributes)->save();
                $report->bump($this->key(), 'updated');
            } else {
                $template = Template::query()->create($attributes);
                $report->bump($this->key(), 'created');
            }

            $ids->put('template', $legacyId, $template->id);
        }

        if (! $dryRun) {
            $pruned = $this->registry->pruneCamsDuplicatesOfLocal();
            if ($pruned > 0) {
                $report->warn("Removed {$pruned} CAMS-synced template duplicate(s) that matched legacy/local imports.");
            }
        }
    }

    private function findExisting(
        int $legacyId,
        string $code,
        ?int $lineId,
        string $name,
        string $language,
    ): ?Template {
        $byLegacy = Template::query()
            ->where('payload->legacy_id', $legacyId)
            ->first();

        if ($byLegacy !== null) {
            return $byLegacy;
        }

        $byCode = Template::query()
            ->where('code', $code)
            ->when($lineId !== null, fn ($q) => $q->where('whatsapp_line_id', $lineId))
            ->first();

        if ($byCode !== null) {
            return $byCode;
        }

        return Template::query()
            ->where('name', $name)
            ->where('language', $language)
            ->where('source', TemplateSource::Local)
            ->when($lineId !== null, fn ($q) => $q->where('whatsapp_line_id', $lineId))
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function buildWizardPayload(
        object $row,
        string $name,
        string $body,
        string $category,
        string $language,
        TemplateStatus $status,
        string $code,
    ): array {
        $headerType = $this->mapHeaderType($row->header_type ?? null);
        $headerText = (string) ($row->header_desc ?? $row->header_text ?? '');
        $headerMedia = filled($row->header_media ?? null) ? (string) $row->header_media : null;
        $footer = (string) ($row->footer_desc ?? $row->footer ?? '');

        $buttons = [];
        if (filled($row->button_desc ?? null)) {
            $hasLink = filled($row->button_link ?? null);
            $buttons[] = [
                'text' => (string) $row->button_desc,
                'type' => $hasLink ? 'url' : 'quick_reply',
                'url' => $hasLink ? (string) $row->button_link : '',
                'flow_id' => '',
            ];
        }
        if (filled($row->phone_desc ?? null)) {
            $buttons[] = [
                'text' => (string) $row->phone_desc,
                'type' => 'phone',
                'url' => (string) ($row->phone_link ?? ''),
                'flow_id' => '',
            ];
        }

        $buttonMode = $buttons === []
            ? ''
            : (collect($buttons)->contains(fn (array $b) => in_array($b['type'], ['url', 'phone'], true))
                ? 'call_to_action'
                : 'quick_reply');

        $defaults = Template::defaultPayload();

        return array_replace_recursive($defaults, [
            'meta' => [
                'name' => $name,
                'category' => $category,
                'language' => $language,
                'template_type' => 'regular',
                'setup_completed' => $status !== TemplateStatus::Draft,
            ],
            'header' => [
                'type' => $headerType,
                'text' => $headerType === 'text' ? $headerText : '',
                'media_path' => null,
                'media_url' => in_array($headerType, ['image', 'video', 'document'], true) ? $headerMedia : null,
                'use_url' => filled($headerMedia),
                'doc_name' => $headerType === 'document' ? basename((string) $headerMedia) : null,
            ],
            'body' => [
                'text' => $body,
                'samples' => [],
            ],
            'footer' => [
                'text' => $footer,
            ],
            'buttons' => $buttons,
            'button_mode' => $buttonMode,
            'legacy_id' => (int) $row->id,
            'legacy_uid' => $row->uid ?? null,
            'legacy_template_code' => $code,
            'legacy_real_template_name' => $row->real_template_name ?? null,
        ]);
    }

    private function mapHeaderType(mixed $raw): string
    {
        $value = strtolower(trim((string) ($raw ?? 'none')));

        return match (true) {
            in_array($value, ['text', 'txt'], true) => 'text',
            in_array($value, ['image', 'img', 'picture'], true) => 'image',
            in_array($value, ['video'], true) => 'video',
            in_array($value, ['document', 'doc', 'pdf'], true) => 'document',
            default => 'none',
        };
    }

    /**
     * @return \Illuminate\Database\Query\Builder
     */
    private function legacyTemplatesQuery(int $customerId)
    {
        $query = $this->legacy->db()->table('new_templates')
            ->where('new_templates.customer_id', $customerId)
            ->orderBy('new_templates.id');

        if (
            $this->legacy->tableExists('new_template_categories')
            && $this->legacy->hasColumn('new_templates', 'new_template_category_id')
        ) {
            $query
                ->leftJoin(
                    'new_template_categories',
                    'new_template_categories.id',
                    '=',
                    'new_templates.new_template_category_id',
                )
                ->select([
                    'new_templates.*',
                    'new_template_categories.category_name as legacy_category_name',
                ]);
        }

        return $query;
    }

    private function mapLanguage(mixed $raw): string
    {
        $value = trim((string) ($raw ?? ''));

        // Preserve exact legacy value (en, en_GB, hi, …). Only fill a default when blank.
        return $value !== '' ? $value : 'en';
    }

    /**
     * Only new_templates.template_code is a valid CAMS TemplateCode.
     */
    private function resolveTemplateCode(object $row): ?string
    {
        $code = trim((string) ($row->template_code ?? ''));

        if ($code === '' || str_contains($code, ' ')) {
            return null;
        }

        return Str::limit($code, 100, '');
    }

    private function mapStatus(mixed $status): TemplateStatus
    {
        $value = strtolower((string) $status);

        return match (true) {
            in_array($value, ['approved', 'active', '1', 'live'], true) => TemplateStatus::Approved,
            in_array($value, ['rejected', 'failed', 'error'], true) => TemplateStatus::Rejected,
            in_array($value, ['pending', 'submitted', 'in_review', 'review'], true) => TemplateStatus::PendingReview,
            default => TemplateStatus::Draft,
        };
    }
}
