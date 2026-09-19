<?php

declare(strict_types=1);

namespace App\Domains\Admin\Http\Controllers;

use App\Domains\Admin\Support\AdminListQuery;
use App\Domains\Operations\Services\MetaPricing\MetaPricingSyncMessageFormatter;
use App\Domains\Operations\Services\MetaPricing\MetaWhatsAppUsdPricingSyncService;
use App\Http\Controllers\Controller;
use App\Models\CountryPricing;
use App\Models\CountryPricingLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

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
        AdminListQuery::applySearch($query, $parsed['q'], ['country_name', 'country_code', 'dial_code']);
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
        $data = $this->validated($request);
        $data['admin_id'] = auth('admin')->id();

        CountryPricing::query()->create($data);

        return redirect()->route('admin.pricing.index')->with('status', 'Country pricing saved.');
    }

    public function edit(CountryPricing $pricing): View
    {
        return view('admin.pricing.form', ['row' => $pricing]);
    }

    public function update(Request $request, CountryPricing $pricing): RedirectResponse
    {
        $data = $this->validated($request, $pricing);
        $this->logPriceChanges($pricing, $data);
        $pricing->update($data);

        return redirect()->route('admin.pricing.index')->with('status', 'Country pricing updated.');
    }

    public function toggle(CountryPricing $pricing): RedirectResponse
    {
        $pricing->status = $pricing->isActive() ? 0 : 1;
        $pricing->save();

        return back()->with('status', 'Pricing row '.($pricing->isActive() ? 'activated' : 'deactivated').'.');
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

            $code = strtoupper((string) $row['country_code']);
            CountryPricing::query()->updateOrCreate(
                ['country_code' => $code],
                [
                    'admin_id' => auth('admin')->id(),
                    'country_name' => (string) ($row['country_name'] ?? $code),
                    'dial_code' => $row['dial_code'] ?? null,
                    'marketing_price' => $this->nullableFloat($row['marketing_price'] ?? $row['marketing_rate'] ?? null),
                    'utility_price' => $this->nullableFloat($row['utility_price'] ?? $row['utility_rate'] ?? null),
                    'auth_price' => $this->nullableFloat($row['auth_price'] ?? $row['authentication_rate'] ?? null),
                    'auth_international_price' => $this->nullableFloat($row['auth_international_price'] ?? null),
                    'service_price' => $this->nullableFloat($row['service_price'] ?? $row['service_rate'] ?? null),
                    'tekpro_marketing_price' => $this->nullableFloat($row['tekpro_marketing_price'] ?? null),
                    'tekpro_utility_price' => $this->nullableFloat($row['tekpro_utility_price'] ?? null),
                    'tekpro_auth_price' => $this->nullableFloat($row['tekpro_auth_price'] ?? null),
                    'tekpro_auth_international_price' => $this->nullableFloat($row['tekpro_auth_international_price'] ?? null),
                    'tekpro_service_price' => $this->nullableFloat($row['tekpro_service_price'] ?? null),
                    'currency' => (string) ($row['currency'] ?? '₹'),
                    'status' => 1,
                ],
            );
            $count++;
        }
        fclose($handle);

        return back()->with('status', "Imported {$count} pricing row(s).");
    }

    public function syncMeta(MetaWhatsAppUsdPricingSyncService $sync): RedirectResponse
    {
        try {
            $adminId = auth('admin')->id();
            $results = $sync->sync(
                updatedBy: $adminId !== null ? (int) $adminId : null,
                source: 'auto_fetch',
            );
            $formatted = MetaPricingSyncMessageFormatter::format($results);

            return back()->with('status', $formatted['message'].' '.$formatted['detail']);
        } catch (Throwable $e) {
            return back()->with('error', 'Meta pricing sync failed: '.$e->getMessage());
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?CountryPricing $existing = null): array
    {
        $data = $request->validate([
            'country_code' => [
                'nullable',
                'string',
                'max:255',
                function (string $attribute, mixed $value, \Closure $fail) use ($existing): void {
                    if ($value === null || $value === '') {
                        return;
                    }
                    $query = CountryPricing::query()->where('country_code', strtoupper((string) $value));
                    if ($existing !== null) {
                        $query->whereKeyNot($existing->id);
                    }
                    if ($query->exists()) {
                        $fail('This country code already exists.');
                    }
                },
            ],
            'country_name' => ['required', 'string', 'max:255'],
            'dial_code' => ['nullable', 'string', 'max:255'],
            'region_codes' => ['nullable', 'string'],
            'marketing_price' => ['nullable', 'numeric', 'min:0'],
            'utility_price' => ['nullable', 'numeric', 'min:0'],
            'auth_price' => ['nullable', 'numeric', 'min:0'],
            'auth_international_price' => ['nullable', 'numeric', 'min:0'],
            'service_price' => ['nullable', 'numeric', 'min:0'],
            'tekpro_marketing_price' => ['nullable', 'numeric', 'min:0'],
            'tekpro_utility_price' => ['nullable', 'numeric', 'min:0'],
            'tekpro_auth_price' => ['nullable', 'numeric', 'min:0'],
            'tekpro_auth_international_price' => ['nullable', 'numeric', 'min:0'],
            'tekpro_service_price' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'max:10'],
            'status' => ['sometimes', 'boolean'],
        ]);

        if (filled($data['country_code'] ?? null)) {
            $data['country_code'] = strtoupper((string) $data['country_code']);
        } else {
            $data['country_code'] = null;
        }

        $regionRaw = trim((string) ($data['region_codes'] ?? ''));
        $data['region_codes'] = $regionRaw === ''
            ? null
            : array_values(array_filter(array_map('trim', explode(',', $regionRaw))));

        $data['status'] = $request->boolean('status', true) ? 1 : 0;

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function logPriceChanges(CountryPricing $pricing, array $data): void
    {
        $adminId = auth('admin')->id();
        if ($adminId === null || blank($pricing->country_code)) {
            return;
        }

        foreach (CountryPricing::priceFields() as $column => $conversation) {
            if (! array_key_exists($column, $data)) {
                continue;
            }

            $old = $pricing->{$column};
            $new = $data[$column];

            if ((string) $old === (string) $new) {
                continue;
            }

            CountryPricingLog::query()->create([
                'country_code' => (string) $pricing->country_code,
                'conversation' => $conversation,
                'old_price' => $old,
                'new_price' => $new,
                'updated_by' => $adminId,
            ]);
        }
    }

    private function nullableFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value;
    }
}
