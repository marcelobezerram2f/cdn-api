<?php

namespace App\Repositories\DeskManager;

use App\Repositories\DeskManager\Lib\DeskManagerAPIRepository;
use App\Repositories\WhmcsInvoicesRepository;
use App\Models\CdnClient;
use App\Services\EventLogService;
use App\Services\UserIdFromTokenService;
use Exception;


class DeskManagerRepository
{

    const AUTO_CATEGORY = "11007";
    const REQUEST = "000004";
    const SERVICE_GROUP = "000092";
    private $cdnClient;
    private $logSys;
    protected $facilityLog;
    private $cdnTenant;
    private $userRespository;
    private $userIdFromToken;
    private $deskManagerApi;

    public function __construct()
    {
        $this->cdnClient = new CdnClient();
        $this->logSys = new EventLogService();
        $this->facilityLog = basename(__FILE__);
        $this->userIdFromToken = new UserIdFromTokenService();
        $this->deskManagerApi = new DeskManagerAPIRepository();

    }


    public function deskManagerGetCases($data)
    {
        try {

            $response = [];
            $userLogged = $this->userIdFromToken->getUserDataFromToken($data['token']);
            $client = $this->cdnClient->find($userLogged['cdn_client_id']);
            // Se o cliente se enquadrar na regra de external id
            if (strlen($client->external_id) > 5) {
                $token = $this->deskManagerApi->token(
                    env('DESKMANAGER_ENDPOINT') .
                    "/Login/autenticar",
                    env("DESKMANAGER_CHAVE_AMBIENTE"),
                    env("DESKMANAGER_CHAVE_OPERADOR"),
                );
                $endpoint = env('DESKMANAGER_ENDPOINT') . "/Usuarios/lista";
                $query = array("Pesquisa" => "", "Filtro" => array("_8816" => $client->external_id . "ADM"));

                $cases = $this->deskManagerApi->post($endpoint, $token["access_token"], $query);

                $requesting = ($cases->total != 1) ? false : true;
                if ($requesting && is_numeric($cases->root[0]->CodigoCliente) && $cases->root[0]->CodigoCliente > 1) {
                    $calls = $this->deskManagerApi->post(
                        env('DESKMANAGER_ENDPOINT') . "/ChamadosSuporte/lista",
                        $token["access_token"],
                        array("Pesquisa" => "", "Ativo" => "EmAberto", "Filtro" => array("CodCliente" => $cases->root[0]->CodigoCliente))
                    );
                } else {
                    $this->logSys->syslog(
                        '[CDN-API | CLIENT CDN] Consulta de lista de chamados do Desk Manager retornou vazia.',
                        null,
                        'INFO',
                        $this->facilityLog . ':' . basename(__FUNCTION__)
                    );
                    return [];
                }

                foreach ($calls->root as $call) {
                    $array = [
                        "case_id" => $call->CodChamado,
                        "case_number" => $call->CodChamado,
                        "contact_id" => null,
                        "subject" => $call->Assunto,
                        "department" => $call->NomeGrupo,
                        "status" => $call->NomeStatus,
                        "date" => $call->DataCriacao . "T" . $call->HoraCriacao,
                    ];

                    array_push($response, $array);
                    unset($array);
                }

                unset($data['token']);
                $this->logSys->syslog(
                    '[CDN-API | CLIENT CDN] Consulta de lista de chamados do Desk Manager  ocorreu com sucesso. ',
                    null,
                    'INFO',
                    $this->facilityLog . ':' . basename(__FUNCTION__)
                );
                return $response;
            }

        } catch (Exception $e) {
            $this->logSys->syslog(
                '[CDN-API | CLIENT CDN] Ocorreu uma exceção na consulta de lista de chamados do Desk Manager ',
                "Erro: " . $e->getMessage() . " Dados de entrada : " . json_encode($data),
                'ERROR',
                $this->facilityLog . ':' . basename(__FUNCTION__)
            );
            return ['message' => "Fatal failure occurred when querying the case list", "code" => 400, "errors" => [$e->getMessage()], "trace" => [$e->getTraceAsString()]];
        }
    }

    public function deskManagerClosedCases($data)
    {
        try {
            $response = [];
            $userLogged = $this->userIdFromToken->getUserDataFromToken($data['token']);
            $client = $this->cdnClient->find($userLogged['cdn_client_id']);
            // Se o cliente se enquadrar na regra de external id
            if (strlen($client->external_id) > 5) {
                $token = $this->deskManagerApi->token(
                    env('DESKMANAGER_ENDPOINT') .
                    "/Login/autenticar",
                    env("DESKMANAGER_CHAVE_AMBIENTE"),
                    env("DESKMANAGER_CHAVE_OPERADOR"),
                );
                $endpoint = env('DESKMANAGER_ENDPOINT') . "/Usuarios/lista";
                $query = array("Pesquisa" => "", "Filtro" => array("_8816" => $client->external_id . "ADM"));

                $cases = $this->deskManagerApi->post($endpoint, $token['access_token'], $query);

                $requesting = ($cases->total != 1) ? false : true;

                if ($requesting && is_numeric($cases->root[0]->CodigoCliente) && $cases->root[0]->CodigoCliente > 1) {
                    $calls = $this->deskManagerApi->post(
                        env('DESKMANAGER_ENDPOINT') . "/ChamadosSuporte/lista",
                        $token["access_token"],
                        array("Pesquisa" => "", "Ativo" => "Todos", "Filtro" => array("CodCliente" => $cases->root[0]->CodigoCliente, "CodStatusAtual" => "000002"))
                    );
                }

                foreach ($calls->root as $call) {

                    $array = [
                        "case_id" => $call->CodChamado,
                        "case_number" => $call->CodChamado,
                        "contact_id" => null,
                        "subject" => $call->Assunto,
                        "department" => $call->NomeGrupo,
                        "status" => $call->NomeStatus,
                        "date" => $call->DataCriacao . "T" . $call->HoraCriacao,
                    ];

                    array_push($response, $array);
                    unset($array);
                }

                unset($data['token']);
                $this->logSys->syslog(
                    '[CDN-API | CLIENT CDN] Consulta de lista de chamados do Desk Manager  ocorreu com sucesso. ',
                    null,
                    'INFO',
                    $this->facilityLog . ':' . basename(__FUNCTION__)
                );
                return $response;
            }
        } catch (Exception $e) {
            $this->logSys->syslog(
                '[CDN-API | CLIENT CDN] Ocorreu uma exceção na consulta de lista de casos fechados do Desk Manager ',
                'Endpoint  : ' . env('DESKMANAGER_ENDPOINT') . "/services/data/v49.0/query/ , Erro: " . $e->getMessage() . " Dados de entrada : " . json_encode($data),
                'ERROR',
                $this->facilityLog . ':' . basename(__FUNCTION__)
            );
            return ['message' => "Fatal failure occurred when querying the closed case list", "code" => 400, "errors" => [$e->getMessage()]];
        }
    }

    public function deskManagerInfoCases($data)
    {
        try {

            $response = [];
            $userLogged = $this->userIdFromToken->getUserDataFromToken($data['token']);
            $client = $this->cdnClient->find($userLogged['cdn_client_id']);
            $whmcs = new WhmcsInvoicesRepository();
            $clientInfo = $whmcs->getClients(['client_id' => $client->client_id, 'stats' => false]);
            // Se o cliente se enquadrar na regra de external id
            if (strlen($client->external_id) > 5) {
                $token = $this->deskManagerApi->token(
                    env('DESKMANAGER_ENDPOINT') .
                    "/Login/autenticar",
                    env("DESKMANAGER_CHAVE_AMBIENTE"),
                    env("DESKMANAGER_CHAVE_OPERADOR"),
                );

                if (strlen(base64_decode($data["case_number"])) > 5) {
                    $call = $this->deskManagerApi->post(env('DESKMANAGER_ENDPOINT') . "/ChamadosSuporte/lista", $token["access_token"], array("Pesquisa" => $data["case_number"]));
                    $historyCall = $this->deskManagerApi->post(env('DESKMANAGER_ENDPOINT') . "/ChamadoHistoricos/lista", $token["access_token"], array("CodChamado" => $data["case_number"], "Solicitante" => "S"));
                    $clientList = $this->deskManagerApi->post(env('DESKMANAGER_ENDPOINT') . "/Clientes/lista", $token["access_token"], array("Pesquisa" => "", "Filtro" => array("ExternalID" => $client->external_id)));
                    $requesting = $this->deskManagerApi->post(env('DESKMANAGER_ENDPOINT') . "/Usuarios", $token["access_token"], array("Chave" => $call->root[0]->ChaveUsuario));
                }

                $description = parseStringToArray($call->root[0]->Descricao);

                $history = [];
                $lasModification = null;

                if (!empty($historyCall->root)) {
                    foreach ($historyCall->root as $value) {
                        $array = [
                            "HtmlBody" => $value->Descricao,
                            "MessageDate" => $value->DataCriacao . "T" . $value->HoraCriacao
                        ];
                        array_push($history, $array);
                        unset($array);

                        $lasModification = $value->DataCriacao . "T" . $value->HoraCriacao;
                    }
                }

                $case = [
                    "Id"=>$call->root[0]->CodChamado,
                    "CaseNumber" => $call->root[0]->CodChamado,
                    "ContactEmail" => $clientInfo['email'],
                    "CreatedDate" => $call->root[0]->DataCriacao."T".$call->root[0]->HoraCriacao,
                    "Origin" =>"Web",
                    "ContactMobile" => null,
                    "Item__c"=> null,
                    "Type_c"=>null,
                    "Subtipo__c"=>null,
                    "ContactPhone" => $clientList->root[0]->Telefone,
                    "Description" => $description['caso'],
                    "LastModifiedDate" => !is_null($lasModification) ? $lasModification : $call->root[0]->DataCriacao . "T" . $call->root[0]->HoraCriacao,
                    "Status" => $call->root[0]->NomeStatus,
                    "Subject" => $description['assunto'],
                    "SuppliedEmail" => $description['email'],
                    "SuppliedName" => $description['cliente'],
                    "SuppliedPhone" => null,
                ];
                return ["case" => $case, "account" => $clientList, "history" => $history];

            }
        } catch (Exception $e) {
            $this->logSys->syslog(
                '[CDN-API | CLIENT CDN] Ocorreu uma exceção na consulta de histórico de caso do Desk Manager ',
                "Erro: " . $e->getMessage() . " Dados de entrada : " . json_encode($data),
                'ERROR',
                $this->facilityLog . ':' . basename(__FUNCTION__)
            );
            return ['message' => "Fatal failure occurred when querying the closed case list", "code" => 400, "errors" => [$e->getMessage()], $e->getTraceAsString];
        }
    }


    public function DeskManagerAddCommentOfCase($data)
    {
        try {
            $data['case_number'] = $data['id'];
            $userLogged = $this->userIdFromToken->getUserDataFromToken($data['token']);
            $client = $this->cdnClient->find($userLogged['cdn_client_id']);
            $whmcs = new WhmcsInvoicesRepository();
            $clientInfo = $whmcs->getClients(['client_id' => $client->client_id, 'stats' => false]);
            $from = $clientInfo["email"];
            $clientName = (strlen($clientInfo["companyname"]) < 3) ? $clientInfo["fullname"] : $clientInfo["companyname"];
            $tx = "Enviado Por: " . $clientName . "\n" . "Mensagem: " . $data["message"];
            $html = "Enviado Por: " . $clientName . "<br>" . "Mensagem: " . $data["message"];

            if (strlen($client->external_id) > 5) {
                $token = $this->deskManagerApi->token(
                    env('DESKMANAGER_ENDPOINT') .
                    "/Login/autenticar",
                    env("DESKMANAGER_CHAVE_AMBIENTE"),
                    env("DESKMANAGER_CHAVE_OPERADOR"),
                );

                if (strlen($data["case_number"]) > 5) {
                    $call = $this->deskManagerApi->post(env('DESKMANAGER_ENDPOINT') . "/ChamadosSuporte/lista", $token["access_token"], array("Pesquisa" => $data["case_number"]));
                    $historyCall = $this->deskManagerApi->post(env('DESKMANAGER_ENDPOINT') . "/ChamadoHistoricos/lista", $token["access_token"], array("CodChamado" => $data["case_number"], "Solicitante" => "S"));
                    $clientList = $this->deskManagerApi->post(env('DESKMANAGER_ENDPOINT') . "/Clientes/lista", $token["access_token"], array("Pesquisa" => "", "Filtro" => array("ExternalID" => $client->external_id)));
                    $requesting = $this->deskManagerApi->post(env('DESKMANAGER_ENDPOINT') . "/Usuarios", $token["access_token"], array("Chave" => $call->root[0]->ChaveUsuario));
                }

                if ($clientList->root[0]->Chave > 0 && is_numeric($clientList->root[0]->Chave)) {
                    if ($clientList->root[0]->Chave != $requesting->TUsuario->Cliente[0]->id) {
                        $call = null;
                        $historyCall = null;
                        $clientList = null;
                        $requesting = null;
                        $response = ['message'=>'Comment not added, customer ID is different from caller ID', 'code'=>400, 'errors'=>"user and call identifiers are different"];
                    } else {
                        $interactionData = array(
                            "TChamado" => array(
                                "Chave" => $call->root[0]->Chave,
                                "Solicitante" => $requesting->TUsuario->Chave,
                                "Descricao" => $data["message"]
                            )
                        );
                        $interaction = $this->deskManagerApi->put(env('DESKMANAGER_ENDPOINT') . "/Chamados", $token["access_token"], $interactionData);
                        if (isset($interaction->success) && $interaction->success = true) {
                            $response = ["message" => "Comentário incluso com sucesso!"];
                        } else if (isset($interaction->success) && $interaction->success = false) {
                            $response = ["message" => "Ocorreu uma falha na inclusão de comentário do chamado!", "errors" => $interaction, 400];

                        } else if (!isset($interection->success)) {
                            $response = ["message" => "Ocorreu uma falha na inclusão de comentário do chamado!", "errors" => $interaction, 400];
                        }

                    }
                }
                unset($data['token']);
            } else {
                $this->logSys->syslog(
                    '[CDN-API | CLIENT CDN]  Houve falha de na inclusão do comentário em caso Desk Manager. ',
                    "Endpoint :" . env('DESKMANAGER_ENDPOINT') . "/Chamados |  EXTERNAL_ID => " . externalIdMask($client->external_id) . "-  Dados de entrada : " . json_encode($data),
                    'INFO',
                    $this->facilityLog . ':' . basename(__FUNCTION__)
                );
                return ['code' => 400, 'message' => "External ID invalid!"];
            }
            $this->logSys->syslog(
                '[CDN-API | CLIENT CDN] Inclusão de comentário em caso Desk Manager ocorreu com sucesso. ',
                null,
                'INFO',
                $this->facilityLog . ':' . basename(__FUNCTION__)
            );
            return $response;

        } catch (Exception $e) {
            $this->logSys->syslog(
                '[CDN-API | CLIENT CDN] Ocorreu uma exceção na inclusão de comentário de caso do Desk Manager ',
                "Erro: " . $e->getMessage() . " Dados de entrada : " . json_encode($data),
                'ERROR',
                $this->facilityLog . ':' . basename(__FUNCTION__)
            );
            return ['message' => "Fatal failure in persistence new comment", "code" => 400, "errors" => [$e->getMessage()]];

        }
    }

    public function DeskmanagerOpenCase($data)
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

                $token = $this->deskManagerApi->Token(
                    env('DESKMANAGER_ENDPOINT') .
                    "/Login/autenticar",
                    env("DESKMANAGER_CHAVE_AMBIENTE"),
                    env("DESKMANAGER_CHAVE_OPERADOR"),
                );

                $account = $this->deskManagerApi->post(
                    env('DESKMANAGER_ENDPOINT') . "/Usuarios/lista",
                    $token['access_token'],
                    array("Pesquisa" => "", "Filtro" => array("_8816" => $client->external_id . "ADM"))
                );

                if ($account->total != 1) {
                    $content = "Cliente: " . $clientInfo["fullname"] . "\n" .
                        "CPF/CNPJ: " . $client->external_id . "\n" .
                        "Email: " . $clientInfo["email"] . "\n" .
                        "ClientId: " . $clientInfo["client_id"] . "\n" .
                        "Assunto: " . $data["subject"] . "\n\n" .
                        "Caso:\n" . $data["descripttion"];

                    $openCase = $this->deskManagerApi->put(
                        env('DESKMANAGER_ENDPOINT') . "/ChamadosSuporte/gerarReferencia",
                        $token["access_token"],
                        array("Descricao" => $content, "CodGrupo" => self::SERVICE_GROUP)
                    );
                } else {
                    $content = "Cliente: " . $clientInfo["fullname"] . "\n" .
                        "CPF/CNPJ: " . $client->external_id . "\n" .
                        "Email: " . $clientInfo["email"] . "\n" .
                        "ClientId: " . $clientInfo["client_id"] . "\n" .
                        "Assunto: " . $data['subject'] . "\n\n" .
                        "Caso: \n" . $data['description'];
                    $caseData = array(
                        "TChamado" => array(
                            "Solicitante" => $account->root[0]->Chave,
                            "AutoCategoria" => self::AUTO_CATEGORY,
                            "Solicitacao" => self::REQUEST,
                            "Assunto" => "[INT] " . $data['subject'],
                            "ObservacaoInterna" => "Chamado aberto via portal CDN pelo cliente\n\nÉ necessário recategorizar o chamado com a auto categoria correta referente ao tipo de atendimento requisitado.",
                            "Descricao" => $content
                        )
                    );
                    $openCase = $this->deskManagerApi->put(
                        env('DESKMANAGER_ENDPOINT') . "/ChamadosSuporte",
                        $token["access_token"],
                        $caseData
                    );
                }
                $openCase = $openCase[0];

                if (strlen($openCase) > 5) {
                    $response = [
                        "message" => "Caso aberto com sucesso",
                        "case_number" => $openCase,
                        "code" => 200
                    ];

                } else {
                    $response = [
                        "message" => "Ocorreu uma falha ao registrar caso",
                        "code" => 400
                    ];
                }
            }

            return $response;
        } catch (Exception $e) {
            $this->logSys->syslog(
                '[CDN-API | CLIENT CDN] Ocorreu uma exceção na inclusão novo de caso do Desk Manager ',
                "Erro: " . $e->getMessage() . " Dados de entrada : " . json_encode($data),
                'ERROR',
                $this->facilityLog . ':' . basename(__FUNCTION__)
            );
            return ['message' => "Fatal failure in persistence new case", "code" => 400, "errors" => [$e->getMessage()]];
        }
    }


    public function externalIdFalse($data)
    {

        $email_padrao = (strlen($data["EmailPadrao"]) < 5) ? "servicecloud@servicedesk.srv.br" : $data["EmailPadrao"];
        return array(
            'pagetitle' => 'Desk Manager',
            'breadcrumb' => array('index.php?m=Desk Manager' => 'Desk Manager'),
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
