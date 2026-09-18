<?php

declare(strict_types=1);

namespace App\Domains\Admin\Http\Controllers;

use App\Domains\Admin\Support\AdminListQuery;
use App\Http\Controllers\Controller;
use App\Models\CountryPricingLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CountryPricingLogController extends Controller
{
    public function index(Request $request): View
    {
        $parsed = AdminListQuery::fromRequest(
            $request,
            allowedSorts: ['created_at', 'country_code', 'id'],
            defaultSort: 'created_at',
            defaultDirection: 'desc',
        );

        $query = CountryPricingLog::query()->with('admin:id,name,email');
        AdminListQuery::applySearch($query, $parsed['q'], ['country_code', 'conversation']);
        AdminListQuery::applySort(
            $query,
            $parsed['sort'],
            $parsed['direction'],
            [
                'created_at' => 'created_at',
                'country_code' => 'country_code',
                'id' => 'id',
            ],
            'created_at',
        );

        return view('admin.pricing.logs', [
            'rows' => $query->paginate(50)->withQueryString(),
            'filters' => $parsed,
            'sortOptions' => [
                ['value' => 'created_at', 'label' => 'Newest first', 'direction' => 'desc'],
                ['value' => 'country_code', 'label' => 'Country code', 'direction' => 'asc'],
            ],
        ]);
    }
}
