<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Laravel\Passport\Passport;
use Laravel\Passport\TokenRepository;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Token\Parser;
use App\Services\EventLogService;
use App\Repositories\UserRepository;

use Exception;

class UserAuthController extends Controller
{

    protected $eventLogService;
    protected $facility;
    private $userRespository;

    public function __construct()
    {
        $this->eventLogService = new EventLogService();
        $this->facility = basename(__FILE__);
        $this->userRespository = new UserRepository();
    }


    /**
     * Método reponsável por efetuar o login
     *
     * @param Request $request
     *
     */

        public function login(Request $request)
        {
            try{

                if(Auth::attempt(['user_name' => $request->user_name, 'password' => $request->password])){
                    $user = Auth::user();
                    $success['token'] =  $user->createToken('cdnapitoken')->accessToken;
                    $success['token_type'] = "Bearer";
                    $success['email'] = $user->email;
                    return response()->json($success, 200);
                }
                else{
                    return response()->json(['message' => 'Acesso não autorizado.'], 401);
                }

            } catch (Exception $e) {

                $response = ['message' => 'Ocorreu um erro ao fazer o login.', 'code' => 400];

                $this->eventLogService->syslog(
                    '[UserAuthController] Ocorreu um erro ao fazer o login.',
                    'UserAuthController Error: ' . json_encode($e->getMessage()),
                    'ERROR',
                    $this->facility
                );
            }
    }

    /**
    * Método reponsável por efetuar o logout
    *
    * @param Request $request
    *
    */

    public function logout(Request $request)
    {
        $bearerToken = $request->bearerToken();
        //Encoded do token bearer para obter o id e buscar na tabela oauth_access_tokens
        $token_hash = (new Parser(new JoseEncoder()))->parse($bearerToken)->claims()
            ->all()['jti'];

        if (isset($token_hash)){

            $token = Passport::token()->where('id', $token_hash)
                             ->where('revoked', false)
                             ->first();

            $tokenRepository = app(TokenRepository::class);

            $logout = $tokenRepository->revokeAccessToken($token->id);

            return $logout;

        }else{
            return response()->json(['message' => 'Não foi possível o logout.'], 400);
        }
    }


    /**
    * Método reponsável por confirmar se foi o usuario quem solicito alterar a senha
    *
    * @param Request $request
    *
    */

    public function confirmChangePassword(Request $request)
    {
        $data = $request->all();
        $response = $this->userRespository->confirmChangePassword($data);
        if (isset($response['code'])) {
            $code = $response['code'];
        } else {
            $code = 200;
        }
        return response($response, $code);
    }

    /**
    * Método reponsável por alterar a senha do usuario
    *
    * @param Request $request
    *
    */

    public function changePassword(Request $request)
    {
        $data = $request->all();
        $response = $this->userRespository->changePassword($data);
        if (isset($response['code'])) {
            $code = $response['code'];
        } else {
            $code = 200;
        }
        return response($response, $code);
    }

    /**
    * Método reponsável por alterar a senha do usuario logado
    *
    * @param Request $request
    *
    */

    public function changePasswordLoggedUser(Request $request)
    {
        $data = $request->all();
        $response = $this->userRespository->changePasswordLoggedUser($data);
        if (isset($response['code'])) {
            $code = $response['code'];
        } else {
            $code = 200;
        }
        return response($response, $code);
    }


}
