<?php

namespace App\Console\Commands;

use App\Models\OauthClient;
use Illuminate\Console\Command;

class GetCredentials extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'get-credentials';

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
        $credenciais  =  OauthClient::where('name', 'admin')->get();

        foreach($credenciais as $credential) {

            print_r ("=================================================================================================================================== \n");
            $this->line( "<info>USER NAME : </info>". $credential->name." \n");
            $this->line("<info>CLIENT_ID :</info>". $credential->id." \n");
            $this->line("<info>CLIENT_SECRET : </info>". $credential->secret." \n");


        }
        print_r ("=================================================================================================================================== \n");

    }
}
