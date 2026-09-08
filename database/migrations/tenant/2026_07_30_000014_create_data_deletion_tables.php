<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_exports', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('data_age', 32);
            $table->json('modules');
            $table->string('status', 32)->default('pending')->index();
            $table->string('file_path')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedBigInteger('requested_by')->nullable();
            $table->auditable();
        });

        Schema::create('data_deletion_schedules', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('data_age', 32);
            $table->json('modules');
            $table->string('schedule_delay', 32);
            $table->boolean('export_before_delete')->default(true);
            $table->timestamp('scheduled_for')->index();
            $table->string('status', 32)->default('scheduled')->index();
            $table->unsignedInteger('records_deleted')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedBigInteger('requested_by')->nullable();
            $table->auditable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_deletion_schedules');
        Schema::dropIfExists('data_exports');
    }
};
