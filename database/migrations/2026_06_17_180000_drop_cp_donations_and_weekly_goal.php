<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Donations were reworked to live as DONATION loot reports (reviewable in
     * /loot). The standalone `cp_donations` recognition ledger and the
     * `weekly_donation_goal` (adena meta, replaced by weekly item objectives)
     * are no longer used.
     */
    public function up(): void
    {
        Schema::dropIfExists('cp_donations');

        if (Schema::hasColumn('const_parties', 'weekly_donation_goal')) {
            Schema::table('const_parties', function (Blueprint $table) {
                $table->dropColumn('weekly_donation_goal');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('const_parties', 'weekly_donation_goal')) {
            Schema::table('const_parties', function (Blueprint $table) {
                $table->unsignedBigInteger('weekly_donation_goal')->nullable()->after('tracker_round_points_up');
            });
        }

        if (! Schema::hasTable('cp_donations')) {
            Schema::create('cp_donations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('cp_id')->constrained('const_parties')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('type', 10);
                $table->foreignId('item_id')->nullable()->constrained('items')->nullOnDelete();
                $table->unsignedBigInteger('quantity')->nullable();
                $table->unsignedBigInteger('adena_value')->default(0);
                $table->string('note', 255)->nullable();
                $table->timestamps();
                $table->index(['cp_id', 'created_at']);
                $table->index(['cp_id', 'user_id']);
            });
        }
    }
};
