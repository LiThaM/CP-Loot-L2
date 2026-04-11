<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('const_parties', function (Blueprint $table) {
            // Relax leader_id constraint (it should be assigned when a leader registers)
            $table->unsignedBigInteger('leader_id')->nullable()->change();

            // Add missing columns
            if (! Schema::hasColumn('const_parties', 'server')) {
                $table->string('server')->nullable()->after('name');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('const_parties', function (Blueprint $table) {
            $table->dropColumn('server');
            $table->unsignedBigInteger('leader_id')->nullable(false)->change();
        });
    }
};
