<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaign_recipients', function (Blueprint $table) {
            if (! Schema::hasColumn('campaign_recipients', 'variable_values')) {
                $table->json('variable_values')->nullable()->after('contact_phone');
            }
            if (! Schema::hasColumn('campaign_recipients', 'responded_at')) {
                $table->timestamp('responded_at')->nullable()->after('read_at');
            }
        });

        if (! Schema::hasTable('campaign_webhooks')) {
            Schema::create('campaign_webhooks', function (Blueprint $table) {
                $table->id();
                $table->foreignId('campaign_id')->constrained('campaigns')->cascadeOnDelete();
                $table->string('url', 500);
                $table->string('secret_key', 64)->nullable();
                $table->json('events')->nullable();
                $table->string('status', 20)->default('active');
                $table->timestamps();

                $table->index(['campaign_id', 'status']);
            });
        }

        if (! Schema::hasTable('automation_events')) {
            Schema::create('automation_events', function (Blueprint $table) {
                $table->id();
                $table->publicUuid();
                $table->string('name', 191);
                $table->string('status', 32)->default('draft');
                $table->string('event_type', 64);
                $table->json('payload')->nullable();
                $table->timestamp('scheduled_at')->nullable();
                $table->timestamp('processed_at')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('team_members')->nullOnDelete();
                $table->auditable();

                $table->index(['status', 'scheduled_at']);
            });
        }

        if (! Schema::hasTable('woo_commerce_stores')) {
            Schema::create('woo_commerce_stores', function (Blueprint $table) {
                $table->id();
                $table->publicUuid();
                $table->string('store_url', 500);
                $table->string('consumer_key', 255)->nullable();
                $table->string('consumer_secret', 255)->nullable();
                $table->string('status', 32)->default('inactive');
                $table->json('settings')->nullable();
                $table->auditable();
            });
        }

        if (! Schema::hasTable('website_trackers')) {
            Schema::create('website_trackers', function (Blueprint $table) {
                $table->id();
                $table->publicUuid();
                $table->string('name', 191);
                $table->string('domain', 255);
                $table->string('tracking_token', 64)->unique();
                $table->string('status', 32)->default('active');
                $table->auditable();
            });
        }

        if (! Schema::hasTable('wallet_auto_recharge_settings')) {
            Schema::create('wallet_auto_recharge_settings', function (Blueprint $table) {
                $table->id();
                $table->boolean('enabled')->default(false);
                $table->decimal('threshold_amount', 12, 2)->default(500);
                $table->decimal('recharge_amount', 12, 2)->default(5000);
                $table->timestamp('last_triggered_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_auto_recharge_settings');
        Schema::dropIfExists('website_trackers');
        Schema::dropIfExists('woo_commerce_stores');
        Schema::dropIfExists('automation_events');
        Schema::dropIfExists('campaign_webhooks');
    }
};
