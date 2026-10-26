<?php

declare(strict_types=1);

namespace App\Domains\Audience\Http\Controllers;

use App\Domains\Audience\Services\PublicUnsubscribeService;
use App\Http\Controllers\Controller;
use Illuminate\View\View;

class PublicUnsubscribeController extends Controller
{
    public function show(string $token): View
    {
        return view('audience.unsubscribe', [
            'token' => $token,
            'state' => 'confirm',
        ]);
    }

    public function store(string $token, PublicUnsubscribeService $service): View
    {
        return view('audience.unsubscribe', [
            'token' => $token,
            'state' => $service->confirm($token),
        ]);
    }
}
