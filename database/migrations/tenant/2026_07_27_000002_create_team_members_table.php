<?php

declare(strict_types=1);

use App\Enums\RecordStatus;
use App\Enums\TeamMemberRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_members', function (Blueprint $table) {
            $table->id();
            $table->publicUuid();
            $table->foreignId('parent_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('first_name', 80)->nullable();
            $table->string('last_name', 80)->nullable();
            $table->string('email', 191)->unique();
            $table->phoneNumber('phone', unique: true, nullable: true);
            $table->string('password');
            $table->string('role', 32)->default(TeamMemberRole::Member->value)->index();
            $table->string('status', 32)->default(RecordStatus::Active->value)->index();
            $table->boolean('auto_assign_chats')->default(false);
            $table->json('assigned_whatsapp_line_ids')->nullable();
            $table->json('permissions')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->auditable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_members');
    }
};
