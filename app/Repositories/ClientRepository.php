<?php

namespace App\Repositories;

use App\Models\CdnClient;
use App\Models\CdnTargetGroup;
use App\Models\CdnTenant;
use App\Services\EventLogService;
use Exception;



class ClientRepository
{


    private $cdnClient;
    private $logSys;
    protected $facilityLog;
    private $cdnTenant;
    private $userRespository;

    public function __construct()
    {
        $this->cdnClient = new CdnClient();
        $this->logSys = new EventLogService();
        $this->facilityLog = basename(__FILE__);
        $this->cdnTenant = new CdnTenant();
        $this->userRespository = new UserRepository();
    }


    public function getAll()
    {
        try {
            $accounts = $this->cdnClient->all();

            $response = [];

            if ($accounts) {
                foreach ($accounts as $account) {
                    // Recupera os tenants relacionamdos ao cliente
                    $tenants = [];
                    $users = [];
                    foreach ($account->tenants as $tenant) {
                        $targetGroup = CdnTargetGroup::find($tenant->cdn_target_group_id);
                        $dataTenant = [
                            "tenant" => $tenant->tenant,
                            "api_key" => $tenant->api_key,
                            "cdn_target_group" => $targetGroup->name
                        ];
                        array_push($tenants, $dataTenant);
                        unset($dataTenant);
                    }
                    // Recupera os usuários relacionamdos ao cliente
                    foreach ($account->users as $user) {
                        $dataUser = [
                            "name" => $user->name,
                            "user_name" => $user->user_name,
                            "email" => $user->email
                        ];
                        array_push($users, $dataUser);
                        unset($dataTenant);
                    }

                    $data = [
                        "name_base" => $account->name,
                        "account" => $account->account,
                        "users" => $users,
                        "tenants" => $tenants
                    ];
                    array_push($response, $data);
                    unset($data);

                }
            } else {
                $this->logSys->syslog(
                    '[CDN-API | CLIENT CDN] Query de retorno de todos clientes da tabela CDN_CLIENTS retornou vazia',
                    'Query : ' . "whereNotNull('id')->with(['user', 'tenants'])->get()",
                    'ALERT',
                    $this->facilityLog . ':' . basename(__FUNCTION__)
                );
                $response = ["code" => 400, "message" => "Empty account database", "error" => ["There are no registered accounts to list"]];
            }
        } catch (Exception $e) {
            $this->logSys->syslog(
                '[CDN-API | CLIENT CDN] Ocorreu uma exceção na consulta de todos clientes',
                'ERRO : ' . $e->getMessage(),
                'ERROR',
                $this->facilityLog . ':' . basename(__FUNCTION__)
            );

            $response = ["code" => 400, "message" => "Fatal failure in account list recovery", "error" => [$e->getMessage()]];
        }
        return $response;
    }

    public function getByAccount($data)
    {
        try {

            if (empty($data['account']) || !isset($data['account']) ) {
                return [
                    'code' => 400,
                    'message' => 'Warning!Invalidation of the data entered.',
                    'errors' => ['account field is required'],
                ];
            }
            $client = $this->cdnClient->where('account', $data['account'])->with(['users', 'tenants'])->get();
            $response = [];
            $tenants = [];
            $users = [];
            if ($client) {
                foreach ($client as $account) {
                    foreach ($account->tenants as $tenant) {
                        $targetGroup = CdnTargetGroup::find($tenant->cdn_target_group_id);
                        $dataTenant = [
                            "tenant" => $tenant->tenant,
                            "api_key" => $tenant->api_key,
                            "cdn_target_group" => $targetGroup->name
                        ];
                        array_push($tenants, $dataTenant);
                        unset($dataTenant);
                    }
                    foreach ($account->users as $user) {
                        $dataUser = [
                            "name" => $user->name,
                            "user_name" => $user->user_name,
                            "email" => $user->email
                        ];
                        array_push($users, $dataUser);
                        unset($dataTenant);
                    }
                    $data = [
                        "name_base" => $account->name,
                        "account" => $account->account,
                        "users" => $users,
                        "tenants" => $tenants
                    ];
                    array_push($response, $data);
                    unset($data);
                }
                $response["code"] = 200;
            } else {
                $this->logSys->syslog(
                    '[CDN-API | CLIENT CDN] Query de retorno da conta de cliente da tabela CDN_CLIENTS retornou vazia',
                    'Query : ' . "whereNotNull('id')->with(['user', 'tenants'])->get()",
                    'ALERT',
                    $this->facilityLog . ':' . basename(__FUNCTION__)
                );
                $response = ["code" => 400, "message" => "account not founr ", "error" => ["Account not found in the database "]];
            }
        } catch (Exception $e) {
            $this->logSys->syslog(
                '[CDN-API | CLIENT CDN] Ocorreu uma exceção na consulta de conta de cliente',
                'ERRO : ' . $e->getMessage() . "Parâmetros recebidos :" . $data['account'],
                'ERROR',
                $this->facilityLog . ':' . basename(__FUNCTION__)
            );

            $response = ["code" => 400, "message" => "Fatal failure in account recovery", "error" => [$e->getMessage()]];
        }
        return $response;

    }

    /**
     * Método responsável por :
     * a) criar e persistir conta do cliente na tabela cdn_clients
     * b) invocar criação do usuário master
     *
     * @param array $data
     *
     * @return array $response
     */


    public function create($data)
    {
        try {
            $data['account'] = strtolower(makeAccountName($data['name_base']));
            $data['name'] = strtolower($data['name_base']);
            $newClient = $this->cdnClient->create($data);
            $dataUser['name'] = $data['name_base'];
            $dataUser['user_name'] = nextTenantOrUser($data['account'], 'u');
            $dataUser['cdn_client_id'] = $newClient->id;
            $dataUser['email'] = $data['email'];
            $newUser = $this->userRespository->create($dataUser, $data);
            if ($newUser['code'] == 400) {
                /*
                * Caso haja alguma falha na criação do usuário o client account é excluido (rollback do client)
                * e retorna o erro de cliação de usuário para a requisição inicial
                */
                $this->delete($data);
                return $newUser;
            }

            $response = [
                "id" => $newClient->id,
                "account" => $data['account'],
                "main_user" => $dataUser['user_name'],
                "password" => $newUser['password']
            ];
            /*$this->logSys->auditLog(
                $data['header'],
                'create account',  'INFO', 'Client Account criada com sucesso ', $data, $response
            );*/
        } catch (Exception $e) {
            $this->logSys->syslog(
                '[CDN-API | CLIENT CDN] Ocorreu uma falha na persitência dos dados do cliente',
                'ERRO : ' . $e->getMessage() . '. Parâmetros de recebidos: ' . json_encode($data),
                'ERROR',
                $this->facilityLog . ':' . basename(__FUNCTION__)
            );
            $response = [
                'code' => 400,
                'message' => 'Fatal failure in customer data persistence. Please inform your system administrator.',
                'errors' => ['Database inclusion failed', $e->getMessage()]
            ];


        }
        return $response;
    }


    public function resetPasswordByUserName($data)
    {
        try {

            if (empty($data['user_name']) || !isset($data['user_name']) ) {
                return [
                    'code' => 400,
                    'message' => 'Warning!Invalidation of the data entered.',
                    'errors' => ['user_name field is required'],
                ];
            }
            $resetPassword = $this->userRespository->resetPasswordByUserName($data['user_name']);

            if ($resetPassword['code'] == 200) {
                $account = $this->cdnClient->find($resetPassword['cdn_client_id']);
                $response = [
                    "code" => 200,
                    "message" => "password reset successful",
                    "name"=>$account->name,
                    "account" => $account->account,
                    "user_name" => $resetPassword['user_name'],
                    "password" => $resetPassword['password']
                ];

                $this->logSys->syslog(
                    "[CDN-API | CLIENT CDN] Senha do usuário ".$data['user_name']." resetada com sucesso",
                    null,
                    'INFO',
                    $this->facilityLog . ':' . basename(__FUNCTION__)
                );
            } else {
                $this->logSys->syslog(
                    "[CDN-API | CLIENT CDN] Ocorreu uma falha no reset da senha do usuário ". $data['user_name'],
                    'ERRO : ' . json_encode($resetPassword),
                    'ERROR',
                    $this->facilityLog . ':' . basename(__FUNCTION__)
                );
                $response = ["message" => "Password reset failed", "errors" => $resetPassword, "code" => 400];
            }

        } catch (Exception $e) {
            $this->logSys->syslog(
                "[CDN-API | CLIENT CDN] Ocorreu uma exceção no reset da senha do usuário " .$data['user_name'],
                'ERRO : ' . $e->getMessage(),
                'ERROR',
                $this->facilityLog . ':' . basename(__FUNCTION__)
            );
            $response = ["message" => "fatal password reset failurev", "errors" => $e->getMessage(), "code" => 400];

        }

        return $response;

    }
    public function getClient($data)
    {
        try {
            $client = $this->cdnClient->where('external_id', $data['external_id'])->first();
            if ($client) {
                $tenant = $this->cdnTenant->where('cdn_client_id', $client->id)->orderBy("id", "DESC")->first();
                $response = [
                    "id" => $client->id,
                    "account" => $client->account,
                    "tenant" => $tenant->tenant
                ];
            } else {
                //validar email
                $validade = validateEmailUser($data);
                if (!is_null($validade)) {
                    return [
                        'code' => 400,
                        'message' => 'Warning!Invalidation of the data entered.',
                        'errors' => $validade,
                    ];
                }

                $newClient = $this->create($data);

                $response = [
                    "id" => $newClient['id'],
                    "account" => $newClient['account'],
                    "main_user" => $newClient['main_user'],
                    "password" => $newClient['password']
                ];
            }
        } catch (Exception $e) {
            $this->logSys->syslog(
                '[CDN-API | CLIENT CDN] Ocorreu uma falha na consulta dos dados do cliente',
                'ERRO : ' . $e->getMessage() . '. Parâmetros de recebidos: ' . json_encode($data),
                'ERROR',
                $this->facilityLog . ':' . basename(__FUNCTION__)
            );
            $response = [
                'code' => 400,
                'message' => 'Fatal failure in customer data query. Please inform your system administrator.',
                'errors' => ['Database query failed', $e->getMessage()]
            ];
        }
        return $response;

    }
    /**
     * Método responsável por :
     * a) verificar se a conta em questão há tenant registrado, se não houver
     * exclui conta do cliente na tabela cdn_clients
     *
     * @param array $data
     *
     * @return array $response
     */
    public function delete($data)
    {
        try {
            if (isset($data['client_id'])) {
                $field = 'client_id';
                $value = $data['client_id'];
            } else if (isset($data['external_id'])) {
                $field = 'external_id';
                $value = $data['external_id'];
            }

            $client = $this->cdnClient->where($field, $value)->first();
            $hasUsers = $this->userRespository->getByClient($client);
            $hasTenants = $this->cdnTenant->where('cdn_client_id', $client->id)->count();

            if ($hasTenants > 0) {
                /*$this->logSys->auditLog(
                    $data['header'],
                    'delete account',  'INFO', 'Client Account '.$client->account.' não pode ser excluida, pois há '.$hasTenants.' registrados', $data, null
                );*/
                $response = [
                    'code' => 400,
                    'message' => 'Client has related tenant, exclusion denied .',
                    'error' => ['Client has related tenant, exclusion denied ']
                ];
            } else {
                if (!is_null($hasUsers)) {
                    foreach ($hasUsers as $hasUser) {
                        $delUser = $this->userRespository->delete($hasUser->id);
                        if (isset($delUser['code'])) {
                            /*$this->logSys->auditLog(
                                $data['header'],
                                'delete account',  'ERROR', 'Ocorreu uma falha ao excluir o usuário '.$hasUser->user_name, $data, $delUser
                            );*/
                        } else {
                           /*$this->logSys->auditLog(
                                $data['header'],
                                'delete account',  'INFO', 'Usuário '.$hasUser->user_name.' excluido com sucesso', $data, $delUser
                            );*/
                        }
                    }
                }
                $account = $client->account;
                $client->delete();
                /*$this->logSys->auditLog(
                    $data['header'],
                    'delete account',  'INFO', 'Client Account '.$account.' excluida com sucesso', $data, null
                );*/
                $response = [
                    'message' => 'Successful deletion of customer record .',
                ];
            }
        } catch (Exception $e) {
            $this->logSys->syslog(
                '[CDN-API | CLIENT CDN] Ocorreu uma falha na consulta dos dados do cliente',
                'ERRO : ' . $e->getMessage() . '. Parâmetros de recebidos: ' . json_encode($data),
                'ERROR',
                $this->facilityLog . ':' . basename(__FUNCTION__)
            );
            $response = [
                'code' => 400,
                'message' => 'Fatal failure in customer data query. Please inform your system administrator.',
                'errors' => ['Database query failed', $e->getMessage()]
            ];
            /*$this->logSys->auditLog(
                $data['header'],
                'delete account',  'INFO', 'Ocorreu uma exceção no método de exclusão do cliente', $data, $response
            );*/
        }

        return $response;

    }



}
