<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cp_requests', function (Blueprint $table) {
            $table->id();
            $table->string('cp_name');
            $table->string('server')->nullable();
            $table->string('chronicle')->nullable();
            $table->string('leader_name')->nullable();
            $table->string('contact_email')->nullable();
            $table->text('message')->nullable();
            $table->string('status')->default('pending');
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cp_requests');
    }
};
