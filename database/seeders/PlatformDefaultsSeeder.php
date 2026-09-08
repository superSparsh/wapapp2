<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\AdminRole;
use App\Models\Currency;
use App\Models\Language;
use Illuminate\Database\Seeder;

class PlatformDefaultsSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['name' => 'Indian Rupee', 'code' => 'INR', 'format' => '₹{PRICE}'],
            ['name' => 'US Dollar', 'code' => 'USD', 'format' => '${PRICE}'],
        ] as $currency) {
            Currency::query()->updateOrCreate(
                ['code' => $currency['code']],
                ['name' => $currency['name'], 'format' => $currency['format'], 'is_active' => true],
            );
        }

        Language::query()->updateOrCreate(
            ['code' => 'en'],
            ['name' => 'English', 'region_code' => 'US', 'is_active' => true],
        );

        AdminRole::query()->updateOrCreate(
            ['slug' => 'super-admin'],
            ['name' => 'Super Admin', 'permissions' => AdminRole::PERMISSIONS, 'is_active' => true],
        );
    }
}
