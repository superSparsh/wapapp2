<?php

declare(strict_types=1);

namespace App\Domains\Account\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class LegalController extends Controller
{
    public function privacy(): View
    {
        return view('profile.privacy-policy', $this->legalContext());
    }

    public function terms(): View
    {
        return view('profile.terms-of-service', $this->legalContext());
    }

    /**
     * @return array<string, mixed>
     */
    private function legalContext(): array
    {
        return [
            'legal' => config('legal'),
            'productName' => (string) config('legal.product_name', config('app.name', 'WapApp')),
            'companyName' => (string) config('legal.company_legal_name', 'Tekkonnectpro IT Services'),
            'companyLocation' => (string) config('legal.company_location', 'Bengaluru, India'),
            'grievanceEmail' => (string) config('legal.grievance.email', 'support@wapapp.in'),
            'grievanceDays' => (int) config('legal.grievance.response_days', 7),
            'processingRegion' => (string) config('legal.processing.primary_region', 'Singapore'),
            'whatsappBsp' => (string) config('legal.processing.whatsapp_bsp'),
            'platformName' => (string) config('legal.processing.platform'),
            'effectiveDate' => (string) config('legal.effective_date'),
        ];
    }
}
