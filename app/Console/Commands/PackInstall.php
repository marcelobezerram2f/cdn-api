<?php

namespace App\Console\Commands;

use App\Models\CdnOriginGroup;
use App\Models\CdnOriginServer;
use App\Models\CdnResource;
use App\Models\CdnResourceOriginGroup;
use Exception;
use Illuminate\Console\Command;
use App\Services\EventLogService;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Exchange\AMQPExchangeType;
use App\Models\CdnLetsencryptAcmeRegister;


use PDO;

class PackInstall extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cdn-api:install';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    protected $facilityLog;
    private $eventLog;

    /**use App\Models\CdnOriginGroup;
use App\Models\CdnOriginServer;
use App\Models\CdnResource;
use App\Models\CdnResourceOriginGroup;
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $this->eventLog = new EventLogService();
        $this->facilityLog = basename(__FILE__);
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        try {

            $this->line("iniciando a Atualização da API CDN API .....");
            $phpCommand = $this->ask("Informe comando para a versão PHP 8.2. EX.: php82, ou <ENTER> para  apenas <comment> php </comment> ...");
            $phpCommand = !$phpCommand ? 'php' : $phpCommand;
            $this->line("Limpando config do projeto .....");
            $cache = shell_exec($phpCommand . ' artisan config:clear 2>&1');
            $this->line(" ");
            $this->line("Limpando cache do projeto .....");
            $cache = shell_exec($phpCommand . ' artisan cache:clear 2>&1');
            $this->line(" ");
            if (strpos($cache, "PHP Fatal error") !== false || strpos($cache, "not found") !== false) {
                $version = shell_exec('php --version 2>&1');
                $this->line("Comando PHP não encontrado ou versão inferior ao exigido [<comment>php 8.2</comment>]");
                $this->line("versão ativa em php --version \n<comment>$version</comment>");
                exit;
            }
            sleep(seconds: 2);
            $migrate = shell_exec("$phpCommand artisan migrate 2>&1");
            $this->line($migrate);
            sleep(3);
            $this->line("ajustando tabela  \n<comment>cdn_letsencrypt_acme_registries</comment> ...");

            $certificates = CdnLetsencryptAcmeRegister::all();
            foreach ($certificates as $certificate) {
                $certificate->company = "lets_encrypt";
                if (is_null($certificate->fullchain)) {
                    $certificate->fullchain = "-";
                }
                $certificate->save();
            }

            $this->line("ajustando tabela  \n<comment>cdn_resource_origin_groups</comment> ...");
            $serverGroups = CdnOriginGroup::all();
            foreach ($serverGroups as $serverGroup) {
                $originServer = CdnOriginServer::where('cdn_origin_group_id', $serverGroup->id)->count();
                if ($originServer == 1) {
                    $resourceGroup = CdnResourceOriginGroup::where('cdn_origin_group_id', $serverGroup->id)->first();
                    if (!is_null($resourceGroup)) {
                        $resource = CdnResource::find($resourceGroup->cdn_resource_id);
                        $serverGroup->type= "single";
                        $serverGroup->group_description = 'Grupo de servidores de origem exclusivo para o cdn resource ' . $resource->cdn_resource_hostname;
                        $serverGroup->save();
                    }
                }
            }



        } catch (Exception $e) {
            $this->line("Ocorreu um erro na execução do script  \n<comment>".$e->getMessage()."</comment> ...");
            $this->eventLog->syslog('[API-CDN | (cdn-api:install) ] Ocorreu uma falha na configuração da API.', $e->getMessage(), 'Error', $this->facilityLog . ':' . basename(__FUNCTION__));
        }
    }

}