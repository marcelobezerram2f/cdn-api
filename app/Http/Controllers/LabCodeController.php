<?php

namespace App\Http\Controllers;

use App\Models\CdnLetsencryptAcmeRegister;
use App\Models\CdnResource;
use App\Models\LetsencryptCertificateReprocessing;
use App\Repositories\AcmeLetsEncrypt\AcmeDnsClientRepository;
use App\Repositories\AcmeLetsEncrypt\AcmeStorageRepository;
use App\Repositories\AcmeManager;
use App\Repositories\CdnOriginServerGroupRepository;
use App\Repositories\CdnResourcesRepository;
use App\Services\Acme\Client;
use App\Services\ZeroSslService;
use Exception;
use Illuminate\Http\Request;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use App\Services\SecurityService;
use Spatie\SslCertificate\SslCertificate;

class LabCodeController extends Controller
{



    public function index()
    {
        $update = new CdnResourcesRepository();
        $teste =  '{"request_code":"5be2a610-a6d9-4fca-8c3f-aec81e75933f","update_data":{"cdn_resource_hostname":"teste-resource06-dev.vcdn.net.br","cdn_origin_hostname":"media-server-01.vrealstream.com.br","cdn_origin_server_port":"443","cdn_origin_protocol":"https","cdn_ingest_point_id":1,"cdn_target_group_id":1,"cdn_template_id":2,"description":"TESTE SERVER GROUP","cdn_headers":[{"name":"content-Type","value":"application/Json"}],"cdn_origin_group_id":[]},"code":200}';
        $array = json_decode($teste, true);
        dd($update->updateReturn($array));
    }


    /**
     * Código de teste de consumo da API ACME sem LIB
     *
     */




}

