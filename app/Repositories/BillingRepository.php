<?php

namespace App\Repositories;

use App\Models\CdnApi;
use App\Models\CdnDataSummarizedStreamServer;
use App\Models\CdnDataSummarizedTenant;
use App\Services\EventLogService;
use Exception;
use Illuminate\Support\Facades\DB;

class BillingRepository
{



    private $cdnDataSummarizedStreamServer;
    private $cdnDataSummarizedTenant;
    private $cdnAPi;
    private $logSys;
    protected $facilityLog;


    public function __construct()
    {
        $this->cdnDataSummarizedStreamServer = new CdnDataSummarizedStreamServer();
        $this->cdnDataSummarizedTenant = new CdnDataSummarizedTenant();
        $this->cdnAPi = new CdnApi();
        $this->logSys = new EventLogService();
        $this->facilityLog = basename(__FILE__);
    }


    public function saveSummarized($summaryDate)
    {
        try {
            $payload = [
                "start_date" => $summaryDate['start_date']
            ];
            $api_name = env('CDN_AGGREGATE_API_NAME');
            $api = $this->cdnAPi->where('api_name', $api_name)->first();
            $endpoint = "api/v1/streaming/getSummaryDay";
            $summaryzed = apiConsumer($payload, $api, $endpoint);

            $tenantSummary = summarizeBytesTransmitted($summaryzed);

            if (empty($summaryzed)) {
                $this->logSys->syslog(
                    '[CDN-API | Summaries Data] Não há dados de telemetria sumarizados para persistência na tabela cdn_data_summarized',
                    'Parâmetros de consulta:' . json_encode($summaryDate),
                    "INFO",
                    $this->facilityLog . ':' . basename(__FUNCTION__)
                );
            } else {

                foreach ($tenantSummary as $summary) {
                    $tenant = [
                        'tenant' => $summary['tenant'],
                        'summary_date' => $summary['date'],
                        'total_bytes_transmitted' => $summary['total_bytes_transmitted']
                    ];
                    $summaryTenant = $this->cdnDataSummarizedTenant->create($tenant);
                    foreach ($summary['stream_servers'] as $streamServer) {
                        $server = [
                            'cdn_data_summarized_tenant_id' => $summaryTenant->id,
                            'summary_date' => $streamServer['date'],
                            'stream_server' => $streamServer['stream_server'],
                            'bytes_transmitted' => $streamServer['bytes_transmitted']
                        ];
                        $this->cdnDataSummarizedStreamServer->create($server);

                    }
                }
            }



            $this->logSys->syslog(
                '[CDN-API | Summaries Data] Dados de telemetria sumarizados persistido na tabela cdn_data_summarized com sucesso',
                'Parâmetros de consulta:' . json_encode($summaryzed),
                "INFO",
                $this->facilityLog . ':' . basename(__FUNCTION__)
            );

        } catch (Exception $e) {
            $this->logSys->syslog(
                '[CDN-API | Summaries Data] Ocorreu uma falha na persistência na tabela cdn_data_summarized dos dados de telemetria sumarizada',
                'Parâmetros de consulta:' . json_encode($summaryDate) . ' Erro : ' . $e->getMessage(),
                "ERROR",
                $this->facilityLog . ':' . basename(__FUNCTION__)
            );
        }
    }

    public function getSummarized($data)
    {
        try {
            $valid = getSummaryValidate($data);
            if (!empty($valid)) {
                return [
                    'message' => 'rejected payload',
                    'errors' => $valid,
                    'code' => 400
                ];
            }

            $startDate = $data['start_date'];
            $endDate = isset($data['end_date']) ? $data['end_date'] : $startDate;

            $summaries = $this->cdnDataSummarizedTenant->whereBetween("summary_date", [$startDate, $endDate])
                ->where('tenant', strtolower($data['tenant']))
                ->select('tenant', DB::raw('SUM(total_bytes_transmitted) as total_bytes_transmitted'))
                ->groupBy('tenant')
                ->get()->toArray();

            if (empty($summaries)) {
                $this->logSys->syslog(
                    '[CDN-API | Summaries Data] Não há dados sumarizados para recuperar na data informada',
                    'Parâmetros de consulta:' . json_encode($data),
                    "WARNING",
                    $this->facilityLog . ':' . basename(__FUNCTION__)
                );

                return [
                    "message" => "No data found!",
                    "code" => 202
                ];
            }

            $response = [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'tenant' => $summaries [0]['tenant'],
                'total_bytes_transmitted' => $summaries [0]['total_bytes_transmitted']

            ];

        } catch (Exception $e) {

            $this->logSys->syslog(
                '[CDN-API | Summaries Data] Ocorreu uma falha na recuperação dos dados de telemetria sumarizada na tabela cdn_data_summarized_tenant diária',
                'Parâmetros de consulta:' . json_encode($data) . ' Erro : ' . $e->getMessage(),
                "ERROR",
                $this->facilityLog . ':' . basename(__FUNCTION__)
            );

            $response = [
                "message" => " failure to retrieve daily summarized telemetry data.",
                "errors" => [$e->getMessage()],
                "code" => 400
            ];
        }

        return $response;

    }
    public function getStatement($data)
    {
        try {
            $valid = getSummaryValidate($data);
            if (!empty($valid)) {
                return [
                    'message' => 'rejected payload',
                    'errors' => $valid,
                    'code' => 400
                ];
            }

            $page = isset($data['page']) ? $data['page'] : 1;
            $startDate = $data['start_date'];
            $endDate = isset($data['end_date']) ? $data['end_date'] : $startDate;

            $summaries  =  $this->getSummarized($data);

            $statement = $this->cdnDataSummarizedTenant->whereBetween("summary_date", [$startDate, $endDate])
                ->where('tenant', strtolower($data['tenant']))->with('streamServer')
                ->get();

            if (empty($statement)) {
                $this->logSys->syslog(
                    '[CDN-API | Summaries Data] Não há dados sumarizados para recuperar na data informada',
                    'Parâmetros de consulta:' . json_encode($data),
                    "WARNING",
                    $this->facilityLog . ':' . basename(__FUNCTION__)
                );

                return [
                    "message" => "No data found!",
                    "code" => 202
                ];
            }


            $summaryTenant = [];
            foreach ($statement as $summary) {
                $data = [
                    "summary_date" => $summary->summary_date,
                    "tenant" => $summary->tenant,
                    "bytes_transmitted" => $summary->total_bytes_transmitted
                ];
                foreach ($summary->streamServer as $streamServer) {
                    $data['stream_servers'][] = [
                        "stream_server" => $streamServer->stream_server,
                        "bytes_transmitted" => $streamServer->bytes_transmitted
                    ];
                }
                array_push($summaryTenant, $data);
            }

            $summaryTenant = (paginate($summaryTenant, $page, env('MAX_ITEMS_PAGE')));
            $response = [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'tenant' => $summaries['tenant'],
                'total_bytes_transmitted'=>$summaries['total_bytes_transmitted'],
                'register_number' => count($statement),
                'total_page' => intval($summaryTenant['totalPage']),
                'next_page' => intval($summaryTenant['nextPage']),
                'summaries' => $summaryTenant['pageItems']
            ];

            $this->logSys->syslog(
                '[CDN-API | Summaries Data] Dados sumarizados (diário) recuperados com sucesso',
                'Parâmetros de consulta:' . json_encode($data),
                "ERROR",
                $this->facilityLog . ':' . basename(__FUNCTION__)
            );
        } catch (Exception $e) {

            $this->logSys->syslog(
                '[CDN-API | Summaries Data] Ocorreu uma falha na recupetação dos dados de telemetria sumarizada na tabela cdn_data_summarized diária',
                'Parâmetros de consulta:' . json_encode($data) . ' Erro : ' . $e->getMessage(),
                "ERROR",
                $this->facilityLog . ':' . basename(__FUNCTION__)
            );

            $response = [
                "message" => " failure to retrieve daily summarized telemetry data.",
                "errors" => [$e->getMessage()],
                "code" => 400
            ];
        }

        return $response;
    }


    public function getSummariesHour($data)
    {
        try {
            $valid = getSummaryValidate($data);
            if (!empty($valid)) {
                return [
                    'message' => 'rejected payload',
                    'errors' => $valid,
                    'code' => 400
                ];
            }

            $page = isset($data['page']) ? $data['page'] : 1;

            $api_name = env('CDN_AGGREGATE_API_NAME');
            $api = $this->cdnAPi->where('api_name', $api_name)->first();
            $endpoint = "api/v1/streaming/getSummaryHour";
            $summaryzed = apiConsumer($data, $api, $endpoint);
            if (empty($summaryzed['summary_hours'])) {
                return ['message' => 'No data found!', 'code' => 202];
            }

            $statement = (paginate($summaryzed['summary_hours'], $page, env('MAX_ITEMS_PAGE')));
            $response = [
                'start_date' => $summaryzed['start_date'],
                'end_date' => $summaryzed['end_date'],
                'register_number' => count($summaryzed['summary_hours']),
                'total_page' => intval($statement['totalPage']),
                'next_page' => intval($statement['nextPage']),
                'summaries' => $statement['pageItems']
            ];


        } catch (Exception $e) {
            $this->logSys->syslog(
                '[CDN-API | Summaries Data] Ocorreu uma falha na recuperação dos dados de telemetria sumarizada na tabela cdn_data_summarized diária',
                'Parâmetros de consulta:' . json_encode($data) . ' Erro : ' . $e->getMessage(),
                "ERROR",
                $this->facilityLog . ':' . basename(__FUNCTION__)
            );

            $response = [
                "message" => " failure to retrieve daily summarized telemetry data.",
                "errors" => [$e->getMessage()],
                "code" => 400
            ];


        }

        return $response;
    }


    public function getRawData($data)
    {
        try {

            $valid = getRawDataValidate($data);
            if (!empty($valid)) {
                return [
                    'message' => 'rejected payload',
                    'errors' => $valid,
                    'code' => 400
                ];
            }
            $payload = [
                'start_date' => $data['start_date'],
                'start_hour' => $data['start_hour']
            ];
            if (isset($data['end_date']) || $data['end_date'] != null) {
                $payload['end_date'] = $data['end_date'];
            }

            if (isset($data['end_hour']) || $data['end_hour'] != null) {
                $payload['end_hour'] = $data['end_hour'];
            }

            $api_name = env('CDN_AGGREGATE_API_NAME');
            $api = $this->cdnAPi->where('api_name', $api_name)->first();
            $endpoint = "api/v1/streaming/getRawData";
            $summaryzed = apiConsumer($data, $api, $endpoint);

            if (empty($summaryzed['raw_data'])) {
                return ['message' => 'No data found!', 'code' => 202];
            }

            return $summaryzed;

        } catch (Exception $e) {


        }
    }



}




