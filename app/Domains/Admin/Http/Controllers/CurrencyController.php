<?php

declare(strict_types=1);

namespace App\Domains\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Currency;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CurrencyController extends Controller
{
    public function index(): View
    {
        return view('admin.currencies.index', [
            'rows' => Currency::query()->orderBy('code')->paginate(50),
        ]);
    }

    public function create(): View
    {
        return view('admin.currencies.form');
    }

    public function store(Request $request): RedirectResponse
    {
        Currency::query()->create($this->validated($request));

        return redirect()->route('admin.currencies.index')->with('status', 'Currency created.');
    }

    public function edit(Currency $currency): View
    {
        return view('admin.currencies.form', ['row' => $currency]);
    }

    public function update(Request $request, Currency $currency): RedirectResponse
    {
        $currency->update($this->validated($request, $currency));

        return redirect()->route('admin.currencies.index')->with('status', 'Currency updated.');
    }

    public function toggle(Currency $currency): RedirectResponse
    {
        $currency->is_active = ! $currency->is_active;
        $currency->save();

        return back()->with('status', 'Currency '.($currency->is_active ? 'enabled' : 'disabled').'.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Currency $existing = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'code' => [
                'required',
                'string',
                'size:3',
                function (string $attribute, mixed $value, \Closure $fail) use ($existing): void {
                    $query = Currency::query()->where('code', strtoupper((string) $value));
                    if ($existing !== null) {
                        $query->whereKeyNot($existing->id);
                    }
                    if ($query->exists()) {
                        $fail('This currency code already exists.');
                    }
                },
            ],
            'format' => ['nullable', 'string', 'max:64'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $data['code'] = strtoupper($data['code']);
        $data['format'] = ($data['format'] ?? '') !== '' ? $data['format'] : '{PRICE}';
        $data['is_active'] = $request->boolean('is_active', $existing?->is_active ?? true);

        return $data;
    }
}
