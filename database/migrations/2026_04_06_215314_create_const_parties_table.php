<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('const_parties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('leader_id')->constrained('users')->onDelete('cascade');
            $table->string('name');
            $table->string('invite_code')->unique()->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('const_parties');
    }
};
