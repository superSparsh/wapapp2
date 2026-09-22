<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcement_feature_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('announcement_id')->constrained('announcements')->cascadeOnDelete();
            $table->string('tenant_id', 64)->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('customer_email', 191);
            $table->string('customer_name', 191)->nullable();
            $table->string('plan_name', 191)->nullable();
            $table->boolean('is_acknowledged')->default(false);
            $table->boolean('is_viewed')->default(false);
            $table->timestamps();

            $table->unique(['announcement_id', 'tenant_id'], 'announcement_feature_requests_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcement_feature_requests');
    }
};
