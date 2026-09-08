<?php

declare(strict_types=1);

namespace App\Domains\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\PlatformTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PlatformTemplateController extends Controller
{
    /** @var list<string> */
    private const TYPES = ['whatsapp', 'email', 'sms'];

    public function index(Request $request): View
    {
        $category = trim((string) $request->query('category', ''));

        return view('admin.platform-templates.index', [
            'rows' => PlatformTemplate::query()
                ->when($category !== '', fn ($query) => $query->where('category', $category))
                ->orderBy('name')
                ->paginate(24)
                ->withQueryString(),
            'categories' => PlatformTemplate::query()
                ->whereNotNull('category')
                ->distinct()
                ->orderBy('category')
                ->pluck('category'),
            'category' => $category,
        ]);
    }

    public function create(): View
    {
        return view('admin.platform-templates.form', ['types' => self::TYPES]);
    }

    public function store(Request $request): RedirectResponse
    {
        PlatformTemplate::query()->create($this->validated($request));

        return redirect()->route('admin.platform-templates.index')->with('status', 'Template created.');
    }

    public function edit(PlatformTemplate $template): View
    {
        return view('admin.platform-templates.form', [
            'row' => $template,
            'types' => self::TYPES,
        ]);
    }

    public function update(Request $request, PlatformTemplate $template): RedirectResponse
    {
        $template->update($this->validated($request, $template));

        return redirect()->route('admin.platform-templates.index')->with('status', 'Template updated.');
    }

    public function destroy(PlatformTemplate $template): RedirectResponse
    {
        $template->delete();

        return back()->with('status', 'Template deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?PlatformTemplate $existing = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'category' => ['nullable', 'string', 'max:64'],
            'type' => ['required', 'string', 'in:'.implode(',', self::TYPES)],
            'body' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $data['is_active'] = $request->boolean('is_active', $existing?->is_active ?? true);

        return $data;
    }
}
