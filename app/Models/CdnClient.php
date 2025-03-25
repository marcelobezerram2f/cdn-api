<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CdnClient extends Model
{
    use HasFactory;


    protected $table = 'cdn_clients';
    protected $primaryKey = 'id';
    protected $fillable = [
        'external_id',
        'client_id',
        'name',
        'account',
        'document',
        'address',
        'neighborhood',
        'city',
        'state',
        'zipcode'
    ];


    public function tenants ()
    {
        return $this->hasMany(CdnTenant::class, 'cdn_client_id', 'id');
    }

    public function users()
    {
        return $this->hasMany(User::class,'cdn_client_id', 'id');
    }
    
}
