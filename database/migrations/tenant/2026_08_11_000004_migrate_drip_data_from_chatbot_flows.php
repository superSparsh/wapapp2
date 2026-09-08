<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('chatbot_flows') || ! Schema::hasColumn('chatbot_flows', 'type')) {
            return;
        }

        $dripFlows = DB::table('chatbot_flows')->where('type', 'drip')->get();

        foreach ($dripFlows as $flow) {
            $campaignId = DB::table('drip_campaigns')->insertGetId([
                'uuid' => $flow->uuid ?? (string) Str::uuid(),
                'name' => $flow->name,
                'status' => $flow->status,
                'exported_data' => $flow->exported_data,
                'created_by' => $flow->created_by,
                'published_at' => $flow->published_at,
                'timezone' => $flow->timezone ?? 'Asia/Kolkata',
                'start_date' => $flow->start_date,
                'end_date' => $flow->end_date,
                'audience_id' => $flow->audience_id,
                'trigger_type' => $flow->trigger_type,
                'created_at' => $flow->created_at,
                'updated_at' => $flow->updated_at,
                'deleted_at' => $flow->deleted_at,
            ]);

            if (Schema::hasTable('chatbot_flow_stats')) {
                $stats = DB::table('chatbot_flow_stats')->where('chatbot_flow_id', $flow->id)->get();

                foreach ($stats as $stat) {
                    DB::table('drip_campaign_stats')->insert([
                        'uuid' => $stat->uuid ?? (string) Str::uuid(),
                        'drip_campaign_id' => $campaignId,
                        'conversation_id' => $stat->conversation_id,
                        'node_id' => $stat->node_id,
                        'node_type' => $stat->node_type,
                        'contact_phone' => $stat->contact_phone,
                        'action' => $stat->action,
                        'metadata' => $stat->metadata,
                        'created_at' => $stat->created_at,
                        'updated_at' => $stat->updated_at,
                    ]);
                }

                DB::table('chatbot_flow_stats')->where('chatbot_flow_id', $flow->id)->delete();
            }

            if (Schema::hasTable('chatbot_flow_states')) {
                $states = DB::table('chatbot_flow_states')->where('chatbot_flow_id', $flow->id)->get();

                foreach ($states as $state) {
                    DB::table('drip_campaign_states')->insert([
                        'conversation_id' => $state->conversation_id,
                        'drip_campaign_id' => $campaignId,
                        'current_node_id' => $state->current_node_id,
                        'variables' => $state->variables,
                        'status' => $state->status,
                        'expires_at' => $state->expires_at,
                        'processed_at' => $state->processed_at,
                        'created_at' => $state->created_at,
                        'updated_at' => $state->updated_at,
                    ]);
                }

                DB::table('chatbot_flow_states')->where('chatbot_flow_id', $flow->id)->delete();
            }

            DB::table('chatbot_flows')->where('id', $flow->id)->delete();
        }
    }

    public function down(): void
    {
        // Irreversible data migration.
    }
};
