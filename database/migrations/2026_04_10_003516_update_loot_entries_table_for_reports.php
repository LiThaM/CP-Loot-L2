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
        // Re-creating the table is safer for major refactors in SQLite
        Schema::dropIfExists('loot_entries');

        Schema::create('loot_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loot_report_id')->constrained('loot_reports')->onDelete('cascade');
            $table->foreignId('item_id')->constrained('items')->onDelete('cascade');
            $table->foreignId('awarded_to')->nullable()->constrained('users')->onDelete('set null');
            $table->integer('amount')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This is a destructive upgrade, down logic would need to restore the old structure
        // But for development, dropIfExists is the standard for redo
        Schema::dropIfExists('loot_entries');
    }
};
