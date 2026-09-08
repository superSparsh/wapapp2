<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->foreignId('assigned_team_member_id')
                ->nullable()
                ->after('assigned_user_id')
                ->constrained('team_members')
                ->nullOnDelete();

            $table->index(['assigned_team_member_id', 'status'], 'conversations_team_member_index');
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropIndex('conversations_team_member_index');
            $table->dropConstrainedForeignId('assigned_team_member_id');
        });
    }
};
