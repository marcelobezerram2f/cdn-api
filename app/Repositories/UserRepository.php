<?php

namespace App\Repositories;

use App\Models\User;
use App\Models\OauthClient;
use App\Services\EventLogService;
use Illuminate\Support\Facades\Hash;
use Laravel\Passport\ClientRepository as PassportClientRepository;
use Laravel\Passport\Client;
use App\Services\UserIdFromTokenService;
use Illuminate\Support\Facades\Mail;
use App\Mail\SendUserCredentials;
use App\Mail\SendUserChangePassword;
use App\Mail\SendUserConfirmChangePassword;
use Exception;



class UserRepository
{

    private $user;
    private $oauthClient;
    private $logSys;
    protected $facilityLog;
    protected $userIdFromToken;
    private $passportClientRepository;

    public function __construct()
    {
        $this->user = new User();
        $this->oauthClient = new OauthClient();
        $this->logSys = new EventLogService();
        $this->userIdFromToken = new UserIdFromTokenService();
        $this->facilityLog = basename(__FILE__);
        $this->passportClientRepository = new PassportClientRepository();
    }

    /**
     * Método responsável por retornar todos os usuario
     *
     * @param response
     */
    public function getAll($data)
    {
        try {
            $this->logSys->syslog('[CDN-API - UserRepository] Iniciando retorno de usuários.', '', 'INFO', $this->facilityLog . ':' . basename(__FUNCTION__));

            $user_logged = $this->userIdFromToken->getUserDataFromToken($data['token']);

            if (isset($user_logged) && strtolower($user_logged['user_type']) == 'admin') {
                $response = $this->user->with(['oauthClient'])->get();

                if (isset($response)) {
                    foreach ($response as $key => $user) {
                        $user['client_id'] = $user['oauthClient']['id'];
                        $user['client_secret'] = $user['oauthClient']['secret'];
                        unset($user['oauthClient']);
                    }
                    $this->logSys->syslog('[CDN-API - UserRepository] Retorno dados dos usuários efetuada com sucesso.', '', 'INFO', $this->facilityLog . ':' . basename(__FUNCTION__));
                } else {
                    $this->logSys->syslog('[CDN-API - UserRepository] Não foram encontrados registros de usuários.', '', 'ERROR', $this->facilityLog . ':' . basename(__FUNCTION__));
                    $response = [];
                }
            } else {
                $response = ['message' => 'Usuario sem permissão para retornar os dados dos usuários.', 'code' => 400];
            }
        } catch (Exception $e) {
            $error = ['Error: ' => $e->getMessage()];
            $this->logSys->syslog('[CDN-API - UserRepository] Ocorreu um erro ao retornar os usuários.', 'Ocorreu um erro ao retornar os usuários. ' . json_encode($error), 'ERROR', $this->facilityLog . ':' . basename(__FUNCTION__));

            $response = ['message' => 'Ocorreu um erro ao retornar os usuários. Contate o administrador do sistema.', 'code' => 400];
        }
        return $response;
    }


    public function resetPasswordByUserName($userName)
    {

        try {
            $user = $this->user->where("user_name", $userName)->first();
            if ($user) {
                $password = makePassword();
                $user->password = Hash::make($password);
                $user->save();
                $response =
                    [
                        "cdn_client_id" => $user->cdn_client_id,
                        "user_name" => $user->user_name,
                        "password" => $password,
                        "code" => 200
                    ];
                    $this->logSys->syslog(
                        "[CDN-API | USER CDN] Reset de senha de usuário $userName através da API efetuado com sucesso.",
                        null,
                        'ERROR',
                        $this->facilityLog . ':' . basename(__FUNCTION__)
                    );
            } else {
                $this->logSys->syslog(
                    "[CDN-API | USER CDN] Reset de senha de usuário $userName através da API  não foi efetuado, Usuário não encontrado.",
                     "Reset de senha de usuário $userName através da API  não foi efetuado, Usuário não encontrado na tabela users. Parâmetros de recebidos: $userName" ,
                    'ALERT',
                    $this->facilityLog . ':' . basename(__FUNCTION__)
                );
                $response = ["code" =>204];
            }
        } catch (Exception $e) {
            $this->logSys->syslog(
                '[CDN-API | USER CDN] Ocorreu uma exceção no reset de senha de usuário através da API',
                'ERRO : ' . $e->getMessage() . ". Parâmetros de recebidos: $userName" ,
                'ERROR',
                $this->facilityLog . ':' . basename(__FUNCTION__)
            );
            $response = ["message" => "fatal failure in password reset", "errors" =>[$e->getMessage()], "code"=>400];
        }

        return $response;

    }
    /**
     * Método responsável por retornar os dados do usuario pelo id
     *
     * @param request $request
     *
     * @param response
     */
    public function getById($data)
    {

        try {

            $this->logSys->syslog('[CDN-API - UserRepository] Iniciando retorno dados do usuário.', '', 'INFO', $this->facilityLog . ':' . basename(__FUNCTION__));

            $user_logged = $this->userIdFromToken->getUserDataFromToken($data['token']);

            if (isset($user_logged) && strtolower($user_logged['user_type']) == 'admin') {

                $response = $this->user->where('id', $data['id'])->with('oauthClient')->first();

                if (isset($response)) {
                    $response['client_id'] = $response['oauthClient']['id'];
                    $response['client_secret'] = $response['oauthClient']['secret'];
                    unset($response['oauthClient']);
                    $this->logSys->syslog('[CDN-API - UserRepository] Dados do usuário retornados com sucesso', 'Id usuario: ' . json_encode($data['id']), 'INFO', $this->facilityLog . ':' . basename(__FUNCTION__));
                } else {
                    $this->logSys->syslog('[CDN-API - UserRepository] Usuário não foi encontrado.', 'Id usuário: ' . json_encode($data['id']), 'ERROR', $this->facilityLog . ':' . basename(__FUNCTION__));
                    $response = [];
                }
            } else {
                $response = ['message' => 'Usuario sem permissão para retornar os dados do usuário.', 'code' => 400];
            }
        } catch (Exception $e) {
            $error = ['Error: ' => $e->getMessage()];
            $this->logSys->syslog('[CDN-API - UserRepository] Ocorreu um erro ao retornar os dados do usuário', 'Ocorreu um erro ao retornar os dados do usuário. ' . json_encode($error), 'ERROR', $this->facilityLog . ':' . basename(__FUNCTION__));

            $response = ['message' => 'Ocorreu um erro ao retornar os dados do usuário. Contate o administrador do sistema.', 'code' => 400];
        }
        return $response;
    }

    /**
     * Método responsável por persistir os dados do usuario
     *
     * @param request $request
     *
     * @param response
     */
    public function create($data, $dataLog)
    {
        try {

            $password = makePassword();
            $newUser = [
                'name' => $data['name'],
                'cdn_client_id' => $data['cdn_client_id'],
                'email' => $data['email'],
                'user_name' => $data['user_name'],
                'user_type' => 'client',
                'password' => Hash::make($password)
            ];
            $user = $this->user->create($newUser);
            $this->passportClientRepository->createPersonalAccessClient($user->id, $user->user_name, env('APP_URL'));

            $lang = isset($data['lang']) ? $data['lang'] : 'en';

            if (isset($data['email'])){
                Mail::to($data['email'])->send(new SendUserCredentials($data['email'], $data['name'], $data['user_name'], $password, $lang));
            }

            $response = ['password' => $password, 'code' => 200];
            /*$this->logSys->auditLog(
                $dataLog['header'],
                'create user',
                'INFO',
                'Usuário ' . $data['user_name'] . ' criado com sucesso ',
                $data,
                $response
            );*/

        } catch (Exception $e) {
            $error = ['Error: ' => $e->getMessage()];
            $this->logSys->syslog('[CDN-API - UserRepository] Ocorreu um erro ao cadastrar o usuário.', 'Ocorreu um erro ao cadastrar o usuário. Dados: ' . json_encode($data) . ' ' . json_encode($error), 'ERROR', $this->facilityLog . ':' . basename(__FUNCTION__));
            $response = ['message' => 'Fatal failure when creating the main user.', 'Error: ' => $e->getMessage(), 'code' => 400];
            /*$this->logSys->auditLog(
                $dataLog['header'],
                'create user',
                'ERROR',
                'Ocorreu uma exceção na execução do método de persistência do usuário ' . $data['user_name'] . ' no banco de dados ',
                $data,
                $response
            );*/
        }
        return $response;
    }


    /**
     * Método responsável por alterar os dados do usuario
     *
     * @param request $request
     *
     * @param response
     */
    public function update($data)
    {
        try {
            $this->logSys->syslog('[CDN-API - UserRepository] Iniciando alteração dados do usuário.', '', 'INFO', $this->facilityLog . ':' . basename(__FUNCTION__));

            $user_logged = $this->userIdFromToken->getUserDataFromToken($data['token']);

            if (isset($user_logged) && strtolower($user_logged['user_type']) == 'admin') {

                $user = $this->user->where('id', $data['id'])->where('id', $data['id'])->first();

                if (isset($user)) {
                    $old_user_email = $user->email;
                    $user->name = $data['name'];
                    $user->email = $data['email'];
                    $user->save();

                    $this->oauthClient->where('user_id', $user->id)->update(['name' => $data['email']]);

                    $response = $this->getById($data);
                    $this->logSys->syslog('[CDN-API - UserRepository] Persistência da alteração dos dados do usuário efetuada com sucesso.', 'Id usuário: ' . $data['id'] . ' Dados da persistência: ' . json_encode($data), 'INFO', $this->facilityLog . ':' . basename(__FUNCTION__));
                } else {
                    $this->logSys->syslog('[CDN-API - UserRepository] Não foi possível alterar os dados do usuário.', 'Id usuário: ' . $data['id'] . ' Dados da persistência: ' . json_encode($data), 'INFO', $this->facilityLog . ':' . basename(__FUNCTION__));
                    $response = ['message' => 'Não foi possível alterar os dados do usuário.', 'code' => 400];
                }
            } else {
                $response = ['message' => 'Usuario sem permissão para alterar dados de usuários.', 'code' => 400];
            }
        } catch (Exception $e) {
            $error = ['Error: ' => $e->getMessage()];
            $this->logSys->syslog('[CDN-API - UserRepository] Ocorreu um erro ao alterar os dados do usuário.', 'Ocorreu um erro ao alterar os dados do usuário. Dados da persistência: ' . json_encode($data) . ' ' . json_encode($error), 'ERROR', $this->facilityLog . ':' . basename(__FUNCTION__));
            $response = ['message' => 'Ocorreu um erro ao alterar os dados do usuário. Contate o administrador do sistema.', 'code' => 400];
        }
        return $response;
    }

    public function getByClient($client)
    {
        try {
            $users = $this->user->where('client_id', $client->id)->get();
            if ($users) {
                return $users;
            } else {
                return null;
            }
        } catch (Exception $e) {
            $error = ['Error: ' => $e->getMessage()];
            $this->logSys->syslog('[CDN-API - UserRepository] Ocorreu um erro na captura dos dados do usuário .', 'Ocorreu um erro na captura dos dados do usuário . Dados da consulta: ' . json_encode($client) . ' ' . json_encode($error), 'ERROR', $this->facilityLog . ':' . basename(__FUNCTION__));
            $response = ['message' => 'Ocorreu um erro ao consultar os dados do usuário. Contate o administrador do sistema.', 'code' => 400];
            return $response;
        }

    }



    /**
     * Método responsável por excluír o usuario
     *
     * @param request $request
     *
     * @param response
     */
    public function delete($data)
    {
        try {

            $this->logSys->syslog('[CDN-API - UserRepository] Iniciando excluír usuário.', '', 'INFO', $this->facilityLog . ':' . basename(__FUNCTION__));

            $user_logged = $this->userIdFromToken->getUserDataFromToken($data['token']);

            if (isset($user_logged) && strtolower($user_logged['user_type']) == 'admin') {

                $user = $this->user->where('id', $data['id'])->first();

                if (isset($user)) {

                    $exists_credentials = $this->oauthClient->where('name', $user['email'])->first();
                    if (isset($exists_credentials)) {
                        $this->oauthClient->where('name', $user['email'])->delete();
                    }

                    $response = $this->user->where('id', $data['id'])->delete();

                    $this->logSys->syslog('[CDN-API - UserRepository] O usuario foi excluído com sucesso.', 'Id usuario: ' . json_encode($data['id']), 'INFO', $this->facilityLog . ':' . basename(__FUNCTION__));
                    $response = ['message' => 'O usuario foi excluído com sucesso.'];

                } else {
                    $this->logSys->syslog('[CDN-API - UserRepository] Usuário não existe.', 'Id usuario: ' . json_encode($data['id']), 'ERROR', $this->facilityLog . ':' . basename(__FUNCTION__));
                    $response = ['message' => 'Usuário não existe.', 'code' => 400];
                }
            } else {
                $response = ['message' => 'Usuario sem permissão para excluír usuários.', 'code' => 400];
            }
        } catch (Exception $e) {
            $error = ['Error: ' => $e->getMessage()];
            $this->logSys->syslog('[CDN-API - TmUserRepository] Ocorreu um erro ao excluír o usuário.', 'Ocorreu um erro ao excluír o usuário. Id usuario: ' . json_encode($data['id']) . ' ' . json_encode($error), 'ERROR', $this->facilityLog . ':' . basename(__FUNCTION__));

            $response = ['message' => 'Ocorreu um erro ao excluír o usuário. Contate o administrador do sistema.', 'code' => 400];
        }
        return $response;
    }

    /**
     * Método responsável por confirma a alterar da senha do usuario
     *
     * @param request $request
     *
     * @param response
     */
    public function confirmChangePassword($data)
    {
        try {

            $validade = validateDataUser($data, true, false, false);
            if (!empty($validade)) {
                return [
                    'code' => 400,
                    'message' => 'Warning!Invalidation of the data entered.',
                    'errors' => $validade,
                ];
            }

            $user = $this->user->where('email',$data['email'])->first();

            if ($user){

                $lang = isset($data['lang']) ? $data['lang'] : 'ptbr';

                //Gerando codigo validador alteração senha
                $code = generateValidationCode($user->user_name, $user->password);
                $code = implode(' - ', str_split($code));

                Mail::to($user->email)
                ->send(new SendUserConfirmChangePassword($user->email, $user->name, $user->user_name, $lang, $code));
                $response = ['message' => 'Email confirmation update main user password has been sending successfully.'];

            }else{
                $response = ['message' => 'No main user was found, based on the email provided.', 'code' => 400];
            }
        } catch (Exception $e) {
            $error = ['Error: ' => $e->getMessage()];
            $this->logSys->syslog('[CDN-API - UserRepository] Ocorreu um erro ao enviar a confirmação para alterar o password do usuário.', 'Ocorreu um erro ao enviar a confirmação para alterar o password do usuário. Dados: ' . json_encode($data['email']) . ' ' . json_encode($error), 'ERROR', $this->facilityLog . ':' . basename(__FUNCTION__));
            $response = ['message' => 'Fatal failure when send email confirmation updating password the main user.', 'Error: ' => $e->getMessage(), 'code' => 400];
        }
        return $response;
    }

    /**
     * Método responsável por alterar a senha do usuario
     *
     * @param request $request
     *
     * @param response
     */
    public function changePassword($data)
    {
        try {

            $validade = validateDataUser($data, true, true, true);
            if (!empty($validade)) {
                return [
                    'code' => 400,
                    'message' => 'Warning!Invalidation of the data entered.',
                    'errors' => $validade,
                ];
            }

            $user = $this->user->where('email',$data['email'])->first();

            if ($user){

                //Gerando codigo validador alteração senha
                $code = generateValidationCode($user->user_name, $user->password);
                if ($data['validation_code'] !== $code){
                    return [
                        'code' => 400,
                        'message' => 'Warning!Invalidation of the data entered.',
                        'errors' => 'Validation code provided is not valid',
                    ];
                }

                $new_password = $data['new_password'];
                $password_hash = Hash::make($new_password);
                $user->update(['password' => $password_hash]);
                $lang = isset($data['lang']) ? $data['lang'] : 'ptbr';

                Mail::to($user->email)
                ->send(new SendUserChangePassword($user->email, $user->name, $user->user_name, $new_password, $lang));

                $response = ['message' => 'The main user password has been changed successfully.'];

            }else{
                $response = ['message' => 'No main user was found, based on the email provided.', 'code' => 400];
            }
        } catch (Exception $e) {
            $error = ['Error: ' => $e->getMessage()];
            $this->logSys->syslog('[CDN-API - UserRepository] Ocorreu um erro ao alterar o password do usuário.', 'Ocorreu um erro ao alterar o password do usuário. Dados: ' . json_encode($data['email']) . ' ' . json_encode($error), 'ERROR', $this->facilityLog . ':' . basename(__FUNCTION__));
            $response = ['message' => 'Fatal failure when updating password the main user.', 'Error: ' => $e->getMessage(), 'code' => 400];
        }
        return $response;
    }


    /**
     * Método responsável por alterar a senha do usuario logado
     *
     * @param request $request
     *
     * @param response
     */
    public function changePasswordLoggedUser($data)
    {
        try {

            $validade = validateDataUser($data, false, true, false, true, true);
            if (!empty($validade)) {
                return [
                    'code' => 400,
                    'message' => 'Warning!Invalidation of the data entered.',
                    'errors' => $validade,
                ];
            }

            $user = $this->user->where('user_name',$data['user_name'])->first();

            if ($user){

                //$user_logged = auth()->user();

                if (!Hash::check($data['current_password'], $user->password)) {
                    $response = ['message' => 'The current password entered, is not correct.', 'code' => 400];
                }else{
                    $new_password = $data['new_password'];
                    $password_hash = Hash::make($new_password);
                    $user->update(['password' => $password_hash]);
                    $lang = isset($data['lang']) ? $data['lang'] : 'ptbr';

                    if (isset($user->email)){
                        Mail::to($user->email)
                        ->send(new SendUserChangePassword($user->email, $user->name, $user->user_name, $new_password, $lang));
                    }

                    $response = ['message' => 'The main user password has been changed successfully.'];
                }

            }else{
                $response = ['message' => 'No main user was found, based on the user name provided.', 'code' => 400];
            }
        } catch (Exception $e) {
            $error = ['Error: ' => $e->getMessage()];
            $this->logSys->syslog('[CDN-API - UserRepository] Ocorreu um erro ao alterar o password do usuário logado.', 'Ocorreu um erro ao alterar o password do usuário logado. Dados: ' . json_encode($data['user_name']) . ' ' . json_encode($error), 'ERROR', $this->facilityLog . ':' . basename(__FUNCTION__));
            $response = ['message' => 'Fatal failure when updating password the main user logged.', 'Error: ' => $e->getMessage(), 'code' => 400];
        }
        return $response;
    }

    //Gerer as credenciais Client Id e Secret do usuário para autenticação Client Credentials
    public function createCredentials($userId, $userName)
    {
        try {
            $clients = new ClientRepository;

            $providers = array_keys(config('auth.providers'));

            $provider = 'users';

            $client = $clients->createPasswordGrantClient(
                $userId,
                $userName,
                env('APP_URL'),
                $provider
            );

            return $this->outputClientDetails($client);
        } catch (Exception $e) {
            $error = ['Error: ' => $e->getMessage()];
            $this->logSys->syslog('[CDN-API - UserRepository] Ocorreu um erro ao gerar as credenciais do usuário do checkpoint', 'Ocorreu um erro ao gerar as credenciais do usuário do checkpoint. Dados: ' . json_encode($error), 'ERROR', $this->facilityLog . ':' . basename(__FUNCTION__));

            $response = ['message' => 'Ocorreu um erro ao gerar as crdenciais do usuário do checkpoint. Contate o administrador do sistema.', 'code' => 400];
            return $response;
        }
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
