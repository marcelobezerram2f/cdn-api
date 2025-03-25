<?php

namespace App\Console\Commands;

use Exception;
use Illuminate\Console\Command;
use App\Services\QueueSupervisorService;

class ClearQueue extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rabbitmq:clear';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete all jobs from the specified queue in RabbitMQ';



    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        try {

            $queues = [
                'cdn_create_tenant                     |<comment>Envia mensagem ao DISPATCHER para criar um novo TENANT</comment>',
                'cdn_create_cdn_resource               |<comment>Envia mensagem ao DISPATCHER para criar um novo RESOURCE</comment>',
                'cdn_new_template                      |<comment>Envia mensagem ao DISPATCHER para fazer o parse do TEMPLATE e efetuar cópia</comment>',
                'cdn_resource_routes                   |<comment>Envia mensagem ao DISPATCHER para configurar ROTA do resource</comment>',
                'cdn_create_tenant_return              |<comment>Mensagem enviada do DISPATCHER com o resultado da criação do TENANT</comment>',
                'cdn_create_cdn_resource_return        |<comment>Mensagem enviada do DISPATCHER com o resultado da criação do RESOURCE</comment>',
                'cdn_create_cdn_new_template_return    |<comment>Mensagem enviada do DISPATCHER com o resultado do parse e cópia do TEMPLATE</comment>',
                'cdn_create_cdn_route_return           |<comment>Mensagem enviada do DISPATCHER com o resultado da configuração da ROTA do resource</comment>',
                'cdn_delete_tenant                     |<comment>Envia mensagem ao DISPATCHER para excluir um TENANT</comment>',
                'cdn_delete_cdn_resource               |<comment>Envia mensagem ao DISPATCHER para excluir um RESOURCE</comment>',
                'cdn_delete_tenant_return              |<comment>Mensagem enviada do DISPATCHER com o resultado da exclusão do TENANT</comment>',
                'cdn_delete_cdn_resource_return        |<comment>Mensagem enviada do DISPATCHER com o resultado da exclusão do RESOURCE</comment>',
                'cdn_create_cdn_ssl_cert               |<comment>Envia mensagem ao DISPATCHER para instalar o certificado SSL</comment>',
                'cdn_create_cdn_ssl_cert_return        |<comment>Mensagem enviada do DISPATCHER com o resultado da instalação do certificado SSL</comment>',
                'cdn_block_cdn_resource                |<comment>Envia mensagem ao DISPATCHER para bloquear um RESOURCE</comment>',
                'cdn_block_cdn_resource_return         |<comment>Mensagem enviada do DISPATCHER com o resultado do bloqueio do RESOURCE</comment>',
                'cdn_unblock_cdn_resource              |<comment>Envia mensagem ao DISPATCHER para desbloquear um RESOURCE</comment>',
                'cdn_unblock_cdn_resource_return       |<comment>Mensagem enviada do DISPATCHER com o resultado do desbloqueio do RESOURCE</comment>',
                'cdn_update_cdn_resource               |<comment>Envia mensagem ao DISPATCHER para excluir um RESOURCE e seu TEMPLATE para efetuar UPDATE</comment>',
                'cdn_update_cdn_resource_return        |<comment>Mensagem enviada do DISPATCHER com o resultado da exclusão do RESOURCE e seu TEMPLATE para efetuar UPDATE</comment>',
                'cdn_check_cdn_resource                |<comment>Envia mensagem ao DISPATCHER para verificar a existência de um RESOURCE</comment>',
                'cdn_check_cdn_resource_return         |<comment>Mensagem enviada do DISPATCHER com o resultado da verificação da existência de um RESOURCE</comment>',
                'cdn_delete_cdn_template               |<comment>Envia mensagem ao DISPATCHER para excluir um TEMPLATE</comment>',
                'cdn_delete_cdn_template_return        |<comment>Mensagem enviada do DISPATCHER com o resultado da exclusão do TEMPLATE</comment>',
                'cdn_delete_cdn_route                  |<comment>Envia mensagem ao DISPATCHER para excluir uma ROTA de resource</comment>',
                'cdn_delete_cdn_route_return           |<comment>Mensagem enviada do DISPATCHER com o resultado da exclusão da ROTA</comment>',
                'cdn_delete_cdn_ssl                    |<comment>Envia mensagem ao DISPATCHER para desinstalar o certificado SSL</comment>',
                'cdn_delete_cdn_ssl_return             |<comment>Mensagem enviada do DISPATCHER com o resultado da desistalação do certificado SSL</comment>',
            ];

            $queueName = $this->choice('Selecione a fila para limpar:', $queues);
            $queueSelect = trim(explode("|",$queueName)[0]);
            $msgSelect = trim(explode("|",$queueName)[1]);
            $queue = new QueueSupervisorService();
            $clear  = $queue->purgeQueue($queueSelect);
            if ($clear['code'] ==200) {
                $this->info("Fila <comment>'$queueSelect'</comment> que '$msgSelect'. Limpa com sucesso!");

            }else {
                $this->error($clear['message']);
            }


        } catch (Exception $e) {
            $this->error("Ocorreu uma falha fatal na execução da limpeza da fila . ERRO: " . $e->getMessage());
        }


    }
}
