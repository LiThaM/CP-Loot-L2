<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('changelog_entries')->insert([
            'type'       => 'feature',
            'audience'   => 'web',
            'title_es'   => 'Craft en lote — elige cuántos craftear de un golpe',
            'title_en'   => 'Batch crafting — pick how many to craft at once',
            'title_it'   => 'Craft in serie — scegli quanti craftare in una volta',
            'title_ru'   => 'Массовый крафт — выберите количество за один раз',
            'body_es'    => "Al craftear desde el warehouse ya no tienes que pulsar el botón 200 veces. El modal de confirmación incluye ahora un selector de **cantidad** (1 – 999).\n\n**Máximo automático** — el sistema calcula cuántas unidades puedes craftear con el stock actual y pre-rellena el input con ese número. Aparece el hint «max N» junto al label y un botón **Max** para volver al máximo con un click.\n\n**Bloqueo si te pasas** — si introduces una cantidad mayor a la que el warehouse puede cubrir, el input se resalta en rojo, aparece un aviso y el botón Confirmar queda deshabilitado hasta que ajustes la cantidad.\n\n**Todo en una transacción** — el backend consume los materiales × cantidad y produce el output × cantidad en un único bloque atómico. Los sub-crafteos automáticos también se escalan.",
            'body_en'    => "When crafting from the warehouse you no longer need to click the button 200 times. The confirmation modal now includes a **quantity** picker (1 – 999).\n\n**Auto-max** — the system calculates how many units you can craft with current stock and pre-fills the input with that number. A «max N» hint and a **Max** button let you snap back to the maximum with one click.\n\n**Blocked if you exceed the max** — if you enter a quantity beyond what the warehouse can cover, the input turns red, a warning appears, and the Confirm button is disabled until you adjust.\n\n**Single atomic transaction** — the backend consumes materials × quantity and produces output × quantity in one atomic block. Auto-sub-crafted materials also scale accordingly.",
            'body_it'    => "Quando crafti dal warehouse non devi più cliccare il pulsante 200 volte. Il modal di conferma include ora un selettore di **quantità** (1 – 999).\n\n**Massimo automatico** — il sistema calcola quante unità puoi craftare con lo stock attuale e pre-compila il campo. Appare il suggerimento «max N» e un pulsante **Max** per tornare al massimo con un click.\n\n**Bloccato se superi il massimo** — se inserisci una quantità superiore a quella coperta dal warehouse, il campo diventa rosso, compare un avviso e il pulsante Conferma è disabilitato.\n\n**Transazione atomica** — il backend consuma materiali × quantità e produce output × quantità in un unico blocco atomico.",
            'body_ru'    => "При крафте из склада больше не нужно нажимать кнопку 200 раз. В окне подтверждения теперь есть **выбор количества** (1 – 999).\n\n**Автомаксимум** — система рассчитывает, сколько единиц можно скрафтить при текущих запасах, и подставляет это число автоматически. Рядом с полем отображается подсказка «max N» и кнопка **Max** для возврата к максимуму в один клик.\n\n**Блокировка при превышении** — если введённое количество превышает доступные ресурсы, поле выделяется красным, появляется предупреждение, а кнопка «Подтвердить» блокируется.\n\n**Единая транзакция** — бэкенд списывает материалы × количество и добавляет результат × количество атомарно.",
            'published_at' => now(),
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('changelog_entries')->where('title_es', 'Craft en lote — elige cuántos craftear de un golpe')->delete();
    }
};
