<?php

namespace App\Repositories;

use App\Jobs\CreateCdnSSLCertJob;
use App\Jobs\DeleteCdnSSLCertJob;
use App\Models\CdnLetsencryptAcmeRegister;
use App\Models\CdnResource;
use App\Repositories\AcmeLetsEncrypt\AcmeDnsClientRepository;
use App\Repositories\AcmeLetsEncrypt\AcmeStorageRepository;
use App\Services\EventLogService;
use App\Services\SecurityService;
use Exception;
use Spatie\SslCertificate\SslCertificate;


class CertificateManagerRepository
{

    private $storageCertRepository;
    private $cdnLetsencryptAcmeRegister;
    private $acmeDnsClientRepository;
    private $acmeStorageRepository;
    private $cdnResource;
    private $logSys;
    private $facilityLog;
    private $provisioingRepository;

    public function __construct()
    {
        $this->storageCertRepository = new StorageCertRepository();
        $this->cdnLetsencryptAcmeRegister = new CdnLetsencryptAcmeRegister();
        $this->acmeDnsClientRepository = new AcmeDnsClientRepository();
        $this->acmeStorageRepository = new AcmeStorageRepository();
        $this->cdnResource = new CdnResource();
        $this->logSys = new EventLogService();
        $this->facilityLog = basename(__FILE__);
        $this->provisioingRepository = new ProvisioningRepository();

    }

    /**
     * Método responsável por criar certificado Let's Encrypt ou certificado customizado
     *
     * @param array $data
     *
     * @return array
     */

    public function create($data)
    {
        try {
            $sslStepStatus = new StepProvisioningRepository();

            $resource = $this->cdnResource->where("request_code", $data['request_code'])->first();
            $hasCertificate = $this->cdnLetsencryptAcmeRegister->where("cdn_resource_id", $resource->id)->get();
            if ($hasCertificate) {
                return [
                    "code" => 400,
                    "message" => "Warning! You already have a valid certificate registered, contact support",
                    "error" => ["certificate already registered"]
                ];
            } else {
                if (is_null($data['certificate'])) {
                    if (CAACheck($resource->cdn_resource_hostname) == false) {
                        $this->logSys->syslog(
                            "[CDN-API | CERTIFICATE-MANAGER - CREATE] DNS do domínio $resource->cdn_resource_hostname possui restrição do tipo CAA para Let's Encrypt!",
                            null,
                            'ALERT',
                            $this->facilityLog . ':' . basename(__FUNCTION__)
                        );
                        $sslStepStatus->updateStep($resource->id, 6, 'failed', "DNS is restricted by the Let's Encrypt Certificate Authority (CA)");
                        return [
                            'code' => 400,
                            'message' => "Warning! Your DNS is restricted by the Let's Encrypt Certificate Authority (CA).  Please include the CAA entry below in your DNS.",
                            'errors' => ['CAA   0   issue  "letsencrypt.org"'],
                        ];
                    } else {
                        $register = $this->cdnLetsencryptAcmeRegister->RegisterAccount();
                        if (isset($register['code'])) {
                            $this->logSys->syslog(
                                "[CDN-API | CERTIFICATE-MANAGER - CREATE] Ocorreu uma falha no registro de certificado Let's Encrypt no serviço ACME!",
                                "ERROR : " . json_encode($register) . "Domínio : $resource->cdn_resource_hostname",
                                'ALERT',
                                $this->facilityLog . ':' . basename(__FUNCTION__)
                            );
                            $sslStepStatus->updateStep($resource->id, 8, 'failed', "There was an error registering the Let's Encrypty certificate");

                            return [
                                "code" => 400,
                                "message" => "Warning! There was an error registering the Let's Encrypty certificate, please try again later.",
                                "error" => ["failure to register the certificate with the ACME service."]
                            ];

                        } else {
                            $register['cdn_resource_id'] = $resource->id;
                            $newRegister = $this->cdnLetsencryptAcmeRegister->create($register);
                            $resource->cdn_acme_lets_encrypt_id = $newRegister->id;
                            $resource->cname_ssl_verify = null;
                            $resource->save();
                            $this->logSys->syslog(
                                "[CDN-API | CERTIFICATE-MANAGER - CREATE] Registro de certificado Let's Encrypt no serviço ACME efetuado com sucesso!",
                                "Data: " . $resource->cdn_resource_hostname,
                                'INFO',
                                $this->facilityLog . ':' . basename(__FUNCTION__)
                            );
                            $sslStepStatus->updateStep($resource->id, 8, 'waiting', "Waiting for SSL certificate Let's Encrypt installation");

                            return [
                                'code' => 200,
                                "message" => "SSL certificate successfully registered, configure your DNS and wait for the certificate to be installed on cdn resource $resource->cdn_resource_hostname."
                            ];
                        }
                    }
                } else {
                    $storageCertRepository = new StorageCertRepository();
                    $custonCertificate = $storageCertRepository->saveCustonCertificate($data, $data['cdn_resource_hostname'], $resource->id);
                    if ($custonCertificate['code'] == 400) {
                        $this->logSys->syslog(
                            "[CDN-API | CERTIFICATE-MANAGER - CREATE] Ocorreu um erro no tratamento ou persistência de certficado customizado do resource $resource->cdn_resource_hostname",
                            "ERRO :" . json_encode($custonCertificate),
                            'ALERT',
                            $this->facilityLog . ':' . basename(__FUNCTION__)
                        );
                        $sslStepStatus->updateStep($resource->id, 9, 'failed', "Faults in the rules for entering personalized certificates.");

                        return [
                            'code' => 400,
                            'message' => "Warning! We've found flaws in the custom certificate entry rules. Check them out!.",
                            'errors' => $custonCertificate['errors'],
                        ];
                    } else {
                        $resource->cdn_acme_lets_encrypt_id = $custonCertificate['id'];
                        $resource->cname_ssl_verify = null;
                        $resource->save();
                        $this->cdnInstallSSLCert(['request_code' => $resource->request_code, 'cdn_resource_hostname'=>$resource->cdn_resource_hostname]);
                        $this->logSys->syslog(
                            "[CDN-API | CERTIFICATE-MANAGER - CREATE] Tratamento e persistência de certficado customizado do resource $resource->cdn_resource_hostname, efetuado com sucesso.",
                            null,
                            'INFO',
                            $this->facilityLog . ':' . basename(__FUNCTION__)
                        );
                        $sslStepStatus->updateStep($resource->id, 2, 'waiting', 'Waiting for SSL certificate custon installation');
                        return ['code' => 200, "message" => "SSL certificate successfully included, wait for installation on cdn resource $resource->cdn_resource_hostname."];
                    }
                }
            }
        } catch (Exception $e) {
            $sslStepStatus->updateStep($resource->id, 2, 'failed', "Fatal failure in the SSL certificate installation request.");
            $this->logSys->syslog(
                '[CDN-API | CERTIFICATE-MANAGER - CREATE] Ocorreu uma exceção na criação do certificado SSL !',
                "DADOS :" . json_encode($data) . "ERROR : " . $e->getMessage(),
                'INFO',
                $this->facilityLog . ':' . basename(__FUNCTION__)
            );
            return [
                'code' => 400,
                'message' => "Fatal failure in the SSL certificate installation request. Contact support.",
                'errors' => $e->getMessage(),
            ];
        }
    }

    /**
     * Método responsável por efetuar update de certificado Let's Encrypt ou certificado customizado
     *
     * @param array $data
     *
     * @return array
     */
    public function update($data)
    {
        $sslStepStatus = new StepProvisioningRepository();
        $security = new SecurityService();

        try {
            $resource = $this->cdnResource->where("request_code", $data['request_code'])->first();
            $hasCertificate = $this->cdnLetsencryptAcmeRegister->where("cdn_resource_id", $resource->id)->first();
            if (!$hasCertificate) {
                return $this->create($data);
            } else {
                if (is_null($data['certificate'] && $hasCertificate->company != "lets_encrypt")) {
                    if (CAACheck($resource->cdn_resource_hostname) == false) {
                        $this->logSys->syslog(
                            "[CDN-API | CERTIFICATE-MANAGER - UPDATE] DNS do domínio $resource->cdn_resource_hostname possui restrição do tipo CAA para Let's Encrypt!",
                            null,
                            'ALERT',
                            $this->facilityLog . ':' . basename(__FUNCTION__)
                        );
                        return [
                            'code' => 400,
                            'message' => "Warning! Your DNS is restricted by the Let's Encrypt Certificate Authority (CA).  Please include the CAA entry below in your DNS.",
                            'errors' => ['CAA   0   issue  "letsencrypt.org"'],
                        ];
                    } else {
                        $register = $this->cdnLetsencryptAcmeRegister->RegisterAccount();
                        if (isset($register['code'])) {
                            $this->logSys->syslog(
                                "[CDN-API | CERTIFICATE-MANAGER - UPDATE] Ocorreu uma falha no registro de certificado Let's Encrypt no serviço ACME!",
                                "ERROR : " . json_encode($register) . "Domínio : $resource->cdn_resource_hostname",
                                'ALERT',
                                $this->facilityLog . ':' . basename(__FUNCTION__)
                            );
                            return [
                                "code" => 400,
                                "message" => "Warning! There was an error registering the Let's Encrypty certificate, please try again later.",
                                "error" => ["failure to register the certificate with the ACME service."]
                            ];
                        } else {
                            $hasCertificate->username = $register["username"];
                            $hasCertificate->password = $register["password"];
                            $hasCertificate->fulldomain = $register["fulldomain"];
                            $hasCertificate->subdomain = $register["subdomain"];
                            $hasCertificate->company = "lets_encrypt";
                            $hasCertificate->save();
                            $resource->cname_ssl_verify = null;
                            $resource->save();
                            return [
                                'code' => 200,
                                "message" => "SSL certificate successfully registered, configure your DNS and wait for the certificate to be installed on cdn resource $resource->cdn_resource_hostname."
                            ];
                        }
                    }
                } else {
                    $updateCertificate = $this->storageCertRepository->updateCustonCertificate($data, $resource->cdn_resource_hostname, $resource->id);
                    if (isset($updateCertificate['errors'])) {
                        $this->logSys->syslog(
                            "[CDN-API | CERTIFICATE-MANAGER - UPDATE] Ocorreu um erro no tratamento de certficado customizado do resource $resource->cdn_resource_hostname",
                            "ERRO :" . json_encode($updateCertificate),
                            'ALERT',
                            $this->facilityLog . ':' . basename(__FUNCTION__)
                        );
                        return [
                            'code' => 400,
                            'message' => "Warning! We've found flaws in the custom certificate entry rules. Check them out!.",
                            'errors' => $updateCertificate['errors'],
                        ];
                    } else {
                        $hasCertificate->company = $updateCertificate['company'];
                        $hasCertificate->certificate = $security->dataEncrypt($updateCertificate['certificate']);
                        $hasCertificate->private_key = $security->dataEncrypt($updateCertificate['private_key']);
                        $hasCertificate->intermediate_certificate = null;
                        $hasCertificate->csr = null;
                        $hasCertificate->certificate_created = $updateCertificate['certificate_created'];
                        $hasCertificate->certificate_expires = $updateCertificate['certificate_expires'];
                        $hasCertificate->save();
                        $resource->cname_ssl_verify = null;
                        $resource->save();
                        $this->logSys->syslog(
                            "[CDN-API | CERTIFICATE-MANAGER - UPDATE] Tratamento e persistência de certficado customizado do resource $resource->cdn_resource_hostname, efetuado com sucesso.",
                            null,
                            'INFO',
                            $this->facilityLog . ':' . basename(__FUNCTION__)
                        );
                        $this->cdnInstallSSLCert(['request_code' => $resource->request_code, 'cdn_resource_hostname'=>$resource->cdn_resource_hostname]);
                        return ['code' => 200, "message" => "SSL certificate successfully updated, wait for installation on cdn resource $resource->cdn_resource_hostname."];
                    }
                }
            }

        } catch (Exception $e) {
            $this->logSys->syslog(
                '[CDN-API | CERTIFICATE-MANAGER - UPDATE] Ocorreu um exceção no update de certiffiado SSL !',
                "ERROR : " . $e->getMessage(),
                'ERROR',
                $this->facilityLog . ':' . basename(__FUNCTION__)
            );
            return [
                'code' => 400,
                'message' => "SSL certificate update request fails. Contact support.",
                'errors' => $e->getMessage(),
            ];
        }
    }



    public function checkSsl($cdnResource)
    {
        $sslStepStatus = new StepProvisioningRepository();

        if (!is_null($cdnResource->cdn_acme_lets_encrypt_id)) {
            $c = 0;
            $ssl = CdnLetsencryptAcmeRegister::find($cdnResource->cdn_acme_lets_encrypt_id);
            if ($ssl->company == "lets_encrypt" && is_null($ssl->published)) {
                $checkSsl = CNAMEValidate("_acme-challenge." . $cdnResource->cdn_resource_hostname, $ssl->fulldomain);
                if ($checkSsl == true) {
                    $cdnResource->cname_ssl_verify = true;
                    $cdnResource->save();
                    $sslStepStatus->updateStep($cdnResource->id, 7, 'finished', "Let's Encrypt CNAME entry successfully validated");
                    $getCertificate = $this->acmeStorageRepository->certGeneration($cdnResource);
                    if ($getCertificate['code'] == 400) {
                        $sslStepStatus->updateStep($cdnResource->id, 8, 'failed', $getCertificate['message']);
                        $c++;
                    } else {
                        rmCertFolder(base_path()."/".".acme/".$cdnResource->cdn_resource_hostname."_ecc");
                        $sslStepStatus->updateStep($cdnResource->id, 8, 'waiting', "SSL ACME Let's Encrypt , wait for the installation in the cdn retource that will take place at the end of the provisioning process");
                    }
                } else {
                    $sslStepStatus->updateStep($cdnResource->id, 7, 'failed', "Let's Encrypt SSL CNAME entry not found , please check the cname delivery in your DNS.");
                    $c++;
                }
            }
            return $c == 0 ? true : false;
        }
    }

    public function cdnInstallSSLCert($data, $updateResource = null)
    {
        $sslStepStatus = new StepProvisioningRepository();
        $security =  new SecurityService();

        try {
            $cdnResources = $this->cdnResource->where('request_code', $data['request_code'])->with('tenant', 'letsEncryptAcmeRegister')->get();
            foreach ($cdnResources as $cdnResource) {
                $sslData = [
                    'request_code' => $cdnResource->request_code,
                    'cdn_resource_hostname' => $cdnResource->cdn_resource_hostname,
                    'tenant' => $cdnResource->tenant->tenant,
                    'certificate' => $security->dataDecrypt($cdnResource->letsEncryptAcmeRegister->fullchain),
                    'private_key' => $security->dataDecrypt($cdnResource->letsEncryptAcmeRegister->private_key)
                ];
                try {
                    CreateCdnSSLCertJob::dispatch($sslData)->onQueue('cdn_create_cdn_ssl_cert');
                    $sslStepStatus->updateStep($cdnResource->id, 2, 'waiting', 'Installing the SSL certificate');
                    $this->logSys->syslog(
                        '[CDN-API | Provisioning CDN]  Instalação do certificado SSL do resource.' . $data['cdn_resource_hostname'] . ' executando em fila!',
                        null,
                        'INFO',
                        $this->facilityLog . ':' . basename(__FUNCTION__)
                    );
                } catch (Exception $e) {

                    $this->logSys->syslog(
                        '[CDN-API | Provisioning CDN] Ocorreu exceção na inclusão da instalação do certificado SSL na fila de criação',
                        'ERRO : ' . $e->getMessage() . '. Parâmetros de recebidos: ' . json_encode($data) . ' JOB: DeleteCdnTanantJob',
                        'ERROR',
                        $this->facilityLog . ':' . basename(__FUNCTION__)
                    );

                }
            }
        } catch (Exception $e) {
            $this->logSys->syslog(
                '[CDN-API | Provisioning CDN] Ocorreu exceção na inclusão da instalação do certificado SSL do resource.',
                'ERRO : ' . $e->getMessage() . '. Parâmetros de recebidos: ' . json_encode($data) . ' JOB: CreateCdnSSLCertJob',
                'ERROR',
                $this->facilityLog . ':' . basename(__FUNCTION__)
            );
        }
    }

    public function cdnInstallSSLCertReturn($data)
    {
        $sslStepStatus = new StepProvisioningRepository();

        try {
            $this->logSys->syslog(
                '[CDN-API | SSL INSTALATION] Retorno do processamento e cópia do certificado SSL ' . $data['cdn_resource_hostname'],
                "Dados de retorno do processamento de processamento e cópia do certificado SSL . : " . json_encode($data),
                'INFO',
                $this->facilityLog . ':' . basename(__FUNCTION__)
            );
            $resources = $this->cdnResource->where('request_code', $data['request_code'])->with('letsEncryptAcmeRegister')->get();
            if ($data['code'] == 200) {

                $certificate = SslCertificate::createForHostName($data['cdn_resource_hostname']);
                if ($certificate->isValid()) {
                    $sslCompanny = $certificate->getOrganization();
                    foreach ($resources as $resource) {
                        $letsEncryptAcmeRegister = $this->cdnLetsencryptAcmeRegister->where('cdn_resource_id', $resource->id)->first();
                        $letsEncryptAcmeRegister->published = 1;
                        $letsEncryptAcmeRegister->save();
                    }
                    $sslStepStatus->updateStep($resource->id, 8, 'finished', "SSL $sslCompanny, successfully installed");
                    $this->logSys->syslog(
                        '[CDN-API | SSL INSTALATION]  Certificado SSL do resource ' . $data['cdn_resource_hostname'] . ' instalado com sucesso!',
                        null,
                        'INFO',
                        $this->facilityLog . ':' . basename(__FUNCTION__)
                    );
                } else {
                    foreach ($resources as $resource) {
                        if ($resource->letsEncryptAcmeRegister->publication_attempts < env('NUMBER_QUEUE_ATTEMPTS')) {
                            $resource->letsEncryptAcmeRegister->publication_attempts = $resource->letsEncryptAcmeRegister->publication_attempts + 1;
                            $resource->letsEncryptAcmeRegister->save();
                            $sslStepStatus->updateStep($resource->id, 8, 'waiting', 'SSL certificate not valid, wait for new installation.');
                            $this->logSys->syslog(
                                '[CDN-API | SSL INSTALATION]  Falha na validação do certificado SSL do resource.' . $data['cdn_resource_hostname'] . ' NOVA TENTANTIVA em execução !',
                                "Validação do  certificado SSL do hostname " . $data['cdn_resource_hostname'] . "falhou,  " . $resource->letsEncryptAcmeRegister->publication_attempts . " em execução. Retorno de Falha : " . json_encode($data),
                                'WARNING',
                                $this->facilityLog . ':' . basename(__FUNCTION__)
                            );
                            $this->cdnInstallSSLCert($data);
                        } else {
                            $sslStepStatus->updateStep($resource->id, 8, 'failed', 'SSL certificate not valid or not installed, retries exceeded. Contact support.');
                            $this->logSys->syslog(
                                '[CDN-API | SSL INSTALATION]  Falha na validação do certificado SSL do resource.' . $data['cdn_resource_hostname'] . ' tentativas excedidas!',
                                "Validação do  certificado SSL do hostname " . $data['cdn_resource_hostname'] . "falhou,  " . $resource->letsEncryptAcmeRegister->publication_attempts . " foram efetuadas",
                                'INFO',
                                $this->facilityLog . ':' . basename(__FUNCTION__)
                            );
                        }
                    }
                }
            } else {
                foreach ($resources as $resource) {
                    if ($resource->letsEncryptAcmeRegister->publication_attempts < env('NUMBER_QUEUE_ATTEMPTS')) {
                        $resource->letsEncryptAcmeRegister->publication_attempts = $resource->letsEncryptAcmeRegister->publication_attempts + 1;
                        $resource->letsEncryptAcmeRegister->save();
                        $this->logSys->syslog(
                            '[CDN-API | SSL INSTALATION]  Falha na instalação do certificado SSL do resource.' . $data['cdn_resource_hostname'] . ' NOVA TENTANTIVA em execução !',
                            "Ocorreu uma falha na execução da instalação do certificado SSL na API cdn-dispatcker-api, tentativa " . $resource->letsEncryptAcmeRegister->publication_attempts . " em execução. Retorno de Falha : " . json_encode($data),
                            'WARNING',
                            $this->facilityLog . ':' . basename(__FUNCTION__)
                        );
                        $sslStepStatus->updateStep($resource->id, 8, 'waiting', 'SSL certificate not installed, wait for new installation.');
                        $this->cdnInstallSSLCert($data);

                    } else {

                        $sslStepStatus->updateStep($resource->id, 8, 'failed', 'SSL certificate not installed. contact support.');
                        $this->logSys->syslog(
                            '[CDN-API | SSL INSTALATION]  Falha na instalação do certificado SSL do resource.' . $data['cdn_resource_hostname'] . ' tentativas excedidas!',
                            "Ocorreeu uma falha na execução da instalação do certificado SSL na API cdn-dispatcker-api, foram efetuadas " . $resource->letsEncryptAcmeRegister->publication_attempts . " tentativas. Retorno de Falha : " . json_encode($data),
                            'INFO',
                            $this->facilityLog . ':' . basename(__FUNCTION__)
                        );
                    }
                }
            }
        } catch (Exception $e) {
            $resources = $this->cdnResource->where('request_code', $data['request_code'])->first();
            $sslStepStatus->updateStep($resource->id, 8, 'failed', "Fatal failure in SSL certificate $sslCompanny installation. Call support.");
            $this->logSys->syslog(
                '[CDN-API | SSL INSTALATION] Ocorreu exceção na execução da instalação do certificado SSL do resource.' . $data['cdn_resource_hostname'],
                'ERRO : ' . $e->getMessage() . '. Parâmetros de recebidos: ' . json_encode($data) . ' JOB: CreateCdnSSLCertReturnJob',
                'ERROR',
                $this->facilityLog . ':' . basename(__FUNCTION__)
            );
        }
    }


    public function deleteCdnSSLCert($data)
    {
        $sslStepStatus = new StepProvisioningRepository();
        $cdnResources = $this->cdnResource->where('request_code', $data['request_code'])->with('letsEncryptAcmeRegister')->get();
        if ($cdnResources) {
            foreach ($cdnResources as $cdnResource) {
                $certificate = SslCertificate::createForHostName($cdnResource->cdn_resource_hostname);
                $company = $certificate->getOrganization();
                $sslStepStatus->updateStep($cdnResource->id, 7, 'waiting', "Uninstalling the $company SSL certificate on CDN servers");
            }
        }
        DeleteCdnSSLCertJob::dispatch($data)->onQueue('cdn_delete_cdn_ssl');
    }



    public function deleteCdnSLLCertReturn($data)
    {
        $sslStepStatus = new StepProvisioningRepository();
        $cdnResource = $this->cdnResource->where('request_code', $data['request_code'])->first();

        if ($data['code'] == 200) {
            if($cdnResource){
                $sslStepStatus->updateStep($cdnResource->id, 7, 'finished', 'SSL certificate and your data successfully deleted');
                $sslStepStatus->updateStep($cdnResource->id, 8, 'waiting', 'Delete SSL certificate registration');
                $deleteCertificate = $this->deleteSSLCert($cdnResource);
                if ($deleteCertificate['code'] == 200) {
                    $sslStepStatus->updateStep($cdnResource->id, 8, 'finished', ' SSL certificate registration successfully deleted');

                } else {
                    $sslStepStatus->updateStep($cdnResource->id, 8, 'failed', $deleteCertificate['message']);
                }
            }

        } else {
            $sslStepStatus->updateStep($cdnResource->id, 7, 'failed', 'SSL certificate removal playbook error, try again or contact support');

        }
    }

    public function deleteSSLCert($resource)
    {
        $sslStepStatus = new StepProvisioningRepository();
        try {
            $cdnResource = $this->cdnResource->find($resource->id);
            $ssl = $this->cdnLetsencryptAcmeRegister->find($cdnResource->cdn_acme_lets_encrypt_id);
            $cdnResource->cdn_acme_lets_encrypt_id = null;
            $cdnResource->cname_ssl_verify = null;
            $cdnResource->save();
            $ssl->delete();
            $this->logSys->syslog(
                '[CDN-API | Provisioning CDN]  Exclusão do registro do certificado SSL do resource ' . $resource->cdn_resource_hostname . ' efetuado com sucesso.',
                'JOB: DeleteCdnSSLCertReturnJob',
                'INFO',
                $this->facilityLog . ':' . basename(__FUNCTION__)
            );
            return ['code' => 200];
        } catch (Exception $e) {
            $this->logSys->syslog(
                '[CDN-API | Provisioning CDN] Ocorreu exceção na exclusão do registro do certificado SSL do resource.' . $resource->cdn_resource_hostname,
                'ERRO : ' . $e->getMessage() . 'JOB: DeleteCdnSSLCertReturnJob',
                'ERROR',
                $this->facilityLog . ':' . basename(__FUNCTION__)
            );
           return ['message' => 'Fatal failure delete Certificate SSL register data', 'code' => 400];
        }
    }

    public function validSLLCert($certificate) {

        if(is_null($certificate->fullchain)) {
            return true;
        }else {
            $today = date('Y-m-d');
            $renew = verifyRenew($today, $certificate->certificate_expires, env('SSL_RENEWAL_DAYS'));
            if($renew) {
                return true;
            }else {
                return false;
            }
        }

    }

}
