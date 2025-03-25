<?php

namespace App\Jobs;

use App\Repositories\CertificateManagerRepository;
use App\Repositories\ProvisioningRepository;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\ProviderRepository;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CreateCdnSSLCertReturnJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */

     private $data;
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
        \Log::info('Job de retorno de instalção do certificado SSL iniciado ' . json_encode($this->data));
        $provisioningRepository = new CertificateManagerRepository();
        $provisioningRepository->cdnInstallSSLCertReturn($this->data);
    }
}
