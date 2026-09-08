<?php

declare(strict_types=1);

namespace App\Domains\FormBuilder\Services;

use App\Domains\FormBuilder\Enums\FormStatus;
use App\Domains\FormBuilder\Enums\FieldType;
use App\Domains\FormBuilder\Support\FormActorContext;
use App\Domains\FormBuilder\Support\FormFieldNormalizer;
use App\Models\SignupForm;
use Illuminate\Support\Facades\DB;

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

        return DB::transaction(function () use ($data, $name, $slug, $fields): SignupForm {
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
                'logo_path' => $data['logo_path'] ?? null,
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
            }

            if (isset($data['logo_path'])) {
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
     */
    public function storeLogo($file): string
    {
        $directory = config('form-builder.logo_directory', 'form-logos');
        $disk = config('form-builder.logo_disk', 'local');

        return $file->store($directory, $disk);
    }

    /**
     * Normalize field definitions — sort by always-top priority, ensure structure.
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

        // Slice to max after ensuring locked fields
        $normalized = array_slice($normalized, 0, $maxFields);

        // Sort: always-top fields first (logo, header, phone), then rest in original order
        usort($normalized, function (array $a, array $b): int {
            $aType = FieldType::tryFrom($a['type']);
            $bType = FieldType::tryFrom($b['type']);

            $aTop = $aType?->isAlwaysTop() ?? false;
            $bTop = $bType?->isAlwaysTop() ?? false;

            if ($aTop && ! $bTop) {
                return -1;
            }
            if (! $aTop && $bTop) {
                return 1;
            }

            return 0;
        });

        return $normalized;
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
