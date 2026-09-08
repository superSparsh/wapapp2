<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('drip_campaigns', function (Blueprint $table) {
            $table->id();
            $table->publicUuid();
            $table->string('name', 191);
            $table->string('status', 32)->default('draft');
            $table->longText('exported_data')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('team_members')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->string('timezone', 64)->nullable()->default('Asia/Kolkata');
            $table->timestamp('start_date')->nullable();
            $table->timestamp('end_date')->nullable();
            $table->foreignId('audience_id')->nullable()->constrained('mail_lists')->nullOnDelete();
            $table->string('trigger_type', 50)->nullable();
            $table->auditable();

            $table->index(['status', 'deleted_at'], 'drip_campaigns_status_deleted_idx');
            $table->index(['audience_id', 'deleted_at'], 'drip_campaigns_audience_deleted_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('drip_campaigns');
    }
};
