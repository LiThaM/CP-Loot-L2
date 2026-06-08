<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $translations = [
        // Nav
        'nav.cp.auctions' => ['es' => 'Subastas', 'en' => 'Auctions'],
        'nav.cp.auctions_hint' => ['es' => 'Subasta items del CP por DKP o adena', 'en' => 'Auction CP items for DKP or adena'],

        // Warehouse assign — DKP cost preview
        'warehouse.assign.dkp_cost.title' => ['es' => 'Coste DKP estimado', 'en' => 'Estimated DKP cost'],
        'warehouse.assign.dkp_cost.points' => ['es' => 'pts', 'en' => 'pts'],
        'warehouse.assign.dkp_cost.skipped' => ['es' => 'Regalo — no se descontarán puntos', 'en' => 'Gift — no points will be deducted'],
        'warehouse.assign.dkp_cost.hint' => [
            'es' => 'Calculado como precio_estimado × cantidad ÷ divisor del CP.',
            'en' => 'Computed as estimated_price × amount ÷ CP divisor.',
        ],
        'warehouse.assign.dkp_cost.skip_label' => [
            'es' => 'No descontar puntos (regalo)',
            'en' => "Don't deduct points (gift)",
        ],

        // Auctions page
        'auction.page.title' => ['es' => 'Subastas del CP', 'en' => 'CP Auctions'],
        'auction.kicker' => ['es' => 'Subastas', 'en' => 'Auctions'],
        'auction.my_available' => ['es' => 'Tu disponible', 'en' => 'Your available'],
        'auction.empty.active' => ['es' => 'No hay subastas activas.', 'en' => 'No active auctions.'],
        'auction.empty.closed' => ['es' => 'No hay subastas pendientes de entrega.', 'en' => 'No auctions pending fulfillment.'],
        'auction.empty.finished' => ['es' => 'Sin historial de subastas.', 'en' => 'No auction history.'],
        'auction.tab.active' => ['es' => 'Activas', 'en' => 'Active'],
        'auction.tab.closed' => ['es' => 'Cerradas (a entregar)', 'en' => 'Closed (pending fulfill)'],
        'auction.tab.finished' => ['es' => 'Histórico', 'en' => 'History'],

        'auction.status.open' => ['es' => 'Abierta', 'en' => 'Open'],
        'auction.status.closed' => ['es' => 'Cerrada', 'en' => 'Closed'],
        'auction.status.fulfilled' => ['es' => 'Entregada', 'en' => 'Fulfilled'],
        'auction.status.cancelled' => ['es' => 'Cancelada', 'en' => 'Cancelled'],

        'auction.starting_at' => ['es' => 'Inicial', 'en' => 'Starting'],
        'auction.buy_now' => ['es' => 'Buy now', 'en' => 'Buy now'],
        'auction.ends_in' => ['es' => 'Termina en', 'en' => 'Ends in'],
        'auction.expired' => ['es' => 'Expirada', 'en' => 'Expired'],
        'auction.current_bid' => ['es' => 'Puja actual', 'en' => 'Current bid'],
        'auction.winner' => ['es' => 'Ganador', 'en' => 'Winner'],

        // Open modal
        'auction.open.cta' => ['es' => 'Abrir subasta', 'en' => 'Open auction'],
        'auction.open.title' => ['es' => 'Nueva subasta', 'en' => 'New auction'],
        'auction.open.item' => ['es' => 'Item', 'en' => 'Item'],
        'auction.open.amount' => ['es' => 'Cantidad', 'en' => 'Amount'],
        'auction.open.currency' => ['es' => 'Moneda', 'en' => 'Currency'],
        'auction.open.starting_bid' => ['es' => 'Puja inicial', 'en' => 'Starting bid'],
        'auction.open.buy_now' => ['es' => 'Buy now (opcional)', 'en' => 'Buy now (optional)'],
        'auction.open.duration' => ['es' => 'Duración', 'en' => 'Duration'],
        'auction.open.success' => ['es' => 'Subasta abierta', 'en' => 'Auction opened'],

        // Bid modal
        'auction.bid.cta' => ['es' => 'Pujar', 'en' => 'Bid'],
        'auction.bid.title' => ['es' => 'Pujar', 'en' => 'Place a bid'],
        'auction.bid.min' => ['es' => 'Mínimo', 'en' => 'Minimum'],
        'auction.bid.confirm' => ['es' => 'Pujar', 'en' => 'Bid'],
        'auction.bid.placed' => ['es' => 'Puja registrada', 'en' => 'Bid placed'],

        // Fulfill / cancel
        'auction.fulfill.cta' => ['es' => 'Entregar', 'en' => 'Fulfill'],
        'auction.fulfill.confirm_title' => ['es' => '¿Entregar el item al ganador?', 'en' => 'Fulfill the auction?'],
        'auction.fulfill.confirm_text' => [
            'es' => 'Se asignará el item al ganador y se descontará su puja.',
            'en' => 'The winner receives the item and their winning bid is deducted.',
        ],
        'auction.cancel.cta' => ['es' => 'Cancelar', 'en' => 'Cancel'],
        'auction.cancel.confirm_title' => ['es' => '¿Cancelar la subasta?', 'en' => 'Cancel the auction?'],
        'auction.cancel.confirm_text' => [
            'es' => 'El item volverá al almacén. No se puede deshacer.',
            'en' => 'The item is returned to the warehouse. This cannot be undone.',
        ],
    ];

    public function up(): void
    {
        $now = now();
        foreach ($this->translations as $key => $langs) {
            foreach ($langs as $lang => $value) {
                DB::table('translations')->updateOrInsert(
                    ['language' => $lang, 'key' => $key],
                    ['value' => $value, 'updated_at' => $now, 'created_at' => $now],
                );
            }
        }
    }

    public function down(): void
    {
        DB::table('translations')
            ->whereIn('key', array_keys($this->translations))
            ->whereIn('language', ['es', 'en'])
            ->delete();
    }
};
