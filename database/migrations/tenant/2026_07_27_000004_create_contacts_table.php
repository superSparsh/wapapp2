<?php

declare(strict_types=1);

use App\Enums\ContactOptInStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->publicUuid();
            $table->phoneNumber('phone', unique: true);
            $table->string('name', 150)->nullable();
            $table->string('email', 191)->nullable()->index();
            $table->char('country_code', 3)->nullable();
            $table->string('opt_in_status', 32)->default(ContactOptInStatus::Unknown->value)->index();
            $table->timestamp('opted_in_at')->nullable();
            $table->timestamp('opted_out_at')->nullable();
            $table->string('source', 64)->nullable()->index();
            $table->json('custom_fields')->nullable();
            $table->json('metadata')->nullable();
            $table->auditable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};
