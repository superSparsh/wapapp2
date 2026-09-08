<?php

declare(strict_types=1);

use App\Enums\TenantStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTenantsTable extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name', 150);
            $table->string('company_name', 150)->nullable();
            $table->string('email', 191)->nullable()->unique();
            $table->phoneNumber('phone', unique: true, nullable: true);
            $table->string('status', 32)->default(TenantStatus::Active->value)->index();
            $table->foreignId('plan_id')->nullable()->constrained('plans')->nullOnDelete();
            $table->string('timezone', 64)->default('Asia/Kolkata');
            $table->char('locale', 10)->default('en');
            $table->string('country_code', 3)->nullable();
            $table->json('settings')->nullable();
            $table->timestamp('provisioned_at')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->json('data')->nullable();

            $table->index(['status', 'plan_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
}
