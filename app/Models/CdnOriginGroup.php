<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CdnOriginGroup extends Model
{
    use HasFactory;


    protected $table = 'cdn_origin_groups';
    protected $primaryKey = 'id';
    protected $fillable = [
        'group_name',
        'group_description',
        'cdn_tenant_id',
        'type',
    ];

    public function tenant()
    {
        return $this->hasOne(CdnTenant::class, 'id', 'cdn_tenant_id');
    }

    public function resource()
    {
        return $this->hasOne(CdnResource::class, 'id', 'cdn_resource_id');
    }

    public function originServers()
    {
        return $this->hasMany(CdnOriginServer::class, 'cdn_origin_group_id', 'id');
    }
}
