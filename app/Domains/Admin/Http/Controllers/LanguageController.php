<?php

declare(strict_types=1);

namespace App\Domains\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Language;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LanguageController extends Controller
{
    public function index(): View
    {
        return view('admin.languages.index', [
            'rows' => Language::query()->orderBy('name')->paginate(50),
        ]);
    }

    public function create(): View
    {
        return view('admin.languages.form');
    }

    public function store(Request $request): RedirectResponse
    {
        Language::query()->create($this->validated($request));

        return redirect()->route('admin.languages.index')->with('status', 'Language created.');
    }

    public function edit(Language $language): View
    {
        return view('admin.languages.form', ['row' => $language]);
    }

    public function update(Request $request, Language $language): RedirectResponse
    {
        $language->update($this->validated($request, $language));

        return redirect()->route('admin.languages.index')->with('status', 'Language updated.');
    }

    public function toggle(Language $language): RedirectResponse
    {
        $language->is_active = ! $language->is_active;
        $language->save();

        return back()->with('status', 'Language '.($language->is_active ? 'enabled' : 'disabled').'.');
    }

    public function destroy(Language $language): RedirectResponse
    {
        $language->delete();

        return back()->with('status', 'Language deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Language $existing = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'code' => [
                'required',
                'string',
                'max:16',
                function (string $attribute, mixed $value, \Closure $fail) use ($existing): void {
                    $query = Language::query()->where('code', strtolower((string) $value));
                    if ($existing !== null) {
                        $query->whereKeyNot($existing->id);
                    }
                    if ($query->exists()) {
                        $fail('This language code already exists.');
                    }
                },
            ],
            'region_code' => ['nullable', 'string', 'max:16'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $data['code'] = strtolower($data['code']);
        $data['is_active'] = $request->boolean('is_active', $existing?->is_active ?? true);

        return $data;
    }
}
