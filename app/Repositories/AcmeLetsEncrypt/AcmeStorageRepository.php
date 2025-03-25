<?php

namespace App\Repositories\AcmeLetsEncrypt;

use App\Models\CdnLetsencryptAcmeRegister;
use App\Services\EventLogService;
use App\Services\SecurityService;
use Exception;
use Log;

class AcmeStorageRepository
{
    private $cdnLetsencryptAcmeRegistries;
    private $acmeDnsClientRepository;
    private $logSys;
    protected $facilityLog;
    private $zeroSslService;

    public function __construct()
    {
        $this->acmeDnsClientRepository = new AcmeDnsClientRepository();
        $this->cdnLetsencryptAcmeRegistries = new CdnLetsencryptAcmeRegister();
        $this->logSys = new EventLogService();
        $this->facilityLog = basename(__FILE__);
    }


    public function certGeneration($cdnResouce)
    {
        try {
            $security = new SecurityService();

            $letsencryptAcmeRegistries = $this->cdnLetsencryptAcmeRegistries->find($cdnResouce->cdn_acme_lets_encrypt_id);
            $account = [
                "username" => $letsencryptAcmeRegistries->username,
                "password" => $letsencryptAcmeRegistries->password,
                "fulldomain" => $letsencryptAcmeRegistries->fulldomain,
                "subdomain" => $letsencryptAcmeRegistries->subdomain,
                "allowfrom" => []
            ];
            $certificate = $this->acmeDnsClientRepository->requestCertificate($account, $cdnResouce->cdn_resource_hostname);
            Log::info("geração do certificado SSL Letsencrypt : " . json_encode($certificate));
            if (!isset($certificate['code'])) {
                $letsencryptAcmeRegistries->certificate = $security->dataEncrypt($certificate['certificate']);
                $letsencryptAcmeRegistries->private_key = $security->dataEncrypt($certificate['private_key']);
                $letsencryptAcmeRegistries->intermediate_certificate = $security->dataEncrypt($certificate['intermediate_certificate']);
                $letsencryptAcmeRegistries->csr = $security->dataEncrypt($certificate['csr']);
                $letsencryptAcmeRegistries->fullchain = $security->dataEncrypt($certificate['fullchain']);
                $letsencryptAcmeRegistries->certificate_created = $certificate['certificate_created'];
                $letsencryptAcmeRegistries->certificate_expires = $certificate['certificate_expires'];
                $letsencryptAcmeRegistries->save();
                $this->logSys->syslog(
                    '[CDN-API | ACME-LETSENCRYPT] Certificado ACME-LETSENCRYPT do domínio ' . $cdnResouce->cdn_resource_hostname . ' persistido com sucesso!',
                    null,
                    'INFO',
                    $this->facilityLog . ':' . basename(__FUNCTION__)
                );
                return ['code' => 200];
            } else {
                $this->logSys->syslog(
                    '[CDN-API | ACME-LETSENCRYPT] Ocorreu um erro na geração Certificado ACME-LETSENCRYPT do domínio ' . $cdnResouce->cdn_resource_hostname ,
                    'Message ACME :' . json_encode($certificate) . " Parâmetros : " . json_encode($account),
                    'INFO',
                    $this->facilityLog . ':' . basename(__FUNCTION__)
                );
                return ['code' => 400, 'message' => $certificate['message']];
            }

        } catch (Exception $e) {
            Log::error("ERRO PERSITÊNCIA CERT : " . $e->getMessage() . " TRACE : " . $e->getTraceAsString());
            $this->logSys->syslog(
                '[CDN-API | ACME-LETSENCRYPT] Falha fatal na gareção do certificado ACME-LETSENCRYPT do domínio ' . $cdnResouce->cdn_resource_hostname,
                ' Ocorreu um erro na persitência do certificado ACME-LETSENCRYPT  - DADOS' . $cdnResouce->cdn_resource_hostname . 'ERROR :' . $e->getMessage(),
                'ERROR',
                $this->facilityLog . ':' . basename(__FUNCTION__)
            );
            return ['code' => 400, 'message' => "Fatal failure when generating Let's Encrypt certificate. Contact Support."];
        }
    }


}
