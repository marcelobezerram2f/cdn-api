<?php

namespace App\Jobs;

use App\Repositories\DeprovisioningRepository;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CheckCdnResourceReturnJob implements ShouldQueue
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
        $this->dada =  $data;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $deprovisioning =  new DeprovisioningRepository();
        $deprovisioning->checkCdnResourceReturn($this->data);
    }
}
