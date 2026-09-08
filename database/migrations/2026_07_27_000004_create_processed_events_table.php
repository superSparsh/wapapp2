<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('processed_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_type', 32);
            $table->string('idempotency_key', 191);
            $table->string('status', 32);
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['event_type', 'idempotency_key'], 'processed_events_dedup_unique');
            $table->index('created_at', 'processed_events_cleanup_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('processed_events');
    }
};
