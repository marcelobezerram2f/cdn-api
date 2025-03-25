<?php

namespace App\Repositories;

use App\Models\CdnIngestPoint;
use App\Services\EventLogService;
use Exception;

class IngestPointRepository
{

    private $cdnIngestPoint;
    private $logSys;
    protected $facilityLog;

    public function __construct()
    {
        $this->cdnIngestPoint = new CdnIngestPoint();
        $this->logSys = new EventLogService();
        $this->facilityLog = basename(__FILE__);
    }


    public function getIngestPointId($ingestPointName)
    {
        $ingestPointId = $this->cdnIngestPoint->where('pop_prefix', strtoupper($ingestPointName))->first();
        return $ingestPointId->id;
    }


    public function getById($ingestPointId)
    {
        $ingestPoint = $this->cdnIngestPoint->find($ingestPointId);
        $response =  [
            'name' => $ingestPoint->name,
            'origin_central' => $ingestPoint->origin_central,
            'pop_prefix' => $ingestPoint->pop_prefix
        ];
        return $response;
    }


    public function getAll()
    {
        try {
            $ingestPoints = $this->cdnIngestPoint->all();

            $response = [];
            foreach ($ingestPoints as $ingestPoint) {
                $data = [
                    'id'=>$ingestPoint->id,
                    'name'=>$ingestPoint->name,
                    'origin_central'=>$ingestPoint->origin_central,
                    'pop_prefix'=>$ingestPoint->pop_prefix,
                ];
                array_push($response, $data);
                unset($data);

            }
        } catch (Exception $e) {
            $this->logSys->syslog(
                '[CDN-API | IngestPoint] Ocorreu uma exceção na recuperação da lista de Ingest Point',
                'ERRO : ' . $e->getMessage(),
                'ERROR',
                $this->facilityLog . ':' . basename(__FUNCTION__)
            );
            $response = [
                'message' => 'Fatal failure to list ingest point',
                'errors' => [$e->getMessage()],
                'code' => 400
            ];
        }

        return $response;
    }

    public function create($data)
    {
        try {
            $hastargetGroup = $this->cdnIngestPoint->where('name', $data['name'])->count();
            if($hastargetGroup>0) {
                return [
                    'message' => 'Alread exist '.strtoupper($data['name']). 'ingest point',
                    'errors' => ['Alread exist ingest point'],
                    'code' => 400
                ];
            }
            $this->cdnIngestPoint->create($data);
            $response = [
                'message' => strtoupper($data['name']). "ingest point saved successfully",
                'code' => 200
            ];
        } catch (Exception $e) {
            $this->logSys->syslog(
                '[CDN-API | TargetGroup] Ocorreu uma exceção na persistencia do ingest point'. strtoupper($data['name']),
                'ERRO : ' . $e->getMessage(),
                'ERROR',
                $this->facilityLog . ':' . basename(__FUNCTION__)
            );
            $response = [
                'message' => 'Fatal failure to save ingest point :'. strtoupper($data['name']),
                'errors' => [$e->getMessage()],
                'code' => 400
            ];
        }
        return $response;
    }

    public function delete($data)
    {
        try {
            $targetGroup = $this->cdnIngestPoint->where('name',$data['name'])->first();
            if($targetGroup){
                $targetGroup->delete();
                $response = [
                    'message' => strtoupper($data['name']). "ingest point deleted successfully",
                    'code' => 200
                ];
            } else {
                $response = [
                    'message' => "ingest point " .strtoupper($data['name']). " not existing",
                    'errors' => ["ingest point not existing in data base"],
                    'code' => 400
                ];
            }

        } catch (Exception $e) {
            $this->logSys->syslog(
                '[CDN-API | TargetGroup] Ocorreu uma exceção na exclusão do ingest point'. strtoupper($data['name']),
                'ERRO : ' . $e->getMessage(),
                'ERROR',
                $this->facilityLog . ':' . basename(__FUNCTION__)
            );
            $response = [
                'message' => 'Fatal failure to delete ingest point :'. strtoupper($data['name']),
                'errors' => [$e->getMessage()],
                'code' => 400
            ];
        }
        return $response;
    }
}
