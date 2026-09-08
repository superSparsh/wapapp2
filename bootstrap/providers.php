<?php

use App\Providers\AppServiceProvider;
use App\Providers\MacroServiceProvider;
use App\Providers\TenancyServiceProvider;

return [
    AppServiceProvider::class,
    MacroServiceProvider::class,
    TenancyServiceProvider::class,
];
