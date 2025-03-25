<?php

namespace App\Console\Commands;

use App\Repositories\ProvisioningRepository;
use Illuminate\Console\Command;

class VerifyCname extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cname:check';

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
        
        $provisioningRepository  = new ProvisioningRepository();
        $provisioningRepository->checkCname();

    }
}
