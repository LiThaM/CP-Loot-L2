<?php

use App\Contexts\Loot\Domain\Models\LootReport;
use App\Contexts\Loot\Domain\Models\LootReportAttendee;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Earlier DONATION reports were created without an attendee/recipient, so
     * the loot UI didn't show who donated. Attribute each existing DONATION to
     * its donor (requested_by_id) as the single attendee + recipient.
     */
    public function up(): void
    {
        LootReport::where('event_type', 'DONATION')
            ->whereNotNull('requested_by_id')
            ->chunkById(200, function ($reports) {
                foreach ($reports as $report) {
                    if (empty($report->recipient_ids)) {
                        $report->recipient_ids = [$report->requested_by_id];
                        $report->save();
                    }
                    $exists = LootReportAttendee::where('loot_report_id', $report->id)
                        ->where('user_id', $report->requested_by_id)
                        ->exists();
                    if (! $exists) {
                        LootReportAttendee::create([
                            'loot_report_id' => $report->id,
                            'user_id' => $report->requested_by_id,
                            'external_name' => null,
                            'is_external' => false,
                        ]);
                    }
                }
            });
    }

    public function down(): void
    {
        // No-op: attendee/recipient attribution for donations is correct data.
    }
};
