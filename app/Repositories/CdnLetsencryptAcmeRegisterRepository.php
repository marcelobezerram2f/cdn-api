<?php

namespace App\Repositories;


use App\Models\CdnLetsencryptAcmeRegister;
use Exception;
use App\Services\EventLogService;


class CdnLetsencryptAcmeRegisterRepository {


    private $cdnLetsencryptAcmeRegister;
    private $logSys;
    protected $facilityLog;

    public function __construct() {

        $this->cdnLetsencryptAcmeRegister =  new CdnLetsencryptAcmeRegister();
        $this->logSys = new EventLogService();
        $this->facilityLog = basename(__FILE__);
    }



    public function create($data, $cdnResouceId)
    {
        try {
            $hasCertificate = $this->cdnLetsencryptAcmeRegister->where('cdn_resource_id', $cdnResouceId)->first();
            if($hasCertificate) {
                $hasCertificate->published = null;
                $hasCertificate->certificate = null;
                $hasCertificate->private_key = null;
                $hasCertificate->intermediate_certificate = null;
                $hasCertificate->csr = null;
                if(!is_null($data['certificate'])) {
                    $hasCertificate->certificate = $data['certificate'];
                    $hasCertificate->private_key = $data['private_key'];
                } else {
                    $hasCertificate->username = $data['username'];
                    $hasCertificate->password = $data['password'];
                    $hasCertificate->fulldomain=$data['fulldomain'];
                    $hasCertificate->subdomain=$data['subdomain'];
                    $hasCertificate->company='lets_encrypt';
                }
                $hasCertificate->save();
                return $hasCertificate;
            } else {
                return $this->cdnLetsencryptAcmeRegister->create($data);
            }
        } catch (Exception $e) {
            $this->logSys->syslog(
                '[CDN-API | REGISTER_ACME] Ocorreu uma falha na persitência dos dados na tabela  cdn_letsencrypt_acme_register configuração de CDN',
                'ERRO : ' . $e->getMessage() . '. Parâmetros de recebidos: ' . json_encode($data),
                'ERROR',
                $this->facilityLog . ':' . basename(__FUNCTION__)
            );
            return ['errors' => $e->getMessage()];
        }

    }

    public function getByCdnResource($data)
    {
        return $this->cdnLetsencryptAcmeRegister->where('cdn_resource_id', $data)->get();
    }


}
