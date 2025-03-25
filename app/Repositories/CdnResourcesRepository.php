<?php

namespace App\Repositories;

use App\Jobs\BlockCdnResourceJob;
use App\Jobs\UnblockCdnResourceJob;
use App\Jobs\UpdateCdnResourceJob;
use App\Models\CdnHeader;
use App\Models\CdnLetsencryptAcmeRegister;
use App\Models\CdnOriginServer;
use App\Models\CdnProvisioningStep;
use App\Models\CdnResource;
use App\Models\CdnResourceBlock;
use App\Models\CdnResourceOriginGroup;
use App\Models\CdnTenant;
use App\Repositories\AcmeLetsEncrypt\AcmeDnsClientRepository;
use App\Repositories\AcmeLetsEncrypt\AcmeStorageRepository;
use App\Repositories\CdnOriginServerGroupRepository;
use App\Services\EventLogService;
use App\Services\UserIdFromTokenService;
use Exception;
use Illuminate\Support\Facades\Log;



class CdnResourcesRepository
{

    private $cdnTenant;
    private $cdnResource;
    private $userIdFromTokenService;
    private $logSys;
    protected $facilityLog;
    private $template;
    private $ingestPoint;
    private $targetGroup;
    private $cdnResourceBlock;
    private $provisioningStep;
    private $storageCertRepository;
    private $acmeStorageRepository;
    private $cdnLetsencryptAcmeRepository;


    public function __construct()
    {
        $this->cdnTenant = new CdnTenant();
        $this->cdnResource = new CdnResource();
        $this->userIdFromTokenService = new UserIdFromTokenService();
        $this->logSys = new EventLogService();
        $this->facilityLog = basename(__FILE__);
        $this->template = new TemplateRepository();
        $this->ingestPoint = new IngestPointRepository();
        $this->targetGroup = new TargetGroupRepository();
        $this->cdnResourceBlock = new CdnResourceBlock();
        $this->provisioningStep = new StepProvisioningRepository();
        $this->storageCertRepository = new StorageCertRepository();
        $this->acmeStorageRepository = new AcmeStorageRepository();
        $this->cdnLetsencryptAcmeRepository = new CdnLetsencryptAcmeRegisterRepository();
    }


    /**
     * Método responsável por recuperar o registro de um cdn_resource especifico através do nome informado.
     *
     * @param array $data Dados para consulta do CDN Resource.
     *
     * @return array Retorna os dados do CDN Resource ou mensagem de erro.
     */

    public function getCdnResource($data)
    {
        try {


            $cdnResouces = $this->cdnResource->where('cdn_resource_hostname', $data['cdn_resource_hostname'])
                ->with(['provisioningStep', 'tenant', 'cname', 'letsEncryptAcmeRegister'])
                ->get();

            if (count($cdnResouces) == 0) {
                $this->logSys->syslog(
                    '[CDN-API | CdnResources] Cdn resource consultado inexistente!',
                    'DADOS : ' . json_encode($data),
                    'ERROR',
                    $this->facilityLog . ':' . basename(__FUNCTION__)
                );
                return [
                    'message' => 'Cdn resource ' . strtoupper($data['cdn_resource_hostname']) . ' does not exist in the database',
                    'errors' => ['Cdn resource not found in table cdn_resources'],
                    'code' => 400
                ];
            }

            foreach ($cdnResouces as $cdnResource) {

                if (!is_null($cdnResource->cdn_acme_lets_encrypt_id)) {
                    $registerSSL = CdnLetsencryptAcmeRegister::find($cdnResource->cdn_acme_lets_encrypt_id);
                    $cnameSSL = $registerSSL->fulldomain;
                }

                if (!is_null($cdnResource->cdn_resource_block_id)) {
                    $data_block = CdnResourceBlock::find($cdnResource->cdn_resource_block_id);
                }

                if (is_null($cdnResource->cdn_acme_lets_encrypt_id)) {
                    $sslCuston = false;
                } else {
                    $sslCuston = $cdnResource->letsEncryptAcmeRegister->company == 'lets_encrypt' ? false : true;
                }

                $resourceOriginGroup = CdnResourceOriginGroup::where('cdn_resource_id', $cdnResource->id)->with('originGroup.originServers')->get()->toArray();
                $originServers = serverGroups($resourceOriginGroup, $cdnResource->cdn_resource_hostname);
                $response = [
                    'request_code' => $cdnResource->request_code,
                    'tenant' => $cdnResource->tenant->tenant,
                    'ssl' => is_null($cdnResource->cdn_acme_lets_encrypt_id) ? false : true,
                    'ssl_custon' => $sslCuston,
                    'cdn_resource_hostname' => $cdnResource->cdn_resource_hostname,
                    'cdn_resource_hostname_ssl' => is_null($cdnResource->cdn_acme_lets_encrypt_id) ? null : "_acme-challenge." . $cdnResource->cdn_resource_hostname,
                    'cdn_origin_hostname' => $cdnResource->cdn_origin_hostname,
                    'storage_id' => $cdnResource->storage_id,
                    'template' => $this->template->getTemplateName($cdnResource->cdn_template_id),
                    'cname' => cnameById($cdnResource->cdn_cname_id),
                    'cname_ssl' => is_null($cdnResource->cdn_acme_lets_encrypt_id) ? null : $cnameSSL,
                    'cname_validate' => $cdnResource->cname_verify == 1 ? true : false,
                    'cname_ssl_validate' => $cdnResource->cname_ssl_verify == 1 ? true : false,
                    'cdn_resource_block_id' => $cdnResource->cdn_resource_block_id,
                    'target_group' => $this->targetGroup->getById($cdnResource->cdn_target_group_id),
                    'ingest_point' => $this->ingestPoint->getById($cdnResource->cdn_ingest_point_id),
                    'provisioned' => $cdnResource->provisioned == 1 ? true : false,
                    'ssl_certificate_expires' => is_null($cdnResource->cdn_acme_lets_encrypt_id) ? null : $cdnResource->letsEncryptAcmeRegister->certificate_expires,
                    'data_block' => is_null($cdnResource->cdn_resource_block_id) ? null : $data_block,
                    'description' => $cdnResource->description,
                ];

                //tratamento do grupo de servidores single ou multiplo

                $spreadServers = serverGroups($resourceOriginGroup, $cdnResource->cdn_resource_hostname);
                if (count($spreadServers) == 1) {
                    if (count($spreadServers[0]['nodes']) == 1) {
                        $originServer = CdnOriginServer::where("cdn_origin_group_id", getIdGroupServer($resourceOriginGroup)[0])->get();
                        foreach ($originServer as $origin) {
                            $response['cdn_origin_protocol'] = $origin->cdn_origin_protocol;
                            $response['cdn_origin_hostname'] = $origin->cdn_origin_hostname;
                            $response['cdn_origin_server_port'] = $origin->cdn_origin_server_port;
                        }
                    } else {
                        $response['cdn_origin_group_id'] = getIdGroupServer($resourceOriginGroup);

                    }
                } else {
                    $response['cdn_origin_group_id'] = getIdGroupServer($resourceOriginGroup);
                }

                //verificação de custon headers

                $custonHeaders = CdnHeader::where('cdn_resource_id', $cdnResource->id)->get();
                $response['cdn_headers'] = [];
                if ($custonHeaders) {
                    foreach ($custonHeaders as $custonHeader) {
                        $array = [
                            'name' => $custonHeader->name,
                            'value' => $custonHeader->value
                        ];
                        array_push($response['cdn_headers'], $array);
                        unset($array);
                    }
                }



                $provisioningStep = [];
                foreach ($cdnResource->provisioningStep as $step) {
                    $data_step = [
                        'step_desription' => $step->step_description,
                        'step' => $step->step,
                        'status' => trim($step->status),
                        'observation' => $step->observation,
                        'date_event' => date('Y-m-d H:i:s', strtotime($step->updated_at))
                    ];
                    array_push($provisioningStep, $data_step);
                    unset($data_step);
                }
                unset($data_resource);
                $response['provisioning_evolution'] = $provisioningStep;
            }

        } catch (Exception $e) {

            $this->logSys->syslog(
                '[CDN-API | CdnResources] Ocorreu uma exceção na recuperação do lista de cdn resource ' . $data['cdn_resource_hostname'],
                'DADOS : ' . json_encode($data) . ' - ERRO : ' . $e->getMessage(),
                'ERROR',
                $this->facilityLog . ':' . basename(__FUNCTION__)
            );

            $response = [
                'message' => 'Fatal failure to cdn resource query ' . $data['cdn_resource_hostname'],
                'errors' => [$e->getMessage(), $e->getTraceAsString()],
                'code' => 400

            ];
        }
        return $response;
    }

    /**
     * Método responsável por efetuara checagem do DNS CNAME manualmente, através do Painel VCDN.
     * @param array $data Dados para verificação do CNAME.
     *
     * Se cname não foi validado ainda , verifica a validade de cname,
     *      se verdadeiro segue com o provisionamento do CDN RESOURCE e retorna true na chave dns_cname_configured
     *      se falso retorna falso retorna false na chave dns_cname_configured e aguarda próxima validação automática ou manual através do painel
     * Se já foi validado anteriormente retorna true na chave dns_cname_configured
     *
     * @return array $response Retorna o status da validação do CNAME.
     */

    public function checkDnsCname($data)
    {
        try {
            $validade = formRequestCname($data);
            if (!empty($validade)) {
                return [
                    'code' => 400,
                    'message' => 'Warning!Invalidation of the data entered.',
                    'errors' => $validade,
                ];
            }
            $checkCname = CNAMEValidate($data['cdn_resource_hostname'], $data['cname']);
            $cdnResouces = $this->cdnResource->where('cdn_resource_hostname', $data['cdn_resource_hostname'])
                ->with(['provisioningStep', 'tenant'])
                ->get();
            $provisioningStep = new StepProvisioningRepository();
            foreach ($cdnResouces as $cdnResouce) {
                if ($cdnResouce->cname_verify == false) {
                    if ($checkCname == true) {
                        if (!is_null($cdnResouce->cdn_acme_lets_encrypt_id)) {
                            $cnameSSL = CdnLetsencryptAcmeRegister::find($cdnResouce->cdn_acme_lets_encrypt_id);
                            $checkSsl = CNAMEValidate("_acme-challenge." . $cdnResouce->cdn_resource_hostname, $cnameSSL->fulldomain);
                            if ($checkSsl == true) {
                                $cdnResouce->cname_ssl_verify = true;
                                $cdnResouce->save();
                                $acmeStorageRepository = new AcmeStorageRepository();
                                $getCertificate = $acmeStorageRepository->certGeneration($cdnResouce);
                                Log::info("LetsEncrypt gerenate : " . json_encode($getCertificate));
                                if ($getCertificate['code'] == 400) {
                                    $provisioningStep->updateStep($cdnResouce->id, 8, 'failed', $getCertificate['message']);
                                    $response = [
                                        'dns_cname_configured' => false,
                                        'code' => 400
                                    ];
                                } else {
                                    $resource = $this->cdnResource->find($cdnResouce->id);
                                    $resource->cname_verify = true;
                                    $resource->save();
                                    $provisioningStep->updateStep($cdnResouce->id, 1, 'finished', 'Successfully Validated CNAME');
                                    $provisioning = new ProvisioningRepository();
                                    $provisioning->createCDNResource([
                                        'id' => $cdnResouce->id,
                                        'tenant_id' => $cdnResouce->tenant->id,
                                        'tenant' => $cdnResouce->tenant->tenant,
                                        'request_code' => $cdnResouce->request_code
                                    ]);
                                    rmCertFolder(base_path() . "/" . ".acme/" . $resource->cdn_resource_hostname . "_ecc");

                                    $provisioningStep->updateStep($cdnResouce->id, 2, 'finished', 'Certificate request and generation successful');
                                    $response = [
                                        'dns_cname_configured' => true,
                                        'code' => 200
                                    ];
                                }
                            } else {
                                $provisioningStep->updateStep($cdnResouce->id, 1, 'failed', 'Invalid SSL cname or not configured in dns');
                                $response = [
                                    'dns_cname_configured' => false,
                                    'code' => 200
                                ];
                                continue;
                            }
                        } else {
                            $resource = $this->cdnResource->find($cdnResouce->id);
                            $resource->cname_verify = true;
                            $resource->save();
                            $provisioningStep->updateStep($cdnResouce->id, 1, 'finished', 'Successfully Validated CNAME');
                            $provisioning = new ProvisioningRepository();
                            $provisioning->createCDNResource([
                                'id' => $cdnResouce->id,
                                'tenant_id' => $cdnResouce->tenant->id,
                                'tenant' => $cdnResouce->tenant->tenant,
                                'request_code' => $cdnResouce->request_code
                            ]);
                            $response = [
                                'dns_cname_configured' => true,
                                'code' => 200
                            ];
                        }

                    } else {
                        $provisioningStep->updateStep($cdnResouce->id, 1, 'failed', 'Invalid cname or not configured in dns - (manual check)');
                        $response = [
                            'dns_cname_configured' => false,
                            'code' => 200
                        ];
                    }
                } else {
                    $response = [
                        'dns_cname_configured' => true,
                        'code' => 200
                    ];
                }
            }
        } catch (Exception $e) {

            if (!isset($data['cdn_resource_hostname'])) {
                $resource = 'Campo CDN resource NÃO informado!';
            } else {
                $resource = $data['cdn_resource_hostname'];
            }

            $this->logSys->syslog(
                '[CDN-API | CdnResources] Ocorreu uma exceção na validação do DNS CNAME via portal do  CDN Resource ' . $resource,
                'DADOS : ' . json_encode($data) . ' - ERRO : ' . $e->getMessage(),
                'ERROR',
                $this->facilityLog . ':' . basename(__FUNCTION__)
            );
            $response = [
                'message' => 'Fatal failure when validating the DNS CNAME of the CDN Resource' . $data['cdn_resource_hostname'],
                'errors' => [$e->getMessage()],
                'code' => 400
            ];

        }
        return $response;
    }

    /**
     * Ativa ou desativa o certificado SSL para um CDN Resource.
     *
     * @param array $data Dados para ativação/desativação do SSL.
     * @return array Retorna o status da operação.
     */
    public function sslActive($data)
    {
        try {
            $data['ssl'] = filter_var($data['ssl'], FILTER_VALIDATE_BOOLEAN);
            Log::info("inicia o registro do letsencrypt");
            $cdnResource = $this->cdnResource->where('request_code', $data['request_code'])->first();
            if (!is_null($cdnResource->cdn_acme_lets_encrypt_id)) {
                if ($data['ssl'] == false) {
                    $this->provisioningStep->createSLLSteps($cdnResource->id, 'uninstall');
                    $ssl = new CertificateManagerRepository();
                    $request = [
                        "request_code" => $cdnResource->request_code,
                        "cdn_resource_hostname" => $cdnResource->cdn_resource_hostname,
                    ];
                    $ssl->deleteCdnSSLCert($request);
                    return ['message' => 'SSL certificate marked for uninstallation, wait for the process to finish in the background', 'code' => 200];
                } else {
                    return $this->sslInstall($data, $cdnResource);
                }
            } else {
                if ($data['ssl'] == false) {
                    return ['message' => 'No SSL certificate registered to delete', 'code' => 400];
                } else {
                    return $this->sslInstall($data, $cdnResource);
                }
            }
        } catch (Exception $e) {
            $this->logSys->syslog(
                '[CDN-API | CdnResources] Ocorreu uma exceção ao ativar o certificado ssl na conta.',
                'DADOS : ' . json_encode($data) . ' - ERRO : ' . $e->getMessage(),
                'ERROR',
                $this->facilityLog . ':' . basename(__FUNCTION__)
            );
            return [
                'message' => 'Fatal failure when activating ssl certificate on account' . $cdnResource['cdn_resource_hostname'],
                'errors' => [$e->getMessage()],
                'code' => 400
            ];
        }
    }


    public function sslInstall($data, $cdnResource)
    {

        if (is_null($data['certificate'])) {
            $this->provisioningStep->createSLLSteps($cdnResource->id, 'letsencrypt');
            $checkCAA = CAACheck($cdnResource->cdn_resource_hostname);
            if ($checkCAA == true) {
                $this->provisioningStep->updateStep($cdnResource->id, 6, 'finished', 'DNS CAA entry validated.');
                $acmeLetsencrypt = new AcmeDnsClientRepository();
                $register = $acmeLetsencrypt->RegisterAccount();
                if (isset($register['code']) && $register['code'] == 400) {
                    $this->provisioningStep->deleteSSLSteps($cdnResource->id);
                    return [
                        "code" => 400,
                        "message" => "Fatal failure when adding ssl certificate'",
                        "errors" => $register['errors']
                    ];
                } else {
                    $this->provisioningStep->updateStep($cdnResource->id, 7, 'waiting', "Wait for Let's Encrypt CNAME entry validation");
                    $register['cdn_resource_id'] = $cdnResource->id;
                    $newRegister = $this->cdnLetsencryptAcmeRepository->create($register, $cdnResource->id);
                    // Atualiza registro do cdn resource na tabela cdn_resource
                    $cdnResource->cdn_acme_lets_encrypt_id = $newRegister->id;
                    $cdnResource->cname_ssl_verify = null;
                    $cdnResource->save();
                    return [
                        "code" => 200,
                        "message" => "Let's Encrypt SSL certificate successfully registered, wait for installation.",
                    ];
                }
            } else {
                $this->provisioningStep->updateStep($cdnResource->id, 6, 'failed', "Your DNS is restricted by the Let's Encrypt Certificate Authority (CA).");
                $response = [
                    'code' => 400,
                    'message' => "Warning! Your DNS is restricted by the Let's Encrypt Certificate Authority (CA).  Please include the CAA entry below in your DNS.",
                    'errors' => ['CAA   0   issue  "letsencrypt.org"'],
                ];
                return $response;
            }
        } else if (!is_null($data['certificate'])) {
            $this->provisioningStep->createSLLSteps($cdnResource->id, 'custon');
            $storageCertRepository = new StorageCertRepository();
            $custonCertificate = $storageCertRepository->saveCustonCertificate($data, $cdnResource->cdn_resource_hostname, $cdnResource->id);
            if ($custonCertificate['code'] == 400) {
                $this->provisioningStep->deleteSSLSteps($cdnResource->id);
                return [
                    'code' => 400,
                    'message' => "Warning! We've found flaws in the custom certificate entry rules. Check them out!.",
                    'errors' => $custonCertificate['errors'],
                ];
            } else {
                $cdnResource->cdn_acme_lets_encrypt_id = $custonCertificate['id'];
                $cdnResource->cname_ssl_verify = true;
                $cdnResource->save();
                return [
                    "code" => 200,
                    "message" => "Custon SSL certificate successfully registered, wait for installation.",
                ];
            }
        }
    }



    public function sslRecheck($data)
    {
        $cdnResource = $this->cdnResource->where('request_code', $data['request_code'])->first();
        if (!is_null($cdnResource->cdn_acme_lets_encrypt_id)) {
            $sslCertificate = CdnLetsencryptAcmeRegister::find($cdnResource->cdn_acme_lets_encrypt_id);
            //verifica se o cdn resource já foi provisionado
            if (!is_null($cdnResource->provisioned)) {
                //verificca se há tentativas excedidas
                if (
                    $sslCertificate->attempt_install <= env("CERTIFICATE_SSL_INSTALL_TRIES") &&
                    is_null($sslCertificate->last_attempt)
                ) {
                    //incrementando tentativa;
                    $sslCertificate->attempt_install = $sslCertificate->attempt_install + 1;
                    $sslCertificate->last_attempt = $sslCertificate->attempt_install == env("CERTIFICATE_SSL_INSTALL_TRIES")
                        ? date('Y-m-d H:i:s')
                        : null;
                    $sslCertificate->save();
                    $certificateManagerRepository = new CertificateManagerRepository();

                    /* verifica se o certificado foi persistido, se sim verifica a validade
                    * se o certificado não foi persistido retorna true
                    * se o certificado foi persistido retorna false
                    * se o certificado foi persistido e esta expirado retorna true
                    * se o certificado foi persistido e esta expirado retorna false
                    * se verdadeiro gera o certificado novamente
                    */
                    if($certificateManagerRepository->validSLLCert($sslCertificate)) {

                        $getCertificate = $certificateManagerRepository->checkSsl($cdnResource);

                        if ($getCertificate == false) {
                            return [
                                'code' => 400,
                                'message' => 'Certificate generation failed in acme.wait a few moments and try again.',
                                'errors' =>['please check the messages on the right side of the screen ']
                            ];
                        }

                    }
                    //verifica se o certificado é lets encrypt caso contrario invova a instalação do certificado
                    if ($sslCertificate->company == "lets_encrypt") {
                        $this->provisioningStep->createSLLSteps($cdnResource, 'lets_encrypt');
                        $check = $certificateManagerRepository->checkSsl($cdnResource);
                        //verifica se a checagem do registro lets encrypt está sem exceções,
                        //se sim  invoca a instalação do certificado SSL Lets Encript
                        if ($check == true) {
                            $certificateManagerRepository->cdnInstallSSLCert(['request_code' => $cdnResource->request_code, 'cdn_resource_hostname' => $cdnResource->cdn_resource_hostname]);
                            $this->provisioningStep->updateStep($cdnResource->id, 8, 'waiting', "Installing the Let's Encrypt SSL certificate");
                        }
                    } else {
                        $this->provisioningStep->updateStep($cdnResource->id, 9, 'waiting', "Installing the Custon SSL certificate");
                        $certificateManagerRepository->cdnInstallSSLCert(['request_code' => $cdnResource->request_code, 'cdn_resource_hostname' => $cdnResource->cdn_resource_hostname]);
                    }

                } else {
                    return [
                        'code' => 400,
                        'message' => 'Number of attempts exceeded',
                        'errors' => ['Number of manual attempts exceeded, wait 30 minutes and try again.']
                    ];
                }
                return [
                    'code' => 200,
                    'message' => 'New attempt to install the SSL certificate has been made. please wait',
                ];

            } else {
                return [
                    'code' => 400,
                    'message' => 'Resource not provisioned',
                    'errors' => ['Resource not yet provisioned, wait for the provisioning to finish.']
                ];
            }
        }
    }

    public function update($data)
    {
        try {

            $cdnResource = $this->cdnResource->where('request_code', $data['request_code'])->with('tenant', 'ingestPoint')->first();

            $updateData = [
                'cdn_resource_hostname' => $data['cdn_resource_hostname'],
                'cdn_origin_hostname' => $data['cdn_origin_hostname'],
                'cdn_origin_server_port' => $data['cdn_origin_server_port'],
                'cdn_origin_protocol' => $data['cdn_origin_protocol'],
                'cdn_ingest_point_id' => $this->ingestPoint->getIngestPointId($data['cdn_ingest_point']),
                'cdn_target_group_id' => $this->targetGroup->getTargetGroupId($data['cdn_target_group']),
                'cdn_template_id' => $this->template->getTemplateId($data['cdn_template_name']),
                'description' => isset($data['description']) ? $data['description'] : '',
                'cdn_headers' => isset($data['cdn_headers']) ? $data['cdn_headers'] : '',
                'cdn_origin_group_id' => isset($data['cdn_origin_group_id']) ? $data['cdn_origin_group_id'] : '',
            ];

            if ($cdnResource) {
                if (descriptionOnly($updateData, $cdnResource)) {
                    $cdnResource->description = $updateData['description'];
                    $cdnResource->save();
                    return ['message' => "cdn resource description successfully updated ", "code" => 200];
                } else {

                    //instanciando e invocando classe e método para alteração de custon header
                    $cdnHeaderRepository = new CdnHeaderRepository();
                    $cdnHeaderRepository->updateResource($updateData['cdn_headers'], $cdnResource->id);

                    //instanciando e invocando classe e método para alteração servidores de origem
                    $cdnOriginServerGroupRepository = new CdnOriginServerGroupRepository();
                    $cdnOriginServerGroupRepository->updateCdnResource($updateData, $cdnResource->id);

                    //alterando demais informações
                    $cdnResource->cdn_ingest_point_id = $updateData["cdn_ingest_point_id"];
                    $cdnResource->cdn_target_group_id = $updateData["cdn_target_group_id"];
                    $cdnResource->cdn_template_id = $updateData["cdn_template_id"];
                    $cdnResource->description = isset($updateData['description']) ? $updateData['description'] : null;
                    $cdnResource->save();

                    //array para exclusão do cdn resource no dispatcher
                    $updateResource = [
                        "request_code" => $cdnResource->request_code,
                        "update_data" => $updateData,
                        "cdn_resource_hostname" => $cdnResource->cdn_resource_hostname,
                        "cdn_target_group" => $data["cdn_target_group"],
                        "api_key" => $cdnResource->tenant->api_key,
                        "cdn_ingest_point" => $cdnResource->ingestPoint->origin_central
                    ];
                    // crindo passo de alteração do resource
                    $provisioningStep = new StepProvisioningRepository();
                    if (isset($data['reload'])) {
                        $provisioningStep->createStep($cdnResource->id, 10, 'Re-reading Resource', 'waiting', 'Reload cdn resource data');
                        $message = 'Data for re-reading  cdn resouce successfully received. Wait for the data to be processed.';
                    } else {
                        $provisioningStep->createStep($cdnResource->id, 10, 'Update Resource', 'waiting', 'Update cdn resource data');
                        $message = 'Data for Update cdn resouce successfully received. Wait for the data to be processed.';
                    }

                    if (is_null($cdnResource->storage_id)) {
                        $provisioningRepository = new ProvisioningRepository();
                        $provisioningRepository->checkCname();
                        $response = [
                            'message' => $message,
                            'code' => 200
                        ];
                    } else {
                        UpdateCdnResourceJob::dispatch($updateResource)->onQueue('cdn_update_cdn_resource');
                        $response = [
                            'message' => $message,
                            'code' => 200
                        ];
                    }
                }
                //invodando o fila de alteração do resource no dispatcher
            }
        } catch (Exception $e) {
            $this->logSys->syslog(
                '[CDN-API | CdnResources] Ocorreu uma excução na inclusão de update do CDN Resource na fila.',
                'DADOS : ' . json_encode($data) . ' - ERRO : ' . $e->getMessage(),
                'ERROR',
                $this->facilityLog . ':' . basename(__FUNCTION__)
            );
            $response = [
                'message' => 'Fatal failure  in the inclusion of the CDN Resource update/reload in the queue.' . $data['cdn_resource_hostname'],
                'errors' => [$e->getMessage()],
                'code' => 400
            ];

        }
        return $response;
    }



    public function updateReturn($data)
    {
        try {
            //recuperando dados do resource
            $cdnResource = $this->cdnResource->where('request_code', $data['request_code'])->first();
            $provisioningStep = new StepProvisioningRepository();

            // se a exclusão do resource ocorreu com sucesso no dispatcher
            if ($data['code'] == 200) {
                // flag de campos para reprovisionamento do resource
                $cdnResource->storage_id = null;
                $cdnResource->cname_verify = false;
                $cdnResource->provisioned = false;
                $cdnResource->save();
                // reiniciand passos para monitoramento no frontend
                $provisioningStep->deleteStep($cdnResource->id, 10);
                $provisioningStep->updateStep($cdnResource->id, 1, 'waiting', 'Waiting for CNAME Validation');
                $provisioningStep->updateStep($cdnResource->id, 3, 'pending', null, 'Updata CDN Resource');
                $provisioningStep->updateStep($cdnResource->id, 4, 'pending', null, 'Update CDN Template data');
                $provisioningStep->updateStep($cdnResource->id, 5, 'pending', null, 'Update CDN Resource Route');

                // iniciando o reprovisionamento do resource
                $provisioningRepository = new ProvisioningRepository();
                $provisioningRepository->checkCname();

                $response = [
                    'code' => 200
                ];
            } else {
                $provisioningStep->updateStep($cdnResource->id, 10, 'failed', 'Update not carried out due to cdn dispatcher failure');
                $response = [
                    'code' => 400
                ];
            }
        } catch (Exception $e) {
            $this->logSys->syslog(
                '[CDN-API | CdnResources] Ocorreu uma exceção no processamento do retorno de  update do CDN Resource na fila.',
                'DADOS : ' . json_encode($data) . ' - ERRO : ' . $e->getMessage(),
                'ERROR',
                $this->facilityLog . ':' . basename(__FUNCTION__)
            );
            $response = [
                'message' => 'Fatal failure when validating the DNS CNAME of the CDN Resource' . $data['update_data']['cdn_resource_hostname'],
                'errors' => [$e->getMessage()],
                "trace" => [$e->getTraceAsString()],
                'code' => 400
            ];
        }
        return $response;
    }

    public function blockResource($data)
    {

        try {
            $user = $this->userIdFromTokenService->getUserDataFromToken($data['header']['token']);
            if ($user['user_type'] == 'admin') {
                $typeBlock = 'admin';
            } else {
                $typeBlock = 'technical';
            }
            $cdnResource = $this->cdnResource->where('request_code', $data['request_code'])->first();

            $block = [
                'cdn_resource_id' => $cdnResource->id,
                'reason' => $data['reason'],
                'type' => $typeBlock,
            ];
            $cdnBlock = $this->cdnResourceBlock->create($block);
            $cdnResource->cdn_resource_block_id = $cdnBlock->id;
            $cdnResource->save();
            $targetGroup = $this->targetGroup->getById($cdnResource->cdn_target_group_id);


            $resourceBlock = [
                'type_block' => $typeBlock,
                'cdn_resource_block_id' => $cdnBlock->id,
                'request_code' => $data['request_code'],
                'cdn_resource_hostname' => $cdnResource->cdn_resource_hostname,
                'target_group' => $targetGroup['plan'],
            ];

            BlockCdnResourceJob::dispatch($resourceBlock)->onQueue('cdn_block_cdn_resource');

            $response = [
                'message' => 'Request to block cdn resource successfully completed, wait for processing, actual blocking may take some time.',
                'code' => 200
            ];

        } catch (Exception $e) {
            $this->logSys->syslog(
                '[CDN-API | CdnResources] Ocorreu uma exceção ao incluir o bloqueio de resource na fila ',
                'DADOS : ' . json_encode($data) . ' - ERRO : ' . $e->getMessage(),
                'ERROR',
                $this->facilityLog . ':' . basename(__FUNCTION__)
            );
            $response = [
                'message' => 'Fatal failure in cdn resource block request. Contact your system administrator .',
                'errors' => [$e->getMessage()],
                'code' => 400
            ];
        }
        return $response;
    }

    public function blockResourceReturn($data)
    {
        try {
            if ($data['code'] == 400) {

                $cdnResource = $this->cdnResource->where('request_code', $data['return_data']['request_code'])->first();
                $cdnResource->cdn_resource_block_id = null;
                $cdnResource->save();

                $this->logSys->syslog(
                    '[CDN-API | CdnResources] Ocorreu uma falha no bloqueio do cdn resource ' . $cdnResource->cdm_resource_hostname,
                    'Result : ' . json_encode($data),
                    'ERROR',
                    $this->facilityLog . ':' . basename(__FUNCTION__)
                );
            }

            $this->logSys->syslog(
                '[CDN-API | CdnResources] bloqueio do cdn resource ' . $cdnResource->cdm_resource_hostname . ' efetuado com sucesso.',
                null,
                'INFO',
                $this->facilityLog . ':' . basename(__FUNCTION__)
            );


        } catch (Exception $e) {
            $this->logSys->syslog(
                '[CDN-API | CdnResources] Ocorreu uma exceção no processamento do retorno de bloqueio de cdn resource ',
                'DADOS : ' . json_encode($data) . ' - ERRO : ' . $e->getMessage(),
                'ERROR',
                $this->facilityLog . ':' . basename(__FUNCTION__)
            );
        }
    }

    public function unblockResource($data)
    {

        try {
            $user = $this->userIdFromTokenService->getUserDataFromToken($data['header']['token']);
            $cdnResource = $this->cdnResource->where('request_code', $data['request_code'])->with('cdnResourceBlock')->first();
            if (is_null($cdnResource->cdn_resource_block_id)) {
                return [
                    'message' => 'There is no active lock for the selected cdn resource, please contact support.',
                    'code' => 400
                ];
            } else {

                $targetGroup = $this->targetGroup->getById($cdnResource->cdn_target_group_id);
                $resourceUnBlock = [
                    'type_block' => $cdnResource->cdnResourceBlock->type,
                    'cdn_resource_block_id' => $cdnResource->cdnResourceBlock->id,
                    'request_code' => $data['request_code'],
                    'cdn_resource_hostname' => $cdnResource->cdn_resource_hostname,
                    'target_group' => $targetGroup['plan'],
                ];

                if ($cdnResource->cdnResourceBlock->type == 'admin') {
                    if ($user['user_type'] == 'admin') {
                        UnblockCdnResourceJob::dispatch($resourceUnBlock)->onQueue('cdn_unblock_cdn_resource');
                        $response = [
                            'message' => 'Request to unblock cdn resource successfully completed, wait for processing, actual blocking may take some time.',
                            'code' => 200
                        ];
                    } else {
                        $response = [
                            'message' => 'Resource blocked for administrative reasons, please contact support.',
                            'code' => 401
                        ];
                    }

                } else {
                    UnblockCdnResourceJob::dispatch($resourceUnBlock)->onQueue('cdn_unblock_cdn_resource');
                    $response = [
                        'message' => 'Request to unblock cdn resource successfully completed, wait for processing, actual blocking may take some time.',
                        'code' => 200
                    ];
                }
            }


        } catch (Exception $e) {
            $this->logSys->syslog(
                '[CDN-API | CdnResources] Ocorreu uma exceção ao incluir o desbloqueio de resource na fila ',
                'DADOS : ' . json_encode($data) . ' - ERRO : ' . $e->getMessage(),
                'ERROR',
                $this->facilityLog . ':' . basename(__FUNCTION__)
            );
            $response = [
                'message' => 'Fatal failure in cdn resource unlock request. Contact your system administrator .',
                'errors' => [$e->getMessage()],
                'code' => 400
            ];
        }
        return $response;
    }

    public function unBlockResourceReturn($data)
    {

        try {
            if ($data["code"] == 200) {
                $cdnResource = $this->cdnResource->where('request_code', $data['return_data']['request_code'])->first();
                $cdnResource->cdn_resource_block_id = null;
                $cdnResource->save();
                $unblock = $this->cdnResourceBlock->find($data['return_data']['cdn_resource_block_id']);
                $unblock->delete();
                $this->logSys->syslog(
                    '[CDN-API | CdnResources] desbloqueio do cdn resource ' . $cdnResource->cdn_resource_hostname . ' efetuado com sucesso.',
                    null,
                    'INFO',
                    $this->facilityLog . ':' . basename(__FUNCTION__)
                );

            } else {

                /**
                 *   Precisa verificar o que fazer quando houver falha no bloqueio
                 *
                 */
                $this->logSys->syslog(
                    '[CDN-API | CdnResources] Ocorreu erro desbloqueio do cdn resource ' . $data['data_rteturn']['cdn_resource_hostname'],
                    "Retorno do dispatcher : " . json_encode($data),
                    'ERROR',
                    $this->facilityLog . ':' . basename(__FUNCTION__)
                );

            }
        } catch (Exception $e) {
            $this->logSys->syslog(
                '[CDN-API | CdnResources] Ocorreu uma exceção no processamento do retorno de desbloqueio de cdn resource ',
                'DADOS : ' . json_encode($data) . ' - ERRO : ' . $e->getMessage(),
                'ERROR',
                $this->facilityLog . ':' . basename(__FUNCTION__)
            );
        }
    }

    public function checkBlockCdnResource($requestCode)
    {
        $cdnResource = $this->cdnResource->where('request_code', $requestCode)->with('cdnResourceBlock')->first();
        if (!is_null($cdnResource->cdn_resource_block_id)) {
            return $cdnResource->cdnResourceBlock;
        } else {
            return null;
        }

    }

    /**
     * Método responsável por marcar o cdn resource para exclusão
     *
     * @param  array $data
     *
     *
     * @return array
     *
     */



    public function deleteResource($data)
    {
        try {
            $cdnResource = $this->cdnResource->where('request_code', $data['request_code'])->first();
            if ($cdnResource) {
                $cdnResource->marked_deletion = true;
                $cdnResource->attempt_delete = null;
                $cdnResource->save();
            }
            CdnProvisioningStep::where('cdn_resource_id', $cdnResource->id)->delete();


            $this->provisioningStep->deletionSteps($cdnResource->id, 'pendind', null, null);
            $response = ["message" => "Cdn resource marked for deletion, wait for processing.", "code" => 200];
        } catch (Exception $e) {
            $this->logSys->syslog(
                '[CDN-API | CdnResources] Ocorreu uma exceção na marcação para exclusão do cdn resource ',
                'DADOS : ' . json_encode($data) . ' - ERRO : ' . $e->getMessage(),
                'ERROR',
                $this->facilityLog . ':' . basename(__FUNCTION__)
            );
            $response = ["message" => "Fatal failure in marking cdn resource for deletion ", "code" => 400, "errors" => [$e->getMessage()]];
        }
        return $response;

    }

    public function deleteResouceData($data)
    {
        $cdnResource = $this->cdnResource->where('request_code', $data['request_code'])->with('tenant')->first();
        $step = is_null($cdnResource->cdn_acme_lets_encrypt_id) ? 3 : 4;
        try {
            CdnProvisioningStep::where('cdn_resource_id', $cdnResource->id)->delete();
            CdnResourceOriginGroup::where('cdn_resource_id', $cdnResource->id)->delete();
            CdnHeader::where('cdn_resource_id', $cdnResource->id)->delete();
            if (!is_null($cdnResource->cdn_acme_lets_encrypt_id)) {
                $ssl = CdnLetsencryptAcmeRegister::find($cdnResource->cdn_acme_lets_encrypt_id);
                $ssl->delete();
            }
            $cdnResource->delete();
        } catch (Exception $e) {
            $this->provisioningStep->updateStep($cdnResource->id, $step, 'failed', 'Fatal failure when deleting cdn resource, contact support');
            $this->logSys->syslog(
                '[CDN-API | CdnResources] Ocorreu uma exceção na exclusão do cdn resource ',
                'DADOS : ' . json_encode($data) . ' - ERRO : ' . $e->getMessage(),
                'ERROR',
                $this->facilityLog . ':' . basename(__FUNCTION__)
            );
        }




    }

}
