<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('templates', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('tenant_id', 64)->index();
            $table->string('code')->nullable();
            $table->string('name');
            $table->string('language', 20)->default('en_GB');
            $table->string('category', 40)->default('MARKETING');
            $table->string('status', 30)->default('draft');
            $table->string('source', 20)->default('local');
            $table->unsignedBigInteger('whatsapp_line_id')->nullable();
            $table->unsignedBigInteger('team_member_id')->nullable();
            $table->string('team_member_name')->nullable();
            $table->json('payload')->nullable();
            $table->text('body_preview')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'whatsapp_line_id']);
            $table->index(['tenant_id', 'code']);
            $table->index(['tenant_id', 'deleted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('templates');
    }
};
