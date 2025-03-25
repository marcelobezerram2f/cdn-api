<?php

namespace App\Console\Commands;

use App\Repositories\DeprovisioningRepository;
use Illuminate\Console\Command;

class StartDeleteResource extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cdn-resource:delete';

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
        $deprovisioning = new DeprovisioningRepository();
        $deprovisioning->CheckCdnResource();

    }
}
