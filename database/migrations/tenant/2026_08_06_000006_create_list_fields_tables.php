<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('list_fields', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->foreignId('mail_list_id')->constrained('mail_lists')->cascadeOnDelete();
            $table->string('label');
            $table->string('type', 32); // text, number, dropdown, multiselect, checkbox, radio, date, datetime, textarea
            $table->string('tag')->nullable();
            $table->text('default_value')->nullable();
            $table->boolean('required')->default(false);
            $table->boolean('visible')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('list_field_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('list_field_id')->constrained('list_fields')->cascadeOnDelete();
            $table->string('label');
            $table->string('value');
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('list_field_options');
        Schema::dropIfExists('list_fields');
    }
};
