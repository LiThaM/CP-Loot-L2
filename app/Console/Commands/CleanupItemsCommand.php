<?php

namespace App\Console\Commands;

use App\Contexts\Loot\Domain\Models\Item;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanupItemsCommand extends Command
{
    protected $signature = 'items:cleanup
        {--apply : Ejecuta cambios en DB (por defecto es dry-run)}
        {--delete-unreferenced : Borra items inválidos si no están referenciados}
        {--fix-referenced : Corrige items inválidos referenciados (nombre/icon/image) para que no rompan UI}';

    protected $description = 'Limpia items inválidos (sin nombre o sin external_id) y evita que queden registros rotos.';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $deleteUnref = (bool) $this->option('delete-unreferenced');
        $fixRef = (bool) $this->option('fix-referenced');

        $bad = Item::query()
            ->whereNull('name')
            ->orWhereRaw("TRIM(COALESCE(name, '')) = ''")
            ->orWhereIn('name', ['-', '—'])
            ->orWhereNull('external_id')
            ->orWhere('external_id', '<=', 0)
            ->orderBy('id')
            ->get();

        if ($bad->isEmpty()) {
            $this->info('No se encontraron items inválidos.');

            return self::SUCCESS;
        }

        $rows = [];
        $toDelete = [];
        $toFix = [];

        foreach ($bad as $item) {
            $refCounts = $this->referenceCounts((int) $item->id);
            $refs = array_sum($refCounts);

            $action = 'none';
            if ($refs === 0 && $deleteUnref) {
                $action = $apply ? 'delete' : 'delete(dry)';
                $toDelete[] = (int) $item->id;
            } elseif ($refs > 0 && $fixRef) {
                $action = $apply ? 'fix' : 'fix(dry)';
                $toFix[] = (int) $item->id;
            }

            $rows[] = [
                'id' => (int) $item->id,
                'external_id' => (int) ($item->external_id ?? 0),
                'chronicle' => (string) ($item->chronicle ?? ''),
                'name' => (string) ($item->name ?? ''),
                'refs' => $refs,
                'loot_entries' => (int) ($refCounts['loot_entries'] ?? 0),
                'wishlists' => (int) ($refCounts['wishlists'] ?? 0),
                'recipe_materials' => (int) ($refCounts['recipe_materials'] ?? 0),
                'recipe_outputs' => (int) ($refCounts['recipe_outputs'] ?? 0),
                'recipes_output' => (int) ($refCounts['recipes_output'] ?? 0),
                'action' => $action,
            ];
        }

        $this->info('Items inválidos detectados: '.count($rows));
        $this->table(
            ['id', 'external_id', 'chronicle', 'name', 'refs', 'loot_entries', 'wishlists', 'recipe_materials', 'recipe_outputs', 'recipes_output', 'action'],
            array_slice($rows, 0, 80)
        );
        if (count($rows) > 80) {
            $this->line('... (mostrando 80 de '.count($rows).')');
        }

        if (! $apply) {
            $this->comment('Dry-run: usa --apply para ejecutar cambios. Puedes combinar con --delete-unreferenced y/o --fix-referenced.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($toDelete, $toFix) {
            if (count($toFix) > 0) {
                $items = Item::whereIn('id', $toFix)->get();
                foreach ($items as $item) {
                    $name = trim((string) ($item->name ?? ''));
                    $isBadName = $name === '' || in_array($name, ['-', '—'], true);
                    $externalId = (int) ($item->external_id ?? 0);
                    $fallback = $externalId > 0 ? "Unknown item #{$externalId}" : "Unknown item (id {$item->id})";

                    $updates = [];
                    if ($isBadName) {
                        $updates['name'] = $fallback;
                    }
                    if ((string) ($item->icon_name ?? '') === '-') {
                        $updates['icon_name'] = null;
                    }
                    if ((string) ($item->image_url ?? '') === 'https://wikipedia1.mw2.wiki/i64/.png') {
                        $updates['image_url'] = null;
                    }
                    if ($externalId <= 0) {
                        $updates['external_id'] = null;
                    }

                    if (count($updates) > 0) {
                        $item->update($updates);
                    }
                }
            }

            if (count($toDelete) > 0) {
                Item::whereIn('id', $toDelete)->delete();
            }
        });

        $this->info('Limpieza aplicada.');
        if (count($toFix) > 0) {
            $this->line('Items corregidos: '.count($toFix));
        }
        if (count($toDelete) > 0) {
            $this->line('Items borrados: '.count($toDelete));
        }

        return self::SUCCESS;
    }

    /**
     * @return array<string, int>
     */
    private function referenceCounts(int $itemId): array
    {
        $lootEntries = (int) DB::table('loot_entries')->where('item_id', $itemId)->count();
        $wishlists = (int) DB::table('wishlists')->where('item_id', $itemId)->count();
        $recipeMaterials = (int) DB::table('recipe_materials')->where('item_id', $itemId)->count();
        $recipeOutputs = (int) DB::table('recipe_outputs')->where('item_id', $itemId)->count();
        $recipesOutput = (int) DB::table('recipes')->where('output_item_id', $itemId)->count();

        return [
            'loot_entries' => $lootEntries,
            'wishlists' => $wishlists,
            'recipe_materials' => $recipeMaterials,
            'recipe_outputs' => $recipeOutputs,
            'recipes_output' => $recipesOutput,
        ];
    }
}

