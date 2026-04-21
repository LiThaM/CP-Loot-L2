<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_tickets', function (Blueprint $table) {
            $table->string('ticket_number')->unique()->nullable()->after('id');
            $table->string('type')->default('support')->after('status'); // support, bug, data_discrepancy
            $table->foreignId('assigned_to_user_id')->nullable()->constrained('users')->nullOnDelete()->after('type');
            $table->timestamp('closed_at')->nullable()->after('assigned_to_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('support_tickets', function (Blueprint $table) {
            $table->dropForeign(['assigned_to_user_id']);
            $table->dropColumn(['ticket_number', 'type', 'assigned_to_user_id', 'closed_at']);
        });
    }
};
