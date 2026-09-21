<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('isv_terms_acceptances', function (Blueprint $table) {
            $table->id();
            $table->publicUuid();
            $table->string('business_name')->nullable();
            $table->string('website_email')->nullable()->unique();
            $table->string('bm_id')->nullable();
            $table->string('use_case')->nullable();
            $table->text('business_address')->nullable();
            $table->string('country_code', 8)->nullable()->default('IN');
            $table->string('cus_space_id', 64)->nullable();
            $table->string('status', 32)->nullable()->default('Unverified');
            $table->auditable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('isv_terms_acceptances');
    }
};
