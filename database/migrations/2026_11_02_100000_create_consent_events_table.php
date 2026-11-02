<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consent_events', function (Blueprint $table) {
            $table->id();
            $table->publicUuid();
            $table->string('tenant_id')->nullable()->index();
            $table->string('subject_type', 64)->index();
            $table->string('subject_id', 191)->nullable()->index();
            $table->string('subject_email', 191)->nullable()->index();
            $table->string('event_type', 32)->index();
            $table->string('purpose', 128)->index();
            $table->string('channel', 64)->default('web');
            $table->string('source', 128)->nullable();
            $table->string('document_url', 2048)->nullable();
            $table->json('meta')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('occurred_at')->index();
            $table->auditable();

            $table->index(['purpose', 'event_type', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consent_events');
    }
};
