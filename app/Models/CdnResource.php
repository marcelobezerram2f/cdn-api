<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CdnResource extends Model
{
    use HasFactory;


    protected $table = 'cdn_resources';
    protected $primaryKey = 'id';
    protected $fillable = [
       'request_code',
       'cdn_resource_hostname',
       'cdn_origin_hostname',
       'cdn_origin_protocol',
       'cdn_origin_server_port',
       'cdn_ingest_point_id',
       'cdn_target_group_id',
       'cdn_template_id',
       'cdn_tenant_id',
       'cdn_cname_id',
       'description',
       'cdn_acme_lets_encrypt_id',
       'cdn_resource_block_id',
       'cname_verify',
       'cname_ssl_verify',
       'provisioned',
       'storage_id',
       'marked_deletion',
       'attempt_create',
       'attempt_delete'
    ];

    public function tenant()
    {
        return $this->hasOne(CdnTenant::class, 'id', 'cdn_tenant_id');
    }

    public function resourceOriginGroup()
    {
        return $this->hasMany(CdnResourceOriginGroup::class, 'cdn_resource_id', 'id');
    }

    public function cname()
    {
        return $this->hasOne(CdnCname::class, 'id', 'cdn_cname_id');
    }

    public function template(){
        return $this->hasOne(CdnTemplate::class, 'id', 'cdn_template_id');
    }

    public function ingestPoint(){
        return $this->hasOne(CdnIngestPoint::class, 'id', 'cdn_ingest_point_id');
    }

    public function targetGroup(){
        return $this->hasOne(CdnTargetGroup::class, 'id', 'cdn_target_group_id');
    }

    public function provisioningStep()
    {
        return $this->hasMany(CdnProvisioningStep::class, 'cdn_resource_id', 'id');
    }

    public function cdnResourceBlock()
    {
        return  $this->hasOne(CdnResourceBlock::class, 'cdn_resource_id', 'id');
    }

    public function letsEncryptAcmeRegister()
    {
        return $this->hasOne(CdnLetsencryptAcmeRegister::class, "id", "cdn_acme_lets_encrypt_id");
    }

    public function headers(){
        return $this->hasMany(CdnHeader::class, 'cdn_resource_id', 'id');
    }
}
