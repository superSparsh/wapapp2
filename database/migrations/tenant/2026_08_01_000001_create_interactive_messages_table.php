<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('interactive_messages', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('type')->default('button');
            $table->json('content');
            $table->foreignId('whatsapp_line_id')->nullable()->constrained('whatsapp_lines')->nullOnDelete();
            $table->foreignId('team_member_id')->nullable()->constrained('team_members')->nullOnDelete();
            $table->string('team_member_name')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['whatsapp_line_id', 'type'], 'interactive_messages_line_type_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('interactive_messages');
    }
};
