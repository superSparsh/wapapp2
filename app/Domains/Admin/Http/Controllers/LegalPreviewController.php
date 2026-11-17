<?php

declare(strict_types=1);

namespace App\Domains\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class LegalPreviewController extends Controller
{
    public function privacy(): View
    {
        return view('admin.legal.privacy', $this->legalContext());
    }

    public function terms(): View
    {
        return view('admin.legal.terms', $this->legalContext());
    }

    public function breach(): View
    {
        return view('admin.legal.breach', $this->legalContext());
    }

    public function collectionNotice(): View
    {
        return view('admin.legal.collection-notice', $this->legalContext());
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
            'grievanceEmail' => (string) config('legal.grievance.email', 'support@tittu.in'),
            'grievanceDays' => (int) config('legal.grievance.response_days', 7),
            'processingRegion' => (string) config('legal.processing.primary_region', 'Singapore'),
            'whatsappBsp' => (string) config('legal.processing.whatsapp_bsp'),
            'platformName' => (string) config('legal.processing.platform'),
            'effectiveDate' => (string) config('legal.effective_date'),
            'breachOwnerName' => (string) config('legal.breach.owner_name', 'Privacy / Security Owner'),
            'breachOwnerEmail' => (string) config('legal.breach.owner_email', 'support@tittu.in'),
            'breachEscalationEmail' => (string) config('legal.breach.escalation_email', 'support@tittu.in'),
            'breachBoardHours' => (int) config('legal.breach.board_notify_hours', 72),
            'breachPlaybookVersion' => (string) config('legal.breach.playbook_version', '1.0'),
            'breachPlaybookDate' => (string) config('legal.breach.playbook_date', '29 October 2026'),
        ];
    }
}
