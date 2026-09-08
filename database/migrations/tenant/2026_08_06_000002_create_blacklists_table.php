<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blacklists', function (Blueprint $table) {
            $table->id();
            $table->publicUuid();
            $table->string('phone', 20)->nullable()->index();
            $table->string('email', 191)->nullable()->index();
            $table->string('reason', 255)->nullable();
            $table->auditable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blacklists');
    }
};
