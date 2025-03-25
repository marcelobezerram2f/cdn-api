<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CdnOriginServer extends Model
{
    use HasFactory;

    protected $table = "cdn_origin_servers";
    protected $primary_key = "id";
    protected $fillable = [
        'cdn_origin_hostname',
        'cdn_origin_protocol',
        'cdn_origin_server_port',
        'cdn_origin_group_id',
        'type'

    ];


    public function originGroup()
    {
        return $this->hasOne(CdnOriginGroup::class, 'id', 'cdn_origin_group_id');
    }



}
