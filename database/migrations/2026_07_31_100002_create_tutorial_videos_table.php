<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tutorial_videos', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('module_name');
            $table->string('youtube_id', 191);
            $table->text('description')->nullable();
            $table->string('duration', 32)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'sort_order'], 'tutorial_videos_active_sort_idx');
            $table->index('module_name', 'tutorial_videos_module_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tutorial_videos');
    }
};
