<?php

namespace App\Repositories;

use App\Models\CdnHeader;
use App\Models\CdnResource;
use Exception;
use App\Services\EventLogService;


class CdnHeaderRepository
{



    private $cdnHeader;

    private $logSys;
    protected $facilityLog;

    public function __construct()
    {
        $this->cdnHeader = new CdnHeader();
        $this->logSys = new EventLogService();
        $this->facilityLog = basename(__FILE__);
    }


    public function create($data, $resourceId)
    {
        try {
            foreach ($data as $header) {
                $this->cdnHeader->create([
                    'cdn_resource_id' => $resourceId,
                    'name' => $header['name'],
                    'value' => $header['value']
                ]);
            }
            $this->logSys->syslog(
                "[CDN-API | HEADER CDN] Custon Header incluso com sucesso no CDN Resource de ID $resourceId",
                "Custon header incluso :" . json_encode($data),
                'INFO',
                $this->facilityLog . ':' . basename(__FUNCTION__)
            );
            return ['code' => 200];

        } catch (Exception $e) {

            $this->logSys->syslog(
                "[CDN-API | HEADER CDN] Falha fatal a inclusão de custon header no CDN Resource de ID $resourceId",
                "Custon header  :" . json_encode($data) . " ERROR : " . $e->getMessage(),
                'ERROR',
                $this->facilityLog . ':' . basename(__FUNCTION__)
            );
            return [
                'message' => 'Fatal header persistence failure',
                'errors' => [$e->getMessage()],
                'code ' => 400
            ];
        }
    }

    public function getHeadersByResource($resourceId)
    {
        try {

            $headers = $this->cdnHeader->where('cdn_resource_id', $resourceId)->get();
            $response = [];
            if ($headers) {
                foreach ($headers as $header) {
                    $data = [
                        "name" => $header->name,
                        "value" => $header->value
                    ];
                    array_push($response, $data);
                }
            }

        } catch (Exception $e) {
            $this->logSys->syslog(
                "[CDN-API | HEADER CDN] Falha fatal a consulta de custon header por CDN Resource de ID $resourceId",
                " ERROR : " . $e->getMessage(),
                'ERROR',
                $this->facilityLog . ':' . basename(__FUNCTION__)
            );
            $response = [
                'message' => 'Fatal header persistence failure',
                'errors' => [$e->getMessage()],
                'code ' => 400
            ];
        }

        return $response;
    }

    public function getForwardHeader($resourceId)
    {
        try {
            $response = "";
            $headers = $this->cdnHeader->where('cdn_resource_id', $resourceId)->get();
            if ($headers) {
                foreach ($headers as $header) {
                    $response .= $header->name . ",";
                }
            }
            return explode(",", substr($response, 0, -1));
        } catch (Exception $e) {
            $this->logSys->syslog(
                "[CDN-API | HEADER CDN] Falha fatal a consulta de foeward header por CDN Resource de ID $resourceId",
                " ERROR : " . $e->getMessage(),
                'ERROR',
                $this->facilityLog . ':' . basename(__FUNCTION__)
            );

            return [];
        }
    }

    public function updateResource($data, $resourceId)
    {
        try {
            $headers = $this->cdnHeader->where('cdn_resource_id', $resourceId)->get();
            if($headers) {
                foreach($headers as $header) {
                    $header->delete();
                }
            }
            if(!empty($data)) {
                $this->create($data, $resourceId);
            }
            return ['code' => 200, 'message' => 'Headers updated successfully'];
        } catch (Exception $e) {

            $this->logSys->syslog(
                "[CDN-API | HEADER CDN] Falha fatal na alteração de custon header no CDN Resource de ID $resourceId",
                "Custon header  :" . json_encode($data) . " ERROR : " . $e->getMessage(),
                'ERROR',
                $this->facilityLog . ':' . basename(__FUNCTION__)
            );

            return [
                'code' => 500,
                'message' => 'Failed to update headers.',
                'errors' => [$e->getMessage()],
            ];
        }

    }

    public function delete($id)
    {
        try {
            $header = $this->cdnHeader->findOrFail($id)->delete();

            return ['code' => 200, 'message' => 'Header deleted successfully'];
        } catch (Exception $e) {
            $this->logSys->syslog(
                "[CDN-API | HEADER CDN] Falha fatal na exclusão de custon header de ID $id",
                " ERROR : " . $e->getMessage(),
                'ERROR',
                $this->facilityLog . ':' . basename(__FUNCTION__)
            );
            return [
                'code' => 500,
                'message' => 'Failed to delete header.',
                'errors' => [$e->getMessage()],
            ];
        }

    }

    public function deletebyResource($resourceId)
    {
        try {
            $headers = $this->cdnHeader->where('cdn_resource_id', $resourceId)->get();
            if($headers) {
                foreach($headers as $header) {
                    $header->delete();
                }
            }
            return ['code' => 200, 'message' => 'Headers deleted successfully'];
        } catch (Exception $e) {
            $this->logSys->syslog(
                "[CDN-API | HEADER CDN] Falha fatal na exclusão de custon header no CDN Resource de ID $resourceId",
                " ERROR : " . $e->getMessage(),
                'ERROR',
                $this->facilityLog . ':' . basename(__FUNCTION__)
            );
            return [
                'code' => 500,
                'message' => 'Failed to delete headers.',
                'errors' => [$e->getMessage()],
            ];
        }
    }
}
