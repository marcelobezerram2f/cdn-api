<?php

namespace App\Console\Commands;

use App\Models\CdnLetsencryptAcmeRegister;
use Illuminate\Console\Command;
use Carbon\Carbon;

class ResetTriesSSLInstall extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tries-ssl-install:reset';

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
        $sslCertificates = CdnLetsencryptAcmeRegister::whereNot('last_attempt')->get();
        foreach($sslCertificates as $sslCertificate) {
            if($this->attemptsIfExpired($sslCertificate->last_attempt)) {
                $sslCertificate->attempt_install = null;
                $sslCertificate->last_attempt = null;
                $sslCertificate->save();
            }
        }
    }

    public function attemptsIfExpired($last_attempt)
{
    // Define o momento atual
    $now = Carbon::now();

    // Calcula a diferença em minutos entre $last_attempt e $now
    $lastAttemptTime = Carbon::createFromFormat('Y-m-d H:i:s', $last_attempt);

    if ($lastAttemptTime->diffInMinutes($now) >= 30) {
        return true;
    } else {
        return false;
    }
}
}
