<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loot_report_attendees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loot_report_id')->constrained('loot_reports')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('external_name', 80)->nullable();
            $table->boolean('is_external')->default(false);
            // share_adena is set when a SELL is committed against the source
            // farm report; it tracks how much this person was paid in this
            // specific sale. One sell = one set of attendee rows on the SELL
            // report (not the farm report), to preserve per-sale history.
            $table->bigInteger('share_adena')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['loot_report_id', 'is_external']);
            $table->index('user_id');
            $table->index('paid_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loot_report_attendees');
    }
};
