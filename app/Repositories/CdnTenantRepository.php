<?php

namespace App\Repositories;

use App\Jobs\DeleteCdnTanantJob;
use App\Jobs\DeleteCdnTenantJob;
use App\Models\CdnClient;
use App\Models\CdnResource;
use App\Models\CdnTenant;
use App\Services\EventLogService;
use App\Services\UserIdFromTokenService;
use Exception;



class CdnTenantRepository
{

    private $cdnTenant;
    private $cdnResource;
    private $userIdFromTokenService;
    private $logSys;
    protected $facilityLog;
    private $template;
    private $ingestPoint;
    private $targetGroup;
    private $provisioningStep;




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
        $this->provisioningStep = new StepProvisioningRepository();
    }


    public function update($data)
    {

        try {
            $updateTenant = $this->cdnTenant->find($data['tenant_id']);
            $updateTenant->api_key = $data['api_key'];
            $updateTenant->save();

            $this->logSys->syslog(
                '[CDN-API | CdnResources] Inclusão da api_key do tenant' . $updateTenant->tenant . ' efetuada com sucesso!',
                null,
                'INFO',
                $this->facilityLog . ':' . basename(__FUNCTION__)
            );
            $response = [
                'code' => 200
            ];
        } catch (Exception $e) {
            $this->logSys->syslog(
                '[CDN-API | CdnResources] Ocorreu uma exceção na inclusão da api_key do tenant ' . $data['tenant'],
                'ERRO : ' . $e->getMessage(),
                'ERROR',
                $this->facilityLog . ':' . basename(__FUNCTION__)
            );
            $response = [
                'code' => 400
            ];
        }
        return $response;
    }

    public function allTenants($data)
    {
        try {
            $response = [];
            $user = $this->userIdFromTokenService->getUserDataFromToken($data['header']['token']);

            if ($user['user_type'] == 'admin') {
                $tenants = $this->cdnTenant->all();
            } else {
                $client = CdnClient::find($user['cdn_client_id']);
                $tenants = $this->cdnTenant->where('cdn_client_id', $user['cdn_client_id'])->get();
            }
            if (count($tenants) == 0) {
                $this->logSys->syslog(
                    '[CDN-API | CdnResources] Lista de tenant vazia!',
                    'Lista de tenants recuperada da tabela cdn_tenants retornou vazia',
                    'ERROR',
                    $this->facilityLog . ':' . basename(__FUNCTION__)
                );
                return [
                    'message' => 'No tenants in the database',
                    'errors' => ['No tenants registered in table cdn_tenants'],
                    'code' => 400
                ];
            }
            foreach ($tenants as $tenant) {
                $data = [
                    'id' => $tenant->id,
                    'tenant' => $tenant->tenant,
                    'tenant_name' => $client->name,
                    'account_id' => $client->account,
                    'description' => $tenant->description,
                    'api_key' => $tenant->api_key,
                    'target_group' => $this->targetGroup->getById($tenant->cdn_target_group_id)
                ];
                array_push($response, $data);
                unset($data);
            }
        } catch (Exception $e) {
            $this->logSys->syslog(
                '[CDN-API | CdnResources] Ocorreu uma exceção na recuperação da lista geral de  tenant',
                'ERRO : ' . $e->getMessage(),
                'ERROR',
                $this->facilityLog . ':' . basename(__FUNCTION__)
            );

            $response = [
                'message' => 'Fatal failure to tenant general list',
                'errors' => [$e->getMessage()],
                'code' => 400

            ];
        }
        return $response;
    }
    public function tenantByName($data)
    {
        try {
            if (is_null($data['tenant']) || empty($data['tenant']) || !isset($data['tenant'])) {
                return [
                    'code' => 400,
                    'message' => 'Warning!Invalidation of the data entered.',
                    'errors' => ['tenant must be informed .'],
                ];
            }

            $tenants = $this->cdnTenant->where('tenant', $data['tenant'])->with(['cdnResources.cdnResourceBlock', 'client'])->get();

            if (count($tenants) == 0) {
                $this->logSys->syslog(
                    '[CDN-API | CdnResources] Tenant consultado inexistente!',
                    'DADOS : ' . json_encode($data),
                    'ERROR',
                    $this->facilityLog . ':' . basename(__FUNCTION__)
                );
                return [
                    'message' => 'Tenant ' . strtoupper($data['tenant']) . ' does not exist in the database',
                    'errors' => ['Tenant not found in table cdn_tenants'],
                    'code' => 400
                ];
            }

            foreach ($tenants as $tenant) {
                $response = [
                    'id' => $tenant->id,
                    'tenant' => $tenant->tenant,
                    'tenant_name' => $tenant->client->name,
                    'description' => $tenant->description,
                    'api_key' => $tenant->api_key
                ];
                if (empty($tenant->cdnResources)) {
                    $resources = null;
                } else {
                    $resources = [];
                    foreach ($tenant->cdnResources as $cdnResource) {
                        $data_resource = [
                            'request_code' => $cdnResource->request_code,
                            'cdn_resource_hostname' => $cdnResource->cdn_resource_hostname,
                            'cdn_origin_hostname' => $cdnResource->cdn_origin_hostname,
                            'storage_id' => $cdnResource->storage_id,
                            'ssl' => is_null($cdnResource->cdn_acme_lets_encrypt_id) ? false : true,
                            'template' => $this->template->getTemplateName($cdnResource->cdn_template_id),
                            'cname' => cnameById($cdnResource->cdn_cname_id),
                            'cname_validate' => $cdnResource->cname_verify == 1 ? true : false,
                            'target_group' => $this->targetGroup->getById($cdnResource->cdn_target_group_id),
                            'ingest_point' => $this->ingestPoint->getById($cdnResource->cdn_ingest_point_id),
                            'provisioned' => $cdnResource->provisioned == 1 ? true : false,
                            'data_block' => is_null($cdnResource->cdn_resource_block_id) ? null : $cdnResource->cdnResourceBlock,
                            'marked_deletion' => $cdnResource->marked_deletion  == 1 ? true : false,
                            'description' => $cdnResource->description,
                        ];
                        array_push($resources, $data_resource);
                        unset($data_resource);
                    }
                }
                $response['cdn_resources'] = $resources;
            }
        } catch (Exception $e) {

            if (isset($data['tenant'])) {
                $tenant = $data['tenant'];
            } else
                $tenant = 'Chave tenant não informada na requisição';
            $this->logSys->syslog(
                '[CDN-API | CdnResources] Ocorreu uma exceção na recuperação da lista de cdn resource do tenant ' . $tenant,
                'DADOS : ' . json_encode($data) . ' - ERRO : ' . $e->getMessage(),
                'ERROR',
                $this->facilityLog . ':' . basename(__FUNCTION__)
            );

            $response = [
                'message' => 'Fatal failure to cdn resource list of tenant ',
                'errors' => [$e->getMessage()],
                'code' => 400

            ];
        }

        return $response;
    }


    public function tenantByClient($data)
    {
        try {

            if (is_null($data['client_id']) || empty($data['client_id']) || !isset($data['client_id'])) {
                return [
                    'code' => 400,
                    'message' => 'Warning!Invalidation of the data entered.',
                    'errors' => ['Client_id must be informed .'],
                ];
            }

            if (is_null($data['external_id']) || empty($data['external_id']) || !isset($data['external_id'])) {
                return [
                    'code' => 400,
                    'message' => 'Warning!Invalidation of the data entered.',
                    'error' => ['External_id must be informed .'],
                ];
            }

            $clientRepository = new ClientRepository();
            $client = $clientRepository->getClient($data);
            $tenants = $this->cdnTenant->where('cdn_client_id', $client['id'])->with('cdnResources')->get();
            if (count($tenants) == 0) {
                $this->logSys->syslog(
                    '[CDN-API | CdnResources] Tenant consultado inexistente!',
                    'DADOS : ' . json_encode($data),
                    'ERROR',
                    $this->facilityLog . ':' . basename(__FUNCTION__)
                );
                return [
                    'message' => 'Tenant ' . strtoupper($data['tenant']) . ' does not exist in the database',
                    'errors' => ['Tenant not found in table cdn_tenants'],
                    'code' => 400
                ];
            }

            foreach ($tenants as $tenant) {
                $response = [
                    'id' => $tenant->id,
                    'tenant' => $tenant->tenant,
                    'description' => $tenant->description,
                    'api_key' => $tenant->api_key
                ];
                if (empty($tenant->cdnResources)) {
                    $resources = null;
                } else {
                    $resources = [];
                    foreach ($tenant->cdnResources as $cdnResource) {
                        $data_resource = [
                            'cdn_resource_hostname' => $cdnResource->cdn_resource_hostname,
                            'cdn_origin_hostname' => $cdnResource->cdn_origin_hostname,
                            'storage_id' => $cdnResource->storage_id,
                            'template' => $this->template->getTemplateName($cdnResource->cdn_template_id),
                            'cname' => cnameById($cdnResource->cdn_cname_id),
                            'cname_validate' => $cdnResource->cname_verify == 1 ? true : false,
                            'target_group' => $this->targetGroup->getById($cdnResource->cdn_target_group_id),
                            'ingest_point' => $this->ingestPoint->getById($cdnResource->cdn_ingest_point_id),
                            'provisioned' => $cdnResource->provisioned == 1 ? true : false,
                            'description' => $cdnResource->description,
                        ];
                        array_push($resources, $data_resource);
                        unset($data_resource);
                    }
                }
                $response['cdn_resources'] = $resources;
            }
        } catch (Exception $e) {

            $this->logSys->syslog(
                '[CDN-API | CdnResources] Ocorreu uma exceção na recuperação da lista de cdn resource do tenant por identificação de cliente',
                'DADOS : ' . json_encode($data) . ' - ERRO : ' . $e->getMessage(),
                'ERROR',
                $this->facilityLog . ':' . basename(__FUNCTION__)
            );

            $response = [
                'message' => 'Fatal failure to tenant list of client ',
                'errors' => [$e->getMessage()],
                'code' => 400

            ];
        }

        return $response;
    }


    public function blockResource($data)
    {

        try {
            if (is_null($data['tenant']) || empty($data['tenant']) || !isset($data['tenant'])) {
                return [
                    'code' => 400,
                    'message' => 'Warning!Invalidation of the data entered.',
                    'errors' => ['tenant must be informed .'],
                ];
            }

            $tenants = $this->cdnTenant->where('tenant', $data['tenant'])->with(['cdnResources', 'client'])->get();

            if (count($tenants) == 0) {
                $this->logSys->syslog(
                    '[CDN-API | CdnResources] Tenant consultado inexistente!',
                    'DADOS : ' . json_encode($data),
                    'ERROR',
                    $this->facilityLog . ':' . basename(__FUNCTION__)
                );
                return [
                    'message' => 'Tenant ' . strtoupper($data['tenant']) . ' does not exist in the database',
                    'errors' => ['Tenant not found in table cdn_tenants'],
                    'code' => 400
                ];
            }

            foreach ($tenants as $tenant) {
                $response = [
                    'id' => $tenant->id,
                    'tenant' => $tenant->tenant,
                    'description' => $tenant->description,
                    'api_key' => $tenant->api_key
                ];
                if (empty($tenant->cdnResources)) {
                    $resources = null;
                } else {
                    $resources = [];
                    foreach ($tenant->cdnResources as $cdnResource) {
                        if (!is_null($cdnResource->storage_id)) {
                            $cdnResource = new CdnResourcesRepository();
                            $cdnResource->blockResource(['request_code' => $cdnResource->request_code]);
                            $data_resource = [
                                $cdnResource->cdn_resource_hostname,
                            ];
                            array_push($resources, $data_resource);
                            unset($data_resource);
                        }
                    }
                    $response['quantity_resources_blocked'] = count($resources);
                    $response['cdn_resources_blocked'] = $resources;
                }
            }
        } catch (Exception $e) {

            if (isset($data['tenant'])) {
                $tenant = $data['tenant'];
            } else
                $tenant = 'Chave tenant não informada na requisição';
            $this->logSys->syslog(
                '[CDN-API | CdnResources] Ocorreu uma exceção de bloqueio de cdn resource do tenant ' . $tenant,
                'DADOS : ' . json_encode($data) . ' - ERRO : ' . $e->getMessage(),
                'ERROR',
                $this->facilityLog . ':' . basename(__FUNCTION__)
            );

            $response = [
                'message' => 'Fatal failure when requesting to block cdn resources',
                'errors' => [$e->getMessage()],
                'code' => 400

            ];
        }
        return $response;
    }



    /**
     * Exclusão de CDN Resources e Tenant
     *
     */

     public function deleteTenant($data)
     {
         try {
             $tenant = $this->cdnTenant->where('tenant', $data['tenant'])->first();
             if ($tenant) {
                 $cdnResources = $this->cdnResource->where('cdn_tenant_id', $tenant->id)->where('cname_verify', 1)->with('provisioningStep')->get();
                 if (count($cdnResources) > 0) {
                     foreach ($cdnResources as $cdnResource) {
                         if ($cdnResource->provisioned == 1) {
                             return [
                                 'code' => 400,
                                 'message' => 'Tenant deletion failed.',
                                 'errors' => ['Warning! tenant cannot be deleted as there are related cdn resources.'],
                             ];
                         }
                     }
                 } else {
                     $tenant->attempt_delete = 1;
                     $tenant->save();
                     $request = [
                         'tenant_id' => $tenant->id,
                         'tenant' => $tenant->tenant
                     ];
                     try {
                         DeleteCdnTenantJob::dispatch($request)->onQueue('cdn_delete_tenant');
                         $response = [
                             'code' => 200,
                             'message' => 'Tenant exclusion successfully received. Wait for the deletion process.',
                         ];
                         $this->logSys->syslog(
                             '[CDN-API | CDN TENANT] Inclusão da exclusão de tenant na fila  cdn_tenant_delete efetuada.',
                             ' Parâmetros de inclusos: ' . json_encode($request) . ' JOB: DeleteCdnTanantJob',
                             'INFO',
                             $this->facilityLog . ':' . basename(__FUNCTION__)
                         );
                     } catch (Exception $e) {

                         $this->logSys->syslog(
                             '[CDN-API | CDN TENANT] Ocorreu exceção na inclusão da exclusão de tenant na fila de criação',
                             'ERRO : ' . $e->getMessage() . '. Parâmetros de recebidos: ' . json_encode($data) . ' Parametros de fila :' . json_encode($request) . ' JOB: DeleteCdnTanantJob',
                             'ERROR',
                             $this->facilityLog . ':' . basename(__FUNCTION__)
                         );
                     }
                 }
             }
         } catch (Exception $e) {
             $this->logSys->syslog(
                 '[CDN-API | CDN TENANT] Ocorreu exceção na inclusão de esclusão do cdn tenant.' . $data['tenant'] . ' na fila.',
                 'ERRO : ' . $e->getMessage() . '. Parâmetros de recebidos: ' . json_encode($data) . ' JOB: DeleteCdnTenantJob',
                 'ERROR',
                 $this->facilityLog . ':' . basename(__FUNCTION__)
             );
             $response = [
                 'code' => 400,
                 'message' => 'fatal failure occurred when deleting the tenant.',
                 'errors' => ['Warning! Fatal failure occurred when deleting the tenant.', $e->getMessage()],
             ];
         }
         return $response;
     }


     public function deleteTenantReturn($data)
     {
         try {
             $deleteCdnTenant = $this->cdnTenant->find($data['tenant_id']);
             if ($data['code'] == 200) {
                 $deleteCdnTenant->delete();
                 $cdnResources = $this->cdnResource->where('cdn_tenant_id', $data['tenant_id'])
                     ->where('cname_verify', 0)
                     ->where('provisioned', 0)
                     ->get();
                 if ($cdnResources) {
                     foreach ($cdnResources as $cdnResource) {
                         $cdnSteps = $this->provisioningStep->deleteSteps($cdnResource->id, $cdnResource->cdn_resource_hostname, $data['tenant']);
                         if ($cdnSteps['code'] == 200) {
                             $cdnResource->delete();
                         } else {
                             //ponto de falha que deve ser notificada.
                             $this->logSys->syslog(
                                 '[CDN-API | CDN TENANT] Ocorreu uma falha  na exclusão dos status de provisionamento do resource .' . $cdnResource->cdn_resource_hostname . ' cdn resource ' . $cdnResource->cdn_resource_hostname,
                                 'ERRO : ' . json_encode($cdnSteps) . '. Parâmetros de recebidos: ' . json_encode($data) . ' JOB: DeleteCdnTenantReturnJob',
                                 'ERROR',
                                 $this->facilityLog . ':' . basename(__FUNCTION__)
                             );
                         }
                     }
                 }
             } else {
                 if ($deleteCdnTenant->attempt_delete < env('NUMBER_QUEUE_ATTEMPTS')) {
                     $deleteCdnTenant->attempt_delete = $deleteCdnTenant->attempt_delete + 1;
                     $deleteCdnTenant->save();
                     $this->logSys->syslog(
                         '[CDN-API | CDN TENANT] Ocorreu uma falha na exclusão do tenant' . $deleteCdnTenant->tenant . ', incluso novamente na fila para efetuar a tentativa de exclusão número ' . $deleteCdnTenant->attempt_delete,
                         'Falha de provisionamento do tenant :' . json_encode($data),
                         'ERROR',
                          $this->facilityLog . ':' . basename(__FUNCTION__)
                     );
                     $this->deleteTenant($data);
                 } else {
                     $this->logSys->syslog(
                         '[CDN-API | CDN TENANT] Ocorreu uma falha na exclusão do tenant' . $deleteCdnTenant->tenant . ', número de tentativa de provisionamento número  excedido .',
                         'Falha de exclusão do tenant :' . json_encode($data),
                         'ERROR',
                         $this->facilityLog . ':' . basename(__FUNCTION__)
                     );
                 }
             }
         } catch (Exception $e) {
             $this->logSys->syslog(
                 '[CDN-API | CDN TENANT] Ocorreu exceção na exclusão do cdn tenant.' . $data['tenant'],
                 'ERRO : ' . $e->getMessage() . '. Parâmetros de recebidos: ' . json_encode($data) . ' JOB: DeleteCdnTenantReturnJob',
                 'ERROR',
                 $this->facilityLog . ':' . basename(__FUNCTION__)
             );
         }
     }
}
