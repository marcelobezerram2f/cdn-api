<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use App\Services\EventLogService;


class ZeroSslService
{
    private string $apiKey;
    private string $apiUrl = 'https://api.zerossl.com';
    private $logSys;
    protected $facilityLog;
    private $eabKid;
    private $hmacKey;

    public function __construct()
    {
        $this->apiKey = env('ZEROSSL_API_KEY');
        $this->logSys = new EventLogService();
        $this->facilityLog = basename(__FILE__);
        $this->eabKid =  env('ZEROSSL_EAB_KID');
        $this->hmacKey = env('ZEROSSL_EAB_HMAC_KEY');
    }

    public function order($domain)
    {
        $sintaxeOrder  =  "acme.sh --issue -d $domain --dns dns_acmedns \--yes-I-know-dns-manual-mode-enough-go-ahead-please";

        $order = shell_exec($sintaxeOrder .' 2>&1');


    }
}
