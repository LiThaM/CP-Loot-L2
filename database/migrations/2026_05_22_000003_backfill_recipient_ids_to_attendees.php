<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('loot_reports')
            ->whereNotNull('recipient_ids')
            ->orderBy('id')
            ->chunkById(200, function ($reports) use ($now) {
                foreach ($reports as $report) {
                    $ids = json_decode((string) $report->recipient_ids, true);
                    if (!is_array($ids) || empty($ids)) {
                        continue;
                    }

                    // Idempotent: skip reports that already have attendee rows.
                    $alreadyHas = DB::table('loot_report_attendees')
                        ->where('loot_report_id', $report->id)
                        ->exists();
                    if ($alreadyHas) {
                        continue;
                    }

                    $rows = [];
                    foreach ($ids as $userId) {
                        if (!is_numeric($userId)) {
                            continue;
                        }
                        $rows[] = [
                            'loot_report_id' => $report->id,
                            'user_id' => (int) $userId,
                            'external_name' => null,
                            'is_external' => false,
                            'share_adena' => null,
                            'paid_at' => null,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }

                    if (!empty($rows)) {
                        DB::table('loot_report_attendees')->insert($rows);
                    }
                }
            });
    }

    public function down(): void
    {
        // We never delete back: the data was reconstructed from recipient_ids,
        // which is still intact on loot_reports. Wiping here would discard
        // any externals added through the new flow.
    }
};
