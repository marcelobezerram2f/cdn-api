<?php

namespace App\Console\Commands;

use App\Models\CdnLetsencryptAcmeRegister;
use App\Models\CdnResource;
use App\Repositories\CertificateManagerRepository;
use Illuminate\Console\Command;

class InstallSSLCerificate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ssl:install';

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
        $certificateManagerRepository  = new CertificateManagerRepository();
        $certificates  = CdnLetsencryptAcmeRegister::whereNull("published")->get();
        foreach($certificates as $certificate) {
            if($certificate->company == 'lets_encrypt') {
                $this->installLetsEncryptSSL();
            } else {
                $resource = CdnResource::find($certificate->cdn_resource_id);
                $data = ["request_code" =>$resource->request_code, "cdn_resource_hostname" =>$resource->cdn_resource_hostname];
                $certificateManagerRepository->cdnInstallSSLCert($data);
            }
        }
    }


    public function installLetsEncryptSSL()
    {
        $resources = CdnResource::whereNull('cname_ssl_verify')->whereNotNull('cdn_acme_lets_encrypt_id')->whereNotNull('provisioned')->get();
        $certificateManagerRepository  = new CertificateManagerRepository();
        foreach($resources as $resource) {
            if ($resource->provisioned == true) {
                $check =  $certificateManagerRepository->checkSsl($resource);
                if($check==true) {
                    $data = ["request_code" =>$resource->request_code, "cdn_resource_hostname" =>$resource->cdn_resource_hostname];
                    $certificateManagerRepository->cdnInstallSSLCert($data);
                }
            }
        }
    }
}
