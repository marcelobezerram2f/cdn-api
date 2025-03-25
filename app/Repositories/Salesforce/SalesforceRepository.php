<?php

namespace App\Repositories\Salesforce;

use App\Repositories\Salesforce\Lib\SalesforceAPIRepository as SalesforceAPI;
use App\Repositories\WhmcsInvoicesRepository;

use App\Models\CdnClient;
use App\Models\CdnTenant;
use App\Services\EventLogService;
use App\Services\UserIdFromTokenService;
use Exception;


class SalesforceRepository
{
    private $cdnClient;
    private $logSys;
    protected $facilityLog;
    private $cdnTenant;
    private $userRespository;
    private $userIdFromToken;
    private $salesforceApi;
    public function __construct()
    {
        $this->cdnClient = new CdnClient();
        $this->logSys = new EventLogService();
        $this->facilityLog = basename(__FILE__);
        $this->userIdFromToken = new UserIdFromTokenService();
        $this->salesforceApi = new SalesforceAPI();
    }


    public function salesforceGetCases($data)
    {
        try {
            $response = [];
            $userLogged = $this->userIdFromToken->getUserDataFromToken($data['token']);
            $client = $this->cdnClient->find($userLogged['cdn_client_id']);
            // Se o cliente se enquadrar na regra de external id
            if (strlen($client->external_id) > 5) {
                $token = $this->salesforceApi->Token(
                    env('SALESFORCE_ENDPOINT') .
                    "/services/oauth2/token",
                    env("SALESFORCE_CLIENTID"),
                    env("SALESFORCE_SECRET"),
                    env("SALESFORCE_USER"),
                    env("SALESFORCE_PASS") . env("SALESFORCE_TOKEN")
                );
                $endpoint  =  env('SALESFORCE_ENDPOINT')."/services/data/v49.0/query/";
                $query = str_replace(
                        " ",
                        "%20",
                        "?q=SELECT Id, CaseNumber, ContactId, Contact.Name, AccountId, Account.Name, Account.External_ID__c, ParentId, SuppliedName, SuppliedEmail, SuppliedPhone, SuppliedCompany, Type, Status, Reason, Origin, Language, Subject, Priority, Description, IsClosed, ClosedDate, IsEscalated, OwnerId, IsClosedOnCreate, SlaStartDate, SlaExitDate, IsStopped, StopStartDate, CreatedDate, ContactPhone, ContactMobile, ContactEmail, ContactFax, Comments, Email_enviado_base__c, Subtipo__c, Item__c, Email_de_destino__c, Prioridade_Padrao__c, Proprietario_Padrao__c, Prioridade_da_SLA__c, Resolucao__c, Tipo__c, indicador_de_restricao__c, Marco_Violado_Base__c, Tempo_Total__c, Marco_Violado__c FROM Case WHERE Account.External_ID__c = '" . $client->external_id . "' AND Status != 'Fechado' order by CreatedDate desc"
                );
                $cases = $this->salesforceApi->Get($endpoint,$token,$query);

                foreach ($cases->records as $case) {

                    $array = [
                        "case_id" => $case->Id,
                        "case_number" => $case->CaseNumber,
                        "contact_id" => $case->ContactId,
                        "subject" => $case->Subject,
                        "department" => $case->Tipo__c,
                        "status" => $case->Status,
                        "date" => $case->CreatedDate,
                    ];

                    array_push($response, $array);
                    unset($array);
                }

                unset($data['token']);
                $this->logSys->syslog(
                    '[CDN-API | CLIENT CDN] Consulta de lista de chamados do Salesforce  ocorreu com sucesso. ',
                    "Endpoint  : $endpoint, Query = $query , EXTERNAL_ID => ".externalIdMask($client->external_id) ."-  Dados de entrada : ".json_encode($data),
                    'INFO',
                    $this->facilityLog . ':' . basename(__FUNCTION__)
                );
                return $response;
            }

        } catch(Exception $e) {
            $this->logSys->syslog(
                '[CDN-API | CLIENT CDN] Ocorreu uma exceção na consulta de lista de chamados do Salesforce ',
                "Erro: ". $e->getMessage()." Dados de entrada : ".json_encode($data),
                'ERROR',
                $this->facilityLog . ':' . basename(__FUNCTION__)
            );
            return ['message' => "Fatal failure occurred when querying the case list", "code"=>400, "errors" =>[$e->getMessage()]];
        }
    }

    public function salesforceGetClosedCases($data)
    {
        try {
            $userLogged = $this->userIdFromToken->getUserDataFromToken($data['token']);
            $client = $this->cdnClient->find($userLogged['cdn_client_id']);
            // Se o cliente se enquadrar na regra de external id
            if (strlen($client->external_id) > 5) {
                $token = $this->salesforceApi->Token(
                    env('SALESFORCE_ENDPOINT') .
                    "/services/oauth2/token",
                    env("SALESFORCE_CLIENTID"),
                    env("SALESFORCE_SECRET"),
                    env("SALESFORCE_USER"),
                    env("SALESFORCE_PASS") . env("SALESFORCE_TOKEN")
                );
                $endpoint = env('SALESFORCE_ENDPOINT') ."/services/data/v49.0/query/";
                $query = str_replace(" ", "%20", "?q=SELECT Id, CaseNumber, ContactId, Contact.Name, AccountId, Account.Name, Account.External_ID__c, ParentId, SuppliedName, SuppliedEmail, SuppliedPhone, SuppliedCompany, Type, Status, Reason, Origin, Language, Subject, Priority, Description, IsClosed, ClosedDate, IsEscalated, OwnerId, IsClosedOnCreate, SlaStartDate, SlaExitDate, IsStopped, StopStartDate, CreatedDate, ContactPhone, ContactMobile, ContactEmail, ContactFax, Comments, Email_enviado_base__c, Subtipo__c, Item__c, Email_de_destino__c, Prioridade_Padrao__c, Proprietario_Padrao__c, Prioridade_da_SLA__c, Resolucao__c, Tipo__c, indicador_de_restricao__c, Marco_Violado_Base__c, Tempo_Total__c, Marco_Violado__c FROM Case WHERE Account.External_ID__c = '" . $client->external_id . "' AND Status = 'Fechado' order by CreatedDate desc");
                $cases = $this->salesforceApi->Get($endpoint,$token,$query);
            }

            unset($data['token']);
            $this->logSys->syslog(
                '[CDN-API | CLIENT CDN] Consulta de lista de casos fechado do Salesforce  ocorreu com sucesso. ',
                "Endpoint  : $endpoint, Query : $query -  Dados de entrada : ".json_encode($data),
                'INFO',
                $this->facilityLog . ':' . basename(__FUNCTION__)
            );

            return $cases->records;

        } catch (Exception $e) {
            $this->logSys->syslog(
                '[CDN-API | CLIENT CDN] Ocorreu uma exceção na consulta de lista de casos fechados do Salesforce ',
                'Endpoint  : ' . env('SALESFORCE_ENDPOINT') ."/services/data/v49.0/query/ , Erro: ". $e->getMessage()." Dados de entrada : ".json_encode($data),
                'ERROR',
                $this->facilityLog . ':' . basename(__FUNCTION__)
            );
            return ['message' => "Fatal failure occurred when querying the closed case list", "code"=>400, "errors" =>[$e->getMessage()]];
        }
    }

    public function salesforceInfoCases($data)
    {
        try {

            $userLogged = $this->userIdFromToken->getUserDataFromToken($data['token']);
            $client = $this->cdnClient->find($userLogged['cdn_client_id']);
            $whmcs = new WhmcsInvoicesRepository();
            $clientInfo = $whmcs->getClients(['client_id' => $client->client_id, 'stats' => false]);
            // Se o cliente se enquadrar na regra de external id
            if (strlen($client->external_id) > 5) {
                $token = $this->salesforceApi->Token(
                    env('SALESFORCE_ENDPOINT') .
                    "/services/oauth2/token",
                    env("SALESFORCE_CLIENTID"),
                    env("SALESFORCE_SECRET"),
                    env("SALESFORCE_USER"),
                    env("SALESFORCE_PASS") . env("SALESFORCE_TOKEN")
                );
                $endpointCase  = env('SALESFORCE_ENDPOINT') . "/services/data/v49.0/sobjects/Case/CaseNumber/";

                $case = $this->salesforceApi->Get($endpointCase,$token,$data["case_number"]);

                $endpointAccount  = env('SALESFORCE_ENDPOINT') . "/services/data/v49.0/sobjects/Account/External_ID__c/";
                $account = $this->salesforceApi->Get($endpointAccount,$token,$client->external_id);

                $endpointHistory = "";
                $queryHistory = "";
                if (strlen($case->Id) > 1) {
                    $endpointHistory = env('SALESFORCE_ENDPOINT') . "/services/data/v49.0/query/";
                    $queryHistory = str_replace(" ", "%20", "?q=Select ActivityId, BccAddress, CcAddress, CreatedById, CreatedDate, FromAddress, FromName, HasAttachment, Headers, HtmlBody, Id, Incoming, IsClientManaged, IsDeleted, IsExternallyVisible, LastModifiedById, LastModifiedDate, MessageDate, MessageIdentifier, ParentId, RelatedToId, ReplyToEmailMessageId, Status, Subject, SystemModstamp, TextBody, ThreadIdentifier, ToAddress, ValidatedFromAddress From EmailMessage WHERE ParentId = '" . $case->Id . "' ORDER BY CreatedDate DESC");
                    $history = $this->salesforceApi->Get($endpointHistory,$token,$queryHistory);
                }

                if ($account->Id != $case->AccountId) {
                    return [
                        'message' => "This case does not belong to your account",
                        'code' => 400
                    ];
                }

                unset($data['token']);
                $this->logSys->syslog(
                    '[CDN-API | CLIENT CDN] Consulta de histórico de caso Salesforce ocorreu com sucesso. ',
                    "Endpoint Case  : $endpointCase Case Number : ".$data['case_number']."| Endpoint Account : $endpointAccount | Account : ".externalIdMask($client->external_id) ." | EndpointHistory : $endpointHistory |QueryHistory - $queryHistory  | Dados de entrada : ".json_encode($data),
                    'INFO',
                    $this->facilityLog . ':' . basename(__FUNCTION__)
                );

                return ["case" => $case, "account" => $account, "history" => $history->records];
            }
        } catch (Exception $e) {
            $this->logSys->syslog(
                '[CDN-API | CLIENT CDN] Ocorreu uma exceção na consulta de histórico de caso do Salesforce ',
                "Erro: ". $e->getMessage()." Dados de entrada : ".json_encode($data),
                'ERROR',
                $this->facilityLog . ':' . basename(__FUNCTION__)
            );
            return ['message' => "Fatal failure occurred when querying the closed case list", "code"=>400, "errors" =>[$e->getMessage()]];
        }
    }


    public function salesforceAddCommentOfCase($data)
    {
        try {

            $userLogged = $this->userIdFromToken->getUserDataFromToken($data['token']);
            $client = $this->cdnClient->find($userLogged['cdn_client_id']);
            $whmcs = new WhmcsInvoicesRepository();
            $clientInfo = $whmcs->getClients(['client_id' => $client->client_id, 'stats' => false]);
            $from = $clientInfo["email"];
            $to = $data['Email_de_destino__c'];
            $clientName = (strlen($clientInfo["companyname"]) < 3) ? $clientInfo["fullname"] : $clientInfo["companyname"];
            $tx = "Enviado Por: " . $clientName . "\n" . "Mensagem: " . $data["message"];
            $html = "Enviado Por: " . $clientName . "<br>" . "Mensagem: " . $data["message"];

            $interaction_data = array(
                "ParentId" => $data["id"],
                "TextBody" => $tx,
                "HtmlBody" => $html,
                "Subject" => $data["subject"],
                "FromName" => $clientName,
                "FromAddress" => $from,
                "ToAddress" => $to,
                "Status" => "0",
                "RelatedToId" => $data["id"],
                "IsTracked" => "true",
                "Incoming" => "true"
            );

            $token = $this->salesforceApi->Token(
                env('SALESFORCE_ENDPOINT') .
                "/services/oauth2/token",
                env("SALESFORCE_CLIENTID"),
                env("SALESFORCE_SECRET"),
                env("SALESFORCE_USER"),
                env("SALESFORCE_PASS") . env("SALESFORCE_TOKEN")
            );

            $interaction = $this->salesforceApi->Post(
                env('SALESFORCE_ENDPOINT') . "/services/data/v49.0/sobjects/EmailMessage",
                $token,
                json_encode($interaction_data)
            );

            if (isset($interaction->success) && $interaction->success = true) {
                $response = ["message" => "Comentário incluso com sucesso!"];
            } else if (isset($interaction->success) && $interaction->success = false) {
                $response = ["message" => "Ocorreu uma falha na inclusão de comentário do chamado!", "errors" => $interaction, 400];

            } else if (!isset($interection->success)) {
                $response = ["message" => "Ocorreu uma falha na inclusão de comentário do chamado!", "errors" => $interaction, 400];
            }

            unset($data['token']);
            $this->logSys->syslog(
                '[CDN-API | CLIENT CDN] Inclusão de comentário em caso Salesforce ocorreu com sucesso. ',
                "Endpoint :".env('SALESFORCE_ENDPOINT') . "/services/data/v49.0/sobjects/EmailMessage | Query :".json_encode($interaction_data)." | EXTERNAL_ID => ".externalIdMask($client->external_id) ."-  Dados de entrada : ".json_encode($data),
                'INFO',
                $this->facilityLog . ':' . basename(__FUNCTION__)
            );
            return $response;
        } catch (Exception $e) {
            $this->logSys->syslog(
                '[CDN-API | CLIENT CDN] Ocorreu uma exceção na inclusão de comentário de caso do Salesforce ',
                "Erro: ". $e->getMessage()." Dados de entrada : ".json_encode($data),
                'ERROR',
                $this->facilityLog . ':' . basename(__FUNCTION__)
            );
            return ['message' => "Fatal failure in persistence new comment", "code"=>400, "errors" =>[$e->getMessage()]];

        }
    }

    public function salesforceOpenCase($data)
    {
        try {

            $userLogged = $this->userIdFromToken->getUserDataFromToken($data['token']);
            $client = $this->cdnClient->find($userLogged['cdn_client_id']);
            $whmcs = new WhmcsInvoicesRepository();

            $clientInfo = $whmcs->getClients(['client_id' => $client->client_id, 'stats' => false]);

            $erro = 0;
            $erro_tx = null;
            if (strlen($data["subject"]) < 3) {
                $erro++;
                $erro_tx .= "Você deve informar um assunto para o Chamado<br>";
            }
            if (strlen($data["description"]) < 3) {
                $erro++;
                $erro_tx .= "Você deve informar a Descrição do Caso<br>";
            }

            if ($erro < 1) {

                $token = $this->salesforceApi->Token(
                    env('SALESFORCE_ENDPOINT') .
                    "/services/oauth2/token",
                    env("SALESFORCE_CLIENTID"),
                    env("SALESFORCE_SECRET"),
                    env("SALESFORCE_USER"),
                    env("SALESFORCE_PASS") . env("SALESFORCE_TOKEN")
                );

                $account = $this->salesforceApi->Get(
                    env('SALESFORCE_ENDPOINT') . "/services/data/v49.0/sobjects/Account/External_ID__c/",
                    $token,
                    $client->external_id
                );
                $contact = $this->salesforceApi->Get(
                    env('SALESFORCE_ENDPOINT') . "/services/data/v49.0/sobjects/Contact/External_ID__c/",
                    $token,
                    $client->external_id .
                    "ADM"
                );



                $clientName = (strlen($clientInfo["companyname"]) < 3) ? $clientInfo["fullname"] : $clientInfo["companyname"];

                if (strlen($account->Id) > 5) {
                    $content = $data["description"];
                } else {
                    $content = "Documento: " . $client->external_id . "\n----------------------------------\n" . $data["description"];
                }

                $email_padrao = env('SALESFORCE_DEFAULT_EMAIL');

                $dados = array(
                    "SuppliedEmail" => $clientInfo["email"],
                    "SuppliedName" => $clientName,
                    "SuppliedPhone" => $clientInfo["phonenumber"],
                    "Origin" => "Web",
                    "Subject" => "[CDN] " . $data["subject"],
                    "Description" => $content,
                    "Email_de_destino__c" => $email_padrao,
                    "OwnerId" => $contact->OwnerId,
                );

                if (strlen($account->Id) > 5) {
                    $dados["AccountId"] = $account->Id;
                }
                if (strlen($contact->Id) > 5) {
                    $dados["ContactId"] = $contact->Id;
                }
                $openCase = $this->salesforceApi->Post(
                    env('SALESFORCE_ENDPOINT') .
                    "/services/data/v49.0/sobjects/Case/",
                    $token,
                    json_encode($dados)
                );

                $case = $this->salesforceApi->Get(
                    env('SALESFORCE_ENDPOINT') .
                    "/services/data/v49.0/sobjects/Case/Id/",
                    $token,
                    $openCase->id
                );

                if (isset($openCase->success) ) {
                    if ($openCase->success == true) {
                        $response  =  [
                            "message" => "Caso aberto com sucesso",
                            "case_number" => $case->CaseNumber,
                            "code" => 200
                        ];
                    } else {
                        $response  =  [
                            "message" => "Ocorreu uma falha ao registrar caso",
                            "erros" => $openCase,
                            "code" => 400
                        ];
                    }
                }
            }else {
                $response  =  [
                    "message" => "Ocorreu uma falha ao registrar caso",
                    "erros" => $erro_tx,
                    "code" => 400
                ];
            }
            $getAccount =  env('SALESFORCE_ENDPOINT') . "/services/data/v49.0/sobjects/Account/External_ID__c/  | Query: ".externalIdMask($client->external_id);
            $getContact =  $getAccount. "ADM";
            $ReqOpen = env('SALESFORCE_ENDPOINT') ."/services/data/v49.0/sobjects/Case/";
            $reqCase = env('SALESFORCE_ENDPOINT') ."/services/data/v49.0/sobjects/Case/Id/";
            unset($data['token']);
            $this->logSys->syslog(
                '[CDN-API | CLIENT CDN] Inclusão novo caso Salesforce ocorreu com sucesso. ',
                "Endpoint GET ACCOUNT : $getAccount | Endpoint GET CONTACT : $getContact | Endpoint OPEN CASE $ReqOpen -- Query : ".json_encode($dados). "Endpoint CASE $reqCase -- Query  $openCase->id -- Dados de Entrada : ".json_encode($data),
                'INFO',
                $this->facilityLog . ':' . basename(__FUNCTION__)
            );
            return $response;
        } catch (Exception $e) {
            $this->logSys->syslog(
                '[CDN-API | CLIENT CDN] Ocorreu uma exceção na inclusão novo de caso do Salesforce ',
                "Erro: ". $e->getMessage()." Dados de entrada : ".json_encode($data),
                'ERROR',
                $this->facilityLog . ':' . basename(__FUNCTION__)
            );
            return ['message' => "Fatal failure in persistence new case", "code"=>400, "errors" =>[$e->getMessage()]];
        }
    }


    public function externalIdFalse($data)
    {

        $email_padrao = (strlen($data["EmailPadrao"]) < 5) ? "servicecloud@servicedesk.srv.br" : $data["EmailPadrao"];
        return array(
            'pagetitle' => 'Salesforce',
            'breadcrumb' => array('index.php?m=salesforce' => 'Salesforce'),
            'templatefile' => 'templates/clienterro',
            'requirelogin' => true, # accepts true/false
            'forcessl' => false, # accepts true/false
            'vars' => array(
                'email' => $email_padrao,
                'link' => $data['modulelink']
            ),
        );

    }







}
