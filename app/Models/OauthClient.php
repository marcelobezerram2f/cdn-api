<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OauthClient extends Model
{
    use HasFactory;

    /**
     * Variáveis de parâmetros da tabela
     * Nome da tabela, chave primária e campos habilitados para persistência
     */

    protected $table = 'oauth_clients';
    protected $primaryKey = 'id';

    protected $keyType = 'string';

    protected $fillable = [
        'name',
        'client_id',
        'client_secret',
        'token'
    ];


}
