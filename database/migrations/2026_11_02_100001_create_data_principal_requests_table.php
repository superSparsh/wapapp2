<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_principal_requests', function (Blueprint $table) {
            $table->id();
            $table->publicUuid();
            $table->string('tenant_id')->nullable()->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('requester_name', 191);
            $table->string('requester_email', 191)->index();
            $table->string('requester_phone', 32)->nullable();
            $table->string('request_type', 64)->index();
            $table->text('description');
            $table->string('nominee_name', 191)->nullable();
            $table->string('nominee_email', 191)->nullable();
            $table->string('nominee_phone', 32)->nullable();
            $table->string('nominee_relation', 128)->nullable();
            $table->string('status', 32)->default('open')->index();
            $table->text('admin_note')->nullable();
            $table->foreignId('handled_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('due_at')->nullable()->index();
            $table->timestamp('resolved_at')->nullable();
            $table->json('meta')->nullable();
            $table->auditable();

            $table->index(['status', 'due_at']);
            $table->index(['request_type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_principal_requests');
    }
};
