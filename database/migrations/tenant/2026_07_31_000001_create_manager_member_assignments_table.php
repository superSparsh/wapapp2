<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('manager_member_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('manager_id')->constrained('team_members')->cascadeOnDelete();
            $table->foreignId('member_id')->constrained('team_members')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['parent_user_id', 'member_id'], 'mma_parent_member_uq');
            $table->unique(['parent_user_id', 'manager_id', 'member_id'], 'mma_parent_mgr_member_uq');
            $table->index(['parent_user_id', 'manager_id'], 'mma_parent_mgr_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manager_member_assignments');
    }
};
