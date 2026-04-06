<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loot_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cp_id')->constrained('const_parties')->onDelete('cascade');
            $table->foreignId('item_id')->constrained('items')->onDelete('cascade');
            $table->foreignId('requested_by')->constrained('users')->onDelete('cascade');
            $table->foreignId('awarded_to')->nullable()->constrained('users')->onDelete('set null');
            $table->enum('status', ['pending', 'confirmed', 'sold'])->default('pending');
            $table->string('image_proof')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loot_entries');
    }
};
