<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_flow_submissions', function (Blueprint $table) {
            $table->id();
            $table->publicUuid();
            $table->foreignId('whatsapp_flow_id')->constrained('whatsapp_flows')->cascadeOnDelete();
            $table->foreignId('conversation_id')->nullable()->constrained('conversations')->nullOnDelete();
            $table->string('contact_phone', 32);
            $table->json('form_data');
            $table->string('status', 32)->default('received');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['whatsapp_flow_id', 'created_at'], 'wf_submissions_flow_created_idx');
            $table->index('contact_phone', 'wf_submissions_phone_idx');
            $table->index('status', 'wf_submissions_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_flow_submissions');
    }
};
