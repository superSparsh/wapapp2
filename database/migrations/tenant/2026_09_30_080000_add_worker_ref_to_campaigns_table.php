<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->unsignedBigInteger('worker_ref')
                ->nullable()
                ->after('id')
                ->comment('Central campaign_worker_refs.id — globally unique OCI lifecycle key');
            $table->unique('worker_ref', 'campaigns_worker_ref_unique');
        });
    }

    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropUnique('campaigns_worker_ref_unique');
            $table->dropColumn('worker_ref');
        });
    }
};
