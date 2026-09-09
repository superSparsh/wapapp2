<?php

declare(strict_types=1);

namespace App\Domains\Admin\Http\Controllers;

use App\Domains\Admin\Support\AdminListQuery;
use App\Http\Controllers\Controller;
use App\Models\CountryPricing;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CountryPricingController extends Controller
{
    public function index(Request $request): View
    {
        $parsed = AdminListQuery::fromRequest(
            $request,
            allowedSorts: ['country_name', 'country_code', 'id'],
            defaultSort: 'country_name',
            defaultDirection: 'asc',
        );

        $query = CountryPricing::query();
        AdminListQuery::applySearch($query, $parsed['q'], ['country_name', 'country_code']);
        AdminListQuery::applySort(
            $query,
            $parsed['sort'],
            $parsed['direction'],
            [
                'country_name' => 'country_name',
                'country_code' => 'country_code',
                'id' => 'id',
            ],
            'country_name',
        );

        return view('admin.pricing.index', [
            'rows' => $query->paginate(50)->withQueryString(),
            'filters' => $parsed,
            'sortOptions' => [
                ['value' => 'country_name', 'label' => 'Country A–Z', 'direction' => 'asc'],
                ['value' => 'country_code', 'label' => 'Code A–Z', 'direction' => 'asc'],
                ['value' => 'id', 'label' => 'Newest first', 'direction' => 'desc'],
            ],
        ]);
    }

    public function create(): View
    {
        return view('admin.pricing.form');
    }

    public function store(Request $request): RedirectResponse
    {
        CountryPricing::query()->create($this->validated($request));

        return redirect()->route('admin.pricing.index')->with('status', 'Country pricing saved.');
    }

    public function edit(CountryPricing $pricing): View
    {
        return view('admin.pricing.form', ['row' => $pricing]);
    }

    public function update(Request $request, CountryPricing $pricing): RedirectResponse
    {
        $pricing->update($this->validated($request, $pricing));

        return redirect()->route('admin.pricing.index')->with('status', 'Country pricing updated.');
    }

    public function toggle(CountryPricing $pricing): RedirectResponse
    {
        $pricing->is_active = ! $pricing->is_active;
        $pricing->save();

        return back()->with('status', 'Pricing row '.($pricing->is_active ? 'activated' : 'deactivated').'.');
    }

    public function import(Request $request): RedirectResponse
    {
        $request->validate(['csv' => ['required', 'file', 'mimes:csv,txt']]);
        $handle = fopen($request->file('csv')->getRealPath(), 'r');
        if ($handle === false) {
            return back()->with('error', 'Unable to read CSV.');
        }

        $header = fgetcsv($handle);
        $count = 0;
        while (($data = fgetcsv($handle)) !== false) {
            $row = array_combine($header ?: [], $data);
            if (! is_array($row) || blank($row['country_code'] ?? null)) {
                continue;
            }
            CountryPricing::query()->updateOrCreate(
                ['country_code' => strtoupper((string) $row['country_code'])],
                [
                    'country_name' => (string) ($row['country_name'] ?? $row['country_code']),
                    'marketing_rate' => (float) ($row['marketing_rate'] ?? 0),
                    'utility_rate' => (float) ($row['utility_rate'] ?? 0),
                    'authentication_rate' => (float) ($row['authentication_rate'] ?? 0),
                    'service_rate' => (float) ($row['service_rate'] ?? 0),
                    'currency' => strtoupper((string) ($row['currency'] ?? 'USD')),
                    'is_active' => true,
                ],
            );
            $count++;
        }
        fclose($handle);

        return back()->with('status', "Imported {$count} pricing row(s).");
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?CountryPricing $existing = null): array
    {
        $data = $request->validate([
            'country_code' => [
                'required',
                'string',
                'max:8',
                function (string $attribute, mixed $value, \Closure $fail) use ($existing): void {
                    $query = CountryPricing::query()->where('country_code', strtoupper((string) $value));
                    if ($existing !== null) {
                        $query->whereKeyNot($existing->id);
                    }
                    if ($query->exists()) {
                        $fail('This country code already exists.');
                    }
                },
            ],
            'country_name' => ['required', 'string', 'max:191'],
            'marketing_rate' => ['required', 'numeric', 'min:0'],
            'utility_rate' => ['required', 'numeric', 'min:0'],
            'authentication_rate' => ['required', 'numeric', 'min:0'],
            'service_rate' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        $data['country_code'] = strtoupper($data['country_code']);
        $data['currency'] = strtoupper($data['currency']);
        $data['is_active'] = $request->boolean('is_active', true);

        return $data;
    }
}
