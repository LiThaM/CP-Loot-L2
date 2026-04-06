<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('cp_id')->nullable()->constrained('const_parties')->onDelete('set null');
            $table->foreignId('role_id')->nullable()->constrained('roles')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['cp_id']);
            $table->dropForeign(['role_id']);
            $table->dropColumn(['cp_id', 'role_id']);
        });
    }
};
