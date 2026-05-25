<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $bodyEs = <<<'MD'
**Mejoras en el crafting del CP Vault**:

- **Permisos**: admins, líderes y contables ya pueden fijar, mover y quitar recetas pinned (antes solo el líder exacto del CP).
- **Auto-craft visible**: si te falta un material intermedio craftable (p.ej. *Crafted Leather*) pero tienes los raw materials, ahora el botón **Craft** se habilita y el sistema te muestra "Se auto-crafteará". Tras craftear, el toast indica qué intermedios se auto-craftearon y qué se produjo.
- **Recetas-pergamino solo para items finales**: ya no se exige el "Recipe: X" en el warehouse cuando crafteas un material (Crafted Leather, Cord, etc). Sigue siendo obligatorio para weapons/armors/jewelry/accessories.
- **Pin desde Craft Bulk**: en `/party/craft-bulk` ahora hay un botón 📌 por receta planificada para fijarla como prioridad directamente.
- **Roles inline en miembros**: en el listado de miembros del CP, cambia el rol desde un dropdown sin abrir el modal.
MD;

        $bodyEn = <<<'MD'
**Manual crafting improvements**:

- **Permissions**: admins, leaders and accountants can now pin/reorder/remove pinned recipes (previously only the exact CP leader).
- **Auto-craft visible**: when an intermediate craftable is missing (e.g. *Crafted Leather*) but the raw materials are present, the **Craft** button now enables and the row shows "Will auto-craft". After crafting, the toast lists which intermediates were auto-crafted and what was produced.
- **Recipe scroll only for final items**: the warehouse no longer requires "Recipe: X" when you craft an intermediate Material (Crafted Leather, Cord, …). Still required for weapons/armors/jewelry/accessories.
- **Pin from Craft Bulk**: each planned recipe in `/party/craft-bulk` now has a 📌 button to pin it as priority in one click.
- **Inline role assignment**: members listing now has a role dropdown per row so you can change roles without opening the modal.
MD;

        DB::table('changelog_entries')->updateOrInsert(
            ['type' => 'feature', 'version' => null, 'title_en' => 'Manual crafting: relaxed permissions, visible auto-craft, scroll-only-for-finals'],
            [
                'audience' => 'web',
                'title_es' => 'Crafting manual: permisos abiertos, auto-craft visible, pergamino solo para finales',
                'title_en' => 'Manual crafting: relaxed permissions, visible auto-craft, scroll-only-for-finals',
                'body_es' => $bodyEs,
                'body_en' => $bodyEn,
                'published_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        DB::table('changelog_entries')
            ->where('title_en', 'Manual crafting: relaxed permissions, visible auto-craft, scroll-only-for-finals')
            ->delete();
    }
};
