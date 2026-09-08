<?php

declare(strict_types=1);

use App\Enums\RecordStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mail_lists', function (Blueprint $table) {
            $table->id();
            $table->publicUuid();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('status', 32)->default(RecordStatus::Active->value)->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('contacts', function (Blueprint $table) {
            $table->unsignedBigInteger('mail_list_id')->nullable()->after('phone')->index();
        });
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropColumn('mail_list_id');
        });

        Schema::dropIfExists('mail_lists');
    }
};
