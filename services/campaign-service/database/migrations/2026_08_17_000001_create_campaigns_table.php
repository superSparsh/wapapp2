<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('tenant_id', 64)->index();
            $table->string('name', 255);
            $table->string('status', 20)->default('draft');
            $table->unsignedBigInteger('audience_id')->nullable()->index();
            $table->unsignedBigInteger('whatsapp_line_id')->nullable()->index();
            $table->unsignedBigInteger('template_id')->nullable()->index();
            $table->json('template_variables')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->string('timezone', 50)->default('Asia/Kolkata');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('total_recipients')->default(0);
            $table->unsignedInteger('total_delivered')->default(0);
            $table->unsignedInteger('total_failed')->default(0);
            $table->unsignedInteger('total_read')->default(0);
            $table->unsignedInteger('total_response')->default(0);
            $table->unsignedInteger('total_unsubscribed')->default(0);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'status', 'created_at'], 'campaigns_tenant_status_created_idx');
            $table->index(['tenant_id', 'audience_id', 'status'], 'campaigns_tenant_aud_status_idx');
            $table->index(['tenant_id', 'whatsapp_line_id', 'status'], 'campaigns_tenant_line_status_idx');
            $table->index(['tenant_id', 'scheduled_at'], 'campaigns_tenant_scheduled_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaigns');
    }
};
