<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CdnHeader extends Model
{
    use HasFactory;

    protected $table = 'cdn_headers';
    protected $primaryKey = 'id';
    protected $fillable = [
        'cdn_resource_id',
        'name',
        'value',
    ];


    public function resouce() {

        return $this->hasOne(CdnResource::class, 'id', 'cdn_resource_id');
    }

}
