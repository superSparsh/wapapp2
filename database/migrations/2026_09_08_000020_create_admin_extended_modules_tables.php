<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_roles', function (Blueprint $table): void {
            $table->id();
            $table->publicUuid();
            $table->string('name', 120);
            $table->string('slug', 120)->unique();
            $table->json('permissions')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->auditable();
        });

        Schema::table('admins', function (Blueprint $table): void {
            if (! Schema::hasColumn('admins', 'admin_role_id')) {
                $table->foreignId('admin_role_id')->nullable()->after('id')->constrained('admin_roles')->nullOnDelete();
            }
        });

        Schema::create('currencies', function (Blueprint $table): void {
            $table->id();
            $table->publicUuid();
            $table->string('name', 120);
            $table->char('code', 3)->unique();
            $table->string('format', 64)->default('{PRICE}');
            $table->boolean('is_active')->default(true)->index();
            $table->auditable();
        });

        Schema::create('renew_subscription_requests', function (Blueprint $table): void {
            $table->id();
            $table->publicUuid();
            $table->string('tenant_id', 191)->index();
            $table->foreignId('plan_id')->nullable()->constrained('plans')->nullOnDelete();
            $table->statusColumn('status', 'pending');
            $table->text('notes')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->auditable();
        });

        Schema::create('recharge_subscription_requests', function (Blueprint $table): void {
            $table->id();
            $table->publicUuid();
            $table->string('tenant_id', 191)->index();
            $table->decimal('amount', 14, 2);
            $table->char('currency', 3)->default('INR');
            $table->statusColumn('status', 'pending');
            $table->text('notes')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->auditable();
        });

        Schema::create('customer_readiness_submissions', function (Blueprint $table): void {
            $table->id();
            $table->publicUuid();
            $table->string('customer_name', 191)->nullable();
            $table->string('customer_email', 191)->nullable()->index();
            $table->string('business_name', 191)->nullable();
            $table->string('business_email', 191)->nullable();
            $table->string('website', 512)->nullable();
            $table->string('doc_type', 32)->nullable();
            $table->statusColumn('status', 'pending');
            $table->json('data')->nullable();
            $table->json('meta')->nullable();
            $table->auditable();
        });

        Schema::create('customer_onboarding_submissions', function (Blueprint $table): void {
            $table->id();
            $table->publicUuid();
            $table->string('reference', 64)->nullable()->index();
            $table->string('company_name', 191)->nullable();
            $table->string('email', 191)->nullable()->index();
            $table->string('service_label', 191)->nullable();
            $table->statusColumn('status', 'pending');
            $table->json('payload')->nullable();
            $table->json('attachment_paths')->nullable();
            $table->json('zoho_response')->nullable();
            $table->auditable();
        });

        Schema::create('platform_templates', function (Blueprint $table): void {
            $table->id();
            $table->publicUuid();
            $table->string('name', 191);
            $table->string('category', 64)->nullable()->index();
            $table->string('type', 32)->default('whatsapp')->index();
            $table->longText('body')->nullable();
            $table->json('metadata')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->auditable();
        });

        Schema::create('form_templates', function (Blueprint $table): void {
            $table->id();
            $table->publicUuid();
            $table->string('name', 191);
            $table->string('slug', 120)->unique();
            $table->longText('html')->nullable();
            $table->json('fields')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->auditable();
        });

        Schema::create('page_layouts', function (Blueprint $table): void {
            $table->id();
            $table->publicUuid();
            $table->string('name', 191);
            $table->string('slug', 120)->unique();
            $table->string('alias', 64)->nullable()->index();
            $table->longText('html')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->auditable();
        });

        Schema::create('languages', function (Blueprint $table): void {
            $table->id();
            $table->publicUuid();
            $table->string('name', 120);
            $table->string('code', 16)->unique();
            $table->string('region_code', 16)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->auditable();
        });

        Schema::create('plugins', function (Blueprint $table): void {
            $table->id();
            $table->publicUuid();
            $table->string('name', 120)->unique();
            $table->string('title', 191);
            $table->text('description')->nullable();
            $table->string('version', 32)->nullable();
            $table->string('type', 64)->default('general')->index();
            $table->boolean('is_enabled')->default(false)->index();
            $table->json('metadata')->nullable();
            $table->auditable();
        });

        Schema::create('zoho_wallet_credit_requests', function (Blueprint $table): void {
            $table->id();
            $table->publicUuid();
            $table->string('tenant_id', 191)->index();
            $table->string('source', 32)->default('zoho')->index();
            $table->decimal('amount', 14, 2)->default(0);
            $table->char('currency', 3)->default('INR');
            $table->statusColumn('status', 'pending');
            $table->string('external_id', 191)->nullable()->index();
            $table->string('invoice_number', 191)->nullable();
            $table->timestamp('wallet_credited_at')->nullable();
            $table->text('last_error')->nullable();
            $table->json('metadata')->nullable();
            $table->auditable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zoho_wallet_credit_requests');
        Schema::dropIfExists('plugins');
        Schema::dropIfExists('languages');
        Schema::dropIfExists('page_layouts');
        Schema::dropIfExists('form_templates');
        Schema::dropIfExists('platform_templates');
        Schema::dropIfExists('customer_onboarding_submissions');
        Schema::dropIfExists('customer_readiness_submissions');
        Schema::dropIfExists('recharge_subscription_requests');
        Schema::dropIfExists('renew_subscription_requests');
        Schema::dropIfExists('currencies');

        if (Schema::hasColumn('admins', 'admin_role_id')) {
            Schema::table('admins', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('admin_role_id');
            });
        }

        Schema::dropIfExists('admin_roles');
    }
};
