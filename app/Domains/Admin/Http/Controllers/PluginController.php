<?php

declare(strict_types=1);

namespace App\Domains\Admin\Http\Controllers;

use App\Domains\Admin\Support\AdminListQuery;
use App\Http\Controllers\Controller;
use App\Models\Plugin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PluginController extends Controller
{
    public function index(Request $request): View
    {
        $parsed = AdminListQuery::fromRequest(
            $request,
            allowedSorts: ['title', 'name', 'type', 'id'],
            defaultSort: 'title',
            defaultDirection: 'asc',
        );

        $query = Plugin::query();
        AdminListQuery::applySearch($query, $parsed['q'], ['title', 'name', 'type']);
        AdminListQuery::applySort(
            $query,
            $parsed['sort'],
            $parsed['direction'],
            [
                'title' => 'title',
                'name' => 'name',
                'type' => 'type',
                'id' => 'id',
            ],
            'title',
        );

        return view('admin.plugins.index', [
            'rows' => $query->paginate(50)->withQueryString(),
            'filters' => $parsed,
            'sortOptions' => [
                ['value' => 'title', 'label' => 'Title A–Z', 'direction' => 'asc'],
                ['value' => 'name', 'label' => 'Key A–Z', 'direction' => 'asc'],
                ['value' => 'type', 'label' => 'Type', 'direction' => 'asc'],
                ['value' => 'id', 'label' => 'Newest first', 'direction' => 'desc'],
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:191'],
            'name' => [
                'nullable',
                'string',
                'max:120',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (Plugin::query()->where('name', Str::slug((string) $value))->exists()) {
                        $fail('A plugin with this key already exists.');
                    }
                },
            ],
            'type' => ['nullable', 'string', 'max:64'],
            'version' => ['nullable', 'string', 'max:32'],
            'description' => ['nullable', 'string'],
        ]);

        $name = Str::slug(($data['name'] ?? '') !== '' ? (string) $data['name'] : $data['title']);

        if (Plugin::query()->where('name', $name)->exists()) {
            return back()->with('error', 'A plugin with this key already exists.');
        }

        Plugin::query()->create([
            'name' => $name,
            'title' => $data['title'],
            'type' => ($data['type'] ?? '') !== '' ? $data['type'] : 'general',
            'version' => $data['version'] ?? null,
            'description' => $data['description'] ?? null,
            'is_enabled' => false,
        ]);

        return redirect()->route('admin.plugins.index')->with('status', 'Plugin registered.');
    }

    public function toggle(Plugin $plugin): RedirectResponse
    {
        $plugin->is_enabled = ! $plugin->is_enabled;
        $plugin->save();

        return back()->with('status', 'Plugin '.($plugin->is_enabled ? 'enabled' : 'disabled').'.');
    }

    public function destroy(Plugin $plugin): RedirectResponse
    {
        $plugin->delete();

        return back()->with('status', 'Plugin removed.');
    }
}
