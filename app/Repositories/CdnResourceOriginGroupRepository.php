<?php

namespace App\Repositories;

use App\Models\CdnOriginGroup;
use App\Models\CdnOriginServer;
use App\Models\CdnResource;
use App\Models\CdnResourceOriginGroup;
use Exception;
use App\Services\EventLogService;
use Illuminate\Support\Facades\Log;


class CdnResourceOriginGroupRepository
{

    private $logSys;
    protected $facilityLog;

    public function __construct()
    {

        $this->logSys = new EventLogService();
        $this->facilityLog = basename(__FILE__);

    }
    public function create($data, $resourceId)
    {
        try {
            Log::info("Atribuindo um grupo de servidores : dados ->" . json_encode($data) . " Resource : ". $resourceId);
            for ($i = 0; $i < count($data); $i++) {
                CdnResourceOriginGroup::create([
                    'cdn_resource_id' => $resourceId,
                    'cdn_origin_group_id' => $data[$i],
                    'state' => $i == 0 ? 'active' : 'fail-over'
                ]);

                $this->logSys->syslog(
                    "[CDN-API | ResourceOriginServeGroup CDN] Atribuindo um grupo de servidores de origem ao resource ",
                    "Group Data  :" . json_encode($data) ,
                    'INFO',
                    $this->facilityLog . ':' . basename(__FUNCTION__)
                );
            }

            return ['code' => 200];
        } catch (Exception $e) {
            $this->logSys->syslog(
                "[CDN-API | ResourceOriginServeGroup CDN] Falha fatal na atribuição de Resource ao Grupo de Origin server",
                "Tenant Data  :" . json_encode($data) . " ERROR : " . $e->getMessage(),
                'ERROR',
                $this->facilityLog . ':' . basename(__FUNCTION__)
            );
            return [
                "code" => 400,
                "message" => "Fatal failure when assigning origin server",
                "errors" => [$e->getMessage()],
            ];
        }

    }

    public function getOriginGroups($resourceId)
    {
        $serverGroups = CdnResourceOriginGroup::where('cdn_resource_id', $resourceId)->get();
        $serverGroupsIds = [];
        foreach ($serverGroups as $serverGroup) {
            array_push($serverGroupsId, $serverGroup->cdn_server_group_id);
        }


    }

    public function updateResourceOriginGroup($data, $resourceId)
    {
        $this->deleteResource($resourceId);
        $this->create($data, $resourceId);
    }


    public function deleteResource($resourceId)
    {
        try {
            $resourceGroups = CdnResourceOriginGroup::where('cdn_resource_id', $resourceId)->get();
            if ($resourceGroups) {
                foreach ($resourceGroups as $resourceGroup) {
                    $resourceGroup->delete();
                    $this->logSys->syslog(
                        "[CDN-API | ResourceOriginServeGroup CDN] Removendo atribuição de grupo de servidores de origem ao resource ",
                        "ID do Resource  :" . json_encode($resourceId) ,
                        'INFO',
                        $this->facilityLog . ':' . basename(__FUNCTION__)
                    );

                }
            }
        } catch (Exception $e) {

            $this->logSys->syslog(
                "[CDN-API | ResourceOriginServeGroup CDN] Falha fatal na exclusão de atribuição de Resource ao Grupo de Origin server",
                "Id do Resouurce  :" . $resourceId . " ERROR : " . $e->getMessage(),
                'ERROR',
                $this->facilityLog . ':' . basename(__FUNCTION__)
            );

            return [
                "code" => 400,
                "message" => "Fatal failure when removing all origin server assignment",
                "errors" => [$e->getMessage()],
            ];
        }
    }

    public function deleteGroup($data, $resourceId)
    {
        try {
            for ($i = 0; $i < count($data); $i++) {
                CdnResourceOriginGroup::where([
                    'cdn_resource_id' => $resourceId,
                    'cdn_origin_group' => $data[$i]
                ])->delete();

            }
        } catch (Exception $e) {
            $this->logSys->syslog(
                "[CDN-API | ResourceOriginServeGroup CDN] Falha fatal na exclusão de atribuição de Resource ao Grupo de Origin server",
                "Id do Resouurce  :" . $resourceId . "dados : " . json_encode($data) . " ERROR : " . $e->getMessage(),
                'ERROR',
                $this->facilityLog . ':' . basename(__FUNCTION__)
            );
            return [
                "code" => 400,
                "message" => "Fatal failure when removing origin server assignment",
                "errors" => [$e->getMessage()],
            ];
        }
    }
}
