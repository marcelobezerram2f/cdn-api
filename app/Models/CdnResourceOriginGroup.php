<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CdnResourceOriginGroup extends Model
{
    use HasFactory;

    protected $table = 'cdn_resource_origin_groups';
    protected $primaryKey = 'id';
    protected $fillable = [
        'cdn_resource_id',
        'cdn_origin_group_id',
        'state',
    ];


    public function resource(){
        return $this->hasOne(CdnResource::class, 'id', 'cdn_resource_id');
    }

    public function originGroup() {
        return $this->hasOne(CdnOriginGroup::class,'id', 'cdn_origin_group_id');
    }
}
