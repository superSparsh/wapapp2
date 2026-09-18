<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('country_pricing')) {
            $this->reshapeCountryPricing();
        }

        if (! Schema::hasTable('country_pricing_logs')) {
            Schema::create('country_pricing_logs', function (Blueprint $table): void {
                $table->id();
                $table->string('country_code');
                $table->string('conversation');
                $table->decimal('old_price', 10, 4)->nullable();
                $table->decimal('new_price', 10, 4)->nullable();
                $table->unsignedBigInteger('updated_by');
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

                $table->index('country_code');
                $table->index('updated_by');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('country_pricing_logs');

        // Non-destructive down: keep reshaped country_pricing as-is.
    }

    private function reshapeCountryPricing(): void
    {
        Schema::table('country_pricing', function (Blueprint $table): void {
            if (! Schema::hasColumn('country_pricing', 'admin_id')) {
                $table->unsignedInteger('admin_id')->nullable()->after('id');
            }
            if (! Schema::hasColumn('country_pricing', 'dial_code')) {
                $table->string('dial_code')->nullable()->after('country_code');
            }
            if (! Schema::hasColumn('country_pricing', 'region_codes')) {
                $table->json('region_codes')->nullable()->after('dial_code');
            }
            if (! Schema::hasColumn('country_pricing', 'auth_international_price')) {
                $table->decimal('auth_international_price', 10, 4)->nullable();
            }
            if (! Schema::hasColumn('country_pricing', 'tekpro_marketing_price')) {
                $table->double('tekpro_marketing_price', 10, 4)->nullable();
            }
            if (! Schema::hasColumn('country_pricing', 'tekpro_utility_price')) {
                $table->double('tekpro_utility_price', 10, 4)->nullable();
            }
            if (! Schema::hasColumn('country_pricing', 'tekpro_auth_price')) {
                $table->double('tekpro_auth_price', 10, 4)->nullable();
            }
            if (! Schema::hasColumn('country_pricing', 'tekpro_auth_international_price')) {
                $table->double('tekpro_auth_international_price', 10, 4)->nullable();
            }
            if (! Schema::hasColumn('country_pricing', 'tekpro_service_price')) {
                $table->double('tekpro_service_price', 10, 4)->nullable();
            }
            if (! Schema::hasColumn('country_pricing', 'status') && ! Schema::hasColumn('country_pricing', 'is_active')) {
                $table->tinyInteger('status')->default(1);
            }
        });

        $this->renameIfExists('marketing_rate', 'marketing_price');
        $this->renameIfExists('utility_rate', 'utility_price');
        $this->renameIfExists('authentication_rate', 'auth_price');
        $this->renameIfExists('service_rate', 'service_price');

        if (Schema::hasColumn('country_pricing', 'is_active') && ! Schema::hasColumn('country_pricing', 'status')) {
            Schema::table('country_pricing', function (Blueprint $table): void {
                $table->tinyInteger('status')->default(1)->after('tekpro_service_price');
            });
            DB::table('country_pricing')->update([
                'status' => DB::raw('CASE WHEN is_active = 1 THEN 1 ELSE 0 END'),
            ]);
            Schema::table('country_pricing', function (Blueprint $table): void {
                $table->dropColumn('is_active');
            });
        }

        // Ensure price columns exist with target names/types for fresh-ish DBs.
        Schema::table('country_pricing', function (Blueprint $table): void {
            if (! Schema::hasColumn('country_pricing', 'marketing_price')) {
                $table->decimal('marketing_price', 10, 4)->nullable();
            }
            if (! Schema::hasColumn('country_pricing', 'utility_price')) {
                $table->decimal('utility_price', 10, 4)->nullable();
            }
            if (! Schema::hasColumn('country_pricing', 'auth_price')) {
                $table->decimal('auth_price', 10, 4)->nullable();
            }
            if (! Schema::hasColumn('country_pricing', 'service_price')) {
                $table->decimal('service_price', 10, 4)->nullable();
            }
        });

        // Widen currency to varchar(10) and set default closer to legacy.
        try {
            DB::statement("ALTER TABLE country_pricing MODIFY currency VARCHAR(10) NOT NULL DEFAULT '₹'");
        } catch (\Throwable) {
            // SQLite / non-MySQL test envs skip MODIFY.
        }

        // country_code nullable like legacy (keep unique when present).
        try {
            DB::statement('ALTER TABLE country_pricing MODIFY country_code VARCHAR(255) NULL');
        } catch (\Throwable) {
            // ignore on non-MySQL
        }
    }

    private function renameIfExists(string $from, string $to): void
    {
        if (! Schema::hasColumn('country_pricing', $from) || Schema::hasColumn('country_pricing', $to)) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE country_pricing CHANGE `{$from}` `{$to}` DECIMAL(10,4) NULL");

            return;
        }

        Schema::table('country_pricing', function (Blueprint $table) use ($from, $to): void {
            $table->renameColumn($from, $to);
        });
    }
};
