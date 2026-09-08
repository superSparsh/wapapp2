<?php

declare(strict_types=1);

namespace App\Domains\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Plugin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PluginController extends Controller
{
    public function index(): View
    {
        return view('admin.plugins.index', [
            'rows' => Plugin::query()->orderBy('title')->paginate(50),
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
