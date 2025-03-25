<?php

namespace App\Repositories;


use App\Jobs\CheckCdnResourceJob;
use App\Jobs\DeleteCdnResourceJob;
use App\Jobs\DeleteCdnResourcesJob;
use App\Jobs\DeleteCdnRouteJob;
use App\Jobs\DeleteCdnSSLCertJob;
use App\Jobs\DeleteCdnTemplateJob;
use App\Models\CdnCname;
use App\Models\CdnLetsencryptAcmeRegister;
use App\Models\CdnResource;
use App\Models\CdnTenant;
use App\Repositories\AcmeLetsEncrypt\AcmeDnsClientRepository;
use App\Repositories\AcmeLetsEncrypt\AcmeStorageRepository;
use App\Services\EventLogService;
use App\Services\UserIdFromTokenService;
use Exception;
use Illuminate\Support\Facades\Log;
use Spatie\SslCertificate\SslCertificate;

class DeprovisioningRepository
{

    private $cdnTenant;
    private $cdnResource;
    private $provisioningStep;
    private $cdnResourcesRepository;
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
        $this->cdnResourcesRepository = new CdnResourcesRepository();

    }


    /**
     * Método respon sável por invocara a fila de checagem do resource  na api dispatcher
     *
     * @return  void
     *
     */

    public function CheckCdnResource()
    {
        try {
            $resources = $this->cdnResource->where('marked_deletion', true)->where('attempt_delete', null)->get();
            if ($resources) {
                foreach ($resources as $resource) {
                    $tenant = $this->cdnTenant->find($resource->cdn_tenant_id);
                    $resourceToCheck = [
                        "request_code" => $resource->request_code,
                        "tenant" => $tenant->tenant,
                        "api_key" => $tenant->api_key,
                        "cdn_resource_hostname" => $resource->cdn_resource_hostname
                    ];
                    CheckCdnResourceJob::dispatch($resourceToCheck)->onQueue('cdn_check_cdn_resource');
                }
            }
        } catch (Exception $e) {
            $this->logSys->syslog(
                '[CDN-API | Deprovisioning CDN] Ocorreu exceção na inclusão da checagem de cdn_resource na fila cdn_check_cdn_resource ',
                'ERRO : ' . $e->getMessage() . ' JOB: CheckCdnResourceJob',
                'ERROR',
                $this->facilityLog . ':' . basename(__FUNCTION__)
            );
        }
    }

    public function checkCdnResourceReturn($data)
    {
        try {
            $resource = $this->cdnResource->where('request_code', $data['request_code'])->first();
            $this->provisioningStep->updateStep($resource->id, 5, 'finished', 'CDN Resource existence check completed');
            if ($data['has_resource'] == true) {
                $resource->attempt_delete = 1;
                $resource->save();
                if (!is_null($resource->cdn_acme_lets_encrypt_id)) {
                    $this->provisioningStep->updateStep($resource->id, 1, 'waiting', 'Deleting the SSL certificate in progress');
                    DeleteCdnSSLCertJob::dispatch($data)->onQueue('cdn_delete_cdn_ssl');
                }
                $this->deleteCdnRoute($data);
            } else {
                $step = is_null($resource->cdn_acme_lets_encrypt_id) ? 3 : 4;
                $this->provisioningStep->updateStep($resource->id, $step, 'waiting', 'Resource not provisioned! Removal of cdn route in progress.');
                sleep(5);
                if (!isset($data['has_resource'])) {
                    $resource->attempt_delete = null;
                    $resource->save();
                }
                $this->cdnResourcesRepository->deleteResouceData($data);
            }
        } catch (Exception $e) {
            $this->logSys->syslog(
                '[CDN-API | Deprovisioning CDN] Ocorreu exceção no processamento de retorno da checagem de cdn_resource na fila cdn_check_cdn_resource_return',
                'ERRO : ' . $e->getMessage() . ' JOB: checkCdnResourceReturn',
                'ERROR',
                $this->facilityLog . ':' . basename(__FUNCTION__)
            );

        }
    }


    public function deleteCdnRoute($data)
    {
        try {
            $cdnResources = $this->cdnResource->where('request_code', $data['request_code'])->with(['tenant', 'targetGroup'])->get();
            foreach ($cdnResources as $cdnResource) {
                $step = is_null($cdnResource->cdn_acme_lets_encrypt_id) ? 1 : 2;
                $request = [
                    'request_code' => $data['request_code'],
                    'tenant' => $cdnResource->tenant->tenant,
                    'cdn_resource_hostname' => $cdnResource->cdn_resource_hostname,
                    'cdn_target_groups' => $cdnResource->targetGroup->name
                ];

                try {
                    $this->provisioningStep->updateStep($cdnResource->id, $step, 'waiting', 'Removal of cdn route in progress.');
                    DeleteCdnRouteJob::dispatch($request)->onQueue('cdn_delete_cdn_route');
                } catch (Exception $e) {
                    $this->logSys->syslog(
                        '[CDN-API | Deprovisioning CDN] Ocorreu exceção na inclusão de exclusão do cdn route na fila cdn_delete_cdn_route.' . $data['cdn_resource_hostname'],
                        'ERRO : ' . $e->getMessage() . '. Parâmetros de recebidos: ' . json_encode($data) . ' JOB: DeleteCdnRouteReturnJob',
                        'ERROR',
                        $this->facilityLog . ':' . basename(__FUNCTION__)
                    );
                }
            }
        } catch (Exception $e) {
            $this->logSys->syslog(
                '[CDN-API | Deprovisioning CDN] Ocorreu exceção no  método de inclusão de exclusão do cdn route na fila cdn_delete_cdn_route.' . $data['cdn_resource_hostname'],
                'ERRO : ' . $e->getMessage() . '. Parâmetros de recebidos: ' . json_encode($data) . ' JOB: DeleteCdnRouteReturnJob',
                'ERROR',
                $this->facilityLog . ':' . basename(__FUNCTION__)
            );
        }
    }

    public function deleteCdnRouteReturn($data)
    {
        try {
            $cdnResource = $this->cdnResource->where('request_code', $data['request_code'])->first();
            $step = is_null($cdnResource->cdn_acme_lets_encrypt_id) ? 1 : 2;
            if ($data['code'] == 200) {
                $this->logSys->syslog(
                    '[CDN-API | Deprovisioning CDN] Exclusão do cdn route.' . $data['cdn_resource_hostname'] . ' efetuado com sucesso.',
                    'Parâmetros de recebidos: ' . json_encode($data) . ' JOB: DeleteCdnRouteReturnJob',
                    'INFO',
                    $this->facilityLog . ':' . basename(__FUNCTION__)
                );
                $this->provisioningStep->updateStep($cdnResource->id, $step, 'finished', 'Cdn route successfully removed.');
                $cdnResource->attempt_delete =0;
                $cdnResource->save();
                $this->deleteCdnTemplate($data);
            } else {
                if ($cdnResource->attempt_delete < env('NUMBER_QUEUE_ATTEMPTS')) {
                    $cdnResource->attempt_delete = $cdnResource->attempt_delete + 1;
                    $cdnResource->save();
                    $this->deleteCdnRoute($data);
                    $this->logSys->syslog(
                        '[CDN-API | Deprovisioning CDN] Ocorreu uma falha na exclusão do cdn route do CDN Resource' . $cdnResource->cdn_resource_hostname . ', incluso novamente na fila para efetuar a tentativa de provisionamento número ' . $cdnResource->attempt_create,
                        'Falha de exclusão do cdn route :' . json_encode($data),
                        'ERROR',
                        $this->facilityLog . ':' . basename(__FUNCTION__)
                    );
                    $this->provisioningStep->updateStep($cdnResource->id, $step, 'failed', 'CDN route removal playbook execution error.');
                } else {
                    $this->provisioningStep->updateStep($cdnResource->id, $step, 'failed', 'failure to configure the cdn route, attempts exceeded.');
                    $this->logSys->syslog(
                        '[CDN-API | Deprovisioning CDN] Ocorreu um na configuração do cdn route do CDN Resource' . $cdnResource->cdn_resource_hostname,
                        'Erro : ' . json_encode($data),
                        'ERROR',
                        $this->facilityLog . ':' . basename(__FUNCTION__)
                    );
                }

            }
        } catch (Exception $e) {
            $this->logSys->syslog(
                '[CDN-API | Deprovisioning CDN] Ocorreu exceção na exclusão do cdn route.' . $data['cdn_resource_hostname'],
                'ERRO : ' . $e->getMessage() . '. Parâmetros de recebidos: ' . json_encode($data) . ' JOB: DeleteCdnRouteReturnJob',
                'ERROR',
                $this->facilityLog . ':' . basename(__FUNCTION__)
            );
        }
    }

    public function deleteCdnTemplate($data)
    {

        try {

            $cdnResources = $this->cdnResource->where('request_code', $data['request_code'])->with(['tenant', 'targetGroup'])->get();

            foreach ($cdnResources as $cdnResource) {
                $step = is_null($cdnResource->cdn_acme_lets_encrypt_id) ? 2 : 3;
                $request = [
                    'request_code' => $data['request_code'],
                    'tenant' => $cdnResource->tenant->tenant,
                    'cdn_resource_hostname' => $cdnResource->cdn_resource_hostname,
                    'cdn_origin_hostname' => $cdnResource->cdn_origin_hostname,
                    'storage_id' => $cdnResource->storage_id,
                    'cdn_origin_server_port' => $cdnResource->cdn_origin_server_port,
                    'cdn_ingest_point' => $cdnResource->ingestPoint->origin_central,
                    'template_name' => $cdnResource->template->template_name,
                    'protocol' => $cdnResource->cdn_origin_protocol == 'https' ? 1 : 0
                ];

                $this->provisioningStep->updateStep($cdnResource->id, $step, 'waiting', 'Removal of cdn template in progress.');

                DeleteCdnTemplateJob::dispatch($request)->onQueue("cdn_delete_cdn_template");
            }
        } catch (Exception $e) {
            $this->logSys->syslog(
                '[CDN-API | Deprovisioning CDN] Ocorreu exceção no  método de inclusão de exclusão do cdn template na fila cdn_delete_cdn_template.' . $data['cdn_resource_hostname'],
                'ERRO : ' . $e->getMessage() . '. Parâmetros de recebidos: ' . json_encode($data) . ' JOB: DeleteCdnTemplateJob',
                'ERROR',
                $this->facilityLog . ':' . basename(__FUNCTION__)
            );
        }

    }

    public function deleteCdnTemplateReturn($data)
    {
        try {
            $cdnResource = $this->cdnResource->where('request_code', $data['request_code'])->first();
            $step = is_null($cdnResource->cdn_acme_lets_encrypt_id) ? 2 : 3;
            if ($data['code'] == 200) {
                $this->logSys->syslog(
                    '[CDN-API | Deprovisioning CDN]Exclusão do cdn template.' . $data['cdn_resource_hostname'] . ' efetuado com sucesso.',
                    'Parâmetros de recebidos: ' . json_encode($data) . ' JOB: DeleteCdnTemplateReturnJob',
                    'INFO',
                    $this->facilityLog . ':' . basename(__FUNCTION__)
                );
                $cdnResource->attempt_delete =0;
                $cdnResource->save();
                $this->provisioningStep->updateStep($cdnResource->id, $step, 'finished', 'Cdn route successfully removed.');
                $this->deleteCdnResource($data);
            } else {
                if ($cdnResource->attempt_delete < env('NUMBER_QUEUE_ATTEMPTS')) {
                    $cdnResource->attempt_delete = $cdnResource->attempt_delete + 1;
                    $cdnResource->save();
                    $this->deleteCdnTemplate($data);
                    $this->logSys->syslog(
                        '[CDN-API | Deprovisioning CDN] Ocorreu uma falha na exclusão do cdn template do CDN Resource' . $cdnResource->cdn_resource_hostname . ', incluso novamente na fila para efetuar a tentativa de provisionamento número ' . $cdnResource->attempt_create,
                        'Falha de exclusão do cdn template :' . json_encode($data),
                        'ERROR',
                        $this->facilityLog . ':' . basename(__FUNCTION__)
                    );
                    $this->provisioningStep->updateStep($cdnResource->id, $step, 'failed', 'CDN template removal playbook execution error.');
                } else {
                    $this->provisioningStep->updateStep($cdnResource->id, $step, 'failed', 'failure to removal the cdn template, attempts exceeded.');
                    $this->logSys->syslog(
                        '[CDN-API | Deprovisioning CDN] Ocorreu um na configuração do cdn template do CDN Resource' . $cdnResource->cdn_resource_hostname,
                        'Erro : ' . json_encode($data),
                        'ERROR',
                        $this->facilityLog . ':' . basename(__FUNCTION__)
                    );
                }
            }
        } catch (Exception $e) {
            $this->logSys->syslog(
                '[CDN-API | Deprovisioning CDN] Ocorreu exceção no  método retorno de exclusão do cdn template.' . $data['cdn_resource_hostname'],
                'ERRO : ' . $e->getMessage() . '. Parâmetros de recebidos: ' . json_encode($data) . ' JOB: DeleteCdnTemplateJob',
                'ERROR',
                $this->facilityLog . ':' . basename(__FUNCTION__)
            );
        }
    }
    public function deleteCdnResource($data)
    {
        try {
            $cdnResource = $this->cdnResource->where('request_code', $data['request_code'])->with('tenant')->first();

            $request = [
                "request_code" => $data['request_code'],
                "cdn_resource_hostname" => $cdnResource->cdn_resource_hostname,
                "api_key" => $cdnResource->tenant->api_key
            ];
            DeleteCdnResourceJob::dispatch($request)->onQueue("cdn_delete_cdn_resource");
        } catch (Exception $e) {
            $this->logSys->syslog(
                '[CDN-API | Deprovisioning CDN] Ocorreu exceção no  método retorno de exclusão do cdn template.' . $data['cdn_resource_hostname'],
                'ERRO : ' . $e->getMessage() . '. Parâmetros de recebidos: ' . json_encode($data) . ' JOB: DeleteCdnTemplateJob',
                'ERROR',
                $this->facilityLog . ':' . basename(__FUNCTION__)
            );
        }

    }

    public function deleteCdnResourceReturn($data)
    {
        try {
            $cdnResource = $this->cdnResource->where('request_code', $data['request_code'])->first();
            $step = is_null($cdnResource->cdn_acme_lets_encrypt_id) ? 3 : 4;
            if ($data['code'] == 200) {
                $this->logSys->syslog(
                    '[CDN-API | Deprovisioning CDN] Exclusão do CDN Resource.' . $data['cdn_resource_hostname'] . ' efetuado com sucesso.',
                    'Parâmetros de recebidos: ' . json_encode($data) . ' JOB: DeleteCdnTemplateReturnJob',
                    'INFO',
                    $this->facilityLog . ':' . basename(__FUNCTION__)
                );
                $this->provisioningStep->updateStep($cdnResource->id, $step, 'finished', 'Cdn route successfully removed.');
                // excluindo origin server single ou desatribuindo grupo de origin server ao resource
                $resourceOriginGroup =  new CdnOriginServerGroupRepository();
                $resourceOriginGroup->deleteSingle($cdnResource->id);
                //excluindo custon header se existir
                $custonHeader =  new CdnHeaderRepository();
                $custonHeader->deletebyResource($cdnResource->id);
                //excluindo dados da tabela cdn_resources
                $this->cdnResourcesRepository->deleteResouceData($data);
            } else {
                if ($cdnResource->attempt_delete < env('NUMBER_QUEUE_ATTEMPTS')) {
                    $cdnResource->attempt_delete = $cdnResource->attempt_delete + 1;
                    $cdnResource->save();
                    $this->deleteCdnResource($data);
                    $this->logSys->syslog(
                        '[CDN-API | Deprovisioning CDN] Ocorreu uma falha na exclusão do CDN Resource' . $cdnResource->cdn_resource_hostname . ', incluso novamente na fila para efetuar a tentativa de provisionamento número ' . $cdnResource->attempt_create,
                        'Falha de exclusão do cdn resource :' . json_encode($data),
                        'ERROR',
                        $this->facilityLog . ':' . basename(__FUNCTION__)
                    );
                    $this->provisioningStep->updateStep($cdnResource->id, $step, 'failed', 'CDN Resource removal playbook execution error.');
                } else {
                    $this->provisioningStep->updateStep($cdnResource->id, $step, 'failed', 'failure to removal the CDN Resource, attempts exceeded.');
                    $this->logSys->syslog(
                        '[CDN-API | Deprovisioning CDN] Ocorreu um na configuração do CDN Resource do CDN Resource' . $cdnResource->cdn_resource_hostname,
                        'Erro : ' . json_encode($data),
                        'ERROR',
                        $this->facilityLog . ':' . basename(__FUNCTION__)
                    );
                }
            }
        }  catch (Exception $e) {
            $this->logSys->syslog(
                '[CDN-API | Deprovisioning CDN] Ocorreu exceção no  método retorno de exclusão do cdn resource.' . $data['cdn_resource_hostname'],
                'ERRO : ' . $e->getMessage() . '. Parâmetros de recebidos: ' . json_encode($data) . ' JOB: DeleteCdnResourceJob',
                'ERROR',
                $this->facilityLog . ':' . basename(__FUNCTION__)
            );
        }
    }

}


