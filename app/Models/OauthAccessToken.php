<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OauthAccessToken extends Model
{
    use HasFactory;

    /**
     * Variáveis de parâmetros da tabela
     * Nome da tabela, chave primária e campos habilitados para persistência
     */

    protected $table =  'oauth_access_tokens';
    protected $primaryKey  = 'id';

    protected $keyType = 'string';

    public function oauthClient()
    {
        return $this->hasOne(OauthClient::class, 'id', 'client_id');
    }

}
