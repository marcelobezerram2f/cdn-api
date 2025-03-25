<?php

namespace App\Jobs;

use App\Repositories\DeprovisioningRepository;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class DeleteCdnRouteReturnJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $data;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($data)
    {
        $this->data = $data;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
         Log::info('Iniciando o JOB de retorno da exclusão da rota. dados :'. json_encode($this->data));
        $deprovisioning =  new DeprovisioningRepository();
        $deprovisioning->deleteCdnRouteReturn($this->data);
    }
}
