<?php

namespace App\Repositories;

use App\Models\CdnApi;
use App\Services\EventLogService;
use Exception;

class GraphDataRepository
{

    private $cdnAPi;
    private $logSys;
    protected $facilityLog;


    public function __construct()
    {
        $this->cdnAPi = new CdnApi();
        $this->logSys = new EventLogService();
        $this->facilityLog = basename(__FILE__);
    }


    public function fiveMinutesAverage($data) {
        try {
            $validade = validateDateInterval($data, 'fiveMinutes');
            if (!empty($validade)) {
                return [
                    'code' => 400,
                    'message' => 'Warning!Invalidation of the data entered.',
                    'errors' => $validade,
                ];
            }

            $api_name = env('CDN_AGGREGATE_API_NAME');
            $api = $this->cdnAPi->where('api_name', $api_name)->first();
            $endpoint = "api/v1/graph/getFiveToFive";
            $fiveMinutesAverageData = apiConsumer($data, $api, $endpoint);

            if (empty($fiveMinutesAverageData)) {
                $this->logSys->syslog(
                    '[CDN-API | Graph Data] Não há dados de telemetria a cada 5 minutos para os graficos.',
                    'Parâmetros de consulta:' . json_encode($data),
                    "INFO",
                    $this->facilityLog . ':' . basename(__FUNCTION__)
                );
            }

            return $fiveMinutesAverageData;

        } catch (Exception $e) {
            $this->logSys->syslog(
                '[CDN-API | Graph Data] Ocorreu uma falha no retorno dos dados de telemetria a cada 5 minutos para os graficos.',
                'Parâmetros de consulta:' . json_encode($data) . ' Erro : ' . $e->getMessage(),
                "ERROR",
                $this->facilityLog . ':' . basename(__FUNCTION__)
            );
        }
    }


    public function everyMinutes($data) {
        try {

            $api_name = env('CDN_AGGREGATE_API_NAME');
            $api = $this->cdnAPi->where('api_name', $api_name)->first();
            $endpoint = "api/v1/graph/averyMinutes";
            $everyMinutesData = apiConsumer($data, $api, $endpoint);

            if (empty($everyMinutesData)) {
                $this->logSys->syslog(
                    '[CDN-API | Graph Data] Não há dados de telemetria a cada minuto para os graficos.',
                    'Parâmetros de consulta:' . json_encode($data),
                    "INFO",
                    $this->facilityLog . ':' . basename(__FUNCTION__)
                );
            }

            return $everyMinutesData;

        } catch (Exception $e) {
            $this->logSys->syslog(
                '[CDN-API | Graph Data] Ocorreu uma falha no retorno dos dados de telemetria a cada minuto para os graficos.',
                'Parâmetros de consulta:' . json_encode($data) . ' Erro : ' . $e->getMessage(),
                "ERROR",
                $this->facilityLog . ':' . basename(__FUNCTION__)
            );
        }

    }


    public function daily($data) {
        try {
            $validade = validateDateInterval($data, 'daily');
            if (!empty($validade)) {
                return [
                    'code' => 400,
                    'message' => 'Warning!Invalidation of the data entered.',
                    'errors' => $validade,
                ];
            }

            $api_name = env('CDN_AGGREGATE_API_NAME');
            $api = $this->cdnAPi->where('api_name', $api_name)->first();
            $endpoint = "api/v1/graph/daily";
            $dailyData = apiConsumer($data, $api, $endpoint);

            if (empty($dailyData)) {
                $this->logSys->syslog(
                    '[CDN-API | Graph Data] Não há dados de telemetria diaria para os graficos.',
                    'Parâmetros de consulta:' . json_encode($data),
                    "INFO",
                    $this->facilityLog . ':' . basename(__FUNCTION__)
                );
            }

            return $dailyData;

        } catch (Exception $e) {
            $this->logSys->syslog(
                '[CDN-API | Graph Data] Ocorreu uma falha no retorno dos dados de telemetria diaria para os graficos.',
                'Parâmetros de consulta:' . json_encode($data) . ' Erro : ' . $e->getMessage(),
                "ERROR",
                $this->facilityLog . ':' . basename(__FUNCTION__)
            );
        }

    }


    public function monthly($data) {
        try {

            $validade = validateDateInterval($data, 'monthly');
            if (!empty($validade)) {
                return [
                    'code' => 400,
                    'message' => 'Warning!Invalidation of the data entered.',
                    'errors' => $validade,
                ];
            }
            $api_name = env('CDN_AGGREGATE_API_NAME');
            $api = $this->cdnAPi->where('api_name', $api_name)->first();
            $endpoint = "api/v1/graph/monthly";
            $monthlyData = apiConsumer($data, $api, $endpoint);

            if (empty($monthlyData)) {
                $this->logSys->syslog(
                    '[CDN-API | Graph Data] Não há dados de telemetria mensal para os graficos.',
                    'Parâmetros de consulta:' . json_encode($data),
                    "INFO",
                    $this->facilityLog . ':' . basename(__FUNCTION__)
                );
            }

            return $monthlyData;

        } catch (Exception $e) {
            $this->logSys->syslog(
                '[CDN-API | Graph Data] Ocorreu uma falha no retorno dos dados de telemetria mensal para os graficos.',
                'Parâmetros de consulta:' . json_encode($data) . ' Erro : ' . $e->getMessage(),
                "ERROR",
                $this->facilityLog . ':' . basename(__FUNCTION__)
            );
        }

    }






}
