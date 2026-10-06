<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tutorial_videos', function (Blueprint $table): void {
            $table->string('previous_youtube_id', 191)->nullable()->after('youtube_id');
            $table->timestamp('video_updated_at')->nullable()->after('previous_youtube_id');
        });
    }

    public function down(): void
    {
        Schema::table('tutorial_videos', function (Blueprint $table): void {
            $table->dropColumn(['previous_youtube_id', 'video_updated_at']);
        });
    }
};
