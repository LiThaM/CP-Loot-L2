<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Donations to the CP common fund — both adena and items. Adena
     * donations already lived in points_logs (ADENA_OFFSET via the donate
     * flow) and adjust member balances; this dedicated ledger is the
     * *recognition* record that powers the donations ranking and the weekly
     * goal KPI, without touching loot/warehouse balances. `adena_value` is
     * the adena-equivalent worth used for ranking/goal maths (= amount for
     * adena donations; = qty x market/NPC price for item donations).
     */
    public function up(): void
    {
        if (! Schema::hasTable('cp_donations')) {
            Schema::create('cp_donations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('cp_id')->constrained('const_parties')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('type', 10); // 'adena' | 'item'
                $table->foreignId('item_id')->nullable()->constrained('items')->nullOnDelete();
                $table->unsignedBigInteger('quantity')->nullable();
                $table->unsignedBigInteger('adena_value')->default(0);
                $table->string('note', 255)->nullable();
                $table->timestamps();

                $table->index(['cp_id', 'created_at']);
                $table->index(['cp_id', 'user_id']);
            });
        }

        if (! Schema::hasColumn('const_parties', 'weekly_donation_goal')) {
            Schema::table('const_parties', function (Blueprint $table) {
                // Leader-set target (adena-equivalent) for the rolling 7-day
                // donations KPI. null = no goal set yet (KPI hidden).
                $table->unsignedBigInteger('weekly_donation_goal')->nullable()->after('tracker_round_points_up');
            });
        }

        // Backfill historical adena donations from points_logs so the ranking
        // and KPI aren't empty on launch. Only the voluntary-donation rows
        // (exact description from AdenaActionController@donate) — NOT the
        // assignment-discount offsets, which share the ADENA_OFFSET type.
        $alreadySeeded = DB::table('cp_donations')->where('type', 'adena')->exists();
        if (! $alreadySeeded) {
            $rows = DB::table('points_logs')
                ->where('action_type', 'ADENA_OFFSET')
                ->where('description', 'Donación voluntaria al fondo de la CP')
                ->get(['cp_id', 'user_id', 'adena', 'created_at', 'updated_at']);

            foreach ($rows as $r) {
                DB::table('cp_donations')->insert([
                    'cp_id' => $r->cp_id,
                    'user_id' => $r->user_id,
                    'type' => 'adena',
                    'item_id' => null,
                    'quantity' => null,
                    'adena_value' => abs((int) $r->adena),
                    'note' => 'Donación de adena',
                    'created_at' => $r->created_at,
                    'updated_at' => $r->updated_at,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cp_donations');
        if (Schema::hasColumn('const_parties', 'weekly_donation_goal')) {
            Schema::table('const_parties', function (Blueprint $table) {
                $table->dropColumn('weekly_donation_goal');
            });
        }
    }
};
