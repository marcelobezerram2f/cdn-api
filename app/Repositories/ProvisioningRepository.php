<?php

namespace App\Repositories;

use App\Jobs\CreateCdnNewTemplateJob;
use App\Jobs\CreateCdnResourceJob;
use App\Jobs\CreateCdnResourceRouteJob;
use App\Jobs\CreateCdnTenantJob;
use App\Jobs\CreateCdnTenantReturnJob;
use App\Models\CdnLetsencryptAcmeRegister;
use App\Models\CdnOriginServer;
use App\Models\CdnResource;
use App\Models\CdnResourceOriginGroup;
use App\Models\CdnTenant;
use App\Repositories\AcmeLetsEncrypt\AcmeDnsClientRepository;
use App\Repositories\AcmeLetsEncrypt\AcmeStorageRepository;
use App\Services\EventLogService;
use App\Services\UserIdFromTokenService;
use Exception;
use Illuminate\Support\Facades\Log;

class ProvisioningRepository
{

    private $cdnTenant;
    private $cdnResource;
    private $provisioningStep;
    private $logSys;
    protected $facilityLog;
    private $ingestPoint;
    private $targetGroup;
    private $template;
    private $client;
    private $cdnTenantRepository;
    private $userFromTokenService;
    private $cdnLetsencryptAcmeRepository;
    private $acmeStorageRepository;
    private $storageCertRepository;
    private $CdnOriginServerGroupRepository;
    private $cdnHeadersRepository;


    public function __construct()
    {
        $this->cdnTenant = new CdnTenant();
        $this->cdnResource = new CdnResource();
        $this->client = new ClientRepository();
        $this->provisioningStep = new StepProvisioningRepository();
        $this->cdnTenantRepository = new CdnTenantRepository();
        $this->ingestPoint = new IngestPointRepository();
        $this->targetGroup = new TargetGroupRepository();
        $this->template = new TemplateRepository();
        $this->logSys = new EventLogService();
        $this->facilityLog = basename(__FILE__);
        $this->userFromTokenService = new UserIdFromTokenService();
        $this->cdnLetsencryptAcmeRepository = new CdnLetsencryptAcmeRegisterRepository();
        $this->acmeStorageRepository = new AcmeStorageRepository();
        $this->storageCertRepository = new StorageCertRepository();
        $this->CdnOriginServerGroupRepository = new CdnOriginServerGroupRepository();
        $this->cdnHeadersRepository = new CdnHeaderRepository();
    }

    /**
     * Método responsável por :
     *
     * a) invocar a classe de gestão do cliente para criar ou recuparar cliente existente
     * b) caso seja novo cliente sera persitido novo cliente e usuário master
     * c) caso cliente existente recupera os dados do cliente para relacionamento com novo tenant
     * d) caso haja falha o na criação do cliente/ usuário ou na recuparação do sdado sde uma cliente existente o tenant não será criado
     * @param array $data
     *
     * @return array $response
     */


    public function newTenant($data)
    {
        try {
            $validade = formRequest($data);
            if (!empty($validade)) {
                return [
                    'code' => 400,
                    'message' => 'Warning!Invalidation of the data entered.',
                    'errors' => $validade,
                ];
            }
            $client = $this->client->getClient($data);
            if (isset($client['code'])) {
                /**
                 * Caso haja a indice "code" no retorno de getClient, significa que houve uma falha na criação da conta do cliente ou do usuário
                 * tenant não será criado, caso tenha sido uma falha na criação do usuário o rollback da tabela cdn_client já foi executado.
                 */
                return $client;
            }

            $newTenant = [
                'tenant' => nextTenantOrUser(isset($client['tenant']) ? $client['tenant'] : $client['account'], 't'),
                'cdn_client_id' => $client['id'],
                'cdn_target_group_id' => $this->targetGroup->getTargetGroupId($data['cdn_target_group']),
                'description' => isset($data['description']) ? $data['description'] : null
            ];

            //persistindo novo tenant na tabela cdn_tenants
            $tenant = $this->cdnTenant->create($newTenant);
            //invocando o método que envia o tenant para fila de criação
            $startCreate = $this->createTenant(['tenant_id' => $tenant->id, 'tenant' => $tenant->tenant]);
            $response['code'] = 200;
            $response['message'] = 'CDN configuration data received successfully, wait for the process to finish';
            $response['tenant'] = $tenant->tenant;
            $response['account'] = $client['account'];
            $response['status'] = $startCreate['status'];
            if (isset($client['main_user'])) {
                $response['main_user'] = $client['main_user'];
                $response['password'] = $client['password'];
            }
        } catch (Exception $e) {
            $this->logSys->syslog(
                '[CDN-API | Provisioning CDN] Ocorreu uma falha na persitência dos dados para configuração de CDN',
                'ERRO : ' . $e->getMessage() . '. Parâmetros de recebidos: ' . json_encode($data),
                'ERROR',
                $this->facilityLog . ':' . basename(__FUNCTION__)
            );
            $response = [
                'code' => 400,
                'message' => 'Fatal failure in receiving the data for configuring the CDN. Please inform your system administrator.',
                'errors' => ['Database inclusion failed', $e->getMessage()]
            ];
        }
        return $response;
    }

    public function createTenant($data)
    {

        $checkHMQ1 = checkRabbitMQConnection(env("RABBITMQ_HOST"));
        $checkHMQ2 = checkRabbitMQConnection(env("RABBITMQ2_HOST"));
        $this->logSys->syslog(
            '[CDN-API | Provisioning CDN] Checangem de conexão com servidor rabbitMQ',
            'onQueue : cdn_create_tenant, Conexão da chave .env |QUEUE_CONNECTION ' .
            env("QUEUE_CONNECTION") .
            " |SERVER1:" . env("RABBITMQ_HOST") .
            " |SERVER2:" . env("RABBITMQ2_HOST") .
            " |VHOST:" . env("RABBITMQ_VHOST") .
            " |USER:" . env("RABBITMQ_USER") .
            " |PORTA" . env("RABBITMQ_PORT") .
            ' Servidor 1  chave .env RABBITMQ_HOST ' . $checkHMQ1 . '. Servidor 2  chave .env RABBITMQ2_HOST : ' . $checkHMQ2,
            'ERROR',
            $this->facilityLog . ':' . basename(__FUNCTION__)
        );

        $tenant = $this->cdnTenant->find($data['tenant_id']);
        $tenant->attempt_create = is_null($tenant->attempt_create) ? 1 : $tenant->attempt_create + 1;
        try {
            CreateCdnTenantJob::dispatch($data)->onQueue('cdn_create_tenant');
            $tenant->queued = true;
        } catch (Exception $e) {
            $tenant->queued = false;
            if ($tenant->attempt_create > env('QUANTITY_QUEUE_REPROCESSING')) {
                $this->logSys->syslog(
                    '[CDN-API | Provisioning CDN] Ocorreu uma falha na inclusão do tenant na fila de criação',
                    'Numero de tentativas (' . env('QUANTITY_QUEUE_REPROCESSING') . ') excedidas - ERRO : ' . $e->getMessage() . '. Parâmetros de recebidos: ' . json_encode($data) . ' JOB: CreateCdnTenantJob! ATENÇÃO O sistema tentará novamente',
                    'ERROR',
                    $this->facilityLog . ':' . basename(__FUNCTION__)
                );

            } else {
                $this->logSys->syslog(
                    '[CDN-API | Provisioning CDN] Ocorreu uma falha na inclusão     ação',
                    'Ocorreu uma falha na inclusão do tenant na fila de criação, nova tentativa será efetuada automaticamente - ERRO : ' . $e->getMessage() . '. Parâmetros de recebidos: ' . json_encode($data) . 'JOB: CreateCdnTenantJob! ATENÇÃO O sistema tentará novamente',
                    'ERROR',
                    $this->facilityLog . ':' . basename(__FUNCTION__)
                );
            }
        }
        $tenant->save();
        return ['status' => 'creating the tenant name, wait for processing.'];
    }

    public function createTenantReturn($data)
    {
        try {
            $this->logSys->syslog(
                '[CDN-API | Provisioning CDN] Retorno de provisionamento do tenant ' . $data['tenant'],
                "Dados de retorno do processamento de provisionamento do tenant . : " . json_encode($data),
                'INFO',
                $this->facilityLog . ':' . basename(__FUNCTION__)
            );


            \Log::info("Iniciando método createTenantReturn Data: " . json_encode($data));
            // se o provisionamento efetuando apenas do tenant
            if ($data['code'] == 200) {
                $updateTenant = $this->cdnTenantRepository->update($data);
                if ($updateTenant['code'] == 200) {
                    $this->logSys->syslog(
                        '[CDN-API | Provisioning CDN] Tenant' . $data['tenant'] . ' provisionado com sucesso!',
                        'Tenant' . $data['tenant'] . ' provisionado com sucesso, sob api_key ' . $data['api_key'],
                        'INFO',
                        $this->facilityLog . ':' . basename(__FUNCTION__)
                    );
                } else {
                    CreateCdnTenantReturnJob::dispatch($data)->onQueue('cdn_create_tenant_return');
                }
            } else {
                \Log::info("Reenviando a criação do tenant para fila: " . json_encode($data));
                $this->createTenant($data);
            }
        } catch (Exception $e) {
            $this->logSys->syslog(
                '[CDN-API | Provisioning CDN] Ocorreu Exceção no provisionamento do tenant' . $data['tenant'] . '( Retorno do dispached)',
                'Exceção de provisionamento do tenant :' . json_encode($data),
                'ERROR',
                $this->facilityLog . ':' . basename(__FUNCTION__)
            );
        }
    }

    public function newCdnResource($data)
    {
        try {
            $data['cdn_headers'] = json_decode($data['cdn_headers'], true);
            $data['cdn_origin_group_id'] = json_decode($data['cdn_origin_group_id'], true);
            $data['storage_id'] = null;


            $validade = formRequestResource($data);
            if (!empty($validade)) {
                return [
                    'code' => 400,
                    'message' => 'Warning!Invalidation of the data entered.',
                    'errors' => $validade,
                ];
            }


            $data['ssl'] = filter_var($data['ssl'], FILTER_VALIDATE_BOOLEAN);
            $data['certificate'] = convertToNull($data['certificate']);

            $tenant = $this->cdnTenant->where('tenant', $data['tenant'])->first();

            if (is_null($tenant->api_key)) {
                return [
                    'code' => 400,
                    'message' => 'Warning! resource cannot be created because tenant has not been registered. Try again later when the api key field is not empty.',
                    'errors' => ['api key is not registered'],
                ];
            }
            // verifica o grupo de origin server
            if (is_null(convertToNull($data['cdn_origin_group_id']))) {
                $originGroup = [
                    "group_name" => $data['cdn_resource_hostname'],
                    'group_description' => 'Grupo de servidores de origem exclusivo para o cdn resource '.  $data['cdn_resource_hostname'],
                    "type" => "single",
                    "cdn_tenant_id" => $tenant->id,
                    "tenant" => $data['tenant'],
                    "origin_servers" => [
                        [
                            "cdn_origin_hostname" => $data['cdn_origin_hostname'],
                            "cdn_origin_protocol" => $data['cdn_origin_protocol'],
                            "cdn_origin_server_port" => $data['cdn_origin_server_port']
                        ]
                    ]
                ];

                $serverGroup = $this->CdnOriginServerGroupRepository->create($originGroup);
                if (isset($serverGroup['code'])) {
                    return $serverGroup;
                } else {
                    $data['cdn_origin_group_id'] = [$serverGroup['cdn_origin_group_id']];
                }
            }

            // verifica a existência do tenant
            if ($tenant) {
                $cname = cname();
                $resource = [
                    'request_code' => makeRequestCode(),
                    'cdn_resource_hostname' => $data['cdn_resource_hostname'],
                    'cdn_ingest_point_id' => $this->ingestPoint->getIngestPointId($data['cdn_ingest_point']),
                    'cdn_target_group_id' => !is_null($data['cdn_target_group']) ? $this->targetGroup->getTargetGroupId($data['cdn_target_group']) : $tenant->cdn_target_group_id,
                    'cdn_tenant_id' => $tenant->id,
                    'cdn_cname_id' => $cname->id,
                    'cdn_template_id' => $this->template->getTemplateId($data['cdn_template_name']),
                    'description' => isset($data['description']) ? $data['description'] : null
                ];
                $newCdnResource = $this->cdnResource->create($resource);

                // atribuindo ao grupo de origin serve
                $cdnResourceOriginGroupRepository = new CdnResourceOriginGroupRepository();
                $assignServerGroups = $cdnResourceOriginGroupRepository->create($data['cdn_origin_group_id'], $newCdnResource->id);
                if ($assignServerGroups['code'] == 400) {
                    $deleteCdnResource = $this->cdnResource->find($newCdnResource->id);
                    $this->provisioningStep->deleteSteps($newCdnResource->id, $newCdnResource, $tenant);
                    $deleteCdnResource->delete();
                    return $assignServerGroups;
                }
                $this->provisioningStep->createSteps($newCdnResource->id, 'waiting', 'Waiting for CNAME Validation', (is_null($data['certificate']) ? null : "x"), ($data['ssl'] == true && is_null($data['certificate']) ? "x" : null));
                // se header custon
                if (isset($data['cdn_headers'])) {
                    $headers = $this->cdnHeadersRepository->create($data['cdn_headers'], $newCdnResource->id);
                    if ($headers['code'] == 400) {
                        $deleteCdnResource = $this->cdnResource->find($newCdnResource->id);
                        $this->provisioningStep->deleteSteps($newCdnResource->id, $newCdnResource, $tenant);
                        $deleteCdnResource->delete();
                        return $headers;
                    }
                }
                // se solicitado certificado SSL
                if ($data['ssl']) {
                    // verifica se o certificado é Letsencrypt
                    if (is_null($data['certificate'])) {
                        if (CAACheck($data['cdn_resource_hostname']) == false) {
                            $deleteCdnResource = $this->cdnResource->find($newCdnResource->id);
                            $this->provisioningStep->deleteSteps($newCdnResource->id, $newCdnResource, $tenant);
                            $deleteCdnResource->delete();
                            return [
                                'code' => 400,
                                'message' => "Warning! Your DNS is restricted by the Let's Encrypt Certificate Authority (CA).  Please include the CAA entry below in your DNS.",
                                'errors' => ['CAA   0   issue  "letsencrypt.org"'],
                            ];
                        }
                        $this->provisioningStep->updateStep($newCdnResource->id, 6, 'finished', "Valid CAA entry for Let's Encrypt SSL Certificate");
                        $acmeLetsencrypt = new AcmeDnsClientRepository();
                        $zeroSsl =
                        // registra conta no ACME Letsencrypt
                        $register = $acmeLetsencrypt->RegisterAccount();
                        // se ocorreu falha no registro efetua rollback e retorna o erro
                        if (isset($register['code'])) {
                            $this->provisioningStep->deleteSteps($newCdnResource->id, $newCdnResource, $tenant);
                            $deleteCdnResource = $this->cdnResource->find($newCdnResource->id);
                            $deleteCdnResource->delete();
                            return $register;
                        }
                        // Caso contrário persiste registro da conta na tabela cdn_letsencrypt_acme_regitries
                        $register['cdn_resource_id'] = $newCdnResource->id;
                        $newRegister = $this->cdnLetsencryptAcmeRepository->create($register, $newCdnResource->id);
                        // Atualiza registro do cdn resource na tabela cdn_resource
                        $newCdnResource->cdn_acme_lets_encrypt_id = $newRegister->id;
                        $newCdnResource->save();
                        $cdn_cname_ssl = $register['fulldomain'];

                    } else {
                        $storageCertRepository = new StorageCertRepository();
                        $custonCertificate = $storageCertRepository->saveCustonCertificate($data, $data['cdn_resource_hostname'], $newCdnResource->id);
                        if ($custonCertificate['code'] == 400) {
                            return [
                                'code' => 400,
                                'message' => "Warning! We've found flaws in the custom certificate entry rules. Check them out!.",
                                'errors' => $custonCertificate['errors'],
                            ];
                        } else {
                            $newCdnResource->cdn_acme_lets_encrypt_id = $custonCertificate['id'];
                            $newCdnResource->cname_ssl_verify = true;
                            $newCdnResource->save();
                        }
                    }
                }
                // Caso SSL false persiste a solicitalicação de criação do resource na tabela cdn_resources considerando o cname default
                $response = [
                    'code' => 200,
                    'request_code' => $newCdnResource->request_code,
                    'cdn_resource_hostname' => $newCdnResource->cdn_resource_hostname,
                    'cdn_resource_hostname_ssl' => $data['ssl'] == true ? "_acme-challenge." . $newCdnResource->cdn_resource_hostname : null,
                    'cname' => $cname->cname,
                    'cname_ssl' => $data['ssl'] == true ? $cdn_cname_ssl : null,
                    'ssl' => $data['ssl'],
                    'tenant' => $data['tenant'],
                    'cdn_target_group' => !is_null($data['cdn_target_group']) ? $data['cdn_target_group'] : $this->targetGroup->getById($tenant->cdn_target_group_id),
                    'message' => 'CDN configuration data received successfully, wait for the process to finish'
                ];
            } else {
                // caso  tenant inexistente
                $response = [
                    'code' => 400,
                    'message' => 'cdn resource registration failure.',
                    'errors' => ['Warning! tenant name not found.'],
                ];

            }
        } catch (Exception $e) {
            // caso haja falha fatal no processo
            $this->logSys->syslog(
                '[CDN-API | Provisioning CDN] Ocorreu exceção na inclusão do cdn resource banco de dados',
                'ERRO : ' . $e->getMessage() . '. Parâmetros de recebidos: ' . json_encode($data),
                'ERROR',
                $this->facilityLog . ':' . basename(__FUNCTION__)
            );
            $response = [
                'code' => 400,
                'message' => 'cdn resource registration failure.',
                'errors' => ['Fatal failure cdn resource registration.', $e->getMessage(), $e->getTraceAsString()]
            ];
        }
        // retorna ao controlador
        return $response;
    }



    public function checkCname()
    {

        $this->logSys->syslog(
            '[CDN-API | Provisioning CDN] Iniciando a verificação de cname de Resources não provisionados',
            null,
            'INFO',
            $this->facilityLog . ':' . basename(__FUNCTION__)
        );
        try {
            $cndConfigNotCheckeds = $this->cdnResource->where('cname_verify', false)->where('marked_deletion', null)->with(['cname', 'tenant'])->get();
            foreach ($cndConfigNotCheckeds as $cndConfigNotChecked) {
                $check = CNAMEValidate($cndConfigNotChecked->cdn_resource_hostname, $cndConfigNotChecked->cname->cname);
                if ($check == true) {
                    $cndConfigNotChecked->cname_verify = true;
                    $cndConfigNotChecked->save();
                    $this->provisioningStep->deleteStep($cndConfigNotChecked->id, 10);
                    $this->provisioningStep->updateStep($cndConfigNotChecked->id, 1, 'finished', 'Successfully Validated CNAME.');
                    $this->createCDNResource([
                        'id' => $cndConfigNotChecked->id,
                        'tenant_id' => $cndConfigNotChecked->tenant->id,
                        'tenant' => $cndConfigNotChecked->tenant->tenant,
                        'request_code' => $cndConfigNotChecked->request_code
                    ]);
                } else {
                    $this->provisioningStep->updateStep($cndConfigNotChecked->id, 1, 'failed', 'CNAME entry not found , please check the cname delivery in your DNS.');
                }
            }
        } catch (Exception $e) {
            $this->logSys->syslog(
                '[CDN-API | Provisioning CDN] Ocorreu uma falha na persitência dos dados para configuração de CDN',
                'ERRO : ' . $e->getMessage(),
                'ERROR',
                $this->facilityLog . ':' . basename(__FUNCTION__)
            );
            return false;
        }
    }


    public function createCDNResource($data)
    {
        try {
            Log::info(json_encode($data) . "oi");
            if (isset($data['request_code'])) {
                $cdnResources = $this->cdnResource->where('request_code', $data['request_code'])->with(['cname', 'tenant', 'ingestPoint'])->get();
            } else {
                $this->provisioningStep->updateStep($data['id'], 2, 'waiting', 'Creating the Tenant Name');
            }

            foreach ($cdnResources as $cdnResource) {
                $originServer =  $this->CdnOriginServerGroupRepository->getOriginServerMain($cdnResource->id);
                Log::info("Retorno dos servidor de Origen : " . json_encode($originServer));
                $request = [
                    'tenant' => $cdnResource->tenant->tenant,
                    'api_key' => $cdnResource->tenant->api_key,
                    'request_code' => $cdnResource->request_code,
                    'cdn_resource_hostname' => $cdnResource->cdn_resource_hostname,
                    'cdn_origin_hostname' => $originServer[0]['cdn_origin_hostname'],
                    'cdn_origin_server_port' => $originServer[0]['cdn_origin_server_port'],
                    'pop_prefix' => $cdnResource->ingestPoint->pop_prefix,
                ];
                CreateCdnResourceJob::dispatch($request)->onQueue('cdn_create_cdn_resource');
                $cdnResource->attempt_create = $cdnResource->attempt_create + 1;
                $cdnResource->save();
                $this->provisioningStep->updateStep($cdnResource->id, 3, 'waiting', 'Creating the CDN Resource');
            }

        } catch (Exception $e) {
            $cdnResource = $this->cdnResource->where('request_code', $data['request_code'])->first();
            $cdnResource->cname_verify = false;
            $cdnResource->save();
            $this->provisioningStep->updateStep($cdnResource->id, 3, 'failed', 'Fatal Failure  .');
            $this->logSys->syslog(

                '[CDN-API | Provisioning CDN] Ocorreu exceção na inclusão do cdn resource na fila de criação',
                'ERRO : ' . $e->getMessage() . '. Parâmetros de recebidos: ' . json_encode($data) . ' JOB: CreateCdnResourceJob',
                'ERROR',
                $this->facilityLog . ':' . basename(__FUNCTION__)
            );
        }
    }

    public function createCDNResourceReturn($data)
    {
        \Log::info('Método createCDNResourceReturn  iniciado ' . json_encode($data));
        $this->logSys->syslog(
            '[CDN-API | Provisioning CDN] Retorno de provisionamento do cdn resources ' . $data['cdn_resource_hostname'],
            "Dados de retorno do processamento de provisionamento do cdn resources . : " . json_encode($data),
            'INFO',
            $this->facilityLog . ':' . basename(__FUNCTION__)
        );

        try {
            if (isset($data['request_code'])) {
                $updateCdnResources = $this->cdnResource->where('request_code', $data['request_code'])->with(['cname', 'tenant'])->get();
            }

            if ($data['code'] == 200) {
                foreach ($updateCdnResources as $updateCdnResource) {
                    $updateCdnResource->storage_id = $data['storage_id'];
                    $updateCdnResource->attempt_create = 0;
                    $updateCdnResource->save();
                    $this->logSys->syslog(
                        '[CDN-API | Provisioning CDN] CDN Resource ' . $updateCdnResource->cdn_resource_hostname . ' do tenant ' . $updateCdnResource->tenant->tenant . ' provisionado com sucesso!',
                        'CDN Resource' . $updateCdnResource->cdn_resource_hostname . ' provisioanado com sucesso, sob storage_id ' . $updateCdnResource->storage_id,
                        'INFO',
                        $this->facilityLog . ':' . basename(__FUNCTION__)
                    );
                    $this->provisioningStep->updateStep($updateCdnResource->id, 3, 'finished', 'CDN RESOURCE created successfully');
                    $this->copyCdnTemplate($data);
                }
            } else {
                foreach ($updateCdnResources as $updateCdnResource) {
                    if ($updateCdnResource->attempt_create < env('NUMBER_QUEUE_ATTEMPTS')) {
                        $updateCdnResource->attempt_create = $updateCdnResource->attempt_create + 1;
                        $updateCdnResource->save();
                        $this->logSys->syslog(
                            '[CDN-API | Provisioning CDN] Ocorreu uma falha no provisionamento do cdn_resource ' . $updateCdnResource->cdn_resource_hostname . ', incluso novamente na fila para efetuar a tentativa de provisionamento número ' . $updateCdnResource->attempt_create,
                            'Falha de provisionamento do cdn_resource :' . json_encode($data),
                            'ERROR',
                            $this->facilityLog . ':' . basename(__FUNCTION__)
                        );
                        $this->createCDNResource($data);
                    } else {
                        $this->logSys->syslog(
                            '[CDN-API | Provisioning CDN] Ocorreu exceção no provisionamento do cdn_resource ' . $updateCdnResource->cdn_resource_hostname . ', número de tentativa de provisionamento número  excedido .',
                            'Falha de provisionamento do cdn_resource :' . json_encode($data),
                            'ERROR',
                            $this->facilityLog . ':' . basename(__FUNCTION__)
                        );
                        $this->provisioningStep->updateStep($updateCdnResource->id, 3, 'failed', $data['massage']);
                    }
                }

            }
        } catch (Exception $e) {
            \Log::info('ERRO Método createCDNResourceReturn: ' . $e->getMessage());

            $this->logSys->syslog(
                '[CDN-API | Provisioning CDN] Ocorreu Exceção no provisionamento de cdn_resource ( Retorno do dispached)',
                'Exceção de provisionamento do cdn resource :' . json_encode($data) . ' ERROR :' . $e->getMessage(),
                'ERROR',
                $this->facilityLog . ':' . basename(__FUNCTION__)
            );
        }
    }

    public function copyCdnTemplate($data)
    {
        try {
            if (isset($data['request_code'])) {
                $cdnResources = $this->cdnResource->where('request_code', $data['request_code'])->with(['tenant', 'template', 'ingestPoint', 'targetGroup'])->get();
            }

            foreach ($cdnResources as $cdnResource) {
                $originServers = CdnResourceOriginGroup::where('cdn_resource_id', $cdnResource->id)->with('originGroup.originServers')->get()->toArray();
                $serverGroups = serverGroups($originServers, $cdnResource->cdn_resource_hostname);
                $request = [
                    'request_code' => $data['request_code'],
                    'custon_headers' => $this->cdnHeadersRepository->getHeadersByResource($cdnResource->id),
                    'forward_headers' => $this->cdnHeadersRepository->getForwardHeader($cdnResource->id),
                    'tenant' => $cdnResource->tenant->tenant,
                    'cdn_resource_hostname' => $cdnResource->cdn_resource_hostname,
                    'server_groups_declare' => serverGroupsDeclare($serverGroups),
                    'server_groups' => $serverGroups,
                    'cdn_origin_nodes' => $originServers,
                    'storage_id' => $cdnResource->storage_id,
                    'cdn_ingest_point' => $cdnResource->ingestPoint->origin_central,
                    'template_name' => $cdnResource->template->template_name,
                ];

                $templateData = transformDataWithPlaceholders(json_decode($cdnResource->template->template_json, true), $request);

                if (isset($templateData['error'])) {
                    $this->provisioningStep->updateStep($cdnResource->id, 4, 'failed', 'Failed to join data with template');
                } else {
                    $request['template_json'] = $templateData;
                    try {
                        CreateCdnNewTemplateJob::dispatch($request)->onQueue('cdn_new_template');
                        $this->provisioningStep->updateStep($cdnResource->id, 4, 'waiting', 'copying configuration CDN-Template');
                        $this->logSys->syslog(
                            '[CDN-API | Provisioning CDN] Cdn Template incluso na fila cdn_new_template .',
                            'Parâmetros : ' . json_encode($data) . ' JOB: CreateCdnResourceRouteJob',
                            'ERROR',
                            $this->facilityLog . ':' . basename(__FUNCTION__)
                        );
                    } catch (Exception $e) {
                        $this->logSys->syslog(
                            '[CDN-API | Provisioning CDN] Ocorreu exceção na inclusão da cópia do cdn template na fila de criação',
                            'ERRO : ' . $e->getMessage() . '. Parâmetros de recebidos: ' . json_encode($data) . ' JOB: CreateCdnNewTemplateJob',
                            'ERROR',
                            $this->facilityLog . ':' . basename(__FUNCTION__)
                        );

                    }
                }
            }
        } catch (Exception $e) {
            $this->logSys->syslog(
                '[CDN-API | Provisioning CDN] Ocorreu exceção na inclusão do cdn template na fila de tratamento e cópia do cdn resource ',
                'ERRO : ' . $e->getTraceAsString() . '. Parâmetros de recebidos: ' . json_encode($data) . ' JOB: CreateCdnNewTemplateJob',
                'ERROR',
                $this->facilityLog . ':' . basename(__FUNCTION__)
            );
        }
    }


    public function copyCdnTemplateReturn($data)
    {
        try {

            $this->logSys->syslog(
                '[CDN-API | Provisioning CDN] Retorno do processamento e cópia do cdn template ' . $data['cdn_resource_hostname'],
                "Dados de retorno do processamento de processamento e cópia do cdn template . : " . json_encode($data),
                'INFO',
                $this->facilityLog . ':' . basename(__FUNCTION__)
            );


            if (isset($data['request_code'])) {
                $cdnResource = $this->cdnResource->where('request_code', $data['request_code'])->with(['tenant', 'targetGroup'])->first();
            }
            if ($data['code'] == 200) {
                $this->provisioningStep->updateStep($cdnResource->id, 4, 'finished', 'configuration and copy the CDN-Template file');
                $cdnResource->attempt_create = 0;
                $cdnResource->save();
                $this->logSys->syslog(
                    '[CDN-API | Provisioning CDN] Configuração e copia de template do cdn resource' . $cdnResource->cdn_resource_hostname . ', realizado com sucesso ',
                    null,
                    'INFO',
                    $this->facilityLog . ':' . basename(__FUNCTION__)
                );

                $this->createCdnRoute($data);

            } else {
                if ($cdnResource->attempt_create < env('NUMBER_QUEUE_ATTEMPTS')) {
                    $cdnResource->attempt_create = $cdnResource->attempt_create + 1;
                    $cdnResource->save();
                    $this->copyCdnTemplate($data);
                    $this->logSys->syslog(
                        '[CDN-API | Provisioning CDN] Ocorreu uma falha na configuração e copia de template cdn_resource' . $cdnResource->cdn_resource_hostname . ', incluso novamente na fila para efetuar a tentativa de provisionamento número ' . $cdnResource->attempt_create,
                        'Falha de provisionamento do tenant :' . json_encode($data),
                        'ERROR',
                        $this->facilityLog . ':' . basename(__FUNCTION__)
                    );
                } else {
                    $this->provisioningStep->updateStep($cdnResource->id, 4, 'failed', 'failure to configure and copy the cdn template');
                    $this->logSys->syslog(
                        '[CDN-API | Provisioning CDN] Ocorreu um na configuração e copia do cdn template do CDN Resource' . $cdnResource->cdn_resource_hostname . ', realizado com sucesso ',
                        'Erro : ' . json_encode($data),
                        'ERROR',
                        $this->facilityLog . ':' . basename(__FUNCTION__)
                    );
                }
            }


        } catch (Exception $e) {
            $this->logSys->syslog(
                '[CDN-API | Provisioning CDN] Ocorreu exceção na configuração  e cópia do cdn template do cdn resource' . $data['cdn_resource_hostname'],
                'ERRO : ' . $e->getMessage() . '. Parâmetros de recebidos: ' . json_encode($data) . ' JOB: CreateCdnNewTemplateReturnJob',
                'ERROR',
                $this->facilityLog . ':' . basename(__FUNCTION__)
            );
        }

    }


    public function createCdnRoute($data)
    {
        try {
            $cdnResources = $this->cdnResource->where('request_code', $data['request_code'])->with(['tenant', 'targetGroup'])->get();
            foreach ($cdnResources as $cdnResource) {
                $request = [
                    'request_code' => $data['request_code'],
                    'tenant' => $cdnResource->tenant->tenant,
                    'cdn_resource_hostname' => $cdnResource->cdn_resource_hostname,
                    'cdn_target_groups' => $cdnResource->targetGroup->name
                ];
                try {
                    CreateCdnResourceRouteJob::dispatch($request)->onQueue('cdn_resource_routes');
                    $this->provisioningStep->updateStep($cdnResource->id, 5, 'waiting', 'creating cdn route');
                    $this->logSys->syslog(
                        '[CDN-API | Provisioning CDN] Cdn route incluso na fila cdn_resource_routes .',
                        'Parâmetros : ' . json_encode($data) . ' JOB: CreateCdnResourceRouteJob',
                        'ERROR',
                        $this->facilityLog . ':' . basename(__FUNCTION__)
                    );
                } catch (Exception $e) {
                    $this->logSys->syslog(
                        '[CDN-API | Provisioning CDN] Ocorreu exceção na inclusão da cópia do cdn route na fila de criação',
                        'ERRO : ' . $e->getMessage() . '. Parâmetros de recebidos: ' . json_encode($data) . ' JOB: CreateCdnResourceRouteJob',
                        'ERROR',
                        $this->facilityLog . ':' . basename(__FUNCTION__)
                    );
                }
            }
        } catch (Exception $e) {
            $this->logSys->syslog(
                '[CDN-API | Provisioning CDN] Ocorreu exceção na inclusão do cdn route na fila do cdn resource ' . $data['cdn_resource_hostname'],
                'ERRO : ' . $e->getMessage() . '. Parâmetros de recebidos: ' . json_encode($data) . ' JOB: CreateCdnResourceRouteJob',
                'ERROR',
                $this->facilityLog . ':' . basename(__FUNCTION__)
            );
        }
    }
    public function CdnRouteReturn($data)
    {
        try {
            Log::info("Iniciando método CdnRouteReturn. " . json_encode($data));
            $this->logSys->syslog(
                '[CDN-API | Provisioning CDN] Retorno de configuração do cdn route ' . $data['cdn_resource_hostname'],
                "Dados de retorno do processamento de configuração de rota . : " . json_encode($data),
                'INFO',
                $this->facilityLog . ':' . basename(__FUNCTION__)
            );
            if (isset($data['request_code'])) {
                $cdnResource = $this->cdnResource->where('request_code', $data['request_code'])->first();
                if (!$cdnResource) {
                    Log::info("CDN Resource não encontrado para o request_code: " . $data['request_code']);
                    return;
                }
            }
            if ($data['code'] == 200) {
                $this->provisioningStep->updateStep($cdnResource->id, 5, 'finished', 'configuration the cdn route');
                $cdnResource->provisioned = true;
                $cdnResource->attempt_create = 0;
                $cdnResource->save();
                $this->logSys->syslog(
                    '[CDN-API | Provisioning CDN] Configuração do cdn route' . $cdnResource->cdn_resource_hostname . ', realizado com sucesso ',
                    null,
                    'INFO',
                    $this->facilityLog . ':' . basename(__FUNCTION__)
                );
                if (!is_null($cdnResource->cdn_acme_lets_encrypt_id)) {
                    $sslRegister = CdnLetsencryptAcmeRegister::find($cdnResource->cdn_acme_lets_encrypt_id);
                    if (is_null($sslRegister->published)) {
                        if ($sslRegister->company == "lets_encrypt") {
                            $this->provisioningStep->updateStep($cdnResource->id, 7, 'waiting', 'Validating the CNAME entry in DNS.');
                        } else {
                            $this->provisioningStep->updateStep($cdnResource->id, 8, 'waiting', 'Running the custom certificate installation.');
                        }
                    }
                }
            } else {
                if ($cdnResource->attempt_create < env('NUMBER_QUEUE_ATTEMPTS')) {
                    $cdnResource->attempt_create = $cdnResource->attempt_create + 1;
                    $cdnResource->save();
                    $this->createCdnRoute($data);
                    $this->logSys->syslog(
                        '[CDN-API | Provisioning CDN] Ocorreu uma falha na configuraçõe do cdn route do CDN Resource' . $cdnResource->cdn_resource_hostname . ', incluso novamente na fila para efetuar a tentativa de provisionamento número ' . $cdnResource->attempt_create,
                        'Falha de provisionamento do tenant :' . json_encode($data),
                        'ERROR',
                        $this->facilityLog . ':' . basename(__FUNCTION__)
                    );
                } else {
                    $this->provisioningStep->updateStep($cdnResource->id, 5, 'failed', 'failure to configure the cdn route');
                    $this->logSys->syslog(
                        '[CDN-API | Provisioning CDN] Ocorreu um na configuração do cdn route do CDN Resource' . $cdnResource->cdn_resource_hostname,
                        'Erro : ' . json_encode($data),
                        'ERROR',
                        $this->facilityLog . ':' . basename(__FUNCTION__)
                    );
                }
            }
            return ['message' => "Processamento do retorno da criação de rotas ocorreu com sucesso . "];
        } catch (Exception $e) {
            $this->logSys->syslog(
                '[CDN-API | Provisioning CDN] Ocorreu exceção na configuração de cdn route.' . $data['cdn_resource_hostname'],
                'ERRO : ' . $e->getMessage() . '. Parâmetros de recebidos: ' . json_encode($data) . ' JOB: CreateCdnResourceRouteReturnJob',
                'ERROR',
                $this->facilityLog . ':' . basename(__FUNCTION__)
            );
            Log::info("Ocorreu um erro na validação do retorno do CdnRoute. " . $e->getMessage());
        }
    }
}
