<?php

declare(strict_types=1);

use App\Domains\FormBuilder\Enums\FormStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('signup_forms', function (Blueprint $table) {
            $table->id();
            $table->publicUuid();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('status', 32)->default(FormStatus::Inactive->value)->index();

            // Associations (nullable — not all forms need a list or template)
            $table->foreignId('whatsapp_line_id')->nullable()->constrained('whatsapp_lines')->nullOnDelete();
            $table->unsignedBigInteger('list_id')->nullable()->index();
            $table->unsignedBigInteger('template_id')->nullable()->index();
            $table->unsignedBigInteger('team_member_id')->nullable();
            $table->string('team_member_name')->nullable();

            // Form definition stored as JSON (fields, logo path, embed settings)
            $table->json('fields')->nullable();
            $table->string('logo_path')->nullable();
            $table->json('embed_settings')->nullable();
            $table->string('redirect_url')->nullable();
            $table->text('custom_css')->nullable();
            $table->text('embed_code')->nullable();

            // Cached statistics (avoids COUNT queries on listing)
            $table->unsignedInteger('submission_count')->default(0);
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('read_count')->default(0);
            $table->unsignedInteger('delivered_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);

            $table->timestamps();
            $table->softDeletes();

            // Optimised composite indexes for common listing queries
            $table->index(['status', 'deleted_at'], 'signup_forms_status_idx');
            $table->index(['whatsapp_line_id', 'deleted_at'], 'signup_forms_line_idx');
        });

        Schema::create('form_submissions', function (Blueprint $table) {
            $table->id();
            $table->publicUuid();
            $table->foreignId('signup_form_id')->constrained('signup_forms')->cascadeOnDelete();
            $table->unsignedBigInteger('contact_id')->nullable()->index();
            $table->unsignedBigInteger('template_id')->nullable()->index();
            $table->string('phone', 20)->nullable()->index();
            $table->json('submission_data')->nullable();
            $table->string('message_status', 32)->default('pending')->index();
            $table->string('external_message_id', 191)->nullable();
            $table->text('failed_reason')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            $table->index(['signup_form_id', 'created_at'], 'form_submissions_form_created_idx');
            $table->index(['message_status', 'signup_form_id'], 'form_submissions_status_form_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_submissions');
        Schema::dropIfExists('signup_forms');
    }
};
