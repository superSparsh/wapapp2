<?php

declare(strict_types=1);

namespace App\Domains\Audience\Http\Controllers;

use App\Domains\Audience\Models\ListField;
use App\Models\MailList;
use App\Support\PublicId;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ListFieldController extends Controller
{
    /**
     * Manage list fields page.
     */
    public function index(Request $request): View
    {
        $mailList = $request->filled('list')
            ? PublicId::findOrFail(MailList::class, (string) $request->input('list'))
            : null;

        $fields = $mailList
            ? ListField::query()
                ->with('options')
                ->where('mail_list_id', $mailList->id)
                ->orderBy('sort_order')
                ->get()
            : collect();

        return view('audience.list-fields', [
            'mailList' => $mailList,
            'fields' => $fields,
        ]);
    }

    /**
     * Add a new field to a list.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'mail_list_id' => PublicId::uuidExistsRules(MailList::class, nullable: false),
            'label' => 'required|string|max:255',
            'type' => 'required|string|in:' . implode(',', ListField::TYPES),
            'tag' => 'nullable|string|max:255',
            'default_value' => 'nullable|string',
            'required' => 'boolean',
            'visible' => 'boolean',
        ]);

        $mailList = PublicId::findOrFail(MailList::class, $validated['mail_list_id']);
        $maxOrder = ListField::where('mail_list_id', $mailList->id)->max('sort_order') ?? 0;

        ListField::create([
            'uuid' => (string) Str::uuid(),
            'mail_list_id' => $mailList->id,
            'label' => $validated['label'],
            'type' => $validated['type'],
            'tag' => $validated['tag'] ?? null,
            'default_value' => $validated['default_value'] ?? null,
            'required' => $validated['required'] ?? false,
            'visible' => $validated['visible'] ?? true,
            'sort_order' => $maxOrder + 1,
        ]);

        return redirect()->route('audience.list-fields', ['list' => $mailList->uuid])
            ->with('status', 'Field added successfully.');
    }

    /**
     * Update all fields for a list (bulk save).
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'mail_list_id' => PublicId::uuidExistsRules(MailList::class, nullable: false),
            'fields' => 'array',
            'fields.*.id' => PublicId::uuidExistsRules(ListField::class, nullable: false),
            'fields.*.label' => 'required|string|max:255',
            'fields.*.type' => 'required|string|in:' . implode(',', ListField::TYPES),
            'fields.*.tag' => 'nullable|string|max:255',
            'fields.*.default_value' => 'nullable|string',
            'fields.*.required' => 'boolean',
            'fields.*.visible' => 'boolean',
        ]);

        $mailList = PublicId::findOrFail(MailList::class, $validated['mail_list_id']);

        if (! empty($validated['fields'])) {
            foreach ($validated['fields'] as $index => $fieldData) {
                $field = ListField::query()
                    ->where('mail_list_id', $mailList->id)
                    ->where('uuid', $fieldData['id'])
                    ->firstOrFail();

                // Don't allow editing protected tags
                $tag = $field->isProtected() ? $field->tag : ($fieldData['tag'] ?? null);

                $field->update([
                    'label' => $fieldData['label'],
                    'type' => $fieldData['type'],
                    'tag' => $tag,
                    'default_value' => $fieldData['default_value'] ?? null,
                    'required' => filter_var($fieldData['required'] ?? false, FILTER_VALIDATE_BOOLEAN),
                    'visible' => filter_var($fieldData['visible'] ?? false, FILTER_VALIDATE_BOOLEAN),
                    'sort_order' => $index,
                ]);
            }
        }

        return redirect()->route('audience.list-fields', ['list' => $mailList->uuid])
            ->with('status', 'Fields updated successfully.');
    }

    /**
     * Delete a field.
     */
    public function destroy(ListField $field): RedirectResponse
    {
        $mailList = MailList::query()->find($field->mail_list_id);
        $listKey = $mailList?->uuid;

        if ($field->isProtected()) {
            return redirect()->route('audience.list-fields', array_filter(['list' => $listKey]))
                ->withErrors(['field' => 'Cannot delete a protected system field.']);
        }

        $field->options()->delete();
        $field->delete();

        return redirect()->route('audience.list-fields', array_filter(['list' => $listKey]))
            ->with('status', 'Field deleted successfully.');
    }
}
