<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('full_name', 255);
            $table->string('type', 16)->index();
            $table->string('contact_info', 255);
            $table->auditable();
        });

        Schema::create('account_preferences', function (Blueprint $table) {
            $table->id();
            $table->boolean('alerts_enabled')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_preferences');
        Schema::dropIfExists('notification_contacts');
    }
};
