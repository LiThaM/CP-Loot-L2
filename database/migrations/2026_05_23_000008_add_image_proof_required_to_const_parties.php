<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('const_parties', function (Blueprint $table) {
            if (! Schema::hasColumn('const_parties', 'image_proof_required')) {
                $table->boolean('image_proof_required')->default(true)->after('is_active');
            }
        });
    }

    public function down(): void
    {
        Schema::table('const_parties', function (Blueprint $table) {
            if (Schema::hasColumn('const_parties', 'image_proof_required')) {
                $table->dropColumn('image_proof_required');
            }
        });
    }
};
