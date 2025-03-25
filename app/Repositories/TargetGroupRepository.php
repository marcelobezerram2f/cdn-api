<?php

namespace App\Repositories;

use App\Models\CdnTargetGroup;
use App\Services\EventLogService;
use Exception;

class TargetGroupRepository
{

    private $cdnTargetGroup;
    private $logSys;
    protected $facilityLog;

    public function __construct()
    {
        $this->cdnTargetGroup = new CdnTargetGroup();
        $this->logSys = new EventLogService();
        $this->facilityLog = basename(__FILE__);
    }

    public function getAll()
    {
        try {
            $targetGroups = $this->cdnTargetGroup->all();
            $response = [];
            foreach ($targetGroups as $targetGroup) {

                $data = [
                    "id" => $targetGroup->id,
                    "name" => $targetGroup->name,
                    "plan"=> $targetGroup->plan
                ];
                array_push($response, $data);
                unset($data);
            }
        } catch (Exception $e) {
            $this->logSys->syslog(
                '[CDN-API | TargetGroup] Ocorreu uma exceção na recuperação da lista de target groups',
                'ERRO : ' . $e->getMessage(),
                'ERROR',
                $this->facilityLog . ':' . basename(__FUNCTION__)
            );
            $response = [
                'message' => 'Fatal failure to list target groups',
                'error' => $e->getMessage(),
                'code' => 400
            ];
        }

        return $response;
    }

    public function getTargetGroupId($targetGroupName)
    {
        $targetGroupId = $this->cdnTargetGroup->where('plan', $targetGroupName)->first();
        return $targetGroupId->id;
    }

    public function create($data)
    {
        try {
            $hastargetGroup = $this->cdnTargetGroup->where('name', $data['name'])->count();
            if($hastargetGroup>0) {
                return [
                    'message' => 'Alread exist '.strtoupper($data['name']). 'target group',
                    'error' => 'Alread exist target group',
                    'code' => 400
                ];
            }
            $this->cdnTargetGroup->create($data);
            $response = [
                'message' => strtoupper($data['name']). "target group saved successfully",
                'code' => 200
            ];
        } catch (Exception $e) {
            $this->logSys->syslog(
                '[CDN-API | TargetGroup] Ocorreu uma exceção na persistencia do target group'. strtoupper($data['name']),
                'ERRO : ' . $e->getMessage(),
                'ERROR',
                $this->facilityLog . ':' . basename(__FUNCTION__)
            );
            $response = [
                'message' => 'Fatal failure to save target group :'. strtoupper($data['name']),
                'error' => $e->getMessage(),
                'code' => 400
            ];
        }
        return $response;
    }

    public function getById($targetGroupId)
    {
        $targetGroup = $this->cdnTargetGroup->find($targetGroupId);
        $response  =  [
            'name' =>$targetGroup->name,
            'plan' => $targetGroup->plan
        ];
        return $response;
    }

    public function delete($data)
    {
        try {
            $targetGroup = $this->cdnTargetGroup->where('name',$data['name'])->first();
            if($targetGroup){
                $targetGroup->delete();
                $response = [
                    'message' => strtoupper($data['name']). "target group deleted successfully",
                    'code' => 200
                ];
            } else {
                $response = [
                    'message' => "Target group " .strtoupper($data['name']). " not existing",
                    'error' => "Target group not found!",
                    'code' => 400
                ];
            }

        } catch (Exception $e) {
            $this->logSys->syslog(
                '[CDN-API | TargetGroup] Ocorreu uma exceção na exclusão do target group'. strtoupper($data['name']),
                'ERRO : ' . $e->getMessage(),
                'ERROR',
                $this->facilityLog . ':' . basename(__FUNCTION__)
            );
            $response = [
                'message' => 'Fatal failure to delete target group :'. strtoupper($data['name']),
                'error' => $e->getMessage(),
                'code' => 400
            ];
        }
        return $response;
    }



}
