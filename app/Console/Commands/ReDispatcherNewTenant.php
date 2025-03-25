<?php

namespace App\Console\Commands;

use App\Models\CdnTenant;
use App\Repositories\ProvisioningRepository;
use App\Services\QueueSupervisorService;
use Illuminate\Console\Command;
use App\Traits\QueueTrait;

class ReDispatcherNewTenant extends Command
{

    use QueueTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dipatcher:worker';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    public function __construct()
    {
       parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $tenants = CdnTenant::where('queued', false)->get();
        $createTenant =  new ProvisioningRepository();
        foreach($tenants as $tenant){
            $createTenant->createTenant(['tenant_id'=>$tenant->id, 'tenant'=>$tenant->tenant]);
        }
        $queue  = new QueueSupervisorService();
        $queue->getQueueJob('cdn_create_tenant_return');

    }
}
