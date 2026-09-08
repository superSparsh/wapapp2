<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::create('announcements', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->text('body');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
        });

        Schema::create('country_pricing', function (Blueprint $table): void {
            $table->id();
            $table->string('country_code', 8)->unique();
            $table->string('country_name');
            $table->decimal('marketing_rate', 12, 4)->default(0);
            $table->decimal('utility_rate', 12, 4)->default(0);
            $table->decimal('authentication_rate', 12, 4)->default(0);
            $table->decimal('service_rate', 12, 4)->default(0);
            $table->string('currency', 3)->default('USD');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('cloud_bill_uploads', function (Blueprint $table): void {
            $table->id();
            $table->string('filename');
            $table->string('path');
            $table->string('period')->nullable();
            $table->decimal('amount', 14, 2)->nullable();
            $table->string('currency', 3)->default('USD');
            $table->decimal('exchange_rate', 14, 6)->nullable();
            $table->string('status')->default('uploaded')->index();
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cloud_bill_uploads');
        Schema::dropIfExists('country_pricing');
        Schema::dropIfExists('announcements');
        Schema::dropIfExists('platform_settings');
    }
};
