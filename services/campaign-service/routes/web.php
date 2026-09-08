<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json(['service' => 'campaign-service', 'status' => 'active']);
});
