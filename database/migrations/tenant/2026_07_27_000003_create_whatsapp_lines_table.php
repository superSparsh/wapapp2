<?php

declare(strict_types=1);

use App\Enums\RecordStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_lines', function (Blueprint $table) {
            $table->id();
            $table->publicUuid();
            $table->phoneNumber('phone', unique: true);
            $table->string('display_name', 150)->nullable();
            $table->string('waba_id', 64)->nullable()->index();
            $table->string('alibaba_cust_space_id', 64)->nullable()->index();
            $table->string('alibaba_phone_number_id', 64)->nullable()->index();
            $table->string('status', 32)->default(RecordStatus::Active->value);
            $table->boolean('is_default')->default(false);
            $table->string('quality_rating', 32)->nullable();
            $table->string('messaging_limit_tier', 32)->nullable();
            $table->json('profile')->nullable();
            $table->json('metadata')->nullable();
            $table->auditable();

            $table->index(['status', 'is_default'], 'whatsapp_lines_status_default_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_lines');
    }
};
