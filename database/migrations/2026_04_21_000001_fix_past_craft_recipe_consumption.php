<?php
 
use Illuminate\Database\Migrations\Migration;
use App\Contexts\Loot\Domain\Models\LootReport;
use App\Contexts\Loot\Domain\Models\LootEntry;
use App\Contexts\Loot\Domain\Models\Recipe;
use Illuminate\Support\Facades\DB;
 
return new class extends Migration
{
    public function up(): void
    {
        // 1. Get all successful crafts (PRODUCE events)
        $produceReports = LootReport::where('event_type', 'WAREHOUSE_CRAFT_PRODUCE')
            ->where('status', 'confirmed')
            ->with(['entries', 'cp'])
            ->get();
 
        foreach ($produceReports as $produceReport) {
            $producedEntry = $produceReport->entries->first();
            if (!$producedEntry) continue;
 
            // 2. Find the recipe that likely produced this item
            $chronicle = $produceReport->cp->chronicle ?: 'IL';
            $recipe = Recipe::where('output_item_id', $producedEntry->item_id)
                ->where('chronicle', $chronicle)
                ->first();
            
            // If not found by direct output, try alternative outputs
            if (!$recipe) {
                $recipeId = DB::table('recipe_outputs')
                    ->where('item_id', $producedEntry->item_id)
                    ->value('recipe_id');
                
                if ($recipeId) {
                    $recipe = Recipe::find($recipeId);
                }
            }
 
            if (!$recipe || !$recipe->recipe_item_id) continue;
 
            // 3. Find the corresponding CONSUME report
            // It should have been created at almost the same time, same CP, same user
            $consumeReport = LootReport::where('event_type', 'WAREHOUSE_CRAFT_CONSUME')
                ->where('cp_id', $produceReport->cp_id)
                ->where('requested_by_id', $produceReport->requested_by_id)
                ->whereBetween('created_at', [
                    $produceReport->created_at->subSeconds(2),
                    $produceReport->created_at->addSeconds(2)
                ])
                ->first();
 
            if (!$consumeReport) continue;
 
            // 4. Check if the recipe item is already in the consume report
            $alreadyConsumed = LootEntry::where('loot_report_id', $consumeReport->id)
                ->where('item_id', $recipe->recipe_item_id)
                ->exists();
 
            if (!$alreadyConsumed) {
                // Retroactively add the recipe consumption
                LootEntry::create([
                    'loot_report_id' => $consumeReport->id,
                    'item_id' => $recipe->recipe_item_id,
                    'amount' => 1,
                ]);
            }
        }
    }
 
    public function down(): void
    {
        // This is a one-way fix for data integrity. 
        // Reversing it would require tracking which entries were added by this script.
    }
};
