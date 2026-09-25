<?php

declare(strict_types=1);

namespace App\Domains\FormBuilder\Services;

use App\Domains\FormBuilder\Enums\FormStatus;
use App\Domains\FormBuilder\Enums\FieldType;
use App\Domains\FormBuilder\Support\FormActorContext;
use App\Domains\FormBuilder\Support\FormFieldNormalizer;
use App\Models\FormSubmission;
use App\Models\SignupForm;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FormBuilderService
{
    public function __construct(
        private readonly FormActorContext $actorContext,
    ) {}

    /**
     * Create a new signup form with the given data.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): SignupForm
    {
        $name = trim($data['name'] ?? 'Untitled Form');
        $slug = SignupForm::generateSlug($name);
        $fields = $this->normalizeFields($data['fields'] ?? FieldType::defaultFields());
        $logoPath = $data['logo_path'] ?? $this->logoPathFromFields($fields);

        return DB::transaction(function () use ($data, $name, $slug, $fields, $logoPath): SignupForm {
            $form = SignupForm::query()->create([
                'name' => $name,
                'slug' => $slug,
                'status' => FormStatus::fromBoolean(
                    filter_var($data['activate'] ?? false, FILTER_VALIDATE_BOOLEAN)
                ),
                'whatsapp_line_id' => $this->actorContext->whatsappLineId(),
                'list_id' => $data['list_id'] ?? null,
                'template_id' => $data['template_id'] ?? null,
                'team_member_id' => $this->actorContext->teamMemberId(),
                'team_member_name' => $this->actorContext->teamMemberName(),
                'fields' => $fields,
                'logo_path' => $logoPath,
                'embed_settings' => $data['embed_settings'] ?? SignupForm::defaultEmbedSettings(),
                'redirect_url' => $data['redirect_url'] ?? null,
                'custom_css' => $data['custom_css'] ?? null,
                'embed_code' => null, // Generated below
            ]);

            $form->embed_code = $form->generateEmbedCode();
            $form->save();

            return $form->refresh();
        });
    }

    /**
     * Update an existing signup form.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(SignupForm $form, array $data): SignupForm
    {
        return DB::transaction(function () use ($form, $data): SignupForm {
            $updates = [];

            if (isset($data['name'])) {
                $updates['name'] = trim($data['name']);
            }

            if (isset($data['list_id'])) {
                $updates['list_id'] = $data['list_id'];
            }

            if (isset($data['template_id'])) {
                $updates['template_id'] = $data['template_id'];
            }

            if (isset($data['fields'])) {
                $updates['fields'] = $this->normalizeFields($data['fields']);
                if (! array_key_exists('logo_path', $data)) {
                    $fromFields = $this->logoPathFromFields($updates['fields']);
                    if ($fromFields !== null) {
                        $updates['logo_path'] = $fromFields;
                    }
                }
            }

            if (array_key_exists('logo_path', $data)) {
                $updates['logo_path'] = $data['logo_path'];
            }

            if (isset($data['embed_settings'])) {
                $updates['embed_settings'] = array_merge(
                    $form->embedSettings(),
                    $data['embed_settings']
                );
            }

            if (array_key_exists('redirect_url', $data)) {
                $updates['redirect_url'] = $data['redirect_url'];
            }

            if (array_key_exists('custom_css', $data)) {
                $updates['custom_css'] = $data['custom_css'];
            }

            if (array_key_exists('activate', $data)) {
                $updates['status'] = FormStatus::fromBoolean(
                    filter_var($data['activate'], FILTER_VALIDATE_BOOLEAN)
                );
            }

            if (! empty($updates)) {
                $form->update($updates);
                // Regenerate embed code if name changed
                $form->embed_code = $form->generateEmbedCode();
                $form->save();
            }

            return $form->refresh();
        });
    }

    /**
     * Toggle the form status between Active and Inactive.
     */
    public function toggleStatus(SignupForm $form): SignupForm
    {
        $form->status = $form->isActive()
            ? FormStatus::Inactive
            : FormStatus::Active;
        $form->save();

        return $form->refresh();
    }

    /**
     * Soft-delete a form.
     */
    public function delete(SignupForm $form): bool
    {
        return $form->delete();
    }

    /**
     * Live form delivery stats from submission timestamps (legacy conversations parity).
     *
     * @return array{total: int, sent: int, delivered: int, read: int, failed: int}
     */
    public function liveSubmissionStats(SignupForm $form): array
    {
        $counts = FormSubmission::query()
            ->where('signup_form_id', $form->id)
            ->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN sent_at IS NOT NULL THEN 1 ELSE 0 END) as sent,
                SUM(CASE WHEN delivered_at IS NOT NULL THEN 1 ELSE 0 END) as delivered,
                SUM(CASE WHEN read_at IS NOT NULL THEN 1 ELSE 0 END) as read_count,
                SUM(CASE WHEN message_status = 'failed' OR (failed_at IS NOT NULL AND delivered_at IS NULL) THEN 1 ELSE 0 END) as failed
            ")
            ->first();

        return [
            'total' => (int) ($counts->total ?? 0),
            'sent' => (int) ($counts->sent ?? 0),
            'delivered' => (int) ($counts->delivered ?? 0),
            'read' => (int) ($counts->read_count ?? 0),
            'failed' => (int) ($counts->failed ?? 0),
        ];
    }

    /**
     * Paginated submission log with optional status filter (campaign recipient-log parity).
     *
     * @return LengthAwarePaginator<int, FormSubmission>
     */
    public function submissionLog(SignupForm $form, int $perPage = 10, ?string $status = null): LengthAwarePaginator
    {
        $query = FormSubmission::query()
            ->where('signup_form_id', $form->id)
            ->with('contact:id,name,phone')
            ->orderByDesc('created_at');

        $this->applySubmissionStatusFilter($query, $status);

        return $query->paginate($perPage)->withQueryString();
    }

    /**
     * Stream CSV export of form submissions (optional status filter).
     */
    public function exportSubmissionsCsv(SignupForm $form, ?string $status = null): StreamedResponse
    {
        $filename = 'form-submissions-'.$form->id.'-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($form, $status): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'SI. No',
                'Contact Phone',
                'Contact Name',
                'Status',
                'Reason',
                'Submitted At',
                'Sent At',
                'Delivered At',
                'Read At',
                'Failed At',
            ]);

            $query = FormSubmission::query()
                ->where('signup_form_id', $form->id)
                ->with('contact:id,name,phone')
                ->orderByDesc('created_at');

            $this->applySubmissionStatusFilter($query, $status);

            $index = 0;
            foreach ($query->cursor() as $submission) {
                $index++;
                fputcsv($handle, [
                    $index,
                    $submission->phone ?? 'N/A',
                    $submission->contact?->name ?? 'N/A',
                    ucfirst($submission->displayStatus()),
                    $submission->failed_reason ?: '—',
                    $submission->created_at?->format('Y-m-d H:i:s') ?? '',
                    $submission->sent_at?->format('Y-m-d H:i:s') ?? '',
                    $submission->delivered_at?->format('Y-m-d H:i:s') ?? '',
                    $submission->read_at?->format('Y-m-d H:i:s') ?? '',
                    $submission->failed_at?->format('Y-m-d H:i:s') ?? '',
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    /**
     * @param  Builder<FormSubmission>  $query
     */
    private function applySubmissionStatusFilter(Builder $query, ?string $status): void
    {
        if ($status === null || $status === '') {
            return;
        }

        match ($status) {
            'sent' => $query->whereNotNull('sent_at'),
            'delivered' => $query->whereNotNull('delivered_at'),
            'read' => $query->whereNotNull('read_at'),
            'failed' => $query->where(function (Builder $q): void {
                $q->where('message_status', 'failed')
                    ->orWhere(function (Builder $inner): void {
                        $inner->whereNotNull('failed_at')->whereNull('delivered_at');
                    });
            }),
            'pending' => $query->whereNull('sent_at')
                ->where(function (Builder $q): void {
                    $q->whereNull('message_status')
                        ->orWhereNotIn('message_status', ['failed', 'sent', 'delivered', 'read']);
                })
                ->whereNull('failed_at'),
            default => null,
        };
    }

    /**
     * Bulk-delete forms by UUIDs.
     *
     * @param  array<string>  $uuids
     */
    public function bulkDelete(array $uuids): int
    {
        return SignupForm::query()->whereIn('uuid', $uuids)->delete();
    }

    /**
     * Store the uploaded logo and return the path.
     *
     * Tenant public disk is not served by /storage/... (central symlink), so
     * previews must use logoPreviewUrl() / streamLogo().
     */
    public function storeLogo($file): string
    {
        $directory = config('form-builder.logo_directory', 'form-logos');
        $disk = config('form-builder.logo_disk', 'public');

        Storage::disk($disk)->makeDirectory($directory);

        $path = $file->store($directory, $disk);

        if ($path === false || $path === '') {
            throw new \RuntimeException('Failed to store form logo.');
        }

        return $path;
    }

    public function logoPreviewUrl(string $path): string
    {
        $path = ltrim(str_replace('\\', '/', $path), '/');

        return route('form-builder.logos.show', ['path' => $path]);
    }

    public function publicLogoPreviewUrl(string $tenantId, string $path): string
    {
        $path = ltrim(str_replace('\\', '/', $path), '/');

        return route('public.form.logo', ['tenant' => $tenantId, 'path' => $path]);
    }

    public function streamLogo(string $path): StreamedResponse
    {
        $path = $this->normalizeLogoPath($path);
        $disk = Storage::disk(config('form-builder.logo_disk', 'public'));

        if (! $disk->exists($path)) {
            abort(404);
        }

        $mime = (string) ($disk->mimeType($path) ?: 'application/octet-stream');

        return response()->stream(function () use ($disk, $path): void {
            $stream = $disk->readStream($path);
            if (! is_resource($stream)) {
                return;
            }

            fpassthru($stream);
            fclose($stream);
        }, 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="'.basename($path).'"',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    private function normalizeLogoPath(string $path): string
    {
        $path = ltrim(str_replace('\\', '/', $path), '/');
        $path = preg_replace('#\.\./#', '', $path) ?? $path;

        $directory = trim((string) config('form-builder.logo_directory', 'form-logos'), '/');
        if ($directory === '' || ! str_starts_with($path, $directory.'/')) {
            abort(404);
        }

        return $path;
    }

    /**
     * Normalize field definitions — ensure structure and preserve user order.
     *
     * @param  array<int, array<string, mixed>>  $fields
     * @return array<int, array<string, mixed>>
     */
    private function normalizeFields(array $fields): array
    {
        $maxFields = (int) config('form-builder.max_fields', 20);
        $fields = FormFieldNormalizer::normalizeList($fields);

        $normalized = [];
        foreach ($fields as $field) {
            $typeStr = (string) ($field['type'] ?? 'input');
            $fieldType = FieldType::tryFrom($typeStr) ?? FieldType::Input;

            $entry = array_merge($fieldType->defaultConfig(), $field);
            $entry['type'] = $fieldType->value;

            $normalized[] = $entry;
        }

        // Ensure locked fields (phone) are always present
        $this->ensureLockedFields($normalized);

        // Slice to max after ensuring locked fields — preserve user field order (shuffle).
        $normalized = array_slice($normalized, 0, $maxFields);

        foreach ($normalized as &$entry) {
            $type = FieldType::tryFrom((string) ($entry['type'] ?? ''));
            if (in_array($type, [FieldType::Logo, FieldType::Header, FieldType::Paragraph], true)) {
                $entry['required'] = false;
            }
        }
        unset($entry);

        return $normalized;
    }

    /**
     * @param  list<array<string, mixed>>  $fields
     */
    private function logoPathFromFields(array $fields): ?string
    {
        foreach ($fields as $field) {
            if (($field['type'] ?? '') !== FieldType::Logo->value) {
                continue;
            }

            $path = $field['image_path'] ?? null;

            return is_string($path) && $path !== '' ? $path : null;
        }

        return null;
    }

    /**
     * Ensure all locked fields (e.g. phone) are present in the fields array.
     *
     * @param  array<int, array<string, mixed>>  $fields
     */
    private function ensureLockedFields(array &$fields): void
    {
        foreach (FieldType::cases() as $fieldType) {
            if (! $fieldType->isLocked()) {
                continue;
            }

            $found = false;
            foreach ($fields as $field) {
                if (($field['type'] ?? '') === $fieldType->value) {
                    $found = true;
                    break;
                }
            }

            if (! $found) {
                $fields[] = $fieldType->defaultConfig();
            }
        }
    }
}
