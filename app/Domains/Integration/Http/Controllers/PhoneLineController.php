<?php

declare(strict_types=1);

namespace App\Domains\Integration\Http\Controllers;

use App\Domains\Integration\Http\Requests\LoginAsLineRequest;
use App\Domains\Integration\Http\Requests\SetDefaultLineRequest;
use App\Domains\Integration\Http\Requests\SetLinePasswordRequest;
use App\Domains\Integration\Services\LineProfileService;
use App\Domains\Integration\Services\PhoneLineService;
use App\Http\Controllers\Controller;
use App\Models\WhatsappLine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

/**
 * Handles the "Manage your Phone Numbers" section of the Account area.
 *
 * Routes:
 *   GET  /profile/phone-lines              → index
 *   GET  /profile/phone-lines/add          → create
 *   POST /profile/phone-lines/add          → store
 *   POST /profile/phone-lines/password     → setPassword
 *   POST /profile/phone-lines/login-as     → loginAs
 *   POST /profile/phone-lines/exit-context → exitContext
 *   POST /profile/phone-lines/set-default  → setDefault
 */
class PhoneLineController extends Controller
{
    public function __construct(
        private readonly PhoneLineService $phoneLineService,
        private readonly LineProfileService $lineProfileService,
    ) {}

    /**
     * GET /profile/phone-lines — List all secondary lines.
     */
    public function index(): View
    {
        $lines          = $this->phoneLineService->secondaryLines();
        $totalCount     = WhatsappLine::query()->count();
        $isLocked       = PhoneLineService::isLocked();
        $lockedLine     = $isLocked ? $this->phoneLineService->lockedLine() : null;
        $defaultLine    = $this->phoneLineService->defaultLine();
        $canAddNumber   = $defaultLine?->isConnected() ?? false;
        $lineLoginUrl   = route('line.login');

        return view('profile.phone-lines', compact(
            'lines', 'totalCount', 'isLocked', 'lockedLine', 'canAddNumber', 'lineLoginUrl'
        ));
    }

    /**
     * GET /profile/phone-lines/add — Form to register an additional WhatsApp number via CAMS.
     */
    public function create(): View|RedirectResponse
    {
        if (PhoneLineService::isLocked()) {
            return redirect()->route('profile.phone-lines.index')
                ->with('error', 'Cannot add numbers while in number-specific access mode.');
        }

        $defaultLine = $this->phoneLineService->defaultLine();
        if ($defaultLine === null || ! $defaultLine->isConnected() || blank($defaultLine->alibaba_cust_space_id)) {
            return redirect()->route('profile.phone-lines.index')
                ->with('error', 'WhatsApp must be connected first (customer space is missing). Finish onboarding, then add more numbers.');
        }

        return view('profile.phone-lines-add', [
            'defaultLine' => $defaultLine,
        ]);
    }

    /**
     * POST /profile/phone-lines/add — Call AddChatappPhoneNumber and upsert WhatsappLine.
     */
    public function store(Request $request): RedirectResponse
    {
        if (PhoneLineService::isLocked()) {
            return redirect()->route('profile.phone-lines.index')
                ->with('error', 'Cannot add numbers while in number-specific access mode.');
        }

        $validated = $request->validate([
            'country_code' => ['required', 'string', 'regex:/^\d{1,4}$/'],
            'phone_number' => ['required', 'string', 'regex:/^\d{6,15}$/'],
            'verified_name' => ['required', 'string', 'max:128'],
        ]);

        try {
            $line = $this->lineProfileService->addChatappPhoneNumber($validated);
        } catch (RuntimeException $e) {
            return back()
                ->withInput()
                ->with('error', $e->getMessage());
        }

        return redirect()->route('profile.phone-lines.index')
            ->with('success', 'Number '.$line->displayPhone().' was sent to WhatsApp successfully. Use Sync Data on Integration if the channel list needs a refresh.');
    }

    /**
     * POST /profile/phone-lines/password — Set/update Number Access password for a line.
     */
    public function setPassword(SetLinePasswordRequest $request): RedirectResponse
    {
        if (PhoneLineService::isLocked()) {
            return redirect()->route('profile.phone-lines.index')
                ->with('error', 'Password cannot be changed while in number-specific access mode.');
        }

        $line = $this->phoneLineService->findLine((int) $request->input('line_id'));

        if (! $line instanceof WhatsappLine) {
            return redirect()->route('profile.phone-lines.index')
                ->with('error', 'Phone number not found.');
        }

        if ($line->is_default) {
            return redirect()->route('profile.phone-lines.index')
                ->with('error', 'The default WhatsApp number is managed under Integration.');
        }

        $this->phoneLineService->setPassword($line, (string) $request->input('password'));

        return redirect()->route('profile.phone-lines.index')
            ->with('success', 'Number Access password saved for ' . $line->displayPhone() . '.');
    }

    /**
     * POST /profile/phone-lines/login-as — Lock session to a specific line context.
     */
    public function loginAs(LoginAsLineRequest $request): RedirectResponse
    {
        $line = $this->phoneLineService->findLine((int) $request->input('line_id'));

        if (! $line instanceof WhatsappLine) {
            return redirect()->route('profile.phone-lines.index')
                ->with('error', 'Phone number not found.');
        }

        if ($line->is_default) {
            return redirect()->route('profile.phone-lines.index')
                ->with('error', 'The default WhatsApp number is managed under Integration.');
        }

        try {
            $this->phoneLineService->loginAsLine($line, (string) $request->input('password'));
        } catch (\InvalidArgumentException $e) {
            return redirect()->route('profile.phone-lines.index')
                ->with('error', $e->getMessage());
        }

        return redirect()->route('dashboard')
            ->with('success', 'Now viewing ' . $line->displayPhone() . ' in number-specific access mode.');
    }

    /**
     * POST /profile/phone-lines/exit-context — Exit number-specific access mode.
     */
    public function exitContext(): RedirectResponse
    {
        $this->phoneLineService->exitLineContext();

        return redirect()->route('dashboard')
            ->with('success', 'Number-specific access mode exited. You can now use all numbers.');
    }

    /**
     * POST /profile/phone-lines/set-default — Promote a secondary line to default.
     */
    public function setDefault(SetDefaultLineRequest $request): RedirectResponse
    {
        if (PhoneLineService::isLocked()) {
            return redirect()->route('profile.phone-lines.index')
                ->with('error', 'Default number cannot be changed while in number-specific access mode.');
        }

        $line = $this->phoneLineService->findLine((int) $request->input('line_id'));

        if (! $line instanceof WhatsappLine) {
            return redirect()->route('profile.phone-lines.index')
                ->with('error', 'Phone number not found.');
        }

        if ($line->is_default) {
            return redirect()->route('profile.phone-lines.index')
                ->with('error', 'This number is already the default.');
        }

        if (! $line->isConnected()) {
            return redirect()->route('profile.phone-lines.index')
                ->with('error', 'Only a connected number can be set as default.');
        }

        $this->phoneLineService->setAsDefault($line);

        return redirect()->route('profile.phone-lines.index')
            ->with('success', 'Default WhatsApp number is now ' . $line->displayPhone() . '.');
    }
}
