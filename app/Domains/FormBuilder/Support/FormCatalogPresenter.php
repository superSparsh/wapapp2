<?php

declare(strict_types=1);

namespace App\Domains\FormBuilder\Support;

use App\Domains\FormBuilder\Enums\FormStatus;
use App\Models\SignupForm;
use Illuminate\Support\Collection;

class FormCatalogPresenter
{
    /**
     * Transform a collection of SignupForm models into table-row arrays for the index view.
     *
     * @param  Collection<int, SignupForm>  $forms
     * @param  int  $page  Current page (1-based)
     * @param  int  $perPage  Items per page
     * @return list<array<string, mixed>>
     */
    public function tableRows(Collection $forms, int $page = 1, int $perPage = 10): array
    {
        $offset = ($page - 1) * $perPage;

        return $forms
            ->slice(0, $perPage)  // Collection is already paginated by the controller
            ->values()
            ->map(fn (SignupForm $form, int $index): array => [
                'serial' => str_pad((string) ($offset + $index + 1), 2, '0', STR_PAD_LEFT),
                'uuid' => $form->uuid,
                'name' => $form->name,
                'slug' => $form->slug,
                'url' => $form->publicUrl(),
                'status' => $form->status->label(),
                'status_class' => $form->status->chipClass(),
                'is_active' => $form->isActive(),
                'created_at' => $form->created_at?->format('Y-m-d h:i A') ?? '—',
                'stats' => [
                    'sent' => $form->sent_count,
                    'read' => $form->read_count,
                    'delivered' => $form->delivered_count,
                    'failed' => $form->failed_count,
                    'total' => $form->submission_count,
                ],
                'edit_url' => route('form-builder.edit', $form),
                'statistics_url' => route('form-builder.statistics', $form),
                'delete_url' => route('form-builder.destroy', $form),
                'toggle_url' => route('form-builder.toggle', $form),
                'copy_url' => $form->publicUrl(),
            ])
            ->all();
    }
}
