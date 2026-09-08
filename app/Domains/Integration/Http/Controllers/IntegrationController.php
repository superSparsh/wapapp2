<?php

declare(strict_types=1);

namespace App\Domains\Integration\Http\Controllers;

use App\Domains\Integration\Http\Requests\UpdateLineProfileRequest;
use App\Domains\Integration\Services\LineProfileService;
use App\Http\Controllers\Controller;
use App\Models\WhatsappLine;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

class IntegrationController extends Controller
{
    public function index(LineProfileService $lineProfileService): View
    {
        return view('profile.integration', [
            'business' => $lineProfileService->businessDetails(),
            'lines' => $lineProfileService->lines(),
        ]);
    }

    public function edit(LineProfileService $lineProfileService, ?WhatsappLine $whatsappLine = null): View|RedirectResponse
    {
        $line = $whatsappLine ?? $lineProfileService->defaultLine();
        if (! $line) {
            return redirect()
                ->route('profile.integration')
                ->withErrors(['integration' => 'No WhatsApp line connected yet.']);
        }

        try {
            $line = $lineProfileService->pullProfileFromProvider($line);
        } catch (Throwable) {
            // Keep local profile if pull fails.
        }

        return view('profile.integration-connected', [
            'line' => $line,
            'profile' => $line->profile ?? [],
        ]);
    }

    public function update(
        UpdateLineProfileRequest $request,
        WhatsappLine $whatsappLine,
        LineProfileService $lineProfileService,
    ): RedirectResponse {
        $lineProfileService->updateProfile(
            line: $whatsappLine,
            data: $request->validated(),
            logo: $request->file('logo'),
            removeLogo: $request->boolean('remove_logo'),
        );

        return redirect()
            ->route('profile.integration')
            ->with('status', 'Business profile updated for '.$whatsappLine->display_name.'.');
    }

    public function sync(LineProfileService $lineProfileService): RedirectResponse
    {
        $line = $lineProfileService->defaultLine();
        abort_unless($line instanceof WhatsappLine, 404);

        try {
            $lineProfileService->syncFromProvider($line);
        } catch (RuntimeException $exception) {
            return redirect()
                ->route('profile.integration')
                ->withErrors(['integration' => $exception->getMessage()]);
        } catch (Throwable $exception) {
            report($exception);

            return redirect()
                ->route('profile.integration')
                ->withErrors(['integration' => 'Sync failed. Please try again.']);
        }

        return redirect()
            ->route('profile.integration')
            ->with('status', 'WhatsApp business data synced successfully.');
    }
}
