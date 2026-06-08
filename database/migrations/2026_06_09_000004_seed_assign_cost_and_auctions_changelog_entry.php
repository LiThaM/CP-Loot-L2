<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $bodyEs = <<<'MD'
Dos novedades grandes que cierran el bucle de puntos DKP:

**1. Gasto automático al asignar items del almacén.** Si el CP tiene el tracker DKP activado, al asignar un item desde el almacén a un miembro se le descuentan automáticamente puntos = `precio × cantidad ÷ divisor`. El modal de asignación muestra el coste estimado antes de confirmar y tiene un checkbox "No descontar puntos (regalo)" para excepciones.

**2. Sistema de subastas.** Disponible en [`/party/auctions`](/party/auctions). El leader saca un item del almacén a subasta eligiendo moneda (DKP points o adena), puja inicial, buy-now opcional y duración (15min a 3 días). Los miembros pujan; cada nueva puja libera al pujador anterior (sin escrow). Cuando expira, un cron cierra la subasta y asigna ganador. El leader pulsa **Entregar** para asignar el item y descontar la puja ganadora.

El sistema admite saldo DKP negativo — la app no bloquea si el coste supera tu saldo, simplemente te quedas en deuda.
MD;

        $bodyEn = <<<'MD'
Two big additions that close the DKP points loop:

**1. Automatic spending when assigning warehouse items.** If the CP has the DKP tracker enabled, assigning an item from the warehouse to a member automatically deducts points = `price × amount ÷ divisor`. The assign modal shows the estimated cost before confirming and includes a "Gift — don't deduct points" checkbox for exceptions.

**2. Auction system.** Available at [`/party/auctions`](/party/auctions). The leader puts a warehouse item up for auction picking currency (DKP points or adena), starting bid, optional buy-now price and duration (15 min to 3 days). Members bid; each new bid frees the previous bidder (no escrow). When time runs out a cron closes the auction and sets the winner. The leader presses **Fulfill** to hand over the item and charge the winning bid.

The system allows negative DKP balance — the app doesn't block if the cost exceeds your balance, you just end up in debt.
MD;

        DB::table('changelog_entries')->updateOrInsert(
            ['type' => 'feature', 'version' => null, 'title_en' => 'DKP spend on assign + CP auctions'],
            [
                'audience' => 'web',
                'title_es' => 'Gasto DKP al asignar + subastas del CP',
                'title_en' => 'DKP spend on assign + CP auctions',
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
            ->where('title_en', 'DKP spend on assign + CP auctions')
            ->delete();
    }
};
