<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class FetchItemsCommand extends Command
{
    protected $signature = 'app:fetch-items-command';

    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        return self::SUCCESS;
    }
}
