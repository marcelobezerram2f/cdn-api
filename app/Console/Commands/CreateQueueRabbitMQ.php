<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Exchange\AMQPExchangeType;
use App\Services\EventLogService;


class CreateQueueRabbitMQ extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rabbitmq:create';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';


    /**
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
        $this->createQueue('cdn_create_tenant');
        $this->createQueue('cdn_create_cdn_resource');
        $this->createQueue('cdn_new_template');
        $this->createQueue('cdn_resource_routes');
        $this->createQueue('cdn_create_tenant_return');
        $this->createQueue('cdn_create_cdn_resource_return');
        $this->createQueue('cdn_create_cdn_new_template_return');
        $this->createQueue('cdn_create_cdn_route_return');
        $this->createQueue('cdn_delete_tenant');
        $this->createQueue('cdn_delete_cdn_resource');
        $this->createQueue('cdn_delete_tenant_return');
        $this->createQueue('cdn_delete_cdn_resource_return');
        $this->createQueue('cdn_create_cdn_ssl_cert');
        $this->createQueue('cdn_create_cdn_ssl_cert_return');
        $this->createQueue('cdn_block_cdn_resource');
        $this->createQueue('cdn_block_cdn_resource_return');
        $this->createQueue('cdn_unblock_cdn_resource');
        $this->createQueue('cdn_unblock_cdn_resource_return');
        $this->createQueue('cdn_update_cdn_resource');
        $this->createQueue('cdn_update_cdn_resource_return');
        $this->createQueue('cdn_check_cdn_resource');
        $this->createQueue('cdn_check_cdn_resource_return');
        $this->createQueue('cdn_delete_cdn_resource');
        $this->createQueue('cdn_delete_cdn_resource_return');
        $this->createQueue('cdn_delete_cdn_template');
        $this->createQueue('cdn_delete_cdn_template_return');
        $this->createQueue('cdn_delete_cdn_route');
        $this->createQueue('cdn_delete_cdn_route_return');
        $this->createQueue('cdn_delete_cdn_ssl');
        $this->createQueue('cdn_delete_cdn_ssl_return');

    }


    public function createQueue($queue)
    {
        try {
            $this->line("Criando fila <question>$queue</question>....");
            try {
                $exchange = env('RABBITMQ_EXCHANGE_NAME');
                $connection = new AMQPStreamConnection(env('RABBITMQ_HOST'), env('RABBITMQ_PORT'), env('RABBITMQ_USER'), env('RABBITMQ_PASSWORD'), env('RABBITMQ_VHOST'));
            } catch (\Exception $e) {
                $exchange = env('RABBITMQ_EXCHANGE_NAME');
                $connection = new AMQPStreamConnection(env('RABBITMQ2_HOST'), env('RABBITMQ_PORT'), env('RABBITMQ_USER'), env('RABBITMQ_PASSWORD'), env('RABBITMQ_VHOST'));
            }
            $channel = $connection->channel();
            list($queue_name) = $channel->queue_declare($queue, false, true, false, false);
            $channel->exchange_declare($exchange, AMQPExchangeType::DIRECT, false, true, false);
            $channel->queue_bind($queue_name, $exchange);
            $channel->close();
            $connection->close();
            $this->line("Fila </info>$queue<info> criada com sucesso no RabbitMQ");
            return true;

        } catch (\Exception $e) {
            $erro = $e->getMessage();
            $this->line("Ocorreu um erro na criação da fila <comment>$queue</comment> criada com sucesso no RabbitMQ \n<error>$erro</error>\n\n");
            return false;
        }
    }

    public function setApiAggregator()
    {
        $this->line("Informe os parâmetros de conexão com CDN-AGGREGATOR-API..");
        $name = $this->ask("informe o nome da API cdn-aggregator-api <comment>ou pressione enter para cdn-aggregator</comment>");
        $name = !$name ? 'cdn-aggregator' : $name;
        $host = $this->ask("informe a URL da API cdn-aggregator-api <comment>ou pressione enter para https://cdn-aggregator-api.cw.com.br</comment>");
        $host = !$host ? 'https://cdn-aggregator-api.cw.com.br' : $host;
        $basicToken = null;
        while ($basicToken == null) {
            $basicToken = $this->ask("informe o Basic Token  para autenticação na API cdn-aggregator-api");
            if ($basicToken == null) {
                $this->line("Basic Token de autenticação na API cdn-aggregator-api ...");
            }
        }
        $this->line("Parametros de configuração de conexão com CDN-AGGREGATOR-API está correto?... \n
        API_NAME   : $name \n
        URL        : $host \n
        BASIC_TOKEN: $basicToken
        ");

        $data = [
            "api_name" => $name,
            "url" => $host,
            "token" => $basicToken
        ];
        return $this->saveApiAggregator($data);
    }


    public function saveApiAggregator($data)
    {
        try {
            $confirm = $this->ask("Confirma parâmetros Informados? (Y/N)  <ENTER> para <comment> Y </comment>");
            $confirm = $confirm == null ? "Y" : strtoupper($confirm);
            if ($confirm == "N") {
                $this->setApiAggregator();
            } else {
                $hostname = env('DB_HOST');
                $database = env('DB_DATABASE');
                $username = env('DB_USERNAME');
                $password = env('DB_PASSWORD');
                $pdo = new \PDO("mysql:dbname=$database;host=$hostname", $username, $password);
                $sql = $pdo->prepare("INSERT INTO cdn_apis (api_name,
                                                       `url`,
                                                       token,
                                                       created_at,
                                                       updated_at
                                                     )
                                VALUES (:an, :ul, :tk, :ca, :ua)");
                $sql->bindValue(":an",  $data['api_name']);
                $sql->bindValue(":ul",  $data['url']);
                $sql->bindValue(":tk", $data['token']);
                $sql->bindValue(":ca", date('Y-m-d H:i:s', strtotime('-3 hours')));
                $sql->bindValue(":ua", date('Y-m-d H:i:s', strtotime('-3 hours')));
                $sql->execute();
                $file = file(base_path() . '/.env');
                $i = count($file) + 2;
                $param = 'CDN_AGGREGATE_API_NAME =' . $data['api_name'] . "\n";
                $file[$i] = $param;
                $this->line("Salvando arquivo .env");
                $appString = implode($file);
                $appWriter = fopen(base_path() . '/.env', "w");
                fwrite($appWriter, $appString);
                fclose($appWriter);
            }
        }catch (Exception $e) {
            $this->line("Falha na configuração de conexão com a API AGGREGATOR... \n".
            $e->getMessage());
            return ["error"=>$e->getMessage()];
        }
    }

    public function configDbEnv()
    {
        $this->line("Informe os parâmetros de conexão com o banco de dados..");
        $name = "";
        $username = "";
        $password = "";

        $drive = $this->ask(
            "Informe o SGBD será utilizado nesse projeto. \n
                'pgsql' para PostgreSQL \n
                'sqlsrv' para Microsoft SQLSERVER \n
                <comment>ou pressione enter para MYSQL OU MARIA-DB</comment>"
        );
        $host = $this->ask("informe o host do banco de dados <comment>ou pressione enter para 127.0.0.1</comment>");
        $port = $this->ask("informe a porta do banco de dados <comment>ou pressione enter para 3306 </comment>");

        while ($name == null)
            $name = $this->ask("informe o nome do banco de dados");

        while ($username == null)
            $username = $this->ask("informe o usuario do banco de dados");

        while ($password == null)
            $password = $this->ask("informe o senha de acesso ao banco de dados");

        $drive = $drive == null ? "mysql" : strtolower($drive);
        $host = $host == null ? "127.0.0.1" : $host;
        $port = $port == null ? "3306" : $port;

        $this->saveConfigDbEnv($drive, $host, $port, $name, $username, $password);

    }

    public function saveConfigDbEnv($drive, $host, $port, $name, $username, $password)
    {

        try {
            $this->line("Parâmetros da acesso ao banco de dados. \n
            DB_CONNECTION = $drive \n
            DB_HOST = $host \n
            DB_PORT = $port \n
            DB_DATABASE = $name \n
            DB_USERNAME = $username \n
            DB_PASSWORD = $password \n");

            $confirm = $this->ask("Confirma parâmetros Informados? (Y/N)  <ENTER> para <comment> Y </comment>");

            $confirm = $confirm == null ? "Y" : strtoupper($confirm);

            if ($confirm == "N") {
                $this->configDbEnv();
            } else {

                /** Manipulando o arquivo .env para configuração do banco de dados*/

                $params = array("DB_CONNECTION", "DB_HOST", "DB_PORT", "DB_DATABASE", "DB_USERNAME", "DB_PASSWORD");
                $dbconf = array("DB_CONNECTION" => $drive, "DB_HOST" => $host , "DB_PORT" => $port, "DB_DATABASE" => $name , "DB_USERNAME" => $username , "DB_PASSWORD" => $password );
                $logConf = array("DB_CONNECTION" => $drive, "DB_HOST" => $host , "DB_PORT" => $port, "DB_DATABASE" => $name , "DB_USERNAME" => $username );

                $this->eventLog->syslog(
                    "[CDN-API | (cdnapi:install) ] Configurando parametros de banco de dados.",
                    'Parametros de banco de dados =>' . json_encode($logConf),
                    'INFO', $this->facilityLog . ':' . basename(__FUNCTION__)
                );

                $file = file(base_path() . '/.env');
                for ($i = 0; $i < count($file); $i++) {
                    if (in_array(explode('=', $file[$i])[0], $params) !== false) {
                        $key = explode('=', $file[$i])[0];
                        $value = $key . '=' . $dbconf["$key"] . "\n";
                        $file[$i] = $value;
                        $this->line("Parâmetro $key configurado ... <comment>$value</comment>");
                    }
                }
                $this->line("Salvando arquivo .env");

                $appString = implode($file);
                $appWriter = fopen(base_path() . '/.env', "w");
                fwrite($appWriter, $appString);
                fclose($appWriter);
                sleep(2);
                $dotenv = Dotenv::createImmutable(base_path());
                $dotenv->load();
                $this->line("Fim da manipulação do arquivo .env para configuração do banco de dados...");

            }
        } catch (Exception $e) {
            $this->eventLog->syslog(
                "[CDN-API | (cdnapi:install) ] Ocorreu um erro na configuração do banco de dados.",
                'Error : ' . $e->getMessage(),
                'ERROR', $this->facilityLog . ':' . basename(__FUNCTION__)
            );

        }
    }



    public function setCommomParams($phpVersion)
    {

        $domain = "";
        $this->line(" Iniciando configurações de parametros do arquivo .env  ...");

        $appName = $this->ask("Informe um nome para API ou  <ENTER> para <comment> CDN-API </comment> ...");

        while ($domain == null)
            $domain = $this->ask("Informe o Domínio da aplicação  <comment> Ex: https://cdn-api.com.br </comment> ...");

        $appName = $appName == null ? "CDN-API" : $appName;

        $this->saveCommomParams(
            $appName,
            $domain,
            $phpVersion
        );
    }

    public function saveCommomParams($appName, $domain, $phpVersion)
    {
        try {

            $this->line("Parâmetros da Aplicação e conta de e-mail. \n
                APP_NAME = $appName \n
                APP_URL = $domain \n
            ");

            $confirm = $this->ask("Confirma parâmetros Informados? (Y/N)  <ENTER> para <comment> Y </comment>");

            $confirm = $confirm == null ? "Y" : strtoupper($confirm);

            if ($confirm == "N") {
                $this->setCommomParams();
            } else {

                $this->eventLog->syslog(
                    "[CDN-AGGREGATOR-API | (cdn-agreggator:install) ] Configurando parametros comuns.",
                    "Parametros comuns => APP_NAME = $appName , APP_HOST=$domain",
                    'info',
                    $this->facilityLog . ':' . basename(__FUNCTION__)
                );


                $file = file(base_path() . '/.env');
                for ($i = 0; $i < count($file); $i++) {
                    $envKey = explode('=', $file[$i])[0];

                    if ($envKey == 'APP_NAME') {
                        $this->line("Nomeando aplicação ...");
                        $file[$i] = "APP_NAME=" . $appName . "\n";
                        $this->line("Aplicação nomeada ... <comment>" . $file[$i] . "</comment>");
                        https://cdn-aggregator-api.com.br
                    }

                    if ($envKey == 'APP_DEBUG') {
                        $this->line("Desabilitando nível de debug da aplicação ...");
                        $file[$i] = "APP_DEBUG=false\n";
                        $this->line("nivel de debug da aplicação desabilitado ... <comment>" . $file[$i] . "</comment>");
                    }

                    if ($envKey == 'APP_URL') {
                        $this->line("Configurando host da aplicação ...");
                        $file[$i] = "APP_URL=" . $domain . "\n\n";
                        $this->line("Host da aplicação configurada ... <comment>APP_HOST=$domain</comment>");
                    }

                    if ($envKey == 'LOG_NAME_SYSLOG') {
                        $this->line("Configurando host da aplicação para Graylog ...");
                        $file[$i] = "LOG_NAME_SYSLOG=" . $appName . "\n\n";
                        $this->line("Host da aplicação configurada ... <comment>APP_HOST=$domain</comment>");
                    }

                }

                $appString = implode($file);
                $appWriter = fopen(base_path() . '/.env', "w");
                fwrite($appWriter, $appString);
                fclose($appWriter);

                $this->eventLog->syslog('[CDN-API | (cdn-api:install) ] Parametros comuns salvados com sucesso no arquivo env.', '', 'INFO', $this->facilityLog . ':' . basename(__FUNCTION__));
                sleep(2);
                $this->line("Gerando chave da aplicação...");
                $command = shell_exec("$phpVersion artisan key:generate");
                $this->eventLog->syslog("[CDN-API | (cdn-api:install) ] Gerando chave da aplicação.", 'Return command: ' . json_encode($command), 'INFO', $this->facilityLog . ':' . basename(__FUNCTION__));
                $this->line("<comment>$command</comment>");
            }

        } catch (Exception $e) {

            $this->eventLog->syslog(
                '[CDN-API | (cdn-api:install) ] Ocorreu uma falha na configuração dos parametros comuns.',
                'Error ' . $e->getMessage(),
                'ERROR',
                $this->facilityLog . ':' . basename(__FUNCTION__)
            );

        }
    }


}
