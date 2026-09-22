<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('wa_health_snapshots')) {
            Schema::create('wa_health_snapshots', function (Blueprint $table): void {
                $table->id();
                $table->string('tenant_id', 64)->index();
                $table->unsignedBigInteger('whatsapp_line_id')->index();
                $table->string('phone', 32)->nullable();
                $table->string('quality_rating', 32)->nullable();
                $table->string('messaging_limit_tier', 64)->nullable();
                $table->string('line_status', 32)->nullable();
                $table->unsignedInteger('tier_limit')->default(0);
                $table->unsignedInteger('usage_24h')->default(0);
                $table->date('snapshot_date')->index();
                $table->timestamps();

                $table->unique(['tenant_id', 'whatsapp_line_id', 'snapshot_date'], 'wa_health_snapshots_line_date_uq');
            });
        }

        if (! Schema::hasTable('wa_health_alerts')) {
            Schema::create('wa_health_alerts', function (Blueprint $table): void {
                $table->id();
                $table->string('tenant_id', 64)->nullable()->index();
                $table->unsignedBigInteger('whatsapp_line_id')->nullable()->index();
                $table->unsignedBigInteger('template_id')->nullable()->index();
                $table->string('alert_type', 64)->index();
                $table->string('severity', 32)->default('warning')->index();
                $table->string('title');
                $table->text('body')->nullable();
                $table->string('dedupe_key', 191)->nullable()->unique();
                $table->boolean('is_read')->default(false)->index();
                $table->timestamp('occurred_at')->index();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_health_alerts');
        Schema::dropIfExists('wa_health_snapshots');
    }
};
