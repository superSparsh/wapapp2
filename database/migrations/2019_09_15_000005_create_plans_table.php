<?php

declare(strict_types=1);

use App\Enums\BillingCycle;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->publicUuid();
            $table->string('name', 120);
            $table->string('slug', 80)->unique();
            $table->text('description')->nullable();
            $table->decimal('price', 12, 2)->default(0);
            $table->char('currency', 3)->default('INR');
            $table->string('billing_cycle', 32)->default(BillingCycle::Monthly->value);
            $table->unsignedInteger('messages_limit')->nullable();
            $table->unsignedInteger('contacts_limit')->nullable();
            $table->unsignedInteger('team_members_limit')->nullable();
            $table->unsignedInteger('whatsapp_lines_limit')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->json('features')->nullable();
            $table->auditable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
