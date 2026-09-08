<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaign_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('campaigns')->cascadeOnDelete();
            $table->unsignedBigInteger('contact_id')->nullable()->index();
            $table->string('contact_phone', 20)->index();
            $table->string('status', 20)->default('pending')->index();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->string('failure_reason', 255)->nullable();
            $table->timestamp('unsubscribed_at')->nullable();
            $table->string('message_id', 100)->nullable()->index();
            $table->json('variable_values')->nullable();
            $table->timestamps();

            $table->index(['campaign_id', 'status'], 'camp_recipients_camp_status_idx');
            $table->index(['campaign_id', 'contact_phone'], 'camp_recipients_camp_phone_idx');
            $table->index(['campaign_id', 'created_at'], 'camp_recipients_camp_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_recipients');
    }
};
