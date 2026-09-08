<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment('Template microservice running smoothly.');
})->purpose('Display an inspiring quote');
