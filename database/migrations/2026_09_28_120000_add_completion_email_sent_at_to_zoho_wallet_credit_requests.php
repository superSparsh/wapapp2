<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('zoho_wallet_credit_requests', function (Blueprint $table): void {
            if (! Schema::hasColumn('zoho_wallet_credit_requests', 'completion_email_sent_at')) {
                $table->timestamp('completion_email_sent_at')->nullable()->after('wallet_credited_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('zoho_wallet_credit_requests', function (Blueprint $table): void {
            if (Schema::hasColumn('zoho_wallet_credit_requests', 'completion_email_sent_at')) {
                $table->dropColumn('completion_email_sent_at');
            }
        });
    }
};
