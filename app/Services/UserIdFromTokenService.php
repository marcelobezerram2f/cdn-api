<?php

namespace App\Services;

use App\Models\OauthClient;
use App\Models\User;
use App\Models\OauthAccessToken;
use Lcobucci\JWT\Exception;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Token\Parser;

class UserIdFromTokenService
{

    protected $modelUser;
    protected $oauthAccessTokenModel;

    public function __construct()
    {
        $this->modelUser = new User();
        $this->oauthAccessTokenModel = new OauthAccessToken();
    }


    /**
     * Método para obter os dados do user a partir do token.
     *
     * @param $token
     * @return array
     */
    public function getUserDataFromToken($token)
    {
        try {
            $token_hash = (new Parser(new JoseEncoder()))->parse($token)->claims()
                ->all()['jti'];
            $client = $this->oauthAccessTokenModel->where('id', $token_hash)->with('oauthClient')->first();

            if (isset($client)) {
                if(is_null($client->user_id)) {
                    $client=OauthClient::find($client->client_id);
                }
                $user = $this->modelUser->find($client->user_id);
                if (!isset($user)) {
                    $response = ['message' => 'Não foi possível retornar os dados do usuario a partir do token na requisição.', 'code' => 400];
                } else {
                    $response = json_decode($user, true);
                }
            }
        } catch (Exception $e) {
            $response = ['message' => 'Ocorreu um erro ao retornar os dados do usuario a partir do token na requisição.', 'code' => 400];
        }
        return $response;
    }

}
