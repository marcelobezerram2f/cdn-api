<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CdnProvisioningStep extends Model
{
    use HasFactory;

    protected $table = 'cdn_provisioning_steps';
    protected $primaryKey = 'id';
    protected $fillable = [
        'cdn_resource_id',
        'cdn_tenant_id',
        'step',
        'step_description',
        'status',
        'observation'
    ];
}
