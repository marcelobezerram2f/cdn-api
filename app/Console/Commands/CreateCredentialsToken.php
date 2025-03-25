<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\Client;

class CreateCredentialsToken extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'create:token {name}';


    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $name = $this->argument('name');
        if (isset($name)) {
            $client = Client::where('name', $name)->first();
            if ($client) {
                $accessToken = base64_encode("$client->id:$client->secret");
                $client->token = $accessToken;
                $client->save();
                print_r("\n Anote o token: \n");
                print_r("\n Client_Id : $client->id \n");
                print_r("\n Basic_Token : $accessToken \n");
            } else {
                print_r("Nome do usuário inexistente para geração do Basic Token!\n");
            }


        } else {

            print_r(" Informe o nome do usuário para geração do Basic Token!");

        }

    }
}
