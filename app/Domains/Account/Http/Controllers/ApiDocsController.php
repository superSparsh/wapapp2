<?php

declare(strict_types=1);

namespace App\Domains\Account\Http\Controllers;

use App\Domains\Account\Services\ApiDocumentationService;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\View\View;

class ApiDocsController extends Controller
{
    public function show(ApiDocumentationService $apiDocumentationService): View
    {
        $user = auth('web')->user();
        abort_unless($user instanceof User, 403);

        return view('profile.api-docs', [
            'sections' => $apiDocumentationService->sections($user),
            'baseUrl' => config('account.api.base_url'),
            'exampleUrl' => rtrim((string) config('account.api.base_url'), '/').'/lists?api_token='.($user->api_token ?? 'YOUR_API_TOKEN'),
        ]);
    }
}
