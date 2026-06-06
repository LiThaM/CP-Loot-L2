<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('const_parties', function (Blueprint $table) {
            if (! Schema::hasColumn('const_parties', 'tracker_enabled')) {
                $table->boolean('tracker_enabled')->default(false)->after('image_proof_required');
            }
            if (! Schema::hasColumn('const_parties', 'tracker_divisor')) {
                $table->unsignedInteger('tracker_divisor')->default(1000)->after('tracker_enabled');
            }
            if (! Schema::hasColumn('const_parties', 'tracker_enabled_at')) {
                // Stamp the first time the toggle flipped to ON so auto-derive
                // can ignore loot reports from before opt-in (avoid avalanching
                // months of historical contributions on a fresh activation).
                $table->timestamp('tracker_enabled_at')->nullable()->after('tracker_divisor');
            }
        });
    }

    public function down(): void
    {
        Schema::table('const_parties', function (Blueprint $table) {
            foreach (['tracker_enabled', 'tracker_divisor', 'tracker_enabled_at'] as $col) {
                if (Schema::hasColumn('const_parties', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
