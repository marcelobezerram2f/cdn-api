<?php

namespace App\Console\Commands;

use App\Services\QueueSupervisorService;
use Illuminate\Console\Command;
use App\Traits\QueueTrait;


class WorkerQueue extends Command
{
    use QueueTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cdn:queue';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recovers JOB queue cdn_create_tenant for execution in convoy via ansible-playbook';

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
        \Log::info("Iniciando cron de leitura do  Exchange ". env('RABBITMQ_EXCHANGE_NAME'));
        $queue  = new QueueSupervisorService();
        $queue->getQueueJob('cdn_create_tenant_return');
        $queue->getQueueJob('cdn_create_cdn_resource_return');
        $queue->getQueueJob('cdn_create_cdn_new_template_return');
        $queue->getQueueJob('cdn_create_cdn_route_return');
        $queue->getQueueJob('cdn_create_cdn_ssl_cert_return');
        $queue->getQueueJob('cdn_block_cdn_resource_return');
        $queue->getQueueJob('cdn_unblock_cdn_resource_return');
        $queue->getQueueJob('cdn_update_cdn_resource_return');
        $queue->getQueueJob('cdn_delete_tenant_return');
        $queue->getQueueJob('cdn_check_cdn_resource_return');
        $queue->getQueueJob('cdn_delete_cdn_ssl_return');
        $queue->getQueueJob('cdn_delete_cdn_route_return');
        $queue->getQueueJob('cdn_delete_cdn_template_return');
        $queue->getQueueJob('cdn_delete_cdn_resource_return');


    }
}
