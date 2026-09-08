<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('segments', function (Blueprint $table) {
            $table->id();
            $table->publicUuid();
            $table->string('name');
            $table->unsignedBigInteger('mail_list_id')->nullable()->index();
            $table->json('conditions')->nullable();
            $table->unsignedInteger('contact_count')->default(0);
            $table->auditable();

            $table->foreign('mail_list_id')
                ->references('id')
                ->on('mail_lists')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('segments');
    }
};
