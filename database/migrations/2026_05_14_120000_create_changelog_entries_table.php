<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('changelog_entries', function (Blueprint $table) {
            $table->id();
            $table->string('type', 32);
            $table->string('version', 32)->nullable();
            $table->string('title_es');
            $table->string('title_en');
            $table->text('body_es')->nullable();
            $table->text('body_en')->nullable();
            $table->timestamp('published_at');
            $table->timestamps();

            $table->index('published_at');
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('changelog_entries');
    }
};
