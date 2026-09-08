<?php

declare(strict_types=1);

namespace App\Domains\Integration\Http\Controllers;

use App\Domains\Integration\Http\Requests\LineLoginRequest;
use App\Domains\Integration\Services\PhoneLineService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Public "Line Login" page — no main-account authentication required.
 *
 * Allows someone to access the Inbox for a specific WhatsApp number
 * by entering the phone number + the Number Access password set by
 * the account owner.
 *
 * Routes:
 *   GET  /line-login → showForm
 *   POST /line-login → login
 */
class LineLoginController extends Controller
{
    public function __construct(
        private readonly PhoneLineService $phoneLineService,
    ) {}

    /**
     * GET /line-login — Show the public login form.
     */
    public function showForm(): View
    {
        return view('auth.line-login');
    }

    /**
     * POST /line-login — Authenticate by phone + password and lock session.
     */
    public function login(LineLoginRequest $request): View|RedirectResponse
    {
        try {
            $line = $this->phoneLineService->lineLoginByPhone(
                (string) $request->input('phone'),
                (string) $request->input('password'),
            );
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['phone' => $e->getMessage()])->withInput();
        }

        return redirect()->route('dashboard')
            ->with('success', 'Now viewing ' . $line->displayPhone() . ' in number-specific access mode.');
    }
}
