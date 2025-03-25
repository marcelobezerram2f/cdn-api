<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CdnTenant extends Model
{
    use HasFactory;

    protected $table = 'cdn_tenants';
    protected $primaryKey = 'id';
    protected $fillable = [
        'tenant',
        'api_key',
        'cdn_target_group_id',
        'cdn_client_id',
        'description',
        'attempt_create',
        'attempt_delete',
        'queued'
    ];

    public function cdnResources()
    {
        return $this->hasMany(CdnResource::class, 'cdn_tenant_id', 'id');
    }

    public function client() {
        return $this->hasOne(CdnClient::class, 'id', 'cdn_client_id');
    }

    public function step() {
        return $this->hasOne(CdnProvisioningStep::class, 'cdn_tenant_id', 'id');
    }

    public function targetGroup()
    {
        return $this->hasOne(CdnTargetGroup::class, 'id', 'cdn_target_group_id');
    }
}
