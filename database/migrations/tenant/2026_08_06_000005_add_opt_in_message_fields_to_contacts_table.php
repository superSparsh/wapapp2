<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->string('send_opt_in_message', 10)->default('no')->after('status');
            $table->boolean('opt_in_message_sent')->default(false)->after('send_opt_in_message');
            $table->timestamp('opt_in_message_sent_at')->nullable()->after('opt_in_message_sent');
            $table->string('opt_in_message_delivery_status', 32)->nullable()->after('opt_in_message_sent_at');
            $table->text('opt_in_message_delivery_error')->nullable()->after('opt_in_message_delivery_status');
            $table->timestamp('opt_in_message_delivered_at')->nullable()->after('opt_in_message_delivery_error');
        });
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropColumn([
                'send_opt_in_message',
                'opt_in_message_sent',
                'opt_in_message_sent_at',
                'opt_in_message_delivery_status',
                'opt_in_message_delivery_error',
                'opt_in_message_delivered_at',
            ]);
        });
    }
};
