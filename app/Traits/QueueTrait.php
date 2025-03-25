<?php

namespace App\Traits;

use App\Services\EventLogService;
use PhpAmqpLib\Exchange\AMQPExchangeType;

trait QueueTrait
{

    private $logSys;
    protected $facilityLog;


    public function __construct()
    {
        $this->logSys = new EventLogService();
        $this->facilityLog = basename(__FILE__);
    }
    public function processQueue($queue)
    {
        $this->logSys->syslog(
            '[CDN-API | Provisioning CDN] Inicando leitura da fila  ' . $queue,
            null,
            'INFO',
            $this->facilityLog . ':' . basename(__FUNCTION__)
        );
        $exchange = env('RABBITMQ_EXCHANGE_NAME');
        $consumerTag = 'consumer';
        $channel = connectQueue();

        // Declaração da fila e do exchange
        $channel->queue_declare($queue, false, true, false, false);
        $channel->exchange_declare($exchange, AMQPExchangeType::DIRECT, false, true, false);
        $channel->queue_bind($queue, $exchange);

        $job = function ($msg) {
            $object = json_decode($msg->getBody(), true);
            $thisJob = $object['data']['command'];
            $tenantCreateJob = unserialize($thisJob);
            \Log::info("Unserilize de fila " . json_encode($tenantCreateJob));
            $tenantCreateJob->handle();
            $msg->ack();
        };

        // Configuração do QoS
        $channel->basic_qos(null, 1, false);
        $channel->basic_consume($queue, $consumerTag, false, false, false, false, $job);

        try {
            // Loop de consumo contínuo

            while ($channel->is_consuming()) {
                try {
                    $channel->wait(null, false, 5); //  timeout para um intervalo menor, como 5 segundos
                } catch (\PhpAmqpLib\Exception\AMQPTimeoutException $e) {
                    // Timeout é esperado, verificar se ainda há mensagens na fila
                    $queueInfo = $channel->queue_declare($queue, true);
                    $messageCount = $queueInfo[1];
                    if ($messageCount == 0) {
                        $this->logSys->syslog(
                            "[CDN-API | Provisioning CDN] Não há tarefa a ser executada na fila $queue ",
                            null,
                            'INFO',
                            $this->facilityLog . ':' . basename(__FUNCTION__)
                        );
                        break;
                    }
                    // Se ainda houver mensagens, continue consumindo
                }
            }
        } catch (\Throwable $e) {
            $this->logSys->syslog(
                '[CDN-API | Provisioning CDN] Ocorreu uma falha no consumo da fila ' . $queue,
                'ERRO : ' .$e->getMessage(). " TRACE : " .$e->getTraceAsString() . ' QUEUETRAIT: processQueue',
                'ERROR',
                $this->facilityLog . ':' . basename(__FUNCTION__)
            );
        } finally {
            // Fecha o canal e a conexão
            $channel->getConnection()->close();
        }
    }




}
