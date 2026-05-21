<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            if (! Schema::hasColumn('items', 'market_price')) {
                $table->unsignedBigInteger('market_price')->nullable()->after('base_points');
            }
            if (! Schema::hasColumn('items', 'market_price_updated_at')) {
                $table->timestamp('market_price_updated_at')->nullable()->after('market_price');
            }
            if (! Schema::hasColumn('items', 'market_price_updated_by')) {
                $table->foreignId('market_price_updated_by')->nullable()->after('market_price_updated_at')
                    ->constrained('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            if (Schema::hasColumn('items', 'market_price_updated_by')) {
                $table->dropForeign(['market_price_updated_by']);
                $table->dropColumn('market_price_updated_by');
            }
            if (Schema::hasColumn('items', 'market_price_updated_at')) {
                $table->dropColumn('market_price_updated_at');
            }
            if (Schema::hasColumn('items', 'market_price')) {
                $table->dropColumn('market_price');
            }
        });
    }
};
