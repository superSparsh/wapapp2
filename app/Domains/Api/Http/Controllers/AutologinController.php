<?php

declare(strict_types=1);

namespace App\Domains\Api\Http\Controllers;

use App\Domains\Api\Services\ApiLoginTokenService;
use App\Domains\Auth\Exceptions\AccountInactiveException;
use App\Domains\Team\Support\TeamPostLoginRedirect;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use RuntimeException;
use Throwable;

class AutologinController extends Controller
{
    public function __invoke(string $token, ApiLoginTokenService $tokens): RedirectResponse
    {
        try {
            $tokens->consume($token);
        } catch (AccountInactiveException|RuntimeException $e) {
            return redirect()
                ->route('login')
                ->withErrors(['email' => $e->getMessage()]);
        } catch (Throwable $e) {
            report($e);

            return redirect()
                ->route('login')
                ->withErrors(['email' => 'Unable to complete auto login.']);
        }

        return TeamPostLoginRedirect::intended();
    }
}
