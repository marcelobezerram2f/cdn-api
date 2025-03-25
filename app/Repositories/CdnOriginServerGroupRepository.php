<?php

namespace App\Repositories;

use App\Models\CdnOriginGroup;
use App\Models\CdnOriginServer;
use App\Models\CdnResource;
use App\Models\CdnResourceOriginGroup;
use App\Models\CdnTenant;
use Exception;
use App\Services\EventLogService;

class CdnOriginServerGroupRepository
{

    private $cdnOriginGroup;
    private $cdnOriginServe;
    private $cdnResourceOriginGroup;
    private $cdnTenant;
    private $logSys;
    protected $facilityLog;

    public function __construct()
    {
        $this->cdnOriginGroup = new CdnOriginGroup();
        $this->cdnOriginServe = new CdnOriginServer();
        $this->cdnResourceOriginGroup = new CdnResourceOriginGroup();
        $this->cdnTenant = new CdnTenant();
        $this->logSys = new EventLogService();
        $this->facilityLog = basename(__FILE__);

    }
    public function getByTenant($data)
    {
        try {

            $tenant = $this->cdnTenant->where('tenant', $data['tenant'])->first();

            $groupServers = $this->cdnOriginGroup
                ->where('cdn_tenant_id', $tenant->id)
                ->with('originServers')
                ->get();

            if ($groupServers) {
                return $groupServers->map(function ($groupServer) {
                    return [
                        "cdn_origin_group_id" => $groupServer->id,
                        "group_name" => $groupServer->group_name,
                        "group_description" => $groupServer->group_description,
                        "cdn_tenant_id" => $groupServer->cdn_tenant_id,
                        "type" => $groupServer->type,
                        "origin_servers" => $groupServer->originServers->map(function ($originServer) {
                            return [
                                'cdn_origin_server_id' => $originServer->id,
                                'cdn_origin_hostname' => $originServer->cdn_origin_hostname,
                                'cdn_origin_protocol' => $originServer->cdn_origin_protocol,
                                'cdn_origin_server_port' => $originServer->cdn_origin_server_port,
                                'type' => $originServer->type
                            ];
                        })->toArray(),
                    ];
                })->toArray();
            } else {
                return [];
            }

        } catch (Exception $e) {
            $this->logSys->syslog(
                "[CDN-API | OriginServeGroup CDN] Falha fatal na coleta de  Grupo de Origin server por tenant " . $data['tenant'],
                "Tenant Data  :" . json_encode($data) . " ERROR : " . $e->getMessage(),
                'ERROR',
                $this->facilityLog . ':' . basename(__FUNCTION__)
            );
            return [
                "code" => 400,
                "message" => "Fatal failure in the query of the origin server group",
                "errors" => [$e->getMessage()],
            ];
        }
    }
    public function create($data)
    {
        try {

            if (empty($data['origin_servers'])) {
                return [
                    "code" => 400,
                    "message" => "Origin server group cannot be saved empty",
                    "errors" => ["Origin servers not found in the list"]
                ];
            }

            $tenant = $this->cdnTenant->where('tenant', $data['tenant'])->first();

            $hasGroup = $this->cdnOriginGroup->where('group_name', $data['group_name'])
                ->where('cdn_tenant_id', $tenant->id)
                ->count();


            if ($hasGroup != 0) {

                return [
                    "code" => 400,
                    "message" => "There is a group of origin servers with the given name, choose another name ",
                    "errors" => ["Source server group name duplication alert"]
                ];
            }
            $group = $this->cdnOriginGroup->create([
                'group_name' => $data['group_name'],
                'group_description' => $data['group_description'],
                'type' => $data['type'],
                'cdn_tenant_id' => $tenant->id,
            ]);


            $originServers = collect($data['origin_servers'])->map(function ($originServer, $index) use ($group) {
                return $this->cdnOriginServe->create([
                    'cdn_origin_hostname' => $originServer['cdn_origin_hostname'],
                    'cdn_origin_protocol' => $originServer['cdn_origin_protocol'],
                    'cdn_origin_server_port' => $originServer['cdn_origin_server_port'],
                    'type' => $index === 0 ? 'main' : 'additional',
                    'cdn_origin_group_id' => $group->id,
                ]);
            })->toArray();


            return [
                "cdn_origin_group_id" => $group->id,
                "group_name" => $group->group_name,
                "group_description" => $group->group_description,
                "cdn_origin_servers" => $originServers,
                "message" => "Origin server group persisted successfully",
            ];

        } catch (Exception $e) {
            $this->logSys->syslog(
                "[CDN-API | OriginServeGroup CDN] Falha fatal na inclusão de Grupo Origin server por tenant " . $data['tenant'],
                "Tenant Data  :" . json_encode($data) . " ERROR : " . $e->getMessage(),
                'ERROR',
                $this->facilityLog . ':' . basename(__FUNCTION__)
            );

            return [
                "code" => 400,
                "message" => "Fatal failure in the persistence of the origin server group",
                "errors" => [$e->getMessage()],
            ];
        }
    }

    public function addOriginServer($data, $group)
    {

        try {
            /*if (isset($data['type']) && $data['type'] === 'main') {
                 $existingMain = $this->cdnOriginServe->where('type', 'main')->where('cdn_origin_group_id', $group->id)->first();
                 if ($existingMain) {
                     $existingMain->update(['type' => 'additional']);
                 }
             }*/
            $originServer = $this->cdnOriginServe->create([
                'cdn_origin_hostname' => $data['cdn_origin_hostname'],
                'cdn_origin_protocol' => $data['cdn_origin_protocol'],
                'cdn_origin_server_port' => $data['cdn_origin_server_port'],
                //'type' => !is_null($data['type']) ? $data['type'] : 'additional',
                'type' => 'additional',
                'cdn_origin_group_id' => $group->id,
            ]);
            return $originServer;
        } catch (Exception $e) {
            $this->logSys->syslog(
                "[CDN-API | OriginServeGroup CDN] Falha fatal na inclusão de  Origin server no grupo ",
                "Data  :" . json_encode($data) . " ERROR : " . $e->getMessage(),
                'ERROR',
                $this->facilityLog . ':' . basename(__FUNCTION__)
            );
            return [
                "code" => 400,
                "message" => "Fatal failure in the persistence of the origin server",
                "errors" => [$e->getMessage()],
            ];
        }

    }
    public function getById($data)
    {
        try {
            $groupServers = $this->cdnOriginGroup
                ->where('id', $data['cdn_origin_group_id'])
                ->with('originServers')
                ->get();

            return $groupServers->map(function ($groupServer) {
                return [
                    "group_name" => $groupServer->group_name,
                    "group_description" => $groupServer->group_description,
                    "cdn_tenant_id" => $groupServer->cdn_tenant_id,
                    "origin_servers" => $groupServer->originServers->map(function ($originServer) {
                        return [
                            'cdn_origin_server_id' => $originServer->id,
                            'cdn_origin_hostname' => $originServer->cdn_origin_hostname,
                            'cdn_origin_protocol' => $originServer->cdn_origin_protocol,
                            'cdn_origin_server_port' => $originServer->cdn_origin_server_port,
                            'type' => $originServer->type,

                        ];
                    })->toArray(),
                ];
            })->toArray();

        } catch (Exception $e) {
            $this->logSys->syslog(
                "[CDN-API | OriginServeGroup CDN] Falha fatal na coleta de  Origin server no grupo por ID",
                "Data  :" . json_encode($data) . " ERROR : " . $e->getMessage(),
                'ERROR',
                $this->facilityLog . ':' . basename(__FUNCTION__)
            );
            return [
                "code" => 400,
                "message" => "Fatal failure in the query of the origin server group",
                "errors" => [$e->getMessage()],
            ];
        }
    }


    public function getForTemplate($data)
    {
        try {

            for ($i = 0; $i < count($data); $i++) {
                $groupServers = $this->cdnOriginGroup
                    ->where('id', $data[$i])
                    ->with('originServers')
                    ->get();

            }

            return $groupServers->map(function ($groupServer) {
                return [
                    "group_name" => $groupServer->group_name,
                    "cdn_tenant_id" => $groupServer->cdn_tenant_id,
                    "origin_servers" => $groupServer->originServers->map(function ($originServer) {
                        return [
                            'cdn_origin_server_id' => $originServer->id,
                            'cdn_origin_hostname' => $originServer->cdn_origin_hostname,
                            'cdn_origin_protocol' => $originServer->cdn_origin_protocol,
                            'cdn_origin_server_port' => $originServer->cdn_origin_server_port,
                            'type' => $originServer->type,

                        ];
                    })->toArray(),
                ];
            })->toArray();

        } catch (Exception $e) {
            $this->logSys->syslog(
                "[CDN-API | OriginServeGroup CDN] Falha fatal na coleta de  Origin server no grupo  para template",
                "Data  :" . json_encode($data) . " ERROR : " . $e->getMessage(),
                'ERROR',
                $this->facilityLog . ':' . basename(__FUNCTION__)
            );
            return [
                "code" => 400,
                "message" => "Fatal failure in the query of the origin server group",
                "errors" => [$e->getMessage()],
            ];
        }
    }


    public function getOriginServers($data)
    {
        $originServer = $this->cdnOriginServe->where('cdn_origin_group_id', $data['cdn_origin_server_id'])->get();
        foreach ($originServer as $server) {
            $reponse = [
                'cdn_origin_server_id' => $server->id,
                'cdn_origin_hostname' => $server->cdn_origin_hostname,
                'cdn_origin_protocol' => $server->cdn_origin_protocol,
                'cdn_origin_server_port' => $server->cdn_origin_server_port,
                'type' => $server->type
            ];
        }
        return $reponse;
    }


    public function getOriginServerMain($cdnResourceId)
    {
        $response = [];

        $originGroups = $this->cdnResourceOriginGroup
            ->where('cdn_resource_id', $cdnResourceId)
            ->where('state', 'active')
            ->get();
        foreach ($originGroups as $group) {
            $originServers = $this->cdnOriginServe
                ->where('cdn_origin_group_id', $group->cdn_origin_group_id)
                ->where('type', 'main')
                ->get();
            foreach ($originServers as $server) {
                $response[] = [
                    'cdn_origin_server_id' => $server->id,
                    'cdn_origin_hostname' => $server->cdn_origin_hostname,
                    'cdn_origin_protocol' => $server->cdn_origin_protocol,
                    'cdn_origin_server_port' => $server->cdn_origin_server_port,
                    'type' => $server->type
                ];
            }
        }
        return $response;
    }


    public function updateGroup($data)
    {
        try {
            $hasAllocated = CdnResourceOriginGroup::where('cdn_origin_group_id', $data['cdn_origin_group_id'])->get();
            if ($hasAllocated != 0) {
                return [
                    "code" => 400,
                    "message" => "There are one or more cdn resources allocating the origin server group",
                    "errors" => ["Update from origin server group not allowed"]
                ];
            } else {

                $group = $this->cdnOriginGroup->findOrFail($data['cdn_origin_group_id']);
                // Check for duplicate group name within the same tenant
                if (
                    $this->cdnOriginGroup->where('group_name', $data['group_name'])
                        ->where('cdn_tenant_id', $group->cdn_tenant_id)
                        ->where('id', '!=', $group->id) // Exclude the current group from the check
                        ->exists()
                ) {
                    return [
                        "code" => 400,
                        "message" => "There is a group of origin servers with the given name, choose another name",
                        "errors" => ["Source server group name duplication alert"],
                    ];
                }

                $group->update(['group_name' => $data['group_name'], 'group_description' => $data['group_description']]);

                $errors = [];
                foreach ($data['origin_servers'] as $originServer) {

                    $result = isset($originServer['cdn_origin_server_id'])
                        ? $this->updateOriginServer($originServer)
                        : $this->addOriginServer($originServer, $group);

                    if (isset($result['errors']) && is_array($result['errors'])) {
                        $errors = array_merge($errors, $result['errors']);
                    }
                }
                return [
                    "message" => "Update of the origin server group successfully completed",
                    "errors" => empty($errors) ? [] : $errors,
                ];
            }

        } catch (Exception $e) {
            $this->logSys->syslog(
                "[CDN-API | OriginServeGroup CDN] Falha fatal na alteração de  Origin server no grupo ",
                "Data  :" . json_encode($data) . " ERROR : " . $e->getMessage(),
                'ERROR',
                $this->facilityLog . ':' . basename(__FUNCTION__)
            );
            return [
                "code" => 400,
                "message" => "Fatal failure in the update of the origin server group",
                "errors" => [$e->getMessage()],
            ];
        }
    }

    public function updateOriginServer($data)
    {
        try {

            //recupera o
            $originServer = $this->cdnOriginServe->find($data['cdn_origin_server_id']);

            /*if (isset($data['type']) && $data['type'] === 'main') {
                $existingMain = $this->cdnOriginServe->where('type', 'main')->where('id', $data['cdn_origin_server_id'])->first();
                if ($existingMain) {
                    $existingMain->update(['type' => 'additional']);
                }
            }*/
            $originServer->update($data);
            $response = [
                "message" => "Update of the origin server successfully completed",
                "origin_server" => [
                    'cdn_origin_server_id' => $originServer->id,
                    'cdn_origin_hostname' => $originServer->cdn_origin_hostname,
                    'cdn_origin_protocol' => $originServer->cdn_origin_protocol,
                    'cdn_origin_server_port' => $originServer->cdn_origin_server_port,
                    'type' => $originServer->type
                ]
            ];

        } catch (Exception $e) {
            $this->logSys->syslog(
                "[CDN-API | OriginServeGroup CDN] Falha fatal na alteração de  Origin server no grupo ",
                "Data  :" . json_encode($data) . " ERROR : " . $e->getMessage(),
                'ERROR',
                $this->facilityLog . ':' . basename(__FUNCTION__)
            );
            $response = [
                "code" => 400,
                "message" => "Fatal failure in the update of the origin server",
                "errors" => [$e->getMessage()],
            ];
        }
        return $response;
    }

    public function deleteGroup($data)
    {
        try {

            $hasAllocated = CdnResourceOriginGroup::where('cdn_origin_group_id', $data['cdn_origin_group_id'])->count();
            if ($hasAllocated != 0) {
                return [
                    "code" => 400,
                    "message" => "There are one or more cdn retources allocating the origin server group",
                    "errors" => ["Exclusion from origin server group not allowed"]
                ];
            } else {
                $originServers = $this->cdnOriginServe->where('cdn_origin_group_id', $data['cdn_origin_group_id'])->delete();
                // if (!is_null($originServers)) {
                //     $originServers->delete();
                // }
                $group = $this->cdnOriginGroup->find($data['cdn_origin_group_id']);
                $group->delete();
                $response = [
                    "message" => "Successful deletion of origin server group",
                ];
            }
        } catch (Exception $e) {
            $this->logSys->syslog(
                "[CDN-API | OriginServeGroup CDN] Falha fatal na exclusão de  Origin server no grupo ",
                "Data  :" . json_encode($data) . " ERROR : " . $e->getMessage(),
                'ERROR',
                $this->facilityLog . ':' . basename(__FUNCTION__)
            );
            $response = [
                "code" => 400,
                "message" => "Fatal failure when deleting the origin server group ",
                "errors" => [$e->getMessage()]
            ];
        }

        return $response;
    }

    public function deleteOriginServer($data)
    {
        try {
            $originServer = $this->cdnOriginServe->find($data['cdn_origin_server_id']);

            $hasAllocated = CdnResourceOriginGroup::where('cdn_origin_group_id', $originServer->cdn_origin_group_id)->count();
            if ($hasAllocated != 0 || $originServer->type == "main") {
                return [
                    "code" => 400,
                    "message" => "The origin server is allocated  or  is main server , set another server as the main before deleting the origin server.",
                    "errors" => ["Exclusion from origin server not allowed"]
                ];
            } else {
                $originServer->delete();
                $response = [
                    "message" => "Successful deletion of origin server",
                ];
            }
        } catch (Exception $e) {
            $this->logSys->syslog(
                "[CDN-API | OriginServeGroup CDN] Falha fatal na exclusão de  Origin server no grupo ",
                "Data  :" . json_encode($data) . " ERROR : " . $e->getMessage(),
                'ERROR',
                $this->facilityLog . ':' . basename(__FUNCTION__)
            );
            $response = [
                "code" => 400,
                "message" => "Fatal failure when deleting the origin server",
                "errors" => [$e->getMessage()]
            ];
        }
        return $response;
    }

    public function rollback($groupName)
    {
        $originGroup = $this->cdnOriginGroup->where('group_name', $groupName)->first();
        $originServe = $this->cdnOriginServe->where('cdn_origin_group_id', $originGroup->id)->first();
        $originServe->delete();
        $originGroup->delete();
    }


    public function updateCdnResource(array $updateData, int $resourceId)
    {
        try {
            // Verificar a quantidade de ocorrências do resourceId na tabela cdn_resource_origin_groups
            $resourceOriginGroups = CdnResourceOriginGroup::where('cdn_resource_id', $resourceId)->get();

            if ($resourceOriginGroups->count() === 1) {
                $originGroup = CdnOriginGroup::find($resourceOriginGroups->first()->cdn_origin_group_id);

                if ($originGroup) {
                    if ($originGroup->type === 'single') {
                        // Apagar o registro da tabela cdn_origin_servers
                        CdnOriginServer::where('cdn_origin_group_id', $originGroup->id)->delete();
                        // Apagar o registro da tabela cdn_origin_groups
                        $originGroup->delete();
                    }

                    // Apagar o(s) registro(s) da tabela cdn_resource_origin_groups
                    CdnResourceOriginGroup::where('cdn_resource_id', $resourceId)->delete();
                }
            }

            // Atualizar ou criar grupos de origem conforme o updateData
            $cdnResourceOriginGroupRepository = new CdnResourceOriginGroupRepository();

            if (empty($updateData['cdn_origin_group_id'])) {
                // Criar um novo grupo de origem
                $cdnResource = CdnResource::find($resourceId);
                $tenant = CdnTenant::find($cdnResource->cdn_tenant_id);

                $originGroup = [
                    "group_name" => $cdnResource->cdn_resource_hostname,
                    'group_description' => 'Grupo de servidores de origem exclusivo para o cdn resource ' . $updateData['cdn_resource_hostname'],
                    "type" => "single",
                    "cdn_tenant_id" => $tenant->id,
                    "tenant" => $tenant->tenant,
                    "origin_servers" => [
                        [
                            "cdn_origin_hostname" => $updateData['cdn_origin_hostname'],
                            "cdn_origin_protocol" => $updateData['cdn_origin_protocol'],
                            "cdn_origin_server_port" => $updateData['cdn_origin_server_port']
                        ]
                    ]
                ];

                $serverGroup = $this->create($originGroup);
                $cdnResourceOriginGroupRepository->updateResourceOriginGroup([$serverGroup['cdn_origin_group_id']], $resourceId);
            } else {
                // Atualizar os grupos de origem
                $cdnResourceOriginGroupRepository->updateResourceOriginGroup($updateData['cdn_origin_group_id'], $resourceId);
            }
        } catch (Exception $e) {
            $this->logSys->syslog(
                "[CDN-API | OriginServeGroup CDN] Falha fatal no update de  Origin server no grupo ",
                "Data  :" . json_encode($updateData) . " ERROR : " . $e->getMessage(),
                'ERROR',
                $this->facilityLog . ':' . basename(__FUNCTION__)
            );
        }
    }


    public function deleteSingle($resourceId)
    {
        try {
            $resourceOriginGroups = CdnResourceOriginGroup::where('cdn_resource_id', $resourceId)->get();

            if ($resourceOriginGroups->count() === 1) {
                $originGroup = CdnOriginGroup::find($resourceOriginGroups->first()->cdn_origin_group_id);

                if ($originGroup) {
                    if ($originGroup->type === 'single') {
                        CdnOriginServer::where('cdn_origin_group_id', $originGroup->id)->delete();
                        $originGroup->delete();
                    }
                }
            }

            CdnResourceOriginGroup::where('cdn_resource_id', $resourceId)->delete();
        } catch (Exception $e) {
            $this->logSys->syslog(
                "[CDN-API | OriginServeGroup CDN] Falha fatal ao excluir de  Origin server no grupo ",
                "Data  resource ID :" . json_encode($resourceId) . " ERROR : " . $e->getMessage(),
                'ERROR',
                $this->facilityLog . ':' . basename(__FUNCTION__)
            );
        }

    }

}
