<?php

declare(strict_types=1);

namespace App\Domains\Audience\Http\Controllers;

use App\Domains\Audience\Models\ListField;
use App\Domains\Audience\Services\ListFieldService;
use App\Models\MailList;
use App\Support\PublicId;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class ListFieldController extends Controller
{
    public function __construct(
        private readonly ListFieldService $listFieldService,
    ) {}

    /**
     * Manage list fields page.
     */
    public function index(Request $request): View
    {
        $mailList = $request->filled('list')
            ? PublicId::findOrFail(MailList::class, (string) $request->input('list'))
            : null;

        if ($mailList !== null) {
            $this->listFieldService->ensureDefaultFields($mailList);
        }

        $fields = $mailList
            ? ListField::query()
                ->with('options')
                ->where('mail_list_id', $mailList->id)
                ->orderBy('sort_order')
                ->orderBy('id')
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
            'type' => 'required|string|in:'.implode(',', ListField::TYPES),
            'tag' => 'nullable|string|max:255',
            'default_value' => 'nullable|string',
            'required' => 'nullable|boolean',
            'visible' => 'nullable|boolean',
        ]);

        $mailList = PublicId::findOrFail(MailList::class, $validated['mail_list_id']);
        $this->listFieldService->ensureDefaultFields($mailList);

        $maxOrder = ListField::where('mail_list_id', $mailList->id)->max('sort_order') ?? 0;
        $tag = trim((string) ($validated['tag'] ?? ''));
        if ($tag === '') {
            $tag = $this->listFieldService->generateUniqueTag($mailList->id, $validated['label']);
        } elseif (in_array($tag, ListField::PROTECTED_TAGS, true) || $this->tagTaken($mailList->id, $tag)) {
            $tag = $this->listFieldService->generateUniqueTag($mailList->id, $validated['label']);
        }

        ListField::create([
            'mail_list_id' => $mailList->id,
            'label' => $validated['label'],
            'type' => $validated['type'],
            'tag' => $tag,
            'default_value' => $validated['default_value'] ?? null,
            'required' => (bool) ($validated['required'] ?? false),
            'visible' => array_key_exists('visible', $validated)
                ? (bool) $validated['visible']
                : true,
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
            'fields.*.type' => 'required|string|in:'.implode(',', ListField::TYPES),
            'fields.*.tag' => 'nullable|string|max:255',
            'fields.*.default_value' => 'nullable|string',
            'fields.*.required' => 'nullable',
            'fields.*.visible' => 'nullable',
            'fields.*.options_text' => 'nullable|string',
        ]);

        $mailList = PublicId::findOrFail(MailList::class, $validated['mail_list_id']);

        if (! empty($validated['fields'])) {
            foreach ($validated['fields'] as $index => $fieldData) {
                $field = ListField::query()
                    ->where('mail_list_id', $mailList->id)
                    ->where('uuid', $fieldData['id'])
                    ->firstOrFail();

                $tag = $field->isProtected()
                    ? $field->tag
                    : trim((string) ($fieldData['tag'] ?? ''));

                if (! $field->isProtected()) {
                    if ($tag === '') {
                        $tag = $this->listFieldService->generateUniqueTag(
                            $mailList->id,
                            $fieldData['label'],
                            $field->id,
                        );
                    } elseif (
                        in_array($tag, ListField::PROTECTED_TAGS, true)
                        || $this->tagTaken($mailList->id, $tag, $field->id)
                    ) {
                        $tag = $this->listFieldService->generateUniqueTag(
                            $mailList->id,
                            $fieldData['label'],
                            $field->id,
                        );
                    }
                }

                $field->update([
                    'label' => $fieldData['label'],
                    'type' => $field->isProtected() ? $field->type : $fieldData['type'],
                    'tag' => $tag,
                    'default_value' => $fieldData['default_value'] ?? null,
                    'required' => $field->isProtected()
                        ? $field->required
                        : filter_var($fieldData['required'] ?? false, FILTER_VALIDATE_BOOLEAN),
                    'visible' => $field->isProtected()
                        ? $field->visible
                        : filter_var($fieldData['visible'] ?? false, FILTER_VALIDATE_BOOLEAN),
                    'sort_order' => $index,
                ]);

                if (! $field->isProtected()) {
                    $this->listFieldService->syncOptions(
                        $field->fresh(),
                        $this->listFieldService->parseOptionsText($fieldData['options_text'] ?? null),
                    );
                }
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

    private function tagTaken(int $mailListId, string $tag, ?int $ignoreFieldId = null): bool
    {
        return ListField::query()
            ->where('mail_list_id', $mailListId)
            ->where('tag', $tag)
            ->when($ignoreFieldId !== null, fn ($q) => $q->where('id', '!=', $ignoreFieldId))
            ->exists();
    }
}
