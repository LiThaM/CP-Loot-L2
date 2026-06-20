<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clan_market_listings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clan_id')->constrained('clans')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('listing_type', ['wts', 'wtb']);
            $table->enum('item_type', ['item', 'account'])->default('item');
            $table->foreignId('item_id')->nullable()->constrained('items')->nullOnDelete();
            $table->string('item_name');
            $table->string('item_image_url')->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->bigInteger('price')->nullable();
            $table->boolean('is_negotiable')->default(false);
            $table->string('contact_info')->nullable();
            $table->enum('status', ['active', 'sold', 'cancelled'])->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clan_market_listings');
    }
};
