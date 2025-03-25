<?php

namespace App\Console\Commands;

use App\Models\CdnApi;
use App\Models\User;
use Exception;
use Illuminate\Console\Command;
use App\Services\EventLogService;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Client;
class CreateCredentials extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'create:credentials';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    protected $facilityLog;
    private $eventLog;

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
        $this->saveUser('admin');
    }


    public function saveUser($name)
    {

        try {
            $credentials = $this->createCredentials($name);
            $message = ("Anote as credenciais de autenticação client credentials no CDN-AGGREGATOR : \n\n
                ===========================================================================================================\n
                Name          : $name \n
                client_id     :" . $credentials['client_id'] . "\n
                client_secret :" . $credentials['client_secret'] . "\n
                ===========================================================================================================\n\n"
            );
            $this->eventLog->syslog("[CDN-API | (cdn-api:install) ] Credenciais geradas com sucesso.", 'Credentials : ' . json_encode($credentials), 'INFO', $this->facilityLog . ':' . basename(__FUNCTION__));
            $this->line($message);


        } catch (Exception $e) {

            $this->line($e->getMessage());

            $this->eventLog->syslog(
                "[CDN-API | (cdn-api:install) ] Ocorreu um erro ao criar as credenciais para o usuário.",
                'Error : ' . $e->getMessage(),
                'ERROR',
                $this->facilityLog . ':' . basename(__FUNCTION__)
            );
        }
    }

    public function createCredentials($name)
    {

        $clients = new ClientRepository;

        $providers = array_keys(config('auth.providers'));

        $provider = 'users';

        $client = $clients->createPasswordGrantClient(
            null,
            $name,
            env('APP_URL'),
            $provider
        );

        return $this->outputClientDetails($client);
    }

    /**
     * Output the client's ID and secret key.
     *
     * @param  \Laravel\Passport\Client  $client
     * @return array
     */
    public function outputClientDetails(Client $client)
    {

        return [
            'client_id' => $client->id,
            'client_secret' => $client->plainSecret
        ];
    }

}

