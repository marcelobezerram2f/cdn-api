<?php

namespace App\Console\Commands;

use App\Http\Controllers\LabCodeController;
use Illuminate\Console\Command;

class LabCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lab:command';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
            $labcode = new LabCodeController();
            $labcode->index();

    }
}
