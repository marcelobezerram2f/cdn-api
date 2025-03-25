<?php

namespace App\Http\Controllers;

use App\Models\CdnLetsencryptAcmeRegister;
use App\Models\CdnResource;
use App\Repositories\AcmeLetsEncrypt\AcmeDnsClientRepository;
use App\Repositories\AcmeLetsEncrypt\AcmeStorageRepository;
use App\Repositories\AcmeLetsEncrypt\StorageRepository;
use App\Repositories\CertificateManagerRepository;
use App\Repositories\ProvisioningRepository;
use App\Services\SecurityService;
use Illuminate\Http\Request;

use Rogierw\RwAcme\Api;
use Rogierw\RwAcme\Http\Client;
use Spatie\SslCertificate\SslCertificate;

class AcmeLetsEncryptController extends Controller
{


    public function register()
    {
        $acme = new AcmeDnsClientRepository();
        return $acme->RegisterAccount();
    }


    public function generateCert()
    {

        $cert =  CdnLetsencryptAcmeRegister::find(99);
        $sec =  new SecurityService();

       // dd("cert -> ". $sec->dataDecrypt($cert->certificate),"key -> ". $sec->dataDecrypt($cert->private_key) );

       dd(CNAMEValidate('_acme-challenge.m2fsolucoes.com', '3460c027-0f94-4af3-9b76-7c11d837ec84.auth-dns.vcdn.net.br'));


        $account = [
            "username" => "adcbe7b7-daee-44e2-9c25-a55c02b8b804",
            "password" => "QdanPHowxINGUfvM8WSFjcwKDZTUZhdgALKmjA_r",
            "fulldomain" => "80eb0c49-f98b-41bb-8ce1-b4323cc0ce51.auth-dns.vcdn.net.br",
            "subdomain" => "80eb0c49-f98b-41bb-8ce1-b4323cc0ce51",
            "allowfrom" => []
        ];


        /*$requester = new AcmeDnsClientRepository();
        $requester->requestCertificate($account);*/

        //$resource = CdnResource::where('request_code', '2f9eda56-f669-48a8-9679-c5b1e9c5dc32')->first();

        $certificate = SslCertificate::createForHostName('teste-resource01-dev.vcdn.net.br');
        dd($certificate);


    }
}
