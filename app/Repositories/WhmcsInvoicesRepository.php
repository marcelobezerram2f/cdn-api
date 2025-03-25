<?php

namespace App\Repositories;

use App\Models\CdnClient;
use App\Models\CdnTenant;
use App\Services\EventLogService;
use App\Services\UserIdFromTokenService;
use Exception;

class WhmcsInvoicesRepository
{

    private $cdnClient;
    private $logSys;
    protected $facilityLog;
    private $cdnTenant;
    private $userRespository;
    private $userIdFromToken;

    public function __construct()
    {
        $this->cdnClient = new CdnClient();
        $this->logSys = new EventLogService();
        $this->facilityLog = basename(__FILE__);
        $this->userIdFromToken = new UserIdFromTokenService();
    }

    public function getClients($data)
    {

        $body = [
            'action' => 'GetClientsDetails',
            'username' => env('WHMCS_IDENTIFIER'),
            'password' => env('WHMCS_SECRET_ID'),
            'clientid' => $data['client_id'],
            'stats' => $data['stats'],
            'responsetype' => 'json',
        ];
        $response = json_decode($this->whmcsConsumer($body), true);
        return $response['client'];
    }


    public function ssoInvoice($data)
    {
        $userLogged = $this->userIdFromToken->getUserDataFromToken($data['token']);
        $client = $this->cdnClient->find($userLogged['cdn_client_id']);
        $body = [
            'action' => 'CreateSsoToken',
            'username' => env('WHMCS_IDENTIFIER'),
            'password' => env('WHMCS_SECRET_ID'),
            'client_id' => $client->client_id,
            'destination' => 'sso:custom_redirect',
            'sso_redirect_path' => 'viewinvoice.php?id=' . $data['invoice'],
            'responsetype' => 'json',
        ];
        return $this->whmcsConsumer($body);
    }

    public function ssoInvoices($data)
    {
        $userLogged = $this->userIdFromToken->getUserDataFromToken($data['token']);
        $client = $this->cdnClient->find($userLogged['cdn_client_id']);
        $body = [
            'action' => 'CreateSsoToken',
            'username' => env('WHMCS_IDENTIFIER'),
            'password' => env('WHMCS_SECRET_ID'),
            'client_id' => $client->client_id,
            'destination' => 'clientarea:invoices',
            'responsetype' => 'json',
        ];
        return $this->whmcsConsumer($body);
    }


    public function getInvoice($data)
    {
        $body = [
            'action' => 'GetInvoice',
            'username' => env('WHMCS_IDENTIFIER'),
            'password' => env('WHMCS_SECRET_ID'),
            'invoiceid' => $data['invoice'],
            'responsetype' => 'json',
        ];
        return json_decode($this->whmcsConsumer($body), true);
    }

    public function getInvoices($data)
    {
        try {
            $userLogged = $this->userIdFromToken->getUserDataFromToken($data['token']);

            $client = $this->cdnClient->find($userLogged['cdn_client_id']);
            $body = [
                'action' => "GetInvoices",
                'username' => env('WHMCS_IDENTIFIER'),
                'password' => env('WHMCS_SECRET_ID'),
                'userid' => $client->client_id,
                'order_by' => 'invoicenumber',
                'responsetype' => 'json',
            ];
            $result = json_decode($this->whmcsConsumer($body), true);
            if (empty($result["invoices"])) {
                $response = $this->handleInvoices($result);

            } else {

                $response = $this->handleInvoices($result);
            }
        } catch (Exception $e) {
            $this->logSys->syslog(
                '[CDN-API | CLIENT CDN] Ocorreu uma falha na recuperação das invoices do client ',
                'ERRO : ' . $e->getMessage() . '. Parâmetros de recebidos: ' . json_encode($data),
                'ERROR',
                $this->facilityLog . ':' . basename(__FUNCTION__)
            );
            $response = [
                'code' => 400,
                'message' => 'Fatal failure in get invoices. Please inform your system administrator.',
                'errors' => ['Get Invoices failed', $e->getMessage()]
            ];
        }
        return $response;
    }

    public function whmcsConsumer($body)
    {
        try {

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, env('WHMCS_URL'));
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt(
                $ch,
                CURLOPT_POSTFIELDS,
                http_build_query($body)
            );
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            $request = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($code == 200) {
                $response = $request;
            } else if ($code >= 400) {
                $this->logSys->syslog(
                    '[CDN-API | CLIENT CDN] Ocorreu uma falha na API WHMCS na recuperação da(s) invoice(s)',
                    'Dados de requicisão : ' . json_encode($body) . '. Return : ' . $request,
                    'ERROR',
                    $this->facilityLog . ':' . basename(__FUNCTION__)
                );
                $response = [
                    'code' => 400,
                    'message' => 'The WHMCS API failed to retrieve the invoice. Please inform your system administrator.',
                    'errors' => ['Get Invoices failed :', $request]
                ];
            }

        } catch (Exception $e) {
            $this->logSys->syslog(
                '[CDN-API | CLIENT CDN]  Ocorreu uma falha na API WHMCS na recuperação da(s) invoice(s)',
                'ERRO : ' . $e->getMessage() . '. Parâmetros de recebidos: ' . json_encode($body),
                'ERROR',
                $this->facilityLog . ':' . basename(__FUNCTION__)
            );
            $response = [
                'code' => 400,
                'message' => 'Fatal failure in get invoices. Please inform your system administrator.',
                'errors' => ['Get Invoices failed', $e->getMessage()]
            ];
        }

        return $response;
    }

    public function handleInvoices($invoices)
    {

        $response['invoices'] = [];
        $response['totalresults'] = $invoices['totalresults'];
        if (!empty($invoices['invoices'])) {
            $response['firstname'] = $invoices['invoices']['invoice'][0]['firstname'];
            $response['lastname'] = $invoices['invoices']['invoice'][0]['lastname'];
            $response['companyname'] = $invoices['invoices']['invoice'][0]['companyname'];
            foreach ($invoices['invoices']['invoice'] as $invoice) {
                $data = [
                    'invoice' => $invoice['id'],
                    'date' => $invoice['date'],
                    'duedate' => $invoice['duedate'],
                    'datepaid' => $invoice['datepaid'],
                    'total' => $invoice['total'],
                    'status' => $invoice['status']
                ];
                array_push($response['invoices'], $data);
                unset($data);
            }
        } else {
            $response['firstname'] = null;
            $response['lastname'] = null;
            $response['companyname'] = null;
        }
        return $response;
    }

}
