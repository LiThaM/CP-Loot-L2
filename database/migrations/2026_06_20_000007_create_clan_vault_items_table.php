<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clan_vault_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clan_id')->constrained('clans')->cascadeOnDelete();
            $table->foreignId('item_id')->nullable()->constrained('items')->nullOnDelete();
            $table->string('item_name');
            $table->string('item_image_url')->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->enum('status', ['in_vault', 'auctioning', 'assigned', 'raffled', 'removed'])->default('in_vault');
            $table->foreignId('assigned_to_cp_id')->nullable()->constrained('const_parties')->nullOnDelete();
            $table->foreignId('deposited_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clan_vault_items');
    }
};
