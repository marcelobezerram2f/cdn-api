<?php

namespace App\Services;

use App\Traits\QueueTrait;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Exception\AMQPConnectionClosedException;


class QueueSupervisorService
{
    use QueueTrait;

    private $logSys;
    protected $facilityLog;


    public function __construct()
    {
        $this->logSys = new EventLogService();
        $this->facilityLog = basename(__FILE__);
    }

    public function getQueueJob($queue)
    {
        $this->processQueue($queue);
    }

    public function purgeQueue(string $queueName)
    {

        try {
            // Cria a conexão
            $connection = connectQUeue();
            $channel = $connection->channel();

            // Declara a fila (garante que ela existe) - Mantenha a mesma configuração de durable, exclusive, auto_delete
            // que você usa para declarar a fila quando você publica mensagens.  Caso contrário, poderá dar erro.
            $channel->queue_declare($queueName, false, true, false, false); //Exemplo de declaração durável

            // Limpa a fila
            $channel->queue_purge($queueName);

            // Fecha a conexão
            $channel->close();
            $connection->close();
            return ["code"=>200];
        } catch (AMQPConnectionClosedException $e) {
            return ["message"=>"Erro ao conectar ao RabbitMQ: " . $e->getMessage(), "code"=>400];
        } catch (\Exception $e) {
            return ["message"=>"Erro ao limpar a fila '$queueName': " . $e->getMessage(), "code"=>400];
        }
    }

}
